<?php
/**
 * Template that generates the upcoming renewal subscription email.
 *
 * This template can be overridden by copying it to yourtheme/invoicing/emails/wpinv-email-renewal_reminder.php.
 *
 * @version 2.8.25
 * @var WPInv_Subscription $object
 */

defined( 'ABSPATH' ) || exit;

$invoice = $object->get_parent_payment();

// Print the email header.
do_action( 'wpinv_email_header', $email_heading, $invoice, $email_type, $sent_to_admin );

// Generate the custom message body.
echo wp_kses_post( $message_body );

// Print the billing details.
do_action( 'wpinv_email_billing_details', $invoice, $email_type, $sent_to_admin );

// Print the email footer.
do_action( 'wpinv_email_footer', $invoice, $email_type, $sent_to_admin );
