<?php
/**
 * The main template file (blog-style fallback with standard WordPress loop).
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main edupro-blog-page">
    <section class="edupro-page-hero">
        <div class="edupro-container container">
            <?php
            if ( function_exists( 'edupro_get_breadcrumb' ) ) {
                echo edupro_get_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
            <h1 class="edupro-page-hero__title">
                <?php
                if ( is_home() && ! is_front_page() ) {
                    single_post_title();
                } elseif ( is_search() ) {
                    printf(
                        /* translators: %s: search query */
                        esc_html__( 'Search Results for: %s', 'edupro' ),
                        '<span>' . esc_html( get_search_query() ) . '</span>'
                    );
                } else {
                    esc_html_e( 'Latest Articles & Learning Guides', 'edupro' );
                }
                ?>
            </h1>
            <p class="edupro-page-hero__subtitle">
                <?php esc_html_e( 'Career insights, study tips, and industry trends from our expert instructors.', 'edupro' ); ?>
            </p>
        </div>
    </section>

    <section class="edupro-section edupro-blog-section">
        <div class="edupro-container container">
            <?php if ( have_posts() ) : ?>
                <div class="edupro-blog-grid">
                    <?php
                    while ( have_posts() ) :
                        the_post();
                        $post_cats = get_the_category();
                        ?>
                        <article id="post-<?php the_ID(); ?>" <?php post_class( 'edupro-post-card animate-on-scroll' ); ?>>
                            <?php if ( has_post_thumbnail() ) : ?>
                                <a href="<?php the_permalink(); ?>" class="edupro-post-card__media">
                                    <?php
                                    echo wp_get_attachment_image(
                                        get_post_thumbnail_id(),
                                        'blog-thumb',
                                        false,
                                        array(
                                            'class'   => 'edupro-post-card__img',
                                            'loading' => 'lazy',
                                        )
                                    );
                                    ?>
                                </a>
                            <?php endif; ?>

                            <div class="edupro-post-card__body">
                                <div class="edupro-post-card__meta">
                                    <?php if ( ! empty( $post_cats ) ) : ?>
                                        <a href="<?php echo esc_url( get_category_link( $post_cats[0]->term_id ) ); ?>" class="edupro-post-card__cat">
                                            <?php echo esc_html( $post_cats[0]->name ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                                        <?php echo esc_html( get_the_date() ); ?>
                                    </time>
                                </div>

                                <h2 class="edupro-post-card__title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h2>

                                <div class="edupro-post-card__excerpt">
                                    <?php the_excerpt(); ?>
                                </div>

                                <div class="edupro-post-card__footer">
                                    <div class="edupro-post-card__author">
                                        <?php echo get_avatar( get_the_author_meta( 'ID' ), 28 ); ?>
                                        <span><?php the_author(); ?></span>
                                    </div>
                                    <a href="<?php the_permalink(); ?>" class="edupro-post-card__more">
                                        <?php esc_html_e( 'Read More →', 'edupro' ); ?>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <div class="edupro-pagination">
                    <?php
                    the_posts_pagination( array(
                        'mid_size'  => 2,
                        'prev_text' => __( '&larr; Previous', 'edupro' ),
                        'next_text' => __( 'Next &rarr;', 'edupro' ),
                    ) );
                    ?>
                </div>

            <?php else : ?>
                <div class="edupro-empty-state">
                    <h2><?php esc_html_e( 'Nothing Found', 'edupro' ); ?></h2>
                    <p><?php esc_html_e( 'It seems we cannot find what you are looking for. Try searching for a course or article below.', 'edupro' ); ?></p>
                    <?php get_search_form(); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
get_footer();
