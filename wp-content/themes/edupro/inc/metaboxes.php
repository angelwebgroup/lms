<?php
/**
 * EduPro LMS — Admin Meta Boxes
 *
 * Registers, renders, styles, and saves comprehensive admin meta boxes for:
 *  - lms_course: Course Pricing, Course Details, Course Curriculum, SEO & Sharing
 *  - lms_lesson: Lesson Details (with admin video player preview)
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
   1. ENQUEUE ADMIN SCRIPTS (wp.media, wp.editor, jQuery UI Sortable)
   ========================================================================= */

add_action( 'admin_enqueue_scripts', 'edupro_metabox_admin_enqueue' );
function edupro_metabox_admin_enqueue( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, array( 'lms_course', 'lms_lesson' ), true ) ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_editor();
    wp_enqueue_script( 'jquery-ui-sortable' );
}

/* =========================================================================
   2. REGISTER ALL META BOXES
   ========================================================================= */

add_action( 'add_meta_boxes', 'edupro_register_meta_boxes' );
function edupro_register_meta_boxes() {

    /* ---------- lms_course meta boxes ---------- */
    add_meta_box(
        'edupro_course_pricing',
        __( 'Course Pricing', 'edupro' ),
        'edupro_render_pricing_metabox',
        'lms_course',
        'normal',
        'high'
    );

    add_meta_box(
        'edupro_course_details',
        __( 'Course Details', 'edupro' ),
        'edupro_render_details_metabox',
        'lms_course',
        'normal',
        'high'
    );

    add_meta_box(
        'edupro_curriculum',
        __( 'Course Curriculum', 'edupro' ),
        'edupro_render_curriculum_metabox',
        'lms_course',
        'normal',
        'default'
    );

    add_meta_box(
        'edupro_course_page_extras',
        __( 'Course Page Extras, Gallery & FAQs', 'edupro' ),
        'edupro_render_course_page_extras_metabox',
        'lms_course',
        'normal',
        'default'
    );

    add_meta_box(
        'edupro_seo',
        __( 'SEO & Social Sharing', 'edupro' ),
        'edupro_render_seo_metabox',
        'lms_course',
        'normal',
        'low'
    );

    /* ---------- lms_lesson meta boxes ---------- */
    add_meta_box(
        'edupro_lesson_details',
        __( 'Lesson Details', 'edupro' ),
        'edupro_render_lesson_details_metabox',
        'lms_lesson',
        'normal',
        'high'
    );
}

/* =========================================================================
   3. PREMIUM INLINE ADMIN CSS
   ========================================================================= */

add_action( 'admin_head', 'edupro_metabox_admin_styles' );
function edupro_metabox_admin_styles() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, array( 'lms_course', 'lms_lesson' ), true ) ) {
        return;
    }
    ?>
    <style>
        /* ---- Postbox Card Polish ---- */
        #edupro_course_pricing.postbox,
        #edupro_course_details.postbox,
        #edupro_curriculum.postbox,
        #edupro_course_page_extras.postbox,
        #edupro_seo.postbox,
        #edupro_lesson_details.postbox {
            border: 1px solid #e5e7eb;
            border-top: 3px solid #e31b23;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(17, 24, 39, 0.05);
            overflow: hidden;
            margin-bottom: 20px;
            background: #fff;
        }
        #edupro_course_pricing .postbox-header,
        #edupro_course_details .postbox-header,
        #edupro_curriculum .postbox-header,
        #edupro_course_page_extras .postbox-header,
        #edupro_seo .postbox-header,
        #edupro_lesson_details .postbox-header {
            background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
            border-bottom: 1px solid #f0f0f2;
            padding: 4px 10px;
        }
        #edupro_course_pricing .postbox-header h2,
        #edupro_course_details .postbox-header h2,
        #edupro_curriculum .postbox-header h2,
        #edupro_course_page_extras .postbox-header h2,
        #edupro_seo .postbox-header h2,
        #edupro_lesson_details .postbox-header h2 {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.01em;
        }
        #edupro_course_pricing .inside,
        #edupro_course_details .inside,
        #edupro_curriculum .inside,
        #edupro_course_page_extras .inside,
        #edupro_seo .inside,
        #edupro_lesson_details .inside {
            padding: 18px 20px 20px;
            margin: 0;
        }

        /* ---- EduPro Meta Box Base ---- */
        .edupro-mb {
            font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
            color: #1f2937;
        }
        .edupro-mb * { box-sizing: border-box; }
        .edupro-mb-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }
        .edupro-mb-col { flex: 1 1 210px; min-width: 0; }
        .edupro-mb-col.full { flex: 1 1 100%; }
        .edupro-mb-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .edupro-mb input[type="text"],
        .edupro-mb input[type="number"],
        .edupro-mb input[type="url"],
        .edupro-mb input[type="date"],
        .edupro-mb select,
        .edupro-mb textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 7px;
            font-size: 13.5px;
            color: #111827;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            line-height: 1.45;
        }
        .edupro-mb input[type="text"]:focus,
        .edupro-mb input[type="number"]:focus,
        .edupro-mb input[type="url"]:focus,
        .edupro-mb input[type="date"]:focus,
        .edupro-mb select:focus,
        .edupro-mb textarea:focus {
            border-color: #e31b23;
            outline: none;
            box-shadow: 0 0 0 3px rgba(227, 27, 35, 0.12);
        }
        .edupro-mb textarea { min-height: 86px; resize: vertical; }
        .edupro-hint {
            font-size: 11.5px;
            color: #6b7280;
            margin: 5px 0 0;
        }
        .edupro-section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #e31b23;
            border-bottom: 1.5px solid #fee2e2;
            padding-bottom: 6px;
            margin: 0 0 14px;
        }
        .edupro-divider {
            border: none;
            border-top: 1px dashed #e5e7eb;
            margin: 18px 0;
        }
        .edupro-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #fef2f2;
            color: #e31b23;
            border: 1px solid #fecaca;
        }
        .edupro-badge--green {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        /* ---- Toggle Switch ---- */
        .edupro-toggle-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .edupro-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edupro-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
            margin: 0;
        }
        .edupro-toggle input { opacity: 0; width: 0; height: 0; }
        .edupro-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 999px;
            transition: background 0.25s;
            cursor: pointer;
        }
        .edupro-slider::before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: transform 0.25s;
        }
        .edupro-toggle input:checked + .edupro-slider { background: #e31b23; }
        .edupro-toggle input:checked + .edupro-slider::before { transform: translateX(20px); }
        .edupro-toggle-title { font-size: 13px; font-weight: 600; color: #111827; display: block; }
        .edupro-toggle-sub { font-size: 11.5px; color: #6b7280; display: block; }

        /* ---- Buttons ---- */
        .edupro-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all 0.18s ease;
            text-decoration: none;
            line-height: 1.2;
        }
        .edupro-btn .dashicons {
            font-size: 15px;
            width: 15px;
            height: 15px;
            line-height: 15px;
        }
        .edupro-btn-primary {
            background: #e31b23;
            color: #fff;
            border-color: #e31b23;
        }
        .edupro-btn-primary:hover { background: #c5161d; border-color: #c5161d; color: #fff; }
        .edupro-btn-outline {
            background: #fff;
            color: #e31b23;
            border-color: #e31b23;
        }
        .edupro-btn-outline:hover { background: #fef2f2; color: #c5161d; }
        .edupro-btn-ghost {
            background: #f3f4f6;
            color: #374151;
            border-color: #e5e7eb;
        }
        .edupro-btn-ghost:hover { background: #e5e7eb; color: #111827; }
        .edupro-btn-danger {
            background: #fff;
            color: #b91c1c;
            border-color: #fca5a5;
        }
        .edupro-btn-danger:hover { background: #fef2f2; border-color: #ef4444; }
        .edupro-btn-sm { padding: 6px 11px; font-size: 12px; }

        /* ---- Price Input with Prefix ---- */
        .edupro-input-group {
            display: flex;
            align-items: stretch;
        }
        .edupro-input-prefix {
            display: inline-flex;
            align-items: center;
            padding: 0 11px;
            background: #f3f4f6;
            border: 1.5px solid #d1d5db;
            border-right: none;
            border-radius: 7px 0 0 7px;
            font-weight: 700;
            color: #374151;
            font-size: 14px;
        }
        .edupro-input-group input {
            border-radius: 0 7px 7px 0 !important;
        }

        /* ---- What You'll Learn Repeater ---- */
        .edupro-repeater-list { list-style: none; margin: 0 0 10px; padding: 0; }
        .edupro-repeater-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            background: #f9fafb;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
        }
        .edupro-repeater-handle {
            cursor: grab;
            color: #9ca3af;
            display: inline-flex;
            align-items: center;
        }
        .edupro-repeater-handle:hover { color: #e31b23; }
        .edupro-repeater-item input { flex: 1; }
        .edupro-icon-btn {
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s, background 0.15s;
        }
        .edupro-icon-btn:hover { color: #e31b23; background: #fef2f2; }

        /* ---- Curriculum Builder ---- */
        .edupro-curriculum-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
            padding: 10px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .edupro-curriculum-stats {
            font-size: 12.5px;
            color: #4b5563;
            font-weight: 600;
        }
        .edupro-curriculum-stats strong { color: #e31b23; }
        .edupro-section-card {
            border: 1.5px solid #e5e7eb;
            border-radius: 9px;
            margin-bottom: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
        }
        .edupro-section-card.ui-sortable-helper {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            border-color: #e31b23;
        }
        .edupro-section-placeholder {
            border: 2px dashed #fca5a5;
            background: #fef2f2;
            border-radius: 9px;
            height: 60px;
            margin-bottom: 14px;
        }
        .edupro-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f9fafb;
            padding: 10px 14px;
            border-bottom: 1px solid #e5e7eb;
        }
        .edupro-drag-handle {
            cursor: grab;
            color: #9ca3af;
            display: inline-flex;
            align-items: center;
            padding: 2px;
        }
        .edupro-drag-handle:hover { color: #e31b23; }
        .edupro-section-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e31b23;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 5px;
            white-space: nowrap;
        }
        .edupro-section-title-input {
            flex: 1;
            font-weight: 600 !important;
        }
        .edupro-section-body { padding: 14px; }
        .edupro-section-card.is-collapsed .edupro-section-body { display: none; }
        .edupro-section-desc-wrap {
            margin-bottom: 14px;
            background: #fcfcfd;
            border: 1px solid #f0f0f2;
            border-radius: 7px;
            padding: 10px 12px;
        }
        .edupro-section-desc-wrap .wp-editor-wrap { margin-top: 4px; }
        .edupro-lessons-list {
            list-style: none;
            margin: 0 0 12px;
            padding: 0;
            min-height: 12px;
        }
        .edupro-lesson-card {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #e31b23;
            border-radius: 7px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #fafafa;
        }
        .edupro-lesson-placeholder {
            border: 2px dashed #fca5a5;
            background: #fef2f2;
            border-radius: 7px;
            height: 48px;
            margin-bottom: 8px;
        }
        .edupro-lesson-top {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .edupro-lesson-top .lesson-title { flex: 2 1 200px; }
        .edupro-lesson-top .lesson-type { flex: 0 0 135px; width: 135px; }
        .edupro-lesson-top .lesson-duration { flex: 0 0 95px; width: 95px; }
        .edupro-lesson-content-row {
            margin-top: 8px;
            padding-left: 26px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }
        .edupro-lesson-content-row .lesson-content-input,
        .edupro-lesson-content-row .lesson-content-textarea {
            flex: 1 1 260px;
        }
        .edupro-lesson-content-row .lesson-content-textarea { min-height: 64px; }
        .edupro-lesson-preview-flag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
            padding-top: 8px;
            white-space: nowrap;
            cursor: pointer;
        }

        /* ---- SEO & SERP Preview ---- */
        .edupro-char-count {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: none;
            letter-spacing: 0;
        }
        .edupro-char-count.is-warn { color: #d97706; }
        .edupro-char-count.is-over { color: #e31b23; }
        .edupro-serp-preview {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
            max-width: 620px;
        }
        .edupro-serp-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9ca3af;
            margin-bottom: 8px;
        }
        .edupro-serp-url { font-size: 12px; color: #006621; margin-bottom: 3px; word-break: break-all; }
        .edupro-serp-title { font-size: 18px; color: #1a0dab; font-weight: 500; margin-bottom: 4px; line-height: 1.25; }
        .edupro-serp-desc { font-size: 13px; color: #4d5156; line-height: 1.45; }
        .edupro-og-preview {
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
            max-width: 380px;
            background: #f9fafb;
        }
        .edupro-og-preview img { width: 100%; height: 200px; object-fit: cover; display: block; }

        /* ---- Lesson Type Pills & Video Preview ---- */
        .edupro-pill-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .edupro-type-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid #d1d5db;
            background: #fff;
            color: #374151;
            transition: all 0.18s ease;
        }
        .edupro-type-pill:hover { border-color: #e31b23; color: #e31b23; }
        .edupro-type-pill.is-active {
            border-color: #e31b23;
            background: #fef2f2;
            color: #e31b23;
            box-shadow: 0 2px 6px rgba(227, 27, 35, 0.14);
        }
        .edupro-type-pill input { display: none; }
        .edupro-video-preview {
            margin-top: 12px;
            border-radius: 9px;
            overflow: hidden;
            aspect-ratio: 16 / 9;
            background: #0f172a;
            max-width: 540px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        }
        .edupro-video-preview iframe,
        .edupro-video-preview video {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        .edupro-url-with-upload {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .edupro-url-with-upload input { flex: 1; }

        /* ---- Dual-Currency Regional Price Overrides Panel ---- */
        .edupro-regional-panel {
            border: 1.5px solid #e5e7eb;
            border-radius: 9px;
            margin: 6px 0 18px;
            background: #fff;
            overflow: hidden;
        }
        .edupro-regional-header {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 14px;
            background: #f9fafb;
            border: none;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: background 0.15s;
        }
        .edupro-regional-panel.is-collapsed .edupro-regional-header {
            border-bottom: none;
        }
        .edupro-regional-header:hover { background: #f3f4f6; }
        .edupro-regional-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: #111827;
        }
        .edupro-regional-header-left .dashicons {
            color: #e31b23;
            font-size: 16px;
            width: 16px;
            height: 16px;
            line-height: 16px;
        }
        .edupro-regional-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .edupro-regional-chevron {
            color: #6b7280;
            transition: transform 0.2s;
        }
        .edupro-regional-panel.is-collapsed .edupro-regional-chevron {
            transform: rotate(-90deg);
        }
        .edupro-regional-body {
            padding: 14px;
            background: #fcfcfd;
        }
        .edupro-regional-panel.is-collapsed .edupro-regional-body {
            display: none;
        }
        .edupro-regional-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }
        .edupro-regional-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #e31b23;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .edupro-regional-card-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 12px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #e5e7eb;
        }
        .edupro-regional-note {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            margin-top: 12px;
            padding: 9px 12px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 7px;
            font-size: 12px;
            color: #92400e;
        }
        .edupro-regional-note .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #d97706;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ---- Course Page Extras: Key Facts Grid, Gallery & FAQs ---- */
        .edupro-facts-2col {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-bottom: 6px;
        }
        @media (max-width: 782px) {
            .edupro-facts-2col { grid-template-columns: 1fr; }
        }
        .edupro-gallery-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 12px 0 6px;
            padding: 0;
            list-style: none;
        }
        .edupro-gallery-thumb {
            position: relative;
            width: 108px;
            height: 82px;
            border-radius: 8px;
            overflow: hidden;
            border: 1.5px solid #e5e7eb;
            background: #f3f4f6;
            cursor: grab;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }
        .edupro-gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .edupro-gallery-thumb-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: none;
            background: rgba(17, 24, 39, 0.78);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
            transition: background 0.15s;
        }
        .edupro-gallery-thumb-remove:hover { background: #e31b23; }
        .edupro-gallery-thumb-remove .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            line-height: 14px;
        }
        .edupro-gallery-empty {
            padding: 14px;
            border: 1.5px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            font-size: 12.5px;
            color: #6b7280;
            margin-top: 10px;
        }
        .edupro-faq-list {
            list-style: none;
            margin: 0 0 12px;
            padding: 0;
        }
        .edupro-faq-row {
            border: 1.5px solid #e5e7eb;
            border-left: 3px solid #e31b23;
            border-radius: 8px;
            background: #fafafa;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .edupro-faq-row-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .edupro-faq-row-head .edupro-faq-q { flex: 1; font-weight: 600; }
        .edupro-faq-row-body { padding-left: 26px; }
        .edupro-faq-row-body textarea { min-height: 68px; }
        .edupro-faq-placeholder {
            border: 2px dashed #fca5a5;
            background: #fef2f2;
            border-radius: 8px;
            height: 96px;
            margin-bottom: 10px;
        }
    </style>
    <?php
}

/* =========================================================================
   4. META BOX: COURSE PRICING (edupro_course_pricing)
   ========================================================================= */

function edupro_render_pricing_metabox( $post ) {
    wp_nonce_field( 'edupro_save_pricing', 'edupro_pricing_nonce' );

    $regular_price    = get_post_meta( $post->ID, '_edupro_regular_price', true );
    $sale_price       = get_post_meta( $post->ID, '_edupro_sale_price', true );
    $sale_end_date    = get_post_meta( $post->ID, '_edupro_sale_end_date', true );
    $is_free          = get_post_meta( $post->ID, '_edupro_is_free', true );
    $enrollment_limit = get_post_meta( $post->ID, '_edupro_enrollment_limit', true );

    // Regional price overrides (UK GBP & India INR)
    $regular_price_gbp = get_post_meta( $post->ID, '_edupro_regular_price_gbp', true );
    $sale_price_gbp    = get_post_meta( $post->ID, '_edupro_sale_price_gbp', true );
    $regular_price_inr = get_post_meta( $post->ID, '_edupro_regular_price_inr', true );
    $sale_price_inr    = get_post_meta( $post->ID, '_edupro_sale_price_inr', true );

    // Base currency from currency.php / options (fallback INR ₹)
    $base_currency = function_exists( 'edupro_get_default_currency' )
        ? strtoupper( (string) edupro_get_default_currency() )
        : strtoupper( (string) get_option( 'edupro_default_currency', 'INR' ) );
    if ( ! in_array( $base_currency, array( 'INR', 'GBP', 'USD' ), true ) ) {
        $base_currency = 'INR';
    }
    if ( function_exists( 'edupro_get_default_currency' ) && function_exists( 'edupro_get_currency_symbol' ) ) {
        $base_symbol = edupro_get_currency_symbol( $base_currency );
    } else {
        $symbol_map  = array( 'GBP' => '£', 'USD' => '$', 'INR' => '₹' );
        $base_symbol = isset( $symbol_map[ $base_currency ] ) ? $symbol_map[ $base_currency ] : '₹';
    }

    $has_gbp_override = ( '' !== (string) $regular_price_gbp || '' !== (string) $sale_price_gbp );
    $has_inr_override = ( '' !== (string) $regular_price_inr || '' !== (string) $sale_price_inr );
    $has_any_override = ( $has_gbp_override || $has_inr_override );

    $discount_pct = 0;
    if ( ! $is_free && (float) $regular_price > 0 && '' !== $sale_price && (float) $sale_price < (float) $regular_price ) {
        $discount_pct = (int) round( ( ( (float) $regular_price - (float) $sale_price ) / (float) $regular_price ) * 100 );
    }
    ?>
    <div class="edupro-mb">

        <div class="edupro-section-heading">
            <span>
                <?php
                printf(
                    /* translators: 1: currency symbol, 2: currency code */
                    esc_html__( 'Pricing & Enrollment Capacity (Base Currency: %1$s %2$s)', 'edupro' ),
                    esc_html( $base_symbol ),
                    esc_html( $base_currency )
                );
                ?>
            </span>
            <span id="edupro-discount-badge" class="edupro-badge" style="<?php echo $discount_pct > 0 ? '' : 'display:none;'; ?>">
                <?php echo esc_html( $discount_pct . '% OFF' ); ?>
            </span>
        </div>

        <!-- Is Free Course toggle -->
        <div class="edupro-toggle-card">
            <div class="edupro-toggle-wrap">
                <label class="edupro-toggle" for="_edupro_is_free">
                    <input type="checkbox" name="_edupro_is_free" id="_edupro_is_free" value="1" <?php checked( $is_free, '1' ); ?>>
                    <span class="edupro-slider"></span>
                </label>
                <div>
                    <span class="edupro-toggle-title"><?php esc_html_e( 'Is Free Course?', 'edupro' ); ?></span>
                    <span class="edupro-toggle-sub"><?php esc_html_e( 'Students can enroll immediately without checkout or payment.', 'edupro' ); ?></span>
                </div>
            </div>
            <span id="edupro-free-badge" class="edupro-badge edupro-badge--green" style="<?php echo '1' === (string) $is_free ? '' : 'display:none;'; ?>">
                <?php esc_html_e( 'FREE ACCESS', 'edupro' ); ?>
            </span>
        </div>

        <div id="edupro-price-fields" style="<?php echo '1' === (string) $is_free ? 'opacity:0.45;pointer-events:none;' : ''; ?>">
            <div class="edupro-mb-row">
                <div class="edupro-mb-col">
                    <label class="edupro-mb-label" for="_edupro_regular_price">
                        <span>
                            <?php
                            printf(
                                /* translators: 1: currency symbol, 2: currency code */
                                esc_html__( 'Regular Price (%1$s %2$s)', 'edupro' ),
                                esc_html( $base_symbol ),
                                esc_html( $base_currency )
                            );
                            ?>
                        </span>
                    </label>
                    <div class="edupro-input-group">
                        <span class="edupro-input-prefix"><?php echo esc_html( $base_symbol ); ?></span>
                        <input type="number" id="_edupro_regular_price" name="_edupro_regular_price"
                               value="<?php echo esc_attr( $regular_price ); ?>"
                               min="0" step="0.01" placeholder="<?php echo 'GBP' === $base_currency ? 'e.g. 49.00' : 'e.g. 2999'; ?>">
                    </div>
                    <p class="edupro-hint"><?php esc_html_e( 'Standard course MRP in base currency (shown struck-through when on sale).', 'edupro' ); ?></p>
                </div>

                <div class="edupro-mb-col">
                    <label class="edupro-mb-label" for="_edupro_sale_price">
                        <span>
                            <?php
                            printf(
                                /* translators: 1: currency symbol, 2: currency code */
                                esc_html__( 'Sale / Selling Price (%1$s %2$s)', 'edupro' ),
                                esc_html( $base_symbol ),
                                esc_html( $base_currency )
                            );
                            ?>
                        </span>
                    </label>
                    <div class="edupro-input-group">
                        <span class="edupro-input-prefix"><?php echo esc_html( $base_symbol ); ?></span>
                        <input type="number" id="_edupro_sale_price" name="_edupro_sale_price"
                               value="<?php echo esc_attr( $sale_price ); ?>"
                               min="0" step="0.01" placeholder="<?php echo 'GBP' === $base_currency ? 'e.g. 29.00' : 'e.g. 1499'; ?>">
                    </div>
                    <p class="edupro-hint"><?php esc_html_e( 'Discounted selling price. Leave empty to charge Regular Price.', 'edupro' ); ?></p>
                </div>

                <div class="edupro-mb-col">
                    <label class="edupro-mb-label" for="_edupro_sale_end_date">
                        <span><?php esc_html_e( 'Sale End Date', 'edupro' ); ?></span>
                    </label>
                    <input type="date" id="_edupro_sale_end_date" name="_edupro_sale_end_date"
                           value="<?php echo esc_attr( $sale_end_date ); ?>">
                    <p class="edupro-hint"><?php esc_html_e( 'Optional expiry date for the promotional sale price.', 'edupro' ); ?></p>
                </div>
            </div>

            <!-- Collapsible Regional Price Overrides Sub-Panel (UK GBP £ & India INR ₹) -->
            <div class="edupro-regional-panel <?php echo $has_any_override ? '' : 'is-collapsed'; ?>" id="edupro-regional-panel">
                <button type="button" class="edupro-regional-header" id="edupro-regional-toggle"
                        aria-expanded="<?php echo $has_any_override ? 'true' : 'false'; ?>">
                    <span class="edupro-regional-header-left">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <span><?php esc_html_e( 'Regional Price Overrides (Optional — UK £ & India ₹)', 'edupro' ); ?></span>
                    </span>
                    <span class="edupro-regional-header-right">
                        <span id="edupro-regional-status-badge" class="edupro-badge <?php echo $has_any_override ? '' : 'edupro-badge--green'; ?>">
                            <?php
                            if ( $has_gbp_override && $has_inr_override ) {
                                esc_html_e( '£ GBP & ₹ INR Custom', 'edupro' );
                            } elseif ( $has_gbp_override ) {
                                esc_html_e( '£ GBP Custom', 'edupro' );
                            } elseif ( $has_inr_override ) {
                                esc_html_e( '₹ INR Custom', 'edupro' );
                            } else {
                                esc_html_e( 'Auto-Convert', 'edupro' );
                            }
                            ?>
                        </span>
                        <span class="dashicons dashicons-arrow-down-alt2 edupro-regional-chevron"></span>
                    </span>
                </button>

                <div class="edupro-regional-body">
                    <div class="edupro-regional-grid">
                        <!-- UK GBP Card -->
                        <div class="edupro-regional-card">
                            <div class="edupro-regional-card-title">
                                <span><?php esc_html_e( 'United Kingdom — GBP (£)', 'edupro' ); ?></span>
                                <span class="edupro-badge">£ GBP</span>
                            </div>
                            <div class="edupro-mb-row" style="margin-bottom:0;">
                                <div class="edupro-mb-col" style="flex:1 1 110px;">
                                    <label class="edupro-mb-label" for="_edupro_regular_price_gbp">
                                        <span><?php esc_html_e( 'UK Regular Price (£)', 'edupro' ); ?></span>
                                    </label>
                                    <div class="edupro-input-group">
                                        <span class="edupro-input-prefix">£</span>
                                        <input type="number" id="_edupro_regular_price_gbp" name="_edupro_regular_price_gbp"
                                               class="edupro-regional-input"
                                               value="<?php echo esc_attr( $regular_price_gbp ); ?>"
                                               min="0" step="0.01" placeholder="Auto">
                                    </div>
                                </div>
                                <div class="edupro-mb-col" style="flex:1 1 110px;">
                                    <label class="edupro-mb-label" for="_edupro_sale_price_gbp">
                                        <span><?php esc_html_e( 'UK Sale Price (£)', 'edupro' ); ?></span>
                                    </label>
                                    <div class="edupro-input-group">
                                        <span class="edupro-input-prefix">£</span>
                                        <input type="number" id="_edupro_sale_price_gbp" name="_edupro_sale_price_gbp"
                                               class="edupro-regional-input"
                                               value="<?php echo esc_attr( $sale_price_gbp ); ?>"
                                               min="0" step="0.01" placeholder="Auto">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- India INR Card -->
                        <div class="edupro-regional-card">
                            <div class="edupro-regional-card-title">
                                <span><?php esc_html_e( 'India — INR (₹)', 'edupro' ); ?></span>
                                <span class="edupro-badge">₹ INR</span>
                            </div>
                            <div class="edupro-mb-row" style="margin-bottom:0;">
                                <div class="edupro-mb-col" style="flex:1 1 110px;">
                                    <label class="edupro-mb-label" for="_edupro_regular_price_inr">
                                        <span><?php esc_html_e( 'India Regular Price (₹)', 'edupro' ); ?></span>
                                    </label>
                                    <div class="edupro-input-group">
                                        <span class="edupro-input-prefix">₹</span>
                                        <input type="number" id="_edupro_regular_price_inr" name="_edupro_regular_price_inr"
                                               class="edupro-regional-input"
                                               value="<?php echo esc_attr( $regular_price_inr ); ?>"
                                               min="0" step="0.01" placeholder="Auto">
                                    </div>
                                </div>
                                <div class="edupro-mb-col" style="flex:1 1 110px;">
                                    <label class="edupro-mb-label" for="_edupro_sale_price_inr">
                                        <span><?php esc_html_e( 'India Sale Price (₹)', 'edupro' ); ?></span>
                                    </label>
                                    <div class="edupro-input-group">
                                        <span class="edupro-input-prefix">₹</span>
                                        <input type="number" id="_edupro_sale_price_inr" name="_edupro_sale_price_inr"
                                               class="edupro-regional-input"
                                               value="<?php echo esc_attr( $sale_price_inr ); ?>"
                                               min="0" step="0.01" placeholder="Auto">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edupro-regional-note">
                        <span class="dashicons dashicons-info-outline"></span>
                        <span><?php esc_html_e( 'Leave blank to auto-convert using the exchange rate configured in Settings → Payment Gateways.', 'edupro' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="edupro-mb-row" style="margin-bottom:0;">
            <div class="edupro-mb-col" style="max-width:300px;">
                <label class="edupro-mb-label" for="_edupro_enrollment_limit">
                    <span><?php esc_html_e( 'Max Students (Enrollment Limit)', 'edupro' ); ?></span>
                </label>
                <input type="number" id="_edupro_enrollment_limit" name="_edupro_enrollment_limit"
                       value="<?php echo esc_attr( $enrollment_limit ); ?>"
                       min="0" step="1" placeholder="0 = Unlimited">
                <p class="edupro-hint"><?php esc_html_e( 'Leave 0 or blank for unlimited student seats.', 'edupro' ); ?></p>
            </div>
        </div>

    </div>

    <script>
    (function(){
        var freeToggle    = document.getElementById('_edupro_is_free');
        var priceFields   = document.getElementById('edupro-price-fields');
        var freeBadge     = document.getElementById('edupro-free-badge');
        var regInput      = document.getElementById('_edupro_regular_price');
        var saleInput     = document.getElementById('_edupro_sale_price');
        var discountBadge = document.getElementById('edupro-discount-badge');
        var regPanel      = document.getElementById('edupro-regional-panel');
        var regToggle     = document.getElementById('edupro-regional-toggle');
        var regStatus     = document.getElementById('edupro-regional-status-badge');
        var gbpReg        = document.getElementById('_edupro_regular_price_gbp');
        var gbpSale       = document.getElementById('_edupro_sale_price_gbp');
        var inrReg        = document.getElementById('_edupro_regular_price_inr');
        var inrSale       = document.getElementById('_edupro_sale_price_inr');

        function updatePricingUI(){
            var isFree = freeToggle && freeToggle.checked;
            if(priceFields){
                priceFields.style.opacity       = isFree ? '0.45' : '1';
                priceFields.style.pointerEvents = isFree ? 'none' : '';
            }
            if(freeBadge){
                freeBadge.style.display = isFree ? '' : 'none';
            }
            if(discountBadge && regInput && saleInput){
                var reg  = parseFloat(regInput.value);
                var sale = parseFloat(saleInput.value);
                if(!isFree && reg > 0 && !isNaN(sale) && sale >= 0 && sale < reg){
                    var pct = Math.round(((reg - sale) / reg) * 100);
                    discountBadge.textContent = pct + '% OFF';
                    discountBadge.style.display = '';
                } else {
                    discountBadge.style.display = 'none';
                }
            }
        }

        function updateRegionalStatus(){
            if(!regStatus) return;
            var hasGbp = (gbpReg && gbpReg.value.trim() !== '') || (gbpSale && gbpSale.value.trim() !== '');
            var hasInr = (inrReg && inrReg.value.trim() !== '') || (inrSale && inrSale.value.trim() !== '');
            regStatus.classList.remove('edupro-badge--green');
            if(hasGbp && hasInr){
                regStatus.textContent = '£ GBP & ₹ INR Custom';
            } else if(hasGbp){
                regStatus.textContent = '£ GBP Custom';
            } else if(hasInr){
                regStatus.textContent = '₹ INR Custom';
            } else {
                regStatus.textContent = 'Auto-Convert';
                regStatus.classList.add('edupro-badge--green');
            }
        }

        if(regToggle && regPanel){
            regToggle.addEventListener('click', function(){
                var collapsed = regPanel.classList.toggle('is-collapsed');
                regToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        }

        [gbpReg, gbpSale, inrReg, inrSale].forEach(function(inp){
            if(inp) inp.addEventListener('input', updateRegionalStatus);
        });

        if(freeToggle) freeToggle.addEventListener('change', updatePricingUI);
        if(regInput)   regInput.addEventListener('input', updatePricingUI);
        if(saleInput)  saleInput.addEventListener('input', updatePricingUI);
    })();
    </script>
    <?php
}

/* =========================================================================
   5. META BOX: COURSE DETAILS (edupro_course_details)
   ========================================================================= */

function edupro_render_details_metabox( $post ) {
    wp_nonce_field( 'edupro_save_details', 'edupro_details_nonce' );

    $duration       = get_post_meta( $post->ID, '_edupro_duration', true );
    $level          = get_post_meta( $post->ID, '_edupro_course_level', true );
    $language       = get_post_meta( $post->ID, '_edupro_language', true );
    $requirements   = get_post_meta( $post->ID, '_edupro_requirements', true );
    $what_you_learn = get_post_meta( $post->ID, '_edupro_what_you_learn', true );
    $intro_video    = get_post_meta( $post->ID, '_edupro_course_intro_video', true );
    $certificate    = get_post_meta( $post->ID, '_edupro_certificate', true );

    // Normalize legacy lowercase level values
    $level_map = array(
        'beginner'     => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced'     => 'Advanced',
        'all'          => 'All Levels',
        'all levels'   => 'All Levels',
    );
    if ( isset( $level_map[ strtolower( (string) $level ) ] ) ) {
        $level = $level_map[ strtolower( (string) $level ) ];
    }

    // Parse "What You'll Learn" (supports both JSON array and newline-separated text)
    $bullets = array();
    if ( ! empty( $what_you_learn ) ) {
        $decoded = json_decode( $what_you_learn, true );
        if ( is_array( $decoded ) ) {
            $bullets = array_values( array_filter( array_map( 'trim', $decoded ), 'strlen' ) );
        } else {
            $lines   = preg_split( '/\r\n|\r|\n/', (string) $what_you_learn );
            $bullets = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
        }
    }
    $textarea_value = implode( "\n", $bullets );
    if ( empty( $bullets ) ) {
        $bullets = array( '' );
    }

    $duration_preview = '';
    if ( '' !== $duration && (int) $duration > 0 ) {
        $duration_preview = function_exists( 'edupro_get_duration_label' )
            ? edupro_get_duration_label( (int) $duration )
            : ( (int) $duration . ' min' );
    }
    ?>
    <div class="edupro-mb">

        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Course Specifications', 'edupro' ); ?></span>
            <span id="edupro-duration-badge" class="edupro-badge" style="<?php echo $duration_preview ? '' : 'display:none;'; ?>">
                <?php echo esc_html( $duration_preview ); ?>
            </span>
        </div>

        <div class="edupro-mb-row">
            <div class="edupro-mb-col">
                <label class="edupro-mb-label" for="_edupro_duration">
                    <span><?php esc_html_e( 'Duration (minutes)', 'edupro' ); ?></span>
                </label>
                <input type="number" id="_edupro_duration" name="_edupro_duration"
                       value="<?php echo esc_attr( $duration ); ?>"
                       min="0" step="1" placeholder="e.g. 480">
                <p class="edupro-hint"><?php esc_html_e( 'Total course runtime in minutes (e.g. 150 = 2 hr 30 min).', 'edupro' ); ?></p>
            </div>

            <div class="edupro-mb-col">
                <label class="edupro-mb-label" for="_edupro_course_level">
                    <span><?php esc_html_e( 'Course Level', 'edupro' ); ?></span>
                </label>
                <select id="_edupro_course_level" name="_edupro_course_level">
                    <?php
                    $levels = array(
                        ''             => __( '— Select Level —', 'edupro' ),
                        'Beginner'     => __( 'Beginner', 'edupro' ),
                        'Intermediate' => __( 'Intermediate', 'edupro' ),
                        'Advanced'     => __( 'Advanced', 'edupro' ),
                        'All Levels'   => __( 'All Levels', 'edupro' ),
                    );
                    foreach ( $levels as $val => $label ) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr( $val ),
                            selected( $level, $val, false ),
                            esc_html( $label )
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="edupro-mb-col">
                <label class="edupro-mb-label" for="_edupro_language">
                    <span><?php esc_html_e( 'Language', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_language" name="_edupro_language"
                       value="<?php echo esc_attr( $language ); ?>"
                       placeholder="e.g. English, Hindi">
            </div>
        </div>

        <hr class="edupro-divider">

        <!-- Requirements -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Requirements / Prerequisites', 'edupro' ); ?></span>
        </div>
        <div class="edupro-mb-row">
            <div class="edupro-mb-col full">
                <label class="edupro-mb-label" for="_edupro_requirements">
                    <span><?php esc_html_e( 'Requirements', 'edupro' ); ?></span>
                </label>
                <textarea id="_edupro_requirements" name="_edupro_requirements" rows="4"
                          placeholder="<?php esc_attr_e( 'Enter course prerequisites, one per line…', 'edupro' ); ?>"><?php echo esc_textarea( $requirements ); ?></textarea>
            </div>
        </div>

        <hr class="edupro-divider">

        <!-- What You'll Learn (Bullet-point Repeater + Synced Textarea) -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( "What You'll Learn (Learning Outcomes)", 'edupro' ); ?></span>
            <button type="button" class="edupro-btn edupro-btn-ghost edupro-btn-sm" id="edupro-toggle-learn-mode">
                <span class="dashicons dashicons-editor-ul"></span>
                <span id="edupro-learn-mode-label"><?php esc_html_e( 'Bulk Textarea Mode', 'edupro' ); ?></span>
            </button>
        </div>

        <div id="edupro-learn-repeater-wrap">
            <ul class="edupro-repeater-list" id="edupro-learn-list">
                <?php foreach ( $bullets as $bullet ) : ?>
                <li class="edupro-repeater-item">
                    <span class="edupro-repeater-handle" title="<?php esc_attr_e( 'Drag to reorder', 'edupro' ); ?>">
                        <span class="dashicons dashicons-menu"></span>
                    </span>
                    <input type="text" class="edupro-learn-bullet-input"
                           value="<?php echo esc_attr( $bullet ); ?>"
                           placeholder="<?php esc_attr_e( 'e.g. Build production-ready web apps from scratch', 'edupro' ); ?>">
                    <button type="button" class="edupro-icon-btn edupro-repeater-remove" title="<?php esc_attr_e( 'Remove bullet', 'edupro' ); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="edupro-btn edupro-btn-outline edupro-btn-sm" id="edupro-add-learn-item">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add Bullet Point', 'edupro' ); ?>
            </button>
        </div>

        <!-- Synced textarea (also toggleable for bulk editing) -->
        <div id="edupro-learn-textarea-wrap" style="display:none;margin-top:8px;">
            <textarea id="_edupro_what_you_learn" name="_edupro_what_you_learn" rows="5"
                      placeholder="<?php esc_attr_e( 'One learning outcome per line…', 'edupro' ); ?>"><?php echo esc_textarea( $textarea_value ); ?></textarea>
            <p class="edupro-hint"><?php esc_html_e( 'Enter one bullet point per line. Automatically syncs with the repeater editor.', 'edupro' ); ?></p>
        </div>

        <hr class="edupro-divider">

        <!-- Intro Video URL -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Course Intro Video', 'edupro' ); ?></span>
        </div>
        <div class="edupro-mb-row">
            <div class="edupro-mb-col full">
                <label class="edupro-mb-label" for="_edupro_course_intro_video">
                    <span><?php esc_html_e( 'Intro Video URL (YouTube / Vimeo / Self-Hosted MP4)', 'edupro' ); ?></span>
                </label>
                <div class="edupro-url-with-upload">
                    <input type="text" id="_edupro_course_intro_video" name="_edupro_course_intro_video"
                           value="<?php echo esc_attr( $intro_video ); ?>"
                           placeholder="https://www.youtube.com/watch?v=... or MP4 URL">
                    <button type="button" class="edupro-btn edupro-btn-ghost" id="edupro-intro-video-upload">
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php esc_html_e( 'Media Library', 'edupro' ); ?>
                    </button>
                </div>
                <p class="edupro-hint"><?php esc_html_e( 'Supports YouTube, Vimeo, or self-hosted MP4/WebM video files.', 'edupro' ); ?></p>
                <div id="edupro-intro-video-preview" style="<?php echo $intro_video ? '' : 'display:none;'; ?>">
                    <?php if ( $intro_video ) : ?>
                        <?php echo edupro_build_video_embed( $intro_video ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="edupro-divider">

        <!-- Provides Certificate -->
        <div class="edupro-toggle-card" style="margin-bottom:0;">
            <div class="edupro-toggle-wrap">
                <label class="edupro-toggle" for="_edupro_certificate">
                    <input type="checkbox" name="_edupro_certificate" id="_edupro_certificate" value="1" <?php checked( $certificate, '1' ); ?>>
                    <span class="edupro-slider"></span>
                </label>
                <div>
                    <span class="edupro-toggle-title"><?php esc_html_e( 'Provides Certificate?', 'edupro' ); ?></span>
                    <span class="edupro-toggle-sub"><?php esc_html_e( 'Award a verified Certificate of Completion when students finish 100% of lessons.', 'edupro' ); ?></span>
                </div>
            </div>
        </div>

    </div>

    <script>
    (function($){
        /* ---- Duration live label ---- */
        var durInput = document.getElementById('_edupro_duration');
        var durBadge = document.getElementById('edupro-duration-badge');
        if(durInput && durBadge){
            durInput.addEventListener('input', function(){
                var mins = parseInt(this.value, 10);
                if(!isNaN(mins) && mins > 0){
                    var h = Math.floor(mins / 60);
                    var m = mins % 60;
                    var text = h > 0 ? (m > 0 ? h + ' hr ' + m + ' min' : h + ' hr') : (m + ' min');
                    durBadge.textContent = text;
                    durBadge.style.display = '';
                } else {
                    durBadge.style.display = 'none';
                }
            });
        }

        /* ---- What You'll Learn repeater <-> textarea sync ---- */
        var list         = document.getElementById('edupro-learn-list');
        var addBtn       = document.getElementById('edupro-add-learn-item');
        var textarea     = document.getElementById('_edupro_what_you_learn');
        var repeaterWrap = document.getElementById('edupro-learn-repeater-wrap');
        var textareaWrap = document.getElementById('edupro-learn-textarea-wrap');
        var toggleBtn    = document.getElementById('edupro-toggle-learn-mode');
        var modeLabel    = document.getElementById('edupro-learn-mode-label');
        var bulkMode     = false;

        function syncRepeaterToTextarea(){
            var lines = [];
            list.querySelectorAll('.edupro-learn-bullet-input').forEach(function(inp){
                var v = inp.value.trim();
                if(v) lines.push(v);
            });
            textarea.value = lines.join('\n');
        }

        function createBulletItem(value){
            var li = document.createElement('li');
            li.className = 'edupro-repeater-item';
            li.innerHTML =
                '<span class="edupro-repeater-handle" title="Drag to reorder"><span class="dashicons dashicons-menu"></span></span>'
              + '<input type="text" class="edupro-learn-bullet-input" placeholder="<?php echo esc_js( __( 'e.g. Build production-ready web apps from scratch', 'edupro' ) ); ?>">'
              + '<button type="button" class="edupro-icon-btn edupro-repeater-remove" title="Remove bullet"><span class="dashicons dashicons-no-alt"></span></button>';
            li.querySelector('input').value = value || '';
            attachBulletEvents(li);
            return li;
        }

        function attachBulletEvents(li){
            li.querySelector('.edupro-repeater-remove').addEventListener('click', function(){
                if(list.children.length > 1){
                    li.remove();
                } else {
                    li.querySelector('input').value = '';
                }
                syncRepeaterToTextarea();
            });
            li.querySelector('input').addEventListener('input', syncRepeaterToTextarea);
        }

        function syncTextareaToRepeater(){
            var lines = textarea.value.split(/\r?\n/).map(function(s){ return s.trim(); }).filter(Boolean);
            list.innerHTML = '';
            if(!lines.length) lines = [''];
            lines.forEach(function(line){ list.appendChild(createBulletItem(line)); });
        }

        list.querySelectorAll('.edupro-repeater-item').forEach(attachBulletEvents);
        syncRepeaterToTextarea();

        if($.fn && $.fn.sortable){
            $(list).sortable({
                handle: '.edupro-repeater-handle',
                axis: 'y',
                stop: syncRepeaterToTextarea
            });
        }

        addBtn.addEventListener('click', function(){
            var li = createBulletItem('');
            list.appendChild(li);
            li.querySelector('input').focus();
        });

        toggleBtn.addEventListener('click', function(){
            bulkMode = !bulkMode;
            if(bulkMode){
                syncRepeaterToTextarea();
                repeaterWrap.style.display = 'none';
                textareaWrap.style.display = '';
                modeLabel.textContent = '<?php echo esc_js( __( 'Bullet Repeater Mode', 'edupro' ) ); ?>';
            } else {
                syncTextareaToRepeater();
                textareaWrap.style.display = 'none';
                repeaterWrap.style.display = '';
                modeLabel.textContent = '<?php echo esc_js( __( 'Bulk Textarea Mode', 'edupro' ) ); ?>';
            }
        });

        /* ---- Intro Video Preview & Media Uploader ---- */
        var videoInput   = document.getElementById('_edupro_course_intro_video');
        var videoPreview = document.getElementById('edupro-intro-video-preview');
        var uploadBtn    = document.getElementById('edupro-intro-video-upload');

        function renderVideoPreview(url){
            if(!videoPreview) return;
            var html = window.eduproBuildVideoEmbed ? window.eduproBuildVideoEmbed(url) : '';
            videoPreview.innerHTML = html;
            videoPreview.style.display = html ? '' : 'none';
        }

        var vTimer;
        if(videoInput){
            videoInput.addEventListener('input', function(){
                clearTimeout(vTimer);
                vTimer = setTimeout(function(){
                    renderVideoPreview(videoInput.value.trim());
                }, 450);
            });
        }

        if(uploadBtn && typeof wp !== 'undefined' && wp.media){
            var introFrame;
            uploadBtn.addEventListener('click', function(e){
                e.preventDefault();
                if(introFrame){ introFrame.open(); return; }
                introFrame = wp.media({
                    title: '<?php echo esc_js( __( 'Select Intro Video', 'edupro' ) ); ?>',
                    button: { text: '<?php echo esc_js( __( 'Use Video', 'edupro' ) ); ?>' },
                    multiple: false,
                    library: { type: 'video' }
                });
                introFrame.on('select', function(){
                    var att = introFrame.state().get('selection').first().toJSON();
                    videoInput.value = att.url;
                    renderVideoPreview(att.url);
                });
                introFrame.open();
            });
        }
    })(jQuery);
    </script>
    <?php
}

/* =========================================================================
   6. META BOX: COURSE CURRICULUM (edupro_curriculum)
   ========================================================================= */

function edupro_render_curriculum_metabox( $post ) {
    wp_nonce_field( 'edupro_save_curriculum', 'edupro_curriculum_nonce' );

    $curriculum_json = get_post_meta( $post->ID, '_edupro_curriculum', true );
    $curriculum      = array();
    if ( ! empty( $curriculum_json ) ) {
        $decoded = json_decode( $curriculum_json, true );
        if ( is_array( $decoded ) ) {
            $curriculum = $decoded;
        }
    }
    if ( empty( $curriculum ) ) {
        $curriculum = array(
            array(
                'title'       => '',
                'description' => '',
                'lessons'     => array(
                    array(
                        'title'      => '',
                        'type'       => 'video',
                        'content'    => '',
                        'duration'   => '',
                        'is_preview' => false,
                    ),
                ),
            ),
        );
    }

    $total_lessons = 0;
    foreach ( $curriculum as $sec ) {
        $total_lessons += isset( $sec['lessons'] ) && is_array( $sec['lessons'] ) ? count( $sec['lessons'] ) : 0;
    }
    ?>
    <div class="edupro-mb">

        <div class="edupro-curriculum-toolbar">
            <div class="edupro-curriculum-stats">
                <strong id="edupro-stat-sections"><?php echo (int) count( $curriculum ); ?></strong>
                <?php esc_html_e( 'Sections', 'edupro' ); ?> &middot;
                <strong id="edupro-stat-lessons"><?php echo (int) $total_lessons; ?></strong>
                <?php esc_html_e( 'Lessons', 'edupro' ); ?>
                <span style="font-weight:400;color:#6b7280;margin-left:6px;"><?php esc_html_e( '(Drag handles to reorder sections & lessons)', 'edupro' ); ?></span>
            </div>
            <button type="button" id="edupro-add-section" class="edupro-btn edupro-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add Section', 'edupro' ); ?>
            </button>
        </div>

        <div id="edupro-curriculum-builder">
            <?php foreach ( $curriculum as $s_index => $section ) :
                $sec_title   = isset( $section['title'] ) ? $section['title'] : '';
                $sec_desc    = isset( $section['description'] ) ? $section['description'] : '';
                $sec_lessons = isset( $section['lessons'] ) && is_array( $section['lessons'] ) ? $section['lessons'] : array();
                $editor_id   = 'edupro_sec_desc_' . ( $s_index + 1 );
            ?>
            <div class="edupro-section-card" data-editor-id="<?php echo esc_attr( $editor_id ); ?>">
                <div class="edupro-section-header">
                    <span class="edupro-drag-handle edupro-section-handle" title="<?php esc_attr_e( 'Drag section', 'edupro' ); ?>">
                        <span class="dashicons dashicons-menu"></span>
                    </span>
                    <span class="edupro-section-num"><?php printf( esc_html__( 'Section %d', 'edupro' ), (int) $s_index + 1 ); ?></span>
                    <input type="text" class="edupro-section-title-input"
                           value="<?php echo esc_attr( $sec_title ); ?>"
                           placeholder="<?php esc_attr_e( 'e.g. Module 1: Getting Started', 'edupro' ); ?>">
                    <button type="button" class="edupro-icon-btn edupro-toggle-section" title="<?php esc_attr_e( 'Collapse / Expand', 'edupro' ); ?>">
                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                    </button>
                    <button type="button" class="edupro-icon-btn edupro-section-remove" title="<?php esc_attr_e( 'Remove Section', 'edupro' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>

                <div class="edupro-section-body">
                    <div class="edupro-section-desc-wrap">
                        <label class="edupro-mb-label" for="<?php echo esc_attr( $editor_id ); ?>">
                            <span><?php esc_html_e( 'Section Description', 'edupro' ); ?></span>
                        </label>
                        <?php
                        wp_editor(
                            $sec_desc,
                            $editor_id,
                            array(
                                'textarea_name' => 'edupro_sec_desc_tmp[' . $editor_id . ']',
                                'textarea_rows' => 3,
                                'media_buttons' => false,
                                'teeny'         => true,
                                'quicktags'     => true,
                                'tinymce'       => array(
                                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                                    'toolbar2' => '',
                                ),
                            )
                        );
                        ?>
                    </div>

                    <div class="edupro-mb-label" style="margin-bottom:8px;">
                        <span><?php esc_html_e( 'Lessons in this Section', 'edupro' ); ?></span>
                    </div>

                    <ul class="edupro-lessons-list">
                        <?php foreach ( $sec_lessons as $lesson ) :
                            $l_title   = isset( $lesson['title'] ) ? $lesson['title'] : '';
                            $l_type    = isset( $lesson['type'] ) ? $lesson['type'] : 'video';
                            $l_content = isset( $lesson['content'] ) ? $lesson['content'] : '';
                            $l_dur     = isset( $lesson['duration'] ) ? $lesson['duration'] : '';
                            $l_prev    = ! empty( $lesson['is_preview'] );
                            $is_text   = in_array( $l_type, array( 'text', 'quiz' ), true );
                        ?>
                        <li class="edupro-lesson-card">
                            <div class="edupro-lesson-top">
                                <span class="edupro-drag-handle edupro-lesson-handle" title="<?php esc_attr_e( 'Drag lesson', 'edupro' ); ?>">
                                    <span class="dashicons dashicons-menu"></span>
                                </span>
                                <input type="text" class="lesson-title"
                                       value="<?php echo esc_attr( $l_title ); ?>"
                                       placeholder="<?php esc_attr_e( 'Lesson title…', 'edupro' ); ?>">
                                <select class="lesson-type">
                                    <option value="video" <?php selected( $l_type, 'video' ); ?>><?php esc_html_e( 'Video', 'edupro' ); ?></option>
                                    <option value="text" <?php selected( $l_type, 'text' ); ?>><?php esc_html_e( 'Text / Article', 'edupro' ); ?></option>
                                    <option value="quiz" <?php selected( $l_type, 'quiz' ); ?>><?php esc_html_e( 'Quiz', 'edupro' ); ?></option>
                                    <option value="pdf" <?php selected( $l_type, 'pdf' ); ?>><?php esc_html_e( 'PDF', 'edupro' ); ?></option>
                                </select>
                                <input type="number" class="lesson-duration" min="0" step="1"
                                       value="<?php echo esc_attr( $l_dur ); ?>"
                                       placeholder="<?php esc_attr_e( 'Min', 'edupro' ); ?>"
                                       title="<?php esc_attr_e( 'Duration in minutes', 'edupro' ); ?>">
                                <button type="button" class="edupro-icon-btn edupro-lesson-remove" title="<?php esc_attr_e( 'Remove Lesson', 'edupro' ); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="edupro-lesson-content-row">
                                <?php if ( $is_text ) : ?>
                                    <textarea class="lesson-content lesson-content-textarea"
                                              placeholder="<?php echo 'quiz' === $l_type ? esc_attr__( 'Quiz ID, instructions, or questions…', 'edupro' ) : esc_attr__( 'Lesson article text or summary…', 'edupro' ); ?>"><?php echo esc_textarea( $l_content ); ?></textarea>
                                <?php else : ?>
                                    <input type="text" class="lesson-content lesson-content-input"
                                           value="<?php echo esc_attr( $l_content ); ?>"
                                           placeholder="<?php echo 'pdf' === $l_type ? esc_attr__( 'PDF document URL…', 'edupro' ) : esc_attr__( 'Video URL (YouTube / Vimeo / MP4)…', 'edupro' ); ?>">
                                <?php endif; ?>
                                <label class="edupro-lesson-preview-flag">
                                    <input type="checkbox" class="lesson-preview" value="1" <?php checked( $l_prev ); ?>>
                                    <?php esc_html_e( 'Free Preview', 'edupro' ); ?>
                                </label>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button type="button" class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-add-lesson-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Add Lesson', 'edupro' ); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Hidden JSON payload saved to _edupro_curriculum -->
        <input type="hidden" id="edupro_curriculum_json" name="_edupro_curriculum" value="<?php echo esc_attr( wp_json_encode( $curriculum ) ); ?>">
    </div>

    <script>
    (function($){
        var $builder  = $('#edupro-curriculum-builder');
        var $jsonInp  = $('#edupro_curriculum_json');
        var editorSeq = <?php echo (int) count( $curriculum ); ?>;

        function escAttr(str){
            return String(str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function getEditorContent(editorId){
            if(typeof tinymce !== 'undefined'){
                var ed = tinymce.get(editorId);
                if(ed && !ed.isHidden()){
                    return ed.getContent();
                }
            }
            var ta = document.getElementById(editorId);
            return ta ? ta.value : '';
        }

        function initDynamicWpEditor(editorId){
            if(typeof wp !== 'undefined' && wp.editor && typeof wp.editor.initialize === 'function'){
                wp.editor.initialize(editorId, {
                    tinymce: {
                        wpautop: true,
                        toolbar1: 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                        toolbar2: '',
                        setup: function(editor){
                            editor.on('change keyup blur', syncCurriculumJSON);
                        }
                    },
                    quicktags: true,
                    mediaButtons: false
                });
            }
        }

        function removeWpEditor(editorId){
            if(typeof wp !== 'undefined' && wp.editor && typeof wp.editor.remove === 'function'){
                wp.editor.remove(editorId);
            }
        }

        function updateSectionNumbersAndStats(){
            var secCount = 0;
            var lesCount = 0;
            $builder.children('.edupro-section-card').each(function(idx){
                secCount++;
                $(this).find('.edupro-section-num').text('Section ' + (idx + 1));
                lesCount += $(this).find('.edupro-lesson-card').length;
            });
            $('#edupro-stat-sections').text(secCount);
            $('#edupro-stat-lessons').text(lesCount);
        }

        function syncCurriculumJSON(){
            if(typeof tinymce !== 'undefined'){
                tinymce.triggerSave();
            }
            var sections = [];
            $builder.children('.edupro-section-card').each(function(){
                var $sec      = $(this);
                var editorId  = $sec.attr('data-editor-id');
                var lessons   = [];

                $sec.find('.edupro-lesson-card').each(function(){
                    var $l = $(this);
                    lessons.push({
                        title:      $l.find('.lesson-title').val() || '',
                        type:       $l.find('.lesson-type').val() || 'video',
                        content:    $l.find('.lesson-content').val() || '',
                        duration:   $l.find('.lesson-duration').val() || '',
                        is_preview: $l.find('.lesson-preview').is(':checked')
                    });
                });

                sections.push({
                    title:       $sec.find('.edupro-section-title-input').val() || '',
                    description: getEditorContent(editorId),
                    lessons:     lessons
                });
            });

            $jsonInp.val(JSON.stringify(sections));
            updateSectionNumbersAndStats();
        }

        function buildLessonHTML(lesson){
            lesson = lesson || { title: '', type: 'video', content: '', duration: '', is_preview: false };
            var isText = (lesson.type === 'text' || lesson.type === 'quiz');
            var placeholder = lesson.type === 'pdf'
                ? '<?php echo esc_js( __( 'PDF document URL…', 'edupro' ) ); ?>'
                : (lesson.type === 'quiz'
                    ? '<?php echo esc_js( __( 'Quiz ID, instructions, or questions…', 'edupro' ) ); ?>'
                    : (lesson.type === 'text'
                        ? '<?php echo esc_js( __( 'Lesson article text or summary…', 'edupro' ) ); ?>'
                        : '<?php echo esc_js( __( 'Video URL (YouTube / Vimeo / MP4)…', 'edupro' ) ); ?>'));

            var contentField = isText
                ? '<textarea class="lesson-content lesson-content-textarea" placeholder="'+escAttr(placeholder)+'">'+escAttr(lesson.content)+'</textarea>'
                : '<input type="text" class="lesson-content lesson-content-input" value="'+escAttr(lesson.content)+'" placeholder="'+escAttr(placeholder)+'">';

            return (
                '<li class="edupro-lesson-card">'
              +   '<div class="edupro-lesson-top">'
              +     '<span class="edupro-drag-handle edupro-lesson-handle" title="Drag lesson"><span class="dashicons dashicons-menu"></span></span>'
              +     '<input type="text" class="lesson-title" value="'+escAttr(lesson.title)+'" placeholder="<?php echo esc_js( __( 'Lesson title…', 'edupro' ) ); ?>">'
              +     '<select class="lesson-type">'
              +       '<option value="video"'+(lesson.type==='video'?' selected':'')+'>Video</option>'
              +       '<option value="text"'+(lesson.type==='text'?' selected':'')+'>Text / Article</option>'
              +       '<option value="quiz"'+(lesson.type==='quiz'?' selected':'')+'>Quiz</option>'
              +       '<option value="pdf"'+(lesson.type==='pdf'?' selected':'')+'>PDF</option>'
              +     '</select>'
              +     '<input type="number" class="lesson-duration" min="0" step="1" value="'+escAttr(lesson.duration)+'" placeholder="Min" title="Duration in minutes">'
              +     '<button type="button" class="edupro-icon-btn edupro-lesson-remove" title="Remove Lesson"><span class="dashicons dashicons-no-alt"></span></button>'
              +   '</div>'
              +   '<div class="edupro-lesson-content-row">'
              +     contentField
              +     '<label class="edupro-lesson-preview-flag">'
              +       '<input type="checkbox" class="lesson-preview" value="1"'+(lesson.is_preview?' checked':'')+'> Free Preview'
              +     '</label>'
              +   '</div>'
              + '</li>'
            );
        }

        function initLessonSortable($list){
            if(!$.fn || !$.fn.sortable) return;
            $list.sortable({
                handle: '.edupro-lesson-handle',
                connectWith: '.edupro-lessons-list',
                placeholder: 'edupro-lesson-placeholder',
                tolerance: 'pointer',
                stop: syncCurriculumJSON
            });
        }

        function initSectionSortable(){
            if(!$.fn || !$.fn.sortable) return;
            var savedEditorContent = {};
            $builder.sortable({
                handle: '.edupro-section-handle',
                placeholder: 'edupro-section-placeholder',
                axis: 'y',
                tolerance: 'pointer',
                start: function(e, ui){
                    var edId = ui.item.attr('data-editor-id');
                    if(edId){
                        savedEditorContent[edId] = getEditorContent(edId);
                        removeWpEditor(edId);
                        var ta = document.getElementById(edId);
                        if(ta) ta.value = savedEditorContent[edId];
                    }
                },
                stop: function(e, ui){
                    var edId = ui.item.attr('data-editor-id');
                    if(edId){
                        initDynamicWpEditor(edId);
                    }
                    syncCurriculumJSON();
                }
            });
        }

        /* ---- Add Section ---- */
        $('#edupro-add-section').on('click', function(){
            editorSeq++;
            var edId = 'edupro_sec_desc_' + editorSeq;
            var html =
                '<div class="edupro-section-card" data-editor-id="'+edId+'">'
              +   '<div class="edupro-section-header">'
              +     '<span class="edupro-drag-handle edupro-section-handle" title="Drag section"><span class="dashicons dashicons-menu"></span></span>'
              +     '<span class="edupro-section-num">Section</span>'
              +     '<input type="text" class="edupro-section-title-input" placeholder="<?php echo esc_js( __( 'e.g. Module: Core Concepts', 'edupro' ) ); ?>">'
              +     '<button type="button" class="edupro-icon-btn edupro-toggle-section" title="Collapse / Expand"><span class="dashicons dashicons-arrow-up-alt2"></span></button>'
              +     '<button type="button" class="edupro-icon-btn edupro-section-remove" title="Remove Section"><span class="dashicons dashicons-trash"></span></button>'
              +   '</div>'
              +   '<div class="edupro-section-body">'
              +     '<div class="edupro-section-desc-wrap">'
              +       '<label class="edupro-mb-label" for="'+edId+'"><span><?php echo esc_js( __( 'Section Description', 'edupro' ) ); ?></span></label>'
              +       '<textarea id="'+edId+'" name="edupro_sec_desc_tmp['+edId+']" rows="3"></textarea>'
              +     '</div>'
              +     '<div class="edupro-mb-label" style="margin-bottom:8px;"><span><?php echo esc_js( __( 'Lessons in this Section', 'edupro' ) ); ?></span></div>'
              +     '<ul class="edupro-lessons-list">'+buildLessonHTML()+'</ul>'
              +     '<button type="button" class="edupro-btn edupro-btn-outline edupro-btn-sm edupro-add-lesson-btn">'
              +       '<span class="dashicons dashicons-plus-alt2"></span> <?php echo esc_js( __( 'Add Lesson', 'edupro' ) ); ?>'
              +     '</button>'
              +   '</div>'
              + '</div>';

            var $sec = $(html).appendTo($builder);
            initDynamicWpEditor(edId);
            initLessonSortable($sec.find('.edupro-lessons-list'));
            $sec.find('.edupro-section-title-input').trigger('focus');
            syncCurriculumJSON();
        });

        /* ---- Delegated events ---- */
        $builder.on('click', '.edupro-add-lesson-btn', function(){
            var $list = $(this).siblings('.edupro-lessons-list');
            var $item = $(buildLessonHTML()).appendTo($list);
            $item.find('.lesson-title').trigger('focus');
            syncCurriculumJSON();
        });

        $builder.on('click', '.edupro-lesson-remove', function(){
            $(this).closest('.edupro-lesson-card').remove();
            syncCurriculumJSON();
        });

        $builder.on('click', '.edupro-section-remove', function(){
            var $sec = $(this).closest('.edupro-section-card');
            var edId = $sec.attr('data-editor-id');
            if(edId) removeWpEditor(edId);
            $sec.remove();
            syncCurriculumJSON();
        });

        $builder.on('click', '.edupro-toggle-section', function(){
            var $sec  = $(this).closest('.edupro-section-card');
            var $icon = $(this).find('.dashicons');
            $sec.toggleClass('is-collapsed');
            $icon.toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');
        });

        // Swap input <-> textarea when lesson type changes between URL types and text/quiz
        $builder.on('change', '.lesson-type', function(){
            var type    = $(this).val();
            var $card   = $(this).closest('.edupro-lesson-card');
            var $row    = $card.find('.edupro-lesson-content-row');
            var $oldEl  = $row.find('.lesson-content');
            var currVal = $oldEl.val() || '';
            var isText  = (type === 'text' || type === 'quiz');
            var placeholder = type === 'pdf'
                ? '<?php echo esc_js( __( 'PDF document URL…', 'edupro' ) ); ?>'
                : (type === 'quiz'
                    ? '<?php echo esc_js( __( 'Quiz ID, instructions, or questions…', 'edupro' ) ); ?>'
                    : (type === 'text'
                        ? '<?php echo esc_js( __( 'Lesson article text or summary…', 'edupro' ) ); ?>'
                        : '<?php echo esc_js( __( 'Video URL (YouTube / Vimeo / MP4)…', 'edupro' ) ); ?>'));

            var $newEl;
            if(isText){
                $newEl = $('<textarea class="lesson-content lesson-content-textarea"></textarea>').attr('placeholder', placeholder).val(currVal);
            } else {
                $newEl = $('<input type="text" class="lesson-content lesson-content-input">').attr('placeholder', placeholder).val(currVal);
            }
            $oldEl.replaceWith($newEl);
            syncCurriculumJSON();
        });

        $builder.on('input change', 'input, select, textarea', syncCurriculumJSON);

        // Attach to initial PHP-rendered TinyMCE editors once initialized
        $(document).on('tinymce-editor-init', function(event, editor){
            if(editor && editor.id && editor.id.indexOf('edupro_sec_desc_') === 0){
                editor.on('change keyup blur', syncCurriculumJSON);
            }
        });

        // Ensure JSON is synced before Classic Editor or Gutenberg saves
        $('#post').on('submit', syncCurriculumJSON);
        if(typeof wp !== 'undefined' && wp.data && wp.data.subscribe){
            var wasSaving = false;
            wp.data.subscribe(function(){
                var editorStore = wp.data.select('core/editor');
                if(!editorStore) return;
                var isSaving = editorStore.isSavingPost();
                if(isSaving && !wasSaving){
                    syncCurriculumJSON();
                }
                wasSaving = isSaving;
            });
        }

        // Init sortables
        initSectionSortable();
        $builder.find('.edupro-lessons-list').each(function(){
            initLessonSortable($(this));
        });
        syncCurriculumJSON();
    })(jQuery);
    </script>
    <?php
}

/* =========================================================================
   7. META BOX: SEO & SHARING (edupro_seo)
   ========================================================================= */

function edupro_render_seo_metabox( $post ) {
    wp_nonce_field( 'edupro_save_seo', 'edupro_seo_nonce' );

    $meta_title       = get_post_meta( $post->ID, '_edupro_meta_title', true );
    $meta_description = get_post_meta( $post->ID, '_edupro_meta_description', true );
    $og_image_id      = get_post_meta( $post->ID, '_edupro_og_image', true );
    $og_image_url     = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'medium_large' ) : '';
    $fallback_title   = $post->post_title ? $post->post_title : __( 'Course Title', 'edupro' );
    $permalink        = get_permalink( $post->ID ) ?: home_url( '/courses/' );
    ?>
    <div class="edupro-mb">

        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Search Engine Preview', 'edupro' ); ?></span>
        </div>

        <!-- Live Google SERP Preview -->
        <div class="edupro-serp-preview">
            <div class="edupro-serp-label"><?php esc_html_e( 'Google Snippet Preview', 'edupro' ); ?></div>
            <div class="edupro-serp-url"><?php echo esc_html( $permalink ); ?></div>
            <div class="edupro-serp-title" id="edupro-serp-title">
                <?php echo esc_html( $meta_title ? $meta_title : $fallback_title ); ?>
            </div>
            <div class="edupro-serp-desc" id="edupro-serp-desc">
                <?php echo esc_html( $meta_description ? $meta_description : __( 'Add a compelling meta description to improve click-through rates from Google search results.', 'edupro' ) ); ?>
            </div>
        </div>

        <div class="edupro-mb-row">
            <div class="edupro-mb-col full">
                <label class="edupro-mb-label" for="_edupro_meta_title">
                    <span><?php esc_html_e( 'SEO Meta Title', 'edupro' ); ?></span>
                    <span class="edupro-char-count" id="edupro-title-count">0 / 60</span>
                </label>
                <input type="text" id="_edupro_meta_title" name="_edupro_meta_title"
                       value="<?php echo esc_attr( $meta_title ); ?>"
                       maxlength="80"
                       placeholder="<?php esc_attr_e( 'Custom title tag for search engines…', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'Optimal length: 50–60 characters. Leave blank to default to the course title.', 'edupro' ); ?></p>
            </div>
        </div>

        <div class="edupro-mb-row">
            <div class="edupro-mb-col full">
                <label class="edupro-mb-label" for="_edupro_meta_description">
                    <span><?php esc_html_e( 'SEO Meta Description', 'edupro' ); ?></span>
                    <span class="edupro-char-count" id="edupro-desc-count">0 / 160</span>
                </label>
                <textarea id="_edupro_meta_description" name="_edupro_meta_description"
                          rows="3" maxlength="220"
                          placeholder="<?php esc_attr_e( 'Concise summary of the course shown in search results…', 'edupro' ); ?>"><?php echo esc_textarea( $meta_description ); ?></textarea>
                <p class="edupro-hint"><?php esc_html_e( 'Optimal length: 140–160 characters.', 'edupro' ); ?></p>
            </div>
        </div>

        <hr class="edupro-divider">

        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Open Graph / Social Share Image', 'edupro' ); ?></span>
        </div>

        <div class="edupro-mb-row" style="margin-bottom:0;">
            <div class="edupro-mb-col full">
                <input type="hidden" id="_edupro_og_image" name="_edupro_og_image"
                       value="<?php echo esc_attr( $og_image_id ); ?>">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="edupro-btn edupro-btn-primary" id="edupro-og-upload-btn">
                        <span class="dashicons dashicons-format-image"></span>
                        <?php esc_html_e( 'Upload / Select OG Image', 'edupro' ); ?>
                    </button>
                    <button type="button" class="edupro-btn edupro-btn-danger" id="edupro-og-remove-btn" style="<?php echo $og_image_id ? '' : 'display:none;'; ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Remove Image', 'edupro' ); ?>
                    </button>
                </div>
                <p class="edupro-hint"><?php esc_html_e( 'Recommended: 1200×630 px. Shown when shared on Facebook, LinkedIn, X/Twitter, and WhatsApp.', 'edupro' ); ?></p>
                <div id="edupro-og-preview" style="<?php echo $og_image_url ? '' : 'display:none;'; ?>">
                    <?php if ( $og_image_url ) : ?>
                        <div class="edupro-og-preview">
                            <img src="<?php echo esc_url( $og_image_url ); ?>" alt="<?php esc_attr_e( 'Open Graph Preview', 'edupro' ); ?>">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script>
    (function(){
        var titleInput = document.getElementById('_edupro_meta_title');
        var descInput  = document.getElementById('_edupro_meta_description');
        var titleCount = document.getElementById('edupro-title-count');
        var descCount  = document.getElementById('edupro-desc-count');
        var serpTitle  = document.getElementById('edupro-serp-title');
        var serpDesc   = document.getElementById('edupro-serp-desc');
        var fallbackT  = <?php echo wp_json_encode( $fallback_title ); ?>;
        var fallbackD  = <?php echo wp_json_encode( __( 'Add a compelling meta description to improve click-through rates from Google search results.', 'edupro' ) ); ?>;

        function bindCounter(input, counter, max, serpEl, fallback){
            if(!input || !counter) return;
            function update(){
                var len = input.value.length;
                counter.textContent = len + ' / ' + max;
                counter.classList.remove('is-warn', 'is-over');
                if(len > max){
                    counter.classList.add('is-over');
                } else if(len > max * 0.85){
                    counter.classList.add('is-warn');
                }
                if(serpEl){
                    serpEl.textContent = input.value.trim() || fallback;
                }
            }
            input.addEventListener('input', update);
            update();
        }
        bindCounter(titleInput, titleCount, 60, serpTitle, fallbackT);
        bindCounter(descInput, descCount, 160, serpDesc, fallbackD);

        /* ---- wp.media OG Image Uploader ---- */
        var mediaFrame;
        var ogInput   = document.getElementById('_edupro_og_image');
        var ogPreview = document.getElementById('edupro-og-preview');
        var ogUpload  = document.getElementById('edupro-og-upload-btn');
        var ogRemove  = document.getElementById('edupro-og-remove-btn');

        if(ogUpload){
            ogUpload.addEventListener('click', function(e){
                e.preventDefault();
                if(typeof wp === 'undefined' || !wp.media) return;
                if(mediaFrame){ mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title:    '<?php echo esc_js( __( 'Select Social Sharing (OG) Image', 'edupro' ) ); ?>',
                    button:   { text: '<?php echo esc_js( __( 'Use Selected Image', 'edupro' ) ); ?>' },
                    multiple: false,
                    library:  { type: 'image' }
                });
                mediaFrame.on('select', function(){
                    var att = mediaFrame.state().get('selection').first().toJSON();
                    ogInput.value = att.id;
                    var imgUrl = (att.sizes && att.sizes.medium_large) ? att.sizes.medium_large.url : ((att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url);
                    ogPreview.innerHTML = '<div class="edupro-og-preview"><img src="'+imgUrl+'" alt="OG Image"></div>';
                    ogPreview.style.display = '';
                    if(ogRemove) ogRemove.style.display = '';
                });
                mediaFrame.open();
            });
        }

        if(ogRemove){
            ogRemove.addEventListener('click', function(){
                ogInput.value = '';
                ogPreview.innerHTML = '';
                ogPreview.style.display = 'none';
                ogRemove.style.display = 'none';
            });
        }
    })();
    </script>
    <?php
}

/* =========================================================================
   7b. META BOX: COURSE PAGE EXTRAS, GALLERY & FAQS (edupro_course_page_extras)
   ========================================================================= */

function edupro_render_course_page_extras_metabox( $post ) {
    wp_nonce_field( 'edupro_save_course_extras', 'edupro_course_extras_nonce' );

    $awarding_body       = get_post_meta( $post->ID, '_edupro_awarding_body', true );
    $study_method        = get_post_meta( $post->ID, '_edupro_study_method', true );
    $assessment_method   = get_post_meta( $post->ID, '_edupro_assessment_method', true );
    $tutor_support       = get_post_meta( $post->ID, '_edupro_tutor_support', true );
    $gallery_csv         = get_post_meta( $post->ID, '_edupro_course_gallery', true );
    $testimonial_video   = get_post_meta( $post->ID, '_edupro_testimonial_video', true );
    $testimonial_caption = get_post_meta( $post->ID, '_edupro_testimonial_caption', true );
    $faqs_raw            = get_post_meta( $post->ID, '_edupro_course_faqs', true );

    // Parse gallery attachment IDs
    $gallery_ids = array();
    if ( ! empty( $gallery_csv ) ) {
        $parts = is_array( $gallery_csv ) ? $gallery_csv : explode( ',', (string) $gallery_csv );
        $gallery_ids = array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
    }
    $gallery_csv_clean = implode( ',', $gallery_ids );

    // Parse FAQs JSON
    $faqs = array();
    if ( ! empty( $faqs_raw ) ) {
        $decoded_faqs = is_array( $faqs_raw ) ? $faqs_raw : json_decode( (string) $faqs_raw, true );
        if ( is_array( $decoded_faqs ) ) {
            foreach ( $decoded_faqs as $item ) {
                if ( is_array( $item ) && ( isset( $item['q'] ) || isset( $item['a'] ) ) ) {
                    $faqs[] = array(
                        'q' => isset( $item['q'] ) ? (string) $item['q'] : '',
                        'a' => isset( $item['a'] ) ? (string) $item['a'] : '',
                    );
                }
            }
        }
    }
    ?>
    <div class="edupro-mb">

        <!-- 1. Key Course Facts (2-col grid) -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Key Course Facts & Accreditation', 'edupro' ); ?></span>
        </div>

        <div class="edupro-facts-2col">
            <div>
                <label class="edupro-mb-label" for="_edupro_awarding_body">
                    <span><?php esc_html_e( 'Awarding Body / Accreditation', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_awarding_body" name="_edupro_awarding_body"
                       value="<?php echo esc_attr( $awarding_body ); ?>"
                       placeholder="<?php esc_attr_e( 'e.g. CPD Accredited & QLS Endorsed', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'Leave blank to use default Awarding Body from Website Settings.', 'edupro' ); ?></p>
            </div>

            <div>
                <label class="edupro-mb-label" for="_edupro_study_method">
                    <span><?php esc_html_e( 'Study Method', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_study_method" name="_edupro_study_method"
                       value="<?php echo esc_attr( $study_method ); ?>"
                       placeholder="<?php esc_attr_e( 'e.g. 100% Online / Self-Paced', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'How students attend or study this course.', 'edupro' ); ?></p>
            </div>

            <div>
                <label class="edupro-mb-label" for="_edupro_assessment_method">
                    <span><?php esc_html_e( 'Assessment Method', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_assessment_method" name="_edupro_assessment_method"
                       value="<?php echo esc_attr( $assessment_method ); ?>"
                       placeholder="<?php esc_attr_e( 'e.g. Online MCQ & Practical', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'Exam, assignment, or quiz assessment format.', 'edupro' ); ?></p>
            </div>

            <div>
                <label class="edupro-mb-label" for="_edupro_tutor_support">
                    <span><?php esc_html_e( 'Tutor Support', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_tutor_support" name="_edupro_tutor_support"
                       value="<?php echo esc_attr( $tutor_support ); ?>"
                       placeholder="<?php esc_attr_e( 'e.g. 24/7 Dedicated Tutor Support', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'Support availability shown in the Course Key Facts grid.', 'edupro' ); ?></p>
            </div>
        </div>

        <hr class="edupro-divider">

        <!-- 2. Course Gallery Images -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Course Gallery Images', 'edupro' ); ?></span>
            <span id="edupro-gallery-count-badge" class="edupro-badge">
                <?php echo (int) count( $gallery_ids ); ?> <?php esc_html_e( 'Images', 'edupro' ); ?>
            </span>
        </div>

        <input type="hidden" id="_edupro_course_gallery" name="_edupro_course_gallery" value="<?php echo esc_attr( $gallery_csv_clean ); ?>">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="edupro-btn edupro-btn-primary" id="edupro-gallery-add-btn">
                <span class="dashicons dashicons-images-alt2"></span>
                <?php esc_html_e( 'Add Gallery Images', 'edupro' ); ?>
            </button>
            <button type="button" class="edupro-btn edupro-btn-danger" id="edupro-gallery-clear-btn" style="<?php echo ! empty( $gallery_ids ) ? '' : 'display:none;'; ?>">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e( 'Clear Gallery', 'edupro' ); ?>
            </button>
        </div>
        <p class="edupro-hint"><?php esc_html_e( 'Select multiple images from the Media Library (e.g. classroom, certificate sample, projects). Drag thumbnails to reorder.', 'edupro' ); ?></p>

        <ul class="edupro-gallery-grid" id="edupro-gallery-list" style="<?php echo ! empty( $gallery_ids ) ? '' : 'display:none;'; ?>">
            <?php foreach ( $gallery_ids as $att_id ) :
                $thumb_url = wp_get_attachment_image_url( $att_id, 'thumbnail' );
                if ( ! $thumb_url ) {
                    $thumb_url = wp_get_attachment_image_url( $att_id, 'medium' );
                }
                if ( ! $thumb_url ) {
                    continue;
                }
            ?>
            <li class="edupro-gallery-thumb" data-id="<?php echo (int) $att_id; ?>">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
                <button type="button" class="edupro-gallery-thumb-remove" title="<?php esc_attr_e( 'Remove image', 'edupro' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="edupro-gallery-empty" id="edupro-gallery-empty" style="<?php echo empty( $gallery_ids ) ? '' : 'display:none;'; ?>">
            <?php esc_html_e( 'No gallery images selected yet. Click "Add Gallery Images" to pick course highlights or certificate previews.', 'edupro' ); ?>
        </div>

        <hr class="edupro-divider">

        <!-- 3. Student Testimonial Video -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Student Testimonial Video (Sidebar Highlight)', 'edupro' ); ?></span>
        </div>

        <div class="edupro-mb-row">
            <div class="edupro-mb-col" style="flex:2 1 320px;">
                <label class="edupro-mb-label" for="_edupro_testimonial_video">
                    <span><?php esc_html_e( 'Testimonial Video URL', 'edupro' ); ?></span>
                </label>
                <div class="edupro-url-with-upload">
                    <input type="url" id="_edupro_testimonial_video" name="_edupro_testimonial_video"
                           value="<?php echo esc_attr( $testimonial_video ); ?>"
                           placeholder="https://www.youtube.com/watch?v=... or MP4 URL">
                    <button type="button" class="edupro-btn edupro-btn-ghost" id="edupro-testimonial-video-upload">
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php esc_html_e( 'Media Library', 'edupro' ); ?>
                    </button>
                </div>
                <p class="edupro-hint"><?php esc_html_e( 'Optional graduate review video displayed on the Single Course page sidebar.', 'edupro' ); ?></p>
            </div>

            <div class="edupro-mb-col" style="flex:1 1 220px;">
                <label class="edupro-mb-label" for="_edupro_testimonial_caption">
                    <span><?php esc_html_e( 'Testimonial Caption / Label', 'edupro' ); ?></span>
                </label>
                <input type="text" id="_edupro_testimonial_caption" name="_edupro_testimonial_caption"
                       value="<?php echo esc_attr( $testimonial_caption ); ?>"
                       placeholder="<?php esc_attr_e( 'e.g. Watch Sarah\'s Graduate Story', 'edupro' ); ?>">
                <p class="edupro-hint"><?php esc_html_e( 'Short label displayed beneath the testimonial video.', 'edupro' ); ?></p>
            </div>
        </div>

        <div id="edupro-testimonial-video-preview" style="<?php echo ! empty( $testimonial_video ) ? '' : 'display:none;'; ?>margin-bottom:6px;">
            <?php if ( ! empty( $testimonial_video ) ) : ?>
                <?php echo edupro_build_video_embed( $testimonial_video ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>

        <hr class="edupro-divider">

        <!-- 4. Custom Course FAQs Repeater -->
        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Custom Course FAQs', 'edupro' ); ?></span>
            <span id="edupro-faq-count-badge" class="edupro-badge">
                <?php echo (int) count( $faqs ); ?> <?php esc_html_e( 'FAQs', 'edupro' ); ?>
            </span>
        </div>

        <ul class="edupro-faq-list" id="edupro-faq-list">
            <?php foreach ( $faqs as $faq ) : ?>
            <li class="edupro-faq-row">
                <div class="edupro-faq-row-head">
                    <span class="edupro-drag-handle edupro-faq-handle" title="<?php esc_attr_e( 'Drag to reorder FAQ', 'edupro' ); ?>">
                        <span class="dashicons dashicons-menu"></span>
                    </span>
                    <input type="text" class="edupro-faq-q"
                           value="<?php echo esc_attr( $faq['q'] ); ?>"
                           placeholder="<?php esc_attr_e( 'Question (e.g. Is this course accredited in the UK & India?)', 'edupro' ); ?>">
                    <button type="button" class="edupro-icon-btn edupro-faq-remove" title="<?php esc_attr_e( 'Remove FAQ', 'edupro' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <div class="edupro-faq-row-body">
                    <textarea class="edupro-faq-a" rows="2"
                              placeholder="<?php esc_attr_e( 'Answer…', 'edupro' ); ?>"><?php echo esc_textarea( $faq['a'] ); ?></textarea>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <button type="button" class="edupro-btn edupro-btn-outline edupro-btn-sm" id="edupro-add-faq-btn">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Add FAQ', 'edupro' ); ?>
        </button>
        <p class="edupro-hint"><?php esc_html_e( 'Custom FAQs replace or extend the default FAQ tab on this course page. Drag handles to reorder.', 'edupro' ); ?></p>

        <input type="hidden" id="_edupro_course_faqs" name="_edupro_course_faqs" value="<?php echo esc_attr( wp_json_encode( $faqs ) ); ?>">

    </div>

    <script>
    (function($){
        /* ---- 1. Course Gallery Multi-Select Media Picker ---- */
        var $galleryInput = $('#_edupro_course_gallery');
        var $galleryList  = $('#edupro-gallery-list');
        var $galleryEmpty = $('#edupro-gallery-empty');
        var $galleryClear = $('#edupro-gallery-clear-btn');
        var $galleryBadge = $('#edupro-gallery-count-badge');
        var galleryFrame;

        function syncGalleryIds(){
            var ids = [];
            $galleryList.children('.edupro-gallery-thumb').each(function(){
                var id = parseInt($(this).attr('data-id'), 10);
                if(id > 0 && ids.indexOf(id) === -1){
                    ids.push(id);
                }
            });
            $galleryInput.val(ids.join(','));
            $galleryBadge.text(ids.length + ' <?php echo esc_js( __( 'Images', 'edupro' ) ); ?>');
            if(ids.length > 0){
                $galleryList.show();
                $galleryEmpty.hide();
                $galleryClear.show();
            } else {
                $galleryList.hide();
                $galleryEmpty.show();
                $galleryClear.hide();
            }
        }

        function appendGalleryThumb(id, url){
            if(!id || !url) return;
            if($galleryList.children('.edupro-gallery-thumb[data-id="' + id + '"]').length){
                return;
            }
            var safeUrl = String(url).replace(/"/g, '&quot;');
            var $li = $(
                '<li class="edupro-gallery-thumb" data-id="' + id + '">'
              +   '<img src="' + safeUrl + '" alt="">'
              +   '<button type="button" class="edupro-gallery-thumb-remove" title="<?php echo esc_js( __( 'Remove image', 'edupro' ) ); ?>">'
              +     '<span class="dashicons dashicons-no-alt"></span>'
              +   '</button>'
              + '</li>'
            );
            $galleryList.append($li);
        }

        $('#edupro-gallery-add-btn').on('click', function(e){
            e.preventDefault();
            if(typeof wp === 'undefined' || !wp.media) return;
            if(galleryFrame){
                galleryFrame.open();
                return;
            }
            galleryFrame = wp.media({
                title:    '<?php echo esc_js( __( 'Select Course Gallery Images', 'edupro' ) ); ?>',
                button:   { text: '<?php echo esc_js( __( 'Add to Gallery', 'edupro' ) ); ?>' },
                multiple: 'add',
                library:  { type: 'image' }
            });
            galleryFrame.on('select', function(){
                var selection = galleryFrame.state().get('selection');
                selection.each(function(model){
                    var att = model.toJSON();
                    var thumb = (att.sizes && att.sizes.thumbnail)
                        ? att.sizes.thumbnail.url
                        : ((att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url);
                    appendGalleryThumb(att.id, thumb);
                });
                syncGalleryIds();
            });
            galleryFrame.open();
        });

        $galleryList.on('click', '.edupro-gallery-thumb-remove', function(){
            $(this).closest('.edupro-gallery-thumb').remove();
            syncGalleryIds();
        });

        $galleryClear.on('click', function(){
            $galleryList.empty();
            syncGalleryIds();
        });

        if($.fn && $.fn.sortable){
            $galleryList.sortable({
                items: '> .edupro-gallery-thumb',
                tolerance: 'pointer',
                stop: syncGalleryIds
            });
        }

        /* ---- 2. Testimonial Video Media Picker & Live Preview ---- */
        var $testVideoInput   = $('#_edupro_testimonial_video');
        var $testVideoPreview = $('#edupro-testimonial-video-preview');
        var testVideoFrame;
        var testVideoTimer;

        function renderTestimonialVideo(url){
            var html = window.eduproBuildVideoEmbed ? window.eduproBuildVideoEmbed(url) : '';
            $testVideoPreview.html(html);
            $testVideoPreview.toggle(!!html);
        }

        $testVideoInput.on('input', function(){
            clearTimeout(testVideoTimer);
            var val = $(this).val().trim();
            testVideoTimer = setTimeout(function(){
                renderTestimonialVideo(val);
            }, 450);
        });

        $('#edupro-testimonial-video-upload').on('click', function(e){
            e.preventDefault();
            if(typeof wp === 'undefined' || !wp.media) return;
            if(testVideoFrame){ testVideoFrame.open(); return; }
            testVideoFrame = wp.media({
                title:    '<?php echo esc_js( __( 'Select Testimonial Video', 'edupro' ) ); ?>',
                button:   { text: '<?php echo esc_js( __( 'Use Video', 'edupro' ) ); ?>' },
                multiple: false,
                library:  { type: 'video' }
            });
            testVideoFrame.on('select', function(){
                var att = testVideoFrame.state().get('selection').first().toJSON();
                $testVideoInput.val(att.url);
                renderTestimonialVideo(att.url);
            });
            testVideoFrame.open();
        });

        /* ---- 3. Custom Course FAQs Repeater ---- */
        var $faqList  = $('#edupro-faq-list');
        var $faqInput = $('#_edupro_course_faqs');
        var $faqBadge = $('#edupro-faq-count-badge');

        function syncFaqsJSON(){
            var items = [];
            $faqList.children('.edupro-faq-row').each(function(){
                var q = $(this).find('.edupro-faq-q').val().trim();
                var a = $(this).find('.edupro-faq-a').val().trim();
                if(q || a){
                    items.push({ q: q, a: a });
                }
            });
            $faqInput.val(JSON.stringify(items));
            $faqBadge.text(items.length + ' <?php echo esc_js( __( 'FAQs', 'edupro' ) ); ?>');
        }

        function buildFaqRow(){
            return $(
                '<li class="edupro-faq-row">'
              +   '<div class="edupro-faq-row-head">'
              +     '<span class="edupro-drag-handle edupro-faq-handle" title="<?php echo esc_js( __( 'Drag to reorder FAQ', 'edupro' ) ); ?>"><span class="dashicons dashicons-menu"></span></span>'
              +     '<input type="text" class="edupro-faq-q" placeholder="<?php echo esc_js( __( 'Question (e.g. Is this course accredited in the UK & India?)', 'edupro' ) ); ?>">'
              +     '<button type="button" class="edupro-icon-btn edupro-faq-remove" title="<?php echo esc_js( __( 'Remove FAQ', 'edupro' ) ); ?>"><span class="dashicons dashicons-trash"></span></button>'
              +   '</div>'
              +   '<div class="edupro-faq-row-body">'
              +     '<textarea class="edupro-faq-a" rows="2" placeholder="<?php echo esc_js( __( 'Answer…', 'edupro' ) ); ?>"></textarea>'
              +   '</div>'
              + '</li>'
            );
        }

        $('#edupro-add-faq-btn').on('click', function(){
            var $row = buildFaqRow().appendTo($faqList);
            $row.find('.edupro-faq-q').trigger('focus');
            syncFaqsJSON();
        });

        $faqList.on('click', '.edupro-faq-remove', function(){
            $(this).closest('.edupro-faq-row').remove();
            syncFaqsJSON();
        });

        $faqList.on('input change', '.edupro-faq-q, .edupro-faq-a', syncFaqsJSON);

        if($.fn && $.fn.sortable){
            $faqList.sortable({
                handle: '.edupro-faq-handle',
                axis: 'y',
                placeholder: 'edupro-faq-placeholder',
                stop: syncFaqsJSON
            });
        }

        // Sync before Classic Editor & Gutenberg saves
        $('#post').on('submit', function(){
            syncGalleryIds();
            syncFaqsJSON();
        });
        if(typeof wp !== 'undefined' && wp.data && wp.data.subscribe){
            var extrasWasSaving = false;
            wp.data.subscribe(function(){
                var editorStore = wp.data.select('core/editor');
                if(!editorStore) return;
                var isSaving = editorStore.isSavingPost();
                if(isSaving && !extrasWasSaving){
                    syncGalleryIds();
                    syncFaqsJSON();
                }
                extrasWasSaving = isSaving;
            });
        }

        syncGalleryIds();
        syncFaqsJSON();
    })(jQuery);
    </script>
    <?php
}

/* =========================================================================
   8. META BOX: LESSON DETAILS (lms_lesson)
   ========================================================================= */

function edupro_render_lesson_details_metabox( $post ) {
    wp_nonce_field( 'edupro_save_lesson', 'edupro_lesson_nonce' );

    $lesson_type    = get_post_meta( $post->ID, '_edupro_lesson_type', true );
    $lesson_type    = in_array( $lesson_type, array( 'video', 'text', 'pdf' ), true ) ? $lesson_type : 'video';
    $video_url      = get_post_meta( $post->ID, '_edupro_video_url', true );
    $video_duration = get_post_meta( $post->ID, '_edupro_video_duration', true );
    $is_preview     = get_post_meta( $post->ID, '_edupro_is_preview', true );
    $lesson_order   = get_post_meta( $post->ID, '_edupro_lesson_order', true );
    $course_id      = get_post_meta( $post->ID, '_edupro_course_id', true );

    $courses = get_posts( array(
        'post_type'      => 'lms_course',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );
    ?>
    <div class="edupro-mb">

        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Course Assignment & Ordering', 'edupro' ); ?></span>
        </div>

        <div class="edupro-mb-row">
            <div class="edupro-mb-col">
                <label class="edupro-mb-label" for="_edupro_course_id">
                    <span><?php esc_html_e( 'Parent Course', 'edupro' ); ?></span>
                </label>
                <select id="_edupro_course_id" name="_edupro_course_id">
                    <option value=""><?php esc_html_e( '— Select Parent Course —', 'edupro' ); ?></option>
                    <?php foreach ( $courses as $course ) : ?>
                        <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( (int) $course_id, (int) $course->ID ); ?>>
                            <?php echo esc_html( $course->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="edupro-mb-col" style="max-width:190px;">
                <label class="edupro-mb-label" for="_edupro_lesson_order">
                    <span><?php esc_html_e( 'Lesson Order', 'edupro' ); ?></span>
                </label>
                <input type="number" id="_edupro_lesson_order" name="_edupro_lesson_order"
                       value="<?php echo esc_attr( $lesson_order ); ?>"
                       min="0" step="1" placeholder="1">
                <p class="edupro-hint"><?php esc_html_e( 'Sequence number in course.', 'edupro' ); ?></p>
            </div>
        </div>

        <hr class="edupro-divider">

        <div class="edupro-section-heading">
            <span><?php esc_html_e( 'Lesson Type & Media', 'edupro' ); ?></span>
        </div>

        <div class="edupro-pill-group" id="edupro-lesson-type-group">
            <?php
            $types = array(
                'video' => array( 'icon' => 'dashicons-video-alt3', 'label' => __( 'Video', 'edupro' ) ),
                'text'  => array( 'icon' => 'dashicons-media-text', 'label' => __( 'Text / Article', 'edupro' ) ),
                'pdf'   => array( 'icon' => 'dashicons-pdf',        'label' => __( 'PDF Document', 'edupro' ) ),
            );
            foreach ( $types as $val => $info ) :
                $is_active = ( $lesson_type === $val );
            ?>
            <label class="edupro-type-pill <?php echo $is_active ? 'is-active' : ''; ?>" data-type="<?php echo esc_attr( $val ); ?>">
                <input type="radio" name="_edupro_lesson_type" value="<?php echo esc_attr( $val ); ?>" <?php checked( $lesson_type, $val ); ?>>
                <span class="dashicons <?php echo esc_attr( $info['icon'] ); ?>"></span>
                <span><?php echo esc_html( $info['label'] ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Video / PDF URL + Duration -->
        <div id="edupro-media-fields" style="<?php echo 'text' === $lesson_type ? 'display:none;' : ''; ?>">
            <div class="edupro-mb-row">
                <div class="edupro-mb-col" style="flex:2 1 320px;">
                    <label class="edupro-mb-label" for="_edupro_video_url">
                        <span id="edupro-video-url-label">
                            <?php echo 'pdf' === $lesson_type ? esc_html__( 'PDF File URL', 'edupro' ) : esc_html__( 'Video URL (YouTube / Vimeo / Self-Hosted MP4)', 'edupro' ); ?>
                        </span>
                    </label>
                    <div class="edupro-url-with-upload">
                        <input type="url" id="_edupro_video_url" name="_edupro_video_url"
                               value="<?php echo esc_attr( $video_url ); ?>"
                               placeholder="<?php echo 'pdf' === $lesson_type ? 'https://example.com/workbook.pdf' : 'https://www.youtube.com/watch?v=...'; ?>">
                        <button type="button" class="edupro-btn edupro-btn-ghost" id="edupro-lesson-media-upload">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e( 'Upload', 'edupro' ); ?>
                        </button>
                    </div>
                    <p class="edupro-hint" id="edupro-video-url-hint">
                        <?php echo 'pdf' === $lesson_type
                            ? esc_html__( 'Upload a PDF via the Media Library or paste a direct PDF link.', 'edupro' )
                            : esc_html__( 'Supports YouTube, Vimeo, or direct MP4/WebM/Ogg URLs. Live preview appears below.', 'edupro' ); ?>
                    </p>
                </div>

                <div class="edupro-mb-col" id="edupro-duration-col" style="max-width:210px;<?php echo 'video' === $lesson_type ? '' : 'display:none;'; ?>">
                    <label class="edupro-mb-label" for="_edupro_video_duration">
                        <span><?php esc_html_e( 'Video Duration (min)', 'edupro' ); ?></span>
                    </label>
                    <input type="number" id="_edupro_video_duration" name="_edupro_video_duration"
                           value="<?php echo esc_attr( $video_duration ); ?>"
                           min="0" step="0.5" placeholder="e.g. 15">
                </div>
            </div>

            <!-- Embedded Admin Video Player Preview -->
            <div id="edupro-lesson-video-preview" style="<?php echo ( 'video' === $lesson_type && ! empty( $video_url ) ) ? '' : 'display:none;'; ?>">
                <?php if ( 'video' === $lesson_type && ! empty( $video_url ) ) : ?>
                    <?php echo edupro_build_video_embed( $video_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
        </div>

        <hr class="edupro-divider">

        <!-- Free Preview Toggle -->
        <div class="edupro-toggle-card" style="margin-bottom:0;">
            <div class="edupro-toggle-wrap">
                <label class="edupro-toggle" for="_edupro_is_preview">
                    <input type="checkbox" name="_edupro_is_preview" id="_edupro_is_preview" value="1" <?php checked( $is_preview, '1' ); ?>>
                    <span class="edupro-slider"></span>
                </label>
                <div>
                    <span class="edupro-toggle-title"><?php esc_html_e( 'Free Preview Lesson', 'edupro' ); ?></span>
                    <span class="edupro-toggle-sub"><?php esc_html_e( 'Allow non-enrolled visitors to watch or read this lesson as a sample.', 'edupro' ); ?></span>
                </div>
            </div>
        </div>

    </div>

    <script>
    (function(){
        var pills        = document.querySelectorAll('#edupro-lesson-type-group .edupro-type-pill');
        var mediaFields  = document.getElementById('edupro-media-fields');
        var durCol       = document.getElementById('edupro-duration-col');
        var videoPreview = document.getElementById('edupro-lesson-video-preview');
        var videoInput   = document.getElementById('_edupro_video_url');
        var urlLabel     = document.getElementById('edupro-video-url-label');
        var urlHint      = document.getElementById('edupro-video-url-hint');
        var uploadBtn    = document.getElementById('edupro-lesson-media-upload');

        function getCurrentType(){
            var checked = document.querySelector('input[name="_edupro_lesson_type"]:checked');
            return checked ? checked.value : 'video';
        }

        function updatePreview(){
            if(getCurrentType() !== 'video'){
                videoPreview.style.display = 'none';
                return;
            }
            var html = window.eduproBuildVideoEmbed ? window.eduproBuildVideoEmbed(videoInput.value.trim()) : '';
            videoPreview.innerHTML = html;
            videoPreview.style.display = html ? '' : 'none';
        }

        pills.forEach(function(pill){
            pill.addEventListener('click', function(){
                var radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                var val = radio.value;

                pills.forEach(function(p){ p.classList.remove('is-active'); });
                this.classList.add('is-active');

                if(val === 'text'){
                    mediaFields.style.display = 'none';
                    videoPreview.style.display = 'none';
                } else {
                    mediaFields.style.display = '';
                    durCol.style.display      = val === 'video' ? '' : 'none';
                    urlLabel.textContent      = val === 'pdf'
                        ? '<?php echo esc_js( __( 'PDF File URL', 'edupro' ) ); ?>'
                        : '<?php echo esc_js( __( 'Video URL (YouTube / Vimeo / Self-Hosted MP4)', 'edupro' ) ); ?>';
                    urlHint.textContent       = val === 'pdf'
                        ? '<?php echo esc_js( __( 'Upload a PDF via the Media Library or paste a direct PDF link.', 'edupro' ) ); ?>'
                        : '<?php echo esc_js( __( 'Supports YouTube, Vimeo, or direct MP4/WebM/Ogg URLs. Live preview appears below.', 'edupro' ) ); ?>';
                    videoInput.placeholder    = val === 'pdf'
                        ? 'https://example.com/workbook.pdf'
                        : 'https://www.youtube.com/watch?v=...';
                    updatePreview();
                }
            });
        });

        var vTimer;
        if(videoInput){
            videoInput.addEventListener('input', function(){
                clearTimeout(vTimer);
                vTimer = setTimeout(updatePreview, 450);
            });
        }

        if(uploadBtn){
            uploadBtn.addEventListener('click', function(e){
                e.preventDefault();
                if(typeof wp === 'undefined' || !wp.media) return;
                var isPdf = getCurrentType() === 'pdf';
                var frame = wp.media({
                    title:    isPdf ? '<?php echo esc_js( __( 'Select PDF Document', 'edupro' ) ); ?>' : '<?php echo esc_js( __( 'Select Lesson Video', 'edupro' ) ); ?>',
                    button:   { text: isPdf ? '<?php echo esc_js( __( 'Use PDF', 'edupro' ) ); ?>' : '<?php echo esc_js( __( 'Use Video', 'edupro' ) ); ?>' },
                    multiple: false,
                    library:  { type: isPdf ? 'application/pdf' : 'video' }
                });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    videoInput.value = att.url;
                    updatePreview();
                });
                frame.open();
            });
        }
    })();
    </script>
    <?php
}

/* =========================================================================
   9. SHARED CLIENT-SIDE VIDEO EMBED HELPER (printed once in admin_footer)
   ========================================================================= */

add_action( 'admin_footer', 'edupro_metabox_shared_video_js' );
function edupro_metabox_shared_video_js() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, array( 'lms_course', 'lms_lesson' ), true ) ) {
        return;
    }
    ?>
    <script>
    window.eduproBuildVideoEmbed = function(url){
        if(!url) return '';
        var yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        if(yt){
            return '<div class="edupro-video-preview"><iframe src="https://www.youtube.com/embed/' + yt[1] + '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
        }
        var vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if(vimeo){
            return '<div class="edupro-video-preview"><iframe src="https://player.vimeo.com/video/' + vimeo[1] + '" allowfullscreen></iframe></div>';
        }
        if(/\.(mp4|webm|ogg|m4v)(\?.*)?$/i.test(url)){
            var safe = url.replace(/"/g, '&quot;');
            return '<div class="edupro-video-preview"><video controls preload="metadata" src="' + safe + '"></video></div>';
        }
        return '';
    };
    </script>
    <?php
}

/* =========================================================================
   10. SAVE_POST CALLBACKS (ONE PER META BOX, PROPER NONCE + SANITIZATION)
   ========================================================================= */

/**
 * Common guard for save_post callbacks.
 *
 * @param int    $post_id      Post ID.
 * @param string $nonce_field  $_POST key for nonce.
 * @param string $nonce_action Nonce action name.
 * @return bool
 */
function edupro_can_save_metabox( $post_id, $nonce_field, $nonce_action ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return false;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return false;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return false;
    }
    if ( empty( $_POST[ $nonce_field ] ) ) {
        return false;
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
    return (bool) wp_verify_nonce( $nonce, $nonce_action );
}

/* ---- 10a. Save Course Pricing ---- */
add_action( 'save_post_lms_course', 'edupro_save_pricing_metabox', 10, 1 );
function edupro_save_pricing_metabox( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_pricing_nonce', 'edupro_save_pricing' ) ) {
        return;
    }

    $regular_price = isset( $_POST['_edupro_regular_price'] ) && '' !== trim( (string) wp_unslash( $_POST['_edupro_regular_price'] ) )
        ? max( 0.0, edupro_sanitize_price( wp_unslash( $_POST['_edupro_regular_price'] ) ) )
        : '';

    $sale_price = isset( $_POST['_edupro_sale_price'] ) && '' !== trim( (string) wp_unslash( $_POST['_edupro_sale_price'] ) )
        ? max( 0.0, edupro_sanitize_price( wp_unslash( $_POST['_edupro_sale_price'] ) ) )
        : '';

    // Regional price overrides (empty string when blank so auto-conversion applies)
    $regional_keys = array(
        '_edupro_regular_price_gbp',
        '_edupro_sale_price_gbp',
        '_edupro_regular_price_inr',
        '_edupro_sale_price_inr',
    );
    foreach ( $regional_keys as $regional_key ) {
        if ( isset( $_POST[ $regional_key ] ) ) {
            $raw_val = trim( (string) wp_unslash( $_POST[ $regional_key ] ) );
            $val     = '' !== $raw_val ? max( 0.0, edupro_sanitize_price( $raw_val ) ) : '';
            update_post_meta( $post_id, $regional_key, $val );
        }
    }

    $sale_end_date = isset( $_POST['_edupro_sale_end_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['_edupro_sale_end_date'] ) )
        : '';
    if ( $sale_end_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sale_end_date ) ) {
        $sale_end_date = '';
    }

    $is_free          = isset( $_POST['_edupro_is_free'] ) ? '1' : '0';
    $enrollment_limit = isset( $_POST['_edupro_enrollment_limit'] ) ? absint( $_POST['_edupro_enrollment_limit'] ) : 0;

    update_post_meta( $post_id, '_edupro_regular_price', $regular_price );
    update_post_meta( $post_id, '_edupro_sale_price', $sale_price );
    update_post_meta( $post_id, '_edupro_sale_end_date', $sale_end_date );
    update_post_meta( $post_id, '_edupro_is_free', $is_free );
    update_post_meta( $post_id, '_edupro_enrollment_limit', $enrollment_limit );

    // Sync effective price & free flag for payment-gateways.php and student-auth.php compatibility
    $effective_price = 0.0;
    if ( '1' !== $is_free ) {
        $sale_active = ( '' !== $sale_price && (float) $sale_price >= 0 );
        if ( $sale_active && $sale_end_date && strtotime( $sale_end_date . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
            $sale_active = false;
        }
        $effective_price = $sale_active ? (float) $sale_price : (float) $regular_price;
    }
    update_post_meta( $post_id, '_edupro_course_price', $effective_price );
    update_post_meta( $post_id, '_edupro_course_free', ( '1' === $is_free || $effective_price <= 0 ) ? '1' : '0' );
}

/* ---- 10b. Save Course Details ---- */
add_action( 'save_post_lms_course', 'edupro_save_details_metabox', 10, 1 );
function edupro_save_details_metabox( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_details_nonce', 'edupro_save_details' ) ) {
        return;
    }

    $duration     = isset( $_POST['_edupro_duration'] ) ? absint( $_POST['_edupro_duration'] ) : 0;
    $language     = isset( $_POST['_edupro_language'] ) ? sanitize_text_field( wp_unslash( $_POST['_edupro_language'] ) ) : '';
    $requirements = isset( $_POST['_edupro_requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_edupro_requirements'] ) ) : '';
    $intro_video  = isset( $_POST['_edupro_course_intro_video'] ) ? esc_url_raw( wp_unslash( $_POST['_edupro_course_intro_video'] ) ) : '';
    $certificate  = isset( $_POST['_edupro_certificate'] ) ? '1' : '0';

    $allowed_levels = array( '', 'Beginner', 'Intermediate', 'Advanced', 'All Levels' );
    $course_level   = isset( $_POST['_edupro_course_level'] ) ? sanitize_text_field( wp_unslash( $_POST['_edupro_course_level'] ) ) : '';
    if ( ! in_array( $course_level, $allowed_levels, true ) ) {
        $course_level = '';
    }

    // What You'll Learn — clean newline-separated bullet list
    $what_you_learn = '';
    if ( isset( $_POST['_edupro_what_you_learn'] ) ) {
        $raw_learn = sanitize_textarea_field( wp_unslash( $_POST['_edupro_what_you_learn'] ) );
        $lines     = preg_split( '/\r\n|\r|\n/', $raw_learn );
        $clean     = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
        $what_you_learn = implode( "\n", $clean );
    }

    update_post_meta( $post_id, '_edupro_duration', $duration );
    update_post_meta( $post_id, '_edupro_course_level', $course_level );
    update_post_meta( $post_id, '_edupro_language', $language );
    update_post_meta( $post_id, '_edupro_requirements', $requirements );
    update_post_meta( $post_id, '_edupro_what_you_learn', $what_you_learn );
    update_post_meta( $post_id, '_edupro_course_intro_video', $intro_video );
    update_post_meta( $post_id, '_edupro_certificate', $certificate );
}

/* ---- 10c. Save Course Curriculum ---- */
add_action( 'save_post_lms_course', 'edupro_save_curriculum_metabox', 10, 1 );
function edupro_save_curriculum_metabox( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_curriculum_nonce', 'edupro_save_curriculum' ) ) {
        return;
    }

    if ( ! isset( $_POST['_edupro_curriculum'] ) ) {
        return;
    }

    $raw     = wp_unslash( $_POST['_edupro_curriculum'] );
    $decoded = json_decode( $raw, true );

    if ( ! is_array( $decoded ) ) {
        return;
    }

    $allowed_lesson_types = array( 'video', 'text', 'quiz', 'pdf' );
    $clean_sections       = array();

    foreach ( $decoded as $section ) {
        if ( ! is_array( $section ) ) {
            continue;
        }

        $clean_lessons = array();
        if ( ! empty( $section['lessons'] ) && is_array( $section['lessons'] ) ) {
            foreach ( $section['lessons'] as $lesson ) {
                if ( ! is_array( $lesson ) ) {
                    continue;
                }

                $l_type = isset( $lesson['type'] ) ? sanitize_key( $lesson['type'] ) : 'video';
                if ( ! in_array( $l_type, $allowed_lesson_types, true ) ) {
                    $l_type = 'video';
                }

                $raw_content = isset( $lesson['content'] ) ? (string) $lesson['content'] : '';
                if ( in_array( $l_type, array( 'video', 'pdf' ), true ) ) {
                    $l_content = esc_url_raw( $raw_content );
                } else {
                    $l_content = wp_kses_post( $raw_content );
                }

                $clean_lessons[] = array(
                    'title'      => sanitize_text_field( isset( $lesson['title'] ) ? $lesson['title'] : '' ),
                    'type'       => $l_type,
                    'content'    => $l_content,
                    'duration'   => isset( $lesson['duration'] ) && '' !== $lesson['duration'] ? absint( $lesson['duration'] ) : '',
                    'is_preview' => ! empty( $lesson['is_preview'] ),
                );
            }
        }

        $clean_sections[] = array(
            'title'       => sanitize_text_field( isset( $section['title'] ) ? $section['title'] : '' ),
            'description' => wp_kses_post( isset( $section['description'] ) ? $section['description'] : '' ),
            'lessons'     => $clean_lessons,
        );
    }

    update_post_meta( $post_id, '_edupro_curriculum', wp_json_encode( $clean_sections ) );
}

/* ---- 10d. Save Course SEO & Sharing ---- */
add_action( 'save_post_lms_course', 'edupro_save_seo_metabox', 10, 1 );
function edupro_save_seo_metabox( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_seo_nonce', 'edupro_save_seo' ) ) {
        return;
    }

    $meta_title = isset( $_POST['_edupro_meta_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['_edupro_meta_title'] ) )
        : '';
    $meta_desc  = isset( $_POST['_edupro_meta_description'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['_edupro_meta_description'] ) )
        : '';
    $og_image   = isset( $_POST['_edupro_og_image'] ) ? absint( $_POST['_edupro_og_image'] ) : 0;

    update_post_meta( $post_id, '_edupro_meta_title', $meta_title );
    update_post_meta( $post_id, '_edupro_meta_description', $meta_desc );
    update_post_meta( $post_id, '_edupro_og_image', $og_image );
}

/* ---- 10d-2. Save Course Page Extras, Gallery & FAQs ---- */
add_action( 'save_post_lms_course', 'edupro_save_course_page_extras_metabox', 10, 1 );
function edupro_save_course_page_extras_metabox( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_course_extras_nonce', 'edupro_save_course_extras' ) ) {
        return;
    }

    // Key Course Facts
    $text_keys = array(
        '_edupro_awarding_body',
        '_edupro_study_method',
        '_edupro_assessment_method',
        '_edupro_tutor_support',
        '_edupro_testimonial_caption',
    );
    foreach ( $text_keys as $key ) {
        $val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        update_post_meta( $post_id, $key, $val );
    }

    // Student Testimonial Video URL
    $testimonial_video = isset( $_POST['_edupro_testimonial_video'] )
        ? esc_url_raw( wp_unslash( $_POST['_edupro_testimonial_video'] ) )
        : '';
    update_post_meta( $post_id, '_edupro_testimonial_video', $testimonial_video );

    // Course Gallery Images (sanitized positive integer CSV)
    $gallery_csv = '';
    if ( isset( $_POST['_edupro_course_gallery'] ) ) {
        $raw_gallery = (string) wp_unslash( $_POST['_edupro_course_gallery'] );
        $ids         = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_gallery ) ) ) ) );
        $gallery_csv = implode( ',', $ids );
    }
    update_post_meta( $post_id, '_edupro_course_gallery', $gallery_csv );

    // Custom Course FAQs (JSON array of {q, a})
    $clean_faqs = array();
    if ( isset( $_POST['_edupro_course_faqs'] ) ) {
        $raw_faqs = (string) wp_unslash( $_POST['_edupro_course_faqs'] );
        $decoded  = json_decode( $raw_faqs, true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $faq ) {
                if ( ! is_array( $faq ) ) {
                    continue;
                }
                $q = sanitize_text_field( isset( $faq['q'] ) ? (string) $faq['q'] : '' );
                $a = wp_kses_post( isset( $faq['a'] ) ? (string) $faq['a'] : '' );
                if ( '' !== trim( $q ) || '' !== trim( wp_strip_all_tags( $a ) ) ) {
                    $clean_faqs[] = array(
                        'q' => $q,
                        'a' => $a,
                    );
                }
            }
        }
    }
    update_post_meta( $post_id, '_edupro_course_faqs', wp_json_encode( $clean_faqs ) );
}

/* ---- 10e. Save Lesson Details (lms_lesson) ---- */
add_action( 'save_post_lms_lesson', 'edupro_save_lesson_meta', 10, 1 );
function edupro_save_lesson_meta( $post_id ) {
    if ( ! edupro_can_save_metabox( $post_id, 'edupro_lesson_nonce', 'edupro_save_lesson' ) ) {
        return;
    }

    $allowed_types = array( 'video', 'text', 'pdf' );
    $lesson_type   = isset( $_POST['_edupro_lesson_type'] )
        ? sanitize_key( wp_unslash( $_POST['_edupro_lesson_type'] ) )
        : 'video';
    if ( ! in_array( $lesson_type, $allowed_types, true ) ) {
        $lesson_type = 'video';
    }

    $video_url = isset( $_POST['_edupro_video_url'] )
        ? esc_url_raw( wp_unslash( $_POST['_edupro_video_url'] ) )
        : '';

    $video_duration = isset( $_POST['_edupro_video_duration'] )
        ? max( 0, (float) wp_unslash( $_POST['_edupro_video_duration'] ) )
        : 0;

    $is_preview   = isset( $_POST['_edupro_is_preview'] ) ? '1' : '0';
    $lesson_order = isset( $_POST['_edupro_lesson_order'] ) ? absint( $_POST['_edupro_lesson_order'] ) : 0;
    $course_id    = isset( $_POST['_edupro_course_id'] ) ? absint( $_POST['_edupro_course_id'] ) : 0;

    update_post_meta( $post_id, '_edupro_lesson_type', $lesson_type );
    update_post_meta( $post_id, '_edupro_video_url', $video_url );
    update_post_meta( $post_id, '_edupro_video_duration', $video_duration );
    update_post_meta( $post_id, '_edupro_is_preview', $is_preview );
    update_post_meta( $post_id, '_edupro_lesson_order', $lesson_order );
    update_post_meta( $post_id, '_edupro_course_id', $course_id );
}

/* =========================================================================
   11. FRONT-END SEO TITLE & META TAGS OUTPUT
   ========================================================================= */

add_filter( 'pre_get_document_title', 'edupro_filter_course_document_title', 20 );
function edupro_filter_course_document_title( $title ) {
    if ( is_singular( 'lms_course' ) ) {
        $custom_title = get_post_meta( get_queried_object_id(), '_edupro_meta_title', true );
        if ( ! empty( $custom_title ) ) {
            return $custom_title;
        }
    }
    return $title;
}

add_action( 'wp_head', 'edupro_output_seo_tags', 2 );
function edupro_output_seo_tags() {
    if ( ! is_singular( 'lms_course' ) ) {
        return;
    }

    $post_id    = get_queried_object_id();
    $meta_title = get_post_meta( $post_id, '_edupro_meta_title', true );
    $meta_desc  = get_post_meta( $post_id, '_edupro_meta_description', true );
    $og_img_id  = (int) get_post_meta( $post_id, '_edupro_og_image', true );
    $og_img_url = $og_img_id
        ? wp_get_attachment_image_url( $og_img_id, 'large' )
        : get_the_post_thumbnail_url( $post_id, 'large' );

    $og_title = $meta_title ? $meta_title : get_the_title( $post_id );
    $og_desc  = $meta_desc ? $meta_desc : wp_strip_all_tags( get_the_excerpt( $post_id ) );

    if ( $meta_desc ) {
        echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
    }
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
    if ( $og_desc ) {
        echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '">' . "\n";
    if ( $og_img_url ) {
        echo '<meta property="og:image" content="' . esc_url( $og_img_url ) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
    if ( $og_desc ) {
        echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
    }
    if ( $og_img_url ) {
        echo '<meta name="twitter:image" content="' . esc_url( $og_img_url ) . '">' . "\n";
    }
}

/* =========================================================================
   12. ADMIN LIST TABLE COLUMNS FOR COURSES & LESSONS
   ========================================================================= */

add_filter( 'manage_lms_course_posts_columns', 'edupro_course_admin_columns' );
function edupro_course_admin_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['edupro_price']    = __( 'Price', 'edupro' );
            $new['edupro_level']    = __( 'Level', 'edupro' );
            $new['edupro_duration'] = __( 'Duration', 'edupro' );
        }
    }
    return $new;
}

add_action( 'manage_lms_course_posts_custom_column', 'edupro_course_admin_column_content', 10, 2 );
function edupro_course_admin_column_content( $column, $post_id ) {
    if ( 'edupro_price' === $column ) {
        $is_free = get_post_meta( $post_id, '_edupro_is_free', true );
        if ( '1' === (string) $is_free ) {
            echo '<span style="color:#047857;font-weight:700;">' . esc_html__( 'Free', 'edupro' ) . '</span>';
            return;
        }

        $base_currency = function_exists( 'edupro_get_default_currency' )
            ? strtoupper( (string) edupro_get_default_currency() )
            : strtoupper( (string) get_option( 'edupro_default_currency', 'INR' ) );
        if ( function_exists( 'edupro_get_default_currency' ) && function_exists( 'edupro_get_currency_symbol' ) ) {
            $base_symbol = edupro_get_currency_symbol( $base_currency );
        } else {
            $symbol_map  = array( 'GBP' => '£', 'USD' => '$', 'INR' => '₹' );
            $base_symbol = isset( $symbol_map[ $base_currency ] ) ? $symbol_map[ $base_currency ] : '₹';
        }

        $reg  = get_post_meta( $post_id, '_edupro_regular_price', true );
        $sale = get_post_meta( $post_id, '_edupro_sale_price', true );
        if ( '' !== $sale && (float) $sale >= 0 && ( '' === $reg || (float) $sale < (float) $reg ) ) {
            echo '<strong style="color:#e31b23;">' . esc_html( $base_symbol . number_format( (float) $sale, 2 ) ) . '</strong>';
            if ( '' !== $reg && (float) $reg > 0 ) {
                echo ' <del style="color:#9ca3af;">' . esc_html( $base_symbol . number_format( (float) $reg, 2 ) ) . '</del>';
            }
        } elseif ( '' !== $reg && (float) $reg > 0 ) {
            echo '<strong>' . esc_html( $base_symbol . number_format( (float) $reg, 2 ) ) . '</strong>';
        } else {
            echo '&mdash;';
        }

        // Regional override badges (UK GBP & India INR)
        $gbp_reg  = get_post_meta( $post_id, '_edupro_regular_price_gbp', true );
        $gbp_sale = get_post_meta( $post_id, '_edupro_sale_price_gbp', true );
        $inr_reg  = get_post_meta( $post_id, '_edupro_regular_price_inr', true );
        $inr_sale = get_post_meta( $post_id, '_edupro_sale_price_inr', true );

        $badges = array();
        if ( '' !== (string) $gbp_sale || '' !== (string) $gbp_reg ) {
            $gbp_eff  = '' !== (string) $gbp_sale ? (float) $gbp_sale : (float) $gbp_reg;
            $badges[] = '<span style="display:inline-block;margin:3px 4px 0 0;padding:1px 6px;border-radius:4px;font-size:11px;font-weight:600;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;">UK £' . esc_html( number_format( $gbp_eff, 2 ) ) . '</span>';
        }
        if ( '' !== (string) $inr_sale || '' !== (string) $inr_reg ) {
            $inr_eff  = '' !== (string) $inr_sale ? (float) $inr_sale : (float) $inr_reg;
            $badges[] = '<span style="display:inline-block;margin:3px 4px 0 0;padding:1px 6px;border-radius:4px;font-size:11px;font-weight:600;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;">IN ₹' . esc_html( number_format( $inr_eff, 2 ) ) . '</span>';
        }
        if ( ! empty( $badges ) ) {
            echo '<div style="margin-top:2px;">' . implode( '', $badges ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    } elseif ( 'edupro_level' === $column ) {
        $level = get_post_meta( $post_id, '_edupro_course_level', true );
        echo $level ? esc_html( $level ) : '&mdash;';
    } elseif ( 'edupro_duration' === $column ) {
        $mins = (int) get_post_meta( $post_id, '_edupro_duration', true );
        if ( $mins > 0 ) {
            echo esc_html( function_exists( 'edupro_get_duration_label' ) ? edupro_get_duration_label( $mins ) : $mins . ' min' );
        } else {
            echo '&mdash;';
        }
    }
}

add_filter( 'manage_lms_lesson_posts_columns', 'edupro_lesson_admin_columns' );
function edupro_lesson_admin_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['edupro_course']  = __( 'Course', 'edupro' );
            $new['edupro_type']    = __( 'Type', 'edupro' );
            $new['edupro_order']   = __( 'Order', 'edupro' );
            $new['edupro_preview'] = __( 'Preview', 'edupro' );
        }
    }
    return $new;
}

add_action( 'manage_lms_lesson_posts_custom_column', 'edupro_lesson_admin_column_content', 10, 2 );
function edupro_lesson_admin_column_content( $column, $post_id ) {
    if ( 'edupro_course' === $column ) {
        $cid = (int) get_post_meta( $post_id, '_edupro_course_id', true );
        if ( $cid ) {
            echo '<a href="' . esc_url( get_edit_post_link( $cid ) ) . '">' . esc_html( get_the_title( $cid ) ) . '</a>';
        } else {
            echo '&mdash;';
        }
    } elseif ( 'edupro_type' === $column ) {
        $type = get_post_meta( $post_id, '_edupro_lesson_type', true ) ?: 'video';
        echo '<strong>' . esc_html( strtoupper( $type ) ) . '</strong>';
    } elseif ( 'edupro_order' === $column ) {
        $order = get_post_meta( $post_id, '_edupro_lesson_order', true );
        echo '' !== $order ? (int) $order : '&mdash;';
    } elseif ( 'edupro_preview' === $column ) {
        $prev = get_post_meta( $post_id, '_edupro_is_preview', true );
        echo '1' === (string) $prev
            ? '<span style="color:#047857;font-weight:700;">' . esc_html__( 'Free Preview', 'edupro' ) . '</span>'
            : '&mdash;';
    }
}

/* =========================================================================
   13. HELPERS: VIDEO EMBED & PRICE SANITIZER FALLBACK
   ========================================================================= */

/**
 * Renders an admin/front-end responsive video embed for YouTube, Vimeo, or MP4/WebM/Ogg.
 *
 * @param string $url Video URL.
 * @return string HTML embed markup.
 */
function edupro_build_video_embed( $url ) {
    if ( empty( $url ) ) {
        return '';
    }

    // YouTube (watch, youtu.be, embed, shorts)
    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $m ) ) {
        $embed_url = 'https://www.youtube.com/embed/' . $m[1];
        return '<div class="edupro-video-preview"><iframe src="' . esc_url( $embed_url ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>';
    }

    // Vimeo
    if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m ) ) {
        $embed_url = 'https://player.vimeo.com/video/' . $m[1];
        return '<div class="edupro-video-preview"><iframe src="' . esc_url( $embed_url ) . '" allowfullscreen loading="lazy"></iframe></div>';
    }

    // Self-hosted HTML5 video
    if ( preg_match( '/\.(mp4|webm|ogg|m4v)(\?.*)?$/i', $url ) ) {
        return '<div class="edupro-video-preview"><video controls preload="metadata" src="' . esc_url( $url ) . '"></video></div>';
    }

    return '';
}

if ( ! function_exists( 'edupro_sanitize_price' ) ) {
    function edupro_sanitize_price( $price ) {
        $sanitized = preg_replace( '/[^0-9.]/', '', (string) $price );
        $parts     = explode( '.', $sanitized );
        if ( count( $parts ) > 2 ) {
            $sanitized = $parts[0] . '.' . implode( '', array_slice( $parts, 1 ) );
        }
        return (float) $sanitized;
    }
}
