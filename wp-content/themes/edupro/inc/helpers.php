<?php
/**
 * EduPro LMS — inc/helpers.php
 *
 * Global utility / helper functions used throughout the theme.
 * All functions are prefixed edupro_ to avoid conflicts.
 *
 * @package EduPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Currency helpers
// ---------------------------------------------------------------------------

if ( ! function_exists( 'edupro_get_currency_symbol' ) ) :
/**
 * Returns the site currency symbol (fallback when inc/currency.php is not loaded).
 *
 * @param  string $code Optional ISO currency code ('INR', 'GBP', 'USD').
 * @return string
 */
function edupro_get_currency_symbol( $code = '' ) {
    $symbols = [
        'INR' => '₹',
        'GBP' => '£',
        'USD' => '$',
    ];
    $code = strtoupper( (string) $code );
    return isset( $symbols[ $code ] ) ? $symbols[ $code ] : '₹';
}
endif;

if ( ! function_exists( 'edupro_format_price' ) ) :
/**
 * Formats a numeric price with the currency prefix and 2 decimal places.
 *
 * @param  float|int|string $price     Raw price value.
 * @param  string           $currency  Optional ISO currency code.
 * @return string                      Formatted price string, e.g. "₹1,299.00".
 */
function edupro_format_price( $price, $currency = '' ) {
    $price  = edupro_sanitize_price( $price );
    $symbol = edupro_get_currency_symbol( $currency );

    // number_format: 2 decimals, dot separator, comma thousands separator.
    return $symbol . number_format( (float) $price, 2, '.', ',' );
}
endif;

/**
 * Sanitizes a price value and returns a float.
 * Strips anything that is not a digit or a decimal point.
 *
 * @param  mixed $price  Raw input.
 * @return float
 */
function edupro_sanitize_price( $price ) {
    // Allow digits and a single decimal point; remove everything else.
    $sanitized = preg_replace( '/[^0-9.]/', '', (string) $price );

    // If multiple dots exist, keep only the first one.
    $parts = explode( '.', $sanitized );
    if ( count( $parts ) > 2 ) {
        $sanitized = $parts[0] . '.' . implode( '', array_slice( $parts, 1 ) );
    }

    return (float) $sanitized;
}

// ---------------------------------------------------------------------------
// 2. Course rating
// ---------------------------------------------------------------------------

/**
 * Calculates the average star rating for a course from its review meta.
 *
 * Each review is stored as a serialised array in the post meta key
 * `course_reviews`. Each entry in that array must have a `rating` key (1-5).
 *
 * @param  int   $course_id  Post ID of the lms_course.
 * @return float             Average rating, or 0.0 if no reviews.
 */
function edupro_get_course_rating( $course_id ) {
    $course_id = absint( $course_id );
    if ( ! $course_id ) {
        return 0.0;
    }

    $reviews = get_post_meta( $course_id, 'course_reviews', true );

    if ( empty( $reviews ) || ! is_array( $reviews ) ) {
        return 0.0;
    }

    $total  = 0;
    $count  = 0;

    foreach ( $reviews as $review ) {
        if ( isset( $review['rating'] ) ) {
            $rating = (float) $review['rating'];
            // Clamp to valid range.
            if ( $rating >= 1 && $rating <= 5 ) {
                $total += $rating;
                $count++;
            }
        }
    }

    if ( $count === 0 ) {
        return 0.0;
    }

    return round( $total / $count, 1 );
}

// ---------------------------------------------------------------------------
// 3. Enrolment count
// ---------------------------------------------------------------------------

/**
 * Counts how many users are enrolled in a given course.
 *
 * Enrolment is tracked by storing the course ID inside the user-meta key
 * `_edupro_enrolled_courses` as a serialised array.
 *
 * @param  int $course_id  Post ID of the lms_course.
 * @return int             Number of enrolled students.
 */
function edupro_get_enrolled_count( $course_id ) {
    $course_id = absint( $course_id );
    if ( ! $course_id ) {
        return 0;
    }

    global $wpdb;

    /*
     * We query usermeta for rows where `_edupro_enrolled_courses` contains the
     * serialised representation of this course ID. Using LIKE is acceptable for
     * most LMS scales; for very large sites a dedicated enrolment table is
     * recommended.
     */
    $like_value = '%' . $wpdb->esc_like( '"' . $course_id . '"' ) . '%';

    // Also handle integer-indexed serialised arrays: s:...; i:0; i:<course_id>;
    $like_value_int = '%' . $wpdb->esc_like( 'i:' . $course_id . ';' ) . '%';

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND ( meta_value LIKE %s OR meta_value LIKE %s )",
            '_edupro_enrolled_courses',
            $like_value,
            $like_value_int
        )
    );

    return $count;
}

// ---------------------------------------------------------------------------
// 4. Lessons count
// ---------------------------------------------------------------------------

/**
 * Counts the number of published lessons that belong to a course.
 *
 * Lessons (post type `lms_lesson`) store their parent course ID in the
 * post-meta key `_edupro_course_id`.
 *
 * @param  int $course_id  Post ID of the lms_course.
 * @return int             Number of published lessons.
 */
function edupro_get_course_lessons_count( $course_id ) {
    $course_id = absint( $course_id );
    if ( ! $course_id ) {
        return 0;
    }

    $query = new WP_Query( [
        'post_type'      => 'lms_lesson',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',          // only fetch IDs — fastest
        'no_found_rows'  => true,           // skip SQL_CALC_FOUND_ROWS
        'meta_query'     => [
            [
                'key'     => '_edupro_course_id',
                'value'   => $course_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ] );

    return (int) $query->post_count;
}

// ---------------------------------------------------------------------------
// 5. Enrolment check
// ---------------------------------------------------------------------------

/**
 * Checks whether a user is enrolled in a given course.
 *
 * @param  int      $course_id  Post ID of the lms_course.
 * @param  int|null $user_id    User ID; defaults to the currently logged-in user.
 * @return bool
 */
function edupro_is_student_enrolled( $course_id, $user_id = null ) {
    $course_id = absint( $course_id );

    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    } else {
        $user_id = absint( $user_id );
    }

    if ( ! $course_id || ! $user_id ) {
        return false;
    }

    $enrolled_courses = get_user_meta( $user_id, '_edupro_enrolled_courses', true );

    if ( empty( $enrolled_courses ) || ! is_array( $enrolled_courses ) ) {
        return false;
    }

    return in_array( $course_id, array_map( 'absint', $enrolled_courses ), true );
}

// ---------------------------------------------------------------------------
// 6. Breadcrumb
// ---------------------------------------------------------------------------

/**
 * Generates full breadcrumb HTML: Home > Category > Course (or current page).
 *
 * Outputs nothing on the front page. Callers should echo the return value.
 *
 * @return string  HTML string for the breadcrumb.
 */
function edupro_get_breadcrumb() {
    if ( is_front_page() ) {
        return '';
    }

    $separator = '<span class="edupro-breadcrumb__sep" aria-hidden="true">/</span>';
    $items     = [];

    // Home
    $items[] = sprintf(
        '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
        esc_url( home_url( '/' ) ),
        esc_html__( 'Home', 'edupro' )
    );

    if ( is_singular( 'lms_course' ) ) {
        // Category
        $terms = get_the_terms( get_the_ID(), 'course_category' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $term    = reset( $terms );
            $items[] = sprintf(
                '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
                esc_url( get_term_link( $term ) ),
                esc_html( $term->name )
            );
        }
        // Course title (current, not linked)
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( get_the_title() )
        );

    } elseif ( is_singular( 'lms_lesson' ) ) {
        // Parent course
        $course_id = (int) get_post_meta( get_the_ID(), '_edupro_course_id', true );
        if ( $course_id ) {
            // Category of the course
            $terms = get_the_terms( $course_id, 'course_category' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $term    = reset( $terms );
                $items[] = sprintf(
                    '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
                    esc_url( get_term_link( $term ) ),
                    esc_html( $term->name )
                );
            }
            $items[] = sprintf(
                '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
                esc_url( get_permalink( $course_id ) ),
                esc_html( get_the_title( $course_id ) )
            );
        }
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( get_the_title() )
        );

    } elseif ( is_tax() ) {
        $term    = get_queried_object();
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( $term->name )
        );

    } elseif ( is_post_type_archive() ) {
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( post_type_archive_title( '', false ) )
        );

    } elseif ( is_page() ) {
        // Ancestor pages
        $ancestors = array_reverse( get_post_ancestors( get_the_ID() ) );
        foreach ( $ancestors as $ancestor_id ) {
            $items[] = sprintf(
                '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
                esc_url( get_permalink( $ancestor_id ) ),
                esc_html( get_the_title( $ancestor_id ) )
            );
        }
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( get_the_title() )
        );

    } elseif ( is_singular() ) {
        // Generic singular (blog post, etc.)
        $category = get_the_category();
        if ( $category ) {
            $items[] = sprintf(
                '<a href="%s" class="edupro-breadcrumb__link">%s</a>',
                esc_url( get_category_link( $category[0]->term_id ) ),
                esc_html( $category[0]->name )
            );
        }
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( get_the_title() )
        );

    } elseif ( is_archive() ) {
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html( get_the_archive_title() )
        );

    } elseif ( is_search() ) {
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s &ldquo;%s&rdquo;</span>',
            esc_html__( 'Search Results for', 'edupro' ),
            esc_html( get_search_query() )
        );

    } elseif ( is_404() ) {
        $items[] = sprintf(
            '<span class="edupro-breadcrumb__current" aria-current="page">%s</span>',
            esc_html__( '404 — Page Not Found', 'edupro' )
        );
    }

    if ( count( $items ) <= 1 ) {
        // Only "Home" — not useful to show.
        return '';
    }

    $html  = '<nav class="edupro-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'edupro' ) . '">';
    $html .= '<ol class="edupro-breadcrumb__list">';
    foreach ( $items as $index => $item ) {
        $is_last  = ( $index === count( $items ) - 1 );
        $html    .= '<li class="edupro-breadcrumb__item">' . $item . '</li>';
        if ( ! $is_last ) {
            $html .= '<li class="edupro-breadcrumb__item edupro-breadcrumb__item--sep" aria-hidden="true">' . $separator . '</li>';
        }
    }
    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}

// ---------------------------------------------------------------------------
// 7. Duration label
// ---------------------------------------------------------------------------

/**
 * Converts a duration in minutes to a human-readable label.
 *
 * Examples:
 *   45  → "45 min"
 *   60  → "1 hr"
 *   90  → "1 hr 30 min"
 *   120 → "2 hr"
 *
 * @param  int|float $minutes  Duration in minutes.
 * @return string              Formatted label.
 */
function edupro_get_duration_label( $minutes ) {
    $minutes = max( 0, (int) round( (float) $minutes ) );

    if ( $minutes === 0 ) {
        return __( '0 min', 'edupro' );
    }

    $hours   = (int) floor( $minutes / 60 );
    $mins    = $minutes % 60;

    if ( $hours === 0 ) {
        /* translators: %d = number of minutes */
        return sprintf( _n( '%d min', '%d min', $mins, 'edupro' ), $mins );
    }

    if ( $mins === 0 ) {
        /* translators: %d = number of hours */
        return sprintf( _n( '%d hr', '%d hr', $hours, 'edupro' ), $hours );
    }

    /* translators: 1: hours, 2: minutes */
    return sprintf(
        __( '%1$d hr %2$d min', 'edupro' ),
        $hours,
        $mins
    );
}

// ---------------------------------------------------------------------------
// 8. Star rating HTML
// ---------------------------------------------------------------------------

/**
 * Returns HTML for a star rating widget.
 *
 * Uses inline clip-path / percentage widths so a fractional rating (e.g. 3.7)
 * renders as a partially-filled star without JS.
 *
 * Markup:
 *   <span class="edupro-stars" title="3.7 out of 5">
 *     <span class="edupro-stars__filled" style="width:74%">★★★★★</span>
 *     <span class="edupro-stars__empty"  aria-hidden="true">★★★★★</span>
 *   </span>
 *
 * CSS contract (must exist in main.css):
 *   .edupro-stars          { position:relative; display:inline-block; color:#ccc; }
 *   .edupro-stars__filled  { position:absolute; left:0; top:0; overflow:hidden; white-space:nowrap; color:#f5a623; }
 *   .edupro-stars__empty   { display:block; }
 *
 * @param  float $rating  Rating value (e.g. 3.7).
 * @param  int   $max     Maximum stars (default 5).
 * @return string         HTML string.
 */
function edupro_get_star_rating_html( $rating, $max = 5 ) {
    $rating = (float) $rating;
    $max    = max( 1, (int) $max );

    // Clamp rating to [0, $max].
    $rating = max( 0, min( $max, $rating ) );

    // Percentage fill.
    $fill_pct = round( ( $rating / $max ) * 100, 2 );

    // Build the star string (all filled chars; CSS handles the clip).
    $star_chars = str_repeat( '★', $max );

    /* translators: 1: rating value, 2: max stars */
    $title = esc_attr(
        sprintf( __( '%1$s out of %2$s', 'edupro' ), number_format( $rating, 1 ), $max )
    );

    $html  = '<span class="edupro-stars" title="' . $title . '" aria-label="' . $title . '">';
    $html .= '<span class="edupro-stars__filled" style="width:' . esc_attr( $fill_pct ) . '%" aria-hidden="true">' . $star_chars . '</span>';
    $html .= '<span class="edupro-stars__empty"  aria-hidden="true">' . $star_chars . '</span>';
    $html .= '</span>';

    return $html;
}
