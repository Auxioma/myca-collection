<?php

declare(strict_types=1);

namespace Myca\Dropship\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Paliers de remise de l'achat de stock : à partir de N pièces du même modèle, X % de remise.
 */
class StockTiers extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('min_qty', ['label' => __('À partir de (pièces du modèle)'), 'class' => 'required-entry validate-digits']);
        $this->addColumn('discount', ['label' => __('Remise (%)'), 'class' => 'required-entry validate-number']);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Ajouter un palier');
    }
}
