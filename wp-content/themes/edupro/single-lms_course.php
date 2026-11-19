<?php
/**
 * The template for displaying a single EduPro Course (lms_course) — Udemy-style layout.
 *
 * Includes:
 *  - Dark hero header with breadcrumb, title, subtitle, badges, rating, students, instructor, last updated, language
 *  - Sticky right sidebar course purchase/enroll box (video preview, pricing, gateway logos, guarantee, includes)
 *  - Interactive Tab Navigation: Overview | Curriculum | Instructor | Reviews | FAQ
 *  - What You'll Learn checkmark grid, Requirements accordion, Expandable full description
 *  - Curriculum accordion (supports both _edupro_curriculum JSON sections & linked lms_lesson posts)
 *  - Instructor profile card with stats
 *  - Reviews section with rating breakdown bars, individual review cards & submission form
 *  - FAQ accordion & Related Courses grid
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
    the_post();

    $course_id   = get_the_ID();
    $course_url  = get_permalink( $course_id );
    $author_id   = (int) get_the_author_meta( 'ID' );
    $author_name = get_the_author_meta( 'display_name', $author_id );
    $author_bio  = get_the_author_meta( 'description', $author_id );

    // Pricing meta (multi-currency aware via inc/currency.php).
    $sale_end_date    = get_post_meta( $course_id, '_edupro_sale_end_date', true );
    $enrollment_limit = (int) get_post_meta( $course_id, '_edupro_enrollment_limit', true );

    if ( function_exists( 'edupro_get_course_price_data' ) ) {
        $price_data      = edupro_get_course_price_data( $course_id );
        $active_currency = ! empty( $price_data['currency'] ) ? $price_data['currency'] : 'INR';
        $currency_symbol = ! empty( $price_data['symbol'] ) ? $price_data['symbol'] : '₹';
        $is_free         = ! empty( $price_data['is_free'] );
        $regular_price   = isset( $price_data['regular'] ) ? (float) $price_data['regular'] : 0.0;
        $sale_price      = isset( $price_data['sale'] ) ? (float) $price_data['sale'] : 0.0;
        $display_price   = isset( $price_data['effective'] ) ? (float) $price_data['effective'] : 0.0;
        $discount_pct    = isset( $price_data['discount_pct'] ) ? (int) $price_data['discount_pct'] : 0;
        $is_sale_active  = ( ! $is_free && $sale_price > 0 && $regular_price > 0 && $sale_price < $regular_price );
    } else {
        $active_currency = 'INR';
        $currency_symbol = function_exists( 'edupro_get_currency_symbol' ) ? edupro_get_currency_symbol() : '₹';
        $is_free         = ( '1' === (string) get_post_meta( $course_id, '_edupro_is_free', true ) );
        $regular_price   = (float) get_post_meta( $course_id, '_edupro_regular_price', true );
        $sale_price      = (float) get_post_meta( $course_id, '_edupro_sale_price', true );

        $is_sale_active = false;
        if ( ! $is_free && $sale_price > 0 && $regular_price > 0 && $sale_price < $regular_price ) {
            if ( empty( $sale_end_date ) || strtotime( $sale_end_date . ' 23:59:59' ) >= current_time( 'timestamp' ) ) {
                $is_sale_active = true;
            }
        }
        $display_price = $is_free ? 0 : ( $is_sale_active ? $sale_price : $regular_price );
        if ( ! $is_free && $display_price <= 0 ) {
            $is_free = true;
        }
        $discount_pct = ( $is_sale_active && $regular_price > 0 )
            ? (int) round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 )
            : 0;
    }

    // Details meta.
    $duration_mins    = (int) get_post_meta( $course_id, '_edupro_duration', true );
    $duration_label   = ( $duration_mins > 0 && function_exists( 'edupro_get_duration_label' ) ) ? edupro_get_duration_label( $duration_mins ) : __( 'Self-Paced', 'edupro' );
    $meta_level       = get_post_meta( $course_id, '_edupro_course_level', true );
    $meta_language    = get_post_meta( $course_id, '_edupro_language', true );
    $requirements_raw = get_post_meta( $course_id, '_edupro_requirements', true );
    $learn_raw        = get_post_meta( $course_id, '_edupro_what_you_learn', true );
    $intro_video      = get_post_meta( $course_id, '_edupro_course_intro_video', true );
    $has_certificate  = ( '1' === (string) get_post_meta( $course_id, '_edupro_certificate', true ) );
    $curriculum_raw   = get_post_meta( $course_id, '_edupro_curriculum', true );

    // Level & Language labels (taxonomy fallback to meta).
    $level_terms = get_the_terms( $course_id, 'course_level' );
    if ( $level_terms && ! is_wp_error( $level_terms ) ) {
        $level_label = $level_terms[0]->name;
    } else {
        $level_key   = strtolower( (string) $meta_level );
        $level_map   = array(
            'beginner'     => __( 'Beginner', 'edupro' ),
            'intermediate' => __( 'Intermediate', 'edupro' ),
            'advanced'     => __( 'Advanced', 'edupro' ),
            'all'          => __( 'All Levels', 'edupro' ),
            'all levels'   => __( 'All Levels', 'edupro' ),
        );
        $level_label = isset( $level_map[ $level_key ] ) ? $level_map[ $level_key ] : ( $meta_level ?: __( 'All Levels', 'edupro' ) );
    }

    $lang_terms     = get_the_terms( $course_id, 'course_language' );
    $language_label = ( $lang_terms && ! is_wp_error( $lang_terms ) ) ? $lang_terms[0]->name : ( $meta_language ?: __( 'English', 'edupro' ) );

    // Parse What You'll Learn items.
    $learn_items = array();
    if ( ! empty( $learn_raw ) ) {
        $decoded_learn = json_decode( $learn_raw, true );
        if ( is_array( $decoded_learn ) ) {
            $learn_items = array_filter( array_map( 'trim', $decoded_learn ) );
        } else {
            $learn_items = array_filter( array_map( 'trim', explode( "\n", $learn_raw ) ) );
        }
    }

    // Parse Requirements lines.
    $requirements_list = array();
    if ( ! empty( $requirements_raw ) ) {
        $requirements_list = array_filter( array_map( 'trim', explode( "\n", $requirements_raw ) ) );
    }

    // Linked lms_lesson posts for this course.
    $linked_lessons = get_posts( array(
        'post_type'      => 'lms_lesson',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_edupro_lesson_order',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => '_edupro_course_id',
                'value'   => $course_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );

    // Parse JSON curriculum sections.
    $curriculum_sections = array();
    if ( ! empty( $curriculum_raw ) ) {
        $decoded_curr = json_decode( $curriculum_raw, true );
        if ( is_array( $decoded_curr ) ) {
            foreach ( $decoded_curr as $sec ) {
                if ( ! empty( $sec['title'] ) || ! empty( $sec['lessons'] ) ) {
                    $curriculum_sections[] = $sec;
                }
            }
        }
    }

    // Compute total lessons count across linked posts & JSON curriculum.
    $total_lessons_count = count( $linked_lessons );
    if ( 0 === $total_lessons_count && ! empty( $curriculum_sections ) ) {
        foreach ( $curriculum_sections as $sec ) {
            $total_lessons_count += ! empty( $sec['lessons'] ) && is_array( $sec['lessons'] ) ? count( $sec['lessons'] ) : 0;
        }
    }

    // Enrollment & Access.
    $is_logged_in   = is_user_logged_in();
    $is_enrolled    = function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id );
    $has_access     = function_exists( 'edupro_check_student_access' ) ? edupro_check_student_access( $course_id ) : $is_enrolled;
    $enrolled_count = function_exists( 'edupro_get_enrolled_count' ) ? edupro_get_enrolled_count( $course_id ) : 0;
    $course_progress = ( $is_enrolled && function_exists( 'edupro_get_course_progress' ) ) ? (int) edupro_get_course_progress( $course_id, get_current_user_id() ) : 0;

    // First lesson permalink for enrolled "Continue Learning" button.
    $first_lesson_url = ! empty( $linked_lessons ) ? get_permalink( $linked_lessons[0]->ID ) : $course_url . '#curriculum';

    // Checkout URL & Login URL.
    $checkout_page = get_page_by_path( 'checkout' );
    $checkout_url  = $checkout_page
        ? add_query_arg( 'course_id', $course_id, get_permalink( $checkout_page->ID ) )
        : add_query_arg( 'course_id', $course_id, home_url( '/checkout/' ) );
    $login_page_id = (int) get_option( 'edupro_login_page_id' );
    $login_url     = $login_page_id ? add_query_arg( 'redirect_to', $course_url, get_permalink( $login_page_id ) ) : wp_login_url( $course_url );

    // Reviews & Rating breakdown.
    $reviews = get_post_meta( $course_id, 'course_reviews', true );
    if ( ! is_array( $reviews ) ) {
        $reviews = array();
    }
    $reviews_count = count( $reviews );
    $avg_rating    = function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $course_id ) : 0.0;
    $display_rating = $avg_rating > 0 ? $avg_rating : 4.8;

    $rating_distribution = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
    foreach ( $reviews as $rev ) {
        $r_int = isset( $rev['rating'] ) ? max( 1, min( 5, (int) round( (float) $rev['rating'] ) ) ) : 5;
        $rating_distribution[ $r_int ]++;
    }

    // Instructor stats.
    $instructor_courses_count = count_user_posts( $author_id, 'lms_course', true );

    // Hero background thumbnail URL.
    $hero_bg_url = has_post_thumbnail( $course_id ) ? get_the_post_thumbnail_url( $course_id, 'hero-thumb' ) : '';

    // Related courses (same category).
    $course_cats = get_the_terms( $course_id, 'course_category' );
    $related_args = array(
        'post_type'      => 'lms_course',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'post__not_in'   => array( $course_id ),
    );
    if ( $course_cats && ! is_wp_error( $course_cats ) ) {
        $related_args['tax_query'] = array(
            array(
                'taxonomy' => 'course_category',
                'field'    => 'term_id',
                'terms'    => wp_list_pluck( $course_cats, 'term_id' ),
            ),
        );
    }
    $related_query = new WP_Query( $related_args );
    ?>

    <main id="primary" class="site-main edupro-single-course">

        <!-- =================================================================
             1. COURSE HERO HEADER (Dark Overlay, Udemy-Style)
             ================================================================= -->
        <section class="edupro-course-hero course-hero" <?php echo $hero_bg_url ? 'style="background-image:url(' . esc_url( $hero_bg_url ) . ');"' : ''; ?>>
            <div class="edupro-course-hero__overlay" aria-hidden="true"></div>
            <div class="edupro-container container edupro-course-hero__inner">
                <div class="edupro-course-hero__content">

                    <!-- Breadcrumb -->
                    <?php
                    if ( function_exists( 'edupro_get_breadcrumb' ) ) {
                        echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>

                    <!-- Badges -->
                    <div class="edupro-course-hero__badges">
                        <span class="edupro-badge badge-bestseller"><?php esc_html_e( 'Bestseller', 'edupro' ); ?></span>
                        <span class="edupro-badge badge-level"><?php echo esc_html( $level_label ); ?></span>
                        <?php if ( $has_certificate ) : ?>
                            <span class="edupro-badge badge-cert"><?php esc_html_e( 'Certificate Included', 'edupro' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Title & Subtitle -->
                    <h1 class="edupro-course-hero__title"><?php the_title(); ?></h1>

                    <?php if ( has_excerpt() ) : ?>
                        <p class="edupro-course-hero__subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>

                    <!-- Rating & Students Row -->
                    <div class="edupro-course-hero__rating-row">
                        <span class="edupro-course-hero__rating-val"><?php echo esc_html( number_format( $display_rating, 1 ) ); ?></span>
                        <?php
                        if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                            echo edupro_get_star_rating_html( $display_rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                        <a href="#reviews" class="edupro-course-hero__reviews-link">
                            (<?php
                            printf(
                                /* translators: %d: review count */
                                esc_html( _n( '%d rating', '%d ratings', $reviews_count, 'edupro' ) ),
                                absint( $reviews_count )
                            );
                            ?>)
                        </a>
                        <span class="edupro-course-hero__students">
                            <?php
                            printf(
                                /* translators: %s: enrolled students count */
                                esc_html__( '%s students enrolled', 'edupro' ),
                                esc_html( number_format_i18n( $enrolled_count ) )
                            );
                            ?>
                        </span>
                    </div>

                    <!-- Meta Info Row: Instructor, Last Updated, Language -->
                    <div class="edupro-course-hero__meta">
                        <span class="edupro-course-hero__meta-item">
                            <?php esc_html_e( 'Created by', 'edupro' ); ?>
                            <a href="#instructor"><strong><?php echo esc_html( $author_name ); ?></strong></a>
                        </span>
                        <span class="edupro-course-hero__meta-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <?php
                            printf(
                                /* translators: %s: last modified date */
                                esc_html__( 'Last updated %s', 'edupro' ),
                                esc_html( get_the_modified_date( 'M Y' ) )
                            );
                            ?>
                        </span>
                        <span class="edupro-course-hero__meta-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                            </svg>
                            <?php echo esc_html( $language_label ); ?>
                        </span>
                    </div>

                </div>
            </div>
        </section>

        <!-- =================================================================
             2. MAIN TWO-COLUMN COURSE BODY (65% Content + 35% Sticky Sidebar)
             ================================================================= -->
        <section class="edupro-course-body">
            <div class="edupro-container container">
                <div class="edupro-course-layout">

                    <!-- LEFT COLUMN: TABS & CONTENT -->
                    <div class="edupro-course-main">

                        <!-- Tab Navigation -->
                        <nav class="edupro-course-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Course Sections', 'edupro' ); ?>">
                            <button type="button" class="edupro-course-tab is-active" role="tab" aria-selected="true" data-tab="overview">
                                <?php esc_html_e( 'Overview', 'edupro' ); ?>
                            </button>
                            <button type="button" class="edupro-course-tab" role="tab" aria-selected="false" data-tab="curriculum">
                                <?php esc_html_e( 'Curriculum', 'edupro' ); ?>
                            </button>
                            <button type="button" class="edupro-course-tab" role="tab" aria-selected="false" data-tab="instructor">
                                <?php esc_html_e( 'Instructor', 'edupro' ); ?>
                            </button>
                            <button type="button" class="edupro-course-tab" role="tab" aria-selected="false" data-tab="reviews">
                                <?php esc_html_e( 'Reviews', 'edupro' ); ?> (<?php echo absint( $reviews_count ); ?>)
                            </button>
                            <button type="button" class="edupro-course-tab" role="tab" aria-selected="false" data-tab="faq">
                                <?php esc_html_e( 'FAQ', 'edupro' ); ?>
                            </button>
                        </nav>

                        <!-- TAB 1: OVERVIEW -->
                        <div id="overview" class="edupro-course-panel is-active" data-panel="overview" role="tabpanel">

                            <!-- What You'll Learn Grid -->
                            <?php if ( ! empty( $learn_items ) ) : ?>
                                <div class="edupro-course-box edupro-learn-box">
                                    <h2 class="edupro-course-box__title"><?php esc_html_e( "What You'll Learn", 'edupro' ); ?></h2>
                                    <ul class="edupro-learn-grid">
                                        <?php foreach ( $learn_items as $item ) : ?>
                                            <li class="edupro-learn-grid__item">
                                                <svg class="edupro-check-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                                <span><?php echo esc_html( $item ); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Requirements Accordion -->
                            <?php if ( ! empty( $requirements_list ) ) : ?>
                                <div class="edupro-course-box edupro-requirements-box">
                                    <div class="edupro-accordion-item is-open">
                                        <button type="button" class="edupro-accordion-trigger" aria-expanded="true">
                                            <span><?php esc_html_e( 'Requirements & Prerequisites', 'edupro' ); ?></span>
                                            <svg class="edupro-accordion-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </button>
                                        <div class="edupro-accordion-content">
                                            <ul class="edupro-requirements-list">
                                                <?php foreach ( $requirements_list as $req ) : ?>
                                                    <li><?php echo esc_html( $req ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Full Course Description with Expand / Collapse -->
                            <div class="edupro-course-box edupro-description-box">
                                <h2 class="edupro-course-box__title"><?php esc_html_e( 'Course Description', 'edupro' ); ?></h2>
                                <div class="edupro-description-collapsible is-collapsed" id="edupro-course-description">
                                    <div class="entry-content edupro-prose">
                                        <?php the_content(); ?>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="edupro-expand-toggle"
                                    data-target="#edupro-course-description"
                                    data-more-text="<?php esc_attr_e( 'Show More ↓', 'edupro' ); ?>"
                                    data-less-text="<?php esc_attr_e( 'Show Less ↑', 'edupro' ); ?>"
                                    aria-expanded="false"
                                >
                                    <?php esc_html_e( 'Show More ↓', 'edupro' ); ?>
                                </button>
                            </div>
                        </div>

                        <!-- TAB 2: CURRICULUM ACCORDION -->
                        <div id="curriculum" class="edupro-course-panel" data-panel="curriculum" role="tabpanel">
                            <div class="edupro-course-box edupro-curriculum-box">
                                <div class="edupro-curriculum-header">
                                    <h2 class="edupro-course-box__title"><?php esc_html_e( 'Course Curriculum', 'edupro' ); ?></h2>
                                    <div class="edupro-curriculum-summary">
                                        <span>
                                            <?php
                                            printf(
                                                /* translators: %d: total lessons */
                                                esc_html( _n( '%d Lesson', '%d Lessons', $total_lessons_count, 'edupro' ) ),
                                                absint( $total_lessons_count )
                                            );
                                            ?>
                                        </span>
                                        <span aria-hidden="true">&bull;</span>
                                        <span><?php echo esc_html( $duration_label ); ?></span>
                                    </div>
                                </div>

                                <?php if ( ! empty( $curriculum_sections ) ) : ?>
                                    <div class="edupro-curriculum-accordion">
                                        <?php foreach ( $curriculum_sections as $sec_idx => $section ) : ?>
                                            <?php
                                            $sec_title   = ! empty( $section['title'] ) ? $section['title'] : sprintf( __( 'Section %d', 'edupro' ), $sec_idx + 1 );
                                            $sec_lessons = ! empty( $section['lessons'] ) && is_array( $section['lessons'] ) ? $section['lessons'] : array();
                                            $is_first    = ( 0 === $sec_idx );
                                            ?>
                                            <div class="edupro-curriculum-section edupro-accordion-item <?php echo $is_first ? 'is-open' : ''; ?>">
                                                <button type="button" class="edupro-curriculum-section__header edupro-accordion-trigger" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>">
                                                    <span class="edupro-curriculum-section__title">
                                                        <svg class="edupro-accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                            <polyline points="6 9 12 15 18 9"></polyline>
                                                        </svg>
                                                        <strong><?php echo esc_html( $sec_title ); ?></strong>
                                                    </span>
                                                    <span class="edupro-curriculum-section__meta">
                                                        <?php
                                                        printf(
                                                            /* translators: %d: number of lessons in section */
                                                            esc_html( _n( '%d lecture', '%d lectures', count( $sec_lessons ), 'edupro' ) ),
                                                            count( $sec_lessons )
                                                        );
                                                        ?>
                                                    </span>
                                                </button>
                                                <div class="edupro-curriculum-section__body edupro-accordion-content">
                                                    <?php if ( ! empty( $section['description'] ) ) : ?>
                                                        <div class="edupro-curriculum-section__desc">
                                                            <?php echo wp_kses_post( wpautop( $section['description'] ) ); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <ul class="edupro-lesson-list">
                                                        <?php foreach ( $sec_lessons as $les_idx => $lesson ) : ?>
                                                            <?php
                                                            $l_type       = isset( $lesson['type'] ) ? $lesson['type'] : 'video';
                                                            $l_title      = ! empty( $lesson['title'] ) ? $lesson['title'] : sprintf( __( 'Lesson %d', 'edupro' ), $les_idx + 1 );
                                                            $l_content    = ! empty( $lesson['content'] ) ? $lesson['content'] : '';
                                                            $l_dur        = ! empty( $lesson['duration'] ) ? $lesson['duration'] : '';
                                                            $l_is_prev    = ! empty( $lesson['is_preview'] ) || ( 0 === $sec_idx && 0 === $les_idx );
                                                            $icon_map     = array(
                                                                'video' => '▶',
                                                                'text'  => '📄',
                                                                'pdf'   => '📎',
                                                                'quiz'  => '❓',
                                                            );
                                                            $l_icon = isset( $icon_map[ $l_type ] ) ? $icon_map[ $l_type ] : '▶';
                                                            ?>
                                                            <li class="edupro-lesson-row">
                                                                <span class="edupro-lesson-row__left">
                                                                    <span class="edupro-lesson-row__icon" aria-hidden="true"><?php echo esc_html( $l_icon ); ?></span>
                                                                    <span class="edupro-lesson-row__title"><?php echo esc_html( $l_title ); ?></span>
                                                                </span>
                                                                <span class="edupro-lesson-row__right">
                                                                    <?php if ( $l_is_prev && $l_content && filter_var( $l_content, FILTER_VALIDATE_URL ) ) : ?>
                                                                        <a href="<?php echo esc_url( $l_content ); ?>" target="_blank" rel="noopener noreferrer" class="edupro-preview-link">
                                                                            <?php esc_html_e( 'Preview', 'edupro' ); ?>
                                                                        </a>
                                                                    <?php elseif ( ! $has_access && ! $l_is_prev ) : ?>
                                                                        <span class="edupro-lesson-lock" title="<?php esc_attr_e( 'Enroll to unlock', 'edupro' ); ?>">🔒</span>
                                                                    <?php endif; ?>
                                                                    <?php if ( $l_dur ) : ?>
                                                                        <span class="edupro-lesson-row__dur">
                                                                            <?php
                                                                            echo esc_html(
                                                                                is_numeric( $l_dur ) && function_exists( 'edupro_get_duration_label' )
                                                                                    ? edupro_get_duration_label( (float) $l_dur )
                                                                                    : $l_dur
                                                                            );
                                                                            ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <span class="edupro-lesson-row__type"><?php echo esc_html( ucfirst( $l_type ) ); ?></span>
                                                                </span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ( ! empty( $linked_lessons ) ) : ?>
                                    <div class="edupro-curriculum-accordion edupro-linked-lessons">
                                        <div class="edupro-curriculum-section edupro-accordion-item is-open">
                                            <button type="button" class="edupro-curriculum-section__header edupro-accordion-trigger" aria-expanded="true">
                                                <span class="edupro-curriculum-section__title">
                                                    <svg class="edupro-accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                        <polyline points="6 9 12 15 18 9"></polyline>
                                                    </svg>
                                                    <strong><?php esc_html_e( 'Course Lessons', 'edupro' ); ?></strong>
                                                </span>
                                                <span class="edupro-curriculum-section__meta">
                                                    <?php
                                                    printf(
                                                        esc_html( _n( '%d lesson', '%d lessons', count( $linked_lessons ), 'edupro' ) ),
                                                        count( $linked_lessons )
                                                    );
                                                    ?>
                                                </span>
                                            </button>
                                            <div class="edupro-curriculum-section__body edupro-accordion-content">
                                                <ul class="edupro-lesson-list">
                                                    <?php foreach ( $linked_lessons as $l_post ) : ?>
                                                        <?php
                                                        $l_type       = get_post_meta( $l_post->ID, '_edupro_lesson_type', true ) ?: 'video';
                                                        $l_duration   = (float) get_post_meta( $l_post->ID, '_edupro_video_duration', true );
                                                        $l_is_preview = ( '1' === (string) get_post_meta( $l_post->ID, '_edupro_is_preview', true ) );
                                                        $l_completed  = ( $is_logged_in && function_exists( 'edupro_is_lesson_complete' ) && edupro_is_lesson_complete( $l_post->ID, get_current_user_id() ) );
                                                        $can_open     = $has_access || $l_is_preview;
                                                        ?>
                                                        <li class="edupro-lesson-row <?php echo $l_completed ? 'is-completed' : ''; ?>">
                                                            <span class="edupro-lesson-row__left">
                                                                <span class="edupro-lesson-row__icon" aria-hidden="true">
                                                                    <?php echo $l_completed ? '✅' : ( 'pdf' === $l_type ? '📎' : ( 'text' === $l_type ? '📄' : '▶' ) ); ?>
                                                                </span>
                                                                <?php if ( $can_open ) : ?>
                                                                    <a href="<?php echo esc_url( get_permalink( $l_post->ID ) ); ?>" class="edupro-lesson-row__link">
                                                                        <?php echo esc_html( $l_post->post_title ); ?>
                                                                    </a>
                                                                <?php else : ?>
                                                                    <span class="edupro-lesson-row__title"><?php echo esc_html( $l_post->post_title ); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="edupro-lesson-row__right">
                                                                <?php if ( $l_is_preview ) : ?>
                                                                    <a href="<?php echo esc_url( get_permalink( $l_post->ID ) ); ?>" class="edupro-preview-link">
                                                                        <?php esc_html_e( 'Free Preview', 'edupro' ); ?>
                                                                    </a>
                                                                <?php elseif ( ! $has_access ) : ?>
                                                                    <span class="edupro-lesson-lock" title="<?php esc_attr_e( 'Enroll to unlock', 'edupro' ); ?>">🔒</span>
                                                                <?php endif; ?>
                                                                <?php if ( $l_duration > 0 ) : ?>
                                                                    <span class="edupro-lesson-row__dur">
                                                                        <?php echo esc_html( function_exists( 'edupro_get_duration_label' ) ? edupro_get_duration_label( $l_duration ) : $l_duration . ' min' ); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ( empty( $curriculum_sections ) && empty( $linked_lessons ) ) : ?>
                                    <p class="edupro-empty-inline"><?php esc_html_e( 'Curriculum modules will be updated shortly.', 'edupro' ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TAB 3: INSTRUCTOR CARD -->
                        <div id="instructor" class="edupro-course-panel" data-panel="instructor" role="tabpanel">
                            <div class="edupro-course-box edupro-instructor-box">
                                <h2 class="edupro-course-box__title"><?php esc_html_e( 'Meet Your Instructor', 'edupro' ); ?></h2>
                                <div class="edupro-instructor-card">
                                    <div class="edupro-instructor-card__avatar">
                                        <?php echo get_avatar( $author_id, 120, '', $author_name ); ?>
                                    </div>
                                    <div class="edupro-instructor-card__body">
                                        <h3 class="edupro-instructor-card__name"><?php echo esc_html( $author_name ); ?></h3>
                                        <p class="edupro-instructor-card__role"><?php esc_html_e( 'Lead Course Instructor & Subject Matter Expert', 'edupro' ); ?></p>
                                        <div class="edupro-instructor-card__stats">
                                            <span>⭐ <?php echo esc_html( number_format( $display_rating, 1 ) ); ?> <?php esc_html_e( 'Instructor Rating', 'edupro' ); ?></span>
                                            <span>👥 <?php echo esc_html( number_format_i18n( $enrolled_count ) ); ?> <?php esc_html_e( 'Students', 'edupro' ); ?></span>
                                            <span>🎓 <?php echo absint( max( 1, $instructor_courses_count ) ); ?> <?php esc_html_e( 'Courses', 'edupro' ); ?></span>
                                        </div>
                                        <p class="edupro-instructor-card__bio">
                                            <?php
                                            echo esc_html(
                                                $author_bio ?: __( 'An experienced educator and industry practitioner dedicated to helping learners build practical, career-ready skills through hands-on instruction.', 'edupro' )
                                            );
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: REVIEWS SECTION -->
                        <div id="reviews" class="edupro-course-panel" data-panel="reviews" role="tabpanel">
                            <div class="edupro-course-box edupro-reviews-box">
                                <h2 class="edupro-course-box__title"><?php esc_html_e( 'Student Feedback & Reviews', 'edupro' ); ?></h2>

                                <!-- Average + Distribution Bars -->
                                <div class="edupro-reviews-summary">
                                    <div class="edupro-reviews-summary__score">
                                        <div class="edupro-reviews-big-num"><?php echo esc_html( number_format( $display_rating, 1 ) ); ?></div>
                                        <?php
                                        if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                                            echo edupro_get_star_rating_html( $display_rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        }
                                        ?>
                                        <span class="edupro-reviews-summary__label">
                                            <?php
                                            printf(
                                                esc_html( _n( '%d Review', '%d Reviews', $reviews_count, 'edupro' ) ),
                                                absint( $reviews_count )
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="edupro-reviews-bars">
                                        <?php for ( $star = 5; $star >= 1; $star-- ) : ?>
                                            <?php
                                            $star_count = $rating_distribution[ $star ];
                                            $star_pct   = $reviews_count > 0 ? round( ( $star_count / $reviews_count ) * 100 ) : ( 5 === $star ? 82 : ( 4 === $star ? 14 : ( 3 === $star ? 4 : 0 ) ) );
                                            ?>
                                            <div class="edupro-rating-bar-row">
                                                <span class="edupro-rating-bar-row__label"><?php echo absint( $star ); ?> ★</span>
                                                <div class="edupro-rating-bar-row__track">
                                                    <div class="edupro-rating-bar-row__fill" style="width:<?php echo absint( $star_pct ); ?>%;"></div>
                                                </div>
                                                <span class="edupro-rating-bar-row__pct"><?php echo absint( $star_pct ); ?>%</span>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Individual Review Cards -->
                                <div class="edupro-course-reviews-list">
                                    <?php if ( ! empty( $reviews ) ) : ?>
                                        <?php foreach ( array_reverse( $reviews ) as $review ) : ?>
                                            <?php
                                            $r_name   = ! empty( $review['author'] )
                                                ? $review['author']
                                                : ( ! empty( $review['name'] ) ? $review['name'] : __( 'Verified Student', 'edupro' ) );
                                            $r_rating = isset( $review['rating'] ) ? (float) $review['rating'] : 5.0;
                                            $r_date   = ! empty( $review['date'] ) ? $review['date'] : '';
                                            $r_text   = ! empty( $review['comment'] ) ? $review['comment'] : ( ! empty( $review['text'] ) ? $review['text'] : '' );
                                            ?>
                                            <article class="edupro-review-card">
                                                <div class="edupro-review-card__top">
                                                    <div class="edupro-review-card__reviewer">
                                                        <span class="edupro-review-card__initials" aria-hidden="true">
                                                            <?php echo esc_html( mb_substr( $r_name, 0, 1 ) ); ?>
                                                        </span>
                                                        <div>
                                                            <strong><?php echo esc_html( $r_name ); ?></strong>
                                                            <?php if ( $r_date ) : ?>
                                                                <span class="edupro-review-card__date"><?php echo esc_html( $r_date ); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                                                        echo edupro_get_star_rating_html( $r_rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    }
                                                    ?>
                                                </div>
                                                <?php if ( $r_text ) : ?>
                                                    <p class="edupro-review-card__text"><?php echo esc_html( $r_text ); ?></p>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <p class="edupro-empty-inline"><?php esc_html_e( 'No student reviews yet. Be the first to review this course!', 'edupro' ); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Submit Review Form -->
                                <?php if ( $is_logged_in ) : ?>
                                    <form id="edupro-review-form" class="edupro-review-form" method="post">
                                        <h3><?php esc_html_e( 'Write a Review', 'edupro' ); ?></h3>
                                        <input type="hidden" name="course_id" value="<?php echo absint( $course_id ); ?>">
                                        <div class="edupro-form-group">
                                            <label for="edupro-review-rating"><?php esc_html_e( 'Your Rating', 'edupro' ); ?></label>
                                            <select id="edupro-review-rating" name="rating" required>
                                                <option value="5">★★★★★ — <?php esc_html_e( 'Excellent (5/5)', 'edupro' ); ?></option>
                                                <option value="4">★★★★☆ — <?php esc_html_e( 'Very Good (4/5)', 'edupro' ); ?></option>
                                                <option value="3">★★★☆☆ — <?php esc_html_e( 'Good (3/5)', 'edupro' ); ?></option>
                                                <option value="2">★★☆☆☆ — <?php esc_html_e( 'Fair (2/5)', 'edupro' ); ?></option>
                                                <option value="1">★☆☆☆☆ — <?php esc_html_e( 'Poor (1/5)', 'edupro' ); ?></option>
                                            </select>
                                        </div>
                                        <div class="edupro-form-group">
                                            <label for="edupro-review-comment"><?php esc_html_e( 'Your Review', 'edupro' ); ?></label>
                                            <textarea id="edupro-review-comment" name="comment" rows="4" placeholder="<?php esc_attr_e( 'Share your learning experience with other students…', 'edupro' ); ?>" required></textarea>
                                        </div>
                                        <button type="submit" class="edupro-btn edupro-btn--primary btn-primary">
                                            <?php esc_html_e( 'Submit Review', 'edupro' ); ?>
                                        </button>
                                        <div class="edupro-form-message" aria-live="polite"></div>
                                    </form>
                                <?php else : ?>
                                    <p class="edupro-review-login-prompt">
                                        <a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in', 'edupro' ); ?></a>
                                        <?php esc_html_e( 'to leave a review for this course.', 'edupro' ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TAB 5: FAQ ACCORDION -->
                        <div id="faq" class="edupro-course-panel" data-panel="faq" role="tabpanel">
                            <div class="edupro-course-box edupro-faq-box">
                                <h2 class="edupro-course-box__title"><?php esc_html_e( 'Frequently Asked Questions', 'edupro' ); ?></h2>
                                <?php
                                $faqs = array(
                                    array(
                                        'q' => __( 'How long do I have access to this course?', 'edupro' ),
                                        'a' => __( 'Once enrolled, you receive unlimited lifetime access to all video lessons, downloadable resources, and future curriculum updates across desktop and mobile devices.', 'edupro' ),
                                    ),
                                    array(
                                        'q' => __( 'Will I receive a certificate upon completion?', 'edupro' ),
                                        'a' => __( 'Yes! After completing 100% of the lessons, you can immediately download your verified Certificate of Completion directly from your Student Dashboard.', 'edupro' ),
                                    ),
                                    array(
                                        'q' => __( 'What payment methods are accepted?', 'edupro' ),
                                        'a' => __( 'For India (INR ₹) we accept Cashfree & Razorpay (UPI, NetBanking, RuPay, Debit/Credit Cards). For the UK & International (GBP £ / USD $) we accept Stripe (Cards, Apple Pay, Google Pay, Klarna), PayPal, and UK/India Bank Transfer — all secured with 256-bit SSL encryption.', 'edupro' ),
                                    ),
                                    array(
                                        'q' => __( 'What if I am not satisfied with the course?', 'edupro' ),
                                        'a' => __( 'Every paid course is backed by our 30-day 100% money-back guarantee. Reach out to our support team within 30 days for a full refund.', 'edupro' ),
                                    ),
                                );
                                ?>
                                <div class="edupro-faq-list">
                                    <?php foreach ( $faqs as $f_idx => $faq ) : ?>
                                        <div class="edupro-accordion-item <?php echo 0 === $f_idx ? 'is-open' : ''; ?>">
                                            <button type="button" class="edupro-accordion-trigger" aria-expanded="<?php echo 0 === $f_idx ? 'true' : 'false'; ?>">
                                                <span><?php echo esc_html( $faq['q'] ); ?></span>
                                                <svg class="edupro-accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                    <polyline points="6 9 12 15 18 9"></polyline>
                                                </svg>
                                            </button>
                                            <div class="edupro-accordion-content">
                                                <p><?php echo esc_html( $faq['a'] ); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div><!-- .edupro-course-main -->

                    <!-- RIGHT COLUMN: STICKY COURSE ENROLLMENT BOX -->
                    <aside class="edupro-course-sidebar">
                        <div class="edupro-course-card-sticky course-sidebar-card">

                            <!-- Intro Video or Course Thumbnail -->
                            <div class="edupro-course-preview">
                                <?php if ( $intro_video && function_exists( 'edupro_build_video_embed' ) ) : ?>
                                    <?php echo edupro_build_video_embed( $intro_video ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php elseif ( has_post_thumbnail( $course_id ) ) : ?>
                                    <?php
                                    echo wp_get_attachment_image(
                                        get_post_thumbnail_id( $course_id ),
                                        'course-thumb',
                                        false,
                                        array( 'class' => 'edupro-course-preview__img' )
                                    );
                                    ?>
                                <?php endif; ?>
                            </div>

                            <div class="edupro-course-card-sticky__body">
                                <!-- Price Row -->
                                <div class="edupro-sidebar-pricing" data-currency="<?php echo esc_attr( $active_currency ); ?>">
                                    <?php if ( $is_free ) : ?>
                                        <span class="edupro-sidebar-price edupro-sidebar-price--free"><?php esc_html_e( 'Free', 'edupro' ); ?></span>
                                    <?php elseif ( $is_sale_active ) : ?>
                                        <span class="edupro-sidebar-price">
                                            <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $sale_price, $active_currency ) : $currency_symbol . number_format( $sale_price, 2 ) ); ?>
                                        </span>
                                        <del class="edupro-sidebar-price-old">
                                            <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $regular_price, $active_currency ) : $currency_symbol . number_format( $regular_price, 2 ) ); ?>
                                        </del>
                                        <span class="edupro-badge badge-discount">
                                            <?php printf( esc_html__( '%d%% OFF', 'edupro' ), absint( $discount_pct ) ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="edupro-sidebar-price">
                                            <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $display_price, $active_currency ) : $currency_symbol . number_format( $display_price, 2 ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $is_sale_active && ! empty( $sale_end_date ) ) : ?>
                                    <p class="edupro-sale-timer">
                                        ⏰ <?php
                                        printf(
                                            /* translators: %s: sale end date */
                                            esc_html__( 'Limited-time offer ends %s', 'edupro' ),
                                            esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sale_end_date ) ) )
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Primary CTA Button -->
                                <?php if ( $is_enrolled ) : ?>
                                    <div class="edupro-enrolled-progress">
                                        <div class="edupro-enrolled-progress__top">
                                            <span><?php esc_html_e( 'Your Progress', 'edupro' ); ?></span>
                                            <strong><?php echo absint( $course_progress ); ?>%</strong>
                                        </div>
                                        <div class="edupro-progress-bar">
                                            <div class="edupro-progress-bar__fill" style="width:<?php echo absint( $course_progress ); ?>%;"></div>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( $first_lesson_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full edupro-btn--lg">
                                        <?php echo $course_progress > 0 ? esc_html__( 'Continue Learning →', 'edupro' ) : esc_html__( 'Start Course Now →', 'edupro' ); ?>
                                    </a>
                                <?php elseif ( $is_free ) : ?>
                                    <?php if ( $is_logged_in ) : ?>
                                        <button
                                            type="button"
                                            class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full edupro-btn--lg edupro-enroll-btn"
                                            data-course-id="<?php echo absint( $course_id ); ?>"
                                            data-free="1"
                                        >
                                            <?php esc_html_e( 'Enroll For Free', 'edupro' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $login_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full edupro-btn--lg">
                                            <?php esc_html_e( 'Log In to Enroll Free', 'edupro' ); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $checkout_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full edupro-btn--lg">
                                        <?php esc_html_e( 'Enroll Now', 'edupro' ); ?>
                                    </a>
                                <?php endif; ?>

                                <!-- Money-Back Guarantee Badge -->
                                <div class="edupro-guarantee-badge">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                        <polyline points="9 12 11 14 15 10"></polyline>
                                    </svg>
                                    <span><?php esc_html_e( '30-Day 100% Money-Back Guarantee', 'edupro' ); ?></span>
                                </div>

                                <!-- Course Includes List -->
                                <div class="edupro-course-includes">
                                    <h4><?php esc_html_e( 'This course includes:', 'edupro' ); ?></h4>
                                    <ul>
                                        <li>
                                            <span aria-hidden="true">🎬</span>
                                            <span><?php echo esc_html( $duration_label ); ?> <?php esc_html_e( 'of on-demand content', 'edupro' ); ?></span>
                                        </li>
                                        <li>
                                            <span aria-hidden="true">📚</span>
                                            <span>
                                                <?php
                                                printf(
                                                    esc_html( _n( '%d structured lesson', '%d structured lessons', max( 1, $total_lessons_count ), 'edupro' ) ),
                                                    absint( max( 1, $total_lessons_count ) )
                                                );
                                                ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span aria-hidden="true">♾️</span>
                                            <span><?php esc_html_e( 'Full lifetime access', 'edupro' ); ?></span>
                                        </li>
                                        <li>
                                            <span aria-hidden="true">📱</span>
                                            <span><?php esc_html_e( 'Access on mobile, tablet & desktop', 'edupro' ); ?></span>
                                        </li>
                                        <?php if ( $has_certificate ) : ?>
                                            <li>
                                                <span aria-hidden="true">🏆</span>
                                                <span><?php esc_html_e( 'Certificate of Completion', 'edupro' ); ?></span>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ( $enrollment_limit > 0 ) : ?>
                                            <li>
                                                <span aria-hidden="true">🎟️</span>
                                                <span>
                                                    <?php
                                                    printf(
                                                        /* translators: %d: max seats */
                                                        esc_html__( 'Limited to %d seats', 'edupro' ),
                                                        absint( $enrollment_limit )
                                                    );
                                                    ?>
                                                </span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>

                                <!-- Payment Gateway Logos (India & UK Supported Methods) -->
                                <?php
                                $gateway_badge_labels = array(
                                    'cashfree'      => __( 'Cashfree / UPI', 'edupro' ),
                                    'razorpay'      => __( 'Razorpay', 'edupro' ),
                                    'stripe'        => __( 'Stripe (Apple Pay / Klarna)', 'edupro' ),
                                    'paypal'        => __( 'PayPal', 'edupro' ),
                                    'bank_transfer' => __( 'Bank Transfer', 'edupro' ),
                                );
                                $displayed_gateways = array();
                                if ( function_exists( 'edupro_get_enabled_gateways' ) ) {
                                    $enabled_gws = edupro_get_enabled_gateways( $active_currency );
                                    if ( is_array( $enabled_gws ) && ! empty( $enabled_gws ) ) {
                                        foreach ( $enabled_gws as $gw_key => $gw_info ) {
                                            $gw_id = is_array( $gw_info ) && ! empty( $gw_info['id'] ) ? $gw_info['id'] : (string) $gw_key;
                                            if ( isset( $gateway_badge_labels[ $gw_id ] ) ) {
                                                $displayed_gateways[ $gw_id ] = $gateway_badge_labels[ $gw_id ];
                                            } elseif ( is_array( $gw_info ) && ! empty( $gw_info['label'] ) ) {
                                                $displayed_gateways[ $gw_id ] = $gw_info['label'];
                                            }
                                        }
                                    }
                                }
                                if ( empty( $displayed_gateways ) ) {
                                    $displayed_gateways = $gateway_badge_labels;
                                }
                                ?>
                                <div class="edupro-sidebar-gateways">
                                    <span class="edupro-sidebar-gateways__label">
                                        <?php
                                        printf(
                                            /* translators: %s: active currency code */
                                            esc_html__( 'Secure Checkout (%s) via:', 'edupro' ),
                                            esc_html( $active_currency )
                                        );
                                        ?>
                                    </span>
                                    <div class="edupro-sidebar-gateways__icons">
                                        <?php foreach ( $displayed_gateways as $gw_slug => $gw_label ) : ?>
                                            <span class="edupro-gw-pill edupro-gw-pill--<?php echo esc_attr( $gw_slug ); ?>">
                                                <?php echo esc_html( $gw_label ); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </aside>

                </div><!-- .edupro-course-layout -->
            </div>
        </section>

        <!-- =================================================================
             3. RELATED COURSES GRID
             ================================================================= -->
        <?php if ( $related_query->have_posts() ) : ?>
            <section class="edupro-section edupro-related-courses edupro-section--tinted">
                <div class="edupro-container container">
                    <div class="edupro-section-header">
                        <div>
                            <span class="edupro-section-eyebrow"><?php esc_html_e( 'Recommended For You', 'edupro' ); ?></span>
                            <h2 class="edupro-section-title"><?php esc_html_e( 'Students Also Enrolled In', 'edupro' ); ?></h2>
                        </div>
                        <a href="<?php echo esc_url( get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' ) ); ?>" class="edupro-btn edupro-btn--outline btn-outline">
                            <?php esc_html_e( 'Browse All Courses →', 'edupro' ); ?>
                        </a>
                    </div>
                    <div class="course-grid edupro-course-grid">
                        <?php
                        while ( $related_query->have_posts() ) :
                            $related_query->the_post();
                            get_template_part( 'template-parts/course-card', null, array( 'course_id' => get_the_ID() ) );
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

<?php
endwhile;

get_footer();
