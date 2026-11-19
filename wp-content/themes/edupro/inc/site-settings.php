<?php
/**
 * EduPro LMS - Website Settings (Admin Dashboard + Helpers + Shortcodes)
 *
 * Provides a centralized, code-free WordPress Admin panel ("Website Settings")
 * to manage branding, logos, brand colors, announcement bar, phone/WhatsApp,
 * emails, office addresses, Google Maps embeds, social links, and footer legal text.
 *
 * Option Key: edupro_site_settings (array)
 *
 * @package EduPro
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * 1. DEFAULT SETTINGS & HELPER FUNCTIONS
 * ========================================================================== */

/**
 * Return the default 8 prestigious accreditation & partner brands.
 *
 * @return array<int, array{name:string, sublabel:string, badge_color:string, logo_url:string}>
 */
function edupro_get_default_trusted_brands() {
    return array(
        array(
            'name'        => 'Pearson',
            'sublabel'    => 'Accredited Partner',
            'badge_color' => '#005a70',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'edexcel',
            'sublabel'    => 'Approved Centre',
            'badge_color' => '#0f172a',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'QUALITY LICENCE',
            'sublabel'    => 'SCHEME ENDORSED',
            'badge_color' => '#be123c',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'RoSPA',
            'sublabel'    => 'Member',
            'badge_color' => '#1d4ed8',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'The CPD',
            'sublabel'    => 'Certification Service',
            'badge_color' => '#047857',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'NCFE',
            'sublabel'    => 'CACHE Endorsed',
            'badge_color' => '#6d28d9',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'IOSH',
            'sublabel'    => 'Approved',
            'badge_color' => '#b45309',
            'logo_url'    => '',
        ),
        array(
            'name'        => 'NHS',
            'sublabel'    => 'Accepted Training',
            'badge_color' => '#005eb8',
            'logo_url'    => '',
        ),
    );
}

/**
 * Return the default values for EduPro Website Settings.
 *
 * @return array<string, mixed>
 */
function edupro_get_site_settings_defaults() {
    $wp_name         = get_bloginfo( 'name' );
    $wp_description  = get_bloginfo( 'description' );
    $custom_logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
    $custom_logo_url = $custom_logo_id ? (string) wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';

    return array(
        // Tab 1: Brand & Identity
        'site_name'                    => $wp_name ? $wp_name : 'EduPro LMS',
        'site_tagline'                 => $wp_description ? $wp_description : __( 'Accredited Online Courses & Certifications', 'edupro' ),
        'header_logo_id'               => $custom_logo_id,
        'header_logo_url'              => $custom_logo_url,
        'footer_logo_id'               => 0,
        'footer_logo_url'              => '',
        'primary_color'                => get_theme_mod( 'edupro_primary_color', '#be123c' ),
        'announcement_enabled'         => '1',
        'announcement_text'            => __( 'Special Offer: Use code WELCOME20 at checkout for 20% off accredited courses!', 'edupro' ),
        'announcement_cta_text'        => __( 'Explore Courses', 'edupro' ),
        'announcement_cta_url'         => home_url( '/courses/' ),

        // Tab 2: Contact, Phone & WhatsApp
        'phone_primary'                => '',
        'phone_secondary'              => '',
        'whatsapp_number'              => '',
        'whatsapp_message'             => __( 'Hello! I would like to learn more about your courses.', 'edupro' ),
        'whatsapp_floating_enabled'    => '1',
        'email_support'                => (string) get_option( 'admin_email', '' ),
        'email_sales'                  => '',
        'business_hours'               => __( 'Mon – Sat: 9:00 AM – 7:00 PM', 'edupro' ),
        'address_primary'              => '',
        'address_secondary'            => '',

        // Tab 3: Google Maps
        'map_embed'                    => '',
        'map_directions_url'           => '',
        'map_height'                   => 380,

        // Tab 4: Social Links
        'social_facebook'              => '',
        'social_instagram'             => '',
        'social_youtube'               => '',
        'social_linkedin'              => '',
        'social_twitter'               => '',
        'social_tiktok'                => '',
        'social_telegram'              => '',

        // Tab 5: Footer & Legal
        'footer_about'                 => __( 'Empowering learners worldwide with career-focused, accredited online courses taught by industry experts.', 'edupro' ),
        'footer_trust_badge'           => __( 'CPD Accredited & ISO Certified Online Learning', 'edupro' ),
        'footer_copyright'             => '© {year} {site_name}. All rights reserved.',

        // Tab 6: Homepage & Carousels (v1.3.0)
        'hero_style'                   => 'glass_light',
        'hero_badge'                   => __( "UK & India's Leading Online Learning & CPD Provider", 'edupro' ),
        'hero_heading'                 => __( 'An Interactive Platform For', 'edupro' ),
        'hero_highlight_word'          => __( 'Learning', 'edupro' ),
        'hero_subtitle'                => __( 'Advance your career from anywhere in the world with industry-recognized certifications, expert tutor mentorship, and flexible self-paced study.', 'edupro' ),
        'hero_card_1_title'            => __( '1000+ Online Courses', 'edupro' ),
        'hero_card_1_desc'             => __( 'Explore accredited programmes across business, tech, healthcare, and design.', 'edupro' ),
        'hero_card_2_title'            => __( 'Lifetime Access', 'edupro' ),
        'hero_card_2_desc'             => __( 'Study at your own pace anytime, anywhere, on desktop, tablet, or mobile.', 'edupro' ),
        'hero_card_3_title'            => __( 'Accredited Certificates', 'edupro' ),
        'hero_card_3_desc'             => __( 'Earn globally recognized CPD & QLS certificates verified by employers.', 'edupro' ),

        'brands_enabled'               => '1',
        'brands_heading_prefix'        => __( 'Trusted by', 'edupro' ),
        'brands_heading_highlight'     => __( '300+ Leading Universities', 'edupro' ),
        'brands_heading_suffix'        => __( 'and Companies Globally', 'edupro' ),
        'trusted_brands'               => edupro_get_default_trusted_brands(),

        'spotlight_enabled'            => '1',
        'spotlight_badge'              => __( 'Alumni Spotlight', 'edupro' ),
        'spotlight_heading'            => __( 'Success Stories', 'edupro' ),
        'spotlight_text'               => __( 'Join thousands of graduates who transformed their careers through our accredited online programmes, practical assessments, and dedicated 1-to-1 tutor support.', 'edupro' ),
        'spotlight_text_2'             => __( 'Whether you are upskilling for a promotion, transitioning into a new industry, or launching your own practice, our internationally recognized qualifications open doors worldwide.', 'edupro' ),
        'spotlight_stat_1_val'         => '96%',
        'spotlight_stat_1_label'       => __( 'Career Advancement Rate', 'edupro' ),
        'spotlight_stat_2_val'         => '140+',
        'spotlight_stat_2_label'       => __( 'Countries Represented', 'edupro' ),
        'spotlight_video_url'          => '',
        'spotlight_video_thumb'        => '',
        'spotlight_video_name'         => 'OLIVIA SZULC',
        'spotlight_video_label'        => __( 'GRADUATE INTERVIEW', 'edupro' ),

        'giftcard_enabled'             => '1',
        'giftcard_eyebrow'             => __( 'SHARE KNOWLEDGE', 'edupro' ),
        'giftcard_heading'             => __( 'Order Gift Card for Your Loved Ones Today', 'edupro' ),
        'giftcard_desc'                => __( 'Give the gift of career growth and lifelong learning. Let friends, family, or team members unlock accredited courses at their own pace.', 'edupro' ),
        'giftcard_bullets'             => "Instant digital gift delivery by email\nRedeemable across 1,000+ accredited courses\nFlexible gift amounts with 12-month validity\nIncludes official CPD & QLS certification",
        'giftcard_value_label'         => '£25 – £150',
        'giftcard_cta_text'            => __( 'Order Gift Card', 'edupro' ),
        'giftcard_cta_url'             => home_url( '/courses/' ),

        // Tab 7: Single Course Layout (v1.3.0)
        'course_show_key_facts'        => '1',
        'course_show_at_a_glance'      => '1',
        'course_show_brands_carousel'  => '1',
        'course_show_google_reviews'   => '1',
        'course_show_help_card'        => '1',
        'course_show_gallery'          => '1',
        'course_default_awarding_body' => __( 'CPD Accredited & QLS Endorsed', 'edupro' ),
        'course_help_card_title'       => __( 'Need Guidance?', 'edupro' ),
        'course_help_card_desc'        => __( 'Speak with our friendly course advisors on phone or WhatsApp.', 'edupro' ),
    );
}

/**
 * Retrieve all EduPro Website Settings merged with defaults.
 *
 * Relies on WordPress's built-in option cache so changes made via
 * update_option('edupro_site_settings', ...) take effect immediately.
 *
 * @return array<string, mixed>
 */
function edupro_get_site_settings() {
    $defaults = edupro_get_site_settings_defaults();
    $saved    = get_option( 'edupro_site_settings', array() );

    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    $settings = wp_parse_args( $saved, $defaults );

    // Resolve attachment URLs if an attachment ID exists and URL was left blank.
    if ( ! empty( $settings['header_logo_id'] ) && empty( $settings['header_logo_url'] ) ) {
        $resolved = wp_get_attachment_image_url( absint( $settings['header_logo_id'] ), 'full' );
        if ( $resolved ) {
            $settings['header_logo_url'] = $resolved;
        }
    }

    if ( ! empty( $settings['footer_logo_id'] ) && empty( $settings['footer_logo_url'] ) ) {
        $resolved = wp_get_attachment_image_url( absint( $settings['footer_logo_id'] ), 'full' );
        if ( $resolved ) {
            $settings['footer_logo_url'] = $resolved;
        }
    }

    // Ensure trusted_brands is always a well-formed indexed array.
    if ( ! isset( $saved['trusted_brands'] ) || ! is_array( $saved['trusted_brands'] ) ) {
        $settings['trusted_brands'] = $defaults['trusted_brands'];
    } else {
        $settings['trusted_brands'] = array_values( $saved['trusted_brands'] );
    }

    // Ensure primary_color is always a valid hex color.
    $clean_color               = sanitize_hex_color( (string) $settings['primary_color'] );
    $settings['primary_color'] = $clean_color ? $clean_color : '#be123c';

    return $settings;
}

/**
 * Retrieve a single Website Setting by key with optional fallback default.
 *
 * Note: Boolean string values like '0' are preserved and NOT treated as empty.
 *
 * @param string $key     Setting key inside edupro_site_settings.
 * @param mixed  $default Fallback value if setting is unset or empty string.
 * @return mixed
 */
function edupro_get_site_setting( $key, $default = '' ) {
    $settings = edupro_get_site_settings();

    if ( array_key_exists( $key, $settings ) && '' !== $settings[ $key ] && null !== $settings[ $key ] ) {
        return $settings[ $key ];
    }

    return $default;
}

/**
 * Build a WhatsApp click-to-chat URL (https://wa.me/{digits}?text=...).
 *
 * @param string $custom_message Optional prefilled message; falls back to saved whatsapp_message.
 * @return string Empty string if no valid WhatsApp number is configured.
 */
function edupro_get_whatsapp_url( $custom_message = '' ) {
    $raw_number = (string) edupro_get_site_setting( 'whatsapp_number', '' );
    $digits     = preg_replace( '/[^0-9]/', '', $raw_number );

    if ( empty( $digits ) ) {
        return '';
    }

    $message = ( '' !== trim( (string) $custom_message ) )
        ? trim( (string) $custom_message )
        : (string) edupro_get_site_setting( 'whatsapp_message', '' );

    $url = 'https://wa.me/' . $digits;
    if ( '' !== $message ) {
        $url .= '?text=' . rawurlencode( $message );
    }

    return $url;
}

/**
 * Compute a slightly darkened hex color for hover states.
 *
 * @param string $hex     Hex color string (e.g. #e31b23).
 * @param int    $percent Percentage to darken (0-100).
 * @return string
 */
function edupro_darken_hex_color( $hex, $percent = 14 ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 3 === strlen( $hex ) ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
        return '#b8141b';
    }

    $factor = max( 0, min( 1, ( 100 - (int) $percent ) / 100 ) );
    $r      = (int) round( hexdec( substr( $hex, 0, 2 ) ) * $factor );
    $g      = (int) round( hexdec( substr( $hex, 2, 2 ) ) * $factor );
    $b      = (int) round( hexdec( substr( $hex, 4, 2 ) ) * $factor );

    return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

/**
 * Parse a Google Maps iframe snippet, direct embed URL, or plain address
 * into a safe Google Maps embed URL.
 *
 * @param string $raw Raw admin input.
 * @return string Safe https:// embed URL, or empty string if input is empty.
 */
function edupro_parse_google_map_embed_url( $raw ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }

    // Case 1: Full <iframe ... src="..."> embed HTML.
    if ( false !== stripos( $raw, '<iframe' ) ) {
        if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $raw, $matches ) ) {
            $raw = html_entity_decode( trim( $matches[1] ), ENT_QUOTES, 'UTF-8' );
        } else {
            return '';
        }
    }

    // Case 2: Direct URL.
    if ( preg_match( '#^https?://#i', $raw ) ) {
        $parsed = wp_parse_url( $raw );
        $host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';
        $query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

        // Allow Google Maps hosts (google.com, google.co.uk, google.co.in, maps.google.*, etc.).
        if ( preg_match( '/(^|\.)google\.[a-z.]{2,}$/i', $host ) ) {
            // Already an embed endpoint or output=embed URL.
            if ( false !== stripos( $path, '/maps/embed' ) || false !== stripos( $query, 'output=embed' ) ) {
                return esc_url_raw( preg_replace( '#^http://#i', 'https://', $raw ) );
            }

            // Regular Google Maps link (e.g. /maps/place/Name or ?q=...) -> convert to embed query.
            parse_str( $query, $query_vars );
            if ( ! empty( $query_vars['q'] ) ) {
                return 'https://maps.google.com/maps?q=' . rawurlencode( (string) $query_vars['q'] ) . '&t=&z=14&ie=UTF8&iwloc=&output=embed';
            }
            if ( preg_match( '#/maps/place/([^/@]+)#i', $path, $place_match ) ) {
                $place = urldecode( str_replace( '+', ' ', $place_match[1] ) );
                return 'https://maps.google.com/maps?q=' . rawurlencode( $place ) . '&t=&z=14&ie=UTF8&iwloc=&output=embed';
            }

            // Fallback: append output=embed cleanly.
            return esc_url_raw( add_query_arg( 'output', 'embed', preg_replace( '#^http://#i', 'https://', $raw ) ) );
        }

        // Reject arbitrary non-Google iframe hosts for security.
        return '';
    }

    // Case 3: Plain address or place name (e.g. "London, UK" or "MG Road, Bengaluru").
    return 'https://maps.google.com/maps?q=' . rawurlencode( $raw ) . '&t=&z=14&ie=UTF8&iwloc=&output=embed';
}

/**
 * Return metadata + crisp inline SVG for supported social networks.
 *
 * @return array<string, array{label:string, key:string, svg:string}>
 */
function edupro_get_social_networks_meta() {
    return array(
        'facebook'  => array(
            'label' => __( 'Facebook', 'edupro' ),
            'key'   => 'social_facebook',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
        ),
        'instagram' => array(
            'label' => __( 'Instagram', 'edupro' ),
            'key'   => 'social_instagram',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
        ),
        'youtube'   => array(
            'label' => __( 'YouTube', 'edupro' ),
            'key'   => 'social_youtube',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>',
        ),
        'linkedin'  => array(
            'label' => __( 'LinkedIn', 'edupro' ),
            'key'   => 'social_linkedin',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
        ),
        'twitter'   => array(
            'label' => __( 'X (Twitter)', 'edupro' ),
            'key'   => 'social_twitter',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        ),
        'tiktok'    => array(
            'label' => __( 'TikTok', 'edupro' ),
            'key'   => 'social_tiktok',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>',
        ),
        'telegram'  => array(
            'label' => __( 'Telegram', 'edupro' ),
            'key'   => 'social_telegram',
            'svg'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 3.6a1.1 1.1 0 0 0-1.15-.17L2.6 10.5c-.82.32-.8 1.48.03 1.77l4.46 1.54 1.74 5.4c.23.71 1.12.92 1.64.38l2.53-2.6 4.76 3.5c.63.46 1.52.12 1.7-.64l3.15-15.05a1.1 1.1 0 0 0-.71-1.2zM8.7 13.1l9.2-5.7-7.1 7.1-.35 2.75-1.15-3.55-.6-.6z"/></svg>',
        ),
    );
}

/* ==========================================================================
 * 2. FRONTEND DYNAMIC PRIMARY BRAND COLOR CSS
 * ========================================================================== */

/**
 * Output dynamic CSS variables for the configured primary brand color in wp_head.
 * Runs at priority 20 so it overrides functions.php's default Customizer style tag.
 */
function edupro_site_settings_dynamic_css() {
    $primary = sanitize_hex_color( (string) edupro_get_site_setting( 'primary_color', '#be123c' ) );
    if ( ! $primary ) {
        $primary = '#be123c';
    }
    $primary_dark = edupro_darken_hex_color( $primary, 14 );

    echo '<style id="edupro-site-settings-css">:root{'
        . '--color-primary:' . esc_attr( $primary ) . ';'
        . '--edupro-primary:' . esc_attr( $primary ) . ';'
        . '--color-primary-dark:' . esc_attr( $primary_dark ) . ';'
        . '--edupro-primary-dark:' . esc_attr( $primary_dark ) . ';'
        . '}</style>' . "\n";
}
add_action( 'wp_head', 'edupro_site_settings_dynamic_css', 20 );

/* ==========================================================================
 * 3. WORDPRESS ADMIN MENU & ASSETS
 * ========================================================================== */

/**
 * Register the top-level "Website Settings" admin menu page.
 */
function edupro_register_site_settings_menu() {
    add_menu_page(
        __( 'Website Settings', 'edupro' ),
        __( 'Website Settings', 'edupro' ),
        'manage_options',
        'edupro-site-settings',
        'edupro_render_site_settings_page',
        'dashicons-admin-site-alt3',
        3
    );
}
add_action( 'admin_menu', 'edupro_register_site_settings_menu' );

/**
 * Enqueue WP Media Library and Color Picker assets on Website Settings page only.
 *
 * @param string $hook_suffix Current admin screen hook suffix.
 */
function edupro_enqueue_site_settings_assets( $hook_suffix ) {
    if ( 'toplevel_page_edupro-site-settings' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
}
add_action( 'admin_enqueue_scripts', 'edupro_enqueue_site_settings_assets' );

/* ==========================================================================
 * 4. SAVE & SANITIZE HANDLER
 * ========================================================================== */

/**
 * Handle saving Website Settings form submission with nonce, capability check,
 * full field sanitization, and WordPress core option syncing.
 */
function edupro_handle_save_site_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to modify website settings.', 'edupro' ), 403 );
    }

    check_admin_referer( 'edupro_save_site_settings', 'edupro_site_settings_nonce' );

    $raw = isset( $_POST['edupro_site_settings'] ) && is_array( $_POST['edupro_site_settings'] )
        ? wp_unslash( $_POST['edupro_site_settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        : array();

    $active_tab   = isset( $_POST['edupro_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['edupro_active_tab'] ) ) : 'brand';
    $allowed_tabs = array( 'brand', 'contact', 'maps', 'social', 'footer', 'homepage', 'course_layout' );
    if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
        $active_tab = 'brand';
    }

    // Sanitize color.
    $primary_color = isset( $raw['primary_color'] ) ? sanitize_hex_color( trim( (string) $raw['primary_color'] ) ) : '#be123c';
    if ( ! $primary_color ) {
        $primary_color = '#be123c';
    }

    // Sanitize header & footer logos.
    $header_logo_id  = isset( $raw['header_logo_id'] ) ? absint( $raw['header_logo_id'] ) : 0;
    $header_logo_url = isset( $raw['header_logo_url'] ) ? esc_url_raw( trim( (string) $raw['header_logo_url'] ) ) : '';
    if ( $header_logo_id && ! $header_logo_url ) {
        $header_logo_url = (string) wp_get_attachment_image_url( $header_logo_id, 'full' );
    }

    $footer_logo_id  = isset( $raw['footer_logo_id'] ) ? absint( $raw['footer_logo_id'] ) : 0;
    $footer_logo_url = isset( $raw['footer_logo_url'] ) ? esc_url_raw( trim( (string) $raw['footer_logo_url'] ) ) : '';
    if ( $footer_logo_id && ! $footer_logo_url ) {
        $footer_logo_url = (string) wp_get_attachment_image_url( $footer_logo_id, 'full' );
    }

    // Sanitize map_embed (allow safe <iframe> snippet, Google Maps URL, or plain address).
    $raw_map_embed = isset( $raw['map_embed'] ) ? trim( (string) $raw['map_embed'] ) : '';
    if ( false !== stripos( $raw_map_embed, '<iframe' ) ) {
        $allowed_iframe = array(
            'iframe' => array(
                'src'             => true,
                'width'           => true,
                'height'          => true,
                'style'           => true,
                'allowfullscreen' => true,
                'loading'         => true,
                'referrerpolicy'  => true,
                'title'           => true,
                'frameborder'     => true,
            ),
        );
        $clean_map_embed = trim( wp_kses( $raw_map_embed, $allowed_iframe ) );
    } elseif ( preg_match( '#^https?://#i', $raw_map_embed ) ) {
        $clean_map_embed = esc_url_raw( $raw_map_embed );
    } else {
        $clean_map_embed = sanitize_text_field( $raw_map_embed );
    }

    // Sanitize map height (clamped 200 - 900 px).
    $map_height = isset( $raw['map_height'] ) ? absint( $raw['map_height'] ) : 380;
    if ( $map_height < 200 ) {
        $map_height = 200;
    } elseif ( $map_height > 900 ) {
        $map_height = 900;
    }

    // Sanitize hero style.
    $hero_style = isset( $raw['hero_style'] ) ? sanitize_key( (string) $raw['hero_style'] ) : 'glass_light';
    if ( ! in_array( $hero_style, array( 'glass_light', 'dark_pro' ), true ) ) {
        $hero_style = 'glass_light';
    }

    // Sanitize Trusted Brands repeater array.
    $clean_brands = array();
    if ( isset( $raw['trusted_brands'] ) && is_array( $raw['trusted_brands'] ) ) {
        foreach ( $raw['trusted_brands'] as $brand_row ) {
            if ( ! is_array( $brand_row ) ) {
                continue;
            }
            $b_name     = isset( $brand_row['name'] ) ? sanitize_text_field( trim( (string) $brand_row['name'] ) ) : '';
            $b_sublabel = isset( $brand_row['sublabel'] ) ? sanitize_text_field( trim( (string) $brand_row['sublabel'] ) ) : '';
            $b_color    = isset( $brand_row['badge_color'] ) ? sanitize_hex_color( trim( (string) $brand_row['badge_color'] ) ) : '#be123c';
            $b_logo     = isset( $brand_row['logo_url'] ) ? esc_url_raw( trim( (string) $brand_row['logo_url'] ) ) : '';

            if ( '' === $b_name && '' === $b_logo ) {
                continue;
            }

            $clean_brands[] = array(
                'name'        => $b_name,
                'sublabel'    => $b_sublabel,
                'badge_color' => $b_color ? $b_color : '#be123c',
                'logo_url'    => $b_logo,
            );
        }
    } elseif ( empty( $raw['trusted_brands_submitted'] ) ) {
        // Preserve existing brands if the repeater was not part of the submission.
        $existing     = edupro_get_site_settings();
        $clean_brands = is_array( $existing['trusted_brands'] ) ? $existing['trusted_brands'] : edupro_get_default_trusted_brands();
    }

    $clean = array(
        // Tab 1: Brand & Identity
        'site_name'                    => isset( $raw['site_name'] ) ? sanitize_text_field( $raw['site_name'] ) : '',
        'site_tagline'                 => isset( $raw['site_tagline'] ) ? sanitize_text_field( $raw['site_tagline'] ) : '',
        'header_logo_id'               => $header_logo_id,
        'header_logo_url'              => $header_logo_url,
        'footer_logo_id'               => $footer_logo_id,
        'footer_logo_url'              => $footer_logo_url,
        'primary_color'                => $primary_color,
        'announcement_enabled'         => ! empty( $raw['announcement_enabled'] ) ? '1' : '0',
        'announcement_text'            => isset( $raw['announcement_text'] ) ? sanitize_text_field( $raw['announcement_text'] ) : '',
        'announcement_cta_text'        => isset( $raw['announcement_cta_text'] ) ? sanitize_text_field( $raw['announcement_cta_text'] ) : '',
        'announcement_cta_url'         => isset( $raw['announcement_cta_url'] ) ? esc_url_raw( trim( (string) $raw['announcement_cta_url'] ) ) : '',

        // Tab 2: Contact, Phone & WhatsApp
        'phone_primary'                => isset( $raw['phone_primary'] ) ? sanitize_text_field( $raw['phone_primary'] ) : '',
        'phone_secondary'              => isset( $raw['phone_secondary'] ) ? sanitize_text_field( $raw['phone_secondary'] ) : '',
        'whatsapp_number'              => isset( $raw['whatsapp_number'] ) ? sanitize_text_field( $raw['whatsapp_number'] ) : '',
        'whatsapp_message'             => isset( $raw['whatsapp_message'] ) ? sanitize_textarea_field( $raw['whatsapp_message'] ) : '',
        'whatsapp_floating_enabled'    => ! empty( $raw['whatsapp_floating_enabled'] ) ? '1' : '0',
        'email_support'                => isset( $raw['email_support'] ) ? sanitize_email( $raw['email_support'] ) : '',
        'email_sales'                  => isset( $raw['email_sales'] ) ? sanitize_email( $raw['email_sales'] ) : '',
        'business_hours'               => isset( $raw['business_hours'] ) ? sanitize_text_field( $raw['business_hours'] ) : '',
        'address_primary'              => isset( $raw['address_primary'] ) ? sanitize_textarea_field( $raw['address_primary'] ) : '',
        'address_secondary'            => isset( $raw['address_secondary'] ) ? sanitize_textarea_field( $raw['address_secondary'] ) : '',

        // Tab 3: Google Maps
        'map_embed'                    => $clean_map_embed,
        'map_directions_url'           => isset( $raw['map_directions_url'] ) ? esc_url_raw( trim( (string) $raw['map_directions_url'] ) ) : '',
        'map_height'                   => $map_height,

        // Tab 4: Social Links
        'social_facebook'              => isset( $raw['social_facebook'] ) ? esc_url_raw( trim( (string) $raw['social_facebook'] ) ) : '',
        'social_instagram'             => isset( $raw['social_instagram'] ) ? esc_url_raw( trim( (string) $raw['social_instagram'] ) ) : '',
        'social_youtube'               => isset( $raw['social_youtube'] ) ? esc_url_raw( trim( (string) $raw['social_youtube'] ) ) : '',
        'social_linkedin'              => isset( $raw['social_linkedin'] ) ? esc_url_raw( trim( (string) $raw['social_linkedin'] ) ) : '',
        'social_twitter'               => isset( $raw['social_twitter'] ) ? esc_url_raw( trim( (string) $raw['social_twitter'] ) ) : '',
        'social_tiktok'                => isset( $raw['social_tiktok'] ) ? esc_url_raw( trim( (string) $raw['social_tiktok'] ) ) : '',
        'social_telegram'              => isset( $raw['social_telegram'] ) ? esc_url_raw( trim( (string) $raw['social_telegram'] ) ) : '',

        // Tab 5: Footer & Legal
        'footer_about'                 => isset( $raw['footer_about'] ) ? sanitize_textarea_field( $raw['footer_about'] ) : '',
        'footer_trust_badge'           => isset( $raw['footer_trust_badge'] ) ? sanitize_text_field( $raw['footer_trust_badge'] ) : '',
        'footer_copyright'             => isset( $raw['footer_copyright'] ) ? wp_kses_post( $raw['footer_copyright'] ) : '',

        // Tab 6: Homepage & Carousels
        'hero_style'                   => $hero_style,
        'hero_badge'                   => isset( $raw['hero_badge'] ) ? sanitize_text_field( $raw['hero_badge'] ) : '',
        'hero_heading'                 => isset( $raw['hero_heading'] ) ? sanitize_text_field( $raw['hero_heading'] ) : '',
        'hero_highlight_word'          => isset( $raw['hero_highlight_word'] ) ? sanitize_text_field( $raw['hero_highlight_word'] ) : '',
        'hero_subtitle'                => isset( $raw['hero_subtitle'] ) ? sanitize_textarea_field( $raw['hero_subtitle'] ) : '',
        'hero_card_1_title'            => isset( $raw['hero_card_1_title'] ) ? sanitize_text_field( $raw['hero_card_1_title'] ) : '',
        'hero_card_1_desc'             => isset( $raw['hero_card_1_desc'] ) ? sanitize_text_field( $raw['hero_card_1_desc'] ) : '',
        'hero_card_2_title'            => isset( $raw['hero_card_2_title'] ) ? sanitize_text_field( $raw['hero_card_2_title'] ) : '',
        'hero_card_2_desc'             => isset( $raw['hero_card_2_desc'] ) ? sanitize_text_field( $raw['hero_card_2_desc'] ) : '',
        'hero_card_3_title'            => isset( $raw['hero_card_3_title'] ) ? sanitize_text_field( $raw['hero_card_3_title'] ) : '',
        'hero_card_3_desc'             => isset( $raw['hero_card_3_desc'] ) ? sanitize_text_field( $raw['hero_card_3_desc'] ) : '',

        'brands_enabled'               => ! empty( $raw['brands_enabled'] ) ? '1' : '0',
        'brands_heading_prefix'        => isset( $raw['brands_heading_prefix'] ) ? sanitize_text_field( $raw['brands_heading_prefix'] ) : '',
        'brands_heading_highlight'     => isset( $raw['brands_heading_highlight'] ) ? sanitize_text_field( $raw['brands_heading_highlight'] ) : '',
        'brands_heading_suffix'        => isset( $raw['brands_heading_suffix'] ) ? sanitize_text_field( $raw['brands_heading_suffix'] ) : '',
        'trusted_brands'               => $clean_brands,

        'spotlight_enabled'            => ! empty( $raw['spotlight_enabled'] ) ? '1' : '0',
        'spotlight_badge'              => isset( $raw['spotlight_badge'] ) ? sanitize_text_field( $raw['spotlight_badge'] ) : '',
        'spotlight_heading'            => isset( $raw['spotlight_heading'] ) ? sanitize_text_field( $raw['spotlight_heading'] ) : '',
        'spotlight_text'               => isset( $raw['spotlight_text'] ) ? sanitize_textarea_field( $raw['spotlight_text'] ) : '',
        'spotlight_text_2'             => isset( $raw['spotlight_text_2'] ) ? sanitize_textarea_field( $raw['spotlight_text_2'] ) : '',
        'spotlight_stat_1_val'         => isset( $raw['spotlight_stat_1_val'] ) ? sanitize_text_field( $raw['spotlight_stat_1_val'] ) : '',
        'spotlight_stat_1_label'       => isset( $raw['spotlight_stat_1_label'] ) ? sanitize_text_field( $raw['spotlight_stat_1_label'] ) : '',
        'spotlight_stat_2_val'         => isset( $raw['spotlight_stat_2_val'] ) ? sanitize_text_field( $raw['spotlight_stat_2_val'] ) : '',
        'spotlight_stat_2_label'       => isset( $raw['spotlight_stat_2_label'] ) ? sanitize_text_field( $raw['spotlight_stat_2_label'] ) : '',
        'spotlight_video_url'          => isset( $raw['spotlight_video_url'] ) ? esc_url_raw( trim( (string) $raw['spotlight_video_url'] ) ) : '',
        'spotlight_video_thumb'        => isset( $raw['spotlight_video_thumb'] ) ? esc_url_raw( trim( (string) $raw['spotlight_video_thumb'] ) ) : '',
        'spotlight_video_name'         => isset( $raw['spotlight_video_name'] ) ? sanitize_text_field( $raw['spotlight_video_name'] ) : '',
        'spotlight_video_label'        => isset( $raw['spotlight_video_label'] ) ? sanitize_text_field( $raw['spotlight_video_label'] ) : '',

        'giftcard_enabled'             => ! empty( $raw['giftcard_enabled'] ) ? '1' : '0',
        'giftcard_eyebrow'             => isset( $raw['giftcard_eyebrow'] ) ? sanitize_text_field( $raw['giftcard_eyebrow'] ) : '',
        'giftcard_heading'             => isset( $raw['giftcard_heading'] ) ? sanitize_text_field( $raw['giftcard_heading'] ) : '',
        'giftcard_desc'                => isset( $raw['giftcard_desc'] ) ? sanitize_textarea_field( $raw['giftcard_desc'] ) : '',
        'giftcard_bullets'             => isset( $raw['giftcard_bullets'] ) ? sanitize_textarea_field( $raw['giftcard_bullets'] ) : '',
        'giftcard_value_label'         => isset( $raw['giftcard_value_label'] ) ? sanitize_text_field( $raw['giftcard_value_label'] ) : '',
        'giftcard_cta_text'            => isset( $raw['giftcard_cta_text'] ) ? sanitize_text_field( $raw['giftcard_cta_text'] ) : '',
        'giftcard_cta_url'             => isset( $raw['giftcard_cta_url'] ) ? esc_url_raw( trim( (string) $raw['giftcard_cta_url'] ) ) : '',

        // Tab 7: Single Course Layout
        'course_show_key_facts'        => ! empty( $raw['course_show_key_facts'] ) ? '1' : '0',
        'course_show_at_a_glance'      => ! empty( $raw['course_show_at_a_glance'] ) ? '1' : '0',
        'course_show_brands_carousel'  => ! empty( $raw['course_show_brands_carousel'] ) ? '1' : '0',
        'course_show_google_reviews'   => ! empty( $raw['course_show_google_reviews'] ) ? '1' : '0',
        'course_show_help_card'        => ! empty( $raw['course_show_help_card'] ) ? '1' : '0',
        'course_show_gallery'          => ! empty( $raw['course_show_gallery'] ) ? '1' : '0',
        'course_default_awarding_body' => isset( $raw['course_default_awarding_body'] ) ? sanitize_text_field( $raw['course_default_awarding_body'] ) : '',
        'course_help_card_title'       => isset( $raw['course_help_card_title'] ) ? sanitize_text_field( $raw['course_help_card_title'] ) : '',
        'course_help_card_desc'        => isset( $raw['course_help_card_desc'] ) ? sanitize_textarea_field( $raw['course_help_card_desc'] ) : '',
    );

    update_option( 'edupro_site_settings', $clean );

    // Sync core WordPress options & theme mods so everything stays unified.
    if ( '' !== $clean['site_name'] ) {
        update_option( 'blogname', $clean['site_name'] );
    }
    update_option( 'blogdescription', $clean['site_tagline'] );

    if ( $clean['header_logo_id'] > 0 ) {
        set_theme_mod( 'custom_logo', $clean['header_logo_id'] );
    } elseif ( '' === $clean['header_logo_url'] ) {
        remove_theme_mod( 'custom_logo' );
    }

    set_theme_mod( 'edupro_primary_color', $clean['primary_color'] );

    $redirect_url = add_query_arg(
        array(
            'page'             => 'edupro-site-settings',
            'tab'              => $active_tab,
            'settings-updated' => '1',
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_edupro_save_site_settings', 'edupro_handle_save_site_settings' );

/* ==========================================================================
 * 5. ADMIN PAGE UI (7-TAB INTERFACE)
 * ========================================================================== */

/**
 * Render the Website Settings WP Admin page.
 */
function edupro_render_site_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings   = edupro_get_site_settings();
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $tabs       = array(
        'brand'         => array(
            'label' => __( 'Brand & Identity', 'edupro' ),
            'icon'  => 'dashicons-art',
        ),
        'contact'       => array(
            'label' => __( 'Contact, Phone & WhatsApp', 'edupro' ),
            'icon'  => 'dashicons-phone',
        ),
        'maps'          => array(
            'label' => __( 'Google Maps', 'edupro' ),
            'icon'  => 'dashicons-location-alt',
        ),
        'social'        => array(
            'label' => __( 'Social Links', 'edupro' ),
            'icon'  => 'dashicons-share',
        ),
        'footer'        => array(
            'label' => __( 'Footer & Legal', 'edupro' ),
            'icon'  => 'dashicons-editor-insertmore',
        ),
        'homepage'      => array(
            'label' => __( 'Homepage & Carousels', 'edupro' ),
            'icon'  => 'dashicons-slides',
        ),
        'course_layout' => array(
            'label' => __( 'Single Course Layout', 'edupro' ),
            'icon'  => 'dashicons-welcome-learn-more',
        ),
    );

    if ( ! array_key_exists( $active_tab, $tabs ) ) {
        $active_tab = 'brand';
    }

    $updated         = isset( $_GET['settings-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $map_preview_url = edupro_parse_google_map_embed_url( $settings['map_embed'] );
    ?>
    <div class="wrap edupro-ws-wrap">
        <div class="edupro-ws-header">
            <div class="edupro-ws-header__title">
                <h1>
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php esc_html_e( 'EduPro Website Settings', 'edupro' ); ?>
                </h1>
                <p><?php esc_html_e( 'Configure your entire website branding, contact details, WhatsApp button, Google Map, social links, and footer without touching code.', 'edupro' ); ?></p>
            </div>
            <div class="edupro-ws-header__shortcodes">
                <span class="edupro-ws-sc-label"><?php esc_html_e( 'Shortcodes:', 'edupro' ); ?></span>
                <code>[edupro_contact_info]</code>
                <code>[edupro_map]</code>
                <code>[edupro_social_links]</code>
                <code>[edupro_brands_carousel]</code>
            </div>
        </div>

        <?php if ( $updated ) : ?>
            <div class="notice notice-success is-dismissible edupro-ws-notice">
                <p><strong><?php esc_html_e( 'Settings saved!', 'edupro' ); ?></strong> <?php esc_html_e( 'Your website changes are now live across the header, footer, contact sections, and shortcodes.', 'edupro' ); ?></p>
            </div>
        <?php endif; ?>

        <nav class="edupro-ws-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Website Settings Sections', 'edupro' ); ?>">
            <?php foreach ( $tabs as $tab_key => $tab_info ) : ?>
                <button
                    type="button"
                    class="edupro-ws-tab <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>"
                    data-tab="<?php echo esc_attr( $tab_key ); ?>"
                    role="tab"
                    aria-selected="<?php echo $active_tab === $tab_key ? 'true' : 'false'; ?>"
                >
                    <span class="dashicons <?php echo esc_attr( $tab_info['icon'] ); ?>"></span>
                    <span><?php echo esc_html( $tab_info['label'] ); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="edupro-ws-form">
            <input type="hidden" name="action" value="edupro_save_site_settings" />
            <input type="hidden" name="edupro_active_tab" id="edupro_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
            <?php wp_nonce_field( 'edupro_save_site_settings', 'edupro_site_settings_nonce' ); ?>

            <!-- ==========================================================
                 TAB 1: BRAND & IDENTITY
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'brand' === $active_tab ? 'is-active' : ''; ?>" data-panel="brand">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Site Identity & Logos', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc"><?php esc_html_e( 'Updates your site title, tagline, and logos. Automatically stays in sync with WordPress Settings and the Customizer.', 'edupro' ); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_site_name"><?php esc_html_e( 'Website Name', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_site_name" name="edupro_site_settings[site_name]" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" placeholder="EduPro Academy" />
                                <p class="description"><?php esc_html_e( 'Synced with WordPress Site Title (blogname).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_site_tagline"><?php esc_html_e( 'Tagline / Subtitle', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_site_tagline" name="edupro_site_settings[site_tagline]" value="<?php echo esc_attr( $settings['site_tagline'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Accredited Online Courses & Certifications', 'edupro' ); ?>" />
                                <p class="description"><?php esc_html_e( 'Synced with WordPress Tagline (blogdescription).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Header Logo', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-media-picker" data-preview-bg="light">
                                    <div class="edupro-ws-media-preview <?php echo empty( $settings['header_logo_url'] ) ? 'is-empty' : ''; ?>">
                                        <?php if ( ! empty( $settings['header_logo_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $settings['header_logo_url'] ); ?>" alt="<?php esc_attr_e( 'Header logo preview', 'edupro' ); ?>" />
                                        <?php else : ?>
                                            <span class="edupro-ws-media-placeholder"><?php esc_html_e( 'No logo selected', 'edupro' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" class="edupro-ws-media-id" name="edupro_site_settings[header_logo_id]" value="<?php echo esc_attr( (string) absint( $settings['header_logo_id'] ) ); ?>" />
                                    <input type="url" class="regular-text edupro-ws-media-url" name="edupro_site_settings[header_logo_url]" value="<?php echo esc_url( $settings['header_logo_url'] ); ?>" placeholder="https://..." />
                                    <div class="edupro-ws-media-actions">
                                        <button type="button" class="button button-secondary edupro-ws-upload-btn"><?php esc_html_e( 'Select / Upload Logo', 'edupro' ); ?></button>
                                        <button type="button" class="button-link-delete edupro-ws-remove-btn" <?php echo empty( $settings['header_logo_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove Logo', 'edupro' ); ?></button>
                                    </div>
                                </div>
                                <p class="description"><?php esc_html_e( 'Recommended: transparent PNG or SVG (around 220×60px). Syncs automatically with WordPress Custom Logo.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Footer Logo (Light / Dark BG)', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-media-picker" data-preview-bg="dark">
                                    <div class="edupro-ws-media-preview edupro-ws-media-preview--dark <?php echo empty( $settings['footer_logo_url'] ) ? 'is-empty' : ''; ?>">
                                        <?php if ( ! empty( $settings['footer_logo_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $settings['footer_logo_url'] ); ?>" alt="<?php esc_attr_e( 'Footer logo preview', 'edupro' ); ?>" />
                                        <?php else : ?>
                                            <span class="edupro-ws-media-placeholder"><?php esc_html_e( 'Optional light logo', 'edupro' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" class="edupro-ws-media-id" name="edupro_site_settings[footer_logo_id]" value="<?php echo esc_attr( (string) absint( $settings['footer_logo_id'] ) ); ?>" />
                                    <input type="url" class="regular-text edupro-ws-media-url" name="edupro_site_settings[footer_logo_url]" value="<?php echo esc_url( $settings['footer_logo_url'] ); ?>" placeholder="https://..." />
                                    <div class="edupro-ws-media-actions">
                                        <button type="button" class="button button-secondary edupro-ws-upload-btn"><?php esc_html_e( 'Select Footer Logo', 'edupro' ); ?></button>
                                        <button type="button" class="button-link-delete edupro-ws-remove-btn" <?php echo empty( $settings['footer_logo_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove Logo', 'edupro' ); ?></button>
                                    </div>
                                </div>
                                <p class="description"><?php esc_html_e( 'Optional white/light logo shown in the dark footer. Falls back to Header Logo if left blank.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_primary_color"><?php esc_html_e( 'Primary Brand Color', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_primary_color" name="edupro_site_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" class="edupro-color-picker" data-default-color="#be123c" />
                                <p class="description"><?php esc_html_e( 'Applied globally via --color-primary and --edupro-primary CSS custom properties.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Top Announcement Bar', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Announcement Bar', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[announcement_enabled]" value="1" <?php checked( $settings['announcement_enabled'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Show promotional announcement bar at the top of the header', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_announcement_text"><?php esc_html_e( 'Announcement Message', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_announcement_text" name="edupro_site_settings[announcement_text]" value="<?php echo esc_attr( $settings['announcement_text'] ); ?>" class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_announcement_cta_text"><?php esc_html_e( 'CTA Link Text', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_announcement_cta_text" name="edupro_site_settings[announcement_cta_text]" value="<?php echo esc_attr( $settings['announcement_cta_text'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Explore Courses', 'edupro' ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_announcement_cta_url"><?php esc_html_e( 'CTA Link URL', 'edupro' ); ?></label></th>
                            <td>
                                <input type="url" id="edupro_announcement_cta_url" name="edupro_site_settings[announcement_cta_url]" value="<?php echo esc_url( $settings['announcement_cta_url'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( home_url( '/courses/' ) ); ?>" />
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 2: CONTACT, PHONE & WHATSAPP
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'contact' === $active_tab ? 'is-active' : ''; ?>" data-panel="contact">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Phone Numbers & WhatsApp Chat', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc"><?php esc_html_e( 'Shown in the header top bar, mobile menu drawer, footer, floating WhatsApp/Call widget, and [edupro_contact_info] shortcode.', 'edupro' ); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_phone_primary"><?php esc_html_e( 'Primary Phone Number', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_phone_primary" name="edupro_site_settings[phone_primary]" value="<?php echo esc_attr( $settings['phone_primary'] ); ?>" class="regular-text" placeholder="+91 98765 43210" />
                                <p class="description"><?php esc_html_e( 'Primary helpline (e.g. India or UK main number).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_phone_secondary"><?php esc_html_e( 'Secondary Phone Number', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_phone_secondary" name="edupro_site_settings[phone_secondary]" value="<?php echo esc_attr( $settings['phone_secondary'] ); ?>" class="regular-text" placeholder="+44 20 7946 0958" />
                                <p class="description"><?php esc_html_e( 'Optional regional / international helpline (e.g. UK or India office).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_whatsapp_number"><?php esc_html_e( 'WhatsApp Number', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_whatsapp_number" name="edupro_site_settings[whatsapp_number]" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" class="regular-text" placeholder="+91 98765 43210" />
                                <p class="description"><?php esc_html_e( 'Include country code (e.g. +91... or +44...). Used to generate wa.me click-to-chat links.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_whatsapp_message"><?php esc_html_e( 'Default WhatsApp Message', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_whatsapp_message" name="edupro_site_settings[whatsapp_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['whatsapp_message'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Pre-filled message when a visitor clicks the WhatsApp button.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Floating WhatsApp & Call Widget', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[whatsapp_floating_enabled]" value="1" <?php checked( $settings['whatsapp_floating_enabled'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display floating WhatsApp & Call quick-contact buttons on the website', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Emails, Business Hours & Offices', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_email_support"><?php esc_html_e( 'Support Email', 'edupro' ); ?></label></th>
                            <td>
                                <input type="email" id="edupro_email_support" name="edupro_site_settings[email_support]" value="<?php echo esc_attr( $settings['email_support'] ); ?>" class="regular-text" placeholder="support@example.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_email_sales"><?php esc_html_e( 'Admissions / Sales Email', 'edupro' ); ?></label></th>
                            <td>
                                <input type="email" id="edupro_email_sales" name="edupro_site_settings[email_sales]" value="<?php echo esc_attr( $settings['email_sales'] ); ?>" class="regular-text" placeholder="admissions@example.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_business_hours"><?php esc_html_e( 'Business Hours', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_business_hours" name="edupro_site_settings[business_hours]" value="<?php echo esc_attr( $settings['business_hours'] ); ?>" class="large-text" placeholder="Mon – Sat: 9:00 AM – 7:00 PM (IST / GMT)" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_address_primary"><?php esc_html_e( 'Primary Office Address', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_address_primary" name="edupro_site_settings[address_primary]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. India Office: 100 Feet Road, Indiranagar, Bengaluru 560038', 'edupro' ); ?>"><?php echo esc_textarea( $settings['address_primary'] ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_address_secondary"><?php esc_html_e( 'Secondary Office Address', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_address_secondary" name="edupro_site_settings[address_secondary]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. UK Office: 71-75 Shelton Street, Covent Garden, London WC2H 9JQ', 'edupro' ); ?>"><?php echo esc_textarea( $settings['address_secondary'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 3: GOOGLE MAPS
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'maps' === $active_tab ? 'is-active' : ''; ?>" data-panel="maps">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Google Maps Location Embed', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc">
                        <?php esc_html_e( 'Display your academy or office location anywhere using the', 'edupro' ); ?>
                        <code>[edupro_map]</code>
                        <?php esc_html_e( 'shortcode (already included on the Contact Us page).', 'edupro' ); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_map_embed"><?php esc_html_e( 'Map Embed Code, URL, or Address', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_map_embed" name="edupro_site_settings[map_embed]" rows="4" class="large-text code" placeholder="<?php esc_attr_e( 'Paste full Google Maps <iframe> code, an https://www.google.com/maps/embed... URL, OR simply type an address like "Covent Garden, London, UK"', 'edupro' ); ?>"><?php echo esc_textarea( $settings['map_embed'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Accepts any of three formats: (1) Google Maps Share → Embed <iframe> HTML, (2) direct embed URL, or (3) plain address/place name.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_map_directions_url"><?php esc_html_e( 'Google Maps Directions / Listing URL', 'edupro' ); ?></label></th>
                            <td>
                                <input type="url" id="edupro_map_directions_url" name="edupro_site_settings[map_directions_url]" value="<?php echo esc_url( $settings['map_directions_url'] ); ?>" class="large-text" placeholder="https://maps.google.com/?q=..." />
                                <p class="description"><?php esc_html_e( 'Optional button link shown beneath the map ("Get Directions").', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_map_height"><?php esc_html_e( 'Map Height (px)', 'edupro' ); ?></label></th>
                            <td>
                                <input type="number" id="edupro_map_height" name="edupro_site_settings[map_height]" value="<?php echo esc_attr( (string) absint( $settings['map_height'] ) ); ?>" min="200" max="900" step="10" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Default: 380px (allowed range: 200px – 900px).', 'edupro' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php if ( ! empty( $map_preview_url ) ) : ?>
                        <div class="edupro-ws-map-preview">
                            <h3><?php esc_html_e( 'Live Map Preview', 'edupro' ); ?></h3>
                            <iframe
                                src="<?php echo esc_url( $map_preview_url ); ?>"
                                width="100%"
                                height="280"
                                style="border:0;border-radius:10px;"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                                title="<?php esc_attr_e( 'Google Maps Preview', 'edupro' ); ?>"
                            ></iframe>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 4: SOCIAL LINKS
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'social' === $active_tab ? 'is-active' : ''; ?>" data-panel="social">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Social Media Profiles', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc">
                        <?php esc_html_e( 'Leave any network blank to hide its icon. Displayed in the footer, contact cards, and via', 'edupro' ); ?>
                        <code>[edupro_social_links]</code>.
                    </p>

                    <table class="form-table" role="presentation">
                        <?php
                        $social_fields = array(
                            'social_facebook'  => array( __( 'Facebook URL', 'edupro' ), 'https://facebook.com/yourpage' ),
                            'social_instagram' => array( __( 'Instagram URL', 'edupro' ), 'https://instagram.com/yourhandle' ),
                            'social_youtube'   => array( __( 'YouTube URL', 'edupro' ), 'https://youtube.com/@yourchannel' ),
                            'social_linkedin'  => array( __( 'LinkedIn URL', 'edupro' ), 'https://linkedin.com/company/yourcompany' ),
                            'social_twitter'   => array( __( 'X (Twitter) URL', 'edupro' ), 'https://x.com/yourhandle' ),
                            'social_tiktok'    => array( __( 'TikTok URL', 'edupro' ), 'https://tiktok.com/@yourhandle' ),
                            'social_telegram'  => array( __( 'Telegram URL', 'edupro' ), 'https://t.me/yourchannel' ),
                        );
                        foreach ( $social_fields as $field_key => $meta ) :
                            ?>
                            <tr>
                                <th scope="row"><label for="edupro_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $meta[0] ); ?></label></th>
                                <td>
                                    <input
                                        type="url"
                                        id="edupro_<?php echo esc_attr( $field_key ); ?>"
                                        name="edupro_site_settings[<?php echo esc_attr( $field_key ); ?>]"
                                        value="<?php echo esc_url( $settings[ $field_key ] ); ?>"
                                        class="large-text"
                                        placeholder="<?php echo esc_attr( $meta[1] ); ?>"
                                    />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 5: FOOTER & LEGAL
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'footer' === $active_tab ? 'is-active' : ''; ?>" data-panel="footer">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Footer Branding & Legal Notice', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_footer_about"><?php esc_html_e( 'Footer About Description', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_footer_about" name="edupro_site_settings[footer_about]" rows="4" class="large-text"><?php echo esc_textarea( $settings['footer_about'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Short academy bio displayed beneath the logo in the footer.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_footer_trust_badge"><?php esc_html_e( 'Trust / Accreditation Badge Text', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_footer_trust_badge" name="edupro_site_settings[footer_trust_badge]" value="<?php echo esc_attr( $settings['footer_trust_badge'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'CPD Accredited & ISO Certified Online Learning', 'edupro' ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_footer_copyright"><?php esc_html_e( 'Copyright Text', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_footer_copyright" name="edupro_site_settings[footer_copyright]" value="<?php echo esc_attr( $settings['footer_copyright'] ); ?>" class="large-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Supports dynamic placeholders:', 'edupro' ); ?>
                                    <code>{year}</code> <?php esc_html_e( '(current year)', 'edupro' ); ?> &amp;
                                    <code>{site_name}</code> <?php esc_html_e( '(website name)', 'edupro' ); ?>.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 6: HOMEPAGE & CAROUSELS (v1.3.0)
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'homepage' === $active_tab ? 'is-active' : ''; ?>" data-panel="homepage">

                <!-- 6A: Hero Section -->
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Hero Section (Luxury Glassmorphism / Dark Pro)', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc"><?php esc_html_e( 'Control your homepage hero visual style, headline, gradient highlight word, and 3 value-proposition glass cards.', 'edupro' ); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_hero_style"><?php esc_html_e( 'Hero Visual Style', 'edupro' ); ?></label></th>
                            <td>
                                <select id="edupro_hero_style" name="edupro_site_settings[hero_style]">
                                    <option value="glass_light" <?php selected( $settings['hero_style'], 'glass_light' ); ?>><?php esc_html_e( 'Luxury Glassmorphism (Light Ambient Rose)', 'edupro' ); ?></option>
                                    <option value="dark_pro" <?php selected( $settings['hero_style'], 'dark_pro' ); ?>><?php esc_html_e( 'Dark Pro Executive', 'edupro' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_hero_badge"><?php esc_html_e( 'Hero Eyebrow Badge', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_hero_badge" name="edupro_site_settings[hero_badge]" value="<?php echo esc_attr( $settings['hero_badge'] ); ?>" class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_hero_heading"><?php esc_html_e( 'Hero Heading Prefix', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_hero_heading" name="edupro_site_settings[hero_heading]" value="<?php echo esc_attr( $settings['hero_heading'] ); ?>" class="large-text" placeholder="An Interactive Platform For" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_hero_highlight_word"><?php esc_html_e( 'Gradient Highlight Word', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_hero_highlight_word" name="edupro_site_settings[hero_highlight_word]" value="<?php echo esc_attr( $settings['hero_highlight_word'] ); ?>" class="regular-text" placeholder="Learning" />
                                <p class="description"><?php esc_html_e( 'Displayed in the signature crimson-to-violet gradient right after the heading prefix.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_hero_subtitle"><?php esc_html_e( 'Hero Subtitle', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_hero_subtitle" name="edupro_site_settings[hero_subtitle]" rows="3" class="large-text"><?php echo esc_textarea( $settings['hero_subtitle'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <h3 class="edupro-ws-subhead"><?php esc_html_e( 'Hero Feature Glass Cards (3 Columns)', 'edupro' ); ?></h3>
                    <div class="edupro-ws-grid-3">
                        <?php for ( $i = 1; $i <= 3; $i++ ) : ?>
                            <div class="edupro-ws-mini-card">
                                <strong><?php echo esc_html( sprintf( __( 'Feature Card %d', 'edupro' ), $i ) ); ?></strong>
                                <p>
                                    <label for="edupro_hero_card_<?php echo esc_attr( (string) $i ); ?>_title"><?php esc_html_e( 'Title', 'edupro' ); ?></label>
                                    <input type="text" id="edupro_hero_card_<?php echo esc_attr( (string) $i ); ?>_title" name="edupro_site_settings[hero_card_<?php echo esc_attr( (string) $i ); ?>_title]" value="<?php echo esc_attr( (string) $settings[ 'hero_card_' . $i . '_title' ] ); ?>" class="widefat" />
                                </p>
                                <p>
                                    <label for="edupro_hero_card_<?php echo esc_attr( (string) $i ); ?>_desc"><?php esc_html_e( 'Description', 'edupro' ); ?></label>
                                    <input type="text" id="edupro_hero_card_<?php echo esc_attr( (string) $i ); ?>_desc" name="edupro_site_settings[hero_card_<?php echo esc_attr( (string) $i ); ?>_desc]" value="<?php echo esc_attr( (string) $settings[ 'hero_card_' . $i . '_desc' ] ); ?>" class="widefat" />
                                </p>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 6B: Trusted Brands Carousel -->
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Trusted Brands & Accreditation Carousel', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc">
                        <?php esc_html_e( 'Displayed on the homepage, optionally on single course pages, and via', 'edupro' ); ?>
                        <code>[edupro_brands_carousel]</code>.
                        <?php esc_html_e( 'Add custom partner logos via Media Library or style typographic accreditation badges.', 'edupro' ); ?>
                    </p>

                    <input type="hidden" name="edupro_site_settings[trusted_brands_submitted]" value="1" />

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Brands Carousel', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[brands_enabled]" value="1" <?php checked( $settings['brands_enabled'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display the Trusted Brands & Accreditation Carousel on the homepage', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Section Heading', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-inline-3">
                                    <input type="text" name="edupro_site_settings[brands_heading_prefix]" value="<?php echo esc_attr( $settings['brands_heading_prefix'] ); ?>" placeholder="Trusted by" />
                                    <input type="text" name="edupro_site_settings[brands_heading_highlight]" value="<?php echo esc_attr( $settings['brands_heading_highlight'] ); ?>" placeholder="300+ Leading Universities" />
                                    <input type="text" name="edupro_site_settings[brands_heading_suffix]" value="<?php echo esc_attr( $settings['brands_heading_suffix'] ); ?>" placeholder="and Companies Globally" />
                                </div>
                                <p class="description"><?php esc_html_e( 'Prefix + Highlighted Crimson Text + Suffix.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3 class="edupro-ws-subhead"><?php esc_html_e( 'Partner & Accreditation Brands', 'edupro' ); ?></h3>
                    <div id="edupro-brands-repeater" class="edupro-ws-repeater">
                        <?php
                        $brands_list = is_array( $settings['trusted_brands'] ) ? $settings['trusted_brands'] : array();
                        foreach ( $brands_list as $b_idx => $brand ) :
                            $b_name  = isset( $brand['name'] ) ? (string) $brand['name'] : '';
                            $b_sub   = isset( $brand['sublabel'] ) ? (string) $brand['sublabel'] : '';
                            $b_color = isset( $brand['badge_color'] ) && $brand['badge_color'] ? (string) $brand['badge_color'] : '#be123c';
                            $b_logo  = isset( $brand['logo_url'] ) ? (string) $brand['logo_url'] : '';
                            ?>
                            <div class="edupro-ws-brand-row" data-index="<?php echo esc_attr( (string) $b_idx ); ?>">
                                <div class="edupro-ws-brand-row__fields">
                                    <div class="edupro-ws-brand-field">
                                        <label><?php esc_html_e( 'Brand Name', 'edupro' ); ?></label>
                                        <input type="text" name="edupro_site_settings[trusted_brands][<?php echo esc_attr( (string) $b_idx ); ?>][name]" value="<?php echo esc_attr( $b_name ); ?>" placeholder="Pearson" />
                                    </div>
                                    <div class="edupro-ws-brand-field">
                                        <label><?php esc_html_e( 'Sub-label / Badge', 'edupro' ); ?></label>
                                        <input type="text" name="edupro_site_settings[trusted_brands][<?php echo esc_attr( (string) $b_idx ); ?>][sublabel]" value="<?php echo esc_attr( $b_sub ); ?>" placeholder="Accredited Partner" />
                                    </div>
                                    <div class="edupro-ws-brand-field edupro-ws-brand-field--color">
                                        <label><?php esc_html_e( 'Accent', 'edupro' ); ?></label>
                                        <input type="color" name="edupro_site_settings[trusted_brands][<?php echo esc_attr( (string) $b_idx ); ?>][badge_color]" value="<?php echo esc_attr( $b_color ); ?>" />
                                    </div>
                                    <div class="edupro-ws-brand-field edupro-ws-brand-field--logo">
                                        <label><?php esc_html_e( 'Logo URL (optional)', 'edupro' ); ?></label>
                                        <div class="edupro-ws-brand-logo-wrap">
                                            <input type="url" class="edupro-ws-brand-logo-url" name="edupro_site_settings[trusted_brands][<?php echo esc_attr( (string) $b_idx ); ?>][logo_url]" value="<?php echo esc_url( $b_logo ); ?>" placeholder="https://..." />
                                            <button type="button" class="button button-secondary edupro-ws-brand-upload-btn"><?php esc_html_e( 'Upload', 'edupro' ); ?></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="edupro-ws-brand-row__meta">
                                    <div class="edupro-ws-brand-thumb">
                                        <?php if ( '' !== $b_logo ) : ?>
                                            <img src="<?php echo esc_url( $b_logo ); ?>" alt="" />
                                        <?php else : ?>
                                            <span class="edupro-ws-brand-swatch" style="background:<?php echo esc_attr( $b_color ); ?>;"></span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button-link-delete edupro-ws-brand-remove-btn" aria-label="<?php esc_attr_e( 'Remove brand', 'edupro' ); ?>">&times; <?php esc_html_e( 'Remove', 'edupro' ); ?></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="edupro-ws-repeater-actions">
                        <button type="button" id="edupro-add-brand-btn" class="button button-secondary">
                            + <?php esc_html_e( 'Add Brand Partner', 'edupro' ); ?>
                        </button>
                    </div>
                </div>

                <!-- 6C: Success Stories / Alumni Spotlight -->
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Success Stories / Alumni Spotlight', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Alumni Spotlight', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[spotlight_enabled]" value="1" <?php checked( $settings['spotlight_enabled'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Show the frosted Alumni Spotlight & Video Interview section on the homepage', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_spotlight_badge"><?php esc_html_e( 'Eyebrow Badge', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_spotlight_badge" name="edupro_site_settings[spotlight_badge]" value="<?php echo esc_attr( $settings['spotlight_badge'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_spotlight_heading"><?php esc_html_e( 'Section Heading', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_spotlight_heading" name="edupro_site_settings[spotlight_heading]" value="<?php echo esc_attr( $settings['spotlight_heading'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_spotlight_text"><?php esc_html_e( 'Primary Story Paragraph', 'edupro' ); ?></label></th>
                            <td><textarea id="edupro_spotlight_text" name="edupro_site_settings[spotlight_text]" rows="3" class="large-text"><?php echo esc_textarea( $settings['spotlight_text'] ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_spotlight_text_2"><?php esc_html_e( 'Secondary Story Paragraph', 'edupro' ); ?></label></th>
                            <td><textarea id="edupro_spotlight_text_2" name="edupro_site_settings[spotlight_text_2]" rows="3" class="large-text"><?php echo esc_textarea( $settings['spotlight_text_2'] ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Highlight Stats (2 Metrics)', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-inline-2">
                                    <div>
                                        <input type="text" name="edupro_site_settings[spotlight_stat_1_val]" value="<?php echo esc_attr( $settings['spotlight_stat_1_val'] ); ?>" placeholder="96%" style="width:100px;" />
                                        <input type="text" name="edupro_site_settings[spotlight_stat_1_label]" value="<?php echo esc_attr( $settings['spotlight_stat_1_label'] ); ?>" placeholder="Career Advancement Rate" class="regular-text" />
                                    </div>
                                    <div>
                                        <input type="text" name="edupro_site_settings[spotlight_stat_2_val]" value="<?php echo esc_attr( $settings['spotlight_stat_2_val'] ); ?>" placeholder="140+" style="width:100px;" />
                                        <input type="text" name="edupro_site_settings[spotlight_stat_2_label]" value="<?php echo esc_attr( $settings['spotlight_stat_2_label'] ); ?>" placeholder="Countries Represented" class="regular-text" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_spotlight_video_url"><?php esc_html_e( 'Interview Video URL', 'edupro' ); ?></label></th>
                            <td>
                                <input type="url" id="edupro_spotlight_video_url" name="edupro_site_settings[spotlight_video_url]" value="<?php echo esc_url( $settings['spotlight_video_url'] ); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=... or Vimeo / MP4 URL" />
                                <p class="description"><?php esc_html_e( 'Opens in a frosted Video Lightbox modal when clicked.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Video Cover Thumbnail', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-media-picker" data-preview-bg="dark">
                                    <div class="edupro-ws-media-preview edupro-ws-media-preview--dark <?php echo empty( $settings['spotlight_video_thumb'] ) ? 'is-empty' : ''; ?>">
                                        <?php if ( ! empty( $settings['spotlight_video_thumb'] ) ) : ?>
                                            <img src="<?php echo esc_url( $settings['spotlight_video_thumb'] ); ?>" alt="<?php esc_attr_e( 'Spotlight video thumbnail preview', 'edupro' ); ?>" />
                                        <?php else : ?>
                                            <span class="edupro-ws-media-placeholder"><?php esc_html_e( 'Optional cover photo', 'edupro' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" class="edupro-ws-media-id" value="0" />
                                    <input type="url" class="regular-text edupro-ws-media-url" name="edupro_site_settings[spotlight_video_thumb]" value="<?php echo esc_url( $settings['spotlight_video_thumb'] ); ?>" placeholder="https://..." />
                                    <div class="edupro-ws-media-actions">
                                        <button type="button" class="button button-secondary edupro-ws-upload-btn"><?php esc_html_e( 'Select Thumbnail', 'edupro' ); ?></button>
                                        <button type="button" class="button-link-delete edupro-ws-remove-btn" <?php echo empty( $settings['spotlight_video_thumb'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'edupro' ); ?></button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Graduate Name & Label', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-inline-2">
                                    <input type="text" name="edupro_site_settings[spotlight_video_name]" value="<?php echo esc_attr( $settings['spotlight_video_name'] ); ?>" placeholder="OLIVIA SZULC" class="regular-text" />
                                    <input type="text" name="edupro_site_settings[spotlight_video_label]" value="<?php echo esc_attr( $settings['spotlight_video_label'] ); ?>" placeholder="GRADUATE INTERVIEW" class="regular-text" />
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 6D: Order Gift Card Section -->
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Order Gift Card Section', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Gift Card Section', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[giftcard_enabled]" value="1" <?php checked( $settings['giftcard_enabled'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display the luxury 3D Gift Card showcase section on the homepage', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_giftcard_eyebrow"><?php esc_html_e( 'Eyebrow Label', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_giftcard_eyebrow" name="edupro_site_settings[giftcard_eyebrow]" value="<?php echo esc_attr( $settings['giftcard_eyebrow'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_giftcard_heading"><?php esc_html_e( 'Heading', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_giftcard_heading" name="edupro_site_settings[giftcard_heading]" value="<?php echo esc_attr( $settings['giftcard_heading'] ); ?>" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_giftcard_desc"><?php esc_html_e( 'Description', 'edupro' ); ?></label></th>
                            <td><textarea id="edupro_giftcard_desc" name="edupro_site_settings[giftcard_desc]" rows="3" class="large-text"><?php echo esc_textarea( $settings['giftcard_desc'] ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_giftcard_bullets"><?php esc_html_e( 'Benefit Bullets (one per line)', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_giftcard_bullets" name="edupro_site_settings[giftcard_bullets]" rows="4" class="large-text"><?php echo esc_textarea( $settings['giftcard_bullets'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Enter each checkmark benefit on its own line.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_giftcard_value_label"><?php esc_html_e( 'Gift Value Badge', 'edupro' ); ?></label></th>
                            <td><input type="text" id="edupro_giftcard_value_label" name="edupro_site_settings[giftcard_value_label]" value="<?php echo esc_attr( $settings['giftcard_value_label'] ); ?>" class="regular-text" placeholder="£25 – £150" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'CTA Button', 'edupro' ); ?></th>
                            <td>
                                <div class="edupro-ws-inline-2">
                                    <input type="text" name="edupro_site_settings[giftcard_cta_text]" value="<?php echo esc_attr( $settings['giftcard_cta_text'] ); ?>" placeholder="Order Gift Card" class="regular-text" />
                                    <input type="url" name="edupro_site_settings[giftcard_cta_url]" value="<?php echo esc_url( $settings['giftcard_cta_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/courses/' ) ); ?>" class="regular-text" />
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ==========================================================
                 TAB 7: SINGLE COURSE LAYOUT (v1.3.0)
                 ========================================================== -->
            <div class="edupro-ws-panel <?php echo 'course_layout' === $active_tab ? 'is-active' : ''; ?>" data-panel="course_layout">
                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Single Course Page Sections', 'edupro' ); ?></h2>
                    <p class="edupro-ws-card-desc"><?php esc_html_e( 'Toggle enhanced sections across every Single Course page. Per-course facts, galleries, testimonial videos, and custom FAQs can be customized inside each Course Editor.', 'edupro' ); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Key Facts & Accreditation Grid', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_key_facts]" value="1" <?php checked( $settings['course_show_key_facts'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Show 6-tile Glass Key Facts grid (Duration, Study Method, Awarding Body, Assessment, Tutor Support, Certificate)', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Course at a Glance Box', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_at_a_glance]" value="1" <?php checked( $settings['course_show_at_a_glance'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Show soft rose-glass "Course at a Glance" summary highlight box in Overview', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Course Photo Gallery Strip', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_gallery]" value="1" <?php checked( $settings['course_show_gallery'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display uploaded course gallery photos when attached to a course', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Trusted Brands Carousel', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_brands_carousel]" value="1" <?php checked( $settings['course_show_brands_carousel'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display the compact Trusted Brands & Accreditation Carousel above Related Courses', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Google Reviews Split Carousel', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_google_reviews]" value="1" <?php checked( $settings['course_show_google_reviews'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Display the Google Reviews summary card & review carousel above Related Courses', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sidebar Advisor Help Card', 'edupro' ); ?></th>
                            <td>
                                <label class="edupro-ws-toggle">
                                    <input type="checkbox" name="edupro_site_settings[course_show_help_card]" value="1" <?php checked( $settings['course_show_help_card'], '1' ); ?> />
                                    <span><?php esc_html_e( 'Show frosted "Need Guidance?" Call & WhatsApp advisor card in the course sidebar', 'edupro' ); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="edupro-ws-card">
                    <h2><?php esc_html_e( 'Default Course Accreditation & Sidebar Copy', 'edupro' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="edupro_course_default_awarding_body"><?php esc_html_e( 'Default Awarding Body', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_course_default_awarding_body" name="edupro_site_settings[course_default_awarding_body]" value="<?php echo esc_attr( $settings['course_default_awarding_body'] ); ?>" class="large-text" placeholder="CPD Accredited & QLS Endorsed" />
                                <p class="description"><?php esc_html_e( 'Used on courses that do not specify a custom Awarding Body in the Course Editor.', 'edupro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_course_help_card_title"><?php esc_html_e( 'Sidebar Help Card Title', 'edupro' ); ?></label></th>
                            <td>
                                <input type="text" id="edupro_course_help_card_title" name="edupro_site_settings[course_help_card_title]" value="<?php echo esc_attr( $settings['course_help_card_title'] ); ?>" class="regular-text" placeholder="Need Guidance?" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edupro_course_help_card_desc"><?php esc_html_e( 'Sidebar Help Card Description', 'edupro' ); ?></label></th>
                            <td>
                                <textarea id="edupro_course_help_card_desc" name="edupro_site_settings[course_help_card_desc]" rows="2" class="large-text"><?php echo esc_textarea( $settings['course_help_card_desc'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="edupro-ws-submit-bar">
                <button type="submit" class="button button-primary button-hero">
                    <?php esc_html_e( 'Save Website Settings', 'edupro' ); ?>
                </button>
                <span class="edupro-ws-submit-hint"><?php esc_html_e( 'Saves all 7 tabs at once.', 'edupro' ); ?></span>
            </div>
        </form>
    </div>

    <style>
        .edupro-ws-wrap { max-width: 1080px; margin-top: 18px; }
        .edupro-ws-header {
            background: linear-gradient(135deg, #120e18 0%, #24172b 100%);
            color: #ffffff;
            padding: 24px 28px;
            border-radius: 14px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 28px rgba(18, 14, 24, 0.16);
        }
        .edupro-ws-header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0;
        }
        .edupro-ws-header h1 .dashicons { color: #e11d48; font-size: 26px; width: 26px; height: 26px; }
        .edupro-ws-header p { margin: 0; color: rgba(255,255,255,0.82); font-size: 13.5px; }
        .edupro-ws-header__shortcodes {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .edupro-ws-sc-label { font-size: 12px; color: rgba(255,255,255,0.75); margin-right: 4px; }
        .edupro-ws-header__shortcodes code {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
        }
        .edupro-ws-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .edupro-ws-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 14px;
            border: 1px solid #dcdcde;
            border-radius: 9px;
            background: #ffffff;
            color: #334155;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .edupro-ws-tab:hover { border-color: #be123c; color: #be123c; }
        .edupro-ws-tab.is-active {
            background: linear-gradient(135deg, #be123c 0%, #e11d48 100%);
            border-color: #be123c;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(190, 18, 60, 0.28);
        }
        .edupro-ws-tab .dashicons { font-size: 16px; width: 16px; height: 16px; }
        .edupro-ws-panel { display: none; }
        .edupro-ws-panel.is-active { display: block; }
        .edupro-ws-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }
        .edupro-ws-card h2 {
            margin: 0 0 6px;
            font-size: 17px;
            font-weight: 700;
            color: #120e18;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .edupro-ws-subhead { margin: 18px 0 12px; font-size: 14px; font-weight: 700; color: #1e293b; }
        .edupro-ws-card-desc { margin: 8px 0 12px; color: #64748b; font-size: 13px; }
        .edupro-ws-toggle { display: inline-flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer; }
        .edupro-ws-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .edupro-ws-mini-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
        }
        .edupro-ws-mini-card strong { display: block; margin-bottom: 8px; color: #be123c; font-size: 13px; }
        .edupro-ws-mini-card label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .edupro-ws-mini-card p { margin: 0 0 10px; }
        .edupro-ws-mini-card p:last-child { margin-bottom: 0; }
        .edupro-ws-inline-2 { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .edupro-ws-inline-3 { display: grid; grid-template-columns: 1fr 1.4fr 1.2fr; gap: 8px; max-width: 720px; }
        .edupro-ws-repeater { display: flex; flex-direction: column; gap: 10px; }
        .edupro-ws-brand-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .edupro-ws-brand-row__fields {
            display: grid;
            grid-template-columns: 1.2fr 1.2fr 70px 1.8fr;
            gap: 10px;
            flex: 1;
            min-width: 280px;
        }
        .edupro-ws-brand-field label { display: block; font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.03em; }
        .edupro-ws-brand-field input[type="text"],
        .edupro-ws-brand-field input[type="url"] { width: 100%; }
        .edupro-ws-brand-field input[type="color"] { width: 52px; height: 32px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; background: #fff; }
        .edupro-ws-brand-logo-wrap { display: flex; gap: 6px; }
        .edupro-ws-brand-row__meta { display: flex; align-items: center; gap: 12px; }
        .edupro-ws-brand-thumb { width: 42px; height: 32px; border-radius: 6px; background: #ffffff; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .edupro-ws-brand-thumb img { max-width: 38px; max-height: 28px; object-fit: contain; }
        .edupro-ws-brand-swatch { width: 18px; height: 18px; border-radius: 50%; display: inline-block; }
        .edupro-ws-repeater-actions { margin-top: 12px; }
        .edupro-ws-media-picker { display: flex; flex-direction: column; gap: 10px; max-width: 460px; }
        .edupro-ws-media-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 76px;
            padding: 12px 16px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }
        .edupro-ws-media-preview--dark { background: #120e18; border-color: #334155; color: #cbd5e1; }
        .edupro-ws-media-preview img { max-height: 58px; max-width: 240px; width: auto; height: auto; object-fit: contain; }
        .edupro-ws-media-placeholder { font-size: 12.5px; color: #94a3b8; }
        .edupro-ws-media-actions { display: flex; align-items: center; gap: 12px; }
        .edupro-ws-map-preview { margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .edupro-ws-map-preview h3 { margin: 0 0 10px; font-size: 14px; color: #334155; }
        .edupro-ws-submit-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 24px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }
        .edupro-ws-submit-bar .button-primary {
            background: linear-gradient(135deg, #be123c 0%, #e11d48 100%) !important;
            border-color: #be123c !important;
            border-radius: 9px !important;
            font-weight: 600 !important;
            padding: 0 26px !important;
        }
        .edupro-ws-submit-bar .button-primary:hover { filter: brightness(0.95); }
        .edupro-ws-submit-hint { color: #64748b; font-size: 13px; }
        @media (max-width: 900px) {
            .edupro-ws-grid-3, .edupro-ws-inline-3, .edupro-ws-brand-row__fields { grid-template-columns: 1fr; }
        }
    </style>

    <script>
    (function($) {
        'use strict';
        $(function() {
            // Color picker initialization.
            if ($.fn.wpColorPicker) {
                $('.edupro-color-picker').wpColorPicker();
            }

            // Tab switching without page reload (keeps unsaved edits intact).
            var $tabs = $('.edupro-ws-tab');
            var $panels = $('.edupro-ws-panel');
            var $activeInput = $('#edupro_active_tab');

            $tabs.on('click', function() {
                var target = $(this).data('tab');
                $tabs.removeClass('is-active').attr('aria-selected', 'false');
                $(this).addClass('is-active').attr('aria-selected', 'true');
                $panels.removeClass('is-active');
                $panels.filter('[data-panel="' + target + '"]').addClass('is-active');
                $activeInput.val(target);

                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', target);
                    url.searchParams.delete('settings-updated');
                    window.history.replaceState({}, '', url.toString());
                }
            });

            // WordPress Media Library picker for Header/Footer logos & Spotlight Thumbnail.
            $('.edupro-ws-media-picker').each(function() {
                var $picker   = $(this);
                var $idInput  = $picker.find('.edupro-ws-media-id');
                var $urlInput = $picker.find('.edupro-ws-media-url');
                var $preview  = $picker.find('.edupro-ws-media-preview');
                var $remove   = $picker.find('.edupro-ws-remove-btn');
                var frame     = null;

                $picker.find('.edupro-ws-upload-btn').on('click', function(e) {
                    e.preventDefault();
                    if (frame) {
                        frame.open();
                        return;
                    }
                    frame = wp.media({
                        title: '<?php echo esc_js( __( 'Select or Upload Image', 'edupro' ) ); ?>',
                        button: { text: '<?php echo esc_js( __( 'Use This Image', 'edupro' ) ); ?>' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (!attachment || !attachment.url) { return; }
                        $idInput.val(attachment.id || 0);
                        $urlInput.val(attachment.url);
                        $preview.removeClass('is-empty').html($('<img>', { src: attachment.url, alt: attachment.alt || 'Preview' }));
                        $remove.show();
                    });
                    frame.open();
                });

                $urlInput.on('change', function() {
                    var val = $.trim($(this).val());
                    if (val) {
                        $preview.removeClass('is-empty').html($('<img>', { src: val, alt: 'Preview' }));
                        $remove.show();
                    } else {
                        $idInput.val(0);
                        $preview.addClass('is-empty').html('<span class="edupro-ws-media-placeholder"><?php echo esc_js( __( 'No image selected', 'edupro' ) ); ?></span>');
                        $remove.hide();
                    }
                });

                $remove.on('click', function(e) {
                    e.preventDefault();
                    $idInput.val(0);
                    $urlInput.val('');
                    $preview.addClass('is-empty').html('<span class="edupro-ws-media-placeholder"><?php echo esc_js( __( 'No image selected', 'edupro' ) ); ?></span>');
                    $remove.hide();
                });
            });

            // Trusted Brands Repeater (Add, Remove, Upload Logo, Live Preview).
            var $repeater = $('#edupro-brands-repeater');
            var brandCounter = $repeater.find('.edupro-ws-brand-row').length;

            $('#edupro-add-brand-btn').on('click', function() {
                var idx = brandCounter++;
                var rowHtml =
                    '<div class="edupro-ws-brand-row" data-index="' + idx + '">' +
                        '<div class="edupro-ws-brand-row__fields">' +
                            '<div class="edupro-ws-brand-field">' +
                                '<label><?php echo esc_js( __( 'Brand Name', 'edupro' ) ); ?></label>' +
                                '<input type="text" name="edupro_site_settings[trusted_brands][' + idx + '][name]" value="" placeholder="Partner Name" />' +
                            '</div>' +
                            '<div class="edupro-ws-brand-field">' +
                                '<label><?php echo esc_js( __( 'Sub-label / Badge', 'edupro' ) ); ?></label>' +
                                '<input type="text" name="edupro_site_settings[trusted_brands][' + idx + '][sublabel]" value="" placeholder="Accredited Partner" />' +
                            '</div>' +
                            '<div class="edupro-ws-brand-field edupro-ws-brand-field--color">' +
                                '<label><?php echo esc_js( __( 'Accent', 'edupro' ) ); ?></label>' +
                                '<input type="color" name="edupro_site_settings[trusted_brands][' + idx + '][badge_color]" value="#be123c" />' +
                            '</div>' +
                            '<div class="edupro-ws-brand-field edupro-ws-brand-field--logo">' +
                                '<label><?php echo esc_js( __( 'Logo URL (optional)', 'edupro' ) ); ?></label>' +
                                '<div class="edupro-ws-brand-logo-wrap">' +
                                    '<input type="url" class="edupro-ws-brand-logo-url" name="edupro_site_settings[trusted_brands][' + idx + '][logo_url]" value="" placeholder="https://..." />' +
                                    '<button type="button" class="button button-secondary edupro-ws-brand-upload-btn"><?php echo esc_js( __( 'Upload', 'edupro' ) ); ?></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="edupro-ws-brand-row__meta">' +
                            '<div class="edupro-ws-brand-thumb"><span class="edupro-ws-brand-swatch" style="background:#be123c;"></span></div>' +
                            '<button type="button" class="button-link-delete edupro-ws-brand-remove-btn">&times; <?php echo esc_js( __( 'Remove', 'edupro' ) ); ?></button>' +
                        '</div>' +
                    '</div>';
                $repeater.append(rowHtml);
            });

            $repeater.on('click', '.edupro-ws-brand-remove-btn', function() {
                $(this).closest('.edupro-ws-brand-row').remove();
            });

            $repeater.on('click', '.edupro-ws-brand-upload-btn', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.edupro-ws-brand-row');
                var $urlInput = $row.find('.edupro-ws-brand-logo-url');
                var $thumb = $row.find('.edupro-ws-brand-thumb');

                var brandFrame = wp.media({
                    title: '<?php echo esc_js( __( 'Select Partner Logo', 'edupro' ) ); ?>',
                    button: { text: '<?php echo esc_js( __( 'Use Logo', 'edupro' ) ); ?>' },
                    library: { type: 'image' },
                    multiple: false
                });
                brandFrame.on('select', function() {
                    var att = brandFrame.state().get('selection').first().toJSON();
                    if (!att || !att.url) { return; }
                    $urlInput.val(att.url);
                    $thumb.html($('<img>', { src: att.url, alt: '' }));
                });
                brandFrame.open();
            });

            $repeater.on('change', 'input[type="color"]', function() {
                var $row = $(this).closest('.edupro-ws-brand-row');
                if (!$.trim($row.find('.edupro-ws-brand-logo-url').val())) {
                    $row.find('.edupro-ws-brand-thumb').html('<span class="edupro-ws-brand-swatch" style="background:' + $(this).val() + ';"></span>');
                }
            });
        });
    })(jQuery);
    </script>
    <?php
}

/* ==========================================================================
 * 6. FRONTEND SHORTCODES
 * ========================================================================== */

/**
 * Shortcode: [edupro_social_links]
 *
 * Renders all configured social media links with crisp inline SVG icons.
 *
 * Attributes:
 *  - show_labels: "yes"|"no" (default "no")
 *  - class: extra CSS class
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string
 */
function edupro_social_links_shortcode( $atts = array() ) {
    $atts = shortcode_atts(
        array(
            'show_labels' => 'no',
            'class'       => '',
        ),
        $atts,
        'edupro_social_links'
    );

    $networks    = edupro_get_social_networks_meta();
    $show_labels = ( 'yes' === strtolower( (string) $atts['show_labels'] ) || '1' === (string) $atts['show_labels'] );
    $extra_class = trim( sanitize_html_class( (string) $atts['class'] ) );

    $items = array();
    foreach ( $networks as $slug => $net ) {
        $url = (string) edupro_get_site_setting( $net['key'], '' );
        if ( '' === $url ) {
            continue;
        }
        $items[] = array(
            'slug'  => $slug,
            'label' => $net['label'],
            'url'   => $url,
            'svg'   => $net['svg'],
        );
    }

    if ( empty( $items ) ) {
        return '';
    }

    $classes = 'edupro-social-list' . ( $extra_class ? ' ' . $extra_class : '' );

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $classes ); ?>" aria-label="<?php esc_attr_e( 'Social Media Links', 'edupro' ); ?>">
        <?php foreach ( $items as $item ) : ?>
            <a
                href="<?php echo esc_url( $item['url'] ); ?>"
                class="edupro-social-link edupro-social-link--<?php echo esc_attr( $item['slug'] ); ?>"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="<?php echo esc_attr( $item['label'] ); ?>"
                title="<?php echo esc_attr( $item['label'] ); ?>"
            >
                <?php echo $item['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG. ?>
                <?php if ( $show_labels ) : ?>
                    <span class="edupro-social-link__label"><?php echo esc_html( $item['label'] ); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
add_shortcode( 'edupro_social_links', 'edupro_social_links_shortcode' );

/**
 * Shortcode: [edupro_contact_info]
 *
 * Renders structured responsive contact cards pulled dynamically from Website Settings:
 * Phone, WhatsApp Chat CTA, Email, Business Hours, Office Addresses, and Social Icons.
 *
 * Attributes:
 *  - show_social: "yes"|"no" (default "yes")
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string
 */
function edupro_contact_info_shortcode( $atts = array() ) {
    $atts = shortcode_atts(
        array(
            'show_social' => 'yes',
        ),
        $atts,
        'edupro_contact_info'
    );

    $phone_primary     = (string) edupro_get_site_setting( 'phone_primary', '' );
    $phone_secondary   = (string) edupro_get_site_setting( 'phone_secondary', '' );
    $whatsapp_number   = (string) edupro_get_site_setting( 'whatsapp_number', '' );
    $whatsapp_url      = edupro_get_whatsapp_url();
    $email_support     = (string) edupro_get_site_setting( 'email_support', '' );
    $email_sales       = (string) edupro_get_site_setting( 'email_sales', '' );
    $business_hours    = (string) edupro_get_site_setting( 'business_hours', '' );
    $address_primary   = (string) edupro_get_site_setting( 'address_primary', '' );
    $address_secondary = (string) edupro_get_site_setting( 'address_secondary', '' );
    $show_social       = ( 'yes' === strtolower( (string) $atts['show_social'] ) || '1' === (string) $atts['show_social'] );
    $social_html       = $show_social ? edupro_social_links_shortcode() : '';

    ob_start();
    ?>
    <div class="edupro-contact-section">
        <div class="edupro-contact-grid">
            <?php if ( '' !== $phone_primary || '' !== $phone_secondary ) : ?>
                <div class="edupro-contact-card">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'Call Us', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <?php if ( '' !== $phone_primary ) : ?>
                            <p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone_primary ) ); ?>"><?php echo esc_html( $phone_primary ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( '' !== $phone_secondary ) : ?>
                            <p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone_secondary ) ); ?>"><?php echo esc_html( $phone_secondary ); ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $whatsapp_url ) : ?>
                <div class="edupro-contact-card edupro-contact-card--whatsapp">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91A9.85 9.85 0 0 0 12.04 2zm5.79 14.01c-.24.68-1.41 1.3-1.95 1.38-.5.07-1.13.1-1.82-.12-.42-.13-.96-.31-1.66-.61-2.92-1.26-4.82-4.2-4.97-4.4-.15-.19-1.18-1.57-1.18-3s.75-2.13 1.02-2.42c.27-.29.59-.36.79-.36h.57c.18 0 .43-.07.67.51.24.59.84 2.04.91 2.19.07.15.12.32.02.51-.1.2-.15.32-.29.49-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.26 1.64 2.04 1.13 1.01 2.08 1.32 2.38 1.47.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.29.39-.24.66-.15.27.1 1.72.81 2.02.96.3.15.49.22.56.34.07.13.07.72-.17 1.4z"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'WhatsApp Support', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <p><?php echo esc_html( $whatsapp_number ); ?></p>
                        <a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-whatsapp">
                            <?php esc_html_e( 'Chat on WhatsApp', 'edupro' ); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $email_support || '' !== $email_sales ) : ?>
                <div class="edupro-contact-card">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'Email Us', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <?php if ( '' !== $email_support ) : ?>
                            <p><a href="mailto:<?php echo esc_attr( antispambot( $email_support ) ); ?>"><?php echo esc_html( antispambot( $email_support ) ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( '' !== $email_sales ) : ?>
                            <p><a href="mailto:<?php echo esc_attr( antispambot( $email_sales ) ); ?>"><?php echo esc_html( antispambot( $email_sales ) ); ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $business_hours ) : ?>
                <div class="edupro-contact-card">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'Business Hours', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <p><?php echo esc_html( $business_hours ); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $address_primary || '' !== $address_secondary ) : ?>
                <div class="edupro-contact-card">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'Our Offices', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <?php if ( '' !== $address_primary ) : ?>
                            <p><?php echo nl2br( esc_html( $address_primary ) ); ?></p>
                        <?php endif; ?>
                        <?php if ( '' !== $address_secondary ) : ?>
                            <p><?php echo nl2br( esc_html( $address_secondary ) ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $social_html ) : ?>
                <div class="edupro-contact-card">
                    <div class="edupro-contact-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </div>
                    <h3 class="edupro-contact-card__title"><?php esc_html_e( 'Follow Us', 'edupro' ); ?></h3>
                    <div class="edupro-contact-card__body">
                        <?php echo $social_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in edupro_social_links_shortcode(). ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
add_shortcode( 'edupro_contact_info', 'edupro_contact_info_shortcode' );

/**
 * Shortcode: [edupro_map]
 *
 * Renders a responsive Google Map iframe and optional Directions button
 * configured in Website Settings -> Google Maps.
 *
 * Attributes:
 *  - height: custom height in pixels (defaults to saved map_height / 380)
 *  - show_directions: "yes"|"no" (default "yes")
 *  - title: iframe accessible title
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string
 */
function edupro_map_shortcode( $atts = array() ) {
    $saved_height = absint( edupro_get_site_setting( 'map_height', 380 ) );
    if ( $saved_height < 200 ) {
        $saved_height = 380;
    }

    $atts = shortcode_atts(
        array(
            'height'          => $saved_height,
            'show_directions' => 'yes',
            'title'           => __( 'Our Location on Google Maps', 'edupro' ),
        ),
        $atts,
        'edupro_map'
    );

    $raw_embed = (string) edupro_get_site_setting( 'map_embed', '' );
    if ( '' === $raw_embed ) {
        // Fallback to primary office address if no explicit map embed was entered.
        $raw_embed = (string) edupro_get_site_setting( 'address_primary', '' );
    }

    $embed_url = edupro_parse_google_map_embed_url( $raw_embed );
    if ( '' === $embed_url ) {
        return '';
    }

    $height          = max( 200, min( 900, absint( $atts['height'] ) ) );
    $show_directions = ( 'yes' === strtolower( (string) $atts['show_directions'] ) || '1' === (string) $atts['show_directions'] );
    $directions_url  = (string) edupro_get_site_setting( 'map_directions_url', '' );

    // Auto-generate a Directions link if admin entered a plain address without a separate URL.
    if ( '' === $directions_url && $show_directions && '' !== $raw_embed && false === stripos( $raw_embed, '<iframe' ) && ! preg_match( '#^https?://#i', $raw_embed ) ) {
        $directions_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $raw_embed );
    }

    ob_start();
    ?>
    <div class="edupro-map-wrap">
        <div class="edupro-map-frame">
            <iframe
                src="<?php echo esc_url( $embed_url ); ?>"
                width="100%"
                height="<?php echo esc_attr( (string) $height ); ?>"
                style="border:0;"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
                title="<?php echo esc_attr( $atts['title'] ); ?>"
            ></iframe>
        </div>
        <?php if ( $show_directions && '' !== $directions_url ) : ?>
            <div class="edupro-map-actions">
                <a href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-map-directions-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                    <span><?php esc_html_e( 'Get Directions on Google Maps', 'edupro' ); ?></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
add_shortcode( 'edupro_map', 'edupro_map_shortcode' );

/* ==========================================================================
 * 7. REUSABLE TRUSTED BRANDS CAROUSEL RENDERER & SHORTCODE (v1.3.0)
 * ========================================================================== */

/**
 * Render the Dashboard-controlled Trusted Brands & Accreditation Carousel.
 *
 * Shared by front-page.php, single-lms_course.php, and [edupro_brands_carousel].
 * Returns HTML string (consistent with edupro_render_google_reviews()).
 *
 * @param string $extra_class Optional extra CSS class(es), e.g. 'edupro-brands-section--compact'.
 * @return string Rendered HTML markup, or empty string if no brands are configured.
 */
function edupro_render_brands_carousel( $extra_class = '' ) {
    $settings = edupro_get_site_settings();
    $brands   = isset( $settings['trusted_brands'] ) && is_array( $settings['trusted_brands'] )
        ? $settings['trusted_brands']
        : array();

    if ( empty( $brands ) ) {
        return '';
    }

    $prefix    = (string) edupro_get_site_setting( 'brands_heading_prefix', __( 'Trusted by', 'edupro' ) );
    $highlight = (string) edupro_get_site_setting( 'brands_heading_highlight', __( '300+ Leading Universities', 'edupro' ) );
    $suffix    = (string) edupro_get_site_setting( 'brands_heading_suffix', __( 'and Companies Globally', 'edupro' ) );

    $section_classes = 'edupro-brands-section';
    if ( '' !== trim( (string) $extra_class ) ) {
        $extra_parts = preg_split( '/\s+/', trim( (string) $extra_class ) );
        foreach ( (array) $extra_parts as $cls ) {
            $sanitized_cls = sanitize_html_class( $cls );
            if ( '' !== $sanitized_cls ) {
                $section_classes .= ' ' . $sanitized_cls;
            }
        }
    }

    $render_card = static function( $brand, $is_clone = false ) {
        $name     = isset( $brand['name'] ) ? trim( (string) $brand['name'] ) : '';
        $sublabel = isset( $brand['sublabel'] ) ? trim( (string) $brand['sublabel'] ) : '';
        $color    = isset( $brand['badge_color'] ) ? sanitize_hex_color( (string) $brand['badge_color'] ) : '#be123c';
        $logo_url = isset( $brand['logo_url'] ) ? trim( (string) $brand['logo_url'] ) : '';

        if ( '' === $name && '' === $logo_url ) {
            return '';
        }
        if ( ! $color ) {
            $color = '#be123c';
        }

        $aria_attr = $is_clone ? ' aria-hidden="true" data-brand-clone="1"' : '';
        ob_start();
        ?>
        <div class="edupro-brand-card" style="--brand-accent: <?php echo esc_attr( $color ); ?>;"<?php echo $aria_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ( '' !== $logo_url ) : ?>
                <img
                    src="<?php echo esc_url( $logo_url ); ?>"
                    alt="<?php echo $is_clone ? '' : esc_attr( $name ? $name : __( 'Partner Logo', 'edupro' ) ); ?>"
                    class="edupro-brand-card__logo"
                    loading="lazy"
                />
                <?php if ( '' !== $sublabel ) : ?>
                    <span class="edupro-brand-card__sub"><?php echo esc_html( $sublabel ); ?></span>
                <?php endif; ?>
            <?php else : ?>
                <span class="edupro-brand-card__dot" aria-hidden="true"></span>
                <span class="edupro-brand-card__text">
                    <span class="edupro-brand-card__name"><?php echo esc_html( $name ); ?></span>
                    <?php if ( '' !== $sublabel ) : ?>
                        <span class="edupro-brand-card__sub"><?php echo esc_html( $sublabel ); ?></span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    };

    ob_start();
    ?>
    <section class="<?php echo esc_attr( $section_classes ); ?>" aria-label="<?php esc_attr_e( 'Trusted Accreditation & Partner Brands', 'edupro' ); ?>">
        <div class="edupro-container">
            <?php if ( '' !== $prefix || '' !== $highlight || '' !== $suffix ) : ?>
                <div class="edupro-brands-header">
                    <h2 class="edupro-brands-heading">
                        <?php if ( '' !== $prefix ) : ?>
                            <span><?php echo esc_html( $prefix ); ?></span>
                        <?php endif; ?>
                        <?php if ( '' !== $highlight ) : ?>
                            <span class="edupro-brands-heading__highlight"><?php echo esc_html( $highlight ); ?></span>
                        <?php endif; ?>
                        <?php if ( '' !== $suffix ) : ?>
                            <span><?php echo esc_html( $suffix ); ?></span>
                        <?php endif; ?>
                    </h2>
                </div>
            <?php endif; ?>

            <div class="edupro-brands-carousel" data-edupro-brands-carousel>
                <button type="button" class="edupro-brands-nav edupro-brands-nav--prev" aria-label="<?php esc_attr_e( 'Previous partner brands', 'edupro' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                </button>

                <div class="edupro-brands-viewport">
                    <div class="edupro-brands-track">
                        <?php
                        foreach ( $brands as $brand ) {
                            echo $render_card( $brand, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        // Duplicate track items for seamless continuous infinite CSS marquee.
                        foreach ( $brands as $brand ) {
                            echo $render_card( $brand, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </div>
                </div>

                <button type="button" class="edupro-brands-nav edupro-brands-nav--next" aria-label="<?php esc_attr_e( 'Next partner brands', 'edupro' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Shortcode: [edupro_brands_carousel]
 *
 * Renders the Dashboard-controlled Trusted Brands & Accreditation Carousel.
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string
 */
function edupro_brands_carousel_shortcode( $atts = array() ) {
    $atts = shortcode_atts(
        array(
            'class' => '',
        ),
        $atts,
        'edupro_brands_carousel'
    );

    return edupro_render_brands_carousel( (string) $atts['class'] );
}
add_shortcode( 'edupro_brands_carousel', 'edupro_brands_carousel_shortcode' );
