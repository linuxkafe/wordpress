<?php
/**
 * Admin Settings Page
 */

declare(strict_types=1);

namespace Xkinstagram\Admin;

use Xkinstagram\Utils\TokenEncryption;
use Xkinstagram\Import\PostImporter;
use Xkinstagram\Cron\AutoDelete;
use Xkinstagram\Cron\AutoImport;
use Xkinstagram\Auth\OAuth;

final class Settings {
    private const OPTION_GROUP = 'xkinstagram_settings';
    private const OPTION_NAME = 'xkinstagram_options';
    private const NONCE_ACTION = 'xkinstagram_settings_nonce';

    public function init(): void {
    }

    public function add_menu_page(): void {
        add_options_page(
            __('xkinstagram Settings', 'xkinstagram'),
            __('xkinstagram', 'xkinstagram'),
            'manage_options',
            'xkinstagram',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default' => $this->get_default_options(),
        ]);

        // Credentials section (advanced, one-time setup)
        add_settings_section(
            'xkinstagram_credentials',
            __('Advanced (App Credentials)', 'xkinstagram'),
            [$this, 'render_credentials_section'],
            'xkinstagram'
        );

        add_settings_field(
            'app_id',
            __('App ID', 'xkinstagram'),
            [$this, 'render_app_id_field'],
            'xkinstagram',
            'xkinstagram_credentials'
        );

        add_settings_field(
            'app_secret',
            __('App Secret', 'xkinstagram'),
            [$this, 'render_app_secret_field'],
            'xkinstagram',
            'xkinstagram_credentials'
        );

        // Auto-delete section
        add_settings_section(
            'xkinstagram_auto_delete',
            __('Auto-Delete Settings', 'xkinstagram'),
            [$this, 'render_auto_delete_section'],
            'xkinstagram'
        );

        add_settings_field(
            'auto_delete_enabled',
            __('Enable Auto-Delete', 'xkinstagram'),
            [$this, 'render_auto_delete_enabled_field'],
            'xkinstagram',
            'xkinstagram_auto_delete'
        );

        add_settings_field(
            'auto_delete_days',
            __('Delete Posts Older Than (Days)', 'xkinstagram'),
            [$this, 'render_auto_delete_days_field'],
            'xkinstagram',
            'xkinstagram_auto_delete'
        );
    }

    public function create_default_options(): void {
        $defaults = $this->get_default_options();
        add_option(self::OPTION_NAME, $defaults, '', 'no');
    }

    public function get_default_options(): array {
        return [
            'app_id' => '',
            'app_secret' => '',
            'access_token' => '',
            'connected' => false,
            'instagram_user_id' => '',
            'instagram_username' => '',
            'token_expires_at' => 0,
            'auto_import_enabled' => true,
            'auto_import_frequency' => 'daily',
            'auto_import_batch' => 25,
            'auto_delete_enabled' => true,
            'auto_delete_days' => 30,
        ];
    }

    public function sanitize_options(array $input): array {
        $defaults = $this->get_default_options();
        $current = get_option(self::OPTION_NAME, $defaults);
        $sanitized = [];

        $sanitized['app_id'] = sanitize_text_field($input['app_id'] ?? '');
        $sanitized['app_secret'] = sanitize_text_field($input['app_secret'] ?? '');

        // Token and connection state are managed by OAuth; carry them forward so
        // saving the settings form never wipes an established connection.
        $incomingToken = $input['access_token'] ?? '';
        if (!empty($incomingToken) && $incomingToken !== ($current['access_token'] ?? '')) {
            try {
                $sanitized['access_token'] = TokenEncryption::encrypt($incomingToken);
            } catch (\Throwable $e) {
                $sanitized['access_token'] = $current['access_token'] ?? '';
                add_settings_error(self::OPTION_GROUP, 'encryption_error', $e->getMessage(), 'error');
            }
        } else {
            $sanitized['access_token'] = $current['access_token'] ?? '';
        }
        $sanitized['connected'] = (bool) ($current['connected'] ?? false);
        $sanitized['instagram_user_id'] = sanitize_text_field($current['instagram_user_id'] ?? '');
        $sanitized['instagram_username'] = sanitize_text_field($current['instagram_username'] ?? '');
        $sanitized['token_expires_at'] = (int) ($current['token_expires_at'] ?? 0);

        $sanitized['auto_import_enabled'] = isset($input['auto_import_enabled']) ? (bool) $input['auto_import_enabled'] : $defaults['auto_import_enabled'];
        $frequency = $input['auto_import_frequency'] ?? $defaults['auto_import_frequency'];
        $sanitized['auto_import_frequency'] = in_array($frequency, AutoImport::FREQUENCIES, true) ? $frequency : $defaults['auto_import_frequency'];
        $sanitized['auto_import_batch'] = max(1, min(100, (int) ($input['auto_import_batch'] ?? $defaults['auto_import_batch'])));

        $sanitized['auto_delete_enabled'] = isset($input['auto_delete_enabled']) ? (bool) $input['auto_delete_enabled'] : $defaults['auto_delete_enabled'];
        $sanitized['auto_delete_days'] = max(1, min(365, (int) ($input['auto_delete_days'] ?? $defaults['auto_delete_days'])));

        return $sanitized;
    }

    public function render_page(): void {
        $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'credentials';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['connected'])): ?>
                <div class="notice notice-success inline"><p><?php _e('Instagram connected successfully.', 'xkinstagram'); ?></p></div>
            <?php elseif (isset($_GET['xkinstagram_error'])): ?>
                <div class="notice notice-error inline"><p><?php echo esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['xkinstagram_error'])))); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['connected'])): ?>
                <div class="notice notice-success inline"><p><?php _e('Instagram connected successfully.', 'xkinstagram'); ?></p></div>
            <?php elseif (isset($_GET['xkinstagram_error'])): ?>
                <div class="notice notice-error inline"><p><?php echo esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['xkinstagram_error'])))); ?></p></div>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=xkinstagram&tab=credentials" class="nav-tab <?php echo $activeTab === 'credentials' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Credentials', 'xkinstagram'); ?>
                </a>
                <a href="?page=xkinstagram&tab=import" class="nav-tab <?php echo $activeTab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import Posts', 'xkinstagram'); ?>
                </a>
                <a href="?page=xkinstagram&tab=auto_delete" class="nav-tab <?php echo $activeTab === 'auto_delete' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Auto-Delete', 'xkinstagram'); ?>
                </a>
            </h2>

            <?php if ($activeTab === 'credentials'): ?>
                <?php $this->render_credentials_tab(); ?>
            <?php elseif ($activeTab === 'import'): ?>
                <?php $this->render_import_tab(); ?>
            <?php elseif ($activeTab === 'auto_delete'): ?>
                <?php $this->render_auto_delete_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_credentials_tab(): void {
        $this->render_connection_panel();
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields(self::OPTION_GROUP);
            do_settings_sections('xkinstagram');
            submit_button();
            ?>
        </form>

        <hr>
        <h2><?php _e('Connection Test', 'xkinstagram'); ?></h2>
        <p><?php _e('Test your Instagram API connection before saving.', 'xkinstagram'); ?></p>
        <button type="button" id="xkinstagram-test-connection" class="button button-primary">
            <?php _e('Test Connection', 'xkinstagram'); ?>
        </button>
        <span class="spinner" id="xkinstagram-test-spinner"></span>
        <div id="xkinstagram-test-result" style="margin-top: 10px;"></div>

        <script>
            jQuery(document).ready(function($) {
                $('#xkinstagram-test-connection').on('click', function() {
                    var $btn = $(this);
                    var $spinner = $('#xkinstagram-test-spinner');
                    var $result = $('#xkinstagram-test-result');
                    
                    $btn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $result.html('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'xkinstagram_test_connection',
                            nonce: '<?php echo wp_create_nonce(self::NONCE_ACTION); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            } else {
                                $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error inline"><p><?php _e('AJAX request failed', 'xkinstagram'); ?></p></div>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    private function render_import_tab(): void {
        $log = PostImporter::get_import_log();
        ?>
        <div class="xkinstagram-import-panel">
            <h2><?php _e('Import Instagram Posts', 'xkinstagram'); ?></h2>
            <p><?php _e('Fetch and import posts from your connected Instagram account.', 'xkinstagram'); ?></p>
            
            <div class="import-controls">
                <label for="import-limit"><?php _e('Posts to import:', 'xkinstagram'); ?></label>
                <input type="number" id="import-limit" name="limit" value="50" min="1" max="200" class="small-text">
                
                <button type="button" id="xkinstagram-start-import" class="button button-primary">
                    <?php _e('Start Import', 'xkinstagram'); ?>
                </button>
                <span class="spinner" id="xkinstagram-import-spinner"></span>
            </div>

            <div id="xkinstagram-import-progress" style="margin-top: 15px; display: none;">
                <div class="progress-bar" style="width: 100%; background: #eee; height: 20px; border-radius: 3px; overflow: hidden;">
                    <div id="import-progress-fill" style="width: 0%; height: 100%; background: #22c55e; transition: width 0.3s;"></div>
                </div>
                <p id="import-progress-text" style="margin-top: 5px;"></p>
            </div>

            <div id="xkinstagram-import-result" style="margin-top: 15px;"></div>

            <hr>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                $this->render_auto_import_section();
                $this->render_auto_import_enabled_field();
                $this->render_auto_import_frequency_field();
                $this->render_auto_import_batch_field();
                submit_button();
                settings_errors(self::OPTION_GROUP);
                ?>
            </form>

            <hr>
            <h3><?php _e('Recent Import History', 'xkinstagram'); ?></h3>
            <?php if (empty($log)): ?>
                <p><?php _e('No imports yet.', 'xkinstagram'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'xkinstagram'); ?></th>
                            <th><?php _e('Imported', 'xkinstagram'); ?></th>
                            <th><?php _e('Skipped', 'xkinstagram'); ?></th>
                            <th><?php _e('Errors', 'xkinstagram'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($log, 0, 20) as $entry): ?>
                            <tr>
                                <td><?php echo esc_html($entry['time']); ?></td>
                                <td><?php echo esc_html($entry['imported']); ?></td>
                                <td><?php echo esc_html($entry['skipped']); ?></td>
                                <td><?php echo esc_html(count($entry['errors'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <script>
                jQuery(document).ready(function($) {
                    $('#xkinstagram-start-import').on('click', function() {
                        var $btn = $(this);
                        var $spinner = $('#xkinstagram-import-spinner');
                        var $progress = $('#xkinstagram-import-progress');
                        var $progressFill = $('#import-progress-fill');
                        var $progressText = $('#import-progress-text');
                        var $result = $('#xkinstagram-import-result');
                        var limit = $('#import-limit').val();
                        
                        $btn.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $progress.show();
                        $result.html('');
                        $progressFill.css('width', '10%');
                        $progressText.text('<?php _e('Starting import...', 'xkinstagram'); ?>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'xkinstagram_import_posts',
                                nonce: '<?php echo wp_create_nonce(self::NONCE_ACTION); ?>',
                                limit: limit
                            },
                            success: function(response) {
                                $progressFill.css('width', '100%');
                                if (response.success) {
                                    var data = response.data;
                                    $progressText.text('<?php _e('Import complete!', 'xkinstagram'); ?>');
                                    var msg = '<div class="notice notice-success inline"><p>';
                                    msg += '<?php _e('Imported:', 'xkinstagram'); ?> ' + data.imported + ' | ';
                                    msg += '<?php _e('Skipped:', 'xkinstagram'); ?> ' + data.skipped + ' | ';
                                    msg += '<?php _e('Errors:', 'xkinstagram'); ?> ' + data.errors.length;
                                    msg += '</p></div>';
                                    if (data.errors.length > 0) {
                                        msg += '<details><summary><?php _e('Errors', 'xkinstagram'); ?></summary><ul>';
                                        data.errors.forEach(function(err) {
                                            msg += '<li>' + err + '</li>';
                                        });
                                        msg += '</ul></details>';
                                    }
                                    $result.html(msg);
                                } else {
                                    $progressText.text('<?php _e('Import failed', 'xkinstagram'); ?>');
                                    $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                                }
                            },
                            error: function() {
                                $progressFill.css('width', '100%');
                                $progressText.text('<?php _e('Import failed', 'xkinstagram'); ?>');
                                $result.html('<div class="notice notice-error inline"><p><?php _e('AJAX request failed', 'xkinstagram'); ?></p></div>');
                            },
                            complete: function() {
                                $btn.prop('disabled', false);
                                $spinner.removeClass('is-active');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    private function render_auto_delete_tab(): void {
        $deleteLog = AutoDelete::get_delete_log();
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields(self::OPTION_GROUP);
            do_settings_sections('xkinstagram');
            submit_button();
            ?>
        </form>

        <hr>
        <h3><?php _e('Recent Cleanup History', 'xkinstagram'); ?></h3>
        <?php if (empty($deleteLog)): ?>
            <p><?php _e('No cleanup runs yet.', 'xkinstagram'); ?></p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'xkinstagram'); ?></th>
                        <th><?php _e('Posts Deleted', 'xkinstagram'); ?></th>
                        <th><?php _e('Cutoff Date', 'xkinstagram'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($deleteLog, 0, 20) as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['time']); ?></td>
                            <td><?php echo esc_html($entry['deleted_count']); ?></td>
                            <td><?php echo esc_html($entry['cutoff_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    public function render_connection_panel(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        $connected = (bool) ($options['connected'] ?? false);
        $username = (string) ($options['instagram_username'] ?? '');
        ?>
        <div class="postbox" style="padding: 16px; max-width: 640px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;"><?php _e('Instagram Connection', 'xkinstagram'); ?></h2>

            <?php if ($connected): ?>
                <p>
                    <span class="dashicons dashicons-yes" style="color: #22c55e;"></span>
                    <?php printf(
                        __('Connected as <strong>@%s</strong>', 'xkinstagram'),
                        esc_html($username)
                    ); ?>
                </p>
                <button type="button" id="xkinstagram-disconnect" class="button">
                    <?php _e('Disconnect', 'xkinstagram'); ?>
                </button>
                <span class="spinner" id="xkinstagram-disconnect-spinner"></span>
                <div id="xkinstagram-disconnect-result" style="margin-top: 10px;"></div>
                <script>
                    jQuery(document).ready(function($) {
                        $('#xkinstagram-disconnect').on('click', function() {
                            var $btn = $(this);
                            var $spinner = $('#xkinstagram-disconnect-spinner');
                            var $result = $('#xkinstagram-disconnect-result');
                            $btn.prop('disabled', true);
                            $spinner.addClass('is-active');
                            $result.html('');
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'xkinstagram_disconnect',
                                    nonce: '<?php echo wp_create_nonce(self::NONCE_ACTION); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                                        $btn.prop('disabled', false);
                                    }
                                },
                                error: function() {
                                    $result.html('<div class="notice notice-error inline"><p><?php _e('AJAX request failed', 'xkinstagram'); ?></p></div>');
                                    $btn.prop('disabled', false);
                                },
                                complete: function() {
                                    $spinner.removeClass('is-active');
                                }
                            });
                        });
                    });
                </script>
            <?php else: ?>
                <p><?php _e('Connect your Instagram account with one click. You will be asked to log in to Instagram and grant access.', 'xkinstagram'); ?></p>
                <?php if (empty($options['app_id']) || empty($options['app_secret'])): ?>
                    <p class="description">
                        <?php _e('First enter your App ID and App Secret below (one-time setup), then register the callback URL in your Meta app:', 'xkinstagram'); ?>
                        <code><?php echo esc_html(OAuth::redirect_uri()); ?></code>
                    </p>
                <?php else: ?>
                    <a href="<?php echo esc_url($this->connect_url()); ?>" class="button button-primary">
                        <?php _e('Connect with Instagram', 'xkinstagram'); ?>
                    </a>
                    <p class="description">
                        <?php _e('OAuth callback URL to register in your app:', 'xkinstagram'); ?>
                        <code><?php echo esc_html(OAuth::redirect_uri()); ?></code>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function connect_url(): string {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        $oauth = new OAuth(
            (string) ($options['app_id'] ?? ''),
            (string) ($options['app_secret'] ?? ''),
            OAuth::redirect_uri()
        );
        return $oauth->authorize_url();
    }

    public function render_credentials_section(): void {
        echo '<p>' . __('Enter your Instagram Graph API credentials once ("Instagram API with Instagram Login"). Register the callback URL below as a valid OAuth redirect URI in your Meta app.', 'xkinstagram') . '</p>';
    }

    public function render_auto_delete_section(): void {
        echo '<p>' . __('Automatically delete imported Instagram posts and their media after the specified number of days.', 'xkinstagram') . '</p>';
    }

    public function render_app_id_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="text" name="%1$s[app_id]" value="%2$s" class="regular-text" placeholder="%3$s">',
            esc_attr(self::OPTION_NAME),
            esc_attr($options['app_id']),
            esc_attr__('Your Instagram App ID', 'xkinstagram')
        );
        echo '<p class="description">' . __('Found in Meta App Dashboard → Instagram → API setup with Instagram Login', 'xkinstagram') . '</p>';
    }

    public function render_app_secret_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="password" name="%1$s[app_secret]" value="%2$s" class="regular-text" placeholder="%3$s" autocomplete="off">',
            esc_attr(self::OPTION_NAME),
            esc_attr($options['app_secret']),
            esc_attr__('Your Instagram App Secret', 'xkinstagram')
        );
        echo '<p class="description">' . __('Found in Meta App Dashboard → Instagram → API setup with Instagram Login → Business login settings', 'xkinstagram') . '</p>';
    }

    public function render_auto_import_section(): void {
        echo '<p>' . __('Automatically import new Instagram posts on a schedule. Each run is incremental and skips posts already imported.', 'xkinstagram') . '</p>';
    }

    public function render_auto_import_enabled_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="checkbox" name="%1$s[auto_import_enabled]" value="1" %2$s>',
            esc_attr(self::OPTION_NAME),
            checked($options['auto_import_enabled'], true, false)
        );
        echo '<p class="description">' . __('When enabled, Instagram posts are imported automatically on the configured schedule.', 'xkinstagram') . '</p>';
    }

    public function render_auto_import_frequency_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<select name="%1$s[auto_import_frequency]">',
            esc_attr(self::OPTION_NAME)
        );
        $labels = [
            'hourly' => __('Hourly', 'xkinstagram'),
            'twicedaily' => __('Twice Daily', 'xkinstagram'),
            'daily' => __('Daily', 'xkinstagram'),
        ];
        foreach (AutoImport::FREQUENCIES as $frequency) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($frequency),
                selected($options['auto_import_frequency'], $frequency, false),
                esc_html($labels[$frequency] ?? $frequency)
            );
        }
        echo '</select>';
        echo '<p class="description">' . __('How often the automatic import runs.', 'xkinstagram') . '</p>';
    }

    public function render_auto_import_batch_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="number" name="%1$s[auto_import_batch]" value="%2$s" class="small-text" min="1" max="100">',
            esc_attr(self::OPTION_NAME),
            esc_attr($options['auto_import_batch'])
        );
        echo '<p class="description">' . __('Maximum posts imported per run. Default: 25.', 'xkinstagram') . '</p>';
    }

    public function render_auto_delete_enabled_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="checkbox" name="%1$s[auto_delete_enabled]" value="1" %2$s>',
            esc_attr(self::OPTION_NAME),
            checked($options['auto_delete_enabled'], true, false)
        );
        echo '<p class="description">' . __('When enabled, posts older than the specified days will be automatically deleted daily.', 'xkinstagram') . '</p>';
    }

    public function render_auto_delete_days_field(): void {
        $options = get_option(self::OPTION_NAME, $this->get_default_options());
        printf(
            '<input type="number" name="%1$s[auto_delete_days]" value="%2$s" class="small-text" min="1" max="365">',
            esc_attr(self::OPTION_NAME),
            esc_attr($options['auto_delete_days'])
        );
        echo '<p class="description">' . __('Default: 30 days. Minimum: 1 day. Maximum: 365 days.', 'xkinstagram') . '</p>';
    }
}