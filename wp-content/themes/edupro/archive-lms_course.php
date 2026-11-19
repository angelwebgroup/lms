<?php
/**
 * The template for displaying the Course Catalog archive (lms_course).
 *
 * Features:
 *  - Left Filter Sidebar: Categories, Level, Language, Price Range (Free/Paid + Min/Max), Rating
 *  - Sort Bar: Newest, Popular, Price Low-High, Price High-Low, Highest Rated
 *  - URL-based filtering powered by WP_Query
 *  - Active filter chips + Clear All
 *  - Responsive course grid using template-parts/course-card.php with pagination
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$archive_url = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );

// Read and sanitize URL filter parameters.
$filter_search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$filter_category  = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
$filter_level     = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$filter_language  = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';
$filter_price     = isset( $_GET['price'] ) ? sanitize_key( wp_unslash( $_GET['price'] ) ) : '';
$filter_min_price = isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ? (float) $_GET['min_price'] : '';
$filter_max_price = isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ? (float) $_GET['max_price'] : '';
$filter_rating    = isset( $_GET['rating'] ) ? (float) $_GET['rating'] : 0;
$filter_orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'newest';
$paged            = max( 1, get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : ( isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) );

// Build WP_Query arguments.
$query_args = array(
    'post_type'      => 'lms_course',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
);

if ( '' !== $filter_search ) {
    $query_args['s'] = $filter_search;
}

// Taxonomy filters.
$tax_query = array( 'relation' => 'AND' );

if ( '' !== $filter_category ) {
    $tax_query[] = array(
        'taxonomy' => 'course_category',
        'field'    => 'slug',
        'terms'    => $filter_category,
    );
}

if ( '' !== $filter_level ) {
    $tax_query[] = array(
        'taxonomy' => 'course_level',
        'field'    => 'slug',
        'terms'    => $filter_level,
    );
}

if ( '' !== $filter_language ) {
    $tax_query[] = array(
        'taxonomy' => 'course_language',
        'field'    => 'slug',
        'terms'    => $filter_language,
    );
}

if ( count( $tax_query ) > 1 ) {
    $query_args['tax_query'] = $tax_query;
}

// Meta filters (Price & Rating).
$meta_query = array( 'relation' => 'AND' );

if ( 'free' === $filter_price ) {
    $meta_query[] = array(
        'relation' => 'OR',
        array(
            'key'     => '_edupro_is_free',
            'value'   => '1',
            'compare' => '=',
        ),
        array(
            'key'     => '_edupro_regular_price',
            'value'   => 0,
            'compare' => '<=',
            'type'    => 'NUMERIC',
        ),
    );
} elseif ( 'paid' === $filter_price ) {
    $meta_query[] = array(
        'relation' => 'OR',
        array(
            'key'     => '_edupro_is_free',
            'value'   => '1',
            'compare' => '!=',
        ),
        array(
            'key'     => '_edupro_is_free',
            'compare' => 'NOT EXISTS',
        ),
    );
    $meta_query[] = array(
        'key'     => '_edupro_regular_price',
        'value'   => 0,
        'compare' => '>',
        'type'    => 'NUMERIC',
    );
}

if ( '' !== $filter_min_price && $filter_min_price >= 0 ) {
    $meta_query[] = array(
        'key'     => '_edupro_regular_price',
        'value'   => $filter_min_price,
        'compare' => '>=',
        'type'    => 'DECIMAL(10,2)',
    );
}

if ( '' !== $filter_max_price && $filter_max_price > 0 ) {
    $meta_query[] = array(
        'key'     => '_edupro_regular_price',
        'value'   => $filter_max_price,
        'compare' => '<=',
        'type'    => 'DECIMAL(10,2)',
    );
}

// Sorting.
switch ( $filter_orderby ) {
    case 'price_low':
        $query_args['meta_key'] = '_edupro_regular_price';
        $query_args['orderby']  = 'meta_value_num';
        $query_args['order']    = 'ASC';
        break;
    case 'price_high':
        $query_args['meta_key'] = '_edupro_regular_price';
        $query_args['orderby']  = 'meta_value_num';
        $query_args['order']    = 'DESC';
        break;
    case 'popular':
        $query_args['orderby'] = 'comment_count';
        $query_args['order']   = 'DESC';
        break;
    case 'rating':
        $query_args['orderby'] = 'date';
        $query_args['order']   = 'DESC';
        break;
    case 'newest':
    default:
        $query_args['orderby'] = 'date';
        $query_args['order']   = 'DESC';
        break;
}

if ( count( $meta_query ) > 1 ) {
    $query_args['meta_query'] = $meta_query;
}

// If minimum rating filter is active, pre-filter matching course IDs cleanly.
if ( $filter_rating > 0 && function_exists( 'edupro_get_course_rating' ) ) {
    $all_course_ids = get_posts( array(
        'post_type'      => 'lms_course',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );
    $matching_ids = array();
    foreach ( $all_course_ids as $cid ) {
        if ( edupro_get_course_rating( $cid ) >= $filter_rating ) {
            $matching_ids[] = $cid;
        }
    }
    $query_args['post__in'] = ! empty( $matching_ids ) ? $matching_ids : array( 0 );
}

$courses_query = new WP_Query( $query_args );

// Fetch filter sidebar terms.
$all_categories = get_terms( array(
    'taxonomy'   => 'course_category',
    'hide_empty' => false,
    'orderby'    => 'name',
) );
$all_levels = get_terms( array(
    'taxonomy'   => 'course_level',
    'hide_empty' => false,
) );
$all_languages = get_terms( array(
    'taxonomy'   => 'course_language',
    'hide_empty' => false,
) );

$has_active_filters = ( '' !== $filter_search || '' !== $filter_category || '' !== $filter_level || '' !== $filter_language || '' !== $filter_price || '' !== $filter_min_price || '' !== $filter_max_price || $filter_rating > 0 );
?>

<main id="primary" class="site-main edupro-catalog-page">

    <!-- Catalog Header -->
    <section class="edupro-page-hero edupro-catalog-hero">
        <div class="edupro-container container">
            <?php
            if ( function_exists( 'edupro_get_breadcrumb' ) ) {
                echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
            <h1 class="edupro-page-hero__title">
                <?php
                if ( '' !== $filter_search ) {
                    printf(
                        /* translators: %s: search keyword */
                        esc_html__( 'Course Results for "%s"', 'edupro' ),
                        esc_html( $filter_search )
                    );
                } else {
                    esc_html_e( 'Explore All Courses', 'edupro' );
                }
                ?>
            </h1>
            <p class="edupro-page-hero__subtitle">
                <?php esc_html_e( 'Filter by category, difficulty level, language, price, or rating to find your next skill.', 'edupro' ); ?>
            </p>
        </div>
    </section>

    <section class="edupro-section edupro-catalog-body">
        <div class="edupro-container container">
            <div class="edupro-catalog-layout">

                <!-- =========================================================
                     FILTER SIDEBAR
                     ========================================================= -->
                <aside class="edupro-catalog-sidebar" id="edupro-catalog-sidebar" aria-label="<?php esc_attr_e( 'Course Filters', 'edupro' ); ?>">
                    <form id="edupro-course-filter-form" class="edupro-filter-form" method="get" action="<?php echo esc_url( $archive_url ); ?>">
                        <div class="edupro-filter-header">
                            <h3><?php esc_html_e( 'Filter Courses', 'edupro' ); ?></h3>
                            <?php if ( $has_active_filters ) : ?>
                                <a href="<?php echo esc_url( $archive_url ); ?>" class="edupro-filter-reset">
                                    <?php esc_html_e( 'Reset All', 'edupro' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Keyword Search -->
                        <div class="edupro-filter-group">
                            <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Keyword', 'edupro' ); ?></h4>
                            <input
                                type="search"
                                name="s"
                                class="edupro-filter-input"
                                value="<?php echo esc_attr( $filter_search ); ?>"
                                placeholder="<?php esc_attr_e( 'Search courses…', 'edupro' ); ?>"
                            >
                        </div>

                        <!-- Categories -->
                        <?php if ( ! is_wp_error( $all_categories ) && ! empty( $all_categories ) ) : ?>
                            <div class="edupro-filter-group">
                                <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Category', 'edupro' ); ?></h4>
                                <ul class="edupro-filter-list">
                                    <li>
                                        <label class="edupro-filter-radio">
                                            <input type="radio" name="category" value="" <?php checked( $filter_category, '' ); ?>>
                                            <span><?php esc_html_e( 'All Categories', 'edupro' ); ?></span>
                                        </label>
                                    </li>
                                    <?php foreach ( $all_categories as $cat ) : ?>
                                        <li>
                                            <label class="edupro-filter-radio">
                                                <input type="radio" name="category" value="<?php echo esc_attr( $cat->slug ); ?>" <?php checked( $filter_category, $cat->slug ); ?>>
                                                <span><?php echo esc_html( $cat->name ); ?></span>
                                                <span class="edupro-filter-count">(<?php echo absint( $cat->count ); ?>)</span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Level -->
                        <?php if ( ! is_wp_error( $all_levels ) && ! empty( $all_levels ) ) : ?>
                            <div class="edupro-filter-group">
                                <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Difficulty Level', 'edupro' ); ?></h4>
                                <ul class="edupro-filter-list">
                                    <li>
                                        <label class="edupro-filter-radio">
                                            <input type="radio" name="level" value="" <?php checked( $filter_level, '' ); ?>>
                                            <span><?php esc_html_e( 'Any Level', 'edupro' ); ?></span>
                                        </label>
                                    </li>
                                    <?php foreach ( $all_levels as $lvl ) : ?>
                                        <li>
                                            <label class="edupro-filter-radio">
                                                <input type="radio" name="level" value="<?php echo esc_attr( $lvl->slug ); ?>" <?php checked( $filter_level, $lvl->slug ); ?>>
                                                <span><?php echo esc_html( $lvl->name ); ?></span>
                                                <span class="edupro-filter-count">(<?php echo absint( $lvl->count ); ?>)</span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Language -->
                        <?php if ( ! is_wp_error( $all_languages ) && ! empty( $all_languages ) ) : ?>
                            <div class="edupro-filter-group">
                                <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Language', 'edupro' ); ?></h4>
                                <ul class="edupro-filter-list">
                                    <li>
                                        <label class="edupro-filter-radio">
                                            <input type="radio" name="language" value="" <?php checked( $filter_language, '' ); ?>>
                                            <span><?php esc_html_e( 'All Languages', 'edupro' ); ?></span>
                                        </label>
                                    </li>
                                    <?php foreach ( $all_languages as $lang ) : ?>
                                        <li>
                                            <label class="edupro-filter-radio">
                                                <input type="radio" name="language" value="<?php echo esc_attr( $lang->slug ); ?>" <?php checked( $filter_language, $lang->slug ); ?>>
                                                <span><?php echo esc_html( $lang->name ); ?></span>
                                                <span class="edupro-filter-count">(<?php echo absint( $lang->count ); ?>)</span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Price Range -->
                        <div class="edupro-filter-group">
                            <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Price', 'edupro' ); ?></h4>
                            <ul class="edupro-filter-list">
                                <li>
                                    <label class="edupro-filter-radio">
                                        <input type="radio" name="price" value="" <?php checked( $filter_price, '' ); ?>>
                                        <span><?php esc_html_e( 'All Prices', 'edupro' ); ?></span>
                                    </label>
                                </li>
                                <li>
                                    <label class="edupro-filter-radio">
                                        <input type="radio" name="price" value="free" <?php checked( $filter_price, 'free' ); ?>>
                                        <span><?php esc_html_e( 'Free Courses', 'edupro' ); ?></span>
                                    </label>
                                </li>
                                <li>
                                    <label class="edupro-filter-radio">
                                        <input type="radio" name="price" value="paid" <?php checked( $filter_price, 'paid' ); ?>>
                                        <span><?php esc_html_e( 'Paid Courses', 'edupro' ); ?></span>
                                    </label>
                                </li>
                            </ul>
                            <div class="edupro-price-range-inputs">
                                <input
                                    type="number"
                                    name="min_price"
                                    min="0"
                                    step="50"
                                    value="<?php echo '' !== $filter_min_price ? esc_attr( $filter_min_price ) : ''; ?>"
                                    placeholder="<?php esc_attr_e( 'Min ₹', 'edupro' ); ?>"
                                    aria-label="<?php esc_attr_e( 'Minimum price', 'edupro' ); ?>"
                                >
                                <span>–</span>
                                <input
                                    type="number"
                                    name="max_price"
                                    min="0"
                                    step="50"
                                    value="<?php echo '' !== $filter_max_price ? esc_attr( $filter_max_price ) : ''; ?>"
                                    placeholder="<?php esc_attr_e( 'Max ₹', 'edupro' ); ?>"
                                    aria-label="<?php esc_attr_e( 'Maximum price', 'edupro' ); ?>"
                                >
                            </div>
                        </div>

                        <!-- Rating Filter -->
                        <div class="edupro-filter-group">
                            <h4 class="edupro-filter-group__title"><?php esc_html_e( 'Minimum Rating', 'edupro' ); ?></h4>
                            <ul class="edupro-filter-list">
                                <li>
                                    <label class="edupro-filter-radio">
                                        <input type="radio" name="rating" value="" <?php checked( $filter_rating, 0 ); ?>>
                                        <span><?php esc_html_e( 'Any Rating', 'edupro' ); ?></span>
                                    </label>
                                </li>
                                <?php foreach ( array( 4.5, 4.0, 3.5, 3.0 ) as $rate_val ) : ?>
                                    <li>
                                        <label class="edupro-filter-radio">
                                            <input type="radio" name="rating" value="<?php echo esc_attr( $rate_val ); ?>" <?php checked( (string) $filter_rating, (string) $rate_val ); ?>>
                                            <span class="edupro-filter-stars">
                                                <?php
                                                if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                                                    echo edupro_get_star_rating_html( $rate_val ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                }
                                                ?>
                                                <?php
                                                /* translators: %s: rating threshold */
                                                printf( esc_html__( '%s & up', 'edupro' ), esc_html( number_format( $rate_val, 1 ) ) );
                                                ?>
                                            </span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <input type="hidden" name="orderby" id="edupro-filter-orderby-hidden" value="<?php echo esc_attr( $filter_orderby ); ?>">

                        <button type="submit" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                            <?php esc_html_e( 'Apply Filters', 'edupro' ); ?>
                        </button>
                    </form>
                </aside>

                <!-- =========================================================
                     COURSE GRID & SORT BAR
                     ========================================================= -->
                <div class="edupro-catalog-main">
                    <!-- Sort Bar -->
                    <div class="edupro-catalog-toolbar">
                        <div class="edupro-catalog-count">
                            <?php
                            printf(
                                /* translators: %s: total courses found */
                                esc_html( _n( 'Showing %s course', 'Showing %s courses', $courses_query->found_posts, 'edupro' ) ),
                                '<strong>' . esc_html( number_format_i18n( $courses_query->found_posts ) ) . '</strong>'
                            );
                            ?>
                        </div>

                        <div class="edupro-catalog-sort">
                            <label for="edupro-sort-select"><?php esc_html_e( 'Sort by:', 'edupro' ); ?></label>
                            <select id="edupro-sort-select" class="edupro-sort-select" name="orderby" onchange="document.getElementById('edupro-filter-orderby-hidden').value=this.value;document.getElementById('edupro-course-filter-form').submit();">
                                <option value="newest" <?php selected( $filter_orderby, 'newest' ); ?>><?php esc_html_e( 'Newest', 'edupro' ); ?></option>
                                <option value="popular" <?php selected( $filter_orderby, 'popular' ); ?>><?php esc_html_e( 'Most Popular', 'edupro' ); ?></option>
                                <option value="price_low" <?php selected( $filter_orderby, 'price_low' ); ?>><?php esc_html_e( 'Price: Low to High', 'edupro' ); ?></option>
                                <option value="price_high" <?php selected( $filter_orderby, 'price_high' ); ?>><?php esc_html_e( 'Price: High to Low', 'edupro' ); ?></option>
                                <option value="rating" <?php selected( $filter_orderby, 'rating' ); ?>><?php esc_html_e( 'Highest Rated', 'edupro' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Course Grid -->
                    <div id="edupro-catalog-results">
                        <?php if ( $courses_query->have_posts() ) : ?>
                            <div class="course-grid edupro-course-grid edupro-course-grid--3col">
                                <?php
                                while ( $courses_query->have_posts() ) :
                                    $courses_query->the_post();
                                    get_template_part( 'template-parts/course-card', null, array( 'course_id' => get_the_ID() ) );
                                endwhile;
                                wp_reset_postdata();
                                ?>
                            </div>

                            <!-- Pagination -->
                            <?php
                            $pagination_links = paginate_links( array(
                                'total'     => $courses_query->max_num_pages,
                                'current'   => $paged,
                                'mid_size'  => 2,
                                'prev_text' => __( '&larr; Previous', 'edupro' ),
                                'next_text' => __( 'Next &rarr;', 'edupro' ),
                                'add_args'  => array_filter( array(
                                    's'         => $filter_search,
                                    'category'  => $filter_category,
                                    'level'     => $filter_level,
                                    'language'  => $filter_language,
                                    'price'     => $filter_price,
                                    'min_price' => $filter_min_price,
                                    'max_price' => $filter_max_price,
                                    'rating'    => $filter_rating ?: '',
                                    'orderby'   => $filter_orderby,
                                ) ),
                            ) );
                            if ( $pagination_links ) :
                                ?>
                                <nav class="edupro-pagination" aria-label="<?php esc_attr_e( 'Courses pagination', 'edupro' ); ?>">
                                    <?php echo wp_kses_post( $pagination_links ); ?>
                                </nav>
                            <?php endif; ?>

                        <?php else : ?>
                            <div class="edupro-empty-state">
                                <h3><?php esc_html_e( 'No Matching Courses Found', 'edupro' ); ?></h3>
                                <p><?php esc_html_e( 'Try adjusting or clearing your filters to discover more courses.', 'edupro' ); ?></p>
                                <a href="<?php echo esc_url( $archive_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary">
                                    <?php esc_html_e( 'Clear All Filters', 'edupro' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<?php
get_footer();
