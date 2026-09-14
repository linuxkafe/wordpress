<?php
/**
 * Auto-Import Cron - Runs PostImporter on a configurable schedule
 * Reuses PostImporter (idempotent by Instagram post ID), so each run imports
 * the newest posts not yet present, converging towards a full mirror.
 */

declare(strict_types=1);

namespace Xkinstagram\Cron;

use Xkinstagram\Api\InstagramApi;
use Xkinstagram\Import\PostImporter;
use Xkinstagram\Utils\TokenEncryption;

final class AutoImport {
    public const CRON_HOOK = 'xkinstagram_auto_import';
    public const OPTION_LAST_RUN = 'xkinstagram_auto_import_last';
    public const FREQUENCIES = ['hourly', 'twicedaily', 'daily'];
    private const REFRESH_WINDOW_SECONDS = 604800;

    public static function enabled(): bool {
        $options = get_option('xkinstagram_options', []);
        return !empty($options['auto_import_enabled']);
    }

    public static function frequency(): string {
        $options = get_option('xkinstagram_options', []);
        $frequency = $options['auto_import_frequency'] ?? 'daily';
        return in_array($frequency, self::FREQUENCIES, true) ? $frequency : 'daily';
    }

    public static function schedule(): void {
        if (!self::enabled()) {
            self::unschedule();
            return;
        }

        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next && wp_get_schedule($next) === self::frequency()) {
            return;
        }

        if ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
        }
        wp_schedule_event(time(), self::frequency(), self::CRON_HOOK);
    }

    public static function unschedule(): void {
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
        }
    }

    public static function run(): void {
        if (!self::enabled()) {
            return;
        }

        $options = get_option('xkinstagram_options', []);

        $appId = (string) ($options['app_id'] ?? '');
        $appSecret = (string) ($options['app_secret'] ?? '');
        $encryptedToken = (string) ($options['access_token'] ?? '');

        if (empty($appId) || empty($appSecret) || empty($encryptedToken)) {
            return;
        }

        try {
            $token = TokenEncryption::decrypt($encryptedToken);
        } catch (\Throwable $e) {
            return;
        }

        if (empty($token)) {
            return;
        }

        try {
            $api = new InstagramApi($appId, $appSecret, $token);
            $token = self::maybe_refresh_token($api, $options, $token);

            $batch = max(1, min(100, (int) ($options['auto_import_batch'] ?? 25)));
            $importer = new PostImporter($api, $batch);
            $stats = $importer->import($batch);

            update_option(self::OPTION_LAST_RUN, [
                'time' => current_time('mysql', true),
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            update_option(self::OPTION_LAST_RUN, [
                'time' => current_time('mysql', true),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function maybe_refresh_token(InstagramApi $api, array $options, string $token): string {
        $expiresAt = (int) ($options['token_expires_at'] ?? 0);
        if ($expiresAt <= 0 || time() <= $expiresAt - self::REFRESH_WINDOW_SECONDS) {
            return $token;
        }

        $result = $api->refresh_token($token);
        $newToken = $result['access_token'] ?? '';
        if (empty($newToken)) {
            return $token;
        }

        $options['access_token'] = TokenEncryption::encrypt($newToken);
        $options['token_expires_at'] = time() + (int) ($result['expires_in'] ?? 5184000);
        update_option('xkinstagram_options', $options);

        return $newToken;
    }

    public static function get_last_run(): array {
        return get_option(self::OPTION_LAST_RUN, []);
    }
}