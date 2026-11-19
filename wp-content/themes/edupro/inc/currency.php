<?php
/**
 * EduPro Multi-Currency Pricing Engine
 *
 * Supports INR (₹), GBP (£), and USD ($) with per-course regional price
 * overrides, configurable exchange rates, cookie persistence, and AJAX switching.
 *
 * @package EduPro
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. SUPPORTED CURRENCIES & ACTIVE CURRENCY RESOLUTION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return metadata for all supported currencies.
 *
 * @return array<string, array<string, mixed>>
 */
function edupro_get_supported_currencies() {
	$currencies = array(
		'INR' => array(
			'code'     => 'INR',
			'symbol'   => '₹',
			'name'     => __( 'Indian Rupee', 'edupro' ),
			'region'   => __( 'India', 'edupro' ),
			'decimals' => 2,
		),
		'GBP' => array(
			'code'     => 'GBP',
			'symbol'   => '£',
			'name'     => __( 'British Pound', 'edupro' ),
			'region'   => __( 'United Kingdom', 'edupro' ),
			'decimals' => 2,
		),
		'USD' => array(
			'code'     => 'USD',
			'symbol'   => '$',
			'name'     => __( 'US Dollar', 'edupro' ),
			'region'   => __( 'International', 'edupro' ),
			'decimals' => 2,
		),
	);

	/**
	 * Filter the supported currencies map.
	 *
	 * @param array $currencies Supported currencies keyed by ISO 4217 code.
	 */
	return apply_filters( 'edupro_supported_currencies', $currencies );
}

/**
 * Sanitize a currency code against supported currencies.
 *
 * @param string $code Candidate ISO currency code.
 * @return string Validated uppercase currency code, or empty string if unsupported.
 */
function edupro_sanitize_currency_code( $code ) {
	$code      = strtoupper( sanitize_text_field( (string) $code ) );
	$supported = edupro_get_supported_currencies();

	return isset( $supported[ $code ] ) ? $code : '';
}

/**
 * Return the site's default currency option ('INR', 'GBP', or 'USD').
 *
 * @return string
 */
function edupro_get_default_currency() {
	$option    = get_option( 'edupro_default_currency', 'INR' );
	$validated = edupro_sanitize_currency_code( $option );

	return $validated ? $validated : 'INR';
}

/**
 * Resolve the currently active currency for the visitor.
 *
 * Priority:
 * 1. Explicit in-request override (after AJAX switch or query param capture)
 * 2. `?currency=CODE` query parameter
 * 3. `edupro_currency` browser cookie
 * 4. Site default option `edupro_default_currency`
 *
 * @param string|null $override Optional internal override setter (not for general callers).
 * @return string Active ISO 4217 currency code ('INR', 'GBP', or 'USD').
 */
function edupro_get_active_currency( $override = null ) {
	static $runtime_currency = '';

	if ( null !== $override ) {
		$clean = edupro_sanitize_currency_code( $override );
		if ( $clean ) {
			$runtime_currency = $clean;
		}
		return $runtime_currency ? $runtime_currency : edupro_get_default_currency();
	}

	if ( '' !== $runtime_currency ) {
		return $runtime_currency;
	}

	// 1. Check URL query parameter.
	if ( isset( $_GET['currency'] ) ) {
		$from_query = edupro_sanitize_currency_code( wp_unslash( $_GET['currency'] ) );
		if ( $from_query ) {
			$runtime_currency = $from_query;
			return $runtime_currency;
		}
	}

	// 2. Check cookie.
	if ( isset( $_COOKIE['edupro_currency'] ) ) {
		$from_cookie = edupro_sanitize_currency_code( wp_unslash( $_COOKIE['edupro_currency'] ) );
		if ( $from_cookie ) {
			$runtime_currency = $from_cookie;
			return $runtime_currency;
		}
	}

	// 3. Fallback to site default.
	return edupro_get_default_currency();
}

/**
 * Return the currency symbol for a given currency code or the active currency.
 *
 * @param string $code Optional ISO currency code ('INR', 'GBP', 'USD').
 * @return string Currency symbol (e.g. '₹', '£', '$').
 */
function edupro_get_currency_symbol( $code = '' ) {
	$supported = edupro_get_supported_currencies();
	$target    = $code ? edupro_sanitize_currency_code( $code ) : edupro_get_active_currency();

	if ( ! $target || ! isset( $supported[ $target ] ) ) {
		$target = edupro_get_default_currency();
	}

	return isset( $supported[ $target ]['symbol'] ) ? $supported[ $target ]['symbol'] : '₹';
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. EXCHANGE RATE CONVERSION & COURSE PRICING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return the configured INR value of 1 unit of a given currency.
 *
 * @param string $currency ISO currency code.
 * @return float Rate relative to INR.
 */
function edupro_get_inr_exchange_rate( $currency ) {
	$currency = edupro_sanitize_currency_code( $currency );

	if ( 'GBP' === $currency ) {
		$rate = (float) get_option( 'edupro_rate_gbp_to_inr', 108 );
		return $rate > 0 ? $rate : 108.0;
	}

	if ( 'USD' === $currency ) {
		$rate = (float) get_option( 'edupro_rate_usd_to_inr', 84 );
		return $rate > 0 ? $rate : 84.0;
	}

	return 1.0; // INR -> INR
}

/**
 * Convert an amount between supported currencies using configured exchange rates.
 *
 * @param float|int|string $amount Monetary amount in source currency.
 * @param string           $from   Source currency code.
 * @param string           $to     Target currency code.
 * @return float Converted amount rounded to 2 decimal places.
 */
function edupro_convert_price( $amount, $from, $to ) {
	$numeric = function_exists( 'edupro_sanitize_price' )
		? edupro_sanitize_price( $amount )
		: (float) preg_replace( '/[^0-9.]/', '', (string) $amount );

	if ( $numeric <= 0 ) {
		return 0.0;
	}

	$from_code = edupro_sanitize_currency_code( $from );
	$to_code   = edupro_sanitize_currency_code( $to );

	if ( ! $from_code ) {
		$from_code = edupro_get_default_currency();
	}
	if ( ! $to_code ) {
		$to_code = edupro_get_active_currency();
	}

	if ( $from_code === $to_code ) {
		return round( $numeric, 2 );
	}

	// Pivot through INR using admin-configured exchange rates.
	$from_to_inr = edupro_get_inr_exchange_rate( $from_code );
	$to_to_inr   = edupro_get_inr_exchange_rate( $to_code );

	if ( $to_to_inr <= 0 ) {
		return round( $numeric, 2 );
	}

	$amount_in_inr = $numeric * $from_to_inr;
	$converted     = $amount_in_inr / $to_to_inr;

	return round( $converted, 2 );
}

/**
 * Resolve complete pricing data for a course in the requested (or active) currency.
 *
 * Checks:
 * 1. Explicit free course flags (`_edupro_is_free`, `_edupro_course_free`)
 * 2. Active sale window (`_edupro_sale_end_date`)
 * 3. Optional regional currency overrides (`_edupro_regular_price_{code}`, `_edupro_sale_price_{code}`)
 * 4. Fallback to base `_edupro_regular_price` / `_edupro_sale_price` (or legacy `_edupro_course_price`)
 *    converted from the site's default currency to the target currency.
 *
 * @param int    $course_id Post ID of the `lms_course`.
 * @param string $currency  Optional target currency code. Defaults to active currency.
 * @return array{
 *     currency: string,
 *     symbol: string,
 *     regular: float,
 *     sale: float,
 *     effective: float,
 *     is_free: bool,
 *     discount_pct: int
 * }
 */
function edupro_get_course_price_data( $course_id, $currency = '' ) {
	$course_id = absint( $course_id );
	$target    = $currency ? edupro_sanitize_currency_code( $currency ) : edupro_get_active_currency();
	if ( ! $target ) {
		$target = edupro_get_default_currency();
	}
	$symbol = edupro_get_currency_symbol( $target );

	$default_result = array(
		'currency'     => $target,
		'symbol'       => $symbol,
		'regular'      => 0.0,
		'sale'         => 0.0,
		'effective'    => 0.0,
		'is_free'      => true,
		'discount_pct' => 0,
	);

	if ( ! $course_id ) {
		return $default_result;
	}

	// Explicit free flags take precedence.
	$is_free_flag = (
		'1' === (string) get_post_meta( $course_id, '_edupro_is_free', true ) ||
		'1' === (string) get_post_meta( $course_id, '_edupro_course_free', true )
	);

	if ( $is_free_flag ) {
		return $default_result;
	}

	// Check whether sale end date has passed.
	$sale_end_date = get_post_meta( $course_id, '_edupro_sale_end_date', true );
	$sale_expired  = false;
	if ( ! empty( $sale_end_date ) ) {
		$end_ts = strtotime( $sale_end_date . ' 23:59:59' );
		if ( $end_ts && current_time( 'timestamp' ) > $end_ts ) {
			$sale_expired = true;
		}
	}

	$sanitize = function ( $val ) {
		if ( '' === $val || null === $val ) {
			return null;
		}
		return function_exists( 'edupro_sanitize_price' )
			? edupro_sanitize_price( $val )
			: (float) preg_replace( '/[^0-9.]/', '', (string) $val );
	};

	$target_lower    = strtolower( $target );
	$override_reg    = $sanitize( get_post_meta( $course_id, '_edupro_regular_price_' . $target_lower, true ) );
	$override_sale   = $sanitize( get_post_meta( $course_id, '_edupro_sale_price_' . $target_lower, true ) );
	$has_override    = ( null !== $override_reg && $override_reg > 0 ) || ( ! $sale_expired && null !== $override_sale && $override_sale > 0 );

	if ( $has_override ) {
		$regular = ( null !== $override_reg && $override_reg > 0 ) ? round( $override_reg, 2 ) : 0.0;
		$sale    = ( ! $sale_expired && null !== $override_sale && $override_sale > 0 ) ? round( $override_sale, 2 ) : 0.0;

		if ( $regular <= 0 && $sale > 0 ) {
			$regular = $sale;
			$sale    = 0.0;
		}
	} else {
		$base_currency = edupro_get_default_currency();
		$base_reg      = $sanitize( get_post_meta( $course_id, '_edupro_regular_price', true ) );
		$base_sale     = $sanitize( get_post_meta( $course_id, '_edupro_sale_price', true ) );
		$legacy_price  = $sanitize( get_post_meta( $course_id, '_edupro_course_price', true ) );

		if ( null === $base_reg && null !== $legacy_price ) {
			$base_reg = $legacy_price;
		}

		$raw_reg  = ( null !== $base_reg && $base_reg > 0 ) ? $base_reg : 0.0;
		$raw_sale = ( ! $sale_expired && null !== $base_sale && $base_sale > 0 ) ? $base_sale : 0.0;

		if ( $raw_reg <= 0 && $raw_sale > 0 ) {
			$raw_reg  = $raw_sale;
			$raw_sale = 0.0;
		}

		$regular = edupro_convert_price( $raw_reg, $base_currency, $target );
		$sale    = $raw_sale > 0 ? edupro_convert_price( $raw_sale, $base_currency, $target ) : 0.0;
	}

	// Only treat sale as active when strictly lower than regular price.
	$on_sale   = ( $sale > 0 && $regular > 0 && $sale < $regular );
	$effective = $on_sale ? $sale : $regular;

	if ( ! $on_sale ) {
		$sale = 0.0;
	}

	$is_free      = ( $effective <= 0.0 );
	$discount_pct = ( $on_sale && $regular > 0 )
		? (int) round( ( ( $regular - $sale ) / $regular ) * 100 )
		: 0;

	return array(
		'currency'     => $target,
		'symbol'       => $symbol,
		'regular'      => round( $regular, 2 ),
		'sale'         => round( $sale, 2 ),
		'effective'    => round( $effective, 2 ),
		'is_free'      => $is_free,
		'discount_pct' => max( 0, min( 99, $discount_pct ) ),
	);
}

/**
 * Format a numeric price with the appropriate currency symbol and 2 decimals.
 *
 * @param float|int|string $price    Raw numeric price value.
 * @param string           $currency Optional ISO currency code. Defaults to active currency.
 * @return string Formatted price string (e.g. "₹1,299.00", "£49.00", "$59.00").
 */
function edupro_format_price( $price, $currency = '' ) {
	$numeric = function_exists( 'edupro_sanitize_price' )
		? edupro_sanitize_price( $price )
		: (float) preg_replace( '/[^0-9.]/', '', (string) $price );

	$symbol = edupro_get_currency_symbol( $currency );

	return $symbol . number_format( (float) $numeric, 2, '.', ',' );
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. COOKIE PERSISTENCE & AJAX CURRENCY SWITCHER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Persist the selected currency into the `edupro_currency` browser cookie.
 *
 * @param string $code Validated ISO currency code.
 * @return bool True if cookie was set/scheduled, false if invalid code.
 */
function edupro_set_currency_cookie( $code ) {
	$valid = edupro_sanitize_currency_code( $code );
	if ( ! $valid ) {
		return false;
	}

	// Update in-request state immediately.
	edupro_get_active_currency( $valid );
	$_COOKIE['edupro_currency'] = $valid;

	if ( ! headers_sent() ) {
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		setcookie(
			'edupro_currency',
			$valid,
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

	return true;
}

/**
 * Persist `?currency=GBP|INR|USD` URL query parameter into the cookie.
 */
function edupro_persist_currency_from_query() {
	if ( ! isset( $_GET['currency'] ) ) {
		return;
	}

	$requested = edupro_sanitize_currency_code( wp_unslash( $_GET['currency'] ) );
	if ( $requested ) {
		edupro_set_currency_cookie( $requested );
	}
}
add_action( 'template_redirect', 'edupro_persist_currency_from_query', 1 );

/**
 * AJAX handler for live currency switching (`edupro_switch_currency`).
 */
function edupro_ajax_switch_currency() {
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

	$raw_code = isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : '';
	$code     = edupro_sanitize_currency_code( $raw_code );

	if ( ! $code ) {
		wp_send_json_error(
			array( 'message' => __( 'Unsupported currency selected.', 'edupro' ) ),
			400
		);
	}

	edupro_set_currency_cookie( $code );

	$supported = edupro_get_supported_currencies();
	$info      = $supported[ $code ];

	wp_send_json_success(
		array(
			'currency' => $code,
			'symbol'   => $info['symbol'],
			'name'     => $info['name'],
			'region'   => $info['region'],
			'message'  => sprintf(
				/* translators: 1: currency symbol, 2: currency code */
				__( 'Currency switched to %1$s %2$s.', 'edupro' ),
				$info['symbol'],
				$code
			),
		)
	);
}
add_action( 'wp_ajax_edupro_switch_currency', 'edupro_ajax_switch_currency' );
add_action( 'wp_ajax_nopriv_edupro_switch_currency', 'edupro_ajax_switch_currency' );
