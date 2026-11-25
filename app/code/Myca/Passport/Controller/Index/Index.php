<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;

/**
 * /passeport sans identifiant : page de présentation du passeport (page CMS « passeport-du-vetement »,
 * l'adresse « passeport » étant réservée aux QR codes).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(): Redirect
    {
        return $this->redirectFactory->create()
            ->setUrl($this->urlBuilder->getUrl(null, ['_direct' => 'passeport-du-vetement']))
            ->setHttpResponseCode(301);
    }
}
