<?php

declare(strict_types=1);

namespace Myca\Partner\Plugin;

use Magento\Customer\Controller\Account\Index as AccountIndex;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Myca\Partner\Model\CurrentPartner;

/**
 * Le tableau de bord d'un partenaire actif est M16, pas celui du client B2C (M28).
 */
class RedirectPartnerToPartnerSpace
{
    public function __construct(
        private readonly CurrentPartner $currentPartner,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute(AccountIndex $subject, callable $proceed): ResultInterface
    {
        if ($this->currentPartner->getActive() !== null) {
            return $this->redirectFactory->create()->setPath('partenaire');
        }

        return $proceed();
    }
}
