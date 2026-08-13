<?php
/**
 * Front-end rendering.
 *
 * The shortcode is the fallback path. The Elementor widget built in Stage 6 is
 * the primary one, and will render the same markup so both share one
 * stylesheet and one script.
 *
 * Assets are registered on every front-end request but enqueued only where the
 * tool actually appears, so a page without it loads nothing.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Shortcode
 */
class KDNA_WH_Shortcode {

	/**
	 * The shortcode tag.
	 */
	const TAG = 'kdna_water_hardness';

	/**
	 * Handle shared by the stylesheet and the script.
	 */
	const HANDLE = 'kdna-water-hardness';

	/**
	 * Counter so every instance on a page gets unique element ids, which the
	 * label and error message associations depend on.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Whether the script data has already been attached this request.
	 *
	 * @var bool
	 */
	private static $localised = false;

	/**
	 * Registers the shortcode and the asset hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_for_content' ), 11 );
	}

	/**
	 * Registers the assets, and enqueues them early when the shortcode can be
	 * seen in the content of the page being viewed.
	 *
	 * Registering and enqueuing are separate on purpose. The check below
	 * catches the ordinary case and gets the stylesheet into the head, where
	 * it belongs. Anything it misses, a widget, a block template, an Elementor
	 * layout, is caught when the shortcode itself renders.
	 *
	 * @return void
	 */
	public static function register_assets() {
		/*
		 * Called from wp_enqueue_scripts, and again by the Elementor widget,
		 * which renders inside an editor iframe where the ordinary hook may
		 * already have run.
		 */
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			self::HANDLE,
			KDNA_WH_URL . 'assets/css/kdna-water-hardness.css',
			array(),
			KDNA_WH_VERSION
		);

		wp_register_script(
			self::HANDLE,
			KDNA_WH_URL . 'assets/js/kdna-water-hardness.js',
			array(),
			KDNA_WH_VERSION,
			true
		);

	}

	/**
	 * Enqueues early when the shortcode can be seen in the content being
	 * viewed, so the stylesheet reaches the head rather than the footer.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_for_content() {
		if ( self::content_has_shortcode() ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Whether the post being viewed contains the shortcode.
	 *
	 * @return bool
	 */
	private static function content_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}

		return has_shortcode( $post->post_content, self::TAG );
	}

	/**
	 * Enqueues the assets and attaches the data the script needs.
	 *
	 * Safe to call more than once: WordPress ignores a repeat enqueue, and the
	 * data is only attached the first time.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		// The widget can reach here before the ordinary registration hook.
		self::register_assets();

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		if ( self::$localised ) {
			return;
		}

		self::$localised = true;

		wp_localize_script(
			self::HANDLE,
			'kdnaWaterHardness',
			array(
				'endpoint'  => KDNA_WH_REST::lookup_url(),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'countries' => KDNA_WH_Countries::for_script(),
				'strings'   => array(
					'loading'      => __( 'Checking…', 'kdna-water-hardness' ),
					'required'     => __( 'Enter your postcode.', 'kdna-water-hardness' ),
					'invalid'      => __( 'That does not look right. Check and try again.', 'kdna-water-hardness' ),
					'failed'       => __( 'Something went wrong looking that up. Please try again in a moment.', 'kdna-water-hardness' ),
					'spansZones'   => __( 'This postcode covers more than one water supply zone, so hardness varies across it.', 'kdna-water-hardness' ),
					'resultFor'    => __( 'Water hardness for', 'kdna-water-hardness' ),
					'sourceLabel'  => __( 'Source', 'kdna-water-hardness' ),
					'estimated'    => __( 'This figure is an estimate rather than a published reading for your zone.', 'kdna-water-hardness' ),
				),
			)
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'country'       => '',
				'show_selector' => 'auto',
				'button_text'   => '',
				'label'         => '',
				'placeholder'   => '',
				'class'         => '',
			),
			$atts,
			self::TAG
		);

		return self::render_tool(
			array(
				'country'       => $atts['country'],
				'show_selector' => $atts['show_selector'],
				'button_text'   => $atts['button_text'],
				'label'         => $atts['label'],
				'placeholder'   => $atts['placeholder'],
				'class'         => $atts['class'],
			)
		);
	}

	/**
	 * Renders the tool itself.
	 *
	 * The shortcode and the Elementor widget both come through here, so there
	 * is one piece of markup, one stylesheet and one script between them. The
	 * widget passes far more in, but nothing structural differs.
	 *
	 * @param array $args Everything the caller wants to set. See the defaults.
	 * @return string
	 */
	public static function render_tool( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				// Which country, and whether the visitor may change it.
				'country'          => '',
				'show_selector'    => 'auto',
				'selector_label'   => __( 'Country', 'kdna-water-hardness' ),

				// The postcode field.
				'label'            => '',
				'placeholder'      => '',
				'help_text'        => '',
				'show_label'       => true,

				// The button.
				'button_text'      => '',
				'loading_text'     => '',
				'icon'             => '',
				'icon_position'    => 'before',

				// Layout.
				'result_behaviour' => 'below',

				// What the result shows.
				'show_scale'       => true,
				'show_zone_name'   => true,
				'show_utility'     => true,
				'show_source'      => true,
				'show_value'       => true,
				'unit'             => '',

				// Per-instance copy, overriding the global settings.
				'copy'             => array(),

				// Editor only. Never set on the front end.
				'preview'          => '',

				// Markup.
				'class'            => '',
			)
		);

		$available = KDNA_WH_Countries::available();

		// Nothing has been imported yet. A visitor should see nothing at all
		// rather than a broken form; an administrator should see why.
		if ( ! $available ) {
			return self::render_empty_notice();
		}

		self::enqueue_assets();
		self::$instance++;

		/*
		 * A country named by the caller is a deliberate choice by whoever built
		 * the page, so it wins over detection.
		 */
		$default = $args['country']
			? KDNA_WH_Countries::default_country( $args['country'] )
			: KDNA_WH_Geo::detect_country();

		$config = KDNA_WH_Countries::get( $default );

		// One country needs no selector: hide it and assume that country.
		if ( 'auto' === $args['show_selector'] || '' === $args['show_selector'] ) {
			$show_selector = count( $available ) > 1;
		} else {
			$show_selector = in_array( strtolower( (string) $args['show_selector'] ), array( '1', 'yes', 'true', 'on', 'show' ), true );
		}

		$id          = 'kdna-wh-' . self::$instance;
		$label       = '' !== $args['label'] ? $args['label'] : $config['label'];
		$placeholder = '' !== $args['placeholder'] ? $args['placeholder'] : $config['placeholder'];
		$button      = '' !== $args['button_text'] ? $args['button_text'] : __( 'Check my water', 'kdna-water-hardness' );

		$classes = array( 'kdna-wh' );

		if ( 'replace' === $args['result_behaviour'] ) {
			$classes[] = 'kdna-wh--replace';
		}

		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}

		$icon = trim( (string) $args['icon'] );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-kdna-wh
			data-default-country="<?php echo esc_attr( $default ); ?>"
			data-kdna-wh-config="<?php echo esc_attr( (string) wp_json_encode( self::build_config( $args, $default ) ) ); ?>">

			<form class="kdna-wh__form" novalidate>

				<?php if ( $show_selector ) : ?>
					<div class="kdna-wh__field kdna-wh__field--country">
						<label class="kdna-wh__label" for="<?php echo esc_attr( $id ); ?>-country">
							<?php echo esc_html( $args['selector_label'] ); ?>
						</label>
						<select class="kdna-wh__select" id="<?php echo esc_attr( $id ); ?>-country" name="country" data-kdna-wh-country>
							<?php foreach ( $available as $code => $country ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default, $code ); ?>>
									<?php echo esc_html( $country['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php else : ?>
					<input type="hidden" name="country" value="<?php echo esc_attr( $default ); ?>" data-kdna-wh-country>
				<?php endif; ?>

				<div class="kdna-wh__field kdna-wh__field--postcode">
					<label class="kdna-wh__label <?php echo $args['show_label'] ? '' : 'screen-reader-text'; ?>"
						for="<?php echo esc_attr( $id ); ?>-postcode" data-kdna-wh-label>
						<?php echo esc_html( $label ); ?>
					</label>

					<input
						class="kdna-wh__input"
						id="<?php echo esc_attr( $id ); ?>-postcode"
						name="postcode"
						type="text"
						inputmode="<?php echo esc_attr( $config['keyboard'] ); ?>"
						autocomplete="postal-code"
						autocapitalize="characters"
						spellcheck="false"
						maxlength="<?php echo esc_attr( $config['maxlength'] ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-describedby="<?php echo esc_attr( $id ); ?>-error<?php echo $args['help_text'] ? ' ' . esc_attr( $id ) . '-help' : ''; ?>"
						data-kdna-wh-input>

					<?php if ( $args['help_text'] ) : ?>
						<p class="kdna-wh__help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $args['help_text'] ); ?></p>
					<?php endif; ?>

					<p class="kdna-wh__error" id="<?php echo esc_attr( $id ); ?>-error" data-kdna-wh-error hidden></p>
				</div>

				<div class="kdna-wh__field kdna-wh__field--submit">
					<button type="submit" class="kdna-wh__button" data-kdna-wh-submit>
						<?php if ( $icon && 'before' === $args['icon_position'] ) : ?>
							<span class="kdna-wh__icon kdna-wh__icon--before" aria-hidden="true"><?php echo wp_kses( $icon, self::icon_tags() ); ?></span>
						<?php endif; ?>

						<span data-kdna-wh-button-text><?php echo esc_html( $button ); ?></span>

						<?php if ( $icon && 'after' === $args['icon_position'] ) : ?>
							<span class="kdna-wh__icon kdna-wh__icon--after" aria-hidden="true"><?php echo wp_kses( $icon, self::icon_tags() ); ?></span>
						<?php endif; ?>
					</button>
				</div>

			</form>

			<?php
			/*
			 * The results panel is a live region so a screen reader announces
			 * the answer when it arrives, rather than leaving the visitor to
			 * discover that anything happened.
			 */
			?>
			<div class="kdna-wh__result" data-kdna-wh-result role="status" aria-live="polite" aria-atomic="true" hidden></div>

		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * The per-instance settings the script reads off the wrapper.
	 *
	 * Carried in a data attribute rather than a second localised object,
	 * because two of these on one page need two sets of settings and a global
	 * can only hold one.
	 *
	 * @param array  $args    The resolved arguments.
	 * @param string $country The country being shown.
	 * @return array
	 */
	private static function build_config( array $args, $country ) {
		$config = array(
			'showScale'    => (bool) $args['show_scale'],
			'showZoneName' => (bool) $args['show_zone_name'],
			'showUtility'  => (bool) $args['show_utility'],
			'showSource'   => (bool) $args['show_source'],
			'showValue'    => (bool) $args['show_value'],
			'replace'      => 'replace' === $args['result_behaviour'],
		);

		if ( $args['loading_text'] ) {
			$config['loading'] = $args['loading_text'];
		}

		if ( $args['unit'] && KDNA_WH_Units::is_valid( $args['unit'] ) ) {
			$config['unit']      = $args['unit'];
			$config['unitLabel'] = KDNA_WH_Units::abbr( $args['unit'] );
		}

		if ( $args['copy'] ) {
			$config['copy'] = self::clean_copy_overrides( $args['copy'] );
		}

		/*
		 * The preview is the whole reason the editor can style a result panel
		 * without hunting for a postcode that triggers one. It is added only in
		 * the editor, so the front end has no way to be affected by it.
		 */
		if ( $args['preview'] && self::is_editor() ) {
			$config['preview'] = $args['preview'];

			if ( 'error' === $args['preview'] ) {
				$config['previewError'] = __( 'That does not look right. Check and try again.', 'kdna-water-hardness' );
			} elseif ( 'form' !== $args['preview'] ) {
				$config['previewResult'] = KDNA_WH_Lookup::sample_result( $args['preview'], $country );
			}
		}

		return $config;
	}

	/**
	 * Cleans per-instance copy overrides.
	 *
	 * The body is markup, so it is filtered the same way the stored copy is.
	 *
	 * @param array $copy Raw overrides, keyed by band or state.
	 * @return array
	 */
	private static function clean_copy_overrides( array $copy ) {
		$clean = array();

		foreach ( $copy as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$heading = isset( $block['heading'] ) ? sanitize_text_field( $block['heading'] ) : '';
			$body    = isset( $block['body'] ) ? wp_kses_post( $block['body'] ) : '';

			// An empty override is not an override: it falls back to the copy
			// set in the plugin's own settings.
			if ( '' === $heading && '' === $body ) {
				continue;
			}

			$entry = array();

			if ( '' !== $heading ) {
				$entry['heading'] = $heading;
			}

			if ( '' !== $body ) {
				$entry['body'] = $body;
			}

			$clean[ sanitize_key( $key ) ] = $entry;
		}

		return $clean;
	}

	/**
	 * Whether this is being rendered inside the Elementor editor.
	 *
	 * @return bool
	 */
	public static function is_editor() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}

		return isset( $elementor->preview ) && $elementor->preview->is_preview_mode();
	}

	/**
	 * The markup allowed inside a button icon.
	 *
	 * @return array
	 */
	private static function icon_tags() {
		return array(
			'i'    => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'svg'  => array(
				'class'   => true,
				'xmlns'   => true,
				'viewbox' => true,
				'width'   => true,
				'height'  => true,
				'fill'    => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
			'g'    => array( 'fill' => true ),
		);
	}

	/**
	 * Shown in place of the form when no country has any data.
	 *
	 * @return string
	 */
	private static function render_empty_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf(
			'<div class="kdna-wh kdna-wh--empty"><p>%s</p></div>',
			esc_html__( 'Water Hardness Lookup: no data has been imported yet, so the tool is hidden. This notice is only visible to administrators.', 'kdna-water-hardness' )
		);
	}
}
