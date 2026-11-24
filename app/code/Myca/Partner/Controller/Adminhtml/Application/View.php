<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Application;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

class View extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::applications';

    /**
     * Consultation seule, ouverte depuis le lien de l'e-mail « nouvelle candidature » : la clé secrète d'URL ne peut
     * pas figurer dans un e-mail. La connexion et le droit « candidatures » restent exigés ; les décisions (POST)
     * gardent la clé de formulaire.
     *
     * @var string[]
     */
    protected $_publicActions = ['view'];

    public function execute(): Page
    {
        return $this->page('Myca_Partner::applications', (string) __('Candidature partenaire'));
    }
}
