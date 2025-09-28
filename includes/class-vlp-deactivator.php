<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    Video_Library_Protect
 * @subpackage Video_Library_Protect/includes
 */
class VLP_Deactivator {

    /**
     * Main deactivation method.
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
