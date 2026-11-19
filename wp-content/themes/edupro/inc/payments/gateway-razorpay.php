<?php
/**
 * EduPro LMS - Razorpay Payment Gateway (Secondary / Backup India)
 *
 * Implements Razorpay REST API v1:
 * - POST /v1/orders (create order)
 * - GET  /v1/payments/{payment_id} (+ capture if authorized) for server-side verification
 * - GET  /v1/orders/{order_id}/payments for order reconciliation
 * - POST /v1/payments/{payment_id}/refund for full/partial refunds
 * - Webhook HMAC-SHA256 verification via `X-Razorpay-Signature`
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EduPro_Gateway_Razorpay extends EduPro_Payment_Gateway {

    const API_BASE = 'https://api.razorpay.com/v1';

    public function get_id() {
        return 'razorpay';
    }

    public function get_label() {
        return __( 'Razorpay (UPI, Cards, NetBanking)', 'edupro' );
    }

    public function get_description() {
        return __( 'Fast Indian checkout with UPI, Cards, Wallets, EMI, and NetBanking via Razorpay.', 'edupro' );
    }

    public function get_region() {
        return __( 'India', 'edupro' );
    }

    public function get_badge() {
        return __( 'Backup · India', 'edupro' );
    }

    public function get_priority() {
        return 20;
    }

    public function get_supported_currencies() {
        return array( 'INR', 'GBP', 'USD' );
    }

    public function is_test_mode() {
        return $this->read_sandbox_option( 'edupro_razorpay_sandbox', 'edupro_razorpay_test_mode' );
    }

    public function is_enabled() {
        if ( get_option( 'edupro_razorpay_enabled', '0' ) !== '1' ) {
            return false;
        }
        return $this->get_key_id() !== '' && $this->get_key_secret() !== '';
    }

    public function get_client_config() {
        return array(
            'key_id'  => $this->get_key_id(),
            'sdk_url' => 'https://checkout.razorpay.com/v1/checkout.js',
        );
    }

    protected function get_key_id() {
        return $this->get_trimmed_option( 'edupro_razorpay_key_id' );
    }

    protected function get_key_secret() {
        return $this->get_trimmed_option( 'edupro_razorpay_key_secret' );
    }

    protected function get_webhook_secret() {
        return $this->get_trimmed_option( 'edupro_razorpay_webhook_secret' );
    }

    protected function get_auth_headers() {
        return array(
            'Authorization' => 'Basic ' . base64_encode( $this->get_key_id() . ':' . $this->get_key_secret() ),
            'Content-Type'  => 'application/json',
        );
    }

    /**
     * Create a Razorpay order via POST /v1/orders.
     *
     * @param object  $order
     * @param WP_Post $course
     * @param WP_User $user
     * @return array|WP_Error
     */
    public function create_payment( $order, $course, $user ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'razorpay_disabled', __( 'Razorpay gateway is not enabled or configured.', 'edupro' ) );
        }

        $currency     = strtoupper( (string) $order->currency );
        $amount_minor = $this->to_minor_units( $order->amount );

        if ( $amount_minor < 100 ) {
            return new WP_Error( 'razorpay_min_amount', __( 'Order amount is below Razorpay minimum.', 'edupro' ) );
        }

        $payload = array(
            'amount'          => $amount_minor,
            'currency'        => $currency,
            'receipt'         => substr( $order->order_id, 0, 40 ),
            'payment_capture' => 1,
            'notes'           => array(
                'order_id'  => $order->order_id,
                'course_id' => (string) absint( $course->ID ),
                'user_id'   => (string) absint( $user->ID ),
                'source'    => 'EduPro LMS',
            ),
        );

        $response = wp_remote_post(
            self::API_BASE . '/orders',
            array(
                'headers' => $this->get_auth_headers(),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
            $msg = isset( $body['error']['description'] )
                ? sanitize_text_field( $body['error']['description'] )
                : __( 'Razorpay order creation failed.', 'edupro' );
            return new WP_Error( 'razorpay_api_error', $msg );
        }

        return array(
            'gateway'           => $this->get_id(),
            'gateway_order_id'  => sanitize_text_field( $body['id'] ),
            'razorpay_order_id' => sanitize_text_field( $body['id'] ),
            'key_id'            => $this->get_key_id(),
            'amount_paise'      => $amount_minor,
            'amount_minor'      => $amount_minor,
            'currency'          => $currency,
            'course_title'      => wp_strip_all_tags( $course->post_title ),
            'site_name'         => get_bloginfo( 'name' ),
            'prefill'           => array(
                'name'  => $user ? $user->display_name : '',
                'email' => $user ? $user->user_email : '',
            ),
        );
    }

    /**
     * Capture an authorized Razorpay payment server-side.
     *
     * @param string $payment_id
     * @param int    $amount_minor
     * @param string $currency
     * @return array|WP_Error
     */
    protected function capture_payment( $payment_id, $amount_minor, $currency ) {
        $response = wp_remote_post(
            self::API_BASE . '/payments/' . rawurlencode( $payment_id ) . '/capture',
            array(
                'headers' => $this->get_auth_headers(),
                'body'    => wp_json_encode( array(
                    'amount'   => (int) $amount_minor,
                    'currency' => strtoupper( $currency ),
                ) ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            $msg = isset( $body['error']['description'] )
                ? sanitize_text_field( $body['error']['description'] )
                : __( 'Failed to capture authorized Razorpay payment.', 'edupro' );
            return new WP_Error( 'razorpay_capture_error', $msg );
        }

        return $body;
    }

    /**
     * Validate a Razorpay payment entity against the local order.
     * Captures automatically if status === 'authorized'.
     *
     * @param object $order
     * @param array  $payment
     * @return array|WP_Error
     */
    protected function validate_payment_entity( $order, $payment ) {
        $payment_id    = sanitize_text_field( (string) ( $payment['id'] ?? '' ) );
        $rz_order_id   = sanitize_text_field( (string) ( $payment['order_id'] ?? '' ) );
        $paid_currency = strtoupper( (string) ( $payment['currency'] ?? '' ) );
        $paid_minor    = intval( $payment['amount'] ?? 0 );
        $paid_amount   = $this->from_minor_units( $paid_minor );
        $p_status      = strtolower( (string) ( $payment['status'] ?? '' ) );

        if ( ! empty( $order->gateway_order_id ) && $rz_order_id !== $order->gateway_order_id ) {
            return new WP_Error( 'razorpay_order_mismatch', __( 'Razorpay payment does not match this order.', 'edupro' ) );
        }

        if ( $paid_currency !== strtoupper( $order->currency ) ) {
            return new WP_Error( 'razorpay_currency_mismatch', __( 'Payment currency does not match order currency.', 'edupro' ) );
        }

        if ( ! $this->amounts_match( $paid_amount, $order->amount ) ) {
            return new WP_Error( 'razorpay_amount_mismatch', __( 'Paid amount does not cover order total.', 'edupro' ) );
        }

        if ( $p_status === 'authorized' ) {
            $captured = $this->capture_payment( $payment_id, $paid_minor, $paid_currency );
            if ( is_wp_error( $captured ) ) {
                return $captured;
            }
            $payment  = $captured;
            $p_status = strtolower( (string) ( $payment['status'] ?? '' ) );
        }

        if ( $p_status === 'captured' ) {
            return array(
                'verified'       => true,
                'status'         => 'completed',
                'transaction_id' => $payment_id,
                'amount_paid'    => $paid_amount,
                'currency'       => $paid_currency,
                'message'        => __( 'Razorpay payment captured and verified.', 'edupro' ),
                'raw'            => $payment,
            );
        }

        if ( $p_status === 'refunded' ) {
            return array(
                'verified'       => false,
                'status'         => 'refunded',
                'transaction_id' => $payment_id,
                'amount_paid'    => $paid_amount,
                'currency'       => $paid_currency,
                'message'        => __( 'Payment has been refunded at Razorpay.', 'edupro' ),
                'raw'            => $payment,
            );
        }

        if ( $p_status === 'failed' ) {
            return array(
                'verified'       => false,
                'status'         => 'failed',
                'transaction_id' => $payment_id,
                'amount_paid'    => 0.0,
                'currency'       => $paid_currency,
                'message'        => sanitize_text_field( $payment['error_description'] ?? __( 'Razorpay payment failed.', 'edupro' ) ),
                'raw'            => $payment,
            );
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => $payment_id,
            'amount_paid'    => 0.0,
            'currency'       => $paid_currency,
            'message'        => sprintf( __( 'Razorpay payment status: %s', 'edupro' ), $p_status ),
            'raw'            => $payment,
        );
    }

    /**
     * Verify HMAC-SHA256 checkout signature AND perform server-side GET /v1/payments/{payment_id}.
     *
     * @param object $order
     * @param array  $payload Keys: razorpay_order_id, razorpay_payment_id, razorpay_signature
     * @return array|WP_Error
     */
    public function verify_payment( $order, $payload = array() ) {
        $key_secret = $this->get_key_secret();
        if ( $this->get_key_id() === '' || $key_secret === '' ) {
            return new WP_Error( 'razorpay_config', __( 'Razorpay credentials are not configured.', 'edupro' ) );
        }

        $rz_order_id   = sanitize_text_field( (string) ( $payload['razorpay_order_id'] ?? '' ) );
        $rz_payment_id = sanitize_text_field( (string) ( $payload['razorpay_payment_id'] ?? '' ) );
        $rz_signature  = sanitize_text_field( (string) ( $payload['razorpay_signature'] ?? '' ) );

        if ( $rz_order_id === '' || $rz_payment_id === '' || $rz_signature === '' ) {
            return new WP_Error( 'razorpay_missing_params', __( 'Missing required Razorpay verification fields.', 'edupro' ) );
        }

        if ( ! empty( $order->gateway_order_id ) && $order->gateway_order_id !== $rz_order_id ) {
            return new WP_Error( 'razorpay_order_mismatch', __( 'Razorpay order ID does not match.', 'edupro' ) );
        }

        // Step 1: Verify HMAC-SHA256 signature
        $expected_sig = hash_hmac( 'sha256', $rz_order_id . '|' . $rz_payment_id, $key_secret );
        if ( ! hash_equals( $expected_sig, $rz_signature ) ) {
            return new WP_Error( 'razorpay_bad_signature', __( 'Razorpay payment signature verification failed.', 'edupro' ) );
        }

        // Step 2: Authoritative server-side GET /v1/payments/{payment_id}
        $response = wp_remote_get(
            self::API_BASE . '/payments/' . rawurlencode( $rz_payment_id ),
            array(
                'headers' => $this->get_auth_headers(),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['id'] ) ) {
            $msg = isset( $body['error']['description'] )
                ? sanitize_text_field( $body['error']['description'] )
                : __( 'Could not fetch payment details from Razorpay.', 'edupro' );
            return new WP_Error( 'razorpay_fetch_error', $msg );
        }

        return $this->validate_payment_entity( $order, $body );
    }

    /**
     * Reconcile an order via GET /v1/orders/{gateway_order_id}/payments.
     *
     * @param object $order
     * @return array|WP_Error
     */
    public function reconcile_order( $order ) {
        if ( $this->get_key_id() === '' || $this->get_key_secret() === '' ) {
            return new WP_Error( 'razorpay_config', __( 'Razorpay credentials are not configured.', 'edupro' ) );
        }

        $rz_order_id = ! empty( $order->gateway_order_id ) ? $order->gateway_order_id : '';
        if ( $rz_order_id === '' ) {
            return new WP_Error( 'razorpay_no_order_id', __( 'Order has no Razorpay order ID to reconcile.', 'edupro' ) );
        }

        $response = wp_remote_get(
            self::API_BASE . '/orders/' . rawurlencode( $rz_order_id ) . '/payments',
            array(
                'headers' => $this->get_auth_headers(),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code  = wp_remote_retrieve_response_code( $response );
        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $items = isset( $body['items'] ) && is_array( $body['items'] ) ? $body['items'] : array();

        if ( $code < 200 || $code >= 300 ) {
            $msg = isset( $body['error']['description'] )
                ? sanitize_text_field( $body['error']['description'] )
                : __( 'Razorpay reconciliation request failed.', 'edupro' );
            return new WP_Error( 'razorpay_reconcile_error', $msg );
        }

        // First look for captured or authorized payments
        foreach ( $items as $item ) {
            $st = strtolower( (string) ( $item['status'] ?? '' ) );
            if ( in_array( $st, array( 'captured', 'authorized' ), true ) ) {
                return $this->validate_payment_entity( $order, $item );
            }
        }

        // Check refunded
        foreach ( $items as $item ) {
            if ( strtolower( (string) ( $item['status'] ?? '' ) ) === 'refunded' ) {
                return $this->validate_payment_entity( $order, $item );
            }
        }

        // Check failed
        $last_failed = null;
        foreach ( $items as $item ) {
            if ( strtolower( (string) ( $item['status'] ?? '' ) ) === 'failed' ) {
                $last_failed = $item;
            }
        }
        if ( $last_failed ) {
            return array(
                'verified'       => false,
                'status'         => 'failed',
                'transaction_id' => sanitize_text_field( (string) ( $last_failed['id'] ?? '' ) ),
                'amount_paid'    => 0.0,
                'currency'       => strtoupper( $order->currency ),
                'message'        => sanitize_text_field( $last_failed['error_description'] ?? __( 'Payment attempt failed at Razorpay.', 'edupro' ) ),
                'raw'            => $last_failed,
            );
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => '',
            'amount_paid'    => 0.0,
            'currency'       => strtoupper( $order->currency ),
            'message'        => __( 'No captured payment found yet at Razorpay.', 'edupro' ),
            'raw'            => $body,
        );
    }

    /**
     * Handle Razorpay webhooks with X-Razorpay-Signature HMAC-SHA256 verification.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $raw_body       = $request->get_body();
        $signature      = trim( (string) $request->get_header( 'x-razorpay-signature' ) );
        $webhook_secret = $this->get_webhook_secret();

        if ( $webhook_secret === '' ) {
            return new WP_REST_Response( array( 'error' => 'Razorpay webhook secret is not configured.' ), 500 );
        }

        if ( $raw_body === '' || $signature === '' ) {
            return new WP_REST_Response( array( 'error' => 'Missing Razorpay signature or payload.' ), 400 );
        }

        $expected = hash_hmac( 'sha256', $raw_body, $webhook_secret );
        if ( ! hash_equals( $expected, $signature ) ) {
            edupro_log_payment_event( '', $this->get_id(), 'webhook_invalid_signature', 'rejected', 'Razorpay HMAC mismatch' );
            return new WP_REST_Response( array( 'error' => 'Invalid Razorpay webhook signature.' ), 400 );
        }

        $event = json_decode( $raw_body, true );
        if ( ! is_array( $event ) || empty( $event['event'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid Razorpay webhook JSON.' ), 400 );
        }

        $event_type = sanitize_text_field( $event['event'] );
        $header_eid = trim( (string) $request->get_header( 'x-razorpay-event-id' ) );
        $event_id   = $header_eid !== '' ? 'rzp_' . $header_eid : 'rzp_' . md5( $raw_body );

        if ( edupro_is_webhook_event_processed( $event_id ) ) {
            return new WP_REST_Response( array( 'status' => 'duplicate_ignored' ), 200 );
        }

        $payment_entity = $event['payload']['payment']['entity'] ?? array();
        $refund_entity  = $event['payload']['refund']['entity'] ?? array();

        // Locate local order via notes.order_id, gateway_order_id, or transaction_id
        $order = null;
        if ( ! empty( $payment_entity['notes']['order_id'] ) ) {
            $order = edupro_get_order( sanitize_text_field( $payment_entity['notes']['order_id'] ) );
        }
        if ( ! $order && ! empty( $payment_entity['order_id'] ) ) {
            $order = edupro_get_order_by_gateway_order_id( sanitize_text_field( $payment_entity['order_id'] ), $this->get_id() );
        }
        if ( ! $order && ! empty( $refund_entity['payment_id'] ) ) {
            $order = edupro_get_order_by_transaction( sanitize_text_field( $refund_entity['payment_id'] ) );
        }

        if ( ! $order ) {
            edupro_log_payment_event( '', $this->get_id(), $event_type, 'not_found', 'Matching order not found', $event_id );
            return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );
        }

        if ( $event_type === 'payment.captured' ) {
            $payment_id    = sanitize_text_field( (string) ( $payment_entity['id'] ?? '' ) );
            $paid_currency = strtoupper( (string) ( $payment_entity['currency'] ?? '' ) );
            $paid_amount   = $this->from_minor_units( $payment_entity['amount'] ?? 0 );

            if ( $paid_currency !== strtoupper( $order->currency ) || ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                edupro_mark_order_failed( $order->order_id, 'Razorpay webhook amount or currency mismatch' );
                edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'mismatch', 'Currency/amount mismatch', $event_id );
                return new WP_REST_Response( array( 'error' => 'Amount or currency mismatch.' ), 400 );
            }

            edupro_complete_verified_order(
                $order->order_id,
                $payment_id,
                'webhook_razorpay',
                array( 'event_id' => $event_id, 'method' => $payment_entity['method'] ?? '' )
            );
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Completed via Razorpay webhook', $event_id );

        } elseif ( $event_type === 'payment.failed' ) {
            $reason = sanitize_text_field( $payment_entity['error_description'] ?? 'Razorpay payment failed' );
            if ( in_array( $order->status, array( 'pending', 'processing' ), true ) ) {
                edupro_mark_order_failed( $order->order_id, $reason );
            }
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', $reason, $event_id );

        } elseif ( $event_type === 'refund.processed' ) {
            $refund_id     = sanitize_text_field( (string) ( $refund_entity['id'] ?? '' ) );
            $refund_amount = $this->from_minor_units( $refund_entity['amount'] ?? $this->to_minor_units( $order->amount ) );
            edupro_record_order_refund( $order->order_id, $refund_id, $refund_amount );
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Refund processed: ' . $refund_id, $event_id );

        } else {
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'ignored', 'Unhandled Razorpay event', $event_id );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Issue a refund via POST /v1/payments/{transaction_id}/refund.
     *
     * @param object     $order
     * @param float|null $amount
     * @param string     $reason
     * @return array|WP_Error
     */
    public function refund_payment( $order, $amount = null, $reason = '' ) {
        if ( $this->get_key_id() === '' || $this->get_key_secret() === '' ) {
            return new WP_Error( 'razorpay_config', __( 'Razorpay credentials are not configured.', 'edupro' ) );
        }

        if ( empty( $order->transaction_id ) ) {
            return new WP_Error( 'razorpay_no_txn', __( 'Missing Razorpay payment ID for refund.', 'edupro' ) );
        }

        $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
        if ( $refund_amount <= 0 || $refund_amount > floatval( $order->amount ) + 0.01 ) {
            return new WP_Error( 'razorpay_refund_amount', __( 'Invalid refund amount.', 'edupro' ) );
        }

        $payload = array(
            'amount' => $this->to_minor_units( $refund_amount ),
            'notes'  => array(
                'order_id' => $order->order_id,
                'reason'   => substr( sanitize_text_field( $reason !== '' ? $reason : 'Admin refund' ), 0, 200 ),
            ),
        );

        $response = wp_remote_post(
            self::API_BASE . '/payments/' . rawurlencode( $order->transaction_id ) . '/refund',
            array(
                'headers' => $this->get_auth_headers(),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
            $msg = isset( $body['error']['description'] )
                ? sanitize_text_field( $body['error']['description'] )
                : __( 'Razorpay refund failed.', 'edupro' );
            return new WP_Error( 'razorpay_refund_error', $msg );
        }

        return array(
            'refund_id' => sanitize_text_field( $body['id'] ),
            'amount'    => $refund_amount,
            'status'    => strtolower( (string) ( $body['status'] ?? 'processed' ) ),
            'raw'       => $body,
        );
    }
}
