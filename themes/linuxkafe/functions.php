<?php
/**
 * linuxkafe functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package linuxkafe
 */

if ( ! defined( '_S_VERSION' ) ) {
    // Replace the version number of the theme on each release.
    define( '_S_VERSION', '1.0.0' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function linuxkafe_setup() {
    /*
     * Make theme available for translation.
     * Translations can be filed in the /languages/directory.
     * If you're building a theme based on linuxkafe, use a find and replace
     * to change 'linuxkafe' to the name of your theme in all the template files.
     */
    load_theme_textdomain( 'linuxkafe', get_template_directory() . '/languages' );

    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    /*
     * Let WordPress manage the document title.
     * By adding theme support, we declare that this theme does not use a
     * hard-coded <title> tag in the document head, and expect WordPress to
     * provide it for us.
     */
    add_theme_support( 'title-tag' );

    /*
     * Enable support for Post Thumbnails on posts and pages.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support( 'post-thumbnails' );

    // This theme uses wp_nav_menu() in one location.
    register_nav_menus(
        array(
            'menu-1' => esc_html__( 'Primary', 'linuxkafe' ),
        )
    );

    /*
     * Switch default core markup for search form, comment form, and comments
     * to output valid HTML5.
     */
    add_theme_support(
        'html5',
        array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        )
    );

    // Set up the WordPress core custom background feature.
    add_theme_support(
        'custom-background',
        apply_filters(
            'linuxkafe_custom_background_args',
            array(
                'default-color' => 'ffffff',
                'default-image' => '',
            )
        )
    );

    // Add theme support for selective refresh for widgets.
    add_theme_support( 'customize-selective-refresh-widgets' );

    /**
     * Add support for core custom logo.
     *
     * @link https://codex.wordpress.org/Theme_Logo
     */
    add_theme_support(
        'custom-logo',
        array(
            'height'      => 250,
            'width'       => 250,
            'flex-width'  => true,
            'flex-height' => true,
        )
    );
}
add_action( 'after_setup_theme', 'linuxkafe_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function linuxkafe_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'linuxkafe_content_width', 640 );
}
add_action( 'after_setup_theme', 'linuxkafe_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function linuxkafe_widgets_init() {
    register_sidebar(
        array(
            'name'          => esc_html__( 'Sidebar', 'linuxkafe' ),
            'id'            => 'sidebar-1',
            'description'   => esc_html__( 'Add widgets here.', 'linuxkafe' ),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        )
    );
}
add_action( 'widgets_init', 'linuxkafe_widgets_init' );

/**
 * Enqueue scripts and styles.
 * [CORRIGIDO] Força o WordPress a ler a versão do ficheiro style.css (e não o _S_VERSION 1.0.0)
 * Isto garante que o cache-busting funciona e as alterações CSS são vistas pelo Google.
 */
function linuxkafe_scripts() {
    // Obtém a versão do tema a partir do ficheiro style.css
    $theme = wp_get_theme();
    $version = $theme->get( 'Version' );
    
    // Enqueue o CSS usando a versão dinâmica (ex: 1.0.7)
    wp_enqueue_style( 'linuxkafe-style', get_stylesheet_uri(), array(), $version );
    wp_style_add_data( 'linuxkafe-style', 'rtl', 'replace' );

    // Enqueue scripts (mantendo a versão original para scripts, se necessário)
    wp_enqueue_script( 'linuxkafe-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'linuxkafe_scripts' );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
    require get_template_directory() . '/inc/jetpack.php';
}

/* ========================================================= */
/* NOVAS FUNÇÕES: LOGOTIPO E MENU (Movidas do header.php)    */
/* Estas funções são chamadas em page.php (ou similar)       */
/* para colocar o logo DENTRO da div .linuxkafe-modern-theme */
/* ========================================================= */

/**
 * Gera o HTML do Logotipo (Site Branding).
 */
function linuxkafe_logo_html() {
    ?>
    <div class="site-branding">
        <?php
        the_custom_logo();
        
        // Código original do título do site, que é ocultado via CSS
        if ( is_front_page() && is_home() ) :
            ?>
            <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
            <?php
        else :
            ?>
            <p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
            <?php
        endif;
        $linuxkafe_description = get_bloginfo( 'description', 'display' );
        if ( $linuxkafe_description || is_customize_preview() ) :
            ?>
            <p class="site-description"><?php echo $linuxkafe_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
        <?php endif; ?>
    </div><?php
}

/**
 * Gera o HTML da Navegação Principal (Versão Checkbox Hack).
 */
function linuxkafe_menu_html() {
    ?>
    <nav id="site-navigation" class="main-navigation">
        <input class="menu-btn" type="checkbox" id="menu-btn" />
        
        <label class="menu-icon" for="menu-btn">
            <span class="navicon"></span>
        </label>
        
        <?php
        wp_nav_menu(
            array(
                'theme_location' => 'menu-1',
                'menu_id'        => 'primary-menu',
                'menu_class'     => 'menu', // O CSS espera a classe .menu na UL
                'container'      => false,  // Removemos a div wrapper para o "sibling selector" (~) do CSS funcionar
            )
        );
        ?>
    </nav><?php
}
// Tenta remover os hooks comuns que os temas usam para inserir o cabeçalho/menu.
// Isto deve forçar a remoção dos elementos indesejados do cabeçalho original.

// Remove a função de marca (logotipo/título) do cabeçalho
remove_action( 'linuxkafe_site_branding', 'linuxkafe_add_site_branding' );
remove_action( 'wp_head', 'linuxkafe_site_branding' );

// Remove a função de navegação primária (menu) do cabeçalho
remove_action( 'linuxkafe_navigation', 'linuxkafe_primary_navigation' ); 
remove_action( 'wp_head', 'linuxkafe_primary_navigation' );

// Se o seu tema usa um hook mais genérico como 'linuxkafe_header' ou 'masthead'
remove_action( 'linuxkafe_header', 'linuxkafe_output_header_content' );
remove_action( 'linuxkafe_header', 'linuxkafe_output_navigation' );


// --- Ações Genéricas Comuns (Tentar se as de cima falharem) ---
// remove_action( 'wp_body_open', 'linuxkafe_skip_link' ); // Se quiser remover o "Skip to content"
// remove_action( 'wp_body_open', 'linuxkafe_do_header' );


/* ========================================================= */
/* NOVO CÓDIGO: IMAGEM DESTACADA COMO FUNDO DA PÁGINA (BODY) */
/* ========================================================= */

/**
 * Adiciona a Imagem Destacada (Featured Image) como fundo do corpo da página (body).
 */
function linuxkafe_featured_image_as_background() {
    // Apenas aplica em posts singulares ou páginas (ajuste conforme necessário)
    if ( is_singular() && has_post_thumbnail() ) {
        // Obtém o URL completo da imagem destacada
        $background_image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
        
        if ( $background_image_url ) {
            ?>
            <style>
                /* O estilo é injetado diretamente no cabeçalho da página */
                body {
                    background-image: url('<?php echo esc_url( $background_image_url ); ?>') !important;
                    background-position: center center !important;
                    background-size: cover !important; /* Garante que a imagem cobre todo o fundo */
                    background-repeat: no-repeat !important;
                    background-attachment: fixed !important; /* Mantém a imagem fixa ao fazer scroll */
                    background-color: #000000 !important; /* Cor de fallback (preto) se a imagem falhar */
                }
                
                /* Oculta a imagem destacada que aparece no conteúdo (se o tema a mostrar por defeito) */
                .post-thumbnail,
                .wp-post-image {
                    display: none !important;
                }
            </style>
            <?php
        }
    }
}
add_action( 'wp_head', 'linuxkafe_featured_image_as_background' );
function enqueue_custom_autoscroll() {
	if ( is_single() || is_page() ) {
		wp_enqueue_script(
				'custom-autoscroll', // Handle único
				get_stylesheet_directory_uri() . '/js/autoscroll.js', // Caminho
				array(), // Dependências (vazio pois é Vanilla JS)
				filemtime( get_stylesheet_directory() . '/js/autoscroll.js' ), // Versão dinâmica para cache
				true // true = carregar no footer (melhor performance de load)
				);
	}
}
add_action('wp_footer', 'enqueue_custom_autoscroll');
