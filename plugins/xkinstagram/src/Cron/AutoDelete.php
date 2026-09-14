<?php
/**
 * Auto-Delete Cron - Removes old imported posts and their media
 */

declare(strict_types=1);

namespace Xkinstagram\Cron;

use Xkinstagram\Import\PostType;

final class AutoDelete {
    public const CRON_HOOK = 'xkinstagram_daily_cleanup';
    public const OPTION_ENABLED = 'xkinstagram_auto_delete_enabled';
    public const OPTION_DAYS = 'xkinstagram_auto_delete_days';

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public static function run(): void {
        $enabled = get_option(self::OPTION_ENABLED, true);
        if (!$enabled) {
            return;
        }

        $days = max(1, (int) get_option(self::OPTION_DAYS, 30));
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $posts = get_posts([
            'post_type' => PostType::POST_TYPE,
            'post_status' => 'any',
            'date_query' => [
                [
                    'column' => 'post_date',
                    'before' => $cutoff,
                    'inclusive' => true,
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        if (empty($posts)) {
            return;
        }

        foreach ($posts as $postId) {
            // Force delete (bypass trash) to also delete attachments
            wp_delete_post($postId, true);
        }

        // Log cleanup
        $log = get_option('xkinstagram_delete_log', []);
        array_unshift($log, [
            'time' => current_time('mysql', true),
            'deleted_count' => count($posts),
            'cutoff_date' => $cutoff,
        ]);
        $log = array_slice($log, 0, 50);
        update_option('xkinstagram_delete_log', $log);
    }

    public static function get_delete_log(): array {
        return get_option('xkinstagram_delete_log', []);
    }
}