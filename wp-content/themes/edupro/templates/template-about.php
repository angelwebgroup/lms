<?php
/**
 * Template Name: About Us
 * Template Post Type: page
 *
 * Luxury Glassmorphism About Us Page:
 * - Luminous .edupro-page-hero--glass with breadcrumb, eyebrow pill, bold H1,
 *   dynamic site description, dual CTA buttons, and 4 frosted KPI stat cards
 * - Mission & Vision 2-column frosted glass section (.edupro-about-mv-grid)
 * - Core Pillars / Why Choose Us 4-card frosted grid (.edupro-about-pillars-grid)
 * - Trusted Brands Auto-Carousel (edupro_render_brands_carousel())
 * - Meet Our Expert Instructors grid (.edupro-about-instructors-grid)
 * - Student Google Reviews Split Carousel (edupro_render_google_reviews(6))
 * - Bottom Dark Crimson-Obsidian Gradient CTA Banner (.edupro-about-cta-banner)
 *
 * @package EduPro
 * @version 1.4.3
 */

defined( 'ABSPATH' ) || exit;

get_header();

$get_setting = function ( $key, $default = '' ) {
	if ( function_exists( 'edupro_get_site_setting' ) ) {
		return (string) edupro_get_site_setting( $key, $default );
	}
	return (string) $default;
};

$site_name   = $get_setting( 'site_name', get_bloginfo( 'name' ) );
if ( '' === trim( $site_name ) ) {
	$site_name = get_bloginfo( 'name' );
}
$site_about  = $get_setting( 'footer_about', get_bloginfo( 'description' ) );
if ( '' === trim( $site_about ) ) {
	$site_about = sprintf(
		/* translators: %s: site name */
		__( '%s delivers flexible, career-ready online qualifications and CPD-accredited training trusted by ambitious learners and employers worldwide.', 'edupro' ),
		$site_name
	);
}

$courses_url  = get_post_type_archive_link( 'lms_course' ) ? get_post_type_archive_link( 'lms_course' ) : home_url( '/courses/' );
$contact_page = get_page_by_path( 'contact-us' );
if ( ! $contact_page ) {
	$contact_page = get_page_by_path( 'contact' );
}
$contact_url  = $contact_page ? get_permalink( $contact_page->ID ) : home_url( '/contact-us/' );

$kpis = array(
	array(
		'value' => '10,000+',
		'label' => __( 'Active Learners', 'edupro' ),
	),
	array(
		'value' => '96%',
		'label' => __( 'Career Advancement', 'edupro' ),
	),
	array(
		'value' => '50+',
		'label' => __( 'Industry Instructors', 'edupro' ),
	),
	array(
		'value' => '4.9/5',
		'label' => __( 'Average Rating', 'edupro' ),
	),
);

$pillars = array(
	array(
		'tone'  => 'rose',
		'title' => __( 'Industry-Aligned Curriculum', 'edupro' ),
		'desc'  => __( 'Every syllabus is co-designed with practicing specialists to reflect the exact tools, standards, and competencies employers hire for today.', 'edupro' ),
		'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
	),
	array(
		'tone'  => 'indigo',
		'title' => __( 'Flexible Self-Paced Learning', 'edupro' ),
		'desc'  => __( 'Study on desktop, tablet, or mobile at your own schedule with lifetime course access, downloadable resources, and resume-where-you-left-off playback.', 'edupro' ),
		'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	),
	array(
		'tone'  => 'emerald',
		'title' => __( 'Verified Accredited Certificates', 'edupro' ),
		'desc'  => __( 'Earn verifiable completion certificates with unique credential IDs ready to share on LinkedIn, CVs, and professional development portfolios.', 'edupro' ),
		'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>',
	),
	array(
		'tone'  => 'amber',
		'title' => __( 'Dedicated Tutor Support', 'edupro' ),
		'desc'  => __( 'Never study alone — receive responsive guidance from qualified tutors and friendly admissions advisors via email, phone, and WhatsApp.', 'edupro' ),
		'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
	),
);

$instructors_query = new WP_Query(
	array(
		'post_type'      => 'lms_instructor',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
	)
);
?>

<main id="primary" class="edupro-about-page">

	<!-- =================================================================== -->
	<!-- 1. LUMINOUS GLASSMORPHISM HERO + 4 KPI CARDS                        -->
	<!-- =================================================================== -->
	<section class="edupro-page-hero edupro-page-hero--glass">
		<div class="edupro-container">
			<?php if ( function_exists( 'edupro_get_breadcrumb' ) ) : ?>
				<?php echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<div class="edupro-page-hero__content">
				<span class="edupro-section-eyebrow">
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__( 'About %s', 'edupro' ),
						esc_html( $site_name )
					);
					?>
				</span>
				<h1 class="edupro-page-hero__title">
					<?php esc_html_e( 'Empowering Careers Through', 'edupro' ); ?>
					<span class="edupro-text-gradient"><?php esc_html_e( 'Accredited Online Learning', 'edupro' ); ?></span>
				</h1>
				<p class="edupro-page-hero__subtitle"><?php echo esc_html( $site_about ); ?></p>

				<div class="edupro-page-hero__actions">
					<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-primary edupro-btn-pill edupro-btn-lg">
						<?php esc_html_e( 'Explore All Courses', 'edupro' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( $contact_url ); ?>" class="edupro-btn edupro-btn-outline edupro-btn-pill edupro-btn-lg">
						<?php esc_html_e( 'Contact Admissions', 'edupro' ); ?>
					</a>
				</div>
			</div>

			<div class="edupro-about-kpis">
				<?php foreach ( $kpis as $kpi ) : ?>
					<div class="edupro-about-kpi-card edupro-glass-panel">
						<strong class="edupro-about-kpi-card__val"><?php echo esc_html( $kpi['value'] ); ?></strong>
						<span class="edupro-about-kpi-card__label"><?php echo esc_html( $kpi['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 2. MISSION & VISION                                                 -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-about-mv-section">
		<div class="edupro-container">
			<div class="edupro-about-mv-grid">
				<article class="edupro-about-mv-card edupro-glass-panel">
					<span class="edupro-about-mv-icon edupro-about-mv-icon--rose" aria-hidden="true">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
					</span>
					<h2><?php esc_html_e( 'Our Mission', 'edupro' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: site name */
							esc_html__( 'At %s, our mission is to make accredited, career-transforming education accessible to every learner regardless of location or schedule — bridging the gap between academic theory and real-world workplace excellence.', 'edupro' ),
							esc_html( $site_name )
						);
						?>
					</p>
				</article>

				<article class="edupro-about-mv-card edupro-glass-panel">
					<span class="edupro-about-mv-icon edupro-about-mv-icon--violet" aria-hidden="true">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
					</span>
					<h2><?php esc_html_e( 'Our Vision', 'edupro' ); ?></h2>
					<p>
						<?php esc_html_e( 'To be the most trusted global learning destination where ambitious professionals, healthcare practitioners, educators, and technologists continuously upskill with recognised qualifications that unlock lasting career growth.', 'edupro' ); ?>
					</p>
				</article>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 3. CORE PILLARS / WHY CHOOSE US                                     -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-about-pillars-section">
		<div class="edupro-container">
			<div class="edupro-section-header edupro-section-header--center">
				<span class="edupro-section-eyebrow"><?php esc_html_e( 'Why Learners Choose Us', 'edupro' ); ?></span>
				<h2><?php esc_html_e( 'Built Around Real Career Outcomes', 'edupro' ); ?></h2>
				<p><?php esc_html_e( 'Four core pillars guide every course we produce and every learner we mentor.', 'edupro' ); ?></p>
			</div>

			<div class="edupro-about-pillars-grid">
				<?php foreach ( $pillars as $pillar ) : ?>
					<article class="edupro-about-pillar-card edupro-glass-panel">
						<span class="edupro-about-pillar-icon edupro-about-pillar-icon--<?php echo esc_attr( $pillar['tone'] ); ?>" aria-hidden="true">
							<?php echo $pillar['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<h3><?php echo esc_html( $pillar['title'] ); ?></h3>
						<p><?php echo esc_html( $pillar['desc'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 4. TRUSTED BRANDS CAROUSEL                                          -->
	<!-- =================================================================== -->
	<?php
	if ( function_exists( 'edupro_render_brands_carousel' ) ) {
		echo edupro_render_brands_carousel(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>

	<!-- =================================================================== -->
	<!-- 5. MEET OUR EXPERT INSTRUCTORS                                      -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-about-instructors-section">
		<div class="edupro-container">
			<div class="edupro-section-header edupro-section-header--center">
				<span class="edupro-section-eyebrow"><?php esc_html_e( 'Learn From Practitioners', 'edupro' ); ?></span>
				<h2><?php esc_html_e( 'Meet Our Expert Instructors', 'edupro' ); ?></h2>
				<p><?php esc_html_e( 'Our faculty combines years of hands-on industry leadership with proven teaching excellence.', 'edupro' ); ?></p>
			</div>

			<div class="edupro-about-instructors-grid">
				<?php if ( $instructors_query->have_posts() ) : ?>
					<?php while ( $instructors_query->have_posts() ) : $instructors_query->the_post(); ?>
						<?php
						$iid         = get_the_ID();
						$designation = get_post_meta( $iid, '_edupro_instructor_title', true );
						$thumb       = has_post_thumbnail( $iid )
							? get_the_post_thumbnail( $iid, 'instructor-avatar', array( 'class' => 'edupro-about-instructor__img', 'loading' => 'lazy' ) )
							: '<span class="edupro-about-instructor__initials" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( get_the_title(), 0, 2 ) ) ) . '</span>';
						$bio_excerpt = get_the_excerpt() ? wp_trim_words( get_the_excerpt(), 20, '…' ) : wp_trim_words( wp_strip_all_tags( get_the_content() ), 20, '…' );
						?>
						<article class="edupro-about-instructor-card edupro-glass-panel">
							<div class="edupro-about-instructor__avatar">
								<a href="<?php the_permalink(); ?>"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							</div>
							<h3 class="edupro-about-instructor__name">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>
							<?php if ( $designation ) : ?>
								<p class="edupro-about-instructor__role"><?php echo esc_html( $designation ); ?></p>
							<?php endif; ?>
							<?php if ( $bio_excerpt ) : ?>
								<p class="edupro-about-instructor__bio"><?php echo esc_html( $bio_excerpt ); ?></p>
							<?php endif; ?>
						</article>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<?php
					$authors = get_users(
						array(
							'number'   => 4,
							'orderby'  => 'post_count',
							'order'    => 'DESC',
							'role__in' => array( 'administrator', 'editor', 'author' ),
						)
					);
					foreach ( $authors as $author ) :
						?>
						<article class="edupro-about-instructor-card edupro-glass-panel">
							<div class="edupro-about-instructor__avatar">
								<img src="<?php echo esc_url( get_avatar_url( $author->ID, array( 'size' => 140 ) ) ); ?>" alt="<?php echo esc_attr( $author->display_name ); ?>" class="edupro-about-instructor__img" loading="lazy" width="120" height="120">
							</div>
							<h3 class="edupro-about-instructor__name"><?php echo esc_html( $author->display_name ); ?></h3>
							<p class="edupro-about-instructor__role"><?php esc_html_e( 'Senior Course Instructor', 'edupro' ); ?></p>
							<p class="edupro-about-instructor__bio">
								<?php
								echo esc_html(
									$author->description
										? wp_trim_words( $author->description, 20, '…' )
										: __( 'Dedicated educator and industry specialist helping learners build job-ready skills.', 'edupro' )
								);
								?>
							</p>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- =================================================================== -->
	<!-- 6. STUDENT GOOGLE REVIEWS CAROUSEL                                  -->
	<!-- =================================================================== -->
	<?php if ( function_exists( 'edupro_render_google_reviews' ) ) : ?>
		<section class="edupro-section edupro-about-reviews-section">
			<div class="edupro-container">
				<div class="edupro-section-header edupro-section-header--center">
					<span class="edupro-section-eyebrow"><?php esc_html_e( 'Verified Student Feedback', 'edupro' ); ?></span>
					<h2><?php esc_html_e( 'Trusted by Thousands of Graduates', 'edupro' ); ?></h2>
				</div>
				<?php echo edupro_render_google_reviews( 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- =================================================================== -->
	<!-- 7. BOTTOM GRADIENT CTA BANNER                                       -->
	<!-- =================================================================== -->
	<section class="edupro-section edupro-about-cta-section">
		<div class="edupro-container">
			<div class="edupro-about-cta-banner">
				<div class="edupro-about-cta-banner__content">
					<h2><?php esc_html_e( 'Ready to Take the Next Step in Your Career?', 'edupro' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: site name */
							esc_html__( 'Join learners worldwide advancing their careers with %s accredited courses and dedicated tutor support.', 'edupro' ),
							esc_html( $site_name )
						);
						?>
					</p>
				</div>
				<div class="edupro-about-cta-banner__actions">
					<a href="<?php echo esc_url( $courses_url ); ?>" class="edupro-btn edupro-btn-white edupro-btn-pill edupro-btn-lg">
						<?php esc_html_e( 'Browse Courses', 'edupro' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( $contact_url ); ?>" class="edupro-btn edupro-btn-glass edupro-btn-pill edupro-btn-lg">
						<?php esc_html_e( 'Talk to an Advisor', 'edupro' ); ?>
					</a>
				</div>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
