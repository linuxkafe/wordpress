<?php
/**
 * Page com Logo no topo do conteúdo.
 * @package linuxkafe
 */
get_header();
?>
<div class="menu-wrapper">
    <?php 
    // Tenta usar a tua função personalizada, se não der, usa o padrão do WP
    if ( function_exists( 'linuxkafe_menu_html' ) ) {
        linuxkafe_menu_html(); 
    } else {
        wp_nav_menu( array(
            'theme_location' => 'menu-1',
            'menu_id'        => 'primary-menu',
            'container_class'=> 'main-navigation', // Ajuda a manter o estilo
        ) );
    }
    ?>
</div>
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
