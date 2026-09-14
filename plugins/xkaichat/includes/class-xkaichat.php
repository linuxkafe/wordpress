<?php
/**
 * Classe principal do plugin.
 *
 * Centraliza dependências, i18n, admin e frontend.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat.
 */
class Xkaichat {

	/**
	 * Nome do plugin.
	 *
	 * @var string
	 */
	protected $plugin_name = 'xkaichat';

	/**
	 * Versão do plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Instâncias de dependências.
	 *
	 * @var array
	 */
	protected $dependencies = array();

	/**
	 * Construtor.
	 */
	public function __construct() {
		$this->version = XKAICHAT_VERSION;
		$this->load_dependencies();
	}

	/**
	 * Carrega as dependências (classes).
	 */
	private function load_dependencies() {
		$includes = array(
			'class-xkaichat-i18n',
			'class-xkaichat-messages',
			'class-xkaichat-verification',
			'class-xkaichat-proxy',
			'class-xkaichat-summary',
		);
		foreach ( $includes as $class_file ) {
			$path = XKAICHAT_PLUGIN_DIR . 'includes/' . $class_file . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Instancia admin, public e regista hooks.
	 */
	public function run() {
		require_once XKAICHAT_PLUGIN_DIR . 'admin/class-xkaichat-admin.php';
		require_once XKAICHAT_PLUGIN_DIR . 'public/class-xkaichat-public.php';

		$this->dependencies['i18n']       = new Xkaichat_I18n( $this->plugin_name );
		$this->dependencies['messages']   = new Xkaichat_Messages( $this->plugin_name, $this->version );
		$this->dependencies['verification'] = new Xkaichat_Verification( $this->plugin_name, $this->version );
		$this->dependencies['proxy']      = new Xkaichat_Proxy( $this->plugin_name, $this->version );
		$this->dependencies['summary']    = new Xkaichat_Summary( $this->plugin_name, $this->version );

		$admin = new Xkaichat_Admin( $this->plugin_name, $this->version, $this->dependencies['proxy'] );
		$admin->register_hooks();

		$public = new Xkaichat_Public(
			$this->plugin_name,
			$this->version,
			$this->dependencies['verification'],
			$this->dependencies['proxy'],
			$this->dependencies['summary'],
			$this->dependencies['messages']
		);
		$public->register_hooks();

		// Os emails do plugin saem com o remetente do site (admin_email); o
		// default do WP (wordpress@dominio) quebra SPF/DKIM e emails de
		// validação podem nunca chegar.
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );

		add_action( 'init', array( $this->dependencies['i18n'], 'load_plugin_textdomain' ) );

		// Limpeza agendada de mensagens antigas.
		if ( ! wp_next_scheduled( 'xkaichat_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'xkaichat_daily_cleanup' );
		}
		add_action( 'xkaichat_daily_cleanup', array( $this->dependencies['messages'], 'cleanup_expired' ) );
	}

	/**
	 * Devolve o conjunto de dependências.
	 *
	 * @return array
	 */
	public function get_dependencies() {
		return $this->dependencies;
	}

	/**
	 * Remetente dos emails: admin_email do site.
	 *
	 * @param string $from Remetente original.
	 * @return string
	 */
	public function mail_from( $from ) {
		$admin = get_option( 'admin_email' );
		return is_email( $admin ) ? $admin : $from;
	}

	/**
	 * Nome do remetente dos emails: nome do site.
	 *
	 * @param string $from_name Nome original.
	 * @return string
	 */
	public function mail_from_name( $from_name ) {
		$name = get_bloginfo( 'name' );
		return '' !== (string) $name ? $name : $from_name;
	}
}