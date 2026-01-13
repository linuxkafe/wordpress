<?php
/**
 * The template for displaying all pages
 *
 * ... (restante dos comentários)
 *
 * @package linuxkafe
 */

get_header();
?>

    <main id="primary" class="site-main">

        <div class="linuxkafe-modern-theme">

            <?php 
            // 1. WRAPPER DO LOGOTIPO (PARA CENTRALIZAÇÃO)
            if ( function_exists( 'linuxkafe_logo_html' ) ) :
            ?>
            <div class="logo-wrapper">
                <?php linuxkafe_logo_html(); ?>
            </div>
            <?php
            endif;

            // 2. WRAPPER DO MENU (PARA ALINHAMENTO À ESQUERDA)
            if ( function_exists( 'linuxkafe_menu_html' ) ) :
            ?>
            <div class="menu-wrapper">
                <?php linuxkafe_menu_html(); ?>
            </div>
            <?php
            endif;
            ?>

            <?php
            while ( have_posts() ) :
                the_post();

                get_template_part( 'template-parts/content', 'page' );

                // If comments are open or we have at least one comment, load up the comment template.
                if ( comments_open() || get_comments_number() ) :
                    comments_template();
                endif;

            endwhile; // End of the loop.
            ?>

        </div></main><?php
get_sidebar();
get_footer();
