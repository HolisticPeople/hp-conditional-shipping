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

<table class="form-table">
	<tbody>
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


