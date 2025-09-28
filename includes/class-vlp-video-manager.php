<?php
/**
 * Video Manager Class
 *
 * Handles all video-related operations including CRUD, metadata, and file management
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Video_Manager {

    /**
     * Instance of this class
     *
     * @var VLP_Video_Manager
     */
    private static $instance = null;

    /**
     * Database table name
     *
     * @var string
     */
    private $videos_table;
    private $categories_table;
    private $relations_table;

    /**
     * Get instance
     *
     * @return VLP_Video_Manager
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
        global $wpdb;
        
        $this->videos_table = $wpdb->prefix . 'vlp_videos';
        $this->categories_table = $wpdb->prefix . 'vlp_categories';
        $this->relations_table = $wpdb->prefix . 'vlp_video_categories';
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_video_post_type'));
        add_action('wp_ajax_vlp_upload_video', array($this, 'handle_video_upload'));
        add_action('wp_ajax_vlp_generate_preview', array($this, 'generate_video_preview'));
    }

    /**
     * Register custom post type for videos (for WordPress integration)
     */
    public function register_video_post_type() {
        $args = array(
            'label' => __('Videos', 'video-library-protect'),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        );
        
        register_post_type('vlp_video', $args);
    }

    /**
     * Create a new video
     *
     * @param array $video_data Video data
     * @return int|false Video ID on success, false on failure
     */
    public function create_video($video_data) {
        global $wpdb;

        error_log('VLP Video Manager Debug: create_video called with data: ' . print_r($video_data, true));
        
        // Check if table exists before attempting to insert
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->videos_table}'");
        error_log('VLP Video Manager Debug: Videos table exists for create: ' . ($table_exists ? 'YES' : 'NO'));
        
        if (!$table_exists) {
            error_log('VLP Video Manager Debug: Cannot create video - table does not exist');
            return false;
        }

        $defaults = array(
            'title' => '',
            'slug' => '',
            'description' => '',
            'preview_video_url' => '',
            'full_video_url' => '',
            'bunny_preview_guid' => '',
            'bunny_full_guid' => '',
            'thumbnail_url' => '',
            'duration' => 0,
            'file_size' => 0,
            'protection_level' => 'free',
            'protection_data' => '',
            'status' => 'published'
        );

        $video_data = wp_parse_args($video_data, $defaults);

        // Generate slug if not provided
        if (empty($video_data['slug'])) {
            $video_data['slug'] = sanitize_title($video_data['title']);
        }

        // Ensure unique slug
        $video_data['slug'] = $this->get_unique_slug($video_data['slug']);

        // Sanitize data
        $video_data = $this->sanitize_video_data($video_data);

        // Serialize protection data if it's an array
        if (is_array($video_data['protection_data'])) {
            $video_data['protection_data'] = serialize($video_data['protection_data']);
        }

        $result = $wpdb->insert(
            $this->videos_table,
            $video_data,
            array(
                '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%d', '%d', 
                '%s', '%s', '%s'
            )
        );

        if ($result !== false) {
            $video_id = $wpdb->insert_id;
            
            // Create WordPress post for SEO and integration
            $this->create_wordpress_post($video_id, $video_data);
            
            return $video_id;
        }

        return false;
    }

    /**
     * Get video by ID
     *
     * @param int $video_id Video ID
     * @return object|null Video object or null
     */
    public function get_video($video_id) {
        global $wpdb;

        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->videos_table} WHERE id = %d",
            $video_id
        ));

        if ($video) {
            // Unserialize protection data
            if (!empty($video->protection_data)) {
                $video->protection_data = maybe_unserialize($video->protection_data);
            }
            
            // Get categories
            $video->categories = $this->get_video_categories($video_id);
        }

        return $video;
    }

    /**
     * Get video by slug
     *
     * @param string $slug Video slug
     * @return object|null Video object or null
     */
    public function get_video_by_slug($slug) {
        global $wpdb;

        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->videos_table} WHERE slug = %s AND status = 'published'",
            $slug
        ));

        if ($video) {
            if (!empty($video->protection_data)) {
                $video->protection_data = maybe_unserialize($video->protection_data);
            }
            $video->categories = $this->get_video_categories($video->id);
        }

        return $video;
    }

    /**
     * Get videos with filters
     *
     * @param array $args Query arguments
     * @return array Array of video objects
     */
    public function get_videos($args = array()) {
        global $wpdb;

        // Debug: Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->videos_table}'");
        error_log('VLP Video Manager Debug: Table ' . $this->videos_table . ' exists: ' . ($table_exists ? 'YES' : 'NO'));
        
        if (!$table_exists) {
            error_log('VLP Video Manager Debug: Videos table does not exist, returning empty array');
            return array();
        }

        $defaults = array(
            'category_id'       => array(),
            'category_slug'     => '',
            'protection_level'  => '',
            'status'            => 'published',
            'search'            => '',
            'orderby'           => 'created_at',
            'order'             => 'DESC',
            'limit'             => 20,
            'offset'            => 0,
            'exclude'           => array(),
        );

        $args = wp_parse_args($args, $defaults);
        $args = $this->normalize_video_query_args($args);

        if (!empty($args['category_slug']) && empty($args['category_id'])) {
            $category_id = $this->get_category_id_by_slug($args['category_slug']);
            if ($category_id) {
                $args['category_id'] = array($category_id);
            }
        }

        list($where_clauses, $where_values) = $this->build_videos_where_clause($args);

        $orderby = $this->sanitize_orderby($args['orderby']);
        $order   = $this->sanitize_order($args['order']);

        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s ORDER BY %s %s%s',
            $this->videos_table,
            implode(' AND ', $where_clauses),
            $orderby,
            $order,
            $limit_sql
        );

        $videos = $wpdb->get_results($wpdb->prepare($sql, $where_values));

        // Debug logging
        error_log('VLP Video Manager Debug: SQL Query: ' . $wpdb->prepare($sql, $where_values));
        error_log('VLP Video Manager Debug: Found ' . count($videos) . ' videos');
        if ($wpdb->last_error) {
            error_log('VLP Video Manager Debug: SQL Error: ' . $wpdb->last_error);
        }

        foreach ($videos as $video) {
            if (!empty($video->protection_data)) {
                $video->protection_data = maybe_unserialize($video->protection_data);
            }
            $video->categories = $this->get_video_categories($video->id);
        }

        return $videos;
    }

    /**
     * Count videos matching the provided filters.
     *
     * @param array $args Query arguments.
     * @return int
     */
    public function count_videos($args = array()) {
        global $wpdb;

        $defaults = array(
            'category_id'      => array(),
            'category_slug'    => '',
            'protection_level' => '',
            'status'           => 'published',
            'search'           => '',
            'exclude'          => array(),
        );

        $args = wp_parse_args($args, $defaults);
        $args = $this->normalize_video_query_args($args);

        if (!empty($args['category_slug']) && empty($args['category_id'])) {
            $category_id = $this->get_category_id_by_slug($args['category_slug']);
            if ($category_id) {
                $args['category_id'] = array($category_id);
            }
        }

        list($where_clauses, $where_values) = $this->build_videos_where_clause($args);

        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE %s',
            $this->videos_table,
            implode(' AND ', $where_clauses)
        );

        return (int) $wpdb->get_var($wpdb->prepare($sql, $where_values));
    }

    /**
     * Retrieve a category ID from its slug.
     *
     * @param string $slug Category slug.
     * @return int|null
     */
    public function get_category_id_by_slug($slug) {
        global $wpdb;

        if (empty($slug)) {
            return null;
        }

        $slug = sanitize_title($slug);

        $category_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->categories_table} WHERE slug = %s",
            $slug
        ));

        return $category_id ? intval($category_id) : null;
    }

    /**
     * Update video
     *
     * @param int $video_id Video ID
     * @param array $video_data Updated video data
     * @return bool Success
     */
    public function update_video($video_id, $video_data) {
        global $wpdb;

        // Remove fields that shouldn't be updated
        unset($video_data['id'], $video_data['created_at']);

        // Sanitize data
        $video_data = $this->sanitize_video_data($video_data);

        // Serialize protection data if needed
        if (isset($video_data['protection_data']) && is_array($video_data['protection_data'])) {
            $video_data['protection_data'] = serialize($video_data['protection_data']);
        }

        $video_data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->videos_table,
            $video_data,
            array('id' => $video_id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete video
     *
     * @param int $video_id Video ID
     * @return bool Success
     */
    public function delete_video($video_id) {
        global $wpdb;

        // Delete video record
        $result = $wpdb->delete(
            $this->videos_table,
            array('id' => $video_id),
            array('%d')
        );

        if ($result !== false) {
            // Delete category relations
            $wpdb->delete(
                $this->relations_table,
                array('video_id' => $video_id),
                array('%d')
            );

            // Delete WordPress post if exists
            $post_id = $this->get_video_post_id($video_id);
            if ($post_id) {
                wp_delete_post($post_id, true);
            }
        }

        return $result !== false;
    }

    /**
     * Increment video views
     *
     * @param int $video_id Video ID
     */
    public function increment_views($video_id) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->videos_table} SET views_count = views_count + 1 WHERE id = %d",
            $video_id
        ));
    }

    /**
     * Get video categories
     *
     * @param int $video_id Video ID
     * @return array Array of category objects
     */
    public function get_video_categories($video_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM {$this->categories_table} c 
             INNER JOIN {$this->relations_table} r ON c.id = r.category_id 
             WHERE r.video_id = %d 
             ORDER BY c.name",
            $video_id
        ));
    }

    /**
     * Sync video categories with the provided list.
     *
     * @param int   $video_id     Video ID.
     * @param array $category_ids Array of category IDs.
     * @return bool
     */
    public function set_video_categories($video_id, $category_ids = array()) {
        global $wpdb;

        $video_id = intval($video_id);
        if ($video_id <= 0) {
            return false;
        }

        $category_ids = array_filter(array_map('intval', (array) $category_ids));

        $existing     = $this->get_video_categories($video_id);
        $existing_ids = wp_list_pluck($existing, 'id');

        $to_remove = array_diff($existing_ids, $category_ids);
        $to_add    = array_diff($category_ids, $existing_ids);

        foreach ($to_remove as $cat_id) {
            $this->remove_video_from_category($video_id, $cat_id);
        }

        foreach ($to_add as $cat_id) {
            $this->add_video_to_category($video_id, $cat_id);
        }

        // Handle case where all categories are removed
        if (empty($category_ids) && !empty($existing_ids)) {
            $wpdb->delete(
                $this->relations_table,
                array('video_id' => $video_id),
                array('%d')
            );
        }

        return true;
    }

    /**
     * Add video to category
     *
     * @param int $video_id Video ID
     * @param int $category_id Category ID
     * @return bool Success
     */
    public function add_video_to_category($video_id, $category_id) {
        global $wpdb;

        return $wpdb->insert(
            $this->relations_table,
            array(
                'video_id' => $video_id,
                'category_id' => $category_id
            ),
            array('%d', '%d')
        ) !== false;
    }

    /**
     * Remove video from category
     *
     * @param int $video_id Video ID
     * @param int $category_id Category ID
     * @return bool Success
     */
    public function remove_video_from_category($video_id, $category_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->relations_table,
            array(
                'video_id' => $video_id,
                'category_id' => $category_id
            ),
            array('%d', '%d')
        ) !== false;
    }

    /**
     * Generate unique slug
     *
     * @param string $slug Base slug
     * @return string Unique slug
     */
    private function get_unique_slug($slug) {
        global $wpdb;

        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->videos_table} WHERE slug = %s",
                $slug
            ));

            if (!$exists) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Normalize incoming query arguments to consistent formats.
     *
     * @param array $args Incoming arguments.
     * @return array
     */
    private function normalize_video_query_args($args) {
        if (!isset($args['category_id'])) {
            $args['category_id'] = array();
        }

        if (!is_array($args['category_id'])) {
            $args['category_id'] = array_filter(array_map('intval', (array) $args['category_id']));
        } else {
            $args['category_id'] = array_filter(array_map('intval', $args['category_id']));
        }

        if (!isset($args['exclude'])) {
            $args['exclude'] = array();
        }

        if (!is_array($args['exclude'])) {
            $args['exclude'] = array_filter(array_map('intval', (array) $args['exclude']));
        } else {
            $args['exclude'] = array_filter(array_map('intval', $args['exclude']));
        }

        $args['status'] = !empty($args['status']) ? sanitize_text_field($args['status']) : 'published';

        if (!empty($args['protection_level'])) {
            $args['protection_level'] = sanitize_text_field($args['protection_level']);
        }

        if (!empty($args['search'])) {
            $args['search'] = sanitize_text_field($args['search']);
        }

        if (!empty($args['category_slug'])) {
            $args['category_slug'] = sanitize_title($args['category_slug']);
        }

        if (isset($args['limit'])) {
            $args['limit'] = intval($args['limit']);
        }

        if (isset($args['offset'])) {
            $args['offset'] = max(0, intval($args['offset']));
        }

        if (!empty($args['orderby'])) {
            $args['orderby'] = sanitize_key($args['orderby']);
        }

        if (!empty($args['order'])) {
            $args['order'] = strtoupper(sanitize_text_field($args['order']));
        }

        return $args;
    }

    /**
     * Build WHERE clause components for a video query.
     *
     * @param array $args Normalized arguments.
     * @return array
     */
    private function build_videos_where_clause($args) {
        global $wpdb;

        $where_clauses = array('status = %s');
        $where_values  = array($args['status']);

        if (!empty($args['category_id'])) {
            $placeholders = implode(',', array_fill(0, count($args['category_id']), '%d'));
            $where_clauses[] = "id IN (SELECT video_id FROM {$this->relations_table} WHERE category_id IN ({$placeholders}))";
            $where_values    = array_merge($where_values, $args['category_id']);
        }

        if (!empty($args['protection_level'])) {
            $where_clauses[] = 'protection_level = %s';
            $where_values[]  = $args['protection_level'];
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = '(title LIKE %s OR description LIKE %s)';
            $where_values[]  = $like;
            $where_values[]  = $like;
        }

        if (!empty($args['exclude'])) {
            $placeholders    = implode(',', array_fill(0, count($args['exclude']), '%d'));
            $where_clauses[] = "id NOT IN ({$placeholders})";
            $where_values    = array_merge($where_values, $args['exclude']);
        }

        return array($where_clauses, $where_values);
    }

    /**
     * Sanitize an order by column.
     *
     * @param string $orderby Requested orderby column.
     * @return string
     */
    private function sanitize_orderby($orderby) {
        $allowed = array('created_at', 'updated_at', 'views_count', 'title');
        $orderby = sanitize_key($orderby);

        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'created_at';
        }

        return $orderby;
    }

    /**
     * Sanitize an SQL order direction.
     *
     * @param string $order Requested order.
     * @return string
     */
    private function sanitize_order($order) {
        $order = strtoupper($order);
        return in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';
    }

    /**
     * Sanitize video data
     *
     * @param array $data Video data
     * @return array Sanitized data
     */
    private function sanitize_video_data($data) {
        $sanitized = array();

        $text_fields = array('title', 'slug', 'bunny_preview_guid', 'bunny_full_guid', 'protection_level', 'status');
        $url_fields  = array('preview_video_url', 'full_video_url', 'thumbnail_url');
        $textarea_fields = array('description');
        $int_fields = array('duration', 'file_size');

        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }

        foreach ($url_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = esc_url_raw($data[$field]);
            }
        }

        foreach ($textarea_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_textarea_field($data[$field]);
            }
        }

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = intval($data[$field]);
            }
        }

        if (isset($data['protection_data'])) {
            $sanitized['protection_data'] = $data['protection_data']; // Will be serialized later
        }

        return $sanitized;
    }

    /**
     * Create WordPress post for SEO
     *
     * @param int $video_id Video ID
     * @param array $video_data Video data
     */
    private function create_wordpress_post($video_id, $video_data) {
        $post_data = array(
            'post_title' => $video_data['title'],
            'post_content' => $video_data['description'],
            'post_status' => $video_data['status'] === 'published' ? 'publish' : 'draft',
            'post_type' => 'vlp_video',
            'meta_input' => array(
                '_vlp_video_id' => $video_id,
                '_vlp_video_slug' => $video_data['slug']
            )
        );

        $post_id = wp_insert_post($post_data);
        
        if ($post_id) {
            // Update video record with WordPress post ID
            global $wpdb;
            $wpdb->update(
                $this->videos_table,
                array('wp_post_id' => $post_id),
                array('id' => $video_id),
                array('%d'),
                array('%d')
            );
        }
    }

    /**
     * Get WordPress post ID for video
     *
     * @param int $video_id Video ID
     * @return int|false Post ID or false
     */
    private function get_video_post_id($video_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT wp_post_id FROM {$this->videos_table} WHERE id = %d",
            $video_id
        ));
    }
}