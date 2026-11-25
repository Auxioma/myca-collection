<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Myca\Dropship\Model\PartnerOrderService;
use Psr\Log\LoggerInterface;

/**
 * Actions de l'écran M17 : jeton de formulaire obligatoire, réponse JSON avec l'état à jour de la commande.
 * Les droits (boutique active du compte connecté) sont contrôlés par le service.
 */
abstract class AbstractJson
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly JsonFactory $jsonFactory,
        protected readonly FormKeyValidator $formKeyValidator,
        protected readonly PartnerOrderService $service,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws LocalizedException
     */
    abstract protected function apply(): ?string;

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Votre session a expiré : rechargez la page.'));
            }
            $message = $this->apply();

            return $result->setData(['ok' => true, 'message' => $message, 'state' => $this->service->getState()]);
        } catch (LocalizedException $e) {
            $state = null;
            try {
                $state = $this->service->getState();
            } catch (LocalizedException) {
                $state = null;
            }

            return $result->setHttpResponseCode(422)->setData(['ok' => false, 'message' => $e->getMessage(), 'state' => $state]);
        } catch (\Throwable $e) {
            $this->logger->error('Myca_Dropship M17 : ' . $e->getMessage(), ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData(['ok' => false, 'message' => (string) __('Une erreur est survenue. Réessayez ou contactez le Service Partenaire.')]);
        }
    }
}
