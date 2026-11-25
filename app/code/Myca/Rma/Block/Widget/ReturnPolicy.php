<?php

declare(strict_types=1);

namespace Myca\Rma\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Myca\Rma\Model\Config;
use Myca\Rma\Model\ReturnPolicy as Policy;

/**
 * Widget « MYCA – Délais de retour » : les délais réellement appliqués par le portail de retour (rétractation légale,
 * politique de 30 jours seulement si elle est activée, délai SAV), pour que la page Retours ne les contredise jamais.
 */
class ReturnPolicy extends Template implements BlockInterface
{
    /** @var string */
    protected $_template = 'Myca_Rma::widget/return-policy.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        private readonly Policy $policy,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isCommercial30DaysEnabled(): bool
    {
        return $this->config->isCommercial30DaysEnabled();
    }

    public function getReturnDays(): int
    {
        return $this->policy->getPolicyDays(Policy::TYPE_RETURN, $this->isCommercial30DaysEnabled(), $this->getWarrantyDays());
    }

    public function getWarrantyDays(): int
    {
        return $this->config->getWarrantyDays();
    }

    /**
     * « 2 ans » plutôt que « 730 jours » lorsque le délai est un nombre entier d'années.
     */
    public function getWarrantyLabel(): string
    {
        $days = $this->getWarrantyDays();
        if ($days >= 365 && $days % 365 === 0) {
            $years = intdiv($days, 365);

            return (string) ($years === 1 ? __('1 an') : __('%1 ans', $years));
        }

        return (string) __('%1 jours', $days);
    }
}
