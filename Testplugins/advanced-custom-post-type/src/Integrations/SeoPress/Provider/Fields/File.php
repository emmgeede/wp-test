<?php

namespace ACPT\Integrations\SeoPress\Provider\Fields;

use ACPT\Utils\Wordpress\WPAttachment;

class File extends Base
{
    /**
     * @return string|null
     */
    public function getValue()
    {
        $label = $this->value['label'];
        $file = $this->value['file'];

        if(!empty($label)){
            return $this->before . $label . $this->after;
        }

        if($file instanceof WPAttachment){
            return $this->before . $file . $this->after;
        }

        return "";
    }
}

