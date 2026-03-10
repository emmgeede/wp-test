<?php

namespace ACPT\Core\Generators\Form\Fields;

class LayoutContainerField extends AbstractField
{
    /**
     * @inheritDoc
     */
    public function render()
    {
        $tagElement = $this->fieldModel->getExtra()['tagElement'] ?? "div";
        $css = $this->fieldModel->getSettings()['css'] ?? "";

        $render = "<".$tagElement." class='acpt-container ".$css."'>";

        foreach ($this->fieldModel->getChildren() as $formFieldModel){
            try {
                $render .= $this->renderSubField($formFieldModel);
            } catch (\Exception $exception){
                do_action("acpt/error", $exception);
            }
        }

        $render .= "</".$tagElement.">";

        return $render;
    }

    /**
     * @inheritDoc
     */
    public function enqueueFieldAssets()
    {
        // TODO: Implement enqueueFieldAssets() method.
    }
}