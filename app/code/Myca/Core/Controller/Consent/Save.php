<?php

declare(strict_types=1);

namespace Myca\Core\Controller\Consent;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Core\Model\CookieConsent;

/**
 * Enregistre la preuve d'un choix de cookies (le choix lui-même est appliqué par le navigateur).
 */
class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CookieConsent $cookieConsent,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(400)->setData(['saved' => false]);
        }
        try {
            /** @var array<string, mixed> $params */
            $params = $this->request->getParams();
            $this->cookieConsent->record($this->cookieConsent->normalize($params), (int) $this->storeManager->getStore()->getId());
        } catch (LocalizedException) {
            return $result->setHttpResponseCode(400)->setData(['saved' => false]);
        }

        return $result->setData(['saved' => true]);
    }
}
