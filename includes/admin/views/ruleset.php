<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2 class="woo-conditional-shipping-heading">
	<a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' ); ?>"><?php esc_html_e( 'Conditions', 'hp-conditional-shipping' ); ?></a>
	 &gt; 
	<?php echo esc_html( $ruleset->get_title() ); ?>
</h2>

<table class="form-table woo-conditional-shipping-ruleset-settings">
	<tbody>
		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Enable / Disable', 'hp-conditional-shipping' ); ?>
				</label>
			</th>
			<td class="forminp">
				<input type="checkbox" name="ruleset_enabled" id="ruleset_enabled" value="1" <?php checked( $ruleset->get_enabled() ); ?> />
				<label for="ruleset_enabled"><?php esc_html_e( 'Enable ruleset', 'hp-conditional-shipping' ); ?></label>
			</td>
		</tr>
		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Title', 'hp-conditional-shipping' ); ?>
					<?php echo wc_help_tip( __( 'This is the name of the ruleset for your reference.', 'hp-conditional-shipping' ) ); ?>
				</label>
			</th>
			<td class="forminp">
				<input type="text" name="ruleset_name" id="ruleset_name" value="<?php echo esc_attr( $ruleset->get_title( 'edit' ) ); ?>" placeholder="<?php esc_attr_e( 'Ruleset name', 'hp-conditional-shipping' ); ?>" />
			</td>
		</tr>
		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Conditions', 'hp-conditional-shipping' ); ?>
					<?php echo wc_help_tip( __( 'The following conditions define whether or not actions are run.', 'hp-conditional-shipping' ) ); ?>
				</label>
			</th>
			<td class="">
				<table
					class="woo-conditional-shipping-conditions wcs-table widefat"
					data-operators="<?php echo htmlspecialchars( json_encode( hp_cs_operators() ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-selected-products="<?php echo htmlspecialchars( json_encode( $ruleset->get_products() ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-selected-coupons="<?php echo htmlspecialchars( json_encode( $ruleset->get_coupons() ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-selected-tags="<?php echo htmlspecialchars( json_encode( $ruleset->get_tags() ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-conditions="<?php echo htmlspecialchars( json_encode( $ruleset->get_conditions() ), ENT_QUOTES, 'UTF-8' ); ?>"
				>
					<tbody class="woo-conditional-shipping-condition-rows">
					</tbody>
					<tfoot>
						<tr>
							<td colspan="4">
								<button type="button" class="button" id="wcs-add-condition"><?php _e( 'Add Condition', 'hp-conditional-shipping' ); ?></button>
								<select name="wcs_operator">
									<option value="and" <?php selected( 'and', $ruleset->get_conditions_operator() ); ?>><?php _e( 'All conditions have to pass (AND)', 'hp-conditional-shipping' ); ?></option>
									<option value="or" <?php selected( 'or', $ruleset->get_conditions_operator() ); ?>><?php _e( 'One condition has to pass (OR)', 'hp-conditional-shipping' ); ?></option>
								</select>
							</td>
						</tr>
					</tfoot>
				</table>
			</td>
		</tr>
		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Actions', 'hp-conditional-shipping' ); ?>
					<?php echo wc_help_tip( __( 'Actions which are run if all conditions pass.', 'hp-conditional-shipping' ) ); ?>
				</label>
			</th>
			<td class="">
				<table
					class="woo-conditional-shipping-actions wcs-table widefat"
					data-actions="<?php echo htmlspecialchars( json_encode( $ruleset->get_actions() ), ENT_QUOTES, 'UTF-8' ); ?>"
				>
					<tbody class="woo-conditional-shipping-action-rows">
					</tbody>
					<tfoot>
						<tr>
							<td colspan="4">
								<button type="button" class="button" id="wcs-add-action"><?php esc_html_e( 'Add Action', 'hp-conditional-shipping' ); ?></button>
							</td>
						</tr>
					</tfoot>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<p class="submit">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save changes', 'hp-conditional-shipping' ); ?>"><?php esc_html_e( 'Save changes', 'hp-conditional-shipping' ); ?></button>

	<input type="hidden" value="<?php echo esc_attr( $ruleset->get_id() ); ?>" name="ruleset_id" />
	<input type="hidden" value="1" name="save" />

	<?php wp_nonce_field( 'woocommerce-settings' ); ?>
</p>

<script type="text/html" id="tmpl-wcs_row_template">
	<tr valign="top" class="condition_row">
		<td class="wcs-condition">
			<div class="wcs-condition-inputs">
				<div>
					<select name="wcs_conditions[{{data.index}}][type]" class="wcs_condition_type_select">
						<option value=""><?php echo hp_cs_esc_html( __( '- Select condition - ', 'hp-conditional-shipping' ) ); ?></option>
						<?php foreach ( hp_cs_filter_groups() as $filter_group ) { ?>
							<optgroup label="<?php echo esc_attr( $filter_group['title'] ); ?>">
								<?php foreach ( $filter_group['filters'] as $key => $filter ) { ?>
									<option
										value="<?php echo esc_attr( $key ); ?>"
										<?php echo ( isset( $filter['pro'] ) && $filter['pro'] ) ? 'disabled' : ''; ?>
										data-operators="<?php echo htmlspecialchars( json_encode( $filter['operators'] ), ENT_QUOTES, 'UTF-8'); ?>"
										<# if ( data.type == '<?php echo esc_attr( $key ); ?>' ) { #>selected<# } #>
									>
										<?php echo hp_cs_esc_html( hp_cs_get_control_title( $filter ) ); ?>
									</option>
								<?php } ?>
							</optgroup>
						<?php } ?>
					</select>
				</div>

				<div class="value_input wcs_product_meta_key_input">
					<select class="wcs-product-meta-field-search" name="wcs_conditions[{{data.index}}][meta_key]" data-placeholder="<?php esc_attr_e( 'Meta key', 'hp-conditional-shipping' ); ?>">
						<# if ( data.meta_key ) { #>
							<option selected value="{{data.meta_key}}">{{data.meta_key}}</option>
						<# } #>
					</select>
				</div>
			</div>
		</td>
		<td class="wcs-operator">
			<div class="wcs-operator-inputs">
				<div class="value_input wcs_product_measurement_mode_input">
					<select name="wcs_conditions[{{data.index}}][product_measurement_mode]" class="">
						<option value="highest" <# if ( data.product_measurement_mode && data.product_measurement_mode == 'highest' ) { #>selected<# } #>><?php esc_html_e( 'highest', 'hp-conditional-shipping' ); ?></option>
						<option value="lowest" <# if ( data.product_measurement_mode && data.product_measurement_mode == 'lowest' ) { #>selected<# } #>><?php esc_html_e( 'lowest', 'hp-conditional-shipping' ); ?></option>
						<option value="sum" <# if ( data.product_measurement_mode && data.product_measurement_mode == 'sum' ) { #>selected<# } #>><?php esc_html_e( 'total sum', 'hp-conditional-shipping' ); ?></option>
					</select>
				</div>

				<?php $subset_filters = hp_cs_subset_filters(); ?>

				<?php if ( ! empty( $subset_filters ) ) { ?>
					<div class="value_input wcs_subset_filter_input">
						<select name="wcs_conditions[{{data.index}}][subset_filter]" class="wcs_subset_filter_input_select">
							<?php foreach ( hp_cs_subset_filters() as $key => $filter ) { ?>
								<?php if ( is_array( $filter ) ) { ?>
									<optgroup label="<?php esc_attr_e( $filter['title'] ); ?>">
										<?php foreach ( $filter['options'] as $filter_key => $filter_label ) { ?>
											<option
												value="<?php echo esc_attr( $filter_key ); ?>"
												class="wcs-subset-filter wcs-subset-filter-<?php echo $filter_key; ?>"
												<# if ( data.subset_filter == '<?php echo esc_attr( $filter_key ); ?>' ) { #>selected<# } #>
											>
												<?php echo $filter_label; ?>
											</option>
										<?php } ?>
									</optgroup>
								<?php } else { ?>
									<option
										value="<?php echo esc_attr( $key ); ?>"
										class="wcs-subset-filter wcs-subset-filter-<?php echo esc_attr( $key ); ?>"
										<# if ( data.subset_filter == '<?php echo esc_attr( $key ); ?>' ) { #>selected<# } #>
									>
										<?php echo $filter; ?>
									</option>
								<?php } ?>
							<?php } ?>
						</select>
					</div>
				<?php } ?>

				<div>
					<select class="wcs_operator_select" name="wcs_conditions[{{data.index}}][operator]">
						<?php foreach ( hp_cs_operators() as $key => $operator ) { ?>
							<option
								value="<?php echo esc_attr( $key ); ?>"
								class="wcs-operator wcs-operator-<?php echo esc_attr( $key ); ?>"
								<# if ( data.operator == '<?php echo esc_attr( $key ); ?>' ) { #>selected<# } #>
							>
								<?php echo esc_html( $operator ); ?>
							</option>
						<?php } ?>
					</select>
				</div>
			</div>
		</td>
		<td class="wcs-values">
			<input class="input-text value_input regular-input wcs_text_value_input" type="text" name="wcs_conditions[{{data.index}}][value]" value="{{data.value}}" />

			<div class="value_input wcs_subtotal_value_input wcs-value-checkbox">
				<input type="checkbox" id="wcs-subtotal-includes-coupons-{{data.index}}" value="1" name="wcs_conditions[{{data.index}}][subtotal_includes_coupons]" <# if ( data.subtotal_includes_coupons ) { #>checked<# } #> />
				<label for="wcs-subtotal-includes-coupons-{{data.index}}"><?php esc_html_e( 'Subtotal includes coupons', 'hp-conditional-shipping' ); ?></label>
			</div>

			<div class="value_input wcs_items_value_input wcs-value-checkbox">
				<input type="checkbox" id="wcs-items-unique-only-{{data.index}}" value="1" name="wcs_conditions[{{data.index}}][items_unique_only]" <# if ( data.items_unique_only ) { #>checked<# } #> />
				<label for="wcs-items-unique-only-{{data.index}}"><?php esc_html_e( 'Count unique items only', 'hp-conditional-shipping' ); ?></label>
			</div>

			<div class="value_input wcs_orders_value_input">
				<div class="wcs_orders_status_input">
					<select name="wcs_conditions[{{data.index}}][orders_status][]" class="wc-enhanced-select" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Order statuses', 'hp-conditional-shipping' ); ?>">
						<?php foreach( hp_cs_order_status_options() as $value => $label ) { ?>
							<option
								value="<?php echo esc_attr( $value ); ?>"
								<# if ( data.orders_status && jQuery.inArray( '<?php echo esc_js( $value ); ?>', data.orders_status ) !== -1 ) { #>
									selected
								<# } #>
							>
								<?php echo hp_cs_esc_html( $label ); ?>
							</option>
						<?php } ?>
					</select>
				</div>

				<div>
					<input type="checkbox" id="wcs-orders-match-guests-by-email-{{data.index}}" value="1" name="wcs_conditions[{{data.index}}][orders_match_guests_by_email]" <# if ( data.orders_match_guests_by_email ) { #>checked<# } #> />
					<label for="wcs-orders-match-guests-by-email-{{data.index}}"><?php esc_html_e( 'Match guests by email', 'hp-conditional-shipping' ); ?></label>
				</div>
			</div>

			<div class="value_input wcs_stock_status_value_input">
				<select name="wcs_conditions[{{data.index}}][stock_status][]" multiple class="select wc-enhanced-select">
					<?php foreach ( hp_cs_get_stock_status_options() as $key => $label) { ?>
						<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.stock_status && data.stock_status.indexOf("<?php echo esc_js( $key ); ?>") !== -1 ) { #>selected<# } #>><?php echo hp_cs_esc_html( $label ); ?></option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_shipping_class_value_input">
				<select name="wcs_conditions[{{data.index}}][shipping_class_ids][]" multiple class="select wc-enhanced-select">
					<?php foreach ( hp_cs_get_shipping_class_options() as $key => $label ) { ?>
						<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.shipping_class_ids && data.shipping_class_ids.indexOf("<?php echo esc_attr( $key ); ?>") !== -1 ) { #>selected<# } #>><?php echo hp_cs_esc_html( $label ); ?></option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_category_value_input">
				<select name="wcs_conditions[{{data.index}}][category_ids][]" multiple class="select wc-enhanced-select">
					<?php foreach ( hp_cs_category_options() as $key => $label) { ?>
						<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.category_ids && data.category_ids.indexOf("<?php echo esc_attr( $key ); ?>") !== -1 ) { #>selected<# } #>><?php echo hp_cs_esc_html( $label ); ?></option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_product_value_input">
				<select class="wc-product-search" multiple="multiple" name="wcs_conditions[{{data.index}}][product_ids][]" data-placeholder="<?php esc_attr_e( 'Search for products', 'hp-conditional-shipping' ); ?>" data-action="woocommerce_json_search_products_and_variations">
					<# if ( data.selected_products && data.selected_products.length > 0 ) { #>
						<# _.each(data.selected_products, function(product) { #>
							<option value="{{ product['id'] }}" selected>{{ product['title'] }}</option>
						<# }) #>
					<# } #>
				</select>
			</div>

			<div class="value_input wcs_product_tag_value_input">
				<select class="wcs-tag-search" multiple="multiple" name="wcs_conditions[{{data.index}}][product_tags][]" data-placeholder="<?php esc_attr_e( 'Search for tags', 'hp-conditional-shipping' ); ?>">
					<# if ( data.selected_tags && data.selected_tags.length > 0 ) { #>
						<# _.each(data.selected_tags, function(tag) { #>
							<option value="{{ tag['id'] }}" selected>{{ tag['title'] }}</option>
						<# }) #>
					<# } #>
				</select>
			</div>

			<div class="value_input wcs_coupon_value_input">
				<select class="wcs-coupon-search" multiple="multiple" name="wcs_conditions[{{data.index}}][coupon_ids][]" data-placeholder="<?php esc_attr_e( 'Search for coupons', 'hp-conditional-shipping' ); ?>">
					<# if ( data.selected_coupons && data.selected_coupons.length > 0 ) { #>
						<# _.each(data.selected_coupons, function(coupon) { #>
							<option value="{{ coupon['id'] }}" selected>{{ coupon['title'] }}</option>
						<# }) #>
					<# } #>
				</select>
			</div>

			<div class="value_input wcs_user_role_value_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][user_roles][]" class="select" multiple>
					<?php foreach ( hp_cs_role_options() as $role_id => $name ) { ?>
						<option
							value="<?php echo esc_attr( $role_id ); ?>"
							<# if ( data.user_roles && jQuery.inArray( '<?php echo esc_js( $role_id ); ?>', data.user_roles ) !== -1 ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $name ); ?>
						</option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_postcode_value_input">
				<textarea name="wcs_conditions[{{data.index}}][postcodes]" class="" placeholder="<?php esc_attr_e( 'List 1 postcode per line', 'woocommerce' ); ?>">{{ data.postcodes }}</textarea>

				<div class="wcs-desc"><?php esc_html_e( 'Postcodes containing wildcards (e.g. CB23*) or fully numeric ranges (e.g. <code>90210...99000</code>) are also supported.', 'hp-conditional-shipping' ); ?></div>
			</div>

			<div class="value_input wcs_textarea_value_input">
				<textarea name="wcs_conditions[{{data.index}}][textarea]" class="" placeholder="<?php esc_attr_e( 'List 1 value per line', 'woocommerce' ); ?>">{{ data.textarea }}</textarea>
			</div>

			<div class="value_input wcs_email_value_input">
				<textarea name="wcs_conditions[{{data.index}}][emails]" class="" placeholder="<?php esc_attr_e( 'List 1 email address per line', 'hp-conditional-shipping' ); ?>">{{ data.emails }}</textarea>
			</div>

			<div class="value_input wcs_phone_value_input">
				<textarea name="wcs_conditions[{{data.index}}][phones]" class="" placeholder="<?php esc_attr_e( 'List 1 phone number per line', 'hp-conditional-shipping' ); ?>">{{ data.phones }}</textarea>
			</div>

			<div class="value_input wcs_city_value_input">
				<textarea name="wcs_conditions[{{data.index}}][cities]" class="" placeholder="<?php esc_attr_e( 'List 1 city per line', 'woocommerce' ); ?>">{{ data.cities }}</textarea>
			</div>

			<div class="value_input wcs_country_value_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][countries][]" class="select" multiple>
					<?php foreach ( hp_cs_country_options() as $code => $country ) { ?>
						<option
							value="<?php echo esc_attr( $code ); ?>"
							<# if ( data.countries && jQuery.inArray( '<?php echo esc_js( $code ); ?>', data.countries ) !== -1 ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $country ); ?>
						</option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_currency_value_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][currencies][]" class="select" multiple>
					<?php foreach ( wcs_currency_options() as $code => $currency ) { ?>
						<option
							value="<?php echo esc_attr( $code ); ?>"
							<# if ( data.currencies && jQuery.inArray( '<?php echo esc_js( $code ); ?>', data.currencies ) !== -1 ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $currency ); ?>
						</option>
					<?php } ?>
				</select>
			</div>
			
			<div class="value_input wcs_state_value_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][states][]" class="select" multiple>
					<?php foreach ( hp_cs_state_options() as $country_id => $states ) { ?>
						<optgroup label="<?php echo esc_attr( $states['country'] ); ?>">
							<?php foreach ( $states['states'] as $state_id => $state ) { ?>
								<option
									value="<?php echo esc_attr( "{$country_id}:{$state_id}" ); ?>"
									<# if ( data.states && jQuery.inArray( '<?php echo esc_js( "{$country_id}:{$state_id}" ); ?>', data.states ) !== -1 ) { #>
										selected
									<# } #>
								>
									<?php echo hp_cs_esc_html( $state ); ?>
								</option>
							<?php } ?>
						</optgroup>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_product_attrs_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][product_attrs][]" class="select" multiple>
					<?php foreach ( hp_cs_product_attr_options() as $taxonomy_id => $attrs ) { ?>
						<optgroup label="<?php echo esc_attr( $attrs['label'] ); ?>">
							<?php foreach ( $attrs['attrs'] as $attr_id => $label ) { ?>
								<option
								value="<?php echo esc_attr( $attr_id ); ?>"
								<# if ( data.product_attrs && jQuery.inArray( '<?php echo esc_js( $attr_id ); ?>', data.product_attrs ) !== -1 ) { #>
									selected
									<# } #>
									>
									<?php echo hp_cs_esc_html( $label ); ?>
								</option>
							<?php } ?>
						</optgroup>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_weekdays_value_input">
				<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][weekdays][]" class="select" multiple>
					<?php foreach ( hp_cs_weekdays_options() as $weekday_id => $weekday ) { ?>
						<option
							value="<?php echo esc_attr( $weekday_id ); ?>"
							<# if ( data.weekdays && jQuery.inArray( '<?php echo esc_js( $weekday_id ); ?>', data.weekdays ) !== -1 ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $weekday ); ?>
						</option>
					<?php } ?>
				</select>
			</div>

			<div class="value_input wcs_date_value_input">
				<input type="text" name="wcs_conditions[{{data.index}}][date]" class="wcs-datepicker" value="{{data.date}}" />
			</div>

			<div class="value_input wcs_time_value_input">
				<select name="wcs_conditions[{{data.index}}][time_hours]" class="select">
					<?php foreach ( hp_cs_time_hours_options() as $hours => $label ) { ?>
						<option
							value="<?php echo esc_attr( $hours ); ?>"
							<# if ( data.time_hours && '<?php echo esc_js( $hours ); ?>' == data.time_hours ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $label ); ?>
						</option>
					<?php } ?>
				</select>
				<span>&nbsp;:&nbsp;</span>
				<select name="wcs_conditions[{{data.index}}][time_mins]" class="select">
					<?php foreach ( hp_cs_time_mins_options() as $mins => $label ) { ?>
						<option
							value="<?php echo esc_attr( $mins ); ?>"
							<# if ( data.time_mins && '<?php echo esc_js( $mins ); ?>' == data.time_mins ) { #>
								selected
							<# } #>
						>
							<?php echo hp_cs_esc_html( $label ); ?>
						</option>
					<?php } ?>
				</select>
			</div>

			<?php if ( class_exists( 'Paid_Member_Subscriptions' ) ) { ?>
				<div class="value_input wcs_user_pms_plans_input">
					<select class="wc-enhanced-select" name="wcs_conditions[{{data.index}}][user_pms_plans][]" class="select" multiple>
						<?php foreach ( hp_cs_pms_plan_options() as $plan_id => $name ) { ?>
							<option
								value="<?php echo esc_attr( $plan_id ); ?>"
								<# if ( data.user_pms_plans && jQuery.inArray( '<?php echo esc_js( $plan_id ); ?>', data.user_pms_plans ) !== -1 ) { #>
									selected
								<# } #>
							>
								<?php echo hp_cs_esc_html( $name ); ?>
							</option>
						<?php } ?>
					</select>
				</div>
			<?php } ?>
		
			<?php do_action( 'woo_conditional_shipping_ruleset_value_inputs', $ruleset ); ?>
		</td>
		<td class="wcs-remove">
			<a href="#" class="wcs-remove-condition wcs-remove-row">
				<span class="dashicons dashicons-trash"></span>
			</a>

			<input type="hidden" name="wcs_conditions[{{data.index}}][guid]" value="{{data.guid}}" />
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-wcs_action_row_template">
	<tr valign="top" class="action_row">
		<td class="wcs-action">
			<select name="wcs_actions[{{data.index}}][type]" class="wcs_action_type_select">
				<option value=""><?php echo hp_cs_esc_html( __( '- Select action - ', 'hp-conditional-shipping' ) ); ?></option>
				<?php foreach ( hp_cs_action_groups() as $group_id => $group ) { ?>
					<optgroup label="<?php echo esc_attr( $group['title'] ); ?>">
						<?php foreach ( $group['actions'] as $key => $action ) { ?>
							<option
								value="<?php echo esc_attr( $key ); ?>"
								<?php echo ( isset( $action['pro'] ) && $action['pro'] ) ? 'disabled' : ''; ?>
								<# if ( data.type == '<?php echo esc_js( $key ); ?>' ) { #>selected<# } #>
							>
								<?php echo esc_html( hp_cs_get_control_title( $action ) ); ?>
							</option>
						<?php } ?>
					</optgroup>
				<?php } ?>
			</select>

			<input type="hidden" name="wcs_actions[{{data.index}}][guid]" value="{{ data.guid }}" />
		</td>
		<td class="wcs-methods">
			<select name="wcs_actions[{{data.index}}][shipping_method_ids][]" multiple class="select wc-enhanced-select" data-placeholder="<?php echo esc_attr( __( '- Select shipping methods -', 'hp-conditional-shipping' ) ); ?>">
				<?php foreach ( hp_cs_shipping_method_options() as $zone_id => $zone ) { ?>
					<optgroup label="<?php esc_attr_e( $zone['title'] ); ?>">
						<?php foreach ( $zone['options'] as $instance_id => $method ) { ?>
							<option value="<?php echo esc_attr( $instance_id ); ?>" <# if ( data.shipping_method_ids && data.shipping_method_ids.indexOf("<?php echo esc_js( $instance_id ); ?>") !== -1 ) { #>selected<# } #>><?php echo hp_cs_esc_html( $method['title'] ); ?></option>
						<?php } ?>
					</optgroup>
				<?php } ?>
			</select>

			<div class="wcs-match-by-name">
				<textarea name="wcs_actions[{{data.index}}][shipping_method_name_match]">{{ data.shipping_method_name_match }}</textarea>
				<div class="wcs-desc"><?php esc_html_e( 'Match shipping methods by name. Wildcards (e.g. DHL Express*) are also supported. Enter one name per line.', 'hp-conditional-shipping' ); ?></div>
			</div>

			<div class="value_input wcs_error_msg_input">
				<textarea name="wcs_actions[{{data.index}}][error_msg]" rows="4" cols="40" placeholder="<?php esc_attr_e( __( 'Custom "no shipping methods available" message', 'hp-conditional-shipping' ) ); ?>">{{ data.error_msg }}</textarea>
			</div>
		</td>
		<td class="wcs-values">
			<div class="value_input wcs_notice_input">
				<textarea name="wcs_actions[{{data.index}}][notice]" rows="4" cols="40" placeholder="<?php esc_attr_e( __( 'Shipping notice', 'hp-conditional-shipping' ) ); ?>">{{ data.notice }}</textarea>

				<div class="wcs-notice-style">
					<label><?php esc_html_e( 'Style:', 'hp-conditional-shipping' ); ?></label>

					<select name="wcs_actions[{{data.index}}][notice_style]">
						<?php foreach ( hp_cs_get_notice_styles() as $key => $label ) { ?>
							<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.notice_style === "<?php echo esc_attr( $key ); ?>" ) { #>selected<# } #>><?php echo esc_html( $label ); ?></option>
						<?php } ?>
					</select>
				</div>

			</div>

			<div class="value_input wcs_price_value_input">
				<div>
					<input name="wcs_actions[{{data.index}}][price]" type="number" step="any" value="{{ data.price }}" />
				</div>

				<div>
					<select name="wcs_actions[{{data.index}}][price_mode]" class="wcs-price-mode">
						<?php foreach( hp_cs_get_price_modes() as $key => $title ) { ?>
							<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.price_mode === "<?php echo esc_attr( $key ); ?>" ) { #>selected<# } #>><?php echo esc_html( $title ); ?></option>
						<?php } ?>
					</select>
				</div>

				<div class="wcs_price_per_input">
					<select name="wcs_actions[{{data.index}}][price_per]">
						<?php foreach( hp_cs_get_price_per_options() as $key => $title ) { ?>
							<option value="<?php echo esc_attr( $key ); ?>" <# if ( data.price_per === "<?php echo esc_attr( $key ); ?>" ) { #>selected<# } #>><?php echo esc_html( $title ); ?></option>
						<?php } ?>
					</select>
				</div>
			</div>

			<div class="value_input wcs_title_value_input">
				<input name="wcs_actions[{{data.index}}][title]" type="text" placeholder="<?php esc_attr_e( '- Shipping method title -', 'hp-conditional-shipping' ); ?>" value="{{data.title}}" />
			</div>
		</td>

		<td class="wcs-remove">
			<a href="#" class="wcs-remove-action wcs-remove-row">
				<span class="dashicons dashicons-trash"></span>
			</a>
		</td>
	</tr>
</script>
