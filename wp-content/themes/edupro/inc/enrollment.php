<?php
/**
 * EduPro Course Enrollment & Progress System
 *
 * Handles student enrollment, unenrollment, progress tracking,
 * lesson completion, curriculum navigation, and free-course AJAX enrollment.
 *
 * @package EduPro
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. COURSE LESSON & PRICING HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retrieve ordered published lesson IDs belonging to a course.
 *
 * Queries all published `lms_lesson` posts linked via `_edupro_course_id`
 * and sorts them in PHP by `_edupro_lesson_order` (ASC), then `menu_order` (ASC),
 * then `ID` (ASC) so lessons without an explicit order meta are never excluded.
 *
 * @param int $course_id Post ID of the `lms_course`.
 * @return int[] Ordered array of lesson post IDs.
 */
function edupro_get_course_lessons( $course_id ) {
	$course_id = absint( $course_id );
	if ( ! $course_id ) {
		return array();
	}

	$lesson_posts = get_posts(
		array(
			'post_type'      => 'lms_lesson',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			'meta_query'     => array(
				array(
					'key'     => '_edupro_course_id',
					'value'   => $course_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	if ( empty( $lesson_posts ) ) {
		return array();
	}

	usort(
		$lesson_posts,
		function ( $a, $b ) {
			$order_a_raw = get_post_meta( $a->ID, '_edupro_lesson_order', true );
			$order_b_raw = get_post_meta( $b->ID, '_edupro_lesson_order', true );

			$has_a = ( '' !== $order_a_raw && null !== $order_a_raw );
			$has_b = ( '' !== $order_b_raw && null !== $order_b_raw );

			if ( $has_a && $has_b ) {
				$diff = (int) $order_a_raw - (int) $order_b_raw;
				if ( 0 !== $diff ) {
					return $diff;
				}
			} elseif ( $has_a ) {
				return -1;
			} elseif ( $has_b ) {
				return 1;
			}

			if ( (int) $a->menu_order !== (int) $b->menu_order ) {
				return (int) $a->menu_order - (int) $b->menu_order;
			}

			return (int) $a->ID - (int) $b->ID;
		}
	);

	return array_map(
		function ( $post ) {
			return (int) $post->ID;
		},
		$lesson_posts
	);
}

if ( ! function_exists( 'edupro_is_course_free' ) ) :
/**
 * Determine whether a course is free to enroll.
 *
 * Supports both metabox meta keys (`_edupro_is_free`, `_edupro_regular_price`,
 * `_edupro_sale_price`, `_edupro_sale_end_date`) and legacy/payment gateway
 * keys (`_edupro_course_free`, `_edupro_course_price`).
 *
 * @param int $course_id Post ID of the `lms_course`.
 * @return bool True if the course is free, false if paid.
 */
function edupro_is_course_free( $course_id ) {
	$course_id = absint( $course_id );
	if ( ! $course_id ) {
		return false;
	}

	// Explicit free flags.
	if ( '1' === (string) get_post_meta( $course_id, '_edupro_is_free', true ) ) {
		return true;
	}
	if ( '1' === (string) get_post_meta( $course_id, '_edupro_course_free', true ) ) {
		return true;
	}

	$sale_price    = get_post_meta( $course_id, '_edupro_sale_price', true );
	$sale_end_date = get_post_meta( $course_id, '_edupro_sale_end_date', true );
	$regular_price = get_post_meta( $course_id, '_edupro_regular_price', true );
	$legacy_price  = get_post_meta( $course_id, '_edupro_course_price', true );

	// Ignore expired sale price.
	$sale_active = ( '' !== $sale_price && null !== $sale_price );
	if ( $sale_active && ! empty( $sale_end_date ) ) {
		$end_ts = strtotime( $sale_end_date . ' 23:59:59' );
		if ( $end_ts && current_time( 'timestamp' ) > $end_ts ) {
			$sale_active = false;
		}
	}

	if ( $sale_active ) {
		$effective_price = (float) $sale_price;
	} elseif ( '' !== $regular_price && null !== $regular_price ) {
		$effective_price = (float) $regular_price;
	} elseif ( '' !== $legacy_price && null !== $legacy_price ) {
		$effective_price = (float) $legacy_price;
	} else {
		$effective_price = 0.0;
	}

	return $effective_price <= 0.0;
}
endif;

/**
 * Retrieve the list of enrolled student user IDs stored on a course.
 *
 * @param int $course_id Post ID of the `lms_course`.
 * @return int[] Array of user IDs.
 */
function edupro_get_enrolled_students( $course_id ) {
	$course_id = absint( $course_id );
	if ( ! $course_id ) {
		return array();
	}

	$students = get_post_meta( $course_id, '_edupro_enrolled_students', true );
	if ( ! is_array( $students ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $students ) ) ) );
}

/**
 * Retrieve the enrollment date string for a user in a course.
 *
 * @param int      $course_id Post ID of the `lms_course`.
 * @param int|null $user_id   Optional. Defaults to current user.
 * @return string MySQL datetime string or empty string if not recorded.
 */
function edupro_get_enrollment_date( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return '';
	}

	return (string) get_user_meta( $user_id, '_edupro_enrollment_' . $course_id . '_date', true );
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ENROLL STUDENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Enroll a student in a course.
 *
 * Adds `$course_id` to user meta array `_edupro_enrolled_courses`, adds
 * `$user_id` to course post meta array `_edupro_enrolled_students`, and
 * records the enrollment timestamp in user meta `_edupro_enrollment_{course_id}_date`.
 *
 * @param int      $course_id Post ID of the `lms_course`.
 * @param int|null $user_id   Optional. User ID to enroll. Defaults to current user.
 * @return bool True on success (or if already enrolled), false on failure.
 */
function edupro_enroll_student( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	// Verify course exists and is an lms_course post.
	$course = get_post( $course_id );
	if ( ! $course || 'lms_course' !== $course->post_type || ! in_array( $course->post_status, array( 'publish', 'private' ), true ) ) {
		return false;
	}

	// Verify user exists.
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	// Read current user enrolled courses.
	$enrolled_courses = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
	if ( ! is_array( $enrolled_courses ) ) {
		$enrolled_courses = array();
	}
	$enrolled_courses = array_values( array_unique( array_filter( array_map( 'absint', $enrolled_courses ) ) ) );

	$already_enrolled = in_array( $course_id, $enrolled_courses, true );

	// Check enrollment limit for new enrollments.
	if ( ! $already_enrolled ) {
		$enrollment_limit = absint( get_post_meta( $course_id, '_edupro_enrollment_limit', true ) );
		if ( $enrollment_limit > 0 ) {
			$current_students = edupro_get_enrolled_students( $course_id );
			if ( count( $current_students ) >= $enrollment_limit ) {
				return false;
			}
		}
	}

	// 1. Add course_id to user meta '_edupro_enrolled_courses'.
	if ( ! $already_enrolled ) {
		$enrolled_courses[] = $course_id;
		update_user_meta( $user_id, '_edupro_enrolled_courses', $enrolled_courses );
	}

	// 2. Add user_id to course meta '_edupro_enrolled_students'.
	$enrolled_students = edupro_get_enrolled_students( $course_id );
	if ( ! in_array( $user_id, $enrolled_students, true ) ) {
		$enrolled_students[] = $user_id;
		update_post_meta( $course_id, '_edupro_enrolled_students', $enrolled_students );
	}

	// 3. Record enrollment date in '_edupro_enrollment_{course_id}_date'.
	$date_meta_key = '_edupro_enrollment_' . $course_id . '_date';
	$existing_date = get_user_meta( $user_id, $date_meta_key, true );
	if ( empty( $existing_date ) ) {
		update_user_meta( $user_id, $date_meta_key, current_time( 'mysql' ) );
	}

	if ( ! $already_enrolled ) {
		/**
		 * Fires after a student is newly enrolled in a course.
		 *
		 * @param int $course_id Enrolled course post ID.
		 * @param int $user_id   Enrolled student user ID.
		 */
		do_action( 'edupro_student_enrolled', $course_id, $user_id );
	}

	return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. UNENROLL STUDENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Unenroll a student from a course.
 *
 * Removes `$course_id` from user meta `_edupro_enrolled_courses`,
 * removes `$user_id` from course post meta `_edupro_enrolled_students`,
 * and cleans up the enrollment date meta `_edupro_enrollment_{course_id}_date`.
 *
 * @param int      $course_id Post ID of the `lms_course`.
 * @param int|null $user_id   Optional. User ID to unenroll. Defaults to current user.
 * @return bool True on success, false on invalid arguments.
 */
function edupro_unenroll_student( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	// 1. Remove course_id from user meta '_edupro_enrolled_courses'.
	$enrolled_courses = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
	if ( is_array( $enrolled_courses ) ) {
		$enrolled_courses = array_values(
			array_filter(
				array_map( 'absint', $enrolled_courses ),
				function ( $id ) use ( $course_id ) {
					return $id > 0 && $id !== $course_id;
				}
			)
		);
		update_user_meta( $user_id, '_edupro_enrolled_courses', $enrolled_courses );
	}

	// 2. Remove user_id from course meta '_edupro_enrolled_students'.
	$enrolled_students = get_post_meta( $course_id, '_edupro_enrolled_students', true );
	if ( is_array( $enrolled_students ) ) {
		$enrolled_students = array_values(
			array_filter(
				array_map( 'absint', $enrolled_students ),
				function ( $id ) use ( $user_id ) {
					return $id > 0 && $id !== $user_id;
				}
			)
		);
		update_post_meta( $course_id, '_edupro_enrolled_students', $enrolled_students );
	}

	// 3. Remove enrollment date meta.
	delete_user_meta( $user_id, '_edupro_enrollment_' . $course_id . '_date' );

	/**
	 * Fires after a student is unenrolled from a course.
	 *
	 * @param int $course_id Unenrolled course post ID.
	 * @param int $user_id   Student user ID.
	 */
	do_action( 'edupro_student_unenrolled', $course_id, $user_id );

	return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. LESSON COMPLETION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retrieve all completed lesson IDs for a user.
 *
 * @param int|null $user_id Optional. Defaults to current user.
 * @return int[] Array of completed lesson post IDs.
 */
function edupro_get_completed_lessons( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
	if ( ! $user_id ) {
		return array();
	}

	$completed = get_user_meta( $user_id, '_edupro_completed_lessons', true );
	if ( ! is_array( $completed ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $completed ) ) ) );
}

/**
 * Mark a lesson as complete for a student in user meta.
 *
 * Updates `_edupro_completed_lessons`, records the lesson completion timestamp,
 * tracks the last accessed lesson for the parent course, and marks the course
 * completed when progress reaches 100%.
 *
 * @param int      $lesson_id Post ID of the `lms_lesson`.
 * @param int|null $user_id   Optional. Defaults to current user.
 * @return bool True on success, false on failure.
 */
function edupro_mark_lesson_complete( $lesson_id, $user_id = null ) {
	$lesson_id = absint( $lesson_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $lesson_id || ! $user_id ) {
		return false;
	}

	$lesson = get_post( $lesson_id );
	if ( ! $lesson || 'lms_lesson' !== $lesson->post_type ) {
		return false;
	}

	$completed = edupro_get_completed_lessons( $user_id );
	$was_new   = ! in_array( $lesson_id, $completed, true );

	if ( $was_new ) {
		$completed[] = $lesson_id;
		update_user_meta( $user_id, '_edupro_completed_lessons', $completed );
		update_user_meta( $user_id, '_edupro_lesson_' . $lesson_id . '_completed_date', current_time( 'mysql' ) );
	}

	$course_id = absint( get_post_meta( $lesson_id, '_edupro_course_id', true ) );
	if ( $course_id ) {
		update_user_meta( $user_id, '_edupro_last_lesson_' . $course_id, $lesson_id );

		// Track per-course completed lessons for fast lookups.
		$course_completed = get_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, true );
		if ( ! is_array( $course_completed ) ) {
			$course_completed = array();
		}
		$course_completed = array_values( array_unique( array_filter( array_map( 'absint', $course_completed ) ) ) );
		if ( ! in_array( $lesson_id, $course_completed, true ) ) {
			$course_completed[] = $lesson_id;
			update_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, $course_completed );
		}

		// Check if the entire course is now 100% complete.
		$progress = edupro_get_course_progress( $course_id, $user_id );
		if ( $progress >= 100 ) {
			$completed_date_key = '_edupro_course_' . $course_id . '_completed_date';
			if ( ! get_user_meta( $user_id, $completed_date_key, true ) ) {
				update_user_meta( $user_id, $completed_date_key, current_time( 'mysql' ) );

				/**
				 * Fires when a student completes 100% of a course's lessons.
				 *
				 * @param int $course_id Completed course post ID.
				 * @param int $user_id   Student user ID.
				 */
				do_action( 'edupro_course_completed', $course_id, $user_id );
			}
		}
	}

	if ( $was_new ) {
		/**
		 * Fires when a student marks a lesson as complete.
		 *
		 * @param int $lesson_id Lesson post ID.
		 * @param int $course_id Parent course post ID (0 if unlinked).
		 * @param int $user_id   Student user ID.
		 */
		do_action( 'edupro_lesson_completed', $lesson_id, $course_id, $user_id );
	}

	return true;
}

/**
 * Unmark a lesson as complete for a student.
 *
 * @param int      $lesson_id Post ID of the `lms_lesson`.
 * @param int|null $user_id   Optional. Defaults to current user.
 * @return bool True on success, false on invalid arguments.
 */
function edupro_unmark_lesson_complete( $lesson_id, $user_id = null ) {
	$lesson_id = absint( $lesson_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $lesson_id || ! $user_id ) {
		return false;
	}

	$completed = edupro_get_completed_lessons( $user_id );
	$updated   = array_values(
		array_filter(
			$completed,
			function ( $id ) use ( $lesson_id ) {
				return $id !== $lesson_id;
			}
		)
	);
	update_user_meta( $user_id, '_edupro_completed_lessons', $updated );
	delete_user_meta( $user_id, '_edupro_lesson_' . $lesson_id . '_completed_date' );

	$course_id = absint( get_post_meta( $lesson_id, '_edupro_course_id', true ) );
	if ( $course_id ) {
		$course_completed = get_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, true );
		if ( is_array( $course_completed ) ) {
			$course_completed = array_values(
				array_filter(
					array_map( 'absint', $course_completed ),
					function ( $id ) use ( $lesson_id ) {
						return $id > 0 && $id !== $lesson_id;
					}
				)
			);
			update_user_meta( $user_id, '_edupro_completed_lessons_' . $course_id, $course_completed );
		}
	}

	return true;
}

/**
 * Check whether a student has completed a specific lesson.
 *
 * @param int      $lesson_id Post ID of the `lms_lesson`.
 * @param int|null $user_id   Optional. Defaults to current user.
 * @return bool True if completed, false otherwise.
 */
function edupro_is_lesson_complete( $lesson_id, $user_id = null ) {
	$lesson_id = absint( $lesson_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $lesson_id || ! $user_id ) {
		return false;
	}

	$completed = edupro_get_completed_lessons( $user_id );
	return in_array( $lesson_id, $completed, true );
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. COURSE PROGRESS & STUDENT COURSES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate a student's completion percentage (0–100) for a course.
 *
 * @param int      $course_id Post ID of the `lms_course`.
 * @param int|null $user_id   Optional. Defaults to current user.
 * @return int Completion percentage from 0 to 100.
 */
function edupro_get_course_progress( $course_id, $user_id = null ) {
	$course_id = absint( $course_id );
	$user_id   = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return 0;
	}

	$lesson_ids = edupro_get_course_lessons( $course_id );
	$total      = count( $lesson_ids );

	if ( 0 === $total ) {
		return 0;
	}

	$completed_all = edupro_get_completed_lessons( $user_id );
	if ( empty( $completed_all ) ) {
		return 0;
	}

	$completed_in_course = count( array_intersect( $lesson_ids, $completed_all ) );
	$percentage          = (int) round( ( $completed_in_course / $total ) * 100 );

	return max( 0, min( 100, $percentage ) );
}

/**
 * Retrieve all enrolled courses for a student along with progress metadata.
 *
 * Returns an array of associative arrays, one per valid enrolled course:
 * - course_id         (int)
 * - title             (string)
 * - permalink         (string)
 * - thumbnail         (string)
 * - enrolled_date     (string)
 * - total_lessons     (int)
 * - completed_lessons (int)
 * - progress          (int) 0–100
 * - is_completed      (bool)
 * - completed_date    (string)
 * - last_lesson_id    (int)
 * - next_lesson_id    (int)
 * - next_lesson_url   (string)
 *
 * @param int|null $user_id Optional. Defaults to current user.
 * @return array[] List of enrolled course records with progress data.
 */
function edupro_get_student_courses( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
	if ( ! $user_id ) {
		return array();
	}

	$enrolled_ids = get_user_meta( $user_id, '_edupro_enrolled_courses', true );
	if ( empty( $enrolled_ids ) || ! is_array( $enrolled_ids ) ) {
		return array();
	}

	$enrolled_ids  = array_values( array_unique( array_filter( array_map( 'absint', $enrolled_ids ) ) ) );
	$completed_all = edupro_get_completed_lessons( $user_id );
	$courses       = array();

	foreach ( $enrolled_ids as $course_id ) {
		$post = get_post( $course_id );
		if ( ! $post || 'lms_course' !== $post->post_type || 'trash' === $post->post_status ) {
			continue;
		}

		$lesson_ids          = edupro_get_course_lessons( $course_id );
		$total_lessons       = count( $lesson_ids );
		$completed_in_course = $total_lessons > 0 ? count( array_intersect( $lesson_ids, $completed_all ) ) : 0;
		$progress            = $total_lessons > 0 ? (int) round( ( $completed_in_course / $total_lessons ) * 100 ) : 0;
		$progress            = max( 0, min( 100, $progress ) );

		$last_lesson_id  = absint( get_user_meta( $user_id, '_edupro_last_lesson_' . $course_id, true ) );
		$next_lesson_id  = edupro_get_next_lesson( $course_id, 0, $user_id );
		$course_url      = get_permalink( $course_id );
		$next_lesson_url = $next_lesson_id ? get_permalink( $next_lesson_id ) : $course_url;
		$thumbnail_url   = get_the_post_thumbnail_url( $course_id, 'course-card' );

		$courses[] = array(
			'course_id'         => $course_id,
			'title'             => get_the_title( $course_id ),
			'permalink'         => $course_url,
			'thumbnail'         => $thumbnail_url ? $thumbnail_url : '',
			'enrolled_date'     => edupro_get_enrollment_date( $course_id, $user_id ),
			'total_lessons'     => $total_lessons,
			'completed_lessons' => $completed_in_course,
			'progress'          => $progress,
			'is_completed'      => ( $total_lessons > 0 && $progress >= 100 ),
			'completed_date'    => (string) get_user_meta( $user_id, '_edupro_course_' . $course_id . '_completed_date', true ),
			'last_lesson_id'    => $last_lesson_id,
			'next_lesson_id'    => $next_lesson_id,
			'next_lesson_url'   => $next_lesson_url,
		);
	}

	return $courses;
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. CURRICULUM NAVIGATION (NEXT & PREVIOUS LESSON)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the next lesson ID in a course's curriculum.
 *
 * Behavior:
 * - If `$current_lesson_id` is supplied and found in the course's ordered lessons,
 *   returns the immediately following lesson ID (or `0` if it is the last lesson).
 * - If `$current_lesson_id` is `0` / omitted (e.g. "Continue Learning" on the
 *   dashboard), returns the first incomplete lesson for `$user_id`, or the first
 *   lesson of the course if all lessons are already complete.
 *
 * @param int      $course_id         Post ID of the `lms_course`.
 * @param int      $current_lesson_id Optional. Current lesson post ID. Default 0.
 * @param int|null $user_id           Optional. Student user ID. Defaults to current user.
 * @return int Next lesson post ID, or 0 if none exists.
 */
function edupro_get_next_lesson( $course_id, $current_lesson_id = 0, $user_id = null ) {
	$course_id         = absint( $course_id );
	$current_lesson_id = absint( $current_lesson_id );
	$user_id           = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( ! $course_id ) {
		return 0;
	}

	$lessons = edupro_get_course_lessons( $course_id );
	if ( empty( $lessons ) ) {
		return 0;
	}

	// Sequential navigation from a specific lesson.
	if ( $current_lesson_id > 0 ) {
		$current_index = array_search( $current_lesson_id, $lessons, true );
		if ( false !== $current_index ) {
			return isset( $lessons[ $current_index + 1 ] ) ? (int) $lessons[ $current_index + 1 ] : 0;
		}
	}

	// Resume / Continue Learning: find first incomplete lesson for the student.
	if ( $user_id > 0 ) {
		$completed = edupro_get_completed_lessons( $user_id );
		foreach ( $lessons as $lesson_id ) {
			if ( ! in_array( $lesson_id, $completed, true ) ) {
				return (int) $lesson_id;
			}
		}
	}

	// All lessons completed (or guest user): return first lesson.
	return (int) $lessons[0];
}

/**
 * Get the previous lesson ID in a course's curriculum.
 *
 * @param int $course_id         Post ID of the `lms_course`.
 * @param int $current_lesson_id Current lesson post ID.
 * @return int Previous lesson post ID, or 0 if at the first lesson.
 */
function edupro_get_prev_lesson( $course_id, $current_lesson_id ) {
	$course_id         = absint( $course_id );
	$current_lesson_id = absint( $current_lesson_id );

	if ( ! $course_id || ! $current_lesson_id ) {
		return 0;
	}

	$lessons       = edupro_get_course_lessons( $course_id );
	$current_index = array_search( $current_lesson_id, $lessons, true );

	if ( false === $current_index || $current_index <= 0 ) {
		return 0;
	}

	return (int) $lessons[ $current_index - 1 ];
}

/**
 * Track the last viewed lesson when an enrolled student opens a lesson page.
 */
function edupro_track_last_viewed_lesson() {
	if ( ! is_user_logged_in() || ! is_singular( 'lms_lesson' ) ) {
		return;
	}

	$lesson_id = get_queried_object_id();
	$course_id = absint( get_post_meta( $lesson_id, '_edupro_course_id', true ) );
	$user_id   = get_current_user_id();

	if ( $course_id && function_exists( 'edupro_check_student_access' ) && edupro_check_student_access( $course_id, $user_id ) ) {
		update_user_meta( $user_id, '_edupro_last_lesson_' . $course_id, $lesson_id );
	}
}
add_action( 'template_redirect', 'edupro_track_last_viewed_lesson' );

// ─────────────────────────────────────────────────────────────────────────────
// 7. AJAX ENROLLMENT HANDLER (FREE COURSES)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX handler for free course enrollment.
 * Action: `edupro_enroll` (both logged-in and logged-out requests).
 */
function edupro_enroll_ajax_handler() {
	// Verify nonce (accepts either 'nonce' or 'security').
	$nonce_key = isset( $_POST['nonce'] ) ? 'nonce' : 'security';
	if ( ! check_ajax_referer( 'edupro_nonce', $nonce_key, false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'edupro' ),
			)
		);
	}

	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	if ( ! $course_id ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid course ID.', 'edupro' ),
			)
		);
	}

	$course = get_post( $course_id );
	if ( ! $course || 'lms_course' !== $course->post_type || 'publish' !== $course->post_status ) {
		wp_send_json_error(
			array(
				'message' => __( 'This course is not available for enrollment.', 'edupro' ),
			)
		);
	}

	// Require login before enrolling.
	if ( ! is_user_logged_in() ) {
		$login_page_id = (int) get_option( 'edupro_login_page_id' );
		$login_base    = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/student-login/' );
		$login_url     = add_query_arg( 'redirect_to', rawurlencode( get_permalink( $course_id ) ), $login_base );

		wp_send_json_error(
			array(
				'message'        => __( 'Please log in or create an account to enroll in this course.', 'edupro' ),
				'requires_login' => true,
				'login_url'      => $login_url,
				'redirect_url'   => $login_url,
			)
		);
	}

	$user_id         = get_current_user_id();
	$next_lesson_id  = edupro_get_next_lesson( $course_id, 0, $user_id );
	$destination_url = $next_lesson_id ? get_permalink( $next_lesson_id ) : get_permalink( $course_id );

	// Already enrolled?
	if ( function_exists( 'edupro_is_student_enrolled' ) && edupro_is_student_enrolled( $course_id, $user_id ) ) {
		wp_send_json_success(
			array(
				'message'          => __( 'You are already enrolled in this course! Taking you to your lessons…', 'edupro' ),
				'already_enrolled' => true,
				'course_id'        => $course_id,
				'first_lesson_url' => $destination_url,
				'redirect_url'     => $destination_url,
			)
		);
	}

	// Paid courses must go through checkout, not free AJAX enrollment.
	if ( ! edupro_is_course_free( $course_id ) ) {
		$checkout_page_id = (int) get_option( 'edupro_checkout_page_id' );
		$checkout_base    = $checkout_page_id ? get_permalink( $checkout_page_id ) : home_url( '/checkout/' );
		$checkout_url     = add_query_arg( 'course_id', $course_id, $checkout_base );

		wp_send_json_error(
			array(
				'message'          => __( 'This is a paid course. Redirecting to checkout…', 'edupro' ),
				'requires_payment' => true,
				'checkout_url'     => $checkout_url,
				'redirect_url'     => $checkout_url,
			)
		);
	}

	// Check enrollment limit before calling enroll so we can return a clear error.
	$enrollment_limit = absint( get_post_meta( $course_id, '_edupro_enrollment_limit', true ) );
	if ( $enrollment_limit > 0 && count( edupro_get_enrolled_students( $course_id ) ) >= $enrollment_limit ) {
		wp_send_json_error(
			array(
				'message' => __( 'Sorry, this course has reached its maximum enrollment limit.', 'edupro' ),
			)
		);
	}

	// Enroll the student.
	$enrolled = edupro_enroll_student( $course_id, $user_id );

	if ( ! $enrolled ) {
		wp_send_json_error(
			array(
				'message' => __( 'Could not complete enrollment. Please try again.', 'edupro' ),
			)
		);
	}

	$enrolled_count = function_exists( 'edupro_get_enrolled_count' )
		? edupro_get_enrolled_count( $course_id )
		: count( edupro_get_enrolled_students( $course_id ) );

	wp_send_json_success(
		array(
			'message'          => __( 'Successfully enrolled! Starting your course…', 'edupro' ),
			'already_enrolled' => false,
			'course_id'        => $course_id,
			'enrolled_count'   => $enrolled_count,
			'first_lesson_url' => $destination_url,
			'redirect_url'     => $destination_url,
		)
	);
}
add_action( 'wp_ajax_edupro_enroll', 'edupro_enroll_ajax_handler' );
add_action( 'wp_ajax_nopriv_edupro_enroll', 'edupro_enroll_ajax_handler' );
