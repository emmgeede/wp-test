<?php

namespace ACPT\Integrations\WooCommerce;

use ACPT\Includes\ACPT_DB;
use ACPT\Integrations\AbstractIntegration;
use ACPT\Integrations\WooCommerce\Ajax\WooCommerceAjax;
use ACPT\Integrations\WooCommerce\Filters\WooCommerceFilters;
use ACPT\Integrations\WooCommerce\Generators\WooCommerceProductAttributeMetaGroups;
use ACPT\Integrations\WooCommerce\Generators\WooCommerceProductData;
use ACPT\Integrations\WooCommerce\Generators\WooCommerceProductVariationMetaGroups;

class ACPT_WooCommerce extends AbstractIntegration
{
    /**
     * @inheritDoc
     */
    protected function name()
    {
        return "woocommerce";
    }

    /**
     * @inheritDoc
     */
    protected function isActive()
    {
        return defined("ACPT_ENABLE_META") and ACPT_ENABLE_META and is_plugin_active( 'woocommerce/woocommerce.php');
    }

    /**
     * Public facade for ACPT_WooCommerce::isActive() method
     *
     * @return bool
     */
    public static function active()
    {
        return (new ACPT_WooCommerce)->isActive();
    }

    /**
     * @inheritDoc
     */
    protected function runIntegration()
    {
        if(!ACPT_DB::tableExists("TABLE_WOOCOMMERCE_PRODUCT_DATA")){
            ACPT_DB::removeOrCreateFeatureTables("woocommerce");
        }

        (new WooCommerceProductData())->generate();
        (new WooCommerceProductVariationMetaGroups())->generate();
        (new WooCommerceFilters())->run();
        (new WooCommerceAjax())->routes();
        (new WooCommerceProductAttributeMetaGroups())->generate();
    }

    /**
     * Example: edit.php?post_type=product&page=product_attributes&edit=1
     *
     * @param $pagenow
     *
     * @return bool
     */
    public static function isWooCommerceProductAttributePage( $pagenow)
    {
        if(!self::active()){
            return false;
        }

        if($pagenow !== "edit.php"){
            return false;
        }

        if(!isset($_GET['post_type'])){
            return false;
        }

        if($_GET['post_type'] !== "product"){
            return false;
        }

        if(!isset($_GET['page'])){
            return false;
        }

        if($_GET['page'] !== "product_attributes"){
            return false;
        }

        $id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

        if(empty($id)){
            return false;
        }

        return true;
    }

    /**
     * @param $id
     *
     * @return string
     */
    public static function getWooCommerceProductAttributeName($id)
    {
        if(function_exists("wc_attribute_taxonomy_name_by_id")){
            return wc_attribute_taxonomy_name_by_id($id);
        }

        global $wpdb;
        $attributeSlug = $wpdb->get_var("select attribute_name from {$wpdb->prefix}woocommerce_attribute_taxonomies where attribute_id=" . $id);

        if(empty($attributeSlug)){
            return "";
        }

        return "pa_".$attributeSlug;
    }
}
