<?php
/**
 * EduPro LMS - Cashfree Payment Gateway (Primary India)
 *
 * Implements Cashfree Payment Gateway REST API v2023-08-01:
 * - POST /orders (create order & payment_session_id for Cashfree JS SDK v3)
 * - GET  /orders/{order_id}/payments (server-side verification & reconciliation)
 * - POST /orders/{order_id}/refunds (full & partial refunds)
 * - Webhook HMAC-SHA256 base64 verification (`x-webhook-timestamp` . $raw_body)
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EduPro_Gateway_Cashfree extends EduPro_Payment_Gateway {

    const API_VERSION = '2023-08-01';

    public function get_id() {
        return 'cashfree';
    }

    public function get_label() {
        return __( 'Cashfree (UPI, Cards, NetBanking)', 'edupro' );
    }

    public function get_description() {
        return __( 'Pay instantly via UPI (GPay, PhonePe, Paytm), Indian Debit/Credit Cards, Wallets, or NetBanking.', 'edupro' );
    }

    public function get_region() {
        return __( 'India', 'edupro' );
    }

    public function get_badge() {
        return __( 'Primary · India', 'edupro' );
    }

    public function get_priority() {
        return 10;
    }

    public function get_supported_currencies() {
        return array( 'INR' );
    }

    public function is_test_mode() {
        return $this->read_sandbox_option( 'edupro_cashfree_sandbox' );
    }

    public function is_enabled() {
        if ( get_option( 'edupro_cashfree_enabled', '0' ) !== '1' ) {
            return false;
        }
        return $this->get_app_id() !== '' && $this->get_secret_key() !== '';
    }

    public function get_client_config() {
        return array(
            'cf_env'  => $this->is_test_mode() ? 'sandbox' : 'production',
            'sdk_url' => 'https://sdk.cashfree.com/js/v3/cashfree.js',
        );
    }

    /**
     * Cashfree PG API base URL.
     *
     * @return string
     */
    protected function get_api_base() {
        return $this->is_test_mode()
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    protected function get_app_id() {
        return $this->get_trimmed_option( 'edupro_cashfree_app_id' );
    }

    protected function get_secret_key() {
        return $this->get_trimmed_option( 'edupro_cashfree_secret_key' );
    }

    protected function get_webhook_secret() {
        $wh_secret = $this->get_trimmed_option( 'edupro_cashfree_webhook_secret' );
        return $wh_secret !== '' ? $wh_secret : $this->get_secret_key();
    }

    /**
     * Common HTTP headers for Cashfree PG REST API calls.
     *
     * @param array $extra
     * @return array
     */
    protected function get_api_headers( $extra = array() ) {
        return array_merge(
            array(
                'x-api-version'   => self::API_VERSION,
                'x-client-id'     => $this->get_app_id(),
                'x-client-secret' => $this->get_secret_key(),
                'Content-Type'    => 'application/json',
                'Accept'          => 'application/json',
            ),
            $extra
        );
    }

    /**
     * Sanitize customer phone to a 10-digit Indian phone format required by Cashfree.
     *
     * @param WP_User $user
     * @return string
     */
    protected function get_customer_phone( $user ) {
        $candidates = array(
            get_user_meta( $user->ID, '_edupro_phone', true ),
            get_user_meta( $user->ID, 'edupro_phone', true ),
            get_user_meta( $user->ID, 'phone', true ),
            get_user_meta( $user->ID, 'billing_phone', true ),
        );

        foreach ( $candidates as $raw ) {
            $digits = preg_replace( '/\D+/', '', (string) $raw );
            if ( strlen( $digits ) >= 10 ) {
                return substr( $digits, -10 );
            }
        }

        // Cashfree requires a valid 10-digit phone; fallback when user hasn't added one yet.
        return '9999999999';
    }

    /**
     * Create a Cashfree PG order and return payment_session_id for Drop-in / Redirect SDK v3.
     *
     * @param object  $order
     * @param WP_Post $course
     * @param WP_User $user
     * @return array|WP_Error
     */
    public function create_payment( $order, $course, $user ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'cashfree_disabled', __( 'Cashfree gateway is not enabled or configured.', 'edupro' ) );
        }

        if ( strtoupper( $order->currency ) !== 'INR' ) {
            return new WP_Error( 'cashfree_currency', __( 'Cashfree only supports INR payments.', 'edupro' ) );
        }

        $amount = round( floatval( $order->amount ), 2 );
        if ( $amount < 1.00 ) {
            return new WP_Error( 'cashfree_min_amount', __( 'Minimum Cashfree order amount is ₹1.00.', 'edupro' ) );
        }

        $return_url = $this->get_checkout_return_url(
            $order,
            array(
                'edupro_cashfree_return' => '1',
                'order_id'               => $order->order_id,
            )
        );

        $customer_name = trim( (string) $user->display_name );
        if ( $customer_name === '' ) {
            $customer_name = $user->user_login;
        }
        $customer_name = substr( preg_replace( '/[^a-zA-Z0-9 .\-]/', '', $customer_name ), 0, 100 );
        if ( $customer_name === '' ) {
            $customer_name = 'Student';
        }

        $payload = array(
            'order_id'         => $order->order_id,
            'order_amount'     => $amount,
            'order_currency'   => 'INR',
            'customer_details' => array(
                'customer_id'    => 'edupro_user_' . absint( $user->ID ),
                'customer_name'  => $customer_name,
                'customer_email' => sanitize_email( $user->user_email ),
                'customer_phone' => $this->get_customer_phone( $user ),
            ),
            'order_meta'       => array(
                'return_url' => $return_url,
                'notify_url' => $this->get_webhook_url(),
            ),
            'order_note'       => substr( sprintf( '%s Course #%d: %s', get_bloginfo( 'name' ) ?: 'Academy', absint( $course->ID ), wp_strip_all_tags( $course->post_title ) ), 0, 200 ),
            'order_tags'       => array(
                'course_id' => (string) absint( $course->ID ),
                'user_id'   => (string) absint( $user->ID ),
            ),
        );

        $response = wp_remote_post(
            $this->get_api_base() . '/orders',
            array(
                'headers' => $this->get_api_headers(
                    array( 'x-idempotency-key' => 'edupro_cf_' . $order->order_id )
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['payment_session_id'] ) ) {
            $msg = isset( $body['message'] )
                ? sanitize_text_field( $body['message'] )
                : __( 'Failed to initialize Cashfree payment session.', 'edupro' );
            return new WP_Error( 'cashfree_api_error', $msg, array( 'status' => $code, 'response' => $body ) );
        }

        $cf_order_id = ! empty( $body['cf_order_id'] ) ? (string) $body['cf_order_id'] : $order->order_id;

        return array(
            'gateway'            => $this->get_id(),
            'gateway_order_id'   => $cf_order_id,
            'cf_order_id'        => $cf_order_id,
            'payment_session_id' => sanitize_text_field( $body['payment_session_id'] ),
            'cf_env'             => $this->is_test_mode() ? 'sandbox' : 'production',
            'return_url'         => $return_url,
            'currency'           => 'INR',
            'amount'             => $amount,
        );
    }

    /**
     * Authoritative server-side verification via GET /orders/{order_id}/payments.
     *
     * @param object $order
     * @param array  $payload
     * @return array|WP_Error
     */
    public function verify_payment( $order, $payload = array() ) {
        if ( $this->get_app_id() === '' || $this->get_secret_key() === '' ) {
            return new WP_Error( 'cashfree_config', __( 'Cashfree credentials are not configured.', 'edupro' ) );
        }

        $url = $this->get_api_base() . '/orders/' . rawurlencode( $order->order_id ) . '/payments';

        $response = wp_remote_get(
            $url,
            array(
                'headers' => $this->get_api_headers(),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            $msg = is_array( $body ) && isset( $body['message'] )
                ? sanitize_text_field( $body['message'] )
                : __( 'Unable to verify Cashfree payment status.', 'edupro' );
            return new WP_Error( 'cashfree_verify_error', $msg );
        }

        $has_pending  = false;
        $has_failed   = false;
        $last_failure = '';

        foreach ( $body as $attempt ) {
            if ( ! is_array( $attempt ) ) {
                continue;
            }
            $p_status = strtoupper( (string) ( $attempt['payment_status'] ?? '' ) );

            if ( $p_status === 'SUCCESS' ) {
                $paid_currency = strtoupper( (string) ( $attempt['payment_currency'] ?? $order->currency ) );
                $paid_amount   = floatval( $attempt['payment_amount'] ?? 0 );
                $txn_id        = (string) ( $attempt['cf_payment_id'] ?? '' );

                if ( $paid_currency !== strtoupper( $order->currency ) ) {
                    return new WP_Error( 'cashfree_currency_mismatch', __( 'Paid currency does not match order currency.', 'edupro' ) );
                }
                if ( ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                    return new WP_Error( 'cashfree_amount_mismatch', __( 'Paid amount is less than the order amount.', 'edupro' ) );
                }

                return array(
                    'verified'       => true,
                    'status'         => 'completed',
                    'transaction_id' => $txn_id,
                    'amount_paid'    => $paid_amount,
                    'currency'       => $paid_currency,
                    'message'        => __( 'Cashfree payment verified successfully.', 'edupro' ),
                    'raw'            => $attempt,
                );
            }

            if ( in_array( $p_status, array( 'PENDING', 'NOT_ATTEMPTED', 'FLAGGED' ), true ) ) {
                $has_pending = true;
            } elseif ( in_array( $p_status, array( 'FAILED', 'USER_DROPPED', 'VOID', 'CANCELLED' ), true ) ) {
                $has_failed   = true;
                $last_failure = sanitize_text_field( $attempt['payment_message'] ?? $p_status );
            }
        }

        if ( $has_pending ) {
            return array(
                'verified'       => false,
                'status'         => 'pending',
                'transaction_id' => '',
                'amount_paid'    => 0.0,
                'currency'       => strtoupper( $order->currency ),
                'message'        => __( 'Payment is still pending confirmation at Cashfree.', 'edupro' ),
                'raw'            => $body,
            );
        }

        if ( $has_failed ) {
            return array(
                'verified'       => false,
                'status'         => 'failed',
                'transaction_id' => '',
                'amount_paid'    => 0.0,
                'currency'       => strtoupper( $order->currency ),
                'message'        => $last_failure !== '' ? $last_failure : __( 'Payment failed or was abandoned.', 'edupro' ),
                'raw'            => $body,
            );
        }

        // No payment attempts recorded yet; check overall order status
        $order_resp = wp_remote_get(
            $this->get_api_base() . '/orders/' . rawurlencode( $order->order_id ),
            array(
                'headers' => $this->get_api_headers(),
                'timeout' => 20,
            )
        );
        if ( ! is_wp_error( $order_resp ) && wp_remote_retrieve_response_code( $order_resp ) === 200 ) {
            $order_body   = json_decode( wp_remote_retrieve_body( $order_resp ), true );
            $order_status = strtoupper( (string) ( $order_body['order_status'] ?? '' ) );
            if ( in_array( $order_status, array( 'EXPIRED', 'TERMINATED' ), true ) ) {
                return array(
                    'verified'       => false,
                    'status'         => 'failed',
                    'transaction_id' => '',
                    'amount_paid'    => 0.0,
                    'currency'       => strtoupper( $order->currency ),
                    'message'        => sprintf( __( 'Cashfree order status: %s', 'edupro' ), $order_status ),
                    'raw'            => $order_body,
                );
            }
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => '',
            'amount_paid'    => 0.0,
            'currency'       => strtoupper( $order->currency ),
            'message'        => __( 'No completed payment found yet at Cashfree.', 'edupro' ),
            'raw'            => $body,
        );
    }

    /**
     * Reconcile order directly against Cashfree Payments API.
     *
     * @param object $order
     * @return array|WP_Error
     */
    public function reconcile_order( $order ) {
        return $this->verify_payment( $order );
    }

    /**
     * Handle Cashfree webhooks with HMAC-SHA256 base64 verification.
     *
     * Cashfree signature algorithm:
     *   base64_encode( hash_hmac( 'sha256', $timestamp . $raw_body, $secret, true ) )
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $raw_body  = $request->get_body();
        $signature = trim( (string) $request->get_header( 'x-webhook-signature' ) );
        $timestamp = trim( (string) $request->get_header( 'x-webhook-timestamp' ) );

        if ( $raw_body === '' || $signature === '' || $timestamp === '' ) {
            return new WP_REST_Response( array( 'error' => 'Missing Cashfree webhook headers or payload.' ), 400 );
        }

        // Verify signature against configured webhook secret (or fallback client secret)
        $secrets = array_unique( array_filter( array(
            $this->get_trimmed_option( 'edupro_cashfree_webhook_secret' ),
            $this->get_secret_key(),
        ) ) );

        if ( empty( $secrets ) ) {
            return new WP_REST_Response( array( 'error' => 'Cashfree webhook secret is not configured.' ), 500 );
        }

        $signed_payload = $timestamp . $raw_body;
        $sig_valid      = false;

        foreach ( $secrets as $secret ) {
            $expected = base64_encode( hash_hmac( 'sha256', $signed_payload, $secret, true ) );
            if ( hash_equals( $expected, $signature ) ) {
                $sig_valid = true;
                break;
            }
        }

        if ( ! $sig_valid ) {
            edupro_log_payment_event( '', $this->get_id(), 'webhook_invalid_signature', 'rejected', 'Cashfree HMAC-SHA256 mismatch' );
            return new WP_REST_Response( array( 'error' => 'Invalid Cashfree webhook signature.' ), 400 );
        }

        // Replay timestamp check (Cashfree sends epoch in milliseconds or seconds)
        if ( ctype_digit( $timestamp ) ) {
            $ts_seconds = strlen( $timestamp ) > 11 ? intval( $timestamp / 1000 ) : intval( $timestamp );
            if ( abs( time() - $ts_seconds ) > 600 ) {
                return new WP_REST_Response( array( 'error' => 'Webhook timestamp outside tolerance window.' ), 400 );
            }
        }

        $event = json_decode( $raw_body, true );
        if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid Cashfree webhook JSON.' ), 400 );
        }

        $event_type = sanitize_text_field( $event['type'] );
        $data       = $event['data'];
        $order_id   = sanitize_text_field( $data['order']['order_id'] ?? ( $data['refund']['order_id'] ?? '' ) );
        $payment_id = sanitize_text_field( (string) ( $data['payment']['cf_payment_id'] ?? ( $data['refund']['cf_refund_id'] ?? '' ) ) );
        $event_time = sanitize_text_field( (string) ( $event['event_time'] ?? $timestamp ) );
        $event_id   = 'cf_' . md5( $event_type . '|' . $order_id . '|' . $payment_id . '|' . $event_time );

        if ( edupro_is_webhook_event_processed( $event_id ) ) {
            return new WP_REST_Response( array( 'status' => 'duplicate_ignored' ), 200 );
        }

        if ( $order_id === '' ) {
            edupro_log_payment_event( '', $this->get_id(), $event_type, 'ignored', 'Missing order_id in payload', $event_id );
            return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
        }

        $order = edupro_get_order( $order_id );
        if ( ! $order ) {
            edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'not_found', 'Order not found', $event_id );
            return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );
        }

        if ( $event_type === 'PAYMENT_SUCCESS_WEBHOOK' ) {
            $payment       = $data['payment'] ?? array();
            $p_status      = strtoupper( (string) ( $payment['payment_status'] ?? '' ) );
            $paid_amount   = floatval( $payment['payment_amount'] ?? 0 );
            $paid_currency = strtoupper( (string) ( $payment['payment_currency'] ?? $order->currency ) );

            if ( $p_status !== 'SUCCESS' ) {
                edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'ignored', 'Payment status not SUCCESS: ' . $p_status, $event_id );
                return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
            }

            if ( $paid_currency !== strtoupper( $order->currency ) || ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                edupro_mark_order_failed( $order_id, 'Webhook amount or currency mismatch' );
                edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'mismatch', sprintf( 'Expected %s %.2f, got %s %.2f', $order->currency, $order->amount, $paid_currency, $paid_amount ), $event_id );
                return new WP_REST_Response( array( 'error' => 'Amount or currency mismatch.' ), 400 );
            }

            edupro_complete_verified_order(
                $order_id,
                $payment_id,
                'webhook_cashfree',
                array( 'event_id' => $event_id, 'payment_group' => $payment['payment_group'] ?? '' )
            );
            edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'processed', 'Order completed via Cashfree webhook', $event_id );

        } elseif ( in_array( $event_type, array( 'PAYMENT_FAILED_WEBHOOK', 'PAYMENT_USER_DROPPED_WEBHOOK' ), true ) ) {
            $reason = sanitize_text_field( $data['payment']['payment_message'] ?? $event_type );
            if ( in_array( $order->status, array( 'pending', 'processing' ), true ) ) {
                edupro_mark_order_failed( $order_id, $reason );
            }
            edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'processed', $reason, $event_id );

        } elseif ( $event_type === 'REFUND_STATUS_WEBHOOK' ) {
            $refund        = $data['refund'] ?? array();
            $refund_status = strtoupper( (string) ( $refund['refund_status'] ?? '' ) );
            $refund_id     = sanitize_text_field( (string) ( $refund['cf_refund_id'] ?? ( $refund['refund_id'] ?? '' ) ) );
            $refund_amount = floatval( $refund['refund_amount'] ?? $order->amount );

            if ( $refund_status === 'SUCCESS' ) {
                edupro_record_order_refund( $order_id, $refund_id, $refund_amount );
            }
            edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'processed', 'Refund status: ' . $refund_status, $event_id );

        } else {
            edupro_log_payment_event( $order_id, $this->get_id(), $event_type, 'ignored', 'Unhandled Cashfree event', $event_id );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Issue a refund via Cashfree PG Refunds API: POST /orders/{order_id}/refunds.
     *
     * @param object     $order
     * @param float|null $amount
     * @param string     $reason
     * @return array|WP_Error
     */
    public function refund_payment( $order, $amount = null, $reason = '' ) {
        if ( $this->get_app_id() === '' || $this->get_secret_key() === '' ) {
            return new WP_Error( 'cashfree_config', __( 'Cashfree credentials are not configured.', 'edupro' ) );
        }

        $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
        if ( $refund_amount <= 0 || $refund_amount > floatval( $order->amount ) + 0.01 ) {
            return new WP_Error( 'cashfree_refund_amount', __( 'Invalid refund amount.', 'edupro' ) );
        }

        $merchant_refund_id = 'REF_' . absint( $order->id ) . '_' . time();

        $payload = array(
            'refund_amount' => $refund_amount,
            'refund_id'     => $merchant_refund_id,
            'refund_note'   => substr( sanitize_text_field( $reason !== '' ? $reason : 'Admin refund via ' . ( get_bloginfo( 'name' ) ?: 'Academy' ) ), 0, 100 ),
        );

        $response = wp_remote_post(
            $this->get_api_base() . '/orders/' . rawurlencode( $order->order_id ) . '/refunds',
            array(
                'headers' => $this->get_api_headers(
                    array( 'x-idempotency-key' => 'edupro_cf_ref_' . $merchant_refund_id )
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body ) ) {
            $msg = is_array( $body ) && isset( $body['message'] )
                ? sanitize_text_field( $body['message'] )
                : __( 'Cashfree refund request failed.', 'edupro' );
            return new WP_Error( 'cashfree_refund_error', $msg );
        }

        return array(
            'refund_id' => (string) ( $body['cf_refund_id'] ?? $merchant_refund_id ),
            'amount'    => $refund_amount,
            'status'    => strtolower( (string) ( $body['refund_status'] ?? 'pending' ) ),
            'raw'       => $body,
        );
    }
}
