<?php
/**
 * Runner de testes PHP sem dependências WP.
 *
 * Uso: php tests/php/run-all.php
 *
 * @package XKaiChat
 */

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['xkc_failures'] = array();
$GLOBALS['xkc_count']    = 0;

function expect( $cond, $msg ) {
	$GLOBALS['xkc_count']++;
	if ( ! $cond ) {
		$GLOBALS['xkc_failures'][] = $msg;
		fwrite( STDERR, "  ✗ $msg\n" );
	}
}

function reset_state() {
	$GLOBALS['wpdb'] = new FauxWpdb();
	$GLOBALS['xkc_options']    = array();
	$GLOBALS['xkc_transients'] = array();
	$GLOBALS['xkc_mailed']     = array();
	$GLOBALS['xkc_mail_fail']  = false;
	$GLOBALS['xkc_http']       = array();
	$GLOBALS['xkc_http_args']  = array();
	$GLOBALS['xkc_theme_mods'] = array();
	$GLOBALS['xkc_attachment_urls'] = array();
}

/* ============ Activator / Deactivator ============ */

function test_activator() {
	reset_state();

	Xkaichat_Activator::activate();

	expect( get_option( 'xkaichat_settings' ) !== false, 'Activator cria opção xkaichat_settings' );
	$s = get_option( 'xkaichat_settings' );
	expect( isset( $s['proxy_url'] ) && 'http://127.0.0.1:5001' === $s['proxy_url'], 'proxy_url por omissão' );
	expect( isset( $s['summary_email'] ) && 'capuchinho@capuchinhoverde.com' === $s['summary_email'], 'summary_email por omissão' );
	expect( isset( $s['accent_color'] ) && '#5a8a4b' === $s['accent_color'], 'estética verde por omissão' );
	expect( isset( $s['enable_global_widget'] ) && 0 === (int) $s['enable_global_widget'], 'widget global off por omissão' );
	expect( isset( $s['widget_auto_open'] ) && 0 === (int) $s['widget_auto_open'], 'auto_open off por omissão' );

	$wpdb = $GLOBALS['wpdb'];
	expect( isset( $wpdb->tables['wp_xkaichat_codes'] ), 'tabela xkaichat_codes criada' );
	expect( isset( $wpdb->tables['wp_xkaichat_messages'] ), 'tabela xkaichat_messages criada' );

	// Idempotente.
	Xkaichat_Activator::activate();
	expect( get_option( 'xkaichat_settings' )['proxy_url'] === 'http://127.0.0.1:5001', 'Activator idempotente (mantém opção)' );
}

function test_deactivator() {
	reset_state();
	Xkaichat_Activator::activate();
	Xkaichat_Deactivator::deactivate();
	expect( true, 'Deactivator executa sem erro (limpeza agendada)' );
}

/* ============ Verification ============ */

function test_verification() {
	reset_state();
	$v = new Xkaichat_Verification( 'xkaichat', '1.0.0' );

	// normalize_phone.
	expect( '912423483' === $v->normalize_phone( '912 423 483' ), 'normalize_phone aceita espaços' );
	expect( '+351912423483' === $v->normalize_phone( '+351 912 423 483' ), 'normalize_phone aceita +351' );
	expect( '' === $v->normalize_phone( '12345' ), 'normalize_phone rejeita número inválido' );
	expect( '' === $v->normalize_phone( '' ), 'normalize_phone vazio devolve vazio' );

	// request_code inválido.
	$r = $v->request_code( 'nao-email', '', '1.2.3.4' );
	expect( is_wp_error( $r ) && 'xkc_invalid_email' === $r->get_error_code(), 'request_code rejeita email inválido' );

	// happy path.
	$r = $v->request_code( 'cliente@example.com', '912423483', '1.2.3.4' );
	expect( ! is_wp_error( $r ) && ! empty( $r['sent'] ), 'request_code envia código com sucesso' );
	expect( 1 === count( $GLOBALS['xkc_mailed'] ), 'wp_mail chamado uma vez' );
	preg_match( '/é:\s*(\d{6})/', $GLOBALS['xkc_mailed'][0]['body'], $m );
	expect( isset( $m[1] ), 'email contém código de 6 dígitos' );
	$code = $m[1];

	// verify happy path.
	$ok = $v->verify_code( 'cliente@example.com', $code, '1.2.3.4' );
	expect( ! is_wp_error( $ok ), 'verify_code aceita código correto' );
	expect( isset( $ok['token'] ) && 32 === strlen( $ok['token'] ), 'token de sessão 32 chars' );
	expect( false !== get_transient( 'xkaichat_session_' . $ok['token'] ), 'sessão criada em transient' );
	$session = get_transient( 'xkaichat_session_' . $ok['token'] );
	expect( 'cliente@example.com' === $session['email'], 'sessão guarda email' );
	expect( '912423483' === $session['phone'], 'sessão guarda telefone' );

	// Código errado.
	reset_state();
	$v = new Xkaichat_Verification( 'xkaichat', '1.0.0' );
	$v->request_code( 'cliente@example.com', '', '1.2.3.4' );
	preg_match( '/é:\s*(\d{6})/', $GLOBALS['xkc_mailed'][0]['body'], $m );
	$wrong = $code === $m[1] ? ( ( (int) $code + 1 ) % 1000000 ) : $code;
	$bad = $v->verify_code( 'cliente@example.com', str_pad( (string) $wrong, 6, '0', STR_PAD_LEFT ), '1.2.3.4' );
	expect( is_wp_error( $bad ) && 'xkc_wrong' === $bad->get_error_code(), 'verify_code rejeita código errado' );

	// Rate limit por IP (máx. 3 na janela).
	reset_state();
	$v = new Xkaichat_Verification( 'xkaichat', '1.0.0' );
	$v->request_code( 'a@example.com', '', '9.9.9.9' );
	$v->request_code( 'b@example.com', '', '9.9.9.9' );
	$v->request_code( 'c@example.com', '', '9.9.9.9' );
	$r4 = $v->request_code( 'd@example.com', '', '9.9.9.9' );
	expect( is_wp_error( $r4 ) && 'xkc_rate_limit' === $r4->get_error_code(), 'rate-limit por IP (4º pedido bloqueado)' );

	// validate_session token inválido / expirado.
	reset_state();
	$v = new Xkaichat_Verification( 'xkaichat', '1.0.0' );
	$bad = $v->validate_session( 'curto' );
	expect( is_wp_error( $bad ) && 'xkc_bad_token' === $bad->get_error_code(), 'validate_session rejeita token curto' );
	$v->request_code( 'c@example.com', '', '1.2.3.4' );
	preg_match( '/é:\s*(\d{6})/', $GLOBALS['xkc_mailed'][0]['body'], $m );
	$ok = $v->verify_code( 'c@example.com', $m[1], '1.2.3.4' );
	$data = $v->validate_session( $ok['token'] );
	expect( is_array( $data ) && 'c@example.com' === $data['email'], 'validate_session devolve sessão' );
	delete_transient( 'xkaichat_session_' . $ok['token'] );
	$expired = $v->validate_session( $ok['token'] );
	expect( is_wp_error( $expired ) && 'xkc_expired_session' === $expired->get_error_code(), 'validate_session detecta sessão expirada' );

	// end_session devolve dados e apaga transient.
	reset_state();
	$v = new Xkaichat_Verification( 'xkaichat', '1.0.0' );
	$v->request_code( 'e@example.com', '', '1.2.3.4' );
	preg_match( '/é:\s*(\d{6})/', $GLOBALS['xkc_mailed'][0]['body'], $m );
	$ok = $v->verify_code( 'e@example.com', $m[1], '1.2.3.4' );
	$end = $v->end_session( $ok['token'] );
	expect( is_array( $end ) && 'e@example.com' === $end['email'], 'end_session devolve dados' );
	expect( false === get_transient( 'xkaichat_session_' . $ok['token'] ), 'end_session apaga transient' );
}

/* ============ Messages ============ */

function test_messages() {
	reset_state();
	$msg = new Xkaichat_Messages( 'xkaichat', '1.0.0' );

	$id = $msg->insert_message( str_repeat( 'a', 32 ), 'c@example.com', 'user', 'Quanto custa a bôla?' );
	expect( is_int( $id ) && $id > 0, 'insert_message devolve row id' );
	$id2 = $msg->insert_message( str_repeat( 'a', 32 ), 'c@example.com', 'assistant', 'A bôla custa 18€.', 1 );
	expect( is_int( $id2 ) && $id2 > $id, 'insert_message 2º id incrementa' );

	expect( false === $msg->insert_message( '', 'c@example.com', 'user', 'x' ), 'insert_message rejeita session vazia' );
	expect( false === $msg->insert_message( str_repeat( 'b', 32 ), 'c@example.com', 'user', '' ), 'insert_message rejeita conteúdo vazio' );

	$rows = $msg->get_session_messages( str_repeat( 'a', 32 ) );
	expect( 2 === count( $rows ), 'get_session_messages devolve 2' );
	expect( 'user' === $rows[0]['role'] && 'assistant' === $rows[1]['role'], 'ordem preservada (ASC)' );
	expect( 1 === (int) $rows[1]['cache_hit'], 'cache_hit persistido' );

	// cleanup_expired com 30 dias (nada apagado por omissão).
	$msg->cleanup_expired();
	expect( 2 === count( $msg->get_session_messages( str_repeat( 'a', 32 ) ) ), 'cleanup com 30 dias não apaga recém-criadas' );

	// Com retenção = -1 (desativado) nada acontece.
	update_option( 'xkaichat_settings', array( 'retention_days' => 0 ) );
	$msg->cleanup_expired();
	expect( 2 === count( $msg->get_session_messages( str_repeat( 'a', 32 ) ) ), 'cleanup desativado (retenção 0)' );
}

/* ============ Summary ============ */

function test_summary() {
	reset_state();
	update_option( 'xkaichat_settings', array( 'summary_enabled' => 1, 'summary_email' => 'capuchinho@capuchinhoverde.com' ) );
	$msg = new Xkaichat_Messages( 'xkaichat', '1.0.0' );
	$sum = new Xkaichat_Summary( 'xkaichat', '1.0.0' );
	$key = str_repeat( 'a', 32 );

	$msg->insert_message( $key, 'c@example.com', 'user', 'Quanto custa a bôla?' );

	$ok = $sum->send( $msg, $key, array( 'email' => 'c@example.com', 'phone' => '912423483' ) );
	expect( true === $ok, 'summary envia com sucesso' );
	expect( 1 === count( $GLOBALS['xkc_mailed'] ), 'summary chamou wp_mail' );
	expect( 'capuchinho@capuchinhoverde.com' === $GLOBALS['xkc_mailed'][0]['to'], 'summary usa destinatário configurado' );
	expect( false !== strpos( $GLOBALS['xkc_mailed'][0]['body'], 'bôla' ), 'summary inclui transcrição' );
	expect( false !== strpos( $GLOBALS['xkc_mailed'][0]['body'], '912423483' ), 'summary inclui telefone' );

	// Desativado.
	reset_state();
	update_option( 'xkaichat_settings', array( 'summary_enabled' => 0 ) );
	$msg = new Xkaichat_Messages( 'xkaichat', '1.0.0' );
	$sum = new Xkaichat_Summary( 'xkaichat', '1.0.0' );
	$key = str_repeat( 'b', 32 );
	$msg->insert_message( $key, 'c@example.com', 'user', 'oi' );
	$ok = $sum->send( $msg, $key, array( 'email' => 'c@example.com' ) );
	expect( true === $ok && 0 === count( $GLOBALS['xkc_mailed'] ), 'summary desativado não envia' );

	// Falha de envio.
	reset_state();
	update_option( 'xkaichat_settings', array( 'summary_enabled' => 1, 'summary_email' => 'capuchinho@capuchinhoverde.com' ) );
	$msg = new Xkaichat_Messages( 'xkaichat', '1.0.0' );
	$sum = new Xkaichat_Summary( 'xkaichat', '1.0.0' );
	$key = str_repeat( 'c', 32 );
	$msg->insert_message( $key, 'c@example.com', 'user', 'oi' );
	$GLOBALS['xkc_mail_fail'] = true;
	$err = $sum->send( $msg, $key, array( 'email' => 'c@example.com' ) );
	expect( is_wp_error( $err ) && 'xkc_summary_mail_failed' === $err->get_error_code(), 'summary reporta falha de wp_mail' );
}

/* ============ Proxy client ============ */

function test_proxy_client() {
	reset_state();
	update_option( 'xkaichat_settings', array( 'proxy_url' => 'http://127.0.0.1:5001/', 'proxy_timeout' => 10, 'proxy_shared_key' => '' ) );
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );

	// chat sucesso.
	$GLOBALS['xkc_http']['http://127.0.0.1:5001/api/chat'] = array(
		'code' => 200,
		'body' => wp_json_encode( array( 'answer' => 'A bôla custa 18€.', 'cache_hit' => false, 'source' => 'pdf' ) ),
	);
	$ok = $px->chat( 'Quanto custa a bôla?' );
	expect( ! is_wp_error( $ok ), 'proxy chat sem erro' );
	expect( 'A bôla custa 18€.' === $ok['answer'], 'proxy chat devolveu answer' );
	expect( 0 === $ok['cache_hit'], 'proxy chat cache_hit falso' );
	expect( 'pdf' === $ok['source'], 'proxy chat source pdf' );
	expect( 'http://127.0.0.1:5001/api/chat' === $GLOBALS['xkc_http_args'][0]['url'], 'proxy chamou endpoint correto (+ trailing slash removido)' );

	// Source default quando em falta.
	reset_state();
	update_option( 'xkaichat_settings', array( 'proxy_url' => 'http://127.0.0.1:5001', 'proxy_timeout' => 10 ) );
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );
	$GLOBALS['xkc_http']['http://127.0.0.1:5001/api/chat'] = array(
		'code' => 200,
		'body' => wp_json_encode( array( 'answer' => 'oi' ) ),
	);
	$ok = $px->chat( 'oi' );
	expect( ! is_wp_error( $ok ) && 'llm' === $ok['source'], 'proxy source default llm' );

	// Erro HTTP.
	reset_state();
	update_option( 'xkaichat_settings', array( 'proxy_url' => 'http://127.0.0.1:5001', 'proxy_timeout' => 10 ) );
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );
	$GLOBALS['xkc_http']['http://127.0.0.1:5001/api/chat'] = array(
		'code' => 502,
		'body' => wp_json_encode( array( 'detail' => 'llm_indisponivel' ) ),
	);
	$err = $px->chat( 'oi' );
	expect( is_wp_error( $err ) && 'xkc_proxy_http' === $err->get_error_code(), 'proxy reporta erro HTTP' );

	// Proxy indisponível (rede).
	reset_state();
	update_option( 'xkaichat_settings', array( 'proxy_url' => 'http://127.0.0.1:5001', 'proxy_timeout' => 10 ) );
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );
	$err = $px->chat( 'oi' );
	expect( is_wp_error( $err ) && 'http_request_failed' === $err->get_error_code(), 'proxy network fail vira WP_Error' );

	// Health.
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );
	$GLOBALS['xkc_http']['http://127.0.0.1:5001/api/health'] = array(
		'code' => 200,
		'body' => wp_json_encode( array( 'status' => 'ok', 'mode' => 'ollama', 'model' => 'qwen3:8b', 'cache_items' => 0 ) ),
	);
	$h = $px->health();
	expect( is_array( $h ) && 'ok' === $h['status'], 'proxy health' );

	// Chave partilhada no header.
	reset_state();
	update_option( 'xkaichat_settings', array( 'proxy_url' => 'http://127.0.0.1:5002', 'proxy_timeout' => 10, 'proxy_shared_key' => 'segredo-teste' ) );
	$px = new Xkaichat_Proxy( 'xkaichat', '1.0.0' );
	$GLOBALS['xkc_http']['http://127.0.0.1:5002/api/chat'] = array(
		'code' => 200,
		'body' => wp_json_encode( array( 'answer' => 'ok', 'cache_hit' => false, 'source' => 'none' ) ),
	);
	$px->chat( 'oi' );
	$headers = $GLOBALS['xkc_http_args'][0]['args']['headers'];
	expect( isset( $headers['X-Xkai-Proxy-Key'] ) && 'segredo-teste' === $headers['X-Xkai-Proxy-Key'], 'header X-Xkai-Proxy-Key enviado' );
}

/* ============ Admin: campos de onboarding (T010) ============ */

function test_admin_onboarding_fields() {
	reset_state();
	$admin = new Xkaichat_Admin( 'xkaichat', '1.0.0', null );

	$input = array(
		'widget_logo_url'        => 'https://example.com/logo.png',
		'terms_text'             => '<script>alert(1)</script>Termos de exemplo.',
		'widget_initial_message' => 'Olá! Está ligado ao Capuchinho Verde.',
	);
	$out = $admin->sanitize_settings( $input );

	expect( 'https://example.com/logo.png' === $out['widget_logo_url'], 'sanitize guarda widget_logo_url' );
	expect( false === strpos( $out['terms_text'], '<script' ), 'sanitize remove HTML dos termos' );
	expect( 'Olá! Está ligado ao Capuchinho Verde.' === $out['widget_initial_message'], 'sanitize guarda widget_initial_message' );
	expect( isset( $out['terms_text'] ) && isset( $out['widget_initial_message'] ), 'sanitize inclui chaves novas' );

	// Input vazio preserva as chaves (defaults).
	$out2 = $admin->sanitize_settings( array() );
	expect( '' === $out2['widget_logo_url'], 'logo vazio default preservado' );
	expect( '' === $out2['terms_text'], 'terms vazio default preservado' );
	expect( '' === $out2['widget_initial_message'], 'initial vazio default preservado' );

	// Auto-open: aceita 1 e devolve 0 por omissão (T012).
	$out3 = $admin->sanitize_settings( array( 'widget_auto_open' => '1' ) );
	expect( 1 === $out3['widget_auto_open'], 'sanitize aceita auto_open=1' );
	expect( 0 === $out2['widget_auto_open'], 'sanitize auto_open default 0' );
}

/* ============ Public: resolução do logótipo (T010) ============ */

function test_public_logo() {
	reset_state();
	$pub = new Xkaichat_Public( 'xkaichat', '1.0.0', null, null, null, null );

	// Prioridade 1: URL configurada.
	$url = $pub->logo_url( array( 'widget_logo_url' => 'https://cdn.site/logo.png' ) );
	expect( 'https://cdn.site/logo.png' === $url, 'logo_url usa URL configurada' );

	// Prioridade 2: custom logo do tema.
	$GLOBALS['xkc_theme_mods'] = array( 'custom_logo' => 7 );
	$GLOBALS['xkc_attachment_urls'] = array( 7 => 'https://cdn.site/custom-logo.png' );
	$url = $pub->logo_url( array( 'widget_logo_url' => '' ) );
	expect( 'https://cdn.site/custom-logo.png' === $url, 'logo_url cai para custom logo do tema' );

	// Sem fonte → cadeia vazia (ícone de chat é o fallback).
	$GLOBALS['xkc_theme_mods'] = array();
	$GLOBALS['xkc_attachment_urls'] = array();
	$url = $pub->logo_url( array( 'widget_logo_url' => '' ) );
	expect( '' === $url, 'logo_url vazio sem fonte' );
}

/* ============ executa ============ */

test_activator();
test_deactivator();
test_verification();
test_messages();
test_summary();
test_proxy_client();
test_admin_onboarding_fields();
test_public_logo();

$total = $GLOBALS['xkc_count'];
$fails = count( $GLOBALS['xkc_failures'] );

echo "PHP: {$total} asserts, {$fails} falhas\n";
if ( $fails > 0 ) {
	echo join( "\n", array_map( function ( $f ) { return "  ✗ " . $f; }, $GLOBALS['xkc_failures'] ) ) . "\n";
	exit( 1 );
}
echo "✅ testes PHP ok\n";