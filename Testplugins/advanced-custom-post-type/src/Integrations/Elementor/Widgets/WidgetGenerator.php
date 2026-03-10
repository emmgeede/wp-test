<?php

namespace ACPT\Integrations\Elementor\Widgets;

use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Integrations\Elementor\Constants\WidgetConstants;
use ACPT\Utils\PHP\Phone;
use ACPT\Utils\Wordpress\Translator;
use Elementor\Controls_Manager;

class WidgetGenerator extends \Elementor\Widget_Base
{
    /**
     * @var MetaFieldModel
     */
    private $boxFieldModel;

    private $index;

	/**
	 * WidgetGenerator constructor.
	 *
	 * @param array $data
	 * @param null $args
	 *
	 * @throws \Exception
	 */
    public function __construct( $data = [], $args = null )
    {
        parent::__construct( $data, $args );

        if(!isset($args['boxFieldModel'])){
            throw new \Exception('A boxFieldModel instance required to run this widget.');
        }

        $this->boxFieldModel = $args['boxFieldModel'];
        $this->index = $args['index'] ?? 0;
    }

    /**
     * Get the widget name
     *
     * @return string
     */
    public function get_name()
    {
    	if(!empty($this->boxFieldModel)){

            if($this->index > 0){
                return $this->boxFieldModel->getFindLabel() . "_" . $this->boxFieldModel->getDbName();
            }

            return $this->boxFieldModel->getDbName();
	    }

        return 'undefined';
    }

	/**
	 * get the UI title
	 *
	 * @return string
	 */
	public function get_title()
	{
		$title = $this->boxFieldModel ? '['.$this->boxFieldModel->getFindLabel().'] ' . $this->boxFieldModel->getUiName() : 'undefined';

		return esc_html__( $title, ACPT_PLUGIN_NAME );
	}

    /**
     * get UI icon
     *
     * @return string
     */
    public function get_icon()
    {
        if( !$this->boxFieldModel ){
            return 'eicon-editor-code';
        }

        switch ($this->boxFieldModel->getType()){
            case MetaFieldModel::ADDRESS_TYPE:
                return ' eicon-map-pin';

            case MetaFieldModel::AUDIO_TYPE:
                return ' eicon-play';

            case MetaFieldModel::AUDIO_MULTI_TYPE:
                return ' eicon-play-o';

            case MetaFieldModel::COLOR_TYPE:
                return 'eicon-paint-brush';

	        case MetaFieldModel::COUNTRY_TYPE:
		        return 'eicon-globe';

            case MetaFieldModel::CURRENCY_TYPE:
                return ' eicon-bag-light';

            case MetaFieldModel::DATE_TYPE:
            case MetaFieldModel::DATE_TIME_TYPE:
            case MetaFieldModel::DATE_RANGE_TYPE:
                return 'eicon-date';

            case MetaFieldModel::EDITOR_TYPE:
                return 'eicon-text-area';

            case MetaFieldModel::EMAIL_TYPE:
                return 'eicon-mail';

            case MetaFieldModel::EMBED_TYPE:
                return 'eicon-gallery-grid';

            case MetaFieldModel::FILE_TYPE:
                return 'eicon-save-o';

            case MetaFieldModel::HTML_TYPE:
                return 'eicon-editor-code';

            case MetaFieldModel::GALLERY_TYPE:
                return 'eicon-photo-library';

            case MetaFieldModel::ID_TYPE:
                return 'eicon-lock-user';

            case MetaFieldModel::IMAGE_SLIDER_TYPE:
                return 'eicon-image-before-after';

            case MetaFieldModel::IMAGE_TYPE:
                return 'eicon-image';

            case MetaFieldModel::LENGTH_TYPE:
                return 'eicon-cursor-move';

            case MetaFieldModel::LIST_TYPE:
                return 'eicon-bullet-list';

            case MetaFieldModel::NUMBER_TYPE:
                return 'eicon-number-field';

            case MetaFieldModel::POST_TYPE:
                return 'eicon-sync';

            case MetaFieldModel::PHONE_TYPE:
                return 'eicon-tel-field';

            case MetaFieldModel::BARCODE_TYPE:
            case MetaFieldModel::QR_CODE_TYPE:
                return 'eicon-barcode';

	        case MetaFieldModel::FLEXIBLE_CONTENT_TYPE:
	        	return 'eicon-lightbox';

	        case MetaFieldModel::REPEATER_TYPE:
		        return 'eicon-post-list';

            case MetaFieldModel::SELECT_TYPE:
            case MetaFieldModel::SELECT_MULTI_TYPE:
                return 'eicon-select';

	        case MetaFieldModel::TABLE_TYPE:
		        return 'eicon-table';

            default:
            case MetaFieldModel::TEXTAREA_TYPE:
            case MetaFieldModel::TEXT_TYPE:
                return 'eicon-t-letter';

            case MetaFieldModel::TIME_TYPE:
                return 'eicon-clock-o';

            case MetaFieldModel::TOGGLE_TYPE:
                return 'eicon-toggle';

            case MetaFieldModel::VIDEO_TYPE:
                return 'eicon-play';

            case MetaFieldModel::WEIGHT_TYPE:
                return 'eicon-basket-medium';

            case MetaFieldModel::URL_TYPE:
                return 'eicon-url';
        }
    }

    /**
     * widget categories
     *
     * @return array
     */
    public function get_categories()
    {
        return [ WidgetConstants::GROUP_NAME ];
    }

    /**
     * get widget keywords
     *
     * @return array
     */
    public function get_keywords()
    {
        return [ WidgetConstants::GROUP_NAME, strtolower($this->boxFieldModel->getType()) ];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'section_title',
            [
                'label' => esc_html__( 'ACPT field', ACPT_PLUGIN_NAME ),
            ]
        );

        $this->add_control(
            'acpt_shortcode',
            [
                'type' => 'acpt_shortcode',
                'default' => $this->boxFieldModel ? '['.$this->boxFieldModel->getFindLabel().'] ' . $this->boxFieldModel->getUiName() : null,
                'placeholder' => esc_html__( 'Enter your code', ACPT_PLUGIN_NAME ),
            ]
        );

        $contexts = $this->getContexts();

	    // Group 1
        if(in_array($this->boxFieldModel->getType(), $contexts['group1'])){
            $this->add_control(
            'acpt_width',
                [
                    'type' => Controls_Manager::TEXT,
                    'label' => esc_html__( 'Width.', ACPT_PLUGIN_NAME ),
                    'default' => '100%',
                    'description' => esc_html__( 'Set the width (in pixels)', ACPT_PLUGIN_NAME ),
                ]
            );

            $this->add_control(
            'acpt_height',
                [
	                'type' => Controls_Manager::TEXT,
                    'label' => esc_html__( 'Height.', ACPT_PLUGIN_NAME ),
                    'default' => '300',
                    'description' => esc_html__( 'Set the height (in pixels)', ACPT_PLUGIN_NAME ),
                ]
            );
        }

        // Group 2
        if(in_array($this->boxFieldModel->getType(), $contexts['group2'])){
            $this->add_control(
                'acpt_target',
                [
	                'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Link target.', ACPT_PLUGIN_NAME ),
                    'default' => '_self',
                    'options' => [
                    	'' => Translator::translate("--Select--"),
                    	'_blank' => 'Opens in the same frame as it was clicked',
                    	'_self' => 'Opens in the parent frame',
                    	'_parent' => 'Opens in the full body of the window',
                    	'_top' => '',
                    ],
                    'description' => esc_html__( 'Select the link target', ACPT_PLUGIN_NAME ),
                ]
            );
        }

        // Group 3
        if(in_array($this->boxFieldModel->getType(), $contexts['group3'])){
            $this->add_control(
                'acpt_dateformat',
                [
	                'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Date format.', ACPT_PLUGIN_NAME ),
                    'default' => 'd/m/Y',
                    'options' => [
	                    '' => Translator::translate("--Select--"),
						"d-M-y" => "dd-mmm-yy (ex. 28-OCT-90)",
						"d-M-Y" => "dd-mmm-yyyy (ex. 28-OCT-1990)",
						"d M y" => "mmm yy (ex. 28 OCT 90)",
						"d M Y" => "mmm yyyy (ex. 28 OCT 1990)",
						"d/m/Y" => "dd/mm/yy (ex. 28/10/90)",
						"m/d/y" => "mm/dd/yy (ex. 10/28/90)",
						"m/d/Y" => "mm/dd/yyyy (ex. 10/28/1990)",
						"d.m.y" => "dd.mm.yy (ex. 28.10.90)",
						"d.m.Y" => "dd.mm.yyyy (ex. 28.10.1990)",
                    ],
                    'description' => esc_html__( 'Select the date format', ACPT_PLUGIN_NAME ),
                ]
            );
        }

        // Group 4
        if(in_array($this->boxFieldModel->getType(), $contexts['group4'])){
            $this->add_control(
            'acpt_width',
                [
                    'type' => Controls_Manager::TEXT,
                    'label' => esc_html__( 'Width (px).', ACPT_PLUGIN_NAME ),
                    'default' => '100%',
                    'description' => esc_html__( 'Set the width (in pixels)', ACPT_PLUGIN_NAME ),
                ]
            );

            $this->add_control(
            'acpt_height',
                [
                    'type' => Controls_Manager::TEXT,
                    'label' => esc_html__( 'Height (px).', ACPT_PLUGIN_NAME ),
                    'default' => '300',
                    'description' => esc_html__( 'Set the height (in pixels)', ACPT_PLUGIN_NAME ),
                ]
            );

            $this->add_control(
            'acpt_elements',
                [
	                'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Number of elements.', ACPT_PLUGIN_NAME ),
                    'default' => '2',
                    'options' => [
                        '' => Translator::translate("--Select--"),
                    	"1" => "One element",
		                "2" => "Two elements",
		                "3" => "Three elements",
		                "4" => "Four elements",
		                "6" => "Six elements",
                    ],
                    'description' => esc_html__( 'Select the number of elements', ACPT_PLUGIN_NAME ),
                ]
            );

            $this->add_control(
                'acpt_sort',
                [
                    'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Sort.', ACPT_PLUGIN_NAME ),
                    'default' => 'asc',
                    'options' => [
                        '' => Translator::translate("--Select--"),
                        "asc" => "Ascendant",
                        "desc" => "Descendant",
                        "rand" => "Random",
                    ],
                    'description' => esc_html__( 'Select the sorting of gallery elements', ACPT_PLUGIN_NAME ),
                ]
            );
        }

	    // Group 5
	    if(in_array($this->boxFieldModel->getType(), $contexts['group5'])){
		    $this->add_control(
			    'acpt_dateformat',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => esc_html__( 'Date format.', ACPT_PLUGIN_NAME ),
				    'default' => 'd/m/Y',
				    'options' => [
					    '' => Translator::translate("--Select--"),
					    "d-M-y" => "dd-mmm-yy (ex. 28-OCT-90)",
					    "d-M-Y" => "dd-mmm-yyyy (ex. 28-OCT-1990)",
					    "d M y" => "mmm yy (ex. 28 OCT 90)",
					    "d M Y" => "mmm yyyy (ex. 28 OCT 1990)",
					    "d/m/Y" => "dd/mm/yy (ex. 28/10/90)",
					    "m/d/y" => "mm/dd/yy (ex. 10/28/90)",
					    "m/d/Y" => "mm/dd/yyyy (ex. 10/28/1990)",
					    "d.m.y" => "dd.mm.yy (ex. 28.10.90)",
					    "d.m.Y" => "dd.mm.yyyy (ex. 28.10.1990)",
				    ],
				    'description' => esc_html__( 'Select the date format', ACPT_PLUGIN_NAME ),
			    ]
		    );

		    $this->add_control(
			    'acpt_timeformat',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => esc_html__( 'Time format.', ACPT_PLUGIN_NAME ),
                    'default' => 'H:i:s',
                    'options' => [
                            'H:i'=> 'H:i (ex. 13:45)',
                            'g:i a' => 'g:i a (ex. 13:45)',
                            'g:i A' => 'g:i A (ex. 1:45 PM)',
                    ],
				    'description' => esc_html__( 'Select the time format', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

	    // Group 6
	    if(in_array($this->boxFieldModel->getType(), $contexts['group6'])){
		    $this->add_control(
			    'acpt_timeformat',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => esc_html__( 'Time format.', ACPT_PLUGIN_NAME ),
				    'default' => 'H:i:s',
				    'options' => [
					    'H:i'=> 'H:i (ex. 13:45)',
					    'g:i a' => 'g:i a (ex. 13:45)',
					    'g:i A' => 'g:i A (ex. 1:45 PM)',
				    ],
				    'description' => esc_html__( 'Select the time format', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

	    // Group 7
	    if(in_array($this->boxFieldModel->getType(), $contexts['group7'])){
		    $this->add_control(
			    'acpt_render',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => esc_html__( 'Display as.', ACPT_PLUGIN_NAME ),
				    'default' => 'H:i:s',
				    'options' => [
				    	'' => Translator::translate("--Select--"),
				    	'text' => 'Plain text',
				    	'link' => 'Link',
				    ],
				    'description' => esc_html__( 'Render this field as', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

	    // Group 8
	    if(in_array($this->boxFieldModel->getType(), $contexts['group8'])){
		    $this->add_control(
			    'acpt_repeater',
			    [
				    'label' => 'Element template',
				    'type' => Controls_Manager::WYSIWYG,
				    'default' => '<div>' . esc_html__( 'Repeater elements template' ) . '</div>',
			    ]
		    );

		    $this->add_control(
			    'acpt_wrapper',
			    [
				    'type' => Controls_Manager::TEXT,
				    'label' => 'Element wrapper',
				    'description' => esc_html__( 'The HTML tag of the wrapper element', ACPT_PLUGIN_NAME ),
				    'default' => 'div',
				    'placeholder' => esc_html__( 'The HTML tag of the wrapper element', ACPT_PLUGIN_NAME ),
			    ]
		    );

		    $this->add_control(
			    'acpt_css',
			    [
				    'type' => Controls_Manager::TEXT,
				    'label' => 'Element wrapper CSS class(es)',
				    'description' => esc_html__( 'The CSS class(es) of the wrapper element', ACPT_PLUGIN_NAME ),
				    'default' => '',
				    'placeholder' => esc_html__( 'Example: acpt-wrapper active', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

	    // Group 9
	    if(in_array($this->boxFieldModel->getType(), $contexts['group9'])){
		    $this->add_control(
			    'acpt_repeater',
			    [
				    'label' => 'Element template',
				    'type' => Controls_Manager::WYSIWYG,
				    'default' => '<div>' . esc_html__( 'Flexible elements template' ) . '</div>',
			    ]
		    );

		    $blocks = [
			    '' => Translator::translate("--Select--"),
		    ];

		    foreach ($this->boxFieldModel->getBlocks() as $block){
			    $blocks[$block->getName()] = $block->getName();
		    }

		    $this->add_control(
			    'acpt_block',
			    [
				    'label' => 'Block',
				    'type' => Controls_Manager::SELECT,
				    'options' => $blocks,
				    'default' => '<div>' . esc_html__( 'Flexible elements template' ) . '</div>',
			    ]
		    );

		    $this->add_control(
			    'acpt_wrapper',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => 'Element wrapper',
				    'description' => esc_html__( 'The HTML tag of the wrapper element', ACPT_PLUGIN_NAME ),
				    'default' => 'div',
				    'options' => [
					    'div' => 'Div',
					    'p' => 'Paragraph',
					    'span' => 'Span',
					    'ol' => 'Ordered list',
					    'ul' => 'Unordered list',
				    ],
				    'placeholder' => esc_html__( 'The HTML tag of the wrapper element', ACPT_PLUGIN_NAME ),
			    ]
		    );

		    $this->add_control(
			    'acpt_css',
			    [
				    'type' => Controls_Manager::TEXT,
				    'label' => 'Element wrapper CSS class(es)',
				    'description' => esc_html__( 'The CSS class(es) of the wrapper element', ACPT_PLUGIN_NAME ),
				    'default' => '',
				    'placeholder' => esc_html__( 'Example: acpt-wrapper active', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

	    // Group 10
	    if(in_array($this->boxFieldModel->getType(), $contexts['group10'])){
		    $this->add_control(
			    'acpt_render',
			    [
				    'type' => Controls_Manager::SELECT,
				    'label' => esc_html__( 'Display as.', ACPT_PLUGIN_NAME ),
				    'default' => 'text',
				    'options' => [
					    '' => Translator::translate("--Select--"),
					    'text' => 'Plain text',
					    'flag' => 'Flag',
					    'full' => 'Flag and text',
				    ],
				    'description' => esc_html__( 'Render this field as', ACPT_PLUGIN_NAME ),
			    ]
		    );
	    }

        // Group 11
        if(in_array($this->boxFieldModel->getType(), $contexts['group11'])){
            $this->add_control(
                'acpt_render',
                [
                    'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Skin.', ACPT_PLUGIN_NAME ),
                    'default' => 'text',
                    'options' => [
                        'light' => 'Light',
                        'dark' => 'Dark',
                    ],
                    'description' => esc_html__( 'Select the skin', ACPT_PLUGIN_NAME ),
                ]
            );
        }

        // Group 12
        if(in_array($this->boxFieldModel->getType(), $contexts['group12'])){
            $this->add_control(
                    'acpt_render',
                    [
                            'type' => Controls_Manager::SELECT,
                            'label' => esc_html__( 'Display as.', ACPT_PLUGIN_NAME ),
                            'default' => 'H:i:s',
                            'options' => [
                                    '' => Translator::translate("--Select--"),
                                    'text' => 'Plain text',
                                    'link' => 'Link',
                            ],
                            'description' => esc_html__( 'Render this field as', ACPT_PLUGIN_NAME ),
                    ]
            );

            $this->add_control(
                    'acpt_phone_format',
                    [
                            'type' => Controls_Manager::SELECT,
                            'label' => esc_html__( 'Phone format.', ACPT_PLUGIN_NAME ),
                            'default' => Phone::FORMAT_E164,
                            'options' => [
                                '' => Translator::translate("--Select--"),
                                Phone::FORMAT_E164 => Phone::FORMAT_E164,
                                Phone::FORMAT_INTERNATIONAL => Phone::FORMAT_INTERNATIONAL,
                                Phone::FORMAT_NATIONAL => Phone::FORMAT_NATIONAL,
                            ],
                            'description' => esc_html__( 'Render this field as', ACPT_PLUGIN_NAME ),
                    ]
            );
        }

        // Group 13
        if(in_array($this->boxFieldModel->getType(), $contexts['group13'])){
            $this->add_control(
                'acpt_render',
                [
                    'type' => Controls_Manager::SELECT,
                    'label' => esc_html__( 'Display as.', ACPT_PLUGIN_NAME ),
                    'default' => 'H:i:s',
                    'options' => [
                        '' => Translator::translate("--Select--"),
                        'value' => 'Value',
                        'label' => 'label',
                    ],
                    'description' => esc_html__( 'Render this field as', ACPT_PLUGIN_NAME ),
                ]
            );
        }

        $this->end_controls_section();
    }

	/**
	 * Render the widget
	 * @throws \Exception
	 */
    protected function render()
    {
        $settings = $this->get_controls_settings();

        echo WidgetRender::render($this->boxFieldModel, $settings);
    }

	/**
	 * @return array
	 */
    private function getContexts()
    {
    	return [
		    'group1'  => [MetaFieldModel::ADDRESS_TYPE, MetaFieldModel::IMAGE_TYPE, MetaFieldModel::VIDEO_TYPE, MetaFieldModel::COLOR_TYPE, MetaFieldModel::TOGGLE_TYPE, MetaFieldModel::IMAGE_SLIDER_TYPE],
		    'group2'  => [MetaFieldModel::URL_TYPE],
		    'group3'  => [MetaFieldModel::DATE_TYPE, MetaFieldModel::DATE_RANGE_TYPE],
		    'group4'  => [MetaFieldModel::GALLERY_TYPE],
		    'group5'  => [MetaFieldModel::DATE_TIME_TYPE],
		    'group6'  => [MetaFieldModel::TIME_TYPE],
		    'group7'  => [MetaFieldModel::EMAIL_TYPE],
		    'group8'  => [MetaFieldModel::REPEATER_TYPE],
		    'group9'  => [MetaFieldModel::FLEXIBLE_CONTENT_TYPE],
		    'group10' => [MetaFieldModel::COUNTRY_TYPE],
		    'group11' => [MetaFieldModel::AUDIO_TYPE, MetaFieldModel::AUDIO_MULTI_TYPE],
		    'group12' => [MetaFieldModel::PHONE_TYPE],
		    'group13' => [MetaFieldModel::CHECKBOX_TYPE, MetaFieldModel::RADIO_TYPE, MetaFieldModel::SELECT_TYPE, MetaFieldModel::SELECT_MULTI_TYPE],
	    ];
    }
}
