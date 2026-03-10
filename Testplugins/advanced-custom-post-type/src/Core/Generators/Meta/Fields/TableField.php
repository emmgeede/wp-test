<?php

namespace ACPT\Core\Generators\Meta\Fields;

use ACPT\Core\Generators\Meta\TableFieldGenerator;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Utils\Wordpress\Translator;

class TableField extends AbstractField
{
	/**
	 * @inheritDoc
	 */
	public function render()
	{
		$this->enqueueAssets();
		$buttonLabel = $this->hasNoValue() ? "Create table" : "Edit table settings";
        $hasGoogleSheetsKey = !empty(TableFieldGenerator::googleSheetsKey());

		if($this->isChild() or $this->isNestedInABlock()){
			$id = "table_".Strings::generateRandomId();
			$dataTargetId = Strings::esc_attr($this->getIdName()).'[value]';
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[type]" value="'.MetaFieldModel::TABLE_TYPE.'">';
			$field .= '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'[original_name]" value="'.$this->metaField->getName().'">';
			$field .= '<input '.$this->required().' name="'. Strings::esc_attr($this->getIdName()).'[value]" type="hidden" value="' .Strings::esc_attr(Strings::escapeForJSON($this->getDefaultValue())) .'">';
		} else {
			$id = Strings::esc_attr($this->getIdName());
			$dataTargetId = $id;
			$field = '<input type="hidden" name="'. Strings::esc_attr($this->getIdName()).'_type" value="'.MetaFieldModel::TABLE_TYPE.'">';
			$field .= '<input '.$this->required().' name="'. Strings::esc_attr($this->getIdName()).'" type="hidden" value="' .Strings::esc_attr(Strings::escapeForJSON($this->getDefaultValue())) .'">';
		}

		$field .= $this->saveTemplateModal($id);
		$field .= $this->importCSVModal($id);
		$field .= $this->importJSONModal($id);
		$field .= $this->importTemplateModal($id);
		$field .= $this->createTableModal($id);

        if($hasGoogleSheetsKey){
            $field .= $this->importSheetModal($id);
        }

		$field .= '<div class="acpt-tabulator" id="'.$id.'" data-target-id="'. $dataTargetId .'" style="margin-bottom: 10px;">';

		if($this->hasNoValue()){
			$field .= '<p class="update-nag notice notice-warning" style="margin: 0;">'.Translator::translate("No table already created.").'</p>';
		} else {
			$field .= Translator::translate("Loading...");
		}

		$field .= '</div>';

		$field .= '<div class="btn-wrapper" style="margin-top: 20px;">';
		$field .= '<a class="acpt-modal-link acpt-open-table-settings button button-primary" href="#acpt-create-table-'.$id.'" rel="modal:open" >'.Translator::translate($buttonLabel).'</a>';

		if(!$this->hasNoValue()){
			$field .= '<a class="acpt-modal-link acpt-open-save-template button button-secondary" href="#acpt-save-template-'.$id.'" rel="modal:open" >'.Translator::translate("Save as template").'</a>';
		}

        $field .= $this->importButtons($id, $hasGoogleSheetsKey);
		$field .= '<a data-target-id="'.$id.'" class="acpt-clear-table button button-danger" href="#">'.Translator::translate("Clear").'</a>';
		$field .= '</div>';

		$field .= '<div class="outcome">';
		$field .= '</div>';

		return $this->renderField($field);
	}

    /**
     * @param $id
     * @param $hasGoogleSheetsKey
     * @return string
     */
    private function importButtons($id, $hasGoogleSheetsKey = false)
    {
        $button = '<div class="acpt_flexible">';
        $button .= '<div class="acpt_add_flexible_block">';
        $button .= '<div>';
        $button .= '<button class="button acpt_add_flexible_btn">';
        $button .= '<span class="acpt_add_flexible_btn_label">'.Translator::translate('Import').'</span>';
        $button .= '<span class="acpt_add_flexible_btn_icon">
					<svg viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="components-panel__arrow" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>
					</span>';
        $button .= '</button>';
        $button .= '<ul class="acpt_flexible_block_items_no_callback">';
        $button .= '<li><a class="acpt-open-import-csv" href="#acpt-import-csv-'.$id.'" rel="modal:open" >'.Translator::translate("Import CSV").'</a></li>';
        $button .= '<li><a class="acpt-open-import-json" href="#acpt-import-json-'.$id.'" rel="modal:open" >'.Translator::translate("Import JSON").'</a></li>';

        if($hasGoogleSheetsKey){
            $button .= '<li><a class="acpt-import-google-sheet" href="#acpt-import-sheet-'.$id.'" rel="modal:open" >'.Translator::translate("Import Google Sheet").'</a></li>';
        }

        $button .= '<li><a class="acpt-open-import-template" href="#acpt-import-template-'.$id.'" rel="modal:open" >'.Translator::translate("Import template").'</a></li>';
        $button .= '</ul>';
        $button .= '</div>';
        $button .= '</div>';
        $button .= '</div>';

        return $button;
    }

	/**
	 * @param $id
	 *
	 * @return string
	 */
	private function saveTemplateModal($id)
	{
		$modal = '<div id="acpt-save-template-'.$id.'" class="modal">';
		$modal .= '<h3>'.Translator::translate("Save this table as template").'</h3>';
		$modal .= '<div class="errors" style="color: #b02828;"></div>';
		$modal .= '<div class="acpt-admin-meta-label" style="margin-bottom: 10px; width: 100%">';
		$modal .= '<label for="acpt-save-template-name-'.$id.'">'.Translator::translate("Name").'</label>';
		$modal .= '<input style="width: 100%" id="acpt-save-template-name-'.$id.'" class="regular-text" type="text"/>';
		$modal .= '</div>';

		$modal .= '<div class="acpt-flex gap-10">';
		$modal .= '<a href="#" class="acpt-save-template-name button button-primary disabled"  data-target-id="'.$id.'">'.Translator::translate("Save").'</a>';
		$modal .= '<a href="#" rel="modal:close" class="acpt-modal-link button button-danger">'.Translator::translate("Close").'</a>';
		$modal .= '</div>';

		$modal .= '</div>';

		return $modal;
	}

    /**
     * @param $id
     * @return string
     */
    private function importSheetModal($id)
    {
        $modal = '<div id="acpt-import-sheet-'.$id.'" class="modal">';
        $modal .= '<h3>'.Translator::translate("Import Google Sheet").'</h3>';
        $modal .= '<div class="errors" style="color: #b02828;"></div>';
        $modal .= '<div class="acpt-import-sheet-button-wrapper">';
        $modal .= '<div style="width:100%; margin-bottom: var(--acpt-spacing);" class="acpt-admin-meta-label"><label for="acpt-import-sheet-'.$id.'" class="">'.Translator::translate("Google Sheet ID").'</label>';
        $modal .= '<input style="width:100%" id="acpt-import-sheet-id-'.$id.'"  class="acpt-import-sheet regular-text" type="text"/></div>';
        $modal .= '<div style="width:100%" class="acpt-admin-meta-label"><label for="acpt-import-sheet-sheet-'.$id.'" class="">'.Translator::translate("Select sheet").'</label>';
        $modal .= '<select style="width:100%; max-width: 100%; margin-bottom: var(--acpt-spacing);" id="acpt-import-sheet-sheet-'.$id.'" disabled class="acpt-import-sheet-sheet regular-text"></select></div>';
        $modal .= '<div style="width:100%" class="acpt-admin-meta-label"><label for="acpt-import-sheet-format-'.$id.'" class="">'.Translator::translate("Format").'</label>';
        $modal .= '<select style="width:100%; max-width: 100%; margin-bottom: var(--acpt-spacing);" id="acpt-import-sheet-format-'.$id.'" disabled class="acpt-import-sheet-format regular-text"></select></div>';
        $modal .= '<button class="acpt-import-sheet button button-primary" disabled  id="acpt-import-sheet-submit-'.$id.'">'.Translator::translate("Import").'</button>';
        $modal .= '<div class="acpt-import-sheet-outcome" id="acpt-import-sheet-outcome-'.$id.'"></div>';
        $modal .= '</div>';
        $modal .= '</div>';

        return $modal;
    }

    /**
     * @param $id
     *
     * @return string
     */
    private function importJSONModal($id)
    {
        $modal = '<div id="acpt-import-json-'.$id.'" class="modal">';
        $modal .= '<h3>'.Translator::translate("Import JSON").'</h3>';
        $modal .= '<div class="errors" style="color: #b02828;"></div>';
        $modal .= '<div class="acpt-import-json-button-wrapper">';
        $modal .= '<div style="width:100%"><label for="acpt-import-json-button-'.$id.'" class="button button-primary acpt-import-json-button">'.Translator::translate("Upload your file").'</label></div>';
        $modal .= '<input id="acpt-import-json-button-'.$id.'" class="acpt-import-json-file" type="file" accept=".json, application/json" />';
        $modal .= '<div class="acpt-import-json-outcome"></div>';
        $modal .= '</div>';
        $modal .= '</div>';

        return $modal;
    }

    /**
     * @param $id
     *
     * @return string
     */
	private function importCSVModal($id)
    {
        $modal = '<div id="acpt-import-csv-'.$id.'" class="modal">';
        $modal .= '<h3>'.Translator::translate("Import CSV").'</h3>';
        $modal .= '<div class="errors" style="color: #b02828;"></div>';
        $modal .= '<div class="acpt-import-csv-button-wrapper">';
        $modal .= '<div style="width:100%"><label for="acpt-import-csv-button-'.$id.'" class="button button-primary acpt-import-csv-button">'.Translator::translate("Upload your file").'</label></div>';
        $modal .= '<input id="acpt-import-csv-button-'.$id.'" class="acpt-import-csv-file" type="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" />';
        $modal .= '<div class="acpt-import-csv-outcome"></div>';
        $modal .= '</div>';
        $modal .= '</div>';

        return $modal;
    }

	/**
	 * @param $id
	 *
	 * @return string
	 */
	private function importTemplateModal($id)
	{
		$modal = '<div id="acpt-import-template-'.$id.'" class="modal">';
		$modal .= '<h3>'.Translator::translate("Import template").'</h3>';
		$modal .= '<div class="errors" style="color: #b02828;"></div>';
		$modal .= '<div class="acpt-admin-meta-label" style="margin-bottom: 10px; width: 100%">';
		$modal .= '<p>'.Translator::translate("Choose the template").':</p>';
		$modal .= '<ul id="acpt-import-template-name-'.$id.'" class="acpt-table-templates">';
		$modal .= '<li>Loading...</li>';
		$modal .= '</ul>';
		$modal .= '</div>';
		$modal .= '</div>';

		return $modal;
	}

	/**
	 * @param $id
	 *
	 * @return string
	 */
	private function createTableModal($id)
	{
		$modal = '<div id="acpt-create-table-'.$id.'" class="modal">';
		$modal .= '<h3>'.Translator::translate("Create new table").'</h3>';

		// rows and cols
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 50%">';
		$modal .= '<label for="acpt-create-table-columns-'.$id.'">'.Translator::translate("Columns").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-columns-'.$id.'" class="regular-text" value="2" type="number" min="1" step="1"/>';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 50%">';
		$modal .= '<label for="acpt-create-table-rows-'.$id.'">'.Translator::translate("Rows").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-rows-'.$id.'" class="regular-text" value="2" type="number" min="1" step="1" />';
		$modal .= '</div>';
		$modal .= '</div>';

		// layout, alignment
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 50%">';
		$modal .= '<label for="acpt-create-table-layout-'.$id.'">'.Translator::translate("Layout").'</label>';
		$modal .= '<select class="regular-text" id="acpt-create-table-layout-'.$id.'" style="width: 100%"><option value="horizontal">horizontal</option><option value="vertical">vertical</option></select>';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 50%">';
		$modal .= '<label for="acpt-create-table-alignment-'.$id.'">'.Translator::translate("Alignment").'</label>';
		$modal .= '<select class="regular-text" id="acpt-create-table-alignment-'.$id.'" style="width: 100%"><option value="left">left</option><option value="center">center</option><option value="right">right</option></select>';
		$modal .= '</div>';
		$modal .= '</div>';

		// border
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-border-style-'.$id.'">'.Translator::translate("Border style").'</label>';
		$modal .= '<select class="regular-text" id="acpt-create-table-border-style-'.$id.'" style="width: 100%">
				<option value="solid">Solid</option>
				<option value="dotted">Dotted</option>
				<option value="dashed">Dashed</option>
				<option value="double">Double</option>
				<option value="groove">Groove</option>
				<option value="ridge">Ridge</option>
				<option value="inset">Inset</option>
				<option value="outset">Outset</option>
				<option value="none">None</option>
				<option value="hidden">Hidden</option>
			</select>';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-border-thickness-'.$id.'">'.Translator::translate("Border weight").' (px)</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-border-thickness-'.$id.'" class="regular-text" value="1" type="number" min="1" step="1" />';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-border-color-'.$id.'">'.Translator::translate("Border color").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-border-color-'.$id.'" class="acpt-color-picker regular-text" value="#cccccc" type="text" />';
		$modal .= '</div>';
		$modal .= '</div>';

		// colors
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-color-'.$id.'">'.Translator::translate("Text color").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-color-'.$id.'" class="acpt-color-picker regular-text" value="#777777" type="text" />';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-background-color-'.$id.'">'.Translator::translate("Main background").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-background-color-'.$id.'" class="acpt-color-picker regular-text" value="#ffffff" type="text" />';
		$modal .= '</div>';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 33%">';
		$modal .= '<label for="acpt-create-table-zebra-background-'.$id.'">'.Translator::translate("Alt background").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-zebra-background-'.$id.'" class="acpt-color-picker regular-text" value="#ffffff" type="text" />';
		$modal .= '</div>';
		$modal .= '</div>';

		// CSS
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-admin-meta-label" style="width: 100%">';
		$modal .= '<label for="acpt-create-table-css-'.$id.'">'.Translator::translate("CSS classes").'</label>';
		$modal .= '<input novalidate style="width: 100%" id="acpt-create-table-css-'.$id.'" class="regular-text" type="text" />';
		$modal .= '</div>';
		$modal .= '</div>';

		// header and footer
		$modal .= '<div class="acpt-flex gap-10" style="margin-bottom: 10px;">';
		$modal .= '<div class="acpt-flex gap-5"><input style="margin: 0" type="checkbox" value="1" checked id="acpt-create-table-header-'.$id.'" /> <label for="acpt-create-table-header-'.$id.'">'.Translator::translate("Add header").'</label></div>';
		$modal .= '<div class="acpt-flex gap-5"><input style="margin: 0" type="checkbox" value="1" id="acpt-create-table-footer-'.$id.'" /> <label for="acpt-create-table-footer-'.$id.'">'.Translator::translate("Add footer").'</label></div>';
		$modal .='</div>';

		// buttons
		$modal .= '<a href="#" rel="modal:close" class="acpt-modal-link acpt-create-table button button-primary" data-target-id="'.$id.'">'.Translator::translate("Create").'</a>';
		$modal .= '</div>';

		return $modal;
	}

	/**
	 * @return bool
	 */
	private function hasNoValue()
	{
		return (empty($this->getDefaultValue()) or $this->getDefaultValue() == "{}");
	}

	/**
	 * Enqueue assets
	 */
	private function enqueueAssets()
	{
		wp_enqueue_script( 'jquery.modal-js', plugins_url( 'advanced-custom-post-type/assets/vendor/jquery.modal/jquery.modal.min.js'), [], '3.1.0', true);
		wp_enqueue_style( 'jquery.modal-css', plugins_url( 'advanced-custom-post-type/assets/vendor/jquery.modal/jquery.modal.min.css'), [], '3.1.0', 'all');
		wp_enqueue_script( 'sortable-js', plugins_url( 'advanced-custom-post-type/assets/vendor/sortablejs/sortablejs.min.js'), [], '3.1.0', true);
		wp_enqueue_script( 'interact-js', plugins_url( 'advanced-custom-post-type/assets/vendor/interact/interact.min.js'), [], '3.1.0', true);
        wp_enqueue_script( 'papa-parse-js', plugins_url( 'advanced-custom-post-type/assets/vendor/papaparse/papaparse.min.js'), [], '5.0.0', true);

        wp_register_script_module( 'acpt-tabulator-js', plugins_url( ACPT_DEV_MODE ? 'advanced-custom-post-type/assets/static/js/ACPTTabulator.js' : 'advanced-custom-post-type/assets/static/js/ACPTTabulator.min.js'), [], ACPT_PLUGIN_VERSION);
        wp_enqueue_script_module( 'custom-tabulator-js', plugins_url( ACPT_DEV_MODE ? 'advanced-custom-post-type/assets/static/js/tabulator.js' : 'advanced-custom-post-type/assets/static/js/tabulator.min.js'), ["acpt-tabulator-js"], ACPT_PLUGIN_VERSION);
	}
}