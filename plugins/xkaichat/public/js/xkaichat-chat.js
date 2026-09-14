/**
 * XKaiChat — estado: termos -> email -> codigo -> chat.
 * Sem dependências externas (vanilla JS).
 */
(function () {
	'use strict';

	var root = document.querySelector('.xkaichat-root');
	if (!root ||
		typeof window.xkaichat_ajax === 'undefined' ||
		typeof window.xkaichat_ajax.ajax === 'undefined') {
		return;
	}

	var cfg = window.xkaichat_ajax;
	var I = cfg.i18n || {};
	var panel = root.querySelector('.xkaichat-panel');
	var fab = root.querySelector('.xkaichat-fab');
	var heading = root.querySelector('[data-role="heading"]');
	var greeting = root.querySelector('[data-role="greeting"]');
	var greetingBubble = root.querySelector('[data-role="greeting-bubble"]');
	var tip = root.querySelector('[data-role="tip"]');
	var tipText = root.querySelector('[data-role="tip-text"]');
	var tipClose = root.querySelector('[data-role="tip-close"]');
	var termsBox = root.querySelector('[data-role="terms"]');
	var termsAcceptBtn = root.querySelector('[data-role="terms-accept-btn"]');
	var views = {
		terms: root.querySelector('[data-role="view-terms"]'),
		email: root.querySelector('[data-role="view-email"]'),
		code: root.querySelector('[data-role="view-code"]'),
		chat: root.querySelector('[data-role="view-chat"]')
	};
	var thinking = root.querySelector('[data-role="thinking"]');
	var thinkingText = root.querySelector('[data-role="thinking-text"]');
	var bodyEl = root.querySelector('[data-role="body"]');
	var maximizeBtn = root.querySelector('[data-role="maximize-btn"]');
	var emailForm = root.querySelector('[data-role="email-form"]');
	var codeForm = root.querySelector('[data-role="code-form"]');
	var chatForm = root.querySelector('[data-role="chat-form"]');
	var emailError = root.querySelector('[data-role="email-error"]');
	var codeError = root.querySelector('[data-role="code-error"]');
	var messageInput = root.querySelector('[data-role="message-input"]');
	var requestCodeBtn = root.querySelector('[data-role="request-code-btn"]');
	var verifyBtn = root.querySelector('[data-role="verify-btn"]');
	var resendBtn = root.querySelector('[data-role="resend-btn"]');
	var endBtn = root.querySelector('[data-role="end-btn"]');

	var sessionToken = null;
	var lastEmail = '';
	var busy = false;
	var SESSION_STORAGE_KEY = 'xkaichat_token';
	var TERMS_STORAGE_KEY = 'xkaichat_terms_accepted';
	var TIP_STORAGE_KEY = 'xkaichat_tip_dismissed';

	heading.textContent = cfg.heading || 'Capuchinho Verde';
	greeting.textContent = cfg.greeting || '';
	if (greetingBubble) {
		greetingBubble.textContent = cfg.initial || cfg.greeting || '';
	}
	if (termsBox) {
		termsBox.textContent = cfg.terms || '';
	}
	if (thinkingText) {
		thinkingText.textContent = I.thinking || 'A escrever…';
	}
	root.style.setProperty('--xkc-brand', cfg.accent || '#5a8a4b');

	// cor do data-accent em configurações antigas
	if (root.getAttribute('data-accent')) {
		root.style.setProperty('--xkc-brand', root.getAttribute('data-accent'));
	}

	function storageGet(key) {
		try {
			return sessionStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function storageSet(key, value) {
		try {
			sessionStorage.setItem(key, value);
		} catch (e) { /* storage indisponível */ }
	}

	function hasSessionToken() {
		return !!storageGet(SESSION_STORAGE_KEY);
	}

	function termsAccepted() {
		return storageGet(TERMS_STORAGE_KEY) === '1';
	}

	function dismissTip() {
		if (!tip || tip.hidden) {
			return;
		}
		tip.hidden = true;
		fab.classList.remove('xkaichat-attention');
		storageSet(TIP_STORAGE_KEY, '1');
	}

	if (tipClose) {
		tipClose.addEventListener('click', dismissTip);
	}

	function initTip() {
		if (!tip || !tipText) {
			return;
		}
		tipText.textContent = cfg.heading || '';
		if (storageGet(TIP_STORAGE_KEY) !== '1') {
			tip.hidden = false;
			fab.classList.add('xkaichat-attention');
		}
	}

	function openPanel() {
		dismissTip();
		panel.hidden = false;
		root.classList.add('xkaichat-open');
	}

	function closePanel() {
		panel.hidden = true;
		root.classList.remove('xkaichat-open');
	}

	fab.addEventListener('click', function () {
		if (panel.hidden) {
			openPanel();
			chooseEntryView();
		} else {
			closePanel();
		}
	});

	if (maximizeBtn) {
		maximizeBtn.addEventListener('click', function () {
			var maximized = root.classList.toggle('xkaichat-maximized');
			maximizeBtn.setAttribute('aria-pressed', maximized ? 'true' : 'false');
		});
	}

	function ajax(action, payload, cb) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		body.append('token', sessionToken || '');
		for (var k in payload) {
			if (Object.prototype.hasOwnProperty.call(payload, k)) {
				body.append(k, payload[k]);
			}
		}

		var xhr = new XMLHttpRequest();
		xhr.open('POST', cfg.ajax, true);
		xhr.onload = function () {
			if (xhr.status !== 200) {
				cb({ error: true, code: 'http_' + xhr.status });
				return;
			}
			try {
				var parsed = JSON.parse(xhr.responseText);
				if (parsed.success) {
					cb(null, parsed.data || {});
				} else {
					cb({ error: true, code: (parsed.data && parsed.data.code) || 'error' });
				}
			} catch (e) {
				cb({ error: true, code: 'parse_error' });
			}
		};
		xhr.onerror = function () {
			cb({ error: true, code: 'network' });
		};
		xhr.send(body);
	}

	function setView(name) {
		for (var v in views) {
			if (Object.prototype.hasOwnProperty.call(views, v)) {
				views[v].hidden = (v !== name);
			}
		}
	}

	function setThinking(on) {
		thinking.hidden = !on;
		busy = on;
		if (on) {
			bodyEl.appendChild(thinking);
			bodyEl.scrollTop = bodyEl.scrollHeight;
		}
		var input = messageInput;
		if (input) {
			input.disabled = on;
		}
	}

	function showError(el, msg, on) {
		if (!el) {
			return;
		}
		el.textContent = msg || '';
		el.hidden = !on;
	}

	function addBubble(text, role) {
		var div = document.createElement('div');
		div.className = 'xkaichat-bubble ' + (role === 'user' ? 'xkaichat-bubble-user' : 'xkaichat-bubble-agent');
		div.textContent = text;
		bodyEl.appendChild(div);
		bodyEl.scrollTop = bodyEl.scrollHeight;
	}

	function persistTranscript() {
		try {
			var msgs = [];
			bodyEl.querySelectorAll('.xkaichat-bubble').forEach(function (b) {
				if (b === greetingBubble || b === thinking) {
					return;
				}
				msgs.push({ t: b.textContent, u: b.classList.contains('xkaichat-bubble-user') });
			});
			sessionStorage.setItem('xkaichat_transcript_' + (sessionToken || 'anon'), JSON.stringify(msgs));
		} catch (e) { /* storage indisponível */ }
	}

	function restoreTranscript() {
		if (!sessionToken) {
			return;
		}
		try {
			var raw = sessionStorage.getItem('xkaichat_transcript_' + sessionToken);
			if (!raw) {
				return;
			}
			var msgs = JSON.parse(raw);
			bodyEl.innerHTML = '';
			if (greetingBubble) {
				greetingBubble.className = 'xkaichat-bubble xkaichat-bubble-agent';
				greetingBubble.textContent = cfg.initial || cfg.greeting || '';
				bodyEl.appendChild(greetingBubble);
			}
			if (thinking && thinking.parentNode !== bodyEl) {
				bodyEl.appendChild(thinking);
			}
			msgs.forEach(function (m) {
				addBubble(m.t, m.u ? 'user' : 'agent');
			});
		} catch (e) { /* ignore */ }
	}

	function clearTranscript() {
		try {
			sessionStorage.removeItem('xkaichat_transcript_' + (sessionToken || 'anon'));
		} catch (e) { /* ignore */ }
	}

	function startChat(token) {
		sessionToken = token;
		storageSet(SESSION_STORAGE_KEY, token);
		restoreTranscript();
		setView('chat');
		messageInput.focus();
	}

	/**
	 * Escolhe o passo de entrada consoante o progresso do utilizador:
	 * sessão válida → chat; termos aceites → email; senão → termos.
	 */
	function chooseEntryView() {
		if (hasSessionToken()) {
			restoreSession();
			return;
		}
		setView(termsAccepted() ? 'email' : 'terms');
	}

	function restoreSession() {
		var stored = storageGet(SESSION_STORAGE_KEY);
		if (!stored) {
			setView(termsAccepted() ? 'email' : 'terms');
			return;
		}
		// A sessão valida-se no primeiro envio: se expirou, o servidor responde
		// session_expired e o widget reencaminha para o passo email.
		sessionToken = stored;
		restoreTranscript();
		setView('chat');
	}

	if (termsAcceptBtn) {
		termsAcceptBtn.addEventListener('click', function () {
			storageSet(TERMS_STORAGE_KEY, '1');
			setView('email');
		});
	}

	emailForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var email = emailForm.email.value.trim();
		var phoneEl = emailForm.phone;
		var phone = phoneEl ? phoneEl.value.trim() : '';

		if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
			showError(emailError, I.email_hint || 'Email inválido.', true);
			return;
		}

		showError(emailError, '', false);
		requestCodeBtn.disabled = true;
		lastEmail = email;

		ajax('xkaichat_request_code', { email: email, phone: phone }, function (err) {
			requestCodeBtn.disabled = false;
			if (err) {
				showError(emailError, mapErr(err), true);
				return;
			}
			setView('code');
		});
	});

	codeForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var code = codeForm.code.value.replace(/[^\d]/g, '');
		if (code.length !== 6) {
			showError(codeError, I.code_hint || 'Digite os 6 dígitos.', true);
			return;
		}

		showError(codeError, '', false);
		verifyBtn.disabled = true;

		ajax('xkaichat_verify_code', { email: lastEmail, code: code }, function (err, data) {
			verifyBtn.disabled = false;
			if (err) {
				showError(codeError, mapErr(err), true);
				if (err.code === 'xkc_expired' || err.code === 'xkc_no_code') {
					setView('email');
				}
				return;
			}
			startChat(data.token);
		});
	});

	resendBtn.addEventListener('click', function () {
		resendBtn.disabled = true;
		ajax('xkaichat_request_code', { email: lastEmail, phone: '' }, function (err) {
			resendBtn.disabled = false;
			if (err) {
				showError(codeError, mapErr(err), true);
			}
		});
	});

	chatForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var text = messageInput.value.trim();
		if (!text || busy) {
			return;
		}

		addBubble(text, 'user');
		persistTranscript();
		messageInput.value = '';
		setThinking(true);

		ajax('xkaichat_send_message', { message: text }, function (err, data) {
			setThinking(false);
			if (err) {
				if (err.code === 'session_expired') {
					addBubble(I.expired || 'A sua sessão expirou.', 'agent');
					try {
						sessionStorage.removeItem(SESSION_STORAGE_KEY);
					} catch (e2) { /* ignore */ }
					sessionToken = null;
					clearTranscript();
					setView('email');
					return;
				}
				addBubble(I.unavailable || 'O assistente está temporariamente indisponível.', 'agent');
				persistTranscript();
				return;
			}
			addBubble(data.answer, 'agent');
			persistTranscript();
		});
	});

	endBtn.addEventListener('click', function () {
		if (!sessionToken) {
			return;
		}
		endBtn.disabled = true;
		ajax('xkaichat_end_session', {}, function () {
			endBtn.disabled = false;
			clearTranscript();
			try {
				sessionStorage.removeItem(SESSION_STORAGE_KEY);
			} catch (e) { /* ignore */ }
			sessionToken = null;
			setView('email');
		});
	});

	function mapErr(err) {
		if (!err) {
			return '';
		}
		switch (err.code) {
			case 'network':
			case 'http_500':
			case 'http_502':
			case 'http_503':
				return I.unavailable || 'Assistente indisponível.';
			case 'xkc_rate_limit':
			case 'xkc_cooldown':
				return I.retry || 'Demasiados pedidos. Aguarde um pouco e tente novamente.';
			case 'xkc_wrong':
				return I.code_err || 'Código incorreto. Tente novamente.';
			case 'xkc_attempts':
				return I.code_err || 'Demasiadas tentativas. Peça um novo código.';
			default:
				return I.error_generic || 'Não foi possível completar o pedido.';
		}
	}

	// inicia fechado por omissão; auto-abre só se configurado nas opções
	initTip();
	chooseEntryView();
	if (Number(cfg.auto_open) === 1) {
		openPanel();
	}
})();