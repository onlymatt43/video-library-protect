<?php
/**
 * Bunny.net Stream Integration
 *
 * Handles video uploads to Bunny.net Stream and generates secure URLs
 * for protected content, including modern JWT for DRM.
 *
 * @package VideoLibraryProtect
 * @since   2.0.0
 * @author  Mathieu Courchesne <mathieu.courchesne@gmail.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load JWT library
if (file_exists(VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/JWT.php')) {
    require_once VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/JWT.php';
    require_once VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/Key.php';
    require_once VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/BeforeValidException.php';
    require_once VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/ExpiredException.php';
    require_once VLP_PLUGIN_DIR . 'includes/vendor/firebase/php-jwt/src/SignatureInvalidException.php';
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class VLP_Bunny_Integration {

    private static $instance = null;
    private $options = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('vlp_settings', []);
    }

    public function is_enabled() {
        return !empty($this->options['bunny_stream_enabled']) && 
               !empty($this->options['bunny_library_id']) && 
               !empty($this->options['bunny_api_key']);
    }

    /**
     * Generates a secure URL for a Bunny.net video.
     *
     * It checks for a DRM key first to generate a JWT. If not present,
     * it falls back to the legacy token authentication.
     *
     * @param string $video_guid The Bunny.net video GUID.
     * @param int    $expiry     URL expiration time in seconds.
     * @param bool   $is_preview Whether this is for a preview or full video.
     * @return string The secure HLS playlist URL.
     */
    public function get_secure_stream_url($video_guid, $expiry = 10800, $is_preview = false) {
        if (!$this->is_enabled() || empty($video_guid)) {
            return '';
        }

        $library_id = $this->options['bunny_library_id'];
        $drm_private_key = !empty($this->options['bunny_drm_private_key']) ? trim($this->options['bunny_drm_private_key']) : null;
        $cdn_hostname = !empty($this->options['bunny_cdn_hostname']) ? $this->options['bunny_cdn_hostname'] : "video.bunnycdn.com";

        // Base URL for the HLS playlist
        $base_url = "https://{$cdn_hostname}/play/{$library_id}/{$video_guid}";

        // --- JWT DRM Authentication (Priority) ---
        if ($drm_private_key && class_exists('Firebase\JWT\JWT')) {
            try {
                $expiration_time = time() + $expiry;
                $payload = [
                    "vid" => $video_guid,
                    "exp" => $expiration_time,
                    "iat" => time() - 60, // Issued at time (60s tolerance)
                    "lib" => $library_id,  // Library ID for additional validation
                ];

                // Add preview restriction if applicable
                if ($is_preview) {
                    $payload["preview"] = true;
                }

                // Sign the token
                $jwt = JWT::encode($payload, $drm_private_key, 'HS256');

                return "{$base_url}/playlist.m3u8?token={$jwt}";

            } catch (Exception $e) {
                error_log('VLP Bunny DRM Error: Failed to generate JWT. ' . $e->getMessage());
                // Fallback to no token if JWT generation fails
                return "{$base_url}/playlist.m3u8";
            }
        }

        // --- Legacy Token Authentication (Fallback) ---
        $api_key = $this->options['bunny_api_key'];
        $expires = time() + $expiry;
        $hash = hash('sha256', $library_id . $api_key . $expires . $video_guid);

        return "{$base_url}/playlist.m3u8?token={$hash}&expires={$expires}";
    }

    /**
     * Upload a video to Bunny Stream
     *
     * @param string $file_path Path to the video file
     * @param string $title Video title
     * @return array|WP_Error Upload result or error
     */
    public function upload_video($file_path, $title) {
        if (!$this->is_enabled()) {
            return new WP_Error('bunny_disabled', 'Bunny Stream integration is not enabled or configured.');
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Video file not found.');
        }

        $library_id = $this->options['bunny_library_id'];
        $api_key = $this->options['bunny_api_key'];

        // Create video entry
        $create_response = wp_remote_post("https://video.bunnycdn.com/library/{$library_id}/videos", [
            'headers' => [
                'AccessKey' => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'title' => $title,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($create_response)) {
            return $create_response;
        }

        $create_body = wp_remote_retrieve_body($create_response);
        $create_data = json_decode($create_body, true);

        if (!$create_data || !isset($create_data['guid'])) {
            return new WP_Error('create_failed', 'Failed to create video entry on Bunny Stream.');
        }

        $video_guid = $create_data['guid'];

        // Upload the actual video file
        $upload_response = wp_remote_put("https://video.bunnycdn.com/library/{$library_id}/videos/{$video_guid}", [
            'headers' => [
                'AccessKey' => $api_key,
            ],
            'body' => file_get_contents($file_path),
            'timeout' => 300, // 5 minutes for video upload
        ]);

        if (is_wp_error($upload_response)) {
            return $upload_response;
        }

        $upload_code = wp_remote_retrieve_response_code($upload_response);

        if ($upload_code !== 200) {
            return new WP_Error('upload_failed', 'Failed to upload video to Bunny Stream.');
        }

        return [
            'guid' => $video_guid,
            'title' => $title,
            'status' => 'uploaded',
        ];
    }

    /**
     * Get video information from Bunny Stream
     *
     * @param string $video_guid Video GUID
     * @return array|WP_Error Video information or error
     */
    public function get_video_info($video_guid) {
        if (!$this->is_enabled() || empty($video_guid)) {
            return new WP_Error('invalid_params', 'Invalid parameters.');
        }

        $library_id = $this->options['bunny_library_id'];
        $api_key = $this->options['bunny_api_key'];

        $response = wp_remote_get("https://video.bunnycdn.com/library/{$library_id}/videos/{$video_guid}", [
            'headers' => [
                'AccessKey' => $api_key,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_Error('api_error', 'Invalid response from Bunny Stream API.');
        }

        return $data;
    }

    /**
     * Delete a video from Bunny Stream
     *
     * @param string $video_guid Video GUID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_video($video_guid) {
        if (!$this->is_enabled() || empty($video_guid)) {
            return new WP_Error('invalid_params', 'Invalid parameters.');
        }

        $library_id = $this->options['bunny_library_id'];
        $api_key = $this->options['bunny_api_key'];

        $response = wp_remote_request("https://video.bunnycdn.com/library/{$library_id}/videos/{$video_guid}", [
            'method' => 'DELETE',
            'headers' => [
                'AccessKey' => $api_key,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200 || $code === 404) { // 404 means already deleted
            return true;
        }

        return new WP_Error('delete_failed', 'Failed to delete video from Bunny Stream.');
    }

    /**
     * Generate a thumbnail URL for a video
     *
     * @param string $video_guid Video GUID
     * @param int    $width      Thumbnail width (optional)
     * @param int    $height     Thumbnail height (optional)
     * @return string Thumbnail URL
     */
    public function get_thumbnail_url($video_guid, $width = 1280, $height = 720) {
        if (!$this->is_enabled() || empty($video_guid)) {
            return '';
        }

        $library_id = $this->options['bunny_library_id'];
        $cdn_hostname = !empty($this->options['bunny_cdn_hostname']) ? $this->options['bunny_cdn_hostname'] : "video.bunnycdn.com";

        return "https://{$cdn_hostname}/{$library_id}/{$video_guid}/thumbnail.jpg?width={$width}&height={$height}";
    }
}
