<?php
/**
 * The template for displaying a single EduPro Lesson (lms_lesson).
 *
 * Uses a clean distraction-free full-screen wrapper that loads
 * template-parts/lesson-player.php while preserving wp_head() and wp_footer().
 *
 * @package EduPro
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'edupro-lesson-player-body' ); ?>>
<?php wp_body_open(); ?>

<?php
while ( have_posts() ) :
    the_post();
    get_template_part( 'template-parts/lesson-player' );
endwhile;
?>

<!-- Toast Notification Container -->
<div id="edupro-toast-container" class="edupro-toast-container" aria-live="polite" aria-atomic="true"></div>

<?php wp_footer(); ?>
</body>
</html>
