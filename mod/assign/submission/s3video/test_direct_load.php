<?php
/**
 * Test direct loading of the S3 Video plugin class.
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

// Require admin login if accessed via browser.
if (!CLI_SCRIPT) {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
}

echo "<h2>S3 Video Plugin Class Loading Test</h2>";
echo "<pre>";

echo "Test 1: Check if lib.php exists\n";
echo "================================\n";
$libpath = $CFG->dirroot . '/mod/assign/submission/s3video/lib.php';
if (file_exists($libpath)) {
    echo "✓ lib.php exists at: $libpath\n\n";
} else {
    die("✗ lib.php NOT found\n");
}

echo "Test 2: Include lib.php directly\n";
echo "================================\n";
require_once($libpath);
echo "✓ lib.php included successfully\n\n";

echo "Test 3: Check if class exists\n";
echo "================================\n";
if (class_exists('assign_submission_s3video')) {
    echo "✓ Class 'assign_submission_s3video' exists\n\n";
} else {
    die("✗ Class 'assign_submission_s3video' NOT found\n");
}

echo "Test 4: Check parent class\n";
echo "================================\n";
$parentfile = $CFG->dirroot . '/mod/assign/submissionplugin.php';
if (file_exists($parentfile)) {
    echo "✓ Parent class file exists: $parentfile\n";
    require_once($parentfile);
    if (class_exists('assign_submission_plugin')) {
        echo "✓ Parent class 'assign_submission_plugin' exists\n\n";
    } else {
        echo "✗ Parent class 'assign_submission_plugin' NOT found\n\n";
    }
} else {
    echo "✗ Parent class file NOT found\n\n";
}

echo "Test 5: Try to instantiate the class\n";
echo "================================\n";
try {
    // We can't fully instantiate without an assignment object, but we can check the class structure
    $reflection = new ReflectionClass('assign_submission_s3video');
    echo "✓ Class can be reflected\n";
    echo "  Methods: " . count($reflection->getMethods()) . "\n";
    echo "  Parent: " . $reflection->getParentClass()->getName() . "\n\n";
    
    echo "  Key methods:\n";
    $methods = ['get_name', 'get_settings', 'is_enabled', 'save', 'view', 'get_form_elements'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "  ✓ $method()\n";
        } else {
            echo "  ✗ $method() MISSING\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

echo "Test 6: Check how Moodle loads the plugin\n";
echo "================================\n";
$pluginman = core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('assignsubmission_s3video');

if ($plugininfo) {
    echo "✓ Plugin info found\n";
    echo "  Root dir: " . $plugininfo->rootdir . "\n";
    echo "  Type dir: " . $plugininfo->typerootdir . "\n";
    echo "  Full path: " . $plugininfo->full_path('lib.php') . "\n\n";
} else {
    echo "✗ Plugin info NOT found\n\n";
}

echo "Test 7: Simulate Moodle's plugin loading\n";
echo "================================\n";
try {
    // This is how Moodle loads submission plugins
    $plugindir = $CFG->dirroot . '/mod/assign/submission/s3video';
    $libfile = $plugindir . '/lib.php';
    
    if (file_exists($libfile)) {
        require_once($libfile);
        
        if (class_exists('assign_submission_s3video')) {
            echo "✓ Class loaded successfully via Moodle's method\n\n";
        } else {
            echo "✗ Class NOT found after including lib.php\n\n";
        }
    } else {
        echo "✗ lib.php not found at expected location\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

echo "Test 8: Check if there are any PHP errors in lib.php\n";
echo "================================\n";
$libcontent = file_get_contents($libpath);
if (strpos($libcontent, 'class assign_submission_s3video') !== false) {
    echo "✓ Class definition found in lib.php\n";
} else {
    echo "✗ Class definition NOT found in lib.php\n";
}

if (strpos($libcontent, 'extends assign_submission_plugin') !== false) {
    echo "✓ Class extends assign_submission_plugin\n";
} else {
    echo "✗ Class does NOT extend assign_submission_plugin\n";
}
echo "\n";

echo "Test 9: Check plugin in database\n";
echo "================================\n";
$version = $DB->get_field('config_plugins', 'value', 
    ['plugin' => 'assignsubmission_s3video', 'name' => 'version']);
if ($version) {
    echo "✓ Plugin version in database: $version\n";
} else {
    echo "✗ Plugin version NOT found in database\n";
}

$disabled = $DB->get_field('config_plugins', 'value',
    ['plugin' => 'assignsubmission_s3video', 'name' => 'disabled']);
if ($disabled === false || $disabled === '0' || $disabled === null) {
    echo "✓ Plugin is enabled\n";
} else {
    echo "✗ Plugin is disabled\n";
}
echo "\n";

echo "================================\n";
echo "All tests completed!\n";
echo "================================\n";

echo "</pre>";

if (!CLI_SCRIPT) {
    echo "<br><a href='{$CFG->wwwroot}/admin/index.php'>Go to Site Administration</a>";
}
