<?php
/**
 * Complete installation script for S3 Video submission plugin.
 * Run this script to ensure the plugin is properly registered in Moodle.
 *
 * Usage: php complete_installation.php
 * Or access via browser: http://yourmoodle/mod/assign/submission/s3video/complete_installation.php
 *
 * @package   assignsubmission_s3video
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Find and include Moodle config.
$configpath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php';
if (!file_exists($configpath)) {
    die("Error: Could not find Moodle config.php\n");
}

require_once($configpath);
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/upgradelib.php');

// Require admin login if accessed via browser.
if (!CLI_SCRIPT) {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
}

echo "S3 Video Plugin Installation Completion Script\n";
echo "===============================================\n\n";

// Step 1: Check if plugin directory exists.
echo "Step 1: Checking plugin directory...\n";
$plugindir = $CFG->dirroot . '/mod/assign/submission/s3video';
if (!is_dir($plugindir)) {
    die("Error: Plugin directory not found at: $plugindir\n");
}
echo "✓ Plugin directory exists\n\n";

// Step 2: Check if lib.php exists and contains the class.
echo "Step 2: Checking lib.php...\n";
$libfile = $plugindir . '/lib.php';
if (!file_exists($libfile)) {
    die("Error: lib.php not found\n");
}

require_once($libfile);

if (!class_exists('assign_submission_s3video')) {
    die("Error: Class 'assign_submission_s3video' not found in lib.php\n");
}
echo "✓ lib.php exists and class is defined\n\n";

// Step 3: Check version.php.
echo "Step 3: Checking version.php...\n";
$versionfile = $plugindir . '/version.php';
if (!file_exists($versionfile)) {
    die("Error: version.php not found\n");
}

$plugin = new stdClass();
require($versionfile);

if (!isset($plugin->component) || $plugin->component !== 'assignsubmission_s3video') {
    die("Error: Invalid component in version.php\n");
}
echo "✓ version.php is valid\n";
echo "  Component: {$plugin->component}\n";
echo "  Version: {$plugin->version}\n\n";

// Step 4: Purge all caches.
echo "Step 4: Purging caches...\n";
purge_all_caches();
echo "✓ Caches purged\n\n";

// Step 5: Check if plugin is registered.
echo "Step 5: Checking plugin registration...\n";
$pluginman = core_plugin_manager::instance();
$pluginman->reset_caches();

$plugininfo = $pluginman->get_plugin_info('assignsubmission_s3video');
if ($plugininfo) {
    echo "✓ Plugin is registered\n";
    echo "  Name: {$plugininfo->displayname}\n";
    echo "  Version: {$plugininfo->versiondb}\n";
    echo "  Status: " . ($plugininfo->is_enabled() ? 'Enabled' : 'Disabled') . "\n\n";
} else {
    echo "⚠ Plugin not yet registered (this is normal for first install)\n\n";
}

// Step 6: Check database tables.
echo "Step 6: Checking database tables...\n";
$dbman = $DB->get_manager();

$table1 = new xmldb_table('assignsubmission_s3video');
$table2 = new xmldb_table('assignsubmission_s3v_log');

if ($dbman->table_exists($table1)) {
    echo "✓ Table 'assignsubmission_s3video' exists\n";
    $count = $DB->count_records('assignsubmission_s3video');
    echo "  Records: $count\n";
} else {
    echo "⚠ Table 'assignsubmission_s3video' does not exist yet\n";
    echo "  This is normal if you haven't completed the Moodle upgrade\n";
}

if ($dbman->table_exists($table2)) {
    echo "✓ Table 'assignsubmission_s3v_log' exists\n";
    $count = $DB->count_records('assignsubmission_s3v_log');
    echo "  Records: $count\n";
} else {
    echo "⚠ Table 'assignsubmission_s3v_log' does not exist yet\n";
    echo "  This is normal if you haven't completed the Moodle upgrade\n";
}
echo "\n";

// Step 7: Check plugin configuration.
echo "Step 7: Checking plugin configuration...\n";
$configs = array(
    'aws_access_key' => get_config('assignsubmission_s3video', 'aws_access_key'),
    'aws_secret_key' => get_config('assignsubmission_s3video', 'aws_secret_key'),
    's3_bucket' => get_config('assignsubmission_s3video', 's3_bucket'),
    's3_region' => get_config('assignsubmission_s3video', 's3_region'),
    'cloudfront_domain' => get_config('assignsubmission_s3video', 'cloudfront_domain'),
    'cloudfront_keypair_id' => get_config('assignsubmission_s3video', 'cloudfront_keypair_id'),
    'cloudfront_private_key' => get_config('assignsubmission_s3video', 'cloudfront_private_key'),
);

$configured = true;
foreach ($configs as $key => $value) {
    if (empty($value)) {
        echo "⚠ $key: Not configured\n";
        $configured = false;
    } else {
        if ($key === 'aws_secret_key' || $key === 'cloudfront_private_key') {
            echo "✓ $key: Configured (hidden)\n";
        } else {
            $displayvalue = strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
            echo "✓ $key: $displayvalue\n";
        }
    }
}
echo "\n";

// Step 8: Check if plugin is enabled in assignment plugins.
echo "Step 8: Checking plugin status in assignment module...\n";
$disabled = get_config('assignsubmission_s3video', 'disabled');
if (empty($disabled)) {
    echo "✓ Plugin is enabled globally\n";
} else {
    echo "⚠ Plugin is disabled globally\n";
    echo "  To enable: Go to Site administration > Plugins > Activity modules > Assignment > Submission plugins\n";
}
echo "\n";

// Summary.
echo "===============================================\n";
echo "Installation Status Summary\n";
echo "===============================================\n\n";

if ($plugininfo && $dbman->table_exists($table1) && $configured) {
    echo "✓ Plugin is fully installed and configured\n\n";
    echo "Next steps:\n";
    echo "1. Go to Site administration > Plugins > Activity modules > Assignment > Submission plugins\n";
    echo "2. Ensure 'S3 Video' is enabled (eye icon should be open)\n";
    echo "3. Create or edit an assignment\n";
    echo "4. Enable 'S3 Video' submission type in assignment settings\n";
} else {
    echo "⚠ Plugin installation is incomplete\n\n";
    echo "Required actions:\n";
    
    if (!$plugininfo) {
        echo "1. Go to Site administration > Notifications\n";
        echo "2. Click 'Upgrade Moodle database now'\n";
        echo "3. This will register the plugin and create database tables\n\n";
    }
    
    if (!$configured) {
        echo "4. Go to Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video\n";
        echo "5. Configure AWS credentials and settings\n\n";
    }
}

echo "For more information, see README.md in the plugin directory\n";

if (!CLI_SCRIPT) {
    echo "\n<br><br><a href='{$CFG->wwwroot}/admin/index.php'>Go to Site Administration</a>";
}

