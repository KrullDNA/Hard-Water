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
									<th scope="col"><?php esc_html_e( 'Format', 'kdna-water-hardness' ); ?></th>
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
											<?php
											$kdna_wh_formats = KDNA_WH_Sources::formats();
											echo esc_html( isset( $kdna_wh_formats[ $kdna_wh_link['format'] ] ) ? $kdna_wh_formats[ $kdna_wh_link['format'] ] : $kdna_wh_formats['web'] );
											?>
										</td>
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
											<?php
											/*
											 * The reason the registry exists is to get back to the
											 * source, so that is the button rather than a link
											 * buried in the label. It opens in a new tab: losing
											 * the admin screen you were working on to a 40 page
											 * PDF is nobody's idea of a good time.
											 */
											?>
											<a class="button button-small" href="<?php echo esc_url( $kdna_wh_link['url'] ); ?>"
												target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( KDNA_WH_Sources::download_label( $kdna_wh_link['format'] ) ); ?>
												<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'kdna-water-hardness' ); ?></span>
											</a>

											<button type="button" class="button-link kdna-wh-edit-link"
												data-target="kdna-wh-link-form-<?php echo esc_attr( $kdna_wh_code ); ?>"
												data-id="<?php echo esc_attr( $kdna_wh_link['id'] ); ?>"
												data-label="<?php echo esc_attr( $kdna_wh_link['label'] ); ?>"
												data-url="<?php echo esc_attr( $kdna_wh_link['url'] ); ?>"
												data-region="<?php echo esc_attr( $kdna_wh_link['region'] ); ?>"
												data-last-checked="<?php echo esc_attr( $kdna_wh_link['last_checked'] ); ?>"
												data-data-date="<?php echo esc_attr( $kdna_wh_link['data_date'] ); ?>"
												data-format="<?php echo esc_attr( $kdna_wh_link['format'] ); ?>">
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

						<p>
							<label for="kdna-wh-format-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'What is at the other end', 'kdna-water-hardness' ); ?></label>
							<select name="format" id="kdna-wh-format-<?php echo esc_attr( $kdna_wh_code ); ?>" class="kdna-wh-link-format">
								<?php foreach ( KDNA_WH_Sources::formats() as $kdna_wh_key => $kdna_wh_name ) : ?>
									<option value="<?php echo esc_attr( $kdna_wh_key ); ?>"><?php echo esc_html( $kdna_wh_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Sets what the button on the row above says, so nobody clicks Download CSV and gets a search box.', 'kdna-water-hardness' ); ?></span>
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
							<?php esc_html_e( 'With the API option, a lookup asks the provider first, caches the answer, and writes it into this site\'s own tables so the data accumulates. If the provider is unreachable, slow, or out of quota, the imported data answers instead and the visitor sees nothing amiss.', 'kdna-water-hardness' ); ?>
						</p>

						<div class="kdna-wh-api-fields">
							<div class="kdna-wh-field-row">
								<p class="kdna-wh-grow">
									<label for="kdna-wh-endpoint-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'API endpoint', 'kdna-water-hardness' ); ?></label>
									<input type="url" name="api_endpoint" id="kdna-wh-endpoint-<?php echo esc_attr( $kdna_wh_code ); ?>"
										class="large-text" value="<?php echo esc_attr( $kdna_wh_config['api_endpoint'] ); ?>"
										placeholder="https://api.example.com/hardness/{postcode}">
									<span class="description">
										<?php esc_html_e( 'Use {postcode} where the postcode belongs. Without it the postcode is added as a query argument. {country} and {key} also work.', 'kdna-water-hardness' ); ?>
									</span>
								</p>
							</div>

							<div class="kdna-wh-field-row">
								<p>
									<label for="kdna-wh-apikey-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'API key', 'kdna-water-hardness' ); ?></label>
									<input type="password" name="api_key" id="kdna-wh-apikey-<?php echo esc_attr( $kdna_wh_code ); ?>"
										class="regular-text" value="<?php echo esc_attr( $kdna_wh_config['api_key'] ); ?>" autocomplete="new-password">
									<span class="description"><?php esc_html_e( 'Sent as a bearer token, so it stays out of server logs.', 'kdna-water-hardness' ); ?></span>
								</p>

								<p>
									<label for="kdna-wh-adapter-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Provider adapter', 'kdna-water-hardness' ); ?></label>
									<select name="api_adapter" id="kdna-wh-adapter-<?php echo esc_attr( $kdna_wh_code ); ?>">
										<?php foreach ( KDNA_WH_Sources::get_adapters() as $kdna_wh_key => $kdna_wh_class ) : ?>
											<option value="<?php echo esc_attr( $kdna_wh_key ); ?>" <?php selected( $kdna_wh_config['api_adapter'], $kdna_wh_key ); ?>>
												<?php echo esc_html( $kdna_wh_key ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<span class="description"><?php esc_html_e( 'A provider needing more than plain JSON gets its own adapter class.', 'kdna-water-hardness' ); ?></span>
								</p>
							</div>

							<div class="kdna-wh-field-row">
								<p>
									<label for="kdna-wh-ttl-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Cache answers for', 'kdna-water-hardness' ); ?></label>
									<input type="number" name="api_ttl_days" id="kdna-wh-ttl-<?php echo esc_attr( $kdna_wh_code ); ?>"
										class="small-text" min="1" max="365" step="1"
										value="<?php echo esc_attr( max( 1, (int) round( $kdna_wh_config['api_ttl'] / DAY_IN_SECONDS ) ) ); ?>">
									<?php esc_html_e( 'days', 'kdna-water-hardness' ); ?>
									<span class="description"><?php esc_html_e( 'Hardness changes once a year at most, so 30 days is a sensible default.', 'kdna-water-hardness' ); ?></span>
								</p>

								<p>
									<label for="kdna-wh-apiconf-<?php echo esc_attr( $kdna_wh_code ); ?>"><?php esc_html_e( 'Store answers as', 'kdna-water-hardness' ); ?></label>
									<select name="api_confidence" id="kdna-wh-apiconf-<?php echo esc_attr( $kdna_wh_code ); ?>">
										<option value="verified" <?php selected( $kdna_wh_config['api_confidence'], 'verified' ); ?>><?php esc_html_e( 'Verified', 'kdna-water-hardness' ); ?></option>
										<option value="estimated" <?php selected( $kdna_wh_config['api_confidence'], 'estimated' ); ?>><?php esc_html_e( 'Estimated', 'kdna-water-hardness' ); ?></option>
									</select>
									<span class="description"><?php esc_html_e( 'Estimated answers always show as inconclusive on the front end.', 'kdna-water-hardness' ); ?></span>
								</p>
							</div>

							<?php if ( $kdna_wh_config['api_error'] ) : ?>
								<div class="notice notice-warning inline">
									<p>
										<strong><?php esc_html_e( 'Last provider error:', 'kdna-water-hardness' ); ?></strong>
										<?php echo esc_html( $kdna_wh_config['api_error'] ); ?>
										<?php if ( $kdna_wh_config['api_error_at'] ) : ?>
											<em>
												<?php
												printf(
													/* translators: %s: how long ago, e.g. 2 hours. */
													esc_html__( '(%s ago)', 'kdna-water-hardness' ),
													esc_html( human_time_diff( strtotime( $kdna_wh_config['api_error_at'] ), current_time( 'timestamp' ) ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
												);
												?>
											</em>
										<?php endif; ?>
										<br>
										<?php esc_html_e( 'Visitors were served the imported data instead and saw no error.', 'kdna-water-hardness' ); ?>
									</p>
								</div>
							<?php endif; ?>
						</div>

						<p class="submit">
							<button type="submit" class="button"><?php esc_html_e( 'Save', 'kdna-water-hardness' ); ?></button>
						</p>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
						<?php wp_nonce_field( 'kdna_wh_clear_api_cache' ); ?>
						<input type="hidden" name="action" value="kdna_wh_clear_api_cache">
						<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_code ); ?>">
						<button type="submit" class="button">
							<?php esc_html_e( 'Clear cached API answers', 'kdna-water-hardness' ); ?>
						</button>
					</form>

					<p class="description">
						<?php esc_html_e( 'Clearing the cache also lifts the pause that follows a provider failure, so the next lookup tries the provider again straight away.', 'kdna-water-hardness' ); ?>
					</p>

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

<div class="kdna-wh-panel">

	<div class="kdna-wh-panel-head">
		<h3><?php esc_html_e( 'Add a country', 'kdna-water-hardness' ); ?></h3>
	</div>

	<div class="kdna-wh-panel-body">

		<p class="kdna-wh-intro">
			<?php esc_html_e( 'Importing a zones CSV for a new country adds it here by itself. Add it up front instead when you want somewhere to record the source documents while you are still gathering them, or to write the band copy before any data exists.', 'kdna-water-hardness' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'kdna_wh_add_country' ); ?>
			<input type="hidden" name="action" value="kdna_wh_add_country">

			<p>
				<label for="kdna-wh-new-country"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></label>
				<select name="country" id="kdna-wh-new-country">
					<?php
					/*
					 * The countries whose postcode rules the plugin already
					 * knows come first, because picking one of those gets the
					 * right field label, example and validation for free.
					 */
					$kdna_wh_known = KDNA_WH_Countries::available();

					foreach ( $kdna_wh_known as $kdna_wh_option ) :
						if ( in_array( $kdna_wh_option, $kdna_wh_countries, true ) ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( $kdna_wh_option ); ?>">
							<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_option ) . ' (' . $kdna_wh_option . ')' ); ?>
						</option>
					<?php endforeach; ?>
					<option value="other"><?php esc_html_e( 'Somewhere else…', 'kdna-water-hardness' ); ?></option>
				</select>
			</p>

			<p>
				<label for="kdna-wh-new-country-code"><?php esc_html_e( 'Or a country code', 'kdna-water-hardness' ); ?></label>
				<input type="text" name="country_code" id="kdna-wh-new-country-code" class="small-text"
					maxlength="2" size="2" placeholder="<?php esc_attr_e( 'IE', 'kdna-water-hardness' ); ?>"
					pattern="[A-Za-z]{2}">
				<span class="description">
					<?php esc_html_e( 'Two letters, ISO 3166-1. Any country works. One the plugin does not know the postcode rules for gets permissive validation until you add them.', 'kdna-water-hardness' ); ?>
				</span>
			</p>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add country', 'kdna-water-hardness' ); ?></button>
			</p>
		</form>

	</div>
</div>
