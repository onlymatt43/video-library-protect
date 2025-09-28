<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Video_Library_Protect
 * @subpackage Video_Library_Protect/includes
 */
class VLP_Activator {

    /**
     * Main activation method.
     *
     * @since 1.0.0
     */
    public static function activate() {
        self::create_database_tables();
        self::set_default_options();
        self::create_pages();
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables.
     *
     * @since 1.0.0
     */
    private static function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Define all tables and their SQL
        $tables = [
            $wpdb->prefix . 'vlp_videos' => "CREATE TABLE {$wpdb->prefix}vlp_videos (
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
                UNIQUE KEY slug (slug)
            ) $charset_collate;",
            $wpdb->prefix . 'vlp_categories' => "CREATE TABLE {$wpdb->prefix}vlp_categories (
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
            ) $charset_collate;",
            $wpdb->prefix . 'vlp_video_categories' => "CREATE TABLE {$wpdb->prefix}vlp_video_categories (
                video_id bigint(20) NOT NULL,
                category_id bigint(20) NOT NULL,
                PRIMARY KEY (video_id, category_id)
            ) $charset_collate;",
            $wpdb->prefix . 'vlp_access_log' => "CREATE TABLE {$wpdb->prefix}vlp_access_log (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                video_id bigint(20),
                user_id bigint(20),
                session_id varchar(64),
                gift_code varchar(100),
                access_type varchar(20),
                granted_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime,
                ip_address varchar(45),
                PRIMARY KEY (id)
            ) $charset_collate;",
            $wpdb->prefix . 'vlp_analytics' => "CREATE TABLE {$wpdb->prefix}vlp_analytics (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                video_id bigint(20) NOT NULL,
                event_type varchar(50) NOT NULL,
                event_data longtext,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
        ];

        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }

        update_option('vlp_db_version', VLP_VERSION);
    }

    /**
     * Set default plugin options.
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        $default_options = [
            'library_page_id' => 0,
            'categories_page_id' => 0,
            'preview_duration' => 30,
            'bunny_stream_enabled' => false,
            'giftcode_integration' => true,
            'default_protection_level' => 'free',
            'enable_analytics' => true,
        ];
        add_option('vlp_settings', $default_options, '', 'no');
    }

    /**
     * Create necessary pages on activation.
     *
     * @since 1.0.0
     */
    private static function create_pages() {
        $pages = [
            'library_page_id' => [
                'title'   => __('Bibliothèque de Vidéos', 'video-library-protect'),
                'slug'    => 'bibliotheque-videos',
                'content' => '<!-- wp:heading --><h2 class="wp-block-heading">Notre Bibliothèque</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Parcourez toutes nos vidéos.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[vlp_video_library]<!-- /wp:shortcode -->',
            ],
            'categories_page_id' => [
                'title'   => __('Catégories de Vidéos', 'video-library-protect'),
                'slug'    => 'categories-videos',
                'content' => '<!-- wp:heading --><h2 class="wp-block-heading">Catégories</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Explorez nos vidéos par catégorie.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[vlp_video_categories]<!-- /wp:shortcode -->',
            ],
        ];

        $settings = get_option('vlp_settings');
        $updated = false;

        foreach ($pages as $option_key => $page_details) {
            // Check if page ID is already set and valid
            if (!empty($settings[$option_key]) && get_post($settings[$option_key])) {
                continue;
            }

            // Check if page exists by slug
            $page = get_page_by_path($page_details['slug'], OBJECT, 'page');

            if (!$page) {
                // Create the page
                $page_id = wp_insert_post([
                    'post_title'     => $page_details['title'],
                    'post_name'      => $page_details['slug'],
                    'post_content'   => $page_details['content'],
                    'post_status'    => 'publish',
                    'post_type'      => 'page',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                ]);

                if ($page_id && !is_wp_error($page_id)) {
                    $settings[$option_key] = $page_id;
                    $updated = true;
                }
            } else {
                // Page exists, just store its ID
                $settings[$option_key] = $page->ID;
                $updated = true;
            }
        }

        if ($updated) {
            update_option('vlp_settings', $settings);
        }
    }
}
