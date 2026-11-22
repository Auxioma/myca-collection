<?php

declare(strict_types=1);

namespace Myca\Catalog\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;

/**
 * Résumé des variantes affiché sous la carte produit : pastilles de couleur et liste des tailles
 * (« S · M · L · XL · XXL »), comme sur les maquettes P01, P03 et P04.
 */
class ProductVariants implements ArgumentInterface
{
    public const COLOR_ATTRIBUTE = 'myca_color';
    public const SIZE_ATTRIBUTE = 'myca_size';

    public function __construct(
        private readonly SwatchHelper $swatchHelper
    ) {
    }

    /**
     * @return array<int, array{label: string, hex: string}>
     */
    public function getColors(Product $product): array
    {
        $options = $this->getOptions($product, self::COLOR_ATTRIBUTE);
        if (!$options) {
            return [];
        }

        $swatches = $this->swatchHelper->getSwatchesByOptionsId(array_keys($options));
        $colors = [];
        foreach ($options as $optionId => $label) {
            $colors[] = ['label' => $label, 'hex' => (string) ($swatches[$optionId]['value'] ?? '')];
        }

        return $colors;
    }

    /**
     * @return string[]
     */
    public function getSizes(Product $product): array
    {
        return array_values($this->getOptions($product, self::SIZE_ATTRIBUTE));
    }

    /**
     * @return array<int, string> identifiant d'option => libellé
     */
    private function getOptions(Product $product, string $attributeCode): array
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            $value = $product->getData($attributeCode);
            $label = $value ? $product->getAttributeText($attributeCode) : '';

            return is_string($label) && $label !== '' ? [(int) $value => $label] : [];
        }

        /** @var Configurable $type */
        $type = $product->getTypeInstance();
        foreach ($type->getConfigurableAttributesAsArray($product) as $attribute) {
            if ($attribute['attribute_code'] !== $attributeCode) {
                continue;
            }
            $options = [];
            foreach ($attribute['values'] as $value) {
                $options[(int) $value['value_index']] = (string) ($value['store_label'] ?? $value['label']);
            }

            return $options;
        }

        return [];
    }
}
