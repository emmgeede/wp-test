<?php

namespace ACPT\Core\Generators\Meta\Fields;

use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Utils\Wordpress\Translator;

class VideoField extends AbstractField
{
	public function render()
	{
        $minSize = $this->getAdvancedOption('min_size') ? $this->getAdvancedOption('min_size') : null;
        $maxSize = $this->getAdvancedOption('max_size') ? $this->getAdvancedOption('max_size') : null;
		$attachmentId = (isset($this->getAttachments()[0])) ? $this->getAttachments()[0]->getId() : '';
        $preview = (!empty($this->getDefaultValue())) ? $this->getPreviewVideo() : '<span class="placeholder">'.Translator::translate("No video selected").'</span>';

        $field = '<div class="file-upload-wrapper">';

		if($this->isChild() or $this->isNestedInABlock()){
			$id = "video_".Strings::generateRandomId();
			$field .= '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[type]" value="'.MetaFieldModel::VIDEO_TYPE.'">';
			$field .= '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[original_name]" value="'.$this->metaField->getName().'">';
            $field .= '<div class="image-preview">'. $preview .'</div>';
			$field .= '<div class="btn-wrapper">';
			$field .= '<input id="'.$id.'[attachment_id]['.$this->getIndex().']" type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[attachment_id]" value="' .$attachmentId.'">';
			$field .= '<input readonly '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'[value]" type="text" class="hidden" value="' .Strings::esc_attr($this->getDefaultValue()) .'" '. $this->appendDataValidateAndLogicAttributes() .'>';
		} else {
			$id = Strings::esc_attr($this->getIdName());
			$field .= '<input type="hidden" name="'. $id.'_type" value="'.MetaFieldModel::VIDEO_TYPE.'">';
            $field .= '<div class="image-preview"><div class="image">'. $preview .'</div></div>';
			$field .= '<div class="btn-wrapper">';
			$field .= '<input id="'.$id.'_attachment_id" name="'. esc_html($this->getIdName()).'_attachment_id" type="hidden" value="' .$attachmentId.'">';
			$field .= '<input readonly '.$this->required().' id="'.$id.'" name="'. Strings::esc_attr($this->getIdName()).'" type="text" class="hidden" value="' .Strings::esc_attr($this->getDefaultValue()) .'" '. $this->appendDataValidateAndLogicAttributes() .'>';
		}

		$field .= '<a data-max-size="'.$maxSize.'" data-min-size="'.$minSize.'" class="upload-video-btn button button-primary">'.Translator::translate("Upload").'</a>';
		$field .= '<a data-target-id="'.$id.'" class="upload-delete-btn delete-video-btn button button-secondary">'.Translator::translate("Delete").'</a>';

		$field .= '</div>';
		$field .= '</div>';

		return $this->renderField($field);
	}

	/**
	 * @return string
	 */
	private function getPreviewVideo()
	{
		return '<div class="image"><video controls>
              <source src="'.esc_url($this->getDefaultValue()).'" type="video/mp4">
            Your browser does not support the video tag.
            </video></div>';
	}
}
