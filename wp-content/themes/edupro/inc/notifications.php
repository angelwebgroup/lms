<?php
/**
 * Notifications Custom Post Type, Countdown Metabox & Top Slider Bar
 *
 * Manages promotional and informational top-bar notifications (`lms_notification`)
 * with optional fixed or evergreen rolling countdown timers and multi-slide rotation.
 *
 * @package EduPro
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * 1. CUSTOM POST TYPE: lms_notification
 * ========================================================================== */

/**
 * Register the `lms_notification` Custom Post Type.
 */
function edupro_register_notification_post_type() {
    $labels = array(
        'name'                  => _x( 'Notifications', 'post type general name', 'edupro' ),
        'singular_name'         => _x( 'Notification', 'post type singular name', 'edupro' ),
        'menu_name'             => _x( 'Notifications', 'admin menu', 'edupro' ),
        'name_admin_bar'        => _x( 'Notification', 'add new on admin bar', 'edupro' ),
        'add_new'               => _x( 'Add New', 'notification', 'edupro' ),
        'add_new_item'          => __( 'Add New Notification', 'edupro' ),
        'new_item'              => __( 'New Notification', 'edupro' ),
        'edit_item'             => __( 'Edit Notification', 'edupro' ),
        'view_item'             => __( 'View Notification', 'edupro' ),
        'all_items'             => __( 'All Notifications', 'edupro' ),
        'search_items'          => __( 'Search Notifications', 'edupro' ),
        'not_found'             => __( 'No notifications found.', 'edupro' ),
        'not_found_in_trash'    => __( 'No notifications found in Trash.', 'edupro' ),
        'items_list'            => __( 'Notifications list', 'edupro' ),
        'items_list_navigation' => __( 'Notifications list navigation', 'edupro' ),
        'filter_items_list'     => __( 'Filter notifications list', 'edupro' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => false,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 8,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array( 'title', 'page-attributes' ),
    );

    register_post_type( 'lms_notification', $args );
}
add_action( 'init', 'edupro_register_notification_post_type', 0 );

/* ==========================================================================
 * 2. METABOX: Notification Banner Settings
 * ========================================================================== */

/**
 * Register the Notification Banner Settings metabox on `lms_notification`.
 */
function edupro_register_notification_metabox() {
    add_meta_box(
        'edupro_notification_details',
        __( 'Notification Banner Settings', 'edupro' ),
        'edupro_render_notification_metabox',
        'lms_notification',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'edupro_register_notification_metabox' );

/**
 * Render the Notification Banner Settings metabox UI.
 *
 * @param WP_Post $post Current post object.
 */
function edupro_render_notification_metabox( $post ) {
    wp_nonce_field( 'edupro_save_notification_meta', 'edupro_notification_nonce' );

    $active_meta   = get_post_meta( $post->ID, '_edupro_notif_active', true );
    // Default new notifications to Active ('1').
    $is_active     = ( '' === $active_meta ) ? '1' : (string) $active_meta;
    $badge         = (string) get_post_meta( $post->ID, '_edupro_notif_badge', true );
    $badge_style   = (string) get_post_meta( $post->ID, '_edupro_notif_badge_style', true );
    if ( ! in_array( $badge_style, array( 'crimson', 'gold', 'emerald' ), true ) ) {
        $badge_style = 'crimson';
    }
    $prefix        = (string) get_post_meta( $post->ID, '_edupro_notif_prefix', true );
    $highlight     = (string) get_post_meta( $post->ID, '_edupro_notif_highlight', true );
    $suffix        = (string) get_post_meta( $post->ID, '_edupro_notif_suffix', true );
    $link          = (string) get_post_meta( $post->ID, '_edupro_notif_link', true );
    $countdown_end = (string) get_post_meta( $post->ID, '_edupro_notif_countdown_end', true );
    $rolling_hours = get_post_meta( $post->ID, '_edupro_notif_rolling_hours', true );
    $rolling_hours = ( '' !== $rolling_hours ) ? absint( $rolling_hours ) : '';
    ?>
    <style>
        .lms-notif-mb-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 20px; margin-top: 8px; }
        .lms-notif-mb-field { display: flex; flex-direction: column; gap: 6px; }
        .lms-notif-mb-field--full { grid-column: 1 / -1; }
        .lms-notif-mb-field label { font-weight: 600; color: #1e293b; font-size: 13px; }
        .lms-notif-mb-field input[type="text"],
        .lms-notif-mb-field input[type="url"],
        .lms-notif-mb-field input[type="number"],
        .lms-notif-mb-field input[type="datetime-local"],
        .lms-notif-mb-field select { width: 100%; max-width: 100%; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; }
        .lms-notif-mb-desc { margin: 0; font-size: 12px; color: #64748b; }
        .lms-notif-mb-toggle { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; color: #0f172a; }
        .lms-notif-mb-preview {
            margin-top: 18px;
            padding: 12px 18px;
            border-radius: 10px;
            background: linear-gradient(90deg, #1a050c 0%, #440b1e 50%, #1a050c 100%);
            color: #ffffff;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 13px;
        }
        .lms-notif-mb-preview__badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px; font-weight: 700; font-size: 11px;
            letter-spacing: 0.04em; text-transform: uppercase;
            border: 1px solid rgba(255,255,255,0.35);
            background: #be123c; color: #ffffff;
        }
        .lms-notif-mb-preview__badge--gold { background: #b45309; color: #fef3c7; }
        .lms-notif-mb-preview__badge--emerald { background: #047857; color: #ecfdf5; }
        .lms-notif-mb-preview__hl { color: #fbbf24; font-weight: 800; text-decoration: underline; text-underline-offset: 3px; }
        .lms-notif-mb-preview__timer {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.22);
            border-radius: 999px; padding: 3px 10px; color: #fde68a; font-family: monospace; font-size: 12px;
        }
        @media (max-width: 782px) { .lms-notif-mb-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="lms-notif-mb-grid">
        <div class="lms-notif-mb-field lms-notif-mb-field--full">
            <label class="lms-notif-mb-toggle">
                <input type="checkbox" name="edupro_notif_active" value="1" <?php checked( $is_active, '1' ); ?> />
                <span><?php esc_html_e( 'Active — Display this notification in the top website slider bar', 'edupro' ); ?></span>
            </label>
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_badge"><?php esc_html_e( 'Badge Text (Optional)', 'edupro' ); ?></label>
            <input type="text" id="edupro_notif_badge" name="edupro_notif_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'e.g. BLACK FRIDAY SALE', 'edupro' ); ?>" />
            <p class="lms-notif-mb-desc"><?php esc_html_e( 'Displayed as a glowing pill badge with a lightning icon on the left.', 'edupro' ); ?></p>
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_badge_style"><?php esc_html_e( 'Badge Color Style', 'edupro' ); ?></label>
            <select id="edupro_notif_badge_style" name="edupro_notif_badge_style">
                <option value="crimson" <?php selected( $badge_style, 'crimson' ); ?>><?php esc_html_e( 'Crimson (Signature Red)', 'edupro' ); ?></option>
                <option value="gold" <?php selected( $badge_style, 'gold' ); ?>><?php esc_html_e( 'Gold (VIP / Limited Offer)', 'edupro' ); ?></option>
                <option value="emerald" <?php selected( $badge_style, 'emerald' ); ?>><?php esc_html_e( 'Emerald (New / Accredited)', 'edupro' ); ?></option>
            </select>
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_prefix"><?php esc_html_e( 'Message Prefix', 'edupro' ); ?></label>
            <input type="text" id="edupro_notif_prefix" name="edupro_notif_prefix" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'e.g. Up to', 'edupro' ); ?>" />
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_highlight"><?php esc_html_e( 'Gold Underlined Highlight', 'edupro' ); ?></label>
            <input type="text" id="edupro_notif_highlight" name="edupro_notif_highlight" value="<?php echo esc_attr( $highlight ); ?>" placeholder="<?php esc_attr_e( 'e.g. 80% Off', 'edupro' ); ?>" />
            <p class="lms-notif-mb-desc"><?php esc_html_e( 'Rendered in bold underlined gold for high visual impact.', 'edupro' ); ?></p>
        </div>

        <div class="lms-notif-mb-field lms-notif-mb-field--full">
            <label for="edupro_notif_suffix"><?php esc_html_e( 'Message Suffix', 'edupro' ); ?></label>
            <input type="text" id="edupro_notif_suffix" name="edupro_notif_suffix" value="<?php echo esc_attr( $suffix ); ?>" placeholder="<?php esc_attr_e( 'e.g. All Accredited CPD & QLS Endorsed Courses', 'edupro' ); ?>" />
        </div>

        <div class="lms-notif-mb-field lms-notif-mb-field--full">
            <label for="edupro_notif_link"><?php esc_html_e( 'Click-Through Link URL (Optional)', 'edupro' ); ?></label>
            <input type="url" id="edupro_notif_link" name="edupro_notif_link" value="<?php echo esc_url( $link ); ?>" placeholder="<?php echo esc_attr( home_url( '/courses/' ) ); ?>" />
            <p class="lms-notif-mb-desc"><?php esc_html_e( 'Makes the entire notification slide clickable.', 'edupro' ); ?></p>
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_countdown_end"><?php esc_html_e( 'Fixed Countdown End Date & Time (Optional)', 'edupro' ); ?></label>
            <input type="datetime-local" id="edupro_notif_countdown_end" name="edupro_notif_countdown_end" value="<?php echo esc_attr( $countdown_end ); ?>" />
            <p class="lms-notif-mb-desc"><?php esc_html_e( 'Automatically hides this notification once the fixed end time passes.', 'edupro' ); ?></p>
        </div>

        <div class="lms-notif-mb-field">
            <label for="edupro_notif_rolling_hours"><?php esc_html_e( 'Evergreen Rolling Countdown (Hours)', 'edupro' ); ?></label>
            <input type="number" id="edupro_notif_rolling_hours" name="edupro_notif_rolling_hours" value="<?php echo esc_attr( (string) $rolling_hours ); ?>" min="0" max="720" step="1" placeholder="<?php esc_attr_e( 'e.g. 14', 'edupro' ); ?>" />
            <p class="lms-notif-mb-desc"><?php esc_html_e( 'Used when Fixed End Date is blank. Resets smoothly per visitor when reaching 0.', 'edupro' ); ?></p>
        </div>
    </div>

    <div class="lms-notif-mb-preview" aria-label="<?php esc_attr_e( 'Banner Preview', 'edupro' ); ?>">
        <?php if ( '' !== $badge ) : ?>
            <span class="lms-notif-mb-preview__badge lms-notif-mb-preview__badge--<?php echo esc_attr( $badge_style ); ?>">
                &#9889; <?php echo esc_html( $badge ); ?>
            </span>
        <?php endif; ?>
        <span>
            <?php echo esc_html( $prefix ); ?>
            <?php if ( '' !== $highlight ) : ?>
                <strong class="lms-notif-mb-preview__hl"><?php echo esc_html( $highlight ); ?></strong>
            <?php endif; ?>
            <?php echo esc_html( $suffix ); ?>
            <?php if ( '' === $prefix && '' === $highlight && '' === $suffix ) : ?>
                <?php echo esc_html( $post->post_title ? $post->post_title : __( 'Enter prefix, highlight, and suffix above to preview.', 'edupro' ) ); ?>
            <?php endif; ?>
        </span>
        <?php if ( '' !== $countdown_end || ( is_numeric( $rolling_hours ) && (int) $rolling_hours > 0 ) ) : ?>
            <span class="lms-notif-mb-preview__timer">
                <?php
                if ( '' !== $countdown_end ) {
                    echo esc_html( sprintf( __( 'Ends %s', 'edupro' ), str_replace( 'T', ' ', $countdown_end ) ) );
                } else {
                    echo esc_html( sprintf( __( 'Ends in %02dh : 00m : 00s', 'edupro' ), (int) $rolling_hours ) );
                }
                ?>
            </span>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Save handler for `lms_notification` metabox fields.
 *
 * @param int $post_id Saved post ID.
 */
function edupro_save_notification_meta( $post_id ) {
    if ( ! isset( $_POST['edupro_notification_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_notification_nonce'] ) ), 'edupro_save_notification_meta' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $is_active = ! empty( $_POST['edupro_notif_active'] ) ? '1' : '0';
    update_post_meta( $post_id, '_edupro_notif_active', $is_active );

    $badge = isset( $_POST['edupro_notif_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_notif_badge'] ) ) : '';
    update_post_meta( $post_id, '_edupro_notif_badge', $badge );

    $badge_style = isset( $_POST['edupro_notif_badge_style'] ) ? sanitize_key( wp_unslash( $_POST['edupro_notif_badge_style'] ) ) : 'crimson';
    if ( ! in_array( $badge_style, array( 'crimson', 'gold', 'emerald' ), true ) ) {
        $badge_style = 'crimson';
    }
    update_post_meta( $post_id, '_edupro_notif_badge_style', $badge_style );

    $prefix    = isset( $_POST['edupro_notif_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_notif_prefix'] ) ) : '';
    $highlight = isset( $_POST['edupro_notif_highlight'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_notif_highlight'] ) ) : '';
    $suffix    = isset( $_POST['edupro_notif_suffix'] ) ? sanitize_text_field( wp_unslash( $_POST['edupro_notif_suffix'] ) ) : '';
    update_post_meta( $post_id, '_edupro_notif_prefix', $prefix );
    update_post_meta( $post_id, '_edupro_notif_highlight', $highlight );
    update_post_meta( $post_id, '_edupro_notif_suffix', $suffix );

    $link = isset( $_POST['edupro_notif_link'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['edupro_notif_link'] ) ) ) : '';
    update_post_meta( $post_id, '_edupro_notif_link', $link );

    // Validate datetime-local format YYYY-MM-DDTHH:MM (or optional :SS).
    $raw_end = isset( $_POST['edupro_notif_countdown_end'] ) ? sanitize_text_field( trim( (string) wp_unslash( $_POST['edupro_notif_countdown_end'] ) ) ) : '';
    if ( '' !== $raw_end && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $raw_end ) ) {
        update_post_meta( $post_id, '_edupro_notif_countdown_end', $raw_end );
    } else {
        update_post_meta( $post_id, '_edupro_notif_countdown_end', '' );
    }

    $raw_rolling   = isset( $_POST['edupro_notif_rolling_hours'] ) ? trim( (string) wp_unslash( $_POST['edupro_notif_rolling_hours'] ) ) : '';
    $rolling_hours = ( '' !== $raw_rolling ) ? min( 720, absint( $raw_rolling ) ) : 0;
    update_post_meta( $post_id, '_edupro_notif_rolling_hours', $rolling_hours > 0 ? $rolling_hours : '' );
}
add_action( 'save_post_lms_notification', 'edupro_save_notification_meta' );

/* ==========================================================================
 * 3. ADMIN LIST TABLE COLUMNS
 * ========================================================================== */

/**
 * Define custom admin columns for the `lms_notification` list table.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function edupro_notification_admin_columns( $columns ) {
    return array(
        'cb'              => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
        'title'           => __( 'Title', 'edupro' ),
        'notif_status'    => __( 'Status', 'edupro' ),
        'notif_badge'     => __( 'Badge', 'edupro' ),
        'notif_preview'   => __( 'Preview Message', 'edupro' ),
        'notif_countdown' => __( 'Countdown', 'edupro' ),
        'notif_order'     => __( 'Order', 'edupro' ),
        'date'            => isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'edupro' ),
    );
}
add_filter( 'manage_lms_notification_posts_columns', 'edupro_notification_admin_columns' );

/**
 * Populate custom admin columns for `lms_notification`.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function edupro_notification_admin_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'notif_status':
            $active = (string) get_post_meta( $post_id, '_edupro_notif_active', true );
            $end    = (string) get_post_meta( $post_id, '_edupro_notif_countdown_end', true );
            $is_exp = false;
            if ( '' !== $end ) {
                $end_ts = edupro_parse_notification_end_timestamp( $end );
                if ( $end_ts && $end_ts <= time() ) {
                    $is_exp = true;
                }
            }

            if ( $is_exp ) {
                echo '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;">' . esc_html__( 'Expired', 'edupro' ) . '</span>';
            } elseif ( '1' === $active ) {
                echo '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;">' . esc_html__( 'Active', 'edupro' ) . '</span>';
            } else {
                echo '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;">' . esc_html__( 'Paused', 'edupro' ) . '</span>';
            }
            break;

        case 'notif_badge':
            $badge = (string) get_post_meta( $post_id, '_edupro_notif_badge', true );
            $style = (string) get_post_meta( $post_id, '_edupro_notif_badge_style', true );
            if ( '' === $badge ) {
                echo '&mdash;';
            } else {
                $bg = ( 'gold' === $style ) ? '#b45309' : ( ( 'emerald' === $style ) ? '#047857' : '#be123c' );
                echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:' . esc_attr( $bg ) . ';color:#ffffff;">' . esc_html( $badge ) . '</span>';
            }
            break;

        case 'notif_preview':
            $prefix    = (string) get_post_meta( $post_id, '_edupro_notif_prefix', true );
            $highlight = (string) get_post_meta( $post_id, '_edupro_notif_highlight', true );
            $suffix    = (string) get_post_meta( $post_id, '_edupro_notif_suffix', true );
            if ( '' === $prefix && '' === $highlight && '' === $suffix ) {
                echo esc_html( get_the_title( $post_id ) );
            } else {
                echo esc_html( $prefix );
                if ( '' !== $highlight ) {
                    echo ' <strong style="color:#be123c;text-decoration:underline;">' . esc_html( $highlight ) . '</strong> ';
                }
                echo esc_html( $suffix );
            }
            break;

        case 'notif_countdown':
            $end     = (string) get_post_meta( $post_id, '_edupro_notif_countdown_end', true );
            $rolling = absint( get_post_meta( $post_id, '_edupro_notif_rolling_hours', true ) );
            if ( '' !== $end ) {
                echo esc_html( str_replace( 'T', ' ', $end ) );
            } elseif ( $rolling > 0 ) {
                echo esc_html( sprintf( __( 'Rolling %dh', 'edupro' ), $rolling ) );
            } else {
                echo '&mdash;';
            }
            break;

        case 'notif_order':
            $post_obj = get_post( $post_id );
            echo esc_html( (string) ( $post_obj ? (int) $post_obj->menu_order : 0 ) );
            break;
    }
}
add_action( 'manage_lms_notification_posts_custom_column', 'edupro_notification_admin_column_content', 10, 2 );

/**
 * Make the Order column sortable in the Notifications list table.
 *
 * @param array<string, string> $columns Sortable columns.
 * @return array<string, string>
 */
function edupro_notification_sortable_columns( $columns ) {
    $columns['notif_order'] = 'menu_order';
    return $columns;
}
add_filter( 'manage_edit-lms_notification_sortable_columns', 'edupro_notification_sortable_columns' );

/* ==========================================================================
 * 4. FRONTEND HELPERS & RENDERER
 * ========================================================================== */

/**
 * Parse a stored `datetime-local` string using the WordPress site timezone
 * into a UTC Unix timestamp.
 *
 * @param string $datetime_local String like `2026-12-31T23:59`.
 * @return int Unix timestamp or 0 if invalid.
 */
function edupro_parse_notification_end_timestamp( $datetime_local ) {
    $datetime_local = trim( (string) $datetime_local );
    if ( '' === $datetime_local ) {
        return 0;
    }

    try {
        $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $dt = new DateTimeImmutable( $datetime_local, $tz );
        return (int) $dt->getTimestamp();
    } catch ( Exception $e ) {
        $ts = strtotime( $datetime_local );
        return $ts ? (int) $ts : 0;
    }
}

/**
 * Format remaining seconds into a crisp countdown string (`14h : 00m : 00s` or `2d 05h : 12m : 08s`).
 *
 * @param int $seconds Remaining seconds.
 * @return string
 */
function edupro_format_notification_countdown( $seconds ) {
    $seconds = max( 0, (int) $seconds );
    $days    = (int) floor( $seconds / DAY_IN_SECONDS );
    $hours   = (int) floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
    $minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
    $secs    = (int) ( $seconds % MINUTE_IN_SECONDS );

    if ( $days > 0 ) {
        return sprintf( '%dd %02dh : %02dm : %02ds', $days, $hours, $minutes, $secs );
    }

    return sprintf( '%02dh : %02dm : %02ds', $hours, $minutes, $secs );
}

/**
 * Query active, non-expired `lms_notification` posts ordered by `menu_order ASC, date DESC`.
 *
 * @return array<int, array<string, mixed>>
 */
function edupro_get_active_notifications() {
    $query = new WP_Query(
        array(
            'post_type'              => 'lms_notification',
            'post_status'            => 'publish',
            'posts_per_page'         => 20,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'orderby'                => array(
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ),
            'meta_query'             => array(
                array(
                    'key'     => '_edupro_notif_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        )
    );

    if ( ! $query->have_posts() ) {
        return array();
    }

    $now   = time();
    $tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
    $items = array();

    foreach ( $query->posts as $post ) {
        $post_id   = (int) $post->ID;
        $raw_end   = (string) get_post_meta( $post_id, '_edupro_notif_countdown_end', true );
        $end_ts    = 0;
        $end_iso   = '';

        if ( '' !== $raw_end ) {
            $end_ts = edupro_parse_notification_end_timestamp( $raw_end );
            if ( $end_ts && $end_ts <= $now ) {
                // Skip expired fixed-date notification.
                continue;
            }
            if ( $end_ts ) {
                try {
                    $dt      = ( new DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tz );
                    $end_iso = $dt->format( DateTimeInterface::ATOM );
                } catch ( Exception $e ) {
                    $end_iso = gmdate( 'c', $end_ts );
                }
            }
        }

        $badge_style = (string) get_post_meta( $post_id, '_edupro_notif_badge_style', true );
        if ( ! in_array( $badge_style, array( 'crimson', 'gold', 'emerald' ), true ) ) {
            $badge_style = 'crimson';
        }

        $rolling_hours = absint( get_post_meta( $post_id, '_edupro_notif_rolling_hours', true ) );
        $prefix        = (string) get_post_meta( $post_id, '_edupro_notif_prefix', true );
        $highlight     = (string) get_post_meta( $post_id, '_edupro_notif_highlight', true );
        $suffix        = (string) get_post_meta( $post_id, '_edupro_notif_suffix', true );

        // Fallback to post title if prefix/highlight/suffix are all empty.
        if ( '' === $prefix && '' === $highlight && '' === $suffix ) {
            $suffix = (string) get_the_title( $post_id );
        }

        $items[] = array(
            'id'               => $post_id,
            'title'            => (string) get_the_title( $post_id ),
            'badge'            => (string) get_post_meta( $post_id, '_edupro_notif_badge', true ),
            'badge_style'      => $badge_style,
            'prefix'           => $prefix,
            'highlight'        => $highlight,
            'suffix'           => $suffix,
            'link'             => (string) get_post_meta( $post_id, '_edupro_notif_link', true ),
            'countdown_end'    => $end_iso,
            'countdown_end_ts' => $end_ts,
            'rolling_hours'    => ( '' === $end_iso && $rolling_hours > 0 ) ? $rolling_hours : 0,
        );
    }

    return $items;
}

/**
 * Render the top-bar Notification Slider with live countdown timers,
 * Currency Switcher, and dismiss button.
 *
 * Falls back cleanly to Website Settings announcement text if no active
 * `lms_notification` posts exist yet.
 *
 * @return string HTML markup.
 */
function edupro_render_notification_bar() {
    // Respect Website Settings master announcement toggle.
    if ( function_exists( 'edupro_get_site_setting' ) && '1' !== (string) edupro_get_site_setting( 'announcement_enabled', '1' ) ) {
        return '';
    }

    $items = edupro_get_active_notifications();

    // Graceful fallback to Website Settings announcement copy when no CPT items exist.
    if ( empty( $items ) ) {
        $fallback_text = function_exists( 'edupro_get_site_setting' )
            ? (string) edupro_get_site_setting( 'announcement_text', '' )
            : '';
        $fallback_text = (string) apply_filters( 'edupro_announcement_bar_text', $fallback_text );

        if ( '' === trim( $fallback_text ) ) {
            return '';
        }

        $fallback_cta_text = function_exists( 'edupro_get_site_setting' )
            ? (string) edupro_get_site_setting( 'announcement_cta_text', '' )
            : '';
        $fallback_cta_url  = function_exists( 'edupro_get_site_setting' )
            ? (string) edupro_get_site_setting( 'announcement_cta_url', home_url( '/courses/' ) )
            : home_url( '/courses/' );

        $items[] = array(
            'id'               => 0,
            'title'            => $fallback_text,
            'badge'            => '',
            'badge_style'      => 'crimson',
            'prefix'           => $fallback_text,
            'highlight'        => $fallback_cta_text,
            'suffix'           => '',
            'link'             => $fallback_cta_url,
            'countdown_end'    => '',
            'countdown_end_ts' => 0,
            'rolling_hours'    => 0,
        );
    }

    // Prepare currency switcher items.
    $active_currency = function_exists( 'edupro_get_active_currency' )
        ? edupro_get_active_currency()
        : ( get_option( 'edupro_default_currency', 'INR' ) ?: 'INR' );

    $supported_currencies = function_exists( 'edupro_get_supported_currencies' )
        ? edupro_get_supported_currencies()
        : array(
            'INR' => array( 'code' => 'INR', 'symbol' => '₹', 'label' => 'India (INR)' ),
            'GBP' => array( 'code' => 'GBP', 'symbol' => '£', 'label' => 'UK (GBP)' ),
            'USD' => array( 'code' => 'USD', 'symbol' => '$', 'label' => 'Global (USD)' ),
        );

    $currency_items = array();
    foreach ( $supported_currencies as $curr_key => $curr_val ) {
        $code = strtoupper( is_array( $curr_val ) && ! empty( $curr_val['code'] ) ? $curr_val['code'] : ( is_string( $curr_key ) ? $curr_key : (string) $curr_val ) );
        if ( is_array( $curr_val ) && ! empty( $curr_val['symbol'] ) ) {
            $symbol = $curr_val['symbol'];
        } elseif ( function_exists( 'edupro_get_currency_symbol' ) ) {
            $symbol = edupro_get_currency_symbol( $code );
        } else {
            $symbol = ( 'GBP' === $code ) ? '£' : ( ( 'USD' === $code ) ? '$' : '₹' );
        }
        $label                   = ( is_array( $curr_val ) && ! empty( $curr_val['label'] ) ) ? $curr_val['label'] : $code;
        $currency_items[ $code ] = array(
            'code'   => $code,
            'symbol' => $symbol,
            'label'  => $label,
        );
    }

    $has_multiple = count( $items ) > 1;
    $now          = time();

    ob_start();
    ?>
    <div id="edupro-announcement-bar" class="edupro-notif-bar edupro-announcement-bar top-bar" role="region" aria-label="<?php esc_attr_e( 'Site Announcements', 'edupro' ); ?>">
        <div class="edupro-container container edupro-notif-bar__inner">

            <div class="edupro-notif-bar__center">
                <?php if ( $has_multiple ) : ?>
                    <button type="button" class="edupro-notif-nav edupro-notif-nav--prev" aria-label="<?php esc_attr_e( 'Previous announcement', 'edupro' ); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                <?php endif; ?>

                <div class="edupro-notif-slider" data-notif-count="<?php echo esc_attr( (string) count( $items ) ); ?>">
                    <?php foreach ( $items as $idx => $item ) : ?>
                        <?php
                        $is_first     = ( 0 === $idx );
                        $has_link     = ! empty( $item['link'] );
                        $has_fixed    = ! empty( $item['countdown_end'] ) && ! empty( $item['countdown_end_ts'] );
                        $has_rolling  = ! $has_fixed && ! empty( $item['rolling_hours'] );
                        $has_timer    = $has_fixed || $has_rolling;
                        $initial_time = '';

                        if ( $has_fixed ) {
                            $initial_time = edupro_format_notification_countdown( max( 0, $item['countdown_end_ts'] - $now ) );
                        } elseif ( $has_rolling ) {
                            $initial_time = edupro_format_notification_countdown( (int) $item['rolling_hours'] * HOUR_IN_SECONDS );
                        }
                        ?>
                        <div
                            class="edupro-notif-slide <?php echo $is_first ? 'is-active' : ''; ?>"
                            data-notif-index="<?php echo esc_attr( (string) $idx ); ?>"
                            aria-hidden="<?php echo $is_first ? 'false' : 'true'; ?>"
                        >
                            <?php if ( $has_link ) : ?>
                                <a href="<?php echo esc_url( $item['link'] ); ?>" class="edupro-notif-slide__link">
                            <?php else : ?>
                                <div class="edupro-notif-slide__inner">
                            <?php endif; ?>

                                <?php if ( '' !== $item['badge'] ) : ?>
                                    <span class="edupro-notif-badge edupro-notif-badge--<?php echo esc_attr( $item['badge_style'] ); ?>">
                                        <svg class="edupro-notif-badge__icon" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                                        <span><?php echo esc_html( $item['badge'] ); ?></span>
                                    </span>
                                <?php endif; ?>

                                <span class="edupro-notif-copy">
                                    <?php if ( '' !== $item['prefix'] ) : ?>
                                        <span class="edupro-notif-prefix"><?php echo esc_html( $item['prefix'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( '' !== $item['highlight'] ) : ?>
                                        <strong class="edupro-notif-highlight"><?php echo esc_html( $item['highlight'] ); ?></strong>
                                    <?php endif; ?>
                                    <?php if ( '' !== $item['suffix'] ) : ?>
                                        <span class="edupro-notif-suffix"><?php echo esc_html( $item['suffix'] ); ?></span>
                                    <?php endif; ?>
                                </span>

                                <?php if ( $has_timer ) : ?>
                                    <span
                                        class="edupro-notif-countdown"
                                        data-notif-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
                                        <?php if ( $has_fixed ) : ?>
                                            data-countdown-end="<?php echo esc_attr( $item['countdown_end'] ); ?>"
                                        <?php else : ?>
                                            data-rolling-hours="<?php echo esc_attr( (string) $item['rolling_hours'] ); ?>"
                                        <?php endif; ?>
                                    >
                                        <span class="edupro-notif-countdown__label"><?php esc_html_e( 'Ends in', 'edupro' ); ?></span>
                                        <span class="edupro-notif-countdown__time"><?php echo esc_html( $initial_time ); ?></span>
                                    </span>
                                <?php endif; ?>

                            <?php if ( $has_link ) : ?>
                                </a>
                            <?php else : ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( $has_multiple ) : ?>
                    <button type="button" class="edupro-notif-nav edupro-notif-nav--next" aria-label="<?php esc_attr_e( 'Next announcement', 'edupro' ); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                <?php endif; ?>
            </div>

            <div class="edupro-notif-bar__right edupro-announcement-bar__right">
                <?php if ( ! empty( $currency_items ) ) : ?>
                    <div class="edupro-currency-switcher" role="group" aria-label="<?php esc_attr_e( 'Switch currency', 'edupro' ); ?>">
                        <?php foreach ( $currency_items as $curr_item ) : ?>
                            <?php
                            $is_curr_active  = ( strtoupper( (string) $active_currency ) === $curr_item['code'] );
                            $curr_switch_url = add_query_arg( 'currency', $curr_item['code'] );
                            ?>
                            <a
                                href="<?php echo esc_url( $curr_switch_url ); ?>"
                                class="edupro-currency-btn <?php echo $is_curr_active ? 'is-active' : ''; ?>"
                                data-currency-switch="<?php echo esc_attr( $curr_item['code'] ); ?>"
                                aria-pressed="<?php echo $is_curr_active ? 'true' : 'false'; ?>"
                                title="<?php echo esc_attr( sprintf( __( 'Display prices in %s', 'edupro' ), $curr_item['label'] ) ); ?>"
                            >
                                <?php echo esc_html( $curr_item['symbol'] . ' ' . $curr_item['code'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button
                    type="button"
                    id="edupro-announcement-close"
                    class="edupro-announcement-bar__close announcement-close"
                    aria-label="<?php esc_attr_e( 'Dismiss announcement', 'edupro' ); ?>"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
