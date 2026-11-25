<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Dropship\Model\PartnerOrderService;
use Psr\Log\LoggerInterface;

/**
 * Étape 4 : validation et paiement. Redirige vers la page du prestataire de paiement s'il en fournit une, sinon
 * vers « Mes commandes ».
 */
class Valider implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ManagerInterface $messageManager,
        private readonly PartnerOrderService $service,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        try {
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Votre session a expiré : rechargez la page.'));
            }
            ['order' => $order, 'redirect_url' => $paymentUrl] = $this->service->place(
                (string) $this->request->getParam('payment_method'),
                (bool) $this->request->getParam('terms')
            );
            if ($paymentUrl !== '') {
                return $redirect->setUrl($paymentUrl);
            }
            $this->messageManager->addSuccessMessage((string) __(
                'Commande n° %1 enregistrée. Elle sera préparée dès la réception de votre paiement ; suivez-la dans « Mes commandes ».',
                $order->getIncrementId()
            ));

            return $redirect->setPath('partenaire/commandes');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Myca_Dropship validation M17 : ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage((string) __('La commande n’a pas pu être créée. Réessayez ou contactez le Service Partenaire.'));
        }

        return $redirect->setPath('partenaire/commander', ['_fragment' => 'validation']);
    }
}
