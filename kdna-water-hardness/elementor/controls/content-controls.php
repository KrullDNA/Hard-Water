<?php
/**
 * The widget's content controls.
 *
 * Six sections, per the specification: Layout, Country selector, Postcode
 * field, Submit button, Results display, and Copy overrides. Plus the preview
 * state control, which exists only so the results panel can be styled in the
 * editor without hunting for a postcode that happens to produce the state you
 * are trying to style.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

/**
 * Class KDNA_WH_Elementor_Content_Controls
 */
class KDNA_WH_Elementor_Content_Controls {

	/**
	 * Adds every content section to the widget.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	public static function register( $widget ) {
		self::layout( $widget );
		self::country_selector( $widget );
		self::postcode_field( $widget );
		self::submit_button( $widget );
		self::results_display( $widget );
		self::copy_overrides( $widget );
		self::preview( $widget );
	}

	/*
	 * -----------------------------------------------------------------------
	 * 1. Layout
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Layout section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function layout( $widget ) {
		$widget->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'result_behaviour',
			array(
				'label'       => __( 'Result behaviour', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'below',
				'options'     => array(
					'below'   => __( 'Show below the form', 'kdna-water-hardness' ),
					'replace' => __( 'Replace the form', 'kdna-water-hardness' ),
				),
				'description' => __( 'Replacing the form suits a landing page where the answer is the whole point. Showing it below suits a product page, where trying a second postcode should be easy.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_responsive_control(
			'content_alignment',
			array(
				'label'     => __( 'Alignment', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'kdna-water-hardness' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centre', 'kdna-water-hardness' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'kdna-water-hardness' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh' => 'text-align: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'form_layout',
			array(
				'label'        => __( 'Form layout', 'kdna-water-hardness' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'inline',
				'options'      => array(
					'stacked' => __( 'Stacked', 'kdna-water-hardness' ),
					'inline'  => __( 'Inline, button beside the field', 'kdna-water-hardness' ),
				),
				'prefix_class' => 'kdna-wh-layout-',
			)
		);

		$widget->add_responsive_control(
			'max_width',
			array(
				'label'      => __( 'Maximum width', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 200,
						'max' => 1200,
					),
					'%'  => array(
						'min' => 20,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 2. Country selector
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Country selector section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function country_selector( $widget ) {
		$widget->start_controls_section(
			'section_country',
			array(
				'label' => __( 'Country selector', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'selector_visibility',
			array(
				'label'       => __( 'Show the selector', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => array(
					'auto' => __( 'Automatically, when more than one country has data', 'kdna-water-hardness' ),
					'show' => __( 'Always', 'kdna-water-hardness' ),
					'hide' => __( 'Never', 'kdna-water-hardness' ),
				),
				'description' => __( 'Left on automatic, the selector appears by itself the moment a second country is imported, and stays hidden until then.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'selector_label',
			array(
				'label'       => __( 'Label', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Country', 'kdna-water-hardness' ),
				'label_block' => true,
				'condition'   => array( 'selector_visibility!' => 'hide' ),
			)
		);

		$widget->add_control(
			'default_country',
			array(
				'label'       => __( 'Starting country', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => self::country_options(),
				'description' => __( 'Leave empty to use the visitor\'s own country, which falls back to Australia. Setting one here overrides that detection.', 'kdna-water-hardness' ),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * The countries that can be chosen from, which is those holding data.
	 *
	 * @return array
	 */
	private static function country_options() {
		$options = array( '' => __( 'Detect the visitor\'s country', 'kdna-water-hardness' ) );

		foreach ( KDNA_WH_Countries::available() as $code => $country ) {
			$options[ $code ] = $country['name'];
		}

		return $options;
	}

	/*
	 * -----------------------------------------------------------------------
	 * 3. Postcode field
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Postcode field section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function postcode_field( $widget ) {
		$widget->start_controls_section(
			'section_field',
			array(
				'label' => __( 'Postcode field', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'show_label',
			array(
				'label'       => __( 'Show the label', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Hiding it keeps the label for screen readers, so the field is never unlabelled.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'label_source',
			array(
				'label'   => __( 'Label', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default' => __( 'Use the country\'s own wording', 'kdna-water-hardness' ),
					'custom'  => __( 'Write my own', 'kdna-water-hardness' ),
				),
			)
		);

		$widget->add_control(
			'label_custom',
			array(
				'label'       => __( 'Custom label', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( 'Postcode', 'kdna-water-hardness' ),
				'condition'   => array( 'label_source' => 'custom' ),
				'description' => __( 'This stays the same in every country, where the default becomes ZIP Code in the United States and Postal Code in Canada.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'placeholder_source',
			array(
				'label'   => __( 'Example shown in the field', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default' => __( 'Use the country\'s own example', 'kdna-water-hardness' ),
					'custom'  => __( 'Write my own', 'kdna-water-hardness' ),
				),
			)
		);

		$widget->add_control(
			'placeholder_custom',
			array(
				'label'       => __( 'Custom example', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => '3000',
				'condition'   => array( 'placeholder_source' => 'custom' ),
			)
		);

		$widget->add_control(
			'help_text',
			array(
				'label'       => __( 'Help text', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'label_block' => true,
				'description' => __( 'Shown under the field, and read out with it.', 'kdna-water-hardness' ),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 4. Submit button
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Submit button section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function submit_button( $widget ) {
		$widget->start_controls_section(
			'section_button',
			array(
				'label' => __( 'Submit button', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'button_text',
			array(
				'label'       => __( 'Button text', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Check my water', 'kdna-water-hardness' ),
				'label_block' => true,
			)
		);

		$widget->add_control(
			'loading_text',
			array(
				'label'       => __( 'While it is checking', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Checking…', 'kdna-water-hardness' ),
				'label_block' => true,
			)
		);

		$widget->add_control(
			'button_icon',
			array(
				'label' => __( 'Icon', 'kdna-water-hardness' ),
				'type'  => Controls_Manager::ICONS,
			)
		);

		$widget->add_control(
			'icon_position',
			array(
				'label'     => __( 'Icon position', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'before',
				'options'   => array(
					'before' => __( 'Before the text', 'kdna-water-hardness' ),
					'after'  => __( 'After the text', 'kdna-water-hardness' ),
				),
				'condition' => array( 'button_icon[value]!' => '' ),
			)
		);

		$widget->add_responsive_control(
			'button_full_width',
			array(
				'label'        => __( 'Full width', 'kdna-water-hardness' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'kdna-wh-button-full-',
				'return_value' => 'yes',
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 5. Results display
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Results display section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function results_display( $widget ) {
		$widget->start_controls_section(
			'section_results',
			array(
				'label' => __( 'Results display', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'show_value',
			array(
				'label'       => __( 'Show the figure', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Turn this off where only the band matters and a number would be noise.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'show_scale',
			array(
				'label'   => __( 'Show the band scale', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$widget->add_control(
			'show_zone_name',
			array(
				'label'       => __( 'Show the supply zone', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Naming the zone is itself a credibility signal: it shows the figure is for their supply, not a regional guess.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'show_utility',
			array(
				'label'   => __( 'Show the water authority', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$widget->add_control(
			'show_source',
			array(
				'label'       => __( 'Show the source link', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Links to the published report the figure came from.', 'kdna-water-hardness' ),
			)
		);

		$widget->add_control(
			'unit_mode',
			array(
				'label'   => __( 'Unit', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'country',
				'options' => array(
					'country' => __( 'Use the country\'s own unit', 'kdna-water-hardness' ),
					'forced'  => __( 'Always use one unit', 'kdna-water-hardness' ),
				),
			)
		);

		$widget->add_control(
			'unit_forced',
			array(
				'label'     => __( 'Which unit', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => KDNA_WH_Units::CANONICAL,
				'options'   => KDNA_WH_Units::options(),
				'condition' => array( 'unit_mode' => 'forced' ),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 6. Copy overrides
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Copy overrides section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function copy_overrides( $widget ) {
		$widget->start_controls_section(
			'section_copy',
			array(
				'label' => __( 'Copy overrides', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'copy_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Only for this one placement.', 'kdna-water-hardness' ),
					esc_html__( 'Anything left empty falls back to the copy set under Water Hardness, Settings, which is where the wording for the whole site belongs. Use this to give a landing page its own angle without duplicating everything.', 'kdna-water-hardness' )
				),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$widget->add_control(
			'copy_claims_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Keep claims to appearance only: what the product does to the water, and how skin looks and feels. Nothing implying a therapeutic effect.', 'kdna-water-hardness' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'override_state',
			array(
				'label'   => __( 'Which result', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'soft',
				'options' => self::state_options(),
			)
		);

		$repeater->add_control(
			'override_heading',
			array(
				'label'       => __( 'Heading', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'override_body',
			array(
				'label'       => __( 'Body copy', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 5,
				'label_block' => true,
			)
		);

		$widget->add_control(
			'copy_overrides',
			array(
				'label'       => __( 'Overrides', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ override_state }}}',
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * The results a copy override can be written for.
	 *
	 * The band labels come from the default configuration rather than a
	 * country's, because the panel is drawn before a country is chosen.
	 *
	 * @return array
	 */
	private static function state_options() {
		$options = array();

		foreach ( KDNA_WH_Bands::default_bands() as $key => $band ) {
			$options[ $key ] = $band['label'];
		}

		$options['inconclusive'] = __( 'Inconclusive', 'kdna-water-hardness' );
		$options['no_match']     = __( 'No data for that postcode', 'kdna-water-hardness' );

		return $options;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The editor preview
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The preview state control.
	 *
	 * Editor only, and enforced as such in the renderer rather than here: the
	 * setting is simply never acted on outside the editor, so there is no way
	 * for a saved page to carry a fake result to a visitor.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function preview( $widget ) {
		$widget->start_controls_section(
			'section_preview',
			array(
				'label' => __( 'Editor preview', 'kdna-water-hardness' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'preview_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Shows a sample result here in the editor so you can style every state without hunting for a postcode that produces one. It has no effect on the live page.', 'kdna-water-hardness' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$widget->add_control(
			'preview_state',
			array(
				'label'   => __( 'Preview', 'kdna-water-hardness' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'form',
				'options' => array(
					'form'         => __( 'Form only', 'kdna-water-hardness' ),
					'confident'    => __( 'A confident result', 'kdna-water-hardness' ),
					'range'        => __( 'A range across zones', 'kdna-water-hardness' ),
					'inconclusive' => __( 'Inconclusive', 'kdna-water-hardness' ),
					'no_match'     => __( 'No data for that postcode', 'kdna-water-hardness' ),
					'error'        => __( 'A validation error', 'kdna-water-hardness' ),
				),
			)
		);

		$widget->end_controls_section();
	}
}
