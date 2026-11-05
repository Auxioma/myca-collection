<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Block;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\PaymentServicesPaypal\Model\Config;

/**
 * @api
 * @deprecated 2.17.0 PayPal messages are now rendered by the Payment Services SDK.
 * @see SmartButtons
 */
class Message extends Template implements ShortcutInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var array
     */
    private $componentConfig;

    /**
     * @var string
     */
    private $pageType;

    /**
     * @param Context $context
     * @param Config $config
     * @param Session $session
     * @param string $pageType
     * @param array $componentConfig
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $session,
        string $pageType = 'minicart',
        array $componentConfig = [],
        array $data = []
    ) {
        $this->config = $config;
        $this->componentConfig = $componentConfig;
        $this->pageType = $pageType;
        parent::__construct($context, $data);

        $template = $data['template'] ?? $componentConfig[$pageType]['template'] ?? null;
        if ($template) {
            $this->setTemplate($template);
        }
    }

    /**
     * @inheritDoc
     */
    public function getAlias() : string
    {
        return 'magpaypayments_message';
    }

    /**
     * Get deprecated component parameters.
     *
     * @return array[]
     */
    public function getComponentParams() : array
    {
        return [
            'styles' => $this->config->getPayLaterStyling(),
            'placement' => $this->componentConfig[$this->pageType]['placement'] ?? '',
            'renderContainer' => $this->componentConfig[$this->pageType]['renderContainer'] ?? ''
        ];
    }

    /**
     * The standalone message component is no longer rendered.
     *
     * @param string $location
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isEnabled(string $location) : bool
    {
        return false;
    }
}
