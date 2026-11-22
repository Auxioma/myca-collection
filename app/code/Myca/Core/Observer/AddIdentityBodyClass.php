<?php

declare(strict_types=1);

namespace Myca\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Myca\Core\Model\Identity;

class AddIdentityBodyClass implements ObserverInterface
{
    public function __construct(
        private readonly PageConfig $pageConfig,
        private readonly Identity $identity
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->pageConfig->addBodyClass('myca-' . $this->identity->get());
    }
}
