<?php
/**
 * The template for displaying the footer.
 *
 * Renders the 4-column widgetized dark footer (footer-widget-1..4 with dynamic
 * Website Settings branding, contact details, social icons, copyright),
 * inline SVG payment icons, Floating WhatsApp & Call quick widget,
 * global toast/modal containers, scroll-to-top button, and wp_footer().
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Safe helper for reading Website Settings (inc/site-settings.php).
$edupro_setting = function ( $key, $default = '' ) {
    if ( function_exists( 'edupro_get_site_setting' ) ) {
        $val = edupro_get_site_setting( $key, $default );
        return ( '' !== $val && null !== $val ) ? $val : $default;
    }
    return $default;
};

$edupro_courses_url   = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
$edupro_dashboard_id  = (int) get_option( 'edupro_dashboard_page_id' );
$edupro_dashboard_url = $edupro_dashboard_id ? get_permalink( $edupro_dashboard_id ) : home_url( '/dashboard/' );
$edupro_login_id      = (int) get_option( 'edupro_login_page_id' );
$edupro_login_url     = $edupro_login_id ? get_permalink( $edupro_login_id ) : home_url( '/student-login/' );
$edupro_register_id   = (int) get_option( 'edupro_register_page_id' );
$edupro_register_url  = $edupro_register_id ? get_permalink( $edupro_register_id ) : home_url( '/student-register/' );

// Brand & Footer Settings.
$edupro_site_name       = $edupro_setting( 'site_name', get_bloginfo( 'name' ) );
$edupro_footer_logo_url = $edupro_setting( 'footer_logo_url', '' );
if ( '' === $edupro_footer_logo_url ) {
    $edupro_footer_logo_url = $edupro_setting( 'header_logo_url', '' );
}
$edupro_footer_about = $edupro_setting(
    'footer_about',
    get_bloginfo( 'description' ) ?: __( 'Master in-demand skills with industry-leading instructors, accredited certificates, and lifetime course access.', 'edupro' )
);
$edupro_footer_trust = $edupro_setting(
    'footer_trust_badge',
    __( 'Rated Excellent by 10,000+ Learners', 'edupro' )
);

// Dynamic Copyright with {year} and {site_name} replacement.
$edupro_default_copyright = get_theme_mod(
    'edupro_footer_copyright',
    sprintf( '&copy; {year} {site_name}. %s', __( 'All rights reserved.', 'edupro' ) )
);
$edupro_copyright_raw = $edupro_setting( 'footer_copyright', $edupro_default_copyright );
$edupro_copyright     = str_replace(
    array( '{year}', '{site_name}' ),
    array( (string) gmdate( 'Y' ), esc_html( $edupro_site_name ) ),
    $edupro_copyright_raw
);

// Contact details from Website Settings.
$edupro_phone_primary   = trim( (string) $edupro_setting( 'phone_primary', '' ) );
$edupro_phone_secondary = trim( (string) $edupro_setting( 'phone_secondary', '' ) );
$edupro_phone_href      = $edupro_phone_primary ? 'tel:' . preg_replace( '/[^0-9+]/', '', $edupro_phone_primary ) : '';
$edupro_phone2_href     = $edupro_phone_secondary ? 'tel:' . preg_replace( '/[^0-9+]/', '', $edupro_phone_secondary ) : '';

$edupro_whatsapp_num = trim( (string) $edupro_setting( 'whatsapp_number', '' ) );
$edupro_whatsapp_url = '';
if ( '' !== $edupro_whatsapp_num ) {
    if ( function_exists( 'edupro_get_whatsapp_url' ) ) {
        $edupro_whatsapp_url = edupro_get_whatsapp_url();
    } else {
        $wa_digits           = preg_replace( '/[^0-9]/', '', $edupro_whatsapp_num );
        $edupro_whatsapp_url = $wa_digits ? 'https://wa.me/' . $wa_digits : '';
    }
}
$edupro_whatsapp_floating = function_exists( 'edupro_get_site_setting' )
    ? ( '1' === (string) edupro_get_site_setting( 'whatsapp_floating_enabled', '1' ) )
    : false;

$edupro_email_support  = trim( (string) $edupro_setting( 'email_support', get_option( 'admin_email' ) ) );
$edupro_business_hours = trim( (string) $edupro_setting( 'business_hours', __( 'Mon – Sat: 9:00 AM – 7:00 PM IST', 'edupro' ) ) );
$edupro_addr_primary   = trim( (string) $edupro_setting( 'address_primary', '' ) );
$edupro_addr_secondary = trim( (string) $edupro_setting( 'address_secondary', '' ) );

// Dynamic Social Links from Website Settings.
$edupro_social_defs = array(
    'social_facebook'  => array(
        'label' => 'Facebook',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>',
    ),
    'social_instagram' => array(
        'label' => 'Instagram',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>',
    ),
    'social_youtube'   => array(
        'label' => 'YouTube',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="#1a1a2e"></polygon></svg>',
    ),
    'social_linkedin'  => array(
        'label' => 'LinkedIn',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>',
    ),
    'social_twitter'   => array(
        'label' => 'X / Twitter',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>',
    ),
    'social_tiktok'    => array(
        'label' => 'TikTok',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"></path></svg>',
    ),
    'social_telegram'  => array(
        'label' => 'Telegram',
        'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.94 2.66a1.1 1.1 0 0 0-1.14-.14L2.46 10.04a1.07 1.07 0 0 0 .06 2l4.53 1.48 1.76 5.56a1.05 1.05 0 0 0 1.77.41l2.52-2.46 4.73 3.47a1.1 1.1 0 0 0 1.72-.66L22.28 3.74a1.1 1.1 0 0 0-.34-1.08zM9.72 13.78l-.67 3.01-1.06-3.36 9.54-6.4-7.81 6.75z"></path></svg>',
    ),
);

$edupro_configured_socials = array();
foreach ( $edupro_social_defs as $s_key => $s_meta ) {
    $s_url = trim( (string) $edupro_setting( $s_key, '' ) );
    if ( '' !== $s_url ) {
        $edupro_configured_socials[ $s_key ] = array(
            'url'   => $s_url,
            'label' => $s_meta['label'],
            'svg'   => $s_meta['svg'],
        );
    }
}
if ( empty( $edupro_configured_socials ) ) {
    $fallback_social_urls = array(
        'social_facebook'  => apply_filters( 'edupro_social_facebook', 'https://facebook.com' ),
        'social_twitter'   => apply_filters( 'edupro_social_twitter', 'https://x.com' ),
        'social_linkedin'  => apply_filters( 'edupro_social_linkedin', 'https://linkedin.com' ),
        'social_youtube'   => apply_filters( 'edupro_social_youtube', 'https://youtube.com' ),
        'social_instagram' => apply_filters( 'edupro_social_instagram', 'https://instagram.com' ),
    );
    foreach ( $fallback_social_urls as $s_key => $s_url ) {
        if ( ! empty( $s_url ) && isset( $edupro_social_defs[ $s_key ] ) ) {
            $edupro_configured_socials[ $s_key ] = array(
                'url'   => $s_url,
                'label' => $edupro_social_defs[ $s_key ]['label'],
                'svg'   => $edupro_social_defs[ $s_key ]['svg'],
            );
        }
    }
}

$edupro_footer_cats = get_terms( array(
    'taxonomy'   => 'course_category',
    'hide_empty' => false,
    'number'     => 6,
    'orderby'    => 'count',
    'order'      => 'DESC',
) );
if ( is_wp_error( $edupro_footer_cats ) || ! is_array( $edupro_footer_cats ) ) {
    $edupro_footer_cats = array();
}
?>

    </div><!-- #content -->

    <footer id="colophon" class="site-footer edupro-footer" role="contentinfo">
        <!-- 4-Column Widget Footer -->
        <div class="edupro-footer__top">
            <div class="edupro-container container">
                <div class="edupro-footer__grid">

                    <!-- Column 1: Brand & About (footer-widget-1) -->
                    <div class="edupro-footer__col">
                        <?php if ( is_active_sidebar( 'footer-widget-1' ) ) : ?>
                            <?php dynamic_sidebar( 'footer-widget-1' ); ?>
                        <?php else : ?>
                            <div class="edupro-footer__brand">
                                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-footer__logo">
                                    <?php if ( '' !== $edupro_footer_logo_url ) : ?>
                                        <img src="<?php echo esc_url( $edupro_footer_logo_url ); ?>" alt="<?php echo esc_attr( $edupro_site_name ); ?>" class="edupro-footer__logo-img">
                                    <?php elseif ( has_custom_logo() ) : ?>
                                        <?php
                                        $custom_logo_id  = (int) get_theme_mod( 'custom_logo' );
                                        $custom_logo_url = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
                                        if ( $custom_logo_url ) :
                                            ?>
                                            <img src="<?php echo esc_url( $custom_logo_url ); ?>" alt="<?php echo esc_attr( $edupro_site_name ); ?>" class="edupro-footer__logo-img">
                                        <?php else : ?>
                                            <span><?php echo esc_html( $edupro_site_name ); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="edupro-logo-icon" aria-hidden="true">
                                            <svg width="32" height="32" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="36" height="36" rx="8" fill="#e31b23"/>
                                                <path d="M18 8L6 14.5L18 21L30 14.5L18 8Z" fill="#ffffff"/>
                                                <path d="M10 17.5V24.5C10 26.2 13.6 28 18 28C22.4 28 26 26.2 26 24.5V17.5L18 21.8L10 17.5Z" fill="#ffffff" fill-opacity="0.88"/>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html( $edupro_site_name ); ?></span>
                                    <?php endif; ?>
                                </a>
                                <p class="edupro-footer__desc">
                                    <?php echo esc_html( $edupro_footer_about ); ?>
                                </p>
                                <?php if ( '' !== $edupro_footer_trust ) : ?>
                                    <div class="edupro-footer__trust-badge">
                                        <span>★ 4.8/5</span>
                                        <span><?php echo esc_html( $edupro_footer_trust ); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Column 2: Course Categories (footer-widget-2) -->
                    <div class="edupro-footer__col">
                        <?php if ( is_active_sidebar( 'footer-widget-2' ) ) : ?>
                            <?php dynamic_sidebar( 'footer-widget-2' ); ?>
                        <?php elseif ( has_nav_menu( 'footer-1' ) ) : ?>
                            <h4 class="widget-title"><?php esc_html_e( 'Top Categories', 'edupro' ); ?></h4>
                            <?php
                            wp_nav_menu( array(
                                'theme_location' => 'footer-1',
                                'menu_class'     => 'edupro-footer__menu',
                                'container'      => false,
                                'depth'          => 1,
                            ) );
                            ?>
                        <?php else : ?>
                            <h4 class="widget-title"><?php esc_html_e( 'Course Categories', 'edupro' ); ?></h4>
                            <ul class="edupro-footer__menu">
                                <?php if ( ! empty( $edupro_footer_cats ) ) : ?>
                                    <?php foreach ( $edupro_footer_cats as $f_cat ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_term_link( $f_cat ) ); ?>">
                                                <?php echo esc_html( $f_cat->name ); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li><a href="<?php echo esc_url( $edupro_courses_url ); ?>"><?php esc_html_e( 'All Courses', 'edupro' ); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Column 3: Quick Links (footer-widget-3) -->
                    <div class="edupro-footer__col">
                        <?php if ( is_active_sidebar( 'footer-widget-3' ) ) : ?>
                            <?php dynamic_sidebar( 'footer-widget-3' ); ?>
                        <?php elseif ( has_nav_menu( 'footer-2' ) ) : ?>
                            <h4 class="widget-title"><?php esc_html_e( 'Quick Links', 'edupro' ); ?></h4>
                            <?php
                            wp_nav_menu( array(
                                'theme_location' => 'footer-2',
                                'menu_class'     => 'edupro-footer__menu',
                                'container'      => false,
                                'depth'          => 1,
                            ) );
                            ?>
                        <?php else : ?>
                            <h4 class="widget-title"><?php esc_html_e( 'Quick Links', 'edupro' ); ?></h4>
                            <ul class="edupro-footer__menu">
                                <li><a href="<?php echo esc_url( $edupro_courses_url ); ?>"><?php esc_html_e( 'Browse Courses', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( $edupro_dashboard_url ); ?>"><?php esc_html_e( 'Student Dashboard', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( $edupro_login_url ); ?>"><?php esc_html_e( 'Student Login', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( $edupro_register_url ); ?>"><?php esc_html_e( 'Create Account', 'edupro' ); ?></a></li>
                                <?php
                                $privacy_url = get_privacy_policy_url();
                                if ( $privacy_url ) :
                                    ?>
                                    <li><a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'edupro' ); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Column 4: Support & Contact (footer-widget-4) -->
                    <div class="edupro-footer__col">
                        <?php if ( is_active_sidebar( 'footer-widget-4' ) ) : ?>
                            <?php dynamic_sidebar( 'footer-widget-4' ); ?>
                        <?php else : ?>
                            <h4 class="widget-title"><?php esc_html_e( 'Contact & Support', 'edupro' ); ?></h4>
                            <ul class="edupro-footer__contact">
                                <?php if ( $edupro_phone_href ) : ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                        <span>
                                            <a href="<?php echo esc_url( $edupro_phone_href ); ?>"><?php echo esc_html( $edupro_phone_primary ); ?></a>
                                            <?php if ( $edupro_phone2_href ) : ?>
                                                <br><a href="<?php echo esc_url( $edupro_phone2_href ); ?>"><?php echo esc_html( $edupro_phone_secondary ); ?></a>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endif; ?>

                                <?php if ( $edupro_whatsapp_url ) : ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.25-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.49-8.41zM12.06 21.8h-.01a9.87 9.87 0 0 1-5.04-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.86 9.86 0 0 1-1.52-5.24c0-5.45 4.43-9.88 9.89-9.88 2.64 0 5.12 1.03 6.98 2.9a9.82 9.82 0 0 1 2.9 6.99c0 5.45-4.44 9.84-9.88 9.84z"/>
                                        </svg>
                                        <a href="<?php echo esc_url( $edupro_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e( 'Chat on WhatsApp', 'edupro' ); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ( '' !== $edupro_email_support ) : ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                        <a href="mailto:<?php echo esc_attr( $edupro_email_support ); ?>">
                                            <?php echo esc_html( $edupro_email_support ); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ( '' !== $edupro_addr_primary ) : ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span>
                                            <?php echo esc_html( $edupro_addr_primary ); ?>
                                            <?php if ( '' !== $edupro_addr_secondary ) : ?>
                                                <br><?php echo esc_html( $edupro_addr_secondary ); ?>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endif; ?>

                                <?php if ( '' !== $edupro_business_hours ) : ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                        <span><?php echo esc_html( $edupro_business_hours ); ?></span>
                                    </li>
                                <?php endif; ?>

                                <li>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    <span><?php esc_html_e( '100% Secure SSL Checkout', 'edupro' ); ?></span>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div><!-- .edupro-footer__grid -->
            </div><!-- .edupro-container -->
        </div><!-- .edupro-footer__top -->

        <!-- Bottom Bar: Copyright, Inline SVG Payment Icons, Social Icons -->
        <div class="edupro-footer__bottom">
            <div class="edupro-container container edupro-footer__bottom-inner">

                <!-- Copyright -->
                <div class="edupro-footer__copyright">
                    <?php echo wp_kses_post( $edupro_copyright ); ?>
                </div>

                <!-- Inline SVG Payment Icons -->
                <div class="edupro-footer__payments" aria-label="<?php esc_attr_e( 'Accepted Payment Methods', 'edupro' ); ?>">
                    <!-- Cashfree -->
                    <span class="edupro-payment-icon" title="Cashfree">
                        <svg width="62" height="28" viewBox="0 0 62 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cashfree">
                            <rect width="62" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <text x="31" y="17.5" text-anchor="middle" fill="#4ade80" font-family="Inter,sans-serif" font-weight="700" font-size="9">Cashfree</text>
                        </svg>
                    </span>
                    <!-- Razorpay -->
                    <span class="edupro-payment-icon" title="Razorpay">
                        <svg width="64" height="28" viewBox="0 0 64 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Razorpay">
                            <rect width="64" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <path d="M13 19L16 9H19L15.5 19H13Z" fill="#3395FF"/>
                            <text x="40" y="17.5" text-anchor="middle" fill="#ffffff" font-family="Inter,sans-serif" font-weight="700" font-size="9">Razorpay</text>
                        </svg>
                    </span>
                    <!-- UPI -->
                    <span class="edupro-payment-icon" title="UPI">
                        <svg width="44" height="28" viewBox="0 0 44 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="UPI">
                            <rect width="44" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <text x="22" y="18" text-anchor="middle" fill="#2ecc71" font-family="Poppins,sans-serif" font-weight="800" font-size="11">UPI</text>
                        </svg>
                    </span>
                    <!-- Visa -->
                    <span class="edupro-payment-icon" title="Visa">
                        <svg width="46" height="28" viewBox="0 0 46 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Visa">
                            <rect width="46" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <text x="23" y="18" text-anchor="middle" fill="#ffffff" font-family="Poppins,sans-serif" font-weight="800" font-style="italic" font-size="11" letter-spacing="0.5">VISA</text>
                        </svg>
                    </span>
                    <!-- Mastercard -->
                    <span class="edupro-payment-icon" title="Mastercard">
                        <svg width="46" height="28" viewBox="0 0 46 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mastercard">
                            <rect width="46" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <circle cx="19" cy="14" r="7" fill="#EB001B"/>
                            <circle cx="27" cy="14" r="7" fill="#F79E1B" fill-opacity="0.9"/>
                        </svg>
                    </span>
                    <!-- Stripe -->
                    <span class="edupro-payment-icon" title="Stripe">
                        <svg width="50" height="28" viewBox="0 0 50 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Stripe">
                            <rect width="50" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <text x="25" y="17.5" text-anchor="middle" fill="#a29bfe" font-family="Poppins,sans-serif" font-weight="700" font-size="10">stripe</text>
                        </svg>
                    </span>
                    <!-- PayPal -->
                    <span class="edupro-payment-icon" title="PayPal">
                        <svg width="56" height="28" viewBox="0 0 56 28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="PayPal">
                            <rect width="56" height="28" rx="4" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.2"/>
                            <text x="28" y="17.5" text-anchor="middle" fill="#64B5F6" font-family="Poppins,sans-serif" font-weight="700" font-style="italic" font-size="10">PayPal</text>
                        </svg>
                    </span>
                </div>

                <!-- Dynamic Social Icons -->
                <?php if ( ! empty( $edupro_configured_socials ) ) : ?>
                    <div class="edupro-footer__socials" aria-label="<?php esc_attr_e( 'Social Media Links', 'edupro' ); ?>">
                        <?php foreach ( $edupro_configured_socials as $social_item ) : ?>
                            <a href="<?php echo esc_url( $social_item['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $social_item['label'] ); ?>">
                                <?php echo $social_item['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div><!-- .edupro-footer__bottom -->
    </footer>

</div><!-- #page -->

<!-- Mini-Cart Slide-Out Drawer (SSR pre-populated from inc/cart.php) -->
<?php
$edupro_cart_summary = function_exists( 'edupro_get_cart_summary' )
    ? edupro_get_cart_summary()
    : array(
        'items'              => array(),
        'count'              => 0,
        'formatted_subtotal' => function_exists( 'edupro_format_price' ) ? edupro_format_price( 0 ) : '₹0.00',
        'cart_url'           => function_exists( 'edupro_get_cart_url' ) ? edupro_get_cart_url() : home_url( '/cart/' ),
        'checkout_url'       => add_query_arg( 'mode', 'cart', home_url( '/checkout/' ) ),
    );
$edupro_drawer_items        = ! empty( $edupro_cart_summary['items'] ) && is_array( $edupro_cart_summary['items'] ) ? $edupro_cart_summary['items'] : array();
$edupro_drawer_count        = isset( $edupro_cart_summary['count'] ) ? (int) $edupro_cart_summary['count'] : count( $edupro_drawer_items );
$edupro_drawer_subtotal     = ! empty( $edupro_cart_summary['formatted_subtotal'] ) ? $edupro_cart_summary['formatted_subtotal'] : '₹0.00';
$edupro_drawer_cart_url     = ! empty( $edupro_cart_summary['cart_url'] ) ? $edupro_cart_summary['cart_url'] : home_url( '/cart/' );
$edupro_drawer_checkout_url = ! empty( $edupro_cart_summary['checkout_url'] ) ? $edupro_cart_summary['checkout_url'] : add_query_arg( 'mode', 'cart', home_url( '/checkout/' ) );
?>
<div id="edupro-cart-overlay" class="edupro-cart-overlay" aria-hidden="true"></div>
<aside
    id="edupro-cart-drawer"
    class="edupro-cart-drawer"
    role="dialog"
    aria-modal="true"
    aria-label="<?php esc_attr_e( 'Shopping Cart', 'edupro' ); ?>"
    aria-hidden="true"
>
    <div class="edupro-cart-drawer__header">
        <h3 class="edupro-cart-drawer__title">
            <?php esc_html_e( 'Shopping Cart', 'edupro' ); ?>
            (<span id="edupro-cart-drawer-count"><?php echo absint( $edupro_drawer_count ); ?></span>)
        </h3>
        <button
            type="button"
            id="edupro-cart-drawer-close"
            class="edupro-cart-drawer__close"
            aria-label="<?php esc_attr_e( 'Close shopping cart', 'edupro' ); ?>"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <div id="edupro-cart-drawer-body" class="edupro-cart-drawer__body">
        <?php if ( ! empty( $edupro_drawer_items ) ) : ?>
            <?php foreach ( $edupro_drawer_items as $c_item ) : ?>
                <div class="edupro-cart-drawer__item" data-course-id="<?php echo absint( $c_item['course_id'] ); ?>">
                    <a href="<?php echo esc_url( $c_item['permalink'] ); ?>" class="edupro-cart-drawer__thumb">
                        <?php if ( ! empty( $c_item['thumbnail'] ) ) : ?>
                            <img src="<?php echo esc_url( $c_item['thumbnail'] ); ?>" alt="<?php echo esc_attr( $c_item['title'] ); ?>" loading="lazy">
                        <?php else : ?>
                            <span class="edupro-cart-drawer__thumb-placeholder" aria-hidden="true">🎓</span>
                        <?php endif; ?>
                    </a>
                    <div class="edupro-cart-drawer__info">
                        <a href="<?php echo esc_url( $c_item['permalink'] ); ?>" class="edupro-cart-drawer__item-title">
                            <?php echo esc_html( $c_item['title'] ); ?>
                        </a>
                        <?php if ( ! empty( $c_item['instructor'] ) ) : ?>
                            <span class="edupro-cart-drawer__item-instructor"><?php echo esc_html( $c_item['instructor'] ); ?></span>
                        <?php endif; ?>
                        <div class="edupro-cart-drawer__item-price">
                            <strong><?php echo esc_html( $c_item['formatted_effective'] ); ?></strong>
                            <?php if ( ! empty( $c_item['discount_pct'] ) && ! empty( $c_item['formatted_regular'] ) ) : ?>
                                <del><?php echo esc_html( $c_item['formatted_regular'] ); ?></del>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="edupro-cart-remove-btn edupro-cart-drawer__remove"
                        data-course-id="<?php echo absint( $c_item['course_id'] ); ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from cart', 'edupro' ), $c_item['title'] ) ); ?>"
                        title="<?php esc_attr_e( 'Remove', 'edupro' ); ?>"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="edupro-cart-drawer__empty">
                <span class="edupro-cart-drawer__empty-icon" aria-hidden="true">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </span>
                <h4><?php esc_html_e( 'Your cart is empty', 'edupro' ); ?></h4>
                <p><?php esc_html_e( 'Explore our accredited courses and add them to your cart to enroll together.', 'edupro' ); ?></p>
                <a href="<?php echo esc_url( $edupro_courses_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary">
                    <?php esc_html_e( 'Browse Courses', 'edupro' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div id="edupro-cart-drawer-footer" class="edupro-cart-drawer__footer"<?php echo 0 === $edupro_drawer_count ? ' hidden' : ''; ?>>
        <div class="edupro-cart-drawer__subtotal-row">
            <span><?php esc_html_e( 'Subtotal', 'edupro' ); ?></span>
            <strong id="edupro-cart-drawer-subtotal"><?php echo esc_html( $edupro_drawer_subtotal ); ?></strong>
        </div>
        <div class="edupro-cart-drawer__actions">
            <a href="<?php echo esc_url( $edupro_drawer_checkout_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                <?php esc_html_e( 'Proceed to Checkout →', 'edupro' ); ?>
            </a>
            <a href="<?php echo esc_url( $edupro_drawer_cart_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-btn--full">
                <?php esc_html_e( 'View Cart', 'edupro' ); ?>
            </a>
        </div>
    </div>
</aside>

<!-- Floating WhatsApp & Call Quick Widget -->
<?php if ( $edupro_whatsapp_floating && ( $edupro_whatsapp_url || $edupro_phone_href ) ) : ?>
    <div class="edupro-floating-contact" aria-label="<?php esc_attr_e( 'Quick Contact', 'edupro' ); ?>">
        <?php if ( $edupro_whatsapp_url ) : ?>
            <a
                href="<?php echo esc_url( $edupro_whatsapp_url ); ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="edupro-float-btn edupro-float-btn--whatsapp"
                aria-label="<?php esc_attr_e( 'Chat with us on WhatsApp', 'edupro' ); ?>"
                title="<?php esc_attr_e( 'WhatsApp Support', 'edupro' ); ?>"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.25-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.49-8.41zM12.06 21.8h-.01a9.87 9.87 0 0 1-5.04-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.86 9.86 0 0 1-1.52-5.24c0-5.45 4.43-9.88 9.89-9.88 2.64 0 5.12 1.03 6.98 2.9a9.82 9.82 0 0 1 2.9 6.99c0 5.45-4.44 9.84-9.88 9.84zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z"/>
                </svg>
                <span><?php esc_html_e( 'WhatsApp', 'edupro' ); ?></span>
            </a>
        <?php endif; ?>
        <?php if ( $edupro_phone_href ) : ?>
            <a
                href="<?php echo esc_url( $edupro_phone_href ); ?>"
                class="edupro-float-btn edupro-float-btn--phone"
                aria-label="<?php esc_attr_e( 'Call us', 'edupro' ); ?>"
                title="<?php echo esc_attr( $edupro_phone_primary ); ?>"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span><?php esc_html_e( 'Call Us', 'edupro' ); ?></span>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Toast Notification Container -->
<div id="edupro-toast-container" class="edupro-toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- Generic Modal Overlay -->
<div id="edupro-modal" class="edupro-modal" role="dialog" aria-modal="true" hidden>
    <div class="edupro-modal__backdrop" data-modal-close></div>
    <div class="edupro-modal__dialog">
        <button type="button" class="edupro-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Close dialog', 'edupro' ); ?>">&times;</button>
        <div class="edupro-modal__content"></div>
    </div>
</div>

<!-- Scroll-to-Top Button -->
<button type="button" id="edupro-scroll-top" class="edupro-scroll-top" aria-label="<?php esc_attr_e( 'Scroll back to top', 'edupro' ); ?>">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>

<?php wp_footer(); ?>
</body>
</html>
