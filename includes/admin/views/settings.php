<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>
	<?php echo esc_html__( 'Conditions', 'hp-conditional-shipping' ); ?>
	<small style="font-weight:normal;opacity:.75;margin-left:8px;">
		<?php echo esc_html( 'v' . HP_CS_VERSION ); ?>
	</small>
</h2>

<?php
$mode           = hp_cs_get_mode();
$discount_rules = hp_cs_get_shipping_discount_rules();
$shipping_classes = [
	''      => __( 'All shipping classes', 'hp-conditional-shipping' ),
	'_none' => __( 'No shipping class', 'hp-conditional-shipping' ),
];
if ( function_exists( 'WC' ) && WC()->shipping ) {
	foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
		$shipping_classes[ $shipping_class->slug ] = $shipping_class->name;
	}
}
$shipping_method_options = hp_cs_shipping_method_options();
$discount_rows = $discount_rules;
$discount_rows[] = hp_cs_normalize_discount_rule( [ 'label' => '', 'order' => count( $discount_rows ) ] );
?>

<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="hp_cs_mode"><?php esc_html_e( 'Runtime mode', 'hp-conditional-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<select id="hp_cs_mode" name="hp_cs_mode">
					<option value="audit" <?php selected( $mode, 'audit' ); ?>><?php esc_html_e( 'Audit only - log and surface notices without changing rates', 'hp-conditional-shipping' ); ?></option>
					<option value="enforce" <?php selected( $mode, 'enforce' ); ?>><?php esc_html_e( 'Enforce - apply restrictions and discounts', 'hp-conditional-shipping' ); ?></option>
					<option value="disabled" <?php selected( $mode, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'hp-conditional-shipping' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Use Audit on staging first. Enforce is the retirement switch for the third-party shipping plugins.', 'hp-conditional-shipping' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Shipping discounts', 'hp-conditional-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<p class="description">
					<?php esc_html_e( 'Discounts are calculated from the current eligible product line amount, so sale prices are already reflected. Imported legacy flags are retained for compatibility.', 'hp-conditional-shipping' ); ?>
				</p>
				<table class="widefat striped" style="margin-top:8px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Enabled', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Label', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Shipping class', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Min eligible amount', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Discount %', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Surface', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Methods', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Name match', 'hp-conditional-shipping' ); ?></th>
							<th><?php esc_html_e( 'Legacy flags', 'hp-conditional-shipping' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $discount_rows as $index => $rule ) : ?>
							<?php $method_ids = array_map( 'strval', (array) ( $rule['shipping_method_ids'] ?? [ '_all' ] ) ); ?>
							<tr>
								<td>
									<label>
										<input type="checkbox" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $rule['enabled'] ?? 'no', 'yes' ); ?>>
										<?php esc_html_e( 'Yes', 'hp-conditional-shipping' ); ?>
									</label>
								</td>
								<td>
									<input type="text" class="regular-text" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $rule['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Regular HP product discount', 'hp-conditional-shipping' ); ?>">
								</td>
								<td>
									<select name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][shipping_class]">
										<?php foreach ( $shipping_classes as $class_slug => $class_label ) : ?>
											<option value="<?php echo esc_attr( $class_slug ); ?>" <?php selected( $rule['shipping_class'] ?? '', $class_slug ); ?>><?php echo esc_html( $class_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="number" min="0" step="0.01" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][min_amount]" value="<?php echo esc_attr( (string) ( $rule['min_amount'] ?? 0 ) ); ?>" style="width:90px;">
								</td>
								<td>
									<input type="number" min="0" step="0.01" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][percentage_discount]" value="<?php echo esc_attr( (string) ( $rule['percentage_discount'] ?? 0 ) ); ?>" style="width:80px;">
								</td>
								<td>
									<select name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][surface]">
										<option value="classic" <?php selected( $rule['surface'] ?? 'classic', 'classic' ); ?>><?php esc_html_e( 'Classic checkout', 'hp-conditional-shipping' ); ?></option>
										<option value="funnel" <?php selected( $rule['surface'] ?? 'classic', 'funnel' ); ?>><?php esc_html_e( 'Funnel checkout', 'hp-conditional-shipping' ); ?></option>
										<option value="both" <?php selected( $rule['surface'] ?? 'classic', 'both' ); ?>><?php esc_html_e( 'Both', 'hp-conditional-shipping' ); ?></option>
									</select>
								</td>
								<td>
									<select name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][shipping_method_ids][]" multiple style="min-width:180px;min-height:80px;">
										<?php foreach ( $shipping_method_options as $zone ) : ?>
											<optgroup label="<?php echo esc_attr( $zone['title'] ); ?>">
												<?php foreach ( $zone['options'] as $instance_id => $method ) : ?>
													<option value="<?php echo esc_attr( $instance_id ); ?>" <?php selected( in_array( (string) $instance_id, $method_ids, true ) ); ?>><?php echo esc_html( $method['title'] ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<textarea name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][shipping_method_name_match]" rows="3" style="width:160px;"><?php echo esc_textarea( $rule['shipping_method_name_match'] ?? '' ); ?></textarea>
								</td>
								<td>
									<label style="display:block;">
										<input type="checkbox" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][deduct_sale_discount]" value="1" <?php checked( $rule['deduct_sale_discount'] ?? 'no', 'yes' ); ?>>
										<?php esc_html_e( 'Deduct sale discount', 'hp-conditional-shipping' ); ?>
									</label>
									<label style="display:block;">
										<input type="checkbox" name="hp_cs_shipping_discount_rules[<?php echo esc_attr( $index ); ?>][deduct_coupon_discount]" value="1" <?php checked( $rule['deduct_coupon_discount'] ?? 'no', 'yes' ); ?>>
										<?php esc_html_e( 'Deduct coupon discount', 'hp-conditional-shipping' ); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Rulesets', 'hp-conditional-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:1%"><?php echo wc_help_tip( __( 'Drag and drop to re-order your rulesets. This is the order in which they will be evaluated.', 'hp-conditional-shipping' ) ); ?></th>
							<th><?php esc_html_e( 'Ruleset', 'hp-conditional-shipping' ); ?></th>
							<th style="width:10%"><?php esc_html_e( 'Enabled', 'hp-conditional-shipping' ); ?></th>
						</tr>
					</thead>
					<tbody class="hp-cs-ruleset-rows">
						<?php foreach ( $rulesets as $ruleset ) : ?>
							<tr>
								<td class="hp-cs-sort">
									<input type="hidden" name="wcs_ruleset_order[]" value="<?php echo esc_attr( $ruleset->get_id() ); ?>">
									<span class="dashicons dashicons-menu"></span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping&ruleset_id=' . $ruleset->get_id() ) ); ?>">
										<?php echo esc_html( $ruleset->get_title() ); ?>
									</a>
									<div style="margin-top:4px;opacity:.8">
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping&ruleset_id=' . $ruleset->get_id() . '&action=duplicate' ), 'hp-cs-duplicate-ruleset' ) ); ?>">
											<?php esc_html_e( 'Duplicate', 'hp-conditional-shipping' ); ?>
										</a>
										<span> | </span>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping&ruleset_id=' . $ruleset->get_id() . '&action=delete' ), 'hp-cs-delete-ruleset' ) ); ?>" onclick="return confirm('Delete this ruleset?');">
											<?php esc_html_e( 'Delete', 'hp-conditional-shipping' ); ?>
										</a>
									</div>
								</td>
								<td>
									<?php $class = $ruleset->get_enabled() ? 'enabled' : 'disabled'; ?>
									<span class="woocommerce-input-toggle woocommerce-input-toggle--<?php echo esc_attr( $class ); ?>" data-hp-cs-toggle="1" data-id="<?php echo esc_attr( $ruleset->get_id() ); ?>"></span>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $rulesets ) ) : ?>
							<tr>
								<td></td>
								<td colspan="2">
									<?php esc_html_e( 'No rulesets defined yet.', 'hp-conditional-shipping' ); ?>
									<a href="<?php echo esc_url( $add_ruleset_url ); ?>"><?php esc_html_e( 'Add new', 'hp-conditional-shipping' ); ?> &raquo;</a>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="3">
								<a href="<?php echo esc_url( $add_ruleset_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Add ruleset', 'hp-conditional-shipping' ); ?></a>
							</td>
						</tr>
					</tfoot>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<p class="submit">
	<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'hp-conditional-shipping' ); ?></button>
	<input type="hidden" name="save" value="1" />
	<input type="hidden" name="wcs_settings" value="1" />
	<?php wp_nonce_field( 'woocommerce-settings' ); ?>
</p>

