<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Commander;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Mode extends AbstractJson implements HttpPostActionInterface
{
    protected function apply(): ?string
    {
        $this->service->setMode((string) $this->request->getParam('mode'));

        return null;
    }
}
