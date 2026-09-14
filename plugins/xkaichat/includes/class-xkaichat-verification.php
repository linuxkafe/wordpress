<?php
/**
 * Verificação de email — códigos temporários, sessões autenticadas e rate-limit.
 *
 * O código guarda-se em hash (nunca em claro). A sessão é um token aleatório
 * guardado num transient com TTL de inatividade.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Verification.
 */
class Xkaichat_Verification {

	/**
	 * TTL do código em segundos.
	 *
	 * @var int
	 */
	public static $code_ttl = 600;

	/**
	 * Máximo de tentativas por código.
	 *
	 * @var int
	 */
	public static $max_attempts = 5;

	/**
	 * Máximo de pedidos de código por IP na janela.
	 *
	 * @var int
	 */
	public static $max_codes_per_ip = 3;

	/**
	 * Janela do rate-limit por IP em segundos.
	 *
	 * @var int
	 */
	public static $ip_window = 600;

	/**
	 * Cooldown entre reenvios por email (segundos).
	 *
	 * @var int
	 */
	public static $email_cooldown = 60;

	/**
	 * TTL da sessão (inatividade) em segundos.
	 *
	 * @var int
	 */
	public static $session_ttl = 1800;

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
	 * @param string $plugin_name Slug.
	 * @param string $version     Version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Nome da tabela de códigos.
	 *
	 * @return string
	 */
	public static function table_codes() {
		global $wpdb;
		return $wpdb->prefix . 'xkaichat_codes';
	}

	/**
	 * Pede um novo código para o email.
	 *
	 * @param string $email Email.
	 * @param string $phone Telefone (opcional).
	 * @param string $ip    IP do utilizador.
	 * @return array|WP_Error
	 */
	public function request_code( $email, $phone, $ip ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'xkc_invalid_email', 'email_invalido' );
		}

		$phone = $this->normalize_phone( $phone );

		$rl = $this->rate_limit( $ip, $email );
		if ( is_wp_error( $rl ) ) {
			return $rl;
		}

		$code = wp_rand( 100000, 999999 );
		$hash = hash( 'sha256', $code . '|' . $email . '|' . wp_salt( 'auth' ) );
		$expires = gmdate( 'Y-m-d H:i:s', time() + self::$code_ttl );

		global $wpdb;
		$wpdb->insert(
			self::table_codes(),
			array(
				'email'      => $email,
				'code_hash'  => $hash,
				'phone'      => $phone,
				'ip'         => $ip,
				'attempts'   => 0,
				'expires_at' => $expires,
			)
		);

		$mail = $this->send_code_email( $email, $code );
		if ( is_wp_error( $mail ) ) {
			return $mail;
		}

		return array(
			'sent'      => true,
			'expires_in'=> self::$code_ttl,
			'cooldown'  => self::$email_cooldown,
		);
	}

	/**
	 * Valida o código e devolve token de sessão em caso de sucesso.
	 *
	 * @param string $email Email.
	 * @param string $code  Código (6 dígitos).
	 * @param string $ip    IP.
	 * @return array|WP_Error
	 */
	public function verify_code( $email, $code, $ip ) {
		$email = sanitize_email( $email );
		$code  = preg_replace( '/\D/', '', (string) $code );

		if ( '' === $email || 6 !== strlen( $code ) ) {
			return new WP_Error( 'xkc_invalid_input', 'dados_invalidos' );
		}

		global $wpdb;
		$table = self::table_codes();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE email = %s AND token = "" ORDER BY id DESC LIMIT 1', $email ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'xkc_no_code', 'sem_codigo' );
		}

		if ( strtotime( $row['expires_at'] ) < time() ) {
			return new WP_Error( 'xkc_expired', 'codigo_expirado' );
		}

		if ( (int) $row['attempts'] >= self::$max_attempts ) {
			return new WP_Error( 'xkc_attempts', 'demasiadas_tentativas' );
		}

		$expected = $row['code_hash'];
		$actual   = hash( 'sha256', $code . '|' . $email . '|' . wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected, $actual ) ) {
			$wpdb->update( $table, array( 'attempts' => (int) $row['attempts'] + 1 ), array( 'id' => (int) $row['id'] ) );
			return new WP_Error( 'xkc_wrong', 'codigo_errado' );
		}

		$token = wp_generate_password( 32, false );
		$wpdb->update( $table, array( 'token' => $token ), array( 'id' => (int) $row['id'] ) );

		// Sessão com dados mínimos, expira por inatividade.
		set_transient(
			'xkaichat_session_' . $token,
			array(
				'email'   => $email,
				'phone'   => isset( $row['phone'] ) ? $row['phone'] : '',
				'created' => time(),
			),
			self::$session_ttl
		);

		$this->delete_expired_codes();

		return array(
			'token' => $token,
			'expires_in' => self::$session_ttl,
		);
	}

	/**
	 * Valida o token de sessão.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error dados da sessão ou erro.
	 */
	public function validate_session( $token ) {
		$token = sanitize_key( $token );
		if ( 32 !== strlen( $token ) ) {
			return new WP_Error( 'xkc_bad_token', 'token_invalido' );
		}

		$data = get_transient( 'xkaichat_session_' . $token );
		if ( false === $data ) {
			return new WP_Error( 'xkc_expired_session', 'sessao_expirada' );
		}

		// Renova a janela de inatividade.
		set_transient( 'xkaichat_session_' . $token, $data, self::$session_ttl );

		return $data;
	}

	/**
	 * Termina a sessão e devolve os dados (para o resumo).
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	public function end_session( $token ) {
		$token = sanitize_key( $token );
		if ( 32 !== strlen( $token ) ) {
			return new WP_Error( 'xkc_bad_token', 'token_invalido' );
		}

		$data = get_transient( 'xkaichat_session_' . $token );
		delete_transient( 'xkaichat_session_' . $token );

		if ( false === $data ) {
			return new WP_Error( 'xkc_expired_session', 'sessao_expirada' );
		}

		return $data;
	}

	/**
	 * Normaliza o número de telefone (PT).
	 *
	 * @param string $phone Número.
	 * @return string
	 */
	public function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^\d+]/', '', (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		if ( preg_match( '/^(\+351)?9\d{8}$/', $phone ) ) {
			return $phone;
		}
		return '';
	}

	/**
	 * Rate-limit por IP e cooldown por email.
	 *
	 * @param string $ip    IP.
	 * @param string $email Email.
	 * @return true|WP_Error
	 */
	private function rate_limit( $ip, $email ) {
		$ip_key   = 'xkc_rl_ip_' . md5( (string) $ip );
		$mail_key = 'xkc_rl_mail_' . md5( strtolower( $email ) );

		$count = (int) get_transient( $ip_key );
		if ( $count >= self::$max_codes_per_ip ) {
			return new WP_Error( 'xkc_rate_limit', 'rate_limit_atingido', array( 'retry_after' => self::$ip_window ) );
		}
		set_transient( $ip_key, $count + 1, self::$ip_window );

		$last = (int) get_transient( $mail_key );
		$elapsed = time() - $last;
		if ( $last > 0 && $elapsed < self::$email_cooldown ) {
			return new WP_Error( 'xkc_cooldown', 'aguarde_reenvio', array( 'retry_after' => self::$email_cooldown - $elapsed ) );
		}
		set_transient( $mail_key, time(), self::$email_cooldown );

		return true;
	}

	/**
	 * Envia o código por email.
	 *
	 * @param string $email Email.
	 * @param int    $code  Código.
	 * @return true|WP_Error
	 */
	private function send_code_email( $email, $code ) {
		$subject = 'O seu código de verificação — Capuchinho Verde';
		$body    = sprintf(
			"Olá!\n\nO seu código de verificação é: %d\n\nO código é válido por %d minutos.\n\nSe não pediu este código, ignore este email.\n\nCom os melhores cumprimentos,\nCapuchinho Verde",
			$code,
			(int) ( self::$code_ttl / 60 )
		);

		$sent = wp_mail( $email, $subject, $body );
		if ( ! $sent ) {
			return new WP_Error( 'xkc_mail_failed', 'falha_envio_email' );
		}
		return true;
	}

	/**
	 * Remove códigos expirados (manutenção ligeira).
	 */
	private function delete_expired_codes() {
		global $wpdb;
		$table = self::table_codes();
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE expires_at < %s', $now ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
}