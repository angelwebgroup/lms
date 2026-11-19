<?php
/**
 * The Front Page (Homepage) template for EduPro LMS.
 *
 * Sections:
 *  1. Hero Section (big headline, course search, optional video background, mini stats counters)
 *  2. Trusted Brands Logos Strip
 *  3. Featured Categories Grid (course_category terms with icon, name, course count)
 *  4. Featured Courses Section (4-col grid of lms_course posts via WP_Query + template-parts/course-card.php)
 *  5. Animated Stats Counter Section (10,000+ students, 500+ courses, 50+ instructors, 4.8 stars)
 *  6. "Why Choose Us" Section (3 feature columns with icons)
 *  7. Testimonials / Google Reviews Section (pulls from inc/google-reviews.php)
 *  8. Newsletter CTA Section
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$courses_url     = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
$register_id     = (int) get_option( 'edupro_register_page_id' );
$register_url    = $register_id ? get_permalink( $register_id ) : home_url( '/student-register/' );

// Customizer Hero settings with rich defaults.
$hero_heading    = get_theme_mod( 'edupro_hero_heading', __( 'Master In-Demand Skills & Accelerate Your Career', 'edupro' ) );
$hero_subheading = get_theme_mod( 'edupro_hero_subheading', __( 'Learn from industry experts with accredited online courses, hands-on projects, and lifetime access — anytime, anywhere.', 'edupro' ) );
$hero_video_url  = get_theme_mod( 'edupro_hero_video_url', '' );

// Dynamic counts with polished minimum showcase floors if site is newly installed.
$published_courses_count     = (int) ( wp_count_posts( 'lms_course' )->publish ?? 0 );
$published_instructors_count = (int) ( wp_count_posts( 'lms_instructor' )->publish ?? 0 );
$stat_students               = max( 10000, count_users()['total_users'] ?? 0 );
$stat_courses                = max( 500, $published_courses_count );
$stat_instructors            = max( 50, $published_instructors_count );

// Featured Categories (course_category taxonomy).
$featured_categories = get_terms( array(
    'taxonomy'   => 'course_category',
    'hide_empty' => false,
    'number'     => 8,
    'orderby'    => 'count',
    'order'      => 'DESC',
) );
if ( is_wp_error( $featured_categories ) || ! is_array( $featured_categories ) ) {
    $featured_categories = array();
}

$category_icon_map = array(
    'business'             => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'technology'           => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
    'design'               => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l7.586 7.586"></path><circle cx="11" cy="11" r="2"></circle></svg>',
    'marketing'            => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
    'health-fitness'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
    'personal-development' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
);
$default_cat_svg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>';

// Featured Courses query (WP_Query).
$featured_courses_query = new WP_Query( array(
    'post_type'      => 'lms_course',
    'post_status'    => 'publish',
    'posts_per_page' => 8,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>

<main id="primary" class="site-main edupro-front-page">

    <!-- =====================================================================
         1. HERO SECTION
         ===================================================================== -->
    <section class="edupro-hero hero-section">
        <?php if ( ! empty( $hero_video_url ) ) : ?>
            <div class="edupro-hero__video-wrap" aria-hidden="true">
                <video class="edupro-hero__video" autoplay muted loop playsinline>
                    <source src="<?php echo esc_url( $hero_video_url ); ?>" type="video/mp4">
                </video>
            </div>
        <?php endif; ?>
        <div class="edupro-hero__overlay" aria-hidden="true"></div>

        <div class="edupro-container container edupro-hero__inner">
            <div class="edupro-hero__content">
                <span class="edupro-hero__eyebrow">
                    <span class="edupro-hero__pulse" aria-hidden="true"></span>
                    <?php esc_html_e( 'Accredited Online Learning Platform', 'edupro' ); ?>
                </span>

                <h1 class="edupro-hero__title"><?php echo esc_html( $hero_heading ); ?></h1>
                <p class="edupro-hero__subtitle"><?php echo esc_html( $hero_subheading ); ?></p>

                <!-- Hero Course Search Bar -->
                <form role="search" method="get" class="edupro-hero__search" action="<?php echo esc_url( $courses_url ); ?>">
                    <label for="edupro-hero-search-input" class="screen-reader-text">
                        <?php esc_html_e( 'Search for online courses', 'edupro' ); ?>
                    </label>
                    <div class="edupro-hero__search-box">
                        <svg class="edupro-hero__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input
                            type="search"
                            id="edupro-hero-search-input"
                            name="s"
                            placeholder="<?php esc_attr_e( 'Search 500+ courses (e.g., Python, Digital Marketing, MBA)…', 'edupro' ); ?>"
                        >
                        <input type="hidden" name="post_type" value="lms_course">
                        <button type="submit" class="edupro-btn edupro-btn--primary btn-primary">
                            <?php esc_html_e( 'Search', 'edupro' ); ?>
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

                <!-- Hero Mini Stats Counters -->
                <div class="edupro-hero__stats">
                    <div class="edupro-hero__stat">
                        <strong class="edupro-counter" data-target="<?php echo absint( $stat_students ); ?>" data-suffix="+">10,000+</strong>
                        <span><?php esc_html_e( 'Active Students', 'edupro' ); ?></span>
                    </div>
                    <div class="edupro-hero__stat">
                        <strong class="edupro-counter" data-target="<?php echo absint( $stat_courses ); ?>" data-suffix="+">500+</strong>
                        <span><?php esc_html_e( 'Online Courses', 'edupro' ); ?></span>
                    </div>
                    <div class="edupro-hero__stat">
                        <strong class="edupro-counter" data-target="<?php echo absint( $stat_instructors ); ?>" data-suffix="+">50+</strong>
                        <span><?php esc_html_e( 'Expert Instructors', 'edupro' ); ?></span>
                    </div>
                    <div class="edupro-hero__stat">
                        <strong>4.8 ★</strong>
                        <span><?php esc_html_e( 'Average Rating', 'edupro' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Hero Right Feature Card -->
            <div class="edupro-hero__visual">
                <div class="edupro-hero__card">
                    <div class="edupro-hero__card-badge"><?php esc_html_e( '🔥 70% OFF Limited Offer', 'edupro' ); ?></div>
                    <h3><?php esc_html_e( 'Start Your Certification Journey', 'edupro' ); ?></h3>
                    <ul class="edupro-hero__checklist">
                        <li>✓ <?php esc_html_e( 'Instant access to HD video lessons & quizzes', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( 'Verified Certificate of Completion', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( 'Learn on mobile, tablet & desktop', 'edupro' ); ?></li>
                        <li>✓ <?php esc_html_e( '30-Day 100% Money-Back Guarantee', 'edupro' ); ?></li>
                    </ul>
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
         2. TRUSTED BRANDS LOGOS STRIP
         ===================================================================== -->
    <section class="edupro-brands" aria-label="<?php esc_attr_e( 'Trusted by Leading Organizations', 'edupro' ); ?>">
        <div class="edupro-container container">
            <p class="edupro-brands__label"><?php esc_html_e( 'Trusted by 1,200+ companies & educational partners worldwide', 'edupro' ); ?></p>
            <div class="edupro-brands__grid">
                <div class="edupro-brand-logo" aria-label="TechNova">
                    <svg width="130" height="32" viewBox="0 0 130 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="6" width="20" height="20" rx="4" fill="currentColor" opacity="0.2"/>
                        <path d="M7 11H17M12 11V21" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        <text x="30" y="21" fill="currentColor" font-family="Poppins,sans-serif" font-weight="700" font-size="14">TechNova</text>
                    </svg>
                </div>
                <div class="edupro-brand-logo" aria-label="SkillBridge">
                    <svg width="135" height="32" viewBox="0 0 135 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="16" r="9" stroke="currentColor" stroke-width="2.2"/>
                        <circle cx="12" cy="16" r="3" fill="currentColor"/>
                        <text x="28" y="21" fill="currentColor" font-family="Poppins,sans-serif" font-weight="700" font-size="14">SkillBridge</text>
                    </svg>
                </div>
                <div class="edupro-brand-logo" aria-label="CloudPeak">
                    <svg width="130" height="32" viewBox="0 0 130 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="12,6 22,24 2,24" stroke="currentColor" stroke-width="2" fill="none"/>
                        <text x="28" y="21" fill="currentColor" font-family="Poppins,sans-serif" font-weight="700" font-size="14">CloudPeak</text>
                    </svg>
                </div>
                <div class="edupro-brand-logo" aria-label="FinEdge">
                    <svg width="115" height="32" viewBox="0 0 115 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 22L10 13L15 17L21 9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <text x="28" y="21" fill="currentColor" font-family="Poppins,sans-serif" font-weight="700" font-size="14">FinEdge</text>
                    </svg>
                </div>
                <div class="edupro-brand-logo" aria-label="GlobalCPD">
                    <svg width="135" height="32" viewBox="0 0 135 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5L21 10V17C21 21.5 17 25 12 27C7 25 3 21.5 3 17V10L12 5Z" stroke="currentColor" stroke-width="2"/>
                        <text x="28" y="21" fill="currentColor" font-family="Poppins,sans-serif" font-weight="700" font-size="14">GlobalCPD</text>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         3. FEATURED CATEGORIES SECTION
         ===================================================================== -->
    <section class="edupro-section edupro-categories-section" id="categories">
        <div class="edupro-container container">
            <div class="edupro-section-header">
                <div>
                    <span class="edupro-section-eyebrow"><?php esc_html_e( 'Browse By Topic', 'edupro' ); ?></span>
                    <h2 class="edupro-section-title"><?php esc_html_e( 'Explore Popular Course Categories', 'edupro' ); ?></h2>
                </div>
                <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline">
                    <?php esc_html_e( 'All Categories →', 'edupro' ); ?>
                </a>
            </div>

            <?php if ( ! empty( $featured_categories ) ) : ?>
                <div class="edupro-categories-grid">
                    <?php foreach ( $featured_categories as $cat ) : ?>
                        <?php
                        $cat_icon_svg = isset( $category_icon_map[ $cat->slug ] ) ? $category_icon_map[ $cat->slug ] : $default_cat_svg;
                        ?>
                        <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="edupro-category-card animate-on-scroll">
                            <span class="edupro-category-card__icon" aria-hidden="true">
                                <?php echo $cat_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <h3 class="edupro-category-card__name"><?php echo esc_html( $cat->name ); ?></h3>
                            <span class="edupro-category-card__count">
                                <?php
                                printf(
                                    /* translators: %d: number of courses in category */
                                    esc_html( _n( '%d Course', '%d Courses', $cat->count, 'edupro' ) ),
                                    absint( $cat->count )
                                );
                                ?>
                            </span>
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
                <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary">
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
                <div class="edupro-empty-state">
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
         5. ANIMATED STATS COUNTER SECTION
         ===================================================================== -->
    <section class="edupro-stats-section" aria-label="<?php esc_attr_e( 'EduPro Platform Statistics', 'edupro' ); ?>">
        <div class="edupro-container container">
            <div class="edupro-stats-grid">
                <div class="edupro-stat-box animate-on-scroll">
                    <div class="edupro-stat-box__icon" aria-hidden="true">🎓</div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_students ); ?>" data-suffix="+">10,000+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Enrolled Students', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box animate-on-scroll">
                    <div class="edupro-stat-box__icon" aria-hidden="true">📚</div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_courses ); ?>" data-suffix="+">500+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Expert-Led Courses', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box animate-on-scroll">
                    <div class="edupro-stat-box__icon" aria-hidden="true">👨‍🏫</div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="<?php echo absint( $stat_instructors ); ?>" data-suffix="+">50+</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Certified Instructors', 'edupro' ); ?></div>
                </div>
                <div class="edupro-stat-box animate-on-scroll">
                    <div class="edupro-stat-box__icon" aria-hidden="true">⭐</div>
                    <div class="edupro-stat-box__number edupro-counter" data-target="4.8" data-decimals="1" data-suffix=" ★">4.8 ★</div>
                    <div class="edupro-stat-box__label"><?php esc_html_e( 'Student Satisfaction Rating', 'edupro' ); ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         6. "WHY CHOOSE US" SECTION (3 Feature Columns with Icons)
         ===================================================================== -->
    <section class="edupro-section edupro-why-section">
        <div class="edupro-container container">
            <div class="edupro-section-header edupro-section-header--center">
                <span class="edupro-section-eyebrow"><?php esc_html_e( 'The EduPro Advantage', 'edupro' ); ?></span>
                <h2 class="edupro-section-title"><?php esc_html_e( 'Why Learners Worldwide Choose EduPro', 'edupro' ); ?></h2>
                <p class="edupro-section-subtitle">
                    <?php esc_html_e( 'Everything you need to gain real-world skills, earn recognized credentials, and stand out to employers.', 'edupro' ); ?>
                </p>
            </div>

            <div class="edupro-features-grid">
                <div class="edupro-feature-card animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="7"></circle>
                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                        </svg>
                    </div>
                    <h3 class="edupro-feature-card__title"><?php esc_html_e( 'Accredited Certificates', 'edupro' ); ?></h3>
                    <p class="edupro-feature-card__desc">
                        <?php esc_html_e( 'Earn verifiable digital and printable certificates upon completion to showcase on LinkedIn, resumes, and job applications.', 'edupro' ); ?>
                    </p>
                </div>

                <div class="edupro-feature-card animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                    </div>
                    <h3 class="edupro-feature-card__title"><?php esc_html_e( 'Self-Paced HD Video Lessons', 'edupro' ); ?></h3>
                    <p class="edupro-feature-card__desc">
                        <?php esc_html_e( 'Study on your own schedule with lifetime access to structured video tutorials, downloadable PDFs, and interactive quizzes.', 'edupro' ); ?>
                    </p>
                </div>

                <div class="edupro-feature-card animate-on-scroll">
                    <div class="edupro-feature-card__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
         7. TESTIMONIALS / GOOGLE REVIEWS SECTION
         ===================================================================== -->
    <section class="edupro-section edupro-reviews-section edupro-section--tinted" id="reviews">
        <div class="edupro-container container">
            <div class="edupro-section-header edupro-section-header--center">
                <span class="edupro-section-eyebrow"><?php esc_html_e( 'Verified Student Feedback', 'edupro' ); ?></span>
                <h2 class="edupro-section-title"><?php esc_html_e( 'What Our Students Say on Google', 'edupro' ); ?></h2>
                <p class="edupro-section-subtitle">
                    <?php esc_html_e( 'Join thousands of learners who transformed their careers with EduPro.', 'edupro' ); ?>
                </p>
            </div>

            <?php
            if ( function_exists( 'edupro_render_google_reviews' ) ) {
                echo edupro_render_google_reviews( 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif ( function_exists( 'edupro_google_reviews_widget' ) ) {
                echo edupro_google_reviews_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                // Graceful built-in fallback if inc/google-reviews.php is not yet configured.
                $fallback_reviews = array(
                    array(
                        'name'   => 'Priya Sharma',
                        'rating' => 5,
                        'date'   => __( '2 weeks ago', 'edupro' ),
                        'text'   => __( 'The course structure is super clear and practical. I landed my first data analyst interview within a month of finishing my certification!', 'edupro' ),
                    ),
                    array(
                        'name'   => 'Rohan Mehta',
                        'rating' => 5,
                        'date'   => __( '1 month ago', 'edupro' ),
                        'text'   => __( 'Best online LMS experience I have tried. Smooth video player, downloadable resources, and responsive instructors.', 'edupro' ),
                    ),
                    array(
                        'name'   => 'Ananya Nair',
                        'rating' => 4.8,
                        'date'   => __( '1 month ago', 'edupro' ),
                        'text'   => __( 'Affordable pricing with Razorpay UPI support made enrolling effortless. The certificate looks great on my LinkedIn profile.', 'edupro' ),
                    ),
                );
                ?>
                <div class="edupro-google-reviews-grid">
                    <?php foreach ( $fallback_reviews as $rev ) : ?>
                        <div class="edupro-review-card animate-on-scroll">
                            <div class="edupro-review-card__top">
                                <div class="edupro-review-card__reviewer">
                                    <span class="edupro-review-card__initials" aria-hidden="true">
                                        <?php echo esc_html( mb_substr( $rev['name'], 0, 1 ) ); ?>
                                    </span>
                                    <div>
                                        <strong><?php echo esc_html( $rev['name'] ); ?></strong>
                                        <span class="edupro-review-card__date"><?php echo esc_html( $rev['date'] ); ?></span>
                                    </div>
                                </div>
                                <span class="edupro-google-g" title="Google Review" aria-label="Google">G</span>
                            </div>
                            <div class="edupro-review-card__stars">
                                <?php
                                if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                                    echo edupro_get_star_rating_html( $rev['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                ?>
                            </div>
                            <p class="edupro-review-card__text">&ldquo;<?php echo esc_html( $rev['text'] ); ?>&rdquo;</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php } ?>
        </div>
    </section>

    <!-- =====================================================================
         8. NEWSLETTER CTA SECTION
         ===================================================================== -->
    <section class="edupro-cta-section">
        <div class="edupro-container container">
            <div class="edupro-cta-box">
                <div class="edupro-cta-box__content">
                    <span class="edupro-cta-box__badge"><?php esc_html_e( 'Weekly Learning Digest', 'edupro' ); ?></span>
                    <h2 class="edupro-cta-box__title"><?php esc_html_e( 'Get Exclusive Course Discounts & Free Career Guides', 'edupro' ); ?></h2>
                    <p class="edupro-cta-box__desc">
                        <?php esc_html_e( 'Subscribe to receive early access to new courses, Black Friday flash offers, and free study resources straight to your inbox.', 'edupro' ); ?>
                    </p>
                </div>
                <form class="edupro-newsletter-form" id="edupro-newsletter-form" method="post" action="#edupro-newsletter-form">
                    <label for="edupro-newsletter-email" class="screen-reader-text">
                        <?php esc_html_e( 'Your email address', 'edupro' ); ?>
                    </label>
                    <input
                        type="email"
                        id="edupro-newsletter-email"
                        name="newsletter_email"
                        placeholder="<?php esc_attr_e( 'Enter your email address…', 'edupro' ); ?>"
                        required
                    >
                    <button type="submit" class="edupro-btn edupro-btn--primary btn-primary">
                        <?php esc_html_e( 'Subscribe Free', 'edupro' ); ?>
                    </button>
                    <p class="edupro-newsletter-note"><?php esc_html_e( 'No spam, ever. Unsubscribe anytime.', 'edupro' ); ?></p>
                </form>
            </div>
        </div>
    </section>

</main>

<?php
get_footer();
