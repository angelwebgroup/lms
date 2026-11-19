<?php
/**
 * EduPro LMS Theme — functions.php
 *
 * Bootstrap file: loads all inc/ modules, registers menus/sidebars/image sizes,
 * declares theme-supports, and enqueues assets.
 *
 * @package EduPro
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 0. Constants
// ---------------------------------------------------------------------------
define( 'EDUPRO_VERSION',   '1.2.1' );
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
// 3. Enqueue Styles
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_enqueue_styles' ) ) :
    function edupro_enqueue_styles() {

        // Google Fonts: Inter + Poppins
        $google_fonts_url = add_query_arg(
            [
                'family'  => 'Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800',
                'display' => 'swap',
            ],
            'https://fonts.googleapis.com/css2'
        );
        wp_enqueue_style(
            'edupro-google-fonts',
            esc_url( $google_fonts_url ),
            [],
            null
        );

        // Main stylesheet
        wp_enqueue_style(
            'edupro-main',
            EDUPRO_URI . '/assets/css/main.css',
            [ 'edupro-google-fonts' ],
            EDUPRO_VERSION
        );

        // Course-specific styles
        wp_enqueue_style(
            'edupro-course',
            EDUPRO_URI . '/assets/css/course.css',
            [ 'edupro-main' ],
            EDUPRO_VERSION
        );

        // Student dashboard styles
        wp_enqueue_style(
            'edupro-dashboard',
            EDUPRO_URI . '/assets/css/dashboard.css',
            [ 'edupro-main' ],
            EDUPRO_VERSION
        );
    }
endif;
add_action( 'wp_enqueue_scripts', 'edupro_enqueue_styles' );

// ---------------------------------------------------------------------------
// 4. Enqueue Scripts
// ---------------------------------------------------------------------------
if ( ! function_exists( 'edupro_enqueue_scripts' ) ) :
    function edupro_enqueue_scripts() {

        // Main JS (jQuery as dependency — WordPress ships jQuery)
        wp_enqueue_script(
            'edupro-main',
            EDUPRO_URI . '/assets/js/main.js',
            [ 'jquery' ],
            EDUPRO_VERSION,
            true  // load in footer
        );

        // Course player JS
        wp_enqueue_script(
            'edupro-course-player',
            EDUPRO_URI . '/assets/js/course-player.js',
            [ 'jquery', 'edupro-main' ],
            EDUPRO_VERSION,
            true
        );

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
        'title'    => __( 'EduPro Settings', 'edupro' ),
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
        'default'           => sprintf( '&copy; %d EduPro. All rights reserved.', date( 'Y' ) ),
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
// 10. Remove WordPress version from public head for security
// ---------------------------------------------------------------------------
remove_action( 'wp_head', 'wp_generator' );
