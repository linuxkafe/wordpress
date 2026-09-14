<?php
/**
 * Post Importer - Orchestrates fetching Instagram posts and creating WP posts
 */

declare(strict_types=1);

namespace Xkinstagram\Import;

use Xkinstagram\Api\InstagramApi;

final class PostImporter {
    private InstagramApi $api;
    private int $batchSize;
    private array $stats;

    public function __construct(InstagramApi $api, int $batchSize = 25) {
        $this->api = $api;
        $this->batchSize = min($batchSize, 100);
        $this->reset_stats();
    }

    public function import(int $limit = 50): array {
        $this->reset_stats();
        $imported = 0;
        $after = '';

        while ($imported < $limit) {
            $remaining = $limit - $imported;
            $currentBatch = min($this->batchSize, $remaining);

            try {
                $response = $this->api->get_user_media($currentBatch, $after);
            } catch (\Throwable $e) {
                $this->stats['errors'][] = "API error: {$e->getMessage()}";
                break;
            }

            $mediaItems = $response['data'] ?? [];
            if (empty($mediaItems)) {
                break;
            }

            foreach ($mediaItems as $media) {
                if ($imported >= $limit) {
                    break;
                }

                $result = $this->import_single_media($media);
                if ($result === 'imported') {
                    $this->stats['imported']++;
                    $imported++;
                } elseif ($result === 'skipped') {
                    $this->stats['skipped']++;
                } else {
                    $this->stats['errors'][] = $result;
                }
            }

            // Pagination
            $after = $response['paging']['cursors']['after'] ?? '';
            if (empty($after)) {
                break;
            }
        }

        $this->log_import();
        return $this->stats;
    }

    private function import_single_media(array $media): string {
        $instagramId = $media['id'] ?? '';
        if (empty($instagramId)) {
            return 'Missing Instagram ID';
        }

        // Check duplicate
        $existing = get_posts([
            'post_type' => PostType::POST_TYPE,
            'meta_key' => PostType::META_INSTAGRAM_ID,
            'meta_value' => $instagramId,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        if (!empty($existing)) {
            return 'skipped';
        }

        // Get full media details for carousel children
        try {
            $mediaDetails = $this->api->get_media_details($instagramId);
        } catch (\Throwable $e) {
            return "Media details failed: {$e->getMessage()}";
        }

        // Prepare post data
        $caption = $mediaDetails['caption'] ?? '';
        $timestamp = $mediaDetails['timestamp'] ?? '';
        $permalink = $mediaDetails['permalink'] ?? '';
        $mediaType = $mediaDetails['media_type'] ?? 'IMAGE';
        $username = $mediaDetails['username'] ?? 'unknown';

        $postDate = $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : current_time('mysql');
        $postTitle = $caption ? wp_trim_words(strip_tags($caption), 10, '') : "Instagram Post by {$username} on " . date_i18n('Y-m-d', strtotime($postDate));

        // Create post
        $postId = wp_insert_post([
            'post_type' => PostType::POST_TYPE,
            'post_title' => $postTitle,
            'post_content' => $caption,
            'post_status' => 'publish',
            'post_date' => $postDate,
            'post_date_gmt' => get_gmt_from_date($postDate),
        ], true);

        if (is_wp_error($postId)) {
            return "Post creation failed: {$postId->get_error_message()}";
        }

        // Attach media
        $mediaResult = MediaHandler::process_media($mediaDetails, $postId);
        
        if ($mediaResult['featured_media_id']) {
            set_post_thumbnail($postId, $mediaResult['featured_media_id']);
        }

        // Save meta
        update_post_meta($postId, PostType::META_INSTAGRAM_ID, $instagramId);
        update_post_meta($postId, PostType::META_PERMALINK, $permalink);
        update_post_meta($postId, PostType::META_MEDIA_TYPE, $mediaType);
        update_post_meta($postId, PostType::META_IMPORTED_AT, current_time('mysql', true));
        
        if (!empty($mediaResult['carousel_media_ids'])) {
            update_post_meta($postId, PostType::META_CAROUSEL_MEDIA, $mediaResult['carousel_media_ids']);
        }

        return 'imported';
    }

    private function reset_stats(): void {
        $this->stats = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    private function log_import(): void {
        $log = get_option('xkinstagram_import_log', []);
        $entry = [
            'time' => current_time('mysql', true),
            'imported' => $this->stats['imported'],
            'skipped' => $this->stats['skipped'],
            'errors' => $this->stats['errors'],
        ];
        array_unshift($log, $entry);
        $log = array_slice($log, 0, 50); // Keep last 50
        update_option('xkinstagram_import_log', $log);
    }

    public static function get_import_log(): array {
        return get_option('xkinstagram_import_log', []);
    }
}