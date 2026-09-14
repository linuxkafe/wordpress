<?php
/**
 * Admin — página de settings, hooks de admin.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Admin.
 */
class Xkaichat_Admin {

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
	 * Cliente proxy.
	 *
	 * @var Xkaichat_Proxy
	 */
	private $proxy;

	/**
	 * Construtor.
	 *
	 * @param string        $plugin_name Slug.
	 * @param string        $version     Version.
	 * @param Xkaichat_Proxy $proxy      Cliente proxy.
	 */
	public function __construct( $plugin_name, $version, $proxy ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->proxy       = $proxy;
	}

	/**
	 * Regista hooks de admin.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_xkaichat_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_xkaichat_reindex', array( $this, 'ajax_reindex' ) );
	}

	/**
	 * Adiciona o menu.
	 */
	public function add_menu() {
		add_options_page(
			__( 'XKaiChat', 'xkaichat' ),
			__( 'XKaiChat', 'xkaichat' ),
			'manage_options',
			'xkaichat',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Regista a opção e os campos.
	 */
	public function register_settings() {
		register_setting(
			'xkaichat_settings_group',
			'xkaichat_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section( 'xkaichat_proxy', __( 'Ligação ao proxy', 'xkaichat' ), '__return_false', 'xkaichat' );
		add_settings_field( 'proxy_url', __( 'URL do proxy', 'xkaichat' ), array( $this, 'field_proxy_url' ), 'xkaichat', 'xkaichat_proxy' );
		add_settings_field( 'proxy_timeout', __( 'Timeout (segundos)', 'xkaichat' ), array( $this, 'field_proxy_timeout' ), 'xkaichat', 'xkaichat_proxy' );
		add_settings_field( 'proxy_shared_key', __( 'Chave partilhada (opcional)', 'xkaichat' ), array( $this, 'field_proxy_shared_key' ), 'xkaichat', 'xkaichat_proxy' );

		add_settings_section( 'xkaichat_communication', __( 'Comunicação', 'xkaichat' ), '__return_false', 'xkaichat' );
		add_settings_field( 'summary_email', __( 'Resumo para (email)', 'xkaichat' ), array( $this, 'field_summary_email' ), 'xkaichat', 'xkaichat_communication' );
		add_settings_field( 'summary_enabled', __( 'Enviar resumo por email', 'xkaichat' ), array( $this, 'field_summary_enabled' ), 'xkaichat', 'xkaichat_communication' );
		add_settings_field( 'retention_days', __( 'Retenção de mensagens (dias)', 'xkaichat' ), array( $this, 'field_retention_days' ), 'xkaichat', 'xkaichat_communication' );

		add_settings_section( 'xkaichat_widget', __( 'Widget', 'xkaichat' ), '__return_false', 'xkaichat' );
		add_settings_field( 'enable_global_widget', __( 'Mostrar em todo o site', 'xkaichat' ), array( $this, 'field_enable_global_widget' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'widget_heading', __( 'Título do widget', 'xkaichat' ), array( $this, 'field_widget_heading' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'widget_greeting', __( 'Saudação do widget', 'xkaichat' ), array( $this, 'field_widget_greeting' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'widget_auto_open', __( 'Abrir automaticamente', 'xkaichat' ), array( $this, 'field_widget_auto_open' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'widget_logo_url', __( 'URL do logótipo', 'xkaichat' ), array( $this, 'field_widget_logo_url' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'terms_text', __( 'Texto de consentimento', 'xkaichat' ), array( $this, 'field_terms_text' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'widget_initial_message', __( 'Mensagem inicial do chat', 'xkaichat' ), array( $this, 'field_widget_initial_message' ), 'xkaichat', 'xkaichat_widget' );
		add_settings_field( 'accent_color', __( 'Cor de acento', 'xkaichat' ), array( $this, 'field_accent_color' ), 'xkaichat', 'xkaichat_widget' );
	}

	/**
	 * Renderiza a página.
	 */
	public function render_page() {
		include XKAICHAT_PLUGIN_DIR . 'admin/partials/xkaichat-admin-display.php';
	}

	/**
	 * Sanitiza as settings.
	 *
	 * @param array $input Valores.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = array(
			'proxy_url'            => 'http://127.0.0.1:5001',
			'proxy_timeout'        => 180,
			'proxy_shared_key'     => '',
			'summary_email'        => 'capuchinho@capuchinhoverde.com',
			'summary_enabled'      => 1,
			'retention_days'       => 30,
			'enable_global_widget' => 0,
			'widget_heading'       => '',
			'widget_greeting'      => '',
			'widget_auto_open'     => 0,
			'widget_logo_url'      => '',
			'terms_text'           => '',
			'widget_initial_message' => '',
			'accent_color'         => '#5a8a4b',
		);

		$input  = wp_parse_args( (array) $input, $defaults );
		$output = array();

		$output['proxy_url']   = esc_url_raw( untrailingslashit( $input['proxy_url'] ) );
		if ( '' === $output['proxy_url'] ) {
			$output['proxy_url'] = $defaults['proxy_url'];
		}

		$output['proxy_timeout'] = max( 10, min( 300, (int) $input['proxy_timeout'] ) );
		$output['proxy_shared_key'] = sanitize_text_field( $input['proxy_shared_key'] );

		$output['summary_email'] = sanitize_email( $input['summary_email'] );
		if ( ! is_email( $output['summary_email'] ) ) {
			$output['summary_email'] = $defaults['summary_email'];
		}

		$output['summary_enabled'] = empty( $input['summary_enabled'] ) ? 0 : 1;
		$output['retention_days']  = max( 0, min( 365, (int) $input['retention_days'] ) );
		$output['enable_global_widget'] = empty( $input['enable_global_widget'] ) ? 0 : 1;
		$output['widget_auto_open']     = empty( $input['widget_auto_open'] ) ? 0 : 1;
		$output['widget_heading']  = sanitize_text_field( $input['widget_heading'] );
		$output['widget_greeting'] = sanitize_textarea_field( $input['widget_greeting'] );

		$output['widget_logo_url'] = esc_url_raw( $input['widget_logo_url'] );
		$output['terms_text']      = sanitize_textarea_field( $input['terms_text'] );
		$output['widget_initial_message'] = sanitize_textarea_field( $input['widget_initial_message'] );

		$color = sanitize_hex_color( $input['accent_color'] );
		if ( false === $color ) {
			$color = $defaults['accent_color'];
		}
		$output['accent_color'] = $color;

		return $output;
	}

	/**
	 * CSS.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_xkaichat' !== $hook ) {
			return;
		}
		wp_enqueue_style( $this->plugin_name . '-admin', XKAICHAT_PLUGIN_URL . 'admin/css/xkaichat-admin.css', array(), $this->version );
		wp_enqueue_script( $this->plugin_name . '-admin', XKAICHAT_PLUGIN_URL . 'admin/js/xkaichat-admin.js', array( 'jquery' ), $this->version, true );
		wp_localize_script(
			$this->plugin_name . '-admin',
			'xkaichat_admin',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'xkaichat_admin' ),
				'i18n'    => array(
					'running' => __( 'A processar…', 'xkaichat' ),
					'done'    => __( 'Concluído', 'xkaichat' ),
				),
			)
		);
	}

	/**
	 * AJAX: limpar cache do proxy.
	 */
	public function ajax_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}
		check_ajax_referer( 'xkaichat_admin', 'nonce' );

		$result = $this->proxy->clear_cache();
		wp_send_json_success( array( 'ok' => ! is_wp_error( $result ) ) );
	}

	/**
	 * AJAX: pedir reindexação RAG ao proxy.
	 */
	public function ajax_reindex() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}
		check_ajax_referer( 'xkaichat_admin', 'nonce' );

		$settings = $this->proxy->settings();
		$base     = untrailingslashit( esc_url_raw( $settings['proxy_url'] ) );

		$response = wp_remote_post(
			$base . '/api/rag/reindex',
			array( 'timeout' => 60 )
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( is_array( $body ) ? $body : array() );
	}

	/**
	 * Campos.
	 */
	public function field_proxy_url() {
		$v = $this->get( 'proxy_url' );
		echo '<input type="url" class="regular-text" name="xkaichat_settings[proxy_url]" value="' . esc_attr( $v ) . '" />';
	}

	/**
	 * Campo timeout.
	 */
	public function field_proxy_timeout() {
		$v = $this->get( 'proxy_timeout', 180 );
		echo '<input type="number" min="10" max="300" name="xkaichat_settings[proxy_timeout]" value="' . esc_attr( $v ) . '" />';
		echo '<p class="description">' . esc_html__( 'Tempo máximo de espera pela resposta do LLM.', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo chave partilhada.
	 */
	public function field_proxy_shared_key() {
		$v = $this->get( 'proxy_shared_key' );
		echo '<input type="password" class="regular-text" name="xkaichat_settings[proxy_shared_key]" value="' . esc_attr( $v ) . '" autocomplete="off" />';
		echo '<p class="description">' . esc_html__( 'Se configurada, é enviada como header X-Xkai-Proxy-Key ao proxy.', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo email de resumo.
	 */
	public function field_summary_email() {
		$v = $this->get( 'summary_email', 'capuchinho@capuchinhoverde.com' );
		echo '<input type="email" class="regular-text" name="xkaichat_settings[summary_email]" value="' . esc_attr( $v ) . '" />';
	}

	/**
	 * Campo toggle resumo.
	 */
	public function field_summary_enabled() {
		$v = (int) $this->get( 'summary_enabled', 1 );
		echo '<input type="checkbox" name="xkaichat_settings[summary_enabled]" value="1" ' . checked( 1, $v, false ) . ' />';
	}

	/**
	 * Campo retenção.
	 */
	public function field_retention_days() {
		$v = $this->get( 'retention_days', 30 );
		echo '<input type="number" min="0" max="365" name="xkaichat_settings[retention_days]" value="' . esc_attr( $v ) . '" />';
		echo '<p class="description">' . esc_html__( '0 = reter indefinidamente. As mensagens antigas são removidas diariamente.', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo widget global.
	 */
	public function field_enable_global_widget() {
		$v = (int) $this->get( 'enable_global_widget', 0 );
		echo '<input type="checkbox" name="xkaichat_settings[enable_global_widget]" value="1" ' . checked( 1, $v, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'Além do shortcode [xkaichat], mostra o botão em todo o site.', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo auto-abrir o painel.
	 */
	public function field_widget_auto_open() {
		$v = (int) $this->get( 'widget_auto_open', 0 );
		echo '<input type="checkbox" name="xkaichat_settings[widget_auto_open]" value="1" ' . checked( 1, $v, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'Se ligado, o painel de chat abre automaticamente ao carregar a página (ferramenta desligada por omissão).', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo título.
	 */
	public function field_widget_heading() {
		$v = $this->get( 'widget_heading' );
		echo '<input type="text" class="regular-text" name="xkaichat_settings[widget_heading]" value="' . esc_attr( $v ) . '" />';
	}

	/**
	 * Campo saudação.
	 */
	public function field_widget_greeting() {
		$v = $this->get( 'widget_greeting' );
		echo '<textarea class="large-text" rows="2" name="xkaichat_settings[widget_greeting]">' . esc_textarea( $v ) . '</textarea>';
	}

	/**
	 * Campo URL do logótipo (FAB e cabeçalho do chat).
	 */
	public function field_widget_logo_url() {
		$v = $this->get( 'widget_logo_url' );
		echo '<input type="url" class="regular-text" name="xkaichat_settings[widget_logo_url]" value="' . esc_attr( $v ) . '" />';
		echo '<p class="description">' . esc_html__( 'Se vazio, usa o logótipo personalizado do tema (se existir); senão, o ícone do chat.', 'xkaichat' ) . '</p>';
	}

	/**
	 * Campo texto de consentimento.
	 */
	public function field_terms_text() {
		$v = $this->get( 'terms_text' );
		if ( '' === $v ) {
			$v = 'Para continuar, aceite o tratamento dos seus dados: o seu email (e, se indicado, o telefone) serão usados apenas para validar esta conversa, responder às suas perguntas e enviar um resumo à equipa. Não partilhamos os seus dados com terceiros.';
		}
		echo '<textarea class="large-text" rows="4" name="xkaichat_settings[terms_text]">' . esc_textarea( $v ) . '</textarea>';
	}

	/**
	 * Campo mensagem inicial do chat.
	 */
	public function field_widget_initial_message() {
		$v = $this->get( 'widget_initial_message' );
		if ( '' === $v ) {
			$v = 'Olá! Já está ligado/a ao Capuchinho Verde. Em que posso ajudar hoje? Pergunte sobre produtos, preços e encomendas.';
		}
		echo '<textarea class="large-text" rows="2" name="xkaichat_settings[widget_initial_message]">' . esc_textarea( $v ) . '</textarea>';
	}

	/**
	 * Campo cor.
	 */
	public function field_accent_color() {
		$v = $this->get( 'accent_color', '#5a8a4b' );
		echo '<input type="color" name="xkaichat_settings[accent_color]" value="' . esc_attr( $v ) . '" />';
	}

	/**
	 * Lê um valor da opção.
	 *
	 * @param string $key     Chave.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private function get( $key, $default = '' ) {
		$settings = get_option( 'xkaichat_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}