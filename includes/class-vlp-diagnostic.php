<?php
/**
 * VLP System Integration Diagnostic
 * 
 * This file tests the complete integration between:
 * 1. GiftCode Protect Plugin
 * 2. Video Library Protect 
 * 3. Bunny.net Stream + DRM
 * 
 * Usage: Add this as a temporary admin page or run via AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_System_Diagnostic {

    public static function run_full_diagnostic() {
        error_log('=== VLP SYSTEM DIAGNOSTIC START ===');
        
        $results = array(
            'giftcode_integration' => self::test_giftcode_integration(),
            'bunny_integration' => self::test_bunny_integration(), 
            'database_tables' => self::test_database_tables(),
            'video_flow' => self::test_video_flow(),
            'settings_configuration' => self::test_settings_configuration()
        );
        
        error_log('=== VLP SYSTEM DIAGNOSTIC END ===');
        return $results;
    }

    /**
     * Test GiftCode Protect Plugin Integration
     */
    private static function test_giftcode_integration() {
        $results = array();
        
        // 1. Check if GiftCode Protect is active
        $gcp_active = class_exists('GiftCode_Manager');
        $results['giftcode_plugin_active'] = $gcp_active;
        error_log('VLP Diagnostic: GiftCode Plugin Active: ' . ($gcp_active ? 'YES' : 'NO'));
        
        if ($gcp_active) {
            // 2. Test GiftCode Manager instantiation
            try {
                $giftcode_manager = new GiftCode_Manager();
                $results['giftcode_manager_creation'] = true;
                error_log('VLP Diagnostic: GiftCode Manager created successfully');
                
                // 3. Test code validation (with a dummy code)
                $test_code = 'TEST123';
                $validation_result = $giftcode_manager->validate_code($test_code);
                $results['giftcode_validation_callable'] = true;
                error_log('VLP Diagnostic: GiftCode validation method callable');
                
            } catch (Exception $e) {
                $results['giftcode_manager_creation'] = false;
                $results['giftcode_error'] = $e->getMessage();
                error_log('VLP Diagnostic: GiftCode Manager error: ' . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Test Bunny.net Integration
     */
    private static function test_bunny_integration() {
        $results = array();
        
        // 1. Check Bunny integration class
        $bunny_integration = VLP_Bunny_Integration::get_instance();
        $results['bunny_class_exists'] = ($bunny_integration !== null);
        
        // 2. Check if Bunny is enabled
        $bunny_enabled = $bunny_integration->is_enabled();
        $results['bunny_enabled'] = $bunny_enabled;
        error_log('VLP Diagnostic: Bunny Integration Enabled: ' . ($bunny_enabled ? 'YES' : 'NO'));
        
        // 3. Check JWT library
        $jwt_exists = class_exists('Firebase\JWT\JWT');
        $results['jwt_library_loaded'] = $jwt_exists;
        error_log('VLP Diagnostic: JWT Library Loaded: ' . ($jwt_exists ? 'YES' : 'NO'));
        
        // 4. Check Bunny settings
        $settings = get_option('vlp_settings', array());
        $results['bunny_library_id'] = !empty($settings['bunny_library_id']);
        $results['bunny_api_key'] = !empty($settings['bunny_api_key']);  
        $results['bunny_drm_key'] = !empty($settings['bunny_drm_private_key']);
        
        error_log('VLP Diagnostic: Bunny Library ID set: ' . ($results['bunny_library_id'] ? 'YES' : 'NO'));
        error_log('VLP Diagnostic: Bunny API Key set: ' . ($results['bunny_api_key'] ? 'YES' : 'NO'));
        error_log('VLP Diagnostic: Bunny DRM Key set: ' . ($results['bunny_drm_key'] ? 'YES' : 'NO'));
        
        // 5. Test JWT token generation (if DRM key exists)
        if ($results['bunny_drm_key'] && $jwt_exists) {
            try {
                $test_url = $bunny_integration->get_secure_stream_url('test-guid-12345', 3600, false);
                $results['jwt_generation_test'] = !empty($test_url);
                error_log('VLP Diagnostic: JWT Generation Test: ' . ($results['jwt_generation_test'] ? 'SUCCESS' : 'FAILED'));
                if ($test_url) {
                    error_log('VLP Diagnostic: Test URL generated: ' . $test_url);
                }
            } catch (Exception $e) {
                $results['jwt_generation_test'] = false;
                $results['jwt_error'] = $e->getMessage();
                error_log('VLP Diagnostic: JWT Generation Error: ' . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Test Database Tables
     */
    private static function test_database_tables() {
        global $wpdb;
        
        $tables = array(
            'vlp_videos' => $wpdb->prefix . 'vlp_videos',
            'vlp_categories' => $wpdb->prefix . 'vlp_categories', 
            'vlp_video_categories' => $wpdb->prefix . 'vlp_video_categories',
            'vlp_access_log' => $wpdb->prefix . 'vlp_access_log',
            'vlp_analytics' => $wpdb->prefix . 'vlp_analytics'
        );
        
        $results = array();
        
        foreach ($tables as $key => $table_name) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            $results[$key] = ($table_exists === $table_name);
            error_log('VLP Diagnostic: Table ' . $table_name . ' exists: ' . ($results[$key] ? 'YES' : 'NO'));
            
            if ($results[$key] && $key === 'vlp_videos') {
                // Count videos in main table
                $video_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $results['video_count'] = intval($video_count);
                error_log('VLP Diagnostic: Video count in database: ' . $video_count);
            }
        }
        
        return $results;
    }

    /**
     * Test Complete Video Flow
     */
    private static function test_video_flow() {
        $results = array();
        
        // 1. Test video manager
        $video_manager = VLP_Video_Manager::get_instance();
        $results['video_manager_exists'] = ($video_manager !== null);
        
        // 2. Test getting videos
        try {
            $videos = $video_manager->get_videos(array('limit' => 5));
            $results['video_retrieval'] = is_array($videos);
            $results['video_count_retrieved'] = count($videos);
            error_log('VLP Diagnostic: Video retrieval test: ' . ($results['video_retrieval'] ? 'SUCCESS' : 'FAILED'));
            error_log('VLP Diagnostic: Videos retrieved: ' . $results['video_count_retrieved']);
            
            // 3. Test video with protection
            foreach ($videos as $video) {
                if ($video->protection_level === 'gift_code') {
                    $results['protected_video_found'] = true;
                    $results['protected_video_id'] = $video->id;
                    $results['protected_video_title'] = $video->title;
                    error_log('VLP Diagnostic: Found protected video: ' . $video->title . ' (ID: ' . $video->id . ')');
                    
                    // Test access check
                    $protection_manager = VLP_Protection_Manager::get_instance();
                    $access_level = $protection_manager->check_video_access($video->id);
                    $results['access_check_result'] = $access_level;
                    error_log('VLP Diagnostic: Access check result: ' . $access_level);
                    break;
                }
            }
        } catch (Exception $e) {
            $results['video_retrieval'] = false;
            $results['video_retrieval_error'] = $e->getMessage();
            error_log('VLP Diagnostic: Video retrieval error: ' . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Test Settings Configuration
     */
    private static function test_settings_configuration() {
        $settings = get_option('vlp_settings', array());
        
        $results = array(
            'settings_exist' => !empty($settings),
            'bunny_configured' => (!empty($settings['bunny_library_id']) && !empty($settings['bunny_api_key'])),
            'drm_configured' => !empty($settings['bunny_drm_private_key']),
            'giftcode_integration_enabled' => !empty($settings['giftcode_integration']),
            'settings_data' => $settings
        );
        
        error_log('VLP Diagnostic: Settings configured: ' . ($results['settings_exist'] ? 'YES' : 'NO'));
        error_log('VLP Diagnostic: Bunny configured: ' . ($results['bunny_configured'] ? 'YES' : 'NO'));
        error_log('VLP Diagnostic: DRM configured: ' . ($results['drm_configured'] ? 'YES' : 'NO'));
        
        return $results;
    }

    /**
     * Generate diagnostic report
     */
    public static function generate_report() {
        $diagnostic_results = self::run_full_diagnostic();
        
        ob_start();
        ?>
        <div class="wrap">
            <h1>VLP System Diagnostic Report</h1>
            
            <div class="card">
                <h2>🎁 GiftCode Integration</h2>
                <ul>
                    <li>Plugin Active: <?php echo $diagnostic_results['giftcode_integration']['giftcode_plugin_active'] ? '✅' : '❌'; ?></li>
                    <?php if (isset($diagnostic_results['giftcode_integration']['giftcode_manager_creation'])): ?>
                    <li>Manager Creation: <?php echo $diagnostic_results['giftcode_integration']['giftcode_manager_creation'] ? '✅' : '❌'; ?></li>
                    <li>Validation Callable: <?php echo $diagnostic_results['giftcode_integration']['giftcode_validation_callable'] ? '✅' : '❌'; ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h2>🐰 Bunny.net Integration</h2>
                <ul>
                    <li>Class Exists: <?php echo $diagnostic_results['bunny_integration']['bunny_class_exists'] ? '✅' : '❌'; ?></li>
                    <li>Enabled: <?php echo $diagnostic_results['bunny_integration']['bunny_enabled'] ? '✅' : '❌'; ?></li>
                    <li>JWT Library: <?php echo $diagnostic_results['bunny_integration']['jwt_library_loaded'] ? '✅' : '❌'; ?></li>
                    <li>Library ID Set: <?php echo $diagnostic_results['bunny_integration']['bunny_library_id'] ? '✅' : '❌'; ?></li>
                    <li>API Key Set: <?php echo $diagnostic_results['bunny_integration']['bunny_api_key'] ? '✅' : '❌'; ?></li>
                    <li>DRM Key Set: <?php echo $diagnostic_results['bunny_integration']['bunny_drm_key'] ? '✅' : '❌'; ?></li>
                    <?php if (isset($diagnostic_results['bunny_integration']['jwt_generation_test'])): ?>
                    <li>JWT Generation: <?php echo $diagnostic_results['bunny_integration']['jwt_generation_test'] ? '✅' : '❌'; ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h2>🗄️ Database Tables</h2>
                <ul>
                    <li>Videos Table: <?php echo $diagnostic_results['database_tables']['vlp_videos'] ? '✅' : '❌'; ?></li>
                    <li>Categories Table: <?php echo $diagnostic_results['database_tables']['vlp_categories'] ? '✅' : '❌'; ?></li>
                    <li>Relations Table: <?php echo $diagnostic_results['database_tables']['vlp_video_categories'] ? '✅' : '❌'; ?></li>
                    <li>Access Log Table: <?php echo $diagnostic_results['database_tables']['vlp_access_log'] ? '✅' : '❌'; ?></li>
                    <li>Analytics Table: <?php echo $diagnostic_results['database_tables']['vlp_analytics'] ? '✅' : '❌'; ?></li>
                    <?php if (isset($diagnostic_results['database_tables']['video_count'])): ?>
                    <li>Videos in DB: <?php echo $diagnostic_results['database_tables']['video_count']; ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h2>🎬 Video Flow Test</h2>
                <ul>
                    <li>Video Manager: <?php echo $diagnostic_results['video_flow']['video_manager_exists'] ? '✅' : '❌'; ?></li>
                    <li>Video Retrieval: <?php echo $diagnostic_results['video_flow']['video_retrieval'] ? '✅' : '❌'; ?></li>
                    <?php if (isset($diagnostic_results['video_flow']['video_count_retrieved'])): ?>
                    <li>Videos Retrieved: <?php echo $diagnostic_results['video_flow']['video_count_retrieved']; ?></li>
                    <?php endif; ?>
                    <?php if (isset($diagnostic_results['video_flow']['protected_video_found'])): ?>
                    <li>Protected Video Found: ✅ (<?php echo $diagnostic_results['video_flow']['protected_video_title']; ?>)</li>
                    <li>Access Check: <?php echo $diagnostic_results['video_flow']['access_check_result']; ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Add admin page for diagnostic
add_action('admin_menu', function() {
    add_submenu_page(
        'vlp_admin',
        'System Diagnostic',
        'Diagnostic', 
        'manage_options',
        'vlp_diagnostic',
        function() {
            echo VLP_System_Diagnostic::generate_report();
        }
    );
});