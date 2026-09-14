<?php
/**
 * Resumo do chat — envia por email o resumo da conversa ao destinatário configurado.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Summary.
 */
class Xkaichat_Summary {

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
	 * Envia o resumo da conversa.
	 *
	 * @param Xkaichat_Messages $messages  Persistência.
	 * @param string            $session_key Chave da conversa.
	 * @param array             $session   Dados da sessão (email, phone).
	 * @return true|WP_Error
	 */
	public function send( $messages, $session_key, $session ) {
		if ( empty( $session_key ) ) {
			return new WP_Error( 'xkc_no_session', 'sem_conversa' );
		}

		$settings = get_option( 'xkaichat_settings', array() );
		$enabled  = isset( $settings['summary_enabled'] ) ? (int) $settings['summary_enabled'] : 1;
		if ( 0 === $enabled ) {
			return true;
		}

		$to      = isset( $settings['summary_email'] ) && is_email( $settings['summary_email'] ) ? $settings['summary_email'] : 'capuchinho@capuchinhoverde.com';
		$rows    = $messages->get_session_messages( $session_key );

		if ( empty( $rows ) ) {
			return true;
		}

		$email = isset( $session['email'] ) ? $session['email'] : '';
		$phone = isset( $session['phone'] ) ? $session['phone'] : '';

		$subject = '[Capuchinho Verde] Resumo de conversa — ' . gmdate( 'Y-m-d H:i' );

		$body  = "RESUMO DE CONVERSA\n==================\n\n";
		$body .= "Data: " . gmdate( 'Y-m-d H:i' ) . "\n";
		$body .= "Email: " . $email . "\n";
		$body .= "Telefone: " . ( '' !== $phone ? $phone : '(não fornecido)' ) . "\n\n";
		$body .= "Palavras-chave da conversa:\n" . $this->extract_keywords_text( $rows ) . "\n\n";
		$body .= "TRANSCRIÇÃO\n-----------\n";

		foreach ( $rows as $row ) {
			$role = 'user' === $row['role'] ? 'Cliente' : 'Assistente';
			$body .= "\n[" . $role . "] " . $row['content'] . "\n";
		}

		$body .= "\n---\nGerado pelo XKaiChat.\n";

		$sent = wp_mail( $to, $subject, $body );
		if ( ! $sent ) {
			return new WP_Error( 'xkc_summary_mail_failed', 'falha_envio_resumo' );
		}

		return true;
	}

	/**
	 * Palavras-chave mais frequentes da conversa (para o resumo).
	 *
	 * @param array $rows Mensagens.
	 * @return string
	 */
	private function extract_keywords_text( $rows ) {
		$stopwords = array(
			'de', 'a', 'o', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'e', 'é', 'em', 'por', 'com',
			'para', 'que', 'do', 'da', 'dos', 'das', 'na', 'no', 'nas', 'nos', 'se', 'eu', 'tu',
			'ele', 'ela', 'nós', 'você', 'me', 'te', 'quanto', 'qual', 'quais', 'tem', 'tenho',
			'tens', 'há', 'ser', 'será', 'está', 'estou', 'gostaria', 'como', 'quando', 'onde',
			'meu', 'minha', 'sua', 'seu', 'vai', 'pode', 'poderia', 'obrigado', 'obrigada', 'bom', 'ola',
		);

		$words = array();
		foreach ( $rows as $row ) {
			if ( 'user' !== $row['role'] ) {
				continue;
			}
			$text = strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $row['content'] ) );
			foreach ( preg_split( '/\s+/u', trim( $text ) ) as $w ) {
				$w = trim( $w );
				if ( '' === $w || mb_strlen( $w ) < 3 ) {
					continue;
				}
				if ( in_array( $w, $stopwords, true ) ) {
					continue;
				}
				$words[ $w ] = isset( $words[ $w ] ) ? $words[ $w ] + 1 : 1;
			}
		}

		arsort( $words );
		$top = array_slice( array_keys( $words ), 0, 12 );

		return empty( $top ) ? '(nenhuma detetada)' : implode( ', ', $top );
	}
}