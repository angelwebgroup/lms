<?php
/**
 * Template Name: Student Login
 * Template Post Type: page
 *
 * Full-featured EduPro student login page:
 * - Centered card with custom logo / site brand
 * - Login form (username or email + password + show/hide toggle + remember me)
 * - Forgot password toggle & AJAX reset email sender
 * - Password reset completion view (?action=edupro_reset_password)
 * - AJAX submit via `edupro_login` action with spinner & validation
 * - Google social login button (placeholder)
 * - Register link & post-login redirect to dashboard
 * - Friendly logged-in state card if user is already logged in
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );
$register_page_id  = (int) get_option( 'edupro_register_page_id' );
$register_url      = $register_page_id ? get_permalink( $register_page_id ) : home_url( '/student-register/' );

$redirect_to = isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] )
	? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) )
	: $dashboard_url;

$is_reset_mode = isset( $_GET['action'] ) && 'edupro_reset_password' === $_GET['action'] && ! empty( $_GET['key'] ) && ! empty( $_GET['login'] );
$reset_login   = $is_reset_mode ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$reset_error   = $is_reset_mode ? get_transient( 'edupro_reset_error_' . md5( $reset_login ) ) : '';
if ( $reset_error ) {
	delete_transient( 'edupro_reset_error_' . md5( $reset_login ) );
}

$logged_out     = isset( $_GET['logged_out'] ) && '1' === $_GET['logged_out'];
$password_reset = isset( $_GET['password_reset'] ) && '1' === $_GET['password_reset'];
$ajax_url       = admin_url( 'admin-ajax.php' );
$nonce          = wp_create_nonce( 'edupro_nonce' );
?>

<main id="primary" class="edupro-auth-section">
	<div class="edupro-auth-container">
		<div class="edupro-auth-card">

			<!-- Brand / Logo -->
			<div class="edupro-auth-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-auth-logo-link">
					<?php if ( has_custom_logo() ) : ?>
						<?php echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'medium', false, array( 'class' => 'edupro-auth-logo-img', 'alt' => get_bloginfo( 'name' ) ) ); ?>
					<?php else : ?>
						<span class="edupro-auth-logo-mark" aria-hidden="true">E</span>
						<span class="edupro-auth-logo-text"><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
				</a>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
				<?php $current_user = wp_get_current_user(); ?>
				<div class="edupro-already-logged-in">
					<div class="edupro-logged-in-avatar">
						<?php echo get_avatar( $current_user->ID, 72, '', $current_user->display_name, array( 'class' => 'edupro-avatar-circle' ) ); ?>
					</div>
					<h1 class="edupro-auth-title"><?php esc_html_e( 'You Are Already Signed In', 'edupro' ); ?></h1>
					<p class="edupro-auth-subtitle">
						<?php
						printf(
							/* translators: 1: display name, 2: email */
							esc_html__( 'Logged in as %1$s (%2$s).', 'edupro' ),
							'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
							esc_html( $current_user->user_email )
						);
						?>
					</p>
					<div class="edupro-logged-in-actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-block">
							<?php esc_html_e( 'Go to Student Dashboard', 'edupro' ); ?> &rarr;
						</a>
						<a href="<?php echo esc_url( function_exists( 'edupro_get_logout_url' ) ? edupro_get_logout_url() : wp_logout_url( get_permalink() ) ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-block">
							<?php esc_html_e( 'Sign Out & Switch Account', 'edupro' ); ?>
						</a>
					</div>
				</div>

			<?php elseif ( $is_reset_mode ) : ?>
				<div class="edupro-auth-header">
					<h1 class="edupro-auth-title"><?php esc_html_e( 'Set a New Password', 'edupro' ); ?></h1>
					<p class="edupro-auth-subtitle"><?php esc_html_e( 'Choose a strong password of at least 8 characters for your account.', 'edupro' ); ?></p>
				</div>

				<?php if ( $reset_error ) : ?>
					<div class="edupro-alert edupro-alert-error" role="alert"><?php echo esc_html( $reset_error ); ?></div>
				<?php endif; ?>

				<form method="post" class="edupro-auth-form">
					<?php wp_nonce_field( 'edupro_reset_password', 'edupro_reset_nonce' ); ?>
					<div class="edupro-form-group">
						<label for="edupro_new_password"><?php esc_html_e( 'New Password', 'edupro' ); ?></label>
						<input type="password" id="edupro_new_password" name="edupro_new_password" required minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'edupro' ); ?>">
					</div>
					<div class="edupro-form-group">
						<label for="edupro_confirm_password"><?php esc_html_e( 'Confirm New Password', 'edupro' ); ?></label>
						<input type="password" id="edupro_confirm_password" name="edupro_confirm_password" required minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Re-enter new password', 'edupro' ); ?>">
					</div>
					<button type="submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
						<?php esc_html_e( 'Update Password', 'edupro' ); ?>
					</button>
				</form>

				<p class="edupro-auth-footer-text">
					<a href="<?php echo esc_url( get_permalink() ); ?>">&larr; <?php esc_html_e( 'Back to Sign In', 'edupro' ); ?></a>
				</p>

			<?php else : ?>
				<!-- Login Panel -->
				<div id="edupro-login-panel">
					<div class="edupro-auth-header">
						<h1 class="edupro-auth-title"><?php esc_html_e( 'Welcome Back', 'edupro' ); ?></h1>
						<p class="edupro-auth-subtitle"><?php esc_html_e( 'Sign in to continue your learning journey', 'edupro' ); ?></p>
					</div>

					<?php if ( $logged_out ) : ?>
						<div class="edupro-alert edupro-alert-info" role="status">
							<?php esc_html_e( 'You have been signed out successfully.', 'edupro' ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $password_reset ) : ?>
						<div class="edupro-alert edupro-alert-success" role="status">
							<?php esc_html_e( 'Your password has been updated. You can now sign in with your new password.', 'edupro' ); ?>
						</div>
					<?php endif; ?>

					<form id="edupro-login-form" class="edupro-auth-form" method="post" novalidate data-edupro-inline-bound="1">
						<input type="hidden" name="action" value="edupro_login">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

						<div id="edupro-login-alert" class="edupro-alert" role="alert" style="display:none;"></div>

						<div class="edupro-form-group">
							<label for="edupro-login-username"><?php esc_html_e( 'Username or Email Address', 'edupro' ); ?></label>
							<input type="text" id="edupro-login-username" name="username" required autocomplete="username" placeholder="<?php esc_attr_e( 'student@example.com', 'edupro' ); ?>">
						</div>

						<div class="edupro-form-group">
							<label for="edupro-login-password"><?php esc_html_e( 'Password', 'edupro' ); ?></label>
							<div class="edupro-password-field">
								<input type="password" id="edupro-login-password" name="password" required autocomplete="current-password" placeholder="<?php esc_attr_e( 'Enter your password', 'edupro' ); ?>">
								<button type="button" class="edupro-toggle-password" data-target="edupro-login-password" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'edupro' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
						</div>

						<div class="edupro-form-options">
							<label class="edupro-checkbox-label">
								<input type="checkbox" id="edupro-login-remember" name="remember" value="true">
								<span><?php esc_html_e( 'Remember me', 'edupro' ); ?></span>
							</label>
							<a href="#forgot-password" id="edupro-show-forgot" class="edupro-auth-link">
								<?php esc_html_e( 'Forgot Password?', 'edupro' ); ?>
							</a>
						</div>

						<button type="submit" id="edupro-login-submit" class="edupro-btn edupro-btn-primary edupro-btn-block edupro-btn-lg">
							<span class="btn-label"><?php esc_html_e( 'Sign In', 'edupro' ); ?></span>
							<span class="btn-spinner" aria-hidden="true" style="display:none;"></span>
						</button>
					</form>

					<div class="edupro-auth-divider">
						<span><?php esc_html_e( 'OR CONTINUE WITH', 'edupro' ); ?></span>
					</div>

					<!-- Social Login Google Button (Placeholder) -->
					<button type="button" id="edupro-google-login-btn" class="edupro-btn-social edupro-btn-google">
						<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
							<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.45c-.28 1.49-1.12 2.75-2.38 3.6v2.98h3.85c2.25-2.08 3.57-5.14 3.57-8.61z"/>
							<path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.91l-3.85-2.98c-1.07.72-2.44 1.15-4.08 1.15-3.14 0-5.8-2.12-6.75-4.97H1.27v3.08C3.25 21.3 7.31 24 12 24z"/>
							<path fill="#FBBC05" d="M5.25 14.29c-.24-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.63H1.27C.46 8.24 0 10.07 0 12s.46 3.76 1.27 5.37l3.98-3.08z"/>
							<path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.59 1.8l3.44-3.44C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.27 6.63l3.98 3.08c.95-2.85 3.61-4.96 6.75-4.96z"/>
						</svg>
						<span><?php esc_html_e( 'Continue with Google', 'edupro' ); ?></span>
					</button>

					<p class="edupro-auth-footer-text">
						<?php esc_html_e( "Don't have an account yet?", 'edupro' ); ?>
						<a href="<?php echo esc_url( $register_url ); ?>" class="edupro-auth-link-strong">
							<?php esc_html_e( 'Create Free Account', 'edupro' ); ?>
						</a>
					</p>
				</div>

				<!-- Forgot Password Panel -->
				<div id="edupro-forgot-panel" style="display:none;">
					<div class="edupro-auth-header">
						<h1 class="edupro-auth-title"><?php esc_html_e( 'Reset Password', 'edupro' ); ?></h1>
						<p class="edupro-auth-subtitle"><?php esc_html_e( 'Enter your email or username and we will send you a password reset link.', 'edupro' ); ?></p>
					</div>

					<form id="edupro-forgot-form" class="edupro-auth-form" method="post" novalidate>
						<input type="hidden" name="action" value="edupro_forgot_password">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

						<div id="edupro-forgot-alert" class="edupro-alert" role="alert" style="display:none;"></div>

						<div class="edupro-form-group">
							<label for="edupro-forgot-email"><?php esc_html_e( 'Email Address or Username', 'edupro' ); ?></label>
							<input type="text" id="edupro-forgot-email" name="email" required placeholder="<?php esc_attr_e( 'student@example.com', 'edupro' ); ?>">
						</div>

						<button type="submit" id="edupro-forgot-submit" class="edupro-btn edupro-btn-primary edupro-btn-block">
							<span class="btn-label"><?php esc_html_e( 'Send Reset Link', 'edupro' ); ?></span>
							<span class="btn-spinner" aria-hidden="true" style="display:none;"></span>
						</button>
					</form>

					<p class="edupro-auth-footer-text">
						<a href="#login" id="edupro-back-to-login" class="edupro-auth-link-strong">
							&larr; <?php esc_html_e( 'Back to Sign In', 'edupro' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

		</div>
	</div>
</main>

<script>
(function(){
	var ajaxUrl = (window.edupro_vars && window.edupro_vars.ajaxurl) ? window.edupro_vars.ajaxurl : <?php echo wp_json_encode( $ajax_url ); ?>;

	// Toggle password visibility
	document.querySelectorAll('.edupro-toggle-password').forEach(function(btn){
		btn.addEventListener('click', function(){
			var input = document.getElementById(btn.getAttribute('data-target'));
			if (!input) return;
			input.type = input.type === 'password' ? 'text' : 'password';
		});
	});

	// Switch between Login and Forgot Password panels
	var loginPanel  = document.getElementById('edupro-login-panel');
	var forgotPanel = document.getElementById('edupro-forgot-panel');
	var showForgot  = document.getElementById('edupro-show-forgot');
	var backLogin   = document.getElementById('edupro-back-to-login');

	if (showForgot && loginPanel && forgotPanel) {
		showForgot.addEventListener('click', function(e){
			e.preventDefault();
			loginPanel.style.display  = 'none';
			forgotPanel.style.display = 'block';
		});
	}
	if (backLogin && loginPanel && forgotPanel) {
		backLogin.addEventListener('click', function(e){
			e.preventDefault();
			forgotPanel.style.display = 'none';
			loginPanel.style.display  = 'block';
		});
	}

	function showAlert(el, type, msg) {
		if (!el) return;
		el.className = 'edupro-alert edupro-alert-' + type;
		el.textContent = msg;
		el.style.display = 'block';
	}

	function setLoading(btn, loading) {
		if (!btn) return;
		btn.disabled = loading;
		var spinner = btn.querySelector('.btn-spinner');
		if (spinner) spinner.style.display = loading ? 'inline-block' : 'none';
	}

	// AJAX Login submit
	var loginForm = document.getElementById('edupro-login-form');
	if (loginForm) {
		loginForm.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl = document.getElementById('edupro-login-alert');
			var btn     = document.getElementById('edupro-login-submit');
			var userVal = document.getElementById('edupro-login-username').value.trim();
			var passVal = document.getElementById('edupro-login-password').value;

			if (!userVal || !passVal) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Please enter your username/email and password.', 'edupro' ) ); ?>);
				return;
			}

			setLoading(btn, true);
			alertEl.style.display = 'none';

			var fd = new FormData(loginForm);
			fd.set('remember', document.getElementById('edupro-login-remember').checked ? 'true' : 'false');

			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (res && res.success) {
						showAlert(alertEl, 'success', (res.data && res.data.message) ? res.data.message : 'Login successful!');
						var target = (res.data && res.data.redirect_url) ? res.data.redirect_url : <?php echo wp_json_encode( $dashboard_url ); ?>;
						setTimeout(function(){ window.location.href = target; }, 500);
					} else {
						setLoading(btn, false);
						var msg = (res && res.data && res.data.message) ? res.data.message : 'Invalid credentials.';
						showAlert(alertEl, 'error', msg);
					}
				})
				.catch(function(){
					setLoading(btn, false);
					showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>);
				});
		});
	}

	// AJAX Forgot Password submit
	var forgotForm = document.getElementById('edupro-forgot-form');
	if (forgotForm) {
		forgotForm.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl = document.getElementById('edupro-forgot-alert');
			var btn     = document.getElementById('edupro-forgot-submit');
			var email   = document.getElementById('edupro-forgot-email').value.trim();

			if (!email) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Please enter your email or username.', 'edupro' ) ); ?>);
				return;
			}

			setLoading(btn, true);
			var fd = new FormData(forgotForm);

			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					setLoading(btn, false);
					if (res && res.success) {
						showAlert(alertEl, 'success', res.data.message);
						forgotForm.reset();
					} else {
						showAlert(alertEl, 'error', (res && res.data && res.data.message) ? res.data.message : 'Could not send reset email.');
					}
				})
				.catch(function(){
					setLoading(btn, false);
					showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>);
				});
		});
	}

	// Google Social Login placeholder notice
	var googleBtn = document.getElementById('edupro-google-login-btn');
	if (googleBtn) {
		googleBtn.addEventListener('click', function(){
			var alertEl = document.getElementById('edupro-login-alert');
			showAlert(alertEl, 'info', <?php echo wp_json_encode( __( 'Google Sign-In requires OAuth client credentials in site settings. Please sign in with your email and password.', 'edupro' ) ); ?>);
		});
	}
})();
</script>

<?php
get_footer();
