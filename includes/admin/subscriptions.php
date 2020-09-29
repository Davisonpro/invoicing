<?php
/**
 * Contains functions that display the subscriptions admin page.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the Subscriptions page
 *
 * @access      public
 * @since       1.0.0
 * @return      void
 */
function wpinv_subscriptions_page() {

	?>

	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div class="bsui">

			<?php

				// Verify user permissions.
				if ( ! wpinv_current_user_can_manage_invoicing() ) {

					echo aui()->alert(
						array(
							'type'    => 'danger',
							'content' => __( 'You are not permitted to view this page.', 'invoicing' ),
						)
					);

				} else if ( ! empty( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {

					// Display a single subscription.
					wpinv_recurring_subscription_details();
				} else {

					// Display a list of available subscriptions.
					getpaid_print_subscriptions_list();
				}

			?>

		</div>
	</div>

	<?php
}

/**
 * Render the Subscriptions table
 *
 * @access      public
 * @since       1.0.19
 * @return      void
 */
function getpaid_print_subscriptions_list() {

	$subscribers_table = new WPInv_Subscriptions_List_Table();
	$subscribers_table->prepare_items();

	?>
	<form id="subscribers-filter" class="bsui" method="get">
		<input type="hidden" name="page" value="wpinv-subscriptions" />
		<?php $subscribers_table->views(); ?>
		<?php $subscribers_table->display(); ?>
	</form>
	<?php
}

/**
 * Render a single subscription.
 *
 * @access      public
 * @since       1.0.0
 * @return      void
 */
function wpinv_recurring_subscription_details() {

	// Fetch the subscription.
	$sub = new WPInv_Subscription( (int) $_GET['id'] );
	if ( ! $sub->get_id() ) {

		echo aui()->alert(
			array(
				'type'    => 'danger',
				'content' => __( 'Subscription not found.', 'invoicing' ),
			)
		);

		return;
	}

	// Use metaboxes to display the subscription details.
	add_meta_box( 'getpaid_admin_subscription_details_metabox', __( 'Subscription Details', 'invoicing' ), 'getpaid_admin_subscription_details_metabox', get_current_screen(), 'normal' );
	add_meta_box( 'getpaid_admin_subscription_update_metabox', __( 'Change Status', 'invoicing' ), 'getpaid_admin_subscription_update_metabox', get_current_screen(), 'side' );
	add_meta_box( 'getpaid_admin_subscription_invoice_details_metabox', __( 'Invoices', 'invoicing' ), 'getpaid_admin_subscription_invoice_details_metabox', get_current_screen(), 'advanced' );
	do_action( 'getpaid_admin_single_subscription_register_metabox', $sub );

	?>

		<form method="post" action="<?php echo admin_url( 'admin.php?page=wpinv-subscriptions&id=' . absint( $sub->get_id() ) ); ?>">

			<?php wp_nonce_field( 'getpaid-nonce', 'getpaid-nonce' ); ?>
			<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
			<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<input type="hidden" name="getpaid-admin-action" value="update_single_subscription" />
			<input type="hidden" name="subscription_id" value="<?php echo (int) $sub->get_id() ;?>" />

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">

					<div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( get_current_screen(), 'side', $sub ); ?>
					</div>

					<div id="postbox-container-2" class="postbox-container">
						<?php do_meta_boxes( get_current_screen(), 'normal', $sub ); ?>
						<?php do_meta_boxes( get_current_screen(), 'advanced', $sub ); ?>
					</div>

				</div>
			</div>

		</form>

		<script>jQuery(document).ready(function(){ postboxes.add_postbox_toggles('getpaid_page_wpinv-subscriptions'); });</script>

	<?php

}

/**
 * Displays the subscription details metabox.
 *
 * @param WPInv_Subscription $sub
 */
function getpaid_admin_subscription_details_metabox( $sub ) {

	// Prepare subscription detail columns.
	$fields = apply_filters(
		'getpaid_subscription_admin_page_fields',
		array(
			'subscription'   => __( 'Subscription', 'invoicing' ),
			'customer'       => __( 'Customer', 'invoicing' ),
			'amount'         => __( 'Amount', 'invoicing' ),
			'start_date'     => __( 'Start Date', 'invoicing' ),
			'renews_on'      => __( 'Next Payment', 'invoicing' ),
			'renewals'       => __( 'Renewals', 'invoicing' ),
			'item'           => __( 'Item', 'invoicing' ),
			'gateway'        => __( 'Payment Method', 'invoicing' ),
			'profile_id'     => __( 'Profile ID', 'invoicing' ),
			'status'         => __( 'Status', 'invoicing' ),
		)
	);

	if ( ! $sub->is_active() && isset( $fields['renews_on'] ) ) {
		unset( $fields['renews_on'] );
	}

	?>

		<table class="table table-borderless" style="font-size: 14px;">
			<tbody>

				<?php foreach ( $fields as $key => $label ) : ?>

					<tr class="getpaid-subscription-meta-<?php echo sanitize_html_class( $key ); ?>">

						<th class="w-25" style="font-weight: 500;">
							<?php echo sanitize_text_field( $label ); ?>
						</th>

						<td class="w-75 text-muted">
							<?php do_action( 'getpaid_subscription_admin_display_' . sanitize_text_field( $key ), $sub ); ?>
						</td>

					</tr>

				<?php endforeach; ?>

			</tbody>
		</table>

	<?php
}

/**
 * Displays the subscription customer.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_customer( $subscription ) {

	$username = __( '(Missing User)', 'invoicing' );

	$user = get_userdata( $subscription->get_customer_id() );
	if ( $user ) {

		$username = sprintf(
			'<a href="user-edit.php?user_id=%s">%s</a>',
			absint( $user->ID ),
			! empty( $user->display_name ) ? sanitize_text_field( $user->display_name ) : sanitize_email( $user->user_email )
		);

	}

	echo  $username;
}
add_action( 'getpaid_subscription_admin_display_customer', 'getpaid_admin_subscription_metabox_display_customer' );

/**
 * Displays the subscription amount.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_amount( $subscription ) {

	$initial   = wpinv_price( wpinv_format_amount( wpinv_sanitize_amount( $subscription->get_initial_amount() ) ), $subscription->get_parent_payment()->get_currency() );
	$recurring = wpinv_price( wpinv_format_amount( wpinv_sanitize_amount( $subscription->get_recurring_amount() ) ), $subscription->get_parent_payment()->get_currency() );
	$period    = 1 == $subscription->get_frequency() ? getpaid_get_subscription_period_label( $subscription->get_period() ) : WPInv_Subscriptions::wpinv_get_pretty_subscription_frequency( $subscription->get_period(),$subscription->get_frequency() );

	if ( $subscription->has_trial_period() ) {

		// translators: $1: is the initial amount, $2: is the trial period, $3: is the recurring amount, $4: is the recurring period
		$amount = sprintf(
			_x( '%1$s trial for %2$s(s) then %3$s / %4$s', 'Subscription amount on admin table. (e.g.: $10 trial for 1 month then $120 / year)', 'invoicing' ),
			$initial,
			sanitize_text_field( $subscription->get_trial_period() ),
			$recurring,
			sanitize_text_field( strtolower( $period ) )
		);

	} else if ( $initial != $recurring ) {

		// translators: $1: is the initial amount, $2: is the recurring amount, $3: is the recurring perio
		$amount = sprintf(
			_x( 'Initial payment of %1$s then %2$s / %3$s', 'Subscription amount on admin table. (e.g.:Initial payment of $100 then $120 / year)', 'invoicing' ),
			$initial,
			$recurring,
			sanitize_text_field( strtolower( $period ) )
		);

	} else {

		// translators: $1: is the recurring amount, $2: is the recurring period
		$amount = sprintf(
			_x( '%1$s / %2$s', 'Subscription amount on admin table. (e.g.: $120 / year)', 'invoicing' ),
			$initial,
			sanitize_text_field( strtolower( $period ) )
		);

	}

	echo "<span>$amount</span>";
}
add_action( 'getpaid_subscription_admin_display_amount', 'getpaid_admin_subscription_metabox_display_amount' );

/**
 * Displays the subscription id.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_id( $subscription ) {
	echo  '#' . absint( $subscription->get_id() );
}
add_action( 'getpaid_subscription_admin_display_subscription', 'getpaid_admin_subscription_metabox_display_id' );

/**
 * Displays the subscription renewal date.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_start_date( $subscription ) {

	$created = $subscription->get_date_created();
	if ( empty( $created ) || '0000-00-00 00:00:00' == $created ) {
		echo "&mdash;";
	} else {
		echo date_i18n( /** @scrutinizer ignore-type */get_option( 'date_format' ), strtotime( $created ) );
	}

}
add_action( 'getpaid_subscription_admin_display_start_date', 'getpaid_admin_subscription_metabox_display_start_date' );

/**
 * Displays the subscription renewal date.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_renews_on( $subscription ) {

	$expiration = $subscription->get_expiration();
	if ( empty( $expiration ) || '0000-00-00 00:00:00' == $expiration ) {
		echo "&mdash;";
	} else {
		echo date_i18n( /** @scrutinizer ignore-type */get_option( 'date_format' ), strtotime( $expiration ) );
	}

}
add_action( 'getpaid_subscription_admin_display_renews_on', 'getpaid_admin_subscription_metabox_display_renews_on' );

/**
 * Displays the subscription renewal count.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_renewals( $subscription ) {
	$max_bills = $subscription->get_bill_times();
	echo $subscription->get_times_billed() . ' / ' . ( empty( $max_bills ) ? "&infin;" : $max_bills );
}
add_action( 'getpaid_subscription_admin_display_renewals', 'getpaid_admin_subscription_metabox_display_renewals' );

/**
 * Displays the subscription item.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_item( $subscription ) {

	$item = get_post( $subscription->get_product_id() );

	if ( ! empty( $item ) ) {
		$link = get_edit_post_link( $item );
		$link = esc_url( $link );
		$name = esc_html( get_the_title( $item ) );
		echo "<a href='$link'>$name</a>";
	} else {
		echo sprintf( __( 'Item #%s', 'invoicing' ), $subscription->get_product_id() );
	}

}
add_action( 'getpaid_subscription_admin_display_item', 'getpaid_admin_subscription_metabox_display_item' );

/**
 * Displays the subscription gateway.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_gateway( $subscription ) {

	$gateway = $subscription->get_gateway();

	if ( ! empty( $gateway ) ) {
		echo sanitize_text_field( wpinv_get_gateway_admin_label( $gateway ) );
	} else {
		echo "&mdash;";
	}

}
add_action( 'getpaid_subscription_admin_display_gateway', 'getpaid_admin_subscription_metabox_display_gateway' );

/**
 * Displays the subscription status.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_status( $subscription ) {
	echo $subscription->get_status_label_html();
}
add_action( 'getpaid_subscription_admin_display_status', 'getpaid_admin_subscription_metabox_display_status' );

/**
 * Displays the subscription profile id.
 *
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_metabox_display_profile_id( $subscription ) {

	$profile_id = $subscription->get_profile_id();

	if ( ! empty( $profile_id ) ) {
		$profile_id = sanitize_text_field( $profile_id );
		echo apply_filters( 'getpaid_subscription_profile_id_display', $profile_id, $subscription );
	} else {
		echo "&mdash;";
	}

}
add_action( 'getpaid_subscription_admin_display_profile_id', 'getpaid_admin_subscription_metabox_display_profile_id' );

/**
 * Displays the subscriptions update metabox.
 * 
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_update_metabox( $subscription ) {

	?>
	<div class="mt-3">

		<?php
			echo aui()->select(
				array(
					'options'          => getpaid_get_subscription_statuses(),
					'name'             => 'subscription_status',
					'id'               => 'subscription_status_update_select',
					'required'         => true,
					'no_wrap'          => false,
					'label'            => __( 'Subscription Status', 'invoicing' ),
					'help_text'        => __( 'Updating the status will trigger related actions and hooks', 'invoicing' ),
					'select2'          => true,
					'value'            => $subscription->get_status( 'edit' ),
				)
			);
		?>

		<div class="mt-2 px-3 py-2 bg-light border-top" style="margin: -12px;">
	
		<?php
			submit_button( __( 'Update', 'invoicing' ), 'primary', 'submit', false );

			$url    = esc_url( wp_nonce_url( add_query_arg( 'getpaid-admin-action', 'subscription_manual_renew' ), 'getpaid-nonce', 'getpaid-nonce' ) );
			$anchor = __( 'Renew Subscription', 'invoicing' );
			$title  = esc_attr__( 'Are you sure you want to extend the subscription and generate a new invoice that will be automatically marked as paid?', 'invoicing' );

			if ( $subscription->is_active() ) {
				echo "<a href='$url' class='float-right text-muted' onclick='return confirm(\"$title\")'>$anchor</a>";
			}

	echo '</div></div>';
}

/**
 * Displays the subscriptions invoices metabox.
 * 
 * @param WPInv_Subscription $subscription
 */
function getpaid_admin_subscription_invoice_details_metabox( $subscription ) {

	$columns = apply_filters(
		'getpaid_subscription_related_invoices_columns',
		array(
			'invoice'      => __( 'Invoice', 'invoicing' ),
			'relationship' => __( 'Relationship', 'invoicing' ),
			'date'         => __( 'Date', 'invoicing' ),
			'status'       => __( 'Status', 'invoicing' ),
			'total'        => __( 'Total', 'invoicing' ),
		),
		$subscription
	);

	// Prepare the invoices.
	$payments = $subscription->get_child_payments();
	$parent   = $subscription->get_parent_invoice();

	if ( $parent->get_id() ) {
		$payments = array_merge( array( $parent ), $payments );
	}
	
	$table_class = 'w-100 bg-white';

	if ( ! is_admin() ) {
		$table_class = 'table table-bordered table-striped';
	}

	?>
		<div class="m-0" style="overflow: auto;">

			<table class="<?php echo $table_class; ?>">

				<thead>
					<tr>
						<?php
							foreach ( $columns as $key => $label ) {
								$key   = esc_attr( $key );
								$label = sanitize_text_field( $label );

								echo "<th class='subscription-invoice-field-$key bg-light p-2 text-left color-dark'>$label</th>";
							}
						?>
					</tr>
				</thead>

				<tbody>

					<?php if ( empty( $payments ) ) : ?>
						<tr>
							<td colspan="<?php echo count($columns); ?>" class="p-2 text-left text-muted">
								<?php _e( 'This subscription has no invoices.', 'invoicing' ); ?>
							</td>
						</tr>
					<?php endif; ?>

					<?php

						foreach( $payments as $payment ) :

							// Ensure that we have an invoice.
							$payment = new WPInv_Invoice( $payment );

							// Abort if the invoice is invalid.
							if ( ! $payment->get_id() ) {
								continue;
							}

							echo '<tr>';

								foreach ( array_keys( $columns ) as $key ) {
									
									echo '<td class="p-2 text-left">';

										switch( $key ) {

											case 'total':
												echo '<strong>' . wpinv_price( wpinv_format_amount( wpinv_sanitize_amount( $payment->get_total ) ), $payment->get_currency() ) . '</strong>';
												break;

											case 'relationship':
												echo $payment->is_renewal() ? __( 'Renewal Invoice', 'invoicing' ) : __( 'Initial Invoice', 'invoicing' );
												break;

											case 'date':
												echo date_i18n( /** @scrutinizer ignore-type */get_option( 'date_format' ), strtotime( $payment->get_date_created() ) );
												break;

											case 'status':

												$status = $payment->get_status_nicename();
												if ( is_admin() ) {
													$status = $payment->get_status_label_html();
												}

												echo $status;
												break;

											case 'invoice':
												$link    = esc_url( get_edit_post_link( $payment->get_id() ) );

												if ( ! is_admin() ) {
													$link = esc_url( $payment->get_view_url() );
												}

												$invoice = sanitize_text_field( $payment->get_number() );
												echo "<a href='$link'>$invoice</a>";
												break;
										}

									echo '</td>';

								}

							echo '</tr>';

						endforeach;
					?>

				</tbody>

			</table>

		</div>

	<?php
}
