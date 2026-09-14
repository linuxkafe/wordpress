<?php
/**
 * Instagram Business Login (OAuth) helper
 * Builds the authorize URL, exchanges the one-time code for a token, and
 * guards the callback against CSRF via a stored `state` value.
 */

declare(strict_types=1);

namespace Xkinstagram\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class OAuth {
    private const AUTH_URL = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const SCOPE = 'instagram_business_basic';
    private const STATE_ACTION = 'xkinstagram_oauth_state';
    private const STATE_OPTION = 'xkinstagram_oauth_state';

    private string $appId;
    private string $appSecret;
    private string $redirectUri;
    private Client $client;

    public function __construct(string $appId, string $appSecret, string $redirectUri, ?Client $client = null) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'xkinstagram/0.3.0',
            ],
        ]);
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
        try {
            $response = $this->client->request('POST', self::TOKEN_URL, [
                'form_params' => [
                    'client_id' => $this->appId,
                    'client_secret' => $this->appSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("OAuth token request failed: {$e->getMessage()}");
        }

        if (!is_array($data) || empty($data['access_token'])) {
            $message = $data['error_message'] ?? 'No access token in response';
            throw new \RuntimeException("OAuth code exchange failed: {$message}");
        }

        return $data;
    }
}