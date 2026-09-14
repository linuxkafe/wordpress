<?php
/**
 * Unit tests for InstagramApi
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkinstagram\Api\InstagramApi;

final class InstagramApiTest extends TestCase {
    private InstagramApi $api;

    protected function setUp(): void {
        $GLOBALS['wp_http_mock'] = [];
        $this->api = new InstagramApi('test_app_id', 'test_app_secret', 'test_access_token');
    }

    protected function tearDown(): void {
        unset($GLOBALS['wp_http_mock']);
    }

    private function queue_response(array|\WP_Error $response): void {
        $GLOBALS['wp_http_mock'][] = $response;
    }

    public function test_test_connection_success(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'id' => '17841405822304914',
                'username' => 'testuser',
                'account_type' => 'BUSINESS',
            ]),
        ]);

        $result = $this->api->test_connection();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('17841405822304914', $result['data']['id']);
        $this->assertEquals('testuser', $result['data']['username']);
    }

    public function test_test_connection_failure(): void {
        $this->queue_response([
            'code' => 400,
            'body' => json_encode([
                'error' => [
                    'message' => 'Invalid access token',
                    'code' => 190,
                ],
            ]),
        ]);

        $result = $this->api->test_connection();
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid access token', $result['error']);
    }

    public function test_get_user_media_success(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'data' => [
                    [
                        'id' => 'media_1',
                        'caption' => 'Test post',
                        'media_type' => 'IMAGE',
                        'media_url' => 'https://example.com/image.jpg',
                        'permalink' => 'https://instagram.com/p/abc123',
                        'timestamp' => '2026-01-01T12:00:00+0000',
                        'username' => 'testuser',
                    ],
                ],
                'paging' => [
                    'cursors' => [
                        'after' => 'next_cursor',
                    ],
                ],
            ]),
        ]);

        $result = $this->api->get_user_media(10);
        
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('media_1', $result['data'][0]['id']);
    }

    public function test_get_media_details_with_children(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'id' => 'media_1',
                'caption' => 'Carousel post',
                'media_type' => 'CAROUSEL_ALBUM',
                'media_url' => 'https://example.com/cover.jpg',
                'children' => [
                    'data' => [
                        ['id' => 'child_1', 'media_type' => 'IMAGE', 'media_url' => 'https://example.com/1.jpg'],
                        ['id' => 'child_2', 'media_type' => 'VIDEO', 'media_url' => 'https://example.com/2.mp4'],
                    ],
                ],
            ]),
        ]);

        $result = $this->api->get_media_details('media_1');
        
        $this->assertEquals('CAROUSEL_ALBUM', $result['media_type']);
        $this->assertArrayHasKey('children', $result);
        $this->assertCount(2, $result['children']['data']);
    }

    public function test_exchange_long_lived_token(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'access_token' => 'long_lived_token_abc',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
        ]);

        $result = $this->api->exchange_long_lived_token('short_lived_token');
        
        $this->assertEquals('long_lived_token_abc', $result['access_token']);
        $this->assertEquals(5184000, $result['expires_in']);
    }

    public function test_refresh_token(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'access_token' => 'refreshed_token_xyz',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
        ]);

        $result = $this->api->refresh_token('expiring_token');
        
        $this->assertEquals('refreshed_token_xyz', $result['access_token']);
        $this->assertEquals(5184000, $result['expires_in']);
    }

    public function test_refresh_token_failure(): void {
        $this->queue_response([
            'code' => 400,
            'body' => json_encode([
                'error' => [
                    'message' => 'Invalid token',
                    'code' => 190,
                ],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token refresh failed');
        
        $this->api->refresh_token('expired_token');
    }

    public function test_request_handles_network_error(): void {
        $this->queue_response(new \WP_Error('http_error', 'Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');
        
        $reflection = new \ReflectionClass($this->api);
        $method = $reflection->getMethod('request');
        $method->setAccessible(true);
        $method->invoke($this->api, 'GET', 'me', ['access_token' => 'test']);
    }
}