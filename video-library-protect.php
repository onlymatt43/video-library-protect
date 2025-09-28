<?php
/**
 * Plugin Name: Video Library Protect
 * Plugin URI: https://votresite.com/plugins/video-library-protect
 * Description: Un système de vidéothèque avancé pour WordPress avec une protection de contenu flexible via des codes cadeaux.
 * Version: 2.0.0
 * Author: Mathieu Courchesne
 * Author URI: https://github.com/onlymatt43
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: video-library-protect
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package VideoLibraryProtect
 * @version 2.0.0
 * @since   2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VLP_VERSION', '2.0.0');
define('VLP_PLUGIN_FILE', __FILE__);
define('VLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VLP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VLP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Video Library Protect Class
 */
final class Video_Library_Protect {

    /**
     * Plugin instance
     *
     * @var Video_Library_Protect
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Video_Library_Protect
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(VLP_PLUGIN_FILE, ['VLP_Activator', 'activate']);
        register_deactivation_hook(VLP_PLUGIN_FILE, ['VLP_Deactivator', 'deactivate']);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load core classes
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes for activation and deactivation
        $this->require_file('includes/class-vlp-activator.php');
        $this->require_file('includes/class-vlp-deactivator.php');

        // Core functional classes
        $this->require_file('includes/class-vlp-video-manager.php');
        $this->require_file('includes/class-vlp-protection-manager.php');
        
        // Integrations and public-facing classes
        $this->require_file('includes/class-vlp-bunny-integration.php', false);
        $this->require_file('includes/class-vlp-presto-integration.php', false);
        $this->require_file('includes/class-vlp-analytics.php', false);
        $this->require_file('public/class-vlp-public.php');
        $this->require_file('public/class-vlp-shortcodes.php');

        // Admin classes
        if (is_admin()) {
            $this->require_file('admin/class-vlp-admin.php');
            $this->require_file('admin/class-vlp-video-admin.php');
        }
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize managers
        $singletons = array(
            'VLP_Video_Manager',
            'VLP_Protection_Manager',
            'VLP_Bunny_Integration',
            'VLP_Presto_Integration',
            'VLP_Analytics',
        );

        foreach ($singletons as $class) {
            if (class_exists($class) && method_exists($class, 'get_instance')) {
                $class::get_instance();
            }
        }

        // Initialize admin
        if (is_admin()) {
            $admin_classes = array('VLP_Admin', 'VLP_Video_Admin');
            foreach ($admin_classes as $class) {
                if (class_exists($class) && method_exists($class, 'get_instance')) {
                    $class::get_instance();
                }
            }
        }

        // Initialize public
        $public_classes = array('VLP_Public', 'VLP_Shortcodes');
        foreach ($public_classes as $class) {
            if (class_exists($class) && method_exists($class, 'get_instance')) {
                $class::get_instance();
            }
        }
    }

    /**
     * Require a plugin file if it exists
     *
     * @param string $relative_path Relative path from plugin root
     * @param bool   $is_required   Whether the file is required
     * @return bool
     */
    private function require_file($relative_path, $is_required = true) {
        $full_path = VLP_PLUGIN_DIR . $relative_path;

        if (file_exists($full_path)) {
            require_once $full_path;
            return true;
        }

        static $logged_missing = array();
        $log_key = ($is_required ? 'required' : 'optional') . ':' . $relative_path;

        if (!isset($logged_missing[$log_key])) {
            if ($is_required) {
                error_log(sprintf('[Video Library Protect] Required file missing: %s', $relative_path));
            } elseif (defined('WP_DEBUG') && constant('WP_DEBUG')) {
                error_log(sprintf('[Video Library Protect] Optional file missing: %s', $relative_path));
            }
            $logged_missing[$log_key] = true;
        }

        return false;
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('video-library-protect', false, dirname(VLP_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Plugin activation - now handled by VLP_Activator.
     */
    public function activate() {
        // This is intentionally left empty. Activation logic is in VLP_Activator.
    }

    /**
     * Plugin deactivation - now handled by VLP_Deactivator.
     */
    public function deactivate() {
        // This is intentionally left empty. Deactivation logic is in VLP_Deactivator.
    }

    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Videos table
        $videos_table = $wpdb->prefix . 'vlp_videos';
        $sql_videos = "CREATE TABLE $videos_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            preview_video_url varchar(500),
            full_video_url varchar(500),
            bunny_preview_guid varchar(100),
            bunny_full_guid varchar(100),
            thumbnail_url varchar(500),
            duration int DEFAULT 0,
            file_size bigint DEFAULT 0,
            protection_level varchar(20) DEFAULT 'free',
            protection_data longtext,
            status varchar(20) DEFAULT 'published',
            views_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY protection_level (protection_level),
            KEY status (status)
        ) $charset_collate;";

        // Categories table
        $categories_table = $wpdb->prefix . 'vlp_categories';
        $sql_categories = "CREATE TABLE $categories_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            protection_level varchar(20) DEFAULT 'free',
            protection_data longtext,
            thumbnail_url varchar(500),
            sort_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // Video-Category relations
        $relations_table = $wpdb->prefix . 'vlp_video_categories';
        $sql_relations = "CREATE TABLE $relations_table (
            video_id bigint(20) NOT NULL,
            category_id bigint(20) NOT NULL,
            PRIMARY KEY (video_id, category_id),
            KEY video_id (video_id),
            KEY category_id (category_id)
        ) $charset_collate;";

        // Access log table
        $access_table = $wpdb->prefix . 'vlp_access_log';
        $sql_access = "CREATE TABLE $access_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            video_id bigint(20),
            category_id bigint(20),
            user_id bigint(20),
            session_id varchar(64),
            gift_code varchar(100),
            access_type varchar(20),
            access_level varchar(20),
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY category_id (category_id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate;";

        // Analytics table
        $analytics_table = $wpdb->prefix . 'vlp_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            video_id bigint(20) NOT NULL,
            user_id bigint(20),
            session_id varchar(64),
            event_type varchar(50) NOT NULL,
            event_data longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_videos);
        dbDelta($sql_categories);
        dbDelta($sql_relations);
        dbDelta($sql_access);
        dbDelta($sql_analytics);

        // Update database version
        update_option('vlp_db_version', '1.0.0');
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'library_page_id' => 0,
            'preview_duration' => 30, // 30 seconds preview
            'bunny_stream_enabled' => false,
            'bunny_library_id' => '',
            'bunny_api_key' => '',
            'bunny_cdn_hostname' => '',
            'presto_player_enabled' => false,
            'giftcode_integration' => true,
            'default_protection_level' => 'free',
            'site_wide_protection' => false,
            'site_protection_codes' => array(),
            'enable_analytics' => true,
            'auto_generate_previews' => true
        );

        add_option('vlp_settings', $default_options);
    }

    /**
     * Create video library page
     */
    private function create_video_library_page() {
        $page_slug = 'video-library';
        $page_exists = get_page_by_path($page_slug);

        if (!$page_exists) {
            $page_data = array(
                'post_title'   => __('Bibliothèque Vidéo', 'video-library-protect'),
                'post_content' => '[vlp_video_library]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $page_slug
            );

            $page_id = wp_insert_post($page_data);
            
            if ($page_id) {
                $settings = get_option('vlp_settings');
                $settings['library_page_id'] = $page_id;
                update_option('vlp_settings', $settings);
            }
        }
    }
}

/**
 * The class responsible for defining all actions that occur upon plugin activation.
 */
require_once VLP_PLUGIN_DIR . 'includes/class-vlp-activator.php';

/**
 * The class responsible for defining all actions that occur upon plugin deactivation.
 */
require_once VLP_PLUGIN_DIR . 'includes/class-vlp-deactivator.php';

/**
 * System diagnostic utilities
 */
if (is_admin()) {
    require_once VLP_PLUGIN_DIR . 'includes/class-vlp-diagnostic.php';
}

/**
 * Initialize the plugin
 */
function vlp_init() {
    return Video_Library_Protect::get_instance();
}

// Start the plugin
vlp_init();