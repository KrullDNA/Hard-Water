<?php
/**
 * Elementor integration.
 *
 * Registers the widget and the category it lives in, and nothing else. All of
 * it is guarded: Elementor is not a dependency of this plugin, and everything
 * apart from the widget keeps working without it.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Elementor
 */
class KDNA_WH_Elementor {

	/**
	 * The category custom widgets are grouped under, so they do not clutter
	 * the general list.
	 */
	const CATEGORY = 'kdna-tools';

	/**
	 * The minimum Elementor version the widget is built against.
	 *
	 * has_widget_inner_wrapper() arrived in 3.16, and it is the method the
	 * whole Atomic markup approach rests on.
	 */
	const MIN_ELEMENTOR = '3.16.0';

	/**
	 * Hooks in, if Elementor is there.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/init', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Whether Elementor is present and recent enough.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}

		return version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '>=' );
	}

	/**
	 * Adds the KDNA Tools category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! self::is_supported() ) {
			return;
		}

		\Elementor\Plugin::$instance->elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'KDNA Tools', 'kdna-water-hardness' ),
				'icon'  => 'eicon-nerd',
			)
		);
	}

	/**
	 * Registers the widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's registry.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ) {
		if ( ! self::is_supported() ) {
			return;
		}

		require_once KDNA_WH_PATH . 'elementor/controls/content-controls.php';
		require_once KDNA_WH_PATH . 'elementor/controls/style-controls.php';
		require_once KDNA_WH_PATH . 'elementor/class-kdna-wh-widget.php';

		$widgets_manager->register( new KDNA_WH_Widget() );
	}

	/**
	 * Tells an administrator why the widget is missing, once, and only where
	 * it is relevant.
	 *
	 * @return void
	 */
	public static function version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! did_action( 'elementor/loaded' ) || self::is_supported() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum Elementor version. */
					__( 'Water Hardness Lookup: the Elementor widget needs Elementor %s or later. The shortcode works regardless.', 'kdna-water-hardness' ),
					self::MIN_ELEMENTOR
				)
			)
		);
	}
}
