<?php

namespace ACPT\Utils\Wordpress\Email;

use ACPT\Utils\Wordpress\Email\Template\AbstractEmailTemplate;
use ACPT\Utils\Wordpress\Email\Template\BlankEmailTemplate;

class EmailTemplateFactory
{
    /**
     * @param string $template
     * @return AbstractEmailTemplate
     */
    public static function create(?string $template = null): AbstractEmailTemplate
    {
        $class = 'ACPT\\Utils\\Wordpress\\Email\\Template\\'.ucfirst($template).'EmailTemplate';

        if(class_exists($class)){
            return new $class;
        }

        return new BlankEmailTemplate();
    }
}
