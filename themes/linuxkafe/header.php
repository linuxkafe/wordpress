<?php
/**
 * Header minimalista (Apenas Menu).
 * @package linuxkafe
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>

    <?php
    if ( is_singular() && has_post_thumbnail() ) {
        $background_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
    }
    ?>
    <style>
        body {
            background-image: url('<?php echo esc_url( $background_url ); ?>');
            /* Mantém as propriedades de fixação que definimos antes */
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            background-size: cover;
        }
    </style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary">
        <?php esc_html_e( 'Skip to content', 'linuxkafe' ); ?>
    </a>

    <header id="masthead" class="site-header">
        <div class="header-container">
            <nav id="site-navigation" class="main-navigation">
                <input class="menu-btn" type="checkbox" id="menu-btn" />
                <label class="menu-icon" for="menu-btn"><span class="navicon"></span></label>
                <?php
                wp_nav_menu(
                    array(
                        'theme_location' => 'menu-1',
                        'menu_id'        => 'primary-menu',
                        'container'      => false,
                        'menu_class'     => 'menu',
                    )
                );
                ?>
            </nav>
        </div>
    </header>
