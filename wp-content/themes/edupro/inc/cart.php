<?php
/**
 * EduPro Multi-Course Shopping Cart System
 *
 * Supports guest carts (cookie `edupro_cart`), logged-in student carts
 * (user meta `_edupro_cart_items`), automatic merge on login/registration,
 * multi-currency pricing summaries, and AJAX cart actions.
 *
 * @package EduPro
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EDUPRO_CART_COOKIE' ) ) {
	define( 'EDUPRO_CART_COOKIE', 'edupro_cart' );
}

if ( ! defined( 'EDUPRO_CART_USER_META' ) ) {
	define( 'EDUPRO_CART_USER_META', '_edupro_cart_items' );
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. INTERNAL STORAGE & VALIDATION HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Parse course IDs stored in the guest `edupro_cart` cookie.
 *
 * @return int[] Sanitized array of unique positive course IDs.
 */
function edupro_cart_parse_cookie() {
	if ( empty( $_COOKIE[ EDUPRO_CART_COOKIE ] ) ) {
		return array();
	}

	$raw     = wp_unslash( $_COOKIE[ EDUPRO_CART_COOKIE ] );
	$decoded = json_decode( $raw, true );

	if ( ! is_array( $decoded ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $decoded ) ) ) );
}

/**
 * Persist or clear the guest `edupro_cart` cookie (30 days, SameSite=Lax).
 *
 * @param int[] $course_ids Array of course IDs (empty clears cookie).
 */
function edupro_cart_set_cookie( array $course_ids ) {
	$clean = array_values( array_unique( array_filter( array_map( 'absint', $course_ids ) ) ) );
	$path  = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';

	if ( empty( $clean ) ) {
		unset( $_COOKIE[ EDUPRO_CART_COOKIE ] );
		if ( ! headers_sent() ) {
			setcookie(
				EDUPRO_CART_COOKIE,
				'',
				array(
					'expires'  => time() - HOUR_IN_SECONDS,
					'path'     => $path,
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		}
		return;
	}

	$encoded                      = wp_json_encode( $clean );
	$_COOKIE[ EDUPRO_CART_COOKIE ] = $encoded;

	if ( ! headers_sent() ) {
		setcookie(
			EDUPRO_CART_COOKIE,
			$encoded,
			array(
				'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
				'path'     => $path,
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}
}

/**
 * Filter a list of candidate course IDs down to valid, published, non-enrolled courses.
 *
 * @param int[] $course_ids Candidate course IDs.
 * @param int   $user_id    User ID (0 for guests).
 * @return int[] Valid cart course IDs.
 */
function edupro_cart_filter_valid_course_ids( array $course_ids, $user_id = 0 ) {
	$unique = array_values( array_unique( array_filter( array_map( 'absint', $course_ids ) ) ) );
	if ( empty( $unique ) ) {
		return array();
	}

	$valid = array();
	foreach ( $unique as $cid ) {
		$post = get_post( $cid );
		if ( ! $post || 'lms_course' !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}
		if ( $user_id > 0 && function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $cid, $user_id ) ) {
			continue;
		}
		$valid[] = $cid;
	}

	return $valid;
}

/**
 * Resolve the target user ID for cart operations.
 *
 * @param int|null $user_id Optional explicit user ID.
 * @return int User ID or 0 for guests.
 */
function edupro_cart_resolve_user_id( $user_id = null ) {
	return null === $user_id ? get_current_user_id() : absint( $user_id );
}

/**
 * Save a sanitized cart items array to user meta (logged-in) or cookie (guest).
 *
 * @param int[] $course_ids Clean array of course IDs.
 * @param int   $user_id    Resolved user ID (0 for guests).
 */
function edupro_cart_persist_items( array $course_ids, $user_id ) {
	$clean = array_values( array_unique( array_filter( array_map( 'absint', $course_ids ) ) ) );

	if ( $user_id > 0 ) {
		update_user_meta( $user_id, EDUPRO_CART_USER_META, $clean );
	} else {
		edupro_cart_set_cookie( $clean );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. PUBLIC CART API
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return the URL of the Shopping Cart page.
 *
 * @return string
 */
function edupro_get_cart_url() {
	$cart_page_id = (int) get_option( 'edupro_cart_page_id', 0 );
	if ( $cart_page_id && 'publish' === get_post_status( $cart_page_id ) ) {
		return get_permalink( $cart_page_id );
	}

	$page = get_page_by_path( 'cart' );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page->ID );
	}

	return home_url( '/cart/' );
}

/**
 * Return the checkout URL for multi-course cart mode (`/checkout/?mode=cart`).
 *
 * @return string
 */
function edupro_get_cart_checkout_url() {
	$checkout_page_id = (int) get_option( 'edupro_checkout_page_id', 0 );
	if ( $checkout_page_id && 'publish' === get_post_status( $checkout_page_id ) ) {
		$base = get_permalink( $checkout_page_id );
	} else {
		$page = get_page_by_path( 'checkout' );
		$base = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page->ID ) : home_url( '/checkout/' );
	}

	return add_query_arg( 'mode', 'cart', $base );
}

/**
 * Get the current cart's valid course IDs (always strips deleted/unpublished
 * and already-enrolled courses).
 *
 * @param int|null $user_id Optional user ID (defaults to current user / guest).
 * @return int[]
 */
function edupro_get_cart_items( $user_id = null ) {
	$uid = edupro_cart_resolve_user_id( $user_id );

	if ( $uid > 0 ) {
		$raw = get_user_meta( $uid, EDUPRO_CART_USER_META, true );
		$raw = is_array( $raw ) ? array_values( array_filter( array_map( 'absint', $raw ) ) ) : array();
	} else {
		$raw = edupro_cart_parse_cookie();
	}

	$valid = edupro_cart_filter_valid_course_ids( $raw, $uid );

	// Persist cleaned list if stale/enrolled items were stripped.
	if ( $valid !== $raw ) {
		edupro_cart_persist_items( $valid, $uid );
	}

	return $valid;
}

/**
 * Return the number of unpaid courses currently in the cart (0 when empty;
 * NEVER counts enrolled courses).
 *
 * @param int|null $user_id Optional user ID.
 * @return int
 */
function edupro_get_cart_count( $user_id = null ) {
	return count( edupro_get_cart_items( $user_id ) );
}

/**
 * Check whether a specific course is currently in the cart.
 *
 * @param int      $course_id Course post ID.
 * @param int|null $user_id   Optional user ID.
 * @return bool
 */
function edupro_is_in_cart( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	if ( ! $course_id ) {
		return false;
	}

	return in_array( $course_id, edupro_get_cart_items( $user_id ), true );
}

/**
 * Add a course to the cart.
 *
 * Rejects non-existent/unpublished courses and courses the user is already enrolled in.
 *
 * @param int      $course_id Course post ID.
 * @param int|null $user_id   Optional user ID.
 * @return array|WP_Error Updated cart summary on success, or WP_Error.
 */
function edupro_add_to_cart( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$uid       = edupro_cart_resolve_user_id( $user_id );

	if ( ! $course_id ) {
		return new WP_Error( 'invalid_course', __( 'Invalid course selected.', 'edupro' ) );
	}

	$post = get_post( $course_id );
	if ( ! $post || 'lms_course' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'unpublished_course', __( 'This course is not available for purchase.', 'edupro' ) );
	}

	if ( $uid > 0 && function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id, $uid ) ) {
		return new WP_Error( 'already_enrolled', __( 'You are already enrolled in this course.', 'edupro' ) );
	}

	// Enforce enrollment limit if set on the course.
	$enrollment_limit = absint( get_post_meta( $course_id, '_edupro_enrollment_limit', true ) );
	if ( $enrollment_limit > 0 && function_exists( 'edupro_get_enrolled_students' ) ) {
		if ( count( edupro_get_enrolled_students( $course_id ) ) >= $enrollment_limit ) {
			return new WP_Error( 'course_full', __( 'Sorry, this course has reached its maximum enrollment limit.', 'edupro' ) );
		}
	}

	$items = edupro_get_cart_items( $uid );
	if ( ! in_array( $course_id, $items, true ) ) {
		$items[] = $course_id;
		edupro_cart_persist_items( $items, $uid );

		/**
		 * Fires when a course is added to a cart.
		 *
		 * @param int $course_id Course post ID.
		 * @param int $uid       User ID (0 for guest).
		 */
		do_action( 'edupro_cart_item_added', $course_id, $uid );
	}

	return edupro_get_cart_summary( '', $uid );
}

/**
 * Remove a course from the cart.
 *
 * @param int      $course_id Course post ID.
 * @param int|null $user_id   Optional user ID.
 * @return array Updated cart summary.
 */
function edupro_remove_from_cart( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$uid       = edupro_cart_resolve_user_id( $user_id );
	$items     = edupro_get_cart_items( $uid );

	$updated = array_values(
		array_filter(
			$items,
			function ( $id ) use ( $course_id ) {
				return $id !== $course_id;
			}
		)
	);

	if ( $updated !== $items ) {
		edupro_cart_persist_items( $updated, $uid );

		/**
		 * Fires when a course is removed from a cart.
		 *
		 * @param int $course_id Course post ID.
		 * @param int $uid       User ID (0 for guest).
		 */
		do_action( 'edupro_cart_item_removed', $course_id, $uid );
	}

	return edupro_get_cart_summary( '', $uid );
}

/**
 * Clear all items from the cart.
 *
 * @param int|null $user_id Optional user ID.
 * @return bool
 */
function edupro_clear_cart( $user_id = null ) {
	$uid = edupro_cart_resolve_user_id( $user_id );

	if ( $uid > 0 ) {
		update_user_meta( $uid, EDUPRO_CART_USER_META, array() );
		// Also clear any lingering guest cookie when clearing current user's cart.
		if ( null === $user_id || $uid === get_current_user_id() ) {
			edupro_cart_set_cookie( array() );
		}
	} else {
		edupro_cart_set_cookie( array() );
	}

	/**
	 * Fires after a cart is cleared.
	 *
	 * @param int $uid User ID (0 for guest).
	 */
	do_action( 'edupro_cart_cleared', $uid );

	return true;
}

/**
 * Build a complete multi-currency summary of the current cart.
 *
 * @param string   $currency Optional ISO currency code. Defaults to active currency.
 * @param int|null $user_id  Optional user ID.
 * @return array{
 *     items: array[],
 *     count: int,
 *     currency: string,
 *     symbol: string,
 *     subtotal: float,
 *     regular_total: float,
 *     savings: float,
 *     formatted_subtotal: string,
 *     formatted_savings: string,
 *     checkout_url: string,
 *     cart_url: string
 * }
 */
function edupro_get_cart_summary( $currency = '', $user_id = null ) {
	$uid = edupro_cart_resolve_user_id( $user_id );

	$target_currency = $currency
		? ( function_exists( 'edupro_sanitize_currency_code' ) ? edupro_sanitize_currency_code( $currency ) : strtoupper( sanitize_text_field( $currency ) ) )
		: ( function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR' );

	if ( ! $target_currency ) {
		$target_currency = function_exists( 'edupro_get_default_currency' ) ? edupro_get_default_currency() : 'INR';
	}

	$symbol = function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol( $target_currency ) : '₹';

	$fmt = function ( $amount ) use ( $target_currency, $symbol ) {
		return function_exists( 'edupro_format_price' )
			? edupro_format_price( $amount, $target_currency )
			: $symbol . number_format( (float) $amount, 2, '.', ',' );
	};

	$course_ids    = edupro_get_cart_items( $uid );
	$items         = array();
	$subtotal      = 0.0;
	$regular_total = 0.0;

	foreach ( $course_ids as $cid ) {
		$post = get_post( $cid );
		if ( ! $post ) {
			continue;
		}

		if ( function_exists( 'edupro_get_course_price_data' ) ) {
			$price = edupro_get_course_price_data( $cid, $target_currency );
		} else {
			$reg   = (float) get_post_meta( $cid, '_edupro_regular_price', true );
			$sale  = (float) get_post_meta( $cid, '_edupro_sale_price', true );
			$eff   = ( $sale > 0 && $sale < $reg ) ? $sale : $reg;
			$price = array(
				'currency'     => $target_currency,
				'symbol'       => $symbol,
				'regular'      => round( $reg, 2 ),
				'sale'         => ( $sale > 0 && $sale < $reg ) ? round( $sale, 2 ) : 0.0,
				'effective'    => round( $eff, 2 ),
				'is_free'      => ( $eff <= 0 ),
				'discount_pct' => ( $sale > 0 && $reg > 0 && $sale < $reg ) ? (int) round( ( ( $reg - $sale ) / $reg ) * 100 ) : 0,
			);
		}

		// Instructor name: check custom instructor post meta first, else post author.
		$instructor_name = '';
		$instructor_id   = absint( get_post_meta( $cid, '_edupro_instructor_id', true ) );
		if ( $instructor_id ) {
			$inst_post = get_post( $instructor_id );
			if ( $inst_post ) {
				$instructor_name = $inst_post->post_title;
			}
		}
		if ( '' === $instructor_name ) {
			$instructor_name = get_the_author_meta( 'display_name', (int) $post->post_author );
		}

		// Duration label.
		$duration_mins  = absint( get_post_meta( $cid, '_edupro_duration', true ) );
		$duration_label = ( $duration_mins > 0 && function_exists( 'edupro_get_duration_label' ) )
			? edupro_get_duration_label( $duration_mins )
			: '';

		$thumb_url = get_the_post_thumbnail_url( $cid, 'course-card' );
		if ( ! $thumb_url ) {
			$thumb_url = get_the_post_thumbnail_url( $cid, 'medium' );
		}

		$regular_amt   = (float) $price['regular'];
		$effective_amt = (float) $price['effective'];

		$subtotal      += $effective_amt;
		$regular_total += ( $regular_amt > $effective_amt ? $regular_amt : $effective_amt );

		$items[] = array(
			'course_id'           => $cid,
			'title'               => get_the_title( $cid ),
			'permalink'           => get_permalink( $cid ),
			'thumbnail'           => $thumb_url ? $thumb_url : '',
			'instructor'          => $instructor_name,
			'duration'            => $duration_label,
			'currency'            => $price['currency'],
			'symbol'              => $price['symbol'],
			'regular'             => $regular_amt,
			'sale'                => (float) $price['sale'],
			'effective'           => $effective_amt,
			'is_free'             => (bool) $price['is_free'],
			'discount_pct'        => (int) $price['discount_pct'],
			'formatted_regular'   => $fmt( $regular_amt ),
			'formatted_effective' => $price['is_free'] ? __( 'Free', 'edupro' ) : $fmt( $effective_amt ),
		);
	}

	$subtotal      = round( $subtotal, 2 );
	$regular_total = round( $regular_total, 2 );
	$savings       = max( 0.0, round( $regular_total - $subtotal, 2 ) );

	return array(
		'items'              => $items,
		'count'              => count( $items ),
		'currency'           => $target_currency,
		'symbol'             => $symbol,
		'subtotal'           => $subtotal,
		'regular_total'      => $regular_total,
		'savings'            => $savings,
		'formatted_subtotal' => $fmt( $subtotal ),
		'formatted_savings'  => $fmt( $savings ),
		'checkout_url'       => edupro_get_cart_checkout_url(),
		'cart_url'           => edupro_get_cart_url(),
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. MERGE GUEST COOKIE INTO USER META ON LOGIN / REGISTRATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Merge guest cart cookie items into a user's persistent cart meta,
 * skipping any already-enrolled or unpublished courses, and clear the cookie.
 *
 * @param int $user_id WordPress user ID.
 */
function edupro_merge_guest_cart_for_user( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}

	$guest_items = edupro_cart_parse_cookie();
	if ( empty( $guest_items ) ) {
		return;
	}

	$existing = get_user_meta( $user_id, EDUPRO_CART_USER_META, true );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}

	$merged = edupro_cart_filter_valid_course_ids( array_merge( $existing, $guest_items ), $user_id );
	update_user_meta( $user_id, EDUPRO_CART_USER_META, $merged );

	// Clear guest cookie after merge.
	edupro_cart_set_cookie( array() );
}

/**
 * Hook callback for standard WordPress `wp_login`.
 *
 * @param string  $user_login Username.
 * @param WP_User $user       Authenticated WP_User object.
 */
function edupro_cart_on_wp_login( $user_login, $user ) {
	if ( $user instanceof WP_User ) {
		edupro_merge_guest_cart_for_user( $user->ID );
	}
}
add_action( 'wp_login', 'edupro_cart_on_wp_login', 10, 2 );

/**
 * Hook callbacks for EduPro AJAX login & registration flows.
 */
add_action( 'edupro_student_login', 'edupro_merge_guest_cart_for_user', 10, 1 );
add_action( 'edupro_student_registered', 'edupro_merge_guest_cart_for_user', 10, 1 );

// ─────────────────────────────────────────────────────────────────────────────
// 4. AJAX HANDLERS (ADD / REMOVE / GET / CLEAR)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verify nonce for cart AJAX endpoints.
 *
 * @return bool
 */
function edupro_cart_verify_ajax_nonce() {
	$nonce = '';
	if ( isset( $_REQUEST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
	} elseif ( isset( $_REQUEST['security'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['security'] ) );
	}

	return (bool) (
		wp_verify_nonce( $nonce, 'edupro_nonce' ) ||
		wp_verify_nonce( $nonce, 'edupro_payment_nonce' )
	);
}

/**
 * AJAX: Add a course to the shopping cart (`edupro_cart_add`).
 */
function edupro_ajax_cart_add() {
	if ( ! edupro_cart_verify_ajax_nonce() ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ),
			403
		);
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$currency  = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

	$result = edupro_add_to_cart( $course_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	$summary = '' !== $currency ? edupro_get_cart_summary( $currency ) : $result;

	wp_send_json_success(
		array(
			'course_id' => $course_id,
			'in_cart'   => true,
			'summary'   => $summary,
			'message'   => sprintf(
				/* translators: %s: course title */
				__( '"%s" added to your cart.', 'edupro' ),
				get_the_title( $course_id )
			),
		)
	);
}
add_action( 'wp_ajax_edupro_cart_add', 'edupro_ajax_cart_add' );
add_action( 'wp_ajax_nopriv_edupro_cart_add', 'edupro_ajax_cart_add' );

/**
 * AJAX: Remove a course from the shopping cart (`edupro_cart_remove`).
 */
function edupro_ajax_cart_remove() {
	if ( ! edupro_cart_verify_ajax_nonce() ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ),
			403
		);
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$currency  = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

	if ( ! $course_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'edupro' ) ), 400 );
	}

	edupro_remove_from_cart( $course_id );
	$summary = edupro_get_cart_summary( $currency );

	wp_send_json_success(
		array(
			'course_id' => $course_id,
			'in_cart'   => false,
			'summary'   => $summary,
			'message'   => __( 'Course removed from your cart.', 'edupro' ),
		)
	);
}
add_action( 'wp_ajax_edupro_cart_remove', 'edupro_ajax_cart_remove' );
add_action( 'wp_ajax_nopriv_edupro_cart_remove', 'edupro_ajax_cart_remove' );

/**
 * AJAX: Fetch the current cart summary (`edupro_cart_get`).
 */
function edupro_ajax_cart_get() {
	if ( ! edupro_cart_verify_ajax_nonce() ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ),
			403
		);
	}

	$currency = isset( $_REQUEST['currency'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['currency'] ) ) : '';
	$summary  = edupro_get_cart_summary( $currency );

	wp_send_json_success(
		array(
			'summary' => $summary,
		)
	);
}
add_action( 'wp_ajax_edupro_cart_get', 'edupro_ajax_cart_get' );
add_action( 'wp_ajax_nopriv_edupro_cart_get', 'edupro_ajax_cart_get' );

/**
 * AJAX: Clear all items from the shopping cart (`edupro_cart_clear`).
 */
function edupro_ajax_cart_clear() {
	if ( ! edupro_cart_verify_ajax_nonce() ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ),
			403
		);
	}

	$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
	edupro_clear_cart();
	$summary = edupro_get_cart_summary( $currency );

	wp_send_json_success(
		array(
			'summary' => $summary,
			'message' => __( 'Your cart has been cleared.', 'edupro' ),
		)
	);
}
add_action( 'wp_ajax_edupro_cart_clear', 'edupro_ajax_cart_clear' );
add_action( 'wp_ajax_nopriv_edupro_cart_clear', 'edupro_ajax_cart_clear' );
