<?php
/**
 * Plugin Name: WP Engine Sites Menu
 * Plugin URI: https://github.com/yourusername/wp-engine-sites-menu
 * Description: Display WP Engine sites menu for quick access to related installs
 * Version: 0.0.1
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-engine-sites-menu
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Composer autoload
if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
}

use WPEngine\WPEngineSDK;

class WPEngine_Sites_Menu {
    private static $instance = null;
    private $sdk = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_test_wpe_credentials', array($this, 'test_credentials_ajax'));
        add_action('wp_ajax_search_wpe_sites', array($this, 'search_sites_ajax'));
        add_action('admin_bar_menu', array($this, 'add_wpe_sites_menu'), 100);
        
        // Handle password encryption on settings update
        add_filter('pre_update_option_wpe_password', array($this, 'encrypt_password'), 10, 2);
    }

    /**
     * Encrypt password before saving to database
     */
    public function encrypt_password($new_value, $old_value) {
        if (empty($new_value)) {
            return $new_value;
        }
        
        // Only encrypt if the value has changed
        if ($new_value !== $old_value) {
            $new_value = $this->encrypt($new_value);
        }
        
        return $new_value;
    }

    /**
     * Encrypt a string using WordPress salt
     */
    private function encrypt($text) {
        if (empty($text)) {
            return $text;
        }
        
        $salt = defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : wp_salt('logged_in');
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $raw_value = openssl_encrypt(
            $text,
            $method,
            $salt,
            0,
            $iv
        );
        
        if ($raw_value === false) {
            return $text;
        }
        
        return base64_encode($iv . $raw_value);
    }

    /**
     * Decrypt a string using WordPress salt
     */
    private function decrypt($text) {
        if (empty($text)) {
            return $text;
        }
        
        $salt = defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : wp_salt('logged_in');
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        
        $decoded = base64_decode($text);
        if ($decoded === false) {
            return $text;
        }
        
        $iv = substr($decoded, 0, $ivlen);
        $raw_value = substr($decoded, $ivlen);
        
        $decrypted = openssl_decrypt(
            $raw_value,
            $method,
            $salt,
            0,
            $iv
        );
        
        return $decrypted === false ? $text : $decrypted;
    }

    /**
     * Get decrypted password
     */
    private function get_password() {
        $encrypted_password = get_option('wpe_password');
        return $this->decrypt($encrypted_password);
    }

    private function init_sdk($username = null, $password = null) {
        if ($this->sdk === null) {
            // If no credentials provided, use stored credentials
            if ($username === null) {
                $username = get_option('wpe_username');
            }
            if ($password === null) {
                $password = $this->get_password();
            }
            
            if (!empty($username) && !empty($password)) {
                try {
                    $this->sdk = new WPEngineSDK([
                        'username' => $username,
                        'password' => $password
                    ]);
                    return true;
                } catch (\Exception $e) {
                    // SDK initialization failed
                    return false;
                }
            }
        }
        return $this->sdk !== null;
    }

    public function enqueue_admin_scripts($hook) {
        // Always enqueue menu scripts for admin bar
        wp_enqueue_script(
            'wpe-menu-script',
            plugins_url('js/menu.js', __FILE__),
            array('jquery'),
            '0.0.1',
            true
        );

        wp_localize_script('wpe-menu-script', 'wpeMenu', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpe_search_sites')
        ));

        // Settings page scripts
        if ('settings_page_wp-engine-sites-menu' === $hook) {
            wp_enqueue_script(
                'wpe-admin-script',
                plugins_url('js/admin.js', __FILE__),
                array('jquery'),
                '0.0.1',
                true
            );

            wp_localize_script('wpe-admin-script', 'wpeAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpe_test_credentials')
            ));
        }

        // Add inline styles
        wp_add_inline_style('admin-bar', '
            /* Main menu container */
            #wpadminbar .wpe-sites-menu .ab-sub-wrapper {
                max-height: 600px;
                overflow-y: auto;
            }

            /* Search box */
            .wpe-search-container {
                padding: 8px !important;
                height: auto !important;
                background: #32373c !important;
                position: sticky !important;
                top: 0 !important;
                z-index: 100000 !important;
                border-bottom: 1px solid #454545 !important;
            }
            .wpe-search-container input {
                width: 100% !important;
                height: 28px !important;
                padding: 4px 8px !important;
                border: 1px solid #7e8993 !important;
                border-radius: 3px !important;
                background: #ffffff !important;
                font-size: 13px !important;
                line-height: normal !important;
                color: #32373c !important;
                margin: 0 !important;
            }
            .wpe-search-container input:focus {
                outline: none !important;
                border-color: #2271b1 !important;
                box-shadow: 0 0 0 1px #2271b1 !important;
            }

            /* Current site section */
            .wpe-current-site {
                background: #2271b1 !important;
                color: #ffffff !important;
                font-weight: 600 !important;
            }
            .wpe-current-site-installs {
                background: #135e96 !important;
            }
            .wpe-current-site-installs .ab-item {
                color: #ffffff !important;
            }

            /* Separator */
            .wpe-menu-separator {
                height: 1px !important;
                margin: 5px 0 !important;
                background: #454545 !important;
                padding: 0 !important;
            }

            /* Other sites label */
            .wpe-other-sites-label {
                padding: 4px 8px !important;
                color: #a7aaad !important;
                font-size: 13px !important;
            }
            
            /* Environment labels */
            .wpe-env-label {
                float: right !important;
                opacity: 0.8 !important;
                font-size: 0.9em !important;
                color: #a7aaad !important;
                margin-left: 8px !important;
            }

            /* Search results */
            .wpe-search-results {
                padding: 4px 0 !important;
            }
            .wpe-search-results .ab-item:hover {
                color: #72aee6 !important;
            }
            .wpe-no-results {
                padding: 4px 8px !important;
                color: #a7aaad !important;
                font-style: italic !important;
            }
        ');
    }

    private function get_domain_root($domain) {
        // Remove protocol and www if present
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        
        // Split domain into parts
        $parts = explode('.', $domain);
        
        // Handle special cases
        if (count($parts) >= 2) {
            // Case 1: .local domain (e.g., wpe-plugin-tester.local)
            if (end($parts) === 'local') {
                return $parts[0];
            }
            
            // Case 2: wpengine.com domain (e.g., eotestingtrans.wpengine.com)
            if (end($parts) === 'com' && prev($parts) === 'wpengine') {
                return reset($parts); // Get first part
            }
            
            // Case 3: Regular domain (e.g., example.com)
            return $parts[count($parts) - 2];
        }
        
        // Fallback to the full domain if no parts
        return $domain;
    }

    public function search_sites_ajax() {
        check_ajax_referer('wpe_search_sites', 'nonce');

        if (!$this->init_sdk()) {
            wp_send_json_error('Failed to initialize SDK');
            return;
        }

        $search = strtolower(sanitize_text_field($_POST['search']));
        if (empty($search)) {
            wp_send_json_success(array('results' => array()));
            return;
        }

        try {
            // Check for cached sites data
            $sites_response = get_transient('wpe_sites_menu_data');
            if ($sites_response === false) {
                $sites_response = $this->sdk->sites->listSites();
                // Cache the response using the configured duration
                $cache_duration = absint(get_option('wpe_cache_duration', 3600));
                set_transient('wpe_sites_menu_data', $sites_response, $cache_duration);
            }

            $results = array();

            if (!empty($sites_response['results'])) {
                foreach ($sites_response['results'] as $site) {
                    if (!empty($site['installs'])) {
                        foreach ($site['installs'] as $install) {
                            if (!empty($install['cname'])) {
                                $site_name = strtolower($site['name']);
                                $install_name = strtolower($install['name']);
                                $domain = strtolower($install['cname']);

                                if (strpos($site_name, $search) !== false ||
                                    strpos($install_name, $search) !== false ||
                                    strpos($domain, $search) !== false) {
                                    $results[] = array(
                                        'site_name' => $site['name'],
                                        'install_name' => $install['name'],
                                        'environment' => $install['environment'],
                                        'url' => 'https://' . $install['cname'] . '/wp-admin'
                                    );
                                }
                            }
                        }
                    }
                }
            }

            wp_send_json_success(array('results' => $results));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function add_wpe_sites_menu($wp_admin_bar) {
        if (!$this->init_sdk()) {
            return;
        }

        try {
            $current_domain = get_site_url();
            $current_domain_root = $this->get_domain_root($current_domain);
            
            // Check for cached sites data
            $sites_response = get_transient('wpe_sites_menu_data');
            if ($sites_response === false) {
                $sites_response = $this->sdk->sites->listSites();
                // Cache the response using the configured duration
                $cache_duration = absint(get_option('wpe_cache_duration', 3600));
                set_transient('wpe_sites_menu_data', $sites_response, $cache_duration);
            }

            $current_site = null;

            // Find current site
            if (!empty($sites_response['results'])) {
                foreach ($sites_response['results'] as $site) {
                    if (!empty($site['installs'])) {
                        foreach ($site['installs'] as $install) {
                            if (!empty($install['cname'])) {
                                $install_domain_root = $this->get_domain_root($install['cname']);
                                if ($install_domain_root === $current_domain_root) {
                                    $current_site = $site;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // Add the main menu item
            $wp_admin_bar->add_node(array(
                'id' => 'wpe-sites',
                'title' => 'WP Engine Sites',
                'href' => '#',
                'meta' => array(
                    'class' => 'wpe-sites-menu'
                )
            ));

            // Add current site section if found
            if ($current_site) {
                // Add "Current Site:" label
                $wp_admin_bar->add_node(array(
                    'parent' => 'wpe-sites',
                    'id' => 'wpe-current-site-label',
                    'title' => __('Current Site:', 'wp-engine-sites-menu'),
                    'meta' => array(
                        'class' => 'wpe-current-site-label'
                    )
                ));

                // Add current site header
                $wp_admin_bar->add_node(array(
                    'parent' => 'wpe-sites',
                    'id' => 'wpe-current-site',
                    'title' => esc_html($current_site['name']),
                    'meta' => array(
                        'class' => 'wpe-current-site'
                    )
                ));

                // Add current site's installs
                foreach ($current_site['installs'] as $install) {
                    if (!empty($install['cname'])) {
                        $wp_admin_bar->add_node(array(
                            'parent' => 'wpe-sites',
                            'id' => 'wpe-current-install-' . sanitize_title($install['name']),
                            'title' => sprintf(
                                '%s (%s)',
                                esc_html($install['name']),
                                esc_html($install['environment'])
                            ),
                            'href' => 'https://' . esc_attr($install['cname']) . '/wp-admin',
                            'meta' => array(
                                'class' => 'wpe-current-site-installs',
                                'target' => '_blank'
                            )
                        ));
                    }
                }

                // Add separator
                $wp_admin_bar->add_node(array(
                    'parent' => 'wpe-sites',
                    'id' => 'wpe-separator',
                    'title' => '',
                    'meta' => array(
                        'class' => 'wpe-menu-separator'
                    )
                ));
            }

            // Add "Other WPE Sites" label
            $wp_admin_bar->add_node(array(
                'parent' => 'wpe-sites',
                'id' => 'wpe-other-sites-label',
                'title' => __('Other WPE Installs', 'wp-engine-sites-menu'),
                'meta' => array(
                    'class' => 'wpe-other-sites-label'
                )
            ));

            // Add search box
            $wp_admin_bar->add_node(array(
                'parent' => 'wpe-sites',
                'id' => 'wpe-sites-search',
                'title' => '<input type="text" placeholder="Search installs..." />',
                'meta' => array(
                    'class' => 'wpe-search-container'
                )
            ));

            // Add container for search results
            $wp_admin_bar->add_node(array(
                'parent' => 'wpe-sites',
                'id' => 'wpe-search-results',
                'title' => '',
                'meta' => array(
                    'class' => 'wpe-search-results'
                )
            ));

        } catch (\Exception $e) {
            // Silently fail - don't show menu if there's an error
        }
    }

    public function test_credentials_ajax() {
        check_ajax_referer('wpe_test_credentials', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            // Get credentials from POST data if provided, otherwise use stored credentials
            $username = !empty($_POST['username']) ? sanitize_text_field($_POST['username']) : null;
            $password = !empty($_POST['password']) ? sanitize_text_field($_POST['password']) : null;

            // If password is masked (********), use stored password
            if ($password === '********') {
                $password = null;
            }

            if (!$this->init_sdk($username, $password)) {
                throw new \Exception(__('Failed to initialize WP Engine SDK', 'wp-engine-sites-menu'));
            }

            // Clear the cache when testing credentials
            delete_transient('wpe_sites_menu_data');

            $sites_response = $this->sdk->sites->listSites();
            $site_count = !empty($sites_response['results']) ? count($sites_response['results']) : 0;

            // Cache the response using the configured duration
            $cache_duration = absint(get_option('wpe_cache_duration', 3600));
            set_transient('wpe_sites_menu_data', $sites_response, $cache_duration);

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Success! Found %d site(s) associated with your account.', 'wp-engine-sites-menu'),
                    $site_count
                )
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            __('WP Engine API', 'wp-engine-sites-menu'),
            __('WP Engine API', 'wp-engine-sites-menu'),
            'manage_options',
            'wp-engine-sites-menu',
            array($this, 'render_admin_page')
        );
    }

    public function init_settings() {
        register_setting('wp_engine_sites_menu_settings', 'wpe_username');
        register_setting('wp_engine_sites_menu_settings', 'wpe_password');
        register_setting('wp_engine_sites_menu_settings', 'wpe_cache_duration', array(
            'type' => 'integer',
            'default' => 3600,
            'sanitize_callback' => array($this, 'sanitize_cache_duration')
        ));
        
        add_settings_section(
            'wp_engine_sites_menu_main',
            __('API Settings', 'wp-engine-sites-menu'),
            array($this, 'settings_section_callback'),
            'wp-engine-sites-menu'
        );

        add_settings_field(
            'wpe_username',
            __('WP Engine Username', 'wp-engine-sites-menu'),
            array($this, 'username_field_callback'),
            'wp-engine-sites-menu',
            'wp_engine_sites_menu_main'
        );

        add_settings_field(
            'wpe_password',
            __('WP Engine Password', 'wp-engine-sites-menu'),
            array($this, 'password_field_callback'),
            'wp-engine-sites-menu',
            'wp_engine_sites_menu_main'
        );

        add_settings_field(
            'wpe_cache_duration',
            __('Cache Duration (seconds)', 'wp-engine-sites-menu'),
            array($this, 'cache_duration_field_callback'),
            'wp-engine-sites-menu',
            'wp_engine_sites_menu_main'
        );
    }

    public function sanitize_cache_duration($value) {
        $value = absint($value);
        return $value > 0 ? $value : 3600;
    }

    public function settings_section_callback() {
        echo '<p>' . __('Enter your WP Engine API credentials below.', 'wp-engine-sites-menu') . '</p>';
    }

    public function username_field_callback() {
        $username = get_option('wpe_username');
        echo '<input type="text" id="wpe_username" name="wpe_username" value="' . esc_attr($username) . '" class="regular-text">';
    }

    public function password_field_callback() {
        $password = get_option('wpe_password');
        // Don't show decrypted password in the field
        echo '<input type="password" id="wpe_password" name="wpe_password" value="' . (!empty($password) ? '********' : '') . '" class="regular-text">';
    }

    public function cache_duration_field_callback() {
        $duration = get_option('wpe_cache_duration', 3600);
        echo '<input type="number" id="wpe_cache_duration" name="wpe_cache_duration" value="' . esc_attr($duration) . '" class="regular-text" min="1">';
        echo '<p class="description">' . __('Default: 3600 seconds (1 hour). Increase to reduce API calls, decrease for more frequent updates.', 'wp-engine-sites-menu') . '</p>';
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('wp_engine_sites_menu_settings');
                do_settings_sections('wp-engine-sites-menu');
                ?>
                <div class="submit-container" style="display: flex; align-items: center; gap: 10px;">
                    <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                    <button type="button" id="test-credentials" class="button button-secondary">
                        <?php _e('Test Credentials', 'wp-engine-sites-menu'); ?>
                    </button>
                </div>
            </form>
            
            <div id="test-credentials-result" style="display: none;" class="wpe-test-credentials-result"></div>
        </div>
        <?php
    }
}

// Initialize the plugin
function wp_engine_sites_menu_init() {
    WPEngine_Sites_Menu::get_instance();
}
add_action('plugins_loaded', 'wp_engine_sites_menu_init');
