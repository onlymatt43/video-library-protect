<?php
/**
 * Protection Manager Class
 *
 * Handles protection logic for videos, categories, and site-wide access
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Protection_Manager {

    /**
     * Instance of this class
     *
     * @var VLP_Protection_Manager
     */
    private static $instance = null;

    /**
     * Protection levels
     */
    const PROTECTION_FREE = 'free';
    const PROTECTION_GIFT_CODE = 'gift_code';
    const PROTECTION_CATEGORY = 'category';
    const PROTECTION_SITE_WIDE = 'site_wide';

    /**
     * Access levels
     */
    const ACCESS_PREVIEW_ONLY = 'preview_only';
    const ACCESS_FULL_VIDEO = 'full_video';
    const ACCESS_CATEGORY = 'category';
    const ACCESS_SITE_WIDE = 'site_wide';

    /**
     * Database tables
     */
    private $access_table;

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
        global $wpdb;
        $this->access_table = $wpdb->prefix . 'vlp_access_log';
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_vlp_validate_gift_code', array($this, 'ajax_validate_gift_code'));
        add_action('wp_ajax_nopriv_vlp_validate_gift_code', array($this, 'ajax_validate_gift_code'));
        
        add_action('wp_ajax_vlp_unlock_video', array($this, 'ajax_unlock_video'));
        add_action('wp_ajax_nopriv_vlp_unlock_video', array($this, 'ajax_unlock_video'));
    }

    /**
     * Check if user has access to video
     *
     * @param int $video_id Video ID
     * @param int $user_id User ID (optional)
     * @param string $session_id Session ID (optional)
     * @return string Access level
     */
    public function check_video_access($video_id, $user_id = null, $session_id = null) {
        // Get video data
        $video_manager = VLP_Video_Manager::get_instance();
        $video = $video_manager->get_video($video_id);
        
        if (!$video) {
            error_log("VLP_DEBUG: Video not found for ID {$video_id}");
            return self::ACCESS_PREVIEW_ONLY;
        }
        
        error_log("VLP_DEBUG: check_video_access() - Video {$video_id} '{$video->title}' protection_level='{$video->protection_level}'");

        // Check site-wide protection first
        if ($this->is_site_wide_protected()) {
            $site_access = $this->check_site_wide_access($user_id, $session_id);
            if (!$site_access) {
                return self::ACCESS_PREVIEW_ONLY;
            }
        }

        // Check video-specific protection
        switch ($video->protection_level) {
            case self::PROTECTION_FREE:
                error_log("VLP_DEBUG: Video {$video_id} is FREE, returning ACCESS_FULL_VIDEO");
                return self::ACCESS_FULL_VIDEO;
                
            case self::PROTECTION_GIFT_CODE:
                error_log("VLP_DEBUG: Video {$video_id} requires GIFT_CODE");
                return $this->check_video_gift_code_access($video_id, $video->protection_data, $user_id, $session_id);
                
            case self::PROTECTION_CATEGORY:
                error_log("VLP_DEBUG: Video {$video_id} uses CATEGORY protection");
                return $this->check_category_access($video->categories, $user_id, $session_id);
                
            default:
                error_log("VLP_DEBUG: Video {$video_id} has unknown protection '{$video->protection_level}', returning PREVIEW_ONLY");
                return self::ACCESS_PREVIEW_ONLY;
        }
    }

    /**
     * Check category access
     *
     * @param array $categories Video categories
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return string Access level
     */
    public function check_category_access($categories, $user_id = null, $session_id = null) {
        if (empty($categories)) {
            return self::ACCESS_FULL_VIDEO;
        }

        foreach ($categories as $category) {
            if ($category->protection_level === self::PROTECTION_FREE) {
                return self::ACCESS_FULL_VIDEO;
            }

            if ($category->protection_level === self::PROTECTION_GIFT_CODE) {
                $protection_data = maybe_unserialize($category->protection_data);
                if ($this->validate_gift_codes($protection_data['required_codes'], $user_id, $session_id)) {
                    return self::ACCESS_CATEGORY;
                }
            }
        }

        return self::ACCESS_PREVIEW_ONLY;
    }

    /**
     * Check video-specific gift code access
     *
     * @param int $video_id Video ID
     * @param mixed $protection_data Protection data
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return string Access level
     */
    private function check_video_gift_code_access($video_id, $protection_data, $user_id = null, $session_id = null) {
        if (empty($protection_data)) {
            return self::ACCESS_PREVIEW_ONLY;
        }

        $protection_data = maybe_unserialize($protection_data);
        $required_codes = $protection_data['required_codes'] ?? array();

        if (empty($required_codes)) {
            return self::ACCESS_PREVIEW_ONLY;
        }

        // Check if user has already used a valid code for this video
        $has_active = $this->has_active_access($video_id, null, $user_id, $session_id);
        error_log("VLP_DEBUG: has_active_access for video {$video_id}: " . ($has_active ? 'TRUE' : 'FALSE'));
        
        if ($has_active) {
            error_log("VLP_DEBUG: User has active access, returning ACCESS_FULL_VIDEO");
            return self::ACCESS_FULL_VIDEO;
        }

        // Check if any of the required codes are valid
        $codes_valid = $this->validate_gift_codes($required_codes, $user_id, $session_id);
        error_log("VLP_DEBUG: validate_gift_codes result: " . ($codes_valid ? 'TRUE' : 'FALSE'));
        
        if ($codes_valid) {
            error_log("VLP_DEBUG: Gift codes validated, returning ACCESS_FULL_VIDEO");
            return self::ACCESS_FULL_VIDEO;
        }

        error_log("VLP_DEBUG: No valid access found, returning ACCESS_PREVIEW_ONLY");
        return self::ACCESS_PREVIEW_ONLY;
    }

    /**
     * Validate gift codes against external gift code plugin
     *
     * @param array $required_codes Required codes
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    public function validate_gift_codes($required_codes, $user_id = null, $session_id = null) {
        error_log("VLP_DEBUG: validate_gift_codes called with codes: " . implode(', ', $required_codes));
        
        if (empty($required_codes)) {
            error_log("VLP_DEBUG: No required codes, returning false");
            return false;
        }

        // Check if GiftCode Protect plugin is active
        $plugin_active = $this->is_giftcode_plugin_active();
        error_log("VLP_DEBUG: is_giftcode_plugin_active: " . ($plugin_active ? 'TRUE' : 'FALSE'));
        
        if (!$plugin_active) {
            error_log("VLP_DEBUG: GiftCode plugin not active, returning false");
            return false;
        }

        // Get stored codes from session or user meta
        $user_codes = $this->get_user_active_codes($user_id, $session_id);
        error_log("VLP_DEBUG: user_codes: " . print_r($user_codes, true));

        foreach ($required_codes as $required_code) {
            error_log("VLP_DEBUG: Checking required code: {$required_code}");
            
            if (in_array($required_code, $user_codes)) {
                error_log("VLP_DEBUG: Code {$required_code} found in user_codes");
                
                // Validate that the code is still valid with GiftCode plugin
                $plugin_valid = $this->validate_with_giftcode_plugin($required_code);
                error_log("VLP_DEBUG: validate_with_giftcode_plugin({$required_code}): " . ($plugin_valid ? 'TRUE' : 'FALSE'));
                
                if ($plugin_valid) {
                    error_log("VLP_DEBUG: User has valid code {$required_code}, returning true");
                    return true;
                } else {
                    error_log("VLP_DEBUG: User code {$required_code} is invalid (expired/inactive), continuing to check other codes");
                }
            } else {
                error_log("VLP_DEBUG: Code {$required_code} NOT found in user_codes, user doesn't have access to this code");
            }
        }

        error_log("VLP_DEBUG: No valid codes found, returning false");
        return false;
    }

    /**
     * Unlock video with gift code
     *
     * @param int $video_id Video ID
     * @param string $gift_code Gift code
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return array Result
     */
    public function unlock_video_with_code($video_id, $gift_code, $user_id = null, $session_id = null) {
        // Get video data
        $video_manager = VLP_Video_Manager::get_instance();
        $video = $video_manager->get_video($video_id);
        
        if (!$video) {
            return array(
                'success' => false,
                'message' => __('Vidéo non trouvée.', 'video-library-protect')
            );
        }

        // Validate gift code format
        if (!$this->validate_code_format($gift_code)) {
            return array(
                'success' => false,
                'message' => __('Format de code invalide.', 'video-library-protect')
            );
        }

        // Check if code is valid for this video
        if (!$this->is_code_valid_for_video($video, $gift_code)) {
            return array(
                'success' => false,
                'message' => __('Ce code ne permet pas d\'accéder à cette vidéo.', 'video-library-protect')
            );
        }

        // Validate with external gift code plugin
        if (!$this->validate_with_giftcode_plugin($gift_code)) {
            return array(
                'success' => false,
                'message' => __('Code cadeau invalide ou expiré.', 'video-library-protect')
            );
        }

        // Grant access
        $access_granted = $this->grant_video_access($video_id, $gift_code, $user_id, $session_id);

        if ($access_granted) {
            // Store code for future use
            $this->store_user_code($gift_code, $user_id, $session_id);

            return array(
                'success' => true,
                'message' => __('Accès accordé à la vidéo.', 'video-library-protect'),
                'access_level' => self::ACCESS_FULL_VIDEO
            );
        }

        return array(
            'success' => false,
            'message' => __('Erreur lors de l\'octroi de l\'accès.', 'video-library-protect')
        );
    }

    /**
     * Unlock category with gift code
     *
     * @param int $category_id Category ID
     * @param string $gift_code Gift code
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return array Result
     */
    public function unlock_category_with_code($category_id, $gift_code, $user_id = null, $session_id = null) {
        // Get category data
        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$categories_table} WHERE id = %d",
            $category_id
        ));

        if (!$category) {
            return array(
                'success' => false,
                'message' => __('Catégorie non trouvée.', 'video-library-protect')
            );
        }

        $protection_data = maybe_unserialize($category->protection_data);
        $required_codes = $protection_data['required_codes'] ?? array();

        if (!in_array($gift_code, $required_codes)) {
            return array(
                'success' => false,
                'message' => __('Ce code ne permet pas d\'accéder à cette catégorie.', 'video-library-protect')
            );
        }

        if (!$this->validate_with_giftcode_plugin($gift_code)) {
            return array(
                'success' => false,
                'message' => __('Code cadeau invalide ou expiré.', 'video-library-protect')
            );
        }

        // Grant category access
        $access_granted = $this->grant_category_access($category_id, $gift_code, $user_id, $session_id);

        if ($access_granted) {
            $this->store_user_code($gift_code, $user_id, $session_id);

            return array(
                'success' => true,
                'message' => __('Accès accordé à la catégorie.', 'video-library-protect'),
                'access_level' => self::ACCESS_CATEGORY
            );
        }

        return array(
            'success' => false,
            'message' => __('Erreur lors de l\'octroi de l\'accès.', 'video-library-protect')
        );
    }

    /**
     * Check if site is protected site-wide
     *
     * @return bool
     */
    public function is_site_wide_protected() {
        $settings = get_option('vlp_settings', array());
        return !empty($settings['site_wide_protection']);
    }

    /**
     * Check site-wide access
     *
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    public function check_site_wide_access($user_id = null, $session_id = null) {
        $settings = get_option('vlp_settings', array());
        $required_codes = $settings['site_protection_codes'] ?? array();

        if (empty($required_codes)) {
            return true; // No site protection configured
        }

        return $this->validate_gift_codes($required_codes, $user_id, $session_id);
    }

    /**
     * Get user's active codes
     *
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return array
     */
    private function get_user_active_codes($user_id = null, $session_id = null) {
        if ($user_id) {
            $codes = get_user_meta($user_id, '_vlp_active_codes', true);
            return is_array($codes) ? $codes : array();
        }

        if ($session_id) {
            $codes = get_transient('vlp_session_codes_' . $session_id);
            return is_array($codes) ? $codes : array();
        }

        return array();
    }

    /**
     * Store user code
     *
     * @param string $gift_code Gift code
     * @param int $user_id User ID
     * @param string $session_id Session ID
     */
    private function store_user_code($gift_code, $user_id = null, $session_id = null) {
        if ($user_id) {
            $codes = $this->get_user_active_codes($user_id);
            $codes[] = $gift_code;
            $codes = array_unique($codes);
            update_user_meta($user_id, '_vlp_active_codes', $codes);
        }

        if ($session_id) {
            $codes = $this->get_user_active_codes(null, $session_id);
            $codes[] = $gift_code;
            $codes = array_unique($codes);
            set_transient('vlp_session_codes_' . $session_id, $codes, DAY_IN_SECONDS);
        }
    }

    /**
     * Grant video access
     *
     * @param int $video_id Video ID
     * @param string $gift_code Gift code
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    private function grant_video_access($video_id, $gift_code, $user_id = null, $session_id = null) {
        global $wpdb;

        $access_data = array(
            'video_id' => $video_id,
            'user_id' => $user_id,
            'session_id' => $session_id ?: $this->generate_session_id(),
            'gift_code' => $gift_code,
            'access_type' => 'video',
            'access_level' => self::ACCESS_FULL_VIDEO,
            'granted_at' => current_time('mysql'),
            'expires_at' => null, // Determined by gift code expiration
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        return $wpdb->insert($this->access_table, $access_data) !== false;
    }

    /**
     * Grant category access
     *
     * @param int $category_id Category ID
     * @param string $gift_code Gift code
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    private function grant_category_access($category_id, $gift_code, $user_id = null, $session_id = null) {
        global $wpdb;

        $access_data = array(
            'category_id' => $category_id,
            'user_id' => $user_id,
            'session_id' => $session_id ?: $this->generate_session_id(),
            'gift_code' => $gift_code,
            'access_type' => 'category',
            'access_level' => self::ACCESS_CATEGORY,
            'granted_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        return $wpdb->insert($this->access_table, $access_data) !== false;
    }

    /**
     * Check if user has active access
     *
     * @param int $video_id Video ID
     * @param int $category_id Category ID
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    private function has_active_access($video_id = null, $category_id = null, $user_id = null, $session_id = null) {
        global $wpdb;

        $where_clauses = array();
        $where_values = array();

        if ($video_id) {
            $where_clauses[] = 'video_id = %d';
            $where_values[] = $video_id;
        }

        if ($category_id) {
            $where_clauses[] = 'category_id = %d';
            $where_values[] = $category_id;
        }

        if ($user_id) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $user_id;
        } elseif ($session_id) {
            $where_clauses[] = 'session_id = %s';
            $where_values[] = $session_id;
        } else {
            return false;
        }

        $where_clauses[] = '(expires_at IS NULL OR expires_at > NOW())';
        
        $where_sql = implode(' AND ', $where_clauses);
        $sql = "SELECT COUNT(*) FROM {$this->access_table} WHERE {$where_sql}";

        return $wpdb->get_var($wpdb->prepare($sql, $where_values)) > 0;
    }

    /**
     * Validate code format
     *
     * @param string $code Gift code
     * @return bool
     */
    private function validate_code_format($code) {
        return preg_match('/^[A-Z0-9\-]{3,50}$/i', trim($code));
    }

    /**
     * Check if code is valid for video
     *
     * @param object $video Video object
     * @param string $gift_code Gift code
     * @return bool
     */
    private function is_code_valid_for_video($video, $gift_code) {
        if ($video->protection_level !== self::PROTECTION_GIFT_CODE) {
            return false;
        }

        $protection_data = maybe_unserialize($video->protection_data);
        $required_codes = $protection_data['required_codes'] ?? array();

        return in_array(strtoupper(trim($gift_code)), array_map('strtoupper', $required_codes));
    }

    /**
     * Check if gift code plugin is active
     *
     * @return bool
     */
    private function is_giftcode_plugin_active() {
        return class_exists('GiftCode_Manager');
    }

    /**
     * Validate with external gift code plugin
     *
     * @param string $gift_code Gift code
     * @return bool
     */
    private function validate_with_giftcode_plugin($gift_code) {
        if (!$this->is_giftcode_plugin_active()) {
            return true; // Allow if external plugin not available
        }

        try {
            $gift_code_manager = new GiftCode_Manager();
            $validation_result = $gift_code_manager->validate_code($gift_code);
            return $validation_result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate session ID
     *
     * @return string
     */
    private function generate_session_id() {
        return wp_generate_password(32, false);
    }

    /**
     * AJAX handler for gift code validation
     */
    public function ajax_validate_gift_code() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');

        $gift_code = sanitize_text_field($_POST['gift_code'] ?? '');
        if (empty($gift_code) && !empty($_POST['unlock_code'])) {
            $gift_code = sanitize_text_field($_POST['unlock_code']);
        }

        if (empty($gift_code)) {
            wp_send_json_error(array('message' => __('Code requis.', 'video-library-protect')));
        }

        $is_valid = $this->validate_with_giftcode_plugin($gift_code);

        if ($is_valid) {
            wp_send_json_success(array('message' => __('Code valide.', 'video-library-protect')));
        } else {
            wp_send_json_error(array('message' => __('Code invalide.', 'video-library-protect')));
        }
    }

    /**
     * AJAX handler for video unlock
     */
    public function ajax_unlock_video() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');

        $video_id = intval($_POST['video_id'] ?? 0);
        $gift_code = sanitize_text_field($_POST['gift_code'] ?? '');
        $user_id = get_current_user_id() ?: null;
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (!$video_id || !$gift_code) {
            wp_send_json_error(array('message' => __('Données manquantes.', 'video-library-protect')));
        }

        $result = $this->unlock_video_with_code($video_id, $gift_code, $user_id, $session_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}