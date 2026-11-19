<?php
/**
 * EduPro LMS - Abstract Payment Gateway Interface
 *
 * All payment gateways (Cashfree, Razorpay, Stripe, PayPal, Bank Transfer)
 * extend this base class so each gateway stays isolated, pluggable, and
 * independently verifiable.
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class EduPro_Payment_Gateway {

    /**
     * Unique gateway identifier slug (e.g. 'cashfree', 'razorpay', 'stripe', 'paypal', 'bank_transfer').
     *
     * @return string
     */
    abstract public function get_id();

    /**
     * Human-readable gateway label displayed on checkout.
     *
     * @return string
     */
    abstract public function get_label();

    /**
     * Short description shown under the gateway selector on checkout.
     *
     * @return string
     */
    abstract public function get_description();

    /**
     * Primary target region label (e.g. 'India', 'UK & Global', 'Global', 'UK & India').
     *
     * @return string
     */
    abstract public function get_region();

    /**
     * Highlight badge shown on checkout (e.g. 'Primary · India', 'UK · Apple Pay · Klarna').
     *
     * @return string
     */
    abstract public function get_badge();

    /**
     * Sort priority (lower appears first: cashfree=10, razorpay=20, stripe=30, paypal=40, bank_transfer=50).
     *
     * @return int
     */
    abstract public function get_priority();

    /**
     * List of supported ISO-4217 uppercase currency codes (e.g. ['INR'], ['GBP', 'USD']).
     *
     * @return array
     */
    abstract public function get_supported_currencies();

    /**
     * Whether this gateway is enabled AND properly configured to process payments.
     *
     * @return bool
     */
    abstract public function is_enabled();

    /**
     * Whether this gateway is running in sandbox / test mode.
     *
     * @return bool
     */
    abstract public function is_test_mode();

    /**
     * Public REST webhook URL for this gateway.
     *
     * @return string
     */
    public function get_webhook_url() {
        return rest_url( 'edupro/v1/webhooks/' . $this->get_id() );
    }

    /**
     * Public, non-secret client configuration exposed to checkout JS.
     *
     * @return array
     */
    abstract public function get_client_config();

    /**
     * Create a payment session / order / intent on the remote gateway.
     *
     * @param object  $order  Row object from wp_edupro_orders.
     * @param WP_Post $course Course post object.
     * @param WP_User $user   WordPress user object.
     * @return array|WP_Error Gateway initiation payload or WP_Error.
     */
    abstract public function create_payment( $order, $course, $user );

    /**
     * Verify a payment server-side after client callback / return redirect.
     *
     * MUST perform authoritative server-side verification with the gateway API
     * (never trust client-submitted status alone).
     *
     * @param object $order   Row object from wp_edupro_orders.
     * @param array  $payload Sanitized callback payload from AJAX/return.
     * @return array|WP_Error ['verified' => bool, 'status' => string, 'transaction_id' => string, 'amount_paid' => float, 'currency' => string, 'message' => string, 'raw' => array]
     */
    abstract public function verify_payment( $order, $payload = array() );

    /**
     * Handle an incoming server-to-server webhook REST request.
     *
     * MUST cryptographically verify the webhook signature before processing.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    abstract public function handle_webhook( WP_REST_Request $request );

    /**
     * Issue a full or partial refund via the gateway API.
     *
     * @param object     $order  Row object from wp_edupro_orders.
     * @param float|null $amount Refund amount in order currency (null = full order amount).
     * @param string     $reason Human-readable refund reason.
     * @return array|WP_Error ['refund_id' => string, 'amount' => float, 'status' => string, 'raw' => array]
     */
    abstract public function refund_payment( $order, $amount = null, $reason = '' );

    /**
     * Query the gateway API directly to reconcile a pending/uncertain order.
     *
     * @param object $order Row object from wp_edupro_orders.
     * @return array|WP_Error ['status' => string, 'transaction_id' => string, 'amount_paid' => float, 'currency' => string, 'message' => string, 'raw' => array]
     */
    abstract public function reconcile_order( $order );

    // -------------------------------------------------------------------------
    // Shared Utility Methods
    // -------------------------------------------------------------------------

    /**
     * Check whether this gateway supports a given currency code.
     *
     * @param string $currency ISO-4217 currency code.
     * @return bool
     */
    public function supports_currency( $currency ) {
        if ( empty( $currency ) ) {
            return true;
        }
        $currency = strtoupper( sanitize_text_field( $currency ) );
        return in_array( $currency, $this->get_supported_currencies(), true );
    }

    /**
     * Export standardized public descriptor array matching the v1.1.0 contract.
     *
     * @return array
     */
    public function to_public_array() {
        return array(
            'id'            => $this->get_id(),
            'label'         => $this->get_label(),
            'description'   => $this->get_description(),
            'region'        => $this->get_region(),
            'badge'         => $this->get_badge(),
            'priority'      => $this->get_priority(),
            'currencies'    => $this->get_supported_currencies(),
            'sandbox'       => $this->is_test_mode(),
            'client_config' => $this->get_client_config(),
        );
    }

    /**
     * Convert major currency units (e.g. 1499.00) to minor units (e.g. 149900 paise/pence/cents).
     *
     * @param float $amount
     * @return int
     */
    protected function to_minor_units( $amount ) {
        return (int) round( floatval( $amount ) * 100 );
    }

    /**
     * Convert minor currency units back to major decimal amount.
     *
     * @param int|float $minor
     * @return float
     */
    protected function from_minor_units( $minor ) {
        return round( floatval( $minor ) / 100, 2 );
    }

    /**
     * Confirm that the gateway-reported paid amount covers the expected order amount
     * within a small floating-point rounding tolerance.
     *
     * @param float $paid_amount
     * @param float $expected_amount
     * @param float $tolerance
     * @return bool
     */
    protected function amounts_match( $paid_amount, $expected_amount, $tolerance = 0.01 ) {
        return ( floatval( $paid_amount ) + floatval( $tolerance ) ) >= floatval( $expected_amount );
    }

    /**
     * Build checkout return URL with gateway-specific query parameters.
     *
     * @param object $order
     * @param array  $extra_args
     * @return string
     */
    protected function get_checkout_return_url( $order, $extra_args = array() ) {
        $is_multi_course = function_exists( 'edupro_get_order_course_ids' )
            && count( edupro_get_order_course_ids( $order ) ) > 1;

        if ( $is_multi_course ) {
            $base = function_exists( 'edupro_get_checkout_url' )
                ? edupro_get_checkout_url()
                : home_url( '/checkout/' );
            $base = add_query_arg( 'mode', 'cart', $base );
        } else {
            $base = function_exists( 'edupro_get_checkout_url' )
                ? edupro_get_checkout_url( $order->course_id )
                : add_query_arg( 'course_id', absint( $order->course_id ), home_url( '/checkout/' ) );
        }

        return add_query_arg( $extra_args, $base );
    }

    /**
     * Read a trimmed string option safely.
     *
     * @param string $option_key
     * @param string $default
     * @return string
     */
    protected function get_trimmed_option( $option_key, $default = '' ) {
        return trim( (string) get_option( $option_key, $default ) );
    }

    /**
     * Check whether sandbox/test mode option is active, honoring legacy *_test_mode option fallback.
     *
     * @param string $sandbox_option
     * @param string $legacy_option
     * @return bool
     */
    protected function read_sandbox_option( $sandbox_option, $legacy_option = '' ) {
        $val = get_option( $sandbox_option, null );
        if ( $val !== null && $val !== false ) {
            return (string) $val === '1';
        }
        if ( $legacy_option !== '' ) {
            $legacy = get_option( $legacy_option, null );
            if ( $legacy !== null && $legacy !== false ) {
                return (string) $legacy === '1';
            }
        }
        return true;
    }
}
