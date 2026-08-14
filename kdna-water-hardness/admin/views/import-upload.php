<?php
/**
 * Data Import, upload tab.
 *
 * Step one of the import: choose the file, what it holds, and which country
 * it covers. Column mapping comes next, once the headings can be read.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

$kdna_wh_countries = KDNA_WH_Sources::get_known_countries();

// Somewhere to start from on a fresh install, without limiting what can be
// added. Any two letter code the user types is accepted.
if ( ! $kdna_wh_countries ) {
	$kdna_wh_countries = array( 'AU' );
}
?>

<h2><?php esc_html_e( 'Import a CSV', 'kdna-water-hardness' ); ?></h2>

<p class="kdna-wh-intro">
	<?php esc_html_e( 'Two kinds of file are imported, and the order matters. Zones carry the hardness figures. Postcode mappings attach postcodes to those zones, so the zones have to be in place first.', 'kdna-water-hardness' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
	<?php wp_nonce_field( 'kdna_wh_upload' ); ?>
	<input type="hidden" name="action" value="kdna_wh_upload">

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'What does this file hold?', 'kdna-water-hardness' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'CSV type', 'kdna-water-hardness' ); ?></legend>

						<label for="kdna-wh-type-zones">
							<input type="radio" name="csv_type" id="kdna-wh-type-zones" value="zones" checked>
							<strong><?php esc_html_e( 'Supply zones', 'kdna-water-hardness' ); ?></strong>
						</label>
						<p class="description kdna-wh-indent">
							<?php esc_html_e( 'One row per named supply zone, with its hardness figure and the report it came from.', 'kdna-water-hardness' ); ?>
						</p>

						<label for="kdna-wh-type-postcodes">
							<input type="radio" name="csv_type" id="kdna-wh-type-postcodes" value="postcodes">
							<strong><?php esc_html_e( 'Postcode mappings', 'kdna-water-hardness' ); ?></strong>
						</label>
						<p class="description kdna-wh-indent">
							<?php esc_html_e( 'One row per postcode and zone pair. A postcode that spans two zones gets two rows, which is normal and is exactly what lets the front end say so.', 'kdna-water-hardness' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kdna-wh-country"><?php esc_html_e( 'Country', 'kdna-water-hardness' ); ?></label></th>
				<td>
					<select name="country" id="kdna-wh-country">
						<?php foreach ( $kdna_wh_countries as $kdna_wh_code ) : ?>
							<option value="<?php echo esc_attr( $kdna_wh_code ); ?>">
								<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) . ' (' . $kdna_wh_code . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="kdna-wh-new-country" class="kdna-wh-inline-label">
						<?php esc_html_e( 'or add a new one', 'kdna-water-hardness' ); ?>
					</label>
					<input type="text" name="new_country" id="kdna-wh-new-country" size="4" maxlength="2"
						placeholder="<?php esc_attr_e( 'GB', 'kdna-water-hardness' ); ?>" class="kdna-wh-country-input">

					<p class="description">
						<?php esc_html_e( 'Two letter ISO country code. Importing a country for the first time is all it takes to add it: once it holds zone data it appears in the front-end country selector on its own, with no code change.', 'kdna-water-hardness' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kdna-wh-file"><?php esc_html_e( 'CSV file', 'kdna-water-hardness' ); ?></label></th>
				<td>
					<input type="file" name="csv_file" id="kdna-wh-file" accept=".csv,.txt,.tsv,text/csv" required>
					<p class="description">
						<?php
						printf(
							/* translators: %s: maximum upload size. */
							esc_html__( 'Up to %s. Column order does not matter, and commas, semicolons or tabs are all read correctly. You match the columns on the next screen.', 'kdna-water-hardness' ),
							esc_html( size_format( wp_max_upload_size() ) )
						);
						?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Upload and continue', 'kdna-water-hardness' ); ?></button>
	</p>
</form>

<h3><?php esc_html_e( 'What the files should contain', 'kdna-water-hardness' ); ?></h3>

<div class="kdna-wh-columns">
	<div class="kdna-wh-column">
		<h4><?php esc_html_e( 'Supply zones', 'kdna-water-hardness' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Required: zone name, hardness value, source URL, source date. Optional: utility name, confidence, country code.', 'kdna-water-hardness' ); ?></p>
		<pre class="kdna-wh-sample">zone_name,utility,hardness,source_url,source_date
Two Rocks,Water Corporation,228,https://example.org/report.pdf,2025-07-01
Dwellingup,Water Corporation,29,https://example.org/report.pdf,2025-07-01</pre>
	</div>

	<div class="kdna-wh-column">
		<h4><?php esc_html_e( 'Postcode mappings', 'kdna-water-hardness' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Required: postcode, and either a zone name or a zone ID. Optional: utility name, country code.', 'kdna-water-hardness' ); ?></p>
		<pre class="kdna-wh-sample">postcode,zone_name
6037,Two Rocks
6213,Dwellingup
6213,Pinjarra</pre>
	</div>
</div>

<p class="description">
	<?php esc_html_e( 'The two rows for 6213 above are not a mistake. That is how a postcode spanning two supply zones is recorded, and it is what lets the tool report a range, or say the answer is inconclusive, instead of quietly picking one.', 'kdna-water-hardness' ); ?>
</p>
