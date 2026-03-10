<?php

namespace ACPT\Core\Generators\Meta\Fields;

use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Utils\Wordpress\Translator;
use ACPT\Utils\Wordpress\WPAttachment;

class ImageField extends AbstractField
{
	public function render()
	{
		$attachmentId = (isset($this->getAttachments()[0])) ? $this->getAttachments()[0]->getId() : '';
        $preview = $this->preview();

        $accepts = ($this->getAdvancedOption('accepts') and is_array($this->getAdvancedOption('accepts'))) ? implode(", ", $this->getAdvancedOption('accepts')) : "image";
        $maxSize = $this->getAdvancedOption('max_size') ?? null;
        $minSize = $this->getAdvancedOption('min_size') ?? null;
        $minWidth = $this->getAdvancedOption('min_width') ?? null;
        $minHeight = $this->getAdvancedOption('min_height') ?? null;
        $maxWidth = $this->getAdvancedOption('max_width') ?? null;
        $maxHeight = $this->getAdvancedOption('max_height') ?? null;

		if($this->isChild() or $this->isNestedInABlock()){
			$id = "image_".Strings::generateRandomId();
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[type]" value="'.MetaFieldModel::IMAGE_TYPE.'">';
			$field .= '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[original_name]" value="'.$this->metaField->getName().'">';
		} else {
			$id = Strings::esc_attr($this->getIdName());
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'_type" value="'.MetaFieldModel::IMAGE_TYPE.'">';
		}

        $field .= '<div class="file-upload-wrapper">';
        $field .= '<div class="image-preview"><div class="image">'. $preview .'</div></div>';
		$field .= '<div class="btn-wrapper">';

		if($this->isChild() or $this->isNestedInABlock()){
			$field .= '<input id="'.$id.'[attachment_id]['.$this->getIndex().']" name="'. esc_html($this->getIdName()).'[attachment_id]" type="hidden" value="' .$attachmentId.'">';
			$field .= '<input readonly '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'[value]" type="text" class="hidden" value="' .Strings::esc_attr($this->getDefaultValue()) .'" '.$this->appendDataValidateAndLogicAttributes().'>';
		} else {
			$field .= '<input id="'.$id.'_attachment_id" name="'. esc_html($this->getIdName()).'_attachment_id" type="hidden" value="' .$attachmentId.'">';
			$field .= '<input readonly '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'" type="text" class="hidden" value="' .Strings::esc_attr($this->getDefaultValue()) .'" '.$this->appendDataValidateAndLogicAttributes().'>';
		}

		$field .= '<a  
            href="#"
            data-accepts="'.$accepts.'"
            data-max-size="'.$maxSize.'"
            data-min-size="'.$minSize.'"
            data-min-width="'.$minWidth.'"
            data-max-width="'.$maxWidth.'"
            data-min-height="'.$minHeight.'"
            data-max-height="'.$maxHeight.'"
            class="upload-image-btn button button-primary">
                '.Translator::translate("Upload").'
            </a>';

		if(!empty($this->getDefaultValue())){
            $field .= '<button data-target-id="'.$id.'" class="upload-delete-btn button button-secondary">'.Translator::translate("Delete").'</button>';
        }

		$field .= '</div>';
		$field .= '</div>';


		return $this->renderField($field);
	}

    /**
     * @return string
     */
    private function preview()
    {
        if(!empty($this->getDefaultValue()) and is_string($this->getDefaultValue())){

            $attachment = WPAttachment::fromUrl($this->getDefaultValue());

            return $attachment->render();
        }

        return '<span class="placeholder">'.Translator::translate("No image selected").'</span>';
    }
}