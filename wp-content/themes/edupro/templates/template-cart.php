<?php
/**
 * Template Name: Shopping Cart
 * Template Post Type: page
 *
 * Full Udemy-style EduPro Multi-Course Shopping Cart Page:
 * - Powered by edupro_get_cart_summary() (guest cookie + logged-in user meta)
 * - Empty cart state with shopping cart SVG & Browse Courses CTA
 * - 2-column responsive layout (.edupro-cart-layout):
 *   * Left (.edupro-cart-items-col): itemized .edupro-cart-item cards with thumbnail,
 *     title, instructor, duration, active-currency regular/sale prices, .edupro-cart-remove-btn,
 *     plus bottom bar with Continue Shopping & Clear Cart (.edupro-cart-clear-btn)
 *   * Right (.edupro-cart-summary-col): sticky .edupro-cart-summary-card with inline currency
 *     switcher (₹ INR | £ GBP), subtotal, total savings pill, Proceed to Checkout → button,
 *     and security/guarantee badges
 *
 * @package EduPro
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$active_currency = function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR';
$currency_symbol = function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol( $active_currency ) : '₹';
$courses_url     = get_post_type_archive_link( 'lms_course' ) ? get_post_type_archive_link( 'lms_course' ) : home_url( '/courses/' );
$cart_page_url   = function_exists( 'edupro_get_cart_url' ) ? edupro_get_cart_url() : get_permalink();

$checkout_base = function_exists( 'edupro_get_checkout_url' )
	? edupro_get_checkout_url()
	: home_url( '/checkout/' );

$summary = array(
	'items'              => array(),
	'count'              => 0,
	'currency'           => $active_currency,
	'symbol'             => $currency_symbol,
	'subtotal'           => 0.0,
	'regular_total'      => 0.0,
	'savings'            => 0.0,
	'formatted_subtotal' => function_exists( 'edupro_format_price' ) ? edupro_format_price( 0, $active_currency ) : $currency_symbol . '0.00',
	'formatted_savings'  => function_exists( 'edupro_format_price' ) ? edupro_format_price( 0, $active_currency ) : $currency_symbol . '0.00',
	'checkout_url'       => add_query_arg( 'mode', 'cart', $checkout_base ),
	'cart_url'           => $cart_page_url,
);

if ( function_exists( 'edupro_get_cart_summary' ) ) {
	$summary = wp_parse_args( edupro_get_cart_summary( $active_currency ), $summary );
}

$items        = ! empty( $summary['items'] ) && is_array( $summary['items'] ) ? $summary['items'] : array();
$item_count   = (int) $summary['count'];
$checkout_url = ! empty( $summary['checkout_url'] ) ? $summary['checkout_url'] : add_query_arg( 'mode', 'cart', $checkout_base );

$switch_currencies = array(
	'INR' => '₹ INR',
	'GBP' => '£ GBP',
);
if ( 'USD' === $summary['currency'] ) {
	$switch_currencies['USD'] = '$ USD';
}
?>

<main id="primary" class="edupro-cart-page">
	<div class="edupro-container">

		<div class="edupro-cart-header">
			<h1><?php esc_html_e( 'Shopping Cart', 'edupro' ); ?></h1>
			<p class="edupro-cart-subtitle">
				<?php
				if ( $item_count > 0 ) {
					printf(
						/* translators: %s: number of courses in cart */
						esc_html( _n( '%s course ready for enrollment', '%s courses ready for enrollment', $item_count, 'edupro' ) ),
						'<span id="edupro-cart-page-count">' . esc_html( number_format_i18n( $item_count ) ) . '</span>'
					);
				} else {
					esc_html_e( 'Review your selected courses before proceeding to secure checkout.', 'edupro' );
				}
				?>
			</p>
		</div>

		<?php if ( empty( $items ) ) : ?>
			<div class="edupro-cart-empty">
				<div class="edupro-cart-empty__icon" aria-hidden="true">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
				</div>
				<h2><?php esc_html_e( 'Your Shopping Cart is Empty', 'edupro' ); ?></h2>
				<p><?php esc_html_e( 'Looks like you haven\'t added any courses yet. Explore our career-focused courses and add your favorites to your cart.', 'edupro' ); ?></p>
				<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-lg">
					<?php esc_html_e( 'Browse Courses', 'edupro' ); ?> &rarr;
				</a>
			</div>

		<?php else : ?>
			<div class="edupro-cart-layout">

				<!-- ========================================================= -->
				<!-- LEFT COLUMN: CART ITEMS LIST                              -->
				<!-- ========================================================= -->
				<section class="edupro-cart-items-col" aria-label="<?php esc_attr_e( 'Courses in your cart', 'edupro' ); ?>">
					<div class="edupro-cart-items-header">
						<h2 class="edupro-cart-items-heading">
							<span class="edupro-cart-page-count"><?php echo esc_html( number_format_i18n( $item_count ) ); ?></span>
							<?php echo esc_html( _n( 'Course in Cart', 'Courses in Cart', $item_count, 'edupro' ) ); ?>
						</h2>
					</div>

					<div class="edupro-cart-items-list" id="edupro-cart-items-list">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$cid            = absint( $item['course_id'] ?? 0 );
							$title          = ! empty( $item['title'] ) ? $item['title'] : get_the_title( $cid );
							$permalink      = ! empty( $item['permalink'] ) ? $item['permalink'] : get_permalink( $cid );
							$thumb          = ! empty( $item['thumbnail'] ) ? $item['thumbnail'] : '';
							$instructor     = ! empty( $item['instructor'] ) ? $item['instructor'] : '';
							$duration       = ! empty( $item['duration'] ) ? $item['duration'] : '';
							$is_free        = ! empty( $item['is_free'] );
							$regular        = (float) ( $item['regular'] ?? 0 );
							$effective      = (float) ( $item['effective'] ?? 0 );
							$discount_pct   = (int) ( $item['discount_pct'] ?? 0 );
							$has_sale       = ( ! $is_free && $regular > 0 && $effective < $regular );
							$fmt_effective  = ! empty( $item['formatted_effective'] ) ? $item['formatted_effective'] : ( $is_free ? __( 'Free', 'edupro' ) : $summary['symbol'] . number_format( $effective, 2 ) );
							$fmt_regular    = ! empty( $item['formatted_regular'] ) ? $item['formatted_regular'] : $summary['symbol'] . number_format( $regular, 2 );
							$lessons_cnt    = function_exists( 'edupro_get_course_lessons_count' ) ? edupro_get_course_lessons_count( $cid ) : 0;
							?>
							<article class="edupro-cart-item" data-course-id="<?php echo esc_attr( $cid ); ?>">
								<div class="edupro-cart-item__thumb">
									<a href="<?php echo esc_url( $permalink ); ?>">
										<?php if ( $thumb ) : ?>
											<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
										<?php else : ?>
											<div class="edupro-cart-item__placeholder"><?php echo esc_html( mb_substr( $title, 0, 1 ) ); ?></div>
										<?php endif; ?>
									</a>
								</div>

								<div class="edupro-cart-item__info">
									<h3 class="edupro-cart-item__title">
										<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
									</h3>
									<?php if ( $instructor ) : ?>
										<p class="edupro-cart-item__instructor">
											<?php
											printf(
												/* translators: %s: instructor name */
												esc_html__( 'By %s', 'edupro' ),
												esc_html( $instructor )
											);
											?>
										</p>
									<?php endif; ?>
									<div class="edupro-cart-item__meta">
										<?php if ( $lessons_cnt > 0 ) : ?>
											<span><?php printf( esc_html( _n( '%d Lesson', '%d Lessons', $lessons_cnt, 'edupro' ) ), $lessons_cnt ); ?></span>
										<?php endif; ?>
										<?php if ( $duration ) : ?>
											<span><?php echo esc_html( $duration ); ?></span>
										<?php endif; ?>
										<?php if ( $has_sale && $discount_pct > 0 ) : ?>
											<span class="edupro-cart-item__discount-badge">
												<?php printf( esc_html__( '%d%% OFF', 'edupro' ), $discount_pct ); ?>
											</span>
										<?php endif; ?>
									</div>
								</div>

								<div class="edupro-cart-item__pricing">
									<div class="edupro-cart-item__price">
										<strong class="edupro-cart-item__effective"><?php echo esc_html( $is_free ? __( 'Free', 'edupro' ) : $fmt_effective ); ?></strong>
										<?php if ( $has_sale ) : ?>
											<del class="edupro-cart-item__regular"><?php echo esc_html( $fmt_regular ); ?></del>
										<?php endif; ?>
									</div>
									<button type="button"
											class="edupro-cart-remove-btn"
											data-course-id="<?php echo esc_attr( $cid ); ?>"
											aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from cart', 'edupro' ), $title ) ); ?>">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
										<span><?php esc_html_e( 'Remove', 'edupro' ); ?></span>
									</button>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<div class="edupro-cart-actions-bar">
						<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-sm">
							&larr; <?php esc_html_e( 'Continue Shopping', 'edupro' ); ?>
						</a>
						<button type="button" class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-cart-clear-btn">
							<?php esc_html_e( 'Clear Cart', 'edupro' ); ?>
						</button>
					</div>
				</section>

				<!-- ========================================================= -->
				<!-- RIGHT COLUMN: STICKY CART SUMMARY                         -->
				<!-- ========================================================= -->
				<aside class="edupro-cart-summary-col">
					<div class="edupro-cart-summary-card">
						<div class="edupro-summary-header-row">
							<h2 class="edupro-summary-heading"><?php esc_html_e( 'Cart Summary', 'edupro' ); ?></h2>
							<div class="edupro-summary-currency-toggle edupro-currency-switcher" role="group" aria-label="<?php esc_attr_e( 'Switch Currency', 'edupro' ); ?>">
								<?php foreach ( $switch_currencies as $cur_code => $cur_label ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'currency', $cur_code, $cart_page_url ) ); ?>"
									   class="edupro-currency-btn <?php echo $summary['currency'] === $cur_code ? 'is-active' : ''; ?>"
									   data-currency-switch="<?php echo esc_attr( $cur_code ); ?>">
										<?php echo esc_html( $cur_label ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="edupro-cart-summary-totals">
							<div class="edupro-summary-row">
								<span><?php esc_html_e( 'Subtotal', 'edupro' ); ?></span>
								<strong id="edupro-cart-page-subtotal" class="edupro-cart-summary-subtotal"><?php echo esc_html( $summary['formatted_subtotal'] ); ?></strong>
							</div>

							<?php if ( (float) $summary['savings'] > 0 ) : ?>
								<div class="edupro-cart-savings-pill">
									<?php
									printf(
										/* translators: %s: formatted savings amount */
										esc_html__( 'You save %s on this bundle!', 'edupro' ),
										'<strong id="edupro-cart-page-savings" class="edupro-cart-summary-savings">' . esc_html( $summary['formatted_savings'] ) . '</strong>'
									);
									?>
								</div>
							<?php endif; ?>

							<p class="edupro-cart-coupon-note">
								<?php esc_html_e( 'Promo coupons and taxes (if applicable) are calculated on the next step.', 'edupro' ); ?>
							</p>
						</div>

						<a href="<?php echo esc_url( $checkout_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-lg edupro-btn-block">
							<?php esc_html_e( 'Proceed to Checkout', 'edupro' ); ?> &rarr;
						</a>

						<ul class="edupro-checkout-guarantees">
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
								<?php esc_html_e( 'Instant Lifetime Access to All Courses', 'edupro' ); ?>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
								<?php esc_html_e( 'Verified Certificates of Completion', 'edupro' ); ?>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<?php esc_html_e( 'Cashfree, Razorpay, Stripe, PayPal & Bank Transfer', 'edupro' ); ?>
							</li>
						</ul>
					</div>
				</aside>

			</div>
		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
