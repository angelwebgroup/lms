<?php
/**
 * Template Name: Course Checkout
 * Template Post Type: page
 *
 * Modular Multi-Currency, Multi-Course Cart & 5-Gateway EduPro Checkout Page (No WooCommerce):
 * - Supports BOTH modes seamlessly:
 *   * Cart Mode (?mode=cart OR course_id=0 with cart items) -> checks out all unpaid cart courses together
 *   * Single-Course Buy Now Mode (?course_id=ID) -> checks out a single course directly
 *   * Gateway Return Mode (Cashfree, PayPal v2, Stripe) -> recovers ALL courses from edupro_get_order_course_ids()
 * - Active currency pricing via edupro_get_course_price_data() + inline currency switcher
 * - Coupon Code Box (Apply/Remove via edupro_apply_coupon) with live Subtotal/Discount/Tax/Total
 * - Renders ONLY enabled gateways returned by edupro_get_enabled_gateways($currency) in priority order:
 *   1. Cashfree (Primary India)
 *   2. Razorpay (Backup India)
 *   3. Stripe (UK & Global — Stripe Payment Element)
 *   4. PayPal (International — PayPal Orders v2)
 *   5. Bank Transfer (India Bank/UPI + UK Sort Code/IBAN — creates pending order only)
 * - Exposes structured data-* config (including data-cart-mode="1"|"0") on #edupro-checkout-app for assets/js/main.js
 *
 * @package EduPro
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

// -----------------------------------------------------------------------------
// 1. Gateway Return Parameters & Checkout Mode Detection
// -----------------------------------------------------------------------------
$return_gateway  = '';
$return_order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '';
$return_token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
$return_pi       = isset( $_GET['payment_intent'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_intent'] ) ) : '';
$paypal_cancel   = ( isset( $_GET['edupro_paypal_cancel'] ) && '1' === $_GET['edupro_paypal_cancel'] )
	|| ( isset( $_GET['edupro_paypal'] ) && 'cancel' === $_GET['edupro_paypal'] );

if ( isset( $_GET['edupro_cashfree_return'] ) && '1' === $_GET['edupro_cashfree_return'] && $return_order_id ) {
	$return_gateway = 'cashfree';
} elseif ( ( ( isset( $_GET['edupro_paypal_return'] ) && '1' === $_GET['edupro_paypal_return'] ) || ( isset( $_GET['edupro_paypal'] ) && 'success' === $_GET['edupro_paypal'] ) ) && $return_order_id ) {
	$return_gateway = 'paypal';
} elseif ( isset( $_GET['edupro_stripe_return'] ) && '1' === $_GET['edupro_stripe_return'] && $return_order_id ) {
	$return_gateway = 'stripe';
}

$return_order = ( $return_order_id && function_exists( 'edupro_get_order' ) ) ? edupro_get_order( $return_order_id ) : null;

$requested_course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
if ( ! $requested_course_id && isset( $_GET['course'] ) ) {
	$requested_course_id = absint( $_GET['course'] );
}
$mode_param = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
$cart_count = function_exists( 'edupro_get_cart_count' ) ? (int) edupro_get_cart_count() : 0;

$is_cart_mode        = false;
$checkout_course_ids = array();

if ( $return_gateway && $return_order ) {
	if ( function_exists( 'edupro_get_order_course_ids' ) ) {
		$checkout_course_ids = edupro_get_order_course_ids( $return_order );
	} elseif ( ! empty( $return_order->course_id ) ) {
		$checkout_course_ids = array( absint( $return_order->course_id ) );
	}
	$is_cart_mode = ( count( $checkout_course_ids ) > 1 );
} elseif ( 'cart' === $mode_param || ( 0 === $requested_course_id && $cart_count > 0 ) ) {
	$is_cart_mode = true;
	$raw_cart_ids = function_exists( 'edupro_get_cart_items' ) ? (array) edupro_get_cart_items() : array();
	foreach ( $raw_cart_ids as $cid ) {
		$cid = absint( $cid );
		if ( ! $cid || 'lms_course' !== get_post_type( $cid ) || 'publish' !== get_post_status( $cid ) ) {
			continue;
		}
		if ( is_user_logged_in() && function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $cid ) ) {
			continue;
		}
		$checkout_course_ids[] = $cid;
	}
	$checkout_course_ids = array_values( array_unique( $checkout_course_ids ) );
} elseif ( $requested_course_id ) {
	if ( 'lms_course' === get_post_type( $requested_course_id ) && 'publish' === get_post_status( $requested_course_id ) ) {
		$checkout_course_ids = array( $requested_course_id );
	}
}

$dashboard_url = function_exists( 'edupro_get_dashboard_url' ) ? edupro_get_dashboard_url() : home_url( '/dashboard/' );
$courses_url   = get_post_type_archive_link( 'lms_course' ) ? get_post_type_archive_link( 'lms_course' ) : home_url( '/courses/' );
$cart_url      = function_exists( 'edupro_get_cart_url' ) ? edupro_get_cart_url() : home_url( '/cart/' );
$checkout_base = get_permalink();
$current_url   = $is_cart_mode
	? add_query_arg( 'mode', 'cart', $checkout_base )
	: ( ! empty( $checkout_course_ids ) ? add_query_arg( 'course_id', $checkout_course_ids[0], $checkout_base ) : $checkout_base );

$auth_nonce    = wp_create_nonce( 'edupro_nonce' );
$payment_nonce = wp_create_nonce( 'edupro_payment_nonce' );

// -----------------------------------------------------------------------------
// 2. Active Currency & Supported Currencies
// -----------------------------------------------------------------------------
$active_currency = function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR';
$currency_symbol = function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol( $active_currency ) : '₹';

$switch_currencies = array(
	'INR' => '₹ INR',
	'GBP' => '£ GBP',
);
if ( 'USD' === $active_currency ) {
	$switch_currencies['USD'] = '$ USD';
}

// -----------------------------------------------------------------------------
// 3. Enabled Payment Gateways for Active Currency (Priority Sorted)
// -----------------------------------------------------------------------------
$enabled_gateways = array();
if ( function_exists( 'edupro_get_enabled_gateways' ) ) {
	$enabled_gateways = edupro_get_enabled_gateways( $active_currency );
} else {
	if ( '1' === get_option( 'edupro_cashfree_enabled', '0' ) && 'INR' === $active_currency ) {
		$enabled_gateways['cashfree'] = array(
			'id'          => 'cashfree',
			'label'       => __( 'Cashfree Payments', 'edupro' ),
			'description' => __( 'Instant checkout via UPI, Credit/Debit Cards, NetBanking & Wallets.', 'edupro' ),
			'region'      => __( 'India', 'edupro' ),
			'badge'       => __( 'Primary • UPI, Cards, NetBanking', 'edupro' ),
			'priority'    => 10,
			'sandbox'     => '1' === get_option( 'edupro_cashfree_sandbox', '1' ),
		);
	}
	if ( '1' === get_option( 'edupro_razorpay_enabled', '0' ) ) {
		$enabled_gateways['razorpay'] = array(
			'id'          => 'razorpay',
			'label'       => __( 'Razorpay', 'edupro' ),
			'description' => __( 'Pay via UPI, RuPay, Visa/Mastercard, NetBanking & EMI.', 'edupro' ),
			'region'      => __( 'India', 'edupro' ),
			'badge'       => __( 'UPI, RuPay, Cards, EMI', 'edupro' ),
			'priority'    => 20,
			'sandbox'     => '1' === get_option( 'edupro_razorpay_sandbox', get_option( 'edupro_razorpay_test_mode', '1' ) ),
		);
	}
	if ( '1' === get_option( 'edupro_stripe_enabled', '0' ) ) {
		$enabled_gateways['stripe'] = array(
			'id'          => 'stripe',
			'label'       => __( 'Stripe', 'edupro' ),
			'description' => __( 'UK & International Cards, Apple Pay, Google Pay & Klarna.', 'edupro' ),
			'region'      => __( 'UK & Global', 'edupro' ),
			'badge'       => __( 'Cards, Apple Pay, Google Pay, Klarna', 'edupro' ),
			'priority'    => 30,
			'sandbox'     => '1' === get_option( 'edupro_stripe_sandbox', get_option( 'edupro_stripe_test_mode', '1' ) ),
		);
	}
	if ( '1' === get_option( 'edupro_paypal_enabled', '0' ) && in_array( $active_currency, array( 'GBP', 'USD' ), true ) ) {
		$enabled_gateways['paypal'] = array(
			'id'          => 'paypal',
			'label'       => __( 'PayPal', 'edupro' ),
			'description' => __( 'Checkout securely with your PayPal balance, bank, or Pay in 3.', 'edupro' ),
			'region'      => __( 'International', 'edupro' ),
			'badge'       => __( 'PayPal & Pay in 3', 'edupro' ),
			'priority'    => 40,
			'sandbox'     => '1' === get_option( 'edupro_paypal_sandbox', '1' ),
		);
	}
	if ( '1' === get_option( 'edupro_bank_transfer_enabled', '0' ) ) {
		$enabled_gateways['bank_transfer'] = array(
			'id'          => 'bank_transfer',
			'label'       => __( 'Bank Transfer', 'edupro' ),
			'description' => __( 'Direct bank transfer (India NEFT/IMPS/UPI or UK Faster Payments).', 'edupro' ),
			'region'      => __( 'UK & India', 'edupro' ),
			'badge'       => __( 'Manual Verification', 'edupro' ),
			'priority'    => 50,
			'sandbox'     => false,
		);
	}
}

$all_enabled_gateways = function_exists( 'edupro_get_enabled_gateways' ) ? edupro_get_enabled_gateways( '' ) : $enabled_gateways;
$default_gateway      = ! empty( $enabled_gateways ) ? array_key_first( $enabled_gateways ) : '';

// Client SDK configuration values.
$stripe_pub_key   = get_option( 'edupro_stripe_publishable_key', '' );
$razorpay_key_id  = get_option( 'edupro_razorpay_key_id', '' );
$cashfree_sandbox = '1' === get_option( 'edupro_cashfree_sandbox', '1' );
$cashfree_env     = $cashfree_sandbox ? 'sandbox' : 'production';

// Bank transfer details (India + UK).
$bank_india = array(
	'account_name'   => get_option( 'edupro_bank_account_name', get_bloginfo( 'name' ) ),
	'bank_name'      => get_option( 'edupro_bank_name', '' ),
	'account_number' => get_option( 'edupro_bank_account_number', '' ),
	'ifsc'           => get_option( 'edupro_bank_ifsc', '' ),
	'upi_id'         => get_option( 'edupro_bank_upi_id', '' ),
);
$bank_uk = array(
	'account_name'   => get_option( 'edupro_bank_uk_account_name', get_bloginfo( 'name' ) ),
	'sort_code'      => get_option( 'edupro_bank_uk_sort_code', '' ),
	'account_number' => get_option( 'edupro_bank_uk_account_number', '' ),
	'iban'           => get_option( 'edupro_bank_uk_iban', '' ),
);
$bank_instructions = get_option( 'edupro_bank_instructions', '' );

// -----------------------------------------------------------------------------
// 4. Build Checkout Courses List & Combined Pricing / Tax Breakdown
// -----------------------------------------------------------------------------
$checkout_courses  = array();
$combined_regular  = 0.0;
$combined_subtotal = 0.0;

foreach ( $checkout_course_ids as $cid ) {
	$c_post = get_post( $cid );
	if ( ! $c_post || 'lms_course' !== $c_post->post_type ) {
		continue;
	}

	$c_price = array(
		'currency'     => $active_currency,
		'symbol'       => $currency_symbol,
		'regular'      => 0.0,
		'sale'         => 0.0,
		'effective'    => 0.0,
		'is_free'      => true,
		'discount_pct' => 0,
	);
	if ( function_exists( 'edupro_get_course_price_data' ) ) {
		$c_price = wp_parse_args( edupro_get_course_price_data( $cid, $active_currency ), $c_price );
	} elseif ( function_exists( 'edupro_get_course_price_info' ) ) {
		$legacy                  = edupro_get_course_price_info( $cid );
		$c_price['regular']      = (float) $legacy['regular'];
		$c_price['sale']         = (float) $legacy['sale'];
		$c_price['effective']    = (float) $legacy['final'];
		$c_price['is_free']      = (bool) $legacy['is_free'];
		$c_price['discount_pct'] = ( $legacy['has_sale'] && $legacy['regular'] > 0 )
			? (int) round( ( ( $legacy['regular'] - $legacy['final'] ) / $legacy['regular'] ) * 100 )
			: 0;
	}

	$active_currency = $c_price['currency'];
	$currency_symbol = $c_price['symbol'];

	if ( ! $c_price['is_free'] ) {
		$combined_subtotal += (float) $c_price['effective'];
		$combined_regular  += max( (float) $c_price['regular'], (float) $c_price['effective'] );
	}

	$checkout_courses[] = array(
		'id'         => $cid,
		'title'      => $c_post->post_title,
		'url'        => get_permalink( $cid ),
		'thumbnail'  => get_the_post_thumbnail_url( $cid, 'course-thumb' ),
		'instructor' => get_the_author_meta( 'display_name', (int) $c_post->post_author ),
		'lessons'    => function_exists( 'edupro_get_course_lessons_count' ) ? edupro_get_course_lessons_count( $cid ) : 0,
		'duration'   => absint( get_post_meta( $cid, '_edupro_duration', true ) ),
		'rating'     => function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $cid ) : 0,
		'price_data' => $c_price,
	);
}

$course_count = count( $checkout_courses );
$primary_item = ! empty( $checkout_courses ) ? $checkout_courses[0] : null;
$all_free     = ( $course_count > 0 && $combined_subtotal <= 0 );
$subtotal     = $all_free ? 0.0 : round( $combined_subtotal, 2 );
$has_sale     = ( ! $all_free && $combined_regular > $subtotal );
$sale_savings = $has_sale ? round( $combined_regular - $subtotal, 2 ) : 0.0;
$discount_pct = ( $has_sale && $combined_regular > 0 ) ? (int) round( ( $sale_savings / $combined_regular ) * 100 ) : 0;

$tax_rate     = max( 0.0, (float) get_option( 'edupro_tax_rate', 0 ) );
$tax_label    = get_option( 'edupro_tax_label', __( 'VAT / GST', 'edupro' ) );
$tax_amount   = ( ! $all_free && $tax_rate > 0 ) ? round( $subtotal * ( $tax_rate / 100 ), 2 ) : 0.0;
$grand_total  = round( $subtotal + $tax_amount, 2 );

$format_money = function ( $amt ) use ( $active_currency, $currency_symbol ) {
	if ( function_exists( 'edupro_format_price' ) ) {
		return edupro_format_price( $amt, $active_currency );
	}
	return $currency_symbol . number_format( (float) $amt, 2, '.', ',' );
};

$checkout_title = '';
if ( 1 === $course_count ) {
	$checkout_title = $primary_item['title'];
} elseif ( $course_count > 1 ) {
	$checkout_title = sprintf(
		/* translators: 1: first course title, 2: remaining count */
		__( '%1$s (+%2$d more)', 'edupro' ),
		$primary_item['title'],
		$course_count - 1
	);
}

$current_user       = is_user_logged_in() ? wp_get_current_user() : null;
$is_enrolled_single = ( ! $is_cart_mode && $primary_item && is_user_logged_in() && function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $primary_item['id'] ) );
$return_is_complete = ( $return_order && 'completed' === $return_order->status );
$js_return_gateway  = ( $return_gateway && ! $return_is_complete ) ? $return_gateway : '';
$final_fmt          = $all_free ? __( 'Free', 'edupro' ) : $format_money( $grand_total );
?>

<main id="primary" class="edupro-checkout-section">
	<div class="edupro-container"
		 id="edupro-checkout-app"
		 data-cart-mode="<?php echo $is_cart_mode ? '1' : '0'; ?>"
		 data-course-id="<?php echo esc_attr( $is_cart_mode ? 0 : ( $primary_item ? $primary_item['id'] : 0 ) ); ?>"
		 data-course-title="<?php echo esc_attr( $checkout_title ); ?>"
		 data-currency="<?php echo esc_attr( $active_currency ); ?>"
		 data-currency-symbol="<?php echo esc_attr( $currency_symbol ); ?>"
		 data-subtotal="<?php echo esc_attr( number_format( $subtotal, 2, '.', '' ) ); ?>"
		 data-tax-rate="<?php echo esc_attr( $tax_rate ); ?>"
		 data-total="<?php echo esc_attr( number_format( $grand_total, 2, '.', '' ) ); ?>"
		 data-checkout-url="<?php echo esc_url( $current_url ); ?>"
		 data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>"
		 data-return-gateway="<?php echo esc_attr( $js_return_gateway ); ?>"
		 data-return-order-id="<?php echo esc_attr( $return_order_id ); ?>"
		 data-return-token="<?php echo esc_attr( $return_token ); ?>"
		 data-return-pi="<?php echo esc_attr( $return_pi ); ?>"
		 data-stripe-pk="<?php echo esc_attr( $stripe_pub_key ); ?>"
		 data-razorpay-key="<?php echo esc_attr( $razorpay_key_id ); ?>"
		 data-cashfree-env="<?php echo esc_attr( $cashfree_env ); ?>"
		 data-payment-nonce="<?php echo esc_attr( $payment_nonce ); ?>"
		 data-auth-nonce="<?php echo esc_attr( $auth_nonce ); ?>"
		 data-user-name="<?php echo esc_attr( $current_user ? $current_user->display_name : '' ); ?>"
		 data-user-email="<?php echo esc_attr( $current_user ? $current_user->user_email : '' ); ?>"
		 data-site-name="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">

		<div class="edupro-checkout-header">
			<h1><?php echo $is_cart_mode ? esc_html__( 'Cart Checkout', 'edupro' ) : esc_html__( 'Course Checkout', 'edupro' ); ?></h1>
			<p><?php esc_html_e( 'Complete your enrollment securely and get instant access to all course materials.', 'edupro' ); ?></p>
		</div>

		<!-- =============================================================== -->
		<!-- GATEWAY RETURN VERIFICATION BANNERS                             -->
		<!-- =============================================================== -->
		<?php if ( $paypal_cancel ) : ?>
			<div class="edupro-verify-banner edupro-verify-banner--warning" role="status">
				<div class="edupro-verify-banner__body">
					<strong><?php esc_html_e( 'PayPal Checkout Cancelled', 'edupro' ); ?></strong>
					<p><?php esc_html_e( 'No payment was captured. You can try again or choose another payment method below.', 'edupro' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $return_gateway && $return_order_id ) : ?>
			<?php if ( $return_is_complete ) : ?>
				<div id="edupro-verify-banner" class="edupro-verify-banner edupro-verify-banner--success" role="status">
					<div class="edupro-verify-banner__body">
						<strong><?php esc_html_e( 'Payment Verified & Enrollment Confirmed!', 'edupro' ); ?></strong>
						<p>
							<?php
							printf(
								/* translators: %s: order id */
								esc_html__( 'Order %s has been verified and all courses in your order are now unlocked.', 'edupro' ),
								'<code>' . esc_html( $return_order_id ) . '</code>'
							);
							?>
						</p>
					</div>
					<div class="edupro-verify-banner__actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-sm">
							<?php esc_html_e( 'Go to Student Dashboard', 'edupro' ); ?> &rarr;
						</a>
						<?php if ( function_exists( 'edupro_get_invoice_url' ) ) : ?>
							<a href="<?php echo esc_url( edupro_get_invoice_url( $return_order_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-btn-sm">
								<?php esc_html_e( 'View Invoice', 'edupro' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php else : ?>
				<div id="edupro-verify-banner" class="edupro-verify-banner edupro-verify-banner--loading" role="status" aria-live="polite">
					<span class="edupro-verify-spinner" aria-hidden="true"></span>
					<div class="edupro-verify-banner__body">
						<strong id="edupro-verify-title"><?php esc_html_e( 'Verifying Your Payment…', 'edupro' ); ?></strong>
						<p id="edupro-verify-message">
							<?php
							printf(
								/* translators: 1: gateway name, 2: order id */
								esc_html__( 'Confirming your %1$s payment for order %2$s directly with the payment gateway. Please do not close this page.', 'edupro' ),
								esc_html( ucfirst( $return_gateway ) ),
								'<code>' . esc_html( $return_order_id ) . '</code>'
							);
							?>
						</p>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( empty( $checkout_courses ) ) : ?>
			<div class="edupro-checkout-empty">
				<h2><?php echo $is_cart_mode ? esc_html__( 'Your Cart is Empty', 'edupro' ) : esc_html__( 'No Course Selected', 'edupro' ); ?></h2>
				<p>
					<?php
					echo $is_cart_mode
						? esc_html__( 'Your shopping cart has no unpaid courses left to check out. Explore our catalog to add courses.', 'edupro' )
						: esc_html__( 'Please pick a course from our catalog to proceed to checkout.', 'edupro' );
					?>
				</p>
				<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-primary">
					<?php esc_html_e( 'Browse Courses', 'edupro' ); ?>
				</a>
			</div>

		<?php elseif ( $is_enrolled_single && ! $return_gateway ) : ?>
			<div class="edupro-checkout-enrolled-card">
				<h2><?php esc_html_e( 'You Are Already Enrolled in This Course!', 'edupro' ); ?></h2>
				<p><?php echo esc_html( $primary_item['title'] ); ?></p>
				<div class="edupro-enrolled-actions">
					<a href="<?php echo esc_url( $primary_item['url'] ); ?>" class="edupro-btn edupro-btn-primary">
						<?php esc_html_e( 'Open Course', 'edupro' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-btn edupro-btn-outline">
						<?php esc_html_e( 'Student Dashboard', 'edupro' ); ?>
					</a>
				</div>
			</div>

		<?php else : ?>
			<div class="edupro-checkout-grid">

				<!-- ========================================================= -->
				<!-- LEFT COLUMN: ORDER SUMMARY + CURRENCY + COUPON            -->
				<!-- ========================================================= -->
				<aside class="edupro-checkout-summary">
					<div class="edupro-summary-card">
						<div class="edupro-summary-header-row">
							<h2 class="edupro-summary-heading">
								<?php
								if ( $is_cart_mode || $course_count > 1 ) {
									printf(
										/* translators: %d: number of courses */
										esc_html( _n( 'Order Summary (%d Course)', 'Order Summary (%d Courses)', $course_count, 'edupro' ) ),
										$course_count
									);
								} else {
									esc_html_e( 'Order Summary', 'edupro' );
								}
								?>
							</h2>
							<?php if ( ! $all_free ) : ?>
								<div class="edupro-summary-currency-toggle edupro-currency-switcher" role="group" aria-label="<?php esc_attr_e( 'Switch Currency', 'edupro' ); ?>">
									<?php foreach ( $switch_currencies as $cur_code => $cur_label ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'currency', $cur_code, $current_url ) ); ?>"
										   class="edupro-currency-btn <?php echo $active_currency === $cur_code ? 'is-active' : ''; ?>"
										   data-currency-switch="<?php echo esc_attr( $cur_code ); ?>">
											<?php echo esc_html( $cur_label ); ?>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $is_cart_mode || $course_count > 1 ) : ?>
							<!-- Multi-Course Cart Summary List -->
							<div class="edupro-summary-courses-list">
								<?php foreach ( $checkout_courses as $item ) : ?>
									<?php $item_pd = $item['price_data']; ?>
									<div class="edupro-summary-course-mini">
										<div class="edupro-summary-course-mini__thumb">
											<?php if ( $item['thumbnail'] ) : ?>
												<img src="<?php echo esc_url( $item['thumbnail'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
											<?php else : ?>
												<div class="edupro-summary-thumb-placeholder"><?php echo esc_html( mb_substr( $item['title'], 0, 1 ) ); ?></div>
											<?php endif; ?>
										</div>
										<div class="edupro-summary-course-mini__info">
											<h4><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h4>
											<span class="edupro-summary-course-mini__instructor"><?php echo esc_html( $item['instructor'] ); ?></span>
										</div>
										<div class="edupro-summary-course-mini__price">
											<strong><?php echo esc_html( $item_pd['is_free'] ? __( 'Free', 'edupro' ) : $format_money( $item_pd['effective'] ) ); ?></strong>
											<?php if ( ! $item_pd['is_free'] && $item_pd['regular'] > $item_pd['effective'] ) : ?>
												<del><?php echo esc_html( $format_money( $item_pd['regular'] ) ); ?></del>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
								<?php if ( $is_cart_mode && ! $return_gateway ) : ?>
									<div class="edupro-summary-edit-cart">
										<a href="<?php echo esc_url( $cart_url ); ?>">&larr; <?php esc_html_e( 'Edit Shopping Cart', 'edupro' ); ?></a>
									</div>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<!-- Single-Course Summary Card -->
							<div class="edupro-summary-course">
								<div class="edupro-summary-thumb">
									<?php if ( $primary_item['thumbnail'] ) : ?>
										<img src="<?php echo esc_url( $primary_item['thumbnail'] ); ?>" alt="<?php echo esc_attr( $primary_item['title'] ); ?>">
									<?php else : ?>
										<div class="edupro-summary-thumb-placeholder"><?php echo esc_html( mb_substr( $primary_item['title'], 0, 1 ) ); ?></div>
									<?php endif; ?>
								</div>
								<div class="edupro-summary-details">
									<h3><a href="<?php echo esc_url( $primary_item['url'] ); ?>"><?php echo esc_html( $primary_item['title'] ); ?></a></h3>
									<p class="edupro-summary-instructor">
										<?php printf( esc_html__( 'Instructor: %s', 'edupro' ), esc_html( $primary_item['instructor'] ) ); ?>
									</p>
									<div class="edupro-summary-meta">
										<?php if ( $primary_item['lessons'] > 0 ) : ?>
											<span><?php printf( esc_html( _n( '%d Lesson', '%d Lessons', $primary_item['lessons'], 'edupro' ) ), $primary_item['lessons'] ); ?></span>
										<?php endif; ?>
										<?php if ( $primary_item['duration'] > 0 && function_exists( 'edupro_get_duration_label' ) ) : ?>
											<span><?php echo esc_html( edupro_get_duration_label( $primary_item['duration'] ) ); ?></span>
										<?php endif; ?>
										<?php if ( $primary_item['rating'] > 0 ) : ?>
											<span>&#9733; <?php echo esc_html( number_format( $primary_item['rating'], 1 ) ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! $all_free ) : ?>
							<!-- Coupon Code Box -->
							<div class="edupro-coupon-box">
								<form id="edupro-coupon-form" class="edupro-coupon-form" method="post" novalidate>
									<label for="edupro-coupon-input" class="screen-reader-text"><?php esc_html_e( 'Coupon Code', 'edupro' ); ?></label>
									<input type="text"
										   id="edupro-coupon-input"
										   class="edupro-coupon-input"
										   name="coupon_code"
										   placeholder="<?php esc_attr_e( 'Have a coupon? (e.g. WELCOME20)', 'edupro' ); ?>"
										   autocomplete="off">
									<button type="submit" id="edupro-coupon-apply" class="edupro-btn edupro-btn-outline edupro-btn-sm">
										<?php esc_html_e( 'Apply', 'edupro' ); ?>
									</button>
								</form>
								<div id="edupro-coupon-applied" class="edupro-coupon-applied" style="display:none;">
									<span id="edupro-coupon-applied-label"></span>
									<button type="button" id="edupro-coupon-remove" class="edupro-coupon-remove" aria-label="<?php esc_attr_e( 'Remove coupon', 'edupro' ); ?>">&times;</button>
								</div>
								<div id="edupro-coupon-message" class="edupro-coupon-message" role="alert" style="display:none;"></div>
								<input type="hidden" id="edupro-active-coupon" name="active_coupon" value="">
							</div>
						<?php endif; ?>

						<!-- Totals Breakdown -->
						<div class="edupro-summary-breakdown">
							<?php if ( $has_sale ) : ?>
								<div class="edupro-summary-row">
									<span><?php esc_html_e( 'Original Price', 'edupro' ); ?></span>
									<del><?php echo esc_html( $format_money( $combined_regular ) ); ?></del>
								</div>
								<div class="edupro-summary-row edupro-summary-discount">
									<span>
										<?php
										printf(
											/* translators: %d: discount percentage */
											esc_html__( 'Course Savings (%d%% OFF)', 'edupro' ),
											$discount_pct
										);
										?>
									</span>
									<span>-<?php echo esc_html( $format_money( $sale_savings ) ); ?></span>
								</div>
							<?php endif; ?>

							<div class="edupro-summary-row">
								<span><?php esc_html_e( 'Subtotal', 'edupro' ); ?></span>
								<span id="edupro-summary-subtotal"><?php echo esc_html( $all_free ? __( 'Free', 'edupro' ) : $format_money( $subtotal ) ); ?></span>
							</div>

							<div id="edupro-summary-coupon-row" class="edupro-summary-row edupro-summary-discount-row" style="display:none;">
								<span><?php esc_html_e( 'Coupon Discount', 'edupro' ); ?> (<code id="edupro-summary-coupon-code"></code>)</span>
								<span id="edupro-summary-coupon-amount"></span>
							</div>

							<?php if ( ! $all_free && $tax_rate > 0 ) : ?>
								<div id="edupro-summary-tax-row" class="edupro-summary-row edupro-summary-tax-row">
									<span><?php echo esc_html( $tax_label ); ?> (<?php echo esc_html( $tax_rate ); ?>%)</span>
									<span id="edupro-summary-tax-amount"><?php echo esc_html( $format_money( $tax_amount ) ); ?></span>
								</div>
							<?php endif; ?>

							<div class="edupro-summary-row edupro-summary-total">
								<strong><?php esc_html_e( 'Total Payable', 'edupro' ); ?></strong>
								<strong id="edupro-summary-total" class="edupro-total-price"><?php echo esc_html( $final_fmt ); ?></strong>
							</div>
						</div>

						<ul class="edupro-checkout-guarantees">
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
								<?php esc_html_e( 'Full Lifetime Access to Course Content', 'edupro' ); ?>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
								<?php esc_html_e( 'Verified Certificate of Completion Included', 'edupro' ); ?>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<?php esc_html_e( '256-bit SSL & Server-Verified Checkout', 'edupro' ); ?>
							</li>
						</ul>
					</div>
				</aside>

				<!-- ========================================================= -->
				<!-- RIGHT COLUMN: AUTH (LOGGED OUT) OR 5-GATEWAY CHECKOUT     -->
				<!-- ========================================================= -->
				<div class="edupro-checkout-payment">

					<?php if ( ! is_user_logged_in() ) : ?>
						<!-- Step 1: Login or Register before paying -->
						<div class="edupro-checkout-card">
							<h2 class="edupro-checkout-step-title"><?php esc_html_e( 'Step 1: Sign In or Create an Account', 'edupro' ); ?></h2>
							<p class="edupro-checkout-step-sub"><?php esc_html_e( 'Sign in or register below to link your courses and invoice to your student portal.', 'edupro' ); ?></p>

							<div class="edupro-checkout-auth-tabs" role="tablist">
								<button type="button" class="edupro-auth-tab is-active" data-auth-tab="login" role="tab" aria-selected="true">
									<?php esc_html_e( 'Sign In', 'edupro' ); ?>
								</button>
								<button type="button" class="edupro-auth-tab" data-auth-tab="register" role="tab" aria-selected="false">
									<?php esc_html_e( 'New Student? Register', 'edupro' ); ?>
								</button>
							</div>

							<form id="edupro-checkout-login-form" class="edupro-auth-form edupro-checkout-auth-panel is-active" method="post" novalidate data-edupro-inline-bound="1">
								<input type="hidden" name="action" value="edupro_login">
								<input type="hidden" name="nonce" value="<?php echo esc_attr( $auth_nonce ); ?>">
								<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">

								<div class="edupro-alert edupro-checkout-auth-alert" role="alert" style="display:none;"></div>

								<div class="edupro-form-group">
									<label for="co-login-user"><?php esc_html_e( 'Username or Email', 'edupro' ); ?></label>
									<input type="text" id="co-login-user" name="username" required autocomplete="username">
								</div>
								<div class="edupro-form-group">
									<label for="co-login-pass"><?php esc_html_e( 'Password', 'edupro' ); ?></label>
									<input type="password" id="co-login-pass" name="password" required autocomplete="current-password">
								</div>
								<button type="submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
									<?php esc_html_e( 'Sign In & Continue to Payment', 'edupro' ); ?>
								</button>
							</form>

							<form id="edupro-checkout-register-form" class="edupro-auth-form edupro-checkout-auth-panel" method="post" novalidate style="display:none;" data-edupro-inline-bound="1">
								<input type="hidden" name="action" value="edupro_register">
								<input type="hidden" name="nonce" value="<?php echo esc_attr( $auth_nonce ); ?>">
								<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">

								<div class="edupro-alert edupro-checkout-auth-alert" role="alert" style="display:none;"></div>

								<div class="edupro-form-grid-2">
									<div class="edupro-form-group">
										<label for="co-reg-fname"><?php esc_html_e( 'First Name', 'edupro' ); ?></label>
										<input type="text" id="co-reg-fname" name="first_name" required>
									</div>
									<div class="edupro-form-group">
										<label for="co-reg-lname"><?php esc_html_e( 'Last Name', 'edupro' ); ?></label>
										<input type="text" id="co-reg-lname" name="last_name" required>
									</div>
								</div>
								<div class="edupro-form-group">
									<label for="co-reg-email"><?php esc_html_e( 'Email Address', 'edupro' ); ?></label>
									<input type="email" id="co-reg-email" name="email" required>
								</div>
								<div class="edupro-form-grid-2">
									<div class="edupro-form-group">
										<label for="co-reg-pass"><?php esc_html_e( 'Password', 'edupro' ); ?></label>
										<input type="password" id="co-reg-pass" name="password" required minlength="8">
									</div>
									<div class="edupro-form-group">
										<label for="co-reg-cpass"><?php esc_html_e( 'Confirm Password', 'edupro' ); ?></label>
										<input type="password" id="co-reg-cpass" name="confirm_password" required minlength="8">
									</div>
								</div>
								<div class="edupro-form-group">
									<label class="edupro-checkbox-label">
										<input type="checkbox" name="terms" value="1" required checked>
										<span><?php esc_html_e( 'I agree to the Terms & Privacy Policy', 'edupro' ); ?></span>
									</label>
								</div>
								<button type="submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
									<?php esc_html_e( 'Create Account & Continue to Payment', 'edupro' ); ?>
								</button>
							</form>
						</div>

					<?php elseif ( $all_free ) : ?>
						<!-- Free Course(s) Direct Enrollment -->
						<div class="edupro-checkout-card">
							<h2 class="edupro-checkout-step-title"><?php esc_html_e( 'Free Course Enrollment', 'edupro' ); ?></h2>
							<p><?php esc_html_e( 'Your selection is completely free. Click below to enroll immediately and start learning!', 'edupro' ); ?></p>
							<div id="edupro-checkout-alert" class="edupro-alert" role="alert" style="display:none;"></div>
							<?php if ( $is_cart_mode ) : ?>
								<button type="button" id="edupro-free-cart-btn" data-gateway-pay="bank_transfer" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
									<?php esc_html_e( 'Complete Free Enrollment', 'edupro' ); ?> &rarr;
								</button>
							<?php else : ?>
								<button type="button" id="edupro-free-checkout-btn" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block edupro-free-enroll-btn" data-course-id="<?php echo esc_attr( $primary_item['id'] ); ?>" data-nonce="<?php echo esc_attr( $auth_nonce ); ?>">
									<?php esc_html_e( 'Complete Free Enrollment', 'edupro' ); ?> &rarr;
								</button>
							<?php endif; ?>
						</div>

					<?php elseif ( empty( $enabled_gateways ) ) : ?>
						<!-- Fallback notice when no gateway is enabled for active currency or site-wide -->
						<div class="edupro-checkout-card">
							<h2 class="edupro-checkout-step-title"><?php esc_html_e( 'Payment Methods Unavailable', 'edupro' ); ?></h2>
							<?php if ( ! empty( $all_enabled_gateways ) ) : ?>
								<?php $alt_currency = ( 'INR' === $active_currency ) ? 'GBP' : 'INR'; ?>
								<div class="edupro-alert edupro-alert-info" role="alert">
									<?php
									printf(
										/* translators: 1: active currency, 2: alternate currency */
										esc_html__( 'No payment gateways are currently enabled for %1$s. Switch to %2$s to view available payment methods.', 'edupro' ),
										'<strong>' . esc_html( $active_currency ) . '</strong>',
										'<strong>' . esc_html( $alt_currency ) . '</strong>'
									);
									?>
								</div>
								<div class="edupro-enrolled-actions" style="margin-top:16px;">
									<a href="<?php echo esc_url( add_query_arg( 'currency', $alt_currency, $current_url ) ); ?>"
									   class="edupro-btn edupro-btn-primary"
									   data-currency-switch="<?php echo esc_attr( $alt_currency ); ?>">
										<?php printf( esc_html__( 'Switch to %s', 'edupro' ), esc_html( $alt_currency ) ); ?> &rarr;
									</a>
								</div>
							<?php else : ?>
								<div class="edupro-alert edupro-alert-warning" role="alert">
									<?php esc_html_e( 'No online payment gateways are currently enabled on this site. Please check back soon or contact support to complete your enrollment.', 'edupro' ); ?>
								</div>
								<div class="edupro-enrolled-actions" style="margin-top:16px;">
									<?php if ( current_user_can( 'manage_options' ) ) : ?>
										<a href="<?php echo esc_url( admin_url( 'options-general.php?page=edupro-payment-gateways' ) ); ?>" class="edupro-btn edupro-btn-primary">
											<?php esc_html_e( 'Configure Payment Gateways', 'edupro' ); ?> &rarr;
										</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-outline">
										&larr; <?php esc_html_e( 'Back to Courses', 'edupro' ); ?>
									</a>
								</div>
							<?php endif; ?>
						</div>

					<?php else : ?>
						<!-- Paid Checkout: Priority-Sorted Dynamic Gateway Selector -->
						<div class="edupro-checkout-card" id="edupro-payment-box">
							<div class="edupro-checkout-account-bar">
								<span>
									<?php
									printf(
										/* translators: 1: user name, 2: email */
										esc_html__( 'Enrolling as %1$s (%2$s)', 'edupro' ),
										'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
										esc_html( $current_user->user_email )
									);
									?>
								</span>
							</div>

							<h2 class="edupro-checkout-step-title"><?php esc_html_e( 'Select Payment Method', 'edupro' ); ?></h2>

							<div id="edupro-checkout-alert" class="edupro-alert" role="alert" style="display:none;"></div>

							<!-- Dynamic Gateway Tabs -->
							<div class="edupro-gateway-tabs" role="tablist">
								<?php foreach ( $enabled_gateways as $gw_id => $gw ) : ?>
									<?php
									$is_active   = ( $gw_id === $default_gateway );
									$badge_class = 'edupro-gateway-badge';
									if ( 'cashfree' === $gw_id ) {
										$badge_class .= ' edupro-gateway-badge--primary';
									} elseif ( 'stripe' === $gw_id ) {
										$badge_class .= ' edupro-gateway-badge--uk';
									} elseif ( 'razorpay' === $gw_id ) {
										$badge_class .= ' edupro-gateway-badge--backup';
									}
									?>
									<button type="button"
											class="edupro-gateway-tab <?php echo $is_active ? 'is-active' : ''; ?>"
											data-gateway="<?php echo esc_attr( $gw_id ); ?>"
											role="tab"
											aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
										<span class="edupro-gw-logo" aria-hidden="true">
											<?php if ( 'cashfree' === $gw_id ) : ?>
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="5" fill="#00C48C"/><path d="M15.8 8.4a4.6 4.6 0 1 0 0 7.2" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/><circle cx="15.5" cy="12" r="1.6" fill="#fff"/></svg>
											<?php elseif ( 'razorpay' === $gw_id ) : ?>
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M14.5 2L7.5 14h5l-2 8 9-13h-5.2L14.5 2z" fill="#3395FF"/></svg>
											<?php elseif ( 'stripe' === $gw_id ) : ?>
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="3" fill="#635BFF"/><rect x="2" y="9" width="20" height="3" fill="#fff" opacity="0.35"/></svg>
											<?php elseif ( 'paypal' === $gw_id ) : ?>
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7.5 20H5l2.5-15h6.2c3 0 4.8 1.5 4.3 4.5-.5 3-2.7 4.5-5.8 4.5H9.6L8.5 20H7.5z" fill="#003087"/><path d="M10.5 21H8l1.8-11h4.6c2.5 0 4 1.2 3.6 3.7-.4 2.4-2.2 3.8-4.8 3.8h-2l-.7 3.5z" fill="#009CDE"/></svg>
											<?php else : ?>
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2"><path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M2 18h20M12 2L2 7h20L12 2z"/></svg>
											<?php endif; ?>
										</span>
										<span class="edupro-gw-tab-text">
											<strong><?php echo esc_html( ! empty( $gw['label'] ) ? $gw['label'] : ucfirst( $gw_id ) ); ?></strong>
											<?php if ( ! empty( $gw['region'] ) ) : ?>
												<span class="edupro-gateway-region"><?php echo esc_html( $gw['region'] ); ?></span>
											<?php endif; ?>
										</span>
										<?php if ( ! empty( $gw['badge'] ) ) : ?>
											<span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $gw['badge'] ); ?></span>
										<?php endif; ?>
									</button>
								<?php endforeach; ?>
							</div>

							<!-- Dynamic Gateway Panels -->
							<div class="edupro-gateway-panels">

								<?php foreach ( $enabled_gateways as $gw_id => $gw ) : ?>
									<?php $is_active = ( $gw_id === $default_gateway ); ?>

									<?php if ( 'cashfree' === $gw_id ) : ?>
										<div class="edupro-gateway-panel <?php echo $is_active ? 'is-active' : ''; ?>" data-gateway-panel="cashfree">
											<?php if ( ! empty( $gw['sandbox'] ) ) : ?>
												<div class="edupro-sandbox-notice">
													<?php esc_html_e( 'Sandbox / Test Mode Active — Use Cashfree test UPI or test card credentials.', 'edupro' ); ?>
												</div>
											<?php endif; ?>
											<div class="edupro-gw-info-box">
												<span class="edupro-gateway-badge edupro-gateway-badge--primary"><?php esc_html_e( 'Primary • UPI, Cards, NetBanking', 'edupro' ); ?></span>
												<h3><?php esc_html_e( 'Pay Instantly with Cashfree Payments', 'edupro' ); ?></h3>
												<p><?php esc_html_e( 'Fast, reliable checkout for India supporting Google Pay, PhonePe, Paytm UPI, RuPay, Visa, Mastercard, NetBanking, and Wallets.', 'edupro' ); ?></p>
											</div>
											<button type="button" id="edupro-pay-cashfree-btn" data-gateway-pay="cashfree" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
												<span class="btn-label"><?php printf( esc_html__( 'Pay %s with Cashfree', 'edupro' ), '<span class="edupro-pay-btn-amount">' . esc_html( $final_fmt ) . '</span>' ); ?></span>
											</button>
										</div>

									<?php elseif ( 'razorpay' === $gw_id ) : ?>
										<div class="edupro-gateway-panel <?php echo $is_active ? 'is-active' : ''; ?>" data-gateway-panel="razorpay">
											<?php if ( ! empty( $gw['sandbox'] ) ) : ?>
												<div class="edupro-sandbox-notice">
													<?php esc_html_e( 'Test Mode Active — Use Razorpay test credentials.', 'edupro' ); ?>
												</div>
											<?php endif; ?>
											<div class="edupro-gw-info-box">
												<span class="edupro-gateway-badge edupro-gateway-badge--backup"><?php esc_html_e( 'UPI, RuPay, Cards, EMI', 'edupro' ); ?></span>
												<h3><?php esc_html_e( 'Pay with Razorpay', 'edupro' ); ?></h3>
												<p><?php esc_html_e( 'Supports UPI, RuPay, Credit/Debit Cards, NetBanking, Wallets, and No-Cost EMI with instant verification.', 'edupro' ); ?></p>
											</div>
											<button type="button" id="edupro-pay-razorpay-btn" data-gateway-pay="razorpay" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
												<span class="btn-label"><?php printf( esc_html__( 'Pay %s with Razorpay', 'edupro' ), '<span class="edupro-pay-btn-amount">' . esc_html( $final_fmt ) . '</span>' ); ?></span>
											</button>
										</div>

									<?php elseif ( 'stripe' === $gw_id ) : ?>
										<div class="edupro-gateway-panel <?php echo $is_active ? 'is-active' : ''; ?>" data-gateway-panel="stripe">
											<?php if ( ! empty( $gw['sandbox'] ) ) : ?>
												<div class="edupro-sandbox-notice">
													<?php esc_html_e( 'Stripe Test Mode Active — Use Stripe test cards (e.g. 4242 4242 4242 4242).', 'edupro' ); ?>
												</div>
											<?php endif; ?>
											<div class="edupro-gw-info-box">
												<span class="edupro-gateway-badge edupro-gateway-badge--uk"><?php esc_html_e( 'Cards, Apple Pay, Google Pay, Klarna', 'edupro' ); ?></span>
												<h3><?php esc_html_e( 'Pay with Stripe (UK & Global)', 'edupro' ); ?></h3>
												<p><?php esc_html_e( 'Pay securely using UK & international Debit/Credit Cards, Apple Pay, Google Pay, Klarna, or Clearpay.', 'edupro' ); ?></p>
											</div>

											<div class="edupro-form-group">
												<label for="edupro-stripe-cardholder"><?php esc_html_e( 'Cardholder / Billing Name', 'edupro' ); ?></label>
												<input type="text" id="edupro-stripe-cardholder" value="<?php echo esc_attr( $current_user->display_name ); ?>" placeholder="<?php esc_attr_e( 'Full name on card', 'edupro' ); ?>">
											</div>

											<div class="edupro-form-group">
												<label><?php esc_html_e( 'Payment Details', 'edupro' ); ?></label>
												<div id="edupro-stripe-payment-element" class="edupro-stripe-element-box">
													<p class="edupro-stripe-loading-hint"><?php esc_html_e( 'Loading secure Stripe Payment Element…', 'edupro' ); ?></p>
												</div>
												<div id="edupro-stripe-card-errors" class="edupro-field-error" role="alert"></div>
											</div>

											<button type="button" id="edupro-pay-stripe-btn" data-gateway-pay="stripe" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
												<span class="btn-label"><?php printf( esc_html__( 'Pay %s Securely', 'edupro' ), '<span class="edupro-pay-btn-amount">' . esc_html( $final_fmt ) . '</span>' ); ?></span>
											</button>
										</div>

									<?php elseif ( 'paypal' === $gw_id ) : ?>
										<div class="edupro-gateway-panel <?php echo $is_active ? 'is-active' : ''; ?>" data-gateway-panel="paypal">
											<?php if ( ! empty( $gw['sandbox'] ) ) : ?>
												<div class="edupro-sandbox-notice">
													<?php esc_html_e( 'PayPal Sandbox Mode Active — Sign in with a PayPal Sandbox buyer account.', 'edupro' ); ?>
												</div>
											<?php endif; ?>
											<div class="edupro-gw-info-box">
												<span class="edupro-gateway-badge"><?php esc_html_e( 'PayPal & Pay in 3', 'edupro' ); ?></span>
												<h3><?php esc_html_e( 'Pay with PayPal', 'edupro' ); ?></h3>
												<p><?php esc_html_e( 'You will be redirected to PayPal to approve your payment (including PayPal Pay in 3 where eligible) and returned here for instant verification.', 'edupro' ); ?></p>
											</div>
											<button type="button" id="edupro-pay-paypal-btn" data-gateway-pay="paypal" class="edupro-btn edupro-btn-paypal edupro-btn-lg edupro-btn-block">
												<span class="btn-label"><?php printf( esc_html__( 'Continue to PayPal (%s)', 'edupro' ), '<span class="edupro-pay-btn-amount">' . esc_html( $final_fmt ) . '</span>' ); ?> &rarr;</span>
											</button>
										</div>

									<?php elseif ( 'bank_transfer' === $gw_id ) : ?>
										<div class="edupro-gateway-panel <?php echo $is_active ? 'is-active' : ''; ?>" data-gateway-panel="bank_transfer">
											<div class="edupro-gw-info-box">
												<span class="edupro-gateway-badge edupro-gateway-badge--backup"><?php esc_html_e( 'UK & India Bank Transfer', 'edupro' ); ?></span>
												<h3><?php esc_html_e( 'Direct Bank Transfer / UPI', 'edupro' ); ?></h3>
												<p><?php esc_html_e( 'Transfer the course fee to either our India or UK bank account below. Submitting creates a pending order — enrollment activates after our team verifies your payment.', 'edupro' ); ?></p>
											</div>

											<div class="edupro-bank-regions-grid">
												<!-- India Bank / UPI Details -->
												<div class="edupro-bank-details-box edupro-bank-region-card edupro-bank-region-card--india">
													<h4 class="edupro-bank-region-title"><?php esc_html_e( 'India Bank / UPI (INR ₹)', 'edupro' ); ?></h4>
													<dl class="edupro-bank-dl">
														<div>
															<dt><?php esc_html_e( 'Account Name', 'edupro' ); ?></dt>
															<dd><?php echo esc_html( $bank_india['account_name'] ? $bank_india['account_name'] : '—' ); ?></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'Bank Name', 'edupro' ); ?></dt>
															<dd><?php echo esc_html( $bank_india['bank_name'] ? $bank_india['bank_name'] : '—' ); ?></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'Account Number', 'edupro' ); ?></dt>
															<dd><code><?php echo esc_html( $bank_india['account_number'] ? $bank_india['account_number'] : '—' ); ?></code></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'IFSC Code', 'edupro' ); ?></dt>
															<dd><code><?php echo esc_html( $bank_india['ifsc'] ? $bank_india['ifsc'] : '—' ); ?></code></dd>
														</div>
														<?php if ( ! empty( $bank_india['upi_id'] ) ) : ?>
															<div>
																<dt><?php esc_html_e( 'UPI ID', 'edupro' ); ?></dt>
																<dd><code><?php echo esc_html( $bank_india['upi_id'] ); ?></code></dd>
															</div>
														<?php endif; ?>
													</dl>
												</div>

												<!-- UK Bank Transfer Details -->
												<div class="edupro-bank-details-box edupro-bank-region-card edupro-bank-region-card--uk">
													<h4 class="edupro-bank-region-title"><?php esc_html_e( 'UK Bank Transfer (GBP £)', 'edupro' ); ?></h4>
													<dl class="edupro-bank-dl">
														<div>
															<dt><?php esc_html_e( 'Account Name', 'edupro' ); ?></dt>
															<dd><?php echo esc_html( $bank_uk['account_name'] ? $bank_uk['account_name'] : '—' ); ?></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'Sort Code', 'edupro' ); ?></dt>
															<dd><code><?php echo esc_html( $bank_uk['sort_code'] ? $bank_uk['sort_code'] : '—' ); ?></code></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'Account Number', 'edupro' ); ?></dt>
															<dd><code><?php echo esc_html( $bank_uk['account_number'] ? $bank_uk['account_number'] : '—' ); ?></code></dd>
														</div>
														<div>
															<dt><?php esc_html_e( 'IBAN', 'edupro' ); ?></dt>
															<dd><code><?php echo esc_html( $bank_uk['iban'] ? $bank_uk['iban'] : '—' ); ?></code></dd>
														</div>
													</dl>
												</div>
											</div>

											<?php if ( $bank_instructions ) : ?>
												<p class="edupro-bank-instructions"><?php echo nl2br( esc_html( $bank_instructions ) ); ?></p>
											<?php endif; ?>

											<div id="edupro-bank-order-confirmation" class="edupro-alert edupro-alert-info" role="status" style="display:none;"></div>

											<button type="button" id="edupro-pay-bank-btn" data-gateway-pay="bank_transfer" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
												<span class="btn-label"><?php esc_html_e( 'Place Bank Transfer Order (Pending Verification)', 'edupro' ); ?></span>
											</button>
										</div>
									<?php endif; ?>

								<?php endforeach; ?>

							</div>
						</div>
					<?php endif; ?>

				</div>
			</div>
		<?php endif; ?>

	</div>
</main>

<?php if ( ! empty( $checkout_courses ) && ! is_user_logged_in() ) : ?>
<script>
(function(){
	var ajaxUrl = (window.edupro_vars && window.edupro_vars.ajaxurl) ? window.edupro_vars.ajaxurl : <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	document.querySelectorAll('.edupro-auth-tab[data-auth-tab]').forEach(function(tab){
		tab.addEventListener('click', function(){
			var target = tab.getAttribute('data-auth-tab');
			document.querySelectorAll('.edupro-auth-tab').forEach(function(t){
				t.classList.remove('is-active');
				t.setAttribute('aria-selected', 'false');
			});
			tab.classList.add('is-active');
			tab.setAttribute('aria-selected', 'true');
			var loginF = document.getElementById('edupro-checkout-login-form');
			var regF   = document.getElementById('edupro-checkout-register-form');
			if (loginF) loginF.style.display = (target === 'login') ? 'block' : 'none';
			if (regF)   regF.style.display   = (target === 'register') ? 'block' : 'none';
		});
	});

	['edupro-checkout-login-form', 'edupro-checkout-register-form'].forEach(function(formId){
		var form = document.getElementById(formId);
		if (!form) return;
		form.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl = form.querySelector('.edupro-checkout-auth-alert');
			var btn     = form.querySelector('button[type="submit"]');
			btn.disabled = true;
			if (alertEl) alertEl.style.display = 'none';

			fetch(ajaxUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (res && res.success) {
						if (alertEl) {
							alertEl.className = 'edupro-alert edupro-alert-success edupro-checkout-auth-alert';
							alertEl.textContent = (res.data && res.data.message) ? res.data.message : 'Authenticated! Loading payment options…';
							alertEl.style.display = 'block';
						}
						setTimeout(function(){ window.location.reload(); }, 450);
					} else {
						btn.disabled = false;
						if (alertEl) {
							alertEl.className = 'edupro-alert edupro-alert-error edupro-checkout-auth-alert';
							alertEl.textContent = (res && res.data && res.data.message) ? res.data.message : 'Authentication failed.';
							alertEl.style.display = 'block';
						}
					}
				})
				.catch(function(){
					btn.disabled = false;
					if (alertEl) {
						alertEl.className = 'edupro-alert edupro-alert-error edupro-checkout-auth-alert';
						alertEl.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>;
						alertEl.style.display = 'block';
					}
				});
		});
	});
})();
</script>
<?php endif; ?>

<?php
get_footer();
