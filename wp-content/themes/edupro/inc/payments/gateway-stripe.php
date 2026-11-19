<?php
/**
 * EduPro LMS - Stripe Payment Gateway (UK & International)
 *
 * Implements Stripe PaymentIntents & Refunds REST API:
 * - POST /v1/payment_intents with automatic_payment_methods[enabled]=true
 *   (supports UK Cards, Apple Pay, Google Pay, Klarna, Clearpay configured in Stripe Dashboard)
 * - GET  /v1/payment_intents/{id} for authoritative server-side verification & reconciliation
 * - POST /v1/refunds for full & partial refunds
 * - Webhook Stripe-Signature verification (`t=`, `v1=`, 300s tolerance, hash_equals)
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EduPro_Gateway_Stripe extends EduPro_Payment_Gateway {

    const API_BASE = 'https://api.stripe.com/v1';

    public function get_id() {
        return 'stripe';
    }

    public function get_label() {
        return __( 'Cards, Apple Pay & Klarna (Stripe)', 'edupro' );
    }

    public function get_description() {
        return __( 'Pay securely with Visa, Mastercard, Amex, Apple Pay, Google Pay, Klarna, or Clearpay.', 'edupro' );
    }

    public function get_region() {
        return __( 'UK & International', 'edupro' );
    }

    public function get_badge() {
        return __( 'UK · Apple Pay · Klarna', 'edupro' );
    }

    public function get_priority() {
        return 30;
    }

    public function get_supported_currencies() {
        return array( 'GBP', 'USD', 'INR' );
    }

    public function is_test_mode() {
        return $this->read_sandbox_option( 'edupro_stripe_sandbox', 'edupro_stripe_test_mode' );
    }

    public function is_enabled() {
        if ( get_option( 'edupro_stripe_enabled', '0' ) !== '1' ) {
            return false;
        }
        return $this->get_publishable_key() !== '' && $this->get_secret_key() !== '';
    }

    public function get_client_config() {
        return array(
            'publishable_key' => $this->get_publishable_key(),
            'sdk_url'         => 'https://js.stripe.com/v3/',
        );
    }

    protected function get_publishable_key() {
        return $this->get_trimmed_option( 'edupro_stripe_publishable_key' );
    }

    protected function get_secret_key() {
        return $this->get_trimmed_option( 'edupro_stripe_secret_key' );
    }

    protected function get_webhook_secret() {
        return $this->get_trimmed_option( 'edupro_stripe_webhook_secret' );
    }

    protected function get_headers( $extra = array() ) {
        return array_merge(
            array(
                'Authorization' => 'Bearer ' . $this->get_secret_key(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            $extra
        );
    }

    /**
     * Create a Stripe PaymentIntent via POST /v1/payment_intents.
     *
     * @param object  $order
     * @param WP_Post $course
     * @param WP_User $user
     * @return array|WP_Error
     */
    public function create_payment( $order, $course, $user ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'stripe_disabled', __( 'Stripe gateway is not enabled or configured.', 'edupro' ) );
        }

        $currency     = strtolower( (string) $order->currency );
        $amount_minor = $this->to_minor_units( $order->amount );

        if ( $amount_minor < 50 ) {
            return new WP_Error( 'stripe_min_amount', __( 'Order amount is below Stripe minimum charge.', 'edupro' ) );
        }

        $return_url = $this->get_checkout_return_url(
            $order,
            array(
                'edupro_stripe_return' => '1',
                'order_id'             => $order->order_id,
            )
        );

        $fields = array(
            'amount'                             => $amount_minor,
            'currency'                           => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description'                        => substr( sprintf( '%s Course: %s (%s)', get_bloginfo( 'name' ) ?: 'Academy', wp_strip_all_tags( $course->post_title ), $order->order_id ), 0, 250 ),
            'metadata[order_id]'                 => $order->order_id,
            'metadata[course_id]'                => (string) absint( $course->ID ),
            'metadata[user_id]'                  => (string) absint( $user->ID ),
            'metadata[source]'                   => 'edupro_lms',
        );

        if ( ! empty( $user->user_email ) ) {
            $fields['receipt_email'] = sanitize_email( $user->user_email );
        }

        $response = wp_remote_post(
            self::API_BASE . '/payment_intents',
            array(
                'headers' => $this->get_headers(
                    array( 'Idempotency-Key' => 'edupro_pi_' . $order->order_id )
                ),
                'body'    => http_build_query( $fields, '', '&' ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) || empty( $body['client_secret'] ) ) {
            $msg = isset( $body['error']['message'] )
                ? sanitize_text_field( $body['error']['message'] )
                : __( 'Stripe PaymentIntent creation failed.', 'edupro' );
            return new WP_Error( 'stripe_api_error', $msg );
        }

        return array(
            'gateway'           => $this->get_id(),
            'gateway_order_id'  => sanitize_text_field( $body['id'] ),
            'payment_intent_id' => sanitize_text_field( $body['id'] ),
            'client_secret'     => sanitize_text_field( $body['client_secret'] ),
            'publishable_key'   => $this->get_publishable_key(),
            'return_url'        => $return_url,
            'amount_minor'      => $amount_minor,
            'currency'          => strtoupper( $currency ),
        );
    }

    /**
     * Authoritative server-side PaymentIntent verification via GET /v1/payment_intents/{id}.
     *
     * @param object $order
     * @param array  $payload Optional: ['payment_intent_id' => 'pi_...']
     * @return array|WP_Error
     */
    public function verify_payment( $order, $payload = array() ) {
        if ( $this->get_secret_key() === '' ) {
            return new WP_Error( 'stripe_config', __( 'Stripe secret key is not configured.', 'edupro' ) );
        }

        $pi_id = ! empty( $payload['payment_intent_id'] )
            ? sanitize_text_field( (string) $payload['payment_intent_id'] )
            : sanitize_text_field( (string) $order->gateway_order_id );

        if ( $pi_id === '' ) {
            return new WP_Error( 'stripe_missing_pi', __( 'Missing Stripe PaymentIntent ID.', 'edupro' ) );
        }

        if ( ! empty( $order->gateway_order_id ) && $order->gateway_order_id !== $pi_id ) {
            return new WP_Error( 'stripe_pi_mismatch', __( 'PaymentIntent ID does not match this order.', 'edupro' ) );
        }

        $response = wp_remote_get(
            self::API_BASE . '/payment_intents/' . rawurlencode( $pi_id ),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_secret_key(),
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['id'] ) ) {
            $msg = isset( $body['error']['message'] )
                ? sanitize_text_field( $body['error']['message'] )
                : __( 'Could not retrieve Stripe PaymentIntent.', 'edupro' );
            return new WP_Error( 'stripe_verify_error', $msg );
        }

        $status        = strtolower( (string) ( $body['status'] ?? '' ) );
        $paid_currency = strtoupper( (string) ( $body['currency'] ?? '' ) );
        $paid_amount   = $this->from_minor_units( $body['amount_received'] ?? 0 );

        if ( $status === 'succeeded' ) {
            if ( $paid_currency !== strtoupper( $order->currency ) ) {
                return new WP_Error( 'stripe_currency_mismatch', __( 'Stripe payment currency does not match order currency.', 'edupro' ) );
            }
            if ( ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                return new WP_Error( 'stripe_amount_mismatch', __( 'Stripe amount_received is less than the order amount.', 'edupro' ) );
            }

            return array(
                'verified'       => true,
                'status'         => 'completed',
                'transaction_id' => sanitize_text_field( $body['id'] ),
                'amount_paid'    => $paid_amount,
                'currency'       => $paid_currency,
                'message'        => __( 'Stripe payment succeeded and verified.', 'edupro' ),
                'raw'            => $body,
            );
        }

        if ( in_array( $status, array( 'canceled', 'requires_payment_method' ), true ) ) {
            $err = isset( $body['last_payment_error']['message'] )
                ? sanitize_text_field( $body['last_payment_error']['message'] )
                : sprintf( __( 'Stripe payment not completed (status: %s).', 'edupro' ), $status );
            return array(
                'verified'       => false,
                'status'         => $status === 'canceled' ? 'failed' : 'pending',
                'transaction_id' => sanitize_text_field( $body['id'] ),
                'amount_paid'    => 0.0,
                'currency'       => $paid_currency,
                'message'        => $err,
                'raw'            => $body,
            );
        }

        return array(
            'verified'       => false,
            'status'         => 'pending',
            'transaction_id' => sanitize_text_field( $body['id'] ),
            'amount_paid'    => 0.0,
            'currency'       => $paid_currency,
            'message'        => sprintf( __( 'Stripe payment status: %s', 'edupro' ), $status ),
            'raw'            => $body,
        );
    }

    /**
     * Reconcile order directly against Stripe PaymentIntents API.
     *
     * @param object $order
     * @return array|WP_Error
     */
    public function reconcile_order( $order ) {
        return $this->verify_payment( $order );
    }

    /**
     * Handle Stripe Webhooks with strict Stripe-Signature verification (t= / v1=, 300s window, hash_equals).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $webhook_secret = $this->get_webhook_secret();
        $raw_body       = $request->get_body();
        $sig_header     = trim( (string) $request->get_header( 'stripe-signature' ) );

        if ( $webhook_secret === '' ) {
            return new WP_REST_Response( array( 'error' => 'Stripe webhook secret is not configured.' ), 500 );
        }

        if ( $raw_body === '' || $sig_header === '' ) {
            return new WP_REST_Response( array( 'error' => 'Missing Stripe-Signature header or payload.' ), 400 );
        }

        $timestamp  = '';
        $signatures = array();
        foreach ( explode( ',', $sig_header ) as $part ) {
            $kv = explode( '=', trim( $part ), 2 );
            if ( count( $kv ) === 2 ) {
                if ( $kv[0] === 't' ) {
                    $timestamp = $kv[1];
                } elseif ( $kv[0] === 'v1' ) {
                    $signatures[] = $kv[1];
                }
            }
        }

        if ( ! ctype_digit( $timestamp ) || empty( $signatures ) ) {
            return new WP_REST_Response( array( 'error' => 'Malformed Stripe-Signature header.' ), 400 );
        }

        // Reject timestamps outside 300-second tolerance window
        if ( abs( time() - intval( $timestamp ) ) > 300 ) {
            return new WP_REST_Response( array( 'error' => 'Stripe webhook timestamp outside 300s tolerance.' ), 400 );
        }

        $expected_sig = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $webhook_secret );
        $sig_valid    = false;
        foreach ( $signatures as $candidate ) {
            if ( hash_equals( $expected_sig, $candidate ) ) {
                $sig_valid = true;
                break;
            }
        }

        if ( ! $sig_valid ) {
            edupro_log_payment_event( '', $this->get_id(), 'webhook_invalid_signature', 'rejected', 'Stripe signature mismatch' );
            return new WP_REST_Response( array( 'error' => 'Invalid Stripe webhook signature.' ), 400 );
        }

        $event = json_decode( $raw_body, true );
        if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['object'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid Stripe event payload.' ), 400 );
        }

        $event_type = sanitize_text_field( $event['type'] );
        $event_id   = ! empty( $event['id'] ) ? 'stripe_' . sanitize_text_field( $event['id'] ) : 'stripe_' . md5( $raw_body );
        $object     = $event['data']['object'];

        if ( edupro_is_webhook_event_processed( $event_id ) ) {
            return new WP_REST_Response( array( 'status' => 'duplicate_ignored' ), 200 );
        }

        if ( $event_type === 'payment_intent.succeeded' ) {
            $pi_id         = sanitize_text_field( (string) ( $object['id'] ?? '' ) );
            $meta_order_id = sanitize_text_field( (string) ( $object['metadata']['order_id'] ?? '' ) );
            $order         = $meta_order_id !== '' ? edupro_get_order( $meta_order_id ) : edupro_get_order_by_gateway_order_id( $pi_id, $this->get_id() );

            if ( ! $order ) {
                edupro_log_payment_event( $meta_order_id, $this->get_id(), $event_type, 'not_found', 'Order not found for ' . $pi_id, $event_id );
                return new WP_REST_Response( array( 'status' => 'order_not_found' ), 200 );
            }

            $paid_currency = strtoupper( (string) ( $object['currency'] ?? '' ) );
            $paid_amount   = $this->from_minor_units( $object['amount_received'] ?? 0 );

            if ( $paid_currency !== strtoupper( $order->currency ) || ! $this->amounts_match( $paid_amount, $order->amount ) ) {
                edupro_mark_order_failed( $order->order_id, 'Stripe webhook amount or currency mismatch' );
                edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'mismatch', 'Currency/amount mismatch', $event_id );
                return new WP_REST_Response( array( 'error' => 'Amount or currency mismatch.' ), 400 );
            }

            edupro_complete_verified_order(
                $order->order_id,
                $pi_id,
                'webhook_stripe',
                array( 'event_id' => $event_id )
            );
            edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Completed via Stripe webhook', $event_id );

        } elseif ( $event_type === 'payment_intent.payment_failed' || $event_type === 'payment_intent.canceled' ) {
            $pi_id         = sanitize_text_field( (string) ( $object['id'] ?? '' ) );
            $meta_order_id = sanitize_text_field( (string) ( $object['metadata']['order_id'] ?? '' ) );
            $order         = $meta_order_id !== '' ? edupro_get_order( $meta_order_id ) : edupro_get_order_by_gateway_order_id( $pi_id, $this->get_id() );

            if ( $order && in_array( $order->status, array( 'pending', 'processing' ), true ) ) {
                $reason = sanitize_text_field( $object['last_payment_error']['message'] ?? $event_type );
                edupro_mark_order_failed( $order->order_id, $reason );
                edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', $reason, $event_id );
            }

        } elseif ( $event_type === 'charge.refunded' ) {
            $pi_id = sanitize_text_field( (string) ( $object['payment_intent'] ?? '' ) );
            $order = $pi_id !== '' ? edupro_get_order_by_gateway_order_id( $pi_id, $this->get_id() ) : null;
            if ( ! $order && $pi_id !== '' ) {
                $order = edupro_get_order_by_transaction( $pi_id );
            }

            if ( $order ) {
                $refund_id     = sanitize_text_field( (string) ( $object['refunds']['data'][0]['id'] ?? '' ) );
                $refund_amount = $this->from_minor_units( $object['amount_refunded'] ?? $this->to_minor_units( $order->amount ) );
                edupro_record_order_refund( $order->order_id, $refund_id, $refund_amount );
                edupro_log_payment_event( $order->order_id, $this->get_id(), $event_type, 'processed', 'Refunded via Stripe webhook: ' . $refund_id, $event_id );
            }

        } else {
            edupro_log_payment_event( '', $this->get_id(), $event_type, 'ignored', 'Unhandled Stripe event', $event_id );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Issue a refund via POST /v1/refunds.
     *
     * @param object     $order
     * @param float|null $amount
     * @param string     $reason
     * @return array|WP_Error
     */
    public function refund_payment( $order, $amount = null, $reason = '' ) {
        if ( $this->get_secret_key() === '' ) {
            return new WP_Error( 'stripe_config', __( 'Stripe secret key is not configured.', 'edupro' ) );
        }

        $pi_id = ! empty( $order->gateway_order_id ) ? $order->gateway_order_id : $order->transaction_id;
        if ( empty( $pi_id ) ) {
            return new WP_Error( 'stripe_no_pi', __( 'Order has no Stripe PaymentIntent ID to refund.', 'edupro' ) );
        }

        $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
        if ( $refund_amount <= 0 || $refund_amount > floatval( $order->amount ) + 0.01 ) {
            return new WP_Error( 'stripe_refund_amount', __( 'Invalid refund amount.', 'edupro' ) );
        }

        $fields = array(
            'payment_intent'     => $pi_id,
            'amount'             => $this->to_minor_units( $refund_amount ),
            'reason'             => 'requested_by_customer',
            'metadata[order_id]' => $order->order_id,
            'metadata[note]'     => substr( sanitize_text_field( $reason !== '' ? $reason : 'Admin refund' ), 0, 200 ),
        );

        $response = wp_remote_post(
            self::API_BASE . '/refunds',
            array(
                'headers' => $this->get_headers(
                    array( 'Idempotency-Key' => 'edupro_ref_' . $order->order_id . '_' . $this->to_minor_units( $refund_amount ) )
                ),
                'body'    => http_build_query( $fields, '', '&' ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
            $msg = isset( $body['error']['message'] )
                ? sanitize_text_field( $body['error']['message'] )
                : __( 'Stripe refund request failed.', 'edupro' );
            return new WP_Error( 'stripe_refund_error', $msg );
        }

        return array(
            'refund_id' => sanitize_text_field( $body['id'] ),
            'amount'    => $refund_amount,
            'status'    => strtolower( (string) ( $body['status'] ?? 'succeeded' ) ),
            'raw'       => $body,
        );
    }
}
