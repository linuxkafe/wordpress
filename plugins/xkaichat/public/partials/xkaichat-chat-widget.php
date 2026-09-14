<?php
/**
 * Widget de chat — markup (SVG inline, sem emojis). As strings são i18n.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$root_class = 'xkaichat-root';
if ( ! empty( $logo_url ) ) {
	$root_class .= ' xkaichat-has-logo';
}
?>

<div class="<?php echo esc_attr( $root_class ); ?>" data-accent="<?php echo esc_attr( get_option( 'xkaichat_settings', array() )['accent_color'] ?? '#5a8a4b' ); ?>">

	<button type="button" class="xkaichat-fab" aria-label="<?php echo esc_attr__( 'Abrir assistente', 'xkaichat' ); ?>">
		<?php if ( ! empty( $logo_url ) ) : ?>
			<img class="xkaichat-fab-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<?php endif; ?>
		<svg class="xkaichat-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M4 4h16c1.1 0 2 .9 2 2v9c0 1.1-.9 2-2 2H8l-5 4V6c0-1.1.9-2 2-2zm3 5v2h10V9H7zm0 4v2h7v-2H7z"/>
		</svg>
		<svg class="xkaichat-fab-close" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3 10.6 10.6 16.9 4.3z"/>
		</svg>
	</button>

	<div class="xkaichat-tip" data-role="tip" hidden>
		<span class="xkaichat-tip-text" data-role="tip-text"></span>
		<button type="button" class="xkaichat-tip-close" data-role="tip-close" aria-label="<?php echo esc_attr__( 'Fechar dica', 'xkaichat' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3 10.6 10.6 16.9 4.3z"/>
			</svg>
		</button>
	</div>

	<div class="xkaichat-panel" role="dialog" aria-label="<?php echo esc_attr__( 'Assistente Capuchinho Verde', 'xkaichat' ); ?>" hidden>
		<header class="xkaichat-header">
			<div class="xkaichat-header-left">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<img class="xkaichat-header-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
				<?php endif; ?>
				<div class="xkaichat-header-title" data-role="heading"></div>
			</div>
			<span class="xkaichat-header-actions">
				<button type="button" class="xkaichat-header-btn" data-role="maximize-btn" aria-label="<?php echo esc_attr__( 'Aumentar tamanho', 'xkaichat' ); ?>" aria-pressed="false">
					<svg class="xkaichat-icon-maximize" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M5 3h4v2H7v2H5V3zm10 0h4v4h-2V5h-2V3zM3 13h2v4h2v2H3v-6zm16 0h2v6h-4v-2h2v-4z"/>
					</svg>
					<svg class="xkaichat-icon-minimize" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M9 3v4H5v2H3V3h6zm6 0h6v6h-2V5h-4V3zM3 13h2v4h4v2H3v-6zm16 0h2v6h-6v-2h4v-4z"/>
					</svg>
				</button>
				<span class="xkaichat-online"><span class="xkaichat-dot"></span> online</span>
			</span>
		</header>

		<section class="xkaichat-view xkaichat-view-terms" data-role="view-terms" hidden>
			<p class="xkaichat-view-title"><?php echo esc_html__( 'Antes de começar', 'xkaichat' ); ?></p>
			<div class="xkaichat-terms" data-role="terms"></div>
			<button type="button" class="xkaichat-btn xkaichat-btn-primary" data-role="terms-accept-btn"><?php echo esc_html__( 'Aceitar e continuar', 'xkaichat' ); ?></button>
		</section>

		<section class="xkaichat-view xkaichat-view-email" data-role="view-email" hidden>
			<p class="xkaichat-greeting" data-role="greeting"></p>
			<form class="xkaichat-form" data-role="email-form">
				<label>
					<span class="xkaichat-label"><?php echo esc_html__( 'O seu email', 'xkaichat' ); ?></span>
					<input type="email" name="email" class="xkaichat-input" required placeholder="nome@exemplo.pt" />
				</label>
				<label>
					<span class="xkaichat-label"><?php echo esc_html__( 'Telefone (opcional)', 'xkaichat' ); ?></span>
					<input type="tel" name="phone" class="xkaichat-input" placeholder="912 345 678" />
				</label>
				<button type="submit" class="xkaichat-btn xkaichat-btn-primary" data-role="request-code-btn"><?php echo esc_html__( 'Enviar código', 'xkaichat' ); ?></button>
				<p class="xkaichat-error" data-role="email-error" hidden></p>
			</form>
			<p class="xkaichat-privacy" data-role="privacy"></p>
		</section>

		<section class="xkaichat-view xkaichat-view-code" data-role="view-code" hidden>
			<p class="xkaichat-greeting"><?php echo esc_html__( 'Introduza o código de 6 dígitos enviado para o seu email.', 'xkaichat' ); ?></p>
			<form class="xkaichat-form" data-role="code-form">
				<label>
					<span class="xkaichat-label"><?php echo esc_html__( 'Código', 'xkaichat' ); ?></span>
					<input type="text" name="code" class="xkaichat-input xkaichat-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required />
				</label>
				<button type="submit" class="xkaichat-btn xkaichat-btn-primary" data-role="verify-btn"><?php echo esc_html__( 'Validar', 'xkaichat' ); ?></button>
				<button type="button" class="xkaichat-btn xkaichat-btn-ghost" data-role="resend-btn"><?php echo esc_html__( 'Reenviar código', 'xkaichat' ); ?></button>
				<p class="xkaichat-error" data-role="code-error" hidden></p>
			</form>
		</section>

		<section class="xkaichat-view xkaichat-view-chat" data-role="view-chat" hidden>
			<div class="xkaichat-body" data-role="body" role="log" aria-live="polite">
				<div class="xkaichat-bubble xkaichat-bubble-agent" data-role="greeting-bubble"></div>
				<div class="xkaichat-bubble xkaichat-bubble-agent xkaichat-typing" data-role="thinking" hidden>
					<span class="xkaichat-typing-dot"></span>
					<span class="xkaichat-typing-dot"></span>
					<span class="xkaichat-typing-dot"></span>
					<span class="xkaichat-typing-text" data-role="thinking-text"></span>
				</div>
			</div>
			<form class="xkaichat-form xkaichat-composer" data-role="chat-form">
				<label for="xkaichat-input" class="screen-reader-text"><?php echo esc_html__( 'Mensagem', 'xkaichat' ); ?></label>
				<input type="text" id="xkaichat-input" class="xkaichat-input" data-role="message-input" placeholder="<?php echo esc_attr__( 'Escreva a sua pergunta…', 'xkaichat' ); ?>" />
				<button type="submit" class="xkaichat-btn xkaichat-btn-round" data-role="send-btn" aria-label="<?php echo esc_attr__( 'Enviar', 'xkaichat' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M3 20.5v-6l8-2.5-8-2.5v-6l18 8.5z"/>
					</svg>
				</button>
				<button type="button" class="xkaichat-btn xkaichat-btn-ghost xkaichat-end" data-role="end-btn"><?php echo esc_html__( 'Terminar conversa', 'xkaichat' ); ?></button>
			</form>
		</section>
	</div>
</div>