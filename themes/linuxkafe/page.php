<?php
/**
 * Page com Logo no topo do conteúdo.
 * @package linuxkafe
 */
get_header();
?>

<main id="primary" class="site-main">
    <div class="linuxkafe-modern-theme">

        <div class="content-branding" style="text-align: center; margin-bottom: 2rem;">
            <?php 
            if ( has_custom_logo() ) {
                the_custom_logo();
            } else {
                echo '<h1 class="site-title" style="color:var(--color-text);">' . get_bloginfo( 'name' ) . '</h1>';
            }
            ?>
        </div>
        <?php
        while ( have_posts() ) :
            the_post();
            get_template_part( 'template-parts/content', 'page' );

            if ( comments_open() || get_comments_number() ) :
                comments_template();
            endif;
        endwhile;
        ?>

    </div>
</main>

<?php
get_sidebar();
get_footer();
