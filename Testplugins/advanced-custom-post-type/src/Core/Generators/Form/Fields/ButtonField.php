<?php

namespace ACPT\Core\Generators\Form\Fields;

use ACPT\Core\Helper\Strings;

class ButtonField extends AbstractField
{
	/**
	 * @inheritDoc
	 */
	public function render()
	{
		$label = (!empty($this->fieldModel->getExtra()['label'])) ? Strings::esc_attr($this->fieldModel->getExtra()['label']) : 'Button';
		$css = (!empty($this->fieldModel->getSettings()['css'])) ? $this->fieldModel->getSettings()['css'] : 'acpt-form-button';
		$type = (!empty($this->fieldModel->getExtra()['type'])) ? $this->fieldModel->getExtra()['type'] : 'submit';
		$action = (!empty($this->fieldModel->getExtra()['action'])) ? $this->fieldModel->getExtra()['action'] : null;
		$dataAttr = 'data-acpt-'.$type . ' data-acpt-action="'.$action.'"';

		return "
			<button
			    ".$this->disabled()."
				id='".Strings::esc_attr($this->getIdName())."'
				type='".$type."'
				class='".$css."'
				".$dataAttr."
			>
				".trim($label)."
			</button>
		";
	}

	public function enqueueFieldAssets() {
		// TODO: Implement enqueueFieldAssets() method.
	}
}
