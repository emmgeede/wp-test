<?php

namespace ACPT\Integrations\WooCommerce\Generators;

use ACPT\Core\Models\Meta\MetaGroupModel;

class WooCommerceProductAttributeMetaGroup
{
    /**
     * @var MetaGroupModel
     */
    private MetaGroupModel $groupModel;
    private                $productAttributeId;
    private                $productAttributeSlug;

    /**
     * WooCommerceProductAttributeMetaGroup constructor.
     *
     * @param MetaGroupModel $groupModel
     * @param                $productAttributeId
     * @param                $productAttributeName
     */
    public function __construct( MetaGroupModel $groupModel, $productAttributeId, $productAttributeName)
    {
        $this->groupModel = $groupModel;
        $this->productAttributeSlug = $productAttributeName;
        $this->productAttributeId = $productAttributeId;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function render()
    {
        if(empty($this->groupModel->getBoxes())){
            return null;
        }

        return $this->standardView();
    }

    /**
     * Standard view
     * @throws \Exception
     */
    private function standardView()
    {
        $render = "";

        foreach ($this->groupModel->getBoxes() as $metaBoxModel){
            foreach ($metaBoxModel->getFields() as $fieldModel){
                $generator = new WooCommerceProductAttributeMetaField($fieldModel, $this->productAttributeId, $this->productAttributeSlug);
                $render .= $generator->render();
            }
        }

        return $render;
    }
}