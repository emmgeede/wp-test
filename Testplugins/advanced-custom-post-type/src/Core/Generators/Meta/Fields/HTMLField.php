<?php

namespace ACPT\Core\Generators\Meta\Fields;

use ACPT\Core\Generators\Meta\AfterAndBeforeFieldGenerator;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;

class HTMLField extends AbstractField
{
	public function render()
	{
		$this->enqueueAssets();
        $allowDangerousContent = $this->getAdvancedOption('allow_dangerous_content') ?? false;

		if($this->isChild() or $this->isNestedInABlock()){
			$id = "html_".Strings::generateRandomId();
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[type]" value="'.MetaFieldModel::HTML_TYPE.'">';
			$field .= '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[original_name]" value="'.$this->metaField->getName().'">';
			$html = '<textarea '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'[value]" class="acpt-admin-meta-field-input acpt-codemirror" rows="8" '.$this->appendDataValidateAndLogicAttributes().'>'.Strings::esc_attr($this->getDefaultValue())
			          .'</textarea>';
		} else {
		    $id = Strings::esc_attr($this->getIdName());
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'_type" value="'.MetaFieldModel::HTML_TYPE.'">';
			$html = '<textarea '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'" class="acpt-form-control acpt-codemirror" rows="8" '.$this->appendDataValidateAndLogicAttributes().'>'.Strings::esc_attr($this->getDefaultValue()).'</textarea>';
		}

		$field .= (new AfterAndBeforeFieldGenerator())->generate($this->metaField, $html);

		if($allowDangerousContent){
            $field .= "<p class='acpt-evaluate-code-wrapper'><a data-target-id='".$id."' class='acpt-evaluate-code' href='#'>Evaluate code</a><span class='acpt-evaluate-code-outcome'></span></p>";
        } else {
            $field .= "<p>Dangerous code (PHP and Javascript) is OFF.</p>";
        }

		return $this->renderField($field);
	}

	public function enqueueAssets()
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