<?php
/**
 * Settings screen.
 *
 * Three tabs: the bands and their copy, which is the part that gets edited
 * often; the advanced settings, which are set once; and the installation
 * status.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- choosing which tab and country to display, not acting on it.
$kdna_wh_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'copy';
$kdna_wh_country = isset( $_GET['country'] ) ? KDNA_WH_DB::normalise_country( wp_unslash( $_GET['country'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$kdna_wh_notice    = KDNA_WH_Admin_Import::get_notice();
$kdna_wh_countries = KDNA_WH_Sources::get_known_countries();

// Copy can be written before any data is imported, so there is always at least
// one country to edit.
if ( ! $kdna_wh_countries ) {
	$kdna_wh_countries = array( 'AU' );
}

if ( ! $kdna_wh_country || ! in_array( $kdna_wh_country, $kdna_wh_countries, true ) ) {
	$kdna_wh_country = reset( $kdna_wh_countries );
}

$kdna_wh_config = KDNA_WH_Bands::get_country( $kdna_wh_country );
$kdna_wh_scale  = KDNA_WH_Bands::scale( $kdna_wh_country );
?>
<div class="wrap kdna-wh-wrap">

	<h1><?php esc_html_e( 'Water Hardness Lookup', 'kdna-water-hardness' ); ?></h1>

	<?php if ( $kdna_wh_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $kdna_wh_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $kdna_wh_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper wp-clearfix">
		<?php
		$kdna_wh_tabs = array(
			'copy'     => __( 'Bands and copy', 'kdna-water-hardness' ),
			'advanced' => __( 'Advanced', 'kdna-water-hardness' ),
			'status'   => __( 'Status', 'kdna-water-hardness' ),
		);

		foreach ( $kdna_wh_tabs as $kdna_wh_slug => $kdna_wh_label ) :
			?>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'    => KDNA_WH_Admin::MENU_SLUG,
						'tab'     => $kdna_wh_slug,
						'country' => $kdna_wh_country,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			" class="nav-tab <?php echo $kdna_wh_tab === $kdna_wh_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $kdna_wh_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'status' === $kdna_wh_tab ) : ?>

		<?php require KDNA_WH_PATH . 'admin/views/settings-status.php'; ?>

	<?php elseif ( 'advanced' === $kdna_wh_tab ) : ?>

		<?php
		$kdna_wh_settings = KDNA_WH_Bands::get_settings();
		?>

		<h2><?php esc_html_e( 'Advanced', 'kdna-water-hardness' ); ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'kdna_wh_save_settings' ); ?>
			<input type="hidden" name="action" value="kdna_wh_save_settings">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="kdna-wh-stale-years"><?php esc_html_e( 'Treat data as dated after', 'kdna-water-hardness' ); ?></label>
						</th>
						<td>
							<input type="number" name="stale_years" id="kdna-wh-stale-years" class="small-text"
								min="0" max="50" step="1" value="<?php echo esc_attr( $kdna_wh_settings['stale_years'] ); ?>">
							<?php esc_html_e( 'years', 'kdna-water-hardness' ); ?>

							<p class="description">
								<?php esc_html_e( 'Measured from the publication date of the report a figure came from. Set to 0 to switch the age check off entirely.', 'kdna-water-hardness' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dated data', 'kdna-water-hardness' ); ?></th>
						<td>
							<label for="kdna-wh-inconclusive-stale">
								<input type="checkbox" name="inconclusive_stale" id="kdna-wh-inconclusive-stale" value="1"
									<?php checked( ! empty( $kdna_wh_settings['inconclusive_stale'] ) ); ?>>
								<?php esc_html_e( 'Show an inconclusive result rather than a band when the figure is dated', 'kdna-water-hardness' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'On by default. A figure whose age you cannot vouch for is better explained than presented as fact. Untick it and dated figures are shown with their band as normal.', 'kdna-water-hardness' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'kdna-water-hardness' ); ?></button>
			</p>
		</form>

		<h3><?php esc_html_e( 'When a result is inconclusive', 'kdna-water-hardness' ); ?></h3>

		<p class="kdna-wh-intro">
			<?php esc_html_e( 'Any one of these three means the visitor sees the inconclusive copy instead of a band:', 'kdna-water-hardness' ); ?>
		</p>

		<ol class="kdna-wh-intro">
			<li><?php esc_html_e( 'The postcode covers supply zones that fall in different bands. Reporting one figure would be wrong, and a range crossing from soft to hard tells the visitor nothing.', 'kdna-water-hardness' ); ?></li>
			<li><?php esc_html_e( 'The figure is marked estimated rather than verified.', 'kdna-water-hardness' ); ?></li>
			<li><?php esc_html_e( 'The report the figure came from is older than the threshold above.', 'kdna-water-hardness' ); ?></li>
		</ol>

	<?php else : ?>

		<?php require KDNA_WH_PATH . 'admin/views/settings-copy.php'; ?>

	<?php endif; ?>

</div>
