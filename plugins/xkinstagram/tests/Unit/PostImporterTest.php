<?php
/**
 * Unit tests for PostImporter
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkinstagram\Api\InstagramApi;
use Xkinstagram\Import\PostImporter;

final class PostImporterTest extends TestCase {
    private PostImporter $importer;
    private InstagramApi $mockApi;

    protected function setUp(): void {
        $this->mockApi = $this->createMock(InstagramApi::class);
        $this->importer = new PostImporter($this->mockApi, 10);
    }

    public function test_import_respects_limit(): void {
        $this->mockApi->method('get_user_media')
            ->willReturn([
                'data' => [
                    ['id' => 'media_1', 'caption' => 'Post 1', 'media_type' => 'IMAGE', 'media_url' => 'https://example.com/1.jpg', 'timestamp' => '2026-01-01T12:00:00+0000', 'username' => 'test'],
                    ['id' => 'media_2', 'caption' => 'Post 2', 'media_type' => 'IMAGE', 'media_url' => 'https://example.com/2.jpg', 'timestamp' => '2026-01-02T12:00:00+0000', 'username' => 'test'],
                ],
                'paging' => ['cursors' => ['after' => 'next']],
            ]);

        $this->mockApi->method('get_media_details')
            ->willReturn([
                'id' => 'media_1',
                'caption' => 'Post 1',
                'media_type' => 'IMAGE',
                'media_url' => 'https://example.com/1.jpg',
                'permalink' => 'https://instagram.com/p/1',
                'timestamp' => '2026-01-01T12:00:00+0000',
                'username' => 'test',
            ]);

        // Mock WP functions
        $this->mock_wp_functions();

        $stats = $this->importer->import(1);
        
        $this->assertEquals(1, $stats['imported']);
    }

    public function test_import_stops_on_empty_response(): void {
        $this->mockApi->method('get_user_media')
            ->willReturn(['data' => [], 'paging' => []]);

        $this->mock_wp_functions();

        $stats = $this->importer->import(50);
        
        $this->assertEquals(0, $stats['imported']);
    }

    private function mock_wp_functions(): void {
        // These are mocked in bootstrap.php
    }
}