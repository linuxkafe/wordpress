<?php
/**
 * Público — shortcode, assets, AJAX do chat.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Public.
 */
class Xkaichat_Public {

	/**
	 * Slug do plugin.
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
	 * Verificação.
	 *
	 * @var Xkaichat_Verification
	 */
	private $verification;

	/**
	 * Proxy client.
	 *
	 * @var Xkaichat_Proxy
	 */
	private $proxy;

	/**
	 * Summary.
	 *
	 * @var Xkaichat_Summary
	 */
	private $summary;

	/**
	 * Mensagens.
	 *
	 * @var Xkaichat_Messages
	 */
	private $messages;

	/**
	 * Flag se o widget está ativo nesta página.
	 *
	 * @var bool
	 */
	private $widget_active = false;

	/**
	 * Construtor.
	 *
	 * @param string               $plugin_name  Slug.
	 * @param string               $version      Version.
	 * @param Xkaichat_Verification $verification Verificação.
	 * @param Xkaichat_Proxy        $proxy        Proxy.
	 * @param Xkaichat_Summary      $summary      Resumo.
	 * @param Xkaichat_Messages     $messages     Mensagens.
	 */
	public function __construct( $plugin_name, $version, $verification, $proxy, $summary, $messages ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->verification = $verification;
		$this->proxy        = $proxy;
		$this->summary      = $summary;
		$this->messages     = $messages;
	}

	/**
	 * Regista hooks públicos.
	 */
	public function register_hooks() {
		add_shortcode( 'xkaichat', array( $this, 'shortcode' ) );
		add_action( 'wp', array( $this, 'maybe_activate_widget' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_xkaichat_request_code', array( $this, 'ajax_request_code' ) );
		add_action( 'wp_ajax_nopriv_xkaichat_request_code', array( $this, 'ajax_request_code' ) );
		add_action( 'wp_ajax_xkaichat_verify_code', array( $this, 'ajax_verify_code' ) );
		add_action( 'wp_ajax_nopriv_xkaichat_verify_code', array( $this, 'ajax_verify_code' ) );
		add_action( 'wp_ajax_xkaichat_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_nopriv_xkaichat_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_xkaichat_end_session', array( $this, 'ajax_end_session' ) );
		add_action( 'wp_ajax_nopriv_xkaichat_end_session', array( $this, 'ajax_end_session' ) );
	}

	/**
	 * Shortcode.
	 *
	 * @return string
	 */
	public function shortcode() {
		$this->widget_active = true;
		return '<div class="xkaichat-host"></div>';
	}

	/**
	 * Ativa o widget globalmente se configurado.
	 */
	public function maybe_activate_widget() {
		if ( true === $this->widget_active ) {
			return;
		}
		$settings = get_option( 'xkaichat_settings', array() );
		if ( ! empty( $settings['enable_global_widget'] ) ) {
			$this->widget_active = true;
		}
	}

	/**
	 * Enfileira assets quando o widget está ativo.
	 */
	public function enqueue_assets() {
		if ( ! $this->widget_active ) {
			return;
		}

		$settings = get_option( 'xkaichat_settings', array() );
		$settings = wp_parse_args(
			$settings,
			array(
				'widget_heading'       => 'Olá! Como podemos ajudar?',
				'widget_greeting'      => 'Bem-vindo à Capuchinho Verde. Faça-nos a sua pergunta sobre produtos, preços e encomendas.',
				'widget_logo_url'      => '',
				'terms_text'           => 'Para continuar, aceite o tratamento dos seus dados: o seu email (e, se indicado, o telefone) serão usados apenas para validar esta conversa, responder às suas perguntas e enviar um resumo à equipa. Não partilhamos os seus dados com terceiros.',
				'widget_initial_message' => 'Olá! Já está ligado/a ao Capuchinho Verde. Em que posso ajudar hoje? Pergunte sobre produtos, preços e encomendas.',
				'widget_auto_open'    => 0,
				'accent_color'         => '#5a8a4b',
			)
		);

		wp_enqueue_style( $this->plugin_name . '-chat', XKAICHAT_PLUGIN_URL . 'public/css/xkaichat-chat.css', array(), $this->version );
		wp_enqueue_script( $this->plugin_name . '-chat', XKAICHAT_PLUGIN_URL . 'public/js/xkaichat-chat.js', array(), $this->version, true );

		add_filter( 'script_loader_tag', array( $this, 'add_cfasync_false' ), 10, 3 );

		wp_localize_script(
			$this->plugin_name . '-chat',
			'xkaichat_ajax',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'xkaichat_public' ),
				'heading'   => $settings['widget_heading'],
				'greeting'  => $settings['widget_greeting'],
				'logo'      => $this->logo_url( $settings ),
				'terms'     => $settings['terms_text'],
				'initial'   => $settings['widget_initial_message'],
				'auto_open' => (int) $settings['widget_auto_open'],
				'accent'    => $settings['accent_color'],
				'privacy'   => __( 'O seu email é usado apenas para validar a conversa e eventual contacto. Os dados são retidos 30 dias.', 'xkaichat' ),
				'i18n'      => array(
					'email'        => __( 'O seu email', 'xkaichat' ),
					'email_hint'   => __( 'Precisa de ser válido para receber o código.', 'xkaichat' ),
					'phone'        => __( 'Telefone (opcional)', 'xkaichat' ),
					'continue'     => __( 'Enviar código', 'xkaichat' ),
					'code'         => __( 'Código de verificação', 'xkaichat' ),
					'code_hint'    => __( 'Enviamos um código de 6 dígitos para o seu email.', 'xkaichat' ),
					'verify'       => __( 'Validar', 'xkaichat' ),
					'resend'       => __( 'Reenviar código', 'xkaichat' ),
					'back'         => __( 'Voltar', 'xkaichat' ),
					'placeholder'  => __( 'Escreva a sua pergunta…', 'xkaichat' ),
					'send'         => __( 'Enviar', 'xkaichat' ),
					'end'          => __( 'Terminar conversa', 'xkaichat' ),
					'thinking'     => __( 'A escrever…', 'xkaichat' ),
					'error_generic'=> __( 'Não foi possível completar o pedido. Tente novamente.', 'xkaichat' ),
					'unavailable'  => __( 'O assistente está temporariamente indisponível. Contacte-nos por telefone 912423483.', 'xkaichat' ),
					'expired'      => __( 'A sua sessão expirou. Valide novamente o email.', 'xkaichat' ),
				),
			)
		);
	}

	/**
	 * Renderiza o widget no footer.
	 */
	public function render_widget() {
		if ( ! $this->widget_active ) {
			return;
		}
		$settings = get_option( 'xkaichat_settings', array() );
		$logo_url = $this->logo_url( $settings );
		include XKAICHAT_PLUGIN_DIR . 'public/partials/xkaichat-chat-widget.php';
	}

	/**
	 * Resolve a URL do logótipo do FAB.
	 *
	 * Prioridade: URL configurada nas settings; senão custom logo do tema;
	 * senão cadeia vazia (o ícone de chat é o fallback).
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public function logo_url( $settings ) {
		if ( ! empty( $settings['widget_logo_url'] ) ) {
			return esc_url_raw( $settings['widget_logo_url'] );
		}

		$logo_id = 0;
		if ( function_exists( 'get_theme_mod' ) ) {
			$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		if ( $logo_id && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		return '';
	}

	/**
	 * AJAX: pedir código.
	 */
	public function ajax_request_code() {
		check_ajax_referer( 'xkaichat_public', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		$result = $this->verification->request_code( $email, $phone, $ip );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'        => $result->get_error_code(),
					'retry_after' => $result->get_error_data( $result->get_error_code() ) ? $result->get_error_data()['retry_after'] : 0,
				),
				200
			);
		}

		nocache_headers();
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: validar código.
	 */
	public function ajax_verify_code() {
		check_ajax_referer( 'xkaichat_public', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		$result = $this->verification->verify_code( $email, $code, $ip );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'  => $result->get_error_code(),
					'token' => '',
				),
				200
			);
		}

		nocache_headers();
		wp_send_json_success(
			array(
				'token'      => $result['token'],
				'expires_in' => $result['expires_in'],
			)
		);
	}

	/**
	 * AJAX: enviar mensagem.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'xkaichat_public', 'nonce' );

		$token   = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		$session = $this->verification->validate_session( $token );
		if ( is_wp_error( $session ) ) {
			wp_send_json_error( array( 'code' => 'session_expired' ), 200 );
		}

		if ( '' === $message ) {
			wp_send_json_error( array( 'code' => 'empty_message' ), 200 );
		}

		// Limite por sessão/mensagem curta.
		$message = wp_trim_words( $message, 120, '…' );

		$this->messages->insert_message( $token, $session['email'], 'user', $message, 0 );

		$answer = $this->proxy->chat( $message );

		if ( is_wp_error( $answer ) ) {
			$this->messages->insert_message( $token, $session['email'], 'assistant', '[erro: ' . $answer->get_error_message() . ']', 0 );
			nocache_headers();
			wp_send_json_error( array( 'code' => 'proxy_unavailable', 'detail' => $answer->get_error_message() ), 200 );
		}

		$this->messages->insert_message( $token, $session['email'], 'assistant', $answer['answer'], $answer['cache_hit'] );

		nocache_headers();
		wp_send_json_success(
			array(
				'answer'    => $answer['answer'],
				'cache_hit' => $answer['cache_hit'],
				'source'    => $answer['source'],
			)
		);
	}

	/**
	 * AJAX: terminar sessão e enviar resumo.
	 */
	public function ajax_end_session() {
		check_ajax_referer( 'xkaichat_public', 'nonce' );

		$token   = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$session = $this->verification->end_session( $token );

		if ( is_wp_error( $session ) ) {
			nocache_headers();
			wp_send_json_success( array( 'closed' => false ), 200 );
		}

		$result = $this->summary->send( $this->messages, $token, $session );
		nocache_headers();
		wp_send_json_success( array( 'closed' => true, 'summary' => ! is_wp_error( $result ) ) );
	}

	public function add_cfasync_false( $tag, $handle, $src ) {
		if ( $handle === $this->plugin_name . '-chat' ) {
			return str_replace( 'src=', 'data-cfasync="false" src=', $tag );
		}
		return $tag;
	}
}