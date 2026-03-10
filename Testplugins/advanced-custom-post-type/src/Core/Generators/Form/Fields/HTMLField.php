<?php

namespace ACPT\Core\Generators\Form\Fields;

use ACPT\Core\Generators\Meta\AfterAndBeforeFieldGenerator;
use ACPT\Core\Helper\Strings;

class HTMLField extends AbstractField
{
	/**
	 * @inheritDoc
	 */
	public function render()
	{
        $allowDangerousContent = false;
		$defaultValue = Strings::htmlspecialchars($this->defaultValue());
		$rows = (!empty($this->fieldModel->getExtra()['rows'])) ? $this->fieldModel->getExtra()['rows'] : 6;
		$cols = (!empty($this->fieldModel->getExtra()['cols'])) ? $this->fieldModel->getExtra()['cols'] : 30;

		if($this->fieldModel->getMetaField() !== null){
            $allowDangerousContent = $this->fieldModel->getMetaField()->getAdvancedOption('allow_dangerous_content') ?? false;
        }

		$field = "
			<textarea
			    ".$this->disabled()."
				id='".Strings::esc_attr($this->getIdName())."'
				name='".Strings::esc_attr($this->getIdName())."'
				placeholder='".$this->placeholder()."'
				class='acpt-codemirror ".Strings::esc_attr($this->cssClass())."'
				rows='".$rows."'
				cols='".$cols."'
			>".$defaultValue."</textarea>";

		if($this->fieldModel->getMetaField() !== null){
            if($allowDangerousContent){
                $field .= "<p class='acpt-evaluate-code-wrapper'><a data-target-id='".Strings::esc_attr($this->getIdName())."' class='acpt-evaluate-code' href='#'>Evaluate code</a><span class='acpt-evaluate-code-outcome'></span></p>";
            } else {
                $field .= "<p>Dangerous code (PHP and Javascript) is OFF.</p>";
            }

			return (new AfterAndBeforeFieldGenerator())->generate($this->fieldModel->getMetaField(), $field);
		}

		return $field;
	}

	public function enqueueFieldAssets()
	{
		wp_register_style( 'codemirror-css', plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/codemirror5.min.css'), [], "5.65.16" );
		wp_enqueue_style( 'codemirror-css' );

		wp_register_script('codemirror-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/codemirror5.min.js') );
		wp_enqueue_script('codemirror-js');

        wp_register_script('codemirror-matchbrackets-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/addon/edit/matchbrackets.js') );
        wp_enqueue_script('codemirror-matchbrackets-js');

        wp_register_script('codemirror-htmlmixed-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/htmlmixed/htmlmixed.min.js') );
        wp_enqueue_script('codemirror-htmlmixed-js');

        wp_register_script('codemirror-xml-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/xml/xml.js') );
        wp_enqueue_script('codemirror-xml-js');

        wp_register_script('codemirror-javascript-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/javascript/javascript.js') );
        wp_enqueue_script('codemirror-javascript-js');

        wp_register_script('codemirror-css-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/css/css.js') );
        wp_enqueue_script('codemirror-css-js');

        wp_register_script('codemirror-clike-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/clike/clike.js') );
        wp_enqueue_script('codemirror-clike-js');

        wp_register_script('codemirror-php-js',  plugins_url( 'advanced-custom-post-type/assets/vendor/codemirror/mode/php/php.js') );
        wp_enqueue_script('codemirror-php-js');
	}
}
