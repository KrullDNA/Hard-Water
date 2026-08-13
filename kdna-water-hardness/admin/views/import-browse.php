<?php
/**
 * Data Import, browse tab.
 *
 * Shows what is actually held, filtered by country, so a figure can be checked
 * against its source without going near the database. Deletion lives here too,
 * both for a selection of zones and for a whole country.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on a list screen.
$kdna_wh_country = isset( $_GET['country'] ) ? KDNA_WH_DB::normalise_country( wp_unslash( $_GET['country'] ) ) : '';
$kdna_wh_view    = isset( $_GET['view'] ) && 'postcodes' === $_GET['view'] ? 'postcodes' : 'zones';
$kdna_wh_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$kdna_wh_paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$kdna_wh_per_page = 50;
$kdna_wh_args     = array(
	'country_code' => $kdna_wh_country,
	'search'       => $kdna_wh_search,
	'limit'        => $kdna_wh_per_page,
	'offset'       => ( $kdna_wh_paged - 1 ) * $kdna_wh_per_page,
);

if ( 'postcodes' === $kdna_wh_view ) {
	$kdna_wh_rows  = KDNA_WH_DB::get_postcode_mappings( $kdna_wh_args );
	$kdna_wh_total = KDNA_WH_DB::count_mappings_filtered( $kdna_wh_args );
} else {
	$kdna_wh_rows  = KDNA_WH_DB::get_zones( $kdna_wh_args );
	$kdna_wh_total = KDNA_WH_DB::count_zones_filtered( $kdna_wh_args );
}

$kdna_wh_countries = KDNA_WH_Sources::get_known_countries();
$kdna_wh_orphans   = KDNA_WH_DB::count_orphan_mappings( $kdna_wh_country );
?>

<h2><?php esc_html_e( 'Browse data', 'kdna-water-hardness' ); ?></h2>

<form method="get" class="kdna-wh-filters">
	<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_WH_Admin::MENU_SLUG . '-import' ); ?>">
	<input type="hidden" name="tab" value="browse">

	<label for="kdna-wh-filter-view" class="screen-reader-text"><?php esc_html_e( 'Show', 'kdna-water-hardness' ); ?></label>
	<select name="view" id="kdna-wh-filter-view">
		<option value="zones" <?php selected( $kdna_wh_view, 'zones' ); ?>><?php esc_html_e( 'Supply zones', 'kdna-water-hardness' ); ?></option>
		<option value="postcodes" <?php selected( $kdna_wh_view, 'postcodes' ); ?>><?php esc_html_e( 'Postcode mappings', 'kdna-water-hardness' ); ?></option>
	</select>

	<label for="kdna-wh-filter-country" class="screen-reader-text"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></label>
	<select name="country" id="kdna-wh-filter-country">
		<option value=""><?php esc_html_e( 'All countries', 'kdna-water-hardness' ); ?></option>
		<?php foreach ( $kdna_wh_countries as $kdna_wh_code ) : ?>
			<option value="<?php echo esc_attr( $kdna_wh_code ); ?>" <?php selected( $kdna_wh_country, $kdna_wh_code ); ?>>
				<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label for="kdna-wh-filter-search" class="screen-reader-text"><?php esc_html_e( 'Search', 'kdna-water-hardness' ); ?></label>
	<input type="search" name="s" id="kdna-wh-filter-search" value="<?php echo esc_attr( $kdna_wh_search ); ?>"
		placeholder="<?php echo 'postcodes' === $kdna_wh_view ? esc_attr__( 'Postcode', 'kdna-water-hardness' ) : esc_attr__( 'Zone or utility', 'kdna-water-hardness' ); ?>">

	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'kdna-water-hardness' ); ?></button>

	<?php if ( $kdna_wh_country || $kdna_wh_search ) : ?>
		<a class="button-link" href="<?php echo esc_url( KDNA_WH_Admin_Import::page_url( array( 'tab' => 'browse' ) ) ); ?>">
			<?php esc_html_e( 'Clear', 'kdna-water-hardness' ); ?>
		</a>
	<?php endif; ?>
</form>

<?php if ( $kdna_wh_orphans ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php
			printf(
				/* translators: %s: number of mappings. */
				esc_html( _n( '%s postcode mapping points at a zone that no longer exists, so those postcodes match nothing. Re-import the postcode file for this country.', '%s postcode mappings point at zones that no longer exist, so those postcodes match nothing. Re-import the postcode file for this country.', $kdna_wh_orphans, 'kdna-water-hardness' ) ),
				esc_html( number_format_i18n( $kdna_wh_orphans ) )
			);
			?>
		</p>
	</div>
<?php endif; ?>

<p class="kdna-wh-count">
	<?php
	printf(
		/* translators: %s: number of records. */
		esc_html( _n( '%s record', '%s records', $kdna_wh_total, 'kdna-water-hardness' ) ),
		esc_html( number_format_i18n( $kdna_wh_total ) )
	);
	?>
</p>

<?php if ( ! $kdna_wh_rows ) : ?>

	<div class="notice notice-info inline">
		<p><?php esc_html_e( 'Nothing to show with those filters.', 'kdna-water-hardness' ); ?></p>
	</div>

<?php elseif ( 'postcodes' === $kdna_wh_view ) : ?>

	<table class="widefat striped kdna-wh-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Postcode', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Zone', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Utility', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Hardness', 'kdna-water-hardness' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $kdna_wh_rows as $kdna_wh_row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $kdna_wh_row['postcode'] ); ?></strong></td>
					<td><?php echo esc_html( $kdna_wh_row['country_code'] ); ?></td>
					<td>
						<?php if ( null === $kdna_wh_row['zone_name'] ) : ?>
							<span class="kdna-wh-bad"><?php esc_html_e( 'Zone deleted', 'kdna-water-hardness' ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $kdna_wh_row['zone_name'] ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $kdna_wh_row['utility_name'] ? $kdna_wh_row['utility_name'] : '—' ); ?></td>
					<td>
						<?php if ( null === $kdna_wh_row['zone_name'] ) : ?>
							—
						<?php else : ?>
							<?php echo esc_html( KDNA_WH_Units::format( $kdna_wh_row['hardness_caco3'] ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php else : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'kdna_wh_delete_zones' ); ?>
		<input type="hidden" name="action" value="kdna_wh_delete_zones">
		<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_country ); ?>">

		<table class="widefat striped kdna-wh-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select all', 'kdna-water-hardness' ); ?></label>
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th scope="col"><?php esc_html_e( 'Zone', 'kdna-water-hardness' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Utility', 'kdna-water-hardness' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Hardness', 'kdna-water-hardness' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Confidence', 'kdna-water-hardness' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Source', 'kdna-water-hardness' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $kdna_wh_rows as $kdna_wh_row ) : ?>
					<tr>
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="kdna-wh-zone-<?php echo esc_attr( $kdna_wh_row['zone_id'] ); ?>">
								<?php
								printf(
									/* translators: %s: zone name. */
									esc_html__( 'Select %s', 'kdna-water-hardness' ),
									esc_html( $kdna_wh_row['zone_name'] )
								);
								?>
							</label>
							<input type="checkbox" name="zone_ids[]" id="kdna-wh-zone-<?php echo esc_attr( $kdna_wh_row['zone_id'] ); ?>"
								value="<?php echo esc_attr( $kdna_wh_row['zone_id'] ); ?>">
						</th>
						<td><strong><?php echo esc_html( $kdna_wh_row['zone_name'] ); ?></strong></td>
						<td><?php echo esc_html( $kdna_wh_row['utility_name'] ? $kdna_wh_row['utility_name'] : '—' ); ?></td>
						<td><?php echo esc_html( $kdna_wh_row['country_code'] ); ?></td>
						<td><?php echo esc_html( KDNA_WH_Units::format( $kdna_wh_row['hardness_caco3'] ) ); ?></td>
						<td>
							<?php if ( 'verified' === $kdna_wh_row['confidence'] ) : ?>
								<span class="kdna-wh-ok"><?php esc_html_e( 'Verified', 'kdna-water-hardness' ); ?></span>
							<?php else : ?>
								<span class="kdna-wh-muted"><?php esc_html_e( 'Estimated', 'kdna-water-hardness' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $kdna_wh_row['source_url'] ) : ?>
								<a href="<?php echo esc_url( $kdna_wh_row['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php
									echo esc_html(
										$kdna_wh_row['source_date']
											? mysql2date( get_option( 'date_format' ), $kdna_wh_row['source_date'] )
											: __( 'Report', 'kdna-water-hardness' )
									);
									?>
								</a>
							<?php else : ?>
								<span class="kdna-wh-bad"><?php esc_html_e( 'None', 'kdna-water-hardness' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button kdna-wh-confirm"
				data-confirm="<?php esc_attr_e( 'Delete the selected zones? Their postcode mappings go with them. This cannot be undone.', 'kdna-water-hardness' ); ?>">
				<?php esc_html_e( 'Delete selected zones', 'kdna-water-hardness' ); ?>
			</button>
		</p>
	</form>

<?php endif; ?>

<?php
$kdna_wh_pages = (int) ceil( $kdna_wh_total / $kdna_wh_per_page );

if ( $kdna_wh_pages > 1 ) :
	?>
	<div class="tablenav">
		<div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $kdna_wh_paged,
						'total'     => $kdna_wh_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			?>
		</div>
	</div>
<?php endif; ?>

<?php if ( $kdna_wh_country ) : ?>
	<h3><?php esc_html_e( 'Delete a country\'s data', 'kdna-water-hardness' ); ?></h3>

	<p class="kdna-wh-intro">
		<?php
		printf(
			/* translators: %s: country name. */
			esc_html__( 'Removes what is held for %s. The source links recorded for the country are kept, so you can import again from the same documents.', 'kdna-water-hardness' ),
			'<strong>' . esc_html( KDNA_WH_Sources::country_name( $kdna_wh_country ) ) . '</strong>'
		);
		?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
		<?php wp_nonce_field( 'kdna_wh_delete_data' ); ?>
		<input type="hidden" name="action" value="kdna_wh_delete_data">
		<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_country ); ?>">
		<input type="hidden" name="scope" value="postcodes">
		<button type="submit" class="button kdna-wh-confirm"
			data-confirm="<?php esc_attr_e( 'Delete every postcode mapping for this country? The zones and their hardness figures are kept.', 'kdna-water-hardness' ); ?>">
			<?php esc_html_e( 'Delete postcode mappings only', 'kdna-water-hardness' ); ?>
		</button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
		<?php wp_nonce_field( 'kdna_wh_delete_data' ); ?>
		<input type="hidden" name="action" value="kdna_wh_delete_data">
		<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_country ); ?>">
		<input type="hidden" name="scope" value="all">
		<button type="submit" class="button button-link-delete kdna-wh-confirm"
			data-confirm="<?php esc_attr_e( 'Delete every zone and every postcode mapping for this country? This cannot be undone.', 'kdna-water-hardness' ); ?>">
			<?php esc_html_e( 'Delete everything for this country', 'kdna-water-hardness' ); ?>
		</button>
	</form>
<?php endif; ?>
