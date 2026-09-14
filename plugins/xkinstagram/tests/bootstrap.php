<?php
/**
 * Test bootstrap
 */

declare(strict_types=1);

// Define WordPress constants for testing
define('ABSPATH', __DIR__ . '/../');
define('WP_CONTENT_DIR', __DIR__ . '/../wp-content/');
define('WP_PLUGIN_DIR', __DIR__ . '/../wp-content/plugins/');
define('SECURE_AUTH_SALT', 'test-salt-for-unit-tests-only-do-not-use-in-production-32chars!!');

// Mock WordPress functions
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($text) { return $text; }
}
if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) { return $selected == $current ? ' selected="selected"' : ''; }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return $value; }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) { exit; }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') { return 'http://example.com/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) { return true; }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args = [], $url = '') { return $url . '&' . http_build_query($args); }
}
if (!function_exists('wp_get_schedule')) {
    function wp_get_schedule($hook, $args = []) { return 'daily'; }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim($str); }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename) { return $filename; }
}
if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes') {
        if (!isset($GLOBALS['wp_options'][$option])) {
            $GLOBALS['wp_options'][$option] = $value;
        }
        return true;
    }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['wp_options'][$option] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        $GLOBALS['wp_options'][$option] = $value;
        return true;
    }
}
if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error') {}
}
if (!function_exists('settings_errors')) {
    function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) {}
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'test-nonce'; }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) { return 1; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) { return true; }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $options = 0) { exit; }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $options = 0) { exit; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {}
}
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true) {}
}
if (!function_exists('is_admin')) {
    function is_admin() { return true; }
}
if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '') { return 'xkinstagram'; }
}
if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = []) {}
}
if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page) {}
}
if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = []) {}
}
if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page) {}
}
if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {}
}
if (!function_exists('submit_button')) {
    function submit_button($text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attrs = null) {}
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/xkinstagram/'; }
}
if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) { return $text; }
}
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) { return '2026-01-01 12:00:00'; }
}
if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false, $gmt = false) { return date($format, $timestamp ?: time()); }
}
if (!function_exists('get_gmt_from_date')) {
    function get_gmt_from_date($date, $format = 'Y-m-d H:i:s') { return $date; }
}
if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false) { return 123; }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof WP_Error; }
}
if (!function_exists('wp_http_mock')) {
    function wp_http_mock(): array { return $GLOBALS['wp_http_mock'] ?? []; }
}
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        $queue = wp_http_mock();
        if ($queue) {
            return array_shift($GLOBALS['wp_http_mock']);
        }
        return ['body' => '', 'headers' => []];
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $queue = wp_http_mock();
        if ($queue) {
            return array_shift($GLOBALS['wp_http_mock']);
        }
        return ['body' => '', 'headers' => []];
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $queue = wp_http_mock();
        if ($queue) {
            return array_shift($GLOBALS['wp_http_mock']);
        }
        return ['body' => '', 'headers' => []];
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) { return is_array($response) ? ($response['body'] ?? '') : ''; }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) { return is_array($response) ? ($response['code'] ?? 200) : 500; }
}
if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response) { return is_array($response) ? ($response['headers'] ?? []) : []; }
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        protected array $errors = [];
        public function __construct($code = '', $message = '') {
            if ($code !== '') {
                $this->errors[$code] = [$message];
            }
        }
        public function get_error_message($code = '') {
            if (empty($this->errors)) {
                return '';
            }
            $messages = reset($this->errors);
            return is_array($messages) ? reset($messages) : '';
        }
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) { return true; }
}
if (!function_exists('get_posts')) {
    function get_posts($args) { return []; }
}
if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post_id, $thumbnail_id) { return true; }
}
if (!function_exists('wp_upload_bits')) {
    function wp_upload_bits($name, $deprecated, $bits, $time = null) { return ['file' => '/tmp/test', 'type' => 'video/mp4', 'error' => false]; }
}
if (!function_exists('wp_insert_attachment')) {
    function wp_insert_attachment($attachment, $filename, $parent_post_id) { return 456; }
}
if (!function_exists('wp_generate_attachment_metadata')) {
    function wp_generate_attachment_metadata($attachment_id, $file) { return []; }
}
if (!function_exists('wp_update_attachment_metadata')) {
    function wp_update_attachment_metadata($attachment_id, $data) { return true; }
}
if (!function_exists('media_sideload_image')) {
    function media_sideload_image($file, $post_id = 0, $desc = null, $return = 'html') { return 789; }
}
if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) { return $checked == $current ? ' checked="checked"' : ''; }
}
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) { return true; }
}
if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook, $args = []) { return true; }
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) { return false; }
}
if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postid, $force_delete = false) { return true; }
}
if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = []) { return $post_type; }
}
if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() { return 'xkinstagram Settings'; }
}