<?php
/**
 * EduPro LMS — Theme Shortcodes
 *
 * Registers all EduPro shortcodes:
 * - [edupro_courses count="8" category="" level="" featured="yes"]
 * - [edupro_categories]
 * - [edupro_reviews count="6"]
 * - [edupro_stats]
 * - [edupro_login_form]
 * - [edupro_register_form]
 * - [edupro_enroll_button course_id=""]
 * - [edupro_instructors count="4"]
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// FALLBACK COURSE CARD RENDERER (used if template-parts/course-card.php absent)
// =============================================================================

/**
 * Render a standalone course card HTML for a given course post ID.
 *
 * @param int $course_id Course ID.
 * @return string
 */
function edupro_render_fallback_course_card( $course_id ) {
	$course_id   = absint( $course_id );
	$permalink   = get_permalink( $course_id );
	$title       = get_the_title( $course_id );
	$thumb_html  = has_post_thumbnail( $course_id )
		? get_the_post_thumbnail( $course_id, 'course-card', array( 'class' => 'course-card__img', 'loading' => 'lazy' ) )
		: '<div class="course-card__placeholder" aria-hidden="true"><span>' . esc_html( mb_substr( $title, 0, 1 ) ) . '</span></div>';

	$terms       = get_the_terms( $course_id, 'course_category' );
	$category    = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
	$level       = get_post_meta( $course_id, '_edupro_course_level', true );
	$duration    = absint( get_post_meta( $course_id, '_edupro_duration', true ) );
	$rating      = function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $course_id ) : 0;
	$enrolled    = function_exists( 'edupro_get_enrolled_count' ) ? edupro_get_enrolled_count( $course_id ) : 0;
	$lessons     = function_exists( 'edupro_get_course_lessons_count' ) ? edupro_get_course_lessons_count( $course_id ) : 0;
	$author_id   = (int) get_post_field( 'post_author', $course_id );
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$pricing     = function_exists( 'edupro_get_course_price_info' )
		? edupro_get_course_price_info( $course_id )
		: array( 'regular' => 0, 'sale' => 0, 'final' => 0, 'is_free' => true, 'has_sale' => false );

	ob_start();
	?>
	<article class="course-card edupro-course-card">
		<div class="course-card__thumb">
			<a href="<?php echo esc_url( $permalink ); ?>" class="course-card__thumb-link">
				<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
			<div class="course-card__badges">
				<?php if ( $pricing['is_free'] ) : ?>
					<span class="edupro-badge edupro-badge--free"><?php esc_html_e( 'Free', 'edupro' ); ?></span>
				<?php elseif ( $pricing['has_sale'] ) : ?>
					<?php $discount = (int) round( ( ( $pricing['regular'] - $pricing['final'] ) / $pricing['regular'] ) * 100 ); ?>
					<span class="edupro-badge edupro-badge--sale"><?php echo esc_html( $discount . '% ' . __( 'OFF', 'edupro' ) ); ?></span>
				<?php endif; ?>
				<?php if ( $level ) : ?>
					<span class="edupro-badge edupro-badge--level"><?php echo esc_html( ucfirst( $level ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="course-card__hover-cta">
				<a href="<?php echo esc_url( $permalink ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-sm">
					<?php esc_html_e( 'View Course', 'edupro' ); ?>
				</a>
			</div>
		</div>

		<div class="course-card__body">
			<?php if ( $category ) : ?>
				<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="course-card__category">
					<?php echo esc_html( $category->name ); ?>
				</a>
			<?php endif; ?>

			<h3 class="course-card__title">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
			</h3>

			<p class="course-card__instructor">
				<?php
				printf(
					/* translators: %s: instructor name */
					esc_html__( 'By %s', 'edupro' ),
					esc_html( $author_name )
				);
				?>
			</p>

			<div class="course-card__rating">
				<span class="course-card__rating-num"><?php echo esc_html( number_format( $rating > 0 ? $rating : 4.8, 1 ) ); ?></span>
				<?php
				if ( function_exists( 'edupro_get_star_rating_html' ) ) {
					echo edupro_get_star_rating_html( $rating > 0 ? $rating : 4.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<span class="course-card__students">(<?php echo esc_html( number_format_i18n( $enrolled ) ); ?>)</span>
			</div>

			<div class="course-card__meta">
				<?php if ( $lessons > 0 ) : ?>
					<span><?php printf( esc_html( _n( '%d Lesson', '%d Lessons', $lessons, 'edupro' ) ), $lessons ); ?></span>
				<?php endif; ?>
				<?php if ( $duration > 0 && function_exists( 'edupro_get_duration_label' ) ) : ?>
					<span><?php echo esc_html( edupro_get_duration_label( $duration ) ); ?></span>
				<?php endif; ?>
			</div>

			<div class="course-card__footer">
				<div class="course-card__price">
					<?php if ( $pricing['is_free'] ) : ?>
						<span class="price-current price-free"><?php esc_html_e( 'Free', 'edupro' ); ?></span>
					<?php else : ?>
						<span class="price-current">
							<?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $pricing['final'] ) : '₹' . number_format( $pricing['final'], 2 ) ); ?>
						</span>
						<?php if ( $pricing['has_sale'] ) : ?>
							<del class="price-original">
								<?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $pricing['regular'] ) : '₹' . number_format( $pricing['regular'], 2 ) ); ?>
							</del>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<a href="<?php echo esc_url( $permalink ); ?>" class="course-card__link">
					<?php esc_html_e( 'Enroll', 'edupro' ); ?> &rarr;
				</a>
			</div>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

// =============================================================================
// 1. [edupro_courses count="8" category="" level="" featured="yes"]
// =============================================================================

function edupro_courses_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count'    => 8,
			'category' => '',
			'level'    => '',
			'featured' => 'yes',
			'columns'  => 4,
			'orderby'  => 'date',
			'order'    => 'DESC',
		),
		$atts,
		'edupro_courses'
	);

	$query_args = array(
		'post_type'      => 'lms_course',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, absint( $atts['count'] ) ),
		'orderby'        => sanitize_key( $atts['orderby'] ),
		'order'          => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
	);

	$tax_query = array();
	if ( ! empty( $atts['category'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'course_category',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', explode( ',', $atts['category'] ) ),
		);
	}
	if ( ! empty( $atts['level'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'course_level',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', explode( ',', $atts['level'] ) ),
		);
	}
	if ( ! empty( $tax_query ) ) {
		$tax_query['relation']   = 'AND';
		$query_args['tax_query'] = $tax_query;
	}

	// If featured="yes", prefer courses tagged/marked featured, but fall back to latest courses if none are flagged yet.
	if ( 'yes' === strtolower( $atts['featured'] ) ) {
		$featured_args               = $query_args;
		$featured_args['meta_query'] = array(
			array(
				'key'   => '_edupro_featured',
				'value' => '1',
			),
		);
		$featured_query = new WP_Query( $featured_args );
		$courses_query  = $featured_query->have_posts() ? $featured_query : new WP_Query( $query_args );
	} else {
		$courses_query = new WP_Query( $query_args );
	}

	if ( ! $courses_query->have_posts() ) {
		return '<div class="edupro-empty-state"><p>' . esc_html__( 'No courses found matching your criteria.', 'edupro' ) . '</p></div>';
	}

	$cols         = max( 1, min( 4, absint( $atts['columns'] ) ) );
	$has_template = locate_template( 'template-parts/course-card.php' );

	ob_start();
	echo '<div class="edupro-courses-grid edupro-grid-cols-' . esc_attr( $cols ) . '">';
	while ( $courses_query->have_posts() ) {
		$courses_query->the_post();
		if ( $has_template ) {
			get_template_part( 'template-parts/course-card' );
		} else {
			echo edupro_render_fallback_course_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	echo '</div>';
	wp_reset_postdata();

	return ob_get_clean();
}
add_shortcode( 'edupro_courses', 'edupro_courses_shortcode' );

// =============================================================================
// 2. [edupro_categories]
// =============================================================================

/**
 * Map a course category slug to a crisp inline SVG icon.
 *
 * @param string $slug Category slug.
 * @return string SVG markup.
 */
function edupro_get_category_icon_svg( $slug ) {
	$icons = array(
		'business'             => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 12h20"/></svg>',
		'technology'           => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/></svg>',
		'design'               => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><circle cx="11" cy="11" r="2"/></svg>',
		'marketing'            => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
		'health-fitness'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
		'personal-development' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
	);

	if ( isset( $icons[ $slug ] ) ) {
		return $icons[ $slug ];
	}

	return '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';
}

function edupro_categories_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count'      => 8,
			'hide_empty' => 'no',
		),
		$atts,
		'edupro_categories'
	);

	$terms = get_terms(
		array(
			'taxonomy'   => 'course_category',
			'hide_empty' => ( 'yes' === strtolower( $atts['hide_empty'] ) ),
			'number'     => absint( $atts['count'] ),
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '<div class="edupro-empty-state"><p>' . esc_html__( 'No course categories found.', 'edupro' ) . '</p></div>';
	}

	ob_start();
	?>
	<div class="edupro-categories-grid">
		<?php foreach ( $terms as $term ) : ?>
			<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="edupro-category-card">
				<span class="edupro-category-card__icon" aria-hidden="true">
					<?php echo edupro_get_category_icon_svg( $term->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<h3 class="edupro-category-card__name"><?php echo esc_html( $term->name ); ?></h3>
				<span class="edupro-category-card__count">
					<?php
					printf(
						esc_html( _n( '%d Course', '%d Courses', $term->count, 'edupro' ) ),
						(int) $term->count
					);
					?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'edupro_categories', 'edupro_categories_shortcode' );

// =============================================================================
// 3. [edupro_reviews count="6"] (delegates to inc/google-reviews.php)
// =============================================================================

if ( ! shortcode_exists( 'edupro_reviews' ) ) {
	add_shortcode(
		'edupro_reviews',
		function ( $atts ) {
			$atts = shortcode_atts( array( 'count' => 6 ), $atts, 'edupro_reviews' );
			if ( function_exists( 'edupro_render_google_reviews' ) ) {
				return edupro_render_google_reviews( absint( $atts['count'] ) );
			}
			return '';
		}
	);
}

// =============================================================================
// 4. [edupro_stats]
// =============================================================================

function edupro_stats_shortcode( $atts ) {
	$course_counts = wp_count_posts( 'lms_course' );
	$real_courses  = isset( $course_counts->publish ) ? (int) $course_counts->publish : 0;

	$inst_counts = post_type_exists( 'lms_instructor' ) ? wp_count_posts( 'lms_instructor' ) : null;
	$real_inst   = ( $inst_counts && isset( $inst_counts->publish ) ) ? (int) $inst_counts->publish : 0;

	$user_counts   = count_users();
	$real_students = isset( $user_counts['avail_roles']['edupro_student'] ) ? (int) $user_counts['avail_roles']['edupro_student'] : 0;

	$atts = shortcode_atts(
		array(
			'students'    => max( 10000, $real_students ),
			'courses'     => max( 500, $real_courses ),
			'instructors' => max( 50, $real_inst ),
			'rating'      => '4.9',
		),
		$atts,
		'edupro_stats'
	);

	$items = array(
		array(
			'target' => absint( $atts['students'] ),
			'suffix' => '+',
			'label'  => __( 'Active Students', 'edupro' ),
			'icon'   => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		),
		array(
			'target' => absint( $atts['courses'] ),
			'suffix' => '+',
			'label'  => __( 'Online Courses', 'edupro' ),
			'icon'   => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
		),
		array(
			'target' => absint( $atts['instructors'] ),
			'suffix' => '+',
			'label'  => __( 'Expert Instructors', 'edupro' ),
			'icon'   => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
		),
		array(
			'target' => (float) $atts['rating'],
			'suffix' => '/5',
			'label'  => __( 'Average Rating', 'edupro' ),
			'icon'   => '<svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
		),
	);

	ob_start();
	?>
	<div class="edupro-stats-grid">
		<?php foreach ( $items as $item ) : ?>
			<div class="edupro-stat-card animate-on-scroll">
				<div class="edupro-stat-card__icon" aria-hidden="true">
					<?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="edupro-stat-card__value">
					<span class="edupro-counter" data-target="<?php echo esc_attr( $item['target'] ); ?>" data-suffix="<?php echo esc_attr( $item['suffix'] ); ?>">
						<?php echo esc_html( is_int( $item['target'] ) ? number_format_i18n( $item['target'] ) . $item['suffix'] : $item['target'] . $item['suffix'] ); ?>
					</span>
				</div>
				<div class="edupro-stat-card__label"><?php echo esc_html( $item['label'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'edupro_stats', 'edupro_stats_shortcode' );

// =============================================================================
// 5. [edupro_login_form]
// =============================================================================

function edupro_login_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'redirect' => '',
		),
		$atts,
		'edupro_login_form'
	);

	$dashboard_url = function_exists( 'edupro_get_dashboard_url' ) ? edupro_get_dashboard_url() : home_url( '/dashboard/' );
	$redirect_to   = ! empty( $atts['redirect'] ) ? esc_url( $atts['redirect'] ) : $dashboard_url;

	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		ob_start();
		?>
		<div class="edupro-auth-logged-in">
			<p>
				<?php
				printf(
					/* translators: %s: user display name */
					esc_html__( 'You are currently logged in as %s.', 'edupro' ),
					'<strong>' . esc_html( $user->display_name ) . '</strong>'
				);
				?>
			</p>
			<div class="edupro-auth-logged-in__actions">
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-btn edupro-btn-primary">
					<?php esc_html_e( 'Go to Dashboard', 'edupro' ); ?>
				</a>
				<a href="<?php echo esc_url( function_exists( 'edupro_get_logout_url' ) ? edupro_get_logout_url() : wp_logout_url( home_url( '/' ) ) ); ?>" class="edupro-btn edupro-btn-outline">
					<?php esc_html_e( 'Log Out', 'edupro' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	$register_page_id = (int) get_option( 'edupro_register_page_id' );
	$register_url     = $register_page_id ? get_permalink( $register_page_id ) : home_url( '/student-register/' );

	ob_start();
	?>
	<div class="edupro-shortcode-auth-wrap">
		<form class="edupro-ajax-login-form edupro-auth-form" method="post" novalidate>
			<input type="hidden" name="action" value="edupro_login">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'edupro_nonce' ) ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

			<div class="edupro-form-alert" role="alert" style="display:none;"></div>

			<div class="edupro-form-group">
				<label for="edupro-sc-login-user"><?php esc_html_e( 'Username or Email Address', 'edupro' ); ?></label>
				<input type="text" id="edupro-sc-login-user" name="username" required autocomplete="username" placeholder="<?php esc_attr_e( 'you@example.com', 'edupro' ); ?>">
			</div>

			<div class="edupro-form-group">
				<label for="edupro-sc-login-pass"><?php esc_html_e( 'Password', 'edupro' ); ?></label>
				<input type="password" id="edupro-sc-login-pass" name="password" required autocomplete="current-password" placeholder="<?php esc_attr_e( 'Enter your password', 'edupro' ); ?>">
			</div>

			<div class="edupro-form-row edupro-form-row--between">
				<label class="edupro-checkbox-label">
					<input type="checkbox" name="remember" value="true">
					<span><?php esc_html_e( 'Remember me', 'edupro' ); ?></span>
				</label>
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="edupro-auth-link">
					<?php esc_html_e( 'Forgot password?', 'edupro' ); ?>
				</a>
			</div>

			<button type="submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
				<span class="edupro-btn-text"><?php esc_html_e( 'Sign In', 'edupro' ); ?></span>
				<span class="edupro-btn-spinner" aria-hidden="true" style="display:none;"></span>
			</button>

			<p class="edupro-auth-switch">
				<?php esc_html_e( "Don't have an account?", 'edupro' ); ?>
				<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create Account', 'edupro' ); ?></a>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'edupro_login_form', 'edupro_login_form_shortcode' );

// =============================================================================
// 6. [edupro_register_form]
// =============================================================================

function edupro_register_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'redirect' => '',
		),
		$atts,
		'edupro_register_form'
	);

	$dashboard_url = function_exists( 'edupro_get_dashboard_url' ) ? edupro_get_dashboard_url() : home_url( '/dashboard/' );
	$redirect_to   = ! empty( $atts['redirect'] ) ? esc_url( $atts['redirect'] ) : $dashboard_url;

	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		return '<div class="edupro-auth-logged-in"><p>'
			. sprintf( esc_html__( 'You are already registered and logged in as %s.', 'edupro' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' )
			. '</p><a href="' . esc_url( $dashboard_url ) . '" class="edupro-btn edupro-btn-primary">' . esc_html__( 'Go to Dashboard', 'edupro' ) . '</a></div>';
	}

	$login_page_id = (int) get_option( 'edupro_login_page_id' );
	$login_url     = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/student-login/' );

	ob_start();
	?>
	<div class="edupro-shortcode-auth-wrap">
		<form class="edupro-ajax-register-form edupro-auth-form" method="post" novalidate>
			<input type="hidden" name="action" value="edupro_register">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'edupro_nonce' ) ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

			<div class="edupro-form-alert" role="alert" style="display:none;"></div>

			<div class="edupro-form-grid-2">
				<div class="edupro-form-group">
					<label for="edupro-sc-reg-fname"><?php esc_html_e( 'First Name', 'edupro' ); ?></label>
					<input type="text" id="edupro-sc-reg-fname" name="first_name" required placeholder="<?php esc_attr_e( 'First name', 'edupro' ); ?>">
				</div>
				<div class="edupro-form-group">
					<label for="edupro-sc-reg-lname"><?php esc_html_e( 'Last Name', 'edupro' ); ?></label>
					<input type="text" id="edupro-sc-reg-lname" name="last_name" required placeholder="<?php esc_attr_e( 'Last name', 'edupro' ); ?>">
				</div>
			</div>

			<div class="edupro-form-group">
				<label for="edupro-sc-reg-email"><?php esc_html_e( 'Email Address', 'edupro' ); ?></label>
				<input type="email" id="edupro-sc-reg-email" name="email" required placeholder="<?php esc_attr_e( 'you@example.com', 'edupro' ); ?>">
			</div>

			<div class="edupro-form-grid-2">
				<div class="edupro-form-group">
					<label for="edupro-sc-reg-pass"><?php esc_html_e( 'Password', 'edupro' ); ?></label>
					<input type="password" id="edupro-sc-reg-pass" name="password" required minlength="8" placeholder="<?php esc_attr_e( 'Min. 8 characters', 'edupro' ); ?>">
				</div>
				<div class="edupro-form-group">
					<label for="edupro-sc-reg-cpass"><?php esc_html_e( 'Confirm Password', 'edupro' ); ?></label>
					<input type="password" id="edupro-sc-reg-cpass" name="confirm_password" required minlength="8" placeholder="<?php esc_attr_e( 'Re-enter password', 'edupro' ); ?>">
				</div>
			</div>

			<div class="edupro-form-group">
				<label class="edupro-checkbox-label">
					<input type="checkbox" name="terms" value="1" required>
					<span><?php esc_html_e( 'I agree to the Terms of Service and Privacy Policy.', 'edupro' ); ?></span>
				</label>
			</div>

			<button type="submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
				<span class="edupro-btn-text"><?php esc_html_e( 'Create Free Account', 'edupro' ); ?></span>
				<span class="edupro-btn-spinner" aria-hidden="true" style="display:none;"></span>
			</button>

			<p class="edupro-auth-switch">
				<?php esc_html_e( 'Already have an account?', 'edupro' ); ?>
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign In', 'edupro' ); ?></a>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'edupro_register_form', 'edupro_register_form_shortcode' );

// =============================================================================
// 7. [edupro_enroll_button course_id=""]
// =============================================================================

function edupro_enroll_button_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'course_id' => get_the_ID(),
			'text'      => '',
			'class'     => '',
		),
		$atts,
		'edupro_enroll_button'
	);

	$course_id = absint( $atts['course_id'] );
	if ( ! $course_id || 'lms_course' !== get_post_type( $course_id ) ) {
		return '';
	}

	$extra_class = sanitize_html_class( $atts['class'] );
	$is_enrolled = is_user_logged_in() && function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id );

	// Already enrolled: link straight to first lesson or dashboard.
	if ( $is_enrolled ) {
		$first_lessons = get_posts(
			array(
				'post_type'      => 'lms_lesson',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_edupro_course_id',
				'meta_value'     => $course_id,
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
			)
		);
		$continue_url = ! empty( $first_lessons ) ? get_permalink( $first_lessons[0] ) : get_permalink( $course_id );
		$label        = ! empty( $atts['text'] ) ? $atts['text'] : __( 'Continue Learning', 'edupro' );

		return sprintf(
			'<a href="%s" class="edupro-btn edupro-btn-secondary edupro-enroll-btn %s">%s &rarr;</a>',
			esc_url( $continue_url ),
			esc_attr( $extra_class ),
			esc_html( $label )
		);
	}

	$pricing = function_exists( 'edupro_get_course_price_info' )
		? edupro_get_course_price_info( $course_id )
		: array( 'final' => 0, 'is_free' => true );

	if ( $pricing['is_free'] ) {
		$label = ! empty( $atts['text'] ) ? $atts['text'] : __( 'Enroll Now — Free', 'edupro' );
		return sprintf(
			'<button type="button" class="edupro-btn edupro-btn-primary edupro-free-enroll-btn %1$s" data-course-id="%2$d" data-nonce="%3$s">%4$s</button>',
			esc_attr( $extra_class ),
			$course_id,
			esc_attr( wp_create_nonce( 'edupro_nonce' ) ),
			esc_html( $label )
		);
	}

	// Paid course -> Checkout page.
	$checkout_pages = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'templates/template-checkout.php',
			'number'     => 1,
		)
	);
	$checkout_base = ! empty( $checkout_pages ) ? get_permalink( $checkout_pages[0]->ID ) : home_url( '/checkout/' );
	$checkout_url  = add_query_arg( 'course_id', $course_id, $checkout_base );
	$price_text    = function_exists( 'edupro_format_price' ) ? edupro_format_price( $pricing['final'] ) : '₹' . number_format( $pricing['final'], 2 );
	$label         = ! empty( $atts['text'] ) ? $atts['text'] : sprintf( __( 'Buy Now — %s', 'edupro' ), $price_text );

	return sprintf(
		'<a href="%s" class="edupro-btn edupro-btn-primary edupro-buy-btn %s">%s</a>',
		esc_url( $checkout_url ),
		esc_attr( $extra_class ),
		esc_html( $label )
	);
}
add_shortcode( 'edupro_enroll_button', 'edupro_enroll_button_shortcode' );

// =============================================================================
// 8. [edupro_instructors count="4"]
// =============================================================================

function edupro_instructors_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count' => 4,
		),
		$atts,
		'edupro_instructors'
	);

	$count = max( 1, absint( $atts['count'] ) );

	$instructors = new WP_Query(
		array(
			'post_type'      => 'lms_instructor',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		)
	);

	ob_start();
	echo '<div class="edupro-instructors-grid">';

	if ( $instructors->have_posts() ) {
		while ( $instructors->have_posts() ) {
			$instructors->the_post();
			$iid         = get_the_ID();
			$designation = get_post_meta( $iid, '_edupro_instructor_title', true );
			$courses_cnt = absint( get_post_meta( $iid, '_edupro_instructor_courses', true ) );
			$students    = absint( get_post_meta( $iid, '_edupro_instructor_students', true ) );
			$thumb       = has_post_thumbnail( $iid )
				? get_the_post_thumbnail( $iid, 'instructor-avatar', array( 'class' => 'edupro-instructor-card__img', 'loading' => 'lazy' ) )
				: '<span class="edupro-instructor-card__initials">' . esc_html( mb_strtoupper( mb_substr( get_the_title(), 0, 2 ) ) ) . '</span>';
			?>
			<article class="edupro-instructor-card">
				<div class="edupro-instructor-card__avatar">
					<a href="<?php the_permalink(); ?>"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</div>
				<h3 class="edupro-instructor-card__name">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>
				<?php if ( $designation ) : ?>
					<p class="edupro-instructor-card__role"><?php echo esc_html( $designation ); ?></p>
				<?php endif; ?>
				<p class="edupro-instructor-card__bio"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
				<div class="edupro-instructor-card__stats">
					<?php if ( $courses_cnt > 0 ) : ?>
						<span><?php printf( esc_html( _n( '%d Course', '%d Courses', $courses_cnt, 'edupro' ) ), $courses_cnt ); ?></span>
					<?php endif; ?>
					<?php if ( $students > 0 ) : ?>
						<span><?php printf( esc_html__( '%s Students', 'edupro' ), esc_html( number_format_i18n( $students ) ) ); ?></span>
					<?php endif; ?>
				</div>
			</article>
			<?php
		}
		wp_reset_postdata();
	} else {
		// Fallback to WordPress authors who have published courses.
		$authors = get_users(
			array(
				'number'  => $count,
				'orderby' => 'post_count',
				'order'   => 'DESC',
				'role__in' => array( 'administrator', 'editor', 'author' ),
			)
		);
		foreach ( $authors as $author ) {
			$course_count = count_user_posts( $author->ID, 'lms_course', true );
			?>
			<article class="edupro-instructor-card">
				<div class="edupro-instructor-card__avatar">
					<img src="<?php echo esc_url( get_avatar_url( $author->ID, array( 'size' => 120 ) ) ); ?>" alt="<?php echo esc_attr( $author->display_name ); ?>" class="edupro-instructor-card__img" loading="lazy" width="120" height="120">
				</div>
				<h3 class="edupro-instructor-card__name"><?php echo esc_html( $author->display_name ); ?></h3>
				<p class="edupro-instructor-card__role"><?php esc_html_e( 'Lead Instructor', 'edupro' ); ?></p>
				<p class="edupro-instructor-card__bio">
					<?php
					echo esc_html(
						$author->description
							? wp_trim_words( $author->description, 18, '…' )
							: __( 'Industry practitioner passionate about practical, project-based online education.', 'edupro' )
					);
					?>
				</p>
				<div class="edupro-instructor-card__stats">
					<span><?php printf( esc_html( _n( '%d Course', '%d Courses', $course_count, 'edupro' ) ), (int) $course_count ); ?></span>
				</div>
			</article>
			<?php
		}
	}

	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'edupro_instructors', 'edupro_instructors_shortcode' );
