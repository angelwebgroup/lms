<?php
/**
 * The template for displaying all single posts.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main edupro-single-post-page">
    <?php
    while ( have_posts() ) :
        the_post();
        $categories  = get_the_category();
        $word_count  = str_word_count( wp_strip_all_tags( get_the_content() ) );
        $read_time   = max( 1, (int) ceil( $word_count / 200 ) );
        $author_id   = (int) get_the_author_meta( 'ID' );
        ?>
        <section class="edupro-page-hero edupro-single-hero">
            <div class="edupro-container container">
                <?php
                if ( function_exists( 'edupro_get_breadcrumb' ) ) {
                    echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>

                <?php if ( ! empty( $categories ) ) : ?>
                    <div class="edupro-single-hero__cats">
                        <?php foreach ( $categories as $cat ) : ?>
                            <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="edupro-badge edupro-badge--red">
                                <?php echo esc_html( $cat->name ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h1 class="edupro-page-hero__title"><?php the_title(); ?></h1>

                <div class="edupro-single-hero__meta">
                    <span class="edupro-single-hero__author">
                        <?php echo get_avatar( $author_id, 32 ); ?>
                        <span><?php echo esc_html( get_the_author() ); ?></span>
                    </span>
                    <span aria-hidden="true">&bull;</span>
                    <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                        <?php echo esc_html( get_the_date() ); ?>
                    </time>
                    <span aria-hidden="true">&bull;</span>
                    <span>
                        <?php
                        printf(
                            /* translators: %d: estimated reading time in minutes */
                            esc_html( _n( '%d min read', '%d min read', $read_time, 'edupro' ) ),
                            absint( $read_time )
                        );
                        ?>
                    </span>
                </div>
            </div>
        </section>

        <section class="edupro-section edupro-single-body">
            <div class="edupro-container container">
                <div class="edupro-single-layout">
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'edupro-single-article' ); ?>>
                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="edupro-single-article__thumb">
                                <?php
                                echo wp_get_attachment_image(
                                    get_post_thumbnail_id(),
                                    'hero-thumb',
                                    false,
                                    array( 'class' => 'edupro-single-article__img' )
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="entry-content edupro-prose">
                            <?php
                            the_content();

                            wp_link_pages( array(
                                'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'edupro' ),
                                'after'  => '</div>',
                            ) );
                            ?>
                        </div>

                        <?php
                        $tags_list = get_the_tag_list( '', ' ' );
                        if ( $tags_list ) :
                            ?>
                            <div class="edupro-single-tags">
                                <span class="edupro-single-tags__label"><?php esc_html_e( 'Tags:', 'edupro' ); ?></span>
                                <?php echo wp_kses_post( $tags_list ); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Author Bio Card -->
                        <div class="edupro-author-box">
                            <div class="edupro-author-box__avatar">
                                <?php echo get_avatar( $author_id, 72 ); ?>
                            </div>
                            <div class="edupro-author-box__info">
                                <span class="edupro-author-box__role"><?php esc_html_e( 'Written by', 'edupro' ); ?></span>
                                <h3 class="edupro-author-box__name"><?php echo esc_html( get_the_author() ); ?></h3>
                                <?php if ( get_the_author_meta( 'description' ) ) : ?>
                                    <p class="edupro-author-box__bio"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Previous / Next Post Navigation -->
                        <nav class="edupro-post-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'edupro' ); ?>">
                            <?php
                            the_post_navigation( array(
                                'prev_text' => '<span class="edupro-post-nav__label">' . esc_html__( '← Previous Article', 'edupro' ) . '</span><span class="edupro-post-nav__title">%title</span>',
                                'next_text' => '<span class="edupro-post-nav__label">' . esc_html__( 'Next Article →', 'edupro' ) . '</span><span class="edupro-post-nav__title">%title</span>',
                            ) );
                            ?>
                        </nav>

                        <?php
                        if ( comments_open() || get_comments_number() ) :
                            comments_template();
                        endif;
                        ?>
                    </article>

                    <!-- Sidebar -->
                    <aside class="edupro-single-sidebar">
                        <?php if ( is_active_sidebar( 'course-sidebar' ) ) : ?>
                            <?php dynamic_sidebar( 'course-sidebar' ); ?>
                        <?php else : ?>
                            <div class="edupro-sidebar-widget edupro-promo-card">
                                <h4><?php esc_html_e( 'Upgrade Your Skills Today', 'edupro' ); ?></h4>
                                <p><?php esc_html_e( 'Explore our accredited online courses with lifetime access and verified certificates.', 'edupro' ); ?></p>
                                <a href="<?php echo esc_url( get_post_type_archive_link( 'lms_course' ) ?: home_url( '/courses/' ) ); ?>" class="edupro-btn edupro-btn--primary btn-primary edupro-btn--full">
                                    <?php esc_html_e( 'Browse All Courses', 'edupro' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </section>
    <?php endwhile; ?>
</main>

<?php
get_footer();
