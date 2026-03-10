<?php

namespace ACPT\Admin;

use ACPT\Core\Repository\CustomPostTypeRepository;
use ACPT\Core\Repository\DynamicBlockRepository;
use ACPT\Core\Repository\FormRepository;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Core\Repository\TaxonomyRepository;
use ACPT\Utils\Wordpress\Translator;

class ACPT_Admin_Bar_Menu
{
    /**
     * @param \WP_Admin_Bar $wpAdminBar
     */
    public static function generate(\WP_Admin_Bar $wpAdminBar)
    {
        if( ! is_admin() ){
            return;
        }

        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }

        // Parent node
        self::addNode($wpAdminBar, "main", self::ACPTLogo());

        // Custom post types
        if(defined("ACPT_ENABLE_CPT") and ACPT_ENABLE_CPT){
            self::addNode($wpAdminBar, "custom-post-types", "Custom Post Types", null, "main");
            self::addNode($wpAdminBar, "create-custom-post-type", "Create new Custom Post Type", "register", "custom-post-types");

            try {
                foreach (CustomPostTypeRepository::get() as $postTypeModel){
                    if(!$postTypeModel->isNative()){
                        self::addNode($wpAdminBar, $postTypeModel->getName(), $postTypeModel->getSingular(), "view/".$postTypeModel->getName(), "custom-post-types");
                        self::addNode($wpAdminBar, "edit-".$postTypeModel->getName(), "Edit", "edit/".$postTypeModel->getName()."/0", $postTypeModel->getName());
                        self::addNode($wpAdminBar, "view-".$postTypeModel->getName(), "View", "view/".$postTypeModel->getName(), $postTypeModel->getName());
                    }
                }
            } catch (\Exception $exception){}
        }

        // Taxonomies
        if(defined("ACPT_ENABLE_TAX") and ACPT_ENABLE_TAX){
            self::addNode($wpAdminBar, "taxonomies", "Taxonomies", "taxonomies", "main");
            self::addNode($wpAdminBar, "register-taxonomy", "Create new Taxonomy", "register_taxonomy", "taxonomies");

            try {
                foreach (TaxonomyRepository::get() as $taxonomyModel){
                    if(!$taxonomyModel->isNative()){
                        self::addNode($wpAdminBar, $taxonomyModel->getSlug(), $taxonomyModel->getSingular(), "view_taxonomy/".$taxonomyModel->getSlug(), "taxonomies");
                        self::addNode($wpAdminBar, "edit-".$taxonomyModel->getSlug(), "Edit", "edit_taxonomy/".$taxonomyModel->getSlug()."/0", $taxonomyModel->getSlug());
                        self::addNode($wpAdminBar, "view-".$taxonomyModel->getSlug(), "View", "view_taxonomy/".$taxonomyModel->getSlug(), $taxonomyModel->getSlug());
                    }
                }
            } catch (\Exception $exception){}
        }

        // Option Pages
        if(defined("ACPT_ENABLE_PAGES") and ACPT_ENABLE_PAGES){
            self::addNode($wpAdminBar, "option-pages", "Option pages", "option-pages", "main");
            self::addNode($wpAdminBar, "option-pages-manage", "Manage pages", "option-pages-manage", "option-pages");
        }

        // Field groups
        if(defined("ACPT_ENABLE_META") and ACPT_ENABLE_META){
            self::addNode($wpAdminBar, "meta", "Field groups", "meta", "main");
            self::addNode($wpAdminBar, "register-meta", "Create new Meta group", "register_meta", "meta");

            try {
                foreach (MetaRepository::get([]) as $metaGroupModel){
                    self::addNode($wpAdminBar, "meta-".$metaGroupModel->getId(), Translator::translate("Edit") . " ". $metaGroupModel->getUIName(), "edit_meta/".$metaGroupModel->getId(), "meta");
                }
            } catch (\Exception $exception){}
        }

        // Forms
        if(defined("ACPT_ENABLE_FORMS") and ACPT_ENABLE_FORMS){
            self::addNode($wpAdminBar, "forms", "Forms", "forms", "main");
            self::addNode($wpAdminBar, "register-form", "Create form", "form-settings", "forms");

            try {
                foreach (FormRepository::get([]) as $formModel){
                    self::addNode($wpAdminBar, "form-".$formModel->getId(), Translator::translate("Manage fields") . " ". $formModel->getLabel(), "form/".$formModel->getId(), "forms");
                }
            } catch (\Exception $exception){}
        }

        // Blocks
        if(defined("ACPT_ENABLE_BLOCKS") and ACPT_ENABLE_BLOCKS){
            self::addNode($wpAdminBar, "blocks", "Dynamic blocks", "blocks", "main");
            self::addNode($wpAdminBar, "register-block", "Create new block", "block", "blocks");

            try {
                foreach (DynamicBlockRepository::get([]) as $blockModel){
                    self::addNode($wpAdminBar, "block-".$blockModel->getId(), $blockModel->getName(), "view_block/".$blockModel->getId(), "blocks");
                    self::addNode($wpAdminBar, "edit-".$blockModel->getId(), "Edit", "block/".$blockModel->getId()."/0", "block-".$blockModel->getId());
                    self::addNode($wpAdminBar, "view-".$blockModel->getId(), "View", "view_block/".$blockModel->getId(), "block-".$blockModel->getId());
                }
            } catch (\Exception $exception){}
        }

        self::addNode($wpAdminBar, "settings", "Settings", "settings", "main");
    }

    /**
     * @return string
     */
    private static function ACPTLogo()
    {
        return '
            <span class="ab-icon">
                <svg width="18" height="18" viewBox="0 0 637 695" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M302.676 417.485C302.676 381.758 283.616 348.746 252.676 330.882L30 202.32C16.6667 194.622 0 204.245 0 219.641V476.765C0 512.491 19.0599 545.504 50 563.367L272.676 691.929C286.009 699.627 302.676 690.005 302.676 674.609V417.485ZM237.676 596.667V417.485C237.676 404.981 231.005 393.426 220.176 387.174L65 297.583V476.765C65 489.269 71.671 500.824 82.5 507.076L237.676 596.667Z" fill="#fff"></path>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M383.338 330.882C352.398 348.746 333.338 381.758 333.338 417.485L333.338 674.609C333.338 690.005 350.005 699.627 363.338 691.929L586.014 563.367C616.954 545.504 636.014 512.491 636.014 476.765L636.014 219.641C636.014 204.245 619.347 194.622 606.014 202.32L383.338 330.882ZM571.014 297.583L415.838 387.174C405.009 393.426 398.338 404.981 398.338 417.485L398.338 596.667L553.514 507.076C564.343 500.824 571.014 489.269 571.014 476.765L571.014 297.583Z" fill="#fff"></path>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M367.676 14.2424C336.736 -3.62085 298.616 -3.62085 267.676 14.2424L45 142.804C31.6667 150.502 31.6667 169.747 45 177.445L267.676 306.007C298.616 323.871 336.736 323.871 367.676 306.007L590.352 177.445C603.685 169.747 603.685 150.502 590.352 142.804L367.676 14.2424ZM490.352 160.125L335.176 70.5341C324.347 64.2819 311.005 64.2819 300.176 70.5341L145 160.125L300.176 249.716C311.005 255.968 324.347 255.968 335.176 249.716L490.352 160.125Z" fill="#fff"></path>
                </svg>
            </span>
            <span class="ab-label">
                ACPT
            </span>
        ';
    }

    /**
     * @param \WP_Admin_Bar $wpAdminBar
     * @param               $id
     * @param               $title
     * @param null          $href
     * @param null          $parent
     */
    private static function addNode(\WP_Admin_Bar $wpAdminBar, $id, $title, $href = null, $parent = null)
    {
        $args = [
            'id'     => self::node($id),
            'title'  => Translator::translate($title),
            'href'   => self::adminLink($href),
        ];

        if(!empty($parent)){
            $args['parent'] = self::node($parent);
        }

        $wpAdminBar->add_node($args);
    }

    /**
     * @param $node
     *
     * @return string
     */
    private static function node($node)
    {
        return ACPT_PLUGIN_NAME . '-' . $node;
    }

    /**
     * @param null $page
     *
     * @return string|void
     */
    private static function adminLink($page = null)
    {
        if(empty($page)){
            return admin_url('admin.php?page='.ACPT_PLUGIN_NAME);
        }

        return admin_url('admin.php?page='.ACPT_PLUGIN_NAME.'#/'.$page);
    }
}