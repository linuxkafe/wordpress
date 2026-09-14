<?php
/**
 * Mensagens — persistência de conversas na tabela própria.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Messages.
 */
class Xkaichat_Messages {

	/**
	 * Nome do plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Versão.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Construtor.
	 *
	 * @param string $plugin_name Slug do plugin.
	 * @param string $version     Version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Nome da tabela de mensagens.
	 *
	 * @return string
	 */
	public static function table_messages() {
		global $wpdb;
		return $wpdb->prefix . 'xkaichat_messages';
	}

	/**
	 * Insere uma mensagem.
	 *
	 * @param string $session_key Chave da sessão/conversa.
	 * @param string $email       Email (para rastreio).
	 * @param string $role        user|assistant.
	 * @param string $content     Conteúdo.
	 * @param int    $cache_hit   1 se veio da cache do proxy.
	 * @return int|false row id.
	 */
	public function insert_message( $session_key, $email, $role, $content, $cache_hit = 0 ) {
		global $wpdb;

		if ( '' === $session_key || '' === $content ) {
			return false;
		}

		$data = array(
			'session_key' => sanitize_key( $session_key ),
			'email'       => sanitize_email( $email ),
			'role'        => in_array( $role, array( 'user', 'assistant' ), true ) ? $role : 'user',
			'content'     => wp_kses_post( $content ),
			'cache_hit'   => (int) $cache_hit,
			'created_at'  => current_time( 'mysql' ),
		);

		$wpdb->insert( self::table_messages(), $data );

		return $wpdb->insert_id;
	}

	/**
	 * Devolve as mensagens de uma sessão.
	 *
	 * @param string $session_key Chave da sessão.
	 * @return array
	 */
	public function get_session_messages( $session_key ) {
		global $wpdb;

		$table = self::table_messages();
		$key   = sanitize_key( $session_key );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE session_key = %s ORDER BY id ASC', $key ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	/**
	 * Apaga mensagens com mais de N dias (retention configurada).
	 */
	public function cleanup_expired() {
		global $wpdb;

		$settings = get_option( 'xkaichat_settings', array() );
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 30;
		if ( $days <= 0 ) {
			return;
		}

		$table = self::table_messages();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE created_at < %s', $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
}