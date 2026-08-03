<?php
/**
 * Displays a list of all subscriptions rules
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Subscriptions table class.
 */
class WPInv_Subscriptions_List_Table extends WP_List_Table {

	/**
	 * URL of this page
	 *
	 * @var   string
	 * @since 1.0.19
	 */
	public $base_url;

	/**
	 * Query
	 *
	 * @var   GetPaid_Subscriptions_Query
	 * @since 1.0.19
	 */
	public $query;

	/**
	 * Total subscriptions
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $total_count;

	/**
	 * Current status subscriptions
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $current_total_count;

	/**
	 * Status counts
	 *
	 * @var   array
	 * @since 1.0.19
	 */
	public $status_counts;

	/**
	 * Number of results to show per page
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	public $per_page = 10;

	/**
	 * Bulk action notice to display.
	 *
	 * @var   array
	 * @since 2.8.37
	 */
	public $bulk_action_notice = array();

	/**
	 * The transient that caches the data used to build the filters.
	 *
	 * @since 2.8.58
	 */
	const FILTERS_TRANSIENT = 'getpaid_subscriptions_table_filters';

	/**
	 * @var null|array Cached filter data.
	 * @since 2.8.58
	 */
	protected $filter_data = null;

	/**
	 *  Constructor function.
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
			)
		);

		$this->per_page = $this->get_items_per_page( 'getpaid_subscriptions_per_page', $this->per_page );

		$this->process_bulk_action();

		$this->prepare_query();

		$this->base_url = remove_query_arg( 'status' );

	}

	/**
	 *  Prepares the display query
	 */
	public function prepare_query() {

		// Prepare query args.
		$query = array(
			'number'      => $this->per_page,
			'paged'       => $this->get_paged(),
			'status'      => ( isset( $_GET['status'] ) && array_key_exists( $_GET['status'], getpaid_get_subscription_statuses() ) ) ? sanitize_text_field( $_GET['status'] ) : 'all',
			'orderby'     => ( isset( $_GET['orderby'] ) ) ? sanitize_text_field( $_GET['orderby'] ) : 'id',
			'order'       => ( isset( $_GET['order'] ) ) ? sanitize_text_field( $_GET['order'] ) : 'DESC',
			'customer_in' => $this->get_user_in(),
		);

		// Filter by item.
		$item_id = isset( $_REQUEST['item_id'] ) ? absint( $_REQUEST['item_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $item_id ) ) {
			$query['product_in'] = array( $item_id );
		}

		// Filter by the month of creation.
		$month = isset( $_REQUEST['m'] ) ? absint( $_REQUEST['m'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 6 === strlen( (string) $month ) ) {
			$query['date_created_query'] = array(
				array(
					'year'  => (int) substr( (string) $month, 0, 4 ),
					'month' => (int) substr( (string) $month, 4, 2 ),
				),
			);
		}

		if ( is_array( $query['customer_in'] ) && empty( $query['customer_in'] ) ) {
			$this->total_count         = 0;
			$this->current_total_count = 0;
			$this->items               = array();
			$this->status_counts       = array();
			return;
		}

		// Prepare class properties.
		$this->query               = new GetPaid_Subscriptions_Query( $query );
		$this->total_count         = $this->query->get_total();
		$this->current_total_count = $this->query->get_total();
		$this->items               = $this->query->get_results();
		$this->status_counts       = getpaid_get_subscription_status_counts( $query );

		if ( 'all' != $query['status'] ) {
			unset( $query['status'] );
			$this->total_count   = getpaid_get_subscriptions( $query, 'count' );
		}

	}

	/**
	 * Get user in.
	 *
	 */
	public function get_user_in() {

		// Abort if no user.
		if ( empty( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		// Or invalid user.
		$user = wp_unslash( sanitize_text_field( $_REQUEST['s'] ) );

		if ( empty( $user ) ) {
			return null;
		}

		// Search matching users.
		$user  = '*' . $user . '*';
		$users = new WP_User_Query(
			array(
				'fields'      => 'ID',
				'search'      => $user,
				'count_total' => false,
			)
		);

		return $users->get_results();
	}

	/**
	 * Gets the list of views available on this table.
	 *
	 * The format is an associative array:
	 * - `'id' => 'link'`
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_views() {

		$current  = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';
		$views    = array(

			'all' => sprintf(
				'<a href="%s" %s>%s&nbsp;<span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', false, $this->base_url ) ),
				$current === 'all' ? ' class="current"' : '',
				__( 'All', 'invoicing' ),
				$this->total_count
			),

		);

		foreach ( array_filter( $this->status_counts ) as $status => $count ) {

			$views[ $status ] = sprintf(
				'<a href="%s" %s>%s&nbsp;<span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', urlencode( $status ), $this->base_url ) ),
				$current === $status ? ' class="current"' : '',
				esc_html( getpaid_get_subscription_status_label( $status ) ),
				$count
			);

		}

		return $views;

	}

	/**
	 * Returns the data used to build the filters.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	public function get_filter_data() {

		if ( is_array( $this->filter_data ) ) {
			return $this->filter_data;
		}

		$cached = get_transient( self::FILTERS_TRANSIENT );

		if ( is_array( $cached ) ) {
			$this->filter_data = $cached;

			return $this->filter_data;
		}

		$this->filter_data = array(
			'items'  => $this->query_used_items(),
			'months' => $this->query_created_months(),
		);

		set_transient( self::FILTERS_TRANSIENT, $this->filter_data, 12 * HOUR_IN_SECONDS );

		return $this->filter_data;
	}

	/**
	 * Queries the items that have at least one subscription.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	protected function query_used_items() {
		global $wpdb;

		return wp_parse_id_list( $wpdb->get_col( "SELECT DISTINCT `product_id` FROM {$wpdb->prefix}wpinv_subscriptions WHERE `product_id` > 0" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Queries the months in which subscriptions were created.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	protected function query_created_months() {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT DISTINCT YEAR( `created` ) AS `year`, MONTH( `created` ) AS `month` FROM {$wpdb->prefix}wpinv_subscriptions WHERE `created` != '0000-00-00 00:00:00' ORDER BY `created` DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$months  = array();

		foreach ( $results as $result ) {
			$months[] = array(
				'year'  => (int) $result->year,
				'month' => (int) $result->month,
			);
		}

		return $months;
	}

	/**
	 * Returns the items that have at least one subscription.
	 *
	 * @since 2.8.58
	 * @return array An array of item ids and their names.
	 */
	public function get_used_items() {
		$data = $this->get_filter_data();
		$ids  = isset( $data['items'] ) ? wp_parse_id_list( $data['items'] ) : array();

		if ( empty( $ids ) ) {
			return array();
		}

		$items = array();
		$posts = get_posts(
			array(
				'post__in'    => $ids,
				'post_type'   => 'wpi_item',
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		foreach ( $posts as $post ) {
			$items[ $post->ID ] = get_the_title( $post );
		}

		asort( $items );

		return $items;
	}

	/**
	 * Returns the months in which subscriptions were created.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	public function get_created_months() {
		$data = $this->get_filter_data();

		return isset( $data['months'] ) ? (array) $data['months'] : array();
	}

	/**
	 * Displays the filters above the table.
	 *
	 * @since 2.8.58
	 *
	 * @param string $which Either top or bottom.
	 */
	protected function extra_tablenav( $which ) {

		if ( 'top' !== $which ) {
			return;
		}

		$months = $this->get_created_months();
		$items  = $this->get_used_items();

		if ( empty( $months ) && empty( $items ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_month = isset( $_REQUEST['m'] ) ? absint( $_REQUEST['m'] ) : 0;
		$current_item  = isset( $_REQUEST['item_id'] ) ? absint( $_REQUEST['item_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="alignleft actions">';

		// Date created.
		if ( ! empty( $months ) ) {
			echo '<label class="screen-reader-text" for="filter-by-date">' . esc_html__( 'Filter by date', 'invoicing' ) . '</label>';
			echo '<select name="m" id="filter-by-date">';
			echo '<option value="0">' . esc_html__( 'All dates', 'invoicing' ) . '</option>';

			foreach ( $months as $month ) {
				$value = sprintf( '%04d%02d', $month['year'], $month['month'] );

				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $current_month, (int) $value, false ),
					esc_html( sprintf( '%1$s %2$d', $GLOBALS['wp_locale']->get_month( $month['month'] ), $month['year'] ) )
				);
			}

			echo '</select>';
		}

		// Item.
		if ( ! empty( $items ) ) {
			echo '<label class="screen-reader-text" for="filter-by-item">' . esc_html__( 'Filter by item', 'invoicing' ) . '</label>';
			echo '<select name="item_id" id="filter-by-item">';
			echo '<option value="0">' . esc_html__( 'All items', 'invoicing' ) . '</option>';

			foreach ( $items as $item_id => $item_name ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $item_id ),
					selected( $current_item, $item_id, false ),
					esc_html( $item_name )
				);
			}

			echo '</select>';
		}

		submit_button( __( 'Filter', 'invoicing' ), '', 'filter_action', false );

		echo '</div>';
	}

	/**
	 * Render most columns
	 *
	 * @access      private
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_default( $item, $column_name ) {
		return apply_filters( "getpaid_subscriptions_table_column_$column_name", $item->$column_name );
	}

	/**
	 * This is how checkbox column renders.
	 *
	 * @param WPInv_Subscription $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="id[]" value="%s" />', esc_html( $item->get_id() ) );
	}

	/**
	 * Status column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_status( $item ) {
		$extra = $item->has_status( 'expired' ) ? '<small class="text-muted d-block">' . wp_sprintf( _x( 'On: %s', 'Expired On:', 'invoicing' ), getpaid_format_date_value( $item->get_expiration() ) ) . '</small>' : '';

		return $item->get_status_label_html() . $extra;
	}

	/**
	 * Subscription column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_subscription( $item ) {

		$username = __( '(Missing User)', 'invoicing' );

		$user = get_userdata( $item->get_customer_id() );
		$capabilities = wpinv_current_user_can_manage_invoicing();

		if ( $user ) {
			$username = sprintf(
				'<a href="user-edit.php?user_id=%s">%s</a>',
				absint( $user->ID ),
				! empty( $user->display_name ) ? esc_html( $user->display_name ) : sanitize_email( $user->user_email )
			);
		}

		// translators: $1: is opening link, $2: is subscription id number, $3: is closing link tag, $4: is user's name
		$column_content = sprintf(
			_x( '%1$s#%2$s%3$s for %4$s', 'Subscription title on admin table. (e.g.: #211 for John Doe)', 'invoicing' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wpinv-subscriptions&id=' . absint( $item->get_id() ) ) ) . '">',
			'<strong>' . esc_attr( $item->get_id() ) . '</strong>',
			'</a>',
			$username
		);

		$row_actions = array();

		// View subscription.
		$view_url    = esc_url( add_query_arg( 'id', $item->get_id(), admin_url( 'admin.php?page=wpinv-subscriptions' ) ) );
		$row_actions['view'] = '<a href="' . $view_url . '">' . __( 'View Subscription', 'invoicing' ) . '</a>';

		// View invoice.
		$invoice = get_post( $item->get_parent_invoice_id() );

		if ( ! empty( $invoice ) ) {
			$invoice_url            = get_edit_post_link( $invoice );
			$row_actions['invoice'] = '<a href="' . $invoice_url . '">' . __( 'View Invoice', 'invoicing' ) . '</a>';
		}

		$delete_url            = esc_url(
			wp_nonce_url(
				add_query_arg(
					array(
						'getpaid-admin-action' => 'subscription_manual_delete',
						'id'                   => $item->get_id(),
					)
				),
				'getpaid-nonce',
				'getpaid-nonce'
			)
		);
		$row_actions['delete'] = '<a class="text-danger" href="' . $delete_url . '">' . __( 'Delete Subscription', 'invoicing' ) . '</a>';

		if ( ! $capabilities ) {
			$row_actions = array();
		}

		$row_actions = $this->row_actions( apply_filters( 'getpaid_subscription_table_row_actions', $row_actions, $item ) );

		return "<strong>$column_content</strong>" . $this->column_amount( $item ) . $row_actions;
	}

	/**
	 * Renewal date column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_renewal_date( $item ) {
		if ( $item->has_status( 'active trialling' ) ) {
			$value = getpaid_format_date_value( $item->get_expiration() );
		} else {
			$value = '-';
		}

		return $value;
	}

	/**
	 * Start date column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_start_date( $item ) {

		$gateway = $item->get_parent_invoice()->get_gateway_title();

		if ( empty( $gateway ) ) {
			return getpaid_format_date_value( $item->get_date_created() );
		}

		$url = apply_filters( 'getpaid_remote_subscription_profile_url', '', $item );
		if ( ! empty( $url ) ) {

			return getpaid_format_date_value( $item->get_date_created() ) . '<br>' . sprintf(
				__( 'Via %s', 'invoicing' ),
				'<strong><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $gateway ) . '</a></strong>'
			);

		}

		return getpaid_format_date_value( $item->get_date_created() ) . '<br>' . sprintf(
			__( 'Via %s', 'invoicing' ),
			'<strong>' . esc_html( $gateway ) . '</strong>'
		);

	}

	/**
	 * Amount column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.19
	 * @return      string
	 */
	public static function column_amount( $item ) {
		$amount = getpaid_get_formatted_subscription_amount( $item );
		return "<span class='text-muted form-text mt-2 mb-2 ms-1 ml-1'>$amount</span>";
	}

	/**
	 * Billing Times column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_renewals( $item ) {
		$max_bills = $item->get_bill_times();
		return $item->get_times_billed() . ' / ' . ( empty( $max_bills ) ? '&infin;' : $max_bills );
	}

	/**
	 * Product ID column
	 *
	 * @param WPInv_Subscription $item
	 * @since       1.0.0
	 * @return      string
	 */
	public function column_item( $item ) {
		$subscription_group = getpaid_get_invoice_subscription_group( $item->get_parent_invoice_id(), $item->get_id() );

		if ( empty( $subscription_group ) ) {
			return $this->generate_item_markup( $item->get_product_id() );
		}

		$markup = array_map( array( $this, 'generate_item_markup' ), array_keys( $subscription_group['items'] ) );
		return implode( ' | ', $markup );

	}

	/**
	 * Generates the items markup.
	 *
	 * @param int $item_id
	 * @since       1.0.0
	 * @return      string
	 */
	public static function generate_item_markup( $item_id ) {
		$item = get_post( $item_id );

		if ( ! empty( $item ) ) {
			$link = get_edit_post_link( $item );
			$name = esc_html( get_the_title( $item ) );
			return wpinv_current_user_can_manage_invoicing() ? "<a href='" . ( $link ? esc_url( $link ) : '#' ) . "'>$name</a>" : $name;
		} else {
			return sprintf( __( 'Item #%s', 'invoicing' ), $item_id );
		}

	}

	/**
	 * Retrieve the current page number
	 *
	 * @return      int
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Setup the final data for the table
	 *
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = $this->screen ? get_hidden_columns( $this->screen ) : array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args(
			array(
				'total_items' => $this->current_total_count,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $this->current_total_count / $this->per_page ),
			)
		);
	}

	/**
	 * Table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return self::get_table_columns();
	}

	/**
	 * Table columns.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	public static function get_table_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />',
			'subscription' => __( 'Subscription', 'invoicing' ),
			'start_date'   => __( 'Start Date', 'invoicing' ),
			'renewal_date' => __( 'Next Payment', 'invoicing' ),
			'renewals'     => __( 'Payments', 'invoicing' ),
			'item'         => __( 'Items', 'invoicing' ),
			'status'       => __( 'Status', 'invoicing' ),
		);

		return apply_filters( 'manage_getpaid_subscriptions_table_columns', $columns );
	}

	/**
	 * Sortable table columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable = array(
			'subscription' => array( 'id', true ),
			'start_date'   => array( 'created', true ),
			'renewal_date' => array( 'expiration', true ),
			'renewals'     => array( 'bill_times', true ),
			'item'         => array( 'product_id', true ),
			'status'       => array( 'status', true ),
		);

		return apply_filters( 'manage_getpaid_subscriptions_sortable_table_columns', $sortable );
	}

	/**
	 * Whether the table has items to display or not
	 *
	 * @return bool
	 */
	public function has_items() {
		return ! empty( $this->current_total_count );
	}

	/**
	 * Bulk actions supported by the subscriptions table.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'invoicing' ),
			'change_status' => __( 'Change status', 'invoicing' ),
		);

		return apply_filters( 'getpaid_subscriptions_table_bulk_actions', $actions );
	}

	/**
	 * Processes bulk actions.
	 *
	 */
	public function process_bulk_action() {

		$action = $this->current_action();

		if ( empty( $action ) ) {
			return;
		}

		// Validate capabilities.
		if ( ! wpinv_current_user_can_manage_invoicing() ) {
			return;
		}

		// Validate nonce.
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$ids = isset( $_REQUEST['id'] ) ? array_map( 'absint', (array) $_REQUEST['id'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// The table submits over GET, so drop the action before the sorting and pagination links are built from the URL.
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'action', 'action2', 'bulk_status', 'id', '_wpnonce', '_wp_http_referer' ), $_SERVER['REQUEST_URI'] );
		}

		if ( empty( $ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'delete':
				foreach ( $ids as $id ) {
					$subscription = new WPInv_Subscription( $id );

					if ( $subscription->exists() ) {
						$subscription->delete();
					}
				}

				$this->bulk_action_notice = array(
					'type'    => 'success',
					'message' => __( 'Selected subscriptions have been deleted.', 'invoicing' ),
				);
				break;

			case 'change_status':
				$new_status       = isset( $_REQUEST['bulk_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bulk_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$allowed_statuses = array_keys( getpaid_get_subscription_statuses() );

				if ( empty( $new_status ) || ! in_array( $new_status, $allowed_statuses, true ) ) {
					$this->bulk_action_notice = array(
						'type'    => 'error',
						'message' => __( 'Please select a valid status.', 'invoicing' ),
					);
					return;
				}

				foreach ( $ids as $id ) {
					$subscription = new WPInv_Subscription( $id );

					if ( $subscription->exists() ) {
						$subscription->set_status( $new_status );
						$subscription->save();
					}
				}

				/* translators: %s: subscription status label */
				$this->bulk_action_notice = array(
					'type'    => 'success',
					'message' => sprintf( __( 'Selected subscriptions updated to %s.', 'invoicing' ), esc_html( getpaid_get_subscription_status_label( $new_status ) ) ),
				);
				break;
		}

	}

}
