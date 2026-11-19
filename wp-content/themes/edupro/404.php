<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * Includes a prominent course search bar and a grid of popular courses.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$courses_url = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );

$popular_courses = new WP_Query( array(
    'post_type'      => 'lms_course',
    'post_status'    => 'publish',
    'posts_per_page' => 4,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>

<main id="primary" class="site-main edupro-404-page">
    <section class="edupro-404-hero">
        <div class="edupro-container container edupro-404-hero__inner">
            <span class="edupro-404-code" aria-hidden="true">404</span>
            <h1 class="edupro-404-title"><?php esc_html_e( 'Oops! That Page Cannot Be Found', 'edupro' ); ?></h1>
            <p class="edupro-404-desc">
                <?php esc_html_e( 'The page or course you are looking for may have moved or no longer exists. Search our catalog or explore popular courses below.', 'edupro' ); ?>
            </p>

            <!-- Course Search Form -->
            <form role="search" method="get" class="edupro-404-search" action="<?php echo esc_url( $courses_url ); ?>">
                <label for="edupro-404-search-input" class="screen-reader-text">
                    <?php esc_html_e( 'Search courses', 'edupro' ); ?>
                </label>
                <input
                    type="search"
                    id="edupro-404-search-input"
                    name="s"
                    placeholder="<?php esc_attr_e( 'Search for courses, topics, or skills…', 'edupro' ); ?>"
                    required
                >
                <input type="hidden" name="post_type" value="lms_course">
                <button type="submit" class="edupro-btn edupro-btn--primary btn-primary">
                    <?php esc_html_e( 'Search Courses', 'edupro' ); ?>
                </button>
            </form>

            <div class="edupro-404-actions">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="edupro-btn edupro-btn--outline btn-outline">
                    <?php esc_html_e( '← Back to Homepage', 'edupro' ); ?>
                </a>
                <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--secondary">
                    <?php esc_html_e( 'Browse All Courses', 'edupro' ); ?>
                </a>
            </div>
        </div>
    </section>

    <?php if ( $popular_courses->have_posts() ) : ?>
        <section class="edupro-section edupro-404-popular">
            <div class="edupro-container container">
                <div class="edupro-section-header">
                    <div>
                        <span class="edupro-section-eyebrow"><?php esc_html_e( 'Keep Learning', 'edupro' ); ?></span>
                        <h2 class="edupro-section-title"><?php esc_html_e( 'Popular Courses You Might Like', 'edupro' ); ?></h2>
                    </div>
                    <a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn--outline btn-outline">
                        <?php esc_html_e( 'View All Courses →', 'edupro' ); ?>
                    </a>
                </div>

                <div class="course-grid edupro-course-grid">
                    <?php
                    while ( $popular_courses->have_posts() ) :
                        $popular_courses->the_post();
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
get_footer();
