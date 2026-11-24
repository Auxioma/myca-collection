<?php

declare(strict_types=1);

namespace Myca\Passport\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Adresse courte imprimée dans le QR : /passeport/IDENTIFIANT → page publique du passeport.
 */
class Router implements RouterInterface
{
    public function __construct(private readonly ActionFactory $actionFactory)
    {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        if (!preg_match('#^passeport/([0-9A-Za-z]{16,32})$#', $path, $match)) {
            return null;
        }
        // Chemin interne réécrit : au tour suivant, le routeur standard sert la page et celui-ci ne reconnaît plus rien.
        $request->setPathInfo('/passeport/view/index');
        $request->setParam('token', $match[1]);

        return $this->actionFactory->create(Forward::class);
    }
}
