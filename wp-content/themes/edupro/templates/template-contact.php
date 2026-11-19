<?php
/**
 * Template Name: Contact Us
 * Template Post Type: page
 *
 * Luxury Glassmorphism Contact Us Page:
 * - Luminous .edupro-page-hero--glass with breadcrumb, eyebrow pill, bold H1,
 *   subtitle, and quick-action pills (Phone, WhatsApp, Email) from Website Settings
 * - 2-column .edupro-contact-page-grid:
 *   * Left (.edupro-contact-form-card): Working AJAX Contact Form (#edupro-contact-form)
 *     with nonce, honeypot, topic select, live validation, and inline JS submission
 *   * Right (.edupro-contact-sidebar): Frosted cards for Direct Phone & WhatsApp Chat,
 *     Email & Support Hours, UK + India Office Addresses, and Social Links
 * - Interactive Google Map Section (.edupro-contact-map-section) via [edupro_map]
 * - Admissions & Support FAQs Accordion (.edupro-contact-faq-section)
 *
 * @package EduPro
 * @version 1.4.3
 */

defined( 'ABSPATH' ) || exit;

get_header();

$get_setting = function ( $key, $default = '' ) {
	if ( function_exists( 'edupro_get_site_setting' ) ) {
		return (string) edupro_get_site_setting( $key, $default );
	}
	return (string) $default;
};

$site_name         = $get_setting( 'site_name', get_bloginfo( 'name' ) );
if ( '' === trim( $site_name ) ) {
	$site_name = get_bloginfo( 'name' );
}
$phone_primary     = $get_setting( 'phone_primary', '' );
$phone_secondary   = $get_setting( 'phone_secondary', '' );
$email_support     = $get_setting( 'email_support', get_option( 'admin_email', '' ) );
$email_sales       = $get_setting( 'email_sales', '' );
$business_hours    = $get_setting( 'business_hours', __( 'Mon – Sat: 9:00 AM – 7:00 PM', 'edupro' ) );
$address_primary   = $get_setting( 'address_primary', '' );
$address_secondary = $get_setting( 'address_secondary', '' );
$whatsapp_url      = function_exists( 'edupro_get_whatsapp_url' ) ? edupro_get_whatsapp_url() : '';
$map_embed         = trim( $get_setting( 'map_embed', '' ) );
$social_html       = do_shortcode( '[edupro_social_links]' );
$map_html          = $map_embed ? trim( do_shortcode( '[edupro_map]' ) ) : '';

$ajax_url          = admin_url( 'admin-ajax.php' );
$nonce             = wp_create_nonce( 'edupro_nonce' );
$current_user      = is_user_logged_in() ? wp_get_current_user() : null;
$prefill_name      = $current_user ? $current_user->display_name : '';
$prefill_email     = $current_user ? $current_user->user_email : '';
$prefill_phone     = $current_user ? (string) get_user_meta( $current_user->ID, '_edupro_phone', true ) : '';

$contact_faqs = array(
	array(
		'q' => __( 'How quickly will an admissions advisor respond to my enquiry?', 'edupro' ),
		'a' => __( 'Our admissions and student support team responds to email enquiries within 2–4 business hours, and WhatsApp or phone calls are typically answered within minutes during business hours.', 'edupro' ),
	),
	array(
		'q' => __( 'Can I get guidance on choosing the right accredited course?', 'edupro' ),
		'a' => __( 'Yes! Select "Course Enquiry" in the form above or start a WhatsApp chat with our advisors. Share your career goals and current qualifications, and we will recommend the best study pathway for you.', 'edupro' ),
	),
	array(
		'q' => __( 'Do you offer corporate, NHS, or group training discounts?', 'edupro' ),
		'a' => __( 'We provide tailored group pricing, dedicated account management, and progress reporting for teams of 5 or more learners. Choose "Corporate / Group Training" above with your team size and preferred courses.', 'edupro' ),
	),
	array(
		'q' => __( 'How do I verify a certificate or get help with an existing enrolment?', 'edupro' ),
		'a' => __( 'Enrolled students can download verified certificates and receipts directly from their Student Dashboard. For order or certificate verification assistance, include your Order ID or Certificate ID in your message.', 'edupro' ),
	),
);
?>

<main id="primary" class="edupro-contact-page">

	<!-- =================================================================== -->
	<!-- 1. LUMINOUS GLASSMORPHISM HERO                                      -->
	<!-- =================================================================== -->
	<section class="edupro-page-hero edupro-page-hero--glass">
		<div class="edupro-container">
			<?php if ( function_exists( 'edupro_get_breadcrumb' ) ) : ?>
				<?php echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<div class="edupro-page-hero__content">
				<span class="edupro-section-eyebrow"><?php esc_html_e( "We're Here To Help", 'edupro' ); ?></span>
				<h1 class="edupro-page-hero__title"><?php esc_html_e( 'Get in Touch With Our Team', 'edupro' ); ?></h1>
				<p class="edupro-page-hero__subtitle">
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__( 'Have questions about courses, accreditation, payments, or corporate training at %s? Our dedicated advisors are ready to assist you.', 'edupro' ),
						esc_html( $site_name )
					);
					?>
				</p>

				<?php if ( $phone_primary || $whatsapp_url || $email_support ) : ?>
					<div class="edupro-page-hero__actions">
						<?php if ( $phone_primary ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone_primary ) ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-pill">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
								<span><?php echo esc_html( $phone_primary ); ?></span>
							</a>
						<?php endif; ?>

						<?php if ( $whatsapp_url ) : ?>
							<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-whatsapp edupro-btn-pill">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.82 11.82 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.26-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.5-8.41zM12.06 21.8a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.83 9.83 0 0 1-1.52-5.24c0-5.45 4.44-9.89 9.88-9.89 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.85-9.89 9.85zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z"/></svg>
								<span><?php esc_html_e( 'WhatsApp Chat', 'edupro' ); ?></span>
							</a>
						<?php endif; ?>

						<?php if ( $email_support ) : ?>
							<a href="mailto:<?php echo esc_attr( antispambot( $email_support ) ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-pill">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
								<span><?php echo esc_html( antispambot( $email_support ) ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 2. MAIN 2-COLUMN GLASS CONTACT FORM & SIDEBAR                       -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-contact-main-section">
		<div class="edupro-container">
			<div class="edupro-contact-page-grid">

				<!-- Left: AJAX Contact Form -->
				<div class="edupro-contact-form-card edupro-glass-panel">
					<div class="edupro-contact-form-card__head">
						<h2><?php esc_html_e( 'Send Us a Message', 'edupro' ); ?></h2>
						<p><?php esc_html_e( 'Fill in the form below and one of our course advisors will get back to you promptly.', 'edupro' ); ?></p>
					</div>

					<form id="edupro-contact-form" class="edupro-contact-form" method="post" novalidate data-edupro-inline-bound="1">
						<input type="hidden" name="action" value="edupro_submit_contact_form">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

						<!-- Anti-spam honeypot -->
						<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
							<label for="edupro-contact-website"><?php esc_html_e( 'Website', 'edupro' ); ?></label>
							<input type="text" id="edupro-contact-website" name="website_url" tabindex="-1" autocomplete="off" value="">
						</div>

						<div id="edupro-contact-status" class="edupro-contact-status edupro-alert" role="alert" aria-live="polite" style="display:none;"></div>

						<div class="edupro-form-row">
							<div class="edupro-form-group">
								<label for="edupro-contact-name"><?php esc_html_e( 'Full Name', 'edupro' ); ?> <span class="required">*</span></label>
								<input type="text" id="edupro-contact-name" name="name" required autocomplete="name" value="<?php echo esc_attr( $prefill_name ); ?>" placeholder="<?php esc_attr_e( 'Your full name', 'edupro' ); ?>">
							</div>
							<div class="edupro-form-group">
								<label for="edupro-contact-email"><?php esc_html_e( 'Email Address', 'edupro' ); ?> <span class="required">*</span></label>
								<input type="email" id="edupro-contact-email" name="email" required autocomplete="email" value="<?php echo esc_attr( $prefill_email ); ?>" placeholder="<?php esc_attr_e( 'you@example.com', 'edupro' ); ?>">
							</div>
						</div>

						<div class="edupro-form-row">
							<div class="edupro-form-group">
								<label for="edupro-contact-phone"><?php esc_html_e( 'Phone / WhatsApp (Optional)', 'edupro' ); ?></label>
								<input type="tel" id="edupro-contact-phone" name="phone" autocomplete="tel" value="<?php echo esc_attr( $prefill_phone ); ?>" placeholder="<?php esc_attr_e( '+44 / +91 ...', 'edupro' ); ?>">
							</div>
							<div class="edupro-form-group">
								<label for="edupro-contact-topic"><?php esc_html_e( 'Inquiry Topic', 'edupro' ); ?></label>
								<select id="edupro-contact-topic" name="topic">
									<option value="Course Enquiry"><?php esc_html_e( 'Course Enquiry', 'edupro' ); ?></option>
									<option value="Payment & Enrolment"><?php esc_html_e( 'Payment & Enrolment', 'edupro' ); ?></option>
									<option value="Corporate / Group Training"><?php esc_html_e( 'Corporate / Group Training', 'edupro' ); ?></option>
									<option value="Certificate & Accreditation"><?php esc_html_e( 'Certificate & Accreditation', 'edupro' ); ?></option>
									<option value="Technical Support"><?php esc_html_e( 'Technical Support', 'edupro' ); ?></option>
								</select>
							</div>
						</div>

						<div class="edupro-form-group">
							<label for="edupro-contact-message"><?php esc_html_e( 'Your Message', 'edupro' ); ?> <span class="required">*</span></label>
							<textarea id="edupro-contact-message" name="message" rows="5" required placeholder="<?php esc_attr_e( 'Tell us how we can help you today…', 'edupro' ); ?>"></textarea>
						</div>

						<button type="submit" id="edupro-contact-submit" class="edupro-btn edupro-btn-primary edupro-btn-pill edupro-btn-lg edupro-btn-block">
							<span class="btn-label"><?php esc_html_e( 'Send Message', 'edupro' ); ?> &rarr;</span>
							<span class="btn-spinner" aria-hidden="true" style="display:none;"></span>
						</button>
					</form>
				</div>

				<!-- Right: Frosted Contact Sidebar Cards -->
				<aside class="edupro-contact-sidebar" aria-label="<?php esc_attr_e( 'Direct Contact Information', 'edupro' ); ?>">

					<!-- Card 1: Direct Phone & WhatsApp Chat -->
					<div class="edupro-contact-info-card edupro-glass-panel">
						<div class="edupro-contact-info-card__head">
							<span class="edupro-contact-icon-tile edupro-contact-icon-tile--rose" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
							</span>
							<div>
								<h3><?php esc_html_e( 'Phone & WhatsApp Support', 'edupro' ); ?></h3>
								<p><?php esc_html_e( 'Speak directly with an admissions advisor.', 'edupro' ); ?></p>
							</div>
						</div>
						<div class="edupro-contact-info-card__body">
							<?php if ( $phone_primary ) : ?>
								<a class="edupro-contact-detail-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone_primary ) ); ?>">
									<strong><?php echo esc_html( $phone_primary ); ?></strong>
								</a>
							<?php endif; ?>
							<?php if ( $phone_secondary ) : ?>
								<a class="edupro-contact-detail-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone_secondary ) ); ?>">
									<?php echo esc_html( $phone_secondary ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $whatsapp_url ) : ?>
								<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-whatsapp edupro-btn-pill edupro-btn-block" style="margin-top:12px;">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.82 11.82 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.26-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.5-8.41zM12.06 21.8a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.83 9.83 0 0 1-1.52-5.24c0-5.45 4.44-9.89 9.88-9.89 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.85-9.89 9.85zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z"/></svg>
									<span><?php esc_html_e( 'Chat on WhatsApp', 'edupro' ); ?></span>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<!-- Card 2: Email & Support Hours -->
					<div class="edupro-contact-info-card edupro-glass-panel">
						<div class="edupro-contact-info-card__head">
							<span class="edupro-contact-icon-tile edupro-contact-icon-tile--indigo" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
							</span>
							<div>
								<h3><?php esc_html_e( 'Email & Support Hours', 'edupro' ); ?></h3>
								<p><?php esc_html_e( 'Fast, friendly email assistance.', 'edupro' ); ?></p>
							</div>
						</div>
						<div class="edupro-contact-info-card__body">
							<?php if ( $email_support ) : ?>
								<a class="edupro-contact-detail-link" href="mailto:<?php echo esc_attr( antispambot( $email_support ) ); ?>">
									<strong><?php echo esc_html( antispambot( $email_support ) ); ?></strong>
								</a>
							<?php endif; ?>
							<?php if ( $email_sales && $email_sales !== $email_support ) : ?>
								<a class="edupro-contact-detail-link" href="mailto:<?php echo esc_attr( antispambot( $email_sales ) ); ?>">
									<?php echo esc_html( antispambot( $email_sales ) ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $business_hours ) : ?>
								<p class="edupro-contact-hours">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
									<span><?php echo esc_html( $business_hours ); ?></span>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Card 3: Office Addresses (UK + India) -->
					<?php if ( $address_primary || $address_secondary ) : ?>
						<div class="edupro-contact-info-card edupro-glass-panel">
							<div class="edupro-contact-info-card__head">
								<span class="edupro-contact-icon-tile edupro-contact-icon-tile--emerald" aria-hidden="true">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
								</span>
								<div>
									<h3><?php esc_html_e( 'Our Offices', 'edupro' ); ?></h3>
									<p><?php esc_html_e( 'Serving learners across the UK, India & worldwide.', 'edupro' ); ?></p>
								</div>
							</div>
							<div class="edupro-contact-info-card__body">
								<?php if ( $address_primary ) : ?>
									<div class="edupro-contact-address-row">
										<p><?php echo nl2br( esc_html( $address_primary ) ); ?></p>
									</div>
								<?php endif; ?>
								<?php if ( $address_secondary ) : ?>
									<div class="edupro-contact-address-row">
										<p><?php echo nl2br( esc_html( $address_secondary ) ); ?></p>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Card 4: Social Links -->
					<?php if ( ! empty( trim( $social_html ) ) ) : ?>
						<div class="edupro-contact-info-card edupro-glass-panel">
							<div class="edupro-contact-info-card__head">
								<h3><?php esc_html_e( 'Connect With Us', 'edupro' ); ?></h3>
							</div>
							<div class="edupro-contact-info-card__body">
								<?php echo $social_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					<?php endif; ?>

				</aside>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 3. INTERACTIVE GOOGLE MAP SECTION                                   -->
	<!-- =================================================================== -->
	<?php if ( ! empty( $map_html ) ) : ?>
		<section class="edupro-section edupro-contact-map-section">
			<div class="edupro-container">
				<div class="edupro-section-header edupro-section-header--center">
					<span class="edupro-section-eyebrow"><?php esc_html_e( 'Find Us', 'edupro' ); ?></span>
					<h2><?php esc_html_e( 'Visit Our Location', 'edupro' ); ?></h2>
				</div>
				<div class="edupro-contact-map-card edupro-glass-panel">
					<?php echo $map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- =================================================================== -->
	<!-- 4. ADMISSIONS & SUPPORT FAQS ACCORDION                              -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-contact-faq-section">
		<div class="edupro-container">
			<div class="edupro-section-header edupro-section-header--center">
				<span class="edupro-section-eyebrow"><?php esc_html_e( 'Quick Answers', 'edupro' ); ?></span>
				<h2><?php esc_html_e( 'Admissions & Support FAQs', 'edupro' ); ?></h2>
				<p><?php esc_html_e( 'Find immediate answers to our most commonly asked questions.', 'edupro' ); ?></p>
			</div>

			<div class="edupro-faq-list edupro-contact-faq-list">
				<?php foreach ( $contact_faqs as $idx => $faq ) : ?>
					<?php $is_first = ( 0 === $idx ); ?>
					<div class="edupro-accordion-item<?php echo $is_first ? ' is-open' : ''; ?>">
						<button type="button" class="edupro-accordion-trigger" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>">
							<span><?php echo esc_html( $faq['q'] ); ?></span>
							<svg class="edupro-accordion-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
						</button>
						<div class="edupro-accordion-content"<?php echo $is_first ? '' : ' hidden'; ?>>
							<p><?php echo esc_html( $faq['a'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

</main>

<script>
(function(){
	var form    = document.getElementById('edupro-contact-form');
	if (!form) return;

	var ajaxUrl  = (window.edupro_vars && window.edupro_vars.ajaxurl) ? window.edupro_vars.ajaxurl : <?php echo wp_json_encode( $ajax_url ); ?>;
	var statusEl = document.getElementById('edupro-contact-status');
	var submitBt = document.getElementById('edupro-contact-submit');

	function showStatus(type, msg) {
		if (!statusEl) return;
		statusEl.className = 'edupro-contact-status edupro-alert edupro-alert-' + type;
		statusEl.textContent = msg;
		statusEl.style.display = 'block';
	}

	form.addEventListener('submit', function(e){
		e.preventDefault();
		var nameVal  = (document.getElementById('edupro-contact-name').value || '').trim();
		var emailVal = (document.getElementById('edupro-contact-email').value || '').trim();
		var msgVal   = (document.getElementById('edupro-contact-message').value || '').trim();

		if (!nameVal) {
			showStatus('error', <?php echo wp_json_encode( __( 'Please enter your full name.', 'edupro' ) ); ?>);
			return;
		}
		if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
			showStatus('error', <?php echo wp_json_encode( __( 'Please enter a valid email address.', 'edupro' ) ); ?>);
			return;
		}
		if (msgVal.length < 10) {
			showStatus('error', <?php echo wp_json_encode( __( 'Please enter a message of at least 10 characters.', 'edupro' ) ); ?>);
			return;
		}

		submitBt.disabled = true;
		var spinner = submitBt.querySelector('.btn-spinner');
		if (spinner) spinner.style.display = 'inline-block';
		statusEl.style.display = 'none';

		fetch(ajaxUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
			.then(function(r){ return r.json(); })
			.then(function(res){
				submitBt.disabled = false;
				if (spinner) spinner.style.display = 'none';
				if (res && res.success) {
					showStatus('success', (res.data && res.data.message) ? res.data.message : 'Message sent successfully!');
					form.reset();
				} else {
					showStatus('error', (res && res.data && res.data.message) ? res.data.message : 'Unable to send message. Please try again.');
				}
			})
			.catch(function(){
				submitBt.disabled = false;
				if (spinner) spinner.style.display = 'none';
				showStatus('error', <?php echo wp_json_encode( __( 'Network error. Please try again or reach us via phone/WhatsApp.', 'edupro' ) ); ?>);
			});
	});
})();
</script>

<?php
get_footer();
