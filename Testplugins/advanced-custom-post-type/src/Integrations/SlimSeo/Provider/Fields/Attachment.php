<?php

namespace ACPT\Integrations\SlimSeo\Provider\Fields;

use ACPT\Utils\Wordpress\WPAttachment;

class Attachment extends Base
{
    /**
     * @return string|null
     */
    public function getValue()
    {
        if($this->value instanceof WPAttachment){
            return $this->before . $this->value . $this->after;
        }

        if(is_array($this->value)){

            $value = [];

            foreach ($this->value as $attachment){
                if($attachment instanceof WPAttachment){
                    $value[] = $this->before . $attachment . $this->after;
                }
            }

            return implode(", ", $value);
        }

        return '';
    }
}
