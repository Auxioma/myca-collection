<?php

declare(strict_types=1);

namespace Myca\Shipping\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

/**
 * Liste déroulante d'une colonne de tableau de configuration (pays, oui/non).
 */
class OptionSelect extends Select
{
    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    public function setInputId(string $value): self
    {
        return $this->setData('id', $value);
    }
}
