<?php
/**
 * Settings, status tab.
 *
 * Proves the plugin installed correctly and shows what it is holding.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

$kdna_wh_status = KDNA_WH_Admin::get_status();
$kdna_wh_labels = array(
	'zones'     => __( 'Supply zones', 'kdna-water-hardness' ),
	'postcodes' => __( 'Postcode mappings', 'kdna-water-hardness' ),
	'lookups'   => __( 'Lookup log', 'kdna-water-hardness' ),
);
?>

<h2><?php esc_html_e( 'Installation status', 'kdna-water-hardness' ); ?></h2>

<?php
$kdna_wh_missing = wp_list_filter( $kdna_wh_status, array( 'exists' => false ) );

if ( $kdna_wh_missing ) :
	?>
	<div class="notice notice-error inline">
		<p>
			<?php esc_html_e( 'One or more database tables are missing. Deactivate and reactivate the plugin to rebuild them. No imported data is lost by deactivating.', 'kdna-water-hardness' ); ?>
		</p>
	</div>
<?php endif; ?>

<table class="widefat striped kdna-wh-table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Table', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Database name', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Created', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Rows', 'kdna-water-hardness' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $kdna_wh_status as $kdna_wh_key => $kdna_wh_row ) : ?>
			<tr>
				<td><strong><?php echo esc_html( isset( $kdna_wh_labels[ $kdna_wh_key ] ) ? $kdna_wh_labels[ $kdna_wh_key ] : $kdna_wh_key ); ?></strong></td>
				<td><code><?php echo esc_html( $kdna_wh_row['table'] ); ?></code></td>
				<td>
					<?php if ( $kdna_wh_row['exists'] ) : ?>
						<span class="kdna-wh-ok"><?php esc_html_e( 'Yes', 'kdna-water-hardness' ); ?></span>
					<?php else : ?>
						<span class="kdna-wh-bad"><?php esc_html_e( 'Missing', 'kdna-water-hardness' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( number_format_i18n( $kdna_wh_row['rows'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description">
	<?php
	printf(
		/* translators: 1: plugin version, 2: database schema version. */
		esc_html__( 'Plugin version %1$s, database schema %2$s.', 'kdna-water-hardness' ),
		esc_html( KDNA_WH_VERSION ),
		esc_html( (string) get_option( KDNA_WH_DB::OPT_DB_VERSION, __( 'not recorded', 'kdna-water-hardness' ) ) )
	);
	?>
</p>

<h2><?php esc_html_e( 'Putting the tool on a page', 'kdna-water-hardness' ); ?></h2>

<p class="kdna-wh-intro">
	<?php esc_html_e( 'Add this shortcode to any page or post:', 'kdna-water-hardness' ); ?>
</p>

<p><code>[kdna_water_hardness]</code></p>

<p class="description">
	<?php esc_html_e( 'The country selector appears by itself once more than one country holds data, and stays hidden while only one does. An Elementor widget with full design control follows in a later stage.', 'kdna-water-hardness' ); ?>
</p>

<h2><?php esc_html_e( 'Unit reference', 'kdna-water-hardness' ); ?></h2>

<p class="kdna-wh-intro">
	<?php esc_html_e( 'Hardness is stored in one canonical unit, mg/L as CaCO3, and converted on import and on display. The figures below show one unit of each measure expressed in the canonical unit.', 'kdna-water-hardness' ); ?>
</p>

<table class="widefat striped kdna-wh-table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Unit', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Shown as', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Used in', 'kdna-water-hardness' ); ?></th>
			<th scope="col"><?php esc_html_e( 'One unit in mg/L CaCO3', 'kdna-water-hardness' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$kdna_wh_factors = KDNA_WH_Units::factors();

		foreach ( KDNA_WH_Units::units() as $kdna_wh_unit_key => $kdna_wh_unit ) :
			?>
			<tr>
				<td><strong><?php echo esc_html( $kdna_wh_unit['label'] ); ?></strong></td>
				<td><?php echo esc_html( $kdna_wh_unit['abbr'] ); ?></td>
				<td><?php echo esc_html( $kdna_wh_unit['used_in'] ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $kdna_wh_factors[ $kdna_wh_unit_key ], 3 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
