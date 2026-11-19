<?php
/**
 * EduPro LMS - Custom Post Types
 *
 * Registers all custom post types used by the EduPro LMS theme.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all EduPro custom post types.
 */
function edupro_register_post_types() {

    /* ------------------------------------------------------------------
     * 1. lms_course – Courses
     * ------------------------------------------------------------------ */
    $course_labels = array(
        'name'                  => _x( 'Courses', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Course', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Courses', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Course', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'course', 'edupro' ),
        'add_new_item'          => __( 'Add New Course', 'edupro' ),
        'new_item'              => __( 'New Course', 'edupro' ),
        'edit_item'             => __( 'Edit Course', 'edupro' ),
        'view_item'             => __( 'View Course', 'edupro' ),
        'all_items'             => __( 'All Courses', 'edupro' ),
        'search_items'          => __( 'Search Courses', 'edupro' ),
        'parent_item_colon'     => __( 'Parent Courses:', 'edupro' ),
        'not_found'             => __( 'No courses found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No courses found in Trash.', 'edupro' ),
        'featured_image'        => __( 'Course Thumbnail', 'edupro' ),
        'set_featured_image'    => __( 'Set course thumbnail', 'edupro' ),
        'remove_featured_image' => __( 'Remove course thumbnail', 'edupro' ),
        'use_featured_image'    => __( 'Use as course thumbnail', 'edupro' ),
        'archives'              => __( 'Course Archives', 'edupro' ),
        'insert_into_item'      => __( 'Insert into course', 'edupro' ),
        'uploaded_to_this_item' => __( 'Uploaded to this course', 'edupro' ),
        'items_list'            => __( 'Courses list', 'edupro' ),
        'items_list_navigation' => __( 'Courses list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter courses list', 'edupro' ),
    );

    $course_args = array(
        'labels'             => $course_labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'courses', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'page-attributes' ),
        'taxonomies'         => array( 'course_category', 'course_tag', 'course_level', 'course_language' ),
    );

    register_post_type( 'lms_course', $course_args );

    /* ------------------------------------------------------------------
     * 2. lms_lesson – Lessons
     * ------------------------------------------------------------------ */
    $lesson_labels = array(
        'name'                  => _x( 'Lessons', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Lesson', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Lessons', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Lesson', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'lesson', 'edupro' ),
        'add_new_item'          => __( 'Add New Lesson', 'edupro' ),
        'new_item'              => __( 'New Lesson', 'edupro' ),
        'edit_item'             => __( 'Edit Lesson', 'edupro' ),
        'view_item'             => __( 'View Lesson', 'edupro' ),
        'all_items'             => __( 'All Lessons', 'edupro' ),
        'search_items'          => __( 'Search Lessons', 'edupro' ),
        'parent_item_colon'     => __( 'Parent Lessons:', 'edupro' ),
        'not_found'             => __( 'No lessons found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No lessons found in Trash.', 'edupro' ),
        'featured_image'        => __( 'Lesson Thumbnail', 'edupro' ),
        'set_featured_image'    => __( 'Set lesson thumbnail', 'edupro' ),
        'remove_featured_image' => __( 'Remove lesson thumbnail', 'edupro' ),
        'use_featured_image'    => __( 'Use as lesson thumbnail', 'edupro' ),
        'archives'              => __( 'Lesson Archives', 'edupro' ),
        'insert_into_item'      => __( 'Insert into lesson', 'edupro' ),
        'uploaded_to_this_item' => __( 'Uploaded to this lesson', 'edupro' ),
        'items_list'            => __( 'Lessons list', 'edupro' ),
        'items_list_navigation' => __( 'Lessons list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter lessons list', 'edupro' ),
    );

    $lesson_args = array(
        'labels'             => $lesson_labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // Appears under Courses via custom admin page
        'show_in_nav_menus'  => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'lessons', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'menu_icon'          => 'dashicons-video-alt2',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
    );

    register_post_type( 'lms_lesson', $lesson_args );

    /* ------------------------------------------------------------------
     * 3. lms_quiz – Quizzes (admin only)
     * ------------------------------------------------------------------ */
    $quiz_labels = array(
        'name'                  => _x( 'Quizzes', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Quiz', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Quizzes', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Quiz', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'quiz', 'edupro' ),
        'add_new_item'          => __( 'Add New Quiz', 'edupro' ),
        'new_item'              => __( 'New Quiz', 'edupro' ),
        'edit_item'             => __( 'Edit Quiz', 'edupro' ),
        'view_item'             => __( 'View Quiz', 'edupro' ),
        'all_items'             => __( 'All Quizzes', 'edupro' ),
        'search_items'          => __( 'Search Quizzes', 'edupro' ),
        'not_found'             => __( 'No quizzes found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No quizzes found in Trash.', 'edupro' ),
        'items_list'            => __( 'Quizzes list', 'edupro' ),
        'items_list_navigation' => __( 'Quizzes list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter quizzes list', 'edupro' ),
    );

    $quiz_args = array(
        'labels'             => $quiz_labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor' ),
    );

    register_post_type( 'lms_quiz', $quiz_args );

    /* ------------------------------------------------------------------
     * 4. lms_review – Course Reviews (Google Reviews integration)
     * ------------------------------------------------------------------ */
    $review_labels = array(
        'name'                  => _x( 'Course Reviews', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Course Review', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Reviews', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Review', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'review', 'edupro' ),
        'add_new_item'          => __( 'Add New Review', 'edupro' ),
        'new_item'              => __( 'New Review', 'edupro' ),
        'edit_item'             => __( 'Edit Review', 'edupro' ),
        'view_item'             => __( 'View Review', 'edupro' ),
        'all_items'             => __( 'All Reviews', 'edupro' ),
        'search_items'          => __( 'Search Reviews', 'edupro' ),
        'not_found'             => __( 'No reviews found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No reviews found in Trash.', 'edupro' ),
        'items_list'            => __( 'Reviews list', 'edupro' ),
        'items_list_navigation' => __( 'Reviews list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter reviews list', 'edupro' ),
    );

    $review_args = array(
        'labels'             => $review_labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => false, // Managed entirely via code
        'show_in_menu'       => false,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
    );

    register_post_type( 'lms_review', $review_args );

    /* ------------------------------------------------------------------
     * 5. lms_instructor – Instructors
     * ------------------------------------------------------------------ */
    $instructor_labels = array(
        'name'                  => _x( 'Instructors', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Instructor', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Instructors', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Instructor', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'instructor', 'edupro' ),
        'add_new_item'          => __( 'Add New Instructor', 'edupro' ),
        'new_item'              => __( 'New Instructor', 'edupro' ),
        'edit_item'             => __( 'Edit Instructor', 'edupro' ),
        'view_item'             => __( 'View Instructor', 'edupro' ),
        'all_items'             => __( 'All Instructors', 'edupro' ),
        'search_items'          => __( 'Search Instructors', 'edupro' ),
        'parent_item_colon'     => __( 'Parent Instructors:', 'edupro' ),
        'not_found'             => __( 'No instructors found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No instructors found in Trash.', 'edupro' ),
        'featured_image'        => __( 'Instructor Photo', 'edupro' ),
        'set_featured_image'    => __( 'Set instructor photo', 'edupro' ),
        'remove_featured_image' => __( 'Remove instructor photo', 'edupro' ),
        'use_featured_image'    => __( 'Use as instructor photo', 'edupro' ),
        'archives'              => __( 'Instructor Archives', 'edupro' ),
        'insert_into_item'      => __( 'Insert into instructor', 'edupro' ),
        'uploaded_to_this_item' => __( 'Uploaded to this instructor', 'edupro' ),
        'items_list'            => __( 'Instructors list', 'edupro' ),
        'items_list_navigation' => __( 'Instructors list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter instructors list', 'edupro' ),
    );

    $instructor_args = array(
        'labels'             => $instructor_labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'instructors', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 7,
        'menu_icon'          => 'dashicons-businessman',
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
    );

    register_post_type( 'lms_instructor', $instructor_args );
}
add_action( 'init', 'edupro_register_post_types', 0 );

/**
 * Add Lessons to the Courses admin menu as a sub-item.
 *
 * Hooks into admin_menu at a late priority to inject the Lessons and
 * Quizzes sub-menu items beneath the Courses top-level item.
 */
function edupro_add_cpt_submenu_items() {
    // Lessons sub-menu under Courses
    add_submenu_page(
        'edit.php?post_type=lms_course',
        __( 'Lessons', 'edupro' ),
        __( 'Lessons', 'edupro' ),
        'edit_posts',
        'edit.php?post_type=lms_lesson'
    );

    // Quizzes sub-menu under Courses
    add_submenu_page(
        'edit.php?post_type=lms_course',
        __( 'Quizzes', 'edupro' ),
        __( 'Quizzes', 'edupro' ),
        'edit_posts',
        'edit.php?post_type=lms_quiz'
    );

    // Add New Lesson shortcut
    add_submenu_page(
        'edit.php?post_type=lms_course',
        __( 'Add New Lesson', 'edupro' ),
        __( 'Add New Lesson', 'edupro' ),
        'edit_posts',
        'post-new.php?post_type=lms_lesson'
    );
}
add_action( 'admin_menu', 'edupro_add_cpt_submenu_items' );

/**
 * Highlight the correct top-level menu item (Courses) when editing a
 * Lesson or Quiz, since those CPTs use show_in_menu=false.
 *
 * @param string $parent_file Current parent menu file.
 * @return string
 */
function edupro_set_cpt_parent_menu( $parent_file ) {
    global $current_screen;

    if ( isset( $current_screen->post_type ) && in_array( $current_screen->post_type, array( 'lms_lesson', 'lms_quiz' ), true ) ) {
        $parent_file = 'edit.php?post_type=lms_course';
    }

    return $parent_file;
}
add_filter( 'parent_file', 'edupro_set_cpt_parent_menu' );

/**
 * Flush rewrite rules once after CPTs and taxonomies are registered on theme activation.
 */
function edupro_flush_rewrite_rules() {
    edupro_register_post_types();
    if ( function_exists( 'edupro_register_taxonomies' ) ) {
        edupro_register_taxonomies();
    }
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'edupro_flush_rewrite_rules' );
