<?php

namespace ACPT\Integrations\SeoPress\Provider;

use ACPT\Constants\MetaTypes;
use ACPT\Constants\Operator;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Core\Repository\OptionPageRepository;
use ACPT\Integrations\SeoPress\Provider\Fields\Address;
use ACPT\Integrations\SeoPress\Provider\Fields\AddressMulti;
use ACPT\Integrations\SeoPress\Provider\Fields\ArrayValues;
use ACPT\Integrations\SeoPress\Provider\Fields\Attachment;
use ACPT\Integrations\SeoPress\Provider\Fields\Base;
use ACPT\Integrations\SeoPress\Provider\Fields\Currency;
use ACPT\Integrations\SeoPress\Provider\Fields\Date;
use ACPT\Integrations\SeoPress\Provider\Fields\DateRange;
use ACPT\Integrations\SeoPress\Provider\Fields\DateTime;
use ACPT\Integrations\SeoPress\Provider\Fields\File;
use ACPT\Integrations\SeoPress\Provider\Fields\Length;
use ACPT\Integrations\SeoPress\Provider\Fields\Phone;
use ACPT\Integrations\SeoPress\Provider\Fields\Post;
use ACPT\Integrations\SeoPress\Provider\Fields\PostMulti;
use ACPT\Integrations\SeoPress\Provider\Fields\Relationship;
use ACPT\Integrations\SeoPress\Provider\Fields\Term;
use ACPT\Integrations\SeoPress\Provider\Fields\TermMulti;
use ACPT\Integrations\SeoPress\Provider\Fields\Time;
use ACPT\Integrations\SeoPress\Provider\Fields\Url;
use ACPT\Integrations\SeoPress\Provider\Fields\User;
use ACPT\Integrations\SeoPress\Provider\Fields\UserMulti;
use ACPT\Integrations\SeoPress\Provider\Fields\Weight;
use ACPT\Utils\PHP\Arrays;

/**
 * @see https://www.seopress.org/support/guides/how-to-integrate-advanced-custom-fields-acf-with-seopress/
 */
class SeoPressProvider
{
    /**
     * Allowed fields
     */
    const ALLOWED_FIELDS = [
        MetaFieldModel::ADDRESS_TYPE,
        MetaFieldModel::ADDRESS_MULTI_TYPE,
        MetaFieldModel::AUDIO_TYPE,
        MetaFieldModel::AUDIO_MULTI_TYPE,
        MetaFieldModel::CHECKBOX_TYPE,
        MetaFieldModel::COLOR_TYPE,
        MetaFieldModel::COUNTRY_TYPE,
        MetaFieldModel::CURRENCY_TYPE,
        MetaFieldModel::DATE_TYPE,
        MetaFieldModel::DATE_RANGE_TYPE,
        MetaFieldModel::DATE_TIME_TYPE,
        MetaFieldModel::EDITOR_TYPE,
        MetaFieldModel::EMAIL_TYPE,
        MetaFieldModel::FILE_TYPE,
        MetaFieldModel::GALLERY_TYPE,
        MetaFieldModel::HTML_TYPE,
        MetaFieldModel::ID_TYPE,
        MetaFieldModel::IMAGE_TYPE,
        MetaFieldModel::LENGTH_TYPE,
        MetaFieldModel::LIST_TYPE,
        MetaFieldModel::NUMBER_TYPE,
        MetaFieldModel::PASSWORD_TYPE,
        MetaFieldModel::PHONE_TYPE,
        MetaFieldModel::POST_TYPE,
        MetaFieldModel::POST_OBJECT_TYPE,
        MetaFieldModel::POST_OBJECT_MULTI_TYPE,
        MetaFieldModel::QR_CODE_TYPE,
        MetaFieldModel::RADIO_TYPE,
        MetaFieldModel::RANGE_TYPE,
        MetaFieldModel::RATING_TYPE,
        MetaFieldModel::SELECT_TYPE,
        MetaFieldModel::SELECT_MULTI_TYPE,
        MetaFieldModel::TERM_OBJECT_TYPE,
        MetaFieldModel::TERM_OBJECT_MULTI_TYPE,
        MetaFieldModel::TEXTAREA_TYPE,
        MetaFieldModel::TEXT_TYPE,
        MetaFieldModel::TIME_TYPE,
        MetaFieldModel::TOGGLE_TYPE,
        MetaFieldModel::URL_TYPE,
        MetaFieldModel::USER_TYPE,
        MetaFieldModel::USER_MULTI_TYPE,
        MetaFieldModel::VIDEO_TYPE,
        MetaFieldModel::WEIGHT_TYPE,
    ];

    /**
     * @var array
     */
    private array $fields = [];

    /**
     * SeoPressProvider constructor.
     */
    public function __construct()
    {
        $this->setFields();
    }

    /**
     * Register ACPT fields
     */
    private function setFields()
    {
        $groups = get_acpt_meta_group_objects();

        foreach ($groups as $group){

            foreach ($group->belongs as $belong){
                foreach ($group->boxes as $box){
                    foreach ($box->fields as $field){

                        // Option page fields
                        if($belong->belongsTo === MetaTypes::OPTION_PAGE){

                            $pages = [];

                            switch ($belong->operator){
                                case Operator::EQUALS:
                                    $pages = [$belong->find];
                                    break;

                                case Operator::IN:
                                    $pages = explode(",", $belong->find);
                                    break;

                                case Operator::NOT_IN:
                                    $excludedPages = explode(",", $belong->find);
                                    $allPages = OptionPageRepository::getAllSlugs();
                                    $pages = array_diff($allPages, $excludedPages);
                                    break;

                                case Operator::NOT_EQUALS:
                                    $excludedPages = [$belong->find];
                                    $allPages = OptionPageRepository::getAllSlugs();
                                    $pages = array_diff($allPages, $excludedPages);
                                    break;
                            }

                            foreach ($pages as $page){
                                $this->registerField($box, $field, $page);
                            }
                        }

                        // other fields
                        else {
                            $this->registerField($box, $field);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $box
     * @param $field
     * @param $page
     * @param $parent
     * @return void
     */
    private function registerField($box, $field, $page = null, $parent = null)
    {
        $key   = '%%_acpt_';
        $label = '[ACPT] - ';

        if($page !== null){
            $key   .=  $page."_";
            $label .= '['.$page.']';
        }

        if($parent !== null){
            $key   .=  $parent."_";
            $label .= '['.$parent.']';
        }

        $key   .=  $box->name.'_'.$field->name.'%%';
        $label .= " " . ($box->label ?? $box->name) . " " . ($field->label ?? $field->name);

        if(
            !isset($this->fields[$key]) and
            in_array($field->type, self::ALLOWED_FIELDS)
        ){
            $this->fields[$key] = [
                'type' => $field->type,
                'box' => $box->label,
                'field' => $field->name,
                'description' => $label,
                'parent' => $parent,
            ];
        }

        foreach ($field->children as $child){
            $this->registerField($box, $child, $page, $field->name);
        }
    }

    /**
     * Run the integration
     */
    public function run()
    {
        add_filter('seopress_titles_template_variables_array', [$this, 'spTitlesTemplateVariablesArray']);
        add_filter('seopress_titles_template_replace_array', [$this, 'spTitlesTemplateReplaceArray']);
        add_filter('seopress_get_dynamic_variables', [$this, 'spGetDynamicVariables']);
    }

    /**
     * @param array $array
     * @return array
     */
    public function spTitlesTemplateVariablesArray($array)
    {
        return array_merge($array, array_keys($this->fields));
    }

    /**
     * @param array $array
     * @return array
     */
    public function spGetDynamicVariables($array)
    {
        foreach ($this->fields as $key => $field){
            $array[$key] = $field['description'];
        }

        return $array;
    }

    /**
     * Replace the placeholder with values
     *
     * @param array $array
     * @return array
     */
    public function spTitlesTemplateReplaceArray($array)
    {
        foreach ($this->fields as $field){
            $array[] = $this->getFieldValue($field);
        }

        return $array;
    }

    /**
     * @param $field
     * @return string
     */
    private function getFieldValue($field)
    {
        global $post;

        if(empty($post)){
            return '';
        }

        if(isset($field['option_page'])){
            $disc = 'option_page';
            $find = $field['option_page'];
        } else {
            $disc = 'post_id';
            $find = $post->ID;
        }

        if(isset($field['parent']) and !empty($field['parent'])){
            $rawValues = [];

            $parentValues = get_acpt_field([
                $disc => $find,
                'box_name' => $field['box'],
                'field_name' => $field['parent'],
                'with_context' => true,
            ]);

            for ($i=0; $i<Arrays::count($parentValues); $i++){
                $rawValue = get_acpt_child_field([
                    $disc => $find,
                    'box_name' => $field['box'],
                    'field_name' => $field['field'],
                    'parent_field_name' => $field['parent'],
                    'index' => $i,
                    'with_context' => true,
                ]);

                $rawValues[] = $this->renderField($field, $rawValue);
            }

             return implode(',', $rawValues);
        }

        $rawValue = get_acpt_field([
            $disc => $find,
            'box_name' => $field['box'],
            'field_name' => $field['field'],
            'with_context' => true,
        ]);

        return $this->renderField($field, $rawValue);
    }

    /**
     * @param $field
     * @param $rawValue
     * @return string|null
     */
    private function renderField($field, $rawValue): ?string
    {
        if(empty($rawValue)){
            return '';
        }

        if(!is_array($rawValue)){
            return '';
        }

        if(!isset($rawValue['value'])){
            return '';
        }

        $type = $field['type'];

        if($type === MetaFieldModel::REPEATER_TYPE or $type === MetaFieldModel::FLEXIBLE_CONTENT_TYPE){
            return '';
        }

        $val = $rawValue['value'];
        $before = $rawValue['before'];
        $after = $rawValue['after'];

        switch ($type){

            // ADDRESS_TYPE
            case MetaFieldModel::ADDRESS_TYPE:
                $value = new Address($val, $before, $after);
                break;

            // ADDRESS_MULTI_TYPE
            case MetaFieldModel::ADDRESS_MULTI_TYPE:
                $value = new AddressMulti($val, $before, $after);
                break;

            // CURRENCY_TYPE
            case MetaFieldModel::CURRENCY_TYPE:
                $value = new Currency($val, $before, $after);
                break;

            // DATE_TYPE
            case MetaFieldModel::DATE_TYPE:
                $value = new Date($val, $before, $after);
                break;

            // DATE_TIME_TYPE
            case MetaFieldModel::DATE_TIME_TYPE:
                $value = new DateTime($val, $before, $after);
                break;

            // TIME_TYPE
            case MetaFieldModel::TIME_TYPE:
                $value = new Time($val, $before, $after);
                break;

            // DATE_RANGE_TYPE
            case MetaFieldModel::DATE_RANGE_TYPE:
                $value = new DateRange($val, $before, $after);
                break;

            // RAW ARRAY VALUES
            case MetaFieldModel::CHECKBOX_TYPE:
            case MetaFieldModel::LIST_TYPE:
            case MetaFieldModel::SELECT_MULTI_TYPE:
                $value = new ArrayValues($val, $before, $after);
                break;

            // LENGTH_TYPE
            case MetaFieldModel::LENGTH_TYPE:
                $value = new Length($val, $before, $after);
                break;

            case MetaFieldModel::FILE_TYPE:
                $value = new File($val, $before, $after);
                break;

            // AUDIO_TYPE
            // AUDIO_MULTI_TYPE
            // IMAGE_TYPE
            // GALLERY_TYPE
            // VIDEO_TYPE
            case MetaFieldModel::AUDIO_TYPE:
            case MetaFieldModel::AUDIO_MULTI_TYPE:
            case MetaFieldModel::IMAGE_TYPE:
            case MetaFieldModel::GALLERY_TYPE:
            case MetaFieldModel::VIDEO_TYPE:
                $value = new Attachment($val, $before, $after);
                break;

            // PHONE_TYPE
            case MetaFieldModel::PHONE_TYPE:
                $value = new Phone($val, $before, $after);
                break;

            // POST_TYPE
            case MetaFieldModel::POST_TYPE:
                $value = new Relationship($val);
                break;

            // POST_OBJECT_TYPE
            case MetaFieldModel::POST_OBJECT_TYPE:
                $value = new Post($val);
                break;

            // POST_OBJECT_MULTI_TYPE
            case MetaFieldModel::POST_OBJECT_MULTI_TYPE:
                $value = new PostMulti($val);
                break;

            // QR_CODE_TYPE
            // URL_TYPE
            case MetaFieldModel::QR_CODE_TYPE:
            case MetaFieldModel::URL_TYPE:
                $value = new Url($val, $before, $after);
                break;

            // TERM_OBJECT_TYPE
            case MetaFieldModel::TERM_OBJECT_TYPE:
                $value = new Term($val);
                break;

            // TERM_OBJECT_MULTI_TYPE
            case MetaFieldModel::TERM_OBJECT_MULTI_TYPE:
                $value = new TermMulti($val);
                break;

            // USER_TYPE
            case MetaFieldModel::USER_TYPE:
                $value = new User($val);
                break;

            // USER_MULTI_TYPE
            case MetaFieldModel::USER_MULTI_TYPE:
                $value = new UserMulti($val);
                break;

            // WEIGHT_TYPE
            case MetaFieldModel::WEIGHT_TYPE:
                $value = new Weight($val, $before, $after);
                break;

            // DEFAULT
            default:
                $value = new Base($val, $before, $after);
        }

        return Strings::esc_attr(wp_strip_all_tags($value->getValue()));
    }
}
