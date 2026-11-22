<?php

declare(strict_types=1);

namespace Myca\Shipping\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Barème « Livraison à domicile » : pays (code ISO ou « * »), tranche de poids en kg, prix TTC.
 */
class HomeRates extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('country_id', ['label' => __('Pays (FR, BE… ou *)'), 'class' => 'required-entry']);
        $this->addColumn('weight_from', ['label' => __('Poids de (kg)'), 'class' => 'validate-number']);
        $this->addColumn('weight_to', ['label' => __('Poids à (kg, 0 = sans limite)'), 'class' => 'validate-number']);
        $this->addColumn('price', ['label' => __('Prix TTC (€)'), 'class' => 'required-entry validate-number']);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Ajouter une tranche');
    }
}
