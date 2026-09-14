<?php
/**
 * Activator — cria tabelas e opções por omissão.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Activator.
 */
class Xkaichat_Activator {

	/**
	 * Executa na activação.
	 */
	public static function activate() {
		self::create_tables();
		self::default_options();
	}

	/**
	 * Cria as tabelas do plugin.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$codes_sql = 'CREATE TABLE ' . $wpdb->prefix . 'xkaichat_codes (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			code_hash CHAR(64) NOT NULL,
			phone VARCHAR(24) DEFAULT "",
			ip VARCHAR(45) DEFAULT "",
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			token CHAR(32) DEFAULT "",
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY expires_at (expires_at)
		) ' . $charset . ';';

		$messages_sql = 'CREATE TABLE ' . $wpdb->prefix . 'xkaichat_messages (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key CHAR(32) NOT NULL DEFAULT "",
			email VARCHAR(190) NOT NULL DEFAULT "",
			role VARCHAR(16) NOT NULL,
			content LONGTEXT NOT NULL,
			cache_hit TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_key (session_key),
			KEY created_at (created_at)
		) ' . $charset . ';';

		dbDelta( $codes_sql );
		dbDelta( $messages_sql );
	}

	/**
	 * Opções por omissão.
	 */
	private static function default_options() {
		$defaults = array(
			'proxy_url'           => 'http://127.0.0.1:5001',
			'proxy_timeout'       => 180,
			'proxy_shared_key'    => '',
			'summary_email'       => 'capuchinho@capuchinhoverde.com',
			'summary_enabled'     => 1,
			'retention_days'      => 30,
			'enable_global_widget'=> 0,
			'widget_heading'      => 'Olá! Como podemos ajudar?',
			'widget_greeting'     => 'Bem-vindo à Capuchinho Verde. Faça-nos a sua pergunta sobre produtos, preços e encomendas.',
			'widget_logo_url'     => '',
			'terms_text'          => 'Para continuar, aceite o tratamento dos seus dados: o seu email (e, se indicado, o telefone) serão usados apenas para validar esta conversa, responder às suas perguntas e enviar um resumo à equipa. Não partilhamos os seus dados com terceiros.',
			'widget_initial_message' => 'Olá! Já está ligado/a ao Capuchinho Verde. Em que posso ajudar hoje? Pergunte sobre produtos, preços e encomendas.',
			'widget_auto_open'    => 0,
			'accent_color'        => '#5a8a4b',
		);

		if ( false === get_option( 'xkaichat_settings' ) ) {
			add_option( 'xkaichat_settings', $defaults );
		}
	}
}