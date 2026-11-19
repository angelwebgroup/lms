<?php
/**
 * EduPro LMS - PayPal Payment Gateway (International)
 *
 * Implements PayPal Orders REST API v2 + Webhooks v1:
 * - OAuth2 access token cached via WordPress transient
 * - POST /v2/checkout/orders (intent: CAPTURE, returns approve_url)
 * - POST /v2/checkout/orders/{id}/capture (server-side capture & amount/currency verification)
 * - GET  /v2/checkout/orders/{id} (order reconciliation)
 * - POST /v1/notifications/verify-webhook-signature (cryptographic webhook verification)
 * - POST /v2/payments/captures/{capture_id}/refund (full/partial refunds)
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EduPro_Gateway_PayPal extends EduPro_Payment_Gateway {

    public function get_id() {
        return 'paypal';
    }

    public function get_label() {
        return __( 'PayPal', 'edupro' );
    }

    public function get_description() {
        return __( 'Pay globally with your PayPal balance, UK/International cards, or PayPal Pay in 3.', 'edupro' );
    }

    public function get_region() {
        return __( 'International', 'edupro' );
    }

    public function get_badge() {
        return __( 'Global · PayPal', 'edupro' );
    }

    public function get_priority() {
        return 40;
    }

    public function get_supported_currencies() {
        return array( 'GBP', 'USD' );
    }

    public function is_test_mode() {
        return $this->read_sandbox_option( 'edupro_paypal_sandbox' );
    }

    public function is_enabled() {
        if ( get_option( 'edupro_paypal_enabled', '0' ) !== '1' ) {
            return false;
        }
        return $this->get_client_id() !== '' && $this->get_client_secret() !== '';
    }

    public function get_client_config() {
        return array(
            'client_id' => $this->get_client_id(),
            'sandbox'   => $this->is_test_mode(),
        );
    }

    protected function get_api_base() {
        return $this->is_test_mode()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function get_client_id() {
        return $this->get_trimmed_option( 'edupro_paypal_client_id' );
    }

    protected function get_client_secret() {
        return $this->get_trimmed_option( 'edupro_paypal_client_secret' );
    }

    protected function get_webhook_id() {
        return $this->get_trimmed_option( 'edupro_paypal_webhook_id' );
    }

    /**
     * Fetch and cache PayPal OAuth2 Bearer access token in a WordPress transient.
     *
     * @param bool $force_refresh
     * @return string|WP_Error
     */
    protected function get_access_token( $force_refresh = false ) {
        $client_id     = $this->get_client_id();
        $client_secret = $this->get_client_secret();

        if ( $client_id === '' || $client_secret === '' ) {
            return new WP_Error( 'paypal_config', __( 'PayPal REST Client ID and Secret are not configured.', 'edupro' ) );
        }

        $env           = $this->is_test_mode() ? 'sandbox' : 'live';
        $transient_key = 'edupro_pp_token_' . $env . '_' . substr( md5( $client_id ), 0, 12 );

        if ( ! $force_refresh ) {
            $cached = get_transient( $transient_key );
            if ( is_string( $cached ) && $cached !== '' ) {
                return $cached;
            }
        }

        $response = wp_remote_post(
            $this->get_api_base() . '/v1/oauth2/token',
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                    'Accept'        => 'application/json',
                ),
                'body'    => 'grant_type=client_credentials',
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
            $msg = isset( $body['error_description'] )
                ? sanitize_text_field( $body['error_description'] )
                : __( 'Failed to authenticate with PayPal OAuth2.', 'edupro' );
            return new WP_Error( 'paypal_oauth_error', $msg );
        }

        $token      = (string) $body['access_token'];
        $expires_in = max( 60, intval( $body['expires_in'] ?? 3600 ) - 120 );
        set_transient( $transient_key, $token, $expires_in );

        return $token;
    }

    /**
     * Build authenticated JSON headers for PayPal API calls.
     *
     * @param string $token
     * @param array  $extra
     * @return array
     */
    protected function get_api_headers( $token, $extra = array() ) {
        return array_merge(
            array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            $extra
        );
    }

    /**
     * Create a PayPal v2 Checkout Order and return approval URL.
     *
     * @param object  $order
     * @param WP_Post $course
     * @param WP_User $user
     * @return array|WP_Error
     */
    public function create_payment( $order, $course, $user ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'paypal_disabled', __( 'PayPal gateway is not enabled or configured.', 'edupro' ) );
        }

        $currency = strtoupper( (string) $order->currency );
        if ( ! $this->supports_currency( $currency ) ) {
            return new WP_Error( 'paypal_currency', sprintf( __( 'PayPal does not support %s for this checkout. Please switch to GBP or USD.', 'edupro' ), $currency ) );
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $return_url = $this->get_checkout_return_url(
            $order,
            array(
                'edupro_paypal_return' => '1',
                'order_id'             => $order->order_id,
            )
        );
        $cancel_url = $this->get_checkout_return_url(
            $order,
            array(
                'edupro_paypal_cancel' => '1',
                'order_id'             => $order->order_id,
            )
        );

        $payload = array(
            'intent'              => 'CAPTURE',
            'purchase_units'      => array(
                array(
                    'reference_id' => $order->order_id,
                    'custom_id'    => $order->order_id,
                    'description'  => substr( wp_strip_all_tags( $course->post_title ), 0, 127 ),
                    'amount'       => array(
                        'currency_code' => $currency,
                        'value'         => number_format( floatval( $order->amount ), 2, '.', '' ),
                    ),
                ),
            ),
            'application_context' => array(
                'brand_name'          => substr( get_bloginfo( 'name' ), 0, 127 ),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => $return_url,
                'cancel_url'          => $cancel_url,
            ),
        );

        $response = wp_remote_post(
            $this->get_api_base() . '/v2/checkout/orders',
            array(
                'headers' => $this->get_api_headers(
                    $token,
                    array( 'PayPal-Request-Id' => 'edupro_pp_' . $order->order_id )
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

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
            $msg = isset( $body['details'][0]['description'] )
                ? sanitize_text_field( $body['details'][0]['description'] )
                : ( isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'Failed to create PayPal order.', 'edupro' ) );
            return new WP_Error( 'paypal_create_error', $msg );
        }

        $approve_url = '';
        if ( ! empty( $body['links'] ) && is_array( $body['links'] ) ) {
            foreach ( $body['links'] as $link ) {
                if ( isset( $link['rel'] ) && in_array( $link['rel'], array( 'approve', 'payer-action' ), true ) && ! empty( $link['href'] ) ) {
                    $approve_url = esc_url_raw( $link['href'] );
                    break;
                }
            }
        }

        if ( $approve_url === '' ) {
            return new WP_Error( 'paypal_no_approve_url', __( 'PayPal did not return an approval URL.', 'edupro' ) );
        }

        return array(
            'gateway'          => $this->get_id(),
            'gateway_order_id' => sanitize_text_field( $body['id'] ),
            'paypal_order_id'  => sanitize_text_field( $body['id'] ),
            'approve_url'      => $approve_url,
            'redirect_url'     => $approve_url,
            'currency'         => $currency,
            'amount'           => round( floatval( $order->amount ), 2 ),
        );
    }

    /**
     * Extract and validate a PayPal capture object from a v2 order response.
     *
     * @param object $order
     * @param array  $pp_order
     * @return array|WP_Error
     */
    protected function extract_capture_verification( $order, $pp_order ) {
        $capture = $pp_order['purchase_units'][0]['payments']['captures'][0] ?? null;
        if ( ! is_array( $capture ) ) {
            return array(
                'verified'       => false,
                'status'         => 'pending',
                'transaction_id' => '',
                'amount_paid'    => 0.0,
                'currency'       => strtoupper( $order->currency ),
                'message'        => __( 'PayPal order has not been captured yet.', 'edupro' ),
                'raw'            => $pp_order,
            );
        }

        $cap_status    = strtoupper( (string) ( $capture['status'] ?? '' ) );
        $capture_id    = sanitize_text_field( (string) ( $capture['id'] ?? '' ) );
        $paid_currency = strtoupper( (string) ( $capture['amount']['currency_code'] ?? '' ) );
        $paid_amount   = floatval( $capture['amount']['value'] ?? 0 );

        if ( $cap_status === 'COMPLETED' ) {
            if ( $paid_currency !== strtoupper( $order->currency ) ) {
                return new WP_Error( 'paypal_currency_mismatch', __( 'Captured PayPal currency does not match order currency.', 'edupro' ) );
            }
            if ( ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                return new WP_Error( 'paypal_amount_mismatch', __( 'Captured PayPal amount is less than order total.', 'edupro' ) );
            }

            return array(
                'verified'       => true,
                'status'         => 'completed',
                'transaction_id' => $capture_id,
                'amount_paid'    => $paid_amount,
                'currency'       => $paid_currency,
                'message'        => __( 'PayPal payment captured and verified.', 'edupro' ),
                'raw'            => $pp_order,
            );
        }

        if ( in_array( $cap_status, array( 'DECLINED', 'FAILED' ), true ) ) {
            return array(
                'verified'       => false,
                'status'         => 'failed',
                'transaction_id' => $capture_id,
                'amount_paid'    => 0.0,
                'currency'       => $paid_currency,
                'message'        => __( 'PayPal capture was declined.', 'edupro' ),
                'raw'            => $pp_order,
            );
        }

        if ( in_array( $cap_status, array( 'REFUNDED', 'PARTIALLY_REFUNDED' ), true ) ) {
            return array(
                'verified'       => false,
                'status'         => 'refunded',
                'transaction_id' => $capture_id,
                'amount_paid'    => $paid_amount,
                'currency'       => $paid_currency,
                'message'        => __( 'PayPal payment was refunded.', 'edupro' ),
                'raw'            => $pp_order,
            );
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => $capture_id,
            'amount_paid'    => 0.0,
            'currency'       => $paid_currency,
            'message'        => sprintf( __( 'PayPal capture status: %s', 'edupro' ), $cap_status ),
            'raw'            => $pp_order,
        );
    }

    /**
     * Server-side PayPal capture: POST /v2/checkout/orders/{paypal_order_id}/capture.
     *
     * @param object $order
     * @param array  $payload Keys: paypal_order_id (or token)
     * @return array|WP_Error
     */
    public function verify_payment( $order, $payload = array() ) {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $paypal_order_id = ! empty( $payload['paypal_order_id'] )
            ? sanitize_text_field( (string) $payload['paypal_order_id'] )
            : sanitize_text_field( (string) $order->gateway_order_id );

        if ( $paypal_order_id === '' ) {
            return new WP_Error( 'paypal_missing_id', __( 'Missing PayPal Order ID for capture.', 'edupro' ) );
        }

        if ( ! empty( $order->gateway_order_id ) && $order->gateway_order_id !== $paypal_order_id ) {
            return new WP_Error( 'paypal_order_mismatch', __( 'PayPal Order ID does not match this order.', 'edupro' ) );
        }

        $response = wp_remote_post(
            $this->get_api_base() . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
            array(
                'headers' => $this->get_api_headers(
                    $token,
                    array( 'PayPal-Request-Id' => 'edupro_cap_' . $order->order_id )
                ),
                'body'    => '{}',
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // If already captured, inspect order state directly via reconcile_order()
        if ( $code === 422 && isset( $body['details'][0]['issue'] ) && $body['details'][0]['issue'] === 'ORDER_ALREADY_CAPTURED' ) {
            return $this->reconcile_order( $order );
        }

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            $msg = isset( $body['details'][0]['description'] )
                ? sanitize_text_field( $body['details'][0]['description'] )
                : ( isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'PayPal capture request failed.', 'edupro' ) );
            return new WP_Error( 'paypal_capture_error', $msg );
        }

        return $this->extract_capture_verification( $order, $body );
    }

    /**
     * Reconcile order via GET /v2/checkout/orders/{gateway_order_id}.
     * If buyer approved the order but return callback dropped before capture, captures server-side.
     *
     * @param object $order
     * @return array|WP_Error
     */
    public function reconcile_order( $order ) {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $paypal_order_id = sanitize_text_field( (string) $order->gateway_order_id );
        if ( $paypal_order_id === '' ) {
            return new WP_Error( 'paypal_no_order_id', __( 'Order has no PayPal Order ID to reconcile.', 'edupro' ) );
        }

        $response = wp_remote_get(
            $this->get_api_base() . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ),
            array(
                'headers' => $this->get_api_headers( $token ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            $msg = isset( $body['message'] )
                ? sanitize_text_field( $body['message'] )
                : __( 'Failed to fetch PayPal order details.', 'edupro' );
            return new WP_Error( 'paypal_reconcile_error', $msg );
        }

        $status = strtoupper( (string) ( $body['status'] ?? '' ) );

        if ( $status === 'COMPLETED' ) {
            return $this->extract_capture_verification( $order, $body );
        }

        if ( $status === 'APPROVED' ) {
            // Buyer approved on PayPal; capture server-side now
            return $this->verify_payment( $order, array( 'paypal_order_id' => $paypal_order_id ) );
        }

        if ( $status === 'VOIDED' ) {
            return array(
                'verified'       => false,
                'status'         => 'failed',
                'transaction_id' => '',
                'amount_paid'    => 0.0,
                'currency'       => strtoupper( $order->currency ),
                'message'        => __( 'PayPal order was voided.', 'edupro' ),
                'raw'            => $body,
            );
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => '',
            'amount_paid'    => 0.0,
            'currency'       => strtoupper( $order->currency ),
            'message'        => sprintf( __( 'PayPal order status: %s', 'edupro' ), $status ),
            'raw'            => $body,
        );
    }

    /**
     * Handle PayPal Webhooks with server-side signature verification via
     * POST /v1/notifications/verify-webhook-signature.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $webhook_id = $this->get_webhook_id();
        $raw_body   = $request->get_body();

        if ( $webhook_id === '' ) {
            return new WP_REST_Response( array( 'error' => 'PayPal Webhook ID is not configured.' ), 500 );
        }

        if ( $raw_body === '' ) {
            return new WP_REST_Response( array( 'error' => 'Empty PayPal webhook body.' ), 400 );
        }

        $event = json_decode( $raw_body, true );
        if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid PayPal webhook JSON.' ), 400 );
        }

        $auth_algo         = trim( (string) $request->get_header( 'paypal-auth-algo' ) );
        $cert_url          = trim( (string) $request->get_header( 'paypal-cert-url' ) );
        $transmission_id   = trim( (string) $request->get_header( 'paypal-transmission-id' ) );
        $transmission_sig  = trim( (string) $request->get_header( 'paypal-transmission-sig' ) );
        $transmission_time = trim( (string) $request->get_header( 'paypal-transmission-time' ) );

        if ( $auth_algo === '' || $cert_url === '' || $transmission_id === '' || $transmission_sig === '' || $transmission_time === '' ) {
            return new WP_REST_Response( array( 'error' => 'Missing required PAYPAL-TRANSMISSION-* headers.' ), 400 );
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return new WP_REST_Response( array( 'error' => 'Unable to authenticate with PayPal to verify webhook.' ), 500 );
        }

        $verify_resp = wp_remote_post(
            $this->get_api_base() . '/v1/notifications/verify-webhook-signature',
            array(
                'headers' => $this->get_api_headers( $token ),
                'body'    => wp_json_encode( array(
                    'auth_algo'         => $auth_algo,
                    'cert_url'          => $cert_url,
                    'transmission_id'   => $transmission_id,
                    'transmission_sig'  => $transmission_sig,
                    'transmission_time' => $transmission_time,
                    'webhook_id'        => $webhook_id,
                    'webhook_event'     => $event,
                ) ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $verify_resp ) ) {
            return new WP_REST_Response( array( 'error' => 'PayPal signature verification request failed.' ), 500 );
        }

        $verify_body = json_decode( wp_remote_retrieve_body( $verify_resp ), true );
        if ( strtoupper( (string) ( $verify_body['verification_status'] ?? '' ) ) !== 'SUCCESS' ) {
            edupro_log_payment_event( '', $this->get_id(), 'webhook_invalid_signature', 'rejected', 'PayPal webhook verification returned FAILURE' );
            return new WP_REST_Response( array( 'error' => 'PayPal webhook signature verification failed.' ), 400 );
        }

        $event_type = sanitize_text_field( $event['event_type'] );
        $event_id   = ! empty( $event['id'] ) ? 'pp_' . sanitize_text_field( $event['id'] ) : 'pp_' . md5( $raw_body );

        if ( edupro_is_webhook_event_processed( $event_id ) ) {
            return new WP_REST_Response( array( 'status' => 'duplicate_ignored' ), 200 );
        }

        $resource        = $event['resource'] ?? array();
        $custom_order_id = sanitize_text_field( (string) ( $resource['custom_id'] ?? '' ) );
        $related_pp_id   = sanitize_text_field( (string) ( $resource['supplementary_data']['related_ids']['order_id'] ?? '' ) );
        $capture_id      = sanitize_text_field( (string) ( $resource['id'] ?? '' ) );

        $order = null;
        if ( $custom_order_id !== '' ) {
            $order = edupro_get_order( $custom_order_id );
        }
        if ( ! $order && $related_pp_id !== '' ) {
            $order = edupro_get_order_by_gateway_order_id( $related_pp_id, $this->get_id() );
        }
        if ( ! $order && $capture_id !== '' ) {
            $order = edupro_get_order_by_transaction( $capture_id );
        }

        if ( ! $order ) {
            edupro_log_payment_event( $custom_order_id, $this->get_id(), $event_type, 'not_found', 'Matching order not found', $event_id );
            return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );
        }

        if ( $event_type === 'PAYMENT.CAPTURE.COMPLETED' ) {
            $paid_currency = strtoupper( (string) ( $resource['amount']['currency_code'] ?? '' ) );
            $paid_amount   = floatval( $resource['amount']['value'] ?? 0 );

            if ( $paid_currency !== strtoupper( $order->currency ) || ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                edupro_mark_order_failed( $order->order_id, 'PayPal webhook amount or currency mismatch' );
                edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'mismatch', 'Currency/amount mismatch', $event_id );
                return new WP_REST_Response( array( 'error' => 'Amount or currency mismatch.' ), 400 );
            }

            edupro_complete_verified_order(
                $order->order_id,
                $capture_id,
                'webhook_paypal',
                array( 'event_id' => $event_id )
            );
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Completed via PayPal webhook', $event_id );

        } elseif ( $event_type === 'PAYMENT.CAPTURE.DENIED' ) {
            if ( in_array( $order->status, array( 'pending', 'processing' ), true ) ) {
                edupro_mark_order_failed( $order->order_id, 'PayPal capture denied' );
            }
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Capture denied', $event_id );

        } elseif ( $event_type === 'PAYMENT.CAPTURE.REFUNDED' ) {
            $refund_id     = $capture_id;
            $refund_amount = floatval( $resource['amount']['value'] ?? $order->amount );
            edupro_record_order_refund( $order->order_id, $refund_id, $refund_amount );
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Refunded via PayPal webhook', $event_id );

        } else {
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'ignored', 'Unhandled PayPal event', $event_id );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Issue a refund via POST /v2/payments/captures/{transaction_id}/refund.
     *
     * @param object     $order
     * @param float|null $amount
     * @param string     $reason
     * @return array|WP_Error
     */
    public function refund_payment( $order, $amount = null, $reason = '' ) {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        if ( empty( $order->transaction_id ) ) {
            return new WP_Error( 'paypal_no_capture_id', __( 'Order is missing a PayPal capture ID for refund.', 'edupro' ) );
        }

        $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
        if ( $refund_amount <= 0 || $refund_amount > floatval( $order->amount ) + 0.01 ) {
            return new WP_Error( 'paypal_refund_amount', __( 'Invalid refund amount.', 'edupro' ) );
        }

        $payload = array(
            'amount'        => array(
                'value'         => number_format( $refund_amount, 2, '.', '' ),
                'currency_code' => strtoupper( (string) $order->currency ),
            ),
            'note_to_payer' => substr( sanitize_text_field( $reason !== '' ? $reason : 'Course refund from EduPro LMS' ), 0, 255 ),
        );

        $response = wp_remote_post(
            $this->get_api_base() . '/v2/payments/captures/' . rawurlencode( $order->transaction_id ) . '/refund',
            array(
                'headers' => $this->get_api_headers(
                    $token,
                    array( 'PayPal-Request-Id' => 'edupro_pp_ref_' . $order->order_id . '_' . time() )
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

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
            $msg = isset( $body['details'][0]['description'] )
                ? sanitize_text_field( $body['details'][0]['description'] )
                : ( isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'PayPal refund failed.', 'edupro' ) );
            return new WP_Error( 'paypal_refund_error', $msg );
        }

        return array(
            'refund_id' => sanitize_text_field( $body['id'] ),
            'amount'    => $refund_amount,
            'status'    => strtolower( (string) ( $body['status'] ?? 'completed' ) ),
            'raw'       => $body,
        );
    }
}
