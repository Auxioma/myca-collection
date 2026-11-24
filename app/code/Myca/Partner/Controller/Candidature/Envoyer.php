<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Candidature;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Myca\Partner\Model\PartnerManagement;

/**
 * Enregistre une candidature P17. Clé de formulaire vérifiée par Magento ; champ piège contre les robots ; en cas
 * d'erreur, la saisie est conservée pour ne pas tout retaper.
 */
class Envoyer implements HttpPostActionInterface
{
    public const SESSION_KEY = 'myca_partner_application_form';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly PartnerManagement $partnerManagement,
        private readonly ManagerInterface $messageManager,
        private readonly DataPersistorInterface $session
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create()->setPath('devenir-partenaire');
        $data = (array) $this->request->getParam('application', []);
        if (trim((string) $this->request->getParam('company_website')) !== '') {
            return $redirect;
        }
        try {
            $this->partnerManagement->submitApplication($data);
            $this->session->clear(self::SESSION_KEY);
            $this->messageManager->addSuccessMessage(__(
                'Merci ! Votre candidature est enregistrée. Le Service Partenaire B2B vérifie vos informations et vous recontacte avant l’activation de votre compte.'
            ));
        } catch (LocalizedException $e) {
            $this->session->set(self::SESSION_KEY, $data);
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('devenir-partenaire', ['_fragment' => 'candidature']);
        }

        return $redirect;
    }
}
