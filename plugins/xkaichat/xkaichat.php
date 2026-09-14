<?php
/**
 * Plugin Name:       XKaiChat
 * Plugin URI:        https://capuchinhoverde.com
 * Description:       Assistente IA para o Capuchinho Verde — chat com Ollama + RAG (menu/FAQ) via proxy intermédio, verificação de email e resumo por email.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            seyon
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xkaichat
 * Domain Path:       /languages
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'XKAICHAT_VERSION', '1.0.0' );
define( 'XKAICHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XKAICHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XKAICHAT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once XKAICHAT_PLUGIN_DIR . 'includes/class-xkaichat-activator.php';
require_once XKAICHAT_PLUGIN_DIR . 'includes/class-xkaichat-deactivator.php';
require_once XKAICHAT_PLUGIN_DIR . 'includes/class-xkaichat.php';

register_activation_hook( __FILE__, array( 'Xkaichat_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Xkaichat_Deactivator', 'deactivate' ) );

/**
 * Arranca o plugin.
 */
function xkaichat_run_xkaichat() {
	$plugin = new Xkaichat();
	$plugin->run();
}
add_action( 'plugins_loaded', 'xkaichat_run_xkaichat' );