<?php

declare(strict_types=1);

namespace Myca\Shipping\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\DataObject;

/**
 * Tableau « Destinations desservies » : pays, point relais oui/non, domicile oui/non.
 */
class Destinations extends AbstractFieldArray
{
    /** @var array<string, OptionSelect> */
    private array $renderers = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Country $countrySource,
        private readonly Yesno $yesno,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _prepareToRender(): void
    {
        $this->addColumn('country_id', ['label' => __('Pays'), 'renderer' => $this->getRenderer('country_id')]);
        $this->addColumn('relay', ['label' => __('Point relais'), 'renderer' => $this->getRenderer('relay')]);
        $this->addColumn('home', ['label' => __('Domicile'), 'renderer' => $this->getRenderer('home')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Ajouter un pays');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        foreach (['country_id', 'relay', 'home'] as $column) {
            $value = $row->getData($column);
            if ($value !== null) {
                $options['option_' . $this->getRenderer($column)->calcOptionHash((string) $value)] = 'selected="selected"';
            }
        }
        $row->setData('option_extra_attrs', $options);
    }

    private function getRenderer(string $column): OptionSelect
    {
        if (!isset($this->renderers[$column])) {
            /** @var OptionSelect $renderer */
            $renderer = $this->getLayout()->createBlock(OptionSelect::class, '', ['data' => ['is_render_to_js_template' => true]]);
            $renderer->setOptions($column === 'country_id'
                ? $this->countrySource->toOptionArray(true)
                : $this->yesno->toOptionArray());
            $this->renderers[$column] = $renderer;
        }

        return $this->renderers[$column];
    }
}
