<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Livraison extends AbstractJson implements HttpPostActionInterface
{
    protected function apply(): ?string
    {
        $recipient = $this->request->getParam('recipient');
        $declared = trim(str_replace([' ', ','], ['', '.'], (string) $this->request->getParam('declared_sale_amount')));
        $this->service->saveDelivery(is_array($recipient) ? $recipient : [], is_numeric($declared) ? (float) $declared : null);

        return (string) __('Livraison enregistrée.');
    }
}
