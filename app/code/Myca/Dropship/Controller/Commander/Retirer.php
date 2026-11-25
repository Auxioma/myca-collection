<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Retirer extends AbstractJson implements HttpPostActionInterface
{
    protected function apply(): ?string
    {
        $this->service->removeModel((int) $this->request->getParam('product_id'), (int) $this->request->getParam('color_id'));

        return null;
    }
}
