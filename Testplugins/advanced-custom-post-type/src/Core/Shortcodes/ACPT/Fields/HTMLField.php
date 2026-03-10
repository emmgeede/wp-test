<?php

namespace ACPT\Core\Shortcodes\ACPT\Fields;

use ACPT\Utils\PHP\PHPEval\PhpEval;

class HTMLField extends AbstractField
{
    public function render()
    {
        if(!$this->isFieldVisible()){
            return null;
        }

	    $rawData = $this->fetchRawData();

	    if(!isset($rawData['value'])){
		    return null;
	    }

        $rawData['value'] = html_entity_decode($rawData['value']);

        return $this->addBeforeAndAfter(PhpEval::getInstance()->evaluate($rawData['value']));
    }
}