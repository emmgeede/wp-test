<?php

namespace ACPT\Integrations\SeoPress\Provider\Fields;

use ACPT\Utils\PHP\Arrays;

class DateRange extends Base
{
    /**
     * @return string|null
     */
    public function getValue()
    {
        if(!isset($this->value['value'])){
            return '';
        }

        $rawValue = $this->value['value'];

        if(is_array($rawValue) and Arrays::count($rawValue) === 2){
            $value = $rawValue[0]. " - " . $rawValue[1];

            return wp_strip_all_tags($this->after . $value . $this->after);
        }

        return '';
    }
}
