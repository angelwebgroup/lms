<?php
/**
 * EduPro Standalone Print-to-PDF Invoice Renderer
 *
 * Generates signed invoice URLs (`edupro_get_invoice_url`) and serves
 * a clean, print-optimized HTML invoice on `?edupro_invoice=ORDER_ID`.
 *
 * @package EduPro
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. INVOICE URL GENERATOR
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a signed URL for viewing/printing an order invoice.
 *
 * @param string $order_id Public EduPro order ID.
 * @return string Signed invoice URL.
 */
function edupro_get_invoice_url( $order_id ) {
	$order_id = sanitize_text_field( (string) $order_id );
	if ( '' === $order_id ) {
		return '';
	}

	return add_query_arg(
		array(
			'edupro_invoice' => $order_id,
			'nonce'          => wp_create_nonce( 'edupro_invoice_' . $order_id ),
		),
		home_url( '/' )
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. HELPERS FOR GATEWAY / VERIFICATION LABELS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Resolve a human-readable payment gateway label for invoices.
 *
 * @param string $gateway_id Gateway identifier.
 * @return string
 */
function edupro_invoice_gateway_label( $gateway_id ) {
	$gateway_id = sanitize_key( (string) $gateway_id );

	if ( function_exists( 'edupro_get_gateway' ) ) {
		$gw = edupro_get_gateway( $gateway_id );
		if ( is_object( $gw ) && method_exists( $gw, 'get_label' ) ) {
			return (string) $gw->get_label();
		}
	}

	$map = array(
		'cashfree'      => __( 'Cashfree Payments (India)', 'edupro' ),
		'razorpay'      => __( 'Razorpay', 'edupro' ),
		'stripe'        => __( 'Stripe (Card / Apple Pay / Google Pay)', 'edupro' ),
		'paypal'        => __( 'PayPal', 'edupro' ),
		'bank_transfer' => __( 'Bank Transfer', 'edupro' ),
		'free'          => __( 'Free Enrollment', 'edupro' ),
	);

	return isset( $map[ $gateway_id ] ) ? $map[ $gateway_id ] : ucwords( str_replace( '_', ' ', $gateway_id ) );
}

/**
 * Resolve a human-readable verification method label for invoices.
 *
 * @param string $verified_via Verification source stored on the order.
 * @return string
 */
function edupro_invoice_verified_label( $verified_via ) {
	$key = sanitize_key( (string) $verified_via );
	$map = array(
		'webhook'         => __( 'Verified via Gateway Webhook', 'edupro' ),
		'callback'        => __( 'Verified via Server-Side API Check', 'edupro' ),
		'reconcile'       => __( 'Verified via Order Reconciliation', 'edupro' ),
		'admin_approval'  => __( 'Approved by Administrator', 'edupro' ),
		'admin'           => __( 'Approved by Administrator', 'edupro' ),
		'free'            => __( 'Free Course Enrollment', 'edupro' ),
	);

	if ( '' === $key ) {
		return __( 'Pending Verification', 'edupro' );
	}

	return isset( $map[ $key ] ) ? $map[ $key ] : ucwords( str_replace( '_', ' ', $key ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. TEMPLATE_REDIRECT STANDALONE INVOICE VIEW
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Intercept `?edupro_invoice=ORDER_ID` requests and output a standalone
 * print-optimized HTML invoice page.
 */
function edupro_maybe_render_invoice_page() {
	if ( ! isset( $_GET['edupro_invoice'] ) ) {
		return;
	}

	$order_id = sanitize_text_field( wp_unslash( $_GET['edupro_invoice'] ) );
	if ( '' === $order_id ) {
		return;
	}

	// Require authenticated user.
	if ( ! is_user_logged_in() ) {
		$login_page_id = (int) get_option( 'edupro_login_page_id' );
		$login_url     = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
		wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( edupro_get_invoice_url( $order_id ) ), $login_url ) );
		exit;
	}

	// Verify nonce.
	$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'edupro_invoice_' . $order_id ) ) {
		wp_die(
			esc_html__( 'Invalid or expired invoice link. Please reopen the invoice from your dashboard or Orders screen.', 'edupro' ),
			esc_html__( 'Invoice Access Denied', 'edupro' ),
			array( 'response' => 403 )
		);
	}

	// Fetch order record.
	$order = null;
	if ( function_exists( 'edupro_get_order' ) ) {
		$order = edupro_get_order( $order_id );
	} else {
		global $wpdb;
		$table = $wpdb->prefix . 'edupro_orders';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %s LIMIT 1", $order_id ) );
	}

	if ( ! $order ) {
		wp_die(
			esc_html__( 'Order not found.', 'edupro' ),
			esc_html__( 'Invoice Not Found', 'edupro' ),
			array( 'response' => 404 )
		);
	}

	// Authorize: only order owner or administrator (`manage_options`).
	$current_user_id = get_current_user_id();
	if ( ! current_user_can( 'manage_options' ) && (int) $order->user_id !== $current_user_id ) {
		wp_die(
			esc_html__( 'You are not authorized to view this invoice.', 'edupro' ),
			esc_html__( 'Access Denied', 'edupro' ),
			array( 'response' => 403 )
		);
	}

	// Gather invoice data with safe defaults for legacy v1.0 order rows.
	$invoice_number  = 'INV-' . $order->order_id;
	$order_currency  = ! empty( $order->currency ) ? strtoupper( (string) $order->currency ) : ( function_exists( 'edupro_get_default_currency' ) ? edupro_get_default_currency() : 'INR' );
	$total_paid      = isset( $order->amount ) ? (float) $order->amount : 0.0;
	$discount_amount = isset( $order->discount_amount ) ? (float) $order->discount_amount : 0.0;
	$tax_amount      = isset( $order->tax_amount ) ? (float) $order->tax_amount : 0.0;
	$subtotal        = ( isset( $order->subtotal ) && (float) $order->subtotal > 0 )
		? (float) $order->subtotal
		: max( 0.0, round( $total_paid - $tax_amount + $discount_amount, 2 ) );
	$coupon_code     = isset( $order->coupon_code ) ? (string) $order->coupon_code : '';
	$refund_amount   = isset( $order->refund_amount ) ? (float) $order->refund_amount : 0.0;
	$status          = isset( $order->status ) ? strtolower( (string) $order->status ) : 'pending';
	$transaction_id  = ! empty( $order->transaction_id ) ? (string) $order->transaction_id : '—';
	$verified_via    = isset( $order->verified_via ) ? (string) $order->verified_via : '';
	$verified_at     = ! empty( $order->verified_at ) && '0000-00-00 00:00:00' !== $order->verified_at ? (string) $order->verified_at : '';
	$created_at      = ! empty( $order->created_at ) ? (string) $order->created_at : current_time( 'mysql' );

	$fmt = function ( $val ) use ( $order_currency ) {
		return function_exists( 'edupro_format_price' )
			? edupro_format_price( $val, $order_currency )
			: $order_currency . ' ' . number_format( (float) $val, 2 );
	};

	// Company details from options.
	$company_name    = trim( (string) get_option( 'edupro_company_name', '' ) );
	if ( '' === $company_name ) {
		$company_name = get_bloginfo( 'name' );
	}
	$company_address = trim( (string) get_option( 'edupro_company_address', '' ) );
	$company_tax_id  = trim( (string) get_option( 'edupro_company_tax_id', '' ) );
	$tax_label       = trim( (string) get_option( 'edupro_tax_label', 'VAT / GST' ) );
	$tax_rate        = (float) get_option( 'edupro_tax_rate', 0 );

	// Student details.
	$student       = get_userdata( (int) $order->user_id );
	$student_name  = $student ? $student->display_name : sprintf( __( 'Student #%d', 'edupro' ), (int) $order->user_id );
	$student_email = $student ? $student->user_email : '—';

	// Course details.
	$course_id    = absint( $order->course_id );
	$course_title = $course_id ? get_the_title( $course_id ) : __( 'Course Enrollment', 'edupro' );
	if ( '' === $course_title ) {
		$course_title = sprintf( __( 'Course #%d', 'edupro' ), $course_id );
	}

	// Status badge styling.
	$status_styles = array(
		'completed'          => array( 'label' => __( 'Paid', 'edupro' ), 'bg' => '#e6f9ef', 'color' => '#138a4b' ),
		'pending'            => array( 'label' => __( 'Pending', 'edupro' ), 'bg' => '#fef3c7', 'color' => '#b45309' ),
		'processing'         => array( 'label' => __( 'Processing', 'edupro' ), 'bg' => '#e0f2fe', 'color' => '#0369a1' ),
		'refunded'           => array( 'label' => __( 'Refunded', 'edupro' ), 'bg' => '#ede9fe', 'color' => '#6d28d9' ),
		'partially_refunded' => array( 'label' => __( 'Partially Refunded', 'edupro' ), 'bg' => '#ede9fe', 'color' => '#6d28d9' ),
		'failed'             => array( 'label' => __( 'Failed', 'edupro' ), 'bg' => '#fee2e2', 'color' => '#b91c1c' ),
		'cancelled'          => array( 'label' => __( 'Cancelled', 'edupro' ), 'bg' => '#f3f4f6', 'color' => '#4b5563' ),
	);
	$badge = isset( $status_styles[ $status ] )
		? $status_styles[ $status ]
		: array( 'label' => ucfirst( $status ), 'bg' => '#f3f4f6', 'color' => '#374151' );

	$dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
	$back_url          = current_user_can( 'manage_options' )
		? admin_url( 'admin.php?page=edupro-orders' )
		: ( $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' ) );

	$gateway_label  = edupro_invoice_gateway_label( isset( $order->gateway ) ? $order->gateway : '' );
	$verified_label = edupro_invoice_verified_label( $verified_via );

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $invoice_number . ' — ' . $company_name ); ?></title>
	<style>
		*, *::before, *::after { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 32px 16px;
			background: #f4f5f9;
			color: #1a1a2e;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			font-size: 14px;
			line-height: 1.55;
			-webkit-font-smoothing: antialiased;
		}
		.inv-toolbar {
			max-width: 820px;
			margin: 0 auto 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
		}
		.inv-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 10px 18px;
			border-radius: 6px;
			font-size: 14px;
			font-weight: 600;
			text-decoration: none;
			cursor: pointer;
			border: 1px solid transparent;
			transition: all 0.2s ease;
		}
		.inv-btn-primary {
			background: #e31b23;
			color: #fff;
		}
		.inv-btn-primary:hover { background: #c0151c; }
		.inv-btn-secondary {
			background: #fff;
			color: #1a1a2e;
			border-color: #d6d9e4;
		}
		.inv-btn-secondary:hover { background: #f8f9ff; }
		.inv-sheet {
			max-width: 820px;
			margin: 0 auto;
			background: #fff;
			border-radius: 10px;
			box-shadow: 0 8px 30px rgba(26, 26, 46, 0.08);
			overflow: hidden;
			border: 1px solid #e8e8f0;
		}
		.inv-accent-bar {
			height: 6px;
			background: linear-gradient(90deg, #e31b23 0%, #c0151c 60%, #1a1a2e 100%);
		}
		.inv-body { padding: 40px 44px; }
		.inv-header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			gap: 24px;
			padding-bottom: 28px;
			border-bottom: 1px solid #eceef5;
		}
		.inv-brand h1 {
			margin: 0 0 6px;
			font-size: 24px;
			font-weight: 800;
			color: #1a1a2e;
			letter-spacing: -0.02em;
		}
		.inv-brand p {
			margin: 2px 0;
			color: #4a4a6a;
			font-size: 13px;
			white-space: pre-line;
		}
		.inv-meta { text-align: right; }
		.inv-title {
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.12em;
			color: #8888aa;
			margin: 0 0 4px;
		}
		.inv-number {
			font-size: 20px;
			font-weight: 700;
			color: #1a1a2e;
			font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
			margin: 0 0 10px;
		}
		.inv-status {
			display: inline-block;
			padding: 4px 12px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}
		.inv-parties {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 24px;
			padding: 24px 0;
			border-bottom: 1px solid #eceef5;
		}
		.inv-section-label {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #8888aa;
			margin: 0 0 8px;
		}
		.inv-party-name {
			font-size: 15px;
			font-weight: 700;
			color: #1a1a2e;
			margin: 0 0 4px;
		}
		.inv-party-detail {
			margin: 2px 0;
			color: #4a4a6a;
			font-size: 13px;
		}
		.inv-table {
			width: 100%;
			border-collapse: collapse;
			margin: 26px 0;
		}
		.inv-table th {
			text-align: left;
			padding: 12px 14px;
			background: #f8f9ff;
			color: #4a4a6a;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			border-top: 1px solid #e8e8f0;
			border-bottom: 1px solid #e8e8f0;
		}
		.inv-table td {
			padding: 16px 14px;
			border-bottom: 1px solid #eceef5;
			vertical-align: top;
		}
		.inv-table th.num, .inv-table td.num { text-align: right; white-space: nowrap; }
		.inv-item-title { font-weight: 600; font-size: 15px; color: #1a1a2e; }
		.inv-item-sub { font-size: 12px; color: #6b6b8d; margin-top: 3px; }
		.inv-totals-wrap {
			display: flex;
			justify-content: flex-end;
			margin-top: 8px;
		}
		.inv-totals {
			width: 320px;
			max-width: 100%;
		}
		.inv-totals-row {
			display: flex;
			justify-content: space-between;
			padding: 7px 0;
			font-size: 14px;
			color: #4a4a6a;
		}
		.inv-totals-row.discount { color: #138a4b; font-weight: 500; }
		.inv-totals-row.refund { color: #6d28d9; font-weight: 500; }
		.inv-totals-row.grand {
			margin-top: 8px;
			padding-top: 12px;
			border-top: 2px solid #1a1a2e;
			font-size: 17px;
			font-weight: 800;
			color: #1a1a2e;
		}
		.inv-payment-box {
			margin-top: 32px;
			padding: 18px 20px;
			background: #f8f9ff;
			border: 1px solid #e8e8f0;
			border-radius: 8px;
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 14px 24px;
		}
		.inv-pay-item dt {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #8888aa;
			margin: 0 0 3px;
		}
		.inv-pay-item dd {
			margin: 0;
			font-size: 13px;
			font-weight: 600;
			color: #1a1a2e;
			word-break: break-all;
		}
		.inv-footer {
			margin-top: 34px;
			padding-top: 18px;
			border-top: 1px solid #eceef5;
			text-align: center;
			font-size: 12px;
			color: #8888aa;
		}
		@media (max-width: 640px) {
			.inv-body { padding: 24px 20px; }
			.inv-header { flex-direction: column; }
			.inv-meta { text-align: left; }
			.inv-parties, .inv-payment-box { grid-template-columns: 1fr; }
		}
		@media print {
			@page { margin: 12mm; size: A4; }
			body { background: #fff; padding: 0; }
			.inv-toolbar { display: none !important; }
			.inv-sheet { box-shadow: none; border: none; border-radius: 0; max-width: 100%; }
			.inv-body { padding: 20px 10px; }
			.inv-accent-bar, .inv-status, .inv-table th, .inv-payment-box {
				-webkit-print-color-adjust: exact;
				print-color-adjust: exact;
			}
		}
	</style>
</head>
<body>
	<div class="inv-toolbar">
		<a href="<?php echo esc_url( $back_url ); ?>" class="inv-btn inv-btn-secondary">
			&larr; <?php esc_html_e( 'Back', 'edupro' ); ?>
		</a>
		<button type="button" class="inv-btn inv-btn-primary" onclick="window.print();">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
			<?php esc_html_e( 'Print / Save as PDF', 'edupro' ); ?>
		</button>
	</div>

	<main class="inv-sheet">
		<div class="inv-accent-bar"></div>
		<div class="inv-body">
			<!-- Header -->
			<header class="inv-header">
				<div class="inv-brand">
					<h1><?php echo esc_html( $company_name ); ?></h1>
					<?php if ( '' !== $company_address ) : ?>
						<p><?php echo esc_html( $company_address ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $company_tax_id ) : ?>
						<p><strong><?php esc_html_e( 'Tax / GST / VAT ID:', 'edupro' ); ?></strong> <?php echo esc_html( $company_tax_id ); ?></p>
					<?php endif; ?>
					<p><?php echo esc_html( home_url( '/' ) ); ?></p>
				</div>
				<div class="inv-meta">
					<p class="inv-title"><?php esc_html_e( 'Tax Invoice / Receipt', 'edupro' ); ?></p>
					<p class="inv-number"><?php echo esc_html( $invoice_number ); ?></p>
					<span class="inv-status" style="background:<?php echo esc_attr( $badge['bg'] ); ?>;color:<?php echo esc_attr( $badge['color'] ); ?>;">
						<?php echo esc_html( $badge['label'] ); ?>
					</span>
				</div>
			</header>

			<!-- Billed To & Invoice Details -->
			<section class="inv-parties">
				<div>
					<h2 class="inv-section-label"><?php esc_html_e( 'Billed To', 'edupro' ); ?></h2>
					<p class="inv-party-name"><?php echo esc_html( $student_name ); ?></p>
					<p class="inv-party-detail"><?php echo esc_html( $student_email ); ?></p>
					<p class="inv-party-detail"><?php echo esc_html( sprintf( __( 'Student ID: #%d', 'edupro' ), (int) $order->user_id ) ); ?></p>
				</div>
				<div>
					<h2 class="inv-section-label"><?php esc_html_e( 'Invoice Details', 'edupro' ); ?></h2>
					<p class="inv-party-detail"><strong><?php esc_html_e( 'Order ID:', 'edupro' ); ?></strong> <?php echo esc_html( $order->order_id ); ?></p>
					<p class="inv-party-detail"><strong><?php esc_html_e( 'Invoice Date:', 'edupro' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_at ) ) ); ?></p>
					<p class="inv-party-detail"><strong><?php esc_html_e( 'Currency:', 'edupro' ); ?></strong> <?php echo esc_html( $order_currency ); ?></p>
				</div>
			</section>

			<!-- Line Items -->
			<table class="inv-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Description', 'edupro' ); ?></th>
						<th class="num"><?php esc_html_e( 'Qty', 'edupro' ); ?></th>
						<th class="num"><?php esc_html_e( 'Amount', 'edupro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<div class="inv-item-title"><?php echo esc_html( $course_title ); ?></div>
							<div class="inv-item-sub"><?php esc_html_e( 'Online Course Enrollment — Lifetime Access', 'edupro' ); ?></div>
						</td>
						<td class="num">1</td>
						<td class="num"><?php echo esc_html( $fmt( $subtotal ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<!-- Totals -->
			<div class="inv-totals-wrap">
				<div class="inv-totals">
					<div class="inv-totals-row">
						<span><?php esc_html_e( 'Subtotal', 'edupro' ); ?></span>
						<span><?php echo esc_html( $fmt( $subtotal ) ); ?></span>
					</div>

					<?php if ( $discount_amount > 0 || '' !== $coupon_code ) : ?>
						<div class="inv-totals-row discount">
							<span>
								<?php
								echo '' !== $coupon_code
									? esc_html( sprintf( __( 'Discount (%s)', 'edupro' ), $coupon_code ) )
									: esc_html__( 'Discount', 'edupro' );
								?>
							</span>
							<span>-<?php echo esc_html( $fmt( $discount_amount ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $tax_amount > 0 ) : ?>
						<div class="inv-totals-row">
							<span>
								<?php
								echo $tax_rate > 0
									? esc_html( sprintf( '%s (%s%%)', $tax_label, rtrim( rtrim( number_format( $tax_rate, 2 ), '0' ), '.' ) ) )
									: esc_html( $tax_label );
								?>
							</span>
							<span><?php echo esc_html( $fmt( $tax_amount ) ); ?></span>
						</div>
					<?php endif; ?>

					<div class="inv-totals-row grand">
						<span><?php echo 'completed' === $status ? esc_html__( 'Total Paid', 'edupro' ) : esc_html__( 'Total Amount', 'edupro' ); ?></span>
						<span><?php echo esc_html( $fmt( $total_paid ) ); ?></span>
					</div>

					<?php if ( $refund_amount > 0 ) : ?>
						<div class="inv-totals-row refund">
							<span><?php esc_html_e( 'Refunded Amount', 'edupro' ); ?></span>
							<span>-<?php echo esc_html( $fmt( $refund_amount ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Payment & Verification Details -->
			<dl class="inv-payment-box">
				<div class="inv-pay-item">
					<dt><?php esc_html_e( 'Payment Gateway', 'edupro' ); ?></dt>
					<dd><?php echo esc_html( $gateway_label ); ?></dd>
				</div>
				<div class="inv-pay-item">
					<dt><?php esc_html_e( 'Transaction ID', 'edupro' ); ?></dt>
					<dd><?php echo esc_html( $transaction_id ); ?></dd>
				</div>
				<div class="inv-pay-item">
					<dt><?php esc_html_e( 'Verification Status', 'edupro' ); ?></dt>
					<dd><?php echo esc_html( $verified_label ); ?></dd>
				</div>
				<div class="inv-pay-item">
					<dt><?php esc_html_e( 'Verified Timestamp', 'edupro' ); ?></dt>
					<dd>
						<?php
						echo $verified_at
							? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $verified_at ) ) )
							: '—';
						?>
					</dd>
				</div>
			</dl>

			<footer class="inv-footer">
				<p><?php echo esc_html( sprintf( __( 'Thank you for learning with %s! This is a computer-generated invoice and does not require a physical signature.', 'edupro' ), $company_name ) ); ?></p>
			</footer>
		</div>
	</main>
</body>
</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'edupro_maybe_render_invoice_page', 5 );
