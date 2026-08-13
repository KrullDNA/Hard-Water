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
	 * Renders the tool.
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

		$available = KDNA_WH_Countries::available();

		// Nothing has been imported yet. A visitor should see nothing at all
		// rather than a broken form; an administrator should see why.
		if ( ! $available ) {
			return self::render_empty_notice();
		}

		self::enqueue_assets();
		self::$instance++;

		$default = KDNA_WH_Countries::default_country( $atts['country'] );
		$config  = KDNA_WH_Countries::get( $default );

		// One country needs no selector: hide it and assume that country.
		$show_selector = 'auto' === $atts['show_selector']
			? count( $available ) > 1
			: in_array( strtolower( (string) $atts['show_selector'] ), array( '1', 'yes', 'true', 'on' ), true );

		$id          = 'kdna-wh-' . self::$instance;
		$label       = '' !== $atts['label'] ? $atts['label'] : $config['label'];
		$placeholder = '' !== $atts['placeholder'] ? $atts['placeholder'] : $config['placeholder'];
		$button      = '' !== $atts['button_text'] ? $atts['button_text'] : __( 'Check my water', 'kdna-water-hardness' );

		ob_start();
		?>
		<div class="kdna-wh <?php echo esc_attr( $atts['class'] ); ?>" data-kdna-wh data-default-country="<?php echo esc_attr( $default ); ?>">

			<form class="kdna-wh__form" novalidate>

				<?php if ( $show_selector ) : ?>
					<div class="kdna-wh__field kdna-wh__field--country">
						<label class="kdna-wh__label" for="<?php echo esc_attr( $id ); ?>-country">
							<?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?>
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
					<label class="kdna-wh__label" for="<?php echo esc_attr( $id ); ?>-postcode" data-kdna-wh-label>
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
						aria-describedby="<?php echo esc_attr( $id ); ?>-error"
						data-kdna-wh-input>

					<p class="kdna-wh__error" id="<?php echo esc_attr( $id ); ?>-error" data-kdna-wh-error hidden></p>
				</div>

				<div class="kdna-wh__field kdna-wh__field--submit">
					<button type="submit" class="kdna-wh__button" data-kdna-wh-submit>
						<span data-kdna-wh-button-text><?php echo esc_html( $button ); ?></span>
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
