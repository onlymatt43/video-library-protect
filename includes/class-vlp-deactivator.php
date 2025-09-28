<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      2.0.0
 * @package    Video_Library_Protect
 * @subpackage Video_Library_Protect/includes
 * @author     Mathieu Courchesne <mathieu.courchesne@gmail.com>
 */
class VLP_Deactivator {

    /**
     * Main deactivation method.
     *
     * @since 2.0.0
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
