<?php
/**
 * EduPro LMS Theme — functions.php
 *
 * Bootstrap file: loads all inc/ modules, registers menus/sidebars/image sizes,
 * declares theme-supports, and enqueues assets.
 *
 * @package EduPro
 * @version 1.4.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 0. Constants
// ---------------------------------------------------------------------------
define( 'EDUPRO_VERSION',   '1.4.2' );
define( 'EDUPRO_DIR',       get_template_directory() );
define( 'EDUPRO_URI',       get_template_directory_uri() );
define( 'EDUPRO_INC',       EDUPRO_DIR . '/inc/' );

// ---------------------------------------------------------------------------
// 1. Load Inc Files
// ---------------------------------------------------------------------------
$edupro_inc_files = [
    'currency.php',
    'cart.php',
    'helpers.php',
    'site-settings.php',
    'custom-post-types.php',
    'taxonomies.php',
    'metaboxes.php',
    'student-auth.php',
    'enrollment.php',
    'coupons.php',
    'invoices.php',
    'payment-gateways.php',
    'ajax-handlers.php',
    'google-reviews.php',
    'shortcodes.php',
    'demo-setup.php',
];

foreach ( $edupro_inc_files as $file ) {
    $path = EDUPRO_INC . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// ---------------------------------------------------------------------------
// 2. Theme Setup
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_theme_setup' ) ) :
    function edupro_theme_setup() {

        // Allow WordPress to supply <title> tag.
        add_theme_support( 'title-tag' );

        // Post thumbnails / featured images.
        add_theme_support( 'post-thumbnails' );

        // HTML5 markup for core elements.
        add_theme_support( 'html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ] );

        // Custom logo in Customizer.
        add_theme_support( 'custom-logo', [
            'height'      => 60,
            'width'       => 200,
            'flex-height' => true,
            'flex-width'  => true,
            'header-text' => [ 'site-title', 'site-description' ],
        ] );

        // Custom background in Customizer.
        add_theme_support( 'custom-background', [
            'default-color' => 'ffffff',
            'default-image' => '',
        ] );

        // Responsive embeds.
        add_theme_support( 'responsive-embeds' );

        // Block editor wide / full alignment.
        add_theme_support( 'align-wide' );

        // Load theme textdomain.
        load_theme_textdomain( 'edupro', EDUPRO_DIR . '/languages' );

        // ---------------------------------------------------------------------------
        // Navigation Menus
        // ---------------------------------------------------------------------------
        register_nav_menus( [
            'primary'  => __( 'Main Navigation', 'edupro' ),
            'footer-1' => __( 'Footer Column 1',  'edupro' ),
            'footer-2' => __( 'Footer Column 2',  'edupro' ),
            'footer-3' => __( 'Footer Column 3',  'edupro' ),
        ] );

        // ---------------------------------------------------------------------------
        // Custom Image Sizes (also declared again via add_image_size below)
        // ---------------------------------------------------------------------------
        add_image_size( 'course-thumb', 400, 250, true );   // hard crop
        add_image_size( 'course-card',  350, 220, true );   // hard crop
        add_image_size( 'hero-thumb',   800, 450, true );   // hard crop
        add_image_size( 'instructor-avatar', 120, 120, true );
        add_image_size( 'blog-thumb',   770, 430, true );
    }
endif;
add_action( 'after_setup_theme', 'edupro_theme_setup' );

// ---------------------------------------------------------------------------
// 3. Smart Minified Asset Loader & Enqueue Styles
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_asset_uri' ) ) :
    /**
     * Resolve minified (.min.css / .min.js) asset URI when SCRIPT_DEBUG is disabled
     * and the minified sibling exists on disk; falls back safely to the source file.
     *
     * @param string $rel_path Relative path inside theme directory (e.g. 'assets/css/main.css').
     * @return string Full asset URI.
     */
    function edupro_asset_uri( $rel_path ) {
        $rel_path = ltrim( (string) $rel_path, '/' );
        if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
            $min_path = preg_replace( '/\.(css|js)$/i', '.min.$1', $rel_path );
            if ( $min_path && $min_path !== $rel_path && file_exists( EDUPRO_DIR . '/' . $min_path ) ) {
                return EDUPRO_URI . '/' . $min_path;
            }
        }
        return EDUPRO_URI . '/' . $rel_path;
    }
endif;

// Preconnect to Google Fonts early for faster typography paint.
add_action( 'wp_head', function() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 1 );

if ( ! function_exists( 'edupro_enqueue_styles' ) ) :
    function edupro_enqueue_styles() {

        // Lean Google Fonts: Plus Jakarta Sans + Inter (display=swap)
        $google_fonts_url = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap';
        wp_enqueue_style(
            'edupro-google-fonts',
            esc_url( $google_fonts_url ),
            [],
            null
        );

        // Main stylesheet (global)
        wp_enqueue_style(
            'edupro-main',
            edupro_asset_uri( 'assets/css/main.css' ),
            [ 'edupro-google-fonts' ],
            EDUPRO_VERSION
        );

        // Course & lesson styles (conditional: course/lesson singles, archive & course taxonomies only)
        if (
            is_singular( [ 'lms_course', 'lms_lesson' ] ) ||
            is_post_type_archive( 'lms_course' ) ||
            is_tax( [ 'course_category', 'course_tag', 'course_level', 'course_language' ] )
        ) {
            wp_enqueue_style(
                'edupro-course',
                edupro_asset_uri( 'assets/css/course.css' ),
                [ 'edupro-main' ],
                EDUPRO_VERSION
            );
        }

        // Student dashboard styles (conditional: dashboard template only)
        if ( is_page_template( 'templates/template-dashboard.php' ) ) {
            wp_enqueue_style(
                'edupro-dashboard',
                edupro_asset_uri( 'assets/css/dashboard.css' ),
                [ 'edupro-main' ],
                EDUPRO_VERSION
            );
        }
    }
endif;
add_action( 'wp_enqueue_scripts', 'edupro_enqueue_styles' );

// ---------------------------------------------------------------------------
// 4. Enqueue Scripts (Conditional + Deferred)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_enqueue_scripts' ) ) :
    function edupro_enqueue_scripts() {

        // Main JS (global; jQuery as dependency — WordPress ships jQuery)
        wp_enqueue_script(
            'edupro-main',
            edupro_asset_uri( 'assets/js/main.js' ),
            [ 'jquery' ],
            EDUPRO_VERSION,
            true  // load in footer
        );

        // Course player JS (conditional: single lesson pages only)
        if ( is_singular( 'lms_lesson' ) ) {
            wp_enqueue_script(
                'edupro-course-player',
                edupro_asset_uri( 'assets/js/course-player.js' ),
                [ 'jquery', 'edupro-main' ],
                EDUPRO_VERSION,
                true
            );
        }

        // Localise variables for JS
        wp_localize_script(
            'edupro-main',
            'edupro_vars',
            [
                'ajaxurl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'edupro_nonce' ),
                'site_url'        => get_site_url(),
                'currency'        => function_exists( 'edupro_get_active_currency' ) ? edupro_get_active_currency() : 'INR',
                'currency_symbol' => function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol() : '₹',
                'cart_url'        => function_exists( 'edupro_get_cart_url' ) ? edupro_get_cart_url() : home_url( '/cart/' ),
                'checkout_url'    => function_exists( 'edupro_get_checkout_url' ) ? edupro_get_checkout_url() : home_url( '/checkout/' ),
            ]
        );

        // Comment reply script on singular posts.
        if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
            wp_enqueue_script( 'comment-reply' );
        }
    }
endif;
add_action( 'wp_enqueue_scripts', 'edupro_enqueue_scripts' );

// Defer EduPro frontend JS so first paint is never render-blocked.
add_filter( 'script_loader_tag', function( $tag, $handle ) {
    if ( is_admin() ) {
        return $tag;
    }
    if ( in_array( $handle, [ 'edupro-main', 'edupro-course-player' ], true ) && false === strpos( $tag, ' defer' ) ) {
        return str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}, 10, 2 );

// ---------------------------------------------------------------------------
// 5. Register Sidebars / Widget Areas
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_register_sidebars' ) ) :
    function edupro_register_sidebars() {

        $defaults = [
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h4 class="widget-title">',
            'after_title'   => '</h4>',
        ];

        register_sidebar( array_merge( $defaults, [
            'name'        => __( 'Course Sidebar', 'edupro' ),
            'id'          => 'course-sidebar',
            'description' => __( 'Widgets displayed in the course single sidebar.', 'edupro' ),
        ] ) );

        register_sidebar( array_merge( $defaults, [
            'name'        => __( 'Footer Widget 1', 'edupro' ),
            'id'          => 'footer-widget-1',
            'description' => __( 'First footer widget column.', 'edupro' ),
        ] ) );

        register_sidebar( array_merge( $defaults, [
            'name'        => __( 'Footer Widget 2', 'edupro' ),
            'id'          => 'footer-widget-2',
            'description' => __( 'Second footer widget column.', 'edupro' ),
        ] ) );

        register_sidebar( array_merge( $defaults, [
            'name'        => __( 'Footer Widget 3', 'edupro' ),
            'id'          => 'footer-widget-3',
            'description' => __( 'Third footer widget column.', 'edupro' ),
        ] ) );

        register_sidebar( array_merge( $defaults, [
            'name'        => __( 'Footer Widget 4', 'edupro' ),
            'id'          => 'footer-widget-4',
            'description' => __( 'Fourth footer widget column.', 'edupro' ),
        ] ) );
    }
endif;
add_action( 'widgets_init', 'edupro_register_sidebars' );

// ---------------------------------------------------------------------------
// 6. Allow SVG uploads (for logo/icons)
// ---------------------------------------------------------------------------
add_filter( 'upload_mimes', function( $mimes ) {
    $mimes['svg']  = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
} );

// ---------------------------------------------------------------------------
// 7. Customizer additions
// ---------------------------------------------------------------------------
add_action( 'customize_register', function( $wp_customize ) {

    // ---- EduPro Panel ----
    $wp_customize->add_panel( 'edupro_panel', [
        'title'    => __( 'Theme Settings', 'edupro' ),
        'priority' => 160,
    ] );

    // -- Hero Section --
    $wp_customize->add_section( 'edupro_hero', [
        'title' => __( 'Hero Section', 'edupro' ),
        'panel' => 'edupro_panel',
    ] );

    $wp_customize->add_setting( 'edupro_hero_heading', [
        'default'           => __( 'Learn New Skills Online', 'edupro' ),
        'sanitize_callback' => 'sanitize_text_field',
    ] );
    $wp_customize->add_control( 'edupro_hero_heading', [
        'label'   => __( 'Hero Heading', 'edupro' ),
        'section' => 'edupro_hero',
        'type'    => 'text',
    ] );

    $wp_customize->add_setting( 'edupro_hero_subheading', [
        'default'           => __( 'Build real-world skills and advance your career with expert-led courses.', 'edupro' ),
        'sanitize_callback' => 'sanitize_textarea_field',
    ] );
    $wp_customize->add_control( 'edupro_hero_subheading', [
        'label'   => __( 'Hero Subheading', 'edupro' ),
        'section' => 'edupro_hero',
        'type'    => 'textarea',
    ] );

    // -- Footer --
    $wp_customize->add_section( 'edupro_footer', [
        'title' => __( 'Footer', 'edupro' ),
        'panel' => 'edupro_panel',
    ] );

    $wp_customize->add_setting( 'edupro_footer_copyright', [
        'default'           => sprintf( '&copy; %d %s. All rights reserved.', date( 'Y' ), get_bloginfo( 'name' ) ?: 'Academy' ),
        'sanitize_callback' => 'wp_kses_post',
    ] );
    $wp_customize->add_control( 'edupro_footer_copyright', [
        'label'   => __( 'Copyright Text', 'edupro' ),
        'section' => 'edupro_footer',
        'type'    => 'textarea',
    ] );

    // -- Colors --
    $wp_customize->add_section( 'edupro_colors', [
        'title' => __( 'Brand Colors', 'edupro' ),
        'panel' => 'edupro_panel',
    ] );

    $wp_customize->add_setting( 'edupro_primary_color', [
        'default'           => '#e31b23',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ] );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'edupro_primary_color', [
        'label'   => __( 'Primary (Brand) Color', 'edupro' ),
        'section' => 'edupro_colors',
    ] ) );
} );

// ---------------------------------------------------------------------------
// 8. Output dynamic CSS custom-properties for Customizer colors
// ---------------------------------------------------------------------------
add_action( 'wp_head', function() {
    $primary = get_theme_mod( 'edupro_primary_color', '#e31b23' );
    echo '<style id="edupro-dynamic-css">:root{--edupro-primary:' . sanitize_hex_color( $primary ) . ';}</style>' . "\n";
} );

// ---------------------------------------------------------------------------
// 9. Expose custom image sizes in Gutenberg / block editor
// ---------------------------------------------------------------------------
add_filter( 'image_size_names_choose', function( $sizes ) {
    return array_merge( $sizes, [
        'course-thumb'      => __( 'Course Thumbnail', 'edupro' ),
        'course-card'       => __( 'Course Card',      'edupro' ),
        'hero-thumb'        => __( 'Hero Thumbnail',   'edupro' ),
        'instructor-avatar' => __( 'Instructor Avatar','edupro' ),
        'blog-thumb'        => __( 'Blog Thumbnail',   'edupro' ),
    ] );
} );

// ---------------------------------------------------------------------------
// 10. Security & Frontend Bloat Cleanup
// ---------------------------------------------------------------------------
remove_action( 'wp_head', 'wp_generator' );

add_action( 'init', function() {
    if ( ! is_admin() ) {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
    }
} );
