<?php
/**
 * Instagram Graph API Client
 */

declare(strict_types=1);

namespace Xkinstagram\Api;

class InstagramApi {
    private const API_VERSION = 'v25.0';
    private const BASE_URL = 'https://graph.instagram.com/';

    private string $appId;
    private string $appSecret;
    private string $accessToken;

    public function __construct(string $appId, string $appSecret, string $accessToken) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->accessToken = $accessToken;
    }

    public function test_connection(): array {
        try {
            $response = $this->request('GET', "me?fields=id,username,account_type", [
                'access_token' => $this->accessToken,
            ]);
            
            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function get_user_media(int $limit = 25, string $after = ''): array {
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username',
            'limit' => min($limit, 100),
        ];
        
        if ($after !== '') {
            $params['after'] = $after;
        }

        return $this->request('GET', "me/media", $params);
    }

    public function get_media_details(string $mediaId): array {
        return $this->request('GET', $mediaId, [
            'access_token' => $this->accessToken,
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username,children{id,media_type,media_url,thumbnail_url}',
        ]);
    }

    public function exchange_long_lived_token(string $shortLivedToken): array {
        $params = [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->appSecret,
            'access_token' => $shortLivedToken,
        ];

        $url = self::BASE_URL . 'access_token?' . http_build_query($params);
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("Token exchange failed: {$response->get_error_message()}");
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status >= 400) {
            throw new \RuntimeException("Token exchange failed: " . wp_remote_retrieve_body($response));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function refresh_token(string $longLivedToken): array {
        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $longLivedToken,
        ];

        $url = self::BASE_URL . 'refresh_access_token?' . http_build_query($params);
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("Token refresh failed: {$response->get_error_message()}");
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status >= 400) {
            throw new \RuntimeException("Token refresh failed: " . wp_remote_retrieve_body($response));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function request(string $method, string $endpoint, array $params = []): array {
        $url = self::BASE_URL . self::API_VERSION . '/' . ltrim($endpoint, '/');
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . http_build_query($params);

        $response = wp_remote_request($url, [
            'method' => $method,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("Network error: {$response->get_error_message()}");
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            throw new \RuntimeException("API Error: {$data['error']['message']} (Code: {$data['error']['code']})");
        }

        return $data;
    }
}