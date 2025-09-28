<?php
/**
 * Presto Player Integration Class
 *
 * Provides a light wrapper around the optional Presto Player plugin so that
 * the Video Library Protect plugin can safely interact with it when available.
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Presto_Integration {

    /**
     * Singleton instance
     *
     * @var VLP_Presto_Integration|null
     */
    private static $instance = null;

    /**
     * Flag indicating if Presto Player integration is active.
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Get instance
     *
     * @return VLP_Presto_Integration
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
        $settings       = get_option('vlp_settings', array());
        $presto_enabled = !empty($settings['presto_player_enabled']);

        $this->enabled = $presto_enabled && self::plugin_available();
    }

    /**
     * Check if the integration is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Determine if the Presto Player plugin is available.
     *
     * @return bool
     */
    public static function plugin_available() {
        if (function_exists('did_action') && did_action('presto_player/loaded')) {
            return true;
        }

        return class_exists('PrestoPlayer')
            || class_exists('PrestoPlayer\\PrestoPlayer')
            || function_exists('presto_player')
            || defined('PRESTO_PLAYER_VERSION');
    }
}
