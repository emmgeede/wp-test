<?php

namespace ACPT\Core\Generators\Form\Fields;

use ACPT\Core\Models\Form\FormFieldModel;
use ACPT\Utils\PHP\Arrays;

class LayoutTabsField extends AbstractField
{

    /**
     * @inheritDoc
     */
    public function render()
    {
        if(!isset($this->fieldModel->getExtra()['tabs'])){
            return null;
        }

        $css = $this->fieldModel->getSettings()['css'] ?? "";
        $tabs = "<div class='acpt-tabs-wrapper ".$css."'>";

        // tabs
        $tabs .= '<ul class="tablist" role="tablist">';

        foreach ($this->fieldModel->getExtra()['tabs'] as $tabIndex => $tab){
            $active = $tabIndex === 0 ? "active" : "";
            $tabs .= '<li data-tab-index="'.$tabIndex.'" role="tab" class="acpt-accordion-tab '.$active.'">'.$tab.'</li>';
        }

        $tabs .= "</ul>";

        // tabs content
        foreach ($this->fieldModel->getExtra()['tabs'] as $tabIndex => $tab){
            $active = $tabIndex === 0 ? "active" : "";
            $tabs .= '<div data-tab="'.$tabIndex.'" class="acpt-accordion-tab-content '.$active.'">';

            $subfields = array_filter($this->fieldModel->getChildren(), function (FormFieldModel $field) use ($tabIndex){
                return $field->getParentTabIndex() === $tabIndex;
            });

            foreach (Arrays::reindex($subfields) as $formFieldModel){
                try {
                    $tabs .= $this->renderSubField($formFieldModel);
                } catch (\Exception $exception){
                    do_action("acpt/error", $exception);
                }
            }

            $tabs .= '</div>';
        }

        $tabs .= "</div>";

        return $tabs;
    }

    /**
     * @inheritDoc
     */
    public function enqueueFieldAssets() {
        // TODO: Implement enqueueFieldAssets() method.
    }
}