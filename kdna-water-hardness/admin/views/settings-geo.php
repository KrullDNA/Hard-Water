<?php
/**
 * Settings, geolocation panel on the Advanced tab.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

$kdna_wh_geo      = KDNA_WH_Geo::get_settings();
$kdna_wh_has_db   = KDNA_WH_Geo::has_database();
$kdna_wh_next     = KDNA_WH_Geo::next_update();
$kdna_wh_detected = KDNA_WH_Geo::detect_raw();
$kdna_wh_cf       = KDNA_WH_Geo::from_cloudflare();
?>

<h2><?php esc_html_e( 'Country pre-selection', 'kdna-water-hardness' ); ?></h2>

<p class="kdna-wh-intro">
	<?php esc_html_e( 'The country selector can be set to the visitor\'s own country before they touch it. It is resolved in this order, and it is only ever a convenience: the selector stays changeable, because mobile traffic resolves to wherever the carrier\'s gateway is and a VPN resolves wherever it exits.', 'kdna-water-hardness' ); ?>
</p>

<ol class="kdna-wh-intro">
	<li>
		<strong><?php esc_html_e( 'Cloudflare', 'kdna-water-hardness' ); ?></strong>
		<?php esc_html_e( 'reads the CF-IPCountry header. Free, instant, and nothing to install. If your site is behind Cloudflare nothing else is needed.', 'kdna-water-hardness' ); ?>
		<?php if ( $kdna_wh_cf ) : ?>
			<span class="kdna-wh-ok">
				<?php
				printf(
					/* translators: %s: ISO country code. */
					esc_html__( 'Active, and it says you are in %s.', 'kdna-water-hardness' ),
					esc_html( $kdna_wh_cf )
				);
				?>
			</span>
		<?php else : ?>
			<span class="kdna-wh-muted"><?php esc_html_e( 'Not detected on this request.', 'kdna-water-hardness' ); ?></span>
		<?php endif; ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'MaxMind GeoLite2', 'kdna-water-hardness' ); ?></strong>
		<?php esc_html_e( 'looks the address up in a database held on this site. Free, but it needs a MaxMind account and a licence key, and the file is refreshed monthly.', 'kdna-water-hardness' ); ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Australia', 'kdna-water-hardness' ); ?></strong>
		<?php esc_html_e( 'if neither works, or if the country detected holds no data.', 'kdna-water-hardness' ); ?>
	</li>
</ol>

<table class="widefat striped kdna-wh-table kdna-wh-summary">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Detected for you, right now', 'kdna-water-hardness' ); ?></th>
			<td>
				<?php if ( $kdna_wh_detected ) : ?>
					<strong><?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_detected ) . ' (' . $kdna_wh_detected . ')' ); ?></strong>
					<?php
					$kdna_wh_effective = KDNA_WH_Geo::detect_country();

					if ( $kdna_wh_effective && $kdna_wh_effective !== $kdna_wh_detected ) :
						?>
						<span class="description">
							<?php
							printf(
								/* translators: %s: country name. */
								esc_html__( 'No data is held for it, so the tool would show %s instead.', 'kdna-water-hardness' ),
								esc_html( KDNA_WH_Sources::country_name( $kdna_wh_effective ) )
							);
							?>
						</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="kdna-wh-muted"><?php esc_html_e( 'Nothing detected, so the fallback would be used.', 'kdna-water-hardness' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'MaxMind database', 'kdna-water-hardness' ); ?></th>
			<td>
				<?php if ( $kdna_wh_has_db ) : ?>
					<span class="kdna-wh-ok"><?php esc_html_e( 'Installed', 'kdna-water-hardness' ); ?></span>
					<?php if ( $kdna_wh_geo['build_epoch'] ) : ?>
						<span class="description">
							<?php
							printf(
								/* translators: %s: date the database was built by MaxMind. */
								esc_html__( 'Built by MaxMind on %s.', 'kdna-water-hardness' ),
								esc_html( date_i18n( get_option( 'date_format' ), (int) $kdna_wh_geo['build_epoch'] ) )
							);
							?>
						</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="kdna-wh-muted"><?php esc_html_e( 'Not installed', 'kdna-water-hardness' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Last downloaded', 'kdna-water-hardness' ); ?></th>
			<td>
				<?php
				echo esc_html(
					$kdna_wh_geo['last_updated']
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $kdna_wh_geo['last_updated'] )
						: __( 'Never', 'kdna-water-hardness' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Next scheduled refresh', 'kdna-water-hardness' ); ?></th>
			<td>
				<?php if ( $kdna_wh_next ) : ?>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $kdna_wh_next ) ); ?>
				<?php else : ?>
					<span class="kdna-wh-muted"><?php esc_html_e( 'Not scheduled. Add a licence key and save to start it.', 'kdna-water-hardness' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $kdna_wh_geo['last_error'] ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last error', 'kdna-water-hardness' ); ?></th>
				<td><span class="kdna-wh-bad"><?php echo esc_html( $kdna_wh_geo['last_error'] ); ?></span></td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'kdna_wh_save_geo' ); ?>
	<input type="hidden" name="action" value="kdna_wh_save_geo">

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Pre-selection', 'kdna-water-hardness' ); ?></th>
				<td>
					<label for="kdna-wh-geo-enabled">
						<input type="checkbox" name="geo_enabled" id="kdna-wh-geo-enabled" value="1" <?php checked( ! empty( $kdna_wh_geo['enabled'] ) ); ?>>
						<?php esc_html_e( 'Pre-select the country from the visitor\'s location', 'kdna-water-hardness' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Turn this off if the site uses full page caching. The first visitor\'s country would otherwise be baked into the cached page for everyone who follows, which is worse than always starting on the default.', 'kdna-water-hardness' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna-wh-geo-account"><?php esc_html_e( 'MaxMind account ID', 'kdna-water-hardness' ); ?></label></th>
				<td>
					<input type="text" name="account_id" id="kdna-wh-geo-account" class="regular-text"
						value="<?php echo esc_attr( $kdna_wh_geo['account_id'] ); ?>" autocomplete="off">
					<p class="description">
						<?php esc_html_e( 'Leave empty to use MaxMind\'s older download method, which needs only the licence key.', 'kdna-water-hardness' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kdna-wh-geo-key"><?php esc_html_e( 'MaxMind licence key', 'kdna-water-hardness' ); ?></label></th>
				<td>
					<input type="password" name="licence_key" id="kdna-wh-geo-key" class="regular-text"
						value="<?php echo esc_attr( $kdna_wh_geo['licence_key'] ); ?>" autocomplete="new-password">
					<p class="description">
						<?php esc_html_e( 'Free from a MaxMind account, under Manage License Keys. The key is stored in this site\'s settings and is never sent anywhere except to MaxMind.', 'kdna-water-hardness' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save geolocation settings', 'kdna-water-hardness' ); ?></button>
	</p>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
	<?php wp_nonce_field( 'kdna_wh_update_geo' ); ?>
	<input type="hidden" name="action" value="kdna_wh_update_geo">
	<button type="submit" class="button" <?php disabled( '' === trim( (string) $kdna_wh_geo['licence_key'] ) ); ?>>
		<?php esc_html_e( 'Download the database now', 'kdna-water-hardness' ); ?>
	</button>
</form>

<?php if ( $kdna_wh_has_db ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
		<?php wp_nonce_field( 'kdna_wh_delete_geo' ); ?>
		<input type="hidden" name="action" value="kdna_wh_delete_geo">
		<button type="submit" class="button button-link-delete kdna-wh-confirm"
			data-confirm="<?php esc_attr_e( 'Remove the geolocation database? Detection falls back to Cloudflare, then to the default country.', 'kdna-water-hardness' ); ?>">
			<?php esc_html_e( 'Remove the database', 'kdna-water-hardness' ); ?>
		</button>
	</form>
<?php endif; ?>

<p class="description">
	<?php esc_html_e( 'The database is around 9 MB and is stored in the uploads folder, not in the plugin, so it survives a plugin update.', 'kdna-water-hardness' ); ?>
</p>
