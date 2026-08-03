<?php
/**
 * Customers Table Class
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WPInv_Customers_Table Class
 *
 * Renders the Gateway Reports table
 *
 * @since 1.0.19
 */
class WPInv_Customers_Table extends WP_List_Table {

	/**
	 * @var int Number of items per page
	 * @since 1.0.19
	 */
	public $per_page = 25;

	/**
	 * @var int Number of items
	 * @since 1.0.19
	 */
	public $total_count = 0;

	public $query;

	/**
	 * The transient that caches the data used to build the filters.
	 *
	 * @since 2.8.58
	 */
	const FILTERS_TRANSIENT = 'getpaid_customers_table_filters';

	/**
	 * @var null|array Cached filter data.
	 * @since 2.8.58
	 */
	protected $filter_data = null;

	/**
	 * Get things started
	 *
	 * @since 1.0.19
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {

		// Set parent defaults
		parent::__construct(
            array(
				'singular' => 'id',
				'plural'   => 'ids',
				'ajax'     => false,
            )
        );

		$this->per_page = $this->get_items_per_page( 'getpaid_customers_per_page', $this->per_page );
	}

	/**
	 * Gets the name of the primary column.
	 *
	 * @since 1.0.19
	 * @access protected
	 *
	 * @return string Name of the primary column.
	 */
	protected function get_primary_column_name() {
		return 'customer';
	}

	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $customer, $column_name ) {
		$value = esc_html( $customer->get( $column_name ) );
		return apply_filters( 'wpinv_customers_table_column' . $column_name, $value, $customer );
	}

	/**
	 * Displays the country column.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 *
	 * @return string Column Name
	 */
	public function column_country( $customer ) {
		$country = wpinv_sanitize_country( $customer->get( 'country' ) );
		if ( $country ) {
			$country = wpinv_country_name( $country );
		}
		return esc_html( $country );
	}

	/**
	 * Displays the state column.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 *
	 * @return string Column Name
	 */
	public function column_state( $customer ) {
		$country = wpinv_sanitize_country( $customer->get( 'country' ) );
		$state   = $customer->get( 'state' );
		if ( $state ) {
			$state = wpinv_state_name( $state, $country );
		}

		return esc_html( $state );
	}

	/**
	 * Displays the signup column.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 *
	 * @return string Column Name
	 */
	public function column_date_created( $customer ) {
		return getpaid_format_date_value( $customer->get( 'date_created' ) );
	}

	/**
	 * Displays the total spent column.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 *
	 * @return string Column Name
	 */
	public function column_purchase_value( $customer ) {
		return wpinv_price( (float) $customer->get( 'purchase_value' ) );
	}

	/**
	 * Displays the total spent column.
	 *
	 * @since 1.0.19
	 *
	 * @param GetPaid_Customer $customer
	 *
	 * @return string Column Name
	 */
	public function column_purchase_count( $customer ) {
		$value = $customer->get( 'purchase_count' );
		$url   = $customer->get( 'user_id' ) ? add_query_arg( array( 'post_type' => 'wpi_invoice', 'author' => $customer->get( 'user_id' ), ), admin_url( 'edit.php' ) ) : '';

		return ( empty( $value ) || empty( $url ) ) ? (int) $value : '<a href="' . esc_url( $url ) . '">' . absint( $value ) . '</a>';

	}

	/**
	 * Displays the customers name
	 *
	 * @param  GetPaid_Customer $customer customer.
	 * @return string
	 */
	public function column_customer( $customer ) {

		$first_name = $customer->get( 'first_name' );
		$last_name  = $customer->get( 'last_name' );
		$email      = $customer->get( 'email' );
		$avatar     = get_avatar( $customer->get( 'user_id' ) ? $customer->get( 'user_id' ) : $email, 32 );

		// Customer view URL.
		$view_url = $customer->get( 'user_id' ) ? esc_url( add_query_arg( 'user_id', $customer->get( 'user_id' ), admin_url( 'user-edit.php' ) ) ) : false;
		$actions  = array();

		if ( $view_url ) {
			$invoices_url = esc_url(
				add_query_arg(
					array(
						'post_type' => 'wpi_invoice',
						'author'    => $customer->get( 'user_id' ),
					),
					admin_url( 'edit.php' )
				)
			);

			$actions['view']     = '<a href="' . $view_url . '#getpaid-fieldset-billing">' . __( 'Edit Details', 'invoicing' ) . '</a>';
			$actions['invoices'] = '<a href="' . $invoices_url . '">' . __( 'Invoices', 'invoicing' ) . '</a>';
		}

		$row_actions = empty( $actions ) ? '' : $this->row_actions( $actions );

		// Customer's name.
		$name   = esc_html( trim( "$first_name $last_name" ) );

		if ( ! empty( $name ) ) {
			$name = "<div style='overflow: hidden;height: 18px;'>$name</div>";
		}

		$email = "<div class='row-title'><a href='mailto:$email'>$email</a></div>";

		return "<div style='display: flex;'><div>$avatar</div><div style='margin-left: 10px;'>$name<strong>$email</strong>$row_actions</div></div>";

	}

	/**
	 * Retrieve the current page number
	 *
	 * @since 1.0.19
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Returns bulk actions.
	 *
	 * @since 1.0.19
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		return array();
	}

	/**
	 * Returns the data used to build the status views and the filters.
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
			'statuses' => $this->query_status_counts(),
			'months'   => $this->query_created_months(),
		);

		set_transient( self::FILTERS_TRANSIENT, $this->filter_data, 12 * HOUR_IN_SECONDS );

		return $this->filter_data;
	}

	/**
	 * Returns the available customer statuses.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	public function get_customer_statuses() {
		return apply_filters(
			'getpaid_customer_statuses',
			array(
				'active'   => __( 'Active', 'invoicing' ),
				'inactive' => __( 'Inactive', 'invoicing' ),
				'blocked'  => __( 'Blocked', 'invoicing' ),
			)
		);
	}

	/**
	 * Counts the customers in each status.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	protected function query_status_counts() {
		global $wpdb;

		$counts  = array();
		$results = $wpdb->get_results( "SELECT `status`, COUNT(`id`) AS `total` FROM {$wpdb->prefix}getpaid_customers GROUP BY `status`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $results as $result ) {
			$counts[ $result->status ] = (int) $result->total;
		}

		return $counts;
	}

	/**
	 * Returns the number of customers in each status.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	public function get_status_counts() {
		$data = $this->get_filter_data();

		return isset( $data['statuses'] ) ? $data['statuses'] : array();
	}

	/**
	 * Displays the status views.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	protected function get_views() {
		$counts   = $this->get_status_counts();
		$current  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_url = remove_query_arg( array( 'status', 'paged' ) );

		$views = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $base_url ),
				'' === $current ? ' class="current"' : '',
				esc_html__( 'All', 'invoicing' ),
				number_format_i18n( array_sum( $counts ) )
			),
		);

		foreach ( $this->get_customer_statuses() as $status => $label ) {

			// Only show statuses that are in use.
			if ( empty( $counts[ $status ] ) ) {
				continue;
			}

			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base_url ) ),
				$current === $status ? ' class="current"' : '',
				esc_html( $label ),
				number_format_i18n( $counts[ $status ] )
			);
		}

		return $views;
	}

	/**
	 * Queries the months in which customers were created.
	 *
	 * @since 2.8.58
	 * @return array
	 */
	protected function query_created_months() {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT DISTINCT YEAR( `date_created` ) AS `year`, MONTH( `date_created` ) AS `month` FROM {$wpdb->prefix}getpaid_customers WHERE `date_created` != '0000-00-00 00:00:00' ORDER BY `date_created` DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Returns the months in which customers were created.
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_month  = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
		$current_paying = isset( $_GET['paying'] ) ? sanitize_key( $_GET['paying'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$months = $this->get_created_months();

		echo '<div class="alignleft actions">';

		// Keep the active view when filtering.
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $current_status ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $current_status ) . '" />';
		}

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

		// Paying customers.
		echo '<label class="screen-reader-text" for="filter-by-paying">' . esc_html__( 'Filter by invoices', 'invoicing' ) . '</label>';
		echo '<select name="paying" id="filter-by-paying">';

		$paying_options = array(
			''    => __( 'All customers', 'invoicing' ),
			'yes' => __( 'With invoices', 'invoicing' ),
			'no'  => __( 'Without invoices', 'invoicing' ),
		);

		foreach ( $paying_options as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $current_paying, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		submit_button( __( 'Filter', 'invoicing' ), '', 'filter_action', false );

		echo '</div>';
	}

	/**
	 *  Prepares the display query
	 */
	public function prepare_query() {

		// Prepare query args.
		$query = array(
			'number' => $this->per_page,
			'paged'  => $this->get_paged(),
		);

		foreach ( array( 'orderby', 'order', 's' ) as $field ) {
			if ( isset( $_GET[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$query[ $field ] = wpinv_clean( rawurlencode_deep( $_GET[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		foreach ( GetPaid_Customer_Data_Store::get_database_fields() as $field => $type ) {

			if ( isset( $_GET[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$query[ $field ] = wpinv_clean( rawurlencode_deep( $_GET[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			// Min max.
			if ( '%f' === $type || '%d' === $type ) {

				if ( isset( $_GET[ $field . '_min' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$query[ $field . '_min' ] = floatval( $_GET[ $field . '_min' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				if ( isset( $_GET[ $field . '_max' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$query[ $field . '_max' ] = floatval( $_GET[ $field . '_max' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}
		}

		// Filter by customers with/without invoices.
		$paying = isset( $_GET['paying'] ) ? sanitize_key( $_GET['paying'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'yes' === $paying ) {
			$query['purchase_count_min'] = 1;
		} elseif ( 'no' === $paying ) {
			$query['purchase_count_max'] = 0;
		}

		// Filter by the month of creation.
		$month = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 6 === strlen( (string) $month ) ) {
			$query['date_created_query'] = array(
				array(
					'year'  => (int) substr( (string) $month, 0, 4 ),
					'month' => (int) substr( (string) $month, 4, 2 ),
				),
			);
		}

		// Prepare class properties.
		$this->query       = getpaid_get_customers( $query, 'query' );
		$this->total_count = $this->query->get_total();
		$this->items       = $this->query->get_results();
	}

	/**
	 * Setup the final data for the table
	 *
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = $this->screen ? get_hidden_columns( $this->screen ) : array();
		$sortable = $this->get_sortable_columns();
		$this->prepare_query();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args(
			array(
				'total_items' => $this->total_count,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $this->total_count / $this->per_page ),
			)
		);
	}

	/**
	 * Sortable table columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable = array(
			'customer' => array( 'first_name', true ),
		);

		foreach ( GetPaid_Customer_Data_Store::get_database_fields() as $field => $type ) {
			$sortable[ $field ] = array( $field, true );
		}

		return apply_filters( 'manage_getpaid_customers_sortable_table_columns', $sortable );
	}

	/**
	 * Table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'customer' => __( 'Customer', 'invoicing' ),
		);

		// Add address fields.
		foreach ( getpaid_user_address_fields() as $key => $value ) {

			// Skip id, user_id and email.
			if ( ! in_array( $key, array( 'id', 'user_id', 'email', 'purchase_value', 'purchase_count', 'date_created', 'date_modified', 'uuid', 'first_name', 'last_name' ), true ) ) {
				$columns[ $key ] = $value;
			}
		}

		$columns['purchase_value'] = __( 'Total Spend', 'invoicing' );
		$columns['purchase_count'] = __( 'Invoices', 'invoicing' );
		$columns['date_created']   = __( 'Date created', 'invoicing' );

		return apply_filters( 'manage_getpaid_customers_table_columns', $columns );
	}
}
