<?php
/**
 * Instagram Graph API Client
 */

declare(strict_types=1);

namespace Xkinstagram\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class InstagramApi {
    private const API_VERSION = 'v25.0';
    private const BASE_URL = 'https://graph.instagram.com/';

    private Client $client;
    private string $appId;
    private string $appSecret;
    private string $accessToken;

    public function __construct(string $appId, string $appSecret, string $accessToken) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->accessToken = $accessToken;
        
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'xkinstagram/0.1.0',
            ],
        ]);
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

        try {
            $response = $this->client->request('GET', 'access_token', [
                'query' => $params,
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $body = $e->getResponse()?->getBody()->getContents() ?? '';
            throw new \RuntimeException("Token exchange failed: {$body}");
        }
    }

    public function refresh_token(string $longLivedToken): array {
        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $longLivedToken,
        ];

        try {
            $response = $this->client->request('GET', 'refresh_access_token', [
                'query' => $params,
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $body = $e->getResponse()?->getBody()->getContents() ?? '';
            throw new \RuntimeException("Token refresh failed: {$body}");
        }
    }

    private function request(string $method, string $endpoint, array $params = []): array {
        $url = self::API_VERSION . '/' . ltrim($endpoint, '/');
        
        try {
            $response = $this->client->request($method, $url, [
                'query' => $params,
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['error'])) {
                throw new \RuntimeException("API Error: {$data['error']['message']} (Code: {$data['error']['code']})");
            }
            
            return $data;
        } catch (RequestException $e) {
            $body = $e->getResponse()?->getBody()->getContents() ?? '';
            $error = json_decode($body, true);
            $message = $error['error']['message'] ?? $e->getMessage();
            throw new \RuntimeException("Request failed: {$message}");
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Network error: {$e->getMessage()}");
        }
    }
}