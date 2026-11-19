<?php
/**
 * Template part for displaying a reusable EduPro Course Card.
 *
 * Accepts optional $args['course_id'] when called via get_template_part(),
 * otherwise uses the current global post ID inside The Loop.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$course_id = ! empty( $args['course_id'] ) ? absint( $args['course_id'] ) : get_the_ID();
if ( ! $course_id ) {
    return;
}

$course_url   = get_permalink( $course_id );
$course_title = get_the_title( $course_id );

// Pricing & Sale logic (multi-currency aware via inc/currency.php).
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
    $sale_end_date   = get_post_meta( $course_id, '_edupro_sale_end_date', true );

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

    $discount_pct = 0;
    if ( $is_sale_active && $regular_price > 0 ) {
        $discount_pct = (int) round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
    }
}

// Taxonomy: Category & Level.
$categories   = get_the_terms( $course_id, 'course_category' );
$primary_cat  = ( $categories && ! is_wp_error( $categories ) ) ? reset( $categories ) : null;

$level_terms  = get_the_terms( $course_id, 'course_level' );
$level_label  = '';
if ( $level_terms && ! is_wp_error( $level_terms ) ) {
    $level_label = $level_terms[0]->name;
} else {
    $meta_level = get_post_meta( $course_id, '_edupro_course_level', true );
    if ( $meta_level ) {
        $level_key   = strtolower( (string) $meta_level );
        $level_map   = array(
            'beginner'     => __( 'Beginner', 'edupro' ),
            'intermediate' => __( 'Intermediate', 'edupro' ),
            'advanced'     => __( 'Advanced', 'edupro' ),
            'all'          => __( 'All Levels', 'edupro' ),
            'all levels'   => __( 'All Levels', 'edupro' ),
        );
        $level_label = isset( $level_map[ $level_key ] ) ? $level_map[ $level_key ] : $meta_level;
    }
}

// Course stats.
$rating         = function_exists( 'edupro_get_course_rating' ) ? edupro_get_course_rating( $course_id ) : 0.0;
$reviews_meta   = get_post_meta( $course_id, 'course_reviews', true );
$reviews_count  = is_array( $reviews_meta ) ? count( $reviews_meta ) : 0;
$enrolled_count = function_exists( 'edupro_get_enrolled_count' ) ? edupro_get_enrolled_count( $course_id ) : 0;
$lessons_count  = function_exists( 'edupro_get_course_lessons_count' ) ? edupro_get_course_lessons_count( $course_id ) : 0;
$duration_mins  = (int) get_post_meta( $course_id, '_edupro_duration', true );
$duration_label = ( $duration_mins > 0 && function_exists( 'edupro_get_duration_label' ) ) ? edupro_get_duration_label( $duration_mins ) : '';

// Instructor info.
$author_id   = (int) get_post_field( 'post_author', $course_id );
$author_name = get_the_author_meta( 'display_name', $author_id );

// Enrollment & Cart state for current user.
$is_enrolled = function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id );
$is_in_cart  = ( ! $is_enrolled && ! $is_free && function_exists( 'edupro_is_in_cart' ) && edupro_is_in_cart( $course_id ) );

// Badges.
$is_bestseller = ( '1' === (string) get_post_meta( $course_id, '_edupro_is_bestseller', true ) ) || $enrolled_count >= 50 || ( $rating >= 4.7 && $reviews_count >= 5 );
$is_new        = ( ( current_time( 'timestamp' ) - (int) get_post_time( 'U', false, $course_id ) ) <= 30 * DAY_IN_SECONDS );

// Checkout URL for paid courses.
$checkout_page = get_page_by_path( 'checkout' );
$checkout_url  = $checkout_page
    ? add_query_arg( 'course_id', $course_id, get_permalink( $checkout_page->ID ) )
    : add_query_arg( 'course_id', $course_id, home_url( '/checkout/' ) );
?>

<article id="course-<?php echo absint( $course_id ); ?>" <?php post_class( 'course-card edupro-course-card animate-on-scroll', $course_id ); ?>>
    <!-- Thumbnail + Overlay Badges + Hover Quick-Enroll -->
    <div class="course-card__media">
        <a href="<?php echo esc_url( $course_url ); ?>" class="course-card__thumb-link" aria-label="<?php echo esc_attr( $course_title ); ?>">
            <?php if ( has_post_thumbnail( $course_id ) ) : ?>
                <?php
                echo wp_get_attachment_image(
                    get_post_thumbnail_id( $course_id ),
                    'course-card',
                    false,
                    array(
                        'class'   => 'course-card__img',
                        'alt'     => esc_attr( $course_title ),
                        'loading' => 'lazy',
                    )
                );
                ?>
            <?php else : ?>
                <div class="course-card__placeholder" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                </div>
            <?php endif; ?>
        </a>

        <!-- Top-Left Badges -->
        <div class="course-card__badges">
            <?php if ( $is_bestseller ) : ?>
                <span class="edupro-badge badge-bestseller"><?php esc_html_e( 'Bestseller', 'edupro' ); ?></span>
            <?php elseif ( $is_new ) : ?>
                <span class="edupro-badge badge-new"><?php esc_html_e( 'New', 'edupro' ); ?></span>
            <?php endif; ?>

            <?php if ( $is_free ) : ?>
                <span class="edupro-badge badge-free"><?php esc_html_e( 'Free', 'edupro' ); ?></span>
            <?php elseif ( $discount_pct > 0 ) : ?>
                <span class="edupro-badge badge-discount">
                    <?php
                    /* translators: %d: percentage discount */
                    printf( esc_html__( '%d%% OFF', 'edupro' ), absint( $discount_pct ) );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ( $level_label ) : ?>
            <span class="course-card__level"><?php echo esc_html( $level_label ); ?></span>
        <?php endif; ?>

        <!-- Hover Quick-View / Enroll Overlay -->
        <div class="course-card__hover-overlay">
            <?php if ( $is_enrolled ) : ?>
                <a href="<?php echo esc_url( $course_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary course-card__quick-btn">
                    <?php esc_html_e( 'Continue Learning', 'edupro' ); ?>
                </a>
            <?php elseif ( $is_free ) : ?>
                <button
                    type="button"
                    class="edupro-btn edupro-btn--primary btn-primary course-card__quick-btn edupro-enroll-btn"
                    data-course-id="<?php echo absint( $course_id ); ?>"
                    data-free="1"
                >
                    <?php esc_html_e( 'Enroll Free', 'edupro' ); ?>
                </button>
            <?php else : ?>
                <button
                    type="button"
                    class="edupro-btn edupro-btn--primary btn-primary course-card__quick-btn edupro-add-to-cart-btn<?php echo $is_in_cart ? ' is-in-cart' : ''; ?>"
                    data-course-id="<?php echo absint( $course_id ); ?>"
                    data-in-cart-label="<?php esc_attr_e( 'In Cart ✓', 'edupro' ); ?>"
                    data-default-label="<?php esc_attr_e( 'Add to Cart', 'edupro' ); ?>"
                >
                    <?php echo $is_in_cart ? esc_html__( 'In Cart ✓', 'edupro' ) : esc_html__( 'Add to Cart', 'edupro' ); ?>
                </button>
            <?php endif; ?>
            <a href="<?php echo esc_url( $course_url ); ?>" class="edupro-btn edupro-btn--ghost course-card__preview-btn">
                <?php esc_html_e( 'View Details', 'edupro' ); ?>
            </a>
        </div>
    </div>

    <!-- Card Body -->
    <div class="course-card__body">
        <!-- Category -->
        <div class="course-card__top-meta">
            <?php if ( $primary_cat ) : ?>
                <a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>" class="course-card__category">
                    <?php echo esc_html( $primary_cat->name ); ?>
                </a>
            <?php endif; ?>
            <?php if ( $duration_label ) : ?>
                <span class="course-card__duration">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <?php echo esc_html( $duration_label ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h3 class="course-card__title">
            <a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( $course_title ); ?></a>
        </h3>

        <!-- Instructor -->
        <div class="course-card__instructor">
            <?php echo get_avatar( $author_id, 22, '', $author_name, array( 'class' => 'course-card__instructor-avatar' ) ); ?>
            <span><?php echo esc_html( $author_name ?: __( 'EduPro Instructor', 'edupro' ) ); ?></span>
        </div>

        <!-- Rating & Students Row -->
        <div class="course-card__stats">
            <div class="course-card__rating">
                <span class="course-card__rating-num"><?php echo esc_html( number_format( $rating > 0 ? $rating : 4.8, 1 ) ); ?></span>
                <?php
                if ( function_exists( 'edupro_get_star_rating_html' ) ) {
                    echo edupro_get_star_rating_html( $rating > 0 ? $rating : 4.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
                <span class="course-card__rating-count">(<?php echo absint( $reviews_count ); ?>)</span>
            </div>
            <div class="course-card__students" title="<?php esc_attr_e( 'Enrolled Students', 'edupro' ); ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span><?php echo esc_html( number_format_i18n( $enrolled_count ) ); ?></span>
            </div>
        </div>

        <!-- Footer: Price & Action -->
        <div class="course-card__footer">
            <div class="course-card__price" data-currency="<?php echo esc_attr( $active_currency ); ?>">
                <?php if ( $is_free ) : ?>
                    <span class="course-card__price-current course-card__price--free"><?php esc_html_e( 'Free', 'edupro' ); ?></span>
                <?php elseif ( $is_sale_active ) : ?>
                    <span class="course-card__price-current">
                        <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $sale_price, $active_currency ) : $currency_symbol . number_format( $sale_price, 2 ) ); ?>
                    </span>
                    <del class="course-card__price-original">
                        <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $regular_price, $active_currency ) : $currency_symbol . number_format( $regular_price, 2 ) ); ?>
                    </del>
                <?php else : ?>
                    <span class="course-card__price-current">
                        <?php echo esc_html( function_exists( 'edupro_format_price' ) ? edupro_format_price( $display_price, $active_currency ) : $currency_symbol . number_format( $display_price, 2 ) ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( ! $is_enrolled && ! $is_free ) : ?>
                <button
                    type="button"
                    class="edupro-btn edupro-btn--outline edupro-btn--sm edupro-add-to-cart-btn course-card__cart-btn<?php echo $is_in_cart ? ' is-in-cart' : ''; ?>"
                    data-course-id="<?php echo absint( $course_id ); ?>"
                    data-in-cart-label="<?php esc_attr_e( 'In Cart ✓', 'edupro' ); ?>"
                    data-default-label="<?php esc_attr_e( 'Add to Cart', 'edupro' ); ?>"
                >
                    <?php echo $is_in_cart ? esc_html__( 'In Cart ✓', 'edupro' ) : esc_html__( 'Add to Cart', 'edupro' ); ?>
                </button>
            <?php elseif ( $lessons_count > 0 ) : ?>
                <span class="course-card__lessons-meta">
                    <?php
                    printf(
                        /* translators: %d: lessons count */
                        esc_html( _n( '%d lesson', '%d lessons', $lessons_count, 'edupro' ) ),
                        absint( $lessons_count )
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</article>
