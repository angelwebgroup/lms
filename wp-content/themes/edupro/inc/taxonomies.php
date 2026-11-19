<?php
/**
 * EduPro LMS - Taxonomies
 *
 * Registers all custom taxonomies and inserts default terms on theme activation.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all EduPro custom taxonomies.
 */
function edupro_register_taxonomies() {

    /* ------------------------------------------------------------------
     * 1. course_category – Course Categories (hierarchical, like Categories)
     * ------------------------------------------------------------------ */
    $cat_labels = array(
        'name'                       => _x( 'Course Categories', 'taxonomy general name', 'edupro' ),
        'singular_name'              => _x( 'Course Category', 'taxonomy singular name', 'edupro' ),
        'search_items'               => __( 'Search Course Categories', 'edupro' ),
        'popular_items'              => null, // Not used for hierarchical
        'all_items'                  => __( 'All Course Categories', 'edupro' ),
        'parent_item'                => __( 'Parent Course Category', 'edupro' ),
        'parent_item_colon'          => __( 'Parent Course Category:', 'edupro' ),
        'edit_item'                  => __( 'Edit Course Category', 'edupro' ),
        'view_item'                  => __( 'View Course Category', 'edupro' ),
        'update_item'                => __( 'Update Course Category', 'edupro' ),
        'add_new_item'               => __( 'Add New Course Category', 'edupro' ),
        'new_item_name'              => __( 'New Course Category Name', 'edupro' ),
        'separate_items_with_commas' => null,
        'add_or_remove_items'        => null,
        'choose_from_most_used'      => null,
        'not_found'                  => __( 'No course categories found.', 'edupro' ),
        'no_terms'                   => __( 'No course categories', 'edupro' ),
        'menu_name'                  => __( 'Categories', 'edupro' ),
        'items_list_navigation'      => __( 'Course categories list navigation', 'edupro' ),
        'items_list'                 => __( 'Course categories list', 'edupro' ),
        'most_used'                  => _x( 'Most Used', 'course_category', 'edupro' ),
        'back_to_items'              => __( '&larr; Go to Course Categories', 'edupro' ),
    );

    $cat_args = array(
        'labels'            => $cat_labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array(
            'slug'         => 'course-category',
            'with_front'   => false,
            'hierarchical' => true,
        ),
    );

    register_taxonomy( 'course_category', array( 'lms_course' ), $cat_args );

    /* ------------------------------------------------------------------
     * 2. course_tag – Course Tags (flat, like Tags)
     * ------------------------------------------------------------------ */
    $tag_labels = array(
        'name'                       => _x( 'Course Tags', 'taxonomy general name', 'edupro' ),
        'singular_name'              => _x( 'Course Tag', 'taxonomy singular name', 'edupro' ),
        'search_items'               => __( 'Search Course Tags', 'edupro' ),
        'popular_items'              => __( 'Popular Course Tags', 'edupro' ),
        'all_items'                  => __( 'All Course Tags', 'edupro' ),
        'parent_item'                => null,
        'parent_item_colon'          => null,
        'edit_item'                  => __( 'Edit Course Tag', 'edupro' ),
        'view_item'                  => __( 'View Course Tag', 'edupro' ),
        'update_item'                => __( 'Update Course Tag', 'edupro' ),
        'add_new_item'               => __( 'Add New Course Tag', 'edupro' ),
        'new_item_name'              => __( 'New Course Tag Name', 'edupro' ),
        'separate_items_with_commas' => __( 'Separate course tags with commas', 'edupro' ),
        'add_or_remove_items'        => __( 'Add or remove course tags', 'edupro' ),
        'choose_from_most_used'      => __( 'Choose from the most used course tags', 'edupro' ),
        'not_found'                  => __( 'No course tags found.', 'edupro' ),
        'no_terms'                   => __( 'No course tags', 'edupro' ),
        'menu_name'                  => __( 'Tags', 'edupro' ),
        'items_list_navigation'      => __( 'Course tags list navigation', 'edupro' ),
        'items_list'                 => __( 'Course tags list', 'edupro' ),
        'most_used'                  => _x( 'Most Used', 'course_tag', 'edupro' ),
        'back_to_items'              => __( '&larr; Go to Course Tags', 'edupro' ),
    );

    $tag_args = array(
        'labels'            => $tag_labels,
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array(
            'slug'       => 'course-tag',
            'with_front' => false,
        ),
    );

    register_taxonomy( 'course_tag', array( 'lms_course' ), $tag_args );

    /* ------------------------------------------------------------------
     * 3. course_level – Course Level (hierarchical)
     * ------------------------------------------------------------------ */
    $level_labels = array(
        'name'                       => _x( 'Course Levels', 'taxonomy general name', 'edupro' ),
        'singular_name'              => _x( 'Course Level', 'taxonomy singular name', 'edupro' ),
        'search_items'               => __( 'Search Course Levels', 'edupro' ),
        'popular_items'              => null,
        'all_items'                  => __( 'All Course Levels', 'edupro' ),
        'parent_item'                => __( 'Parent Course Level', 'edupro' ),
        'parent_item_colon'          => __( 'Parent Course Level:', 'edupro' ),
        'edit_item'                  => __( 'Edit Course Level', 'edupro' ),
        'view_item'                  => __( 'View Course Level', 'edupro' ),
        'update_item'                => __( 'Update Course Level', 'edupro' ),
        'add_new_item'               => __( 'Add New Course Level', 'edupro' ),
        'new_item_name'              => __( 'New Course Level Name', 'edupro' ),
        'separate_items_with_commas' => null,
        'add_or_remove_items'        => null,
        'choose_from_most_used'      => null,
        'not_found'                  => __( 'No course levels found.', 'edupro' ),
        'no_terms'                   => __( 'No course levels', 'edupro' ),
        'menu_name'                  => __( 'Levels', 'edupro' ),
        'items_list_navigation'      => __( 'Course levels list navigation', 'edupro' ),
        'items_list'                 => __( 'Course levels list', 'edupro' ),
        'most_used'                  => _x( 'Most Used', 'course_level', 'edupro' ),
        'back_to_items'              => __( '&larr; Go to Course Levels', 'edupro' ),
    );

    $level_args = array(
        'labels'            => $level_labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array(
            'slug'         => 'course-level',
            'with_front'   => false,
            'hierarchical' => true,
        ),
    );

    register_taxonomy( 'course_level', array( 'lms_course' ), $level_args );

    /* ------------------------------------------------------------------
     * 4. course_language – Course Language (hierarchical)
     * ------------------------------------------------------------------ */
    $lang_labels = array(
        'name'                       => _x( 'Course Languages', 'taxonomy general name', 'edupro' ),
        'singular_name'              => _x( 'Course Language', 'taxonomy singular name', 'edupro' ),
        'search_items'               => __( 'Search Course Languages', 'edupro' ),
        'popular_items'              => null,
        'all_items'                  => __( 'All Course Languages', 'edupro' ),
        'parent_item'                => __( 'Parent Language', 'edupro' ),
        'parent_item_colon'          => __( 'Parent Language:', 'edupro' ),
        'edit_item'                  => __( 'Edit Course Language', 'edupro' ),
        'view_item'                  => __( 'View Course Language', 'edupro' ),
        'update_item'                => __( 'Update Course Language', 'edupro' ),
        'add_new_item'               => __( 'Add New Course Language', 'edupro' ),
        'new_item_name'              => __( 'New Course Language Name', 'edupro' ),
        'separate_items_with_commas' => null,
        'add_or_remove_items'        => null,
        'choose_from_most_used'      => null,
        'not_found'                  => __( 'No course languages found.', 'edupro' ),
        'no_terms'                   => __( 'No course languages', 'edupro' ),
        'menu_name'                  => __( 'Languages', 'edupro' ),
        'items_list_navigation'      => __( 'Course languages list navigation', 'edupro' ),
        'items_list'                 => __( 'Course languages list', 'edupro' ),
        'most_used'                  => _x( 'Most Used', 'course_language', 'edupro' ),
        'back_to_items'              => __( '&larr; Go to Course Languages', 'edupro' ),
    );

    $lang_args = array(
        'labels'            => $lang_labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array(
            'slug'         => 'course-language',
            'with_front'   => false,
            'hierarchical' => true,
        ),
    );

    register_taxonomy( 'course_language', array( 'lms_course' ), $lang_args );
}
add_action( 'init', 'edupro_register_taxonomies', 0 );

/**
 * Insert default taxonomy terms on theme activation.
 *
 * Creates default course_level, course_category, and course_language terms so
 * the LMS works out-of-the-box without any manual configuration.
 *
 * Hooked directly to `after_switch_theme` below.
 */
function edupro_insert_default_terms() {

    // ----------------------------------------------------------------
    // course_level defaults
    // ----------------------------------------------------------------
    $default_levels = array(
        'Beginner'     => 'beginner',
        'Intermediate' => 'intermediate',
        'Advanced'     => 'advanced',
        'All Levels'   => 'all-levels',
    );

    foreach ( $default_levels as $name => $slug ) {
        if ( ! term_exists( $slug, 'course_level' ) ) {
            wp_insert_term(
                $name,
                'course_level',
                array(
                    'slug'        => $slug,
                    'description' => sprintf(
                        /* translators: %s: level name */
                        __( 'Courses suitable for %s learners.', 'edupro' ),
                        $name
                    ),
                )
            );
        }
    }

    // ----------------------------------------------------------------
    // course_category defaults
    // ----------------------------------------------------------------
    $default_categories = array(
        'Business'             => 'business',
        'Technology'           => 'technology',
        'Design'               => 'design',
        'Marketing'            => 'marketing',
        'Health & Fitness'     => 'health-fitness',
        'Personal Development' => 'personal-development',
    );

    foreach ( $default_categories as $name => $slug ) {
        if ( ! term_exists( $slug, 'course_category' ) ) {
            wp_insert_term(
                $name,
                'course_category',
                array(
                    'slug' => $slug,
                )
            );
        }
    }

    // ----------------------------------------------------------------
    // course_language defaults
    // ----------------------------------------------------------------
    $default_languages = array(
        'English' => 'english',
        'Hindi'   => 'hindi',
    );

    foreach ( $default_languages as $name => $slug ) {
        if ( ! term_exists( $slug, 'course_language' ) ) {
            wp_insert_term(
                $name,
                'course_language',
                array(
                    'slug' => $slug,
                )
            );
        }
    }
}
add_action( 'after_switch_theme', 'edupro_insert_default_terms' );

/**
 * Add custom columns to the course_category term list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function edupro_course_category_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;
        if ( 'name' === $key ) {
            $new_columns['course_count_custom'] = __( 'Courses', 'edupro' );
        }
    }
    // Remove the default count column to avoid duplication
    unset( $new_columns['posts'] );
    return $new_columns;
}
add_filter( 'manage_edit-course_category_columns', 'edupro_course_category_columns' );

/**
 * Populate the custom course_category columns.
 *
 * @param string $content     Column content.
 * @param string $column_name Column slug.
 * @param int    $term_id     Term ID.
 * @return string
 */
function edupro_course_category_column_content( $content, $column_name, $term_id ) {
    if ( 'course_count_custom' !== $column_name ) {
        return $content;
    }

    $term = get_term( $term_id, 'course_category' );
    if ( $term && ! is_wp_error( $term ) ) {
        $count   = $term->count;
        $content = sprintf(
            '<a href="%s">%d</a>',
            esc_url(
                add_query_arg(
                    array(
                        'post_type'       => 'lms_course',
                        'course_category' => $term->slug,
                    ),
                    admin_url( 'edit.php' )
                )
            ),
            absint( $count )
        );
    }

    return $content;
}
add_filter( 'manage_course_category_custom_column', 'edupro_course_category_column_content', 10, 3 );
