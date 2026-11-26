<?php

declare(strict_types=1);

namespace Myca\Core\Model;

use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Journal d'audit des opérations sensibles (cahier des charges : « consigner les modifications sensibles dans un
 * journal horodaté », « anciennes/nouvelles valeurs pour stocks, tarifs, contrats et statuts sensibles »).
 * Ajout seul : rien dans l'application ne modifie une entrée ; les valeurs secrètes sont masquées.
 * Une écriture qui échoue est journalisée sans jamais bloquer l'opération métier.
 */
class AuditLogger
{
    private const SECRET_KEYS = '/pass|secret|token|_sk$|_key$|api_key|iban|bic|card|cvc|hash/i';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Json $json,
        private readonly State $appState,
        private readonly ObjectManagerInterface $objectManager,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function log(
        string $action,
        string $entityType,
        string|int|null $entityId,
        ?array $old = null,
        ?array $new = null,
        ?string $comment = null
    ): void {
        try {
            [$actorType, $actorId, $actorName] = $this->actor();
            $this->resource->getConnection()->insert($this->resource->getTableName('myca_audit_log'), [
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_name' => $actorName !== null ? mb_substr($actorName, 0, 255) : null,
                'ip' => $actorType === 'system' ? null : ((string) $this->remoteAddress->getRemoteAddress() ?: null),
                'action' => mb_substr($action, 0, 64),
                'entity_type' => mb_substr($entityType, 0, 64),
                'entity_id' => $entityId !== null ? mb_substr((string) $entityId, 0, 128) : null,
                'old_values' => $old !== null ? $this->json->serialize($this->mask($old)) : null,
                'new_values' => $new !== null ? $this->json->serialize($this->mask($new)) : null,
                'comment' => $comment !== null ? mb_substr($comment, 0, 512) : null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Myca_Core : écriture du journal d\'audit impossible : ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function mask(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEYS, $key)) {
                $values[$key] = $value === null || $value === '' ? $value : '******';
            } elseif (is_array($value)) {
                $values[$key] = $this->mask($value);
            }
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     */
    private function actor(): array
    {
        try {
            $area = $this->appState->getAreaCode();
        } catch (\Exception) {
            $area = null;
        }
        if ($area === Area::AREA_ADMINHTML) {
            $user = $this->objectManager->get(AdminSession::class)->getUser();
            if ($user !== null) {
                return ['admin', (int) $user->getId(), (string) $user->getUserName()];
            }
        }
        if ($area === Area::AREA_FRONTEND) {
            $session = $this->objectManager->get(CustomerSession::class);
            if ($session->isLoggedIn()) {
                return ['customer', (int) $session->getCustomerId(), (string) $session->getCustomer()->getEmail()];
            }
        }

        return ['system', null, $area === null ? 'cli' : $area];
    }
}
