<?php
/**
 * The widget's style controls.
 *
 * Fourteen sections, per the specification. Nick is a designer: a widget he
 * cannot restyle from the panel is a broken widget, so every visible element
 * has a control and every dimensional value is responsive.
 *
 * Two rules run through all of it.
 *
 * Every selector begins at {{WRAPPER}} and then names one of the plugin's own
 * classes. Nothing names .elementor-widget-container, which does not exist
 * under Atomic markup, so nothing here stops working when the optimized markup
 * experiment is switched on.
 *
 * Where the script sets a colour of its own, from a band's configured colour,
 * it sets a custom property rather than an inline style. That leaves these
 * controls able to override it through the ordinary cascade instead of needing
 * !important.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

/**
 * Class KDNA_WH_Elementor_Style_Controls
 */
class KDNA_WH_Elementor_Style_Controls {

	/**
	 * The units offered wherever a dimension is set.
	 */
	const UNITS = array( 'px', 'em', 'rem', '%' );

	/**
	 * Adds every style section to the widget.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	public static function register( $widget ) {
		self::form_container( $widget );
		self::country_selector( $widget );
		self::input_field( $widget );
		self::labels( $widget );
		self::submit_button( $widget );
		self::loading_state( $widget );
		self::results_container( $widget );
		self::result_value( $widget );
		self::band_label( $widget );
		self::band_scale( $widget );
		self::body_copy( $widget );
		self::metadata( $widget );
		self::cta_button( $widget );
		self::message_states( $widget );
	}

	/**
	 * Starts a style section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @param string                 $id     Section id.
	 * @param string                 $label  Section label.
	 * @return void
	 */
	private static function open( $widget, $id, $label ) {
		$widget->start_controls_section(
			$id,
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * 1. Form container
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Form container section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function form_container( $widget ) {
		self::open( $widget, 'style_form', __( 'Form container', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'form_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-wh__form',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'form_border',
				'selector' => '{{WRAPPER}} .kdna-wh__form',
			)
		);

		$widget->add_responsive_control(
			'form_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'form_shadow',
				'selector' => '{{WRAPPER}} .kdna-wh__form',
			)
		);

		$widget->add_responsive_control(
			'form_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'form_margin',
			array(
				'label'      => __( 'Margin', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'form_gap',
			array(
				'label'      => __( 'Space between fields', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__form' => 'gap: {{SIZE}}{{UNIT}};',
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
		self::open( $widget, 'style_selector', __( 'Country selector', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'selector_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__select',
			)
		);

		$widget->start_controls_tabs( 'selector_tabs' );

		$widget->start_controls_tab( 'selector_normal', array( 'label' => __( 'Normal', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			'selector_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__select' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'selector_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__select' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'selector_arrow_colour',
			array(
				'label'       => __( 'Arrow colour', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::COLOR,
				'description' => __( 'Some browsers draw their own arrow and ignore this.', 'kdna-water-hardness' ),
				'selectors'   => array(
					'{{WRAPPER}} .kdna-wh__select' => 'accent-color: {{VALUE}}; --kdna-wh-arrow: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( 'selector_focus', array( 'label' => __( 'Focus', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			'selector_focus_border',
			array(
				'label'     => __( 'Border colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__select:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'selector_focus_shadow',
				'selector' => '{{WRAPPER}} .kdna-wh__select:focus',
			)
		);

		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'selector_border',
				'selector' => '{{WRAPPER}} .kdna-wh__select',
			)
		);

		$widget->add_responsive_control(
			'selector_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'selector_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 3. Input field
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Input field section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function input_field( $widget ) {
		self::open( $widget, 'style_input', __( 'Input field', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__input',
			)
		);

		$widget->start_controls_tabs( 'input_tabs' );

		$widget->start_controls_tab( 'input_normal', array( 'label' => __( 'Normal', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			'input_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__input' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'input_placeholder_colour',
			array(
				'label'     => __( 'Example text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__input::placeholder' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->add_control(
			'input_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__input' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( 'input_focus', array( 'label' => __( 'Focus', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			'input_focus_border',
			array(
				'label'     => __( 'Border colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__input:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'input_focus_shadow',
				'selector' => '{{WRAPPER}} .kdna-wh__input:focus',
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( 'input_error', array( 'label' => __( 'Error', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			'input_error_border',
			array(
				'label'     => __( 'Border colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh--has-error .kdna-wh__input' => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'input_error_text',
			array(
				'label'     => __( 'Message colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__error' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_error_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__error',
			)
		);

		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} .kdna-wh__input',
			)
		);

		$widget->add_responsive_control(
			'input_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'input_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'input_min_height',
			array(
				'label'      => __( 'Minimum height', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 120 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__input' => 'min-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 4. Labels and help text
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Labels and help text section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function labels( $widget ) {
		self::open( $widget, 'style_labels', __( 'Labels and help text', 'kdna-water-hardness' ) );

		$widget->add_control(
			'label_heading',
			array(
				'label' => __( 'Labels', 'kdna-water-hardness' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__label',
			)
		);

		$widget->add_control(
			'label_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__label' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'label_spacing',
			array(
				'label'      => __( 'Space below', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'help_heading',
			array(
				'label'     => __( 'Help text', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'help_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__help',
			)
		);

		$widget->add_control(
			'help_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__help' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->add_responsive_control(
			'help_spacing',
			array(
				'label'      => __( 'Space above', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__help' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 5. Submit button
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Submit button section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function submit_button( $widget ) {
		self::open( $widget, 'style_button', __( 'Submit button', 'kdna-water-hardness' ) );

		self::button_controls( $widget, 'button', '{{WRAPPER}} .kdna-wh__button' );

		$widget->add_responsive_control(
			'button_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .kdna-wh__icon'     => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'button_icon_gap',
			array(
				'label'      => __( 'Icon gap', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__button' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'button_disabled_heading',
			array(
				'label'     => __( 'While it is checking', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'button_disabled_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__button:disabled' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'button_disabled_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__button:disabled' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'button_disabled_opacity',
			array(
				'label'     => __( 'Opacity', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__button:disabled' => 'opacity: {{SIZE}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * The controls both buttons share: typography, spacing, and the normal,
	 * hover and focus states.
	 *
	 * Written once because the submit button and the result's call to action
	 * need identical treatment, and two copies would drift.
	 *
	 * @param \Elementor\Widget_Base $widget   The widget.
	 * @param string                 $prefix   Control id prefix.
	 * @param string                 $selector The element to style.
	 * @return void
	 */
	private static function button_controls( $widget, $prefix, $selector ) {
		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $prefix . '_typography',
				'selector' => $selector,
			)
		);

		$widget->start_controls_tabs( $prefix . '_tabs' );

		$widget->start_controls_tab( $prefix . '_tab_normal', array( 'label' => __( 'Normal', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			$prefix . '_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);

		$widget->add_control(
			$prefix . '_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
			)
		);

		$widget->add_control(
			$prefix . '_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'border-color: {{VALUE}};' ),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $prefix . '_shadow',
				'selector' => $selector,
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( $prefix . '_tab_hover', array( 'label' => __( 'Hover', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			$prefix . '_hover_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector . ':hover' => 'color: {{VALUE}}; opacity: 1;' ),
			)
		);

		$widget->add_control(
			$prefix . '_hover_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector . ':hover' => 'background-color: {{VALUE}}; opacity: 1;' ),
			)
		);

		$widget->add_control(
			$prefix . '_hover_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector . ':hover' => 'border-color: {{VALUE}};' ),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $prefix . '_hover_shadow',
				'selector' => $selector . ':hover',
			)
		);

		$widget->add_control(
			$prefix . '_transition',
			array(
				'label'      => __( 'Transition', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'default'    => array(
					'size' => 0.2,
					'unit' => 's',
				),
				'range'      => array(
					's' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.05,
					),
				),
				'selectors'  => array( $selector => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( $prefix . '_tab_focus', array( 'label' => __( 'Focus', 'kdna-water-hardness' ) ) );

		$widget->add_control(
			$prefix . '_focus_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector . ':focus-visible' => 'color: {{VALUE}}; opacity: 1;' ),
			)
		);

		$widget->add_control(
			$prefix . '_focus_background',
			array(
				'label'     => __( 'Background', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector . ':focus-visible' => 'background-color: {{VALUE}}; opacity: 1;' ),
			)
		);

		$widget->add_control(
			$prefix . '_focus_outline',
			array(
				'label'       => __( 'Outline colour', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::COLOR,
				'description' => __( 'A visible focus outline is what lets someone using a keyboard see where they are. Change its colour rather than removing it.', 'kdna-water-hardness' ),
				'selectors'   => array( $selector . ':focus-visible' => 'outline-color: {{VALUE}};' ),
			)
		);

		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => $prefix . '_border',
				'selector'  => $selector,
				'separator' => 'before',
			)
		);

		$widget->add_responsive_control(
			$prefix . '_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			$prefix . '_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * 6. Loading state
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loading state section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function loading_state( $widget ) {
		self::open( $widget, 'style_loading', __( 'Loading state', 'kdna-water-hardness' ) );

		$widget->add_control(
			'spinner_colour',
			array(
				'label'     => __( 'Spinner colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__spinner' => 'border-color: {{VALUE}}; border-right-color: transparent;',
				),
			)
		);

		$widget->add_responsive_control(
			'spinner_size',
			array(
				'label'      => __( 'Spinner size', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__spinner' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'spinner_thickness',
			array(
				'label'      => __( 'Spinner thickness', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 10 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__spinner' => 'border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'overlay_colour',
			array(
				'label'     => __( 'Overlay colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__overlay' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'overlay_opacity',
			array(
				'label'     => __( 'Overlay opacity', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__overlay' => 'opacity: {{SIZE}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 7. Results container
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Results container section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function results_container( $widget ) {
		self::open( $widget, 'style_result', __( 'Results container', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'result_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-wh__result',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'result_border',
				'selector' => '{{WRAPPER}} .kdna-wh__result',
			)
		);

		$widget->add_responsive_control(
			'result_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__result' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'result_shadow',
				'selector' => '{{WRAPPER}} .kdna-wh__result',
			)
		);

		$widget->add_responsive_control(
			'result_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__result' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'result_margin',
			array(
				'label'      => __( 'Margin', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__result' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'result_animation',
			array(
				'label'        => __( 'Entry animation', 'kdna-water-hardness' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'fade',
				'separator'    => 'before',
				'options'      => array(
					''      => __( 'None', 'kdna-water-hardness' ),
					'fade'  => __( 'Fade in', 'kdna-water-hardness' ),
					'slide' => __( 'Slide up and fade', 'kdna-water-hardness' ),
				),
				'description'  => __( 'Skipped automatically for anyone who has asked their device to reduce motion.', 'kdna-water-hardness' ),
				'prefix_class' => 'kdna-wh-animate-',
			)
		);

		$widget->add_control(
			'result_animation_duration',
			array(
				'label'      => __( 'Animation duration', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array(
					's' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.05,
					),
				),
				'condition'  => array( 'result_animation!' => '' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh' => '--kdna-wh-animation-duration: {{SIZE}}s;',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 8. Result value
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Result value section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function result_value( $widget ) {
		self::open( $widget, 'style_value', __( 'Result figure', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'value_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__number',
			)
		);

		$widget->add_control(
			'value_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__number' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'unit_heading',
			array(
				'label'     => __( 'Unit', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'unit_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__unit',
			)
		);

		$widget->add_control(
			'unit_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__unit' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->add_responsive_control(
			'value_spacing',
			array(
				'label'      => __( 'Space below', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__value' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 9. Band label
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Band label section, including a colour per band.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function band_label( $widget ) {
		self::open( $widget, 'style_band', __( 'Band label', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'band_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__band-label',
			)
		);

		$widget->add_control(
			'band_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__band-label' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'band_background',
			array(
				'label'       => __( 'Background', 'kdna-water-hardness' ),
				'type'        => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use the colour set for each band under Water Hardness, Settings.', 'kdna-water-hardness' ),
				'selectors'   => array(
					'{{WRAPPER}} .kdna-wh__band-label' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'band_border',
				'selector' => '{{WRAPPER}} .kdna-wh__band-label',
			)
		);

		$widget->add_responsive_control(
			'band_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__band-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'band_padding',
			array(
				'label'      => __( 'Padding', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__band-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'band_per_band_heading',
			array(
				'label'     => __( 'Colour per band', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'band_per_band_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Overrides the colours set under Water Hardness, Settings, for this placement only. Leave a band empty to keep its own colour.', 'kdna-water-hardness' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		foreach ( KDNA_WH_Bands::default_bands() as $key => $band ) {
			$widget->add_control(
				'band_bg_' . $key,
				array(
					/* translators: %s: band label, e.g. Soft. */
					'label'     => sprintf( __( '%s background', 'kdna-water-hardness' ), $band['label'] ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .kdna-wh__band[data-band="' . $key . '"] .kdna-wh__band-label' => 'background-color: {{VALUE}};',
					),
				)
			);

			$widget->add_control(
				'band_fg_' . $key,
				array(
					/* translators: %s: band label, e.g. Soft. */
					'label'     => sprintf( __( '%s text', 'kdna-water-hardness' ), $band['label'] ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .kdna-wh__band[data-band="' . $key . '"] .kdna-wh__band-label' => 'color: {{VALUE}};',
					),
				)
			);
		}

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 10. Band scale
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Band scale section, including a colour per band.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function band_scale( $widget ) {
		self::open( $widget, 'style_scale', __( 'Band scale', 'kdna-water-hardness' ) );

		$widget->add_responsive_control(
			'scale_height',
			array(
				'label'      => __( 'Height', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__scale-track' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'scale_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => self::UNITS,
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__scale-track'      => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .kdna-wh__scale-band:first-child' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .kdna-wh__scale-band:last-child'  => 'border-top-right-radius: {{RIGHT}}{{UNIT}}; border-bottom-right-radius: {{BOTTOM}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'scale_spacing_above',
			array(
				'label'      => __( 'Space above', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__scale' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'scale_spacing_below',
			array(
				'label'      => __( 'Space below', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__scale' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'scale_band_heading',
			array(
				'label'     => __( 'Colour per band', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		foreach ( KDNA_WH_Bands::default_bands() as $key => $band ) {
			$widget->add_control(
				'scale_colour_' . $key,
				array(
					'label'     => $band['label'],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .kdna-wh__scale-band[data-band="' . $key . '"]' => 'background-color: {{VALUE}};',
					),
				)
			);
		}

		$widget->add_control(
			'scale_marker_heading',
			array(
				'label'     => __( 'Marker', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'marker_shape',
			array(
				'label'     => __( 'Shape', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'circle',
				'options'   => array(
					'circle'  => __( 'Circle', 'kdna-water-hardness' ),
					'square'  => __( 'Square', 'kdna-water-hardness' ),
					'diamond' => __( 'Diamond', 'kdna-water-hardness' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__scale-marker' => 'border-radius: {{VALUE}};',
				),
				'selectors_dictionary' => array(
					'circle'  => '50%',
					'square'  => '2px',
					'diamond' => '2px',
				),
			)
		);

		$widget->add_control(
			'marker_rotate',
			array(
				'label'     => __( 'Rotate', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HIDDEN,
				'default'   => 'yes',
				'condition' => array( 'marker_shape' => 'diamond' ),
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__scale-marker' => 'transform: rotate( 45deg );',
				),
			)
		);

		$widget->add_control(
			'marker_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__scale-marker' => 'background-color: {{VALUE}}; box-shadow: 0 0 0 1px {{VALUE}};',
					'{{WRAPPER}} .kdna-wh__scale-span'   => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'marker_border_colour',
			array(
				'label'     => __( 'Ring colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__scale-marker' => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'marker_size',
			array(
				'label'      => __( 'Size', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 50 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__scale-marker' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; margin-left: calc( {{SIZE}}{{UNIT}} / -2 );',
					'{{WRAPPER}} .kdna-wh__scale-span'   => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'scale_labels_heading',
			array(
				'label'     => __( 'Scale labels', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'scale_label_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__scale-labels li',
			)
		);

		$widget->add_control(
			'scale_label_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__scale-labels li' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 11. Result body copy
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Result body copy section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function body_copy( $widget ) {
		self::open( $widget, 'style_body', __( 'Result copy', 'kdna-water-hardness' ) );

		$widget->add_control(
			'heading_heading',
			array(
				'label' => __( 'Heading', 'kdna-water-hardness' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'result_heading_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__heading',
			)
		);

		$widget->add_control(
			'result_heading_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__heading' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'result_heading_spacing',
			array(
				'label'      => __( 'Space below', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'body_heading',
			array(
				'label'     => __( 'Body', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'body_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__body',
			)
		);

		$widget->add_control(
			'body_colour',
			array(
				'label'     => __( 'Colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__body' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'body_link_colour',
			array(
				'label'     => __( 'Link colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__body a' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'body_link_hover_colour',
			array(
				'label'     => __( 'Link hover colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__body a:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'body_paragraph_spacing',
			array(
				'label'      => __( 'Space between paragraphs', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__body p' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 12. Metadata line
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Metadata line section: the zone, the utility and the source link.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function metadata( $widget ) {
		self::open( $widget, 'style_meta', __( 'Metadata line', 'kdna-water-hardness' ) );

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_typography',
				'selector' => '{{WRAPPER}} .kdna-wh__meta, {{WRAPPER}} .kdna-wh__zone',
			)
		);

		$widget->add_control(
			'meta_zone_colour',
			array(
				'label'     => __( 'Zone name colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__meta'      => 'color: {{VALUE}}; opacity: 1;',
					'{{WRAPPER}} .kdna-wh__zone-name' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'meta_utility_colour',
			array(
				'label'     => __( 'Utility colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__zone-utility' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->add_control(
			'meta_source_colour',
			array(
				'label'     => __( 'Source link colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__zone-source' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$widget->add_control(
			'meta_source_hover_colour',
			array(
				'label'     => __( 'Source link hover colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__zone-source:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'meta_separator_colour',
			array(
				'label'     => __( 'Separator colour', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-wh__zone' => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'meta_spacing',
			array(
				'label'      => __( 'Space above', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__zones' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 13. Result call to action
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Result CTA section.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function cta_button( $widget ) {
		self::open( $widget, 'style_cta', __( 'Result button', 'kdna-water-hardness' ) );

		self::button_controls( $widget, 'cta', '{{WRAPPER}} .kdna-wh__cta' );

		$widget->add_responsive_control(
			'cta_alignment',
			array(
				'label'     => __( 'Alignment', 'kdna-water-hardness' ),
				'type'      => Controls_Manager::CHOOSE,
				'separator' => 'before',
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
					'{{WRAPPER}} .kdna-wh__cta-wrap' => 'text-align: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'cta_spacing',
			array(
				'label'      => __( 'Space above', 'kdna-water-hardness' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-wh__cta-wrap' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * 14. Message states
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Message states section.
	 *
	 * Inconclusive, no match and error each carry different weight, and the
	 * specification is right that they should not be forced to share styling:
	 * an inconclusive answer is a considered explanation, not a failure, and
	 * should not look like one.
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 * @return void
	 */
	private static function message_states( $widget ) {
		self::open( $widget, 'style_messages', __( 'Message states', 'kdna-water-hardness' ) );

		$states = array(
			'inconclusive' => array(
				'label'    => __( 'Inconclusive', 'kdna-water-hardness' ),
				'selector' => '{{WRAPPER}} .kdna-wh__panel--inconclusive',
			),
			'no_match'     => array(
				'label'    => __( 'No data for that postcode', 'kdna-water-hardness' ),
				'selector' => '{{WRAPPER}} .kdna-wh__panel--no_match',
			),
			'error'        => array(
				'label'    => __( 'Error', 'kdna-water-hardness' ),
				'selector' => '{{WRAPPER}} .kdna-wh__error',
			),
		);

		foreach ( $states as $key => $state ) {
			$widget->add_control(
				'message_' . $key . '_heading',
				array(
					'label'     => $state['label'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$widget->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'message_' . $key . '_typography',
					'selector' => $state['selector'],
				)
			);

			$widget->add_control(
				'message_' . $key . '_colour',
				array(
					'label'     => __( 'Text colour', 'kdna-water-hardness' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $state['selector'] => 'color: {{VALUE}};' ),
				)
			);

			$widget->add_control(
				'message_' . $key . '_background',
				array(
					'label'     => __( 'Background', 'kdna-water-hardness' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $state['selector'] => 'background-color: {{VALUE}};' ),
				)
			);

			$widget->add_control(
				'message_' . $key . '_accent',
				array(
					'label'       => __( 'Icon and accent colour', 'kdna-water-hardness' ),
					'type'        => Controls_Manager::COLOR,
					'description' => __( 'Colours the marker beside the message and the bar down its side.', 'kdna-water-hardness' ),
					'selectors'   => array( $state['selector'] => '--kdna-wh-accent: {{VALUE}};' ),
				)
			);

			$widget->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'message_' . $key . '_border',
					'selector' => $state['selector'],
				)
			);

			$widget->add_responsive_control(
				'message_' . $key . '_padding',
				array(
					'label'      => __( 'Padding', 'kdna-water-hardness' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => self::UNITS,
					'selectors'  => array(
						$state['selector'] => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
		}

		$widget->end_controls_section();
	}
}
