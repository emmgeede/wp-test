<?php

namespace ACPT\Core\Generators\Form\Fields;

use ACPT\Core\Models\Form\FormFieldModel;
use ACPT\Utils\PHP\Arrays;

class LayoutStepsField extends AbstractField
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
        $tabs = "<div class='acpt-steps-wrapper ".$css."'>";

        // steps
        $tabs .= '<ul class="steplist" role="tablist">';

        foreach ($this->fieldModel->getExtra()['tabs'] as $stepIndex => $step){
            $active = $stepIndex === 0 ? "active" : "undone";
            $tabs .= '<li data-tab-index="'.$stepIndex.'" role="tab" class="acpt-steps-tab '.$active.'">';
            $tabs .= '<span class="number">'.($stepIndex+1).'</span>';
            $tabs .= '<span class="title">'.$step.'</span>';
            $tabs .= '</li>';
        }

        $tabs .= "</ul>";

        // steps content
        foreach ($this->fieldModel->getExtra()['tabs'] as $stepIndex => $step){
            $active = $stepIndex === 0 ? "active" : "";
            $tabs .= '<div data-step="'.$stepIndex.'" class="acpt-accordion-step-content '.$active.'">';

            $subfields = array_filter($this->fieldModel->getChildren(), function (FormFieldModel $field) use ($stepIndex){
                return $field->getParentTabIndex() === $stepIndex;
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