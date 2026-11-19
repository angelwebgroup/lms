<?php
/**
 * Template Name: Student Register
 * Template Post Type: page
 *
 * Full student registration page:
 * - Centered card with logo
 * - First Name, Last Name, Email, Password (with strength meter), Confirm Password, Terms checkbox
 * - Email verification info banner
 * - AJAX submit via `edupro_register` action
 * - Redirects to Student Dashboard on success
 * - Google sign-up placeholder & Sign In link
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );
$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );
$login_page_id     = (int) get_option( 'edupro_login_page_id' );
$login_url         = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/student-login/' );
$redirect_to       = isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] )
	? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) )
	: $dashboard_url;

$ajax_url = admin_url( 'admin-ajax.php' );
$nonce    = wp_create_nonce( 'edupro_nonce' );
?>

<main id="primary" class="edupro-auth-section">
	<div class="edupro-auth-container">
		<div class="edupro-auth-card edupro-auth-card--wide">

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
					<h1 class="edupro-auth-title"><?php esc_html_e( 'You Already Have an Active Session', 'edupro' ); ?></h1>
					<p class="edupro-auth-subtitle">
						<?php
						printf(
							esc_html__( 'Signed in as %s.', 'edupro' ),
							'<strong>' . esc_html( $current_user->display_name ) . '</strong>'
						);
						?>
					</p>
					<div class="edupro-logged-in-actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-block">
							<?php esc_html_e( 'Go to Student Dashboard', 'edupro' ); ?> &rarr;
						</a>
						<a href="<?php echo esc_url( function_exists( 'edupro_get_logout_url' ) ? edupro_get_logout_url() : wp_logout_url( get_permalink() ) ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-block">
							<?php esc_html_e( 'Log Out', 'edupro' ); ?>
						</a>
					</div>
				</div>

			<?php else : ?>
				<div class="edupro-auth-header">
					<h1 class="edupro-auth-title"><?php esc_html_e( 'Create Your Student Account', 'edupro' ); ?></h1>
					<p class="edupro-auth-subtitle"><?php echo esc_html( sprintf( __( 'Join thousands of learners advancing their careers with %s', 'edupro' ), get_bloginfo( 'name' ) ?: __( 'our academy', 'edupro' ) ) ); ?></p>
				</div>

				<!-- Email Verification Info Notice -->
				<div class="edupro-info-banner">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
					<span><?php esc_html_e( 'A verification link will be sent to your email upon registration so you can receive course updates and certificates.', 'edupro' ); ?></span>
				</div>

				<form id="edupro-register-form" class="edupro-auth-form" method="post" novalidate data-edupro-inline-bound="1">
					<input type="hidden" name="action" value="edupro_register">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

					<div id="edupro-register-alert" class="edupro-alert" role="alert" style="display:none;"></div>

					<div class="edupro-form-grid-2">
						<div class="edupro-form-group">
							<label for="edupro-reg-first-name"><?php esc_html_e( 'First Name', 'edupro' ); ?> <span class="required">*</span></label>
							<input type="text" id="edupro-reg-first-name" name="first_name" required autocomplete="given-name" placeholder="<?php esc_attr_e( 'e.g. Aarav', 'edupro' ); ?>">
						</div>
						<div class="edupro-form-group">
							<label for="edupro-reg-last-name"><?php esc_html_e( 'Last Name', 'edupro' ); ?> <span class="required">*</span></label>
							<input type="text" id="edupro-reg-last-name" name="last_name" required autocomplete="family-name" placeholder="<?php esc_attr_e( 'e.g. Sharma', 'edupro' ); ?>">
						</div>
					</div>

					<div class="edupro-form-group">
						<label for="edupro-reg-email"><?php esc_html_e( 'Email Address', 'edupro' ); ?> <span class="required">*</span></label>
						<input type="email" id="edupro-reg-email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'edupro' ); ?>">
					</div>

					<div class="edupro-form-grid-2">
						<div class="edupro-form-group">
							<label for="edupro-reg-password"><?php esc_html_e( 'Password', 'edupro' ); ?> <span class="required">*</span></label>
							<div class="edupro-password-field">
								<input type="password" id="edupro-reg-password" name="password" required minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Min. 8 characters', 'edupro' ); ?>">
								<button type="button" class="edupro-toggle-password" data-target="edupro-reg-password" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'edupro' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
							<div class="edupro-password-strength" id="edupro-pw-strength" aria-live="polite">
								<span class="edupro-pw-bar"></span>
								<small class="edupro-pw-label"></small>
							</div>
						</div>

						<div class="edupro-form-group">
							<label for="edupro-reg-confirm-password"><?php esc_html_e( 'Confirm Password', 'edupro' ); ?> <span class="required">*</span></label>
							<div class="edupro-password-field">
								<input type="password" id="edupro-reg-confirm-password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Re-enter password', 'edupro' ); ?>">
								<button type="button" class="edupro-toggle-password" data-target="edupro-reg-confirm-password" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'edupro' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
						</div>
					</div>

					<div class="edupro-form-group">
						<label class="edupro-checkbox-label">
							<input type="checkbox" id="edupro-reg-terms" name="terms" value="1" required>
							<span>
								<?php
								printf(
									/* translators: 1: terms link, 2: privacy policy link */
									wp_kses_post( __( 'I agree to the <a href="%1$s" target="_blank">Terms of Service</a> and <a href="%2$s" target="_blank">Privacy Policy</a>.', 'edupro' ) ),
									esc_url( home_url( '/terms/' ) ),
									esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-policy/' ) )
								);
								?>
							</span>
						</label>
					</div>

					<button type="submit" id="edupro-register-submit" class="edupro-btn edupro-btn-primary edupro-btn-block edupro-btn-lg">
						<span class="btn-label"><?php esc_html_e( 'Create Free Account', 'edupro' ); ?></span>
						<span class="btn-spinner" aria-hidden="true" style="display:none;"></span>
					</button>
				</form>

				<div class="edupro-auth-divider">
					<span><?php esc_html_e( 'OR SIGN UP WITH', 'edupro' ); ?></span>
				</div>

				<button type="button" id="edupro-google-register-btn" class="edupro-btn-social edupro-btn-google">
					<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
						<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.45c-.28 1.49-1.12 2.75-2.38 3.6v2.98h3.85c2.25-2.08 3.57-5.14 3.57-8.61z"/>
						<path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.91l-3.85-2.98c-1.07.72-2.44 1.15-4.08 1.15-3.14 0-5.8-2.12-6.75-4.97H1.27v3.08C3.25 21.3 7.31 24 12 24z"/>
						<path fill="#FBBC05" d="M5.25 14.29c-.24-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.63H1.27C.46 8.24 0 10.07 0 12s.46 3.76 1.27 5.37l3.98-3.08z"/>
						<path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.59 1.8l3.44-3.44C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.27 6.63l3.98 3.08c.95-2.85 3.61-4.96 6.75-4.96z"/>
					</svg>
					<span><?php esc_html_e( 'Sign up with Google', 'edupro' ); ?></span>
				</button>

				<p class="edupro-auth-footer-text">
					<?php esc_html_e( 'Already have an account?', 'edupro' ); ?>
					<a href="<?php echo esc_url( $login_url ); ?>" class="edupro-auth-link-strong">
						<?php esc_html_e( 'Sign In', 'edupro' ); ?>
					</a>
				</p>
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

	// Password strength meter
	var pwInput = document.getElementById('edupro-reg-password');
	var pwWrap  = document.getElementById('edupro-pw-strength');
	if (pwInput && pwWrap) {
		var bar   = pwWrap.querySelector('.edupro-pw-bar');
		var label = pwWrap.querySelector('.edupro-pw-label');
		pwInput.addEventListener('input', function(){
			var val = pwInput.value;
			var score = 0;
			if (val.length >= 8) score++;
			if (/[A-Z]/.test(val)) score++;
			if (/[0-9]/.test(val)) score++;
			if (/[^A-Za-z0-9]/.test(val)) score++;

			var levels = [
				{ w: '0%',   c: '#e8e8f0', t: '' },
				{ w: '25%',  c: '#e31b23', t: 'Weak' },
				{ w: '50%',  c: '#f39c12', t: 'Fair' },
				{ w: '75%',  c: '#3498db', t: 'Good' },
				{ w: '100%', c: '#2ecc71', t: 'Strong' }
			];
			var lvl = val.length === 0 ? levels[0] : levels[Math.max(1, score)];
			if (bar)   { bar.style.width = lvl.w; bar.style.background = lvl.c; }
			if (label) { label.textContent = lvl.t; label.style.color = lvl.c; }
		});
	}

	function showAlert(el, type, msg) {
		if (!el) return;
		el.className = 'edupro-alert edupro-alert-' + type;
		el.textContent = msg;
		el.style.display = 'block';
	}

	// AJAX Registration submit
	var form = document.getElementById('edupro-register-form');
	if (form) {
		form.addEventListener('submit', function(e){
			e.preventDefault();
			var alertEl  = document.getElementById('edupro-register-alert');
			var btn      = document.getElementById('edupro-register-submit');
			var first    = document.getElementById('edupro-reg-first-name').value.trim();
			var last     = document.getElementById('edupro-reg-last-name').value.trim();
			var email    = document.getElementById('edupro-reg-email').value.trim();
			var pass     = document.getElementById('edupro-reg-password').value;
			var confirm  = document.getElementById('edupro-reg-confirm-password').value;
			var terms    = document.getElementById('edupro-reg-terms').checked;

			if (!first || !last) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Please enter your first and last name.', 'edupro' ) ); ?>);
				return;
			}
			if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Please enter a valid email address.', 'edupro' ) ); ?>);
				return;
			}
			if (pass.length < 8) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Password must be at least 8 characters long.', 'edupro' ) ); ?>);
				return;
			}
			if (pass !== confirm) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Passwords do not match.', 'edupro' ) ); ?>);
				return;
			}
			if (!terms) {
				showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Please agree to the Terms of Service and Privacy Policy.', 'edupro' ) ); ?>);
				return;
			}

			btn.disabled = true;
			var spinner = btn.querySelector('.btn-spinner');
			if (spinner) spinner.style.display = 'inline-block';
			alertEl.style.display = 'none';

			var fd = new FormData(form);
			fd.set('terms', '1');

			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (res && res.success) {
						showAlert(alertEl, 'success', (res.data && res.data.message) ? res.data.message : 'Account created!');
						var target = (res.data && res.data.redirect_url) ? res.data.redirect_url : <?php echo wp_json_encode( $dashboard_url ); ?>;
						setTimeout(function(){ window.location.href = target; }, 900);
					} else {
						btn.disabled = false;
						if (spinner) spinner.style.display = 'none';
						showAlert(alertEl, 'error', (res && res.data && res.data.message) ? res.data.message : 'Registration failed.');
					}
				})
				.catch(function(){
					btn.disabled = false;
					if (spinner) spinner.style.display = 'none';
					showAlert(alertEl, 'error', <?php echo wp_json_encode( __( 'Network error. Please try again.', 'edupro' ) ); ?>);
				});
		});
	}

	var googleBtn = document.getElementById('edupro-google-register-btn');
	if (googleBtn) {
		googleBtn.addEventListener('click', function(){
			var alertEl = document.getElementById('edupro-register-alert');
			showAlert(alertEl, 'info', <?php echo wp_json_encode( __( 'Google Sign-Up requires OAuth configuration. Please complete the quick registration form above.', 'edupro' ) ); ?>);
		});
	}
})();
</script>

<?php
get_footer();
