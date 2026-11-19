<?php
/**
 * EduPro LMS — One-Click Demo Website Setup & Auto-Installer
 *
 * Automatically builds a complete, ready-to-use WordPress LMS website upon theme
 * activation (`after_switch_theme`), and provides an admin control panel under
 * Appearance → EduPro Setup to re-run setup or reset & re-import demo content.
 *
 * Creates:
 * - Essential pages (Home, Student Login, Student Register, Dashboard, Checkout, About Us, Contact Us)
 * - Rich course taxonomies (10 course_category terms, 4 course_level terms, 2 course_language terms)
 * - 3 realistic lms_instructor profiles with generated SVG avatars
 * - 8 vocational & tech lms_course posts with pricing, curriculum JSON, reviews & local SVG thumbnails
 * - 28 linked lms_lesson posts (3–4 per course, with Free Preview lesson 1)
 * - 6 verified student/Google reviews seeded into `edupro_manual_reviews` (only if empty)
 * - Navigation menus assigned to `primary`, `footer-1`, `footer-2`, `footer-3`
 * - Flushes rewrite rules so /courses/, /lessons/, /instructors/, /course-category/ work immediately
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// 1. THEME ACTIVATION HOOK (IDEMPOTENT)
// =============================================================================

/**
 * Automatically run the complete EduPro demo setup when the theme is activated.
 * Idempotent: checks `edupro_demo_setup_done` so re-activating never duplicates content.
 */
function edupro_demo_on_theme_activation() {
	// Always ensure CPTs and taxonomies are registered before creating content or flushing rules.
	if ( function_exists( 'edupro_register_post_types' ) ) {
		edupro_register_post_types();
	}
	if ( function_exists( 'edupro_register_taxonomies' ) ) {
		edupro_register_taxonomies();
	}

	if ( ! get_option( 'edupro_demo_setup_done' ) ) {
		edupro_run_demo_setup( false );
		set_transient( 'edupro_demo_activation_notice', 1, 60 * 60 * 24 );
	} else {
		// Still ensure essential page options & rewrite rules stay healthy on re-activation.
		edupro_demo_create_pages( false );
		flush_rewrite_rules();
	}
}
add_action( 'after_switch_theme', 'edupro_demo_on_theme_activation', 20 );

/**
 * Display a helpful welcome notice right after activating EduPro.
 */
function edupro_demo_activation_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_transient( 'edupro_demo_activation_notice' ) ) {
		return;
	}

	if ( isset( $_GET['edupro_dismiss_welcome'] ) && '1' === $_GET['edupro_dismiss_welcome'] ) {
		delete_transient( 'edupro_demo_activation_notice' );
		return;
	}

	$setup_url   = admin_url( 'themes.php?page=edupro-demo-setup' );
	$courses_url = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
	$dismiss_url = add_query_arg( 'edupro_dismiss_welcome', '1' );
	?>
	<div class="notice notice-success is-dismissible" style="border-left-color:#e31b23;padding:14px 18px;">
		<p style="margin:0 0 8px;font-size:14px;">
			<strong><?php esc_html_e( 'Welcome to EduPro LMS!', 'edupro' ); ?></strong>
			<?php esc_html_e( 'Your complete LMS website (Home, Courses, Lessons, Instructors, Student Portal, Checkout, Menus & Reviews) has been automatically installed.', 'edupro' ); ?>
		</p>
		<p style="margin:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button button-primary" style="background:#e31b23;border-color:#c4141b;" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Live Website', 'edupro' ); ?>
			</a>
			<a href="<?php echo esc_url( $courses_url ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Browse Courses', 'edupro' ); ?>
			</a>
			<a href="<?php echo esc_url( $setup_url ); ?>" class="button">
				<?php esc_html_e( 'EduPro Setup Manager', 'edupro' ); ?>
			</a>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" style="margin-left:6px;color:#646970;text-decoration:none;">
				<?php esc_html_e( 'Dismiss', 'edupro' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'edupro_demo_activation_admin_notice' );

// =============================================================================
// 2. MAIN ORCHESTRATOR & RESET HANDLER
// =============================================================================

if ( ! function_exists( 'edupro_run_demo_setup' ) ) :
	/**
	 * Run the complete EduPro demo website setup.
	 *
	 * @param bool $force When true, updates existing demo items and overwrites menu assignments.
	 * @return array Summary of created/verified items.
	 */
	function edupro_run_demo_setup( $force = false ) {
		// Ensure CPTs, taxonomies, student role, and orders table exist.
		if ( function_exists( 'edupro_register_post_types' ) ) {
			edupro_register_post_types();
		}
		if ( function_exists( 'edupro_register_taxonomies' ) ) {
			edupro_register_taxonomies();
		}
		if ( function_exists( 'edupro_add_student_role' ) ) {
			edupro_add_student_role();
		}
		if ( function_exists( 'edupro_create_orders_table' ) ) {
			edupro_create_orders_table();
		}
		if ( function_exists( 'edupro_create_coupons_table' ) ) {
			edupro_create_coupons_table();
		}

		edupro_demo_seed_currency_defaults();
		edupro_demo_seed_site_settings();
		$pages       = edupro_demo_create_pages( $force );
		$terms       = edupro_demo_create_taxonomies();
		$instructors = edupro_demo_create_instructors( $force );
		$catalog     = edupro_demo_create_courses_and_lessons( $force );
		$reviews_cnt = edupro_demo_seed_google_reviews( $force );
		edupro_demo_seed_default_gateway_if_empty();
		$coupons_cnt = edupro_demo_seed_sample_coupon();
		$menus       = edupro_demo_create_menus( $force, $pages );

		$summary = array(
			'pages'       => count( $pages ),
			'categories'  => $terms['categories'],
			'levels'      => $terms['levels'],
			'languages'   => $terms['languages'],
			'instructors' => count( $instructors ),
			'courses'     => $catalog['courses'],
			'lessons'     => $catalog['lessons'],
			'reviews'     => $reviews_cnt,
			'coupons'     => $coupons_cnt,
			'menus'       => count( $menus ),
			'completed'   => current_time( 'mysql' ),
		);

		update_option( 'edupro_demo_setup_done', $summary['completed'] );
		update_option( 'edupro_demo_setup_summary', $summary );

		// Flush permalinks so /courses/, /lessons/, /instructors/, /course-category/ work right away.
		flush_rewrite_rules();

		return $summary;
	}
endif;

/**
 * Reset previously imported demo items (courses, lessons, instructors, SVG attachments,
 * demo menus, and demo reviews) and run a clean re-import.
 *
 * @return array Fresh setup summary.
 */
function edupro_demo_reset_and_reimport() {
	// 1. Delete demo lessons, courses, instructors, and generated SVG attachments.
	$post_types_to_clean = array( 'lms_lesson', 'lms_course', 'lms_instructor', 'attachment' );
	foreach ( $post_types_to_clean as $pt ) {
		$demo_posts = get_posts(
			array(
				'post_type'      => $pt,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_edupro_demo_item',
				'meta_value'     => '1',
			)
		);

		foreach ( $demo_posts as $pid ) {
			if ( 'attachment' === $pt ) {
				wp_delete_attachment( (int) $pid, true );
			} else {
				wp_delete_post( (int) $pid, true );
			}
		}
	}

	// 2. Delete previously created demo nav menus so they are recreated cleanly without duplicate links.
	$demo_menu_names = array(
		'EduPro Primary Menu',
		'EduPro Footer Categories',
		'EduPro Footer Quick Links',
		'EduPro Footer Support',
	);
	foreach ( $demo_menu_names as $menu_name ) {
		$menu_obj = wp_get_nav_menu_object( $menu_name );
		if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
			wp_delete_nav_menu( $menu_obj->term_id );
		}
	}

	// 3. Reset setup flag and demo reviews so fresh defaults are seeded.
	delete_option( 'edupro_demo_setup_done' );
	delete_option( 'edupro_manual_reviews' );

	// 4. Re-run complete setup.
	return edupro_run_demo_setup( true );
}

// =============================================================================
// 3. ESSENTIAL PAGES CREATION
// =============================================================================

/**
 * Create essential LMS pages if they don't already exist, assign templates & options,
 * and configure the static front page.
 *
 * @param bool $force Whether to refresh demo page content when resetting.
 * @return array Map of slug => page_id.
 */
function edupro_demo_create_pages( $force = false ) {
	$author_id = get_current_user_id() ?: 1;
	$courses_url = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );

	$about_content = '<div class="edupro-page-content">'
		. '<h2>' . esc_html__( 'Empowering Careers Through Accredited Online Learning', 'edupro' ) . '</h2>'
		. '<p>' . esc_html__( 'EduPro is a modern online learning academy dedicated to helping learners, healthcare professionals, educators, and technology specialists gain practical, career-ready qualifications at their own pace.', 'edupro' ) . '</p>'
		. '<h3>' . esc_html__( 'Why Thousands of Students Trust EduPro', 'edupro' ) . '</h3>'
		. '<ul>'
		. '<li><strong>' . esc_html__( 'Industry-Aligned Curriculum:', 'edupro' ) . '</strong> ' . esc_html__( 'Every course is designed by practicing assessors, clinicians, and senior engineers to match real workplace standards.', 'edupro' ) . '</li>'
		. '<li><strong>' . esc_html__( '100% Flexible Self-Paced Study:', 'edupro' ) . '</strong> ' . esc_html__( 'Access HD video lessons, structured study guides, and assessments anytime from desktop, tablet, or mobile.', 'edupro' ) . '</li>'
		. '<li><strong>' . esc_html__( 'Verified Certificates:', 'edupro' ) . '</strong> ' . esc_html__( 'Download shareable completion certificates immediately upon finishing your course.', 'edupro' ) . '</li>'
		. '<li><strong>' . esc_html__( 'Dedicated Tutor & Learner Support:', 'edupro' ) . '</strong> ' . esc_html__( 'Get timely guidance whenever you need help with coursework, assignments, or career progression.', 'edupro' ) . '</li>'
		. '</ul>'
		. '<p><a class="edupro-btn edupro-btn-primary" href="' . esc_url( $courses_url ) . '">' . esc_html__( 'Explore All Courses', 'edupro' ) . '</a></p>'
		. '</div>';

	$contact_content = '<div class="edupro-page-content">'
		. '<h2>' . esc_html__( 'We Are Here to Help You Succeed', 'edupro' ) . '</h2>'
		. '<p>' . esc_html__( 'Have questions about course accreditation, corporate group enrolments, payment methods, or accessing your student dashboard? Reach out to our admissions and learner support team directly via phone, WhatsApp, or email below.', 'edupro' ) . '</p>'
		. '[edupro_contact_info]'
		. '<h3>' . esc_html__( 'Find Us on the Map', 'edupro' ) . '</h3>'
		. '[edupro_map]'
		. '<h3>' . esc_html__( 'Frequently Asked Questions', 'edupro' ) . '</h3>'
		. '<p><strong>' . esc_html__( 'How quickly do I get course access after enrolling?', 'edupro' ) . '</strong><br>'
		. esc_html__( 'Access is granted immediately after payment verification (or instantly for free courses). Simply visit your Student Dashboard to begin learning.', 'edupro' ) . '</p>'
		. '<p><strong>' . esc_html__( 'Are certificates included with every course?', 'edupro' ) . '</strong><br>'
		. esc_html__( 'Yes! Once you complete 100% of lessons in any eligible course, your verified EduPro Certificate of Completion becomes available right inside your dashboard.', 'edupro' ) . '</p>'
		. '</div>';

	$pages_config = array(
		'home'             => array(
			'title'    => __( 'Home', 'edupro' ),
			'slug'     => 'home',
			'template' => '',
			'option'   => 'edupro_home_page_id',
			'content'  => '',
		),
		'student-login'    => array(
			'title'    => __( 'Student Login', 'edupro' ),
			'slug'     => 'student-login',
			'template' => 'templates/template-login.php',
			'option'   => 'edupro_login_page_id',
			'content'  => '',
		),
		'student-register' => array(
			'title'    => __( 'Student Register', 'edupro' ),
			'slug'     => 'student-register',
			'template' => 'templates/template-register.php',
			'option'   => 'edupro_register_page_id',
			'content'  => '',
		),
		'dashboard'        => array(
			'title'    => __( 'Student Dashboard', 'edupro' ),
			'slug'     => 'dashboard',
			'template' => 'templates/template-dashboard.php',
			'option'   => 'edupro_dashboard_page_id',
			'content'  => '',
		),
		'checkout'         => array(
			'title'    => __( 'Course Checkout', 'edupro' ),
			'slug'     => 'checkout',
			'template' => 'templates/template-checkout.php',
			'option'   => 'edupro_checkout_page_id',
			'content'  => '',
		),
		'about-us'         => array(
			'title'    => __( 'About Us', 'edupro' ),
			'slug'     => 'about-us',
			'template' => '',
			'option'   => 'edupro_about_page_id',
			'content'  => $about_content,
		),
		'contact-us'       => array(
			'title'    => __( 'Contact Us', 'edupro' ),
			'slug'     => 'contact-us',
			'template' => '',
			'option'   => 'edupro_contact_page_id',
			'content'  => $contact_content,
		),
	);

	$page_ids = array();

	foreach ( $pages_config as $key => $cfg ) {
		$existing = get_page_by_path( $cfg['slug'] );

		// Fallback to stored option ID if slug was renamed.
		if ( ! $existing && ! empty( $cfg['option'] ) ) {
			$opt_id = (int) get_option( $cfg['option'], 0 );
			if ( $opt_id && 'page' === get_post_type( $opt_id ) && 'trash' !== get_post_status( $opt_id ) ) {
				$existing = get_post( $opt_id );
			}
		}

		if ( $existing && 'trash' !== $existing->post_status ) {
			$page_id = (int) $existing->ID;
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post(
					array(
						'ID'          => $page_id,
						'post_status' => 'publish',
					)
				);
			}
			if ( $force && ! empty( $cfg['content'] ) && '' === trim( $existing->post_content ) ) {
				wp_update_post(
					array(
						'ID'           => $page_id,
						'post_content' => $cfg['content'],
					)
				);
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $cfg['title'],
					'post_name'    => $cfg['slug'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $cfg['content'],
					'post_author'  => $author_id,
				)
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_post_meta( $page_id, '_edupro_demo_item', '1' );
			}
		}

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			if ( ! empty( $cfg['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $cfg['template'] );
			}
			if ( ! empty( $cfg['option'] ) ) {
				update_option( $cfg['option'], (int) $page_id );
			}
			$page_ids[ $key ] = (int) $page_id;
		}
	}

	// Set static Front Page to Home so front-page.php renders immediately.
	if ( ! empty( $page_ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $page_ids['home'] );
	}

	return $page_ids;
}

// =============================================================================
// 4. TAXONOMIES (CATEGORIES, LEVELS, LANGUAGES)
// =============================================================================

/**
 * Create rich course_category, course_level, and course_language terms.
 *
 * @return array Counts of created/available terms.
 */
function edupro_demo_create_taxonomies() {
	$categories = array(
		'assessing-quality-assurance' => array(
			'name' => 'Assessing & Quality Assurance',
			'desc' => 'Accredited vocational assessor (CAVA) and internal quality assurance (IQA) qualifications.',
		),
		'functional-skills'           => array(
			'name' => 'Functional Skills',
			'desc' => 'Essential English, Maths, and Digital Functional Skills qualifications for work and further study.',
		),
		'phlebotomy-healthcare'       => array(
			'name' => 'Phlebotomy & Healthcare',
			'desc' => 'Clinical venepuncture, phlebotomy protocols, infection control, and allied healthcare training.',
		),
		'health-social-care'          => array(
			'name' => 'Health & Social Care',
			'desc' => 'Care diplomas, safeguarding, person-centred practice, and adult health & social care leadership.',
		),
		'business-management'         => array(
			'name' => 'Business & Management',
			'desc' => 'Strategic operations, digital marketing, SEO, project delivery, and business growth skills.',
		),
		'it-cyber-security'           => array(
			'name' => 'IT & Cyber Security',
			'desc' => 'Full-stack software engineering, ethical hacking, network security, and cloud architecture.',
		),
		'leadership-management'       => array(
			'name' => 'Leadership & Management',
			'desc' => 'Executive leadership, high-performing teams, change management, and strategic decision making.',
		),
		'education-training'          => array(
			'name' => 'Education & Training',
			'desc' => 'Teaching, vocational training delivery, curriculum design, and classroom assessment.',
		),
		'design-creative'             => array(
			'name' => 'Design & Creative',
			'desc' => 'UI/UX product design, Figma prototyping, visual communication, and brand systems.',
		),
		'personal-development'        => array(
			'name' => 'Personal Development',
			'desc' => 'Career acceleration, executive communication, productivity, and interview mastery.',
		),
	);

	$cat_count = 0;
	foreach ( $categories as $slug => $data ) {
		if ( ! term_exists( $slug, 'course_category' ) ) {
			$res = wp_insert_term(
				$data['name'],
				'course_category',
				array(
					'slug'        => $slug,
					'description' => $data['desc'],
				)
			);
			if ( ! is_wp_error( $res ) ) {
				$cat_count++;
			}
		} else {
			$cat_count++;
		}
	}

	$levels = array(
		'beginner'     => 'Beginner',
		'intermediate' => 'Intermediate',
		'advanced'     => 'Advanced',
		'all-levels'   => 'All Levels',
	);
	$level_count = 0;
	foreach ( $levels as $slug => $name ) {
		if ( ! term_exists( $slug, 'course_level' ) ) {
			$res = wp_insert_term( $name, 'course_level', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $res ) ) {
				$level_count++;
			}
		} else {
			$level_count++;
		}
	}

	$languages = array(
		'english' => 'English',
		'hindi'   => 'Hindi',
	);
	$lang_count = 0;
	foreach ( $languages as $slug => $name ) {
		if ( ! term_exists( $slug, 'course_language' ) ) {
			$res = wp_insert_term( $name, 'course_language', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $res ) ) {
				$lang_count++;
			}
		} else {
			$lang_count++;
		}
	}

	return array(
		'categories' => $cat_count,
		'levels'     => $level_count,
		'languages'  => $lang_count,
	);
}

// =============================================================================
// 5. LOCAL SVG THUMBNAIL & AVATAR GENERATOR (ZERO BROKEN EXTERNAL URLS)
// =============================================================================

/**
 * Save a clean SVG string into wp-content/uploads/edupro-demo/ and register it
 * as a WordPress media attachment with proper dimensions metadata.
 *
 * @param string $slug       Unique filename slug (without .svg).
 * @param string $title      Attachment title.
 * @param string $svg_markup Valid standalone SVG markup.
 * @param int    $width      Width in pixels.
 * @param int    $height     Height in pixels.
 * @return int Attachment ID or 0 on failure.
 */
function edupro_demo_create_svg_attachment( $slug, $title, $svg_markup, $width = 800, $height = 500 ) {
	$slug = sanitize_title( $slug );

	// Reuse existing demo attachment if already created.
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_edupro_demo_svg_slug',
			'meta_value'     => $slug,
		)
	);
	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
		return 0;
	}

	$demo_dir = trailingslashit( $upload_dir['basedir'] ) . 'edupro-demo';
	if ( ! file_exists( $demo_dir ) && ! wp_mkdir_p( $demo_dir ) ) {
		return 0;
	}

	$filename      = $slug . '.svg';
	$file_path     = trailingslashit( $demo_dir ) . $filename;
	$relative_path = 'edupro-demo/' . $filename;
	$file_url      = trailingslashit( $upload_dir['baseurl'] ) . $relative_path;

	// Write SVG file safely.
	$written = @file_put_contents( $file_path, $svg_markup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === $written ) {
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'guid'           => $file_url,
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$file_path
	);

	if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
		return 0;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
	update_post_meta(
		$attachment_id,
		'_wp_attachment_metadata',
		array(
			'width'  => absint( $width ),
			'height' => absint( $height ),
			'file'   => $relative_path,
		)
	);
	update_post_meta( $attachment_id, '_edupro_demo_item', '1' );
	update_post_meta( $attachment_id, '_edupro_demo_svg_slug', $slug );

	return (int) $attachment_id;
}

/**
 * XML-escape helper for SVG text nodes.
 *
 * @param string $text Raw text.
 * @return string Escaped XML-safe string.
 */
function edupro_demo_xml_esc( $text ) {
	return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Build a crisp, modern 800x500 SVG course cover graphic.
 *
 * @param string $title          Course title.
 * @param string $category_label Primary category label.
 * @param string $level          Difficulty level.
 * @param string $color_start    Gradient start hex.
 * @param string $color_end      Gradient end hex.
 * @param string $accent         Accent badge hex.
 * @return string Standalone SVG markup.
 */
function edupro_demo_build_course_svg( $title, $category_label, $level, $color_start, $color_end, $accent = '#e31b23' ) {
	// Wrap title neatly across up to 3 lines (~28 chars per line).
	$wrapped = wordwrap( $title, 28, "\n", false );
	$lines   = array_slice( explode( "\n", $wrapped ), 0, 3 );

	$title_tspans = '';
	$y            = 215;
	foreach ( $lines as $line ) {
		$title_tspans .= '<text x="64" y="' . $y . '" fill="#ffffff" font-family="Poppins, Inter, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif" font-size="36" font-weight="700">'
			. edupro_demo_xml_esc( $line ) . '</text>';
		$y += 46;
	}

	$cat_esc   = edupro_demo_xml_esc( strtoupper( $category_label ) );
	$level_esc = edupro_demo_xml_esc( $level . ' • Accredited Certificate' );

	return '<?xml version="1.0" encoding="UTF-8"?>'
		. '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500" role="img" aria-label="' . edupro_demo_xml_esc( $title ) . '">'
		. '<defs>'
		. '<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">'
		. '<stop offset="0%" stop-color="' . edupro_demo_xml_esc( $color_start ) . '"/>'
		. '<stop offset="100%" stop-color="' . edupro_demo_xml_esc( $color_end ) . '"/>'
		. '</linearGradient>'
		. '<linearGradient id="glow" x1="0%" y1="0%" x2="100%" y2="100%">'
		. '<stop offset="0%" stop-color="' . edupro_demo_xml_esc( $accent ) . '" stop-opacity="0.35"/>'
		. '<stop offset="100%" stop-color="' . edupro_demo_xml_esc( $accent ) . '" stop-opacity="0"/>'
		. '</linearGradient>'
		. '</defs>'
		. '<rect width="800" height="500" fill="url(#bg)"/>'
		. '<circle cx="690" cy="110" r="190" fill="url(#glow)"/>'
		. '<circle cx="690" cy="110" r="120" fill="none" stroke="#ffffff" stroke-opacity="0.08" stroke-width="2"/>'
		. '<circle cx="720" cy="430" r="140" fill="#ffffff" fill-opacity="0.04"/>'
		. '<rect x="64" y="64" width="12" height="12" rx="3" fill="' . edupro_demo_xml_esc( $accent ) . '"/>'
		. '<text x="86" y="75" fill="#ffffff" fill-opacity="0.88" font-family="Inter, -apple-system, sans-serif" font-size="14" font-weight="700" letter-spacing="1.4">' . $cat_esc . '</text>'
		. $title_tspans
		. '<rect x="64" y="394" width="320" height="40" rx="20" fill="#ffffff" fill-opacity="0.12" stroke="#ffffff" stroke-opacity="0.22"/>'
		. '<text x="86" y="419" fill="#ffffff" font-family="Inter, -apple-system, sans-serif" font-size="15" font-weight="600">' . $level_esc . '</text>'
		. '<rect x="618" y="396" width="118" height="36" rx="8" fill="' . edupro_demo_xml_esc( $accent ) . '"/>'
		. '<text x="677" y="420" text-anchor="middle" fill="#ffffff" font-family="Poppins, sans-serif" font-size="15" font-weight="700">EduPro</text>'
		. '</svg>';
}

/**
 * Build a crisp 400x400 SVG portrait avatar for demo instructors.
 *
 * @param string $name        Instructor name.
 * @param string $initials    2-letter initials.
 * @param string $color_start Gradient start hex.
 * @param string $color_end   Gradient end hex.
 * @return string Standalone SVG markup.
 */
function edupro_demo_build_instructor_svg( $name, $initials, $color_start, $color_end ) {
	return '<?xml version="1.0" encoding="UTF-8"?>'
		. '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="' . edupro_demo_xml_esc( $name ) . '">'
		. '<defs>'
		. '<linearGradient id="avbg" x1="0%" y1="0%" x2="100%" y2="100%">'
		. '<stop offset="0%" stop-color="' . edupro_demo_xml_esc( $color_start ) . '"/>'
		. '<stop offset="100%" stop-color="' . edupro_demo_xml_esc( $color_end ) . '"/>'
		. '</linearGradient>'
		. '</defs>'
		. '<rect width="400" height="400" fill="url(#avbg)"/>'
		. '<circle cx="200" cy="148" r="62" fill="#ffffff" fill-opacity="0.18"/>'
		. '<path d="M90 360c0-66 49-112 110-112s110 46 110 112" fill="#ffffff" fill-opacity="0.16"/>'
		. '<text x="200" y="218" text-anchor="middle" fill="#ffffff" font-family="Poppins, Inter, -apple-system, sans-serif" font-size="112" font-weight="700">'
		. edupro_demo_xml_esc( $initials ) . '</text>'
		. '</svg>';
}

// =============================================================================
// 6. INSTRUCTORS (3 REALISTIC lms_instructor POSTS)
// =============================================================================

/**
 * Create 3 realistic lms_instructor posts with metadata matching inc/shortcodes.php.
 *
 * @param bool $force Whether to update existing items.
 * @return array List of instructor post IDs.
 */
function edupro_demo_create_instructors( $force = false ) {
	$author_id = get_current_user_id() ?: 1;

	$instructors = array(
		array(
			'slug'        => 'dr-priya-deshmukh',
			'name'        => 'Dr. Priya Deshmukh',
			'initials'    => 'PD',
			'title'       => 'Clinical Healthcare & Phlebotomy Lead Trainer',
			'courses_cnt' => 3,
			'students'    => 4250,
			'excerpt'     => 'Medical clinician and accredited healthcare trainer with 14+ years of experience in phlebotomy, clinical governance, and health & social care diplomas.',
			'bio'         => '<p>Dr. Priya Deshmukh is a senior healthcare educator and clinical skills trainer with over 14 years of hands-on hospital and vocational teaching experience. She has trained more than 4,200 nurses, phlebotomists, and social care leaders across accredited clinical pathways.</p><p>Her teaching approach emphasizes patient safety, evidence-based clinical protocols, and clear step-by-step demonstrations that help learners transition confidently into healthcare roles.</p>',
			'colors'      => array( '#0f3460', '#16213e' ),
		),
		array(
			'slug'        => 'arjun-mehta',
			'name'        => 'Arjun Mehta',
			'initials'    => 'AM',
			'title'       => 'Principal Full-Stack Architect & Cyber Security Lead',
			'courses_cnt' => 3,
			'students'    => 6840,
			'excerpt'     => 'Full-stack software architect and certified ethical hacker specializing in modern web applications, cloud security, and penetration testing.',
			'bio'         => '<p>Arjun Mehta brings 12+ years of production engineering and offensive security leadership across fintech and SaaS organizations. He specializes in JavaScript, TypeScript, Node.js, React, API security, and ethical hacking methodologies.</p><p>Through practical labs and portfolio-ready capstone builds, Arjun has mentored over 6,800 students into software engineering and security analyst roles.</p>',
			'colors'      => array( '#1a1a2e', '#e31b23' ),
		),
		array(
			'slug'        => 'sarah-jenkins-mba',
			'name'        => 'Sarah Jenkins, MBA',
			'initials'    => 'SJ',
			'title'       => 'Vocational Assessment (CAVA) & Executive Leadership Director',
			'courses_cnt' => 3,
			'students'    => 5120,
			'excerpt'     => 'Chartered Manager, Lead Internal Quality Assurer (IQA), and growth strategist with 15+ years delivering CAVA, executive leadership, and digital marketing programmes.',
			'bio'         => '<p>Sarah Jenkins is a Chartered Manager and Lead IQA with 15+ years of experience designing vocational qualifications, leading executive coaching programmes, and mentoring digital marketing teams.</p><p>She has guided thousands of assessors, department heads, and product designers toward internationally recognized certifications and measurable career advancement.</p>',
			'colors'      => array( '#2c1654', '#5b2c6f' ),
		),
	);

	$ids = array();

	foreach ( $instructors as $index => $inst ) {
		$existing = get_page_by_path( $inst['slug'], OBJECT, 'lms_instructor' );

		if ( $existing && 'trash' !== $existing->post_status ) {
			$post_id = (int) $existing->ID;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_title'   => $inst['name'],
					'post_name'    => $inst['slug'],
					'post_content' => $inst['bio'],
					'post_excerpt' => $inst['excerpt'],
					'post_status'  => 'publish',
					'post_type'    => 'lms_instructor',
					'post_author'  => $author_id,
					'menu_order'   => $index + 1,
				)
			);
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_edupro_instructor_title', $inst['title'] );
			update_post_meta( $post_id, '_edupro_instructor_courses', $inst['courses_cnt'] );
			update_post_meta( $post_id, '_edupro_instructor_students', $inst['students'] );
			update_post_meta( $post_id, '_edupro_demo_item', '1' );

			if ( ! has_post_thumbnail( $post_id ) || $force ) {
				$svg    = edupro_demo_build_instructor_svg( $inst['name'], $inst['initials'], $inst['colors'][0], $inst['colors'][1] );
				$att_id = edupro_demo_create_svg_attachment( 'instructor-' . $inst['slug'], $inst['name'], $svg, 400, 400 );
				if ( $att_id ) {
					set_post_thumbnail( $post_id, $att_id );
				}
			}

			$ids[] = (int) $post_id;
		}
	}

	return $ids;
}

// =============================================================================
// 7. 8 PREMIUM COURSES & LINKED SELF-PACED LESSONS
// =============================================================================

/**
 * Return structured definitions for the 8 realistic EduPro demo courses & their lessons.
 *
 * @return array
 */
function edupro_demo_get_courses_catalog() {
	$sale_end_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
	$sample_vid_1  = 'https://www.youtube.com/watch?v=M7lc1UVf-VE';
	$sample_vid_2  = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';

	return array(
		// 1. CAVA
		array(
			'slug'           => 'level-3-certificate-assessing-vocational-achievement-cava',
			'title'          => 'Level 3 Certificate in Assessing Vocational Achievement (CAVA)',
			'excerpt'        => 'Become a qualified vocational assessor able to assess both occupational competence in the workplace and vocational knowledge in classroom or training environments.',
			'content'        => '<p>The <strong>Level 3 Certificate in Assessing Vocational Achievement (CAVA)</strong> is the gold-standard qualification for aspiring and practicing vocational assessors. It equips you with the full theoretical understanding and hands-on assessment skills required to evaluate learners across both real work environments and structured learning settings.</p><h3>Who Should Enrol?</h3><p>Ideal for workplace supervisors, training providers, FE college tutors, healthcare mentors, and professionals looking to build a flexible, well-paid career as a qualified assessor.</p><h3>Assessment & Certification</h3><p>Complete guided self-paced video modules, practical assessment templates, and portfolio evidence checklists to earn your verified completion certificate.</p>',
			'categories'     => array( 'assessing-quality-assurance', 'education-training' ),
			'tags'           => array( 'CAVA', 'Vocational Assessor', 'Quality Assurance', 'Level 3' ),
			'level'          => 'Intermediate',
			'level_slug'     => 'intermediate',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 14999,
			'sale_price'        => 4999,
			'regular_price_inr' => 14999,
			'sale_price_inr'    => 4999,
			'regular_price_gbp' => 499,
			'sale_price_gbp'    => 299,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 720,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_1,
			'colors'         => array( '#1a1a2e', '#2d1b4e', '#e31b23' ),
			'cat_badge'      => 'Assessing & Quality Assurance',
			'requirements'   => "No prior assessing qualification required — suitable for subject experts entering assessment\nAccess to a computer or tablet with internet connection\nBasic English literacy and communication skills",
			'what_you_learn' => "Understand the principles, standards, and legal regulations governing vocational assessment\nPlan and conduct fair, valid, reliable, and authentic workplace observations\nAssess vocational skills, knowledge, and understanding using diverse evidence methods\nDeliver constructive learner feedback and maintain compliant assessment records\nCollaborate effectively with Internal Quality Assurers (IQA) and awarding bodies\nBuild a complete, audit-ready assessor portfolio from scratch",
			'curriculum'     => array(
				array(
					'title'       => 'Unit 1: Understanding the Principles & Practices of Assessment',
					'description' => '<p>Master assessment methods, VARCS quality standards, standardisation, and regulatory compliance.</p>',
					'lessons'     => array(
						array( 'title' => 'Introduction to Vocational Assessment & CAVA Pathway', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 18, 'is_preview' => true ),
						array( 'title' => 'Assessment Methods, VARCS Criteria & Equality Standards', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 26, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Unit 2 & 3: Assessing Workplace Competence & Vocational Knowledge',
					'description' => '<p>Conduct real-world observations, professional discussions, written evaluations, and IQA sign-offs.</p>',
					'lessons'     => array(
						array( 'title' => 'Planning Workplace Observations & Evidence Matrices', 'type' => 'text', 'content' => 'Step-by-step guide to creating structured assessment plans and learner feedback logs.', 'duration' => 20, 'is_preview' => false ),
						array( 'title' => 'Managing Assessment Decisions, Appeals & IQA Sampling', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 24, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Introduction to Vocational Assessment & CAVA Pathway',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 18,
					'is_preview' => '1',
					'content'    => '<h3>Lesson Overview</h3><p>Welcome to the Level 3 CAVA qualification! In this introductory lesson, we map out all three CAVA units, the role of an accredited assessor, and how to organise your learner evidence portfolio.</p><ul><li>Understanding Units 1, 2, and 3 requirements</li><li>The assessment cycle: Initial assessment, planning, activity, decision, and review</li><li>How to prepare for standardisation meetings</li></ul>',
				),
				array(
					'title'      => 'Assessment Methods, VARCS Criteria & Equality Standards',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 26,
					'is_preview' => '0',
					'content'    => '<h3>Key Concepts</h3><p>Every assessment decision must satisfy the <strong>VARCS</strong> framework: Valid, Authentic, Reliable, Current, and Sufficient. We also explore reasonable adjustments and inclusive assessment planning.</p>',
				),
				array(
					'title'      => 'Planning Workplace Observations & Evidence Matrices',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 20,
					'is_preview' => '0',
					'content'    => '<h3>Designing Holistic Assessment Plans</h3><p>Holistic assessment allows you to cover multiple learning outcomes during a single workplace observation, saving time for both learner and employer.</p><h4>Checklist Before Observing a Learner</h4><ol><li>Confirm assessment criteria and workplace safety rules with the learner.</li><li>Agree on observation date, scope, and contingency plans.</li><li>Prepare structured questioning to fill any knowledge gaps immediately after observation.</li><li>Record objective, criteria-referenced feedback and set SMART action points.</li></ol>',
				),
				array(
					'title'      => 'Managing Assessment Decisions, Appeals & IQA Sampling',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 24,
					'is_preview' => '0',
					'content'    => '<h3>Quality Assurance & Record Keeping</h3><p>Learn how Internal Quality Assurers sample portfolios, how to handle learner appeals transparently, and how to maintain GDPR-compliant assessment records.</p>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Meera Krishnan', 'name' => 'Meera Krishnan', 'rating' => 5, 'date' => '2 weeks ago', 'comment' => 'Extremely clear breakdown of Units 1, 2, and 3. The observation planning templates helped me pass my IQA sampling on the first attempt.' ),
				array( 'author' => 'Daniel Brooks', 'name' => 'Daniel Brooks', 'rating' => 5, 'date' => '1 month ago', 'comment' => 'Best CAVA online course I found. Self-paced lessons fit smoothly around my full-time healthcare training role.' ),
				array( 'author' => 'Kavita Joshi', 'name' => 'Kavita Joshi', 'rating' => 5, 'date' => '2 months ago', 'comment' => 'Comprehensive, practical, and directly aligned with real workplace assessing standards.' ),
			),
		),

		// 2. Phlebotomy Training
		array(
			'slug'           => 'phlebotomy-training-course-healthcare-professionals',
			'title'          => 'Phlebotomy Training Course for Healthcare Professionals',
			'excerpt'        => 'Master safe venepuncture anatomy, infection control, specimen handling, and patient-centred blood collection protocols for clinical environments.',
			'content'        => '<p>Our <strong>Phlebotomy Training Course for Healthcare Professionals</strong> delivers essential theoretical foundations and clinical best practices for safe, hygienic venepuncture. Designed by experienced medical educators, it covers vascular anatomy, order of draw, sharps safety, and managing anxious patients.</p><h3>Career Outcomes</h3><p>Prepare for roles as a Trainee Phlebotomist, Healthcare Assistant (HCA), Clinical Support Worker, or Nursing Assistant in hospitals, GP surgeries, and diagnostic laboratories.</p>',
			'categories'     => array( 'phlebotomy-healthcare', 'health-social-care' ),
			'tags'           => array( 'Phlebotomy', 'Venepuncture', 'Healthcare', 'Clinical Skills' ),
			'level'          => 'Beginner',
			'level_slug'     => 'beginner',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 9999,
			'sale_price'        => 3499,
			'regular_price_inr' => 9999,
			'sale_price_inr'    => 3499,
			'regular_price_gbp' => 349,
			'sale_price_gbp'    => 199,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 540,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_2,
			'colors'         => array( '#0b2545', '#134074', '#e31b23' ),
			'cat_badge'      => 'Phlebotomy & Healthcare',
			'requirements'   => "Open to aspiring phlebotomists, healthcare assistants, nurses, and allied health learners\nNo prior clinical certification required to begin theory modules",
			'what_you_learn' => "Identify antecubital fossa veins safely and select optimal venepuncture sites\nApply strict aseptic non-touch technique (ANTT) and infection prevention protocols\nFollow the correct clinical Order of Draw to prevent sample cross-contamination\nRecognize and manage complications including haematoma, syncope, and needle-stick injury\nCommunicate empathetically to obtain informed patient consent and calm anxious patients\nLabel, store, and transport blood specimens accurately according to lab standards",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: Clinical Foundations, Consent & Infection Control',
					'description' => '<p>Legal responsibilities, patient identification, PPE, and hand hygiene standards.</p>',
					'lessons'     => array(
						array( 'title' => 'Role of the Phlebotomist & Patient Consent Protocols', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 16, 'is_preview' => true ),
						array( 'title' => 'Vascular Anatomy & Safe Vein Selection Techniques', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 24, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: Venepuncture Procedure, Order of Draw & Safety',
					'description' => '<p>Equipment preparation, Vacutainer order of draw, and complication management.</p>',
					'lessons'     => array(
						array( 'title' => 'Complete Order of Draw & Blood Tube Reference Guide', 'type' => 'text', 'content' => 'Clinical reference chart covering tube additives, inversion counts, and labelling.', 'duration' => 15, 'is_preview' => false ),
						array( 'title' => 'Preventing Complications, Fainting & Sharps Disposal', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 22, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Role of the Phlebotomist & Patient Consent Protocols',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 16,
					'is_preview' => '1',
					'content'    => '<h3>Patient-Centred Phlebotomy</h3><p>Learn the two-identifier patient verification protocol, obtaining valid informed consent, maintaining dignity, and adhering to clinical confidentiality.</p>',
				),
				array(
					'title'      => 'Vascular Anatomy & Safe Vein Selection Techniques',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 24,
					'is_preview' => '0',
					'content'    => '<h3>Anatomy of the Antecubital Fossa</h3><p>Understand the median cubital, cephalic, and basilic veins, how to palpate effectively, and which anatomical structures (arteries and nerves) must be avoided.</p>',
				),
				array(
					'title'      => 'Complete Order of Draw & Blood Tube Reference Guide',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 15,
					'is_preview' => '0',
					'content'    => '<h3>Standard Clinical Order of Draw</h3><p>Following the exact Order of Draw prevents additive carryover that can invalidate laboratory test results:</p><ol><li><strong>Blood Culture Bottles</strong> (Sterile — drawn first)</li><li><strong>Light Blue (Sodium Citrate)</strong> — Coagulation studies (PT/INR, APTT)</li><li><strong>Gold / Red (Serum Clot Activator)</strong> — Biochemistry &amp; serology</li><li><strong>Green (Heparin)</strong> — Stat chemistry panels</li><li><strong>Lavender / Purple (EDTA)</strong> — Full Blood Count (FBC) &amp; HbA1c</li><li><strong>Grey (Fluoride Oxalate)</strong> — Glucose &amp; lactate testing</li></ol><p><em>Always gently invert tubes (never shake) and label at the bedside immediately after collection.</em></p>',
				),
				array(
					'title'      => 'Preventing Complications, Fainting & Sharps Disposal',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 22,
					'is_preview' => '0',
					'content'    => '<h3>Managing Clinical Incidents</h3><p>Step-by-step response to vasovagal syncope (fainting), haematoma prevention, haemolysis avoidance, and immediate sharps injury reporting protocols.</p>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Anjali Menon', 'name' => 'Anjali Menon', 'rating' => 5, 'date' => '1 week ago', 'comment' => 'Dr. Priya explains the anatomy and order of draw with incredible clarity. Helped me ace my HCA hospital interview!' ),
				array( 'author' => 'Hannah Wright', 'name' => 'Hannah Wright', 'rating' => 5, 'date' => '3 weeks ago', 'comment' => 'Very well structured and easy to revise before practical clinical sign-offs.' ),
				array( 'author' => 'Suresh Nair', 'name' => 'Suresh Nair', 'rating' => 4, 'date' => '1 month ago', 'comment' => 'Great clinical reference notes and clear infection control walkthroughs.' ),
			),
		),

		// 3. Full-Stack Web Development Masterclass
		array(
			'slug'           => 'full-stack-web-development-masterclass',
			'title'          => 'Full-Stack Web Development Masterclass',
			'excerpt'        => 'Go from zero to production-ready Full-Stack Engineer. Build responsive front-ends, REST APIs, databases, and deployed web apps step by step.',
			'content'        => '<p>The <strong>Full-Stack Web Development Masterclass</strong> is a comprehensive, project-driven bootcamp covering modern HTML5, CSS3, JavaScript (ES6+), React, Node.js, Express, SQL/NoSQL databases, authentication, and cloud deployment.</p><h3>Build Real Portfolio Projects</h3><p>Instead of isolated syntax drills, you will architect and deploy complete applications including a RESTful SaaS dashboard, JWT authentication service, and responsive e-commerce storefront.</p>',
			'categories'     => array( 'it-cyber-security' ),
			'tags'           => array( 'JavaScript', 'React', 'Node.js', 'Full-Stack', 'Web Development' ),
			'level'          => 'All Levels',
			'level_slug'     => 'all-levels',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 12999,
			'sale_price'        => 3999,
			'regular_price_inr' => 12999,
			'sale_price_inr'    => 3999,
			'regular_price_gbp' => 399,
			'sale_price_gbp'    => 249,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 1860,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_1,
			'colors'         => array( '#0f172a', '#1e293b', '#e31b23' ),
			'cat_badge'      => 'IT & Cyber Security',
			'requirements'   => "A Windows, macOS, or Linux computer with internet access\nNo prior programming experience required — fundamentals are covered clearly\nEnthusiasm to build hands-on coding projects",
			'what_you_learn' => "Build semantic, accessible, mobile-first layouts using modern HTML5, Flexbox, and CSS Grid\nMaster modern JavaScript (ES6+), async/await, Fetch API, and clean modular architecture\nCreate interactive component-driven user interfaces with React and state management\nDesign secure REST APIs using Node.js, Express, and relational/document databases\nImplement user authentication, password hashing, RBAC, and input validation\nDeploy full-stack applications with Git workflows, environment configs, and CI/CD basics",
			'curriculum'     => array(
				array(
					'title'       => 'Section 1: Modern Front-End Foundations & JavaScript ES6+',
					'description' => '<p>Responsive UI architecture, DOM mastery, and asynchronous JavaScript.</p>',
					'lessons'     => array(
						array( 'title' => 'Full-Stack Architecture & Developer Environment Setup', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 22, 'is_preview' => true ),
						array( 'title' => 'Modern JavaScript ES6+, Modules & Async Data Fetching', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 35, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Section 2: Back-End APIs, Databases & Cloud Deployment',
					'description' => '<p>Build and secure scalable REST endpoints backed by structured data models.</p>',
					'lessons'     => array(
						array( 'title' => 'Designing RESTful APIs with Node.js, Express & Authentication', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 40, 'is_preview' => false ),
						array( 'title' => 'Production Deployment Checklist & Security Hardening', 'type' => 'text', 'content' => 'Environment variables, HTTPS, CORS, rate-limiting, and automated backups.', 'duration' => 18, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Full-Stack Architecture & Developer Environment Setup',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 22,
					'is_preview' => '1',
					'content'    => '<h3>Setting Up Your Engineering Workspace</h3><p>Learn how browsers, DNS, HTTP APIs, and databases communicate in a modern web stack, and configure VS Code, Git, and Node.js cleanly.</p>',
				),
				array(
					'title'      => 'Modern JavaScript ES6+, Modules & Async Data Fetching',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 35,
					'is_preview' => '0',
					'content'    => '<h3>Writing Clean Modern JavaScript</h3><p>Deep dive into arrow functions, destructuring, array transformations, Promises, <code>async/await</code>, and robust error handling with the Fetch API.</p>',
				),
				array(
					'title'      => 'Designing RESTful APIs with Node.js, Express & Authentication',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 40,
					'is_preview' => '0',
					'content'    => '<h3>Back-End Engineering</h3><p>Structure controllers, middleware, database schemas, and secure JWT/session authentication flows with proper HTTP status codes.</p>',
				),
				array(
					'title'      => 'Production Deployment Checklist & Security Hardening',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 18,
					'is_preview' => '0',
					'content'    => '<h3>Pre-Launch Production Checklist</h3><ul><li>Never commit <code>.env</code> secrets or API keys to Git.</li><li>Enable HTTPS/TLS, secure HTTP headers, and strict CORS origins.</li><li>Sanitize and validate all request payloads server-side.</li><li>Configure structured logging, health checks, and automated daily database backups.</li></ul>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Rohan Kulkarni', 'name' => 'Rohan Kulkarni', 'rating' => 5, 'date' => '6 days ago', 'comment' => 'The cleanest full-stack roadmap I have taken. Went from basic HTML to shipping my own SaaS app in 6 weeks!' ),
				array( 'author' => 'Neha Sharma', 'name' => 'Neha Sharma', 'rating' => 5, 'date' => '2 weeks ago', 'comment' => 'Arjun explains backend APIs and security practices like a true senior architect. Worth every rupee.' ),
				array( 'author' => 'Siddharth Rao', 'name' => 'Siddharth Rao', 'rating' => 5, 'date' => '1 month ago', 'comment' => 'Clear projects, zero filler, and great downloadable checklists.' ),
			),
		),

		// 4. Digital Marketing & SEO Bootcamp
		array(
			'slug'           => 'digital-marketing-seo-bootcamp',
			'title'          => 'Digital Marketing & SEO Bootcamp',
			'excerpt'        => 'Master technical & on-page SEO, Google Ads, Meta campaigns, content funnels, and GA4 analytics to drive measurable revenue growth.',
			'content'        => '<p>The <strong>Digital Marketing &amp; SEO Bootcamp</strong> gives entrepreneurs, freelancers, and marketing professionals a complete, data-driven playbook for acquiring organic and paid customers profitably.</p><h3>Practical Growth Frameworks</h3><p>Learn keyword intent mapping, high-converting landing page copywriting, paid search optimisation, social media funnels, and ROI dashboards you can apply immediately to any brand.</p>',
			'categories'     => array( 'business-management', 'personal-development' ),
			'tags'           => array( 'SEO', 'Digital Marketing', 'Google Ads', 'Analytics', 'Growth' ),
			'level'          => 'Beginner',
			'level_slug'     => 'beginner',
			'languages'      => array( 'english', 'hindi' ),
			'language_label' => 'English, Hindi',
			'regular_price'     => 7999,
			'sale_price'        => 2499,
			'regular_price_inr' => 7999,
			'sale_price_inr'    => 2499,
			'regular_price_gbp' => 249,
			'sale_price_gbp'    => 149,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 660,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_2,
			'colors'         => array( '#2b1055', '#7597de', '#e31b23' ),
			'cat_badge'      => 'Business & Management',
			'requirements'   => "No prior marketing experience needed\nHelpful if you have a website, business idea, or client project to practice on",
			'what_you_learn' => "Conduct high-intent keyword research and build topic-cluster SEO roadmaps\nOptimize on-page SEO, Core Web Vitals, internal linking, and schema markup\nLaunch and scale profitable Google Search Ads and Meta social campaigns\nBuild email automation sequences and high-converting landing pages\nTrack conversions, attribution, and ROAS accurately in Google Analytics 4",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: Complete SEO Strategy & Keyword Research',
					'description' => '<p>Rank sustainably in organic search with technical and content SEO.</p>',
					'lessons'     => array(
						array( 'title' => 'Digital Marketing Funnel & Buyer Intent Mapping', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 20, 'is_preview' => true ),
						array( 'title' => 'On-Page, Technical SEO & High-Authority Content Clusters', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 28, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: Paid Acquisition & GA4 Analytics Mastery',
					'description' => '<p>Turn ad spend into predictable leads with conversion tracking.</p>',
					'lessons'     => array(
						array( 'title' => 'Google Ads, Meta Funnels & GA4 Conversion Tracking Playbook', 'type' => 'text', 'content' => 'Campaign structure, bidding frameworks, and weekly KPI optimisation.', 'duration' => 22, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Digital Marketing Funnel & Buyer Intent Mapping',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 20,
					'is_preview' => '1',
					'content'    => '<h3>Building a Full-Funnel Strategy</h3><p>Map awareness, consideration, and conversion touchpoints so every SEO article and ad campaign works together seamlessly.</p>',
				),
				array(
					'title'      => 'On-Page, Technical SEO & High-Authority Content Clusters',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 28,
					'is_preview' => '0',
					'content'    => '<h3>Modern SEO Execution</h3><p>Learn title tag optimisation, semantic headings, structured data, crawl health, and building pillar pages that rank for competitive commercial terms.</p>',
				),
				array(
					'title'      => 'Google Ads, Meta Funnels & GA4 Conversion Tracking Playbook',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 22,
					'is_preview' => '0',
					'content'    => '<h3>Paid Media &amp; Attribution Essentials</h3><p>Before spending a single rupee on ads, verify your conversion tracking:</p><ul><li>Define primary conversion events (checkout completion, qualified lead form).</li><li>Separate branded, high-intent non-branded, and retargeting campaigns.</li><li>Review Search Term reports weekly to add negative keywords and boost ROAS.</li></ul>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Pooja Malhotra', 'name' => 'Pooja Malhotra', 'rating' => 5, 'date' => '10 days ago', 'comment' => 'Applied the SEO content cluster strategy to our company blog and doubled organic enquiries in 60 days!' ),
				array( 'author' => 'Karan Kapoor', 'name' => 'Karan Kapoor', 'rating' => 5, 'date' => '1 month ago', 'comment' => 'Very actionable and up to date with GA4 and modern paid funnels.' ),
			),
		),

		// 5. Health & Social Care Level 3 Diploma
		array(
			'slug'           => 'health-social-care-level-3-diploma',
			'title'          => 'Health & Social Care Level 3 Diploma',
			'excerpt'        => 'Develop advanced care competencies in safeguarding, person-centred support, mental capacity, and leadership across adult health and social care.',
			'content'        => '<p>The <strong>Health &amp; Social Care Level 3 Diploma</strong> prepares care practitioners and senior support workers to deliver compassionate, compliant, person-centred care across residential, domiciliary, and clinical settings.</p><h3>Key Focus Areas</h3><p>Master safeguarding vulnerable adults, Duty of Care, health and safety risk assessments, effective multi-disciplinary communication, and equality &amp; inclusion best practices.</p>',
			'categories'     => array( 'health-social-care' ),
			'tags'           => array( 'Health & Social Care', 'Safeguarding', 'Level 3 Diploma', 'Care Leadership' ),
			'level'          => 'Intermediate',
			'level_slug'     => 'intermediate',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 11999,
			'sale_price'        => 3999,
			'regular_price_inr' => 11999,
			'sale_price_inr'    => 3999,
			'regular_price_gbp' => 449,
			'sale_price_gbp'    => 249,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 900,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_1,
			'colors'         => array( '#0d4f4f', '#146b6b', '#e31b23' ),
			'cat_badge'      => 'Health & Social Care',
			'requirements'   => "Ideal for care workers, senior support workers, and aspiring care coordinators\nBasic understanding of healthcare or community support environments is helpful",
			'what_you_learn' => "Implement person-centred care plans that uphold dignity, choice, and independence\nRecognize signs of abuse and follow statutory safeguarding reporting procedures\nApply Duty of Care, Mental Capacity principles, and infection prevention standards\nCommunicate effectively with service users, families, and multidisciplinary teams\nManage health & safety risk assessments and medication governance safely",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: Person-Centred Practice & Effective Communication',
					'description' => '<p>Building dignity-first care relationships and overcoming communication barriers.</p>',
					'lessons'     => array(
						array( 'title' => 'Foundations of Person-Centred Health & Social Care', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 22, 'is_preview' => true ),
						array( 'title' => 'Safeguarding Vulnerable Adults & Duty of Care Guide', 'type' => 'text', 'content' => 'Recognizing indicators of neglect/abuse and escalation workflows.', 'duration' => 25, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: Risk Management, Governance & Care Leadership',
					'description' => '<p>Leading shift handovers, audits, and compliant care documentation.</p>',
					'lessons'     => array(
						array( 'title' => 'Health & Safety Risk Assessments & Care Plan Audits', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 24, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Foundations of Person-Centred Health & Social Care',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 22,
					'is_preview' => '1',
					'content'    => '<h3>Core Values in Modern Care</h3><p>Explore the 6Cs of Care (Care, Compassion, Competence, Communication, Courage, Commitment) and how person-centred planning empowers service users.</p>',
				),
				array(
					'title'      => 'Safeguarding Vulnerable Adults & Duty of Care Guide',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 25,
					'is_preview' => '0',
					'content'    => '<h3>Safeguarding Principles &amp; Reporting</h3><p>Every health and social care worker holds a statutory Duty of Care to protect vulnerable individuals from harm.</p><ul><li><strong>Six Safeguarding Principles:</strong> Empowerment, Prevention, Proportionality, Protection, Partnership, and Accountability.</li><li><strong>Immediate Action:</strong> Ensure the individual is safe, listen without leading questions, document factual observations immediately, and report to the Designated Safeguarding Lead.</li></ul>',
				),
				array(
					'title'      => 'Health & Safety Risk Assessments & Care Plan Audits',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 24,
					'is_preview' => '0',
					'content'    => '<h3>Balancing Safety with Independence</h3><p>Learn positive risk-taking frameworks, manual handling compliance, incident reporting, and maintaining inspection-ready care records.</p>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Grace Okafor', 'name' => 'Grace Okafor', 'rating' => 5, 'date' => '2 weeks ago', 'comment' => 'Clear, empathetic, and thoroughly aligned with Level 3 senior carer responsibilities. Helped me earn my promotion!' ),
				array( 'author' => 'Deepak Nair', 'name' => 'Deepak Nair', 'rating' => 5, 'date' => '1 month ago', 'comment' => 'Excellent safeguarding and risk assessment modules with realistic scenarios.' ),
			),
		),

		// 6. Cyber Security & Ethical Hacking
		array(
			'slug'           => 'cyber-security-ethical-hacking',
			'title'          => 'Cyber Security & Ethical Hacking',
			'excerpt'        => 'Learn network defence, OWASP Top 10 web penetration testing, vulnerability assessment, and SOC incident response with hands-on security labs.',
			'content'        => '<p>Our <strong>Cyber Security &amp; Ethical Hacking</strong> programme trains IT professionals and security enthusiasts to think like defenders and ethical penetration testers. Master reconnaissance, vulnerability scanning, web application security, SIEM monitoring, and executive risk reporting.</p><h3>Ethics &amp; Compliance First</h3><p>All offensive techniques are taught strictly within authorized lab environments and aligned with responsible disclosure and industry security frameworks.</p>',
			'categories'     => array( 'it-cyber-security' ),
			'tags'           => array( 'Cyber Security', 'Ethical Hacking', 'OWASP', 'Penetration Testing', 'SOC' ),
			'level'          => 'Advanced',
			'level_slug'     => 'advanced',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 15999,
			'sale_price'        => 5499,
			'regular_price_inr' => 15999,
			'sale_price_inr'    => 5499,
			'regular_price_gbp' => 499,
			'sale_price_gbp'    => 299,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 1440,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_2,
			'colors'         => array( '#111827', '#1f2937', '#e31b23' ),
			'cat_badge'      => 'IT & Cyber Security',
			'requirements'   => "Basic understanding of computer networks (TCP/IP, HTTP/HTTPS) and Linux command line\n8 GB+ RAM computer recommended for running isolated virtual security labs",
			'what_you_learn' => "Perform authorized network reconnaissance, port scanning, and service enumeration\nIdentify and remediate OWASP Top 10 vulnerabilities (SQLi, XSS, CSRF, broken auth)\nConfigure firewalls, IDS/IPS rules, and SIEM log analysis for threat detection\nConduct structured vulnerability assessments and prioritize fixes using CVSS\nWrite executive-grade penetration test reports with actionable remediation steps\nImplement incident response playbooks to contain and recover from breaches",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: Security Foundations & Network Reconnaissance',
					'description' => '<p>Threat modelling, lab isolation, packet analysis, and enumeration.</p>',
					'lessons'     => array(
						array( 'title' => 'Ethical Hacking Methodology & Safe Lab Setup', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 24, 'is_preview' => true ),
						array( 'title' => 'Network Scanning, Enumeration & Vulnerability Discovery', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 32, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: OWASP Web Security & Incident Response',
					'description' => '<p>Auditing modern web apps and defending production infrastructure.</p>',
					'lessons'     => array(
						array( 'title' => 'Auditing OWASP Top 10 Vulnerabilities in Web Applications', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 38, 'is_preview' => false ),
						array( 'title' => 'SOC Incident Response & Remediation Report Template', 'type' => 'text', 'content' => 'Six-phase NIST incident response guide and CVSS reporting framework.', 'duration' => 20, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Ethical Hacking Methodology & Safe Lab Setup',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 24,
					'is_preview' => '1',
					'content'    => '<h3>Rules of Engagement &amp; Isolated Labs</h3><p>Understand legal authorization, scope boundaries, and how to deploy a safe virtualized test network before running any security tooling.</p>',
				),
				array(
					'title'      => 'Network Scanning, Enumeration & Vulnerability Discovery',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 32,
					'is_preview' => '0',
					'content'    => '<h3>Mapping the Attack Surface</h3><p>Discover open ports, fingerprint services, inspect TLS configurations, and validate findings to eliminate false positives.</p>',
				),
				array(
					'title'      => 'Auditing OWASP Top 10 Vulnerabilities in Web Applications',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 38,
					'is_preview' => '0',
					'content'    => '<h3>Web Application Security</h3><p>Hands-on analysis of Broken Access Control, Injection flaws, XSS, CSRF, and SSRF — paired with exact defensive coding patterns to patch each vulnerability.</p>',
				),
				array(
					'title'      => 'SOC Incident Response & Remediation Report Template',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 20,
					'is_preview' => '0',
					'content'    => '<h3>6 Phases of Incident Response</h3><ol><li><strong>Preparation:</strong> Asset inventory, SIEM alerts, and backup verification.</li><li><strong>Identification:</strong> Triage anomalies, confirm IOCs, and classify severity.</li><li><strong>Containment:</strong> Isolate compromised hosts while preserving forensic evidence.</li><li><strong>Eradication:</strong> Remove persistence mechanisms and patch root causes.</li><li><strong>Recovery:</strong> Restore clean systems and monitor closely.</li><li><strong>Lessons Learned:</strong> Update runbooks and security controls.</li></ol>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Vikramaditya Singh', 'name' => 'Vikramaditya Singh', 'rating' => 5, 'date' => '5 days ago', 'comment' => 'Deep technical coverage without skipping defensive remediation. Helped me transition into a Junior SOC Analyst role.' ),
				array( 'author' => 'Tanvi Deshpande', 'name' => 'Tanvi Deshpande', 'rating' => 5, 'date' => '3 weeks ago', 'comment' => 'The OWASP walkthroughs and reporting templates are super practical.' ),
			),
		),

		// 7. Executive Leadership & Management
		array(
			'slug'           => 'executive-leadership-management',
			'title'          => 'Executive Leadership & Management',
			'excerpt'        => 'Elevate your strategic leadership, executive communication, conflict resolution, OKR execution, and high-performing team management skills.',
			'content'        => '<p>Designed for emerging managers, department heads, and founders, <strong>Executive Leadership &amp; Management</strong> bridges the gap between operational management and visionary leadership.</p><h3>Lead With Clarity &amp; Impact</h3><p>Learn proven frameworks for strategic planning, delegation without micromanaging, coaching diverse teams, managing change, and presenting persuasively to executive stakeholders.</p>',
			'categories'     => array( 'leadership-management', 'business-management' ),
			'tags'           => array( 'Leadership', 'Management', 'Strategy', 'Executive Coaching', 'OKRs' ),
			'level'          => 'Advanced',
			'level_slug'     => 'advanced',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 10999,
			'sale_price'        => 3299,
			'regular_price_inr' => 10999,
			'sale_price_inr'    => 3299,
			'regular_price_gbp' => 349,
			'sale_price_gbp'    => 199,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 600,
			'bestseller'     => '0',
			'intro_video'    => $sample_vid_1,
			'colors'         => array( '#1b263b', '#415a77', '#e31b23' ),
			'cat_badge'      => 'Leadership & Management',
			'requirements'   => "Ideal for team leads, supervisors, project managers, and aspiring executives\nNo formal management degree required",
			'what_you_learn' => "Transition from individual contributor to high-trust strategic leader\nDesign measurable OKRs and KPI scorecards that align cross-functional teams\nRun high-impact 1:1 coaching sessions and deliver constructive performance feedback\nNavigate organizational change and resolve workplace conflicts constructively\nCommunicate strategic roadmaps persuasively to boards and senior stakeholders",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: Strategic Leadership & High-Trust Teams',
					'description' => '<p>Leadership styles, psychological safety, and effective delegation.</p>',
					'lessons'     => array(
						array( 'title' => 'Transitioning from Manager to Strategic Executive Leader', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 21, 'is_preview' => true ),
						array( 'title' => 'Setting Aligned OKRs, KPIs & Accountability Cadences', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 25, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: Change Management & Executive Communication',
					'description' => '<p>Leading teams through transformation and difficult stakeholder conversations.</p>',
					'lessons'     => array(
						array( 'title' => 'Executive Communication & Conflict Resolution Playbook', 'type' => 'text', 'content' => 'Frameworks for board briefings, difficult feedback, and change buy-in.', 'duration' => 18, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Transitioning from Manager to Strategic Executive Leader',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 21,
					'is_preview' => '1',
					'content'    => '<h3>The Executive Mindset Shift</h3><p>Learn how to shift from solving every daily fire yourself to building autonomous, accountable systems and empowering high-performing leaders beneath you.</p>',
				),
				array(
					'title'      => 'Setting Aligned OKRs, KPIs & Accountability Cadences',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 25,
					'is_preview' => '0',
					'content'    => '<h3>Execution Frameworks</h3><p>Translate annual company strategy into quarterly Objectives &amp; Key Results (OKRs) with clear ownership and frictionless weekly review rituals.</p>',
				),
				array(
					'title'      => 'Executive Communication & Conflict Resolution Playbook',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 18,
					'is_preview' => '0',
					'content'    => '<h3>The SBI Feedback Model</h3><p>Use <strong>Situation – Behaviour – Impact</strong> to deliver objective, non-defensive coaching conversations:</p><ol><li><strong>Situation:</strong> Anchor to a specific meeting or deliverable.</li><li><strong>Behaviour:</strong> Describe observable actions without assumptions.</li><li><strong>Impact:</strong> Share the measurable effect on the team or customer, then co-create the next step.</li></ol>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Rajeshwari Iyer', 'name' => 'Rajeshwari Iyer', 'rating' => 5, 'date' => '2 weeks ago', 'comment' => 'Practical leadership frameworks I immediately used in my quarterly planning and 1:1s.' ),
				array( 'author' => 'Oliver Grant', 'name' => 'Oliver Grant', 'rating' => 4, 'date' => '1 month ago', 'comment' => 'Concise, sharp, and immediately applicable for newly promoted heads of department.' ),
			),
		),

		// 8. UI/UX Product Design Complete Course
		array(
			'slug'           => 'ui-ux-product-design-complete-course',
			'title'          => 'UI/UX Product Design Complete Course',
			'excerpt'        => 'Master user research, wireframing, Figma design systems, interactive prototyping, and usability testing to launch your product design career.',
			'content'        => '<p>The <strong>UI/UX Product Design Complete Course</strong> takes you through the complete end-to-end digital product design lifecycle. Learn how top design teams discover user needs, structure intuitive information architecture, build scalable Figma component libraries, and hand off pixel-perfect specs to engineers.</p><h3>Portfolio-Ready Case Study</h3><p>Graduate with a polished mobile &amp; web product case study ready to showcase to hiring managers and freelance clients.</p>',
			'categories'     => array( 'design-creative', 'personal-development' ),
			'tags'           => array( 'UI/UX', 'Figma', 'Product Design', 'Design Systems', 'Prototyping' ),
			'level'          => 'Beginner',
			'level_slug'     => 'beginner',
			'languages'      => array( 'english' ),
			'language_label' => 'English',
			'regular_price'     => 8999,
			'sale_price'        => 2999,
			'regular_price_inr' => 8999,
			'sale_price_inr'    => 2999,
			'regular_price_gbp' => 299,
			'sale_price_gbp'    => 179,
			'sale_end_date'     => $sale_end_date,
			'duration'       => 840,
			'bestseller'     => '1',
			'intro_video'    => $sample_vid_2,
			'colors'         => array( '#3b0764', '#7e22ce', '#e31b23' ),
			'cat_badge'      => 'Design & Creative',
			'requirements'   => "No prior design or coding background required\nA free Figma account (works directly in any modern browser)",
			'what_you_learn' => "Conduct user interviews, journey mapping, and competitive UX audits\nCreate clear user flows, low-fidelity wireframes, and responsive layouts\nMaster visual hierarchy, typography scales, colour contrast, and WCAG accessibility\nBuild reusable Auto-Layout design systems and interactive prototypes in Figma\nRun moderated usability tests and iterate designs using real user feedback\nPresent compelling UX case studies and collaborate cleanly with developers",
			'curriculum'     => array(
				array(
					'title'       => 'Module 1: UX Research, User Flows & Wireframing',
					'description' => '<p>Understand user problems deeply before opening a visual design tool.</p>',
					'lessons'     => array(
						array( 'title' => 'Product Design Thinking & User Research Essentials', 'type' => 'video', 'content' => $sample_vid_2, 'duration' => 19, 'is_preview' => true ),
						array( 'title' => 'Visual Hierarchy, Typography & Figma Design Systems', 'type' => 'video', 'content' => $sample_vid_1, 'duration' => 30, 'is_preview' => false ),
					),
				),
				array(
					'title'       => 'Module 2: Interactive Prototyping, Testing & Handoff',
					'description' => '<p>Validate prototypes with users and ship clean specs to engineering.</p>',
					'lessons'     => array(
						array( 'title' => 'Usability Testing Script & Developer Handoff Checklist', 'type' => 'text', 'content' => '5-user testing workflow, accessibility contrast checks, and Figma Dev Mode.', 'duration' => 16, 'is_preview' => false ),
					),
				),
			),
			'lessons'        => array(
				array(
					'title'      => 'Product Design Thinking & User Research Essentials',
					'type'       => 'video',
					'video_url'  => $sample_vid_2,
					'duration'   => 19,
					'is_preview' => '1',
					'content'    => '<h3>Designing for Real Humans</h3><p>Learn how to frame problem statements, craft unbiased user interview questions, and synthesize insights into actionable personas and journey maps.</p>',
				),
				array(
					'title'      => 'Visual Hierarchy, Typography & Figma Design Systems',
					'type'       => 'video',
					'video_url'  => $sample_vid_1,
					'duration'   => 30,
					'is_preview' => '0',
					'content'    => '<h3>Crafting Scalable UI Systems</h3><p>Master 8pt spacing grids, modular type scales, WCAG AA colour contrast, and reusable Figma components with Auto-Layout and design tokens.</p>',
				),
				array(
					'title'      => 'Usability Testing Script & Developer Handoff Checklist',
					'type'       => 'text',
					'video_url'  => '',
					'duration'   => 16,
					'is_preview' => '0',
					'content'    => '<h3>Before Developer Handoff</h3><ul><li>Verify empty, loading, error, and responsive mobile states for every screen.</li><li>Check WCAG AA contrast (at least 4.5:1 for body text).</li><li>Name layers and component variants consistently so engineering tokens map 1:1.</li></ul>',
				),
			),
			'reviews'        => array(
				array( 'author' => 'Shreya Bose', 'name' => 'Shreya Bose', 'rating' => 5, 'date' => '4 days ago', 'comment' => 'Loved the Figma design system workflow! Built my first portfolio case study and landed freelance UI/UX clients right away.' ),
				array( 'author' => 'Aditya Verma', 'name' => 'Aditya Verma', 'rating' => 5, 'date' => '3 weeks ago', 'comment' => 'Balances UX research and crisp visual UI brilliantly.' ),
			),
		),
	);
}

/**
 * Create the 8 premium lms_course posts, attach SVG featured images, assign taxonomies,
 * write all course meta keys, and create 3-4 linked lms_lesson posts per course.
 *
 * @param bool $force Whether to update existing demo courses & lessons.
 * @return array Counts of courses and lessons created/verified.
 */
function edupro_demo_create_courses_and_lessons( $force = false ) {
	$author_id     = get_current_user_id() ?: 1;
	$catalog       = edupro_demo_get_courses_catalog();
	$courses_count = 0;
	$lessons_count = 0;

	foreach ( $catalog as $idx => $course ) {
		$existing = get_page_by_path( $course['slug'], OBJECT, 'lms_course' );

		if ( $existing && 'trash' !== $existing->post_status ) {
			$course_id = (int) $existing->ID;
		} else {
			$course_id = wp_insert_post(
				array(
					'post_title'   => $course['title'],
					'post_name'    => $course['slug'],
					'post_content' => $course['content'],
					'post_excerpt' => $course['excerpt'],
					'post_status'  => 'publish',
					'post_type'    => 'lms_course',
					'post_author'  => $author_id,
					'menu_order'   => $idx + 1,
				)
			);
		}

		if ( ! $course_id || is_wp_error( $course_id ) ) {
			continue;
		}

		$courses_count++;

		// Assign taxonomies.
		if ( ! empty( $course['categories'] ) ) {
			wp_set_object_terms( $course_id, $course['categories'], 'course_category', false );
		}
		if ( ! empty( $course['level_slug'] ) ) {
			wp_set_object_terms( $course_id, array( $course['level_slug'] ), 'course_level', false );
		}
		if ( ! empty( $course['languages'] ) ) {
			wp_set_object_terms( $course_id, $course['languages'], 'course_language', false );
		}
		if ( ! empty( $course['tags'] ) ) {
			wp_set_object_terms( $course_id, $course['tags'], 'course_tag', false );
		}

		// Pricing meta (100% aligned with inc/metaboxes.php, inc/currency.php & inc/payment-gateways.php).
		$effective_price = (float) $course['sale_price'] > 0 ? (float) $course['sale_price'] : (float) $course['regular_price'];
		update_post_meta( $course_id, '_edupro_regular_price', (float) $course['regular_price'] );
		update_post_meta( $course_id, '_edupro_sale_price', (float) $course['sale_price'] );
		update_post_meta( $course_id, '_edupro_sale_end_date', $course['sale_end_date'] );
		update_post_meta( $course_id, '_edupro_is_free', '0' );
		update_post_meta( $course_id, '_edupro_course_price', $effective_price );
		update_post_meta( $course_id, '_edupro_course_free', '0' );
		update_post_meta( $course_id, '_edupro_enrollment_limit', 0 );

		// Regional dual-currency overrides (UK GBP £ + India INR ₹).
		if ( isset( $course['regular_price_gbp'] ) ) {
			update_post_meta( $course_id, '_edupro_regular_price_gbp', (float) $course['regular_price_gbp'] );
		}
		if ( isset( $course['sale_price_gbp'] ) ) {
			update_post_meta( $course_id, '_edupro_sale_price_gbp', (float) $course['sale_price_gbp'] );
		}
		if ( isset( $course['regular_price_inr'] ) ) {
			update_post_meta( $course_id, '_edupro_regular_price_inr', (float) $course['regular_price_inr'] );
		}
		if ( isset( $course['sale_price_inr'] ) ) {
			update_post_meta( $course_id, '_edupro_sale_price_inr', (float) $course['sale_price_inr'] );
		}

		// Course details meta.
		update_post_meta( $course_id, '_edupro_duration', absint( $course['duration'] ) );
		update_post_meta( $course_id, '_edupro_course_level', $course['level'] );
		update_post_meta( $course_id, '_edupro_language', $course['language_label'] );
		update_post_meta( $course_id, '_edupro_requirements', $course['requirements'] );
		update_post_meta( $course_id, '_edupro_what_you_learn', $course['what_you_learn'] );
		update_post_meta( $course_id, '_edupro_certificate', '1' );
		update_post_meta( $course_id, '_edupro_course_intro_video', esc_url_raw( $course['intro_video'] ) );
		update_post_meta( $course_id, '_edupro_curriculum', wp_json_encode( $course['curriculum'] ) );
		update_post_meta( $course_id, '_edupro_is_bestseller', $course['bestseller'] );
		update_post_meta( $course_id, '_edupro_meta_title', $course['title'] . ' | EduPro' );
		update_post_meta( $course_id, '_edupro_meta_description', wp_trim_words( $course['excerpt'], 24, '…' ) );
		update_post_meta( $course_id, '_edupro_demo_item', '1' );

		// Seed course-level reviews if empty (powers star ratings & single course Reviews tab).
		$existing_reviews = get_post_meta( $course_id, 'course_reviews', true );
		if ( ( empty( $existing_reviews ) || $force ) && ! empty( $course['reviews'] ) ) {
			update_post_meta( $course_id, 'course_reviews', $course['reviews'] );
		}

		// Attach clean generated local SVG featured image.
		if ( ! has_post_thumbnail( $course_id ) || $force ) {
			$svg    = edupro_demo_build_course_svg(
				$course['title'],
				$course['cat_badge'],
				$course['level'],
				$course['colors'][0],
				$course['colors'][1],
				$course['colors'][2]
			);
			$att_id = edupro_demo_create_svg_attachment( 'course-' . $course['slug'], $course['title'], $svg, 800, 500 );
			if ( $att_id ) {
				set_post_thumbnail( $course_id, $att_id );
			}
		}

		// Create linked lms_lesson posts for self-paced learning.
		if ( ! empty( $course['lessons'] ) ) {
			foreach ( $course['lessons'] as $order_idx => $lesson ) {
				$lesson_order = $order_idx + 1;
				$lesson_slug  = sanitize_title( $course['slug'] . '-lesson-' . $lesson_order );
				$existing_les = get_page_by_path( $lesson_slug, OBJECT, 'lms_lesson' );

				if ( $existing_les && 'trash' !== $existing_les->post_status ) {
					$lesson_id = (int) $existing_les->ID;
				} else {
					$lesson_id = wp_insert_post(
						array(
							'post_title'   => $lesson['title'],
							'post_name'    => $lesson_slug,
							'post_content' => $lesson['content'],
							'post_status'  => 'publish',
							'post_type'    => 'lms_lesson',
							'post_author'  => $author_id,
							'menu_order'   => $lesson_order,
						)
					);
				}

				if ( $lesson_id && ! is_wp_error( $lesson_id ) ) {
					update_post_meta( $lesson_id, '_edupro_course_id', $course_id );
					update_post_meta( $lesson_id, '_edupro_lesson_type', $lesson['type'] );
					update_post_meta( $lesson_id, '_edupro_video_url', ! empty( $lesson['video_url'] ) ? esc_url_raw( $lesson['video_url'] ) : '' );
					update_post_meta( $lesson_id, '_edupro_video_duration', (float) $lesson['duration'] );
					update_post_meta( $lesson_id, '_edupro_is_preview', '1' === (string) $lesson['is_preview'] ? '1' : '0' );
					update_post_meta( $lesson_id, '_edupro_lesson_order', $lesson_order );
					update_post_meta( $lesson_id, '_edupro_demo_item', '1' );
					$lessons_count++;
				}
			}
		}
	}

	return array(
		'courses' => $courses_count,
		'lessons' => $lessons_count,
	);
}

// =============================================================================
// 8. SEED 6 VERIFIED GOOGLE / STUDENT REVIEWS (inc/google-reviews.php)
// =============================================================================

/**
 * Seed 6 realistic student/Google reviews into `edupro_manual_reviews` only if empty.
 * Admin can switch to live Google Place ID / listing URL anytime under Appearance → Google Reviews.
 *
 * @param bool $force Whether to overwrite existing manual reviews.
 * @return int Number of reviews available.
 */
function edupro_demo_seed_google_reviews( $force = false ) {
	$existing = get_option( 'edupro_manual_reviews', array() );

	if ( ! empty( $existing ) && is_array( $existing ) && ! $force ) {
		return count( $existing );
	}

	$demo_reviews = array(
		array(
			'name'   => 'Aarav Sharma',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-4 days' ) ),
			'photo'  => '',
			'text'   => 'EduPro completely transformed my career trajectory. The Full-Stack Web Development Masterclass was structured, hands-on, and helped me land a senior engineering offer within six weeks of graduating.',
		),
		array(
			'name'   => 'Priya Nair',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-11 days' ) ),
			'photo'  => '',
			'text'   => 'Completed the Level 3 CAVA Assessor course while working full-time. Every module was crystal clear, the lesson player worked seamlessly on my laptop and phone, and my certificate was ready instantly.',
		),
		array(
			'name'   => 'Dr. Rohan Verma',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-3 weeks' ) ),
			'photo'  => '',
			'text'   => 'Outstanding Phlebotomy and Health & Social Care training. The clinical protocols, order of draw charts, and safeguarding checklists are practical and up to date with real workplace standards.',
		),
		array(
			'name'   => 'Sneha Patel',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-1 month' ) ),
			'photo'  => '',
			'text'   => 'I took the UI/UX Product Design and Digital Marketing bootcamps. The student dashboard makes tracking progress effortless, and the instructors explain complex concepts with zero fluff.',
		),
		array(
			'name'   => 'Vikram Desai',
			'rating' => 5,
			'date'   => date_i18n( 'M j, Y', strtotime( '-6 weeks' ) ),
			'photo'  => '',
			'text'   => 'Best value online academy! Smooth checkout, instant lifetime access, and genuinely responsive learner support. The Cyber Security course labs were top-notch.',
		),
		array(
			'name'   => 'Ananya Iyer',
			'rating' => 4.9,
			'date'   => date_i18n( 'M j, Y', strtotime( '-2 months' ) ),
			'photo'  => '',
			'text'   => 'Enrolled our team leads in Executive Leadership & Management. Within a month we saw sharper OKR tracking, better 1:1 coaching, and measurable productivity gains across departments.',
		),
	);

	update_option( 'edupro_manual_reviews', $demo_reviews );

	if ( '' === get_option( 'edupro_google_reviews_mode', '' ) ) {
		update_option( 'edupro_google_reviews_mode', 'manual' );
	}
	if ( '' === get_option( 'edupro_manual_overall_rating', '' ) ) {
		update_option( 'edupro_manual_overall_rating', 4.9 );
	}
	if ( '' === get_option( 'edupro_manual_total_reviews', '' ) ) {
		update_option( 'edupro_manual_total_reviews', 284 );
	}
	if ( '' === get_option( 'edupro_google_business_name', '' ) ) {
		update_option( 'edupro_google_business_name', get_bloginfo( 'name' ) );
	}

	return count( $demo_reviews );
}

/**
 * Seed default multi-currency & exchange rate options if not already configured.
 */
function edupro_demo_seed_currency_defaults() {
	if ( '' === get_option( 'edupro_default_currency', '' ) ) {
		update_option( 'edupro_default_currency', 'INR' );
	}
	if ( '' === get_option( 'edupro_rate_gbp_to_inr', '' ) ) {
		update_option( 'edupro_rate_gbp_to_inr', 108 );
	}
	if ( '' === get_option( 'edupro_rate_usd_to_inr', '' ) ) {
		update_option( 'edupro_rate_usd_to_inr', 84 );
	}
}

/**
 * Seed starter Website Settings (`edupro_site_settings`) ONLY if the option is empty.
 * Admin can edit every field cleanly under WP Admin → Website Settings without touching code.
 */
function edupro_demo_seed_site_settings() {
	$existing = get_option( 'edupro_site_settings', array() );
	if ( ! empty( $existing ) && is_array( $existing ) ) {
		return;
	}

	$site_name   = get_bloginfo( 'name' ) ?: 'EduPro LMS';
	$tagline     = get_bloginfo( 'description' ) ?: __( 'Accredited Online Courses & Career Qualifications', 'edupro' );
	$admin_email = get_option( 'admin_email', 'support@eduproacademy.com' );
	$courses_url = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );

	$defaults = array(
		// Tab 1 — Brand & Identity
		'site_name'                 => $site_name,
		'site_tagline'              => $tagline,
		'header_logo_id'            => 0,
		'header_logo_url'           => '',
		'footer_logo_id'            => 0,
		'footer_logo_url'           => '',
		'primary_color'             => '#e31b23',
		'announcement_enabled'      => '1',
		'announcement_text'         => __( '🎓 LIMITED TIME OFFER — Save up to 70% + Extra 20% OFF with code WELCOME20!', 'edupro' ),
		'announcement_cta_text'     => __( 'Explore Courses →', 'edupro' ),
		'announcement_cta_url'      => $courses_url,

		// Tab 2 — Contact, Phone & WhatsApp
		'phone_primary'             => '+44 20 7946 0192',
		'phone_secondary'           => '+91 80 4568 2100',
		'whatsapp_number'           => '+447700900123',
		'whatsapp_message'          => __( 'Hi! I would like to enquire about EduPro courses and enrolment.', 'edupro' ),
		'whatsapp_floating_enabled' => '1',
		'email_support'             => $admin_email,
		'email_sales'               => $admin_email,
		'business_hours'            => __( 'Mon – Sat: 9:00 AM – 7:00 PM (UK & India Support)', 'edupro' ),
		'address_primary'           => __( 'EduPro Learning Hub, 42 MG Road, Bengaluru, Karnataka 560001, India', 'edupro' ),
		'address_secondary'         => __( 'EduPro Academy UK, 128 City Road, London EC1V 2NX, United Kingdom', 'edupro' ),

		// Tab 3 — Google Maps
		'map_embed'                 => 'London, UK',
		'map_directions_url'        => 'https://maps.google.com/?q=London,+UK',
		'map_height'                => 380,

		// Tab 4 — Social Links
		'social_facebook'           => 'https://facebook.com',
		'social_instagram'          => 'https://instagram.com',
		'social_youtube'            => 'https://youtube.com',
		'social_linkedin'           => 'https://linkedin.com',
		'social_twitter'            => 'https://x.com',
		'social_tiktok'             => '',
		'social_telegram'           => '',

		// Tab 5 — Footer & Legal
		'footer_about'              => __( 'Master in-demand vocational, healthcare, business, and technology skills with expert instructors, verified certificates, and lifetime course access.', 'edupro' ),
		'footer_trust_badge'        => __( 'Rated 4.9/5 Excellent by 10,000+ Learners', 'edupro' ),
		'footer_copyright'          => '© {year} {site_name}. All rights reserved.',
	);

	update_option( 'edupro_site_settings', $defaults );
}

/**
 * Seed starter UK + India Bank Transfer details and enable Bank Transfer ONLY if zero
 * payment gateways are enabled yet, so checkout works cleanly out of the box.
 */
function edupro_demo_seed_default_gateway_if_empty() {
	$any_enabled = (
		'1' === get_option( 'edupro_cashfree_enabled', '0' ) ||
		'1' === get_option( 'edupro_razorpay_enabled', '0' ) ||
		'1' === get_option( 'edupro_stripe_enabled', '0' ) ||
		'1' === get_option( 'edupro_paypal_enabled', '0' ) ||
		'1' === get_option( 'edupro_bank_transfer_enabled', '0' )
	);

	if ( ! $any_enabled ) {
		update_option( 'edupro_bank_transfer_enabled', '1' );
	}

	// Starter India bank & UPI details (populated only when blank).
	if ( '' === get_option( 'edupro_bank_name', '' ) ) {
		update_option( 'edupro_bank_name', 'EduPro Academy India Bank (Update in Settings → Payment Gateways)' );
	}
	if ( '' === get_option( 'edupro_bank_account_name', '' ) ) {
		update_option( 'edupro_bank_account_name', 'EduPro Learning Academy Pvt. Ltd.' );
	}
	if ( '' === get_option( 'edupro_bank_account_number', '' ) ) {
		update_option( 'edupro_bank_account_number', '00001234567890' );
	}
	if ( '' === get_option( 'edupro_bank_ifsc', '' ) ) {
		update_option( 'edupro_bank_ifsc', 'EDUP0001001' );
	}
	if ( '' === get_option( 'edupro_bank_upi_id', '' ) ) {
		update_option( 'edupro_bank_upi_id', 'eduproacademy@upi' );
	}

	// Starter UK Faster Payments / IBAN details (populated only when blank).
	if ( '' === get_option( 'edupro_bank_uk_account_name', '' ) ) {
		update_option( 'edupro_bank_uk_account_name', 'EduPro Academy UK Ltd.' );
	}
	if ( '' === get_option( 'edupro_bank_uk_sort_code', '' ) ) {
		update_option( 'edupro_bank_uk_sort_code', '20-00-00' );
	}
	if ( '' === get_option( 'edupro_bank_uk_account_number', '' ) ) {
		update_option( 'edupro_bank_uk_account_number', '12345678' );
	}
	if ( '' === get_option( 'edupro_bank_uk_iban', '' ) ) {
		update_option( 'edupro_bank_uk_iban', 'GB29NWBK60161331926819' );
	}

	if ( '' === get_option( 'edupro_bank_instructions', '' ) ) {
		update_option( 'edupro_bank_instructions', __( 'Please include your Order Reference ID in the transfer remarks. Your course access will be activated once payment is manually verified by our team.', 'edupro' ) );
	}
}

/**
 * Seed 1 sample promo coupon (`WELCOME20`, 20% off, active) into `wp_edupro_coupons`
 * only if the table exists and the code is not already present.
 *
 * @return int 1 if seeded/present, 0 if coupons table does not exist yet.
 */
function edupro_demo_seed_sample_coupon() {
	global $wpdb;

	if ( function_exists( 'edupro_create_coupons_table' ) ) {
		edupro_create_coupons_table();
	}

	$table  = $wpdb->prefix . 'edupro_coupons';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) {
		return 0;
	}

	$already = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE code = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'WELCOME20'
		)
	);
	if ( $already ) {
		return 1;
	}

	$inserted = $wpdb->insert(
		$table,
		array(
			'code'           => 'WELCOME20',
			'discount_type'  => 'percent',
			'discount_value' => 20.00,
			'currency'       => '',
			'min_amount'     => 0.00,
			'max_uses'       => 0,
			'used_count'     => 0,
			'course_ids'     => '',
			'status'         => 'active',
			'created_at'     => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%f', '%s', '%f', '%d', '%d', '%s', '%s', '%s' )
	);

	return false !== $inserted ? 1 : 0;
}

// =============================================================================
// 9. NAVIGATION MENUS (primary, footer-1, footer-2, footer-3)
// =============================================================================

/**
 * Create and assign WordPress navigation menus for primary, footer-1, footer-2, and footer-3.
 *
 * @param bool  $force    Overwrite existing theme menu assignments when true.
 * @param array $page_ids Map of slug => page_id from edupro_demo_create_pages().
 * @return array Assigned menu location => term_id.
 */
function edupro_demo_create_menus( $force = false, $page_ids = array() ) {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( ! is_array( $locations ) ) {
		$locations = array();
	}

	$courses_url     = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
	$instructors_url = get_post_type_archive_link( 'lms_instructor' ) ?: home_url( '/instructors/' );

	$home_id      = ! empty( $page_ids['home'] ) ? (int) $page_ids['home'] : (int) get_option( 'edupro_home_page_id' );
	$dashboard_id = ! empty( $page_ids['dashboard'] ) ? (int) $page_ids['dashboard'] : (int) get_option( 'edupro_dashboard_page_id' );
	$login_id     = ! empty( $page_ids['student-login'] ) ? (int) $page_ids['student-login'] : (int) get_option( 'edupro_login_page_id' );
	$register_id  = ! empty( $page_ids['student-register'] ) ? (int) $page_ids['student-register'] : (int) get_option( 'edupro_register_page_id' );
	$checkout_id  = ! empty( $page_ids['checkout'] ) ? (int) $page_ids['checkout'] : (int) get_option( 'edupro_checkout_page_id' );
	$about_id     = ! empty( $page_ids['about-us'] ) ? (int) $page_ids['about-us'] : (int) get_option( 'edupro_about_page_id' );
	$contact_id   = ! empty( $page_ids['contact-us'] ) ? (int) $page_ids['contact-us'] : (int) get_option( 'edupro_contact_page_id' );

	$top_categories = get_terms(
		array(
			'taxonomy'   => 'course_category',
			'hide_empty' => false,
			'number'     => 6,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $top_categories ) || ! is_array( $top_categories ) ) {
		$top_categories = array();
	}

	$assigned = array();

	// 1. Primary Menu
	if ( empty( $locations['primary'] ) || $force ) {
		$menu_name = 'EduPro Primary Menu';
		$menu_obj  = wp_get_nav_menu_object( $menu_name );
		if ( ! $menu_obj ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( ! is_wp_error( $menu_id ) ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Home', 'edupro' ),
						'menu-item-url'    => home_url( '/' ),
						'menu-item-status' => 'publish',
					)
				);
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Courses', 'edupro' ),
						'menu-item-url'    => $courses_url,
						'menu-item-status' => 'publish',
					)
				);

				// Categories parent item with top course categories as children.
				$cats_parent = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Categories', 'edupro' ),
						'menu-item-url'    => $courses_url,
						'menu-item-status' => 'publish',
					)
				);
				if ( $cats_parent && ! is_wp_error( $cats_parent ) ) {
					foreach ( $top_categories as $cat_term ) {
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'     => $cat_term->name,
								'menu-item-object-id' => (int) $cat_term->term_id,
								'menu-item-object'    => 'course_category',
								'menu-item-type'      => 'taxonomy',
								'menu-item-parent-id' => (int) $cats_parent,
								'menu-item-status'    => 'publish',
							)
						);
					}
				}

				if ( $dashboard_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Dashboard', 'edupro' ),
							'menu-item-object-id' => $dashboard_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $about_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'About', 'edupro' ),
							'menu-item-object-id' => $about_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $contact_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Contact', 'edupro' ),
							'menu-item-object-id' => $contact_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}

				$locations['primary'] = (int) $menu_id;
				$assigned['primary']  = (int) $menu_id;
			}
		} else {
			$locations['primary'] = (int) $menu_obj->term_id;
			$assigned['primary']  = (int) $menu_obj->term_id;
		}
	} else {
		$assigned['primary'] = (int) $locations['primary'];
	}

	// 2. Footer-1 Menu (Top Course Categories)
	if ( empty( $locations['footer-1'] ) || $force ) {
		$menu_name = 'EduPro Footer Categories';
		$menu_obj  = wp_get_nav_menu_object( $menu_name );
		if ( ! $menu_obj ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( ! is_wp_error( $menu_id ) ) {
				foreach ( $top_categories as $cat_term ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => $cat_term->name,
							'menu-item-object-id' => (int) $cat_term->term_id,
							'menu-item-object'    => 'course_category',
							'menu-item-type'      => 'taxonomy',
							'menu-item-status'    => 'publish',
						)
					);
				}
				$locations['footer-1'] = (int) $menu_id;
				$assigned['footer-1']  = (int) $menu_id;
			}
		} else {
			$locations['footer-1'] = (int) $menu_obj->term_id;
			$assigned['footer-1']  = (int) $menu_obj->term_id;
		}
	} else {
		$assigned['footer-1'] = (int) $locations['footer-1'];
	}

	// 3. Footer-2 Menu (Quick Links)
	if ( empty( $locations['footer-2'] ) || $force ) {
		$menu_name = 'EduPro Footer Quick Links';
		$menu_obj  = wp_get_nav_menu_object( $menu_name );
		if ( ! $menu_obj ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( ! is_wp_error( $menu_id ) ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Browse All Courses', 'edupro' ),
						'menu-item-url'    => $courses_url,
						'menu-item-status' => 'publish',
					)
				);
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Our Instructors', 'edupro' ),
						'menu-item-url'    => $instructors_url,
						'menu-item-status' => 'publish',
					)
				);
				if ( $dashboard_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Student Dashboard', 'edupro' ),
							'menu-item-object-id' => $dashboard_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $login_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Student Login', 'edupro' ),
							'menu-item-object-id' => $login_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $register_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Student Register', 'edupro' ),
							'menu-item-object-id' => $register_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}

				$locations['footer-2'] = (int) $menu_id;
				$assigned['footer-2']  = (int) $menu_id;
			}
		} else {
			$locations['footer-2'] = (int) $menu_obj->term_id;
			$assigned['footer-2']  = (int) $menu_obj->term_id;
		}
	} else {
		$assigned['footer-2'] = (int) $locations['footer-2'];
	}

	// 4. Footer-3 Menu (Support & Info)
	if ( empty( $locations['footer-3'] ) || $force ) {
		$menu_name = 'EduPro Footer Support';
		$menu_obj  = wp_get_nav_menu_object( $menu_name );
		if ( ! $menu_obj ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( ! is_wp_error( $menu_id ) ) {
				if ( $about_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'About Us', 'edupro' ),
							'menu-item-object-id' => $about_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $contact_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Contact Us', 'edupro' ),
							'menu-item-object-id' => $contact_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $checkout_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Course Checkout', 'edupro' ),
							'menu-item-object-id' => $checkout_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				if ( $home_id ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => __( 'Home', 'edupro' ),
							'menu-item-object-id' => $home_id,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}

				$locations['footer-3'] = (int) $menu_id;
				$assigned['footer-3']  = (int) $menu_id;
			}
		} else {
			$locations['footer-3'] = (int) $menu_obj->term_id;
			$assigned['footer-3']  = (int) $menu_obj->term_id;
		}
	} else {
		$assigned['footer-3'] = (int) $locations['footer-3'];
	}

	set_theme_mod( 'nav_menu_locations', $locations );

	return $assigned;
}

// =============================================================================
// 10. ADMIN PAGE: APPEARANCE → EDUPRO SETUP
// =============================================================================

/**
 * Register the Appearance → EduPro Setup admin page.
 */
function edupro_demo_register_admin_page() {
	add_theme_page(
		__( 'EduPro Setup', 'edupro' ),
		__( 'EduPro Setup', 'edupro' ),
		'manage_options',
		'edupro-demo-setup',
		'edupro_demo_render_admin_page'
	);
}
add_action( 'admin_menu', 'edupro_demo_register_admin_page' );

/**
 * Handle one-click Run Setup and Reset & Re-Import form submissions.
 */
function edupro_demo_handle_admin_actions() {
	if ( ! isset( $_POST['edupro_demo_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['edupro_demo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edupro_demo_nonce'] ) ), 'edupro_demo_setup_action' ) ) {
		add_settings_error( 'edupro_demo_setup', 'invalid_nonce', __( 'Security check failed. Please refresh and try again.', 'edupro' ), 'error' );
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['edupro_demo_action'] ) );

	if ( 'run_setup' === $action ) {
		$summary = edupro_run_demo_setup( false );
		delete_transient( 'edupro_demo_activation_notice' );
		add_settings_error(
			'edupro_demo_setup',
			'setup_done',
			sprintf(
				/* translators: 1: courses count, 2: lessons count, 3: pages count */
				__( 'EduPro setup complete! Verified %1$d courses, %2$d lessons, %3$d essential pages, menus, reviews, and flushed permalinks.', 'edupro' ),
				absint( $summary['courses'] ),
				absint( $summary['lessons'] ),
				absint( $summary['pages'] )
			),
			'updated'
		);
	} elseif ( 'reset_reimport' === $action ) {
		$summary = edupro_demo_reset_and_reimport();
		delete_transient( 'edupro_demo_activation_notice' );
		add_settings_error(
			'edupro_demo_setup',
			'reset_done',
			sprintf(
				/* translators: 1: courses count, 2: lessons count, 3: instructors count */
				__( 'Demo content reset and freshly re-imported: %1$d courses, %2$d lessons, %3$d instructors, 6 reviews, and all menus.', 'edupro' ),
				absint( $summary['courses'] ),
				absint( $summary['lessons'] ),
				absint( $summary['instructors'] )
			),
			'updated'
		);
	}
}
add_action( 'admin_init', 'edupro_demo_handle_admin_actions' );

/**
 * Render the Appearance → EduPro Setup management page.
 */
function edupro_demo_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'edupro_demo_setup' );

	$setup_done    = get_option( 'edupro_demo_setup_done', '' );
	$courses_cnt   = (int) ( wp_count_posts( 'lms_course' )->publish ?? 0 );
	$lessons_cnt   = (int) ( wp_count_posts( 'lms_lesson' )->publish ?? 0 );
	$inst_cnt      = (int) ( wp_count_posts( 'lms_instructor' )->publish ?? 0 );
	$cats_raw      = wp_count_terms( array( 'taxonomy' => 'course_category', 'hide_empty' => false ) );
	$cats_cnt      = is_wp_error( $cats_raw ) ? 0 : (int) $cats_raw;
	$reviews_opt   = get_option( 'edupro_manual_reviews', array() );
	$reviews_cnt   = is_array( $reviews_opt ) ? count( $reviews_opt ) : 0;
	$nav_locations = get_theme_mod( 'nav_menu_locations', array() );
	$menus_cnt     = is_array( $nav_locations ) ? count( array_filter( $nav_locations ) ) : 0;

	$essential_pages = array(
		'Home'             => (int) get_option( 'page_on_front' ),
		'Student Login'    => (int) get_option( 'edupro_login_page_id' ),
		'Student Register' => (int) get_option( 'edupro_register_page_id' ),
		'Dashboard'        => (int) get_option( 'edupro_dashboard_page_id' ),
		'Course Checkout'  => (int) get_option( 'edupro_checkout_page_id' ),
		'About Us'         => (int) get_option( 'edupro_about_page_id' ),
		'Contact Us'       => (int) get_option( 'edupro_contact_page_id' ),
	);

	$courses_url  = get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' );
	$reviews_url  = admin_url( 'themes.php?page=edupro-google-reviews' );
	$gateways_url = admin_url( 'options-general.php?page=edupro-payment-gateways' );
	?>
	<div class="wrap edupro-setup-wrap">
		<style>
			.edupro-setup-wrap { max-width: 980px; }
			.edupro-setup-hero {
				background: linear-gradient(135deg, #1a1a2e 0%, #242442 100%);
				color: #fff;
				border-radius: 12px;
				padding: 26px 30px;
				margin: 18px 0 22px;
				border-left: 5px solid #e31b23;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				justify-content: space-between;
				gap: 16px;
			}
			.edupro-setup-hero h1 { color: #fff; margin: 0 0 6px; font-size: 24px; padding: 0; }
			.edupro-setup-hero p { margin: 0; color: rgba(255,255,255,0.82); font-size: 14px; }
			.edupro-setup-pill {
				display: inline-block;
				padding: 5px 12px;
				border-radius: 999px;
				font-size: 12px;
				font-weight: 700;
				background: <?php echo $setup_done ? '#10b981' : '#f59e0b'; ?>;
				color: #fff;
			}
			.edupro-setup-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
				gap: 14px;
				margin-bottom: 22px;
			}
			.edupro-stat-tile {
				background: #fff;
				border: 1px solid #e2e4e7;
				border-top: 3px solid #e31b23;
				border-radius: 9px;
				padding: 16px;
				text-align: center;
				box-shadow: 0 1px 4px rgba(0,0,0,0.04);
			}
			.edupro-stat-tile strong { display: block; font-size: 26px; color: #1a1a2e; line-height: 1.2; }
			.edupro-stat-tile span { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
			.edupro-setup-card {
				background: #fff;
				border: 1px solid #e2e4e7;
				border-radius: 10px;
				padding: 22px 26px;
				margin-bottom: 20px;
				box-shadow: 0 1px 4px rgba(0,0,0,0.04);
			}
			.edupro-setup-card h2 {
				margin: 0 0 14px;
				padding-bottom: 10px;
				border-bottom: 2px solid #f1f5f9;
				font-size: 16px;
				color: #1a1a2e;
			}
			.edupro-pages-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
				gap: 10px;
			}
			.edupro-page-chip {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 10px 14px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				font-size: 13px;
			}
			.edupro-status-ok { color: #059669; font-weight: 700; }
			.edupro-status-miss { color: #dc2626; font-weight: 700; }
			.edupro-actions-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 14px; }
			.edupro-btn-crimson {
				background: #e31b23 !important;
				border-color: #c4141b !important;
				color: #fff !important;
				padding: 4px 18px !important;
				height: auto !important;
				line-height: 2.2 !important;
				font-weight: 600 !important;
			}
			.edupro-btn-crimson:hover { background: #c4141b !important; }
			.edupro-btn-reset {
				background: #fff !important;
				border-color: #dc2626 !important;
				color: #dc2626 !important;
				padding: 4px 16px !important;
				height: auto !important;
				line-height: 2.2 !important;
				font-weight: 600 !important;
			}
			.edupro-btn-reset:hover { background: #fef2f2 !important; }
		</style>

		<div class="edupro-setup-hero">
			<div>
				<h1><?php esc_html_e( 'EduPro LMS — One-Click Website Setup', 'edupro' ); ?></h1>
				<p><?php esc_html_e( 'Install or refresh your complete LMS demo website including pages, 8 vocational & tech courses, self-paced lessons, instructors, Google reviews, and menus.', 'edupro' ); ?></p>
			</div>
			<div>
				<span class="edupro-setup-pill">
					<?php echo $setup_done ? esc_html__( '✓ Setup Installed', 'edupro' ) : esc_html__( 'Pending Setup', 'edupro' ); ?>
				</span>
			</div>
		</div>

		<div class="edupro-setup-stats">
			<div class="edupro-stat-tile"><strong><?php echo absint( $courses_cnt ); ?></strong><span><?php esc_html_e( 'Courses', 'edupro' ); ?></span></div>
			<div class="edupro-stat-tile"><strong><?php echo absint( $lessons_cnt ); ?></strong><span><?php esc_html_e( 'Lessons', 'edupro' ); ?></span></div>
			<div class="edupro-stat-tile"><strong><?php echo absint( $cats_cnt ); ?></strong><span><?php esc_html_e( 'Categories', 'edupro' ); ?></span></div>
			<div class="edupro-stat-tile"><strong><?php echo absint( $inst_cnt ); ?></strong><span><?php esc_html_e( 'Instructors', 'edupro' ); ?></span></div>
			<div class="edupro-stat-tile"><strong><?php echo absint( $reviews_cnt ); ?></strong><span><?php esc_html_e( 'Reviews', 'edupro' ); ?></span></div>
			<div class="edupro-stat-tile"><strong><?php echo absint( $menus_cnt ); ?></strong><span><?php esc_html_e( 'Menus', 'edupro' ); ?></span></div>
		</div>

		<!-- Actions Card -->
		<div class="edupro-setup-card">
			<h2><?php esc_html_e( 'Demo Installer Controls', 'edupro' ); ?></h2>
			<p class="description">
				<?php
				if ( $setup_done ) {
					printf(
						/* translators: %s: timestamp */
						esc_html__( 'Last completed on %s. Use "Run / Verify Setup" to safely fill in any missing pages, categories, or menus without duplicating existing content, or "Reset & Re-Import" to restore fresh demo courses, lessons, instructors, reviews, and menus.', 'edupro' ),
						'<strong>' . esc_html( $setup_done ) . '</strong>'
					);
				} else {
					esc_html_e( 'Click the button below to automatically build the complete EduPro LMS website in one click.', 'edupro' );
				}
				?>
			</p>

			<div class="edupro-actions-row">
				<form method="post" action="">
					<?php wp_nonce_field( 'edupro_demo_setup_action', 'edupro_demo_nonce' ); ?>
					<input type="hidden" name="edupro_demo_action" value="run_setup">
					<button type="submit" class="button edupro-btn-crimson">
						<?php echo $setup_done ? esc_html__( 'Run / Verify Setup (Idempotent)', 'edupro' ) : esc_html__( 'Install Complete Demo Website', 'edupro' ); ?>
					</button>
				</form>

				<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'This will delete previously imported demo courses, lessons, instructors, reviews, and demo menus, then re-import them fresh. Your custom pages will be preserved. Continue?', 'edupro' ) ); ?>');">
					<?php wp_nonce_field( 'edupro_demo_setup_action', 'edupro_demo_nonce' ); ?>
					<input type="hidden" name="edupro_demo_action" value="reset_reimport">
					<button type="submit" class="button edupro-btn-reset">
						<?php esc_html_e( 'Reset & Re-Import Demo Content', 'edupro' ); ?>
					</button>
				</form>
			</div>
		</div>

		<!-- Essential Pages Status -->
		<div class="edupro-setup-card">
			<h2><?php esc_html_e( 'Essential LMS Pages Status', 'edupro' ); ?></h2>
			<div class="edupro-pages-grid">
				<?php foreach ( $essential_pages as $label => $pid ) : ?>
					<?php $exists = ( $pid > 0 && 'publish' === get_post_status( $pid ) ); ?>
					<div class="edupro-page-chip">
						<span>
							<strong><?php echo esc_html( $label ); ?></strong>
							<?php if ( $exists ) : ?>
								<br><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:11.5px;"><?php esc_html_e( 'View Page →', 'edupro' ); ?></a>
							<?php endif; ?>
						</span>
						<span class="<?php echo $exists ? 'edupro-status-ok' : 'edupro-status-miss'; ?>">
							<?php echo $exists ? esc_html__( '✓ Ready', 'edupro' ) : esc_html__( 'Missing', 'edupro' ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Quick Shortcuts -->
		<div class="edupro-setup-card">
			<h2><?php esc_html_e( 'Next Steps & Quick Links', 'edupro' ); ?></h2>
			<p class="description" style="margin-bottom:12px;">
				<?php
				printf(
					/* translators: %s: sample coupon code */
					esc_html__( 'Dual-currency UK (£ GBP) and India (₹ INR) regional pricing is pre-configured on all 8 demo courses. Test checkout discounts right away using sample promo code %s (20%% off).', 'edupro' ),
					'<code>WELCOME20</code>'
				);
				?>
			</p>
			<div class="edupro-actions-row">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Homepage', 'edupro' ); ?></a>
				<a href="<?php echo esc_url( $courses_url ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Course Catalog', 'edupro' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lms_course' ) ); ?>" class="button"><?php esc_html_e( 'Manage Courses', 'edupro' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=edupro-site-settings' ) ); ?>" class="button"><?php esc_html_e( 'Website Settings', 'edupro' ); ?></a>
				<a href="<?php echo esc_url( $gateways_url ); ?>" class="button"><?php esc_html_e( 'Configure Payment Gateways', 'edupro' ); ?></a>
				<a href="<?php echo esc_url( $reviews_url ); ?>" class="button"><?php esc_html_e( 'Configure Google Reviews', 'edupro' ); ?></a>
			</div>
		</div>
	</div>
	<?php
}
