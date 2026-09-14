<?php
/**
 * Instagram Business Login (OAuth) helper
 * Builds the authorize URL, exchanges the one-time code for a token, and
 * guards the callback against CSRF via a stored `state` value.
 */

declare(strict_types=1);

namespace Xkinstagram\Auth;

final class OAuth {
    private const AUTH_URL = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const SCOPE = 'instagram_business_basic';
    private const STATE_ACTION = 'xkinstagram_oauth_state';
    private const STATE_OPTION = 'xkinstagram_oauth_state';

    private string $appId;
    private string $appSecret;
    private string $redirectUri;

    public function __construct(string $appId, string $appSecret, string $redirectUri) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
    }

    public static function redirect_uri(): string {
        return admin_url('admin-post.php?action=xkinstagram_oauth_callback');
    }

    public function authorize_url(): string {
        $state = wp_create_nonce(self::STATE_ACTION);
        update_option(self::STATE_OPTION, $state, false);

        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params, '', '&');
    }

    public function verify_state(string $state): bool {
        $expected = get_option(self::STATE_OPTION, '');
        return $expected !== '' && $state !== '' && hash_equals($expected, $state);
    }

    public function exchange_code(string $code): array {
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body' => [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
                'code' => $code,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("OAuth token request failed: {$response->get_error_message()}");
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['access_token'])) {
            $message = $data['error_message'] ?? 'No access token in response';
            throw new \RuntimeException("OAuth code exchange failed: {$message}");
        }

        return $data;
    }
}