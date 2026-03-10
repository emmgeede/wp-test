<?php

namespace ACPT\Core\Shortcodes\ACPT\Fields;

class ToggleField extends AbstractField
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

        $width = ($this->payload->width !== null) ? str_replace('px', '', $this->payload->width) : 36;

        if($this->payload->preview){
                $width = 24;
        }

        return $this->addBeforeAndAfter('<span class="acpt-toggle-placeholder" style="font-size: '.$width.'px;">'.$this->renderIcon($rawData['value']).'</span>');
    }

	/**
	 * @param $value
	 *
	 * @return string
	 */
    private function renderIcon($value)
    {
	    if($value === "1") {
		    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--bx" width="1em" height="1em" preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24" data-icon="bx:bx-check-circle" style="color: rgb(2, 195, 154);"><path fill="currentColor" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10s10-4.486 10-10S17.514 2 12 2m0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8s8 3.589 8 8s-3.589 8-8 8"></path><path fill="currentColor" d="M9.999 13.587L7.7 11.292l-1.412 1.416l3.713 3.705l6.706-6.706l-1.414-1.414z"></path></svg>';
	    }

	    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--bx" width="1em" height="1em" preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24" data-icon="bx:bx-x-circle" style="color: rgb(249, 65, 68);"><path fill="currentColor" d="M9.172 16.242L12 13.414l2.828 2.828l1.414-1.414L13.414 12l2.828-2.828l-1.414-1.414L12 10.586L9.172 7.758L7.758 9.172L10.586 12l-2.828 2.828z"></path><path fill="currentColor" d="M12 22c5.514 0 10-4.486 10-10S17.514 2 12 2S2 6.486 2 12s4.486 10 10 10m0-18c4.411 0 8 3.589 8 8s-3.589 8-8 8s-8-3.589-8-8s3.589-8 8-8"></path></svg>';
    }
}