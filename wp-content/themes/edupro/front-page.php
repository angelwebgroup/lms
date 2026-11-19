<?php
/**
 * The Front Page (Homepage) template for EduPro LMS (v1.3.0 Luxury Glassmorphism).
 *
 * Sections (Dashboard-controlled via Website Settings):
 *  1. Luminous Glassmorphism Hero (or Dark Pro Hero) with search, trending pills & 3 feature cards
 *  2. Trusted Brands Continuous Auto-Carousel (300+ Leading Universities & Companies)
 *  3. 3-Column Color-Coded Glass Course Categories Grid
 *  4. Featured Courses 4-Column Grid (WP_Query + template-parts/course-card.php)
 *  5. Success Stories / Alumni Spotlight (with Video Lightbox modal trigger)
 *  6. Order Gift Card Section (3D dark-gold Gift Pass + benefits + CTA)
 *  7. Animated Platform Stats Counters
 *  8. "Why Choose Us" 3 Feature Columns
 *  9. Split Google Reviews Summary Card + Carousel (inc/google-reviews.php)
 * 10. Newsletter CTA Section
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Safe helper for reading Website Settings (inc/site-settings.php).
$edupro_setting = function ( $key, $default = '' ) {
    if ( function_exists( 'edupro_get_site_setting' ) ) {
        $val = edupro_get_site_setting( $key, $default );
        return ( '' !== $val && null !== $val ) ? $val : $default;
    }
    return $default;
};

$edupro_toggle = function ( $key, $default = '1' ) {
    if ( function_exists( 'edupro_get_site_setting' ) ) {
        return '1' === (string) edupro_get_site_setting( $key, $default );
    }
    return '1' === (string) $default;
};

$courses_url  = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
$register_id  = (int) get_option( 'edupro_register_page_id' );
$register_url = $register_id ? get_permalink( $register_id ) : home_url( '/student-register/' );
$site_name    = $edupro_setting( 'site_name', get_bloginfo( 'name' ) );

// ── 1. Hero Settings ──────────────────────────────────────────────────────────
$hero_style          = $edupro_setting( 'hero_style', 'glass_light' );
$hero_is_glass       = ( 'dark_pro' !== $hero_style );
$hero_badge          = $edupro_setting( 'hero_badge', __( "UK & India's Leading Online Learning & CPD Provider", 'edupro' ) );
$hero_heading        = $edupro_setting( 'hero_heading', get_theme_mod( 'edupro_hero_heading', __( 'An Interactive Platform For', 'edupro' ) ) );
$hero_highlight_word = $edupro_setting( 'hero_highlight_word', __( 'Learning', 'edupro' ) );
$hero_subheading     = $edupro_setting( 'hero_subtitle', get_theme_mod( 'edupro_hero_subheading', __( 'Advance your career from anywhere in the world with industry-recognized certifications, expert tutor mentorship, and flexible self-paced study.', 'edupro' ) ) );
$hero_video_url      = get_theme_mod( 'edupro_hero_video_url', '' );

$hero_highlights = array(
    array(
        'title' => $edupro_setting( 'hero_card_1_title', __( '1000+ Online Courses', 'edupro' ) ),
        'desc'  => $edupro_setting( 'hero_card_1_desc', __( 'CPD, QLS & career-focused tracks', 'edupro' ) ),
        'tint'  => 'rose',
        'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
    ),
    array(
        'title' => $edupro_setting( 'hero_card_2_title', __( 'Lifetime Access', 'edupro' ) ),
        'desc'  => $edupro_setting( 'hero_card_2_desc', __( 'Study at your own pace, 24/7', 'edupro' ) ),
        'tint'  => 'purple',
        'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    ),
    array(
        'title' => $edupro_setting( 'hero_card_3_title', __( 'Accredited Certificates', 'edupro' ) ),
        'desc'  => $edupro_setting( 'hero_card_3_desc', __( 'Instant verifiable digital PDF', 'edupro' ) ),
        'tint'  => 'emerald',
        'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
    ),
);

// ── Dynamic Counts ────────────────────────────────────────────────────────────
$published_courses_count     = (int) ( wp_count_posts( 'lms_course' )->publish ?? 0 );
$published_instructors_count = (int) ( wp_count_posts( 'lms_instructor' )->publish ?? 0 );
$stat_students               = max( 10000, count_users()['total_users'] ?? 0 );
$stat_courses                = max( 500, $published_courses_count );
$stat_instructors            = max( 50, $published_instructors_count );

// ── Featured Categories (9 items for clean 3x3 glass grid) ────────────────────
$featured_categories = get_terms( array(
    'taxonomy'   => 'course_category',
    'hide_empty' => false,
    'number'     => 9,
    'orderby'    => 'count',
    'order'      => 'DESC',
) );
if ( is_wp_error( $featured_categories ) || ! is_array( $featured_categories ) ) {
    $featured_categories = array();
}

$category_icon_map = array(
    'business'                    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'business-management'         => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'technology'                  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
    'it-cybersecurity'            => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    'design'                      => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><circle cx="11" cy="11" r="2"></circle></svg>',
    'marketing'                   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
    'health-fitness'              => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
    'health-social-care'          => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
    'phlebotomy-healthcare'       => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
    'personal-development'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    'assessing-quality-assurance' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>',
    'functional-skills'           => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>',
);
$default_cat_svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>';
$cat_tints       = array( 'rose', 'purple', 'amber', 'emerald', 'sky', 'pink', 'violet', 'indigo', 'orange' );

// ── Featured Courses Query (preserved 100%) ───────────────────────────────────
$featured_courses_query = new WP_Query( array(
    'post_type'      => 'lms_course',
    'post_status'    => 'publish',
    'posts_per_page' => 8,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

// ── Section Toggles & Content ─────────────────────────────────────────────────
$brands_enabled    = $edupro_toggle( 'brands_enabled', '1' );
$spotlight_enabled = $edupro_toggle( 'spotlight_enabled', '1' );
$giftcard_enabled  = $edupro_toggle( 'giftcard_enabled', '1' );

// Alumni Spotlight settings.
$spotlight_badge        = $edupro_setting( 'spotlight_badge', __( 'Alumni Spotlight', 'edupro' ) );
$spotlight_heading      = $edupro_setting( 'spotlight_heading', __( 'Success Stories', 'edupro' ) );
$spotlight_text         = $edupro_setting( 'spotlight_text', __( 'Thousands of learners across the UK, India, and 140+ countries have launched new careers, earned promotions, and gained CPD-accredited qualifications through our interactive online courses.', 'edupro' ) );
$spotlight_text_2       = $edupro_setting( 'spotlight_text_2', __( 'Hear directly from our graduates about how structured practical assessments and 24/7 tutor mentorship helped them achieve real-world career milestones.', 'edupro' ) );
$spotlight_stat_1_val   = $edupro_setting( 'spotlight_stat_1_val', '96%' );
$spotlight_stat_1_label = $edupro_setting( 'spotlight_stat_1_label', __( 'Career Advancement Rate', 'edupro' ) );
$spotlight_stat_2_val   = $edupro_setting( 'spotlight_stat_2_val', '140+' );
$spotlight_stat_2_label = $edupro_setting( 'spotlight_stat_2_label', __( 'Countries Represented', 'edupro' ) );
$spotlight_video_url    = $edupro_setting( 'spotlight_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
$spotlight_video_thumb  = $edupro_setting( 'spotlight_video_thumb', '' );
$spotlight_video_name   = $edupro_setting( 'spotlight_video_name', 'OLIVIA SZULC' );
$spotlight_video_label  = $edupro_setting( 'spotlight_video_label', __( 'GRADUATE INTERVIEW', 'edupro' ) );

// Gift Card settings.
$giftcard_eyebrow     = $edupro_setting( 'giftcard_eyebrow', __( 'SHARE KNOWLEDGE', 'edupro' ) );
$giftcard_heading     = $edupro_setting( 'giftcard_heading', __( 'Order Gift Card for Your Loved Ones Today', 'edupro' ) );
$giftcard_desc        = $edupro_setting( 'giftcard_desc', __( 'Give the gift of lifelong career growth. Our digital Gift Pass unlocks access to hundreds of accredited online courses — delivered instantly to their inbox.', 'edupro' ) );
$giftcard_bullets_raw = $edupro_setting(
    'giftcard_bullets',
    implode(
        "\n",
        array(
            __( 'Redeemable across 500+ accredited online courses', 'edupro' ),
            __( 'Instant digital email delivery with personal message', 'edupro' ),
            __( 'Valid for 12 months with no hidden fees', 'edupro' ),
            __( 'Available in GBP £ and INR ₹ denominations', 'edupro' ),
        )
    )
);
$giftcard_bullets     = array_filter( array_map( 'trim', explode( "\n", (string) $giftcard_bullets_raw ) ) );
$giftcard_value_label = $edupro_setting( 'giftcard_value_label', '£25 – £150' );
$giftcard_cta_text    = $edupro_setting( 'giftcard_cta_text', __( 'Order Gift Card', 'edupro' ) );
$giftcard_cta_url     = $edupro_setting( 'giftcard_cta_url', $courses_url );
?>

<main id="primary" class="site-main edupro-front-page">

    <!-- =====================================================================
         1. HERO SECTION (Luminous Glassmorphism default / Dark Pro toggle)
         ===================================================================== -->
    <section class="edupro-hero hero-section <?php echo $hero_is_glass ? 'edupro-hero--glass' : 'edupro-hero--dark'; ?>">
        <?php if ( $hero_is_glass ) : ?>
            <!-- Soft Ambient Mesh Blobs (confined to hero via overflow:hidden) -->
            <div class="edupro-ambient-blob edupro-ambient-blob--1" aria-hidden="true"></div>
            <div class="edupro-ambient-blob edupro-ambient-blob--2" aria-hidden="true"></div>
            <div class="edupro-ambient-blob edupro-ambient-blob--3" aria-hidden="true"></div>
        <?php else : ?>
            <?php if ( ! empty( $hero_video_url ) ) : ?>
                <div class="edupro-hero__video-wrap" aria-hidden="true">
                    <video class="edupro-hero__video" autoplay muted loop playsinline>
                        <source src="<?php echo esc_url( $hero_video_url ); ?>" type="video/mp4">
                    </video>
                </div>
            <?php endif; ?>
            <div class="edupro-hero__overlay" aria-hidden="true"></div>
        <?php endif; ?>

        <div class="edupro-container container edupro-hero__inner">
            <div class="edupro-hero__content">
                <p class="edupro-hero__eyebrow-editorial">
                    <span class="edupro-hero__eyebrow-rule" aria-hidden="true"></span>
                    <span><?php echo esc_html( $hero_badge ); ?></span>
                </p>

                <h1 class="edupro-hero__title">
                    <?php echo esc_html( $hero_heading ); ?>
                    <?php if ( '' !== $hero_highlight_word ) : ?>
                        <span class="edupro-text-gradient"><?php echo esc_html( $hero_highlight_word ); ?></span>
                    <?php endif; ?>
                </h1>

                <p class="edupro-hero__subtitle"><?php echo esc_html( $hero_subheading ); ?></p>

                <!-- Hero Course Search Bar -->
                <form role="search" method="get" class="edupro-hero__search" action="<?php echo esc_url( $courses_url ); ?>">
                    <label for="edupro-hero-search-input" class="screen-reader-text">
                        <?php esc_html_e( 'Search for online courses', 'edupro' ); ?>
                    </label>
                    <div class="edupro-hero__search-box">
                        <div class="edupro-hero__search-field">
                            <svg class="edupro-hero__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input
                                type="search"
                                id="edupro-hero-search-input"
                                name="s"
                                placeholder="<?php esc_attr_e( 'Search 500+ courses (e.g., Phlebotomy, Functional Skills, Python)…', 'edupro' ); ?>"
                            >
                        </div>
                        <input type="hidden" name="post_type" value="lms_course">
                        <button type="submit" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--pill">
                            <?php esc_html_e( 'Search Courses', 'edupro' ); ?>
                        </button>
                    </div>
                </form>

                <!-- Quick Category Pills -->
                <?php if ( ! empty( $featured_categories ) ) : ?>
                    <div class="edupro-hero__popular">
                        <span class="edupro-hero__popular-label"><?php esc_html_e( 'Trending:', 'edupro' ); ?></span>
                        <?php foreach ( array_slice( $featured_categories, 0, 4 ) as $hero_cat ) : ?>
                            <a href="<?php echo esc_url( get_term_link( $hero_cat ) ); ?>" class="edupro-hero__pill">
                                <?php echo esc_html( $hero_cat->name ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- 3 Frosted Hero Highlight Cards -->
                <div class="edupro-hero-highlights">
                    <?php foreach ( $hero_highlights as $hl ) : ?>
                        <div class="edupro-hero-highlight-card edupro-glass-panel">
                            <span class="edupro-hero-highlight-card__icon edupro-hero-highlight-card__icon--<?php echo esc_attr( $hl['tint'] ); ?>" aria-hidden="true">
                                <?php echo $hl['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="edupro-hero-highlight-card__text">
                                <strong><?php echo esc_html( $hl['title'] ); ?></strong>
                                <span><?php echo esc_html( $hl['desc'] ); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Hero Right Glass Preview Card -->
            <div class="edupro-hero__visual">
                <div class="edupro-hero__card edupro-glass-panel">
                    <div class="edupro-hero__card-badge"><?php esc_html_e( '🔥 70% OFF Limited Offer', 'edupro' ); ?></div>
                    <h3><?php esc_html_e( 'Start Your Certification Journey', 'edupro' ); ?></h3>
                    <ul class="edupro-hero__checklist">
                        <li>✓ <?php esc_html_e( 'Instant access to HD video lessons & quizzes', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( 'CPD Accredited & QLS Endorsed Certificates', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( 'Pay securely in ₹ INR or £ GBP', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( '30-Day 100% Money-Back Guarantee', 'edupro' ); ?></li>
                    </ul>

                    <!-- Compact Hero Mini Stats (2-Column Horizontal Row) -->
                    <div class="edupro-hero__card-stats">
                        <div class="edupro-hero__stat">
                            <strong class="edupro-counter" data-target="<?php echo absint( $stat_students ); ?>" data-suffix="+">10,000+</strong>
                            <span><?php esc_html_e( 'Active Students', 'edupro' ); ?></span>
                        </div>
                        <div class="edupro-hero__stat">
                            <strong class="edupro-counter" data-target="<?php echo absint( $stat_courses ); ?>" data-suffix="+">500+</strong>
                            <span><?php esc_html_e( 'Accredited Courses', 'edupro' ); ?></span>
                        </div>
                    </div>

                    <div class="edupro-hero__card-actions">
                        <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                            <?php esc_html_e( 'Explore All Courses', 'edupro' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $register_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-btn--full">
                            <?php esc_html_e( 'Create Free Account', 'edupro' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         2. TRUSTED BRANDS CONTINUOUS AUTO-CAROUSEL
         ===================================================================== -->
    <?php if ( $brands_enabled ) : ?>
        <?php
        if ( function_exists( 'edupro_render_brands_carousel' ) ) {
            echo edupro_render_brands_carousel(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            // Built-in fallback if inc/site-settings.php helper is unavailable.
            $fallback_brands = array(
                array( 'name' => 'Pearson', 'sublabel' => 'Approved Centre', 'color' => '#0369a1' ),
                array( 'name' => 'edexcel', 'sublabel' => 'Qualifications', 'color' => '#be123c' ),
                array( 'name' => 'QUALITY LICENCE', 'sublabel' => 'SCHEME ENDORSED', 'color' => '#7c3aed' ),
                array( 'name' => 'RoSPA', 'sublabel' => 'Member', 'color' => '#047857' ),
                array( 'name' => 'The CPD', 'sublabel' => 'Certification Service', 'color' => '#b45309' ),
                array( 'name' => 'NCFE', 'sublabel' => 'CACHE Endorsed', 'color' => '#4338ca' ),
                array( 'name' => 'IOSH', 'sublabel' => 'Approved', 'color' => '#0f766e' ),
                array( 'name' => 'NHS', 'sublabel' => 'Accepted Training', 'color' => '#1d4ed8' ),
            );
            ?>
            <section class="edupro-brands-section" aria-label="<?php esc_attr_e( 'Trusted by Leading Universities and Companies', 'edupro' ); ?>">
                <div class="edupro-container container">
                    <h2 class="edupro-brands-section__heading">
                        <?php esc_html_e( 'Trusted by', 'edupro' ); ?>
                        <span class="edupro-text-crimson"><?php esc_html_e( '300+ Leading Universities', 'edupro' ); ?></span>
                        <?php esc_html_e( 'and Companies Globally', 'edupro' ); ?>
                    </h2>
                    <div class="edupro-brands-carousel" data-edupro-brands-carousel>
                        <button type="button" class="edupro-brands-nav edupro-brands-nav--prev" aria-label="<?php esc_attr_e( 'Previous partners', 'edupro' ); ?>">&#8249;</button>
                        <div class="edupro-brands-viewport">
                            <div class="edupro-brands-track">
                                <?php foreach ( array_merge( $fallback_brands, $fallback_brands ) as $fb_idx => $fb ) : ?>
                                    <div class="edupro-brand-card" <?php echo $fb_idx >= count( $fallback_brands ) ? 'aria-hidden="true"' : ''; ?>>
                                        <span class="edupro-brand-card__dot" style="background:<?php echo esc_attr( $fb['color'] ); ?>;"></span>
                                        <span class="edupro-brand-card__text">
                                            <strong><?php echo esc_html( $fb['name'] ); ?></strong>
                                            <small><?php echo esc_html( $fb['sublabel'] ); ?></small>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="edupro-brands-nav edupro-brands-nav--next" aria-label="<?php esc_attr_e( 'Next partners', 'edupro' ); ?>">&#8250;</button>
                    </div>
                </div>
            </section>
        <?php } ?>
    <?php endif; ?>

    <!-- =====================================================================
         3. 3-COLUMN COLOR-CODED GLASS COURSE CATEGORIES GRID
         ===================================================================== -->
    <section class="edupro-section edupro-categories-section" id="categories">
        <div class="edupro-container container">
            <div class="edupro-section-header">
                <div>
                    <span class="edupro-section-eyebrow"><?php esc_html_e( 'Browse By Subject', 'edupro' ); ?></span>
                    <h2 class="edupro-section-title"><?php esc_html_e( 'Explore Popular Course Categories', 'edupro' ); ?></h2>
                </div>
                <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--pill">
                    <?php esc_html_e( 'View All Categories →', 'edupro' ); ?>
                </a>
            </div>

            <?php if ( ! empty( $featured_categories ) ) : ?>
                <div class="edupro-categories-grid">
                    <?php foreach ( $featured_categories as $cat_idx => $cat ) : ?>
                        <?php
                        $cat_icon_svg = isset( $category_icon_map[ $cat->slug ] ) ? $category_icon_map[ $cat->slug ] : $default_cat_svg;
                        $cat_tint     = $cat_tints[ $cat_idx % count( $cat_tints ) ];
                        ?>
                        <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="edupro-cat-card edupro-category-card edupro-glass-panel animate-on-scroll">
                            <span class="edupro-cat-card__icon edupro-category-card__icon edupro-cat-card__icon--<?php echo esc_attr( $cat_tint ); ?>" aria-hidden="true">
                                <?php echo $cat_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="edupro-cat-card__body">
                                <h3 class="edupro-cat-card__name edupro-category-card__name"><?php echo esc_html( $cat->name ); ?></h3>
                                <span class="edupro-cat-card__count edupro-category-card__count">
                                    <?php
                                    printf(
                                        /* translators: %d: number of courses in category */
                                        esc_html( _n( '%d Course', '%d Courses', $cat->count, 'edupro' ) ),
                                        absint( $cat->count )
                                    );
                                    ?>
                                </span>
                            </div>
                            <span class="edupro-cat-card__arrow" aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="edupro-empty-inline"><?php esc_html_e( 'Course categories will appear here once added.', 'edupro' ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- =====================================================================
         4. FEATURED COURSES SECTION (4-Col Grid using template-parts/course-card.php)
         ===================================================================== -->
    <section class="edupro-section edupro-courses-section edupro-section--tinted" id="featured-courses">
        <div class="edupro-container container">
            <div class="edupro-section-header">
                <div>
                    <span class="edupro-section-eyebrow"><?php esc_html_e( 'Most Popular', 'edupro' ); ?></span>
                    <h2 class="edupro-section-title"><?php esc_html_e( 'Featured Career-Ready Courses', 'edupro' ); ?></h2>
                </div>
                <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--pill">
                    <?php esc_html_e( 'View All Courses →', 'edupro' ); ?>
                </a>
            </div>

            <?php if ( $featured_courses_query->have_posts() ) : ?>
                <div class="course-grid edupro-course-grid">
                    <?php
                    while ( $featured_courses_query->have_posts() ) :
                        $featured_courses_query->the_post();
                        get_template_part( 'template-parts/course-card', null, array( 'course_id' => get_the_ID() ) );
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            <?php else : ?>
                <div class="edupro-empty-state edupro-glass-panel">
                    <h3><?php esc_html_e( 'Courses Coming Soon!', 'edupro' ); ?></h3>
                    <p><?php esc_html_e( 'Publish your first course under WP Admin → Courses → Add New to showcase it here.', 'edupro' ); ?></p>
                    <?php if ( current_user_can( 'publish_posts' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lms_course' ) ); ?>" class="edupro-btn edupro-btn--primary btn-primary">
                            <?php esc_html_e( 'Add Your First Course', 'edupro' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- =====================================================================
         5. SUCCESS STORIES / ALUMNI SPOTLIGHT (Dashboard-Controlled)
         ===================================================================== -->
    <?php if ( $spotlight_enabled ) : ?>
        <section class="edupro-section edupro-spotlight-section" id="success-stories">
            <div class="edupro-container container">
                <div class="edupro-spotlight-card edupro-glass-panel animate-on-scroll">
                    <div class="edupro-spotlight__content">
                        <span class="edupro-spotlight__badge"><?php echo esc_html( $spotlight_badge ); ?></span>
                        <h2 class="edupro-spotlight__title"><?php echo esc_html( $spotlight_heading ); ?></h2>
                        <p class="edupro-spotlight__text"><?php echo esc_html( $spotlight_text ); ?></p>
                        <?php if ( '' !== $spotlight_text_2 ) : ?>
                            <p class="edupro-spotlight__text"><?php echo esc_html( $spotlight_text_2 ); ?></p>
                        <?php endif; ?>

                        <div class="edupro-spotlight__stats">
                            <div class="edupro-spotlight__stat">
                                <strong><?php echo esc_html( $spotlight_stat_1_val ); ?></strong>
                                <span><?php echo esc_html( $spotlight_stat_1_label ); ?></span>
                            </div>
                            <div class="edupro-spotlight__stat">
                                <strong><?php echo esc_html( $spotlight_stat_2_val ); ?></strong>
                                <span><?php echo esc_html( $spotlight_stat_2_label ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="edupro-spotlight__media">
                        <button
                            type="button"
                            class="edupro-spotlight__video"
                            data-video-modal="<?php echo esc_url( $spotlight_video_url ); ?>"
                            data-video-title="<?php echo esc_attr( $spotlight_video_name . ' — ' . $spotlight_video_label ); ?>"
                            aria-label="<?php echo esc_attr( sprintf( __( 'Play graduate story video: %s', 'edupro' ), $spotlight_video_name ) ); ?>"
                            <?php echo '' !== $spotlight_video_thumb ? 'style="background-image:url(' . esc_url( $spotlight_video_thumb ) . ');"' : ''; ?>
                        >
                            <span class="edupro-spotlight__video-overlay" aria-hidden="true"></span>
                            <span class="edupro-spotlight__video-pill"><?php echo esc_html( $spotlight_video_label ); ?></span>
                            <span class="edupro-spotlight__play-btn" aria-hidden="true">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
                                    <polygon points="6 4 20 12 6 20 6 4"></polygon>
                                </svg>
                            </span>
                            <span class="edupro-spotlight__video-caption">
                                <strong><?php echo esc_html( $spotlight_video_name ); ?></strong>
                                <small><?php esc_html_e( 'Watch Graduate Story', 'edupro' ); ?></small>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- =====================================================================
         6. ORDER GIFT CARD SECTION (3D Luxury Gift Pass — Dashboard-Controlled)
         ===================================================================== -->
    <?php if ( $giftcard_enabled ) : ?>
        <section class="edupro-section edupro-giftcard-section edupro-section--tinted" id="gift-card">
            <div class="edupro-container container">
                <div class="edupro-giftcard-grid">
                    <!-- Left: 3D Perspective Dark-Gold Gift Pass -->
                    <div class="edupro-giftcard-visual animate-on-scroll">
                        <div class="edupro-giftcard-3d" role="img" aria-label="<?php echo esc_attr( sprintf( __( '%s Official Gift Pass', 'edupro' ), $site_name ) ); ?>">
                            <div class="edupro-giftcard-3d__shine" aria-hidden="true"></div>
                            <div class="edupro-giftcard-3d__top">
                                <span class="edupro-giftcard-3d__brand"><?php echo esc_html( $site_name ); ?></span>
                                <span class="edupro-giftcard-3d__pass-tag"><?php esc_html_e( 'OFFICIAL GIFT PASS', 'edupro' ); ?></span>
                            </div>
                            <div class="edupro-giftcard-3d__center">
                                <span class="edupro-giftcard-3d__chip" aria-hidden="true"></span>
                                <h3 class="edupro-giftcard-3d__headline"><?php esc_html_e( 'GIFT OF LEARNING', 'edupro' ); ?></h3>
                                <p class="edupro-giftcard-3d__sub"><?php esc_html_e( 'Accredited Online Courses & Certifications', 'edupro' ); ?></p>
                            </div>
                            <div class="edupro-giftcard-3d__bottom">
                                <div>
                                    <small><?php esc_html_e( 'DENOMINATIONS', 'edupro' ); ?></small>
                                    <strong><?php echo esc_html( $giftcard_value_label ); ?></strong>
                                </div>
                                <span class="edupro-giftcard-3d__valid"><?php esc_html_e( 'VALID 12 MONTHS', 'edupro' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Benefits & CTA -->
                    <div class="edupro-giftcard-content animate-on-scroll">
                        <span class="edupro-section-eyebrow"><?php echo esc_html( $giftcard_eyebrow ); ?></span>
                        <h2 class="edupro-section-title"><?php echo esc_html( $giftcard_heading ); ?></h2>
                        <p class="edupro-giftcard-desc"><?php echo esc_html( $giftcard_desc ); ?></p>

                        <?php if ( ! empty( $giftcard_bullets ) ) : ?>
                            <ul class="edupro-giftcard-bullets">
                                <?php foreach ( $giftcard_bullets as $g_bullet ) : ?>
                                    <li>
                                        <span class="edupro-giftcard-check" aria-hidden="true">✓</span>
                                        <span><?php echo esc_html( $g_bullet ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <div class="edupro-giftcard-actions">
                            <a href="<?php echo esc_url( $giftcard_cta_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--pill edupro-btn--lg">
                                <?php echo esc_html( $giftcard_cta_text ); ?> →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- =====================================================================
         7. ANIMATED STATS COUNTER SECTION
         ===================================================================== -->
    <section class="edupro-stats-section" aria-label="<?php esc_attr_e( 'Platform Statistics', 'edupro' ); ?>">
        <div class="edupro-container container">
            <div class="edupro-stats-grid">
                <div class="edupro-stat-box edupro-stat-card animate-on-scroll">
                    <div class="edupro-stat-box__icon edupro-stat-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                    </div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_students ); ?>" data-suffix="+">10,000+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Enrolled Students', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box edupro-stat-card animate-on-scroll">
                    <div class="edupro-stat-box__icon edupro-stat-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_courses ); ?>" data-suffix="+">500+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Expert-Led Courses', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box edupro-stat-card animate-on-scroll">
                    <div class="edupro-stat-box__icon edupro-stat-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_instructors ); ?>" data-suffix="+">50+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Certified Instructors', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box edupro-stat-card animate-on-scroll">
                    <div class="edupro-stat-box__icon edupro-stat-card__icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="4.9" data-decimals="1" data-suffix=" ★">4.9 ★</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Student Satisfaction Rating', 'edupro' ); ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         8. "WHY CHOOSE US" SECTION (3 Feature Columns with Icons)
         ===================================================================== -->
    <section class="edupro-section edupro-why-section">
        <div class="edupro-container container">
            <div class="edupro-section-header edupro-section-header--center">
                <span class="edupro-section-eyebrow"><?php esc_html_e( 'Our Advantage', 'edupro' ); ?></span>
                <h2 class="edupro-section-title"><?php esc_html_e( 'Why Learners Worldwide Choose Us', 'edupro' ); ?></h2>
                <p class="edupro-section-subtitle">
                    <?php esc_html_e( 'Everything you need to gain real-world skills, earn recognized credentials, and stand out to employers.', 'edupro' ); ?>
                </p>
            </div>

            <div class="edupro-features-grid">
                <div class="edupro-feature-card edupro-glass-panel animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="7"></circle>
                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                        </svg>
                    </div>
                    <h3 class="edupro-feature-card__title"><?php esc_html_e( 'Accredited Certificates', 'edupro' ); ?></h3>
                    <p class="edupro-feature-card__desc">
                        <?php esc_html_e( 'Earn verifiable digital and printable certificates upon completion to showcase on LinkedIn, resumes, and job applications.', 'edupro' ); ?>
                    </p>
                </div>

                <div class="edupro-feature-card edupro-glass-panel animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                    </div>
                    <h3 class="edupro-feature-card__title"><?php esc_html_e( 'Self-Paced HD Video Lessons', 'edupro' ); ?></h3>
                    <p class="edupro-feature-card__desc">
                        <?php esc_html_e( 'Study on your own schedule with lifetime access to structured video tutorials, downloadable PDFs, and interactive quizzes.', 'edupro' ); ?>
                    </p>
                </div>

                <div class="edupro-feature-card edupro-glass-panel animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="edupro-feature-card__title"><?php esc_html_e( 'Industry-Expert Mentors', 'edupro' ); ?></h3>
                    <p class="edupro-feature-card__desc">
                        <?php esc_html_e( 'Every curriculum is authored by vetted practitioners with real-world industry experience and dedicated tutor support.', 'edupro' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         9. SPLIT GOOGLE REVIEWS SUMMARY CARD + CAROUSEL
         ===================================================================== -->
    <section class="edupro-section edupro-reviews-section edupro-section--tinted" id="reviews">
        <div class="edupro-container container">
            <div class="edupro-section-header edupro-section-header--center">
                <span class="edupro-section-eyebrow"><?php esc_html_e( 'Verified Student Feedback', 'edupro' ); ?></span>
                <h2 class="edupro-section-title"><?php esc_html_e( 'What Our Students Say on Google', 'edupro' ); ?></h2>
                <p class="edupro-section-subtitle">
                    <?php esc_html_e( 'Join thousands of learners who transformed their careers with accredited online learning.', 'edupro' ); ?>
                </p>
            </div>

            <?php
            if ( function_exists( 'edupro_render_google_reviews' ) ) {
                echo edupro_render_google_reviews( 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif ( function_exists( 'edupro_google_reviews_widget' ) ) {
                echo edupro_google_reviews_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </div>
    </section>

    <!-- =====================================================================
         10. NEWSLETTER CTA SECTION
         ===================================================================== -->
    <section class="edupro-cta-section">
        <div class="edupro-container container">
            <div class="edupro-cta-box">
                <div class="edupro-cta-box__content">
                    <span class="edupro-cta-box__badge"><?php esc_html_e( 'Weekly Learning Digest', 'edupro' ); ?></span>
                    <h2 class="edupro-cta-box__title"><?php esc_html_e( 'Get Exclusive Course Discounts & Free Career Guides', 'edupro' ); ?></h2>
                    <p class="edupro-cta-box__desc">
                        <?php esc_html_e( 'Subscribe to receive early access to new courses, seasonal flash offers, and free study resources straight to your inbox.', 'edupro' ); ?>
                    </p>
                </div>
                <form class="edupro-newsletter-form" id="edupro-newsletter-form" method="post" action="#edupro-newsletter-form">
                    <label for="edupro-newsletter-email" class="screen-reader-text">
                        <?php esc_html_e( 'Your email address', 'edupro' ); ?>
                    </label>
                    <div class="edupro-newsletter-pill">
                        <input
                            type="email"
                            id="edupro-newsletter-email"
                            name="newsletter_email"
                            placeholder="<?php esc_attr_e( 'Enter your email address…', 'edupro' ); ?>"
                            required
                        >
                        <button type="submit" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--pill">
                            <?php esc_html_e( 'Subscribe Free', 'edupro' ); ?>
                        </button>
                    </div>
                    <p class="edupro-newsletter-note"><?php esc_html_e( 'No spam, ever. Unsubscribe anytime.', 'edupro' ); ?></p>
                </form>
            </div>
        </div>
    </section>

</main>

<?php
get_footer();
