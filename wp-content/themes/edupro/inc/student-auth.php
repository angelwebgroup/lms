<?php
/**
 * EduPro Student Authentication System
 *
 * Handles custom login, registration, dashboard page creation,
 * AJAX auth handlers, student role, access control, and content protection.
 *
 * @package EduPro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. REGISTER STUDENT ROLE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register the 'edupro_student' WordPress role.
 * Called on after_switch_theme and also on init so the role always exists.
 */
function edupro_add_student_role() {
    if ( ! get_role( 'edupro_student' ) ) {
        add_role(
            'edupro_student',
            __( 'Student', 'edupro' ),
            array(
                'read'             => true,
                'view_lms_content' => true,
            )
        );
    }
}
add_action( 'after_switch_theme', 'edupro_add_student_role' );
add_action( 'init', 'edupro_add_student_role' );

// ─────────────────────────────────────────────────────────────────────────────
// 2. CREATE PAGES ON THEME ACTIVATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create the Login, Register, and Dashboard pages on theme activation.
 * Sets the correct page template for each page.
 */
function edupro_create_pages_on_activate() {

    $pages = array(
        array(
            'title'    => __( 'Student Login', 'edupro' ),
            'slug'     => 'student-login',
            'template' => 'templates/template-login.php',
            'option'   => 'edupro_login_page_id',
        ),
        array(
            'title'    => __( 'Student Register', 'edupro' ),
            'slug'     => 'student-register',
            'template' => 'templates/template-register.php',
            'option'   => 'edupro_register_page_id',
        ),
        array(
            'title'    => __( 'Dashboard', 'edupro' ),
            'slug'     => 'dashboard',
            'template' => 'templates/template-dashboard.php',
            'option'   => 'edupro_dashboard_page_id',
        ),
    );

    foreach ( $pages as $page ) {
        // Check if a page with this slug already exists.
        $existing = get_page_by_path( $page['slug'] );

        if ( $existing ) {
            // Update the template if page already exists.
            update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
            update_option( $page['option'], $existing->ID );
        } else {
            $page_id = wp_insert_post(
                array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '',
                    'post_author'  => 1,
                )
            );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_post_meta( $page_id, '_wp_page_template', $page['template'] );
                update_option( $page['option'], $page_id );
            }
        }
    }

    // Also ensure the student role exists.
    edupro_add_student_role();
}
add_action( 'after_switch_theme', 'edupro_create_pages_on_activate' );

// ─────────────────────────────────────────────────────────────────────────────
// 3. REDIRECT LOGGED-IN USERS AWAY FROM AUTH PAGES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Redirect already-logged-in users away from the login/register pages
 * to their student dashboard.
 */
function edupro_redirect_after_login() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $login_page_id    = (int) get_option( 'edupro_login_page_id' );
    $register_page_id = (int) get_option( 'edupro_register_page_id' );

    if ( is_page( $login_page_id ) || is_page( $register_page_id ) ) {
        $dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
        $redirect_url      = $dashboard_page_id
            ? get_permalink( $dashboard_page_id )
            : home_url( '/dashboard/' );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
add_action( 'template_redirect', 'edupro_redirect_after_login' );

// ─────────────────────────────────────────────────────────────────────────────
// 4. AJAX LOGIN HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler for student login.
 * Action: edupro_login (both logged-in and logged-out users).
 */
function edupro_student_login_handler() {
    // Verify nonce (supports both 'nonce' and 'security' parameter names).
    $nonce_key = isset( $_POST['nonce'] ) ? 'nonce' : 'security';
    if ( ! check_ajax_referer( 'edupro_nonce', $nonce_key, false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ) );
    }

    $raw_login = '';
    if ( isset( $_POST['username'] ) ) {
        $raw_login = sanitize_text_field( wp_unslash( $_POST['username'] ) );
    } elseif ( isset( $_POST['user_login'] ) ) {
        $raw_login = sanitize_text_field( wp_unslash( $_POST['user_login'] ) );
    } elseif ( isset( $_POST['email'] ) ) {
        $raw_login = sanitize_text_field( wp_unslash( $_POST['email'] ) );
    }

    $password = '';
    if ( isset( $_POST['password'] ) ) {
        $password = wp_unslash( $_POST['password'] );
    } elseif ( isset( $_POST['user_pass'] ) ) {
        $password = wp_unslash( $_POST['user_pass'] );
    }

    $remember = isset( $_POST['remember'] ) && in_array( (string) $_POST['remember'], array( 'true', '1', 'on', 'yes' ), true );

    if ( empty( $raw_login ) || empty( $password ) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter your email/username and password.', 'edupro' ) ) );
    }

    // Resolve email to username if an email address was provided.
    $login_identifier = $raw_login;
    if ( is_email( $raw_login ) ) {
        $user_by_email = get_user_by( 'email', sanitize_email( $raw_login ) );
        if ( $user_by_email ) {
            $login_identifier = $user_by_email->user_login;
        }
    } else {
        $login_identifier = sanitize_user( $raw_login );
    }

    $user = wp_authenticate( $login_identifier, $password );

    if ( is_wp_error( $user ) ) {
        $error_code    = $user->get_error_code();
        $error_message = __( 'Invalid username/email or password.', 'edupro' );

        if ( 'too_many_retries' === $error_code ) {
            $error_message = __( 'Too many failed login attempts. Please try again later.', 'edupro' );
        }

        wp_send_json_error( array( 'message' => $error_message ) );
    }

    // Set auth cookie.
    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, $remember );

    $dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
    $redirect_url      = $dashboard_page_id
        ? get_permalink( $dashboard_page_id )
        : home_url( '/dashboard/' );

    // Allow overriding the redirect URL (validated to local/safe URLs).
    if ( isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] ) ) {
        $candidate    = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
        $redirect_url = wp_validate_redirect( $candidate, $redirect_url );
    }

    /**
     * Fires after a student has successfully logged in via AJAX.
     *
     * @param int    $user_id      The logged-in user ID.
     * @param string $redirect_url The URL to redirect to.
     */
    do_action( 'edupro_student_login', $user->ID, $redirect_url );

    wp_send_json_success(
        array(
            'message'      => __( 'Login successful! Redirecting…', 'edupro' ),
            'redirect_url' => $redirect_url,
            'user_id'      => $user->ID,
        )
    );
}
// Note: wp_ajax_(nopriv_)edupro_login is hooked centrally in inc/ajax-handlers.php.

// ─────────────────────────────────────────────────────────────────────────────
// 5. AJAX REGISTRATION HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler for student registration.
 * Action: edupro_register (logged-out users).
 */
function edupro_student_register_handler() {
    // Verify nonce (supports both 'nonce' and 'security' parameter names).
    $nonce_key = isset( $_POST['nonce'] ) ? 'nonce' : 'security';
    if ( ! check_ajax_referer( 'edupro_nonce', $nonce_key, false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'edupro' ) ) );
    }

    // Allow theme/site filter to disable registration if desired.
    if ( ! apply_filters( 'edupro_allow_student_registration', true ) ) {
        wp_send_json_error( array( 'message' => __( 'Student registration is currently disabled.', 'edupro' ) ) );
    }

    // Gather and sanitize inputs.
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $username   = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';

    $email = '';
    if ( isset( $_POST['email'] ) ) {
        $email = sanitize_email( wp_unslash( $_POST['email'] ) );
    } elseif ( isset( $_POST['user_email'] ) ) {
        $email = sanitize_email( wp_unslash( $_POST['user_email'] ) );
    }

    $password = '';
    if ( isset( $_POST['password'] ) ) {
        $password = wp_unslash( $_POST['password'] );
    } elseif ( isset( $_POST['user_pass'] ) ) {
        $password = wp_unslash( $_POST['user_pass'] );
    }

    $has_confirm = isset( $_POST['confirm_password'] ) || isset( $_POST['password_confirm'] );
    $confirm     = '';
    if ( isset( $_POST['confirm_password'] ) ) {
        $confirm = wp_unslash( $_POST['confirm_password'] );
    } elseif ( isset( $_POST['password_confirm'] ) ) {
        $confirm = wp_unslash( $_POST['password_confirm'] );
    }

    // Validation.
    if ( empty( $first_name ) || empty( $last_name ) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter your first and last name.', 'edupro' ) ) );
    }

    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'edupro' ) ) );
    }

    if ( email_exists( $email ) ) {
        wp_send_json_error( array( 'message' => __( 'An account with that email already exists. Please log in.', 'edupro' ) ) );
    }

    // Auto-generate a unique username from the email if no username field was provided.
    if ( empty( $username ) ) {
        $email_parts = explode( '@', $email );
        $base_user   = sanitize_user( preg_replace( '/[^a-zA-Z0-9_]/', '_', $email_parts[0] ), true );
        if ( empty( $base_user ) ) {
            $base_user = 'student';
        }
        $username = $base_user;
        $suffix   = 1;
        while ( username_exists( $username ) ) {
            $username = $base_user . $suffix;
            $suffix++;
        }
    } else {
        if ( ! validate_username( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid username. Use only letters, numbers, and underscores.', 'edupro' ) ) );
        }
        if ( username_exists( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'That username is already taken. Please choose another.', 'edupro' ) ) );
        }
    }

    if ( strlen( $password ) < 8 ) {
        wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters long.', 'edupro' ) ) );
    }

    if ( $has_confirm && $password !== $confirm ) {
        wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'edupro' ) ) );
    }

    // Ensure the student role exists before assigning it.
    edupro_add_student_role();

    // Create the user.
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
    }

    // Set user data and role.
    wp_update_user(
        array(
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( $first_name . ' ' . $last_name ),
            'role'         => 'edupro_student',
        )
    );

    // Initialize student user meta.
    update_user_meta( $user_id, '_edupro_registered_date', current_time( 'mysql' ) );
    update_user_meta( $user_id, '_edupro_enrolled_courses', array() );

    // Send notification email to admin.
    wp_new_user_notification( $user_id, null, 'admin' );

    // Auto-login after registration.
    wp_clear_auth_cookie();
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, false );

    $dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
    $redirect_url      = $dashboard_page_id
        ? get_permalink( $dashboard_page_id )
        : home_url( '/dashboard/' );

    if ( isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] ) ) {
        $candidate    = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
        $redirect_url = wp_validate_redirect( $candidate, $redirect_url );
    }

    /**
     * Fires after a student has successfully registered via AJAX.
     *
     * @param int $user_id The newly registered user ID.
     */
    do_action( 'edupro_student_registered', $user_id );

    wp_send_json_success(
        array(
            'message'      => __( 'Account created! Welcome to EduPro. Redirecting…', 'edupro' ),
            'redirect_url' => $redirect_url,
            'user_id'      => $user_id,
        )
    );
}
// Note: wp_ajax_(nopriv_)edupro_register is hooked centrally in inc/ajax-handlers.php.

// ─────────────────────────────────────────────────────────────────────────────
// 6. LOGOUT HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Handle logout via GET parameter: ?edupro_logout=1&nonce=<nonce>.
 * Redirects to the login page after logging out.
 */
function edupro_logout_handler() {
    if ( ! isset( $_GET['edupro_logout'] ) || '1' !== $_GET['edupro_logout'] ) {
        return;
    }

    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'edupro_logout' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'edupro' ) );
    }

    wp_logout();

    $login_page_id = (int) get_option( 'edupro_login_page_id' );
    $redirect_url  = $login_page_id
        ? get_permalink( $login_page_id )
        : home_url( '/student-login/' );

    wp_safe_redirect( add_query_arg( 'logged_out', '1', $redirect_url ) );
    exit;
}
add_action( 'template_redirect', 'edupro_logout_handler' );

/**
 * Helper: Return a logout URL with nonce.
 *
 * @return string
 */
function edupro_get_logout_url() {
    return add_query_arg(
        array(
            'edupro_logout' => '1',
            'nonce'         => wp_create_nonce( 'edupro_logout' ),
        ),
        home_url( '/' )
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. FORGOT PASSWORD HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler for forgot-password requests.
 * Sends a password-reset link to the user's registered email.
 */
function edupro_forgot_password_handler() {
    $nonce_key = isset( $_POST['nonce'] ) ? 'nonce' : 'security';
    if ( ! check_ajax_referer( 'edupro_nonce', $nonce_key, false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ) );
    }

    $email_or_user = '';
    if ( isset( $_POST['email'] ) ) {
        $email_or_user = sanitize_text_field( wp_unslash( $_POST['email'] ) );
    } elseif ( isset( $_POST['user_login'] ) ) {
        $email_or_user = sanitize_text_field( wp_unslash( $_POST['user_login'] ) );
    }

    if ( empty( $email_or_user ) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter your email address or username.', 'edupro' ) ) );
    }

    // Retrieve user object.
    if ( is_email( $email_or_user ) ) {
        $user = get_user_by( 'email', $email_or_user );
    } else {
        $user = get_user_by( 'login', $email_or_user );
    }

    // Always return a "success" message to prevent user enumeration.
    if ( ! $user ) {
        wp_send_json_success(
            array( 'message' => __( 'If an account exists for that email, a reset link has been sent.', 'edupro' ) )
        );
    }

    // Generate the reset key.
    $reset_key = get_password_reset_key( $user );

    if ( is_wp_error( $reset_key ) ) {
        wp_send_json_error( array( 'message' => __( 'Could not generate a reset link. Please try again.', 'edupro' ) ) );
    }

    $login_page_id = (int) get_option( 'edupro_login_page_id' );
    $login_url     = $login_page_id
        ? get_permalink( $login_page_id )
        : home_url( '/student-login/' );

    $reset_url = add_query_arg(
        array(
            'action' => 'edupro_reset_password',
            'key'    => rawurlencode( $reset_key ),
            'login'  => rawurlencode( $user->user_login ),
        ),
        $login_url
    );

    $blog_name = get_bloginfo( 'name' );
    $subject   = sprintf( __( '[%s] Password Reset Request', 'edupro' ), $blog_name );

    $message  = sprintf( __( 'Hi %s,', 'edupro' ), $user->display_name ) . "\n\n";
    $message .= __( 'You requested a password reset for your EduPro account.', 'edupro' ) . "\n\n";
    $message .= __( 'Click the link below to reset your password:', 'edupro' ) . "\n\n";
    $message .= $reset_url . "\n\n";
    $message .= __( 'This link will expire in 24 hours.', 'edupro' ) . "\n\n";
    $message .= __( 'If you did not request a password reset, please ignore this email.', 'edupro' ) . "\n\n";
    $message .= sprintf( __( 'Thanks,\nThe %s Team', 'edupro' ), $blog_name );

    $sent = wp_mail( $user->user_email, $subject, $message );

    if ( ! $sent ) {
        wp_send_json_error( array( 'message' => __( 'Failed to send the reset email. Please try again.', 'edupro' ) ) );
    }

    wp_send_json_success(
        array( 'message' => __( 'If an account exists for that email, a reset link has been sent.', 'edupro' ) )
    );
}
add_action( 'wp_ajax_nopriv_edupro_forgot_password', 'edupro_forgot_password_handler' );
add_action( 'wp_ajax_edupro_forgot_password', 'edupro_forgot_password_handler' );

// ─────────────────────────────────────────────────────────────────────────────
// 8. PASSWORD RESET PAGE HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Handle the password-reset form submission from the custom login template.
 * Triggered when ?action=edupro_reset_password is present.
 */
function edupro_process_password_reset() {
    if ( ! isset( $_GET['action'] ) || 'edupro_reset_password' !== $_GET['action'] ) {
        return;
    }

    // Store key/login in session-like transients for the template to use.
    $key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
    $login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

    if ( empty( $key ) || empty( $login ) ) {
        return;
    }

    // Validate the key early so the template can show an error immediately.
    $user = check_password_reset_key( $key, $login );

    if ( is_wp_error( $user ) ) {
        // Store the error so the template can display it.
        set_transient( 'edupro_reset_error_' . md5( $login ), $user->get_error_message(), 60 );
    }

    // Handle form submission.
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['edupro_new_password'] ) ) {
        if ( ! isset( $_POST['edupro_reset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_reset_nonce'] ) ), 'edupro_reset_password' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'edupro' ) );
        }

        $new_password     = wp_unslash( $_POST['edupro_new_password'] );
        $confirm_password = isset( $_POST['edupro_confirm_password'] ) ? wp_unslash( $_POST['edupro_confirm_password'] ) : '';

        if ( strlen( $new_password ) < 8 ) {
            set_transient( 'edupro_reset_error_' . md5( $login ), __( 'Password must be at least 8 characters.', 'edupro' ), 60 );
            return;
        }

        if ( $new_password !== $confirm_password ) {
            set_transient( 'edupro_reset_error_' . md5( $login ), __( 'Passwords do not match.', 'edupro' ), 60 );
            return;
        }

        $valid_user = check_password_reset_key( $key, $login );

        if ( is_wp_error( $valid_user ) ) {
            set_transient( 'edupro_reset_error_' . md5( $login ), __( 'The reset link is invalid or has expired.', 'edupro' ), 60 );
            return;
        }

        reset_password( $valid_user, $new_password );

        $login_page_id = (int) get_option( 'edupro_login_page_id' );
        $login_url     = $login_page_id
            ? get_permalink( $login_page_id )
            : home_url( '/student-login/' );

        wp_safe_redirect( add_query_arg( 'password_reset', '1', $login_url ) );
        exit;
    }
}
add_action( 'template_redirect', 'edupro_process_password_reset' );

// ─────────────────────────────────────────────────────────────────────────────
// 9. ACCESS CONTROL
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check whether the current (or given) user can access a specific course.
 *
 * Returns true if:
 * - The user is an administrator or editor.
 * - The course is free (meta _edupro_course_price == 0 or empty).
 * - The user is enrolled in the course.
 *
 * @param int      $course_id The course post ID.
 * @param int|null $user_id   Optional. Defaults to the current user.
 * @return bool
 */
function edupro_check_student_access( $course_id, $user_id = null ) {
    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return false;
    }

    // Administrators and editors always have access.
    $user = get_userdata( $user_id );
    if ( $user && ( in_array( 'administrator', (array) $user->roles, true ) || in_array( 'editor', (array) $user->roles, true ) ) ) {
        return true;
    }

    // Check enrollment via the enrollment helper.
    if ( function_exists( 'edupro_is_student_enrolled' ) ) {
        return edupro_is_student_enrolled( $course_id, $user_id );
    }

    // Fallback: check user meta directly.
    $enrolled = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
    if ( is_array( $enrolled ) && in_array( (int) $course_id, array_map( 'intval', $enrolled ), true ) ) {
        return true;
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. PROTECT LESSON CONTENT — the_content FILTER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Filter lesson post content to show a lock screen when the user is
 * not enrolled in the parent course.
 *
 * @param string $content The original post content.
 * @return string Modified content (lock screen HTML) or original content.
 */
function edupro_protect_lesson_content( $content ) {
    // Only run on singular lms_lesson posts in the main query.
    if ( ! is_singular( 'lms_lesson' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $lesson_id = get_the_ID();
    $course_id = (int) get_post_meta( $lesson_id, '_edupro_course_id', true );

    if ( ! $course_id ) {
        return $content;
    }

    // Allow free preview lessons to be viewed without enrollment.
    if ( '1' === (string) get_post_meta( $lesson_id, '_edupro_is_preview', true ) ) {
        return $content;
    }

    // Administrators always see the content.
    if ( current_user_can( 'manage_options' ) ) {
        return $content;
    }

    // Check if the user is enrolled.
    if ( is_user_logged_in() && edupro_check_student_access( $course_id ) ) {
        return $content;
    }

    // Build lock screen HTML.
    $course_title     = get_the_title( $course_id );
    $course_permalink = get_permalink( $course_id );
    $thumbnail_html   = '';

    if ( has_post_thumbnail( $course_id ) ) {
        $thumbnail_html = '<div class="edupro-lock-thumbnail">'
            . get_the_post_thumbnail( $course_id, 'course-thumb', array( 'alt' => esc_attr( $course_title ) ) )
            . '</div>';
    }

    $enroll_label = __( 'Enroll Now to Access This Lesson', 'edupro' );

    if ( ! is_user_logged_in() ) {
        $login_page_id = (int) get_option( 'edupro_login_page_id' );
        $login_url     = $login_page_id
            ? add_query_arg( 'redirect_to', get_permalink( $lesson_id ), get_permalink( $login_page_id ) )
            : wp_login_url( get_permalink( $lesson_id ) );

        $enroll_button = sprintf(
            '<a href="%s" class="edupro-btn edupro-btn-primary edupro-lock-login-btn">%s</a>
             <a href="%s" class="edupro-btn edupro-btn-outline edupro-lock-enroll-btn">%s</a>',
            esc_url( $login_url ),
            esc_html__( 'Log In to Access', 'edupro' ),
            esc_url( $course_permalink ),
            esc_html( $enroll_label )
        );
    } else {
        $enroll_button = sprintf(
            '<a href="%s" class="edupro-btn edupro-btn-primary edupro-lock-enroll-btn">%s</a>',
            esc_url( $course_permalink ),
            esc_html( $enroll_label )
        );
    }

    $lock_screen = sprintf(
        '<div class="edupro-lesson-lock-screen">
            <div class="edupro-lock-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48" aria-hidden="true">
                    <path d="M12 1C9.243 1 7 3.243 7 6v2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2h-2V6c0-2.757-2.243-5-5-5zm0 2c1.654 0 3 1.346 3 3v2H9V6c0-1.654 1.346-3 3-3zm0 10a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>
                </svg>
            </div>
            %s
            <h2 class="edupro-lock-course-title">%s</h2>
            <p class="edupro-lock-message">%s</p>
            <div class="edupro-lock-actions">%s</div>
        </div>',
        $thumbnail_html,
        esc_html( $course_title ),
        esc_html__( 'This lesson is part of a paid course. Enroll to unlock all lessons, quizzes, and certificates.', 'edupro' ),
        $enroll_button
    );

    return $lock_screen;
}
add_filter( 'the_content', 'edupro_protect_lesson_content' );

// ─────────────────────────────────────────────────────────────────────────────
// 11. REDIRECT NON-STUDENTS FROM DASHBOARD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Redirect unauthenticated users away from the dashboard page to login.
 */
function edupro_protect_dashboard_page() {
    if ( is_user_logged_in() ) {
        return;
    }

    $dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
    if ( ! $dashboard_page_id || ! is_page( $dashboard_page_id ) ) {
        return;
    }

    $login_page_id = (int) get_option( 'edupro_login_page_id' );
    $login_url     = $login_page_id
        ? get_permalink( $login_page_id )
        : home_url( '/student-login/' );

    wp_safe_redirect( add_query_arg( 'redirect_to', get_permalink( $dashboard_page_id ), $login_url ) );
    exit;
}
add_action( 'template_redirect', 'edupro_protect_dashboard_page' );

// ─────────────────────────────────────────────────────────────────────────────
// 12. PROFILE UPDATE AJAX HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler to update student profile information from the dashboard.
 */
function edupro_update_profile_handler() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to update your profile.', 'edupro' ) ) );
    }

    if ( ! check_ajax_referer( 'edupro_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'edupro' ) ) );
    }

    $user_id    = get_current_user_id();
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $bio        = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';

    $update_data = array( 'ID' => $user_id );

    if ( ! empty( $first_name ) ) {
        $update_data['first_name']   = $first_name;
        $update_data['display_name'] = $first_name . ( $last_name ? ' ' . $last_name : '' );
    }

    if ( ! empty( $last_name ) ) {
        $update_data['last_name'] = $last_name;
    }

    if ( ! empty( $bio ) ) {
        $update_data['description'] = $bio;
    }

    // Handle password change.
    $current_password = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : '';
    $new_password     = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';

    if ( ! empty( $current_password ) && ! empty( $new_password ) ) {
        $user = get_userdata( $user_id );
        if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Current password is incorrect.', 'edupro' ) ) );
        }
        if ( strlen( $new_password ) < 8 ) {
            wp_send_json_error( array( 'message' => __( 'New password must be at least 8 characters.', 'edupro' ) ) );
        }
        $update_data['user_pass'] = $new_password;
    }

    $result = wp_update_user( $update_data );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'edupro' ) ) );
}
// Note: wp_ajax_edupro_update_profile is hooked centrally in inc/ajax-handlers.php.

// ─────────────────────────────────────────────────────────────────────────────
// 13. CUSTOM BODY CLASSES FOR AUTH PAGES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Add custom body classes on auth/dashboard pages for targeted CSS.
 *
 * @param array $classes Existing body classes.
 * @return array Modified body classes.
 */
function edupro_auth_body_classes( $classes ) {
    $login_page_id    = (int) get_option( 'edupro_login_page_id' );
    $register_page_id = (int) get_option( 'edupro_register_page_id' );
    $dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );

    if ( $login_page_id && is_page( $login_page_id ) ) {
        $classes[] = 'edupro-login-page';
        $classes[] = 'edupro-auth-page';
    }

    if ( $register_page_id && is_page( $register_page_id ) ) {
        $classes[] = 'edupro-register-page';
        $classes[] = 'edupro-auth-page';
    }

    if ( $dashboard_page_id && is_page( $dashboard_page_id ) ) {
        $classes[] = 'edupro-dashboard-page';
    }

    return $classes;
}
add_filter( 'body_class', 'edupro_auth_body_classes' );
