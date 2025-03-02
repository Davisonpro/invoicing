<?php
/**
 * Contains the class that exports invoices.
 *
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * GetPaid_Invoice_Exporter Class.
 */
class GetPaid_Invoice_Exporter extends GetPaid_Graph_Downloader {

	/**
	 * Retrieves invoices query args.
	 *
	 * @param string $post_type post type to retrieve.
	 * @param array $args Args to search for.
	 * @return array
	 */
	public function get_invoice_query_args( $post_type, $args ) {

		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => array_keys( wpinv_get_invoice_statuses( true, false, $post_type ) ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		);

		if ( ! empty( $args['status'] ) && in_array( $args['status'], $query_args['post_status'], true ) ) {
			$query_args['post_status'] = wpinv_clean( wpinv_parse_list( $args['status'] ) );
		}

		$date_query = array();
		if ( ! empty( $args['to_date'] ) ) {
			$date_query['before'] = wpinv_clean( $args['to_date'] );
		}

		if ( ! empty( $args['from_date'] ) ) {
			$date_query['after'] = wpinv_clean( $args['from_date'] );
		}

		if ( ! empty( $date_query ) ) {
			$date_query['inclusive']  = true;
			$query_args['date_query'] = array( $date_query );
		}

		return $query_args;
	}

	/**
	 * Retrieves invoices.
	 *
	 * @param array $query_args WP_Query args.
	 * @return WPInv_Invoice[]
	 */
	public function get_invoices( $query_args ) {

		// Get invoices.
		$invoices = new WP_Query( $query_args );

		// Prepare the results.
		return array_map( 'wpinv_get_invoice', $invoices->posts );

	}

	/**
	 * Handles the actual download.
	 *
	 */
	public function export( $post_type, $args ) {

		$invoices  = $this->get_invoices( $this->get_invoice_query_args( $post_type, $args ) );
		$stream    = $this->prepare_output();
		$headers   = $this->get_export_fields( $post_type );
		$file_type = $this->prepare_file_type( strtolower( getpaid_get_post_type_label( $post_type ) ) );

		if ( 'csv' == $file_type ) {
			$this->download_csv( $invoices, $stream, $headers );
		} elseif ( 'xml' == $file_type ) {
			$this->download_xml( $invoices, $stream, $headers );
		} else {
			$this->download_json( $invoices, $stream, $headers );
		}

		fclose( $stream );
		exit;
	}

	/**
	 * Prepares a single invoice for download.
	 *
	 * @param WPInv_Invoice $invoice The invoice to prepare..
	 * @param array $fields The fields to stream.
	 * @since       1.0.19
	 * @return array
	 */
	public function prepare_row( $invoice, $fields ) {

		$prepared      = array();
		$amount_fields = $this->get_amount_fields( $invoice->get_post_type() );
		$meta_fields = $this->get_payment_form_meta( $invoice );

		foreach ( $fields as $field ) {
			$value  = '';
			$method = "get_$field";

			if ( method_exists( $invoice, $method ) ) {
				$value  = $invoice->$method();
			} else if( strpos( $field, '_' ) === 0 && isset( $meta_fields[ $field ] ) ) {
				$value = $meta_fields[ $field ];
			}

			if ( in_array( $field, $amount_fields ) ) {
				$value  = wpinv_round_amount( wpinv_sanitize_amount( $value ) );
			}

			$prepared[ $field ] = wpinv_clean( $value );

		}

		return $prepared;
	}

	/**
	 * Retrieves export fields.
	 *
	 * @param string $post_type
	 * @since       1.0.19
	 * @return array
	 */
	public function get_export_fields( $post_type ) {

		$fields = array(
			'id',
			'parent_id',
			'status',
			'date_created',
			'date_modified',
			'date_due',
			'date_completed',
			'number',
			'key',
			'description',
			'post_type',
			'mode',
			'customer_id',
			'customer_first_name',
			'customer_last_name',
			'customer_phone',
			'customer_email',
			'customer_country',
			'customer_city',
			'customer_state',
			'customer_zip',
			'customer_company',
			'customer_vat_number',
			'customer_address',
			'subtotal',
			'total_discount',
			'total_tax',
			'total_fees',
			'fees',
			'discounts',
			'taxes',
			'cart_details',
			'item_ids',
			'payment_form',
			'discount_code',
			'gateway',
			'transaction_id',
			'currency',
			'disable_taxes',
			'subscription_id',
			'remote_subscription_id',
			'is_viewed',
			'email_cc',
			'template',
			'created_via',
    	);

		// Payment form meta fields.
		$meta_fields = getpaid_get_payment_form_custom_fields();

		if ( ! empty( $meta_fields ) ) {
			foreach ( $meta_fields as $field_key => $field_label ) {
				$fields[] = $field_key;
			}
		}

		return apply_filters( 'getpaid_invoice_exporter_get_fields', $fields, $post_type );
	}

	/**
	 * Retrieves amount fields.
	 *
	 * @param string $post_type
	 * @since       1.0.19
	 * @return array
	 */
	public function get_amount_fields( $post_type ) {

		$fields = array(
			'subtotal',
			'total_discount',
			'total_tax',
			'total_fees',
    	);

		return apply_filters( 'getpaid_invoice_exporter_get_amount_fields', $fields, $post_type );
	}

	/**
	 * Retrieves payment form meta fields.
	 *
	 * @since 2.8.23
	 *
	 * @return array
	 */
	public function get_payment_form_meta( $invoice ) {
		// Payment form meta fields.
		$field_keys = getpaid_get_payment_form_custom_fields();
		$meta = get_post_meta( $invoice->get_id(), 'additional_meta_data', true );

		$field_values = array();
		if ( ! empty( $field_keys ) ) {
			foreach ( $field_keys as $field_key => $field_label ) {
				$value = '';

				if ( ! empty( $meta ) ) {
					foreach ( $meta as $meta_label => $meta_value ) {
						if ( getpaid_strtolower( wpinv_clean( wp_unslash( $meta_label ) ) ) == getpaid_strtolower( $field_label ) ) {
							$value = $meta_value;
						}
					}
				}

				$field_values[ $field_key ] = $value;
			}
		}

		return $field_values;
	}
}
