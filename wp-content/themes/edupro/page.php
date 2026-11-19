<?php
/**
 * The template for displaying all default pages.
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main edupro-default-page">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <section class="edupro-page-hero">
            <div class="edupro-container container">
                <?php
                if ( function_exists( 'edupro_get_breadcrumb' ) ) {
                    echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
                <h1 class="edupro-page-hero__title"><?php the_title(); ?></h1>
            </div>
        </section>

        <section class="edupro-section edupro-page-body">
            <div class="edupro-container container">
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'edupro-page-article' ); ?>>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="edupro-page-article__thumb">
                            <?php
                            echo wp_get_attachment_image(
                                get_post_thumbnail_id(),
                                'hero-thumb',
                                false,
                                array( 'class' => 'edupro-page-article__img' )
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

                    <?php if ( get_edit_post_link() ) : ?>
                        <footer class="entry-footer edupro-page-article__footer">
                            <?php
                            edit_post_link(
                                sprintf(
                                    wp_kses(
                                        /* translators: %s: Name of current post */
                                        __( 'Edit <span class="screen-reader-text">%s</span>', 'edupro' ),
                                        array( 'span' => array( 'class' => array() ) )
                                    ),
                                    wp_kses_post( get_the_title() )
                                ),
                                '<span class="edit-link">',
                                '</span>'
                            );
                            ?>
                        </footer>
                    <?php endif; ?>
                </article>

                <?php
                if ( comments_open() || get_comments_number() ) :
                    comments_template();
                endif;
                ?>
            </div>
        </section>
    <?php endwhile; ?>
</main>

<?php
get_footer();
