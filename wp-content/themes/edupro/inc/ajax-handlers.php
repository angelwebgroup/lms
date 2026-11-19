<?php
/**
 * EduPro LMS — Central AJAX Handlers
 *
 * Implements and registers theme AJAX endpoints (wp_ajax_ & wp_ajax_nopriv_):
 * - edupro_login
 * - edupro_register
 * - edupro_enroll (fallback when inc/enrollment.php has not registered it)
 * - edupro_mark_lesson_complete
 * - edupro_live_search
 * - edupro_load_more_courses
 * - edupro_submit_review
 * - edupro_update_profile
 * - edupro_update_settings
 *
 * Note: Payment AJAX endpoints (`edupro_checkout_initiate`, `edupro_razorpay_verify`,
 * `edupro_stripe_confirm`) are owned and registered authoritatively by `inc/payment-gateways.php`.
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// HELPER: NONCE VERIFICATION & COURSE PRICE RESOLUTION
// =============================================================================

/**
 * Verify EduPro AJAX nonce. Supports both the primary theme nonce (`edupro_nonce`)
 * and the payment nonce (`edupro_payment_nonce`) when allowed.
 *
 * @param bool $allow_payment_nonce Whether to also accept `edupro_payment_nonce`.
 * @return bool
 */
function edupro_verify_ajax_nonce( $allow_payment_nonce = false ) {
	$nonce = '';
	if ( isset( $_POST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
	} elseif ( isset( $_GET['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );
	} elseif ( isset( $_POST['security'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
	}

	if ( empty( $nonce ) ) {
		return false;
	}

	if ( wp_verify_nonce( $nonce, 'edupro_nonce' ) ) {
		return true;
	}

	if ( $allow_payment_nonce && wp_verify_nonce( $nonce, 'edupro_payment_nonce' ) ) {
		return true;
	}

	return false;
}

/**
 * Resolve effective pricing metadata for a course across both meta key conventions.
 *
 * @param int $course_id Course post ID.
 * @return array { regular: float, sale: float, final: float, is_free: bool, has_sale: bool }
 */
function edupro_get_course_price_info( $course_id ) {
	$course_id = absint( $course_id );

	$is_free_flag = get_post_meta( $course_id, '_edupro_is_free', true );
	if ( '' === $is_free_flag ) {
		$is_free_flag = get_post_meta( $course_id, '_edupro_course_free', true );
	}

	$regular = get_post_meta( $course_id, '_edupro_regular_price', true );
	$sale    = get_post_meta( $course_id, '_edupro_sale_price', true );
	$legacy  = get_post_meta( $course_id, '_edupro_course_price', true );

	if ( '' === $regular && '' !== $legacy ) {
		$regular = $legacy;
	}

	$regular_val = function_exists( 'edupro_sanitize_price' ) ? edupro_sanitize_price( $regular ) : (float) $regular;
	$sale_val    = '' !== $sale ? ( function_exists( 'edupro_sanitize_price' ) ? edupro_sanitize_price( $sale ) : (float) $sale ) : 0.0;

	// Check sale end date if present.
	$sale_end = get_post_meta( $course_id, '_edupro_sale_end_date', true );
	if ( $sale_end && strtotime( $sale_end . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
		$sale_val = 0.0;
	}

	$has_sale = ( $sale_val > 0 && $regular_val > 0 && $sale_val < $regular_val );
	$final    = $has_sale ? $sale_val : ( $sale_val > 0 ? $sale_val : $regular_val );
	$is_free  = ( '1' === (string) $is_free_flag || $final <= 0 );

	if ( $is_free ) {
		$final = 0.0;
	}

	return array(
		'regular'  => $regular_val,
		'sale'     => $sale_val,
		'final'    => $final,
		'is_free'  => $is_free,
		'has_sale' => ! $is_free && $has_sale,
	);
}

/**
 * Fallback enrollment helper in case inc/enrollment.php hasn't defined edupro_enroll_student().
 *
 * @param int $course_id Course post ID.
 * @param int $user_id   User ID.
 * @return bool
 */
function edupro_ajax_perform_enrollment( $course_id, $user_id ) {
	if ( function_exists( 'edupro_enroll_student' ) ) {
		return (bool) edupro_enroll_student( $course_id, $user_id );
	}

	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );
	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	$user_courses = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
	if ( ! is_array( $user_courses ) ) {
		$user_courses = array();
	}
	$user_courses = array_map( 'absint', $user_courses );
	if ( ! in_array( $course_id, $user_courses, true ) ) {
		$user_courses[] = $course_id;
		update_user_meta( $user_id, '_edupro_enrolled_courses', array_values( array_unique( $user_courses ) ) );
	}

	$course_students = get_post_meta( $course_id, '_edupro_enrolled_students', true );
	if ( ! is_array( $course_students ) ) {
		$course_students = array();
	}
	$course_students = array_map( 'absint', $course_students );
	if ( ! in_array( $user_id, $course_students, true ) ) {
		$course_students[] = $user_id;
		update_post_meta( $course_id, '_edupro_enrolled_students', array_values( array_unique( $course_students ) ) );
	}

	if ( ! get_user_meta( $user_id, '_edupro_enrollment_' . $course_id . '_date', true ) ) {
		update_user_meta( $user_id, '_edupro_enrollment_' . $course_id . '_date', current_time( 'mysql' ) );
	}

	return true;
}

/**
 * Helper to resolve dashboard permalink.
 *
 * @return string
 */
function edupro_ajax_get_dashboard_url() {
	if ( function_exists( 'edupro_get_dashboard_url' ) ) {
		return edupro_get_dashboard_url();
	}
	$page_id = (int) get_option( 'edupro_dashboard_page_id' );
	return $page_id ? get_permalink( $page_id ) : home_url( '/dashboard/' );
}

// =============================================================================
// 1. AJAX: STUDENT LOGIN (edupro_login)
// =============================================================================

/**
 * Handle AJAX student login with username or email.
 */
function edupro_ajax_login() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ), 403 );
	}

	$login_input = '';
	if ( isset( $_POST['username'] ) ) {
		$login_input = sanitize_text_field( wp_unslash( $_POST['username'] ) );
	} elseif ( isset( $_POST['email'] ) ) {
		$login_input = sanitize_text_field( wp_unslash( $_POST['email'] ) );
	} elseif ( isset( $_POST['log'] ) ) {
		$login_input = sanitize_text_field( wp_unslash( $_POST['log'] ) );
	}

	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$remember = isset( $_POST['remember'] ) && in_array( (string) $_POST['remember'], array( '1', 'true', 'on', 'yes' ), true );

	if ( empty( $login_input ) || empty( $password ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter your username/email and password.', 'edupro' ) ) );
	}

	$user = wp_authenticate( $login_input, $password );

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid username/email or password. Please try again.', 'edupro' ) ) );
	}

	wp_clear_auth_cookie();
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, $remember );

	$redirect_url = edupro_ajax_get_dashboard_url();
	if ( ! empty( $_POST['redirect_to'] ) ) {
		$requested = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
		if ( $requested ) {
			$redirect_url = wp_validate_redirect( $requested, $redirect_url );
		}
	}

	do_action( 'edupro_student_login', $user->ID, $redirect_url );

	wp_send_json_success(
		array(
			'message'      => __( 'Login successful! Redirecting to your dashboard…', 'edupro' ),
			'redirect_url' => $redirect_url,
			'user'         => array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
			),
		)
	);
}
add_action( 'wp_ajax_edupro_login', 'edupro_ajax_login', 5 );
add_action( 'wp_ajax_nopriv_edupro_login', 'edupro_ajax_login', 5 );

// =============================================================================
// 2. AJAX: STUDENT REGISTRATION (edupro_register)
// =============================================================================

/**
 * Handle AJAX student registration.
 * Auto-generates a unique username from email if username is omitted.
 */
function edupro_ajax_register() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ), 403 );
	}

	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$username   = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
	$password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$confirm    = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';
	$terms      = isset( $_POST['terms'] ) ? sanitize_text_field( wp_unslash( $_POST['terms'] ) ) : '1';

	if ( empty( $first_name ) || empty( $last_name ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter both your first and last name.', 'edupro' ) ) );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'edupro' ) ) );
	}

	if ( email_exists( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please log in instead.', 'edupro' ) ) );
	}

	if ( strlen( $password ) < 8 ) {
		wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters long.', 'edupro' ) ) );
	}

	if ( $password !== $confirm ) {
		wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'edupro' ) ) );
	}

	if ( isset( $_POST['terms'] ) && ! in_array( $terms, array( '1', 'true', 'on', 'yes' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'You must agree to the Terms & Conditions to register.', 'edupro' ) ) );
	}

	// Auto-generate username from email if not provided.
	if ( empty( $username ) ) {
		$base_user = sanitize_user( current( explode( '@', $email ) ), true );
		$base_user = $base_user ? $base_user : 'student';
		$username  = $base_user;
		$suffix    = 1;
		while ( username_exists( $username ) ) {
			$username = $base_user . $suffix;
			$suffix++;
		}
	} elseif ( username_exists( $username ) ) {
		wp_send_json_error( array( 'message' => __( 'That username is already taken. Please choose another.', 'edupro' ) ) );
	}

	$user_id = wp_create_user( $username, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
	}

	// Ensure student role exists before assigning.
	$role = get_role( 'edupro_student' ) ? 'edupro_student' : 'subscriber';

	wp_update_user(
		array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'role'         => $role,
		)
	);

	update_user_meta( $user_id, '_edupro_registered_date', current_time( 'mysql' ) );
	update_user_meta( $user_id, '_edupro_enrolled_courses', array() );

	// Send verification / welcome email.
	$verify_token = wp_generate_password( 32, false );
	update_user_meta( $user_id, '_edupro_email_verify_token', $verify_token );
	update_user_meta( $user_id, '_edupro_email_verified', '0' );

	$verify_url = add_query_arg(
		array(
			'edupro_verify_email' => $verify_token,
			'uid'                 => $user_id,
		),
		edupro_ajax_get_dashboard_url()
	);

	$blog_name = get_bloginfo( 'name' );
	$subject   = sprintf( __( 'Welcome to %s — Please Verify Your Email', 'edupro' ), $blog_name );
	$body      = sprintf(
		__( "Hi %1\$s,\n\nWelcome to %2\$s! Your student account is ready.\n\nPlease verify your email address by clicking the link below:\n%3\$s\n\nYou can also start browsing courses right away from your dashboard:\n%4\$s\n\nHappy Learning,\nThe %2\$s Team", 'edupro' ),
		$first_name,
		$blog_name,
		$verify_url,
		edupro_ajax_get_dashboard_url()
	);
	wp_mail( $email, $subject, $body );

	// Auto-login newly created student.
	wp_clear_auth_cookie();
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	$redirect_url = edupro_ajax_get_dashboard_url();
	if ( ! empty( $_POST['redirect_to'] ) ) {
		$requested = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
		if ( $requested ) {
			$redirect_url = wp_validate_redirect( $requested, $redirect_url );
		}
	}

	do_action( 'edupro_student_registered', $user_id );

	wp_send_json_success(
		array(
			'message'      => __( 'Account created! A verification email has been sent. Redirecting to your dashboard…', 'edupro' ),
			'redirect_url' => $redirect_url,
			'user_id'      => $user_id,
		)
	);
}
add_action( 'wp_ajax_edupro_register', 'edupro_ajax_register', 5 );
add_action( 'wp_ajax_nopriv_edupro_register', 'edupro_ajax_register', 5 );

/**
 * Process email verification link click.
 */
function edupro_handle_email_verification_link() {
	if ( empty( $_GET['edupro_verify_email'] ) || empty( $_GET['uid'] ) ) {
		return;
	}

	$uid   = absint( $_GET['uid'] );
	$token = sanitize_text_field( wp_unslash( $_GET['edupro_verify_email'] ) );
	$saved = get_user_meta( $uid, '_edupro_email_verify_token', true );

	if ( $saved && hash_equals( (string) $saved, $token ) ) {
		update_user_meta( $uid, '_edupro_email_verified', '1' );
		delete_user_meta( $uid, '_edupro_email_verify_token' );
	}
}
add_action( 'template_redirect', 'edupro_handle_email_verification_link' );

// =============================================================================
// 3. AJAX: FREE ENROLLMENT (edupro_enroll)
// =============================================================================

/**
 * Handle free course enrollment via AJAX.
 */
function edupro_ajax_enroll() {
	if ( ! edupro_verify_ajax_nonce( true ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	if ( ! is_user_logged_in() ) {
		$login_page_id = (int) get_option( 'edupro_login_page_id' );
		$login_url     = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
		wp_send_json_error(
			array(
				'message'       => __( 'Please log in to enroll in this course.', 'edupro' ),
				'require_login' => true,
				'login_url'     => $login_url,
			)
		);
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	if ( ! $course_id || 'lms_course' !== get_post_type( $course_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid course selected.', 'edupro' ) ) );
	}

	$user_id = get_current_user_id();

	if ( function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id, $user_id ) ) {
		wp_send_json_success(
			array(
				'message'      => __( 'You are already enrolled in this course.', 'edupro' ),
				'enrolled'     => true,
				'redirect_url' => edupro_ajax_get_dashboard_url(),
			)
		);
	}

	// Enforce enrollment limit if set.
	$limit = absint( get_post_meta( $course_id, '_edupro_enrollment_limit', true ) );
	if ( $limit > 0 && function_exists( 'edupro_get_enrolled_count' ) && edupro_get_enrolled_count( $course_id ) >= $limit ) {
		wp_send_json_error( array( 'message' => __( 'This course has reached its maximum enrollment limit.', 'edupro' ) ) );
	}

	// Verify course is free; paid courses must go through checkout.
	$pricing = edupro_get_course_price_info( $course_id );
	if ( ! $pricing['is_free'] ) {
		$checkout_page = get_page_by_path( 'checkout' );
		$checkout_url  = $checkout_page ? add_query_arg( 'course_id', $course_id, get_permalink( $checkout_page->ID ) ) : add_query_arg( 'course_id', $course_id, home_url( '/checkout/' ) );
		wp_send_json_error(
			array(
				'message'      => __( 'This is a paid course. Redirecting to checkout…', 'edupro' ),
				'requires_pay' => true,
				'checkout_url' => $checkout_url,
			)
		);
	}

	$enrolled = edupro_ajax_perform_enrollment( $course_id, $user_id );
	if ( ! $enrolled ) {
		wp_send_json_error( array( 'message' => __( 'Could not complete enrollment. Please try again.', 'edupro' ) ) );
	}

	// Record a zero-amount completed order if payment order table helper exists.
	if ( function_exists( 'edupro_insert_order' ) ) {
		edupro_insert_order(
			array(
				'user_id'        => $user_id,
				'course_id'      => $course_id,
				'amount'         => 0,
				'gateway'        => 'free',
				'status'         => 'completed',
				'transaction_id' => 'FREE-' . $course_id . '-' . time(),
			)
		);
	}

	wp_send_json_success(
		array(
			'message'      => __( 'Successfully enrolled! Redirecting to your course…', 'edupro' ),
			'enrolled'     => true,
			'redirect_url' => edupro_ajax_get_dashboard_url(),
			'course_url'   => get_permalink( $course_id ),
		)
	);
}
if ( ! has_action( 'wp_ajax_edupro_enroll' ) ) {
	add_action( 'wp_ajax_edupro_enroll', 'edupro_ajax_enroll' );
	add_action( 'wp_ajax_nopriv_edupro_enroll', 'edupro_ajax_enroll' );
}

// =============================================================================
// 4. PAYMENT AJAX ENDPOINTS
// =============================================================================
// Payment gateway AJAX actions (`edupro_checkout_initiate`,
// `edupro_razorpay_verify`, `edupro_stripe_confirm`) are registered directly
// in `inc/payment-gateways.php` as the single authoritative owner.

// =============================================================================
// 5. AJAX: MARK LESSON COMPLETE (edupro_mark_lesson_complete)
// =============================================================================

/**
 * Mark a lesson as completed for the current student and return updated course progress.
 */
function edupro_ajax_mark_lesson_complete() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Please log in to track your progress.', 'edupro' ) ), 401 );
	}

	$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$user_id   = get_current_user_id();

	if ( ! $lesson_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid lesson ID.', 'edupro' ) ) );
	}

	if ( ! $course_id ) {
		$course_id = (int) get_post_meta( $lesson_id, '_edupro_course_id', true );
	}

	// Permission check: student must have access to the parent course (if linked).
	if ( $course_id && function_exists( 'edupro_check_student_access' ) && ! edupro_check_student_access( $course_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not enrolled in this course.', 'edupro' ) ), 403 );
	}

	// Mark lesson complete via enrollment helper or directly in user meta.
	if ( function_exists( 'edupro_mark_lesson_complete' ) ) {
		edupro_mark_lesson_complete( $lesson_id, $user_id );
	} else {
		$completed = get_user_meta( $user_id, '_edupro_completed_lessons', true );
		if ( ! is_array( $completed ) ) {
			$completed = array();
		}
		$completed = array_map( 'absint', $completed );
		if ( ! in_array( $lesson_id, $completed, true ) ) {
			$completed[] = $lesson_id;
			update_user_meta( $user_id, '_edupro_completed_lessons', array_values( array_unique( $completed ) ) );
		}
		if ( $course_id ) {
			$course_completed = get_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, true );
			if ( ! is_array( $course_completed ) ) {
				$course_completed = array();
			}
			$course_completed = array_map( 'absint', $course_completed );
			if ( ! in_array( $lesson_id, $course_completed, true ) ) {
				$course_completed[] = $lesson_id;
				update_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, array_values( array_unique( $course_completed ) ) );
			}
		}
	}

	// Calculate updated progress %.
	$progress = 0;
	if ( $course_id ) {
		if ( function_exists( 'edupro_get_course_progress' ) ) {
			$progress = (int) edupro_get_course_progress( $course_id, $user_id );
		} else {
			$total_lessons = function_exists( 'edupro_get_course_lessons_count' ) ? (int) edupro_get_course_lessons_count( $course_id ) : 0;
			$completed_all = get_user_meta( $user_id, '_edupro_completed_lessons', true );
			$completed_all = is_array( $completed_all ) ? array_map( 'absint', $completed_all ) : array();

			$lesson_ids = get_posts(
				array(
					'post_type'      => 'lms_lesson',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => '_edupro_course_id',
					'meta_value'     => $course_id,
				)
			);
			$total_lessons = count( $lesson_ids );
			$done_count    = count( array_intersect( $lesson_ids, $completed_all ) );
			$progress      = $total_lessons > 0 ? (int) round( ( $done_count / $total_lessons ) * 100 ) : 100;
		}

		// Issue certificate timestamp & unique code when course hits 100%.
		if ( $progress >= 100 && ! get_user_meta( $user_id, '_edupro_certificate_' . $course_id . '_code', true ) ) {
			$cert_code = 'CERT-' . strtoupper( substr( md5( $user_id . '-' . $course_id . '-' . wp_salt() ), 0, 10 ) );
			update_user_meta( $user_id, '_edupro_certificate_' . $course_id . '_code', $cert_code );
			update_user_meta( $user_id, '_edupro_certificate_' . $course_id . '_date', current_time( 'mysql' ) );
		}
	}

	// Determine next lesson URL.
	$next_lesson_id  = 0;
	$next_lesson_url = '';
	if ( $course_id && function_exists( 'edupro_get_next_lesson' ) ) {
		$next_lesson_id = (int) edupro_get_next_lesson( $course_id, $lesson_id, $user_id );
	} elseif ( $course_id ) {
		$ordered = get_posts(
			array(
				'post_type'      => 'lms_lesson',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_edupro_course_id',
				'meta_value'     => $course_id,
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
			)
		);
		$pos = array_search( $lesson_id, $ordered, true );
		if ( false !== $pos && isset( $ordered[ $pos + 1 ] ) ) {
			$next_lesson_id = (int) $ordered[ $pos + 1 ];
		}
	}

	if ( $next_lesson_id ) {
		$next_lesson_url = get_permalink( $next_lesson_id );
	}

	wp_send_json_success(
		array(
			'message'          => __( 'Lesson marked as complete!', 'edupro' ),
			'lesson_id'        => $lesson_id,
			'course_id'        => $course_id,
			'progress'         => $progress,
			'course_completed' => ( $progress >= 100 ),
			'next_lesson_id'   => $next_lesson_id,
			'next_lesson_url'  => $next_lesson_url,
		)
	);
}
add_action( 'wp_ajax_edupro_mark_lesson_complete', 'edupro_ajax_mark_lesson_complete' );
add_action( 'wp_ajax_nopriv_edupro_mark_lesson_complete', 'edupro_ajax_mark_lesson_complete' );

// =============================================================================
// 6. AJAX: LIVE COURSE SEARCH (edupro_live_search)
// =============================================================================

/**
 * Live search courses for the header/hero search dropdown.
 */
function edupro_ajax_live_search() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	$query_str = '';
	if ( isset( $_POST['q'] ) ) {
		$query_str = sanitize_text_field( wp_unslash( $_POST['q'] ) );
	} elseif ( isset( $_POST['query'] ) ) {
		$query_str = sanitize_text_field( wp_unslash( $_POST['query'] ) );
	} elseif ( isset( $_GET['q'] ) ) {
		$query_str = sanitize_text_field( wp_unslash( $_GET['q'] ) );
	}

	$limit = isset( $_POST['limit'] ) ? min( 12, max( 1, absint( $_POST['limit'] ) ) ) : 6;

	if ( mb_strlen( $query_str ) < 2 ) {
		wp_send_json_success( array( 'courses' => array(), 'total' => 0 ) );
	}

	$search_query = new WP_Query(
		array(
			'post_type'      => 'lms_course',
			'post_status'    => 'publish',
			's'              => $query_str,
			'posts_per_page' => $limit,
		)
	);

	$results = array();
	if ( $search_query->have_posts() ) {
		while ( $search_query->have_posts() ) {
			$search_query->the_post();
			$cid     = get_the_ID();
			$pricing = edupro_get_course_price_info( $cid );
			$terms   = get_the_terms( $cid, 'course_category' );
			$cat     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
			$rating  = function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $cid ) : 0;
			$dur     = absint( get_post_meta( $cid, '_edupro_duration', true ) );
			$thumb   = get_the_post_thumbnail_url( $cid, 'course-card' );

			$price_label = $pricing['is_free']
				? __( 'Free', 'edupro' )
				: ( function_exists( 'edupro_format_price' ) ? edupro_format_price( $pricing['final'] ) : '₹' . number_format( $pricing['final'], 2 ) );

			$results[] = array(
				'id'         => $cid,
				'title'      => get_the_title( $cid ),
				'url'        => get_permalink( $cid ),
				'thumbnail'  => $thumb ? $thumb : '',
				'category'   => $cat,
				'rating'     => $rating,
				'price'      => $pricing['final'],
				'price_html' => $price_label,
				'is_free'    => $pricing['is_free'],
				'duration'   => ( $dur && function_exists( 'edupro_get_duration_label' ) ) ? edupro_get_duration_label( $dur ) : '',
				'instructor' => get_the_author(),
			);
		}
		wp_reset_postdata();
	}

	wp_send_json_success(
		array(
			'courses'    => $results,
			'total'      => (int) $search_query->found_posts,
			'search_url' => add_query_arg(
				array(
					's'         => rawurlencode( $query_str ),
					'post_type' => 'lms_course',
				),
				home_url( '/' )
			),
		)
	);
}
add_action( 'wp_ajax_edupro_live_search', 'edupro_ajax_live_search' );
add_action( 'wp_ajax_nopriv_edupro_live_search', 'edupro_ajax_live_search' );

// =============================================================================
// 7. AJAX: LOAD MORE / FILTER COURSES (edupro_load_more_courses)
// =============================================================================

/**
 * Paginated AJAX course loader with filtering and sorting support.
 */
function edupro_ajax_load_more_courses() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : ( isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1 );
	$per_page = isset( $_POST['per_page'] ) ? min( 24, max( 1, absint( $_POST['per_page'] ) ) ) : 8;
	$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$level    = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
	$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
	$price    = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '';
	$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$sort     = isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : 'newest';

	$query_args = array(
		'post_type'      => 'lms_course',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
	);

	if ( ! empty( $search ) ) {
		$query_args['s'] = $search;
	}

	$tax_query = array();
	if ( ! empty( $category ) ) {
		$tax_query[] = array(
			'taxonomy' => 'course_category',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', explode( ',', $category ) ),
		);
	}
	if ( ! empty( $level ) ) {
		$tax_query[] = array(
			'taxonomy' => 'course_level',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', explode( ',', $level ) ),
		);
	}
	if ( ! empty( $language ) ) {
		$tax_query[] = array(
			'taxonomy' => 'course_language',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', explode( ',', $language ) ),
		);
	}
	if ( ! empty( $tax_query ) ) {
		$tax_query['relation']   = 'AND';
		$query_args['tax_query'] = $tax_query;
	}

	$meta_query = array();
	if ( 'free' === $price ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'   => '_edupro_is_free',
				'value' => '1',
			),
			array(
				'key'     => '_edupro_regular_price',
				'value'   => 0,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		);
	} elseif ( 'paid' === $price ) {
		$meta_query[] = array(
			'key'     => '_edupro_regular_price',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
	}

	switch ( $sort ) {
		case 'price_low':
			$query_args['meta_key'] = '_edupro_regular_price';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'ASC';
			break;
		case 'price_high':
			$query_args['meta_key'] = '_edupro_regular_price';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
			break;
		case 'popular':
			$query_args['orderby'] = 'comment_count date';
			$query_args['order']   = 'DESC';
			break;
		case 'title':
			$query_args['orderby'] = 'title';
			$query_args['order']   = 'ASC';
			break;
		default:
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
			break;
	}

	if ( ! empty( $meta_query ) ) {
		$query_args['meta_query'] = $meta_query;
	}

	$courses_query = new WP_Query( $query_args );
	$has_template  = locate_template( 'template-parts/course-card.php' );

	ob_start();
	if ( $courses_query->have_posts() ) {
		while ( $courses_query->have_posts() ) {
			$courses_query->the_post();
			if ( $has_template ) {
				get_template_part( 'template-parts/course-card' );
			} elseif ( function_exists( 'edupro_render_fallback_course_card' ) ) {
				echo edupro_render_fallback_course_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		wp_reset_postdata();
	}
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'        => $html,
			'paged'       => $paged,
			'max_pages'   => (int) $courses_query->max_num_pages,
			'found_posts' => (int) $courses_query->found_posts,
			'has_more'    => ( $paged < (int) $courses_query->max_num_pages ),
		)
	);
}
add_action( 'wp_ajax_edupro_load_more_courses', 'edupro_ajax_load_more_courses' );
add_action( 'wp_ajax_nopriv_edupro_load_more_courses', 'edupro_ajax_load_more_courses' );

// =============================================================================
// 8. AJAX: SUBMIT COURSE REVIEW (edupro_submit_review)
// =============================================================================

/**
 * Submit a student review for a course.
 * Stores inside `course_reviews` post meta (used by edupro_get_course_rating())
 * and also records an `lms_review` CPT entry.
 */
function edupro_ajax_submit_review() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Please log in to submit a review.', 'edupro' ) ), 401 );
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$rating    = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : 0;
	$comment   = isset( $_POST['review'] )
		? sanitize_textarea_field( wp_unslash( $_POST['review'] ) )
		: ( isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '' );
	$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

	if ( ! $course_id || 'lms_course' !== get_post_type( $course_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid course.', 'edupro' ) ) );
	}

	if ( $rating < 1 || $rating > 5 ) {
		wp_send_json_error( array( 'message' => __( 'Please choose a star rating between 1 and 5.', 'edupro' ) ) );
	}

	if ( mb_strlen( trim( $comment ) ) < 5 ) {
		wp_send_json_error( array( 'message' => __( 'Please write a short review (at least 5 characters).', 'edupro' ) ) );
	}

	$user_id = get_current_user_id();
	$user    = wp_get_current_user();

	$reviews = get_post_meta( $course_id, 'course_reviews', true );
	if ( ! is_array( $reviews ) ) {
		$reviews = array();
	}

	$review_entry = array(
		'user_id' => $user_id,
		'author'  => $user->display_name,
		'email'   => $user->user_email,
		'avatar'  => get_avatar_url( $user_id, array( 'size' => 64 ) ),
		'rating'  => $rating,
		'title'   => $title,
		'comment' => $comment,
		'date'    => current_time( 'mysql' ),
	);

	// Update existing review by this user or prepend new one.
	$updated = false;
	foreach ( $reviews as $idx => $existing ) {
		if ( isset( $existing['user_id'] ) && (int) $existing['user_id'] === $user_id ) {
			$reviews[ $idx ] = $review_entry;
			$updated         = true;
			break;
		}
	}
	if ( ! $updated ) {
		array_unshift( $reviews, $review_entry );
	}

	update_post_meta( $course_id, 'course_reviews', $reviews );

	// Also create/record lms_review post if CPT is registered.
	if ( post_type_exists( 'lms_review' ) && ! $updated ) {
		$review_post_id = wp_insert_post(
			array(
				'post_type'    => 'lms_review',
				'post_status'  => 'publish',
				'post_title'   => $title ? $title : sprintf( __( 'Review by %s', 'edupro' ), $user->display_name ),
				'post_content' => $comment,
				'post_author'  => $user_id,
			)
		);
		if ( $review_post_id && ! is_wp_error( $review_post_id ) ) {
			update_post_meta( $review_post_id, '_edupro_course_id', $course_id );
			update_post_meta( $review_post_id, '_edupro_rating', $rating );
		}
	}

	$avg_rating = function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $course_id ) : (float) $rating;
	$stars_html = function_exists( 'edupro_get_star_rating_html' ) ? edupro_get_star_rating_html( $rating ) : str_repeat( '★', $rating );

	$card_html = sprintf(
		'<div class="edupro-course-review-item">'
		. '<div class="edupro-course-review-head">'
		. '<img src="%1$s" alt="%2$s" class="edupro-course-review-avatar" width="44" height="44">'
		. '<div><strong>%2$s</strong><div class="edupro-course-review-stars">%3$s <span>%4$s</span></div></div>'
		. '</div>'
		. '%5$s'
		. '<p class="edupro-course-review-text">%6$s</p>'
		. '</div>',
		esc_url( $review_entry['avatar'] ),
		esc_html( $review_entry['author'] ),
		$stars_html,
		esc_html__( 'Just now', 'edupro' ),
		$title ? '<h5 class="edupro-course-review-title">' . esc_html( $title ) . '</h5>' : '',
		esc_html( $comment )
	);

	wp_send_json_success(
		array(
			'message'      => $updated ? __( 'Your review has been updated!', 'edupro' ) : __( 'Thank you! Your review has been published.', 'edupro' ),
			'average'      => $avg_rating,
			'review_count' => count( $reviews ),
			'html'         => $card_html,
		)
	);
}
add_action( 'wp_ajax_edupro_submit_review', 'edupro_ajax_submit_review' );
add_action( 'wp_ajax_nopriv_edupro_submit_review', 'edupro_ajax_submit_review' );

// =============================================================================
// 9. AJAX: UPDATE PROFILE & AVATAR UPLOAD (edupro_update_profile)
// =============================================================================

/**
 * Update student profile: name, email, bio, password, and optional custom avatar upload.
 */
function edupro_ajax_update_profile() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in to update your profile.', 'edupro' ) ), 401 );
	}

	$user_id    = get_current_user_id();
	$user       = get_userdata( $user_id );
	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : $user->first_name;
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : $user->last_name;
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : $user->user_email;
	$bio        = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : $user->description;
	$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

	$update_data = array(
		'ID'           => $user_id,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => trim( $first_name . ' ' . $last_name ) ? trim( $first_name . ' ' . $last_name ) : $user->display_name,
		'description'  => $bio,
	);

	// Validate email change.
	if ( ! empty( $email ) && strtolower( $email ) !== strtolower( $user->user_email ) ) {
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'edupro' ) ) );
		}
		$existing_id = email_exists( $email );
		if ( $existing_id && (int) $existing_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'This email address is already in use by another account.', 'edupro' ) ) );
		}
		$update_data['user_email'] = $email;
	}

	// Validate password change if provided.
	$current_password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
	$new_password     = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
	$confirm_password = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

	if ( ! empty( $new_password ) || ! empty( $current_password ) ) {
		if ( empty( $current_password ) || ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Your current password is incorrect.', 'edupro' ) ) );
		}
		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'New password must be at least 8 characters long.', 'edupro' ) ) );
		}
		if ( ! empty( $confirm_password ) && $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'New password and confirmation do not match.', 'edupro' ) ) );
		}
		$update_data['user_pass'] = $new_password;
	}

	// Handle custom avatar upload if included in multipart request.
	$avatar_url = '';
	if ( ! empty( $_FILES['avatar']['name'] ) ) {
		$file      = $_FILES['avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$max_bytes = 2 * 1024 * 1024; // 2MB limit.

		if ( isset( $file['size'] ) && $file['size'] > $max_bytes ) {
			wp_send_json_error( array( 'message' => __( 'Avatar image must be smaller than 2 MB.', 'edupro' ) ) );
		}

		$allowed_types = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'gif'          => 'image/gif',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_types,
			)
		);

		if ( isset( $uploaded['error'] ) ) {
			wp_send_json_error( array( 'message' => $uploaded['error'] ) );
		}

		if ( ! empty( $uploaded['url'] ) ) {
			$attachment = array(
				'post_mime_type' => $uploaded['type'],
				'post_title'     => sprintf( 'Avatar — %s', $user->user_login ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			);
			$attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );
			if ( ! is_wp_error( $attach_id ) ) {
				$attach_meta = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_meta );
				update_user_meta( $user_id, '_edupro_custom_avatar_id', $attach_id );
			}
			update_user_meta( $user_id, '_edupro_custom_avatar_url', esc_url_raw( $uploaded['url'] ) );
			$avatar_url = esc_url_raw( $uploaded['url'] );
		}
	}

	$result = wp_update_user( $update_data );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	if ( isset( $_POST['phone'] ) ) {
		update_user_meta( $user_id, '_edupro_phone', $phone );
	}

	// Re-authenticate cookie if password changed so user stays logged in.
	if ( ! empty( $update_data['user_pass'] ) ) {
		wp_set_auth_cookie( $user_id, true );
	}

	wp_send_json_success(
		array(
			'message'      => __( 'Profile updated successfully!', 'edupro' ),
			'display_name' => $update_data['display_name'],
			'avatar_url'   => $avatar_url ? $avatar_url : get_avatar_url( $user_id, array( 'size' => 120 ) ),
		)
	);
}
add_action( 'wp_ajax_edupro_update_profile', 'edupro_ajax_update_profile', 5 );
add_action( 'wp_ajax_nopriv_edupro_update_profile', 'edupro_ajax_update_profile', 5 );

/**
 * Filter get_avatar_url to serve the student's uploaded custom avatar everywhere in WP.
 *
 * @param string $url         Default avatar URL.
 * @param mixed  $id_or_email User ID, WP_User, or email.
 * @param array  $args        Avatar args.
 * @return string
 */
function edupro_custom_avatar_url_filter( $url, $id_or_email, $args ) {
	$user_id = 0;

	if ( is_numeric( $id_or_email ) ) {
		$user_id = absint( $id_or_email );
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = (int) $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_id = (int) $id_or_email->post_author;
	} elseif ( $id_or_email instanceof WP_Comment && ! empty( $id_or_email->user_id ) ) {
		$user_id = (int) $id_or_email->user_id;
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$u = get_user_by( 'email', $id_or_email );
		if ( $u ) {
			$user_id = (int) $u->ID;
		}
	}

	if ( $user_id ) {
		$custom_url = get_user_meta( $user_id, '_edupro_custom_avatar_url', true );
		if ( ! empty( $custom_url ) ) {
			return esc_url( $custom_url );
		}
	}

	return $url;
}
add_filter( 'get_avatar_url', 'edupro_custom_avatar_url_filter', 10, 3 );

// =============================================================================
// 10. AJAX: UPDATE DASHBOARD SETTINGS (edupro_update_settings)
// =============================================================================

/**
 * Save notification preferences and language preference from Student Dashboard.
 */
function edupro_ajax_update_settings() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ), 403 );
	}

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'edupro' ) ), 401 );
	}

	$user_id  = get_current_user_id();
	$settings = array(
		'notify_course_updates' => ! empty( $_POST['notify_course_updates'] ) ? '1' : '0',
		'notify_new_lessons'    => ! empty( $_POST['notify_new_lessons'] ) ? '1' : '0',
		'notify_certificates'   => ! empty( $_POST['notify_certificates'] ) ? '1' : '0',
		'notify_promotions'     => ! empty( $_POST['notify_promotions'] ) ? '1' : '0',
		'language'              => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en',
		'timezone'              => isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : 'Asia/Kolkata',
	);

	update_user_meta( $user_id, '_edupro_student_settings', $settings );

	wp_send_json_success(
		array(
			'message'  => __( 'Your preferences have been saved.', 'edupro' ),
			'settings' => $settings,
		)
	);
}
add_action( 'wp_ajax_edupro_update_settings', 'edupro_ajax_update_settings' );
add_action( 'wp_ajax_nopriv_edupro_update_settings', 'edupro_ajax_update_settings' );

// =============================================================================
// 11. AJAX: CONTACT FORM SUBMISSION (edupro_submit_contact_form)
// =============================================================================

/**
 * Handle AJAX submission from the Contact Us page template.
 * Verifies `edupro_nonce`, checks anti-spam honeypot, validates inputs,
 * and emails Website Settings support email (with Reply-To header).
 */
function edupro_ajax_submit_contact_form() {
	if ( ! edupro_verify_ajax_nonce() ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'edupro' ) ), 403 );
	}

	// Honeypot check: silently succeed so bots don't retry.
	$honeypot = isset( $_POST['website_url'] ) ? trim( (string) wp_unslash( $_POST['website_url'] ) ) : '';
	if ( '' !== $honeypot ) {
		wp_send_json_success(
			array(
				'message' => __( 'Thank you! Your message has been sent. Our team will get back to you shortly.', 'edupro' ),
			)
		);
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$topic   = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : __( 'General Enquiry', 'edupro' );
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => __( 'Please enter your full name.', 'edupro' ) ) );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'edupro' ) ) );
	}

	if ( mb_strlen( trim( $message ) ) < 10 ) {
		wp_send_json_error( array( 'message' => __( 'Please write a message of at least 10 characters.', 'edupro' ) ) );
	}

	$site_name = function_exists( 'edupro_get_site_setting' )
		? (string) edupro_get_site_setting( 'site_name', get_bloginfo( 'name' ) )
		: get_bloginfo( 'name' );
	if ( '' === trim( $site_name ) ) {
		$site_name = get_bloginfo( 'name' );
	}

	$recipient = function_exists( 'edupro_get_site_setting' )
		? (string) edupro_get_site_setting( 'email_support', get_option( 'admin_email' ) )
		: (string) get_option( 'admin_email' );
	if ( ! is_email( $recipient ) ) {
		$recipient = (string) get_option( 'admin_email' );
	}

	$subject = sprintf(
		/* translators: 1: site name, 2: inquiry topic, 3: sender name */
		__( '[%1$s Contact] %2$s — %3$s', 'edupro' ),
		$site_name,
		$topic,
		$name
	);

	$body_lines = array(
		sprintf( __( 'New contact enquiry received on %s:', 'edupro' ), $site_name ),
		'',
		sprintf( __( 'Name: %s', 'edupro' ), $name ),
		sprintf( __( 'Email: %s', 'edupro' ), $email ),
		sprintf( __( 'Phone / WhatsApp: %s', 'edupro' ), $phone ? $phone : __( 'Not provided', 'edupro' ) ),
		sprintf( __( 'Topic: %s', 'edupro' ), $topic ),
		'',
		__( 'Message:', 'edupro' ),
		$message,
		'',
		sprintf( __( 'Submitted: %s', 'edupro' ), current_time( 'mysql' ) ),
	);

	$safe_header_name = str_replace( array( "\r", "\n" ), '', $name );
	$headers          = array(
		'Content-Type: text/plain; charset=UTF-8',
		sprintf( 'Reply-To: %s <%s>', $safe_header_name, $email ),
	);

	$sent = wp_mail( $recipient, $subject, implode( "\n", $body_lines ), $headers );

	if ( ! $sent ) {
		wp_send_json_error(
			array(
				'message' => __( 'We could not deliver your message right now. Please email or WhatsApp us directly.', 'edupro' ),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Thank you! Your message has been received and our team will respond shortly.', 'edupro' ),
		)
	);
}
add_action( 'wp_ajax_edupro_submit_contact_form', 'edupro_ajax_submit_contact_form' );
add_action( 'wp_ajax_nopriv_edupro_submit_contact_form', 'edupro_ajax_submit_contact_form' );
