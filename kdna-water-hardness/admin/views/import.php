<?php
/**
 * Data Import screen.
 *
 * Four things live here, because they are all the same job seen from
 * different angles: getting data in, recording where it came from, seeing
 * what is held, and taking it out again.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which tab to display, not acting on it.
$kdna_wh_tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'import';
$kdna_wh_step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
$kdna_wh_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$kdna_wh_job    = $kdna_wh_token ? KDNA_WH_Importer::get_job( $kdna_wh_token ) : null;
$kdna_wh_notice = KDNA_WH_Admin_Import::get_notice();

// An import in progress takes over the screen. Tabs would only invite
// navigating away from a job halfway through.
if ( $kdna_wh_job && in_array( $kdna_wh_step, array( 'map', 'run', 'report' ), true ) ) {
	$kdna_wh_tab = '';
}
?>
<div class="wrap kdna-wh-wrap">

	<h1><?php esc_html_e( 'Water Hardness Data', 'kdna-water-hardness' ); ?></h1>

	<?php if ( $kdna_wh_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $kdna_wh_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $kdna_wh_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $kdna_wh_tab ) : ?>
		<nav class="nav-tab-wrapper wp-clearfix">
			<?php
			$kdna_wh_tabs = array(
				'import'    => __( 'Import CSV', 'kdna-water-hardness' ),
				'countries' => __( 'Countries and sources', 'kdna-water-hardness' ),
				'browse'    => __( 'Browse data', 'kdna-water-hardness' ),
			);

			foreach ( $kdna_wh_tabs as $kdna_wh_slug => $kdna_wh_label ) :
				?>
				<a href="<?php echo esc_url( KDNA_WH_Admin_Import::page_url( array( 'tab' => $kdna_wh_slug ) ) ); ?>"
					class="nav-tab <?php echo $kdna_wh_tab === $kdna_wh_slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $kdna_wh_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php
	// -----------------------------------------------------------------------
	// Step two: column mapping.
	// -----------------------------------------------------------------------
	if ( $kdna_wh_job && 'map' === $kdna_wh_step ) :
		$kdna_wh_fields  = KDNA_WH_Importer::fields( $kdna_wh_job['type'] );
		$kdna_wh_preview = KDNA_WH_CSV::read_preview( $kdna_wh_job['path'], $kdna_wh_job['delimiter'], 3 );
		?>

		<h2><?php esc_html_e( 'Match the columns', 'kdna-water-hardness' ); ?></h2>

		<p class="kdna-wh-intro">
			<?php
			printf(
				/* translators: 1: file name, 2: number of rows, 3: country code. */
				esc_html__( 'Reading %1$s, %2$s rows, for %3$s. The plugin has guessed the columns below from your headings. Correct anything it has got wrong.', 'kdna-water-hardness' ),
				'<strong>' . esc_html( $kdna_wh_job['filename'] ) . '</strong>',
				esc_html( number_format_i18n( $kdna_wh_job['total_rows'] ) ),
				'<strong>' . esc_html( KDNA_WH_Sources::country_name( $kdna_wh_job['country'] ) ) . '</strong>'
			);
			?>
		</p>

		<?php if ( $kdna_wh_preview ) : ?>
			<h3><?php esc_html_e( 'First rows of your file', 'kdna-water-hardness' ); ?></h3>
			<div class="kdna-wh-scroll">
				<table class="widefat striped kdna-wh-preview">
					<thead>
						<tr>
							<?php foreach ( $kdna_wh_job['header'] as $kdna_wh_name ) : ?>
								<th scope="col"><?php echo esc_html( $kdna_wh_name ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $kdna_wh_preview as $kdna_wh_row ) : ?>
							<tr>
								<?php foreach ( $kdna_wh_job['header'] as $kdna_wh_index => $kdna_wh_name ) : ?>
									<td><?php echo esc_html( isset( $kdna_wh_row[ $kdna_wh_index ] ) ? $kdna_wh_row[ $kdna_wh_index ] : '' ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'kdna_wh_map' ); ?>
			<input type="hidden" name="action" value="kdna_wh_map">
			<input type="hidden" name="token" value="<?php echo esc_attr( $kdna_wh_job['token'] ); ?>">

			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $kdna_wh_fields as $kdna_wh_field => $kdna_wh_config ) : ?>
						<tr>
							<th scope="row">
								<label for="kdna-wh-map-<?php echo esc_attr( $kdna_wh_field ); ?>">
									<?php echo esc_html( $kdna_wh_config['label'] ); ?>
									<?php if ( ! empty( $kdna_wh_config['required'] ) ) : ?>
										<span class="kdna-wh-required" aria-hidden="true">*</span>
										<span class="screen-reader-text"><?php esc_html_e( '(required)', 'kdna-water-hardness' ); ?></span>
									<?php endif; ?>
								</label>
							</th>
							<td>
								<select name="mapping[<?php echo esc_attr( $kdna_wh_field ); ?>]" id="kdna-wh-map-<?php echo esc_attr( $kdna_wh_field ); ?>">
									<option value="-1"><?php esc_html_e( '— not in this file —', 'kdna-water-hardness' ); ?></option>
									<?php foreach ( $kdna_wh_job['header'] as $kdna_wh_index => $kdna_wh_name ) : ?>
										<option value="<?php echo esc_attr( $kdna_wh_index ); ?>"
											<?php selected( isset( $kdna_wh_job['mapping'][ $kdna_wh_field ] ) ? $kdna_wh_job['mapping'][ $kdna_wh_field ] : -1, $kdna_wh_index ); ?>>
											<?php echo esc_html( $kdna_wh_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html( $kdna_wh_config['help'] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php if ( 'zones' === $kdna_wh_job['type'] ) : ?>
						<tr>
							<th scope="row">
								<label for="kdna-wh-unit"><?php esc_html_e( 'Unit used in this file', 'kdna-water-hardness' ); ?></label>
							</th>
							<td>
								<select name="unit" id="kdna-wh-unit">
									<?php foreach ( KDNA_WH_Units::options() as $kdna_wh_key => $kdna_wh_label ) : ?>
										<option value="<?php echo esc_attr( $kdna_wh_key ); ?>" <?php selected( $kdna_wh_job['unit'], $kdna_wh_key ); ?>>
											<?php echo esc_html( $kdna_wh_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Values are converted to mg/L as CaCO3 on the way in and stored in that one unit. Get this wrong and every figure in the file will be wrong, so check the units on the report before importing.', 'kdna-water-hardness' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="kdna-wh-confidence"><?php esc_html_e( 'Confidence', 'kdna-water-hardness' ); ?></label>
							</th>
							<td>
								<select name="default_confidence" id="kdna-wh-confidence">
									<option value="estimated" <?php selected( $kdna_wh_job['default_confidence'], 'estimated' ); ?>>
										<?php esc_html_e( 'Estimated, inferred from an average, a neighbouring zone or geology', 'kdna-water-hardness' ); ?>
									</option>
									<option value="verified" <?php selected( $kdna_wh_job['default_confidence'], 'verified' ); ?>>
										<?php esc_html_e( 'Verified, taken from a published report for this named zone', 'kdna-water-hardness' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Applied to every row, unless you mapped a confidence column above. Only verified figures are given as a straight answer on the front end.', 'kdna-water-hardness' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'kdna-water-hardness' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Import options', 'kdna-water-hardness' ); ?></legend>

								<label for="kdna-wh-replace">
									<input type="checkbox" name="replace" id="kdna-wh-replace" value="1">
									<?php
									if ( 'zones' === $kdna_wh_job['type'] ) {
										printf(
											/* translators: %s: country name. */
											esc_html__( 'Delete all existing %s data first', 'kdna-water-hardness' ),
											esc_html( KDNA_WH_Sources::country_name( $kdna_wh_job['country'] ) )
										);
									} else {
										printf(
											/* translators: %s: country name. */
											esc_html__( 'Delete all existing %s postcode mappings first', 'kdna-water-hardness' ),
											esc_html( KDNA_WH_Sources::country_name( $kdna_wh_job['country'] ) )
										);
									}
									?>
								</label>
								<p class="description">
									<?php if ( 'zones' === $kdna_wh_job['type'] ) : ?>
										<?php esc_html_e( 'Use this when the file is a complete replacement. Removing zones also removes that country\'s postcode mappings, because a mapping to a deleted zone matches nothing, so you will need to import the postcode file again afterwards. Leave it unticked to add to what is already held.', 'kdna-water-hardness' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Use this when the file is a complete replacement. Leave it unticked to add to what is already held. Either way, a mapping that already exists is never duplicated.', 'kdna-water-hardness' ); ?>
									<?php endif; ?>
								</p>

								<?php if ( 'zones' === $kdna_wh_job['type'] ) : ?>
									<br>
									<label for="kdna-wh-allow-missing">
										<input type="checkbox" name="allow_missing_source" id="kdna-wh-allow-missing" value="1">
										<?php esc_html_e( 'Import rows that have no source URL or publication date', 'kdna-water-hardness' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Normally a row without a traceable source is rejected, because every figure shown to a customer should be attributable to a published document. Tick this while you are still compiling, and those rows will be stored as estimated whatever else the file says.', 'kdna-water-hardness' ); ?>
									</p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Start import', 'kdna-water-hardness' ); ?></button>
				<a class="button button-link-delete" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'kdna_wh_cancel',
								'token'  => $kdna_wh_job['token'],
							),
							admin_url( 'admin-post.php' )
						),
						'kdna_wh_cancel'
					)
				);
				?>
				"><?php esc_html_e( 'Cancel', 'kdna-water-hardness' ); ?></a>
			</p>
		</form>

		<?php
		// -------------------------------------------------------------------
		// Step three: progress.
		// -------------------------------------------------------------------
	elseif ( $kdna_wh_job && 'run' === $kdna_wh_step ) :
		$kdna_wh_total   = max( 1, (int) $kdna_wh_job['total_rows'] );
		$kdna_wh_percent = min( 100, (int) round( ( $kdna_wh_job['processed'] / $kdna_wh_total ) * 100 ) );
		?>

		<h2><?php esc_html_e( 'Importing', 'kdna-water-hardness' ); ?></h2>

		<p class="kdna-wh-intro">
			<?php esc_html_e( 'The file is being read in blocks, so a large one does not have to finish in a single request. Leave this page open. It moves on by itself.', 'kdna-water-hardness' ); ?>
		</p>

		<div class="kdna-wh-progress" role="progressbar"
			aria-valuenow="<?php echo esc_attr( $kdna_wh_percent ); ?>" aria-valuemin="0" aria-valuemax="100"
			aria-label="<?php esc_attr_e( 'Import progress', 'kdna-water-hardness' ); ?>">
			<span class="kdna-wh-progress-bar" style="width:<?php echo esc_attr( $kdna_wh_percent ); ?>%"></span>
		</div>

		<p>
			<?php
			printf(
				/* translators: 1: rows processed, 2: total rows, 3: percentage. */
				esc_html__( '%1$s of %2$s rows read, %3$s%%.', 'kdna-water-hardness' ),
				'<strong>' . esc_html( number_format_i18n( $kdna_wh_job['processed'] ) ) . '</strong>',
				esc_html( number_format_i18n( $kdna_wh_job['total_rows'] ) ),
				esc_html( $kdna_wh_percent )
			);
			?>
			<br>
			<?php
			printf(
				/* translators: 1: rows imported, 2: rows skipped. */
				esc_html__( '%1$s imported, %2$s skipped so far.', 'kdna-water-hardness' ),
				esc_html( number_format_i18n( $kdna_wh_job['imported'] ) ),
				esc_html( number_format_i18n( $kdna_wh_job['skipped'] ) )
			);
			?>
		</p>

		<p>
			<a class="button" href="<?php echo esc_url( KDNA_WH_Admin_Import::run_url( $kdna_wh_job['token'] ) ); ?>">
				<?php esc_html_e( 'Continue', 'kdna-water-hardness' ); ?>
			</a>
			<a class="button button-link-delete" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'kdna_wh_cancel',
							'token'  => $kdna_wh_job['token'],
						),
						admin_url( 'admin-post.php' )
					),
					'kdna_wh_cancel'
				)
			);
			?>
			"><?php esc_html_e( 'Stop', 'kdna-water-hardness' ); ?></a>
		</p>

		<p class="description">
			<?php esc_html_e( 'If this page stops moving on its own, use Continue.', 'kdna-water-hardness' ); ?>
		</p>

		<?php
		// -------------------------------------------------------------------
		// Step four: the report.
		// -------------------------------------------------------------------
	elseif ( $kdna_wh_job && 'report' === $kdna_wh_step ) :
		?>

		<h2><?php esc_html_e( 'Import finished', 'kdna-water-hardness' ); ?></h2>

		<table class="widefat striped kdna-wh-table kdna-wh-summary">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'File', 'kdna-water-hardness' ); ?></th>
					<td><?php echo esc_html( $kdna_wh_job['filename'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></th>
					<td><?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_job['country'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rows read', 'kdna-water-hardness' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $kdna_wh_job['processed'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Imported', 'kdna-water-hardness' ); ?></th>
					<td><strong><?php echo esc_html( number_format_i18n( $kdna_wh_job['imported'] ) ); ?></strong></td>
				</tr>
				<?php if ( $kdna_wh_job['duplicates'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Already held', 'kdna-water-hardness' ); ?></th>
						<td>
							<?php echo esc_html( number_format_i18n( $kdna_wh_job['duplicates'] ) ); ?>
							<span class="description"><?php esc_html_e( 'skipped, because the same postcode was already attached to the same zone', 'kdna-water-hardness' ); ?></span>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rejected', 'kdna-water-hardness' ); ?></th>
					<td>
						<?php if ( $kdna_wh_job['error_count'] ) : ?>
							<strong class="kdna-wh-bad"><?php echo esc_html( number_format_i18n( $kdna_wh_job['error_count'] ) ); ?></strong>
						<?php else : ?>
							<?php echo esc_html( number_format_i18n( 0 ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $kdna_wh_job['errors'] ) ) : ?>
			<h3><?php esc_html_e( 'Rows that could not be imported', 'kdna-water-hardness' ); ?></h3>

			<p class="kdna-wh-intro">
				<?php esc_html_e( 'Row numbers match your spreadsheet, counting the heading as row 1. Everything else in the file was imported, so fixing these rows and importing the corrected file again will pick up only what is missing.', 'kdna-water-hardness' ); ?>
			</p>

			<?php if ( $kdna_wh_job['error_count'] > count( $kdna_wh_job['errors'] ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: 1: number shown, 2: total number of failed rows. */
							esc_html__( 'Showing the first %1$s of %2$s rejected rows.', 'kdna-water-hardness' ),
							esc_html( number_format_i18n( count( $kdna_wh_job['errors'] ) ) ),
							esc_html( number_format_i18n( $kdna_wh_job['error_count'] ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<table class="widefat striped kdna-wh-table">
				<thead>
					<tr>
						<th scope="col" class="kdna-wh-col-row"><?php esc_html_e( 'Row', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Problem', 'kdna-water-hardness' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kdna_wh_job['errors'] as $kdna_wh_error ) : ?>
						<tr>
							<td><?php echo esc_html( number_format_i18n( $kdna_wh_error['line'] ) ); ?></td>
							<td><code><?php echo esc_html( '' === $kdna_wh_error['value'] ? '—' : $kdna_wh_error['value'] ); ?></code></td>
							<td><?php echo esc_html( $kdna_wh_error['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="submit">
			<a class="button button-primary" href="<?php echo esc_url( KDNA_WH_Admin_Import::page_url() ); ?>">
				<?php esc_html_e( 'Import another file', 'kdna-water-hardness' ); ?>
			</a>
			<a class="button" href="
			<?php
			echo esc_url(
				KDNA_WH_Admin_Import::page_url(
					array(
						'tab'     => 'browse',
						'country' => $kdna_wh_job['country'],
					)
				)
			);
			?>
			"><?php esc_html_e( 'Look at the data', 'kdna-water-hardness' ); ?></a>
		</p>

		<?php
		// -------------------------------------------------------------------
		// Tab: browse the data.
		// -------------------------------------------------------------------
	elseif ( 'browse' === $kdna_wh_tab ) :
		require KDNA_WH_PATH . 'admin/views/import-browse.php';

		// -------------------------------------------------------------------
		// Tab: countries and their source links.
		// -------------------------------------------------------------------
	elseif ( 'countries' === $kdna_wh_tab ) :
		require KDNA_WH_PATH . 'admin/views/import-countries.php';

		// -------------------------------------------------------------------
		// Tab: upload a file. The default.
		// -------------------------------------------------------------------
	else :
		require KDNA_WH_PATH . 'admin/views/import-upload.php';
	endif;
	?>

</div>
