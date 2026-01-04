<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' );
?>

<h2>
	<a href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Conditions', 'hp-conditional-shipping' ); ?></a>
	 &gt;
	<?php echo esc_html( $ruleset->get_title() ); ?>
	<small style="font-weight:normal;opacity:.75;margin-left:8px;">
		<?php echo esc_html( 'v' . HP_CS_VERSION ); ?>
	</small>
</h2>

<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row" class="titledesc"><label><?php esc_html_e( 'Enable / Disable', 'hp-conditional-shipping' ); ?></label></th>
			<td class="forminp">
				<input type="checkbox" name="ruleset_enabled" id="ruleset_enabled" value="1" <?php checked( $ruleset->get_enabled() ); ?> />
				<label for="ruleset_enabled"><?php esc_html_e( 'Enable ruleset', 'hp-conditional-shipping' ); ?></label>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc"><label><?php esc_html_e( 'Title', 'hp-conditional-shipping' ); ?></label></th>
			<td class="forminp">
				<input type="text" name="ruleset_name" value="<?php echo esc_attr( $ruleset->get_title( 'edit' ) ); ?>" placeholder="<?php esc_attr_e( 'Ruleset name', 'hp-conditional-shipping' ); ?>" />
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc"><label><?php esc_html_e( 'Conditions (JSON)', 'hp-conditional-shipping' ); ?></label></th>
			<td class="forminp">
				<p class="description">
					<?php esc_html_e( 'v0.1 ships with a JSON editor to guarantee perfect parity with existing stored rules. A visual builder will be added after parity is proven.', 'hp-conditional-shipping' ); ?>
				</p>
				<textarea name="wcs_conditions_json" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $ruleset->get_conditions(), JSON_PRETTY_PRINT ) ); ?></textarea>
				<input type="hidden" name="wcs_operator" value="<?php echo esc_attr( $ruleset->get_conditions_operator() ); ?>" />
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc"><label><?php esc_html_e( 'Actions (JSON)', 'hp-conditional-shipping' ); ?></label></th>
			<td class="forminp">
				<textarea name="wcs_actions_json" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $ruleset->get_actions(), JSON_PRETTY_PRINT ) ); ?></textarea>
			</td>
		</tr>
	</tbody>
</table>

<p class="submit">
	<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'hp-conditional-shipping' ); ?></button>

	<input type="hidden" name="ruleset_id" value="<?php echo esc_attr( $ruleset->get_id() ); ?>" />
	<input type="hidden" name="save" value="1" />
	<?php wp_nonce_field( 'woocommerce-settings' ); ?>
</p>


