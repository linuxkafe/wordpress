<?php
/**
 * Media Handler - Downloads and attaches media to WordPress posts
 */

declare(strict_types=1);

namespace Xkinstagram\Import;

final class MediaHandler {
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const TIMEOUT = 60;

    /**
     * Process media from Instagram API response and attach to post
     *
     * @param array $mediaData Instagram media object
     * @param int $postId WordPress post ID
     * @return array ['featured_media_id' => int, 'carousel_media_ids' => int[]]
     */
    public static function process_media(array $mediaData, int $postId): array {
        $mediaType = $mediaData['media_type'] ?? 'IMAGE';
        $carouselMediaIds = [];
        $featuredMediaId = 0;

        // Handle carousel albums
        if ($mediaType === 'CAROUSEL_ALBUM' && isset($mediaData['children']['data'])) {
            foreach ($mediaData['children']['data'] as $index => $child) {
                $attachmentId = self::attach_media($child, $postId, $index);
                if ($attachmentId) {
                    $carouselMediaIds[] = $attachmentId;
                    if ($featuredMediaId === 0 && $child['media_type'] === 'IMAGE') {
                        $featuredMediaId = $attachmentId;
                    }
                }
            }
        } else {
            // Single media (IMAGE, VIDEO, REELS)
            $attachmentId = self::attach_media($mediaData, $postId, 0);
            if ($attachmentId) {
                $featuredMediaId = $attachmentId;
            }
        }

        // Fallback: if no featured image but have carousel images, use first
        if ($featuredMediaId === 0 && !empty($carouselMediaIds)) {
            $featuredMediaId = $carouselMediaIds[0];
        }

        return [
            'featured_media_id' => $featuredMediaId,
            'carousel_media_ids' => $carouselMediaIds,
        ];
    }

    /**
     * Attach a single media item to a post
     */
    private static function attach_media(array $media, int $postId, int $index): ?int {
        $mediaType = $media['media_type'] ?? 'IMAGE';
        $mediaUrl = $media['media_url'] ?? ($media['thumbnail_url'] ?? '');

        if (empty($mediaUrl)) {
            return null;
        }

        try {
            if ($mediaType === 'VIDEO' || $mediaType === 'REELS') {
                return self::attach_video($mediaUrl, $postId, $media, $index);
            } else {
                return self::attach_image($mediaUrl, $postId, $media, $index);
            }
        } catch (\Throwable $e) {
            error_log("xkinstagram: Failed to attach media {$mediaUrl}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Attach image using media_sideload_image
     */
    private static function attach_image(string $url, int $postId, array $media, int $index): ?int {
        // media_sideload_image requires allow_url_fopen or cURL
        $attachmentId = media_sideload_image($url, $postId, null, 'id');

        if (is_wp_error($attachmentId)) {
            throw new \RuntimeException("Image sideload failed: {$attachmentId->get_error_message()}");
        }

        // Set alt text from caption if available
        if (!empty($media['caption'])) {
            $alt = wp_trim_words(strip_tags($media['caption']), 20, '');
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        }

        // Store Instagram media ID in attachment meta
        if (!empty($media['id'])) {
            update_post_meta($attachmentId, '_xkinstagram_media_id', $media['id']);
        }

        return (int) $attachmentId;
    }

    /**
     * Attach video using wp_upload_bits + wp_insert_attachment
     */
    private static function attach_video(string $url, int $postId, array $media, int $index): ?int {
        // Download video to temp file
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'stream' => true,
            'headers' => ['User-Agent' => 'xkinstagram/0.1.0'],
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("Video download failed: {$response->get_error_message()}");
        }

        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        // Check file size
        $contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;
        if ($contentLength > self::MAX_FILE_SIZE) {
            throw new \RuntimeException("Video too large: {$contentLength} bytes");
        }

        // Generate filename
        $extension = self::guess_extension($url, $headers['content-type'] ?? '');
        $filename = "instagram-video-{$postId}-{$index}.{$extension}";

        // Upload to WP
        $upload = wp_upload_bits($filename, null, $body);
        if ($upload['error']) {
            throw new \RuntimeException("Upload failed: {$upload['error']}");
        }

        // Insert attachment
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $postId,
        ];

        $attachmentId = wp_insert_attachment($attachment, $upload['file'], $postId);
        if (is_wp_error($attachmentId)) {
            throw new \RuntimeException("Attachment insert failed: {$attachmentId->get_error_message()}");
        }

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        wp_update_attachment_metadata($attachmentId, $attachData);

        // Store Instagram media ID
        if (!empty($media['id'])) {
            update_post_meta($attachmentId, '_xkinstagram_media_id', $media['id']);
        }

        return $attachmentId;
    }

    /**
     * Guess file extension from URL or content-type
     */
    private static function guess_extension(string $url, string $contentType): string {
        // Try URL first
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext && in_array(strtolower($ext), ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
                return strtolower($ext);
            }
        }

        // Fallback to content-type
        return match (true) {
            str_contains($contentType, 'mp4') => 'mp4',
            str_contains($contentType, 'quicktime') => 'mov',
            str_contains($contentType, 'webm') => 'webm',
            default => 'mp4',
        };
    }
}