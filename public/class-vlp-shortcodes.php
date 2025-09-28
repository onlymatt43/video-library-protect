<?php
/**
 * Shortcodes Class
 *
 * Handles all shortcodes for video library display and interaction
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Shortcodes {

    /**
     * Instance of this class
     *
     * @var VLP_Shortcodes
     */
    private static $instance = null;

    /**
     * Get instance
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
        $this->init_shortcodes();
        $this->init_hooks();
    }

    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        add_shortcode('vlp_video_library', array($this, 'render_video_library'));
        add_shortcode('vlp_single_video', array($this, 'render_single_video'));
        add_shortcode('vlp_video_categories', array($this, 'render_video_categories'));
        add_shortcode('vlp_protected_content', array($this, 'render_protected_content'));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_inline_styles'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script('vlp-public', VLP_PLUGIN_URL . 'public/js/vlp-public.js', array('jquery'), VLP_VERSION, true);
        wp_enqueue_style('vlp-public', VLP_PLUGIN_URL . 'public/css/vlp-public.css', array(), VLP_VERSION);

        $settings = get_option('vlp_settings', array());

        // Localize script
        wp_localize_script('vlp-public', 'vlp_public', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_ajax_nonce'),
            'session_id' => $this->get_session_id(),
            'per_page' => 12,
            'analytics_enabled' => !empty($settings['enable_analytics']),
            'auto_refresh' => true,
            'strings' => array(
                'loading' => __('Chargement...', 'video-library-protect'),
                'error' => __('Erreur lors du chargement.', 'video-library-protect'),
                'code_required' => __('Code d\'accès requis', 'video-library-protect'),
                'invalid_code' => __('Code invalide', 'video-library-protect'),
                'access_granted' => __('Accès accordé', 'video-library-protect')
            )
        ));
    }

    /**
     * Add inline styles for better integration
     */
    public function add_inline_styles() {
        ?>
        <style>
        .vlp-video-library {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .vlp-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .vlp-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        </style>
        <?php
    }

    /**
     * Render video library shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_video_library($atts) {
        // Debug: Check what query parameters are available
        error_log("VLP_DEBUG: render_video_library called");
        error_log("VLP_DEBUG: _GET parameters: " . print_r($_GET, true));
        error_log("VLP_DEBUG: get_query_var('video'): " . get_query_var('video'));
        error_log("VLP_DEBUG: get_query_var('vlp_video'): " . get_query_var('vlp_video'));
        
        // Check if we should display a single video instead
        $video_slug = get_query_var('video');
        if (empty($video_slug)) {
            $video_slug = isset($_GET['video']) ? sanitize_text_field($_GET['video']) : '';
        }
        
        // Also check for vlp_video parameter (fallback URL format)
        if (empty($video_slug)) {
            $video_slug = get_query_var('vlp_video');
            if (empty($video_slug)) {
                $video_slug = isset($_GET['vlp_video']) ? sanitize_text_field($_GET['vlp_video']) : '';
            }
        }
        
        error_log("VLP_DEBUG: Final video_slug detected: '{$video_slug}'");
        
        if (!empty($video_slug)) {
            error_log("VLP_DEBUG: Single video requested via query param: {$video_slug}");
            return $this->render_single_video(array('slug' => $video_slug));
        }
        
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 12,
            'columns' => 3,
            'show_categories' => 'true',
            'show_search' => 'true',
            'protection_level' => '',
            'layout' => 'grid', // grid, list, carousel
            'show_previews' => 'true'
        ), $atts, 'vlp_video_library');

        error_log("VLP_DEBUG: Rendering video library grid (no single video requested)");

        // Check site-wide protection first
        $protection_manager = VLP_Protection_Manager::get_instance();
        
        if ($protection_manager->is_site_wide_protected()) {
            $user_id = get_current_user_id() ?: null;
            $session_id = $this->get_session_id();
            
            if (!$protection_manager->check_site_wide_access($user_id, $session_id)) {
                return $this->render_site_protection_form();
            }
        }

        ob_start();
        ?>
        <div class="vlp-video-library" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            
            <?php if ($atts['show_search'] === 'true' || $atts['show_categories'] === 'true'): ?>
            <div class="vlp-library-filters">
                <?php if ($atts['show_search'] === 'true'): ?>
                <div class="vlp-search-container">
                    <input type="text" 
                           id="vlp-search-input" 
                           placeholder="<?php _e('Rechercher une vidéo...', 'video-library-protect'); ?>"
                           class="vlp-search-input">
                    <button type="button" class="vlp-search-btn">
                        🔍
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($atts['show_categories'] === 'true'): ?>
                <div class="vlp-category-filter">
                    <select id="vlp-category-select" class="vlp-category-select">
                        <option value=""><?php _e('Toutes les catégories', 'video-library-protect'); ?></option>
                        <?php echo $this->get_categories_options(); ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

          <div class="vlp-videos-container vlp-videos-grid" 
                 data-columns="<?php echo esc_attr($atts['columns']); ?>"
                 data-layout="<?php echo esc_attr($atts['layout']); ?>">
                 
                <div class="vlp-loading">
                    <?php _e('Chargement des vidéos...', 'video-library-protect'); ?>
                </div>
            </div>

            <div class="vlp-pagination">
                <!-- Pagination will be loaded via AJAX -->
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VLP_Public !== 'undefined' && VLP_Public.VideoLibrary) {
                console.log('Initializing VLP_Public.VideoLibrary');
                window.vlp_library_instance = new VLP_Public.VideoLibrary();
                
                // Load videos immediately
                if (window.vlp_library_instance.loadVideos) {
                    console.log('Loading videos...');
                    window.vlp_library_instance.loadVideos();
                }
            } else {
                console.error('VLP_Public.VideoLibrary not found');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single video shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_single_video($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'slug' => '',
            'width' => '100%',
            'height' => 'auto',
            'autoplay' => 'false',
            'show_info' => 'true',
            'show_related' => 'false'
        ), $atts, 'vlp_single_video');

        // Get video
        $video_manager = VLP_Video_Manager::get_instance();
        
        if (!empty($atts['slug'])) {
            $video = $video_manager->get_video_by_slug($atts['slug']);
        } else {
            $video = $video_manager->get_video(intval($atts['id']));
        }

        if (!$video) {
            return '<div class="vlp-error">' . __('Vidéo non trouvée.', 'video-library-protect') . '</div>';
        }

        // Check access
        $protection_manager = VLP_Protection_Manager::get_instance();
        $user_id = get_current_user_id() ?: null;
        $session_id = $this->get_session_id();
        
        $access_level = $protection_manager->check_video_access($video->id, $user_id, $session_id);

        // Debug logging
        error_log("VLP: Video {$video->id} - Protection: {$video->protection_level}, Access: {$access_level}");

        ob_start();
        ?>
        <div class="vlp-single-video" data-video-id="<?php echo esc_attr($video->id); ?>">
            
            <?php if ($access_level === VLP_Protection_Manager::ACCESS_FULL_VIDEO): ?>
                <?php echo $this->render_video_player($video, $atts, false); ?>
            <?php else: ?>
                <div class="vlp-video-preview-container">
                    <?php echo $this->render_video_player($video, $atts, true); ?>
                    <?php echo $this->render_unlock_form($video); ?>
                </div>
            <?php endif; ?>

            <?php if ($atts['show_info'] === 'true'): ?>
            <div class="vlp-video-info">
                <h3 class="vlp-video-title"><?php echo esc_html($video->title); ?></h3>
                
                <?php if (!empty($video->description)): ?>
                <div class="vlp-video-description">
                    <?php echo wp_kses_post($video->description); ?>
                </div>
                <?php endif; ?>

                <div class="vlp-video-meta">
                    <?php if ($video->duration > 0): ?>
                    <span class="vlp-duration">
                        ⏱️ <?php echo $this->format_duration($video->duration); ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="vlp-views">
                        👁️ <?php echo number_format($video->views_count); ?> vues
                    </span>
                    
                    <span class="vlp-date">
                        📅 <?php echo date_i18n('d/m/Y', strtotime($video->created_at)); ?>
                    </span>
                </div>

                <?php if (!empty($video->categories)): ?>
                <div class="vlp-video-categories">
                    <?php foreach ($video->categories as $category): ?>
                        <span class="vlp-category-tag">
                            <?php echo esc_html($category->name); ?>
                            <?php if ($category->protection_level !== 'free'): ?>🔒<?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_related'] === 'true'): ?>
            <div class="vlp-related-videos">
                <h4><?php _e('Vidéos similaires', 'video-library-protect'); ?></h4>
                <?php echo $this->render_related_videos($video); ?>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render video categories shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_video_categories($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid', // grid, list
            'columns' => 3,
            'show_count' => 'true',
            'show_protected' => 'true'
        ), $atts, 'vlp_video_categories');

        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $categories = $wpdb->get_results(
            "SELECT *, (SELECT COUNT(*) FROM {$wpdb->prefix}vlp_video_categories vc 
                       INNER JOIN {$wpdb->prefix}vlp_videos v ON vc.video_id = v.id 
                       WHERE vc.category_id = c.id AND v.status = 'published') as video_count 
             FROM {$categories_table} c 
             ORDER BY sort_order, name"
        );

        if (empty($categories)) {
            return '<div class="vlp-no-categories">' . __('Aucune catégorie trouvée.', 'video-library-protect') . '</div>';
        }

        ob_start();
        ?>
        <div class="vlp-categories" data-layout="<?php echo esc_attr($atts['layout']); ?>" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($categories as $category): ?>
            <div class="vlp-category-card" data-category-id="<?php echo esc_attr($category->id); ?>">
                
                <?php if (!empty($category->thumbnail_url)): ?>
                <div class="vlp-category-thumbnail">
                    <img src="<?php echo esc_url($category->thumbnail_url); ?>" 
                         alt="<?php echo esc_attr($category->name); ?>"
                         loading="lazy">
                    
                    <?php if ($category->protection_level !== 'free'): ?>
                    <div class="vlp-category-protection-badge">🔒</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="vlp-category-info">
                    <h3 class="vlp-category-name">
                        <a href="<?php echo esc_url($this->get_category_url($category->slug)); ?>">
                            <?php echo esc_html($category->name); ?>
                            <?php if ($category->protection_level !== 'free' && $atts['show_protected'] === 'true'): ?>
                                <span class="vlp-protection-icon">🔒</span>
                            <?php endif; ?>
                        </a>
                    </h3>
                    
                    <?php if (!empty($category->description)): ?>
                    <div class="vlp-category-description">
                        <?php echo wp_trim_words($category->description, 20); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($atts['show_count'] === 'true'): ?>
                    <div class="vlp-category-count">
                        <?php echo sprintf(_n('%d vidéo', '%d vidéos', $category->video_count, 'video-library-protect'), $category->video_count); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="vlp-category-actions">
                    <a href="<?php echo esc_url($this->get_category_url($category->slug)); ?>" class="vlp-btn vlp-btn-primary">
                        <?php _e('Voir les vidéos', 'video-library-protect'); ?>
                    </a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render protected content shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string HTML output
     */
    public function render_protected_content($atts, $content = '') {
        $atts = shortcode_atts(array(
            'codes' => '', // Comma-separated list of required codes
            'message' => 'Ce contenu est protégé. Veuillez entrer un code d\'accès valide.',
            'unlock_message' => 'Contenu déverrouillé !'
        ), $atts, 'vlp_protected_content');

        $required_codes = array_map('trim', explode(',', $atts['codes']));
        $required_codes = array_filter($required_codes);

        if (empty($required_codes)) {
            return do_shortcode($content); // No protection if no codes specified
        }

        // Check if user has access
        $protection_manager = VLP_Protection_Manager::get_instance();
        $user_id = get_current_user_id() ?: null;
        $session_id = $this->get_session_id();

        if ($protection_manager->validate_gift_codes($required_codes, $user_id, $session_id)) {
            return '<div class="vlp-protected-content vlp-unlocked">' . 
                   '<div class="vlp-unlock-message">' . esc_html($atts['unlock_message']) . '</div>' .
                   do_shortcode($content) . 
                   '</div>';
        }

        // Render protection form
        ob_start();
        ?>
        <div class="vlp-protected-content vlp-locked" data-required-codes="<?php echo esc_attr(json_encode($required_codes)); ?>">
            
            <div class="vlp-protection-message">
                <div class="vlp-protection-icon">🔒</div>
                <p><?php echo esc_html($atts['message']); ?></p>
            </div>

            <form class="vlp-unlock-form" data-content-type="protected_content">
                <div class="vlp-form-group">
                    <input type="text" 
                           name="gift_code" 
                           placeholder="<?php _e('Entrez votre code d\'accès', 'video-library-protect'); ?>"
                           class="vlp-code-input"
                           required>
                </div>
                
                <button type="submit" class="vlp-btn vlp-btn-primary vlp-unlock-btn">
                    <?php _e('Déverrouiller le contenu', 'video-library-protect'); ?>
                </button>
            </form>

            <div class="vlp-hidden-content" style="display: none;">
                <?php echo do_shortcode($content); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render video player
     *
     * @param object $video Video object
     * @param array $atts Player attributes
     * @param bool $is_preview Is preview mode
     * @return string HTML output
     */
    private function render_video_player($video, $atts, $is_preview = false) {
        // Check if Presto Player integration is enabled
        if ($this->is_presto_player_enabled()) {
            return $this->render_presto_player($video, $atts, $is_preview);
        }

        // Fallback to basic HTML5 player
        return $this->render_html5_player($video, $atts, $is_preview);
    }

    /**
     * Render Presto Player
     *
     * @param object $video Video object
     * @param array $atts Player attributes
     * @param bool $is_preview Is preview mode
     * @return string HTML output
     */
    private function render_presto_player($video, $atts, $is_preview = false) {
        $video_url = $is_preview ? $video->preview_video_url : $video->full_video_url;
        
        // Use Bunny Stream if available
        $bunny_integration = VLP_Bunny_Integration::get_instance();
        if ($bunny_integration->is_enabled()) {
            $guid = $is_preview ? $video->bunny_preview_guid : $video->bunny_full_guid;
            if ($guid) {
                $video_url = $bunny_integration->get_secure_stream_url($guid, 3600, $is_preview);
            }
        }

        if (empty($video_url)) {
            return '<div class="vlp-error">' . __('Source vidéo non disponible.', 'video-library-protect') . '</div>';
        }

        ob_start();
        ?>
        <div class="vlp-presto-player-container">
            <?php
            // This would integrate with Presto Player shortcode
            echo do_shortcode('[presto_player src="' . esc_url($video_url) . '" poster="' . esc_url($video->thumbnail_url) . '"]');
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render HTML5 player (fallback)
     *
     * @param object $video Video object
     * @param array $atts Player attributes
     * @param bool $is_preview Is preview mode
     * @return string HTML output
     */
    private function render_html5_player($video, $atts, $is_preview = false) {
        $video_url = $is_preview ? $video->preview_video_url : $video->full_video_url;
        
        if (empty($video_url)) {
            return '<div class="vlp-error">' . __('Source vidéo non disponible.', 'video-library-protect') . '</div>';
        }

        ob_start();
        ?>
        <div class="vlp-html5-player-container">
            <video controls 
                   preload="metadata"
                   <?php if (!empty($video->thumbnail_url)): ?>poster="<?php echo esc_url($video->thumbnail_url); ?>"<?php endif; ?>
                   <?php if ($atts['width'] !== 'auto'): ?>width="<?php echo esc_attr($atts['width']); ?>"<?php endif; ?>
                   <?php if ($atts['height'] !== 'auto'): ?>height="<?php echo esc_attr($atts['height']); ?>"<?php endif; ?>
                   <?php if ($atts['autoplay'] === 'true'): ?>autoplay<?php endif; ?>>
                
                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                
                <?php _e('Votre navigateur ne supporte pas la lecture vidéo.', 'video-library-protect'); ?>
            </video>

            <?php if ($is_preview): ?>
            <div class="vlp-preview-overlay">
                <div class="vlp-preview-badge">
                    <?php _e('APERÇU', 'video-library-protect'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render unlock form
     *
     * @param object $video Video object
     * @return string HTML output
     */
    private function render_unlock_form($video) {
        ob_start();
        ?>
        <div class="vlp-unlock-form-container">
            <div class="vlp-unlock-prompt">
                <h4><?php _e('Vidéo complète disponible', 'video-library-protect'); ?></h4>
                <p><?php _e('Entrez votre code cadeau pour accéder à la vidéo complète.', 'video-library-protect'); ?></p>
            </div>

            <form class="vlp-unlock-form" data-video-id="<?php echo esc_attr($video->id); ?>" data-content-type="video">
                <div class="vlp-form-group">
                    <input type="text" 
                           name="gift_code" 
                           placeholder="<?php _e('Code cadeau', 'video-library-protect'); ?>"
                           class="vlp-code-input"
                           required
                           autocomplete="off">
                </div>
                
                <button type="submit" class="vlp-btn vlp-btn-unlock">
                    🔓 <?php _e('Déverrouiller la vidéo', 'video-library-protect'); ?>
                </button>
            </form>

            <div class="vlp-unlock-hints">
                <p class="vlp-hint">💡 <?php _e('Le code est sensible à la casse', 'video-library-protect'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get categories options for select dropdown
     *
     * @return string HTML options
     */
    private function get_categories_options() {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $categories = $wpdb->get_results(
            "SELECT * FROM {$categories_table} ORDER BY name"
        );

        $options = '';
        foreach ($categories as $category) {
            $protected_icon = $category->protection_level !== 'free' ? ' 🔒' : '';
            $options .= sprintf(
                '<option value="%s">%s%s</option>',
                esc_attr($category->slug),
                esc_html($category->name),
                $protected_icon
            );
        }

        return $options;
    }

    /**
     * Get category URL
     *
     * @param string $category_slug Category slug
     * @return string Category URL
     */
    private function get_category_url($category_slug) {
        $settings = get_option('vlp_settings', array());
        $library_page_id = $settings['library_page_id'] ?? 0;
        
        if ($library_page_id) {
            return add_query_arg('category', $category_slug, get_permalink($library_page_id));
        }
        
        return home_url("?vlp_category={$category_slug}");
    }

    /**
     * Format duration in human readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $remaining_seconds > 0 ? 
                "{$minutes}m {$remaining_seconds}s" : 
                "{$minutes}m";
        }
        
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        return $remaining_minutes > 0 ? 
            "{$hours}h {$remaining_minutes}m" : 
            "{$hours}h";
    }

    /**
     * Get session ID
     *
     * @return string Session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    /**
     * Check if Presto Player is enabled
     *
     * @return bool
     */
    private function is_presto_player_enabled() {
        $settings = get_option('vlp_settings', array());
        return !empty($settings['presto_player_enabled']) && class_exists('PrestoPlayer');
    }

    /**
     * Render site protection form
     *
     * @return string HTML output
     */
    private function render_site_protection_form() {
        ob_start();
        ?>
        <div class="vlp-site-protection">
            <div class="vlp-site-protection-message">
                <h3><?php _e('Accès au site restreint', 'video-library-protect'); ?></h3>
                <p><?php _e('Ce site nécessite un code d\'accès valide pour être consulté.', 'video-library-protect'); ?></p>
            </div>

            <form class="vlp-site-unlock-form" data-content-type="site_wide">
                <div class="vlp-form-group">
                    <input type="text" 
                           name="gift_code" 
                           placeholder="<?php _e('Code d\'accès au site', 'video-library-protect'); ?>"
                           class="vlp-code-input"
                           required>
                </div>
                
                <button type="submit" class="vlp-btn vlp-btn-primary">
                    <?php _e('Accéder au site', 'video-library-protect'); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render related videos
     *
     * @param object $video Current video
     * @return string HTML output
     */
    private function render_related_videos($video) {
        if (empty($video->categories)) {
            return '<p>' . __('Aucune vidéo similaire trouvée.', 'video-library-protect') . '</p>';
        }

        $video_manager = VLP_Video_Manager::get_instance();
        $category_ids = wp_list_pluck($video->categories, 'id');
        
        $related_videos = $video_manager->get_videos(array(
            'category_id' => $category_ids[0], // Use first category
            'limit' => 4,
            'exclude' => $video->id
        ));

        if (empty($related_videos)) {
            return '<p>' . __('Aucune vidéo similaire trouvée.', 'video-library-protect') . '</p>';
        }

        ob_start();
        ?>
        <div class="vlp-related-videos-grid">
            <?php foreach ($related_videos as $related_video): ?>
            <div class="vlp-related-video-card">
                <a href="<?php echo esc_url($this->get_video_url($related_video->slug)); ?>">
                    <?php if (!empty($related_video->thumbnail_url)): ?>
                    <img src="<?php echo esc_url($related_video->thumbnail_url); ?>" 
                         alt="<?php echo esc_attr($related_video->title); ?>"
                         loading="lazy">
                    <?php endif; ?>
                    
                    <h5><?php echo esc_html($related_video->title); ?></h5>
                    
                    <?php if ($related_video->protection_level !== 'free'): ?>
                    <span class="vlp-protection-badge">🔒</span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get video URL
     *
     * @param string $video_slug Video slug
     * @return string Video URL
     */
    private function get_video_url($video_slug) {
        $settings = get_option('vlp_settings', array());
        $library_page_id = $settings['library_page_id'] ?? 0;
        
        if ($library_page_id) {
            return add_query_arg('video', $video_slug, get_permalink($library_page_id));
        }
        
        return home_url("?vlp_video={$video_slug}");
    }
}