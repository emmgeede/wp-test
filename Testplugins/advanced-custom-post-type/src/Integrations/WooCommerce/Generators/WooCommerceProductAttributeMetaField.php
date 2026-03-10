<?php

namespace ACPT\Integrations\WooCommerce\Generators;

use ACPT\Constants\MetaTypes;
use ACPT\Core\Generators\Meta\Fields\AbstractField;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Utils\Data\Meta;

class WooCommerceProductAttributeMetaField
{
    /**
     * @var MetaFieldModel
     */
    private MetaFieldModel $metaField;
    private                $productAttributeSlug;
    private                $productAttributeId;

    /**
     * WooCommerceProductAttributeMetaField constructor.
     *
     * @param MetaFieldModel $metaField
     * @param                $productAttributeId
     * @param                $productAttributeName
     */
    public function __construct( MetaFieldModel $metaField, $productAttributeId, $productAttributeName)
    {
        $this->metaField = $metaField;
        $this->productAttributeSlug = $productAttributeName;
        $this->productAttributeId = $productAttributeId;
    }

    /**
     * @return string
     */
    public function render()
    {
        $elementId = "wc_attribute_" . Strings::toDBFormat( $this->metaField->getBox()->getName() ) . '_' . Strings::toDBFormat($this->metaField->getName() ) . "_" . $this->productAttributeId;
        $value = Meta::fetch($elementId, MetaTypes::OPTION_PAGE, $elementId);

        $className = 'ACPT\\Core\\Generators\\Meta\\Fields\\'. $this->metaField->getType().'Field';

        if(!empty($value) and is_serialized($value)){
            $value = unserialize($value);
        }

        if(class_exists($className)){
            /** @var AbstractField $instance */
            $instance = new $className( $this->metaField, 'wc_product_attribute', $this->productAttributeSlug);
            $instance->setNoShowLabel(true);

            if(!empty($value)){
                $instance->setValue($value);
            }

            $render = '<tr class="form-field">';
            $render .= '<th scope="row" valign="top">';
            $render .= '<label for="my-field">'.$this->metaField->getLabelOrName().'</label>';
            $render .= '</th>';
            $render .= '<td>';
            $render .= $instance->render();
            $render .= '</td>';
            $render .= '</tr>';

            return $render;
        }

        return null;
    }
}