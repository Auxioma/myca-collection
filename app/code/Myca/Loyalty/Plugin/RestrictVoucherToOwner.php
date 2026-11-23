<?php

declare(strict_types=1);

namespace Myca\Loyalty\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Utility;

/**
 * Un bon fidélité ne s'applique qu'au panier de son titulaire connecté : un code transmis à un tiers est refusé.
 */
class RestrictVoucherToOwner
{
    /** @var array<int, int|null> titulaire par règle (null : pas un bon fidélité) */
    private array $owners = [];

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function afterCanProcessRule(Utility $subject, bool $result, Rule $rule, Address $address): bool
    {
        if (!$result || !$rule->getCouponCode()) {
            return $result;
        }
        $owner = $this->getOwner((int) $rule->getId());

        return $owner === null || (int) $address->getQuote()->getCustomerId() === $owner;
    }

    private function getOwner(int $ruleId): ?int
    {
        if (!array_key_exists($ruleId, $this->owners)) {
            $connection = $this->resource->getConnection();
            $owner = $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('myca_loyalty_voucher'), ['customer_id'])
                    ->where('rule_id = ?', $ruleId)
            );
            $this->owners[$ruleId] = is_numeric($owner) ? (int) $owner : null;
        }

        return $this->owners[$ruleId];
    }
}
