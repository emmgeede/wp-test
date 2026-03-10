<?php

namespace ACPT\Core\Shortcodes\ACPT;

use ACPT\Constants\MetaTypes;
use ACPT\Integrations\WooCommerce\ACPT_WooCommerce;

class WooCommerceProductAttributeShortcode extends AbstractACPTShortcode
{
    /**
     * @inheritDoc
     */
    public function render( $atts )
    {
        if(!ACPT_WooCommerce::active()){
            return null;
        }

        $productAttr = $atts['attr'];
        $box = $atts['box'];
        $field = $atts['field'];

        if(!$productAttr or !$box or !$field){
            return '';
        }

        $elementId = "wc_attribute_" . $box . '_' . $field . "_" . $productAttr;

        return $this->renderShortcode($elementId, MetaTypes::OPTION_PAGE, $elementId, $atts);
    }
}