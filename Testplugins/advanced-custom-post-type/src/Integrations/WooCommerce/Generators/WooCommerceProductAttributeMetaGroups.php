<?php

namespace ACPT\Integrations\WooCommerce\Generators;

use ACPT\Constants\MetaTypes;
use ACPT\Core\CQRS\Command\SaveWooCommerceProductAttributeMetaCommand;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Integrations\WooCommerce\ACPT_WooCommerce;
use function Twig\render;

class WooCommerceProductAttributeMetaGroups
{
    /**
     * Add product data
     */
    public function generate()
    {
        $this->renderGroups();
        $this->saveData();
    }

    /**
     * Save/update fields
     */
    private function saveData()
    {
        add_action("woocommerce_attribute_updated", function ($attributeId, $data, $attributeName){

            if ( !is_admin() ) {
                return;
            }

            $command = new SaveWooCommerceProductAttributeMetaCommand($attributeId, $_POST);
            $command->execute();

        }, 10, 3);
    }

    /**
     * Render fields on WC product attribute page
     */
    private function renderGroups()
    {
        add_action( 'woocommerce_after_edit_attribute_fields', function (){
            $id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
            $attributeName = ACPT_WooCommerce::getWooCommerceProductAttributeName($id);

            $groups = MetaRepository::get([
                'belongsTo' => MetaTypes::TAXONOMY,
                'find' => $attributeName,
                'clonedFields' => true,
            ]);

            foreach ($groups as $group){
                $generator = new WooCommerceProductAttributeMetaGroup($group, $id, $attributeName);
                echo $generator->render();
            }
        });
    }
}