<?php
/**
 * Página de settings do XKaiChat.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$health = $this->proxy->health();
$health_ok = ! is_wp_error( $health );
?>

<div class="wrap xkaichat-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( 'xkaichat_settings_group' );
		do_settings_sections( 'xkaichat' );
		submit_button();
		?>
	</form>

	<h2><?php echo esc_html__( 'Diagnóstico do proxy', 'xkaichat' ); ?></h2>
	<div class="health-box">
		<?php if ( $health_ok ) : ?>
			<span class="status-pill ok"><?php echo esc_html__( 'Disponível', 'xkaichat' ); ?></span>
			<pre><code><?php
				$summary = array(
					'status'   => isset( $health['status'] ) ? $health['status'] : '',
					'mode'     => isset( $health['mode'] ) ? $health['mode'] : '',
					'cache'    => isset( $health['cache_items'] ) ? (int) $health['cache_items'] : 0,
					'upstream' => isset( $health['upstream'] ) ? $health['upstream'] : 'down',
				);
				echo esc_html( wp_json_encode( $summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			?></code></pre>
		<?php else : ?>
			<span class="status-pill bad"><?php echo esc_html__( 'Indisponível', 'xkaichat' ); ?></span>
			<p><?php echo esc_html( $health->get_error_message() ); ?></p>
		<?php endif; ?>
	</div>

	<div class="actions">
		<button type="button" class="button" id="xkaichat-clear-cache"><?php echo esc_html__( 'Limpar cache do proxy', 'xkaichat' ); ?></button>
		<button type="button" class="button" id="xkaichat-reindex"><?php echo esc_html__( 'Reindexar RAG (PDFs)', 'xkaichat' ); ?></button>
		<span class="xkaichat-admin-feedback" style="margin-left:8px;"></span>
	</div>

	<p class="description">
		<?php echo esc_html__( 'Shortcode do widget: [xkaichat]', 'xkaichat' ); ?>
	</p>

	<p class="description">
		<?php
		printf(
			/* translators: %s: endereço de email do admin (remetente dos emails do plugin). */
			esc_html__( 'Os emails de validação e de resumo são enviados com o remetente do site: %s', 'xkaichat' ),
			'<code>' . esc_html( (string) get_option( 'admin_email' ) ) . '</code>'
		);
		?>
	</p>
</div>