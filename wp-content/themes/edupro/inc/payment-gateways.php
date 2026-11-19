<?php
/**
 * EduPro LMS - Unified Multi-Gateway Payment Orchestrator (v1.1.0)
 *
 * Modular, independent payment gateway architecture (NO WooCommerce):
 * - Cashfree (Primary India, priority 10, INR)
 * - Razorpay (Secondary/Backup India, priority 20, INR/GBP/USD)
 * - Stripe   (UK & International, priority 30, GBP/USD/INR)
 * - PayPal   (International, priority 40, GBP/USD)
 * - Bank Transfer (UK & India manual approval, priority 50, GBP/INR/USD)
 *
 * Strictly enforces server-side payment verification, cryptographic webhook validation,
 * replay deduplication, atomic order completion (preventing double-enrollment /
 * double-coupon usage / double-emails), reconciliation, refunds, and full Admin UI.
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// 1. LOAD MODULAR GATEWAY CLASSES & REGISTRY
// ============================================================

require_once __DIR__ . '/payments/class-gateway-interface.php';
require_once __DIR__ . '/payments/gateway-cashfree.php';
require_once __DIR__ . '/payments/gateway-razorpay.php';
require_once __DIR__ . '/payments/gateway-stripe.php';
require_once __DIR__ . '/payments/gateway-paypal.php';
require_once __DIR__ . '/payments/gateway-bank-transfer.php';

if ( ! function_exists( 'edupro_get_registered_gateways' ) ) :
/**
 * Return all registered payment gateway instances sorted by priority ascending.
 *
 * @return EduPro_Payment_Gateway[] Associative array keyed by gateway ID.
 */
function edupro_get_registered_gateways() {
    static $gateways = null;

    if ( $gateways === null ) {
        $instances = array(
            new EduPro_Gateway_Cashfree(),
            new EduPro_Gateway_Razorpay(),
            new EduPro_Gateway_Stripe(),
            new EduPro_Gateway_PayPal(),
            new EduPro_Gateway_Bank_Transfer(),
        );

        /**
         * Allow third-party extensions to register additional EduPro_Payment_Gateway implementations.
         *
         * @param EduPro_Payment_Gateway[] $instances
         */
        $instances = apply_filters( 'edupro_registered_payment_gateways', $instances );

        usort( $instances, function ( $a, $b ) {
            return $a->get_priority() <=> $b->get_priority();
        } );

        $gateways = array();
        foreach ( $instances as $gw ) {
            if ( $gw instanceof EduPro_Payment_Gateway ) {
                $gateways[ $gw->get_id() ] = $gw;
            }
        }
    }

    return $gateways;
}
endif;

if ( ! function_exists( 'edupro_get_gateway' ) ) :
/**
 * Fetch a single registered gateway instance by ID.
 *
 * @param string $id
 * @return EduPro_Payment_Gateway|null
 */
function edupro_get_gateway( $id ) {
    $id       = sanitize_key( (string) $id );
    $gateways = edupro_get_registered_gateways();
    return isset( $gateways[ $id ] ) ? $gateways[ $id ] : null;
}
endif;

if ( ! function_exists( 'edupro_get_enabled_gateways' ) ) :
/**
 * Return public descriptors for enabled gateways, optionally filtered by currency,
 * sorted by priority (cashfree=10, razorpay=20, stripe=30, paypal=40, bank_transfer=50).
 *
 * Each entry contains: id, label, description, region, badge, priority, currencies, sandbox, client_config.
 *
 * @param string $currency Optional ISO-4217 currency filter (e.g. 'INR', 'GBP', 'USD').
 * @return array
 */
function edupro_get_enabled_gateways( $currency = '' ) {
    $currency = strtoupper( sanitize_text_field( (string) $currency ) );
    $enabled  = array();

    foreach ( edupro_get_registered_gateways() as $gw ) {
        if ( ! $gw->is_enabled() ) {
            continue;
        }
        if ( $currency !== '' && ! $gw->supports_currency( $currency ) ) {
            continue;
        }
        $enabled[ $gw->get_id() ] = $gw->to_public_array();
    }

    return $enabled;
}
endif;

// ============================================================
// 2. DATABASE SCHEMA (wp_edupro_orders & wp_edupro_payment_logs)
// ============================================================

define( 'EDUPRO_PAYMENTS_DB_VERSION', '2.0.0' );

if ( ! function_exists( 'edupro_create_orders_table' ) ) :
/**
 * Create / upgrade wp_edupro_orders and wp_edupro_payment_logs tables via dbDelta.
 */
function edupro_create_orders_table() {
    global $wpdb;

    $orders_table    = $wpdb->prefix . 'edupro_orders';
    $logs_table      = $wpdb->prefix . 'edupro_payment_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_orders = "CREATE TABLE {$orders_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id varchar(100) NOT NULL,
        idempotency_key varchar(128) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        course_id bigint(20) unsigned NOT NULL DEFAULT 0,
        currency varchar(10) NOT NULL DEFAULT 'INR',
        subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
        discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        coupon_code varchar(64) NOT NULL DEFAULT '',
        tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        gateway varchar(50) NOT NULL DEFAULT '',
        gateway_order_id varchar(255) NOT NULL DEFAULT '',
        transaction_id varchar(255) NOT NULL DEFAULT '',
        status varchar(25) NOT NULL DEFAULT 'pending',
        refund_id varchar(255) NOT NULL DEFAULT '',
        refund_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        verified_at datetime DEFAULT NULL,
        verified_via varchar(60) NOT NULL DEFAULT '',
        failure_reason text,
        meta_json longtext,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY order_id (order_id),
        KEY idempotency_key (idempotency_key),
        KEY user_id (user_id),
        KEY course_id (course_id),
        KEY status (status),
        KEY gateway (gateway),
        KEY gateway_order_id (gateway_order_id(191)),
        KEY transaction_id (transaction_id(191))
    ) {$charset_collate};";

    $sql_logs = "CREATE TABLE {$logs_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id varchar(100) NOT NULL DEFAULT '',
        gateway varchar(50) NOT NULL DEFAULT '',
        event_type varchar(100) NOT NULL DEFAULT '',
        event_id varchar(191) DEFAULT NULL,
        status varchar(40) NOT NULL DEFAULT '',
        payload_summary text,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY event_id (event_id),
        KEY order_id (order_id),
        KEY gateway (gateway),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_orders );
    dbDelta( $sql_logs );

    update_option( 'edupro_orders_table_version', EDUPRO_PAYMENTS_DB_VERSION );
}
endif;
add_action( 'after_switch_theme', 'edupro_create_orders_table' );

/**
 * Automatically upgrade database tables when EDUPRO_PAYMENTS_DB_VERSION changes.
 */
function edupro_maybe_create_orders_table() {
    if ( get_option( 'edupro_orders_table_version' ) !== EDUPRO_PAYMENTS_DB_VERSION ) {
        edupro_create_orders_table();
    }
}
add_action( 'init', 'edupro_maybe_create_orders_table', 5 );

// ============================================================
// 3. SHARED HELPERS: URLs, NONCE, PRICING FALLBACK, LOGGING & ORDER CRUD
// ============================================================

if ( ! function_exists( 'edupro_get_dashboard_url' ) ) :
function edupro_get_dashboard_url() {
    $page_id = absint( get_option( 'edupro_dashboard_page_id', 0 ) );
    if ( $page_id ) {
        return get_permalink( $page_id );
    }
    $page = get_page_by_path( 'dashboard' );
    return $page ? get_permalink( $page->ID ) : home_url( '/dashboard/' );
}
endif;

if ( ! function_exists( 'edupro_get_checkout_url' ) ) :
function edupro_get_checkout_url( $course_id = 0 ) {
    $page_id = absint( get_option( 'edupro_checkout_page_id', 0 ) );
    if ( $page_id ) {
        $url = get_permalink( $page_id );
    } else {
        $page = get_page_by_path( 'checkout' );
        $url  = $page ? get_permalink( $page->ID ) : home_url( '/checkout/' );
    }

    if ( $course_id ) {
        $url = add_query_arg( 'course_id', absint( $course_id ), $url );
    }

    return $url;
}
endif;

if ( ! function_exists( 'edupro_verify_payment_nonce' ) ) :
/**
 * Verify AJAX payment nonce — accepts both 'edupro_nonce' and 'edupro_payment_nonce'.
 *
 * @return bool
 */
function edupro_verify_payment_nonce() {
    $nonce = '';
    if ( isset( $_POST['nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
    } elseif ( isset( $_POST['security'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
    }

    if ( $nonce === '' ) {
        return false;
    }

    return (bool) ( wp_verify_nonce( $nonce, 'edupro_nonce' ) || wp_verify_nonce( $nonce, 'edupro_payment_nonce' ) );
}
endif;

if ( ! function_exists( 'edupro_is_course_free' ) ) :
function edupro_is_course_free( $course_id ) {
    $course_id = absint( $course_id );
    if ( ! $course_id ) {
        return false;
    }
    if ( get_post_meta( $course_id, '_edupro_is_free', true ) === '1'
        || get_post_meta( $course_id, '_edupro_course_free', true ) === '1' ) {
        return true;
    }
    if ( function_exists( 'edupro_get_course_price_data' ) ) {
        $pd = edupro_get_course_price_data( $course_id );
        return ! empty( $pd['is_free'] ) || floatval( $pd['effective'] ?? 0 ) <= 0;
    }
    return edupro_get_course_effective_price( $course_id ) <= 0;
}
endif;

if ( ! function_exists( 'edupro_get_course_effective_price' ) ) :
function edupro_get_course_effective_price( $course_id, $currency = '' ) {
    $course_id = absint( $course_id );
    if ( ! $course_id ) {
        return 0.0;
    }

    if ( function_exists( 'edupro_get_course_price_data' ) ) {
        $pd = edupro_get_course_price_data( $course_id, $currency );
        return ! empty( $pd['is_free'] ) ? 0.0 : round( floatval( $pd['effective'] ?? 0 ), 2 );
    }

    if ( get_post_meta( $course_id, '_edupro_is_free', true ) === '1'
        || get_post_meta( $course_id, '_edupro_course_free', true ) === '1' ) {
        return 0.0;
    }

    $sale_price    = get_post_meta( $course_id, '_edupro_sale_price', true );
    $sale_end_date = get_post_meta( $course_id, '_edupro_sale_end_date', true );
    $regular_price = get_post_meta( $course_id, '_edupro_regular_price', true );
    if ( $regular_price === '' ) {
        $regular_price = get_post_meta( $course_id, '_edupro_course_price', true );
    }

    if ( $sale_price !== '' && is_numeric( $sale_price ) && floatval( $sale_price ) >= 0 ) {
        if ( empty( $sale_end_date ) || current_time( 'Y-m-d' ) <= $sale_end_date ) {
            return round( floatval( $sale_price ), 2 );
        }
    }

    return round( floatval( $regular_price ), 2 );
}
endif;

/**
 * Format an amount using currency.php / helpers.php with multi-currency fallback.
 *
 * @param float  $amount
 * @param string $currency
 * @return string
 */
function edupro_payment_format_amount( $amount, $currency = '' ) {
    $currency = strtoupper( sanitize_text_field( (string) $currency ) );
    if ( function_exists( 'edupro_format_price' ) ) {
        $rf = new ReflectionFunction( 'edupro_format_price' );
        if ( $rf->getNumberOfParameters() >= 2 ) {
            return edupro_format_price( $amount, $currency );
        }
    }
    $symbols = array( 'INR' => '₹', 'GBP' => '£', 'USD' => '$' );
    $sym     = $symbols[ $currency ] ?? '₹';
    return $sym . number_format( floatval( $amount ), 2, '.', ',' );
}

/**
 * Check if a webhook event_id has already been recorded in wp_edupro_payment_logs.
 *
 * @param string $event_id
 * @return bool
 */
function edupro_is_webhook_event_processed( $event_id ) {
    global $wpdb;
    $event_id = sanitize_text_field( (string) $event_id );
    if ( $event_id === '' ) {
        return false;
    }

    $found = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}edupro_payment_logs WHERE event_id = %s LIMIT 1",
            $event_id
        )
    );

    return ! empty( $found );
}

/**
 * Write an audit/webhook log entry into wp_edupro_payment_logs.
 *
 * @param string      $order_id
 * @param string      $gateway
 * @param string      $event_type
 * @param string      $status
 * @param string      $payload_summary
 * @param string|null $event_id Unique ID for webhook replay deduplication (nullable).
 * @return bool
 */
function edupro_log_payment_event( $order_id, $gateway, $event_type, $status, $payload_summary = '', $event_id = null ) {
    global $wpdb;

    edupro_maybe_create_orders_table();

    $data    = array(
        'order_id'        => sanitize_text_field( (string) $order_id ),
        'gateway'         => sanitize_key( (string) $gateway ),
        'event_type'      => sanitize_text_field( (string) $event_type ),
        'status'          => sanitize_text_field( (string) $status ),
        'payload_summary' => sanitize_textarea_field( substr( (string) $payload_summary, 0, 2000 ) ),
        'created_at'      => current_time( 'mysql' ),
    );
    $formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

    if ( $event_id !== null && $event_id !== '' ) {
        $data['event_id'] = substr( sanitize_text_field( (string) $event_id ), 0, 191 );
        $formats[]        = '%s';
    }

    // Suppress duplicate-key warning on racing webhook retries
    $prev_suppress = $wpdb->suppress_errors( true );
    $inserted      = $wpdb->insert( $wpdb->prefix . 'edupro_payment_logs', $data, $formats );
    $wpdb->suppress_errors( $prev_suppress );

    return $inserted !== false;
}

/**
 * Generate a unique, gateway-safe order ID (<= 30 chars, alphanumeric + hyphens).
 *
 * @return string
 */
function edupro_generate_order_id() {
    return 'EDU-' . strtoupper( wp_generate_password( 8, false, false ) ) . '-' . time();
}

/**
 * Insert a new order record into wp_edupro_orders.
 *
 * @param array $args
 * @return string|false Unique order_id on success, false on failure.
 */
function edupro_insert_order( $args ) {
    global $wpdb;

    edupro_maybe_create_orders_table();

    $default_currency = function_exists( 'edupro_get_default_currency' )
        ? edupro_get_default_currency()
        : strtoupper( (string) get_option( 'edupro_default_currency', 'INR' ) );

    $defaults = array(
        'order_id'         => '',
        'idempotency_key'  => '',
        'user_id'          => 0,
        'course_id'        => 0,
        'currency'         => $default_currency,
        'subtotal'         => 0.00,
        'discount_amount'  => 0.00,
        'coupon_code'      => '',
        'tax_amount'       => 0.00,
        'amount'           => 0.00,
        'gateway'          => '',
        'gateway_order_id' => '',
        'transaction_id'   => '',
        'status'           => 'pending',
        'meta_json'        => '',
    );

    $args     = wp_parse_args( $args, $defaults );
    $order_id = $args['order_id'] !== '' ? sanitize_text_field( $args['order_id'] ) : edupro_generate_order_id();
    $now      = current_time( 'mysql' );

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'edupro_orders',
        array(
            'order_id'         => $order_id,
            'idempotency_key'  => sanitize_text_field( (string) $args['idempotency_key'] ),
            'user_id'          => absint( $args['user_id'] ),
            'course_id'        => absint( $args['course_id'] ),
            'currency'         => strtoupper( sanitize_text_field( (string) $args['currency'] ) ),
            'subtotal'         => round( floatval( $args['subtotal'] ), 2 ),
            'discount_amount'  => round( floatval( $args['discount_amount'] ), 2 ),
            'coupon_code'      => strtoupper( sanitize_text_field( (string) $args['coupon_code'] ) ),
            'tax_amount'       => round( floatval( $args['tax_amount'] ), 2 ),
            'amount'           => round( floatval( $args['amount'] ), 2 ),
            'gateway'          => sanitize_key( (string) $args['gateway'] ),
            'gateway_order_id' => sanitize_text_field( (string) $args['gateway_order_id'] ),
            'transaction_id'   => sanitize_text_field( (string) $args['transaction_id'] ),
            'status'           => sanitize_key( (string) $args['status'] ),
            'meta_json'        => is_array( $args['meta_json'] ) ? wp_json_encode( $args['meta_json'] ) : (string) $args['meta_json'],
            'created_at'       => $now,
            'updated_at'       => $now,
        ),
        array( '%s', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    return $inserted ? $order_id : false;
}

/**
 * Update arbitrary allowed fields on an order row.
 *
 * @param string $order_id
 * @param array  $fields
 * @return bool
 */
function edupro_update_order_fields( $order_id, $fields ) {
    global $wpdb;

    if ( empty( $fields ) || ! is_array( $fields ) ) {
        return false;
    }

    $fields['updated_at'] = current_time( 'mysql' );
    if ( isset( $fields['meta_json'] ) && is_array( $fields['meta_json'] ) ) {
        $fields['meta_json'] = wp_json_encode( $fields['meta_json'] );
    }

    $res = $wpdb->update(
        $wpdb->prefix . 'edupro_orders',
        $fields,
        array( 'order_id' => sanitize_text_field( (string) $order_id ) )
    );

    return $res !== false;
}

/**
 * Update order status (non-completion transitions; for completion always use edupro_complete_verified_order).
 *
 * @param string $order_id
 * @param string $status
 * @param string $transaction_id
 * @return bool
 */
function edupro_update_order_status( $order_id, $status, $transaction_id = '' ) {
    $allowed = array( 'pending', 'processing', 'completed', 'failed', 'refunded', 'superseded' );
    $status  = sanitize_key( (string) $status );
    if ( ! in_array( $status, $allowed, true ) ) {
        return false;
    }

    $fields = array( 'status' => $status );
    if ( $transaction_id !== '' ) {
        $fields['transaction_id'] = sanitize_text_field( (string) $transaction_id );
    }

    return edupro_update_order_fields( $order_id, $fields );
}

/**
 * Mark an order failed with a failure_reason (only if currently pending/processing).
 *
 * @param string $order_id
 * @param string $reason
 * @return bool
 */
function edupro_mark_order_failed( $order_id, $reason = '' ) {
    global $wpdb;
    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}edupro_orders
             SET status = 'failed', failure_reason = %s, updated_at = %s
             WHERE order_id = %s AND status IN ('pending', 'processing')",
            sanitize_text_field( (string) $reason ),
            current_time( 'mysql' ),
            sanitize_text_field( (string) $order_id )
        )
    );
    return $updated > 0;
}

/**
 * Record refund details on an order row.
 *
 * @param string $order_id
 * @param string $refund_id
 * @param float  $refund_amount
 * @return bool
 */
function edupro_record_order_refund( $order_id, $refund_id, $refund_amount ) {
    return edupro_update_order_fields(
        $order_id,
        array(
            'status'        => 'refunded',
            'refund_id'     => sanitize_text_field( (string) $refund_id ),
            'refund_amount' => round( floatval( $refund_amount ), 2 ),
        )
    );
}

if ( ! function_exists( 'edupro_get_order' ) ) :
/**
 * Fetch an order row by its unique order_id.
 *
 * @param string $order_id
 * @return object|null
 */
function edupro_get_order( $order_id ) {
    global $wpdb;
    $order_id = sanitize_text_field( (string) $order_id );
    if ( $order_id === '' ) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}edupro_orders WHERE order_id = %s LIMIT 1",
            $order_id
        )
    );
}
endif;

/**
 * Fetch an order by gateway_order_id (and optional gateway slug).
 *
 * @param string $gateway_order_id
 * @param string $gateway
 * @return object|null
 */
function edupro_get_order_by_gateway_order_id( $gateway_order_id, $gateway = '' ) {
    global $wpdb;
    $gateway_order_id = sanitize_text_field( (string) $gateway_order_id );
    if ( $gateway_order_id === '' ) {
        return null;
    }

    if ( $gateway !== '' ) {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}edupro_orders WHERE gateway_order_id = %s AND gateway = %s ORDER BY id DESC LIMIT 1",
                $gateway_order_id,
                sanitize_key( $gateway )
            )
        );
    }

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}edupro_orders WHERE gateway_order_id = %s ORDER BY id DESC LIMIT 1",
            $gateway_order_id
        )
    );
}

/**
 * Fetch an order by transaction_id.
 *
 * @param string $transaction_id
 * @return object|null
 */
function edupro_get_order_by_transaction( $transaction_id ) {
    global $wpdb;
    $transaction_id = sanitize_text_field( (string) $transaction_id );
    if ( $transaction_id === '' ) {
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}edupro_orders WHERE transaction_id = %s ORDER BY id DESC LIMIT 1",
            $transaction_id
        )
    );
}

if ( ! function_exists( 'edupro_get_user_orders' ) ) :
/**
 * Fetch a student's order history (most recent first).
 *
 * @param int $user_id
 * @param int $limit
 * @return array
 */
function edupro_get_user_orders( $user_id, $limit = 50 ) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}edupro_orders WHERE user_id = %d AND status != 'superseded' ORDER BY created_at DESC LIMIT %d",
            absint( $user_id ),
            max( 1, absint( $limit ) )
        )
    );
}
endif;

/**
 * Enroll student safely via inc/enrollment.php (with resilient fallback).
 *
 * @param int $course_id
 * @param int $user_id
 * @return bool
 */
function edupro_payment_enroll_student( $course_id, $user_id ) {
    if ( function_exists( 'edupro_enroll_student' ) ) {
        return (bool) edupro_enroll_student( $course_id, $user_id );
    }

    $course_id = absint( $course_id );
    $user_id   = absint( $user_id );
    if ( ! $course_id || ! $user_id ) {
        return false;
    }

    $enrolled_courses = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
    if ( ! is_array( $enrolled_courses ) ) {
        $enrolled_courses = array();
    }
    if ( ! in_array( $course_id, $enrolled_courses, true ) ) {
        $enrolled_courses[] = $course_id;
        update_user_meta( $user_id, '_edupro_enrolled_courses', $enrolled_courses );
        update_user_meta( $user_id, '_edupro_enrollment_' . $course_id . '_date', current_time( 'mysql' ) );
    }

    $enrolled_students = get_post_meta( $course_id, '_edupro_enrolled_students', true );
    if ( ! is_array( $enrolled_students ) ) {
        $enrolled_students = array();
    }
    if ( ! in_array( $user_id, $enrolled_students, true ) ) {
        $enrolled_students[] = $user_id;
        update_post_meta( $course_id, '_edupro_enrolled_students', $enrolled_students );
    }

    return true;
}

// ============================================================
// 4. ATOMIC ORDER COMPLETION (ANTI-DUPLICATION GUARD)
// ============================================================

/**
 * Atomically transition a verified order from pending/processing -> completed.
 *
 * Uses a single conditional SQL UPDATE so concurrent callbacks + webhooks can NEVER
 * double-enroll, double-increment coupon usage, or send duplicate emails.
 *
 * @param string $order_id
 * @param string $transaction_id
 * @param string $verified_via   e.g. 'ajax_cashfree', 'webhook_stripe', 'reconcile_razorpay', 'admin_manual_approval', 'free_checkout'
 * @param array  $details        Optional extra metadata merged into meta_json.
 * @return array ['completed' => bool, 'already_completed' => bool, 'order' => object|null]
 */
function edupro_complete_verified_order( $order_id, $transaction_id, $verified_via, $details = array() ) {
    global $wpdb;

    $order_id       = sanitize_text_field( (string) $order_id );
    $transaction_id = sanitize_text_field( (string) $transaction_id );
    $verified_via   = sanitize_text_field( (string) $verified_via );
    $now            = current_time( 'mysql' );
    $table          = $wpdb->prefix . 'edupro_orders';

    // Atomic conditional transition: only one caller can win the row lock
    $rows_affected = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
             SET status = 'completed',
                 transaction_id = %s,
                 verified_at = %s,
                 verified_via = %s,
                 failure_reason = '',
                 updated_at = %s
             WHERE order_id = %s
               AND status IN ('pending', 'processing')",
            $transaction_id,
            $now,
            $verified_via,
            $now,
            $order_id
        )
    );

    $order = edupro_get_order( $order_id );
    if ( ! $order ) {
        return array(
            'completed'         => false,
            'already_completed' => false,
            'order'             => null,
        );
    }

    if ( (int) $rows_affected === 0 ) {
        // Already completed (idempotent retry) or in terminal state
        return array(
            'completed'         => $order->status === 'completed',
            'already_completed' => $order->status === 'completed',
            'order'             => $order,
        );
    }

    // Merge verification metadata if provided
    if ( ! empty( $details ) && is_array( $details ) ) {
        $existing_meta = ! empty( $order->meta_json ) ? json_decode( $order->meta_json, true ) : array();
        if ( ! is_array( $existing_meta ) ) {
            $existing_meta = array();
        }
        $existing_meta['verification'] = $details;
        edupro_update_order_fields( $order_id, array( 'meta_json' => wp_json_encode( $existing_meta ) ) );
    }

    // 1. Enroll student
    edupro_payment_enroll_student( absint( $order->course_id ), absint( $order->user_id ) );

    // 2. Atomically increment coupon usage if a coupon was applied
    if ( ! empty( $order->coupon_code ) && function_exists( 'edupro_increment_coupon_usage' ) ) {
        edupro_increment_coupon_usage( $order->coupon_code );
    }

    // 3. Send confirmation email with invoice link
    $gw            = edupro_get_gateway( $order->gateway );
    $gateway_label = $gw ? $gw->get_label() : ucfirst( str_replace( '_', ' ', $order->gateway ) );
    edupro_send_payment_confirmation_email(
        absint( $order->user_id ),
        absint( $order->course_id ),
        $order->order_id,
        $gateway_label,
        floatval( $order->amount ),
        strtoupper( (string) $order->currency )
    );

    // 4. Write audit log
    edupro_log_payment_event(
        $order->order_id,
        $order->gateway,
        'order_completed',
        'completed',
        sprintf( 'Verified via %s (txn: %s, amount: %s %.2f)', $verified_via, $transaction_id, $order->currency, floatval( $order->amount ) )
    );

    do_action( 'edupro_order_completed', $order, $verified_via );

    return array(
        'completed'         => true,
        'already_completed' => false,
        'order'             => edupro_get_order( $order_id ),
    );
}

// ============================================================
// 5. MAIN CHECKOUT PROCESSOR (MULTI-CURRENCY, COUPONS, TAX, ANTI-DUP)
// ============================================================

/**
 * Main checkout orchestrator.
 *
 * @param int    $course_id
 * @param int    $user_id
 * @param string $gateway     cashfree|razorpay|stripe|paypal|bank_transfer|free
 * @param string $currency    Optional ISO-4217 currency ('INR'|'GBP'|'USD')
 * @param string $coupon_code Optional coupon code
 * @return array ['success' => bool, 'data' => array] or ['success' => false, 'message' => string]
 */
function edupro_process_checkout( $course_id, $user_id, $gateway, $currency = '', $coupon_code = '' ) {
    global $wpdb;

    $course_id   = absint( $course_id );
    $user_id     = absint( $user_id );
    $gateway     = sanitize_key( (string) $gateway );
    $coupon_code = strtoupper( sanitize_text_field( (string) $coupon_code ) );

    if ( ! $course_id || ! $user_id ) {
        return array( 'success' => false, 'message' => __( 'Invalid course or user.', 'edupro' ) );
    }

    $course = get_post( $course_id );
    $user   = get_userdata( $user_id );
    if ( ! $course || $course->post_type !== 'lms_course' || $course->post_status !== 'publish' || ! $user ) {
        return array( 'success' => false, 'message' => __( 'Course or student account not found.', 'edupro' ) );
    }

    // Strict anti-duplication: reject if already enrolled
    if ( function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id, $user_id ) ) {
        return array( 'success' => false, 'message' => __( 'You are already enrolled in this course.', 'edupro' ) );
    }

    // Check course enrollment cap
    $enrollment_limit = absint( get_post_meta( $course_id, '_edupro_enrollment_limit', true ) );
    if ( $enrollment_limit > 0 && function_exists( 'edupro_get_enrolled_count' ) ) {
        if ( edupro_get_enrolled_count( $course_id ) >= $enrollment_limit ) {
            return array( 'success' => false, 'message' => __( 'This course has reached its maximum enrollment limit.', 'edupro' ) );
        }
    }

    // Resolve active currency & pricing
    if ( $currency === '' ) {
        $currency = function_exists( 'edupro_get_active_currency' )
            ? edupro_get_active_currency()
            : strtoupper( (string) get_option( 'edupro_default_currency', 'INR' ) );
    } else {
        $currency = strtoupper( sanitize_text_field( $currency ) );
    }

    if ( function_exists( 'edupro_get_course_price_data' ) ) {
        $price_data = edupro_get_course_price_data( $course_id, $currency );
        $currency   = strtoupper( (string) ( $price_data['currency'] ?? $currency ) );
        $is_free    = ! empty( $price_data['is_free'] );
        $subtotal   = round( floatval( $price_data['effective'] ?? 0 ), 2 );
    } else {
        $is_free  = edupro_is_course_free( $course_id );
        $subtotal = $is_free ? 0.0 : edupro_get_course_effective_price( $course_id, $currency );
    }

    // Validate optional coupon
    $discount_amount = 0.00;
    $applied_coupon  = '';
    if ( ! $is_free && $subtotal > 0 && $coupon_code !== '' ) {
        if ( ! function_exists( 'edupro_validate_coupon' ) ) {
            return array( 'success' => false, 'message' => __( 'Coupon module is not available.', 'edupro' ) );
        }
        $coupon_res = edupro_validate_coupon( $coupon_code, $course_id, $subtotal, $currency );
        if ( is_wp_error( $coupon_res ) ) {
            return array( 'success' => false, 'message' => $coupon_res->get_error_message() );
        }
        $applied_coupon  = strtoupper( (string) $coupon_res['code'] );
        $discount_amount = round( floatval( $coupon_res['discount_amount'] ), 2 );
    }

    // Tax calculation
    $taxable    = max( 0.00, round( $subtotal - $discount_amount, 2 ) );
    $tax_rate   = max( 0.0, floatval( get_option( 'edupro_tax_rate', 0 ) ) );
    $tax_amount = $taxable > 0 && $tax_rate > 0 ? round( $taxable * ( $tax_rate / 100 ), 2 ) : 0.00;
    $total      = round( $taxable + $tax_amount, 2 );

    // Free course OR 100%-discounted coupon -> direct enrollment (NEVER trust client-supplied gateway='free')
    if ( $is_free || $total <= 0 ) {
        $order_id = edupro_insert_order( array(
            'idempotency_key' => 'free_' . $user_id . '_' . $course_id . '_' . time(),
            'user_id'         => $user_id,
            'course_id'       => $course_id,
            'currency'        => $currency,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount_amount,
            'coupon_code'     => $applied_coupon,
            'tax_amount'      => 0.00,
            'amount'          => 0.00,
            'gateway'         => 'free',
            'status'          => 'pending',
        ) );

        if ( ! $order_id ) {
            return array( 'success' => false, 'message' => __( 'Unable to create enrollment record.', 'edupro' ) );
        }

        edupro_complete_verified_order( $order_id, 'FREE-' . $course_id . '-' . time(), 'free_checkout' );

        return array(
            'success' => true,
            'data'    => array(
                'order_id'        => $order_id,
                'local_order_id'  => $order_id,
                'gateway'         => 'free',
                'currency'        => $currency,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount_amount,
                'tax_amount'      => 0.00,
                'amount'          => 0.00,
                'verified'        => true,
                'enrolled'        => true,
                'status'          => 'completed',
                'message'         => __( 'You have been enrolled in this course!', 'edupro' ),
                'redirect'        => edupro_get_dashboard_url(),
                'redirect_url'    => edupro_get_dashboard_url(),
            ),
        );
    }

    // Validate chosen paid gateway
    $gw = edupro_get_gateway( $gateway );
    if ( ! $gw ) {
        return array( 'success' => false, 'message' => __( 'Invalid payment gateway selected.', 'edupro' ) );
    }
    if ( ! $gw->is_enabled() ) {
        return array(
            'success' => false,
            'message' => sprintf( __( '%s is currently unavailable. Please choose another payment method.', 'edupro' ), $gw->get_label() ),
        );
    }
    if ( ! $gw->supports_currency( $currency ) ) {
        return array(
            'success' => false,
            'message' => sprintf(
                __( '%1$s does not support %2$s. Supported currencies: %3$s.', 'edupro' ),
                $gw->get_label(),
                $currency,
                implode( ', ', $gw->get_supported_currencies() )
            ),
        );
    }

    // Build deterministic idempotency fingerprint for reusing recent identical pending orders (<= 15 mins)
    $idempotency_key = hash( 'sha256', implode( '|', array(
        $user_id,
        $course_id,
        $gateway,
        $currency,
        number_format( $total, 2, '.', '' ),
        $applied_coupon,
    ) ) );

    $table          = $wpdb->prefix . 'edupro_orders';
    $cutoff         = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 15 * MINUTE_IN_SECONDS ) );
    $recent_pending = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND course_id = %d
               AND gateway = %s
               AND idempotency_key = %s
               AND status = 'pending'
               AND created_at >= %s
             ORDER BY id DESC
             LIMIT 1",
            $user_id,
            $course_id,
            $gateway,
            $idempotency_key,
            $cutoff
        )
    );

    if ( $recent_pending && ! empty( $recent_pending->meta_json ) ) {
        $meta = json_decode( $recent_pending->meta_json, true );
        if ( is_array( $meta ) && ! empty( $meta['checkout_payload'] ) ) {
            $reused_payload = $meta['checkout_payload'];
            $reused_payload['reused_order'] = true;
            return array(
                'success' => true,
                'data'    => $reused_payload,
            );
        }
    }

    // Mark stale / superseded pending orders for this user + course (except bank_transfer if still awaiting transfer? supersede old pending attempts so checkout stays clean)
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
             SET status = 'superseded', updated_at = %s
             WHERE user_id = %d
               AND course_id = %d
               AND status = 'pending'
               AND gateway != 'bank_transfer'",
            current_time( 'mysql' ),
            $user_id,
            $course_id
        )
    );

    // Insert fresh pending order
    $order_id = edupro_insert_order( array(
        'idempotency_key' => $idempotency_key,
        'user_id'         => $user_id,
        'course_id'       => $course_id,
        'currency'        => $currency,
        'subtotal'        => $subtotal,
        'discount_amount' => $discount_amount,
        'coupon_code'     => $applied_coupon,
        'tax_amount'      => $tax_amount,
        'amount'          => $total,
        'gateway'         => $gateway,
        'status'          => 'pending',
    ) );

    if ( ! $order_id ) {
        return array( 'success' => false, 'message' => __( 'Could not create order record. Please try again.', 'edupro' ) );
    }

    $order = edupro_get_order( $order_id );

    // Delegate remote session / order / intent creation to the modular gateway
    $gw_result = $gw->create_payment( $order, $course, $user );

    if ( is_wp_error( $gw_result ) ) {
        edupro_mark_order_failed( $order_id, $gw_result->get_error_message() );
        edupro_log_payment_event( $order_id, $gateway, 'create_payment_failed', 'failed', $gw_result->get_error_message() );
        return array( 'success' => false, 'message' => $gw_result->get_error_message() );
    }

    $gateway_order_id = sanitize_text_field( (string) ( $gw_result['gateway_order_id'] ?? '' ) );

    $response_payload = array_merge(
        $gw_result,
        array(
            'order_id'        => $order_id,
            'local_order_id'  => $order_id,
            'gateway'         => $gateway,
            'currency'        => $currency,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount_amount,
            'coupon_code'     => $applied_coupon,
            'tax_amount'      => $tax_amount,
            'amount'          => $total,
            'course_id'       => $course_id,
            'user_id'         => $user_id,
        )
    );

    // Save gateway_order_id and cache checkout payload in meta_json for 15-min idempotent reuse
    edupro_update_order_fields(
        $order_id,
        array(
            'gateway_order_id' => $gateway_order_id,
            'meta_json'        => wp_json_encode( array( 'checkout_payload' => $response_payload ) ),
        )
    );

    edupro_log_payment_event(
        $order_id,
        $gateway,
        'checkout_initiated',
        'pending',
        sprintf( 'Gateway order %s created (%s %.2f)', $gateway_order_id, $currency, $total )
    );

    return array(
        'success' => true,
        'data'    => $response_payload,
    );
}

// ============================================================
// 6. RECONCILIATION & REFUNDS PUBLIC API
// ============================================================

if ( ! function_exists( 'edupro_reconcile_order' ) ) :
/**
 * Perform a live server-side check with the gateway API for an order and update local status.
 *
 * @param string $order_id
 * @return array|WP_Error ['order_id', 'previous_status', 'status', 'transaction_id', 'message']
 */
function edupro_reconcile_order( $order_id ) {
    $order = edupro_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'order_not_found', __( 'Order not found.', 'edupro' ) );
    }

    if ( $order->gateway === 'free' ) {
        return array(
            'order_id'        => $order->order_id,
            'previous_status' => $order->status,
            'status'          => $order->status,
            'transaction_id'  => $order->transaction_id,
            'message'         => __( 'Free order requires no gateway reconciliation.', 'edupro' ),
        );
    }

    $gw = edupro_get_gateway( $order->gateway );
    if ( ! $gw ) {
        return new WP_Error( 'gateway_not_found', __( 'Payment gateway class not found.', 'edupro' ) );
    }

    $prev_status = $order->status;
    $result      = $gw->reconcile_order( $order );

    if ( is_wp_error( $result ) ) {
        edupro_log_payment_event( $order->order_id, $order->gateway, 'reconcile_error', 'error', $result->get_error_message() );
        return $result;
    }

    $remote_status = sanitize_key( (string) ( $result['status'] ?? $order->status ) );
    $txn_id        = sanitize_text_field( (string) ( $result['transaction_id'] ?? $order->transaction_id ) );
    $message       = sanitize_text_field( (string) ( $result['message'] ?? '' ) );

    if ( $remote_status === 'completed' && ! empty( $result['verified'] ) ) {
        if ( $order->status !== 'completed' ) {
            // If previously marked failed/superseded but gateway actually captured funds, reopen to pending for atomic completion
            if ( in_array( $order->status, array( 'failed', 'superseded' ), true ) ) {
                edupro_update_order_status( $order->order_id, 'pending' );
            }
            edupro_complete_verified_order( $order->order_id, $txn_id, 'reconcile_' . $order->gateway );
        }
    } elseif ( $remote_status === 'failed' && in_array( $order->status, array( 'pending', 'processing' ), true ) ) {
        edupro_mark_order_failed( $order->order_id, $message );
    } elseif ( $remote_status === 'refunded' && $order->status !== 'refunded' ) {
        edupro_record_order_refund( $order->order_id, $txn_id, floatval( $result['amount_paid'] ?: $order->amount ) );
    }

    edupro_log_payment_event(
        $order->order_id,
        $order->gateway,
        'reconcile',
        $remote_status,
        sprintf( 'Prev: %s -> Remote: %s (%s)', $prev_status, $remote_status, $message )
    );

    $refreshed = edupro_get_order( $order->order_id );

    return array(
        'order_id'        => $order->order_id,
        'previous_status' => $prev_status,
        'status'          => $refreshed ? $refreshed->status : $remote_status,
        'transaction_id'  => $refreshed ? $refreshed->transaction_id : $txn_id,
        'message'         => $message,
    );
}
endif;

if ( ! function_exists( 'edupro_refund_order' ) ) :
/**
 * Issue a refund through the gateway Refunds API and record refund metadata locally.
 *
 * @param string     $order_id
 * @param float|null $amount   Null for full order amount.
 * @param string     $reason
 * @param bool       $unenroll Whether to also unenroll the student from the course.
 * @return array|WP_Error
 */
function edupro_refund_order( $order_id, $amount = null, $reason = '', $unenroll = false ) {
    $order = edupro_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'order_not_found', __( 'Order not found.', 'edupro' ) );
    }

    if ( $order->status !== 'completed' ) {
        return new WP_Error( 'invalid_order_status', __( 'Only completed orders can be refunded.', 'edupro' ) );
    }

    $gw = edupro_get_gateway( $order->gateway );
    if ( ! $gw ) {
        return new WP_Error( 'gateway_not_found', __( 'Gateway handler not available for this order.', 'edupro' ) );
    }

    $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
    $res           = $gw->refund_payment( $order, $refund_amount, $reason );

    if ( is_wp_error( $res ) ) {
        edupro_log_payment_event( $order->order_id, $order->gateway, 'refund_failed', 'error', $res->get_error_message() );
        return $res;
    }

    $refund_id = sanitize_text_field( (string) ( $res['refund_id'] ?? '' ) );
    edupro_record_order_refund( $order->order_id, $refund_id, $refund_amount );

    if ( $unenroll && function_exists( 'edupro_unenroll_student' ) ) {
        edupro_unenroll_student( absint( $order->course_id ), absint( $order->user_id ) );
    }

    edupro_log_payment_event(
        $order->order_id,
        $order->gateway,
        'refund_issued',
        'refunded',
        sprintf( 'Refund %s for %s %.2f%s', $refund_id, $order->currency, $refund_amount, $unenroll ? ' (student unenrolled)' : '' )
    );

    return array(
        'order_id'  => $order->order_id,
        'refund_id' => $refund_id,
        'amount'    => $refund_amount,
        'status'    => 'refunded',
        'unenrolled'=> (bool) $unenroll,
    );
}
endif;

// ============================================================
// 7. AJAX ENDPOINTS (CHECKOUT & SERVER-SIDE VERIFICATION)
// ============================================================

/**
 * Helper: Load and authorize an order for the current logged-in user during AJAX verification.
 *
 * @param string $order_id
 * @param string $expected_gateway
 * @return object Sends JSON error and exits if invalid.
 */
function edupro_ajax_require_user_order( $order_id, $expected_gateway = '' ) {
    if ( ! edupro_verify_payment_nonce() ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ), 403 );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to verify payment.', 'edupro' ) ), 401 );
    }

    $order = edupro_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => __( 'Order not found.', 'edupro' ) ), 404 );
    }

    if ( (int) $order->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to verify this order.', 'edupro' ) ), 403 );
    }

    if ( $expected_gateway !== '' && $order->gateway !== $expected_gateway ) {
        wp_send_json_error( array( 'message' => __( 'Gateway mismatch for this order.', 'edupro' ) ), 400 );
    }

    return $order;
}

/**
 * AJAX: Initiate checkout (POST: course_id, gateway, currency, coupon_code).
 * Action: edupro_checkout_initiate
 */
function edupro_checkout_ajax() {
    if ( ! edupro_verify_payment_nonce() ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'edupro' ) ), 403 );
    }

    if ( ! is_user_logged_in() ) {
        $login_page_id = absint( get_option( 'edupro_login_page_id', 0 ) );
        $login_url     = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
        wp_send_json_error( array(
            'message'       => __( 'Please log in or create an account to complete enrollment.', 'edupro' ),
            'require_login' => true,
            'login_url'     => $login_url,
        ) );
    }

    $course_id   = isset( $_POST['course_id'] )   ? absint( $_POST['course_id'] )                                    : 0;
    $gateway     = isset( $_POST['gateway'] )     ? sanitize_key( wp_unslash( $_POST['gateway'] ) )                  : '';
    $currency    = isset( $_POST['currency'] )    ? strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) ) : '';
    $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) )       : '';
    $user_id     = get_current_user_id();

    if ( ! $course_id ) {
        wp_send_json_error( array( 'message' => __( 'Please select a valid course.', 'edupro' ) ) );
    }

    if ( $gateway === '' && edupro_is_course_free( $course_id ) ) {
        $gateway = 'free';
    }

    $result = edupro_process_checkout( $course_id, $user_id, $gateway, $currency, $coupon_code );

    if ( ! empty( $result['success'] ) ) {
        wp_send_json_success( $result['data'] );
    } else {
        wp_send_json_error( array( 'message' => $result['message'] ) );
    }
}
add_action( 'wp_ajax_edupro_checkout_initiate', 'edupro_checkout_ajax' );
add_action( 'wp_ajax_nopriv_edupro_checkout_initiate', 'edupro_checkout_ajax' );

/**
 * AJAX: Server-side Cashfree payment verification (POST: order_id).
 * Action: edupro_cashfree_verify
 */
function edupro_cashfree_verify_ajax() {
    $order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
    $order    = edupro_ajax_require_user_order( $order_id, 'cashfree' );

    if ( $order->status === 'completed' ) {
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment verified! Redirecting to your dashboard.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    $gw     = edupro_get_gateway( 'cashfree' );
    $verify = $gw->verify_payment( $order );

    if ( is_wp_error( $verify ) ) {
        edupro_log_payment_event( $order->order_id, 'cashfree', 'ajax_verify_error', 'error', $verify->get_error_message() );
        wp_send_json_error( array( 'message' => $verify->get_error_message() ) );
    }

    if ( ! empty( $verify['verified'] ) && $verify['status'] === 'completed' ) {
        edupro_complete_verified_order( $order->order_id, $verify['transaction_id'], 'ajax_cashfree' );
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment verified successfully! You are now enrolled.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    if ( $verify['status'] === 'failed' ) {
        edupro_mark_order_failed( $order->order_id, $verify['message'] );
        wp_send_json_error( array( 'message' => $verify['message'], 'status' => 'failed' ) );
    }

    wp_send_json_error( array(
        'message' => $verify['message'] ?: __( 'Payment is still being confirmed. Please check again in a moment.', 'edupro' ),
        'status'  => 'pending',
    ) );
}
add_action( 'wp_ajax_edupro_cashfree_verify', 'edupro_cashfree_verify_ajax' );

/**
 * AJAX: Server-side Razorpay signature + payment capture verification.
 * POST: order_id (optional if razorpay_order_id provided), razorpay_order_id, razorpay_payment_id, razorpay_signature
 * Action: edupro_razorpay_verify
 */
function edupro_razorpay_verify_payment() {
    if ( ! edupro_verify_payment_nonce() ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to verify payment.', 'edupro' ) ), 401 );
    }

    $order_id            = isset( $_POST['order_id'] )            ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) )            : '';
    $razorpay_order_id   = isset( $_POST['razorpay_order_id'] )   ? sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ) )   : '';
    $razorpay_payment_id = isset( $_POST['razorpay_payment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ) ) : '';
    $razorpay_signature  = isset( $_POST['razorpay_signature'] )  ? sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ) )  : '';

    $order = $order_id !== '' ? edupro_get_order( $order_id ) : null;
    if ( ! $order && $razorpay_order_id !== '' ) {
        $order = edupro_get_order_by_gateway_order_id( $razorpay_order_id, 'razorpay' );
    }
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => __( 'Matching Razorpay order not found.', 'edupro' ) ), 404 );
    }
    if ( (int) $order->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Order ownership mismatch.', 'edupro' ) ), 403 );
    }

    if ( $order->status === 'completed' ) {
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment already verified! Redirecting to your dashboard.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    $gw     = edupro_get_gateway( 'razorpay' );
    $verify = $gw->verify_payment(
        $order,
        array(
            'razorpay_order_id'   => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature'  => $razorpay_signature,
        )
    );

    if ( is_wp_error( $verify ) ) {
        edupro_mark_order_failed( $order->order_id, $verify->get_error_message() );
        edupro_log_payment_event( $order->order_id, 'razorpay', 'ajax_verify_error', 'failed', $verify->get_error_message() );
        wp_send_json_error( array( 'message' => $verify->get_error_message() ) );
    }

    if ( ! empty( $verify['verified'] ) && $verify['status'] === 'completed' ) {
        edupro_complete_verified_order( $order->order_id, $verify['transaction_id'], 'ajax_razorpay' );
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment verified successfully! You are now enrolled.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    if ( $verify['status'] === 'failed' ) {
        edupro_mark_order_failed( $order->order_id, $verify['message'] );
    }

    wp_send_json_error( array( 'message' => $verify['message'] ?: __( 'Razorpay payment verification pending.', 'edupro' ) ) );
}
add_action( 'wp_ajax_edupro_razorpay_verify', 'edupro_razorpay_verify_payment' );

/**
 * AJAX: Server-side Stripe PaymentIntent verification.
 * POST: order_id (optional if payment_intent_id provided), payment_intent_id
 * Action: edupro_stripe_confirm
 */
function edupro_stripe_confirm_payment() {
    if ( ! edupro_verify_payment_nonce() ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to confirm payment.', 'edupro' ) ), 401 );
    }

    $order_id          = isset( $_POST['order_id'] )          ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) )          : '';
    $payment_intent_id = isset( $_POST['payment_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ) ) : '';

    $order = $order_id !== '' ? edupro_get_order( $order_id ) : null;
    if ( ! $order && $payment_intent_id !== '' ) {
        $order = edupro_get_order_by_gateway_order_id( $payment_intent_id, 'stripe' );
    }
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => __( 'Matching Stripe order not found.', 'edupro' ) ), 404 );
    }
    if ( (int) $order->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Order ownership mismatch.', 'edupro' ) ), 403 );
    }

    if ( $order->status === 'completed' ) {
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment already confirmed! Redirecting to your dashboard.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    $gw     = edupro_get_gateway( 'stripe' );
    $verify = $gw->verify_payment( $order, array( 'payment_intent_id' => $payment_intent_id ) );

    if ( is_wp_error( $verify ) ) {
        edupro_log_payment_event( $order->order_id, 'stripe', 'ajax_confirm_error', 'error', $verify->get_error_message() );
        wp_send_json_error( array( 'message' => $verify->get_error_message() ) );
    }

    if ( ! empty( $verify['verified'] ) && $verify['status'] === 'completed' ) {
        edupro_complete_verified_order( $order->order_id, $verify['transaction_id'], 'ajax_stripe' );
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'Payment confirmed! You are now enrolled.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    if ( $verify['status'] === 'failed' ) {
        edupro_mark_order_failed( $order->order_id, $verify['message'] );
        wp_send_json_error( array( 'message' => $verify['message'], 'status' => 'failed' ) );
    }

    wp_send_json_error( array(
        'message' => $verify['message'] ?: __( 'Payment is still processing at Stripe.', 'edupro' ),
        'status'  => 'pending',
    ) );
}
add_action( 'wp_ajax_edupro_stripe_confirm', 'edupro_stripe_confirm_payment' );

/**
 * AJAX: Server-side PayPal v2 order capture & verification.
 * POST: order_id, paypal_order_id (optional if saved on order)
 * Action: edupro_paypal_capture
 */
function edupro_paypal_capture_ajax() {
    $order_id        = isset( $_POST['order_id'] )        ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) )        : '';
    $paypal_order_id = isset( $_POST['paypal_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_order_id'] ) ) : '';

    if ( $order_id === '' && $paypal_order_id !== '' ) {
        $by_pp    = edupro_get_order_by_gateway_order_id( $paypal_order_id, 'paypal' );
        $order_id = $by_pp ? $by_pp->order_id : '';
    }

    $order = edupro_ajax_require_user_order( $order_id, 'paypal' );

    if ( $order->status === 'completed' ) {
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'PayPal payment already captured! Redirecting to your dashboard.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    $gw     = edupro_get_gateway( 'paypal' );
    $verify = $gw->verify_payment( $order, array( 'paypal_order_id' => $paypal_order_id ) );

    if ( is_wp_error( $verify ) ) {
        edupro_log_payment_event( $order->order_id, 'paypal', 'ajax_capture_error', 'error', $verify->get_error_message() );
        wp_send_json_error( array( 'message' => $verify->get_error_message() ) );
    }

    if ( ! empty( $verify['verified'] ) && $verify['status'] === 'completed' ) {
        edupro_complete_verified_order( $order->order_id, $verify['transaction_id'], 'ajax_paypal' );
        $dashboard_url = edupro_get_dashboard_url();
        wp_send_json_success( array(
            'verified'     => true,
            'status'       => 'completed',
            'enrolled'     => true,
            'message'      => __( 'PayPal payment completed! You are now enrolled.', 'edupro' ),
            'order_id'     => $order->order_id,
            'redirect'     => $dashboard_url,
            'redirect_url' => $dashboard_url,
            'invoice_url'  => function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '',
        ) );
    }

    if ( $verify['status'] === 'failed' ) {
        edupro_mark_order_failed( $order->order_id, $verify['message'] );
        wp_send_json_error( array( 'message' => $verify['message'], 'status' => 'failed' ) );
    }

    wp_send_json_error( array(
        'message' => $verify['message'] ?: __( 'PayPal capture is still pending.', 'edupro' ),
        'status'  => 'pending',
    ) );
}
add_action( 'wp_ajax_edupro_paypal_capture', 'edupro_paypal_capture_ajax' );

// ============================================================
// 8. REST API WEBHOOK ROUTES (SEPARATE PER GATEWAY + LEGACY ALIASES)
// ============================================================

/**
 * Dispatch a REST webhook request to the matching gateway class.
 *
 * @param WP_REST_Request $request
 * @param string          $gateway_id
 * @return WP_REST_Response
 */
function edupro_dispatch_gateway_webhook( WP_REST_Request $request, $gateway_id ) {
    $gw = edupro_get_gateway( $gateway_id );
    if ( ! $gw ) {
        return new WP_REST_Response( array( 'error' => 'Unknown payment gateway.' ), 404 );
    }
    return $gw->handle_webhook( $request );
}

function edupro_register_payment_rest_routes() {
    $webhook_gateways = array( 'cashfree', 'razorpay', 'stripe', 'paypal' );

    foreach ( $webhook_gateways as $gw_id ) {
        register_rest_route(
            'edupro/v1',
            '/webhooks/' . $gw_id,
            array(
                'methods'             => 'POST',
                'callback'            => function ( WP_REST_Request $request ) use ( $gw_id ) {
                    return edupro_dispatch_gateway_webhook( $request, $gw_id );
                },
                'permission_callback' => '__return_true',
            )
        );
    }

    // Legacy v1.0.0 route aliases preserved for backwards compatibility
    register_rest_route(
        'edupro/v1',
        '/stripe-webhook',
        array(
            'methods'             => 'POST',
            'callback'            => function ( WP_REST_Request $request ) {
                return edupro_dispatch_gateway_webhook( $request, 'stripe' );
            },
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        'edupro/v1',
        '/paypal-ipn',
        array(
            'methods'             => 'POST',
            'callback'            => function ( WP_REST_Request $request ) {
                return edupro_dispatch_gateway_webhook( $request, 'paypal' );
            },
            'permission_callback' => '__return_true',
        )
    );
}
add_action( 'rest_api_init', 'edupro_register_payment_rest_routes' );

// ============================================================
// 9. EMAIL NOTIFICATIONS
// ============================================================

/**
 * Send payment confirmation & enrollment email (including Invoice link if available).
 */
function edupro_send_payment_confirmation_email( $user_id, $course_id, $order_id, $gateway_label, $amount, $currency = '' ) {
    $user         = get_userdata( $user_id );
    $course_title = wp_strip_all_tags( get_the_title( $course_id ) );

    if ( ! $user || $course_title === '' ) {
        return;
    }

    $formatted_amount = edupro_payment_format_amount( $amount, $currency );
    $site_name        = get_bloginfo( 'name' );
    $subject          = sprintf( __( '[%1$s] Enrollment Confirmed: %2$s', 'edupro' ), $site_name, $course_title );

    $invoice_line = '';
    if ( function_exists( 'edupro_get_invoice_url' ) ) {
        $invoice_line = "\n" . sprintf( __( 'Download Invoice: %s', 'edupro' ), edupro_get_invoice_url( $order_id ) );
    }

    $message = sprintf(
        __( "Hi %1\$s,\n\nThank you! Your enrollment is confirmed for:\n\nCourse: %2\$s\nOrder ID: %3\$s\nPayment Method: %4\$s\nAmount Paid: %5\$s%6\$s\n\nStart learning now from your dashboard:\n%7\$s\n\nHappy learning,\n%8\$s", 'edupro' ),
        $user->display_name,
        $course_title,
        $order_id,
        $gateway_label,
        $formatted_amount,
        $invoice_line,
        edupro_get_dashboard_url(),
        $site_name
    );

    wp_mail( $user->user_email, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

/**
 * Send UK & India bank transfer instructions email to the student.
 */
function edupro_send_bank_transfer_instructions_email( $user_id, $course_id, $order_id, $amount, $currency = '' ) {
    $user         = get_userdata( $user_id );
    $course_title = wp_strip_all_tags( get_the_title( $course_id ) );

    if ( ! $user || $course_title === '' ) {
        return;
    }

    $formatted_amount = edupro_payment_format_amount( $amount, $currency );
    $site_name        = get_bloginfo( 'name' );
    $subject          = sprintf( __( '[%1$s] Bank Transfer Instructions – Order %2$s', 'edupro' ), $site_name, $order_id );

    $bank_lines = array();
    $in_bank    = trim( (string) get_option( 'edupro_bank_name', '' ) );
    $in_acc     = trim( (string) get_option( 'edupro_bank_account_number', '' ) );
    $in_ifsc    = trim( (string) get_option( 'edupro_bank_ifsc', '' ) );
    $in_name    = trim( (string) get_option( 'edupro_bank_account_name', '' ) );
    $in_upi     = trim( (string) get_option( 'edupro_bank_upi_id', '' ) );

    if ( $in_bank !== '' || $in_acc !== '' || $in_upi !== '' ) {
        $bank_lines[] = __( '--- India Bank / UPI Details (INR) ---', 'edupro' );
        if ( $in_name !== '' ) $bank_lines[] = __( 'Account Name: ', 'edupro' ) . $in_name;
        if ( $in_bank !== '' ) $bank_lines[] = __( 'Bank Name: ', 'edupro' ) . $in_bank;
        if ( $in_acc !== '' )  $bank_lines[] = __( 'Account Number: ', 'edupro' ) . $in_acc;
        if ( $in_ifsc !== '' ) $bank_lines[] = __( 'IFSC Code: ', 'edupro' ) . $in_ifsc;
        if ( $in_upi !== '' )  $bank_lines[] = __( 'UPI ID: ', 'edupro' ) . $in_upi;
        $bank_lines[] = '';
    }

    $uk_name = trim( (string) get_option( 'edupro_bank_uk_account_name', '' ) );
    $uk_sort = trim( (string) get_option( 'edupro_bank_uk_sort_code', '' ) );
    $uk_acc  = trim( (string) get_option( 'edupro_bank_uk_account_number', '' ) );
    $uk_iban = trim( (string) get_option( 'edupro_bank_uk_iban', '' ) );

    if ( $uk_name !== '' || $uk_sort !== '' || $uk_acc !== '' || $uk_iban !== '' ) {
        $bank_lines[] = __( '--- UK Bank Details (GBP) ---', 'edupro' );
        if ( $uk_name !== '' ) $bank_lines[] = __( 'Account Name: ', 'edupro' ) . $uk_name;
        if ( $uk_sort !== '' ) $bank_lines[] = __( 'Sort Code: ', 'edupro' ) . $uk_sort;
        if ( $uk_acc !== '' )  $bank_lines[] = __( 'Account Number: ', 'edupro' ) . $uk_acc;
        if ( $uk_iban !== '' ) $bank_lines[] = __( 'IBAN: ', 'edupro' ) . $uk_iban;
        $bank_lines[] = '';
    }

    $instructions = trim( (string) get_option( 'edupro_bank_instructions', '' ) );

    $message = sprintf(
        __( "Hi %1\$s,\n\nWe have received your bank transfer order for \"%2\$s\".\n\nOrder ID (Reference): %3\$s\nAmount Due: %4\$s\n\n%5\$s%6\$s\n\nYour course access will be unlocked automatically as soon as our team verifies your transfer.\n\nRegards,\n%7\$s", 'edupro' ),
        $user->display_name,
        $course_title,
        $order_id,
        $formatted_amount,
        implode( "\n", $bank_lines ),
        $instructions !== '' ? __( "Instructions:\n", 'edupro' ) . $instructions : '',
        $site_name
    );

    wp_mail( $user->user_email, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

// ============================================================
// 10. ADMIN SETTINGS PAGE (Settings -> Payment Gateways)
// ============================================================

function edupro_register_payment_settings_page() {
    add_submenu_page(
        'options-general.php',
        __( 'Payment Gateways', 'edupro' ),
        __( 'Payment Gateways', 'edupro' ),
        'manage_options',
        'edupro-payment-gateways',
        'edupro_payment_settings_page'
    );
}
add_action( 'admin_menu', 'edupro_register_payment_settings_page' );

/**
 * Save Payment Gateway & Currency/Tax settings per tab.
 */
function edupro_save_payment_settings() {
    if ( ! isset( $_POST['edupro_save_payment_settings'] ) ) {
        return;
    }

    if ( ! isset( $_POST['edupro_payment_settings_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_payment_settings_nonce'] ) ), 'edupro_save_payment_settings' )
    ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $active_tab = isset( $_POST['active_tab'] ) ? sanitize_key( wp_unslash( $_POST['active_tab'] ) ) : 'currency';

    switch ( $active_tab ) {
        case 'currency':
            $def_cur = strtoupper( sanitize_text_field( wp_unslash( $_POST['edupro_default_currency'] ?? 'INR' ) ) );
            if ( ! in_array( $def_cur, array( 'INR', 'GBP', 'USD' ), true ) ) {
                $def_cur = 'INR';
            }
            update_option( 'edupro_default_currency', $def_cur );
            update_option( 'edupro_rate_gbp_to_inr', max( 0.01, floatval( $_POST['edupro_rate_gbp_to_inr'] ?? 108 ) ) );
            update_option( 'edupro_rate_usd_to_inr', max( 0.01, floatval( $_POST['edupro_rate_usd_to_inr'] ?? 84 ) ) );
            update_option( 'edupro_tax_rate', max( 0.0, min( 100.0, floatval( $_POST['edupro_tax_rate'] ?? 0 ) ) ) );
            update_option( 'edupro_tax_label', sanitize_text_field( wp_unslash( $_POST['edupro_tax_label'] ?? 'VAT / GST' ) ) );
            update_option( 'edupro_company_name', sanitize_text_field( wp_unslash( $_POST['edupro_company_name'] ?? '' ) ) );
            update_option( 'edupro_company_address', sanitize_textarea_field( wp_unslash( $_POST['edupro_company_address'] ?? '' ) ) );
            update_option( 'edupro_company_tax_id', sanitize_text_field( wp_unslash( $_POST['edupro_company_tax_id'] ?? '' ) ) );
            break;

        case 'cashfree':
            update_option( 'edupro_cashfree_enabled', isset( $_POST['edupro_cashfree_enabled'] ) ? '1' : '0' );
            update_option( 'edupro_cashfree_sandbox', isset( $_POST['edupro_cashfree_sandbox'] ) ? '1' : '0' );
            update_option( 'edupro_cashfree_app_id', sanitize_text_field( wp_unslash( $_POST['edupro_cashfree_app_id'] ?? '' ) ) );
            update_option( 'edupro_cashfree_secret_key', sanitize_text_field( wp_unslash( $_POST['edupro_cashfree_secret_key'] ?? '' ) ) );
            update_option( 'edupro_cashfree_webhook_secret', sanitize_text_field( wp_unslash( $_POST['edupro_cashfree_webhook_secret'] ?? '' ) ) );
            break;

        case 'razorpay':
            $sb = isset( $_POST['edupro_razorpay_sandbox'] ) ? '1' : '0';
            update_option( 'edupro_razorpay_enabled', isset( $_POST['edupro_razorpay_enabled'] ) ? '1' : '0' );
            update_option( 'edupro_razorpay_sandbox', $sb );
            update_option( 'edupro_razorpay_test_mode', $sb );
            update_option( 'edupro_razorpay_key_id', sanitize_text_field( wp_unslash( $_POST['edupro_razorpay_key_id'] ?? '' ) ) );
            update_option( 'edupro_razorpay_key_secret', sanitize_text_field( wp_unslash( $_POST['edupro_razorpay_key_secret'] ?? '' ) ) );
            update_option( 'edupro_razorpay_webhook_secret', sanitize_text_field( wp_unslash( $_POST['edupro_razorpay_webhook_secret'] ?? '' ) ) );
            break;

        case 'stripe':
            $sb = isset( $_POST['edupro_stripe_sandbox'] ) ? '1' : '0';
            update_option( 'edupro_stripe_enabled', isset( $_POST['edupro_stripe_enabled'] ) ? '1' : '0' );
            update_option( 'edupro_stripe_sandbox', $sb );
            update_option( 'edupro_stripe_test_mode', $sb );
            update_option( 'edupro_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['edupro_stripe_publishable_key'] ?? '' ) ) );
            update_option( 'edupro_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['edupro_stripe_secret_key'] ?? '' ) ) );
            update_option( 'edupro_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['edupro_stripe_webhook_secret'] ?? '' ) ) );
            break;

        case 'paypal':
            update_option( 'edupro_paypal_enabled', isset( $_POST['edupro_paypal_enabled'] ) ? '1' : '0' );
            update_option( 'edupro_paypal_sandbox', isset( $_POST['edupro_paypal_sandbox'] ) ? '1' : '0' );
            update_option( 'edupro_paypal_client_id', sanitize_text_field( wp_unslash( $_POST['edupro_paypal_client_id'] ?? '' ) ) );
            update_option( 'edupro_paypal_client_secret', sanitize_text_field( wp_unslash( $_POST['edupro_paypal_client_secret'] ?? '' ) ) );
            update_option( 'edupro_paypal_webhook_id', sanitize_text_field( wp_unslash( $_POST['edupro_paypal_webhook_id'] ?? '' ) ) );
            update_option( 'edupro_paypal_email', sanitize_email( wp_unslash( $_POST['edupro_paypal_email'] ?? '' ) ) );
            break;

        case 'bank_transfer':
            update_option( 'edupro_bank_transfer_enabled', isset( $_POST['edupro_bank_transfer_enabled'] ) ? '1' : '0' );
            update_option( 'edupro_bank_name', sanitize_text_field( wp_unslash( $_POST['edupro_bank_name'] ?? '' ) ) );
            update_option( 'edupro_bank_account_name', sanitize_text_field( wp_unslash( $_POST['edupro_bank_account_name'] ?? '' ) ) );
            update_option( 'edupro_bank_account_number', sanitize_text_field( wp_unslash( $_POST['edupro_bank_account_number'] ?? '' ) ) );
            update_option( 'edupro_bank_ifsc', sanitize_text_field( wp_unslash( $_POST['edupro_bank_ifsc'] ?? '' ) ) );
            update_option( 'edupro_bank_upi_id', sanitize_text_field( wp_unslash( $_POST['edupro_bank_upi_id'] ?? '' ) ) );
            update_option( 'edupro_bank_uk_account_name', sanitize_text_field( wp_unslash( $_POST['edupro_bank_uk_account_name'] ?? '' ) ) );
            update_option( 'edupro_bank_uk_sort_code', sanitize_text_field( wp_unslash( $_POST['edupro_bank_uk_sort_code'] ?? '' ) ) );
            update_option( 'edupro_bank_uk_account_number', sanitize_text_field( wp_unslash( $_POST['edupro_bank_uk_account_number'] ?? '' ) ) );
            update_option( 'edupro_bank_uk_iban', sanitize_text_field( wp_unslash( $_POST['edupro_bank_uk_iban'] ?? '' ) ) );
            update_option( 'edupro_bank_instructions', sanitize_textarea_field( wp_unslash( $_POST['edupro_bank_instructions'] ?? '' ) ) );
            break;
    }

    add_settings_error(
        'edupro_payment_settings',
        'settings_saved',
        __( 'Payment gateway settings saved.', 'edupro' ),
        'updated'
    );
}
add_action( 'admin_init', 'edupro_save_payment_settings' );

/**
 * Render helper for a read-only copyable webhook URL field.
 *
 * @param string $url
 * @param string $description
 */
function edupro_render_copyable_webhook_row( $url, $description ) {
    ?>
    <tr>
        <th scope="row"><?php esc_html_e( 'Webhook URL', 'edupro' ); ?></th>
        <td>
            <div style="display:flex; gap:8px; align-items:center; max-width:620px;">
                <input type="text" readonly value="<?php echo esc_url( $url ); ?>" class="large-text code edupro-copy-target" onclick="this.select();">
                <button type="button" class="button edupro-copy-btn" data-copy="<?php echo esc_attr( $url ); ?>">
                    <?php esc_html_e( 'Copy', 'edupro' ); ?>
                </button>
            </div>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        </td>
    </tr>
    <?php
}

/**
 * Render the 6-tab Settings -> Payment Gateways admin page.
 */
function edupro_payment_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    settings_errors( 'edupro_payment_settings' );

    $tabs = array(
        'currency'      => __( 'Currency & Tax', 'edupro' ),
        'cashfree'      => __( 'Cashfree (Primary India)', 'edupro' ),
        'razorpay'      => __( 'Razorpay (Backup India)', 'edupro' ),
        'stripe'        => __( 'Stripe (UK & Global)', 'edupro' ),
        'paypal'        => __( 'PayPal (Global)', 'edupro' ),
        'bank_transfer' => __( 'Bank Transfer (UK & India)', 'edupro' ),
    );

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'currency';
    if ( ! array_key_exists( $active_tab, $tabs ) ) {
        $active_tab = 'currency';
    }
    ?>
    <div class="wrap edupro-payment-settings">
        <h1><?php esc_html_e( 'EduPro Payment Gateways & Multi-Currency Settings', 'edupro' ); ?></h1>

        <style>
            .edupro-payment-settings .nav-tab-wrapper { margin-bottom: 20px; }
            .edupro-payment-settings .gw-pill {
                display: inline-block; margin-left: 6px; padding: 1px 6px;
                border-radius: 3px; font-size: 10px; font-weight: 700; vertical-align: middle; text-transform: uppercase;
            }
            .edupro-payment-settings .pill-on { background: #d4edda; color: #155724; }
            .edupro-payment-settings .pill-off { background: #f8d7da; color: #721c24; }
            .edupro-payment-settings .edupro-gw-card {
                background: #fff; border: 1px solid #ccd0d4; border-radius: 6px;
                padding: 22px 26px; max-width: 920px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .edupro-payment-settings .edupro-gw-card h2 {
                margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;
            }
            .edupro-payment-settings .edupro-subhead {
                margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 1px dashed #ddd;
                font-size: 14px; font-weight: 700; color: #1a1a2e;
            }
            .edupro-payment-settings .form-table th { width: 230px; }
        </style>

        <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Payment Settings Tabs', 'edupro' ); ?>">
            <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                <?php
                $is_active = ( $tab_key === $active_tab );
                $tab_url   = add_query_arg(
                    array( 'page' => 'edupro-payment-gateways', 'tab' => $tab_key ),
                    admin_url( 'options-general.php' )
                );
                $gw_obj    = $tab_key !== 'currency' ? edupro_get_gateway( $tab_key ) : null;
                $is_on     = $gw_obj ? ( get_option( 'edupro_' . $tab_key . '_enabled', '0' ) === '1' ) : false;
                ?>
                <a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $tab_label ); ?>
                    <?php if ( $gw_obj ) : ?>
                        <span class="gw-pill <?php echo $is_on ? 'pill-on' : 'pill-off'; ?>">
                            <?php echo $is_on ? esc_html__( 'ON', 'edupro' ) : esc_html__( 'OFF', 'edupro' ); ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="edupro-gw-card">
            <form method="post" action="">
                <?php wp_nonce_field( 'edupro_save_payment_settings', 'edupro_payment_settings_nonce' ); ?>
                <input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

                <?php if ( $active_tab === 'currency' ) : ?>
                    <h2><?php esc_html_e( 'Multi-Currency, Tax & Invoice Identity', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_default_currency"><?php esc_html_e( 'Default Store Currency', 'edupro' ); ?></label></th>
                            <td>
                                <select id="edupro_default_currency" name="edupro_default_currency">
                                    <?php $def_cur = strtoupper( (string) get_option( 'edupro_default_currency', 'INR' ) ); ?>
                                    <option value="INR" <?php selected( $def_cur, 'INR' ); ?>>INR (₹ — India)</option>
                                    <option value="GBP" <?php selected( $def_cur, 'GBP' ); ?>>GBP (£ — United Kingdom)</option>
                                    <option value="USD" <?php selected( $def_cur, 'USD' ); ?>>USD ($ — International)</option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Students can switch between INR, GBP, and USD on the storefront.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_rate_gbp_to_inr"><?php esc_html_e( 'GBP → INR Exchange Rate', 'edupro' ); ?></label></th>
                            <td>
                                <input type="number" step="0.01" min="1" id="edupro_rate_gbp_to_inr" name="edupro_rate_gbp_to_inr"
                                       value="<?php echo esc_attr( get_option( 'edupro_rate_gbp_to_inr', 108 ) ); ?>" class="small-text">
                                <p class="description"><?php esc_html_e( 'Used when a course has no manual GBP/INR override (e.g. 1 GBP = 108 INR).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_rate_usd_to_inr"><?php esc_html_e( 'USD → INR Exchange Rate', 'edupro' ); ?></label></th>
                            <td>
                                <input type="number" step="0.01" min="1" id="edupro_rate_usd_to_inr" name="edupro_rate_usd_to_inr"
                                       value="<?php echo esc_attr( get_option( 'edupro_rate_usd_to_inr', 84 ) ); ?>" class="small-text">
                                <p class="description"><?php esc_html_e( 'Example: 1 USD = 84 INR.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_tax_rate"><?php esc_html_e( 'Tax Rate (%)', 'edupro' ); ?></label></th>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" id="edupro_tax_rate" name="edupro_tax_rate"
                                       value="<?php echo esc_attr( get_option( 'edupro_tax_rate', 0 ) ); ?>" class="small-text"> %
                                <p class="description"><?php esc_html_e( 'Set 0 for tax-inclusive or tax-exempt pricing (e.g. 18 for India GST or 20 for UK VAT).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_tax_label"><?php esc_html_e( 'Tax Label', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_tax_label" name="edupro_tax_label"
                                       value="<?php echo esc_attr( get_option( 'edupro_tax_label', 'VAT / GST' ) ); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>

                    <div class="edupro-subhead"><?php esc_html_e( 'Company / Invoice Details', 'edupro' ); ?></div>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_company_name"><?php esc_html_e( 'Company / Academy Name', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_company_name" name="edupro_company_name"
                                       value="<?php echo esc_attr( get_option( 'edupro_company_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_company_address"><?php esc_html_e( 'Company Address', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_company_address" name="edupro_company_address" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'edupro_company_address', '' ) ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_company_tax_id"><?php esc_html_e( 'GSTIN / UK VAT Number', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_company_tax_id" name="edupro_company_tax_id"
                                       value="<?php echo esc_attr( get_option( 'edupro_company_tax_id', '' ) ); ?>" class="regular-text" placeholder="GSTIN / GB123456789">
                            </td>
                        </tr>
                    </table>

                <?php elseif ( $active_tab === 'cashfree' ) : ?>
                    <h2><?php esc_html_e( 'Cashfree PG Settings (Primary India — INR)', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Cashfree', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_cashfree_enabled" value="1" <?php checked( get_option( 'edupro_cashfree_enabled', '0' ), '1' ); ?>>
                                    <?php esc_html_e( 'Enable Cashfree as the primary Indian payment gateway (UPI, Cards, NetBanking)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sandbox / Test Mode', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_cashfree_sandbox" value="1" <?php checked( get_option( 'edupro_cashfree_sandbox', '1' ), '1' ); ?>>
                                    <?php esc_html_e( 'Use Cashfree Sandbox (https://sandbox.cashfree.com/pg)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_cashfree_app_id"><?php esc_html_e( 'App ID (Client ID)', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_cashfree_app_id" name="edupro_cashfree_app_id"
                                       value="<?php echo esc_attr( get_option( 'edupro_cashfree_app_id', '' ) ); ?>" class="regular-text" placeholder="TEST1000000000000000">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_cashfree_secret_key"><?php esc_html_e( 'Secret Key (Client Secret)', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_cashfree_secret_key" name="edupro_cashfree_secret_key"
                                       value="<?php echo esc_attr( get_option( 'edupro_cashfree_secret_key', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_cashfree_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_cashfree_webhook_secret" name="edupro_cashfree_webhook_secret"
                                       value="<?php echo esc_attr( get_option( 'edupro_cashfree_webhook_secret', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                                <p class="description"><?php esc_html_e( 'Leave blank to verify webhook signatures using your Cashfree Client Secret (default for Cashfree PG v2023-08-01).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        edupro_render_copyable_webhook_row(
                            rest_url( 'edupro/v1/webhooks/cashfree' ),
                            __( 'Add this URL in Cashfree Developers > Webhooks (API version 2023-08-01). Events: PAYMENT_SUCCESS_WEBHOOK, PAYMENT_FAILED_WEBHOOK, PAYMENT_USER_DROPPED_WEBHOOK, REFUND_STATUS_WEBHOOK.', 'edupro' )
                        );
                        ?>
                    </table>

                <?php elseif ( $active_tab === 'razorpay' ) : ?>
                    <h2><?php esc_html_e( 'Razorpay Settings (Backup India — INR / Optional GBP & USD)', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Razorpay', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_razorpay_enabled" value="1" <?php checked( get_option( 'edupro_razorpay_enabled', '0' ), '1' ); ?>>
                                    <?php esc_html_e( 'Enable Razorpay payments (Secondary / Backup India gateway)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sandbox / Test Mode', 'edupro' ); ?></th>
                            <td>
                                <?php $rzp_sb = get_option( 'edupro_razorpay_sandbox', get_option( 'edupro_razorpay_test_mode', '1' ) ); ?>
                                <label>
                                    <input type="checkbox" name="edupro_razorpay_sandbox" value="1" <?php checked( $rzp_sb, '1' ); ?>>
                                    <?php esc_html_e( 'Use Razorpay Test Mode (rzp_test_ keys)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_razorpay_key_id"><?php esc_html_e( 'Key ID', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_razorpay_key_id" name="edupro_razorpay_key_id"
                                       value="<?php echo esc_attr( get_option( 'edupro_razorpay_key_id', '' ) ); ?>" class="regular-text" placeholder="rzp_live_xxxxxxxxxxxxxx">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_razorpay_key_secret"><?php esc_html_e( 'Key Secret', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_razorpay_key_secret" name="edupro_razorpay_key_secret"
                                       value="<?php echo esc_attr( get_option( 'edupro_razorpay_key_secret', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_razorpay_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_razorpay_webhook_secret" name="edupro_razorpay_webhook_secret"
                                       value="<?php echo esc_attr( get_option( 'edupro_razorpay_webhook_secret', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                                <p class="description"><?php esc_html_e( 'Required to verify X-Razorpay-Signature on incoming webhooks.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        edupro_render_copyable_webhook_row(
                            rest_url( 'edupro/v1/webhooks/razorpay' ),
                            __( 'Configure in Razorpay Dashboard > Account & Settings > Webhooks. Events: payment.captured, payment.failed, refund.processed.', 'edupro' )
                        );
                        ?>
                    </table>

                <?php elseif ( $active_tab === 'stripe' ) : ?>
                    <h2><?php esc_html_e( 'Stripe Settings (UK & International — GBP / USD / INR)', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Stripe', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_stripe_enabled" value="1" <?php checked( get_option( 'edupro_stripe_enabled', '0' ), '1' ); ?>>
                                    <?php esc_html_e( 'Enable Stripe (Cards, Apple Pay, Google Pay, Klarna, Clearpay via Automatic Payment Methods)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sandbox / Test Mode', 'edupro' ); ?></th>
                            <td>
                                <?php $str_sb = get_option( 'edupro_stripe_sandbox', get_option( 'edupro_stripe_test_mode', '1' ) ); ?>
                                <label>
                                    <input type="checkbox" name="edupro_stripe_sandbox" value="1" <?php checked( $str_sb, '1' ); ?>>
                                    <?php esc_html_e( 'Use Stripe Test Mode (pk_test_ / sk_test_ keys)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_stripe_publishable_key" name="edupro_stripe_publishable_key"
                                       value="<?php echo esc_attr( get_option( 'edupro_stripe_publishable_key', '' ) ); ?>" class="regular-text" placeholder="pk_live_xxxxxxxxxxxxxx">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_stripe_secret_key"><?php esc_html_e( 'Secret Key', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_stripe_secret_key" name="edupro_stripe_secret_key"
                                       value="<?php echo esc_attr( get_option( 'edupro_stripe_secret_key', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_stripe_webhook_secret"><?php esc_html_e( 'Webhook Signing Secret', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_stripe_webhook_secret" name="edupro_stripe_webhook_secret"
                                       value="<?php echo esc_attr( get_option( 'edupro_stripe_webhook_secret', '' ) ); ?>" class="regular-text" placeholder="whsec_xxxxxxxxxxxxxx" autocomplete="new-password">
                            </td>
                        </tr>
                        <?php
                        edupro_render_copyable_webhook_row(
                            rest_url( 'edupro/v1/webhooks/stripe' ),
                            __( 'Configure in Stripe Dashboard > Developers > Webhooks. Events: payment_intent.succeeded, payment_intent.payment_failed, charge.refunded.', 'edupro' )
                        );
                        ?>
                    </table>

                <?php elseif ( $active_tab === 'paypal' ) : ?>
                    <h2><?php esc_html_e( 'PayPal REST API v2 Settings (International — GBP / USD)', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable PayPal', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_paypal_enabled" value="1" <?php checked( get_option( 'edupro_paypal_enabled', '0' ), '1' ); ?>>
                                    <?php esc_html_e( 'Enable PayPal Orders v2 checkout', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sandbox Mode', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_paypal_sandbox" value="1" <?php checked( get_option( 'edupro_paypal_sandbox', '1' ), '1' ); ?>>
                                    <?php esc_html_e( 'Use PayPal Sandbox (https://api-m.sandbox.paypal.com)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_paypal_client_id"><?php esc_html_e( 'REST Client ID', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_paypal_client_id" name="edupro_paypal_client_id"
                                       value="<?php echo esc_attr( get_option( 'edupro_paypal_client_id', '' ) ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_paypal_client_secret"><?php esc_html_e( 'REST Client Secret', 'edupro' ); ?></label></th>
                            <td>
                                <input type="password" id="edupro_paypal_client_secret" name="edupro_paypal_client_secret"
                                       value="<?php echo esc_attr( get_option( 'edupro_paypal_client_secret', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_paypal_webhook_id"><?php esc_html_e( 'Webhook ID', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_paypal_webhook_id" name="edupro_paypal_webhook_id"
                                       value="<?php echo esc_attr( get_option( 'edupro_paypal_webhook_id', '' ) ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Required for cryptographic PayPal webhook verification via /v1/notifications/verify-webhook-signature.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_paypal_email"><?php esc_html_e( 'PayPal Business Email (Reference)', 'edupro' ); ?></label></th>
                            <td>
                                <input type="email" id="edupro_paypal_email" name="edupro_paypal_email"
                                       value="<?php echo esc_attr( get_option( 'edupro_paypal_email', '' ) ); ?>" class="regular-text" placeholder="payments@yourdomain.com">
                            </td>
                        </tr>
                        <?php
                        edupro_render_copyable_webhook_row(
                            rest_url( 'edupro/v1/webhooks/paypal' ),
                            __( 'Configure in PayPal Developer Dashboard > Webhooks. Events: PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED, PAYMENT.CAPTURE.REFUNDED.', 'edupro' )
                        );
                        ?>
                    </table>

                <?php elseif ( $active_tab === 'bank_transfer' ) : ?>
                    <h2><?php esc_html_e( 'Bank Transfer Settings (UK & India — Manual Verification)', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Bank Transfer', 'edupro' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="edupro_bank_transfer_enabled" value="1" <?php checked( get_option( 'edupro_bank_transfer_enabled', '0' ), '1' ); ?>>
                                    <?php esc_html_e( 'Allow students to place pending bank transfer orders (requires manual admin approval)', 'edupro' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div class="edupro-subhead"><?php esc_html_e( 'India Bank & UPI Details (INR)', 'edupro' ); ?></div>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_bank_name"><?php esc_html_e( 'Bank Name', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_name" name="edupro_bank_name" value="<?php echo esc_attr( get_option( 'edupro_bank_name', '' ) ); ?>" class="regular-text" placeholder="HDFC Bank"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_account_name"><?php esc_html_e( 'Account Holder Name', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_account_name" name="edupro_bank_account_name" value="<?php echo esc_attr( get_option( 'edupro_bank_account_name', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_account_number"><?php esc_html_e( 'Account Number', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_account_number" name="edupro_bank_account_number" value="<?php echo esc_attr( get_option( 'edupro_bank_account_number', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_ifsc"><?php esc_html_e( 'IFSC Code', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_ifsc" name="edupro_bank_ifsc" value="<?php echo esc_attr( get_option( 'edupro_bank_ifsc', '' ) ); ?>" class="regular-text" placeholder="HDFC0001234"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_upi_id"><?php esc_html_e( 'UPI ID (Optional)', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_upi_id" name="edupro_bank_upi_id" value="<?php echo esc_attr( get_option( 'edupro_bank_upi_id', '' ) ); ?>" class="regular-text" placeholder="academy@hdfcbank"></td>
                        </tr>
                    </table>

                    <div class="edupro-subhead"><?php esc_html_e( 'UK Bank Details (GBP — Faster Payments / BACS)', 'edupro' ); ?></div>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_bank_uk_account_name"><?php esc_html_e( 'UK Account Name', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_uk_account_name" name="edupro_bank_uk_account_name" value="<?php echo esc_attr( get_option( 'edupro_bank_uk_account_name', '' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_uk_sort_code"><?php esc_html_e( 'UK Sort Code', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_uk_sort_code" name="edupro_bank_uk_sort_code" value="<?php echo esc_attr( get_option( 'edupro_bank_uk_sort_code', '' ) ); ?>" class="regular-text" placeholder="20-00-00"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_uk_account_number"><?php esc_html_e( 'UK Account Number', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_uk_account_number" name="edupro_bank_uk_account_number" value="<?php echo esc_attr( get_option( 'edupro_bank_uk_account_number', '' ) ); ?>" class="regular-text" placeholder="12345678"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_uk_iban"><?php esc_html_e( 'IBAN / SWIFT (Optional)', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_bank_uk_iban" name="edupro_bank_uk_iban" value="<?php echo esc_attr( get_option( 'edupro_bank_uk_iban', '' ) ); ?>" class="regular-text" placeholder="GB29 NWBK 6016 1331 9268 19"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_bank_instructions"><?php esc_html_e( 'Transfer Instructions', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_bank_instructions" name="edupro_bank_instructions" class="large-text" rows="5"><?php echo esc_textarea( get_option( 'edupro_bank_instructions', '' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Shown to students on checkout confirmation and included in their order email.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" name="edupro_save_payment_settings" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'edupro' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <script>
        (function() {
            document.querySelectorAll('.edupro-copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var val = btn.getAttribute('data-copy') || '';
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(val).then(function() {
                            var orig = btn.textContent;
                            btn.textContent = 'Copied!';
                            setTimeout(function() { btn.textContent = orig; }, 1600);
                        });
                    }
                });
            });
        })();
        </script>
    </div>
    <?php
}

// ============================================================
// 11. ORDERS & RECONCILIATION ADMIN PAGE + CSV EXPORT + WIDGET
// ============================================================

/**
 * Handle CSV export of orders before any admin HTML headers are emitted.
 */
function edupro_handle_orders_csv_export() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'edupro-orders' ) {
        return;
    }
    if ( ! isset( $_GET['edupro_export_csv'] ) || $_GET['edupro_export_csv'] !== '1' ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized.', 'edupro' ) );
    }
    check_admin_referer( 'edupro_export_orders_csv' );

    global $wpdb;
    $table = $wpdb->prefix . 'edupro_orders';
    $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=edupro-orders-' . gmdate( 'Y-m-d-His' ) . '.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array(
        'Order ID', 'User ID', 'Student Email', 'Course ID', 'Course Title',
        'Currency', 'Subtotal', 'Discount', 'Coupon', 'Tax', 'Total',
        'Gateway', 'Gateway Order ID', 'Transaction ID', 'Status',
        'Verified Via', 'Verified At', 'Refund ID', 'Refund Amount',
        'Failure Reason', 'Created At',
    ) );

    foreach ( $rows as $row ) {
        $u = get_userdata( (int) $row['user_id'] );
        fputcsv( $out, array(
            $row['order_id'],
            $row['user_id'],
            $u ? $u->user_email : '',
            $row['course_id'],
            wp_strip_all_tags( get_the_title( (int) $row['course_id'] ) ),
            $row['currency'],
            $row['subtotal'],
            $row['discount_amount'],
            $row['coupon_code'],
            $row['tax_amount'],
            $row['amount'],
            $row['gateway'],
            $row['gateway_order_id'],
            $row['transaction_id'],
            $row['status'],
            $row['verified_via'],
            $row['verified_at'],
            $row['refund_id'],
            $row['refund_amount'],
            $row['failure_reason'],
            $row['created_at'],
        ) );
    }

    fclose( $out );
    exit;
}
add_action( 'admin_init', 'edupro_handle_orders_csv_export' );

/**
 * Dashboard payment overview widget (revenue grouped by currency).
 */
function edupro_payment_dashboard_widget() {
    global $wpdb;
    $table = $wpdb->prefix . 'edupro_orders';

    $total_orders     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status != 'superseded'" );
    $completed_orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" );
    $pending_orders   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
    $rev_rows         = $wpdb->get_results( "SELECT currency, COALESCE(SUM(amount), 0) AS total FROM {$table} WHERE status = 'completed' GROUP BY currency" );
    ?>
    <ul style="margin:0; padding:0; list-style:none;">
        <li style="padding: 6px 0; border-bottom: 1px solid #eee;">
            <strong><?php esc_html_e( 'Total Orders:', 'edupro' ); ?></strong>
            <span style="float:right;"><?php echo esc_html( $total_orders ); ?></span>
        </li>
        <li style="padding: 6px 0; border-bottom: 1px solid #eee;">
            <strong style="color:#155724;"><?php esc_html_e( 'Completed:', 'edupro' ); ?></strong>
            <span style="float:right; color:#155724;"><?php echo esc_html( $completed_orders ); ?></span>
        </li>
        <li style="padding: 6px 0; border-bottom: 1px solid #eee;">
            <strong style="color:#856404;"><?php esc_html_e( 'Pending:', 'edupro' ); ?></strong>
            <span style="float:right; color:#856404;"><?php echo esc_html( $pending_orders ); ?></span>
        </li>
        <?php if ( ! empty( $rev_rows ) ) : ?>
            <?php foreach ( $rev_rows as $rev ) : ?>
                <li style="padding: 6px 0; border-bottom: 1px solid #eee;">
                    <strong><?php echo esc_html( sprintf( __( 'Revenue (%s):', 'edupro' ), strtoupper( $rev->currency ) ) ); ?></strong>
                    <span style="float:right; color:#e31b23; font-weight:700;">
                        <?php echo esc_html( edupro_payment_format_amount( $rev->total, $rev->currency ) ); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        <?php else : ?>
            <li style="padding: 6px 0;">
                <strong><?php esc_html_e( 'Revenue:', 'edupro' ); ?></strong>
                <span style="float:right;">—</span>
            </li>
        <?php endif; ?>
    </ul>
    <p style="margin: 12px 0 0; display:flex; gap:8px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=edupro-orders' ) ); ?>" class="button button-small button-primary">
            <?php esc_html_e( 'Orders & Reconciliation', 'edupro' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=edupro-payment-gateways' ) ); ?>" class="button button-small">
            <?php esc_html_e( 'Gateway Settings', 'edupro' ); ?>
        </a>
    </p>
    <?php
}

function edupro_add_payment_dashboard_widget() {
    if ( current_user_can( 'manage_options' ) ) {
        wp_add_dashboard_widget(
            'edupro_payment_stats',
            __( 'EduPro – Payment Overview', 'edupro' ),
            'edupro_payment_dashboard_widget'
        );
    }
}
add_action( 'wp_dashboard_setup', 'edupro_add_payment_dashboard_widget' );

function edupro_register_orders_page() {
    add_menu_page(
        __( 'EduPro Orders & Reconciliation', 'edupro' ),
        __( 'Orders', 'edupro' ),
        'manage_options',
        'edupro-orders',
        'edupro_orders_admin_page',
        'dashicons-cart',
        26
    );
}
add_action( 'admin_menu', 'edupro_register_orders_page' );

/**
 * Render the Orders & Reconciliation admin page.
 */
function edupro_orders_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'edupro_orders';
    $notices = array();

    // Handle POST actions (Reconcile, Approve Bank Transfer, Refund, Bulk Reconcile Pending)
    if ( isset( $_POST['edupro_order_action'], $_POST['edupro_order_action_nonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_order_action_nonce'] ) ), 'edupro_order_admin_action' )
    ) {
        $action   = sanitize_key( wp_unslash( $_POST['edupro_order_action'] ) );
        $order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ?? '' ) );

        if ( $action === 'reconcile' && $order_id !== '' ) {
            $res = edupro_reconcile_order( $order_id );
            if ( is_wp_error( $res ) ) {
                $notices[] = array( 'type' => 'error', 'text' => sprintf( __( 'Reconciliation failed for %1$s: %2$s', 'edupro' ), $order_id, $res->get_error_message() ) );
            } else {
                $notices[] = array( 'type' => 'success', 'text' => sprintf( __( 'Order %1$s reconciled: %2$s → %3$s (%4$s)', 'edupro' ), $order_id, $res['previous_status'], $res['status'], $res['message'] ) );
            }
        } elseif ( $action === 'approve_bank' && $order_id !== '' ) {
            $order = edupro_get_order( $order_id );
            if ( $order && $order->gateway === 'bank_transfer' && $order->status === 'pending' ) {
                $ref = 'BANK-APPROVED-' . time();
                edupro_complete_verified_order( $order_id, $ref, 'admin_manual_approval', array( 'approved_by' => get_current_user_id() ) );
                $notices[] = array( 'type' => 'success', 'text' => sprintf( __( 'Bank transfer order %s approved and student enrolled.', 'edupro' ), $order_id ) );
            } else {
                $notices[] = array( 'type' => 'error', 'text' => __( 'Order is not a pending bank transfer.', 'edupro' ) );
            }
        } elseif ( $action === 'refund' && $order_id !== '' ) {
            $unenroll = ! empty( $_POST['refund_unenroll'] );
            $reason   = sanitize_text_field( wp_unslash( $_POST['refund_reason'] ?? 'Admin refund' ) );
            $res      = edupro_refund_order( $order_id, null, $reason, $unenroll );
            if ( is_wp_error( $res ) ) {
                $notices[] = array( 'type' => 'error', 'text' => sprintf( __( 'Refund failed for %1$s: %2$s', 'edupro' ), $order_id, $res->get_error_message() ) );
            } else {
                $notices[] = array( 'type' => 'success', 'text' => sprintf( __( 'Refund %1$s issued for order %2$s.', 'edupro' ), $res['refund_id'], $order_id ) );
            }
        } elseif ( $action === 'bulk_reconcile' ) {
            $pending_ids = $wpdb->get_col( "SELECT order_id FROM {$table} WHERE status = 'pending' AND gateway IN ('cashfree','razorpay','stripe','paypal') ORDER BY created_at DESC LIMIT 30" );
            $checked     = 0;
            $completed   = 0;
            $errors      = 0;
            foreach ( $pending_ids as $pid ) {
                $checked++;
                $r = edupro_reconcile_order( $pid );
                if ( is_wp_error( $r ) ) {
                    $errors++;
                } elseif ( $r['status'] === 'completed' ) {
                    $completed++;
                }
            }
            $notices[] = array(
                'type' => 'info',
                'text' => sprintf(
                    __( 'Bulk reconciliation complete: checked %1$d pending orders, %2$d newly completed, %3$d errors.', 'edupro' ),
                    $checked,
                    $completed,
                    $errors
                ),
            );
        }
    }

    // Filters
    $per_page       = 25;
    $current_pg     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $offset         = ( $current_pg - 1 ) * $per_page;
    $status_filter  = isset( $_GET['status'] )  ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $gateway_filter = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '';
    $search_query   = isset( $_GET['s'] )       ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

    $valid_statuses = array( 'pending', 'processing', 'completed', 'failed', 'refunded', 'superseded' );
    $where          = array( '1=1' );
    $params         = array();

    if ( $status_filter !== '' && in_array( $status_filter, $valid_statuses, true ) ) {
        $where[]  = 'status = %s';
        $params[] = $status_filter;
    } else {
        $status_filter = '';
        $where[]       = "status != 'superseded'";
    }

    if ( $gateway_filter !== '' ) {
        $where[]  = 'gateway = %s';
        $params[] = $gateway_filter;
    }

    if ( $search_query !== '' ) {
        $like     = '%' . $wpdb->esc_like( $search_query ) . '%';
        $where[]  = '(order_id LIKE %s OR transaction_id LIKE %s OR gateway_order_id LIKE %s OR coupon_code LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where_sql = implode( ' AND ', $where );

    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total     = empty( $params ) ? (int) $wpdb->get_var( $count_sql ) : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

    $list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $orders   = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

    $total_pages   = max( 1, (int) ceil( $total / $per_page ) );
    $csv_url       = wp_nonce_url( add_query_arg( array( 'page' => 'edupro-orders', 'edupro_export_csv' => '1' ), admin_url( 'admin.php' ) ), 'edupro_export_orders_csv' );
    $status_styles = array(
        'pending'    => array( 'color' => '#856404', 'bg' => '#fff3cd' ),
        'processing' => array( 'color' => '#0c5460', 'bg' => '#d1ecf1' ),
        'completed'  => array( 'color' => '#155724', 'bg' => '#d4edda' ),
        'failed'     => array( 'color' => '#721c24', 'bg' => '#f8d7da' ),
        'refunded'   => array( 'color' => '#004085', 'bg' => '#cce5ff' ),
        'superseded' => array( 'color' => '#555555', 'bg' => '#e2e3e5' ),
    );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'EduPro Orders & Reconciliation', 'edupro' ); ?></h1>

        <form method="post" style="display:inline-block; margin-left:12px;">
            <?php wp_nonce_field( 'edupro_order_admin_action', 'edupro_order_action_nonce' ); ?>
            <input type="hidden" name="edupro_order_action" value="bulk_reconcile">
            <button type="submit" class="page-title-action"><?php esc_html_e( 'Bulk Reconcile Pending', 'edupro' ); ?></button>
        </form>
        <a href="<?php echo esc_url( $csv_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'edupro' ); ?></a>
        <hr class="wp-header-end">

        <?php foreach ( $notices as $n ) : ?>
            <div class="notice notice-<?php echo esc_attr( $n['type'] ); ?> is-dismissible">
                <p><?php echo esc_html( $n['text'] ); ?></p>
            </div>
        <?php endforeach; ?>

        <ul class="subsubsub">
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=edupro-orders' ) ); ?>" <?php echo $status_filter === '' ? 'class="current"' : ''; ?>><?php esc_html_e( 'All Active', 'edupro' ); ?></a> |</li>
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'pending', admin_url( 'admin.php?page=edupro-orders' ) ) ); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Pending', 'edupro' ); ?></a> |</li>
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'completed', admin_url( 'admin.php?page=edupro-orders' ) ) ); ?>" <?php echo $status_filter === 'completed' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Completed', 'edupro' ); ?></a> |</li>
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'failed', admin_url( 'admin.php?page=edupro-orders' ) ) ); ?>" <?php echo $status_filter === 'failed' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Failed', 'edupro' ); ?></a> |</li>
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'refunded', admin_url( 'admin.php?page=edupro-orders' ) ) ); ?>" <?php echo $status_filter === 'refunded' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Refunded', 'edupro' ); ?></a> |</li>
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'superseded', admin_url( 'admin.php?page=edupro-orders' ) ) ); ?>" <?php echo $status_filter === 'superseded' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Superseded', 'edupro' ); ?></a></li>
        </ul>

        <form method="get" style="float:right; margin: 6px 0 12px; display:flex; gap:6px; align-items:center;">
            <input type="hidden" name="page" value="edupro-orders">
            <?php if ( $status_filter !== '' ) : ?>
                <input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
            <?php endif; ?>
            <select name="gateway">
                <option value=""><?php esc_html_e( 'All Gateways', 'edupro' ); ?></option>
                <option value="cashfree" <?php selected( $gateway_filter, 'cashfree' ); ?>>Cashfree</option>
                <option value="razorpay" <?php selected( $gateway_filter, 'razorpay' ); ?>>Razorpay</option>
                <option value="stripe" <?php selected( $gateway_filter, 'stripe' ); ?>>Stripe</option>
                <option value="paypal" <?php selected( $gateway_filter, 'paypal' ); ?>>PayPal</option>
                <option value="bank_transfer" <?php selected( $gateway_filter, 'bank_transfer' ); ?>>Bank Transfer</option>
                <option value="free" <?php selected( $gateway_filter, 'free' ); ?>>Free</option>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Order / Txn / Coupon…', 'edupro' ); ?>">
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'edupro' ); ?></button>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:14%;"><?php esc_html_e( 'Order ID', 'edupro' ); ?></th>
                    <th style="width:13%;"><?php esc_html_e( 'Student', 'edupro' ); ?></th>
                    <th style="width:13%;"><?php esc_html_e( 'Course', 'edupro' ); ?></th>
                    <th style="width:11%;"><?php esc_html_e( 'Amount', 'edupro' ); ?></th>
                    <th style="width:9%;"><?php esc_html_e( 'Gateway', 'edupro' ); ?></th>
                    <th style="width:11%;"><?php esc_html_e( 'Txn ID', 'edupro' ); ?></th>
                    <th style="width:9%;"><?php esc_html_e( 'Verified Via', 'edupro' ); ?></th>
                    <th style="width:8%;"><?php esc_html_e( 'Status', 'edupro' ); ?></th>
                    <th style="width:12%;"><?php esc_html_e( 'Actions', 'edupro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $orders ) ) : ?>
                    <tr><td colspan="9" style="text-align:center; padding:20px;"><?php esc_html_e( 'No orders match the current filter.', 'edupro' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $orders as $order ) : ?>
                        <?php
                        $user  = get_userdata( (int) $order->user_id );
                        $style = $status_styles[ $order->status ] ?? array( 'color' => '#333', 'bg' => '#eee' );
                        $cur   = strtoupper( (string) ( $order->currency ?: 'INR' ) );
                        ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html( $order->order_id ); ?></code><br>
                                <small style="color:#666;"><?php echo esc_html( date_i18n( 'd M Y, H:i', strtotime( $order->created_at ) ) ); ?></small>
                            </td>
                            <td>
                                <?php if ( $user ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a><br>
                                    <small><?php echo esc_html( $user->user_email ); ?></small>
                                <?php else : ?>
                                    <?php echo esc_html( '#' . $order->user_id ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $order->course_id ) ); ?>">
                                    <?php echo esc_html( get_the_title( $order->course_id ) ?: '#' . $order->course_id ); ?>
                                </a>
                            </td>
                            <td>
                                <strong><?php echo esc_html( edupro_payment_format_amount( $order->amount, $cur ) ); ?></strong>
                                <small style="color:#666;">(<?php echo esc_html( $cur ); ?>)</small>
                                <?php if ( ! empty( $order->coupon_code ) ) : ?>
                                    <br><span style="background:#eef2ff; color:#3730a3; padding:1px 5px; border-radius:3px; font-size:11px;"><?php echo esc_html( $order->coupon_code ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $order->gateway ) ) ); ?></td>
                            <td>
                                <?php if ( ! empty( $order->transaction_id ) ) : ?>
                                    <small><code><?php echo esc_html( strlen( $order->transaction_id ) > 22 ? substr( $order->transaction_id, 0, 22 ) . '…' : $order->transaction_id ); ?></code></small>
                                <?php elseif ( ! empty( $order->gateway_order_id ) ) : ?>
                                    <small style="color:#777;" title="<?php esc_attr_e( 'Gateway Order ID', 'edupro' ); ?>"><?php echo esc_html( substr( $order->gateway_order_id, 0, 20 ) ); ?></small>
                                <?php else : ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo $order->verified_via ? esc_html( $order->verified_via ) : '—'; ?></small>
                            </td>
                            <td>
                                <span style="display:inline-block; padding:2px 9px; border-radius:12px; font-size:11px; font-weight:600; color:<?php echo esc_attr( $style['color'] ); ?>; background:<?php echo esc_attr( $style['bg'] ); ?>;">
                                    <?php echo esc_html( ucfirst( $order->status ) ); ?>
                                </span>
                                <?php if ( ! empty( $order->failure_reason ) && $order->status === 'failed' ) : ?>
                                    <br><small style="color:#721c24;" title="<?php echo esc_attr( $order->failure_reason ); ?>"><?php echo esc_html( substr( $order->failure_reason, 0, 32 ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                                    <?php if ( in_array( $order->gateway, array( 'cashfree', 'razorpay', 'stripe', 'paypal' ), true ) && in_array( $order->status, array( 'pending', 'processing', 'failed' ), true ) ) : ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field( 'edupro_order_admin_action', 'edupro_order_action_nonce' ); ?>
                                            <input type="hidden" name="edupro_order_action" value="reconcile">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->order_id ); ?>">
                                            <button type="submit" class="button button-small"><?php esc_html_e( 'Reconcile', 'edupro' ); ?></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ( $order->gateway === 'bank_transfer' && $order->status === 'pending' ) : ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Confirm bank transfer funds have been received and enroll this student?', 'edupro' ) ); ?>');">
                                            <?php wp_nonce_field( 'edupro_order_admin_action', 'edupro_order_action_nonce' ); ?>
                                            <input type="hidden" name="edupro_order_action" value="approve_bank">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->order_id ); ?>">
                                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'edupro' ); ?></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ( $order->status === 'completed' && $order->gateway !== 'free' ) : ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Issue a full refund via the payment gateway for this order?', 'edupro' ) ); ?>');">
                                            <?php wp_nonce_field( 'edupro_order_admin_action', 'edupro_order_action_nonce' ); ?>
                                            <input type="hidden" name="edupro_order_action" value="refund">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->order_id ); ?>">
                                            <label style="font-size:11px; margin-right:3px;" title="<?php esc_attr_e( 'Also remove student course access', 'edupro' ); ?>">
                                                <input type="checkbox" name="refund_unenroll" value="1" checked> <?php esc_html_e( 'Unenroll', 'edupro' ); ?>
                                            </label>
                                            <button type="submit" class="button button-small"><?php esc_html_e( 'Refund', 'edupro' ); ?></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ( function_exists( 'edupro_get_invoice_url' ) && in_array( $order->status, array( 'completed', 'refunded' ), true ) ) : ?>
                                        <a href="<?php echo esc_url( edupro_get_invoice_url( $order->order_id ) ); ?>" target="_blank" rel="noopener" class="button button-small">
                                            <?php esc_html_e( 'Invoice', 'edupro' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post( paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_pg,
                    ) ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $recent_logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}edupro_payment_logs ORDER BY id DESC LIMIT 20" );
        if ( ! empty( $recent_logs ) ) :
        ?>
            <h2 style="margin-top:28px;"><?php esc_html_e( 'Recent Payment & Webhook Audit Logs', 'edupro' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:14%;"><?php esc_html_e( 'Time', 'edupro' ); ?></th>
                        <th style="width:16%;"><?php esc_html_e( 'Order ID', 'edupro' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'Gateway', 'edupro' ); ?></th>
                        <th style="width:18%;"><?php esc_html_e( 'Event', 'edupro' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'Status', 'edupro' ); ?></th>
                        <th style="width:32%;"><?php esc_html_e( 'Summary', 'edupro' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_logs as $log ) : ?>
                        <tr>
                            <td><small><?php echo esc_html( $log->created_at ); ?></small></td>
                            <td><small><code><?php echo esc_html( $log->order_id ?: '—' ); ?></code></small></td>
                            <td><small><?php echo esc_html( $log->gateway ); ?></small></td>
                            <td><small><code><?php echo esc_html( $log->event_type ); ?></code></small></td>
                            <td><small><?php echo esc_html( $log->status ); ?></small></td>
                            <td><small><?php echo esc_html( $log->payload_summary ); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
