<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Ajouter extends AbstractJson implements HttpPostActionInterface
{
    protected function apply(): ?string
    {
        $sizes = $this->request->getParam('sizes');
        $this->service->addModel(
            (int) $this->request->getParam('product_id'),
            (int) $this->request->getParam('color_id'),
            is_array($sizes) ? $sizes : []
        );

        return (string) __('Ajouté à la commande.');
    }
}
