<?php
/**
 * Lookup Log screen.
 *
 * Grouped by postcode and band, because that is the shape the log is useful
 * in. A raw list of every lookup answers nothing; counts per postcode show
 * where the hard-water customers actually are, which is what the geographic
 * targeting in paid media is for.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

$kdna_wh_notice  = KDNA_WH_Admin_Import::get_notice();
$kdna_wh_filters = KDNA_WH_Admin_Log::current_filters();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- paging a list screen.
$kdna_wh_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$kdna_wh_per_page = 50;

$kdna_wh_query           = $kdna_wh_filters;
$kdna_wh_query['limit']  = $kdna_wh_per_page;
$kdna_wh_query['offset'] = ( $kdna_wh_paged - 1 ) * $kdna_wh_per_page;

$kdna_wh_rows    = KDNA_WH_DB::get_lookup_aggregate( $kdna_wh_query );
$kdna_wh_groups  = KDNA_WH_DB::count_lookup_aggregate( $kdna_wh_filters );
$kdna_wh_total   = KDNA_WH_DB::count_lookups_filtered( $kdna_wh_filters );
$kdna_wh_bands   = KDNA_WH_DB::get_band_totals( $kdna_wh_filters );
$kdna_wh_range   = KDNA_WH_DB::get_lookup_date_range();
$kdna_wh_present = KDNA_WH_Sources::get_known_countries();
?>
<div class="wrap kdna-wh-wrap">

	<h1><?php esc_html_e( 'Lookup Log', 'kdna-water-hardness' ); ?></h1>

	<?php if ( $kdna_wh_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $kdna_wh_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $kdna_wh_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="kdna-wh-intro">
		<?php esc_html_e( 'Every lookup is recorded as a country, a postcode, the figure served and the band it fell in. No IP address, no email address, and nothing else identifying a person is stored.', 'kdna-water-hardness' ); ?>
	</p>

	<?php if ( ! $kdna_wh_total && ! array_filter( $kdna_wh_filters ) ) : ?>

		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Nothing logged yet. Lookups appear here as visitors use the tool.', 'kdna-water-hardness' ); ?></p>
		</div>

	<?php else : ?>

		<div class="kdna-wh-tiles">
			<div class="kdna-wh-tile">
				<span class="kdna-wh-tile__figure"><?php echo esc_html( number_format_i18n( $kdna_wh_total ) ); ?></span>
				<span class="kdna-wh-tile__label"><?php esc_html_e( 'Lookups', 'kdna-water-hardness' ); ?></span>
			</div>
			<div class="kdna-wh-tile">
				<span class="kdna-wh-tile__figure"><?php echo esc_html( number_format_i18n( $kdna_wh_groups ) ); ?></span>
				<span class="kdna-wh-tile__label"><?php esc_html_e( 'Postcode and band groups', 'kdna-water-hardness' ); ?></span>
			</div>

			<?php foreach ( array_slice( $kdna_wh_bands, 0, 4 ) as $kdna_wh_band_row ) : ?>
				<div class="kdna-wh-tile">
					<span class="kdna-wh-tile__figure"><?php echo esc_html( number_format_i18n( $kdna_wh_band_row['lookups'] ) ); ?></span>
					<span class="kdna-wh-tile__label">
						<?php echo esc_html( KDNA_WH_Admin_Log::band_label( $kdna_wh_band_row['band'], $kdna_wh_filters['country_code'] ) ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="get" class="kdna-wh-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_WH_Admin::MENU_SLUG . '-log' ); ?>">

			<label for="kdna-wh-log-country" class="screen-reader-text"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></label>
			<select name="country" id="kdna-wh-log-country">
				<option value=""><?php esc_html_e( 'All countries', 'kdna-water-hardness' ); ?></option>
				<?php foreach ( $kdna_wh_present as $kdna_wh_code ) : ?>
					<option value="<?php echo esc_attr( $kdna_wh_code ); ?>" <?php selected( $kdna_wh_filters['country_code'], $kdna_wh_code ); ?>>
						<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="kdna-wh-log-band" class="screen-reader-text"><?php esc_html_e( 'Band', 'kdna-water-hardness' ); ?></label>
			<select name="band" id="kdna-wh-log-band">
				<option value=""><?php esc_html_e( 'All results', 'kdna-water-hardness' ); ?></option>
				<?php foreach ( $kdna_wh_bands as $kdna_wh_band_row ) : ?>
					<option value="<?php echo esc_attr( $kdna_wh_band_row['band'] ); ?>" <?php selected( $kdna_wh_filters['band'], $kdna_wh_band_row['band'] ); ?>>
						<?php echo esc_html( KDNA_WH_Admin_Log::band_label( $kdna_wh_band_row['band'], $kdna_wh_filters['country_code'] ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="kdna-wh-log-from" class="screen-reader-text"><?php esc_html_e( 'From', 'kdna-water-hardness' ); ?></label>
			<input type="date" name="from" id="kdna-wh-log-from" value="<?php echo esc_attr( $kdna_wh_filters['date_from'] ); ?>">

			<label for="kdna-wh-log-to" class="screen-reader-text"><?php esc_html_e( 'To', 'kdna-water-hardness' ); ?></label>
			<input type="date" name="to" id="kdna-wh-log-to" value="<?php echo esc_attr( $kdna_wh_filters['date_to'] ); ?>">

			<label for="kdna-wh-log-search" class="screen-reader-text"><?php esc_html_e( 'Postcode', 'kdna-water-hardness' ); ?></label>
			<input type="search" name="s" id="kdna-wh-log-search" value="<?php echo esc_attr( $kdna_wh_filters['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'Postcode', 'kdna-water-hardness' ); ?>">

			<label for="kdna-wh-log-order" class="screen-reader-text"><?php esc_html_e( 'Sort by', 'kdna-water-hardness' ); ?></label>
			<select name="orderby" id="kdna-wh-log-order">
				<option value="lookups" <?php selected( $kdna_wh_filters['orderby'], 'lookups' ); ?>><?php esc_html_e( 'Most looked up', 'kdna-water-hardness' ); ?></option>
				<option value="last_seen" <?php selected( $kdna_wh_filters['orderby'], 'last_seen' ); ?>><?php esc_html_e( 'Most recent', 'kdna-water-hardness' ); ?></option>
				<option value="hardness" <?php selected( $kdna_wh_filters['orderby'], 'hardness' ); ?>><?php esc_html_e( 'Hardest water', 'kdna-water-hardness' ); ?></option>
				<option value="postcode" <?php selected( $kdna_wh_filters['orderby'], 'postcode' ); ?>><?php esc_html_e( 'Postcode', 'kdna-water-hardness' ); ?></option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'kdna-water-hardness' ); ?></button>

			<?php if ( array_filter( $kdna_wh_filters ) ) : ?>
				<a class="button-link" href="<?php echo esc_url( KDNA_WH_Admin_Log::page_url() ); ?>"><?php esc_html_e( 'Clear', 'kdna-water-hardness' ); ?></a>
			<?php endif; ?>
		</form>

		<?php if ( ! $kdna_wh_rows ) : ?>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'No lookups match those filters.', 'kdna-water-hardness' ); ?></p>
			</div>

		<?php else : ?>

			<table class="widefat striped kdna-wh-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Postcode', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lookups', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Hardness served', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'First seen', 'kdna-water-hardness' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last seen', 'kdna-water-hardness' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kdna_wh_rows as $kdna_wh_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $kdna_wh_row['postcode'] ); ?></strong></td>
							<td><?php echo esc_html( $kdna_wh_row['country_code'] ); ?></td>
							<td><?php echo esc_html( KDNA_WH_Admin_Log::band_label( $kdna_wh_row['band'], $kdna_wh_row['country_code'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $kdna_wh_row['lookups'] ) ); ?></td>
							<td>
								<?php
								echo esc_html(
									null === $kdna_wh_row['avg_hardness']
										? '—'
										: KDNA_WH_Units::format( $kdna_wh_row['avg_hardness'] )
								);
								?>
							</td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $kdna_wh_row['first_seen'] ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $kdna_wh_row['last_seen'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$kdna_wh_pages = (int) ceil( $kdna_wh_groups / $kdna_wh_per_page );

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

		<?php endif; ?>

		<h2><?php esc_html_e( 'Export', 'kdna-water-hardness' ); ?></h2>

		<p class="kdna-wh-intro">
			<?php esc_html_e( 'Downloads exactly what the filters above are showing, grouped the same way, as a CSV.', 'kdna-water-hardness' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-wh-inline-form">
			<?php wp_nonce_field( 'kdna_wh_export_log' ); ?>
			<input type="hidden" name="action" value="kdna_wh_export_log">
			<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_filters['country_code'] ); ?>">
			<input type="hidden" name="band" value="<?php echo esc_attr( $kdna_wh_filters['band'] ); ?>">
			<input type="hidden" name="s" value="<?php echo esc_attr( $kdna_wh_filters['search'] ); ?>">
			<input type="hidden" name="from" value="<?php echo esc_attr( $kdna_wh_filters['date_from'] ); ?>">
			<input type="hidden" name="to" value="<?php echo esc_attr( $kdna_wh_filters['date_to'] ); ?>">
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $kdna_wh_filters['orderby'] ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Download CSV', 'kdna-water-hardness' ); ?></button>
		</form>

		<h2><?php esc_html_e( 'Clearing the log', 'kdna-water-hardness' ); ?></h2>

		<?php if ( $kdna_wh_range['first'] ) : ?>
			<p class="kdna-wh-intro">
				<?php
				printf(
					/* translators: 1: first date, 2: last date. */
					esc_html__( 'The log runs from %1$s to %2$s.', 'kdna-water-hardness' ),
					esc_html( mysql2date( get_option( 'date_format' ), $kdna_wh_range['first'] ) ),
					esc_html( mysql2date( get_option( 'date_format' ), $kdna_wh_range['last'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'kdna_wh_clear_log' ); ?>
			<input type="hidden" name="action" value="kdna_wh_clear_log">

			<label for="kdna-wh-clear-before"><?php esc_html_e( 'Delete everything logged before', 'kdna-water-hardness' ); ?></label>
			<input type="date" name="before" id="kdna-wh-clear-before">

			<button type="submit" class="button kdna-wh-confirm"
				data-confirm="<?php esc_attr_e( 'Delete the logged lookups? Leave the date empty and the whole log goes. This cannot be undone.', 'kdna-water-hardness' ); ?>">
				<?php esc_html_e( 'Delete', 'kdna-water-hardness' ); ?>
			</button>

			<p class="description">
				<?php esc_html_e( 'Leave the date empty to clear the whole log.', 'kdna-water-hardness' ); ?>
			</p>
		</form>

	<?php endif; ?>

</div>
