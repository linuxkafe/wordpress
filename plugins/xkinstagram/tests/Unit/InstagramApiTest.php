<?php
/**
 * Unit tests for InstagramApi
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Xkinstagram\Api\InstagramApi;

final class InstagramApiTest extends TestCase {
    private InstagramApi $api;
    private MockHandler $mockHandler;

    protected function setUp(): void {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        
        $client = new Client(['handler' => $handlerStack]);
        
        $reflection = new \ReflectionClass(InstagramApi::class);
        $this->api = $reflection->newInstanceWithoutConstructor();
        
        $clientProp = $reflection->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($this->api, $client);
        
        $appIdProp = $reflection->getProperty('appId');
        $appIdProp->setAccessible(true);
        $appIdProp->setValue($this->api, 'test_app_id');
        
        $appSecretProp = $reflection->getProperty('appSecret');
        $appSecretProp->setAccessible(true);
        $appSecretProp->setValue($this->api, 'test_app_secret');
        
        $accessTokenProp = $reflection->getProperty('accessToken');
        $accessTokenProp->setAccessible(true);
        $accessTokenProp->setValue($this->api, 'test_access_token');
    }

    public function test_test_connection_success(): void {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => '17841405822304914',
            'username' => 'testuser',
            'account_type' => 'BUSINESS',
        ])));

        $result = $this->api->test_connection();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('17841405822304914', $result['data']['id']);
        $this->assertEquals('testuser', $result['data']['username']);
    }

    public function test_test_connection_failure(): void {
        $this->mockHandler->append(new Response(400, [], json_encode([
            'error' => [
                'message' => 'Invalid access token',
                'code' => 190,
            ],
        ])));

        $result = $this->api->test_connection();
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid access token', $result['error']);
    }

    public function test_get_user_media_success(): void {
        $this->mockHandler->append(new Response(200, [], json_encode([
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
        ])));

        $result = $this->api->get_user_media(10);
        
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('media_1', $result['data'][0]['id']);
    }

    public function test_get_media_details_with_children(): void {
        $this->mockHandler->append(new Response(200, [], json_encode([
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
        ])));

        $result = $this->api->get_media_details('media_1');
        
        $this->assertEquals('CAROUSEL_ALBUM', $result['media_type']);
        $this->assertArrayHasKey('children', $result);
        $this->assertCount(2, $result['children']['data']);
    }

    public function test_exchange_long_lived_token(): void {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'access_token' => 'long_lived_token_abc',
            'token_type' => 'bearer',
            'expires_in' => 5184000,
        ])));

        $reflection = new \ReflectionClass($this->api);
        $method = $reflection->getMethod('exchange_long_lived_token');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->api, 'short_lived_token');
        
        $this->assertEquals('long_lived_token_abc', $result['access_token']);
        $this->assertEquals(5184000, $result['expires_in']);
    }

    public function test_refresh_token(): void {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'access_token' => 'refreshed_token_xyz',
            'token_type' => 'bearer',
            'expires_in' => 5184000,
        ])));

        $result = $this->api->refresh_token('expiring_token');
        
        $this->assertEquals('refreshed_token_xyz', $result['access_token']);
        $this->assertEquals(5184000, $result['expires_in']);
    }

    public function test_refresh_token_failure(): void {
        $this->mockHandler->append(new Response(400, [], json_encode([
            'error' => [
                'message' => 'Invalid token',
                'code' => 190,
            ],
        ])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token refresh failed');
        
        $this->api->refresh_token('expired_token');
    }

    public function test_request_handles_network_error(): void {
        $this->mockHandler->append(new \GuzzleHttp\Exception\ConnectException(
            'Connection refused',
            new \GuzzleHttp\Psr7\Request('GET', 'https://graph.facebook.com/v18.0/me')
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');
        
        $reflection = new \ReflectionClass($this->api);
        $method = $reflection->getMethod('request');
        $method->setAccessible(true);
        $method->invoke($this->api, 'GET', 'me');
    }
}