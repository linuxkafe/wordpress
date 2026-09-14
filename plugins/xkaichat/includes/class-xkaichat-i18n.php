<?php
/**
 * i18n — carrega o text domain.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_I18n.
 */
class Xkaichat_I18n {

	/**
	 * Nome do plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Construtor.
	 *
	 * @param string $plugin_name Nome/slug do plugin.
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Carrega o text domain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			$this->plugin_name,
			false,
			dirname( plugin_basename( XKAICHAT_PLUGIN_DIR ) ) . '/languages/'
		);
	}
}