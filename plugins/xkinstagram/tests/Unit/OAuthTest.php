<?php
/**
 * Unit tests for OAuth helper
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkinstagram\Auth\OAuth;

final class OAuthTest extends TestCase {
    private const REDIRECT_URI = 'http://example.com/wp-admin/admin-post.php?action=xkinstagram_oauth_callback';

    protected function setUp(): void {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_http_mock'] = [];
    }

    protected function tearDown(): void {
        unset($GLOBALS['wp_http_mock']);
    }

    private function make_oauth(): OAuth {
        return new OAuth('app_123', 'app_secret_456', self::REDIRECT_URI);
    }

    private function queue_response(array|\WP_Error $response): void {
        $GLOBALS['wp_http_mock'][] = $response;
    }

    public function test_redirect_uri_points_to_admin_post_callback(): void {
        $this->assertStringContainsString('admin-post.php', OAuth::redirect_uri());
        $this->assertStringContainsString('xkinstagram_oauth_callback', OAuth::redirect_uri());
    }

    public function test_authorize_url_contains_required_params(): void {
        $oauth = $this->make_oauth();
        $url = $oauth->authorize_url();

        $this->assertStringStartsWith('https://www.instagram.com/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=app_123', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=instagram_business_basic', $url);
        $this->assertStringContainsString('state=test-nonce', $url);
    }

    public function test_state_is_stored_and_verified(): void {
        $oauth = $this->make_oauth();
        $oauth->authorize_url();

        $this->assertTrue($oauth->verify_state('test-nonce'));
        $this->assertFalse($oauth->verify_state('forged-state'));
        $this->assertFalse($oauth->verify_state(''));
    }

    public function test_exchange_code_success(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'access_token' => 'short_lived_token',
                'user_id' => '17841400000000000',
                'permissions' => ['instagram_business_basic'],
            ]),
        ]);

        $result = $this->make_oauth()->exchange_code('auth_code_1');
        $this->assertEquals('short_lived_token', $result['access_token']);
        $this->assertEquals('17841400000000000', $result['user_id']);
    }

    public function test_exchange_code_failure_without_token(): void {
        $this->queue_response([
            'code' => 200,
            'body' => json_encode([
                'error_message' => 'Invalid code',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('code exchange failed');
        $this->make_oauth()->exchange_code('bad_code');
    }

    public function test_exchange_code_http_failure(): void {
        $this->queue_response(new \WP_Error('http_error', 'Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth token request failed');
        $this->make_oauth()->exchange_code('code');
    }
}