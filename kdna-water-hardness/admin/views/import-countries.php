<?php
/**
 * Data Import, countries and sources tab.
 *
 * One panel per country: what is held, when it was imported, how old the
 * underlying reports are, and every document the figures were compiled from.
 *
 * The point of this screen is that in two years, when the figures need
 * refreshing, whoever does it opens this page and finds every source listed,
 * with no institutional knowledge required.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

$kdna_wh_countries = KDNA_WH_Sources::get_known_countries();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selecting which panel is open, not acting on it.
$kdna_wh_open = isset( $_GET['country'] ) ? KDNA_WH_DB::normalise_country( wp_unslash( $_GET['country'] ) ) : '';
?>

<h2><?php esc_html_e( 'Countries and sources', 'kdna-water-hardness' ); ?></h2>

<p class="kdna-wh-intro">
	<?php esc_html_e( 'Every figure this tool shows should be traceable to a published utility document. Record those documents here as you compile the data, and the next refresh is a morning\'s work rather than an archaeology project.', 'kdna-water-hardness' ); ?>
</p>

<?php if ( ! $kdna_wh_countries ) : ?>

	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'No countries yet. Import a zones CSV and the country will appear here.', 'kdna-water-hardness' ); ?>
			<a href="<?php echo esc_url( KDNA_WH_Admin_Import::page_url() ); ?>"><?php esc_html_e( 'Import a CSV', 'kdna-water-hardness' ); ?></a>
		</p>
	</div>

<?php else : ?>

	<?php
	foreach ( $kdna_wh_countries as $kdna_wh_code ) :
		$kdna_wh_config    = KDNA_WH_Sources::get_country( $kdna_wh_code );
		$kdna_wh_links     = KDNA_WH_Sources::get_links( $kdna_wh_code );
		$kdna_wh_zones     = KDNA_WH_DB::count_zones( $kdna_wh_code );
		$kdna_wh_postcodes = KDNA_WH_DB::count_postcodes( $kdna_wh_code );
		$kdna_wh_stale     = KDNA_WH_Sources::is_stale( $kdna_wh_code );
		$kdna_wh_is_open   = ( $kdna_wh_open === $kdna_wh_code ) || ( 1 === count( $kdna_wh_countries ) );
		?>

		<div class="kdna-wh-panel">

			<div class="kdna-wh-panel-head">
				<h3>
					<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) ); ?>
					<span class="kdna-wh-code"><?php echo esc_html( $kdna_wh_code ); ?></span>
					<span class="kdna-wh-pill kdna-wh-pill-<?php echo esc_attr( $kdna_wh_config['source_type'] ); ?>">
						<?php echo esc_html( 'api' === $kdna_wh_config['source_type'] ? __( 'API', 'kdna-water-hardness' ) : __( 'CSV', 'kdna-water-hardness' ) ); ?>
					</span>
					<?php if ( $kdna_wh_stale ) : ?>
						<span class="kdna-wh-pill kdna-wh-pill-stale" title="
						<?php
						printf(
							/* translators: %d: number of months. */
							esc_attr__( 'Flagged because the newest source is older than %d months, or has no date recorded.', 'kdna-water-hardness' ),
							(int) KDNA_WH_Sources::STALE_MONTHS
						);
						?>
						">
							<?php esc_html_e( 'Needs review', 'kdna-water-hardness' ); ?>
						</span>
					<?php endif; ?>
				</h3>

				<p class="kdna-wh-panel-meta">
					<?php
					printf(
						/* translators: 1: zone count, 2: postcode count. */
						esc_html__( '%1$s zones, %2$s postcode mappings.', 'kdna-water-hardness' ),
						'<strong>' . esc_html( number_format_i18n( $kdna_wh_zones ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $kdna_wh_postcodes ) ) . '</strong>'
					);
					?>

					<?php if ( $kdna_wh_config['last_import'] ) : ?>
						<?php
						printf(
							/* translators: %s: human readable time difference, e.g. 2 days. */
							esc_html__( 'Last imported %s ago.', 'kdna-water-hardness' ),
							esc_html( human_time_diff( strtotime( $kdna_wh_config['last_import'] ), current_time( 'timestamp' ) ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Nothing imported yet.', 'kdna-water-hardness' ); ?>
					<?php endif; ?>

					<span class="kdna-wh-<?php echo $kdna_wh_stale ? 'bad' : 'ok'; ?>">
						<?php echo esc_html( KDNA_WH_Sources::staleness_label( $kdna_wh_code ) ); ?>
					</span>
				</p>
			</div>

			<details <?php echo $kdna_wh_is_open ? 'open' : ''; ?>>
				<summary><?php esc_html_e( 'Sources and settings', 'kdna-water-hardness' ); ?></summary>

				<div class="kdna-wh-panel-body">

					<h4><?php esc_html_e( 'Source documents', 'kdna-water-hardness' ); ?></h4>

					<?php if ( $kdna_wh_links ) : ?>
						<table class="widefat striped kdna-wh-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Label', 'kdna-water-hardness' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Region', 'kdna-water-hardness' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Data published', 'kdna-water-hardness' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Link checked', 'kdna-water-hardness' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Actions', 'kdna-water-hardness' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $kdna_wh_links as $kdna_wh_link ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( $kdna_wh_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $kdna_wh_link['label'] ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $kdna_wh_link['region'] ? $kdna_wh_link['region'] : '—' ); ?></td>
										<td>
											<?php if ( $kdna_wh_link['data_date'] ) : ?>
												<?php echo esc_html( mysql2date( get_option( 'date_format' ), $kdna_wh_link['data_date'] ) ); ?>
											<?php else : ?>
												<span class="kdna-wh-bad"><?php esc_html_e( 'Not recorded', 'kdna-water-hardness' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<?php
											echo esc_html(
												$kdna_wh_link['last_checked']
													? mysql2date( get_option( 'date_format' ), $kdna_wh_link['last_checked'] )
													: '—'
											);
											?>
										</td>
										<td>
											<button type="button" class="button-link kdna-wh-edit-link"
												data-target="kdna-wh-link-form-<?php echo esc_attr( $kdna_wh_code ); ?>"
												data-id="<?php echo esc_attr( $kdna_wh_link['id'] ); ?>"
												data-label="<?php echo esc_attr( $kdna_wh_link['label'] ); ?>"
												data-url="<?php echo esc_attr( $kdna_wh_link['url'] ); ?>"
												data-region="<?php echo esc_attr( $kdna_wh_link['region'] ); ?>"
												data-last-checked="<?php echo esc_attr( $kdna_wh_link['last_checked'] ); ?>"
												data-data-date="<?php echo esc_attr( $kdna_wh_link['data_date'] ); ?>">
												<?php esc_html_e( 'Edit', 'kdna-water-hardness' ); ?>
											</button>

											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
												<?php wp_nonce_field( 'kdna_wh_delete_link' ); ?>
												<input type="hidden" name="action" value="kdna_wh_delete_link">
												<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_code ); ?>">
												<input type="hidden" name="link_id" value="<?php echo esc_attr( $kdna_wh_link['id'] ); ?>">
												<button type="submit" class="button-link kdna-wh-danger"><?php esc_html_e( 'Remove', 'kdna-water-hardness' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No source documents recorded for this country yet.', 'kdna-water-hardness' ); ?></p>
					<?php endif; ?>

					<h4 id="kdna-wh-link-form-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Add or edit a source', 'kdna-water-hardness' ); ?></h4>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-link-form">
						<?php wp_nonce_field( 'kdna_wh_save_link' ); ?>
						<input type="hidden" name="action" value="kdna_wh_save_link">
						<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_code ); ?>">
						<input type="hidden" name="link_id" value="" class="kdna-wh-link-id">

						<div class="kdna-wh-field-row">
							<p>
								<label for="kdna-wh-label-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Label', 'kdna-water-hardness' ); ?></label>
								<input type="text" name="label" id="kdna-wh-label-<?php echo esc_attr( $kdna_wh_code ); ?>" class="regular-text kdna-wh-link-label"
									placeholder="<?php esc_attr_e( 'Water Corporation, Perth metropolitan', 'kdna-water-hardness' ); ?>" required>
							</p>
							<p>
								<label for="kdna-wh-region-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Region', 'kdna-water-hardness' ); ?></label>
								<input type="text" name="region" id="kdna-wh-region-<?php echo esc_attr( $kdna_wh_code ); ?>" class="kdna-wh-link-region"
									placeholder="<?php esc_attr_e( 'WA', 'kdna-water-hardness' ); ?>">
							</p>
						</div>

						<p>
							<label for="kdna-wh-url-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'URL', 'kdna-water-hardness' ); ?></label>
							<input type="url" name="url" id="kdna-wh-url-<?php echo esc_attr( $kdna_wh_code ); ?>" class="large-text kdna-wh-link-url"
								placeholder="https://" required>
						</p>

						<div class="kdna-wh-field-row">
							<p>
								<label for="kdna-wh-data-date-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Data published', 'kdna-water-hardness' ); ?></label>
								<input type="date" name="data_date" id="kdna-wh-data-date-<?php echo esc_attr( $kdna_wh_code ); ?>" class="kdna-wh-link-data-date">
								<span class="description"><?php esc_html_e( 'Publication date of the report the current figures came from. This is what the review flag is judged on.', 'kdna-water-hardness' ); ?></span>
							</p>
							<p>
								<label for="kdna-wh-last-checked-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Link last checked', 'kdna-water-hardness' ); ?></label>
								<input type="date" name="last_checked" id="kdna-wh-last-checked-<?php echo esc_attr( $kdna_wh_code ); ?>" class="kdna-wh-link-last-checked">
								<span class="description"><?php esc_html_e( 'The date you last confirmed the link still opens.', 'kdna-water-hardness' ); ?></span>
							</p>
						</div>

						<p class="submit">
							<button type="submit" class="button"><?php esc_html_e( 'Save source', 'kdna-water-hardness' ); ?></button>
						</p>
					</form>

					<h4><?php esc_html_e( 'Where this country\'s data comes from', 'kdna-water-hardness' ); ?></h4>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'kdna_wh_save_country' ); ?>
						<input type="hidden" name="action" value="kdna_wh_save_country">
						<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_code ); ?>">

						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Source type', 'kdna-water-hardness' ); ?></legend>

							<label>
								<input type="radio" name="source_type" value="csv" <?php checked( $kdna_wh_config['source_type'], 'csv' ); ?>>
								<?php esc_html_e( 'Imported CSV, held in this site\'s database', 'kdna-water-hardness' ); ?>
							</label>
							<br>
							<label>
								<input type="radio" name="source_type" value="api" <?php checked( $kdna_wh_config['source_type'], 'api' ); ?>>
								<?php esc_html_e( 'Remote API, with imported data as the fallback', 'kdna-water-hardness' ); ?>
							</label>
						</fieldset>

						<p class="description">
							<?php esc_html_e( 'The API option is recorded here now but is not yet wired up: lookups use the imported data whichever is selected until the data source layer is built. It is stored now so the configuration does not have to move later.', 'kdna-water-hardness' ); ?>
						</p>

						<p class="submit">
							<button type="submit" class="button"><?php esc_html_e( 'Save', 'kdna-water-hardness' ); ?></button>
						</p>
					</form>

					<p>
						<a href="
						<?php
						echo esc_url(
							KDNA_WH_Admin_Import::page_url(
								array(
									'tab'     => 'browse',
									'country' => $kdna_wh_code,
								)
							)
						);
						?>
						">
							<?php
							printf(
								/* translators: %s: country name. */
								esc_html__( 'Browse the %s data', 'kdna-water-hardness' ),
								esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) )
							);
							?>
						</a>
					</p>

				</div>
			</details>
		</div>

	<?php endforeach; ?>

<?php endif; ?>
