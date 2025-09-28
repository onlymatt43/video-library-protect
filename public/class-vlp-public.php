<?php
/**
 * Public Bootstrap Class
 *
 * Registers AJAX endpoints and shared helpers for the public-facing portion of
 * the plugin (video library loading, analytics tracking, etc.).
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Public {

    /**
     * Singleton instance
     *
     * @var VLP_Public|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return VLP_Public
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
        add_action('init', array($this, 'maybe_start_session'));

        add_action('wp_ajax_vlp_load_videos', array($this, 'ajax_load_videos'));
        add_action('wp_ajax_nopriv_vlp_load_videos', array($this, 'ajax_load_videos'));

        add_action('wp_ajax_vlp_track_video_event', array($this, 'ajax_track_video_event'));
        add_action('wp_ajax_nopriv_vlp_track_video_event', array($this, 'ajax_track_video_event'));

        add_action('wp_ajax_vlp_check_access_status', array($this, 'ajax_check_access_status'));
        add_action('wp_ajax_nopriv_vlp_check_access_status', array($this, 'ajax_check_access_status'));
    }

    /**
     * Ensure a PHP session exists for storing temporary access tokens.
     */
    public function maybe_start_session() {
        if (is_admin()) {
            return;
        }

        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    /**
     * AJAX handler: dynamic video library loading.
     */
    public function ajax_load_videos() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');

    $page     = max(1, intval($_POST['page'] ?? 1));
    $per_page = max(1, intval($_POST['per_page'] ?? 12));
        $per_page = min($per_page, 48);

        $search          = sanitize_text_field($_POST['search'] ?? '');
        $category_slug   = sanitize_text_field($_POST['category'] ?? '');
        $protection_level = sanitize_text_field($_POST['protection'] ?? '');

        $video_manager = VLP_Video_Manager::get_instance();

        $args = array(
            'status'           => 'published',
            'limit'            => $per_page,
            'offset'           => ($page - 1) * $per_page,
            'search'           => $search,
            'protection_level' => $protection_level,
        );

        if (!empty($category_slug)) {
            $category_id = $video_manager->get_category_id_by_slug($category_slug);
            if ($category_id) {
                $args['category_id'] = $category_id;
            } else {
                wp_send_json_success(array(
                    'html'       => $this->render_no_videos_notice(),
                    'pagination' => '',
                    'total'      => 0,
                ));
            }
        }

        $videos = $video_manager->get_videos($args);
        $total  = $video_manager->count_videos($args);

        $html       = $this->render_videos_html($videos);
        $pagination = $this->render_pagination($page, $per_page, $total);

        wp_send_json_success(array(
            'html'       => $html,
            'pagination' => $pagination,
            'total'      => intval($total),
        ));
    }

    /**
     * AJAX handler: store analytics events sent from the frontend.
     */
    public function ajax_track_video_event() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');

        $analytics = VLP_Analytics::get_instance();
        if (!$analytics->is_enabled()) {
            wp_send_json_success();
        }

        $video_id   = intval($_POST['video_id'] ?? 0);
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if ($video_id <= 0 || empty($event_type)) {
            wp_send_json_error(array('message' => __('Requête invalide.', 'video-library-protect')));
        }

        $analytics->track_event($video_id, $event_type, array(
            'session_id' => $session_id,
            'event_data' => array(
                'timestamp' => sanitize_text_field($_POST['timestamp'] ?? ''),
            ),
        ));

        wp_send_json_success();
    }

    /**
     * AJAX handler: poll whether the user/session has unlocked the specified video.
     */
    public function ajax_check_access_status() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');

        $video_id   = intval($_POST['video_id'] ?? 0);
        $session_id = sanitize_text_field($_POST['session_id'] ?? $this->get_session_id());
        $user_id    = get_current_user_id() ?: null;

        if ($video_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant vidéo manquant.', 'video-library-protect')));
        }

        $protection_manager = VLP_Protection_Manager::get_instance();
        $access_level       = $protection_manager->check_video_access($video_id, $user_id, $session_id);
        $has_access         = $access_level === VLP_Protection_Manager::ACCESS_FULL_VIDEO;

        wp_send_json_success(array('has_access' => $has_access));
    }

    /**
     * Build HTML for the list of video cards.
     *
     * @param array $videos Array of video objects.
     * @return string
     */
    private function render_videos_html($videos) {
        if (empty($videos)) {
            return $this->render_no_videos_notice();
        }

        $output      = '';
        $session_id  = $this->get_session_id();
        $user_id     = get_current_user_id() ?: null;
        $protection  = VLP_Protection_Manager::get_instance();

        foreach ($videos as $video) {
            $access_level = $protection->check_video_access($video->id, $user_id, $session_id);
            
            // Debug logging
            error_log("VLP: Video {$video->id} '{$video->title}' - Protection: {$video->protection_level}, Access: {$access_level}, FULL_ACCESS_CONST: " . VLP_Protection_Manager::ACCESS_FULL_VIDEO);
            
            $output      .= $this->render_video_card($video, $access_level);
        }

        return $output;
    }

    /**
     * Generate the HTML for a single video card.
     *
     * @param object $video        Video record.
     * @param string $access_level Access level for the current viewer.
     * @return string
     */
    private function render_video_card($video, $access_level) {
        $title        = esc_html($video->title);
        $thumbnail    = !empty($video->thumbnail_url) ? esc_url($video->thumbnail_url) : '';
        $video_url    = esc_url($this->get_video_url($video->slug));
        $categories   = $this->format_categories($video);
        $duration     = intval($video->duration);
        $views        = intval($video->views_count);
        $is_protected = $access_level !== VLP_Protection_Manager::ACCESS_FULL_VIDEO;
        
        // Debug logging for protection check
        error_log("VLP: render_video_card - Video {$video->id}, access_level='{$access_level}', ACCESS_FULL_VIDEO='" . VLP_Protection_Manager::ACCESS_FULL_VIDEO . "', is_protected=" . ($is_protected ? 'true' : 'false'));
        
        $badge        = $this->get_protection_badge($video->protection_level);

        ob_start();
        ?>
        <div class="vlp-video-card<?php echo $is_protected ? ' vlp-protected' : ''; ?>" data-video-id="<?php echo esc_attr($video->id); ?>" data-video-url="<?php echo esc_attr($video_url); ?>">
            <div class="vlp-video-thumbnail">
                <?php if ($thumbnail) : ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo $title; ?>">
                <?php else : ?>
                    <div class="vlp-video-thumbnail-placeholder">
                        <span>🎬</span>
                    </div>
                <?php endif; ?>

                <div class="vlp-video-overlay">
                    <button type="button" class="vlp-play-button" aria-label="<?php esc_attr_e('Lire la vidéo', 'video-library-protect'); ?>">▶</button>
                </div>

                <span class="vlp-protection-badge <?php echo esc_attr($badge['class']); ?>">
                    <?php echo esc_html($badge['label']); ?>
                </span>
            </div>

            <div class="vlp-video-content">
                <h3 class="vlp-video-title"><?php echo $title; ?></h3>

                <?php if (!empty($video->description)) : ?>
                    <p class="vlp-video-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($video->description), 24)); ?></p>
                <?php endif; ?>

                <div class="vlp-video-meta">
                    <?php if ($duration > 0) : ?>
                        <span class="vlp-video-duration">⏱ <?php echo esc_html($this->format_duration($duration)); ?></span>
                    <?php endif; ?>

                    <span class="vlp-video-views">👁 <?php echo esc_html(number_format_i18n($views)); ?></span>
                </div>

                <?php if ($categories) : ?>
                    <div class="vlp-video-categories"><?php echo $categories; ?></div>
                <?php endif; ?>

                <div class="vlp-video-actions">
                    <?php if ($is_protected) : ?>
                        <button type="button" class="vlp-btn vlp-btn-secondary vlp-unlock-trigger" data-video-id="<?php echo esc_attr($video->id); ?>">
                            🔒 <?php esc_html_e('Déverrouiller', 'video-library-protect'); ?>
                        </button>
                    <?php else : ?>
                        <a class="vlp-btn vlp-btn-primary" href="<?php echo $video_url; ?>">
                            ▶ <?php esc_html_e('Regarder', 'video-library-protect'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pagination links for the video library.
     *
     * @param int $page      Current page number.
     * @param int $per_page  Items per page.
     * @param int $total     Total items.
     * @return string
     */
    private function render_pagination($page, $per_page, $total) {
        $total_pages = (int) ceil($total / max(1, $per_page));

        if ($total_pages <= 1) {
            return '';
        }

        $links = '';

        for ($i = 1; $i <= $total_pages; $i++) {
            $classes = array('page-numbers');
            if ($i === $page) {
                $classes[] = 'current';
            }

            $links .= sprintf(
                '<a href="#" class="%1$s" data-page="%2$d">%2$d</a>',
                esc_attr(implode(' ', $classes)),
                $i
            );
        }

        return $links;
    }

    /**
     * Render a friendly empty state message.
     *
     * @return string
     */
    private function render_no_videos_notice() {
        return '<div class="vlp-message vlp-message-info">' . esc_html__('Aucune vidéo trouvée pour ces critères.', 'video-library-protect') . '</div>';
    }

    /**
     * Format category labels for a video.
     *
     * @param object $video Video record.
     * @return string
     */
    private function format_categories($video) {
        if (empty($video->categories)) {
            return '';
        }

        $items = array();
        foreach ($video->categories as $category) {
            $label = esc_html($category->name);
            if ($category->protection_level !== VLP_Protection_Manager::PROTECTION_FREE) {
                $label .= ' 🔒';
            }
            $items[] = '<span class="vlp-category-tag">' . $label . '</span>';
        }

        return implode('', $items);
    }

    /**
     * Retrieve the visitor's session identifier.
     *
     * @return string
     */
    private function get_session_id() {
        if (!session_id()) {
            $this->maybe_start_session();
        }

        return session_id();
    }

    /**
     * Determine the appropriate protection badge label/class.
     *
     * @param string $protection_level Protection level key.
     * @return array
     */
    private function get_protection_badge($protection_level) {
        $map = array(
            VLP_Protection_Manager::PROTECTION_FREE       => array(
                'label' => __('Libre', 'video-library-protect'),
                'class' => 'vlp-protection-free',
            ),
            VLP_Protection_Manager::PROTECTION_GIFT_CODE  => array(
                'label' => __('Code requis', 'video-library-protect'),
                'class' => 'vlp-protection-gift-code',
            ),
            VLP_Protection_Manager::PROTECTION_CATEGORY   => array(
                'label' => __('Par catégorie', 'video-library-protect'),
                'class' => 'vlp-protection-category',
            ),
            VLP_Protection_Manager::PROTECTION_SITE_WIDE  => array(
                'label' => __('Site protégé', 'video-library-protect'),
                'class' => 'vlp-protection-site',
            ),
        );

        return $map[$protection_level] ?? array(
            'label' => __('Protégé', 'video-library-protect'),
            'class' => 'vlp-protection-gift-code',
        );
    }

    /**
     * Format a raw duration in seconds to a human readable string.
     *
     * @param int $seconds Duration in seconds.
     * @return string
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;

        if ($minutes < 60) {
            return $remaining_seconds > 0 ? sprintf('%dm %ds', $minutes, $remaining_seconds) : sprintf('%dm', $minutes);
        }

        $hours            = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($remaining_minutes === 0 && $remaining_seconds === 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dh %dm', $hours, $remaining_minutes);
    }

    /**
     * Build the permalink for a single video entry.
     *
     * @param string $slug Video slug.
     * @return string
     */
    private function get_video_url($slug) {
        $settings       = get_option('vlp_settings', array());
        $library_page_id = isset($settings['library_page_id']) ? intval($settings['library_page_id']) : 0;
        
        error_log("VLP_DEBUG: get_video_url for slug '{$slug}' - library_page_id: {$library_page_id}");
        error_log("VLP_DEBUG: settings: " . print_r($settings, true));

        if ($library_page_id > 0) {
            $permalink = get_permalink($library_page_id);
            $video_url = add_query_arg('video', $slug, $permalink);
            error_log("VLP_DEBUG: Using library page - permalink: {$permalink}, final URL: {$video_url}");
            return $video_url;
        }

        $fallback_url = add_query_arg('vlp_video', $slug, home_url('/'));
        error_log("VLP_DEBUG: No library page set, using fallback URL: {$fallback_url}");
        return $fallback_url;
    }
}
