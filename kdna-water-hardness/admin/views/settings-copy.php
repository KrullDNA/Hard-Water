<?php
/**
 * Settings, bands and copy tab.
 *
 * Everything a visitor reads is here, per country, along with the thresholds
 * that decide which block they get.
 *
 * Expects $kdna_wh_country, $kdna_wh_countries, $kdna_wh_config and
 * $kdna_wh_scale from settings.php.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders one editable copy block.
 *
 * Guarded because this is a view rather than a class file, and a view can in
 * principle be included more than once in a request.
 *
 * @param string $key   Band or state key.
 * @param string $title Heading shown above the fields.
 * @param array  $block Stored heading, body, CTA text and CTA URL.
 * @param string $help  Guidance shown under the title.
 * @return void
 */
if ( ! function_exists( 'kdna_wh_render_copy_fields' ) ) :
	function kdna_wh_render_copy_fields( $key, $title, array $block, $help = '' ) {
	$id = 'kdna-wh-copy-' . sanitize_key( $key );
	?>
	<div class="kdna-wh-copy-block">
		<h4><?php echo esc_html( $title ); ?></h4>

		<?php if ( $help ) : ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>

		<p>
			<label for="<?php echo esc_attr( $id ); ?>-heading"><?php esc_html_e( 'Heading', 'kdna-water-hardness' ); ?></label>
			<input type="text" class="large-text" id="<?php echo esc_attr( $id ); ?>-heading"
				name="copy[<?php echo esc_attr( $key ); ?>][heading]"
				value="<?php echo esc_attr( $block['heading'] ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $id ); ?>-body"><?php esc_html_e( 'Body copy', 'kdna-water-hardness' ); ?></label>
			<textarea class="large-text" rows="4" id="<?php echo esc_attr( $id ); ?>-body"
				name="copy[<?php echo esc_attr( $key ); ?>][body]"><?php echo esc_textarea( $block['body'] ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Basic formatting and links are allowed, the same as in a post.', 'kdna-water-hardness' ); ?></span>
		</p>

		<div class="kdna-wh-field-row">
			<p>
				<label for="<?php echo esc_attr( $id ); ?>-cta-text"><?php esc_html_e( 'Button text', 'kdna-water-hardness' ); ?></label>
				<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>-cta-text"
					name="copy[<?php echo esc_attr( $key ); ?>][cta_text]"
					value="<?php echo esc_attr( $block['cta_text'] ); ?>">
			</p>
			<p class="kdna-wh-grow">
				<label for="<?php echo esc_attr( $id ); ?>-cta-url"><?php esc_html_e( 'Button link', 'kdna-water-hardness' ); ?></label>
				<input type="url" class="large-text" id="<?php echo esc_attr( $id ); ?>-cta-url"
					name="copy[<?php echo esc_attr( $key ); ?>][cta_url]"
					value="<?php echo esc_attr( $block['cta_url'] ); ?>" placeholder="https://">
				<span class="description"><?php esc_html_e( 'Leave the link empty and no button is shown.', 'kdna-water-hardness' ); ?></span>
			</p>
		</div>
	</div>
		<?php
	}
endif;
?>

<h2><?php esc_html_e( 'Bands and copy', 'kdna-water-hardness' ); ?></h2>

<?php if ( count( $kdna_wh_countries ) > 1 ) : ?>
	<form method="get" class="kdna-wh-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_WH_Admin::MENU_SLUG ); ?>">
		<input type="hidden" name="tab" value="copy">

		<label for="kdna-wh-country-switch"><?php esc_html_e( 'Editing', 'kdna-water-hardness' ); ?></label>
		<select name="country" id="kdna-wh-country-switch">
			<?php foreach ( $kdna_wh_countries as $kdna_wh_code ) : ?>
				<option value="<?php echo esc_attr( $kdna_wh_code ); ?>" <?php selected( $kdna_wh_country, $kdna_wh_code ); ?>>
					<?php echo esc_html( KDNA_WH_Sources::country_name( $kdna_wh_code ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Switch country', 'kdna-water-hardness' ); ?></button>
	</form>
<?php else : ?>
	<p class="kdna-wh-intro">
		<?php
		printf(
			/* translators: %s: country name. */
			esc_html__( 'Editing %s. Import another country\'s data and it gets its own bands and copy here.', 'kdna-water-hardness' ),
			'<strong>' . esc_html( KDNA_WH_Sources::country_name( $kdna_wh_country ) ) . '</strong>'
		);
		?>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'kdna_wh_save_bands' ); ?>
	<input type="hidden" name="action" value="kdna_wh_save_bands">
	<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_country ); ?>">

	<h3><?php esc_html_e( 'Classification bands', 'kdna-water-hardness' ); ?></h3>

	<p class="kdna-wh-intro">
		<?php esc_html_e( 'Each band runs from the figure it starts at until the next band begins, so you only set the lower end. Figures are in mg/L as CaCO3, the unit everything is stored in, whatever the visitor sees.', 'kdna-water-hardness' ); ?>
	</p>

	<table class="widefat striped kdna-wh-table kdna-wh-bands-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Use', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Label', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Starts at', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Covers', 'kdna-water-hardness' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Colour', 'kdna-water-hardness' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$kdna_wh_ranges = array();

			foreach ( $kdna_wh_scale as $kdna_wh_segment ) {
				$kdna_wh_ranges[ $kdna_wh_segment['key'] ] = $kdna_wh_segment;
			}

			$kdna_wh_first = array_key_first( $kdna_wh_config['bands'] );

			foreach ( $kdna_wh_config['bands'] as $kdna_wh_key => $kdna_wh_band ) :
				$kdna_wh_id      = 'kdna-wh-band-' . sanitize_key( $kdna_wh_key );
				$kdna_wh_segment = isset( $kdna_wh_ranges[ $kdna_wh_key ] ) ? $kdna_wh_ranges[ $kdna_wh_key ] : null;
				?>
				<tr>
					<td>
						<?php if ( $kdna_wh_key === $kdna_wh_first ) : ?>
							<input type="hidden" name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][enabled]" value="1">
							<span class="description" title="<?php esc_attr_e( 'The lowest band is always used, so every reading falls somewhere.', 'kdna-water-hardness' ); ?>">
								<?php esc_html_e( 'Always', 'kdna-water-hardness' ); ?>
							</span>
						<?php else : ?>
							<label class="screen-reader-text" for="<?php echo esc_attr( $kdna_wh_id ); ?>-enabled">
								<?php
								printf(
									/* translators: %s: band label. */
									esc_html__( 'Use the %s band', 'kdna-water-hardness' ),
									esc_html( $kdna_wh_band['label'] )
								);
								?>
							</label>
							<input type="checkbox" id="<?php echo esc_attr( $kdna_wh_id ); ?>-enabled"
								name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][enabled]" value="1"
								<?php checked( ! empty( $kdna_wh_band['enabled'] ) ); ?>>
						<?php endif; ?>
					</td>
					<td>
						<label class="screen-reader-text" for="<?php echo esc_attr( $kdna_wh_id ); ?>-label"><?php esc_html_e( 'Band label', 'kdna-water-hardness' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $kdna_wh_id ); ?>-label"
							name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][label]"
							value="<?php echo esc_attr( $kdna_wh_band['label'] ); ?>" class="regular-text">
					</td>
					<td>
						<label class="screen-reader-text" for="<?php echo esc_attr( $kdna_wh_id ); ?>-min"><?php esc_html_e( 'Starts at', 'kdna-water-hardness' ); ?></label>
						<?php if ( $kdna_wh_key === $kdna_wh_first ) : ?>
							<input type="hidden" name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][min]" value="0">
							<span class="description"><?php esc_html_e( '0', 'kdna-water-hardness' ); ?></span>
						<?php else : ?>
							<input type="number" id="<?php echo esc_attr( $kdna_wh_id ); ?>-min"
								name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][min]"
								value="<?php echo esc_attr( $kdna_wh_band['min'] ); ?>"
								class="small-text" min="0" step="1">
						<?php endif; ?>
					</td>
					<td class="kdna-wh-muted">
						<?php
						if ( ! $kdna_wh_segment ) {
							esc_html_e( 'Not in use', 'kdna-water-hardness' );
						} elseif ( $kdna_wh_segment['open_ended'] ) {
							printf(
								/* translators: %s: a hardness figure. */
								esc_html__( '%s and above', 'kdna-water-hardness' ),
								esc_html( number_format_i18n( $kdna_wh_segment['min'] ) )
							);
						} else {
							printf(
								/* translators: 1: lower figure, 2: upper figure. */
								esc_html__( '%1$s up to %2$s', 'kdna-water-hardness' ),
								esc_html( number_format_i18n( $kdna_wh_segment['min'] ) ),
								esc_html( number_format_i18n( $kdna_wh_segment['max'] ) )
							);
						}
						?>
					</td>
					<td>
						<label class="screen-reader-text" for="<?php echo esc_attr( $kdna_wh_id ); ?>-colour"><?php esc_html_e( 'Band colour', 'kdna-water-hardness' ); ?></label>
						<input type="color" id="<?php echo esc_attr( $kdna_wh_id ); ?>-colour"
							name="bands[<?php echo esc_attr( $kdna_wh_key ); ?>][colour]"
							value="<?php echo esc_attr( $kdna_wh_band['colour'] ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( 'A band is inclusive at the bottom, so with the default thresholds a reading of exactly 60 is moderately hard rather than soft.', 'kdna-water-hardness' ); ?>
	</p>

	<h4><?php esc_html_e( 'How the scale will look', 'kdna-water-hardness' ); ?></h4>

	<div class="kdna-wh-scale-preview">
		<?php foreach ( $kdna_wh_scale as $kdna_wh_segment ) : ?>
			<div class="kdna-wh-scale-preview__band" style="width:<?php echo esc_attr( $kdna_wh_segment['width'] ); ?>%;background:<?php echo esc_attr( $kdna_wh_segment['colour'] ); ?>">
				<span><?php echo esc_html( $kdna_wh_segment['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<h3><?php esc_html_e( 'Copy', 'kdna-water-hardness' ); ?></h3>

	<?php
	/*
	 * The brief asks for this note to sit above the copy fields, so whoever
	 * edits the wording in a year sees the constraint at the moment they need
	 * it rather than having to remember it.
	 */
	?>
	<div class="notice notice-info inline kdna-wh-tga-note">
		<p>
			<strong><?php esc_html_e( 'Keep claims to appearance only.', 'kdna-water-hardness' ); ?></strong>
			<?php esc_html_e( 'The same rules apply here as on pack and elsewhere on the site. You can describe what the product does to the water, and how skin looks and feels: helps prevent the dull, tight feel hard water can leave. You cannot say it repairs the barrier, treats dryness, protects skin from mineral damage, or anything else implying a therapeutic effect.', 'kdna-water-hardness' ); ?>
		</p>
	</div>

	<?php
	foreach ( $kdna_wh_config['bands'] as $kdna_wh_key => $kdna_wh_band ) {
		if ( empty( $kdna_wh_band['enabled'] ) ) {
			continue;
		}

		$kdna_wh_help = '';

		if ( array_key_first( $kdna_wh_config['bands'] ) === $kdna_wh_key ) {
			$kdna_wh_help = __( 'Most of Australia is on soft water, so this block will be seen more than any other. It should not read as "this product is not for you".', 'kdna-water-hardness' );
		}

		kdna_wh_render_copy_fields(
			$kdna_wh_key,
			sprintf(
				/* translators: %s: band label. */
				__( 'Result: %s', 'kdna-water-hardness' ),
				$kdna_wh_band['label']
			),
			$kdna_wh_config['copy'][ $kdna_wh_key ],
			$kdna_wh_help
		);
	}

	kdna_wh_render_copy_fields(
		'inconclusive',
		__( 'Result: inconclusive', 'kdna-water-hardness' ),
		$kdna_wh_config['copy']['inconclusive'],
		__( 'Shown when the postcode spans bands, or the figure is estimated or dated. The specific reason is added automatically above this copy, so write this part as the argument that holds whatever the reading turns out to be.', 'kdna-water-hardness' )
	);

	kdna_wh_render_copy_fields(
		'no_match',
		__( 'Result: no data for that postcode', 'kdna-water-hardness' ),
		$kdna_wh_config['copy']['no_match'],
		__( 'Shown when the postcode is valid but we hold nothing for it.', 'kdna-water-hardness' )
	);
	?>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save bands and copy', 'kdna-water-hardness' ); ?></button>
	</p>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'kdna_wh_reset_bands' ); ?>
	<input type="hidden" name="action" value="kdna_wh_reset_bands">
	<input type="hidden" name="country" value="<?php echo esc_attr( $kdna_wh_country ); ?>">
	<button type="submit" class="button button-link-delete kdna-wh-confirm"
		data-confirm="<?php esc_attr_e( 'Put this country back to the default bands and copy? Anything you have written here will be lost.', 'kdna-water-hardness' ); ?>">
		<?php esc_html_e( 'Reset this country to the defaults', 'kdna-water-hardness' ); ?>
	</button>
</form>
