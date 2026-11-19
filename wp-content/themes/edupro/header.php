<?php
/**
 * The header for the EduPro LMS theme.
 *
 * Displays all of the <head> section, dynamic Website Settings top announcement bar
 * with quick Phone/WhatsApp + interactive Multi-Currency switcher, sticky main header
 * with mega menu, live course search, user account dropdown, enrollment count,
 * and polished mobile off-canvas navigation drawer.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$edupro_is_logged_in = is_user_logged_in();
$edupro_current_user = $edupro_is_logged_in ? wp_get_current_user() : null;

// Safe helper for reading Website Settings (inc/site-settings.php).
$edupro_setting = function ( $key, $default = '' ) {
    if ( function_exists( 'edupro_get_site_setting' ) ) {
        $val = edupro_get_site_setting( $key, $default );
        return ( '' !== $val && null !== $val ) ? $val : $default;
    }
    return $default;
};

// Page URLs with safe fallbacks.
$edupro_login_page_id     = (int) get_option( 'edupro_login_page_id' );
$edupro_register_page_id  = (int) get_option( 'edupro_register_page_id' );
$edupro_dashboard_page_id = (int) get_option( 'edupro_dashboard_page_id' );

$edupro_login_url     = $edupro_login_page_id ? get_permalink( $edupro_login_page_id ) : home_url( '/student-login/' );
$edupro_register_url  = $edupro_register_page_id ? get_permalink( $edupro_register_page_id ) : home_url( '/student-register/' );
$edupro_dashboard_url = $edupro_dashboard_page_id ? get_permalink( $edupro_dashboard_page_id ) : home_url( '/dashboard/' );
$edupro_logout_url    = function_exists( 'edupro_get_logout_url' ) ? edupro_get_logout_url() : wp_logout_url( home_url( '/' ) );
$edupro_courses_url   = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );

// Brand identity & contact from Website Settings.
$edupro_site_name       = $edupro_setting( 'site_name', get_bloginfo( 'name' ) );
$edupro_site_tagline    = $edupro_setting( 'site_tagline', get_bloginfo( 'description', 'display' ) );
$edupro_header_logo_url = $edupro_setting( 'header_logo_url', '' );

$edupro_phone_primary   = trim( (string) $edupro_setting( 'phone_primary', '' ) );
$edupro_phone_href      = $edupro_phone_primary ? 'tel:' . preg_replace( '/[^0-9+]/', '', $edupro_phone_primary ) : '';
$edupro_whatsapp_num    = trim( (string) $edupro_setting( 'whatsapp_number', '' ) );
$edupro_whatsapp_url    = '';
if ( '' !== $edupro_whatsapp_num ) {
    if ( function_exists( 'edupro_get_whatsapp_url' ) ) {
        $edupro_whatsapp_url = edupro_get_whatsapp_url();
    } else {
        $wa_digits           = preg_replace( '/[^0-9]/', '', $edupro_whatsapp_num );
        $edupro_whatsapp_url = $wa_digits ? 'https://wa.me/' . $wa_digits : '';
    }
}

// Top announcement bar settings.
$edupro_announcement_enabled  = function_exists( 'edupro_get_site_setting' )
    ? ( '1' === (string) edupro_get_site_setting( 'announcement_enabled', '1' ) )
    : true;
$edupro_announcement_text     = apply_filters(
    'edupro_announcement_bar_text',
    $edupro_setting( 'announcement_text', __( '🎓 BLACK FRIDAY SALE — 70% OFF All Courses! Ends Sunday', 'edupro' ) )
);
$edupro_announcement_cta_text = $edupro_setting( 'announcement_cta_text', __( 'Shop Now →', 'edupro' ) );
$edupro_announcement_cta_url  = $edupro_setting( 'announcement_cta_url', $edupro_courses_url );

// Real shopping cart count (unpaid items only — never enrolled courses).
$edupro_cart_count = function_exists( 'edupro_get_cart_count' ) ? (int) edupro_get_cart_count() : 0;

// Fetch course categories for the Mega Menu & Mobile Drawer.
$edupro_all_categories = get_terms( array(
    'taxonomy'   => 'course_category',
    'hide_empty' => false,
    'orderby'    => 'count',
    'order'      => 'DESC',
) );
if ( is_wp_error( $edupro_all_categories ) || ! is_array( $edupro_all_categories ) ) {
    $edupro_all_categories = array();
}

$edupro_popular_categories = array_slice( $edupro_all_categories, 0, 6 );
$edupro_grid_categories    = array_slice( $edupro_all_categories, 0, 12 );

// Active currency & supported currencies for interactive switcher.
$edupro_active_currency = function_exists( 'edupro_get_active_currency' )
    ? edupro_get_active_currency()
    : ( get_option( 'edupro_default_currency', 'INR' ) ?: 'INR' );

$edupro_supported_currencies = function_exists( 'edupro_get_supported_currencies' )
    ? edupro_get_supported_currencies()
    : array(
        'INR' => array( 'code' => 'INR', 'symbol' => '₹', 'label' => 'India (INR)' ),
        'GBP' => array( 'code' => 'GBP', 'symbol' => '£', 'label' => 'UK (GBP)' ),
        'USD' => array( 'code' => 'USD', 'symbol' => '$', 'label' => 'Global (USD)' ),
    );

$edupro_currency_items = array();
foreach ( $edupro_supported_currencies as $curr_key => $curr_val ) {
    $code = strtoupper( is_array( $curr_val ) && ! empty( $curr_val['code'] ) ? $curr_val['code'] : ( is_string( $curr_key ) ? $curr_key : (string) $curr_val ) );
    if ( is_array( $curr_val ) && ! empty( $curr_val['symbol'] ) ) {
        $symbol = $curr_val['symbol'];
    } elseif ( function_exists( 'edupro_get_currency_symbol' ) ) {
        $symbol = edupro_get_currency_symbol( $code );
    } else {
        $symbol = ( 'GBP' === $code ) ? '£' : ( ( 'USD' === $code ) ? '$' : '₹' );
    }
    $label = ( is_array( $curr_val ) && ! empty( $curr_val['label'] ) ) ? $curr_val['label'] : $code;
    $edupro_currency_items[ $code ] = array(
        'code'   => $code,
        'symbol' => $symbol,
        'label'  => $label,
    );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary">
    <?php esc_html_e( 'Skip to content', 'edupro' ); ?>
</a>

<div id="page" class="site edupro-site">

    <?php if ( $edupro_announcement_enabled ) : ?>
        <!-- Top Announcement Bar -->
        <div id="edupro-announcement-bar" class="edupro-announcement-bar top-bar" role="region" aria-label="<?php esc_attr_e( 'Site Announcement', 'edupro' ); ?>" style="background-color:#1a1a2e;color:#ffffff;">
            <div class="edupro-container container edupro-announcement-bar__inner">
                <div class="edupro-announcement-bar__text">
                    <span><?php echo esc_html( $edupro_announcement_text ); ?></span>
                    <?php if ( '' !== $edupro_announcement_cta_text && '' !== $edupro_announcement_cta_url ) : ?>
                        <a href="<?php echo esc_url( $edupro_announcement_cta_url ); ?>" class="edupro-announcement-bar__cta">
                            <?php echo esc_html( $edupro_announcement_cta_text ); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="edupro-announcement-bar__right">
                    <?php if ( $edupro_phone_href || $edupro_whatsapp_url ) : ?>
                        <div class="edupro-topbar-contact">
                            <?php if ( $edupro_phone_href ) : ?>
                                <a href="<?php echo esc_url( $edupro_phone_href ); ?>" class="edupro-topbar-link edupro-topbar-phone" title="<?php esc_attr_e( 'Call Us', 'edupro' ); ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <span><?php echo esc_html( $edupro_phone_primary ); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ( $edupro_whatsapp_url ) : ?>
                                <a href="<?php echo esc_url( $edupro_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-topbar-link edupro-topbar-whatsapp" title="<?php esc_attr_e( 'Chat on WhatsApp', 'edupro' ); ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.25-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.49-8.41zM12.06 21.8h-.01a9.87 9.87 0 0 1-5.04-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.86 9.86 0 0 1-1.52-5.24c0-5.45 4.43-9.88 9.89-9.88 2.64 0 5.12 1.03 6.98 2.9a9.82 9.82 0 0 1 2.9 6.99c0 5.45-4.44 9.84-9.88 9.84zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z"/>
                                    </svg>
                                    <span><?php esc_html_e( 'WhatsApp', 'edupro' ); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="edupro-currency-switcher" role="group" aria-label="<?php esc_attr_e( 'Switch currency', 'edupro' ); ?>">
                        <?php foreach ( $edupro_currency_items as $curr_item ) : ?>
                            <?php
                            $is_curr_active  = ( strtoupper( $edupro_active_currency ) === $curr_item['code'] );
                            $curr_switch_url = add_query_arg( 'currency', $curr_item['code'] );
                            ?>
                            <a
                                href="<?php echo esc_url( $curr_switch_url ); ?>"
                                class="edupro-currency-btn <?php echo $is_curr_active ? 'is-active' : ''; ?>"
                                data-currency-switch="<?php echo esc_attr( $curr_item['code'] ); ?>"
                                aria-pressed="<?php echo $is_curr_active ? 'true' : 'false'; ?>"
                                title="<?php echo esc_attr( sprintf( __( 'Display prices in %s', 'edupro' ), $curr_item['label'] ) ); ?>"
                            >
                                <?php echo esc_html( $curr_item['symbol'] . ' ' . $curr_item['code'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( ! $edupro_is_logged_in ) : ?>
                        <a href="<?php echo esc_url( $edupro_login_url ); ?>" class="edupro-topbar-link">
                            <?php esc_html_e( 'Student Login', 'edupro' ); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $edupro_dashboard_url ); ?>" class="edupro-topbar-link">
                            <?php esc_html_e( 'My Dashboard', 'edupro' ); ?>
                        </a>
                    <?php endif; ?>
                    <button
                        type="button"
                        id="edupro-announcement-close"
                        class="edupro-announcement-bar__close announcement-close"
                        aria-label="<?php esc_attr_e( 'Dismiss announcement', 'edupro' ); ?>"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Sticky Header -->
    <header id="masthead" class="site-header edupro-header" role="banner">
        <div class="edupro-container container edupro-header__inner">

            <!-- Brand / Logo -->
            <div class="site-branding edupro-header__logo">
                <?php if ( '' !== $edupro_header_logo_url ) : ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-link edupro-logo-link" rel="home">
                        <img src="<?php echo esc_url( $edupro_header_logo_url ); ?>" alt="<?php echo esc_attr( $edupro_site_name ); ?>" class="custom-logo edupro-logo-img">
                    </a>
                <?php elseif ( has_custom_logo() ) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-logo-link" rel="home">
                        <span class="edupro-logo-icon" aria-hidden="true">
                            <svg width="32" height="32" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="36" height="36" rx="8" fill="#e31b23"/>
                                <path d="M18 8L6 14.5L18 21L30 14.5L18 8Z" fill="#ffffff"/>
                                <path d="M10 17.5V24.5C10 26.2 13.6 28 18 28C22.4 28 26 26.2 26 24.5V17.5L18 21.8L10 17.5Z" fill="#ffffff" fill-opacity="0.88"/>
                            </svg>
                        </span>
                        <span class="edupro-logo-text">
                            <span class="site-title"><?php echo esc_html( $edupro_site_name ); ?></span>
                            <?php if ( $edupro_site_tagline || is_customize_preview() ) : ?>
                                <span class="site-description"><?php echo esc_html( $edupro_site_tagline ); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mega Menu Trigger ("Find Course") -->
            <div class="edupro-megamenu-wrap has-megamenu">
                <button
                    type="button"
                    class="edupro-megamenu-trigger"
                    id="edupro-megamenu-toggle"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="edupro-megamenu-dropdown"
                >
                    <svg class="edupro-icon-grid" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    </svg>
                    <span><?php esc_html_e( 'Find Course', 'edupro' ); ?></span>
                    <svg class="edupro-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <!-- 4-Column Mega Menu Dropdown -->
                <div id="edupro-megamenu-dropdown" class="edupro-megamenu mega-menu" role="region" aria-label="<?php esc_attr_e( 'Course Categories Menu', 'edupro' ); ?>">
                    <div class="edupro-megamenu__grid">
                        <!-- Column 1 (Left): Popular Categories -->
                        <div class="edupro-megamenu__col edupro-megamenu__popular">
                            <h4 class="edupro-megamenu__heading"><?php esc_html_e( 'Popular Categories', 'edupro' ); ?></h4>
                            <ul class="edupro-megamenu__list">
                                <li>
                                    <a href="<?php echo esc_url( $edupro_courses_url ); ?>" class="edupro-megamenu__all-link">
                                        <strong><?php esc_html_e( 'All Courses', 'edupro' ); ?></strong>
                                        <span class="edupro-badge edupro-badge--red"><?php echo absint( wp_count_posts( 'lms_course' )->publish ); ?></span>
                                    </a>
                                </li>
                                <?php if ( ! empty( $edupro_popular_categories ) ) : ?>
                                    <?php foreach ( $edupro_popular_categories as $pop_cat ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_term_link( $pop_cat ) ); ?>">
                                                <span><?php echo esc_html( $pop_cat->name ); ?></span>
                                                <span class="edupro-megamenu__count"><?php echo absint( $pop_cat->count ); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li><a href="<?php echo esc_url( $edupro_courses_url ); ?>"><?php esc_html_e( 'Browse All Courses', 'edupro' ); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Columns 2-4 (Right 3 Columns): Course Categories Grid -->
                        <div class="edupro-megamenu__categories-area">
                            <div class="edupro-megamenu__area-header">
                                <h4 class="edupro-megamenu__heading"><?php esc_html_e( 'Explore by Subject', 'edupro' ); ?></h4>
                                <a href="<?php echo esc_url( $edupro_courses_url ); ?>" class="edupro-megamenu__view-all">
                                    <?php esc_html_e( 'View Full Catalog →', 'edupro' ); ?>
                                </a>
                            </div>
                            <div class="edupro-megamenu__cat-grid">
                                <?php
                                $cat_icons = array(
                                    'business'             => '💼',
                                    'technology'           => '💻',
                                    'design'               => '🎨',
                                    'marketing'            => '📈',
                                    'health-fitness'       => '💪',
                                    'personal-development' => '🚀',
                                );
                                if ( ! empty( $edupro_grid_categories ) ) :
                                    foreach ( $edupro_grid_categories as $cat ) :
                                        $icon = isset( $cat_icons[ $cat->slug ] ) ? $cat_icons[ $cat->slug ] : '📚';
                                        ?>
                                        <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="edupro-megamenu__cat-card">
                                            <span class="edupro-megamenu__cat-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
                                            <span class="edupro-megamenu__cat-info">
                                                <span class="edupro-megamenu__cat-name"><?php echo esc_html( $cat->name ); ?></span>
                                                <span class="edupro-megamenu__cat-meta">
                                                    <?php
                                                    printf(
                                                        /* translators: %d: number of courses */
                                                        esc_html( _n( '%d Course', '%d Courses', $cat->count, 'edupro' ) ),
                                                        absint( $cat->count )
                                                    );
                                                    ?>
                                                </span>
                                            </span>
                                        </a>
                                        <?php
                                    endforeach;
                                else :
                                    ?>
                                    <p class="edupro-megamenu__empty"><?php esc_html_e( 'No course categories created yet.', 'edupro' ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Search Bar -->
            <div class="edupro-header__search header-search">
                <form role="search" method="get" class="edupro-search-form" action="<?php echo esc_url( $edupro_courses_url ); ?>">
                    <label for="edupro-search" class="screen-reader-text"><?php esc_html_e( 'Search for courses', 'edupro' ); ?></label>
                    <input
                        type="search"
                        id="edupro-search"
                        class="edupro-search-input"
                        name="s"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                        placeholder="<?php esc_attr_e( 'What do you want to learn today?', 'edupro' ); ?>"
                        autocomplete="off"
                    >
                    <input type="hidden" name="post_type" value="lms_course">
                    <button type="submit" class="edupro-search-submit" aria-label="<?php esc_attr_e( 'Search courses', 'edupro' ); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
                <div id="edupro-search-results" class="edupro-search-dropdown" role="listbox" aria-label="<?php esc_attr_e( 'Live search results', 'edupro' ); ?>" hidden></div>
            </div>

            <!-- Primary Navigation -->
            <nav id="site-navigation" class="main-navigation edupro-nav" role="navigation" aria-label="<?php esc_attr_e( 'Primary Menu', 'edupro' ); ?>">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'menu_class'     => 'edupro-nav__list',
                    'container'      => false,
                    'fallback_cb'    => function() use ( $edupro_courses_url ) {
                        echo '<ul id="primary-menu" class="edupro-nav__list">';
                        echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'edupro' ) . '</a></li>';
                        echo '<li><a href="' . esc_url( $edupro_courses_url ) . '">' . esc_html__( 'Courses', 'edupro' ) . '</a></li>';
                        $instructors_url = get_post_type_archive_link( 'lms_instructor' );
                        if ( $instructors_url ) {
                            echo '<li><a href="' . esc_url( $instructors_url ) . '">' . esc_html__( 'Instructors', 'edupro' ) . '</a></li>';
                        }
                        $blog_page_id = (int) get_option( 'page_for_posts' );
                        if ( $blog_page_id ) {
                            echo '<li><a href="' . esc_url( get_permalink( $blog_page_id ) ) . '">' . esc_html__( 'Blog', 'edupro' ) . '</a></li>';
                        }
                        echo '</ul>';
                    },
                ) );
                ?>
            </nav>

            <!-- Header Actions: Shopping Cart Drawer Trigger + User Account / Login -->
            <div class="edupro-header__actions">

                <!-- Shopping Cart Slide-Out Drawer Trigger -->
                <button
                    type="button"
                    id="edupro-cart-trigger"
                    class="edupro-enrollment-cart edupro-cart-trigger"
                    aria-expanded="false"
                    aria-controls="edupro-cart-drawer"
                    title="<?php esc_attr_e( 'Shopping Cart', 'edupro' ); ?>"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Shopping Cart (%d items)', 'edupro' ), $edupro_cart_count ) ); ?>"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="edupro-enrollment-cart__count edupro-cart-count<?php echo 0 === $edupro_cart_count ? ' is-empty' : ''; ?>"><?php echo absint( $edupro_cart_count ); ?></span>
                </button>

                <?php if ( $edupro_is_logged_in && $edupro_current_user ) : ?>
                    <!-- Logged-in User Avatar Dropdown -->
                    <div class="edupro-user-menu">
                        <button
                            type="button"
                            class="edupro-user-menu__trigger"
                            aria-expanded="false"
                            aria-haspopup="true"
                        >
                            <?php echo get_avatar( $edupro_current_user->ID, 36, '', $edupro_current_user->display_name, array( 'class' => 'edupro-user-menu__avatar' ) ); ?>
                            <span class="edupro-user-menu__name"><?php echo esc_html( $edupro_current_user->first_name ?: $edupro_current_user->display_name ); ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="edupro-user-menu__dropdown" role="menu">
                            <div class="edupro-user-menu__header">
                                <strong><?php echo esc_html( $edupro_current_user->display_name ); ?></strong>
                                <span><?php echo esc_html( $edupro_current_user->user_email ); ?></span>
                            </div>
                            <ul class="edupro-user-menu__links">
                                <li><a href="<?php echo esc_url( $edupro_dashboard_url ); ?>" role="menuitem"><?php esc_html_e( 'My Courses', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'tab', 'profile', $edupro_dashboard_url ) ); ?>" role="menuitem"><?php esc_html_e( 'Profile', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'tab', 'certificates', $edupro_dashboard_url ) ); ?>" role="menuitem"><?php esc_html_e( 'Certificates', 'edupro' ); ?></a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'tab', 'orders', $edupro_dashboard_url ) ); ?>" role="menuitem"><?php esc_html_e( 'Order History', 'edupro' ); ?></a></li>
                                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                    <li><a href="<?php echo esc_url( admin_url() ); ?>" role="menuitem"><?php esc_html_e( 'WP Admin', 'edupro' ); ?></a></li>
                                <?php endif; ?>
                                <li class="edupro-user-menu__divider"></li>
                                <li><a href="<?php echo esc_url( $edupro_logout_url ); ?>" class="edupro-user-menu__logout" role="menuitem"><?php esc_html_e( 'Log Out', 'edupro' ); ?></a></li>
                            </ul>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- Logged-out Login & Register Buttons -->
                    <a href="<?php echo esc_url( $edupro_login_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-header__login">
                        <?php esc_html_e( 'Log In', 'edupro' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $edupro_register_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-header__register">
                        <?php esc_html_e( 'Sign Up', 'edupro' ); ?>
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Button -->
                <button
                    type="button"
                    id="edupro-mobile-toggle"
                    class="edupro-hamburger mobile-menu-toggle"
                    aria-label="<?php esc_attr_e( 'Open mobile menu', 'edupro' ); ?>"
                    aria-expanded="false"
                    aria-controls="edupro-mobile-drawer"
                >
                    <span class="edupro-hamburger__line"></span>
                    <span class="edupro-hamburger__line"></span>
                    <span class="edupro-hamburger__line"></span>
                </button>
            </div>

        </div>
    </header>

    <!-- Mobile Off-Canvas Slide Drawer -->
    <div id="edupro-mobile-overlay" class="edupro-mobile-overlay" aria-hidden="true"></div>
    <aside id="edupro-mobile-drawer" class="edupro-mobile-drawer mobile-drawer" aria-label="<?php esc_attr_e( 'Mobile Navigation', 'edupro' ); ?>" aria-hidden="true">
        <div class="edupro-mobile-drawer__header">
            <?php if ( '' !== $edupro_header_logo_url ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-logo-link" rel="home">
                    <img src="<?php echo esc_url( $edupro_header_logo_url ); ?>" alt="<?php echo esc_attr( $edupro_site_name ); ?>" class="edupro-logo-img">
                </a>
            <?php elseif ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-logo-link" rel="home">
                    <span class="edupro-logo-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="36" height="36" rx="8" fill="#e31b23"/>
                            <path d="M18 8L6 14.5L18 21L30 14.5L18 8Z" fill="#ffffff"/>
                            <path d="M10 17.5V24.5C10 26.2 13.6 28 18 28C22.4 28 26 26.2 26 24.5V17.5L18 21.8L10 17.5Z" fill="#ffffff" fill-opacity="0.88"/>
                        </svg>
                    </span>
                    <span class="site-title"><?php echo esc_html( $edupro_site_name ); ?></span>
                </a>
            <?php endif; ?>
            <button type="button" id="edupro-mobile-close" class="edupro-mobile-drawer__close" aria-label="<?php esc_attr_e( 'Close mobile menu', 'edupro' ); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="edupro-mobile-drawer__body">
            <!-- Mobile Search -->
            <form role="search" method="get" class="edupro-mobile-search" action="<?php echo esc_url( $edupro_courses_url ); ?>">
                <label for="edupro-mobile-search-input" class="screen-reader-text"><?php esc_html_e( 'Search courses', 'edupro' ); ?></label>
                <input
                    type="search"
                    id="edupro-mobile-search-input"
                    name="s"
                    value="<?php echo esc_attr( get_search_query() ); ?>"
                    placeholder="<?php esc_attr_e( 'Search courses…', 'edupro' ); ?>"
                >
                <input type="hidden" name="post_type" value="lms_course">
                <button type="submit" aria-label="<?php esc_attr_e( 'Search', 'edupro' ); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </form>

            <!-- Mobile Primary Menu -->
            <nav class="edupro-mobile-nav" aria-label="<?php esc_attr_e( 'Mobile Primary Menu', 'edupro' ); ?>">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'menu_class'     => 'edupro-mobile-nav__list',
                    'container'      => false,
                    'fallback_cb'    => function() use ( $edupro_courses_url ) {
                        echo '<ul class="edupro-mobile-nav__list">';
                        echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'edupro' ) . '</a></li>';
                        echo '<li><a href="' . esc_url( $edupro_courses_url ) . '">' . esc_html__( 'All Courses', 'edupro' ) . '</a></li>';
                        echo '</ul>';
                    },
                ) );
                ?>
            </nav>

            <!-- Mobile Course Categories -->
            <?php if ( ! empty( $edupro_all_categories ) ) : ?>
                <div class="edupro-mobile-categories">
                    <h4 class="edupro-mobile-categories__title"><?php esc_html_e( 'Course Categories', 'edupro' ); ?></h4>
                    <div class="edupro-mobile-categories__grid">
                        <?php foreach ( array_slice( $edupro_all_categories, 0, 8 ) as $m_cat ) : ?>
                            <a href="<?php echo esc_url( get_term_link( $m_cat ) ); ?>" class="edupro-mobile-cat-pill">
                                <span class="edupro-mobile-cat-pill__name"><?php echo esc_html( $m_cat->name ); ?></span>
                                <span class="edupro-mobile-categories__count"><?php echo absint( $m_cat->count ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Mobile Currency Switcher -->
            <div class="edupro-mobile-currency">
                <span class="edupro-mobile-currency__label"><?php esc_html_e( 'Currency:', 'edupro' ); ?></span>
                <div class="edupro-currency-switcher edupro-currency-switcher--mobile" role="group" aria-label="<?php esc_attr_e( 'Switch currency', 'edupro' ); ?>">
                    <?php foreach ( $edupro_currency_items as $curr_item ) : ?>
                        <?php
                        $is_curr_active  = ( strtoupper( $edupro_active_currency ) === $curr_item['code'] );
                        $curr_switch_url = add_query_arg( 'currency', $curr_item['code'] );
                        ?>
                        <a
                            href="<?php echo esc_url( $curr_switch_url ); ?>"
                            class="edupro-currency-btn <?php echo $is_curr_active ? 'is-active' : ''; ?>"
                            data-currency-switch="<?php echo esc_attr( $curr_item['code'] ); ?>"
                            aria-pressed="<?php echo $is_curr_active ? 'true' : 'false'; ?>"
                        >
                            <?php echo esc_html( $curr_item['symbol'] . ' ' . $curr_item['code'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Mobile Auth Actions -->
            <div class="edupro-mobile-auth">
                <?php if ( $edupro_is_logged_in ) : ?>
                    <a href="<?php echo esc_url( $edupro_dashboard_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                        <?php esc_html_e( 'My Dashboard', 'edupro' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $edupro_logout_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-btn--full">
                        <?php esc_html_e( 'Log Out', 'edupro' ); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( $edupro_login_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-btn--full">
                        <?php esc_html_e( 'Log In', 'edupro' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $edupro_register_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                        <?php esc_html_e( 'Sign Up Free', 'edupro' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Quick Phone & WhatsApp Contact -->
            <?php if ( $edupro_phone_href || $edupro_whatsapp_url ) : ?>
                <div class="edupro-mobile-contact">
                    <?php if ( $edupro_phone_href ) : ?>
                        <a href="<?php echo esc_url( $edupro_phone_href ); ?>" class="edupro-mobile-contact__btn edupro-mobile-contact__btn--phone">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <span><?php echo esc_html( $edupro_phone_primary ); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ( $edupro_whatsapp_url ) : ?>
                        <a href="<?php echo esc_url( $edupro_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-mobile-contact__btn edupro-mobile-contact__btn--whatsapp">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.25-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.59 0 11.95-5.36 11.95-11.95 0-3.19-1.24-6.19-3.49-8.41zM12.06 21.8h-.01a9.87 9.87 0 0 1-5.04-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.86 9.86 0 0 1-1.52-5.24c0-5.45 4.43-9.88 9.89-9.88 2.64 0 5.12 1.03 6.98 2.9a9.82 9.82 0 0 1 2.9 6.99c0 5.45-4.44 9.84-9.88 9.84zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.22 3.08.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z"/>
                            </svg>
                            <span><?php esc_html_e( 'WhatsApp Support', 'edupro' ); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div id="content" class="site-content">
