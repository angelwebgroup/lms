<?php
/**
 * EduPro Coupon & Discount Code System
 *
 * Manages custom `wp_edupro_coupons` table, server-side coupon validation,
 * atomic usage counters, AJAX checkout coupon application, and Admin CRUD UI.
 *
 * @package EduPro
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EDUPRO_COUPONS_DB_VERSION' ) ) {
	define( 'EDUPRO_COUPONS_DB_VERSION', '1.0.0' );
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. DATABASE TABLE CREATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return the full prefixed name of the coupons database table.
 *
 * @return string
 */
function edupro_get_coupons_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'edupro_coupons';
}

/**
 * Create or upgrade the `wp_edupro_coupons` table using dbDelta.
 */
function edupro_create_coupons_table() {
	global $wpdb;

	$table_name      = edupro_get_coupons_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(64) NOT NULL,
		discount_type varchar(20) NOT NULL DEFAULT 'percent',
		discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
		currency varchar(10) NOT NULL DEFAULT '',
		min_amount decimal(10,2) NOT NULL DEFAULT 0.00,
		max_uses int(11) unsigned NOT NULL DEFAULT 0,
		used_count int(11) unsigned NOT NULL DEFAULT 0,
		course_ids text NULL,
		expires_at datetime NULL DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code),
		KEY status (status)
	) {$charset_collate};";

	dbDelta( $sql );
	update_option( 'edupro_coupons_db_version', EDUPRO_COUPONS_DB_VERSION );
}
add_action( 'after_switch_theme', 'edupro_create_coupons_table' );

/**
 * Ensure the coupons table exists and matches the current schema version.
 */
function edupro_maybe_create_coupons_table() {
	$installed = get_option( 'edupro_coupons_db_version', '' );
	if ( EDUPRO_COUPONS_DB_VERSION !== $installed ) {
		edupro_create_coupons_table();
	}
}
add_action( 'admin_init', 'edupro_maybe_create_coupons_table' );

// ─────────────────────────────────────────────────────────────────────────────
// 2. COUPON LOOKUP, VALIDATION & ATOMIC USAGE TRACKING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalize a coupon code to uppercase alphanumeric + dash/underscore.
 *
 * @param string $code Raw coupon code.
 * @return string
 */
function edupro_normalize_coupon_code( $code ) {
	return preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( trim( (string) $code ) ) );
}

/**
 * Fetch a coupon row by its code.
 *
 * @param string $code Coupon code.
 * @return object|null Database row object or null if not found.
 */
function edupro_get_coupon_by_code( $code ) {
	global $wpdb;

	$normalized = edupro_normalize_coupon_code( $code );
	if ( '' === $normalized ) {
		return null;
	}

	edupro_maybe_create_coupons_table();
	$table = edupro_get_coupons_table_name();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE code = %s LIMIT 1",
			$normalized
		)
	);
}

/**
 * Parse a coupon's `course_ids` column (supports JSON array or CSV).
 *
 * @param string|null $raw Stored `course_ids` column value.
 * @return int[] Array of allowed course post IDs (empty means all courses).
 */
function edupro_parse_coupon_course_ids( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return array();
	}

	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) ) {
		return array_values( array_unique( array_filter( array_map( 'absint', $decoded ) ) ) );
	}

	$parts = explode( ',', $raw );
	return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
}

/**
 * Validate a coupon code against a course (or array of cart course IDs), subtotal, and currency.
 *
 * @param string    $code             Coupon code entered by the student.
 * @param int|int[] $course_id_or_ids Single course post ID or array of cart course IDs.
 * @param float     $subtotal         Pre-discount subtotal in `$currency`.
 * @param string    $currency         Optional ISO currency code (defaults to active currency).
 * @return array{
 *     id: int,
 *     code: string,
 *     discount_type: string,
 *     discount_value: float,
 *     discount_amount: float,
 *     final_total: float,
 *     currency: string
 * }|WP_Error Validated discount breakdown or WP_Error on failure.
 */
function edupro_validate_coupon( $code, $course_id_or_ids, $subtotal, $currency = '' ) {
	$normalized = edupro_normalize_coupon_code( $code );
	if ( is_array( $course_id_or_ids ) ) {
		$target_course_ids = array_values( array_unique( array_filter( array_map( 'absint', $course_id_or_ids ) ) ) );
	} else {
		$single_id         = absint( $course_id_or_ids );
		$target_course_ids = $single_id ? array( $single_id ) : array();
	}
	$subtotal = max( 0.0, round( (float) $subtotal, 2 ) );

	if ( '' === $normalized ) {
		return new WP_Error( 'coupon_empty', __( 'Please enter a coupon code.', 'edupro' ) );
	}

	$target_currency = $currency
		? ( function_exists( 'edupro_sanitize_currency_code' ) ? edupro_sanitize_currency_code( $currency ) : strtoupper( sanitize_text_field( $currency ) ) )
		: ( function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR' );

	if ( ! $target_currency ) {
		$target_currency = 'INR';
	}

	$coupon = edupro_get_coupon_by_code( $normalized );
	if ( ! $coupon ) {
		return new WP_Error( 'coupon_invalid', __( 'Invalid coupon code. Please check and try again.', 'edupro' ) );
	}

	// 1. Active status check.
	if ( 'active' !== $coupon->status ) {
		return new WP_Error( 'coupon_inactive', __( 'This coupon is currently inactive.', 'edupro' ) );
	}

	// 2. Expiration check.
	if ( ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at ) {
		$expires_ts = strtotime( $coupon->expires_at );
		if ( $expires_ts && current_time( 'timestamp' ) > $expires_ts ) {
			return new WP_Error( 'coupon_expired', __( 'This coupon code has expired.', 'edupro' ) );
		}
	}

	// 3. Usage limit check.
	$max_uses   = absint( $coupon->max_uses );
	$used_count = absint( $coupon->used_count );
	if ( $max_uses > 0 && $used_count >= $max_uses ) {
		return new WP_Error( 'coupon_limit_reached', __( 'This coupon has reached its maximum usage limit.', 'edupro' ) );
	}

	// 4. Currency restriction check.
	$coupon_currency = strtoupper( trim( (string) $coupon->currency ) );
	if ( '' !== $coupon_currency && $coupon_currency !== $target_currency ) {
		return new WP_Error(
			'coupon_currency_mismatch',
			sprintf(
				/* translators: %s: required currency code */
				__( 'This coupon is only valid for %s orders.', 'edupro' ),
				$coupon_currency
			)
		);
	}

	// 5. Course restriction check (allows if at least one course in the order/cart matches).
	$allowed_courses = edupro_parse_coupon_course_ids( $coupon->course_ids );
	if ( ! empty( $allowed_courses ) && ( empty( $target_course_ids ) || empty( array_intersect( $target_course_ids, $allowed_courses ) ) ) ) {
		$msg = count( $target_course_ids ) > 1
			? __( 'This coupon is not valid for any of the courses in your cart.', 'edupro' )
			: __( 'This coupon is not valid for the selected course.', 'edupro' );
		return new WP_Error( 'coupon_course_mismatch', $msg );
	}

	// 6. Minimum amount check.
	$min_amount = round( (float) $coupon->min_amount, 2 );
	if ( $min_amount > 0 && $subtotal < $min_amount ) {
		$formatted_min = function_exists( 'edupro_format_price' )
			? edupro_format_price( $min_amount, $target_currency )
			: number_format( $min_amount, 2 );

		return new WP_Error(
			'coupon_min_amount',
			sprintf(
				/* translators: %s: formatted minimum order amount */
				__( 'A minimum order of %s is required to use this coupon.', 'edupro' ),
				$formatted_min
			)
		);
	}

	// 7. Compute discount.
	$discount_type  = ( 'fixed' === $coupon->discount_type ) ? 'fixed' : 'percent';
	$discount_value = max( 0.0, round( (float) $coupon->discount_value, 2 ) );

	if ( 'percent' === $discount_type ) {
		$clamped_pct     = min( 100.0, $discount_value );
		$discount_amount = round( $subtotal * ( $clamped_pct / 100.0 ), 2 );
	} else {
		$discount_amount = round( min( $subtotal, $discount_value ), 2 );
	}

	$discount_amount = max( 0.0, min( $subtotal, $discount_amount ) );
	$final_total     = max( 0.0, round( $subtotal - $discount_amount, 2 ) );

	return array(
		'id'              => (int) $coupon->id,
		'code'            => $coupon->code,
		'discount_type'   => $discount_type,
		'discount_value'  => $discount_value,
		'discount_amount' => $discount_amount,
		'final_total'     => $final_total,
		'currency'        => $target_currency,
	);
}

/**
 * Atomically increment a coupon's usage count in the database.
 *
 * Uses a conditional SQL UPDATE so concurrent redemptions cannot exceed `max_uses`.
 *
 * @param string $code Coupon code.
 * @return bool True if the usage counter was incremented, false otherwise.
 */
function edupro_increment_coupon_usage( $code ) {
	global $wpdb;

	$normalized = edupro_normalize_coupon_code( $code );
	if ( '' === $normalized ) {
		return false;
	}

	edupro_maybe_create_coupons_table();
	$table = edupro_get_coupons_table_name();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table}
			 SET used_count = used_count + 1
			 WHERE code = %s
			   AND ( max_uses = 0 OR used_count < max_uses )",
			$normalized
		)
	);

	return (int) $updated > 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. AJAX COUPON APPLICATION HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler for applying a coupon on checkout (`edupro_apply_coupon`).
 */
function edupro_ajax_apply_coupon() {
	$nonce = '';
	if ( isset( $_POST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
	} elseif ( isset( $_POST['security'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
	}

	$valid_nonce = (
		wp_verify_nonce( $nonce, 'edupro_nonce' ) ||
		wp_verify_nonce( $nonce, 'edupro_payment_nonce' )
	);

	if ( ! $valid_nonce ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ),
			403
		);
	}

	$raw_code = '';
	if ( isset( $_POST['coupon_code'] ) ) {
		$raw_code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );
	} elseif ( isset( $_POST['code'] ) ) {
		$raw_code = sanitize_text_field( wp_unslash( $_POST['code'] ) );
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$cart_mode = isset( $_POST['cart_mode'] ) && in_array( (string) $_POST['cart_mode'], array( '1', 'true', 'yes' ), true );
	$currency  = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

	if ( $cart_mode && ! $course_id ) {
		if ( ! function_exists( 'edupro_get_cart_summary' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cart system is unavailable.', 'edupro' ) ), 500 );
		}

		$cart_summary = edupro_get_cart_summary( $currency );
		if ( empty( $cart_summary['items'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Your cart is empty.', 'edupro' ) ), 400 );
		}

		$course_target = array_map(
			function ( $item ) {
				return absint( $item['course_id'] );
			},
			$cart_summary['items']
		);
		$subtotal = (float) $cart_summary['subtotal'];
		$currency = $cart_summary['currency'];
	} else {
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course specified.', 'edupro' ) ), 400 );
		}

		$course_target = $course_id;
		if ( function_exists( 'edupro_get_course_price_data' ) ) {
			$price_data = edupro_get_course_price_data( $course_id, $currency );
			$subtotal   = (float) $price_data['effective'];
			$currency   = $price_data['currency'];
		} else {
			$sale     = (float) get_post_meta( $course_id, '_edupro_sale_price', true );
			$regular  = (float) get_post_meta( $course_id, '_edupro_regular_price', true );
			$subtotal = ( $sale > 0 && $sale < $regular ) ? $sale : $regular;
			$currency = $currency ? strtoupper( $currency ) : 'INR';
		}
	}

	if ( $subtotal <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Coupons are not needed for free courses.', 'edupro' ) ), 400 );
	}

	$result = edupro_validate_coupon( $raw_code, $course_target, $subtotal, $currency );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	// Calculate tax on the discounted subtotal for an accurate checkout summary.
	$tax_rate   = max( 0.0, (float) get_option( 'edupro_tax_rate', 0 ) );
	$tax_label  = (string) get_option( 'edupro_tax_label', 'VAT / GST' );
	$discounted = (float) $result['final_total'];
	$tax_amount = $tax_rate > 0 ? round( $discounted * ( $tax_rate / 100.0 ), 2 ) : 0.0;
	$grand      = round( $discounted + $tax_amount, 2 );

	$fmt = function ( $amt ) use ( $currency ) {
		return function_exists( 'edupro_format_price' )
			? edupro_format_price( $amt, $currency )
			: number_format( (float) $amt, 2 );
	};

	wp_send_json_success(
		array(
			'code'                      => $result['code'],
			'discount_type'             => $result['discount_type'],
			'discount_value'            => $result['discount_value'],
			'subtotal'                  => $subtotal,
			'discount_amount'           => $result['discount_amount'],
			'discounted_subtotal'       => $discounted,
			'tax_rate'                  => $tax_rate,
			'tax_label'                 => $tax_label,
			'tax_amount'                => $tax_amount,
			'final_total'               => $grand,
			'currency'                  => $currency,
			'symbol'                    => function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol( $currency ) : '₹',
			'subtotal_formatted'        => $fmt( $subtotal ),
			'discount_formatted'        => '-' . $fmt( $result['discount_amount'] ),
			'tax_formatted'             => $fmt( $tax_amount ),
			'final_total_formatted'     => $fmt( $grand ),
			'message'                   => sprintf(
				/* translators: 1: coupon code, 2: formatted discount amount */
				__( 'Coupon "%1$s" applied! You save %2$s.', 'edupro' ),
				$result['code'],
				$fmt( $result['discount_amount'] )
			),
		)
	);
}
add_action( 'wp_ajax_edupro_apply_coupon', 'edupro_ajax_apply_coupon' );
add_action( 'wp_ajax_nopriv_edupro_apply_coupon', 'edupro_ajax_apply_coupon' );

// ─────────────────────────────────────────────────────────────────────────────
// 4. ADMIN SUBMENU UNDER COURSES: COUPONS CRUD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register the Coupons submenu page under Courses (`lms_course`).
 */
function edupro_register_coupons_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=lms_course',
		__( 'Coupons', 'edupro' ),
		__( 'Coupons', 'edupro' ),
		'manage_options',
		'edupro-coupons',
		'edupro_render_coupons_admin_page'
	);
}
add_action( 'admin_menu', 'edupro_register_coupons_admin_menu' );

/**
 * Process admin CRUD form submissions (Create / Toggle Status / Delete).
 *
 * @return array{type: string, message: string}|null Notice payload or null.
 */
function edupro_handle_coupons_admin_actions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return null;
	}

	global $wpdb;
	edupro_maybe_create_coupons_table();
	$table = edupro_get_coupons_table_name();

	// Create coupon (POST).
	if ( isset( $_POST['edupro_coupon_action'] ) && 'create' === $_POST['edupro_coupon_action'] ) {
		check_admin_referer( 'edupro_create_coupon', 'edupro_coupon_nonce' );

		$code           = isset( $_POST['coupon_code'] ) ? edupro_normalize_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$discount_type  = isset( $_POST['discount_type'] ) && 'fixed' === $_POST['discount_type'] ? 'fixed' : 'percent';
		$discount_value = isset( $_POST['discount_value'] ) ? max( 0.0, round( (float) $_POST['discount_value'], 2 ) ) : 0.0;
		$raw_currency   = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
		$currency       = '' === $raw_currency
			? ''
			: ( function_exists( 'edupro_sanitize_currency_code' ) ? edupro_sanitize_currency_code( $raw_currency ) : strtoupper( $raw_currency ) );
		$min_amount     = isset( $_POST['min_amount'] ) ? max( 0.0, round( (float) $_POST['min_amount'], 2 ) ) : 0.0;
		$max_uses       = isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 0;
		$status         = isset( $_POST['status'] ) && 'inactive' === $_POST['status'] ? 'inactive' : 'active';

		// Course restrictions.
		$course_ids_arr = array();
		if ( isset( $_POST['course_ids'] ) && is_array( $_POST['course_ids'] ) ) {
			$course_ids_arr = array_values( array_unique( array_filter( array_map( 'absint', $_POST['course_ids'] ) ) ) );
		}
		$course_ids_json = ! empty( $course_ids_arr ) ? wp_json_encode( $course_ids_arr ) : '';

		// Expiration date.
		$expires_raw = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$expires_at  = null;
		if ( '' !== $expires_raw ) {
			$ts = strtotime( $expires_raw . ' 23:59:59' );
			if ( $ts ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		if ( '' === $code ) {
			return array( 'type' => 'error', 'message' => __( 'Coupon code is required (letters, numbers, dashes, or underscores).', 'edupro' ) );
		}
		if ( $discount_value <= 0 ) {
			return array( 'type' => 'error', 'message' => __( 'Discount value must be greater than zero.', 'edupro' ) );
		}
		if ( 'percent' === $discount_type && $discount_value > 100 ) {
			return array( 'type' => 'error', 'message' => __( 'Percentage discount cannot exceed 100%.', 'edupro' ) );
		}
		if ( edupro_get_coupon_by_code( $code ) ) {
			return array(
				'type'    => 'error',
				'message' => sprintf( __( 'Coupon code "%s" already exists.', 'edupro' ), $code ),
			);
		}

		$data = array(
			'code'           => $code,
			'discount_type'  => $discount_type,
			'discount_value' => $discount_value,
			'currency'       => $currency,
			'min_amount'     => $min_amount,
			'max_uses'       => $max_uses,
			'used_count'     => 0,
			'course_ids'     => $course_ids_json,
			'expires_at'     => $expires_at,
			'status'         => $status,
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%f', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s' );
		if ( null === $expires_at ) {
			unset( $data['expires_at'] );
			$formats = array( '%s', '%s', '%f', '%s', '%f', '%d', '%d', '%s', '%s', '%s' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $data, $formats );
		if ( false === $inserted ) {
			return array( 'type' => 'error', 'message' => __( 'Database error: could not create coupon.', 'edupro' ) );
		}

		return array(
			'type'    => 'success',
			'message' => sprintf( __( 'Coupon "%s" created successfully.', 'edupro' ), $code ),
		);
	}

	// Toggle status (GET with nonce).
	if ( isset( $_GET['edupro_coupon_toggle'] ) ) {
		$coupon_id = absint( $_GET['edupro_coupon_toggle'] );
		check_admin_referer( 'edupro_toggle_coupon_' . $coupon_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $coupon_id ) );
		if ( $current ) {
			$next_status = ( 'active' === $current ) ? 'inactive' : 'active';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'status' => $next_status ),
				array( 'id' => $coupon_id ),
				array( '%s' ),
				array( '%d' )
			);
			return array(
				'type'    => 'success',
				'message' => sprintf( __( 'Coupon status updated to %s.', 'edupro' ), $next_status ),
			);
		}
	}

	// Delete coupon (GET with nonce).
	if ( isset( $_GET['edupro_coupon_delete'] ) ) {
		$coupon_id = absint( $_GET['edupro_coupon_delete'] );
		check_admin_referer( 'edupro_delete_coupon_' . $coupon_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $table, array( 'id' => $coupon_id ), array( '%d' ) );
		if ( $deleted ) {
			return array( 'type' => 'success', 'message' => __( 'Coupon deleted.', 'edupro' ) );
		}
	}

	return null;
}

/**
 * Render the Courses -> Coupons admin management screen.
 */
function edupro_render_coupons_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'edupro' ) );
	}

	global $wpdb;
	$notice = edupro_handle_coupons_admin_actions();
	$table  = edupro_get_coupons_table_name();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$coupons = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );

	$courses = get_posts(
		array(
			'post_type'      => 'lms_course',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$course_titles = array();
	foreach ( $courses as $course ) {
		$course_titles[ $course->ID ] = $course->post_title;
	}

	$currencies = function_exists( 'edupro_get_supported_currencies' )
		? edupro_get_supported_currencies()
		: array(
			'INR' => array( 'symbol' => '₹', 'name' => 'Indian Rupee' ),
			'GBP' => array( 'symbol' => '£', 'name' => 'British Pound' ),
			'USD' => array( 'symbol' => '$', 'name' => 'US Dollar' ),
		);

	$base_url = admin_url( 'edit.php?post_type=lms_course&page=edupro-coupons' );
	?>
	<div class="wrap edupro-coupons-wrap">
		<h1 style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
			<span class="dashicons dashicons-tickets-alt" style="color:#e31b23;font-size:28px;width:28px;height:28px;"></span>
			<?php esc_html_e( 'EduPro Coupons & Discounts', 'edupro' ); ?>
		</h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<style>
			.edupro-coupons-grid { display:grid; grid-template-columns: 360px 1fr; gap:24px; align-items:start; margin-top:16px; }
			@media (max-width: 1080px) { .edupro-coupons-grid { grid-template-columns: 1fr; } }
			.edupro-admin-card { background:#fff; border:1px solid #e2e4ec; border-radius:8px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
			.edupro-admin-card h2 { margin-top:0; padding-bottom:10px; border-bottom:1px solid #eee; font-size:16px; color:#1a1a2e; }
			.edupro-field { margin-bottom:14px; }
			.edupro-field label { display:block; font-weight:600; margin-bottom:5px; font-size:13px; color:#1a1a2e; }
			.edupro-field input[type="text"],
			.edupro-field input[type="number"],
			.edupro-field input[type="date"],
			.edupro-field select { width:100%; max-width:100%; }
			.edupro-field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
			.edupro-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; text-transform:uppercase; }
			.edupro-pill--active { background:#e6f9ef; color:#138a4b; }
			.edupro-pill--inactive { background:#f3f4f6; color:#6b7280; }
			.edupro-pill--expired { background:#fde8e8; color:#c81e1e; }
			.edupro-code-badge { font-family:monospace; font-weight:700; font-size:13px; background:#fef2f2; color:#c0151c; padding:3px 8px; border-radius:4px; border:1px dashed #fca5a5; }
			.edupro-submit-btn { background:#e31b23 !important; border-color:#c0151c !important; color:#fff !important; width:100%; justify-content:center; padding:6px 14px !important; height:auto !important; font-weight:600; }
			.edupro-submit-btn:hover { background:#c0151c !important; }
		</style>

		<div class="edupro-coupons-grid">
			<!-- Create Coupon Form -->
			<div class="edupro-admin-card">
				<h2><?php esc_html_e( 'Create New Coupon', 'edupro' ); ?></h2>
				<form method="post" action="<?php echo esc_url( $base_url ); ?>">
					<?php wp_nonce_field( 'edupro_create_coupon', 'edupro_coupon_nonce' ); ?>
					<input type="hidden" name="edupro_coupon_action" value="create">

					<div class="edupro-field">
						<label for="edupro-coupon-code"><?php esc_html_e( 'Coupon Code *', 'edupro' ); ?></label>
						<input type="text" id="edupro-coupon-code" name="coupon_code" required placeholder="e.g. LAUNCH20" style="text-transform:uppercase;">
					</div>

					<div class="edupro-field-row">
						<div class="edupro-field">
							<label for="edupro-discount-type"><?php esc_html_e( 'Discount Type', 'edupro' ); ?></label>
							<select id="edupro-discount-type" name="discount_type">
								<option value="percent"><?php esc_html_e( 'Percentage (%)', 'edupro' ); ?></option>
								<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'edupro' ); ?></option>
							</select>
						</div>
						<div class="edupro-field">
							<label for="edupro-discount-value"><?php esc_html_e( 'Discount Value *', 'edupro' ); ?></label>
							<input type="number" id="edupro-discount-value" name="discount_value" step="0.01" min="0.01" required placeholder="20">
						</div>
					</div>

					<div class="edupro-field-row">
						<div class="edupro-field">
							<label for="edupro-coupon-currency"><?php esc_html_e( 'Currency Restriction', 'edupro' ); ?></label>
							<select id="edupro-coupon-currency" name="currency">
								<option value=""><?php esc_html_e( 'Any Currency', 'edupro' ); ?></option>
								<?php foreach ( $currencies as $code => $info ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>">
										<?php echo esc_html( $info['symbol'] . ' ' . $code ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="edupro-field">
							<label for="edupro-min-amount"><?php esc_html_e( 'Minimum Order', 'edupro' ); ?></label>
							<input type="number" id="edupro-min-amount" name="min_amount" step="0.01" min="0" value="0">
						</div>
					</div>

					<div class="edupro-field-row">
						<div class="edupro-field">
							<label for="edupro-max-uses"><?php esc_html_e( 'Max Uses (0 = unlimited)', 'edupro' ); ?></label>
							<input type="number" id="edupro-max-uses" name="max_uses" min="0" value="0">
						</div>
						<div class="edupro-field">
							<label for="edupro-expires-at"><?php esc_html_e( 'Expiry Date', 'edupro' ); ?></label>
							<input type="date" id="edupro-expires-at" name="expires_at">
						</div>
					</div>

					<div class="edupro-field">
						<label for="edupro-course-ids"><?php esc_html_e( 'Restrict to Courses (optional)', 'edupro' ); ?></label>
						<select id="edupro-course-ids" name="course_ids[]" multiple size="4">
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>">
									<?php echo esc_html( $course->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Leave unselected to apply to all courses.', 'edupro' ); ?></p>
					</div>

					<div class="edupro-field">
						<label for="edupro-coupon-status"><?php esc_html_e( 'Status', 'edupro' ); ?></label>
						<select id="edupro-coupon-status" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'edupro' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'edupro' ); ?></option>
						</select>
					</div>

					<button type="submit" class="button button-primary edupro-submit-btn">
						<?php esc_html_e( 'Create Coupon', 'edupro' ); ?>
					</button>
				</form>
			</div>

			<!-- Coupons Table -->
			<div class="edupro-admin-card" style="padding:0;overflow:hidden;">
				<table class="wp-list-table widefat fixed striped" style="border:0;">
					<thead>
						<tr>
							<th style="width:130px;"><?php esc_html_e( 'Code', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Discount', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Currency / Min', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Usage', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Courses', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'edupro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'edupro' ); ?></th>
							<th style="width:140px;"><?php esc_html_e( 'Actions', 'edupro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $coupons ) ) : ?>
							<tr>
								<td colspan="8" style="text-align:center;padding:30px;color:#666;">
									<?php esc_html_e( 'No coupons created yet. Use the form on the left to add your first discount code.', 'edupro' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $coupons as $coupon ) : ?>
								<?php
								$is_expired = ( ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at && current_time( 'timestamp' ) > strtotime( $coupon->expires_at ) );
								$allowed    = edupro_parse_coupon_course_ids( $coupon->course_ids );

								if ( 'percent' === $coupon->discount_type ) {
									$discount_label = rtrim( rtrim( number_format( (float) $coupon->discount_value, 2 ), '0' ), '.' ) . '%';
								} else {
									$sym            = $coupon->currency && function_exists( 'edupro_get_currency_symbol' )
										? edupro_get_currency_symbol( $coupon->currency )
										: '';
									$discount_label = $sym . number_format( (float) $coupon->discount_value, 2 );
								}

								$toggle_url = wp_nonce_url(
									add_query_arg( 'edupro_coupon_toggle', $coupon->id, $base_url ),
									'edupro_toggle_coupon_' . $coupon->id
								);
								$delete_url = wp_nonce_url(
									add_query_arg( 'edupro_coupon_delete', $coupon->id, $base_url ),
									'edupro_delete_coupon_' . $coupon->id
								);
								?>
								<tr>
									<td><span class="edupro-code-badge"><?php echo esc_html( $coupon->code ); ?></span></td>
									<td><strong><?php echo esc_html( $discount_label ); ?></strong><br><small><?php echo esc_html( ucfirst( $coupon->discount_type ) ); ?></small></td>
									<td>
										<?php echo $coupon->currency ? esc_html( $coupon->currency ) : esc_html__( 'Any', 'edupro' ); ?>
										<?php if ( (float) $coupon->min_amount > 0 ) : ?>
											<br><small><?php echo esc_html( sprintf( __( 'Min: %s', 'edupro' ), number_format( (float) $coupon->min_amount, 2 ) ) ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php
										echo esc_html(
											(int) $coupon->used_count . ' / ' . ( (int) $coupon->max_uses > 0 ? (int) $coupon->max_uses : '∞' )
										);
										?>
									</td>
									<td>
										<?php
										if ( empty( $allowed ) ) {
											esc_html_e( 'All Courses', 'edupro' );
										} else {
											$names = array();
											foreach ( $allowed as $cid ) {
												$names[] = isset( $course_titles[ $cid ] ) ? $course_titles[ $cid ] : '#' . $cid;
											}
											echo esc_html( implode( ', ', $names ) );
										}
										?>
									</td>
									<td>
										<?php
										echo ( ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at )
											? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $coupon->expires_at ) ) )
											: '—';
										?>
									</td>
									<td>
										<?php if ( $is_expired ) : ?>
											<span class="edupro-pill edupro-pill--expired"><?php esc_html_e( 'Expired', 'edupro' ); ?></span>
										<?php elseif ( 'active' === $coupon->status ) : ?>
											<span class="edupro-pill edupro-pill--active"><?php esc_html_e( 'Active', 'edupro' ); ?></span>
										<?php else : ?>
											<span class="edupro-pill edupro-pill--inactive"><?php esc_html_e( 'Inactive', 'edupro' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small">
											<?php echo 'active' === $coupon->status ? esc_html__( 'Disable', 'edupro' ) : esc_html__( 'Enable', 'edupro' ); ?>
										</a>
										<a href="<?php echo esc_url( $delete_url ); ?>"
										   class="button button-small"
										   style="color:#b91c1c;border-color:#fca5a5;"
										   onclick="return confirm('<?php echo esc_js( __( 'Delete this coupon permanently?', 'edupro' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'edupro' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php
}
