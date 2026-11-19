<?php
/**
 * Template part for the Full-Screen EduPro Lesson Player.
 *
 * Rendered inside single-lms_lesson.php.
 * Layout:
 *  - Top bar: Back to course, Lesson title, Course progress bar, Sidebar/Panel toggles
 *  - Left dark sidebar: Collapsible course curriculum list with completion states & active highlight
 *  - Main stage: Responsive Video Player (YouTube / Vimeo / Self-hosted HTML5 with custom controls),
 *                PDF Viewer iframe, Text/Article lesson content, or Quiz container
 *  - Bottom action bar: Previous Lesson, Mark Complete (AJAX), Next Lesson
 *  - Right panel: Student Notes (persisted per lesson) & Downloadable Resources
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$lesson_id      = get_the_ID();
$course_id      = (int) get_post_meta( $lesson_id, '_edupro_course_id', true );
$lesson_type    = get_post_meta( $lesson_id, '_edupro_lesson_type', true ) ?: 'video';
$video_url      = get_post_meta( $lesson_id, '_edupro_video_url', true );
$video_duration = (float) get_post_meta( $lesson_id, '_edupro_video_duration', true );
$is_preview     = ( '1' === (string) get_post_meta( $lesson_id, '_edupro_is_preview', true ) );

$user_id        = get_current_user_id();
$is_logged_in   = is_user_logged_in();
$has_access     = $is_preview || ( $course_id && function_exists( 'edupro_check_student_access' ) && edupro_check_student_access( $course_id, $user_id ) ) || current_user_can( 'manage_options' );

$course_title   = $course_id ? get_the_title( $course_id ) : __( 'Course', 'edupro' );
$course_url     = $course_id ? get_permalink( $course_id ) : ( get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' ) );
$dashboard_id   = (int) get_option( 'edupro_dashboard_page_id' );
$dashboard_url  = $dashboard_id ? get_permalink( $dashboard_id ) : home_url( '/dashboard/' );

// Fetch all published lessons belonging to this course ordered by _edupro_lesson_order.
$course_lessons = array();
if ( $course_id ) {
    $course_lessons = get_posts( array(
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
}

// Compute prev/next lesson & completion stats.
$prev_lesson_id  = 0;
$next_lesson_id  = 0;
$completed_count = 0;
$total_lessons   = count( $course_lessons );
$current_index   = 0;

foreach ( $course_lessons as $idx => $l_item ) {
    if ( $is_logged_in && function_exists( 'edupro_is_lesson_complete' ) && edupro_is_lesson_complete( $l_item->ID, $user_id ) ) {
        $completed_count++;
    }
    if ( (int) $l_item->ID === (int) $lesson_id ) {
        $current_index = $idx;
        if ( $idx > 0 ) {
            $prev_lesson_id = $course_lessons[ $idx - 1 ]->ID;
        }
        if ( $idx < $total_lessons - 1 ) {
            $next_lesson_id = $course_lessons[ $idx + 1 ]->ID;
        }
    }
}

$is_current_complete = ( $is_logged_in && function_exists( 'edupro_is_lesson_complete' ) && edupro_is_lesson_complete( $lesson_id, $user_id ) );

if ( $course_id && $is_logged_in && function_exists( 'edupro_get_course_progress' ) ) {
    $progress_pct = (int) edupro_get_course_progress( $course_id, $user_id );
} else {
    $progress_pct = $total_lessons > 0 ? (int) round( ( $completed_count / $total_lessons ) * 100 ) : 0;
}

// Determine video provider (YouTube, Vimeo, or HTML5 self-hosted).
$video_provider = '';
$video_embed_id = '';
if ( 'video' === $lesson_type && ! empty( $video_url ) ) {
    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $video_url, $yt_m ) ) {
        $video_provider = 'youtube';
        $video_embed_id = $yt_m[1];
    } elseif ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $vm_m ) ) {
        $video_provider = 'vimeo';
        $video_embed_id = $vm_m[1];
    } else {
        $video_provider = 'html5';
    }
}

// Optional lesson resources meta (array of ['title' => '', 'url' => ''] or fallback).
$lesson_resources = get_post_meta( $lesson_id, '_edupro_lesson_resources', true );
if ( ! is_array( $lesson_resources ) ) {
    $lesson_resources = array();
}
?>

<div
    class="edupro-player-shell lesson-player"
    id="edupro-lesson-player"
    data-lesson-id="<?php echo absint( $lesson_id ); ?>"
    data-course-id="<?php echo absint( $course_id ); ?>"
    data-next-url="<?php echo $next_lesson_id ? esc_url( get_permalink( $next_lesson_id ) ) : ''; ?>"
    data-completed="<?php echo $is_current_complete ? '1' : '0'; ?>"
>
    <!-- =====================================================================
         TOP PROGRESS & NAVIGATION BAR
         ===================================================================== -->
    <header class="edupro-player-topbar">
        <div class="edupro-player-topbar__left">
            <button
                type="button"
                id="edupro-player-sidebar-toggle"
                class="edupro-player-icon-btn"
                aria-label="<?php esc_attr_e( 'Toggle curriculum sidebar', 'edupro' ); ?>"
                aria-expanded="true"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <a href="<?php echo esc_url( $course_url ); ?>" class="edupro-player-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                <span><?php echo esc_html( $course_title ); ?></span>
            </a>
        </div>

        <div class="edupro-player-topbar__center">
            <div class="edupro-player-progress-wrap">
                <span class="edupro-player-progress-label">
                    <?php
                    printf(
                        /* translators: 1: completed lessons, 2: total lessons */
                        esc_html__( '%1$d / %2$d Completed', 'edupro' ),
                        absint( $completed_count ),
                        absint( max( 1, $total_lessons ) )
                    );
                    ?>
                    (<strong id="edupro-player-progress-pct"><?php echo absint( $progress_pct ); ?>%</strong>)
                </span>
                <div class="edupro-player-progress-bar" role="progressbar" aria-valuenow="<?php echo absint( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="edupro-player-progress-bar__fill" id="edupro-player-progress-fill" style="width:<?php echo absint( $progress_pct ); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="edupro-player-topbar__right">
            <button
                type="button"
                id="edupro-player-panel-toggle"
                class="edupro-player-icon-btn"
                aria-label="<?php esc_attr_e( 'Toggle Notes & Resources panel', 'edupro' ); ?>"
                aria-expanded="true"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span><?php esc_html_e( 'Notes', 'edupro' ); ?></span>
            </button>
            <a href="<?php echo esc_url( $dashboard_url ); ?>" class="edupro-player-dash-link">
                <?php esc_html_e( 'Dashboard', 'edupro' ); ?>
            </a>
        </div>
    </header>

    <!-- =====================================================================
         3-PANE WORKSPACE: DARK LEFT SIDEBAR | MAIN STAGE | RIGHT NOTES PANEL
         ===================================================================== -->
    <div class="edupro-player-workspace">

        <!-- LEFT DARK SIDEBAR: COURSE CURRICULUM -->
        <aside id="edupro-player-sidebar" class="edupro-player-sidebar" aria-label="<?php esc_attr_e( 'Course Curriculum', 'edupro' ); ?>">
            <div class="edupro-player-sidebar__head">
                <h2 class="edupro-player-sidebar__title"><?php esc_html_e( 'Course Content', 'edupro' ); ?></h2>
                <span class="edupro-player-sidebar__meta">
                    <?php
                    printf(
                        esc_html( _n( '%d Lesson', '%d Lessons', $total_lessons, 'edupro' ) ),
                        absint( $total_lessons )
                    );
                    ?>
                </span>
            </div>

            <div class="edupro-player-curriculum">
                <?php if ( ! empty( $course_lessons ) ) : ?>
                    <ul class="edupro-player-lesson-list">
                        <?php foreach ( $course_lessons as $idx => $item ) : ?>
                            <?php
                            $item_id        = (int) $item->ID;
                            $is_active      = ( $item_id === (int) $lesson_id );
                            $item_type      = get_post_meta( $item_id, '_edupro_lesson_type', true ) ?: 'video';
                            $item_dur       = (float) get_post_meta( $item_id, '_edupro_video_duration', true );
                            $item_preview   = ( '1' === (string) get_post_meta( $item_id, '_edupro_is_preview', true ) );
                            $item_completed = ( $is_logged_in && function_exists( 'edupro_is_lesson_complete' ) && edupro_is_lesson_complete( $item_id, $user_id ) );
                            $item_unlocked  = $has_access || $item_preview;
                            ?>
                            <li class="edupro-player-lesson-item <?php echo $is_active ? 'is-active' : ''; ?> <?php echo $item_completed ? 'is-completed' : ''; ?>" data-lesson-id="<?php echo absint( $item_id ); ?>">
                                <?php if ( $item_unlocked ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( $item_id ) ); ?>" class="edupro-player-lesson-link" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <?php else : ?>
                                    <span class="edupro-player-lesson-link is-locked">
                                <?php endif; ?>
                                        <span class="edupro-player-lesson-status" aria-hidden="true">
                                            <?php
                                            if ( $item_completed ) {
                                                echo '✓';
                                            } elseif ( ! $item_unlocked ) {
                                                echo '🔒';
                                            } else {
                                                echo esc_html( $idx + 1 );
                                            }
                                            ?>
                                        </span>
                                        <span class="edupro-player-lesson-info">
                                            <span class="edupro-player-lesson-name"><?php echo esc_html( $item->post_title ); ?></span>
                                            <span class="edupro-player-lesson-sub">
                                                <?php echo esc_html( ucfirst( $item_type ) ); ?>
                                                <?php if ( $item_dur > 0 ) : ?>
                                                    &bull; <?php echo esc_html( function_exists( 'edupro_get_duration_label' ) ? edupro_get_duration_label( $item_dur ) : $item_dur . ' min' ); ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                <?php if ( $item_unlocked ) : ?>
                                    </a>
                                <?php else : ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="edupro-player-sidebar__empty"><?php esc_html_e( 'No lessons linked to this course yet.', 'edupro' ); ?></p>
                <?php endif; ?>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main id="primary" class="edupro-player-main">
            <?php if ( ! $has_access ) : ?>
                <!-- Locked State (mirrors student-auth lock screen) -->
                <div class="edupro-player-locked">
                    <?php the_content(); ?>
                </div>
            <?php else : ?>

                <!-- MEDIA STAGE (Video / PDF / Quiz) -->
                <?php if ( 'video' === $lesson_type && ! empty( $video_url ) ) : ?>
                    <div class="edupro-player-stage" data-provider="<?php echo esc_attr( $video_provider ); ?>" data-video-id="<?php echo esc_attr( $video_embed_id ); ?>">
                        <div class="edupro-video-container" id="edupro-video-player">
                            <?php if ( 'youtube' === $video_provider ) : ?>
                                <iframe
                                    id="edupro-yt-iframe"
                                    src="<?php echo esc_url( 'https://www.youtube.com/embed/' . $video_embed_id . '?enablejsapi=1&rel=0&modestbranding=1' ); ?>"
                                    title="<?php echo esc_attr( get_the_title() ); ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                    allowfullscreen
                                ></iframe>
                            <?php elseif ( 'vimeo' === $video_provider ) : ?>
                                <iframe
                                    id="edupro-vimeo-iframe"
                                    src="<?php echo esc_url( 'https://player.vimeo.com/video/' . $video_embed_id . '?dnt=1' ); ?>"
                                    title="<?php echo esc_attr( get_the_title() ); ?>"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            <?php else : ?>
                                <video
                                    id="edupro-html5-video"
                                    class="edupro-html5-video"
                                    src="<?php echo esc_url( $video_url ); ?>"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    <?php echo has_post_thumbnail() ? 'poster="' . esc_url( get_the_post_thumbnail_url( $lesson_id, 'hero-thumb' ) ) . '"' : ''; ?>
                                >
                                    <?php esc_html_e( 'Your browser does not support HTML5 video playback.', 'edupro' ); ?>
                                </video>

                                <!-- Custom Video Controls Overlay (enhanced by assets/js/course-player.js) -->
                                <div class="edupro-video-controls" id="edupro-video-controls" aria-label="<?php esc_attr_e( 'Video Controls', 'edupro' ); ?>">
                                    <button type="button" class="edupro-vc-btn" data-vc="play" aria-label="<?php esc_attr_e( 'Play / Pause', 'edupro' ); ?>">▶</button>
                                    <div class="edupro-vc-progress" data-vc="seek">
                                        <div class="edupro-vc-progress__fill" data-vc="progress-fill"></div>
                                    </div>
                                    <span class="edupro-vc-time" data-vc="time">0:00 / 0:00</span>
                                    <select class="edupro-vc-speed" data-vc="speed" aria-label="<?php esc_attr_e( 'Playback speed', 'edupro' ); ?>">
                                        <option value="0.75">0.75x</option>
                                        <option value="1" selected>1x</option>
                                        <option value="1.25">1.25x</option>
                                        <option value="1.5">1.5x</option>
                                        <option value="2">2x</option>
                                    </select>
                                    <button type="button" class="edupro-vc-btn" data-vc="mute" aria-label="<?php esc_attr_e( 'Mute / Unmute', 'edupro' ); ?>">🔊</button>
                                    <button type="button" class="edupro-vc-btn" data-vc="fullscreen" aria-label="<?php esc_attr_e( 'Fullscreen', 'edupro' ); ?>">⛶</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ( 'pdf' === $lesson_type && ! empty( $video_url ) ) : ?>
                    <div class="edupro-pdf-stage">
                        <div class="edupro-pdf-toolbar">
                            <span>📎 <?php esc_html_e( 'PDF Study Material', 'edupro' ); ?></span>
                            <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer" download class="edupro-btn edupro-btn--outline btn-outline">
                                <?php esc_html_e( 'Download PDF', 'edupro' ); ?>
                            </a>
                        </div>
                        <iframe
                            src="<?php echo esc_url( $video_url ); ?>"
                            class="edupro-pdf-iframe"
                            title="<?php echo esc_attr( get_the_title() ); ?>"
                        ></iframe>
                    </div>
                <?php endif; ?>

                <!-- LESSON HEADER & ACTION BAR -->
                <div class="edupro-player-content-wrap">
                    <div class="edupro-player-lesson-header">
                        <div>
                            <span class="edupro-player-lesson-badge">
                                <?php
                                printf(
                                    /* translators: 1: current lesson index, 2: total lessons */
                                    esc_html__( 'Lesson %1$d of %2$d', 'edupro' ),
                                    absint( $current_index + 1 ),
                                    absint( max( 1, $total_lessons ) )
                                );
                                ?>
                            </span>
                            <h1 class="edupro-player-lesson-title"><?php the_title(); ?></h1>
                        </div>

                        <!-- Mark Complete Button (AJAX) -->
                        <?php if ( $is_logged_in ) : ?>
                            <button
                                type="button"
                                id="edupro-mark-complete-btn"
                                class="edupro-btn <?php echo $is_current_complete ? 'edupro-btn--success is-completed' : 'edupro-btn--primary btn-primary'; ?>"
                                data-lesson-id="<?php echo absint( $lesson_id ); ?>"
                                data-course-id="<?php echo absint( $course_id ); ?>"
                                <?php echo $is_current_complete ? 'disabled' : ''; ?>
                            >
                                <?php echo $is_current_complete ? esc_html__( '✓ Lesson Completed', 'edupro' ) : esc_html__( 'Mark as Complete', 'edupro' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Lesson Written Content / Transcript -->
                    <div class="edupro-player-article entry-content edupro-prose">
                        <?php the_content(); ?>
                    </div>

                    <!-- Previous / Next Lesson Navigation -->
                    <nav class="edupro-player-nav" aria-label="<?php esc_attr_e( 'Lesson Navigation', 'edupro' ); ?>">
                        <?php if ( $prev_lesson_id ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $prev_lesson_id ) ); ?>" class="edupro-btn edupro-btn--outline btn-outline edupro-player-nav__prev">
                                &larr; <?php esc_html_e( 'Previous Lesson', 'edupro' ); ?>
                            </a>
                        <?php else : ?>
                            <span></span>
                        <?php endif; ?>

                        <?php if ( $next_lesson_id ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $next_lesson_id ) ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-player-nav__next">
                                <?php esc_html_e( 'Next Lesson', 'edupro' ); ?> &rarr;
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $course_url ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-player-nav__next">
                                <?php esc_html_e( 'Back to Course Overview', 'edupro' ); ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

            <?php endif; ?>
        </main>

        <!-- RIGHT PANEL: NOTES & RESOURCES -->
        <aside id="edupro-player-right-panel" class="edupro-player-panel" aria-label="<?php esc_attr_e( 'Lesson Notes and Resources', 'edupro' ); ?>">
            <div class="edupro-player-panel__tabs" role="tablist">
                <button type="button" class="edupro-player-panel__tab is-active" role="tab" aria-selected="true" data-panel-tab="notes">
                    <?php esc_html_e( 'Notes', 'edupro' ); ?>
                </button>
                <button type="button" class="edupro-player-panel__tab" role="tab" aria-selected="false" data-panel-tab="resources">
                    <?php esc_html_e( 'Resources', 'edupro' ); ?>
                </button>
            </div>

            <!-- Notes Tab -->
            <div class="edupro-player-panel__body is-active" data-panel-content="notes" role="tabpanel">
                <label for="edupro-lesson-notes" class="edupro-notes-label">
                    <?php esc_html_e( 'Personal Lesson Notes', 'edupro' ); ?>
                </label>
                <textarea
                    id="edupro-lesson-notes"
                    class="edupro-notes-textarea"
                    rows="12"
                    data-lesson-id="<?php echo absint( $lesson_id ); ?>"
                    placeholder="<?php esc_attr_e( 'Jot down key takeaways, timestamps, or questions for this lesson… (auto-saved)', 'edupro' ); ?>"
                ></textarea>
                <div class="edupro-notes-actions">
                    <span id="edupro-notes-status" class="edupro-notes-status"><?php esc_html_e( 'Auto-saves locally', 'edupro' ); ?></span>
                    <button type="button" id="edupro-notes-clear" class="edupro-btn edupro-btn--ghost">
                        <?php esc_html_e( 'Clear', 'edupro' ); ?>
                    </button>
                </div>
            </div>

            <!-- Resources Tab -->
            <div class="edupro-player-panel__body" data-panel-content="resources" role="tabpanel" hidden>
                <h3 class="edupro-resources-heading"><?php esc_html_e( 'Downloadable Resources', 'edupro' ); ?></h3>
                <?php if ( ! empty( $lesson_resources ) ) : ?>
                    <ul class="edupro-resources-list">
                        <?php foreach ( $lesson_resources as $res ) : ?>
                            <?php if ( ! empty( $res['url'] ) ) : ?>
                                <li>
                                    <a href="<?php echo esc_url( $res['url'] ); ?>" target="_blank" rel="noopener noreferrer" download>
                                        <span>📎 <?php echo esc_html( ! empty( $res['title'] ) ? $res['title'] : basename( $res['url'] ) ); ?></span>
                                        <span class="edupro-resources-dl">↓</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ( 'pdf' === $lesson_type && ! empty( $video_url ) ) : ?>
                    <ul class="edupro-resources-list">
                        <li>
                            <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer" download>
                                <span>📎 <?php echo esc_html( get_the_title() ); ?> (PDF)</span>
                                <span class="edupro-resources-dl">↓</span>
                            </a>
                        </li>
                    </ul>
                <?php else : ?>
                    <p class="edupro-resources-empty">
                        <?php esc_html_e( 'No downloadable attachments for this lesson. Check the lesson notes above or return to the course page for course-wide materials.', 'edupro' ); ?>
                    </p>
                <?php endif; ?>

                <div class="edupro-resources-help">
                    <h4><?php esc_html_e( 'Keyboard Shortcuts', 'edupro' ); ?></h4>
                    <ul>
                        <li><kbd>Space</kbd> <?php esc_html_e( 'Play / Pause video', 'edupro' ); ?></li>
                        <li><kbd>F</kbd> <?php esc_html_e( 'Toggle Fullscreen', 'edupro' ); ?></li>
                        <li><kbd>←</kbd> / <kbd>→</kbd> <?php esc_html_e( 'Seek ±5 seconds', 'edupro' ); ?></li>
                    </ul>
                </div>
            </div>
        </aside>

    </div><!-- .edupro-player-workspace -->
</div>
