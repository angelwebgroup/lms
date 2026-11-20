<?php
/**
 * Template Name: Student Dashboard
 * Template Post Type: page
 *
 * Full Student Portal Dashboard:
 * - Left sidebar nav: My Courses, Profile, Certificates, Order History, Settings, Logout
 * - My Courses tab: grid of enrolled courses with circular SVG progress rings, Continue button, certificate download if 100% complete
 * - Profile tab: edit first/last name, email, phone, bio, change password, avatar upload with live preview
 * - Certificates tab: completed course certificates with printable/downloadable certificate preview modal
 * - Order History tab: table of orders from wp_edupro_orders (Order ID, Course, Amount, Gateway, Status, Date)
 * - Settings tab: notification preferences & preferred language
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$login_page_id = (int) get_option( 'edupro_login_page_id' );
$login_url     = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url( get_permalink() );

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', get_permalink(), $login_url ) );
	exit;
}

get_header();

$current_user = wp_get_current_user();
$user_id      = (int) $current_user->ID;
$active_tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'courses';
$valid_tabs   = array( 'courses', 'profile', 'certificates', 'orders', 'settings' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'courses';
}

$ajax_url       = admin_url( 'admin-ajax.php' );
$nonce          = wp_create_nonce( 'edupro_nonce' );
$logout_url     = function_exists( 'edupro_get_logout_url' ) ? edupro_get_logout_url() : wp_logout_url( home_url( '/' ) );
$courses_url    = get_post_type_archive_link( 'lms_course' ) ? get_post_type_archive_link( 'lms_course' ) : home_url( '/courses/' );
$email_verified = '1' === get_user_meta( $user_id, '_edupro_email_verified', true );
$phone          = get_user_meta( $user_id, '_edupro_phone', true );
$user_settings  = get_user_meta( $user_id, '_edupro_student_settings', true );
if ( ! is_array( $user_settings ) ) {
	$user_settings = array(
		'notify_course_updates' => '1',
		'notify_new_lessons'    => '1',
		'notify_certificates'   => '1',
		'notify_promotions'     => '0',
		'language'              => 'en',
		'timezone'              => 'Asia/Kolkata',
	);
}

// -----------------------------------------------------------------------------
// Gather Enrolled Courses + Progress Data
// -----------------------------------------------------------------------------
$enrolled_ids = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
if ( ! is_array( $enrolled_ids ) ) {
	$enrolled_ids = array();
}
$enrolled_ids = array_values( array_unique( array_filter( array_map( 'absint', $enrolled_ids ) ) ) );

$completed_lessons_all = get_user_meta( $user_id, '_edupro_completed_lessons', true );
if ( ! is_array( $completed_lessons_all ) ) {
	$completed_lessons_all = array();
}
$completed_lessons_all = array_map( 'absint', $completed_lessons_all );

$enrolled_courses   = array();
$completed_courses  = array();
$in_progress_count  = 0;

foreach ( $enrolled_ids as $cid ) {
	$course_post = get_post( $cid );
	if ( ! $course_post || 'lms_course' !== $course_post->post_type || 'publish' !== $course_post->post_status ) {
		continue;
	}

	// Lessons belonging to this course.
	$lesson_ids = get_posts(
		array(
			'post_type'      => 'lms_lesson',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_edupro_course_id',
			'meta_value'     => $cid,
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
		)
	);

	$total_lessons = count( $lesson_ids );
	$done_lessons  = count( array_intersect( $lesson_ids, $completed_lessons_all ) );

	if ( function_exists( 'edupro_get_course_progress' ) ) {
		$progress = (int) edupro_get_course_progress( $cid, $user_id );
	} else {
		$progress = $total_lessons > 0 ? (int) round( ( $done_lessons / $total_lessons ) * 100 ) : 0;
	}
	$progress = max( 0, min( 100, $progress ) );

	// Determine next uncompleted lesson link.
	$continue_url = get_permalink( $cid );
	foreach ( $lesson_ids as $lid ) {
		if ( ! in_array( $lid, $completed_lessons_all, true ) ) {
			$continue_url = get_permalink( $lid );
			break;
		}
	}
	if ( $progress >= 100 && ! empty( $lesson_ids ) ) {
		$continue_url = get_permalink( $lesson_ids[0] );
	}

	$cert_code = get_user_meta( $user_id, '_edupro_certificate_' . $cid . '_code', true );
	$cert_date = get_user_meta( $user_id, '_edupro_certificate_' . $cid . '_date', true );
	if ( $progress >= 100 && ! $cert_code ) {
		$cert_code = 'CERT-' . strtoupper( substr( md5( $user_id . '-' . $cid . '-' . wp_salt() ), 0, 10 ) );
		$cert_date = current_time( 'mysql' );
		update_user_meta( $user_id, '_edupro_certificate_' . $cid . '_code', $cert_code );
		update_user_meta( $user_id, '_edupro_certificate_' . $cid . '_date', $cert_date );
	}

	$terms       = get_the_terms( $cid, 'course_category' );
	$cat_name    = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	$enroll_date = get_user_meta( $user_id, '_edupro_enrollment_' . $cid . '_date', true );

	$item = array(
		'id'            => $cid,
		'title'         => get_the_title( $cid ),
		'url'           => get_permalink( $cid ),
		'continue_url'  => $continue_url,
		'thumbnail'     => get_the_post_thumbnail_url( $cid, 'course-card' ),
		'category'      => $cat_name,
		'instructor'    => get_the_author_meta( 'display_name', (int) $course_post->post_author ),
		'total_lessons' => $total_lessons,
		'done_lessons'  => $done_lessons,
		'progress'      => $progress,
		'enrolled_date' => $enroll_date,
		'cert_code'     => $cert_code,
		'cert_date'     => $cert_date,
		'has_cert'      => ( '0' !== get_post_meta( $cid, '_edupro_certificate', true ) ),
	);

	$enrolled_courses[] = $item;
	if ( $progress >= 100 ) {
		$completed_courses[] = $item;
	} else {
		$in_progress_count++;
	}
}

// -----------------------------------------------------------------------------
// Fetch Order History (uses edupro_get_user_orders when available)
// -----------------------------------------------------------------------------
$active_currency = function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR';
$orders          = array();
$total_spent     = 0.0;

if ( function_exists( 'edupro_get_user_orders' ) ) {
	$orders = (array) edupro_get_user_orders( $user_id, 50 );
} else {
	global $wpdb;
	$orders_table = $wpdb->prefix . 'edupro_orders';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table ) {
		$orders = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);
	}
}

foreach ( $orders as $ord ) {
	if ( 'completed' === $ord->status ) {
		$ord_currency = ! empty( $ord->currency ) ? strtoupper( $ord->currency ) : 'INR';
		$ord_amt      = (float) $ord->amount;
		if ( function_exists( 'edupro_convert_price' ) && $ord_currency !== $active_currency ) {
			$ord_amt = (float) edupro_convert_price( $ord_amt, $ord_currency, $active_currency );
		}
		$total_spent += $ord_amt;
	}
}

$avatar_url = get_avatar_url( $user_id, array( 'size' => 140 ) );
?>

<main id="primary" class="edupro-dashboard-page-wrap">
	<div class="edupro-container">

		<!-- Verification Notice (if newly verified via email link) -->
		<?php if ( isset( $_GET['edupro_verify_email'] ) && $email_verified ) : ?>
			<div class="edupro-alert edupro-alert-success" style="margin-bottom:20px;">
				<?php esc_html_e( 'Your email address has been verified successfully!', 'edupro' ); ?>
			</div>
		<?php endif; ?>

		<div class="edupro-dashboard-layout">

			<!-- ============================================================= -->
			<!-- LEFT SIDEBAR NAVIGATION                                       -->
			<!-- ============================================================= -->
			<aside class="edupro-dashboard-sidebar" aria-label="<?php esc_attr_e( 'Dashboard Navigation', 'edupro' ); ?>">
				<div class="edupro-dashboard-user-card">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $current_user->display_name ); ?>" class="edupro-dashboard-avatar" id="edupro-sidebar-avatar" width="72" height="72">
					<h2 class="edupro-dashboard-username" id="edupro-sidebar-name"><?php echo esc_html( $current_user->display_name ); ?></h2>
					<p class="edupro-dashboard-email"><?php echo esc_html( $current_user->user_email ); ?></p>
					<span class="edupro-dashboard-role-badge"><?php esc_html_e( 'Student', 'edupro' ); ?></span>
				</div>

				<nav class="edupro-dashboard-nav">
					<button type="button" class="edupro-dash-nav-item <?php echo 'courses' === $active_tab ? 'is-active' : ''; ?>" data-tab="courses">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
						<span><?php esc_html_e( 'My Courses', 'edupro' ); ?></span>
						<span class="edupro-dash-count"><?php echo esc_html( count( $enrolled_courses ) ); ?></span>
					</button>

					<button type="button" class="edupro-dash-nav-item <?php echo 'profile' === $active_tab ? 'is-active' : ''; ?>" data-tab="profile">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						<span><?php esc_html_e( 'Profile', 'edupro' ); ?></span>
					</button>

					<button type="button" class="edupro-dash-nav-item <?php echo 'certificates' === $active_tab ? 'is-active' : ''; ?>" data-tab="certificates">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
						<span><?php esc_html_e( 'Certificates', 'edupro' ); ?></span>
						<span class="edupro-dash-count"><?php echo esc_html( count( $completed_courses ) ); ?></span>
					</button>

					<button type="button" class="edupro-dash-nav-item <?php echo 'orders' === $active_tab ? 'is-active' : ''; ?>" data-tab="orders">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						<span><?php esc_html_e( 'Order History', 'edupro' ); ?></span>
					</button>

					<button type="button" class="edupro-dash-nav-item <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" data-tab="settings">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
						<span><?php esc_html_e( 'Settings', 'edupro' ); ?></span>
					</button>

					<a href="<?php echo esc_url( $logout_url ); ?>" class="edupro-dash-nav-item edupro-dash-nav-logout">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
						<span><?php esc_html_e( 'Logout', 'edupro' ); ?></span>
					</a>
				</nav>
			</aside>

			<!-- ============================================================= -->
			<!-- MAIN DASHBOARD CONTENT                                        -->
			<!-- ============================================================= -->
			<section class="edupro-dashboard-main">

				<!-- Summary KPI Row -->
				<div class="edupro-dash-kpi-grid">
					<div class="edupro-dash-kpi-card">
						<span class="edupro-kpi-value"><?php echo esc_html( count( $enrolled_courses ) ); ?></span>
						<span class="edupro-kpi-label"><?php esc_html_e( 'Enrolled Courses', 'edupro' ); ?></span>
					</div>
					<div class="edupro-dash-kpi-card">
						<span class="edupro-kpi-value"><?php echo esc_html( $in_progress_count ); ?></span>
						<span class="edupro-kpi-label"><?php esc_html_e( 'In Progress', 'edupro' ); ?></span>
					</div>
					<div class="edupro-dash-kpi-card">
						<span class="edupro-kpi-value"><?php echo esc_html( count( $completed_courses ) ); ?></span>
						<span class="edupro-kpi-label"><?php esc_html_e( 'Certificates Earned', 'edupro' ); ?></span>
					</div>
					<div class="edupro-dash-kpi-card">
						<span class="edupro-kpi-value">
							<?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $total_spent, $active_currency ) : '₹' . number_format( $total_spent, 2 ) ); ?>
						</span>
						<span class="edupro-kpi-label"><?php esc_html_e( 'Total Invested', 'edupro' ); ?></span>
					</div>
				</div>

				<!-- ========================================================= -->
				<!-- TAB 1: MY COURSES                                         -->
				<!-- ========================================================= -->
				<div id="edupro-tab-courses" class="edupro-dash-panel <?php echo 'courses' === $active_tab ? 'is-active' : ''; ?>">
					<div class="edupro-dash-panel-header">
						<div>
							<h1><?php esc_html_e( 'My Courses', 'edupro' ); ?></h1>
							<p><?php esc_html_e( 'Track your progress and pick up right where you left off.', 'edupro' ); ?></p>
						</div>
						<div class="edupro-course-filter-pills">
							<button type="button" class="edupro-pill-btn is-active" data-course-filter="all"><?php esc_html_e( 'All', 'edupro' ); ?></button>
							<button type="button" class="edupro-pill-btn" data-course-filter="in-progress"><?php esc_html_e( 'In Progress', 'edupro' ); ?></button>
							<button type="button" class="edupro-pill-btn" data-course-filter="completed"><?php esc_html_e( 'Completed', 'edupro' ); ?></button>
						</div>
					</div>

					<?php if ( empty( $enrolled_courses ) ) : ?>
						<div class="edupro-dash-empty">
							<h3><?php esc_html_e( 'You have not enrolled in any courses yet', 'edupro' ); ?></h3>
							<p><?php esc_html_e( 'Explore our catalog of expert-led courses and start building new skills today.', 'edupro' ); ?></p>
							<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-primary">
								<?php esc_html_e( 'Browse Courses', 'edupro' ); ?>
							</a>
						</div>
					<?php else : ?>
						<div class="edupro-dash-courses-grid">
							<?php foreach ( $enrolled_courses as $course ) : ?>
								<?php
								$pct           = (int) $course['progress'];
								$is_complete   = ( $pct >= 100 );
								$radius        = 24;
								$circumference = round( 2 * M_PI * $radius, 2 );
								$dash_offset   = round( $circumference - ( $pct / 100 ) * $circumference, 2 );
								?>
								<article class="edupro-dash-course-card" data-status="<?php echo $is_complete ? 'completed' : 'in-progress'; ?>">
									<div class="edupro-dash-course-thumb">
										<a href="<?php echo esc_url( $course['continue_url'] ); ?>">
											<?php if ( $course['thumbnail'] ) : ?>
												<img src="<?php echo esc_url( $course['thumbnail'] ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>" loading="lazy">
											<?php else : ?>
												<div class="edupro-dash-thumb-placeholder"><?php echo esc_html( mb_substr( $course['title'], 0, 1 ) ); ?></div>
											<?php endif; ?>
										</a>
										<?php if ( $is_complete ) : ?>
											<span class="edupro-dash-badge-complete"><?php esc_html_e( 'Completed', 'edupro' ); ?></span>
										<?php endif; ?>
									</div>

									<div class="edupro-dash-course-body">
										<?php if ( $course['category'] ) : ?>
											<span class="edupro-dash-course-cat"><?php echo esc_html( $course['category'] ); ?></span>
										<?php endif; ?>

										<h3 class="edupro-dash-course-title">
											<a href="<?php echo esc_url( $course['url'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a>
										</h3>
										<p class="edupro-dash-course-instructor"><?php echo esc_html( $course['instructor'] ); ?></p>

										<!-- Circular Progress Ring + Lesson Count -->
										<div class="edupro-dash-progress-row">
											<div class="edupro-progress-ring-wrap" data-progress="<?php echo esc_attr( $pct ); ?>">
												<svg class="edupro-progress-ring" width="58" height="58" viewBox="0 0 58 58" aria-label="<?php echo esc_attr( $pct . '%' ); ?>">
													<circle class="edupro-progress-ring__bg" cx="29" cy="29" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="#e8e8f0" stroke-width="5"></circle>
													<circle class="edupro-progress-ring__fill" cx="29" cy="29" r="<?php echo esc_attr( $radius ); ?>" fill="none"
															stroke="<?php echo $is_complete ? '#2ecc71' : '#e31b23'; ?>" stroke-width="5" stroke-linecap="round"
															stroke-dasharray="<?php echo esc_attr( $circumference ); ?>"
															stroke-dashoffset="<?php echo esc_attr( $dash_offset ); ?>"
															transform="rotate(-90 29 29)"></circle>
												</svg>
												<span class="edupro-progress-ring__text"><?php echo esc_html( $pct ); ?>%</span>
											</div>
											<div class="edupro-dash-progress-meta">
												<strong>
													<?php
													printf(
														/* translators: 1: completed lessons, 2: total lessons */
														esc_html__( '%1$d / %2$d Lessons', 'edupro' ),
														(int) $course['done_lessons'],
														(int) $course['total_lessons']
													);
													?>
												</strong>
												<span><?php echo $is_complete ? esc_html__( 'Course Completed!', 'edupro' ) : esc_html__( 'Keep going!', 'edupro' ); ?></span>
											</div>
										</div>

										<div class="edupro-dash-course-actions">
											<a href="<?php echo esc_url( $course['continue_url'] ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-sm">
												<?php echo $pct > 0 ? esc_html__( 'Continue', 'edupro' ) : esc_html__( 'Start Course', 'edupro' ); ?> &rarr;
											</a>
											<?php if ( $is_complete && $course['has_cert'] ) : ?>
												<button type="button"
														class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-open-cert-btn"
														data-student="<?php echo esc_attr( $current_user->display_name ); ?>"
														data-course="<?php echo esc_attr( $course['title'] ); ?>"
														data-date="<?php echo esc_attr( date_i18n( 'F j, Y', strtotime( $course['cert_date'] ? $course['cert_date'] : current_time( 'mysql' ) ) ) ); ?>"
														data-code="<?php echo esc_attr( $course['cert_code'] ); ?>">
													<?php esc_html_e( 'Certificate', 'edupro' ); ?>
												</button>
											<?php endif; ?>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- ========================================================= -->
				<!-- TAB 2: PROFILE                                            -->
				<!-- ========================================================= -->
				<div id="edupro-tab-profile" class="edupro-dash-panel <?php echo 'profile' === $active_tab ? 'is-active' : ''; ?>">
					<div class="edupro-dash-panel-header">
						<div>
							<h1><?php esc_html_e( 'My Profile', 'edupro' ); ?></h1>
							<p><?php esc_html_e( 'Update your personal information, avatar, and account password.', 'edupro' ); ?></p>
						</div>
					</div>

					<form id="edupro-profile-form" class="edupro-dash-card-form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="edupro_update_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

						<div id="edupro-profile-alert" class="edupro-alert" role="alert" style="display:none;"></div>

						<!-- Avatar Upload -->
						<div class="edupro-avatar-upload-box">
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $current_user->display_name ); ?>" id="edupro-profile-avatar-preview" class="edupro-avatar-preview" width="84" height="84">
							<div class="edupro-avatar-upload-controls">
								<label for="edupro-avatar-input" class="edupro-btn edupro-btn-outline edupro-btn-sm">
									<?php esc_html_e( 'Upload New Avatar', 'edupro' ); ?>
								</label>
								<input type="file" id="edupro-avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
								<p class="edupro-field-hint"><?php esc_html_e( 'JPG, PNG or WebP. Max size 2 MB.', 'edupro' ); ?></p>
							</div>
						</div>

						<div class="edupro-form-grid-2">
							<div class="edupro-form-group">
								<label for="edupro-prof-fname"><?php esc_html_e( 'First Name', 'edupro' ); ?></label>
								<input type="text" id="edupro-prof-fname" name="first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" required>
							</div>
							<div class="edupro-form-group">
								<label for="edupro-prof-lname"><?php esc_html_e( 'Last Name', 'edupro' ); ?></label>
								<input type="text" id="edupro-prof-lname" name="last_name" value="<?php echo esc_attr( $current_user->last_name ); ?>" required>
							</div>
						</div>

						<div class="edupro-form-grid-2">
							<div class="edupro-form-group">
								<label for="edupro-prof-email"><?php esc_html_e( 'Email Address', 'edupro' ); ?></label>
								<input type="email" id="edupro-prof-email" name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required>
							</div>
							<div class="edupro-form-group">
								<label for="edupro-prof-phone"><?php esc_html_e( 'Phone Number', 'edupro' ); ?></label>
								<input type="tel" id="edupro-prof-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+91 98765 43210">
							</div>
						</div>

						<div class="edupro-form-group">
							<label for="edupro-prof-bio"><?php esc_html_e( 'Short Bio', 'edupro' ); ?></label>
							<textarea id="edupro-prof-bio" name="bio" rows="3" placeholder="<?php esc_attr_e( 'Tell us about your learning goals…', 'edupro' ); ?>"><?php echo esc_textarea( $current_user->description ); ?></textarea>
						</div>

						<hr class="edupro-dash-divider">
						<h3 class="edupro-dash-subhead"><?php esc_html_e( 'Change Password (Optional)', 'edupro' ); ?></h3>

						<div class="edupro-form-grid-3">
							<div class="edupro-form-group">
								<label for="edupro-prof-cur-pw"><?php esc_html_e( 'Current Password', 'edupro' ); ?></label>
								<input type="password" id="edupro-prof-cur-pw" name="current_password" autocomplete="current-password">
							</div>
							<div class="edupro-form-group">
								<label for="edupro-prof-new-pw"><?php esc_html_e( 'New Password', 'edupro' ); ?></label>
								<input type="password" id="edupro-prof-new-pw" name="new_password" minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Min. 8 characters', 'edupro' ); ?>">
							</div>
							<div class="edupro-form-group">
								<label for="edupro-prof-conf-pw"><?php esc_html_e( 'Confirm New Password', 'edupro' ); ?></label>
								<input type="password" id="edupro-prof-conf-pw" name="confirm_password" minlength="8" autocomplete="new-password">
							</div>
						</div>

						<button type="submit" id="edupro-profile-submit" class="edupro-btn edupro-btn-primary">
							<?php esc_html_e( 'Save Profile Changes', 'edupro' ); ?>
						</button>
					</form>
				</div>

				<!-- ========================================================= -->
				<!-- TAB 3: CERTIFICATES                                       -->
				<!-- ========================================================= -->
				<div id="edupro-tab-certificates" class="edupro-dash-panel <?php echo 'certificates' === $active_tab ? 'is-active' : ''; ?>">
					<div class="edupro-dash-panel-header">
						<div>
							<h1><?php esc_html_e( 'My Certificates', 'edupro' ); ?></h1>
							<p><?php esc_html_e( 'Download or print verified certificates for your completed courses.', 'edupro' ); ?></p>
						</div>
					</div>

					<?php if ( empty( $completed_courses ) ) : ?>
						<div class="edupro-dash-empty">
							<h3><?php esc_html_e( 'No Certificates Earned Yet', 'edupro' ); ?></h3>
							<p><?php esc_html_e( 'Complete 100% of lessons in any enrolled course to unlock your Certificate of Completion.', 'edupro' ); ?></p>
							<button type="button" class="edupro-btn edupro-btn-primary" data-jump-tab="courses">
								<?php esc_html_e( 'View My Courses', 'edupro' ); ?>
							</button>
						</div>
					<?php else : ?>
						<div class="edupro-certificates-grid">
							<?php foreach ( $completed_courses as $cert_course ) : ?>
								<?php $issued_date = date_i18n( 'F j, Y', strtotime( $cert_course['cert_date'] ? $cert_course['cert_date'] : current_time( 'mysql' ) ) ); ?>
								<div class="edupro-certificate-card">
									<div class="edupro-certificate-card__ribbon" aria-hidden="true">
										<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#e31b23" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
									</div>
									<div class="edupro-certificate-card__info">
										<h3><?php echo esc_html( $cert_course['title'] ); ?></h3>
										<p class="edupro-cert-meta">
											<?php esc_html_e( 'Issued:', 'edupro' ); ?> <?php echo esc_html( $issued_date ); ?> &middot;
											<?php esc_html_e( 'ID:', 'edupro' ); ?> <code><?php echo esc_html( $cert_course['cert_code'] ); ?></code>
										</p>
									</div>
									<button type="button"
											class="edupro-btn edupro-btn-primary edupro-btn-sm edupro-open-cert-btn"
											data-student="<?php echo esc_attr( $current_user->display_name ); ?>"
											data-course="<?php echo esc_attr( $cert_course['title'] ); ?>"
											data-date="<?php echo esc_attr( $issued_date ); ?>"
											data-code="<?php echo esc_attr( $cert_course['cert_code'] ); ?>">
										<?php esc_html_e( 'View / Download', 'edupro' ); ?>
									</button>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- ========================================================= -->
				<!-- TAB 4: ORDER HISTORY                                      -->
				<!-- ========================================================= -->
				<div id="edupro-tab-orders" class="edupro-dash-panel <?php echo 'orders' === $active_tab ? 'is-active' : ''; ?>">
					<div class="edupro-dash-panel-header">
						<div>
							<h1><?php esc_html_e( 'Order History', 'edupro' ); ?></h1>
							<p><?php esc_html_e( 'Review all your course purchases and payment statuses.', 'edupro' ); ?></p>
						</div>
					</div>

					<?php if ( empty( $orders ) ) : ?>
						<div class="edupro-dash-empty">
							<h3><?php esc_html_e( 'No Orders Found', 'edupro' ); ?></h3>
							<p><?php esc_html_e( 'When you enroll in a free or paid course, your receipts will appear here.', 'edupro' ); ?></p>
						</div>
					<?php else : ?>
						<div class="edupro-table-responsive">
							<table class="edupro-orders-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Order & Txn ID', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Course', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Gateway', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Status', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Date', 'edupro' ); ?></th>
										<th><?php esc_html_e( 'Invoice', 'edupro' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$gateway_labels = array(
										'cashfree'      => __( 'Cashfree', 'edupro' ),
										'razorpay'      => __( 'Razorpay', 'edupro' ),
										'stripe'        => __( 'Stripe', 'edupro' ),
										'paypal'        => __( 'PayPal', 'edupro' ),
										'bank_transfer' => __( 'Bank Transfer', 'edupro' ),
										'free'          => __( 'Free', 'edupro' ),
									);
									$status_labels = array(
										'completed'  => __( 'Completed', 'edupro' ),
										'pending'    => __( 'Pending Verification', 'edupro' ),
										'processing' => __( 'Processing', 'edupro' ),
										'failed'     => __( 'Failed', 'edupro' ),
										'refunded'   => __( 'Refunded', 'edupro' ),
									);
									foreach ( $orders as $order ) :
										$order_course_ids = function_exists( 'edupro_get_order_course_ids' )
											? edupro_get_order_course_ids( $order )
											: ( ! empty( $order->course_id ) ? array( absint( $order->course_id ) ) : array() );
										$primary_cid      = ! empty( $order_course_ids ) ? (int) $order_course_ids[0] : absint( $order->course_id );
										$course_title     = function_exists( 'edupro_get_order_course_summary_title' )
											? edupro_get_order_course_summary_title( $order )
											: ( get_the_title( $primary_cid ) ? get_the_title( $primary_cid ) : '#' . $primary_cid );
										$course_link      = $primary_cid ? get_permalink( $primary_cid ) : '';
										$all_titles       = array();
										foreach ( $order_course_ids as $ocid ) {
											$t = get_the_title( $ocid );
											if ( $t ) {
												$all_titles[] = $t;
											}
										}
										$titles_tooltip   = count( $all_titles ) > 1 ? implode( ', ', $all_titles ) : '';
										$order_currency   = ! empty( $order->currency ) ? strtoupper( $order->currency ) : 'INR';
										$gw_key           = strtolower( (string) $order->gateway );
										$gw_label         = isset( $gateway_labels[ $gw_key ] ) ? $gateway_labels[ $gw_key ] : ucwords( str_replace( '_', ' ', $gw_key ) );
										$status_key       = strtolower( (string) $order->status );
										$status_slug      = sanitize_html_class( $status_key );
										$status_label     = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : ucfirst( $status_key );
										$coupon_code      = ! empty( $order->coupon_code ) ? $order->coupon_code : '';
										$txn_id           = ! empty( $order->transaction_id ) ? $order->transaction_id : '';
										$invoice_url      = function_exists( 'edupro_get_invoice_url' ) ? edupro_get_invoice_url( $order->order_id ) : '';
										?>
										<tr>
											<td data-label="<?php esc_attr_e( 'Order ID', 'edupro' ); ?>">
												<code><?php echo esc_html( $order->order_id ); ?></code>
												<?php if ( $txn_id ) : ?>
													<div class="edupro-order-txn">
														<small><?php esc_html_e( 'Txn:', 'edupro' ); ?> <code><?php echo esc_html( $txn_id ); ?></code></small>
													</div>
												<?php endif; ?>
											</td>
											<td data-label="<?php esc_attr_e( 'Course', 'edupro' ); ?>">
												<?php if ( $course_link ) : ?>
													<a href="<?php echo esc_url( $course_link ); ?>" <?php echo $titles_tooltip ? 'title="' . esc_attr( $titles_tooltip ) . '"' : ''; ?>>
														<?php echo esc_html( $course_title ); ?>
													</a>
												<?php else : ?>
													<span <?php echo $titles_tooltip ? 'title="' . esc_attr( $titles_tooltip ) . '"' : ''; ?>>
														<?php echo esc_html( $course_title ); ?>
													</span>
												<?php endif; ?>
												<?php if ( $coupon_code ) : ?>
													<div class="edupro-order-coupon">
														<span class="edupro-order-coupon-tag"><?php echo esc_html( $coupon_code ); ?></span>
													</div>
												<?php endif; ?>
											</td>
											<td data-label="<?php esc_attr_e( 'Amount', 'edupro' ); ?>">
												<strong>
													<?php
													echo (float) $order->amount <= 0
														? esc_html__( 'Free', 'edupro' )
														: esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $order->amount, $order_currency ) : $order_currency . ' ' . number_format( (float) $order->amount, 2 ) );
													?>
												</strong>
												<?php if ( (float) $order->amount > 0 ) : ?>
													<small class="edupro-order-currency"><?php echo esc_html( $order_currency ); ?></small>
												<?php endif; ?>
											</td>
											<td data-label="<?php esc_attr_e( 'Gateway', 'edupro' ); ?>"><?php echo esc_html( $gw_label ); ?></td>
											<td data-label="<?php esc_attr_e( 'Status', 'edupro' ); ?>">
												<span class="edupro-order-status edupro-order-status--<?php echo esc_attr( $status_slug ); ?>">
													<?php echo esc_html( $status_label ); ?>
												</span>
											</td>
											<td data-label="<?php esc_attr_e( 'Date', 'edupro' ); ?>"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $order->created_at ) ) ); ?></td>
											<td data-label="<?php esc_attr_e( 'Invoice', 'edupro' ); ?>">
												<?php if ( $invoice_url ) : ?>
													<a href="<?php echo esc_url( $invoice_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-invoice-btn">
														<?php esc_html_e( 'Invoice', 'edupro' ); ?>
													</a>
												<?php else : ?>
													<span>—</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- ========================================================= -->
				<!-- TAB 5: SETTINGS                                           -->
				<!-- ========================================================= -->
				<div id="edupro-tab-settings" class="edupro-dash-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>">
					<div class="edupro-dash-panel-header">
						<div>
							<h1><?php esc_html_e( 'Account Settings', 'edupro' ); ?></h1>
							<p><?php esc_html_e( 'Manage email notifications and language preferences.', 'edupro' ); ?></p>
						</div>
					</div>

					<form id="edupro-settings-form" class="edupro-dash-card-form" method="post">
						<input type="hidden" name="action" value="edupro_update_settings">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

						<div id="edupro-settings-alert" class="edupro-alert" role="alert" style="display:none;"></div>

						<h3 class="edupro-dash-subhead"><?php esc_html_e( 'Email Notifications', 'edupro' ); ?></h3>
						<div class="edupro-settings-toggles">
							<label class="edupro-checkbox-label">
								<input type="checkbox" name="notify_course_updates" value="1" <?php checked( $user_settings['notify_course_updates'] ?? '1', '1' ); ?>>
								<span><?php esc_html_e( 'Course announcements and instructor updates', 'edupro' ); ?></span>
							</label>
							<label class="edupro-checkbox-label">
								<input type="checkbox" name="notify_new_lessons" value="1" <?php checked( $user_settings['notify_new_lessons'] ?? '1', '1' ); ?>>
								<span><?php esc_html_e( 'Notify me when new lessons are added to my courses', 'edupro' ); ?></span>
							</label>
							<label class="edupro-checkbox-label">
								<input type="checkbox" name="notify_certificates" value="1" <?php checked( $user_settings['notify_certificates'] ?? '1', '1' ); ?>>
								<span><?php esc_html_e( 'Certificate completion alerts and download links', 'edupro' ); ?></span>
							</label>
							<label class="edupro-checkbox-label">
								<input type="checkbox" name="notify_promotions" value="1" <?php checked( $user_settings['notify_promotions'] ?? '0', '1' ); ?>>
								<span><?php esc_html_e( 'Special offers, discounts, and new course launches', 'edupro' ); ?></span>
							</label>
						</div>

						<hr class="edupro-dash-divider">
						<h3 class="edupro-dash-subhead"><?php esc_html_e( 'Regional & Language Preferences', 'edupro' ); ?></h3>

						<div class="edupro-form-grid-2">
							<div class="edupro-form-group">
								<label for="edupro-setting-lang"><?php esc_html_e( 'Preferred Language', 'edupro' ); ?></label>
								<select id="edupro-setting-lang" name="language">
									<?php
									$langs = array(
										'en' => 'English',
										'hi' => 'हिन्दी (Hindi)',
										'es' => 'Español (Spanish)',
										'fr' => 'Français (French)',
										'de' => 'Deutsch (German)',
									);
									$cur_lang = $user_settings['language'] ?? 'en';
									foreach ( $langs as $code => $label ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $cur_lang, $code, false ), esc_html( $label ) );
									}
									?>
								</select>
							</div>
							<div class="edupro-form-group">
								<label for="edupro-setting-tz"><?php esc_html_e( 'Time Zone', 'edupro' ); ?></label>
								<select id="edupro-setting-tz" name="timezone">
									<?php
									$tzs = array(
										'Asia/Kolkata'      => 'India Standard Time (IST, UTC+5:30)',
										'UTC'               => 'Coordinated Universal Time (UTC)',
										'Europe/London'     => 'London (GMT/BST)',
										'America/New_York'  => 'Eastern Time (US & Canada)',
										'America/Los_Angeles' => 'Pacific Time (US & Canada)',
									);
									$cur_tz = $user_settings['timezone'] ?? 'Asia/Kolkata';
									foreach ( $tzs as $tz_val => $tz_lbl ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $tz_val ), selected( $cur_tz, $tz_val, false ), esc_html( $tz_lbl ) );
									}
									?>
								</select>
							</div>
						</div>

						<button type="submit" id="edupro-settings-submit" class="edupro-btn edupro-btn-primary">
							<?php esc_html_e( 'Save Preferences', 'edupro' ); ?>
						</button>
					</form>
				</div>

			</section>
		</div>
	</div>
</main>

<!-- Certificate Printable Preview Modal -->
<div id="edupro-cert-modal" class="edupro-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="edupro-cert-modal-title" style="display:none;">
	<div class="edupro-modal-dialog edupro-cert-dialog">
		<button type="button" class="edupro-modal-close" id="edupro-close-cert-modal" aria-label="<?php esc_attr_e( 'Close', 'edupro' ); ?>">&times;</button>
		<div id="edupro-printable-certificate" class="edupro-certificate-sheet">
			<div class="edupro-cert-border">
				<p class="edupro-cert-academy"><?php bloginfo( 'name' ); ?></p>
				<h2 id="edupro-cert-modal-title" class="edupro-cert-heading"><?php esc_html_e( 'Certificate of Completion', 'edupro' ); ?></h2>
				<p class="edupro-cert-presented"><?php esc_html_e( 'This certifies that', 'edupro' ); ?></p>
				<h3 id="edupro-cert-student-name" class="edupro-cert-recipient"></h3>
				<p class="edupro-cert-presented"><?php esc_html_e( 'has successfully completed the online course', 'edupro' ); ?></p>
				<h4 id="edupro-cert-course-name" class="edupro-cert-course"></h4>
				<div class="edupro-cert-footer-row">
					<div>
						<span class="edupro-cert-meta-label"><?php esc_html_e( 'Date of Completion', 'edupro' ); ?></span>
						<strong id="edupro-cert-date"></strong>
					</div>
					<div>
						<span class="edupro-cert-meta-label"><?php esc_html_e( 'Certificate ID', 'edupro' ); ?></span>
						<strong id="edupro-cert-code"></strong>
					</div>
				</div>
			</div>
		</div>
		<div class="edupro-cert-modal-actions">
			<button type="button" id="edupro-print-cert-btn" class="edupro-btn edupro-btn-primary">
				<?php esc_html_e( 'Print / Save as PDF', 'edupro' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
(function(){
	var ajaxUrl = (window.edupro_vars && window.edupro_vars.ajaxurl) ? window.edupro_vars.ajaxurl : <?php echo wp_json_encode( $ajax_url ); ?>;

	// Tab switching
	var navButtons = document.querySelectorAll('.edupro-dash-nav-item[data-tab]');
	var panels     = document.querySelectorAll('.edupro-dash-panel');

	function activateTab(tabName) {
		navButtons.forEach(function(b){ b.classList.toggle('is-active', b.getAttribute('data-tab') === tabName); });
		panels.forEach(function(p){ p.classList.toggle('is-active', p.id === 'edupro-tab-' + tabName); });
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tabName);
			window.history.replaceState({}, '', url.toString());
		}
	}

	navButtons.forEach(function(btn){
		btn.addEventListener('click', function(){ activateTab(btn.getAttribute('data-tab')); });
	});
	document.querySelectorAll('[data-jump-tab]').forEach(function(btn){
		btn.addEventListener('click', function(){ activateTab(btn.getAttribute('data-jump-tab')); });
	});

	// Course filter pills
	document.querySelectorAll('.edupro-pill-btn[data-course-filter]').forEach(function(pill){
		pill.addEventListener('click', function(){
			var filter = pill.getAttribute('data-course-filter');
			document.querySelectorAll('.edupro-pill-btn[data-course-filter]').forEach(function(p){ p.classList.remove('is-active'); });
			pill.classList.add('is-active');
			document.querySelectorAll('.edupro-dash-course-card').forEach(function(card){
				var status = card.getAttribute('data-status');
				card.style.display = (filter === 'all' || status === filter) ? '' : 'none';
			});
		});
	});

	// Avatar preview
	var avatarInput   = document.getElementById('edupro-avatar-input');
	var avatarPreview = document.getElementById('edupro-profile-avatar-preview');
	if (avatarInput && avatarPreview) {
		avatarInput.addEventListener('change', function(){
			if (this.files && this.files[0]) {
				var reader = new FileReader();
				reader.onload = function(ev){ avatarPreview.src = ev.target.result; };
				reader.readAsDataURL(this.files[0]);
			}
		});
	}

	function showAlert(el, type, msg) {
		if (!el) return;
		el.className = 'edupro-alert edupro-alert-' + type;
		el.textContent = msg;
		el.style.display = 'block';
	}

	// Profile update AJAX (multipart FormData for avatar upload)
	var profileForm = document.getElementById('edupro-profile-form');
	if (profileForm) {
		profileForm.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl = document.getElementById('edupro-profile-alert');
			var btn     = document.getElementById('edupro-profile-submit');
			btn.disabled = true;

			var fd = new FormData(profileForm);
			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					btn.disabled = false;
					if (res && res.success) {
						showAlert(alertEl, 'success', res.data.message || 'Profile updated!');
						if (res.data.display_name) {
							var nameEl = document.getElementById('edupro-sidebar-name');
							if (nameEl) nameEl.textContent = res.data.display_name;
						}
						if (res.data.avatar_url) {
							var sideAv = document.getElementById('edupro-sidebar-avatar');
							if (sideAv) sideAv.src = res.data.avatar_url;
							if (avatarPreview) avatarPreview.src = res.data.avatar_url;
						}
						document.getElementById('edupro-prof-cur-pw').value  = '';
						document.getElementById('edupro-prof-new-pw').value  = '';
						document.getElementById('edupro-prof-conf-pw').value = '';
					} else {
						showAlert(alertEl, 'error', (res && res.data && res.data.message) ? res.data.message : 'Update failed.');
					}
				})
				.catch(function(){
					btn.disabled = false;
					showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>);
				});
		});
	}

	// Settings update AJAX
	var settingsForm = document.getElementById('edupro-settings-form');
	if (settingsForm) {
		settingsForm.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl = document.getElementById('edupro-settings-alert');
			var btn     = document.getElementById('edupro-settings-submit');
			btn.disabled = true;

			var fd = new FormData(settingsForm);
			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					btn.disabled = false;
					if (res && res.success) {
						showAlert(alertEl, 'success', res.data.message || 'Preferences saved!');
					} else {
						showAlert(alertEl, 'error', (res && res.data && res.data.message) ? res.data.message : 'Could not save settings.');
					}
				})
				.catch(function(){
					btn.disabled = false;
					showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>);
				});
		});
	}

	// Certificate Modal + Print/Save PDF
	var certModal = document.getElementById('edupro-cert-modal');
	var closeCert = document.getElementById('edupro-close-cert-modal');
	var printCert = document.getElementById('edupro-print-cert-btn');

	document.querySelectorAll('.edupro-open-cert-btn').forEach(function(btn){
		btn.addEventListener('click', function(){
			document.getElementById('edupro-cert-student-name').textContent = btn.getAttribute('data-student');
			document.getElementById('edupro-cert-course-name').textContent  = btn.getAttribute('data-course');
			document.getElementById('edupro-cert-date').textContent         = btn.getAttribute('data-date');
			document.getElementById('edupro-cert-code').textContent         = btn.getAttribute('data-code');
			certModal.style.display = 'flex';
		});
	});

	if (closeCert && certModal) {
		closeCert.addEventListener('click', function(){ certModal.style.display = 'none'; });
		certModal.addEventListener('click', function(e){ if (e.target === certModal) certModal.style.display = 'none'; });
	}

	if (printCert) {
		printCert.addEventListener('click', function(){
			var content = document.getElementById('edupro-printable-certificate').innerHTML;
			var win = window.open('', '_blank', 'width=960,height=700');
			if (!win) { window.print(); return; }
			win.document.write('<!doctype html><html><head><title>Certificate</title><style>' +
				'body{font-family:"Plus Jakarta Sans",Inter,sans-serif;margin:0;padding:40px;background:#fff;display:flex;justify-content:center;align-items:center;min-height:100vh;box-sizing:border-box;}' +
				'.edupro-cert-border{border:10px double #e31b23;padding:56px 64px;text-align:center;max-width:820px;width:100%;}' +
				'.edupro-cert-academy{color:#e31b23;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin:0 0 8px;}' +
				'.edupro-cert-heading{font-size:36px;color:#1a1a2e;margin:0 0 24px;}' +
				'.edupro-cert-presented{color:#666;margin:8px 0;}' +
				'.edupro-cert-recipient{font-size:32px;color:#1a1a2e;border-bottom:2px solid #e8e8f0;display:inline-block;padding:0 24px 8px;margin:12px 0 18px;}' +
				'.edupro-cert-course{font-size:22px;color:#e31b23;margin:10px 0 36px;}' +
				'.edupro-cert-footer-row{display:flex;justify-content:space-between;border-top:1px solid #e8e8f0;padding-top:20px;text-align:left;}' +
				'.edupro-cert-meta-label{display:block;font-size:12px;color:#888;text-transform:uppercase;}' +
				'</style></head><body>' + content + '<script>window.onload=function(){window.print();};<\/script></body></html>');
			win.document.close();
		});
	}
})();
</script>

<?php
get_footer();
