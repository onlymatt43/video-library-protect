<?php
/**
 * Analytics Manager Class
 *
 * Provides helper methods for tracking and retrieving video engagement analytics.
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Analytics {

    /**
     * Singleton instance
     *
     * @var VLP_Analytics|null
     */
    private static $instance = null;

    /**
     * Analytics table name
     *
     * @var string
     */
    private $analytics_table;

    /**
     * Videos table name
     *
     * @var string
     */
    private $videos_table;

    /**
     * Get instance
     *
     * @return VLP_Analytics
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

        $this->analytics_table = $wpdb->prefix . 'vlp_analytics';
        $this->videos_table    = $wpdb->prefix . 'vlp_videos';
    }

    /**
     * Determine if analytics tracking is enabled in the plugin settings.
     *
     * @return bool
     */
    public function is_enabled() {
        $settings = get_option('vlp_settings', array());
        return !empty($settings['enable_analytics']);
    }

    /**
     * Track a video analytics event.
     *
     * @param int    $video_id   Video ID.
     * @param string $event_type Event type (play, pause, complete, etc.).
     * @param array  $payload    Optional additional payload data.
     * @return bool True on success, false otherwise.
     */
    public function track_event($video_id, $event_type, $payload = array()) {
        if (!$this->is_enabled()) {
            return false;
        }

        global $wpdb;

        $video_id   = intval($video_id);
        $event_type = sanitize_text_field($event_type);

        if ($video_id <= 0 || empty($event_type)) {
            return false;
        }

        $user_id    = get_current_user_id() ?: null;
        $session_id = sanitize_text_field($payload['session_id'] ?? '');
        $event_data = isset($payload['event_data']) ? wp_json_encode($payload['event_data']) : null;
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $inserted = $wpdb->insert(
            $this->analytics_table,
            array(
                'video_id'   => $video_id,
                'user_id'    => $user_id,
                'session_id' => $session_id,
                'event_type' => $event_type,
                'event_data' => $event_data,
                'timestamp'  => current_time('mysql'),
                'ip_address' => $ip_address,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        return $inserted !== false;
    }

    /**
     * Retrieve the most recent analytics events.
     *
     * @param int $limit Number of events to retrieve.
     * @return array
     */
    public function get_recent_activity($limit = 10) {
        global $wpdb;

        $limit = max(1, intval($limit));

        $sql = $wpdb->prepare(
            "SELECT a.*, v.title AS video_title
             FROM {$this->analytics_table} a
             LEFT JOIN {$this->videos_table} v ON a.video_id = v.id
             ORDER BY a.timestamp DESC
             LIMIT %d",
            $limit
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get a summary of views per day for the provided number of days.
     *
     * @param int $days Number of days to include.
     * @return array Array of arrays with keys day (Y-m-d) and views.
     */
    public function get_views_by_day($days = 7) {
        global $wpdb;

        $days = max(1, intval($days));

        $sql = $wpdb->prepare(
            "SELECT DATE(timestamp) AS day, COUNT(*) AS views
             FROM {$this->analytics_table}
             WHERE event_type = %s AND timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(timestamp)
             ORDER BY DATE(timestamp) ASC",
            'play',
            $days
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'day'   => $row['day'],
                'views' => intval($row['views']),
            );
        }

        return $results;
    }

    /**
     * Get the most viewed videos within the analytics table.
     *
     * @param int $limit Number of videos to retrieve.
     * @return array
     */
    public function get_popular_videos($limit = 5) {
        global $wpdb;

        $limit = max(1, intval($limit));

        $sql = $wpdb->prepare(
            "SELECT v.id, v.title, COUNT(*) AS view_count
             FROM {$this->analytics_table} a
             INNER JOIN {$this->videos_table} v ON a.video_id = v.id
             WHERE a.event_type = %s
             GROUP BY v.id
             ORDER BY view_count DESC
             LIMIT %d",
            'play',
            $limit
        );

        return $wpdb->get_results($sql);
    }
}
