<?php
/**
 * The widget's style controls.
 *
 * The fourteen style sections arrive in Stage 6b. This file exists now so the
 * widget can call into it from the outset and the two stages do not have to
 * touch each other's code.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Elementor_Style_Controls
 */
class KDNA_WH_Elementor_Style_Controls {

	/**
	 * Adds the style sections to the widget.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	public static function register( $widget ) {
		/*
		 * Stage 6b fills this in: form container, country selector, input
		 * field, labels and help text, submit button, loading state, results
		 * container, result value, band label, band scale, result body copy,
		 * metadata line, result call to action, and the message states.
		 *
		 * Every selector written here must target the plugin's own classes.
		 * Nothing may depend on .elementor-widget-container, which does not
		 * exist under Atomic markup.
		 */
		unset( $widget );
	}
}
