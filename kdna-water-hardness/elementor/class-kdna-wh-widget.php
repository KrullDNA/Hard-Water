<?php
/**
 * The Water Hardness Lookup Elementor widget.
 *
 * Built for Elementor's Atomic markup from the outset rather than retrofitted.
 * Three things follow from that and none of them are optional:
 *
 * has_widget_inner_wrapper() reports false when the optimized markup
 * experiment is on, so Elementor does not add a wrapper the styles would then
 * depend on.
 *
 * Nothing in any stylesheet targets .elementor-widget-container, because under
 * Atomic markup it does not exist.
 *
 * render() emits one wrapper div and no scaffolding around it.
 *
 * The markup itself comes from the same renderer the shortcode uses, so the
 * two cannot drift apart.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Class KDNA_WH_Widget
 */
class KDNA_WH_Widget extends Widget_Base {

	/**
	 * The widget's machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-water-hardness';
	}

	/**
	 * The name shown in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Water Hardness Lookup', 'kdna-water-hardness' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Which category it appears under.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( KDNA_WH_Elementor::CATEGORY );
	}

	/**
	 * What someone might search the panel for.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'water', 'hardness', 'postcode', 'lookup', 'zip', 'kdna' );
	}

	/**
	 * The stylesheets Elementor should load for this widget.
	 *
	 * Declaring them here is what keeps the promise that a page without the
	 * widget loads none of its assets.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		KDNA_WH_Shortcode::register_assets();
		self::register_widget_style();

		return array( KDNA_WH_Shortcode::HANDLE, 'kdna-wh-widget' );
	}

	/**
	 * The scripts Elementor should load for this widget.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		KDNA_WH_Shortcode::register_assets();

		return array( KDNA_WH_Shortcode::HANDLE );
	}

	/**
	 * Registers the widget's own stylesheet, which holds only what the layout
	 * controls need. Everything shared lives in the front-end stylesheet.
	 *
	 * @return void
	 */
	public static function register_widget_style() {
		if ( wp_style_is( 'kdna-wh-widget', 'registered' ) ) {
			return;
		}

		wp_register_style(
			'kdna-wh-widget',
			KDNA_WH_URL . 'elementor/widget.css',
			array( KDNA_WH_Shortcode::HANDLE ),
			KDNA_WH_VERSION
		);
	}

	/**
	 * Whether Elementor should wrap the widget in its own inner container.
	 *
	 * False under the optimized markup experiment, which is the whole point of
	 * Atomic architecture: one less div, and no styles written against a
	 * wrapper that may not be there.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		if ( ! isset( \Elementor\Plugin::$instance->experiments ) ) {
			return true;
		}

		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Elementor's own script handling is not needed: the widget's script binds
	 * itself on element ready.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Registers every control.
	 *
	 * @return void
	 */
	protected function register_controls() {
		KDNA_WH_Elementor_Content_Controls::register( $this );
		KDNA_WH_Elementor_Style_Controls::register( $this );
	}

	/**
	 * Renders the widget.
	 *
	 * One wrapper div, produced by the shared renderer. Nothing is echoed
	 * around it.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Everything below is escaped inside the renderer, which is the single
		// place this markup is built.
		echo KDNA_WH_Shortcode::render_tool( $this->build_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Turns Elementor's settings into the renderer's arguments.
	 *
	 * @param array $settings Settings for display.
	 * @return array
	 */
	protected function build_args( array $settings ) {
		$get = function ( $key, $default = '' ) use ( $settings ) {
			return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
		};

		/*
		 * Switchers need their own reader. Elementor stores an off switch as an
		 * empty string, which $get() cannot tell apart from never having been
		 * set, so reading one through $get() would quietly ignore every toggle
		 * a designer turned off and leave it on.
		 */
		$is_on = function ( $key, $default = true ) use ( $settings ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				return $default;
			}

			return 'yes' === $settings[ $key ];
		};

		$args = array(
			'country'          => $get( 'default_country' ),
			'show_selector'    => $get( 'selector_visibility', 'auto' ),
			'selector_label'   => $get( 'selector_label', __( 'Country', 'kdna-water-hardness' ) ),

			'label'            => 'custom' === $get( 'label_source', 'default' ) ? $get( 'label_custom' ) : '',
			'placeholder'      => 'custom' === $get( 'placeholder_source', 'default' ) ? $get( 'placeholder_custom' ) : '',
			'help_text'        => $get( 'help_text' ),
			'show_label'       => $is_on( 'show_label' ),

			'button_text'      => $get( 'button_text' ),
			'loading_text'     => $get( 'loading_text' ),
			'icon_position'    => $get( 'icon_position', 'before' ),

			'result_behaviour' => $get( 'result_behaviour', 'below' ),

			'show_scale'       => $is_on( 'show_scale' ),
			'show_zone_name'   => $is_on( 'show_zone_name' ),
			'show_utility'     => $is_on( 'show_utility' ),
			'show_source'      => $is_on( 'show_source' ),
			'show_value'       => $is_on( 'show_value' ),
			'unit'             => 'forced' === $get( 'unit_mode', 'country' ) ? $get( 'unit_forced' ) : '',

			'copy'             => $this->build_copy_overrides( $settings ),
			'preview'          => $get( 'preview_state', 'form' ),
			'class'            => $this->layout_classes( $settings ),
		);

		$args['icon'] = $this->render_icon( $settings );

		return $args;
	}

	/**
	 * The classes the layout controls need. Elementor's own responsive
	 * controls write CSS against these in the style controls.
	 *
	 * @param array $settings Settings for display.
	 * @return string
	 */
	protected function layout_classes( array $settings ) {
		$classes = array();

		if ( ! empty( $settings['form_layout'] ) ) {
			$classes[] = 'kdna-wh--form-' . sanitize_html_class( $settings['form_layout'] );
		}

		if ( ! empty( $settings['button_full_width'] ) && 'yes' === $settings['button_full_width'] ) {
			$classes[] = 'kdna-wh--button-full';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Renders the chosen button icon to markup.
	 *
	 * @param array $settings Settings for display.
	 * @return string
	 */
	protected function render_icon( array $settings ) {
		if ( empty( $settings['button_icon']['value'] ) ) {
			return '';
		}

		ob_start();
		\Elementor\Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) );

		return (string) ob_get_clean();
	}

	/**
	 * Collects the per-instance copy overrides out of the repeater.
	 *
	 * @param array $settings Settings for display.
	 * @return array
	 */
	protected function build_copy_overrides( array $settings ) {
		if ( empty( $settings['copy_overrides'] ) || ! is_array( $settings['copy_overrides'] ) ) {
			return array();
		}

		$copy = array();

		foreach ( $settings['copy_overrides'] as $row ) {
			if ( empty( $row['override_state'] ) ) {
				continue;
			}

			$copy[ $row['override_state'] ] = array(
				'heading' => isset( $row['override_heading'] ) ? $row['override_heading'] : '',
				'body'    => isset( $row['override_body'] ) ? $row['override_body'] : '',
			);
		}

		return $copy;
	}
}
