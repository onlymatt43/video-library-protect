<?php
/**
 * Integration test script for GiftCode Protect <-> Video Library Protect
 *
 * Usage (from CLI):
 *  php integration-test.php --wp-path=/path/to/wordpress
 *
 * This script will:
 *  - bootstrap WordPress (wp-load.php)
 *  - create a temporary gift code using GiftCode_Manager
 *  - start a PHP session and trigger do_action('giftcode_protect.code_granted', ...)
 *  - verify that VLP created a transient `vlp_session_codes_{session_id}` containing the code
 *  - clean up created test data
 *
 * NOTE: Run this on a local/staging site only. It will create and delete a code record.
 */

// Simple CLI arg parsing
$wp_path = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--wp-path=') === 0) {
        $wp_path = substr($arg, strlen('--wp-path='));
        break;
    }
}

if (!$wp_path) {
    // try current directory
    $wp_path = getcwd();
}

$wp_load = rtrim($wp_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!file_exists($wp_load)) {
    fwrite(STDERR, "Could not find wp-load.php at: {$wp_load}\n");
    fwrite(STDERR, "Please provide --wp-path pointing to your WordPress installation root.\n");
    exit(2);
}

// Bootstrap WordPress
require_once $wp_load;

echo "WordPress loaded. Running integration test...\n";

// Basic pre-checks
if (!class_exists('GiftCode_Manager')) {
    fwrite(STDERR, "GiftCode_Manager class not found. Ensure GiftCode Protect plugin is active.\n");
    exit(3);
}

if (!class_exists('VLP_Protection_Manager')) {
    fwrite(STDERR, "VLP_Protection_Manager class not found. Ensure Video Library Protect plugin is active.\n");
    exit(4);
}

// Start session to emulate a visitor
if (!session_id()) {
    @session_start();
}
$session_id = session_id();

// Create a test gift code (short, predictable)
$code = 'TESTVLP' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
$manager = new GiftCode_Manager();

try {
    $code_id = $manager->create_code($code, 1 /* days */, 0, 0, 10 /* max uses */);
    if (!$code_id) {
        fwrite(STDERR, "Failed to create test gift code.\n");
        exit(5);
    }
    echo "Created test gift code: {$code} (id={$code_id})\n";
} catch (Exception $e) {
    fwrite(STDERR, "Exception creating code: " . $e->getMessage() . "\n");
    exit(6);
}

// Fire the action as if GiftCode plugin granted access
do_action('giftcode_protect.code_granted', $code, null, $session_id);

// Allow some time for hooks/transients to be set (not usually necessary)
sleep(1);

$transient_key = 'vlp_session_codes_' . $session_id;
$stored = get_transient($transient_key);

if (is_array($stored) && in_array($code, $stored)) {
    echo "SUCCESS: VLP stored the code in transient ({$transient_key}).\n";
    echo "Transient contents: " . print_r($stored, true) . "\n";
} else {
    echo "FAIL: VLP did not store the code in transient '{$transient_key}'.\n";
    echo "Transient value: " . var_export($stored, true) . "\n";
}

// Clean up: remove created gift code and transient
global $wpdb;
$gift_table = $wpdb->prefix . 'giftcode_codes';
$deleted = $wpdb->delete($gift_table, ['id' => intval($code_id)], ['%d']);
if ($deleted !== false) {
    echo "Deleted test gift code id={$code_id}.\n";
} else {
    echo "Warning: failed to delete test gift code id={$code_id}. You may want to remove it manually.\n";
}

delete_transient($transient_key);

echo "Integration test finished.\n";

return 0;
