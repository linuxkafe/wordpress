<?php
/**
 * Plugin Name:       xkinstagram
 * Plugin URI:        https://github.com/seyon/xkinstagram
 * Description:       Import Instagram posts and stories to WordPress
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Seyon
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xkinstagram
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('XKINSTAGRAM_VERSION', '0.2.0');
define('XKINSTAGRAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XKINSTAGRAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XKINSTAGRAM_PLUGIN_FILE', __FILE__);

require_once XKINSTAGRAM_PLUGIN_DIR . 'vendor/autoload.php';

use Xkinstagram\Admin\Settings;
use Xkinstagram\Api\InstagramApi;
use Xkinstagram\Auth\OAuth;
use Xkinstagram\Import\PostImporter;
use Xkinstagram\Import\PostType;
use Xkinstagram\Cron\AutoDelete;
use Xkinstagram\Cron\AutoImport;
use Xkinstagram\Utils\TokenEncryption;

final class Xkinstagram_Plugin {
    private static ?self $instance = null;
    private Settings $settings;

    private function __construct() {
        $this->settings = new Settings();
        add_action('init', [$this, 'init']);
        register_activation_hook(XKINSTAGRAM_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(XKINSTAGRAM_PLUGIN_FILE, [$this, 'deactivate']);
    }

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        PostType::register();
        $this->settings->init();
        
        if (is_admin()) {
            add_action('admin_menu', [$this->settings, 'add_menu_page']);
            add_action('admin_init', [$this->settings, 'register_settings']);
            add_action('wp_ajax_xkinstagram_test_connection', [$this, 'ajax_test_connection']);
            add_action('wp_ajax_xkinstagram_import_posts', [$this, 'ajax_import_posts']);
            add_action('wp_ajax_xkinstagram_get_import_log', [$this, 'ajax_get_import_log']);
            add_action('wp_ajax_xkinstagram_disconnect', [$this, 'ajax_disconnect']);
            add_action('admin_post_xkinstagram_oauth_callback', [$this, 'oauth_callback']);
        }

        // Cron
        add_action('init', [AutoDelete::class, 'schedule']);
        add_action(AutoDelete::CRON_HOOK, [AutoDelete::class, 'run']);
        add_action('init', [AutoImport::class, 'schedule']);
        add_action(AutoImport::CRON_HOOK, [AutoImport::class, 'run']);
    }

    public function activate(): void {
        $this->settings->create_default_options();
        PostType::flush_rewrite_rules();
        AutoDelete::schedule();
        AutoImport::schedule();
    }

    public function deactivate(): void {
        AutoDelete::unschedule();
        AutoImport::unschedule();
        flush_rewrite_rules();
    }

    public function ajax_test_connection(): void {
        check_ajax_referer('xkinstagram_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'xkinstagram')]);
        }

        $app_id = get_option('xkinstagram_app_id', '');
        $app_secret = get_option('xkinstagram_app_secret', '');
        $access_token = get_option('xkinstagram_access_token', '');

        if (empty($app_id) || empty($app_secret) || empty($access_token)) {
            wp_send_json_error(['message' => __('Missing credentials', 'xkinstagram')]);
        }

        try {
            $api = new InstagramApi($app_id, $app_secret, $access_token);
            $result = $api->test_connection();
            
            if ($result['success']) {
                wp_send_json_success(['message' => __('Connection successful!', 'xkinstagram')]);
            } else {
                wp_send_json_error(['message' => $result['error'] ?? __('Connection failed', 'xkinstagram')]);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_import_posts(): void {
        check_ajax_referer('xkinstagram_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'xkinstagram')]);
        }

        $app_id = get_option('xkinstagram_app_id', '');
        $app_secret = get_option('xkinstagram_app_secret', '');
        $access_token = get_option('xkinstagram_access_token', '');

        if (empty($app_id) || empty($app_secret) || empty($access_token)) {
            wp_send_json_error(['message' => __('Missing credentials', 'xkinstagram')]);
        }

        $limit = isset($_POST['limit']) ? max(1, min(200, (int) $_POST['limit'])) : 50;

        try {
            $api = new InstagramApi($app_id, $app_secret, $access_token);
            $importer = new PostImporter($api);
            $stats = $importer->import($limit);
            
            wp_send_json_success($stats);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_get_import_log(): void {
        check_ajax_referer('xkinstagram_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'xkinstagram')]);
        }

        $log = PostImporter::get_import_log();
        $deleteLog = AutoDelete::get_delete_log();
        
        wp_send_json_success([
            'import_log' => $log,
            'delete_log' => $deleteLog,
        ]);
    }

    public function oauth_callback(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'xkinstagram'));
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

        $settings_url = admin_url('admin.php?page=xkinstagram&tab=credentials');

        if (!empty($error)) {
            wp_safe_redirect(add_query_arg([
                'xkinstagram_error' => rawurlencode(__('Instagram denied access', 'xkinstagram') . ': ' . $error),
            ], $settings_url));
            exit;
        }

        $options = get_option('xkinstagram_options', []);
        $appId = (string) ($options['app_id'] ?? '');
        $appSecret = (string) ($options['app_secret'] ?? '');

        if (empty($appId) || empty($appSecret)) {
            wp_safe_redirect(add_query_arg([
                'xkinstagram_error' => rawurlencode(__('Missing app credentials', 'xkinstagram')),
            ], $settings_url));
            exit;
        }

        $oauth = new OAuth($appId, $appSecret, OAuth::redirect_uri());

        if (!$oauth->verify_state($state)) {
            wp_safe_redirect(add_query_arg([
                'xkinstagram_error' => rawurlencode(__('Invalid OAuth state', 'xkinstagram')),
            ], $settings_url));
            exit;
        }

        try {
            $short = $oauth->exchange_code($code);
            $api = new InstagramApi($appId, $appSecret, (string) $short['access_token']);
            $long = $api->exchange_long_lived_token((string) $short['access_token']);

            $token = (string) ($long['access_token'] ?? $short['access_token']);
            $expires = max(0, (int) ($long['expires_in'] ?? 5184000));

            $me = $api->test_connection();
            $profile = $me['data'] ?? [];
            $username = (string) ($profile['username'] ?? ($profile['name'] ?? ''));
            $userId = (string) ($profile['id'] ?? ($short['user_id'] ?? ''));

            $options['access_token'] = TokenEncryption::encrypt($token);
            $options['token_expires_at'] = time() + $expires;
            $options['connected'] = true;
            $options['instagram_user_id'] = $userId;
            $options['instagram_username'] = $username;
            update_option('xkinstagram_options', $options);

            AutoImport::schedule();

            wp_safe_redirect(add_query_arg([
                'connected' => '1',
            ], $settings_url));
        } catch (\Throwable $e) {
            wp_safe_redirect(add_query_arg([
                'xkinstagram_error' => rawurlencode($e->getMessage()),
            ], $settings_url));
        }
        exit;
    }

    public function ajax_disconnect(): void {
        check_ajax_referer('xkinstagram_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'xkinstagram')]);
        }

        $options = get_option('xkinstagram_options', []);
        $options['access_token'] = '';
        $options['connected'] = false;
        $options['instagram_user_id'] = '';
        $options['instagram_username'] = '';
        $options['token_expires_at'] = 0;
        update_option('xkinstagram_options', $options);
        AutoImport::unschedule();

        wp_send_json_success(['message' => __('Disconnected', 'xkinstagram')]);
    }
}

Xkinstagram_Plugin::instance();