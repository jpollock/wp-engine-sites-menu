<?php
/**
 * Plugin Name: WP Engine Sites Menu
 * Plugin URI: https://github.com/yourusername/wp-engine-sites-menu
 * Description: Display WP Engine sites menu for quick access to related installs
 * Version: 1.0.0
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

    private function init_sdk() {
        if ($this->sdk === null) {
            $username = get_option('wpe_username');
            $password = $this->get_password();
            
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
        if ('toplevel_page_wp-engine-sites-menu' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'wpe-admin-script',
            plugins_url('js/admin.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('wpe-admin-script', 'wpeAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpe_test_credentials')
        ));

        // Add inline styles
        wp_add_inline_style('admin-bar', '
            #wpadminbar .wpe-sites-menu .ab-sub-wrapper {
                max-height: 400px;
                overflow-y: auto;
            }
            .wpe-test-credentials-result {
                margin-top: 15px;
                padding: 10px 15px;
                border-radius: 4px;
            }
            .wpe-test-credentials-result.success {
                background-color: #dff0d8;
                border: 1px solid #d6e9c6;
                color: #3c763d;
            }
            .wpe-test-credentials-result.error {
                background-color: #f2dede;
                border: 1px solid #ebccd1;
                color: #a94442;
            }
            #wpadminbar .wpe-site-header {
                padding: 0 8px;
                background: #32373c;
                color: #eee;
                font-weight: 600;
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

    public function add_wpe_sites_menu($wp_admin_bar) {
        if (!$this->init_sdk()) {
            return;
        }

        try {
            $current_domain = get_site_url();
            $current_domain_root = $this->get_domain_root($current_domain);
            
            $sites_response = $this->sdk->sites->listSites();
            $matching_sites = array();

            // First, find sites that have at least one matching install
            if (!empty($sites_response['results'])) {
                foreach ($sites_response['results'] as $site) {
                    if (!empty($site['installs'])) {
                        $has_matching_install = false;
                        
                        // Check if any install matches the domain root
                        foreach ($site['installs'] as $install) {
                            if (!empty($install['cname'])) {
                                $install_domain_root = $this->get_domain_root($install['cname']);
                                if ($install_domain_root === $current_domain_root) {
                                    $has_matching_install = true;
                                    break;
                                }
                            }
                        }
                        
                        // If site has a matching install, include all its installs
                        if ($has_matching_install) {
                            $matching_sites[] = $site;
                        }
                    }
                }
            }

            if (!empty($matching_sites)) {
                // Add the main menu item
                $wp_admin_bar->add_node(array(
                    'id' => 'wpe-sites',
                    'title' => 'WP Engine Sites',
                    'href' => '#',
                    'meta' => array(
                        'class' => 'wpe-sites-menu'
                    )
                ));

                // Add all installs from matching sites
                foreach ($matching_sites as $site) {
                    // Add site name as a header
                    $wp_admin_bar->add_node(array(
                        'parent' => 'wpe-sites',
                        'id' => 'wpe-site-header-' . sanitize_title($site['name']),
                        'title' => esc_html($site['name']),
                        'meta' => array(
                            'class' => 'wpe-site-header'
                        )
                    ));

                    // Add all installs for this site
                    foreach ($site['installs'] as $install) {
                        if (!empty($install['cname'])) {
                            $wp_admin_bar->add_node(array(
                                'parent' => 'wpe-sites',
                                'id' => 'wpe-site-' . sanitize_title($site['name'] . '-' . $install['name']),
                                'title' => sprintf(
                                    '%s (%s)',
                                    esc_html($install['name']),
                                    esc_html($install['environment'])
                                ),
                                'href' => 'https://' . esc_attr($install['cname'])
                            ));
                        }
                    }
                }
            }
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
            if (!$this->init_sdk()) {
                throw new \Exception(__('Failed to initialize WP Engine SDK', 'wp-engine-sites-menu'));
            }

            $sites_response = $this->sdk->sites->listSites();
            $site_count = !empty($sites_response['results']) ? count($sites_response['results']) : 0;

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
        add_menu_page(
            __('WP Engine Sites Menu', 'wp-engine-sites-menu'),
            __('WP Engine Menu', 'wp-engine-sites-menu'),
            'manage_options',
            'wp-engine-sites-menu',
            array($this, 'render_admin_page'),
            'dashicons-admin-site'
        );
    }

    public function init_settings() {
        register_setting('wp_engine_sites_menu_settings', 'wpe_username');
        register_setting('wp_engine_sites_menu_settings', 'wpe_password');
        
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

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $has_credentials = !empty(get_option('wpe_username')) && !empty(get_option('wpe_password'));
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

            <?php if ($has_credentials): ?>
                <h2><?php _e('Connected Installs', 'wp-engine-sites-menu'); ?></h2>
                <?php $this->display_connected_installs(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function display_connected_installs() {
        try {
            if (!$this->init_sdk()) {
                throw new \Exception(__('Failed to initialize WP Engine SDK', 'wp-engine-sites-menu'));
            }

            // Get current site domain
            $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
            
            try {
                // Get all sites first
                $sites_response = $this->sdk->sites->listSites();
                
                if (empty($sites_response['results'])) {
                    echo '<p>' . __('No sites found.', 'wp-engine-sites-menu') . '</p>';
                    return;
                }

                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>' . __('Site Name', 'wp-engine-sites-menu') . '</th>';
                echo '<th>' . __('Install Name', 'wp-engine-sites-menu') . '</th>';
                echo '<th>' . __('Environment', 'wp-engine-sites-menu') . '</th>';
                echo '<th>' . __('Domain', 'wp-engine-sites-menu') . '</th>';
                echo '</tr></thead><tbody>';

                foreach ($sites_response['results'] as $site) {
                    if (!empty($site['installs'])) {
                        foreach ($site['installs'] as $install) {
                            echo '<tr>';
                            echo '<td>' . esc_html($site['name']) . '</td>';
                            echo '<td>' . esc_html($install['name']) . '</td>';
                            echo '<td>' . esc_html($install['environment']) . '</td>';
                            echo '<td>' . esc_html($install['cname'] ?? 'N/A') . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr>';
                        echo '<td>' . esc_html($site['name']) . '</td>';
                        echo '<td colspan="3">' . __('No installations', 'wp-engine-sites-menu') . '</td>';
                        echo '</tr>';
                    }
                }

                echo '</tbody></table>';

            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

        } catch (\Exception $e) {
            echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}

// Initialize the plugin
function wp_engine_sites_menu_init() {
    WPEngine_Sites_Menu::get_instance();
}
add_action('plugins_loaded', 'wp_engine_sites_menu_init');
