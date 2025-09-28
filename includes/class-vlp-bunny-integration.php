<?php
/**
 * Bunny.net Integration Class
 *
 * Handles Bunny CDN and Bunny Stream integration for secure video delivery
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Bunny_Integration {

    /**
     * Instance of this class
     *
     * @var VLP_Bunny_Integration
     */
    private static $instance = null;

    /**
     * Bunny settings
     */
    private $settings;
    private $library_id;
    private $api_key;
    private $cdn_hostname;
    private $security_key;

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
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load Bunny settings
     */
    private function load_settings() {
        $vlp_settings = get_option('vlp_settings', array());
        
        $this->settings = array(
            'enabled' => !empty($vlp_settings['bunny_stream_enabled']),
            'library_id' => $vlp_settings['bunny_library_id'] ?? '',
            'api_key' => $vlp_settings['bunny_api_key'] ?? '',
            'cdn_hostname' => $vlp_settings['bunny_cdn_hostname'] ?? '',
            'security_key' => $vlp_settings['bunny_security_key'] ?? ''
        );

        $this->library_id = $this->settings['library_id'];
        $this->api_key = $this->settings['api_key'];
        $this->cdn_hostname = $this->settings['cdn_hostname'];
        $this->security_key = $this->settings['security_key'];
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (!$this->is_enabled()) {
            return;
        }

        add_action('wp_ajax_vlp_upload_to_bunny', array($this, 'ajax_upload_to_bunny'));
        add_action('wp_ajax_vlp_get_bunny_video_info', array($this, 'ajax_get_video_info'));
        add_filter('vlp_video_stream_url', array($this, 'get_secure_stream_url'), 10, 3);
    }

    /**
     * Check if Bunny integration is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->settings['enabled'] && 
               !empty($this->library_id) && 
               !empty($this->api_key);
    }

    /**
     * Upload video to Bunny Stream
     *
     * @param string $file_path Local file path
     * @param array $video_data Video metadata
     * @return array Upload result
     */
    public function upload_video($file_path, $video_data = array()) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'Bunny Stream not configured'
            );
        }

        try {
            // Step 1: Create video entry in Bunny Stream
            $video_guid = $this->create_bunny_video($video_data);
            
            if (!$video_guid) {
                return array(
                    'success' => false,
                    'message' => 'Failed to create video in Bunny Stream'
                );
            }

            // Step 2: Upload video file
            $upload_result = $this->upload_video_file($video_guid, $file_path);
            
            if (!$upload_result) {
                return array(
                    'success' => false,
                    'message' => 'Failed to upload video file'
                );
            }

            // Step 3: Generate preview if needed
            $preview_guid = null;
            if (!empty($video_data['generate_preview'])) {
                $preview_guid = $this->create_video_preview($video_guid, $video_data);
            }

            return array(
                'success' => true,
                'video_guid' => $video_guid,
                'preview_guid' => $preview_guid,
                'message' => 'Video uploaded successfully'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create video entry in Bunny Stream
     *
     * @param array $video_data Video metadata
     * @return string|false Video GUID or false on failure
     */
    private function create_bunny_video($video_data = array()) {
        $url = "https://video.bunnycdn.com/library/{$this->library_id}/videos";
        
        $data = array(
            'title' => $video_data['title'] ?? 'Untitled Video'
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['guid'])) {
            return $body['guid'];
        }

        return false;
    }

    /**
     * Upload video file to Bunny Stream
     *
     * @param string $video_guid Video GUID
     * @param string $file_path Local file path
     * @return bool Success
     */
    private function upload_video_file($video_guid, $file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $upload_url = "https://video.bunnycdn.com/library/{$this->library_id}/videos/{$video_guid}";
        
        $file_data = file_get_contents($file_path);

        $response = wp_remote_request($upload_url, array(
            'method' => 'PUT',
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/octet-stream'
            ),
            'body' => $file_data,
            'timeout' => 300 // 5 minutes for large files
        ));

        return !is_wp_error($response);
    }

    /**
     * Create video preview (shortened version)
     *
     * @param string $full_video_guid Full video GUID
     * @param array $video_data Video metadata
     * @return string|false Preview GUID or false
     */
    private function create_video_preview($full_video_guid, $video_data = array()) {
        // Create a new video entry for preview
        $preview_data = array(
            'title' => ($video_data['title'] ?? 'Untitled') . ' - Preview'
        );
        
        $preview_guid = $this->create_bunny_video($preview_data);
        
        if (!$preview_guid) {
            return false;
        }

        // Use Bunny's video processing to create a shortened preview
        // This would typically involve API calls to trim the video
        $this->request_video_trimming($full_video_guid, $preview_guid, $video_data);
        
        return $preview_guid;
    }

    /**
     * Request video trimming for preview
     *
     * @param string $source_guid Source video GUID
     * @param string $preview_guid Preview video GUID
     * @param array $video_data Video metadata
     */
    private function request_video_trimming($source_guid, $preview_guid, $video_data) {
        $vlp_settings = get_option('vlp_settings', array());
        $preview_duration = $vlp_settings['preview_duration'] ?? 30; // seconds

        // Note: This is a simplified example. Actual implementation would depend on
        // Bunny's video processing capabilities or external video processing service
        
        $processing_data = array(
            'source_guid' => $source_guid,
            'target_guid' => $preview_guid,
            'start_time' => 0,
            'end_time' => $preview_duration,
            'add_watermark' => true,
            'watermark_text' => 'PREVIEW'
        );

        // Store processing request for later handling
        update_option('vlp_bunny_processing_queue', $processing_data);
    }

    /**
     * Get secure video stream URL
     *
     * @param string $video_guid Video GUID
     * @param int $expires_in Expiration time in seconds
     * @param bool $is_preview Is preview video
     * @return string Secure URL
     */
    public function get_secure_stream_url($video_guid, $expires_in = 3600, $is_preview = false) {
        if (!$this->is_enabled()) {
            return '';
        }

        if (empty($this->security_key)) {
            // Return direct URL if no security key configured
            return "https://{$this->cdn_hostname}/{$video_guid}/playlist.m3u8";
        }

        $expires_timestamp = time() + $expires_in;
        $path = "/{$video_guid}/playlist.m3u8";
        
        // Generate authentication token
        $auth_string = $this->security_key . $path . $expires_timestamp;
        $token = md5($auth_string);
        
        $secure_url = "https://{$this->cdn_hostname}{$path}?token={$token}&expires={$expires_timestamp}";
        
        // Add preview parameters if needed
        if ($is_preview) {
            $secure_url .= '&preview=1';
        }

        return $secure_url;
    }

    /**
     * Get video information from Bunny Stream
     *
     * @param string $video_guid Video GUID
     * @return array|false Video info or false
     */
    public function get_video_info($video_guid) {
        if (!$this->is_enabled()) {
            return false;
        }

        $url = "https://video.bunnycdn.com/library/{$this->library_id}/videos/{$video_guid}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'AccessKey' => $this->api_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body) {
            return array(
                'guid' => $body['guid'] ?? '',
                'title' => $body['title'] ?? '',
                'length' => $body['length'] ?? 0,
                'status' => $body['status'] ?? 0, // 0=queued, 1=processing, 2=encoding, 3=finished, 4=failed
                'thumbnail_url' => $body['thumbnailFileName'] ? 
                    "https://{$this->cdn_hostname}/{$video_guid}/{$body['thumbnailFileName']}" : '',
                'file_size' => $body['storageSize'] ?? 0,
                'created_at' => $body['dateUploaded'] ?? '',
                'resolutions' => $body['availableResolutions'] ?? array()
            );
        }

        return false;
    }

    /**
     * Delete video from Bunny Stream
     *
     * @param string $video_guid Video GUID
     * @return bool Success
     */
    public function delete_video($video_guid) {
        if (!$this->is_enabled()) {
            return false;
        }

        $url = "https://video.bunnycdn.com/library/{$this->library_id}/videos/{$video_guid}";
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'AccessKey' => $this->api_key
            ),
            'timeout' => 30
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get video thumbnail URL
     *
     * @param string $video_guid Video GUID
     * @param string $thumbnail_filename Thumbnail filename
     * @return string Thumbnail URL
     */
    public function get_thumbnail_url($video_guid, $thumbnail_filename = '') {
        if (empty($thumbnail_filename)) {
            $thumbnail_filename = 'thumbnail.jpg';
        }

        return "https://{$this->cdn_hostname}/{$video_guid}/{$thumbnail_filename}";
    }

    /**
     * Generate signed URL for file uploads
     *
     * @param string $path File path
     * @param int $expires_in Expiration in seconds
     * @return string Signed URL
     */
    public function generate_signed_upload_url($path, $expires_in = 3600) {
        if (empty($this->security_key)) {
            return "https://{$this->cdn_hostname}{$path}";
        }

        $expires_timestamp = time() + $expires_in;
        $auth_string = $this->security_key . $path . $expires_timestamp;
        $token = md5($auth_string);
        
        return "https://{$this->cdn_hostname}{$path}?token={$token}&expires={$expires_timestamp}";
    }

    /**
     * AJAX handler for video upload
     */
    public function ajax_upload_to_bunny() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $video_title = sanitize_text_field($_POST['video_title'] ?? '');
        
        if (empty($file_path) || !file_exists($file_path)) {
            wp_send_json_error(array('message' => 'Invalid file path'));
        }

        $video_data = array(
            'title' => $video_title,
            'generate_preview' => !empty($_POST['generate_preview'])
        );

        $result = $this->upload_video($file_path, $video_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for video info retrieval
     */
    public function ajax_get_video_info() {
        check_ajax_referer('vlp_ajax_nonce', 'nonce');
        
        $video_guid = sanitize_text_field($_POST['video_guid'] ?? '');
        
        if (empty($video_guid)) {
            wp_send_json_error(array('message' => 'Video GUID required'));
        }

        $video_info = $this->get_video_info($video_guid);
        
        if ($video_info) {
            wp_send_json_success($video_info);
        } else {
            wp_send_json_error(array('message' => 'Failed to retrieve video info'));
        }
    }

    /**
     * Check if video is ready for streaming
     *
     * @param string $video_guid Video GUID
     * @return bool
     */
    public function is_video_ready($video_guid) {
        $info = $this->get_video_info($video_guid);
        return $info && $info['status'] === 3; // 3 = finished encoding
    }

    /**
     * Get available video resolutions
     *
     * @param string $video_guid Video GUID
     * @return array Available resolutions
     */
    public function get_video_resolutions($video_guid) {
        $info = $this->get_video_info($video_guid);
        return $info ? $info['resolutions'] : array();
    }

    /**
     * Webhook handler for Bunny Stream processing updates
     *
     * @param array $webhook_data Webhook payload
     */
    public function handle_processing_webhook($webhook_data) {
        $video_guid = $webhook_data['VideoGuid'] ?? '';
        $status = $webhook_data['Status'] ?? '';
        
        if (empty($video_guid)) {
            return;
        }

        // Find video in database
        global $wpdb;
        $videos_table = $wpdb->prefix . 'vlp_videos';
        
        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$videos_table} WHERE bunny_full_guid = %s OR bunny_preview_guid = %s",
            $video_guid, $video_guid
        ));

        if (!$video) {
            return;
        }

        // Update video status based on Bunny webhook
        switch ($status) {
            case 3: // Finished
                $this->on_video_processing_complete($video, $video_guid);
                break;
            case 4: // Failed
                $this->on_video_processing_failed($video, $video_guid);
                break;
        }
    }

    /**
     * Handle successful video processing
     *
     * @param object $video Video record
     * @param string $video_guid Video GUID
     */
    private function on_video_processing_complete($video, $video_guid) {
        // Update video info with Bunny data
        $bunny_info = $this->get_video_info($video_guid);
        
        if ($bunny_info) {
            global $wpdb;
            $videos_table = $wpdb->prefix . 'vlp_videos';
            
            $update_data = array(
                'duration' => $bunny_info['length'],
                'file_size' => $bunny_info['file_size']
            );

            if ($bunny_info['thumbnail_url']) {
                $update_data['thumbnail_url'] = $bunny_info['thumbnail_url'];
            }

            $wpdb->update(
                $videos_table,
                $update_data,
                array('id' => $video->id),
                null,
                array('%d')
            );
        }

        // Send notification if needed
        do_action('vlp_video_processing_complete', $video, $video_guid);
    }

    /**
     * Handle failed video processing
     *
     * @param object $video Video record
     * @param string $video_guid Video GUID
     */
    private function on_video_processing_failed($video, $video_guid) {
        // Log error and notify
        error_log("VLP: Video processing failed for GUID: {$video_guid}");
        do_action('vlp_video_processing_failed', $video, $video_guid);
    }
}