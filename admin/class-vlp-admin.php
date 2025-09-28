<?php
/**
 * Core Admin Bootstrap Class
 *
 * Provides shared admin utilities (global assets, AJAX helpers, etc.) so that
 * other admin-oriented classes can focus on their specific concerns.
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Admin {

    /**
     * Singleton instance
     *
     * @var VLP_Admin|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return VLP_Admin
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_global_assets'));
        add_action('wp_ajax_vlp_get_recent_activity', array($this, 'ajax_get_recent_activity'));
    }

    /**
     * Enqueue assets that are shared across the plugin's admin pages.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_global_assets($hook) {
        if (strpos($hook, 'vlp-') === false) {
            return;
        }

        // Allow third-parties to attach additional assets if required.
        do_action('vlp_admin_enqueue_global_assets', $hook);
    }

    /**
     * AJAX handler: recent analytics activity list.
     *
     * @return void
     */
    public function ajax_get_recent_activity() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'video-library-protect')));
        }

        $analytics = VLP_Analytics::get_instance();

        if (!$analytics->is_enabled()) {
            wp_send_json_success(array('html' => $this->render_notice(__('Les statistiques sont désactivées.', 'video-library-protect'), 'info')));
        }

        $activity = $analytics->get_recent_activity(15);
        $html     = $this->render_recent_activity($activity);

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Render recent activity HTML list.
     *
     * @param array $activity Activity rows returned by VLP_Analytics.
     * @return string
     */
    private function render_recent_activity($activity) {
        if (empty($activity)) {
            return $this->render_notice(__('Aucune activité récente.', 'video-library-protect'));
        }

        ob_start();
        ?>
        <ul class="vlp-activity-list">
            <?php foreach ($activity as $entry) :
                $video_title = !empty($entry->video_title) ? $entry->video_title : __('Vidéo supprimée', 'video-library-protect');
                $event_type  = sanitize_text_field($entry->event_type);
                $timestamp   = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->timestamp);
                ?>
                <li class="vlp-activity-item">
                    <span class="vlp-activity-title"><?php echo esc_html($video_title); ?></span>
                    <span class="vlp-activity-event"><?php echo esc_html($this->format_event_label($event_type)); ?></span>
                    <span class="vlp-activity-time"><?php echo esc_html($timestamp); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a basic notice block.
     *
     * @param string $message Message to display.
     * @param string $type    Notice type (success, info, warning, error).
     * @return string
     */
    private function render_notice($message, $type = 'info') {
        $type = in_array($type, array('success', 'info', 'warning', 'error'), true) ? $type : 'info';

        return sprintf(
            '<div class="notice notice-%1$s"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /**
     * Convert an event key into a human-friendly label.
     *
     * @param string $event_type Raw event type.
     * @return string
     */
    private function format_event_label($event_type) {
        $labels = array(
            'play'          => __('Lecture démarrée', 'video-library-protect'),
            'pause'         => __('Lecture en pause', 'video-library-protect'),
            'complete'      => __('Lecture terminée', 'video-library-protect'),
            'progress_25'   => __('25% visionné', 'video-library-protect'),
            'progress_50'   => __('50% visionné', 'video-library-protect'),
            'progress_75'   => __('75% visionné', 'video-library-protect'),
            'progress_100'  => __('100% visionné', 'video-library-protect'),
        );

        return $labels[$event_type] ?? ucwords(str_replace('_', ' ', $event_type));
    }
}
