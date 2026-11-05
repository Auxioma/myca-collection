<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);
namespace Magento\PaymentServicesBase\Model\App;

use Magento\Framework\App\ProductMetadataInterface;

/**
 * Utility class to determine the version of Magento Open Source/Adobe Commerce.
 */
class ProductVersionResolver
{
    /**
     * Used internally to differentiate not-yet-cached versions (null) from cached unknown versions.
     */
    private const UNKNOWN_VERSION = "UNKNOWN_VERSION";

    /**
     * Cached product version. Null if not cached yet.
     *
     * @var string|null
     */
    private ?string $version = null;

    /**
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(readonly ProductMetadataInterface $productMetadata) {
    }

    /**
     * Attempts to resolve the version of Magento Open Source/Adobe Commerce.
     *
     * @return string|null Version in format "2.4.X" (e.g., "2.4.7") or null if unable to resolve
     */
    public function getVersion(): ?string
    {
        if (!$this->version) {
            $this->version = $this->parsePrettyComposerVersion($this->productMetadata->getVersion())
                ?? $this->deduceVersionFromFrameworkClassesInClasspath()
                ?? ProductVersionResolver::UNKNOWN_VERSION;
        }
        return $this->version === ProductVersionResolver::UNKNOWN_VERSION
            ? null : $this->version;
    }

    /**
     * Parse a composer-style pretty version text (e.g., "2.4.7-p7") string into format "2.4.X".
     *
     * @param string $versionText Raw version string from product metadata
     * @return string|null Version in 2.4.X format or null if version unknown
     */
    private function parsePrettyComposerVersion(string $versionText): ?string
    {
        // Extract 2.4.N from version text, e.g., "2.4.7-p7" or "dev-2.4.7-develop" -> "2.4.7".
        return preg_match('/2\.4\.[0-9]+/', $versionText, $matches) ? $matches[0] : null;
    }

    /**
     * Deduce product version by checking for framework classes introduced in specific versions.
     *
     * @return string|null Version in "2.4.X" format or null if too old
     */
    private function deduceVersionFromFrameworkClassesInClasspath(): ?string
    {
        /* NOTE to maintainers
         * ~~~
         *   The following code uses the existence or absence of classes in the \Magento\Framework
         *   package to determine the version of Magento Open Source/Adobe Commerce. For the sake of
         *   this note, let's call these classes "discriminator classes".
         *
         *   For these classes to be effective discriminators, discriminator classes should have
         *   been introduced "as a new feature" in some new Magento Open Source/Adobe Commerce
         *   version that is not a X.Y.Z-pN version. For example:
         *
         *     Class introduced in 2.4.7: good discriminator.
         *     Class introduced in 2.4.7-p1: bad discriminator.
         *
         *   To find a discriminator class for some version, pull magento and change dirs to:
         *   https://github.com/magento/magento2/tree/2.4-develop/lib/internal/Magento/Framework.
         *
         *   Then, compare the files from the latest -pN version prior to the version you want to
         *   detect with the files from the version you want to detect. For example, if you want
         *   to find a discriminator class for 2.4.8, diff the files between 2.4.7-p8 to 2.4.8 and
         *   take note. All added classes are good candidates for discriminator classes.
         *
         *   Discriminator classes would lose effectiveness if backported. For example, if you
         *   choose class Foo as discriminator for 2.4.8, but then it's backported to 2.4.7-p9,
         *   then we would incorrectly detect 2.4.7-p9 as >= 2.4.8. Good news is: backports are
         *   exceptionally rare. As of today, February 2026, only one such backport exists, namely,
         *   \Magento\Framework\Filter\Input\PurifierInterface and its implementation \Purifier,
         *   both introduced in 2.4.5 and backported to 2.4.4-p5.
         *
         *   As the names suggest, the \Purifier input filter is related to input sanitation, and
         *   thus related to security (AC-1498). To reduce the risk of choosing a discriminator
         *   class that will later be backported: avoid security-related classes.
         */

        // phpcs:disable Magento2.PHP.LiteralNamespaces, Generic.Files.LineLength
        if (class_exists("\\Magento\\Framework\\GraphQl\\Query\\QueryDataFormatter")) {
            // Present in https://github.com/magento/magento2/tree/2.4.9-alpha3/lib/internal/Magento/Framework/GraphQl/Query.
            // Absent in https://github.com/magento/magento2/tree/2.4.8-p3/lib/internal/Magento/Framework/GraphQl/Query.
            return "2.4.9";
        }
        if (class_exists("\\Magento\\Framework\\App\\Utility\\IPAddress")) {
            // Present in https://github.com/magento/magento2/tree/2.4.8/lib/internal/Magento/Framework/App/Utility.
            // Absent in https://github.com/magento/magento2/tree/2.4.7-p8/lib/internal/Magento/Framework/App/Utility.
            return "2.4.8";
        }
        if (class_exists("\\Magento\\Framework\\Stdlib\\Cookie\\PhpCookieDisabler")) {
            // Present in https://github.com/magento/magento2/tree/2.4.7/lib/internal/Magento/Framework/Stdlib/Cookie.
            // Absent in https://github.com/magento/magento2/tree/2.4.6-p13/lib/internal/Magento/Framework/Stdlib/Cookie.
            return "2.4.7";
        }
        if (class_exists("\\Magento\\Framework\\Validator\\Hostname")) {
            // Present in https://github.com/magento/magento2/tree/2.4.6/lib/internal/Magento/Framework/Validator.
            // Absent in https://github.com/magento/magento2/tree/2.4.5-p14/lib/internal/Magento/Framework/Validator.
            return "2.4.6";
        }
        if (class_exists("\\Magento\\Framework\\Locale\\LocaleFormatter")) {
            // Present in https://github.com/magento/magento2/tree/2.4.5/lib/internal/Magento/Framework/Locale.
            // Absent in https://github.com/magento/magento2/tree/2.4.4-p13/lib/internal/Magento/Framework/Locale.
            return "2.4.5";
        }
        if (class_exists("\\Magento\\Framework\\App\\Utility\\ReflectionClassFactory")) {
            // Present in https://github.com/magento/magento2/tree/2.4.4/lib/internal/Magento/Framework/App/Utility.
            // Absent in https://github.com/magento/magento2/tree/2.4.3-p3/lib/internal/Magento/Framework/App/Utility.
            return "2.4.4";
        }
        // phpcs:enable Magento2.PHP.LiteralNamespaces, Generic.Files.LineLength
        // Dinosaur version (<2.4.4)
        return null;
    }
}
