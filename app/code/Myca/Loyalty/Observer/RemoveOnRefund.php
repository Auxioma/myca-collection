<?php

declare(strict_types=1);

namespace Myca\Loyalty\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Myca\Loyalty\Model\LoyaltyService;

/**
 * Tout avoir (retour Myca_Rma ou remboursement saisi dans l'administration) retire les points correspondants.
 * L'écriture se fait dans la transaction de l'avoir : si l'avoir échoue, aucun point n'est retiré.
 */
class RemoveOnRefund implements ObserverInterface
{
    public function __construct(private readonly LoyaltyService $loyaltyService)
    {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if ($creditmemo instanceof Creditmemo) {
            $this->loyaltyService->applyCreditmemo($creditmemo);
        }
    }
}
