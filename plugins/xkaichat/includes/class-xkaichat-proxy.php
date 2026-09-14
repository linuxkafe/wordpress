<?php
/**
 * Cliente HTTP do proxy — falar com o serviço intermédio (FastAPI).
 *
 * Contrato com o proxy:
 *   GET  {proxy_url}/api/health
 *   POST {proxy_url}/api/chat     JSON {message: string}
 *   ->   {answer, cache_hit, source}
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Proxy.
 */
class Xkaichat_Proxy {

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
	 * Configurações do plugin.
	 *
	 * @return array
	 */
	public function settings() {
		$settings = get_option( 'xkaichat_settings', array() );
		return wp_parse_args(
			$settings,
			array(
				'proxy_url'        => 'http://127.0.0.1:5001',
				'proxy_timeout'    => 180,
				'proxy_shared_key' => '',
			)
		);
	}

	/**
	 * Envia uma pergunta ao proxy.
	 *
	 * @param string $message Mensagem do utilizador.
	 * @return array|WP_Error {answer, cache_hit, source} ou erro.
	 */
	public function chat( $message ) {
		$settings  = $this->settings();
		$base      = untrailingslashit( esc_url_raw( $settings['proxy_url'] ) );
		$endpoint  = $base . '/api/chat';
		$timeout   = max( 10, (int) $settings['proxy_timeout'] );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'headers'     => $this->headers(),
				'body'        => wp_json_encode(
					array(
						'message' => (string) $message,
						'lang'    => 'pt',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'xkc_proxy_http',
				'proxy_http_' . $code,
				array( 'body' => wp_remote_retrieve_body( $response ) )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['answer'] ) ) {
			return new WP_Error( 'xkc_proxy_bad_json', 'proxy_resposta_invalida' );
		}

		return array(
			'answer'    => isset( $data['answer'] ) ? (string) $data['answer'] : '',
			'cache_hit' => ! empty( $data['cache_hit'] ) ? 1 : 0,
			'source'    => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'llm',
		);
	}

	/**
	 * State do proxy (health).
	 *
	 * @return array|WP_Error
	 */
	public function health() {
		$settings = $this->settings();
		$base     = untrailingslashit( esc_url_raw( $settings['proxy_url'] ) );

		$response = wp_remote_get(
			$base . '/api/health',
			array(
				'timeout' => 5,
				'headers' => $this->headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : new WP_Error( 'xkc_proxy_bad_json', 'proxy_resposta_invalida' );
	}

	/**
	 * Limpa a cache do proxy.
	 *
	 * @return bool|WP_Error
	 */
	public function clear_cache() {
		$settings = $this->settings();
		$base     = untrailingslashit( esc_url_raw( $settings['proxy_url'] ) );

		$response = wp_remote_post(
			$base . '/api/cache/clear',
			array(
				'timeout' => 10,
				'headers' => $this->headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Headers comuns (inclui chave partilhada se configurada).
	 *
	 * @return array
	 */
	private function headers() {
		$settings = $this->settings();
		$headers  = array( 'Content-Type' => 'application/json; charset=utf-8' );
		if ( '' !== (string) $settings['proxy_shared_key'] ) {
			$headers['X-Xkai-Proxy-Key'] = (string) $settings['proxy_shared_key'];
		}
		return $headers;
	}
}