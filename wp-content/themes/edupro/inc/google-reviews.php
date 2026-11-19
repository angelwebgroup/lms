<?php
/**
 * EduPro LMS — Google Reviews Integration & Manual Reviews Manager
 *
 * Supports fetching reviews via Google Places API (Place ID or Google Maps URL)
 * with automatic transient caching and seamless fallback to admin-managed manual reviews.
 *
 * Includes:
 * - edupro_google_reviews_widget()
 * - edupro_render_google_reviews( $count = 6 )
 * - Appearance > Google Reviews admin settings page
 * - Shortcode [edupro_reviews count="6"]
 * - Sidebar Widget class EduPro_Reviews_Widget
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// 1. DEFAULT / FALLBACK REVIEWS & DATA RETRIEVAL
// =============================================================================

/**
 * Extract a Google Place ID from either a raw Place ID or a Google Maps/Business URL.
 *
 * @param string $input Raw Place ID or Google listing URL.
 * @return string Extracted Place ID or sanitized string.
 */
function edupro_extract_google_place_id( $input ) {
	$input = trim( (string) $input );
	if ( empty( $input ) ) {
		return '';
	}

	// If it's a URL, try to parse place_id query param or ftid/place segment.
	if ( filter_var( $input, FILTER_VALIDATE_URL ) ) {
		$parsed = wp_parse_url( $input );
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query_params );
			if ( ! empty( $query_params['place_id'] ) ) {
				return sanitize_text_field( $query_params['place_id'] );
			}
			if ( ! empty( $query_params['placeid'] ) ) {
				return sanitize_text_field( $query_params['placeid'] );
			}
		}

		// Match ChIJ... style Place ID inside URL.
		if ( preg_match( '/(ChIJ[A-Za-z0-9_-]+)/', $input, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
	}

	return sanitize_text_field( $input );
}

/**
 * Default manual reviews used only when admin hasn't saved any reviews yet.
 *
 * @return array
 */
function edupro_get_default_manual_reviews() {
	return array(
		array(
			'name'   => 'Aarav Sharma',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-5 days' ) ),
			'photo'  => '',
			'text'   => 'This academy completely transformed my career. The structured curriculum, hands-on projects, and crystal-clear explanations helped me land a senior developer role within two months of completing the course.',
		),
		array(
			'name'   => 'Priya Nair',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-12 days' ) ),
			'photo'  => '',
			'text'   => 'Best online learning experience I have had! The video player is fast, lessons are concise yet thorough, and the downloadable certificate added real credibility to my LinkedIn profile.',
		),
		array(
			'name'   => 'Rohan Verma',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-3 weeks' ) ),
			'photo'  => '',
			'text'   => 'Worth every single penny. The instructors genuinely care about student outcomes and break down complex topics into easy-to-follow steps. Highly recommend these courses to anyone upskilling.',
		),
		array(
			'name'   => 'Sneha Patel',
			'rating' => 4,
			'date'   => date_i18n( 'M j, Y', strtotime( '-1 month' ) ),
			'photo'  => '',
			'text'   => 'Very well organized courses and an intuitive student dashboard. Tracking my progress across multiple courses is effortless and the lifetime access is a huge bonus.',
		),
		array(
			'name'   => 'Vikram Desai',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-6 weeks' ) ),
			'photo'  => '',
			'text'   => 'I enrolled in three certification courses and loved every one of them. Real-world case studies, responsive support, and smooth checkout made the entire journey seamless.',
		),
		array(
			'name'   => 'Ananya Iyer',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-2 months' ) ),
			'photo'  => '',
			'text'   => 'Practical, up-to-date content with zero fluff. I was able to apply the digital marketing strategies immediately at my job and saw measurable results in the first week.',
		),
	);
}

/**
 * Fetch reviews from Google Places API (with 12-hour transient cache).
 *
 * @param string $place_id_or_url Google Place ID or listing URL.
 * @param string $api_key         Google Places API key.
 * @return array|WP_Error         Normalized reviews payload or WP_Error.
 */
function edupro_fetch_google_places_reviews( $place_id_or_url, $api_key ) {
	$place_id = edupro_extract_google_place_id( $place_id_or_url );

	if ( empty( $place_id ) || empty( $api_key ) ) {
		return new WP_Error( 'missing_credentials', __( 'Google Place ID or API key is missing.', 'edupro' ) );
	}

	$cache_key = 'edupro_greviews_' . md5( $place_id );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$endpoint = add_query_arg(
		array(
			'place_id' => $place_id,
			'fields'   => 'name,rating,user_ratings_total,reviews,url',
			'key'      => $api_key,
		),
		'https://maps.googleapis.com/maps/api/place/details/json'
	);

	$response = wp_remote_get(
		$endpoint,
		array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== (int) $code || empty( $body['result'] ) || ( isset( $body['status'] ) && 'OK' !== $body['status'] ) ) {
		$err_msg = isset( $body['error_message'] ) ? $body['error_message'] : __( 'Failed to fetch reviews from Google Places API.', 'edupro' );
		return new WP_Error( 'google_api_error', $err_msg );
	}

	$result  = $body['result'];
	$reviews = array();

	if ( ! empty( $result['reviews'] ) && is_array( $result['reviews'] ) ) {
		foreach ( $result['reviews'] as $item ) {
			$reviews[] = array(
				'name'   => isset( $item['author_name'] ) ? sanitize_text_field( $item['author_name'] ) : __( 'Google User', 'edupro' ),
				'rating' => isset( $item['rating'] ) ? max( 1, min( 5, (float) $item['rating'] ) ) : 5,
				'date'   => isset( $item['relative_time_description'] )
					? sanitize_text_field( $item['relative_time_description'] )
					: ( isset( $item['time'] ) ? date_i18n( get_option( 'date_format' ), (int) $item['time'] ) : '' ),
				'photo'  => isset( $item['profile_photo_url'] ) ? esc_url_raw( $item['profile_photo_url'] ) : '',
				'text'   => isset( $item['text'] ) ? sanitize_textarea_field( $item['text'] ) : '',
			);
		}
	}

	$payload = array(
		'source'        => 'google',
		'business_name' => isset( $result['name'] ) ? sanitize_text_field( $result['name'] ) : get_bloginfo( 'name' ),
		'rating'        => isset( $result['rating'] ) ? round( (float) $result['rating'], 1 ) : 5.0,
		'total_reviews' => isset( $result['user_ratings_total'] ) ? absint( $result['user_ratings_total'] ) : count( $reviews ),
		'place_url'     => isset( $result['url'] ) ? esc_url_raw( $result['url'] ) : '',
		'place_id'      => $place_id,
		'reviews'       => $reviews,
	);

	set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );

	return $payload;
}

/**
 * Retrieve normalized reviews data (Google Places API or manual fallback).
 *
 * @param string $override_place_id Optional Place ID override.
 * @return array
 */
function edupro_get_google_reviews_data( $override_place_id = '' ) {
	$mode     = get_option( 'edupro_google_reviews_mode', 'manual' );
	$place_id = ! empty( $override_place_id ) ? $override_place_id : get_option( 'edupro_google_place_id', '' );
	$api_key  = get_option( 'edupro_google_api_key', '' );

	// Attempt live Google Places API when configured in API mode or when override is passed with API key.
	if ( ( 'google' === $mode || ! empty( $override_place_id ) ) && ! empty( $place_id ) && ! empty( $api_key ) ) {
		$api_data = edupro_fetch_google_places_reviews( $place_id, $api_key );
		if ( ! is_wp_error( $api_data ) && ! empty( $api_data['reviews'] ) ) {
			return $api_data;
		}
	}

	// Fallback to manual reviews stored in options.
	$manual_reviews = get_option( 'edupro_manual_reviews', null );
	if ( null === $manual_reviews || ! is_array( $manual_reviews ) ) {
		$manual_reviews = edupro_get_default_manual_reviews();
	}

	// Calculate overall rating from manual reviews (unless overridden).
	$avg_rating = 4.9;
	$count      = count( $manual_reviews );
	if ( $count > 0 ) {
		$sum = 0;
		foreach ( $manual_reviews as $rev ) {
			$sum += isset( $rev['rating'] ) ? (float) $rev['rating'] : 5;
		}
		$avg_rating = round( $sum / $count, 1 );
	}

	$saved_rating = get_option( 'edupro_manual_overall_rating', '' );
	$saved_total  = get_option( 'edupro_manual_total_reviews', '' );

	$business_name = get_option( 'edupro_google_business_name', get_bloginfo( 'name' ) );
	$place_url     = '';
	if ( ! empty( $place_id ) ) {
		if ( filter_var( $place_id, FILTER_VALIDATE_URL ) ) {
			$place_url = $place_id;
		} else {
			$place_url = 'https://search.google.com/local/reviews?placeid=' . rawurlencode( $place_id );
		}
	}

	return array(
		'source'        => 'manual',
		'business_name' => $business_name ? $business_name : get_bloginfo( 'name' ),
		'rating'        => '' !== $saved_rating ? round( (float) $saved_rating, 1 ) : $avg_rating,
		'total_reviews' => '' !== $saved_total ? absint( $saved_total ) : max( $count, 128 ),
		'place_url'     => $place_url,
		'place_id'      => edupro_extract_google_place_id( $place_id ),
		'reviews'       => $manual_reviews,
	);
}

// =============================================================================
// 2. PUBLIC RENDERING FUNCTIONS
// =============================================================================

/**
 * Return inline SVG for the multi-color Google "G" badge.
 *
 * @param int $size Icon size in pixels.
 * @return string
 */
function edupro_get_google_g_svg( $size = 20 ) {
	$size = absint( $size );
	return '<svg class="edupro-google-g-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
		. '<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.45c-.28 1.49-1.12 2.75-2.38 3.6v2.98h3.85c2.25-2.08 3.57-5.14 3.57-8.61z"/>'
		. '<path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.91l-3.85-2.98c-1.07.72-2.44 1.15-4.08 1.15-3.14 0-5.8-2.12-6.75-4.97H1.27v3.08C3.25 21.3 7.31 24 12 24z"/>'
		. '<path fill="#FBBC05" d="M5.25 14.29c-.24-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.63H1.27C.46 8.24 0 10.07 0 12s.46 3.76 1.27 5.37l3.98-3.08z"/>'
		. '<path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.59 1.8l3.44-3.44C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.27 6.63l3.98 3.08c.95-2.85 3.61-4.96 6.75-4.96z"/>'
		. '</svg>';
}

/**
 * Render Google Reviews cards HTML (with overall rating badge & expandable review text).
 *
 * @param int   $count Number of reviews to display.
 * @param array $args  Optional display args: place_id, layout (grid|carousel|list), show_badge.
 * @return string HTML output.
 */
function edupro_render_google_reviews( $count = 6, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'place_id'   => '',
			'layout'     => 'carousel',
			'show_badge' => true,
		)
	);

	$count   = max( 1, absint( $count ) );
	$data    = edupro_get_google_reviews_data( $args['place_id'] );
	$reviews = ! empty( $data['reviews'] ) ? array_slice( $data['reviews'], 0, $count ) : array();

	if ( empty( $reviews ) ) {
		return '<div class="edupro-reviews-empty"><p>' . esc_html__( 'No reviews available yet.', 'edupro' ) . '</p></div>';
	}

	$stars_fn    = function_exists( 'edupro_get_star_rating_html' );
	$layout      = in_array( $args['layout'], array( 'grid', 'carousel', 'list' ), true ) ? $args['layout'] : 'carousel';
	$is_carousel = ( 'carousel' === $layout );
	$show_badge  = ! empty( $args['show_badge'] );

	ob_start();
	?>
	<div class="edupro-google-reviews edupro-google-reviews--<?php echo esc_attr( $layout ); ?>">
		<?php if ( $is_carousel ) : ?>
		<div class="edupro-reviews-split<?php echo $show_badge ? '' : ' edupro-reviews-split--no-summary'; ?>">
			<?php if ( $show_badge ) : ?>
				<aside class="edupro-reviews-summary-card" aria-label="<?php esc_attr_e( 'Google Reviews Summary', 'edupro' ); ?>">
					<span class="edupro-reviews-summary-card__ribbon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/>
							<path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
						</svg>
					</span>

					<h3 class="edupro-reviews-summary-card__title"><?php esc_html_e( 'Google Reviews', 'edupro' ); ?></h3>

					<div class="edupro-reviews-summary-card__stars">
						<?php
						if ( $stars_fn ) {
							echo edupro_get_star_rating_html( $data['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo '<span class="edupro-stars-fallback">' . esc_html( str_repeat( '★', (int) round( $data['rating'] ) ) ) . '</span>';
						}
						?>
					</div>

					<div class="edupro-reviews-summary-card__metrics">
						<div class="edupro-reviews-summary-metric">
							<strong><?php echo esc_html( number_format( (float) $data['rating'], 1 ) ); ?></strong>
							<span><?php esc_html_e( 'Rating', 'edupro' ); ?></span>
						</div>
						<span class="edupro-reviews-summary-divider" aria-hidden="true"></span>
						<div class="edupro-reviews-summary-metric">
							<strong><?php echo esc_html( number_format_i18n( (int) $data['total_reviews'] ) ); ?></strong>
							<span><?php esc_html_e( 'Reviews', 'edupro' ); ?></span>
						</div>
					</div>

					<div class="edupro-reviews-summary-card__footer">
						<?php echo edupro_get_google_g_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="edupro-reviews-summary-card__seal-label"><?php esc_html_e( 'Customer Reviews', 'edupro' ); ?></span>
						<span class="edupro-reviews-summary-card__seal-check" aria-hidden="true" title="<?php esc_attr_e( 'Verified Reviews', 'edupro' ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
								<polyline points="22 4 12 14.01 9 11.01"/>
							</svg>
						</span>
					</div>

					<?php if ( ! empty( $data['place_url'] ) ) : ?>
						<a href="<?php echo esc_url( $data['place_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-review-cta-btn">
							<?php esc_html_e( 'Review Us on Google', 'edupro' ); ?>
						</a>
					<?php endif; ?>
				</aside>
			<?php endif; ?>

			<div class="edupro-reviews-carousel-stage">
				<?php if ( count( $reviews ) > 1 ) : ?>
					<button
						type="button"
						class="edupro-reviews-nav-btn edupro-reviews-nav-btn--prev carousel-prev"
						data-carousel-prev
						aria-label="<?php esc_attr_e( 'Previous reviews', 'edupro' ); ?>"
					>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<polyline points="15 18 9 12 15 6"></polyline>
						</svg>
					</button>
				<?php endif; ?>

				<div class="edupro-reviews-track" data-edupro-reviews-slider>
					<?php foreach ( $reviews as $review ) : ?>
						<?php
						$name       = ! empty( $review['name'] ) ? $review['name'] : __( 'Verified Student', 'edupro' );
						$headline   = ! empty( $review['title'] ) ? trim( (string) $review['title'] ) : '';
						$rating     = isset( $review['rating'] ) ? max( 1, min( 5, (float) $review['rating'] ) ) : 5;
						$date       = ! empty( $review['date'] ) ? $review['date'] : '';
						$photo      = ! empty( $review['photo'] ) ? $review['photo'] : '';
						$full_text  = ! empty( $review['text'] ) ? trim( $review['text'] ) : '';
						$max_chars  = 155;
						$is_long    = mb_strlen( $full_text ) > $max_chars;
						$short_text = $is_long ? rtrim( mb_substr( $full_text, 0, $max_chars ) ) . '…' : $full_text;

						$parts    = preg_split( '/\s+/', trim( $name ) );
						$initials = '';
						foreach ( array_slice( $parts, 0, 2 ) as $p ) {
							$initials .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
						}
						?>
						<article class="edupro-review-card edupro-review-card--split">
							<div class="edupro-review-card__top">
								<div class="edupro-review-card__rating">
									<?php
									if ( $stars_fn ) {
										echo edupro_get_star_rating_html( $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									} else {
										echo '<span class="edupro-stars-fallback">' . esc_html( str_repeat( '★', (int) round( $rating ) ) ) . '</span>';
									}
									?>
								</div>
								<span class="edupro-review-verified-pill">
									<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
									<?php esc_html_e( 'VERIFIED', 'edupro' ); ?>
								</span>
							</div>

							<div class="edupro-review-card__body">
								<?php if ( $headline ) : ?>
									<h4 class="edupro-review-card__headline"><?php echo esc_html( $headline ); ?></h4>
								<?php endif; ?>
								<?php if ( $is_long ) : ?>
									<p class="edupro-review-card__text edupro-review-text-short"><?php echo esc_html( $short_text ); ?></p>
									<p class="edupro-review-card__text edupro-review-text-full" style="display:none;"><?php echo esc_html( $full_text ); ?></p>
									<button type="button" class="edupro-review-expand-btn" data-more="<?php esc_attr_e( 'Read more', 'edupro' ); ?>" data-less="<?php esc_attr_e( 'Show less', 'edupro' ); ?>" aria-expanded="false">
										<?php esc_html_e( 'Read more', 'edupro' ); ?>
									</button>
								<?php else : ?>
									<p class="edupro-review-card__text"><?php echo esc_html( $full_text ); ?></p>
								<?php endif; ?>
							</div>

							<footer class="edupro-review-card__footer">
								<div class="edupro-review-card__author">
									<?php if ( $photo ) : ?>
										<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="edupro-review-card__avatar" loading="lazy" width="40" height="40">
									<?php else : ?>
										<span class="edupro-review-card__avatar edupro-review-card__avatar--initials" aria-hidden="true">
											<?php echo esc_html( $initials ? $initials : 'S' ); ?>
										</span>
									<?php endif; ?>
									<div class="edupro-review-card__meta">
										<h4 class="edupro-review-card__name"><?php echo esc_html( $name ); ?></h4>
										<?php if ( $date ) : ?>
											<time class="edupro-review-card__date"><?php echo esc_html( $date ); ?></time>
										<?php endif; ?>
									</div>
								</div>
								<span class="edupro-review-card__google-badge" title="<?php esc_attr_e( 'Verified Google Review', 'edupro' ); ?>">
									<?php echo edupro_get_google_g_svg( 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</span>
							</footer>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ( count( $reviews ) > 1 ) : ?>
					<button
						type="button"
						class="edupro-reviews-nav-btn edupro-reviews-nav-btn--next carousel-next"
						data-carousel-next
						aria-label="<?php esc_attr_e( 'Next reviews', 'edupro' ); ?>"
					>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<polyline points="9 18 15 12 9 6"></polyline>
						</svg>
					</button>
					<div class="edupro-reviews-dots carousel-dots" role="tablist" aria-label="<?php esc_attr_e( 'Review slides navigation', 'edupro' ); ?>"></div>
				<?php endif; ?>
			</div><!-- .edupro-reviews-carousel-stage -->
		</div><!-- .edupro-reviews-split -->

		<?php else : ?>
			<?php if ( $show_badge ) : ?>
				<div class="edupro-reviews-summary-badge">
					<div class="edupro-reviews-badge-left">
						<div class="edupro-reviews-badge-brand">
							<?php echo edupro_get_google_g_svg( 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="edupro-reviews-badge-title">
								<?php esc_html_e( 'Google Reviews', 'edupro' ); ?>
							</span>
						</div>
						<div class="edupro-reviews-badge-score">
							<strong class="edupro-reviews-score-number"><?php echo esc_html( number_format( (float) $data['rating'], 1 ) ); ?></strong>
							<span class="edupro-reviews-score-stars">
								<?php
								if ( $stars_fn ) {
									echo edupro_get_star_rating_html( $data['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} else {
									echo '<span class="edupro-stars-fallback">' . esc_html( str_repeat( '★', (int) round( $data['rating'] ) ) ) . '</span>';
								}
								?>
							</span>
							<span class="edupro-reviews-score-count">
								<?php
								printf(
									/* translators: %s: total reviews count */
									esc_html__( 'Based on %s reviews', 'edupro' ),
									'<strong>' . esc_html( number_format_i18n( (int) $data['total_reviews'] ) ) . '</strong>'
								);
								?>
							</span>
						</div>
					</div>
					<?php if ( ! empty( $data['place_url'] ) ) : ?>
						<div class="edupro-reviews-badge-right">
							<a href="<?php echo esc_url( $data['place_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-review-cta-btn">
								<?php esc_html_e( 'Review Us on Google', 'edupro' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="edupro-reviews-track edupro-reviews-grid" data-edupro-reviews-slider>
				<?php foreach ( $reviews as $review ) : ?>
					<?php
					$name       = ! empty( $review['name'] ) ? $review['name'] : __( 'Verified Student', 'edupro' );
					$rating     = isset( $review['rating'] ) ? max( 1, min( 5, (float) $review['rating'] ) ) : 5;
					$date       = ! empty( $review['date'] ) ? $review['date'] : '';
					$photo      = ! empty( $review['photo'] ) ? $review['photo'] : '';
					$full_text  = ! empty( $review['text'] ) ? trim( $review['text'] ) : '';
					$max_chars  = 160;
					$is_long    = mb_strlen( $full_text ) > $max_chars;
					$short_text = $is_long ? rtrim( mb_substr( $full_text, 0, $max_chars ) ) . '…' : $full_text;

					$parts    = preg_split( '/\s+/', trim( $name ) );
					$initials = '';
					foreach ( array_slice( $parts, 0, 2 ) as $p ) {
						$initials .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
					}
					?>
					<article class="edupro-review-card">
						<header class="edupro-review-card__header">
							<div class="edupro-review-card__author">
								<?php if ( $photo ) : ?>
									<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="edupro-review-card__avatar" loading="lazy" width="44" height="44">
								<?php else : ?>
									<span class="edupro-review-card__avatar edupro-review-card__avatar--initials" aria-hidden="true">
										<?php echo esc_html( $initials ? $initials : 'S' ); ?>
									</span>
								<?php endif; ?>
								<div class="edupro-review-card__meta">
									<h4 class="edupro-review-card__name"><?php echo esc_html( $name ); ?></h4>
									<?php if ( $date ) : ?>
										<time class="edupro-review-card__date"><?php echo esc_html( $date ); ?></time>
									<?php endif; ?>
								</div>
							</div>
							<span class="edupro-review-card__google-badge" title="<?php esc_attr_e( 'Verified Google Review', 'edupro' ); ?>">
								<?php echo edupro_get_google_g_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
						</header>

						<div class="edupro-review-card__rating">
							<?php
							if ( $stars_fn ) {
								echo edupro_get_star_rating_html( $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								echo '<span class="edupro-stars-fallback">' . esc_html( str_repeat( '★', (int) round( $rating ) ) ) . '</span>';
							}
							?>
						</div>

						<div class="edupro-review-card__body">
							<?php if ( $is_long ) : ?>
								<p class="edupro-review-card__text edupro-review-text-short"><?php echo esc_html( $short_text ); ?></p>
								<p class="edupro-review-card__text edupro-review-text-full" style="display:none;"><?php echo esc_html( $full_text ); ?></p>
								<button type="button" class="edupro-review-expand-btn" data-more="<?php esc_attr_e( 'Read more', 'edupro' ); ?>" data-less="<?php esc_attr_e( 'Show less', 'edupro' ); ?>" aria-expanded="false">
									<?php esc_html_e( 'Read more', 'edupro' ); ?>
								</button>
							<?php else : ?>
								<p class="edupro-review-card__text"><?php echo esc_html( $full_text ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	// Print lightweight inline expand script once per page.
	static $expand_script_printed = false;
	if ( ! $expand_script_printed ) {
		$expand_script_printed = true;
		?>
		<script>
		(function(){
			document.addEventListener('click', function(e){
				var btn = e.target.closest('.edupro-review-expand-btn');
				if (!btn) return;
				var body = btn.closest('.edupro-review-card__body');
				if (!body) return;
				var shortEl = body.querySelector('.edupro-review-text-short');
				var fullEl  = body.querySelector('.edupro-review-text-full');
				var expanded = btn.getAttribute('aria-expanded') === 'true';
				if (expanded) {
					if (shortEl) shortEl.style.display = '';
					if (fullEl)  fullEl.style.display  = 'none';
					btn.textContent = btn.getAttribute('data-more') || 'Read more';
					btn.setAttribute('aria-expanded', 'false');
				} else {
					if (shortEl) shortEl.style.display = 'none';
					if (fullEl)  fullEl.style.display  = '';
					btn.textContent = btn.getAttribute('data-less') || 'Show less';
					btn.setAttribute('aria-expanded', 'true');
				}
			});
		})();
		</script>
		<?php
	}

	return ob_get_clean();
}

/**
 * Primary helper function to fetch and render Google Reviews widget HTML.
 *
 * @param array $args Optional args: count, place_id, layout, show_badge, echo.
 * @return string
 */
function edupro_google_reviews_widget( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'count'      => 6,
			'place_id'   => get_option( 'edupro_google_place_id', '' ),
			'layout'     => 'carousel',
			'show_badge' => true,
			'echo'       => false,
		)
	);

	$html = edupro_render_google_reviews( (int) $args['count'], $args );

	if ( ! empty( $args['echo'] ) ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $html;
}

// =============================================================================
// 3. SHORTCODE: [edupro_reviews count="6"]
// =============================================================================

/**
 * Shortcode callback for [edupro_reviews].
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function edupro_reviews_shortcode_handler( $atts ) {
	$atts = shortcode_atts(
		array(
			'count'      => 6,
			'place_id'   => '',
			'layout'     => 'carousel',
			'show_badge' => 'yes',
		),
		$atts,
		'edupro_reviews'
	);

	return edupro_render_google_reviews(
		absint( $atts['count'] ),
		array(
			'place_id'   => sanitize_text_field( $atts['place_id'] ),
			'layout'     => sanitize_text_field( $atts['layout'] ),
			'show_badge' => ( 'no' !== strtolower( $atts['show_badge'] ) && 'false' !== strtolower( $atts['show_badge'] ) ),
		)
	);
}
add_shortcode( 'edupro_reviews', 'edupro_reviews_shortcode_handler' );

// =============================================================================
// 4. SIDEBAR WIDGET CLASS: EduPro_Reviews_Widget
// =============================================================================

class EduPro_Reviews_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'edupro_reviews_widget',
			__( 'Google Reviews', 'edupro' ),
			array(
				'description' => __( 'Display Google Reviews or manually added student testimonials in your sidebar.', 'edupro' ),
				'classname'   => 'widget_edupro_reviews',
			)
		);
	}

	/**
	 * Front-end widget output.
	 *
	 * @param array $args     Widget display arguments.
	 * @param array $instance Saved widget settings.
	 */
	public function widget( $args, $instance ) {
		$title      = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Student Reviews', 'edupro' );
		$count      = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 3;
		$show_badge = isset( $instance['show_badge'] ) ? (bool) $instance['show_badge'] : true;

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo edupro_render_google_reviews(
			$count,
			array(
				'layout'     => 'list',
				'show_badge' => $show_badge,
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values.
	 */
	public function form( $instance ) {
		$title      = isset( $instance['title'] ) ? $instance['title'] : __( 'Student Reviews', 'edupro' );
		$count      = isset( $instance['count'] ) ? absint( $instance['count'] ) : 3;
		$show_badge = isset( $instance['show_badge'] ) ? (bool) $instance['show_badge'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'edupro' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				   type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of Reviews:', 'edupro' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				   type="number" min="1" max="12" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_badge ); ?>
				   id="<?php echo esc_attr( $this->get_field_id( 'show_badge' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'show_badge' ) ); ?>" value="1">
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_badge' ) ); ?>"><?php esc_html_e( 'Show Overall Rating Badge', 'edupro' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values on save.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'      => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'count'      => isset( $new_instance['count'] ) ? max( 1, min( 12, absint( $new_instance['count'] ) ) ) : 3,
			'show_badge' => ! empty( $new_instance['show_badge'] ) ? 1 : 0,
		);
	}
}

add_action(
	'widgets_init',
	function () {
		register_widget( 'EduPro_Reviews_Widget' );
	}
);

// =============================================================================
// 5. ADMIN SETTINGS PAGE: APPEARANCE > GOOGLE REVIEWS
// =============================================================================

/**
 * Register Appearance > Google Reviews options page.
 */
function edupro_register_google_reviews_admin_page() {
	add_theme_page(
		__( 'Google Reviews Settings', 'edupro' ),
		__( 'Google Reviews', 'edupro' ),
		'manage_options',
		'edupro-google-reviews',
		'edupro_render_google_reviews_admin_page'
	);
}
add_action( 'admin_menu', 'edupro_register_google_reviews_admin_page' );

/**
 * Save Google Reviews settings & manual reviews.
 */
function edupro_handle_google_reviews_settings_save() {
	if ( ! isset( $_POST['edupro_google_reviews_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_google_reviews_nonce'] ) ), 'edupro_save_google_reviews' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Clear cache action.
	if ( isset( $_POST['edupro_clear_reviews_cache'] ) ) {
		$place_id = edupro_extract_google_place_id( get_option( 'edupro_google_place_id', '' ) );
		if ( $place_id ) {
			delete_transient( 'edupro_greviews_' . md5( $place_id ) );
		}
		add_settings_error( 'edupro_google_reviews', 'cache_cleared', __( 'Google Reviews cache cleared.', 'edupro' ), 'updated' );
		return;
	}

	$mode = isset( $_POST['edupro_google_reviews_mode'] ) && 'google' === $_POST['edupro_google_reviews_mode'] ? 'google' : 'manual';
	update_option( 'edupro_google_reviews_mode', $mode );

	$place_id = isset( $_POST['edupro_google_place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_google_place_id'] ) ) : '';
	$api_key  = isset( $_POST['edupro_google_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_google_api_key'] ) ) : '';
	$biz_name = isset( $_POST['edupro_google_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_google_business_name'] ) ) : '';
	$rating   = isset( $_POST['edupro_manual_overall_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_manual_overall_rating'] ) ) : '';
	$total    = isset( $_POST['edupro_manual_total_reviews'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_manual_total_reviews'] ) ) : '';

	update_option( 'edupro_google_place_id', $place_id );
	update_option( 'edupro_google_api_key', $api_key );
	update_option( 'edupro_google_business_name', $biz_name );
	update_option( 'edupro_manual_overall_rating', '' !== $rating ? max( 1, min( 5, (float) $rating ) ) : '' );
	update_option( 'edupro_manual_total_reviews', '' !== $total ? absint( $total ) : '' );

	// Clear previous transient when Place ID / API key changes.
	$extracted = edupro_extract_google_place_id( $place_id );
	if ( $extracted ) {
		delete_transient( 'edupro_greviews_' . md5( $extracted ) );
	}

	// Process manual reviews repeater.
	$manual_reviews = array();
	if ( isset( $_POST['manual_reviews'] ) && is_array( $_POST['manual_reviews'] ) ) {
		foreach ( wp_unslash( $_POST['manual_reviews'] ) as $entry ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$r_name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
			$r_text = isset( $entry['text'] ) ? sanitize_textarea_field( $entry['text'] ) : '';

			if ( empty( $r_name ) && empty( $r_text ) ) {
				continue;
			}

			$manual_reviews[] = array(
				'name'   => $r_name ? $r_name : __( 'Anonymous', 'edupro' ),
				'rating' => isset( $entry['rating'] ) ? max( 1, min( 5, (float) $entry['rating'] ) ) : 5,
				'date'   => isset( $entry['date'] ) ? sanitize_text_field( $entry['date'] ) : '',
				'photo'  => isset( $entry['photo'] ) ? esc_url_raw( $entry['photo'] ) : '',
				'text'   => $r_text,
			);
		}
	}
	update_option( 'edupro_manual_reviews', $manual_reviews );

	add_settings_error( 'edupro_google_reviews', 'settings_saved', __( 'Google Reviews settings saved successfully.', 'edupro' ), 'updated' );
}
add_action( 'admin_init', 'edupro_handle_google_reviews_settings_save' );

/**
 * Render the Appearance > Google Reviews admin settings page.
 */
function edupro_render_google_reviews_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'edupro_google_reviews' );

	$mode           = get_option( 'edupro_google_reviews_mode', 'manual' );
	$place_id       = get_option( 'edupro_google_place_id', '' );
	$api_key        = get_option( 'edupro_google_api_key', '' );
	$business_name  = get_option( 'edupro_google_business_name', get_bloginfo( 'name' ) );
	$overall_rating = get_option( 'edupro_manual_overall_rating', '4.9' );
	$total_reviews  = get_option( 'edupro_manual_total_reviews', '250' );
	$manual_reviews = get_option( 'edupro_manual_reviews', null );

	if ( null === $manual_reviews || ! is_array( $manual_reviews ) ) {
		$manual_reviews = edupro_get_default_manual_reviews();
	}
	?>
	<div class="wrap edupro-reviews-admin">
		<div class="edupro-reviews-admin-head">
			<div>
				<h1><?php esc_html_e( 'Google Reviews Settings', 'edupro' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'Display authentic Google Reviews via the Google Places API, or manage custom student reviews manually. Embed anywhere using', 'edupro' ); ?>
					<code>[edupro_reviews count="6"]</code>
				</p>
			</div>
			<button type="button" class="button button-secondary edupro-api-guide-trigger" data-open-api-guide>
				<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'How to Setup Google Review API (Step-by-Step)', 'edupro' ); ?>
			</button>
		</div>

		<style>
			.edupro-reviews-admin .edupro-reviews-admin-head {
				display: flex; align-items: center; justify-content: space-between;
				flex-wrap: wrap; gap: 12px; max-width: 960px; margin-bottom: 6px;
			}
			.edupro-reviews-admin .edupro-api-guide-trigger {
				display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
				height: auto; font-weight: 600; color: #1a1a2e; border-color: #cbd5e1;
				background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
			}
			.edupro-reviews-admin .edupro-api-guide-trigger:hover {
				border-color: #e31b23; color: #e31b23; background: #fff5f5;
			}
			.edupro-reviews-admin .edupro-api-guide-link {
				color: #e31b23; font-weight: 600; text-decoration: none; cursor: pointer;
				background: none; border: none; padding: 0; font-size: 13px;
			}
			.edupro-reviews-admin .edupro-api-guide-link:hover { text-decoration: underline; }
			.edupro-reviews-admin .edupro-admin-card {
				background: #fff; border: 1px solid #e2e4e7; border-radius: 8px;
				padding: 20px 24px; margin: 20px 0; max-width: 960px;
				box-shadow: 0 1px 4px rgba(0,0,0,0.04);
			}
			.edupro-reviews-admin h2.card-title {
				margin: 0 0 16px; padding-bottom: 10px; border-bottom: 2px solid #e31b23;
				font-size: 15px; text-transform: uppercase; letter-spacing: .5px; color: #1a1a2e;
				display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
			}
			.edupro-reviews-admin .edupro-guide-overlay {
				position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65);
				backdrop-filter: blur(3px); z-index: 100050;
				display: none; align-items: center; justify-content: center; padding: 20px;
			}
			.edupro-reviews-admin .edupro-guide-overlay.is-open { display: flex; }
			.edupro-reviews-admin .edupro-guide-modal {
				background: #fff; border-radius: 12px; max-width: 680px; width: 100%;
				max-height: 88vh; display: flex; flex-direction: column;
				box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35); overflow: hidden;
			}
			.edupro-reviews-admin .edupro-guide-modal__head {
				display: flex; align-items: center; justify-content: space-between;
				padding: 18px 24px; background: #1a1a2e; color: #fff;
			}
			.edupro-reviews-admin .edupro-guide-modal__head h2 {
				margin: 0; font-size: 17px; color: #fff; font-weight: 700;
			}
			.edupro-reviews-admin .edupro-guide-modal__close {
				background: rgba(255,255,255,0.14); border: none; color: #fff;
				width: 34px; height: 34px; border-radius: 50%; font-size: 20px;
				line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
			}
			.edupro-reviews-admin .edupro-guide-modal__close:hover { background: #e31b23; }
			.edupro-reviews-admin .edupro-guide-modal__body {
				padding: 22px 24px; overflow-y: auto; font-size: 13.5px; line-height: 1.6; color: #334155;
			}
			.edupro-reviews-admin .edupro-guide-step {
				display: flex; gap: 14px; padding: 14px 16px; margin-bottom: 12px;
				background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
			}
			.edupro-reviews-admin .edupro-guide-step__num {
				width: 28px; height: 28px; border-radius: 50%; background: #e31b23; color: #fff;
				font-weight: 700; font-size: 13px; display: inline-flex; align-items: center;
				justify-content: center; flex-shrink: 0; margin-top: 2px;
			}
			.edupro-reviews-admin .edupro-guide-step h4 {
				margin: 0 0 4px; font-size: 14px; color: #0f172a;
			}
			.edupro-reviews-admin .edupro-guide-step p { margin: 0 0 6px; }
			.edupro-reviews-admin .edupro-guide-step p:last-child { margin-bottom: 0; }
			.edupro-reviews-admin .edupro-guide-tip {
				background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b;
				border-radius: 6px; padding: 12px 14px; margin-top: 14px; font-size: 13px; color: #78350f;
			}
			.edupro-reviews-admin .edupro-guide-modal__foot {
				padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;
				display: flex; justify-content: flex-end; gap: 10px;
			}
			.edupro-mode-switch { display: flex; gap: 16px; margin-bottom: 16px; }
			.edupro-mode-option {
				flex: 1; display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px;
				border: 2px solid #ddd; border-radius: 8px; cursor: pointer; background: #fafafa;
			}
			.edupro-mode-option.is-selected { border-color: #e31b23; background: #fff5f5; }
			.edupro-review-row {
				border: 1px solid #e2e4e7; border-radius: 8px; padding: 16px;
				margin-bottom: 14px; background: #fafafa; position: relative;
			}
			.edupro-review-row-grid {
				display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
				gap: 12px; margin-bottom: 12px;
			}
			.edupro-review-row label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #444; }
			.edupro-review-row input, .edupro-review-row select, .edupro-review-row textarea { width: 100%; }
			.edupro-remove-review {
				position: absolute; top: 10px; right: 12px; color: #b32d2e; background: none;
				border: none; cursor: pointer; font-size: 18px; line-height: 1;
			}
			.edupro-btn-red { background: #e31b23 !important; border-color: #c0151c !important; color: #fff !important; }
			.edupro-btn-red:hover { background: #c0151c !important; }
		</style>

		<form method="post" action="">
			<?php wp_nonce_field( 'edupro_save_google_reviews', 'edupro_google_reviews_nonce' ); ?>

			<!-- Source Mode Selector -->
			<div class="edupro-admin-card">
				<h2 class="card-title"><?php esc_html_e( 'Review Source Mode', 'edupro' ); ?></h2>
				<div class="edupro-mode-switch">
					<label class="edupro-mode-option <?php echo 'google' === $mode ? 'is-selected' : ''; ?>">
						<input type="radio" name="edupro_google_reviews_mode" value="google" <?php checked( $mode, 'google' ); ?>>
						<div>
							<strong><?php esc_html_e( 'Google Places API (Live Sync)', 'edupro' ); ?></strong>
							<p class="description" style="margin:4px 0 0;"><?php esc_html_e( 'Automatically fetches reviews from your Google Business Profile with 12-hour caching. Falls back to manual reviews if unavailable.', 'edupro' ); ?></p>
						</div>
					</label>
					<label class="edupro-mode-option <?php echo 'manual' === $mode ? 'is-selected' : ''; ?>">
						<input type="radio" name="edupro_google_reviews_mode" value="manual" <?php checked( $mode, 'manual' ); ?>>
						<div>
							<strong><?php esc_html_e( 'Manual Reviews Mode', 'edupro' ); ?></strong>
							<p class="description" style="margin:4px 0 0;"><?php esc_html_e( 'No API key required. Add, edit, and curate verified Google/student reviews directly below.', 'edupro' ); ?></p>
						</div>
					</label>
				</div>
			</div>

			<!-- Google Places API Settings -->
			<div class="edupro-admin-card" id="edupro-google-api-box">
				<h2 class="card-title">
					<span><?php esc_html_e( 'Google Places API Configuration', 'edupro' ); ?></span>
					<button type="button" class="edupro-api-guide-link" data-open-api-guide>
						<?php esc_html_e( 'Need help? View API Setup Instructions →', 'edupro' ); ?>
					</button>
				</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="edupro_google_place_id"><?php esc_html_e( 'Google Place ID or Listing URL', 'edupro' ); ?></label></th>
						<td>
							<input type="text" id="edupro_google_place_id" name="edupro_google_place_id"
								   value="<?php echo esc_attr( $place_id ); ?>" class="large-text"
								   placeholder="ChIJN1t_tDeuEmsRUsoyG83frY4 or https://maps.google.com/...">
							<p class="description"><?php esc_html_e( 'Enter your Google Place ID (e.g. ChIJ...) or paste your Google Business Profile / Maps URL.', 'edupro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edupro_google_api_key"><?php esc_html_e( 'Google Places API Key', 'edupro' ); ?></label></th>
						<td>
							<input type="password" id="edupro_google_api_key" name="edupro_google_api_key"
								   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Enable "Places API" in Google Cloud Console and paste your API key here.', 'edupro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Control', 'edupro' ); ?></th>
						<td>
							<button type="submit" name="edupro_clear_reviews_cache" value="1" class="button">
								<?php esc_html_e( 'Clear Cached Google Reviews', 'edupro' ); ?>
							</button>
						</td>
					</tr>
				</table>
			</div>

			<!-- Manual Reviews Manager -->
			<div class="edupro-admin-card" id="edupro-manual-reviews-box">
				<h2 class="card-title"><?php esc_html_e( 'Manual Reviews & Badge Summary', 'edupro' ); ?></h2>

				<table class="form-table" style="margin-bottom:20px;">
					<tr>
						<th scope="row"><label for="edupro_google_business_name"><?php esc_html_e( 'Business / Academy Name', 'edupro' ); ?></label></th>
						<td>
							<input type="text" id="edupro_google_business_name" name="edupro_google_business_name"
								   value="<?php echo esc_attr( $business_name ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edupro_manual_overall_rating"><?php esc_html_e( 'Overall Rating Override', 'edupro' ); ?></label></th>
						<td>
							<input type="number" id="edupro_manual_overall_rating" name="edupro_manual_overall_rating"
								   value="<?php echo esc_attr( $overall_rating ); ?>" min="1" max="5" step="0.1" class="small-text">
							<span class="description"><?php esc_html_e( 'Leave blank to calculate automatically from manual reviews.', 'edupro' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edupro_manual_total_reviews"><?php esc_html_e( 'Total Reviews Count Override', 'edupro' ); ?></label></th>
						<td>
							<input type="number" id="edupro_manual_total_reviews" name="edupro_manual_total_reviews"
								   value="<?php echo esc_attr( $total_reviews ); ?>" min="0" step="1" class="small-text">
							<span class="description"><?php esc_html_e( 'Shown in the overall rating badge (e.g. "Based on 250 reviews").', 'edupro' ); ?></span>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Review Entries', 'edupro' ); ?></h3>
				<div id="edupro-manual-reviews-list">
					<?php foreach ( $manual_reviews as $index => $rev ) : ?>
						<div class="edupro-review-row">
							<button type="button" class="edupro-remove-review" title="<?php esc_attr_e( 'Remove Review', 'edupro' ); ?>">&times;</button>
							<div class="edupro-review-row-grid">
								<div>
									<label><?php esc_html_e( 'Reviewer Name', 'edupro' ); ?></label>
									<input type="text" name="manual_reviews[<?php echo esc_attr( $index ); ?>][name]"
										   value="<?php echo esc_attr( $rev['name'] ?? '' ); ?>" placeholder="John Doe">
								</div>
								<div>
									<label><?php esc_html_e( 'Rating (1-5)', 'edupro' ); ?></label>
									<select name="manual_reviews[<?php echo esc_attr( $index ); ?>][rating]">
										<?php for ( $s = 5; $s >= 1; $s-- ) : ?>
											<option value="<?php echo esc_attr( $s ); ?>" <?php selected( (int) ( $rev['rating'] ?? 5 ), $s ); ?>>
												<?php echo esc_html( $s . ' ★' ); ?>
											</option>
										<?php endfor; ?>
									</select>
								</div>
								<div>
									<label><?php esc_html_e( 'Date Label', 'edupro' ); ?></label>
									<input type="text" name="manual_reviews[<?php echo esc_attr( $index ); ?>][date]"
										   value="<?php echo esc_attr( $rev['date'] ?? '' ); ?>" placeholder="2 weeks ago">
								</div>
								<div>
									<label><?php esc_html_e( 'Reviewer Photo URL', 'edupro' ); ?></label>
									<input type="url" name="manual_reviews[<?php echo esc_attr( $index ); ?>][photo]"
										   value="<?php echo esc_attr( $rev['photo'] ?? '' ); ?>" placeholder="https://...">
								</div>
							</div>
							<div>
								<label><?php esc_html_e( 'Review Text', 'edupro' ); ?></label>
								<textarea rows="3" name="manual_reviews[<?php echo esc_attr( $index ); ?>][text]"
										  placeholder="<?php esc_attr_e( 'Write the student review here…', 'edupro' ); ?>"><?php echo esc_textarea( $rev['text'] ?? '' ); ?></textarea>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" id="edupro-add-review-btn" class="button">
					+ <?php esc_html_e( 'Add Manual Review', 'edupro' ); ?>
				</button>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary edupro-btn-red button-large">
					<?php esc_html_e( 'Save Google Reviews Settings', 'edupro' ); ?>
				</button>
			</p>
		</form>

		<!-- Google Reviews API Setup Instructions Modal -->
		<div id="edupro-api-guide-modal" class="edupro-guide-overlay" role="dialog" aria-modal="true" aria-labelledby="edupro-api-guide-title" aria-hidden="true">
			<div class="edupro-guide-modal">
				<div class="edupro-guide-modal__head">
					<h2 id="edupro-api-guide-title"><?php esc_html_e( 'How to Setup Google Places Reviews API', 'edupro' ); ?></h2>
					<button type="button" class="edupro-guide-modal__close" data-close-api-guide aria-label="<?php esc_attr_e( 'Close instructions', 'edupro' ); ?>">&times;</button>
				</div>
				<div class="edupro-guide-modal__body">
					<p style="margin-top:0;">
						<?php esc_html_e( 'Follow these 5 simple steps to automatically sync real reviews from your Google Business Profile. Results are cached for 12 hours, so API usage stays well within Google’s free monthly credit.', 'edupro' ); ?>
					</p>

					<div class="edupro-guide-step">
						<span class="edupro-guide-step__num">1</span>
						<div>
							<h4><?php esc_html_e( 'Create or Open a Google Cloud Project', 'edupro' ); ?></h4>
							<p>
								<?php esc_html_e( 'Sign in to', 'edupro' ); ?>
								<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console ↗</a>,
								<?php esc_html_e( 'click the project dropdown at the top, and create a new project (e.g. "My LMS Website"). Make sure a Billing Account is linked (Google includes a free monthly tier for Maps/Places).', 'edupro' ); ?>
							</p>
						</div>
					</div>

					<div class="edupro-guide-step">
						<span class="edupro-guide-step__num">2</span>
						<div>
							<h4><?php esc_html_e( 'Enable the "Places API"', 'edupro' ); ?></h4>
							<p>
								<?php esc_html_e( 'Go to', 'edupro' ); ?>
								<strong>APIs &amp; Services → Library</strong>,
								<?php esc_html_e( 'search for', 'edupro' ); ?>
								<code>Places API</code>,
								<?php esc_html_e( 'and click', 'edupro' ); ?>
								<strong>Enable</strong>.
							</p>
						</div>
					</div>

					<div class="edupro-guide-step">
						<span class="edupro-guide-step__num">3</span>
						<div>
							<h4><?php esc_html_e( 'Generate & Restrict Your API Key', 'edupro' ); ?></h4>
							<p>
								<?php esc_html_e( 'Open', 'edupro' ); ?>
								<strong>APIs &amp; Services → Credentials → + Create Credentials → API Key</strong>.
								<?php esc_html_e( 'Copy the generated key and paste it into the "Google Places API Key" field on this page.', 'edupro' ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Security Tip:', 'edupro' ); ?></strong>
								<?php esc_html_e( 'Under API Restrictions, restrict this key to "Places API" only. (Do NOT use HTTP Referrer restriction since WordPress fetches reviews server-side via PHP — use IP restriction or API-only restriction instead.)', 'edupro' ); ?>
							</p>
						</div>
					</div>

					<div class="edupro-guide-step">
						<span class="edupro-guide-step__num">4</span>
						<div>
							<h4><?php esc_html_e( 'Find Your Google Place ID (or Listing URL)', 'edupro' ); ?></h4>
							<p>
								<?php esc_html_e( 'Open Google’s official', 'edupro' ); ?>
								<a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank" rel="noopener noreferrer">Place ID Finder ↗</a>,
								<?php esc_html_e( 'search for your business name, and copy the Place ID (starts with', 'edupro' ); ?>
								<code>ChIJ...</code><?php esc_html_e( '). Paste it into "Google Place ID or Listing URL" — or simply paste your Google Maps business URL.', 'edupro' ); ?>
							</p>
						</div>
					</div>

					<div class="edupro-guide-step">
						<span class="edupro-guide-step__num">5</span>
						<div>
							<h4><?php esc_html_e( 'Select Live Sync Mode & Save', 'edupro' ); ?></h4>
							<p>
								<?php esc_html_e( 'Select "Google Places API (Live Sync)" at the top, click Save Google Reviews Settings, and refresh your homepage. Use "Clear Cached Google Reviews" anytime to pull the latest reviews immediately.', 'edupro' ); ?>
							</p>
						</div>
					</div>

					<div class="edupro-guide-tip">
						<strong><?php esc_html_e( 'Good to know:', 'edupro' ); ?></strong>
						<?php esc_html_e( 'Google’s Places API returns up to 5 most relevant reviews per business along with your live overall rating and total review count. If you prefer to showcase more than 5 reviews without an API key, switch to "Manual Reviews Mode" anytime.', 'edupro' ); ?>
					</div>
				</div>
				<div class="edupro-guide-modal__foot">
					<a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank" rel="noopener noreferrer" class="button">
						<?php esc_html_e( 'Open Google Cloud Console ↗', 'edupro' ); ?>
					</a>
					<button type="button" class="button button-primary edupro-btn-red" data-close-api-guide>
						<?php esc_html_e( 'Got It', 'edupro' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function(){
		var list   = document.getElementById('edupro-manual-reviews-list');
		var addBtn = document.getElementById('edupro-add-review-btn');
		var nextIdx = <?php echo (int) count( $manual_reviews ); ?>;

		// Setup Instructions Modal Controller
		var guideModal = document.getElementById('edupro-api-guide-modal');
		function openGuideModal() {
			if (!guideModal) return;
			guideModal.classList.add('is-open');
			guideModal.setAttribute('aria-hidden', 'false');
		}
		function closeGuideModal() {
			if (!guideModal) return;
			guideModal.classList.remove('is-open');
			guideModal.setAttribute('aria-hidden', 'true');
		}
		document.querySelectorAll('[data-open-api-guide]').forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				openGuideModal();
			});
		});
		document.querySelectorAll('[data-close-api-guide]').forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				closeGuideModal();
			});
		});
		if (guideModal) {
			guideModal.addEventListener('click', function(e){
				if (e.target === guideModal) closeGuideModal();
			});
			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape' && guideModal.classList.contains('is-open')) {
					closeGuideModal();
				}
			});
		}

		document.querySelectorAll('input[name="edupro_google_reviews_mode"]').forEach(function(radio){
			radio.addEventListener('change', function(){
				document.querySelectorAll('.edupro-mode-option').forEach(function(opt){
					opt.classList.remove('is-selected');
				});
				if (this.closest('.edupro-mode-option')) {
					this.closest('.edupro-mode-option').classList.add('is-selected');
				}
			});
		});

		list.addEventListener('click', function(e){
			if (e.target.classList.contains('edupro-remove-review')) {
				var row = e.target.closest('.edupro-review-row');
				if (row) row.remove();
			}
		});

		if (addBtn) {
			addBtn.addEventListener('click', function(){
				var i = nextIdx++;
				var div = document.createElement('div');
				div.className = 'edupro-review-row';
				div.innerHTML =
					'<button type="button" class="edupro-remove-review" title="Remove Review">&times;</button>' +
					'<div class="edupro-review-row-grid">' +
						'<div><label>Reviewer Name</label><input type="text" name="manual_reviews['+i+'][name]" placeholder="Student Name"></div>' +
						'<div><label>Rating (1-5)</label><select name="manual_reviews['+i+'][rating]">' +
							'<option value="5" selected>5 ★</option><option value="4">4 ★</option><option value="3">3 ★</option><option value="2">2 ★</option><option value="1">1 ★</option>' +
						'</select></div>' +
						'<div><label>Date Label</label><input type="text" name="manual_reviews['+i+'][date]" placeholder="Just now"></div>' +
						'<div><label>Reviewer Photo URL</label><input type="url" name="manual_reviews['+i+'][photo]" placeholder="https://..."></div>' +
					'</div>' +
					'<div><label>Review Text</label><textarea rows="3" name="manual_reviews['+i+'][text]" placeholder="Write the student review here…"></textarea></div>';
				list.appendChild(div);
			});
		}
	})();
	</script>
	<?php
}
