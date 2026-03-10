<?php

namespace ACPT\Integrations\ElementorPro\Tags;

use ACPT\Utils\Wordpress\Translator;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

class ACPTSelectTag extends ACPTAbstractTag
{
    /**
     * @inheritDoc
     */
    public function get_categories()
    {
        return [
                Module::TEXT_CATEGORY,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get_name()
    {
        return 'acpt-select';
    }

    /**
     * @inheritDoc
     */
    public function get_title()
    {
        return esc_html__( "ACPT select field", ACPT_PLUGIN_NAME );
    }

    public function register_controls()
    {
        parent::register_controls();

        $this->add_control(
            'render',
            [
                'label' => Translator::translate( 'Rander as' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'value' => 'Value',
                    'label' => 'Label',
                ],
            ]
        );
    }

    /**
     *
     */
    public function render()
    {
        $render = $this->get_settings('render') ?? "value";

        echo $this->renderValueOrLabel($render);
    }

    /**
     * @param string $render
     *
     * @return string
     */
    private function renderValueOrLabel($render = "value")
    {
        $rawData = $this->getRawData();

        $after = $rawData['after'];
        $before = $rawData['before'];
        $value = $rawData['value'];

        if($render === "value"){
            return $before . $value . $after;
        }

        $field = $this->extractField();
        $options = acpt_get_field_options($field['boxName'], $field['fieldName']);
        $array_filter = array_filter($options, function($o) use($value) { return $o['value'] === $value; });
        $array_filter = array_values($array_filter);

        return $array_filter[0]['label'] ?? $value;
    }
}
