<?php
/**
 * Test script to verify admin pages work correctly
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<!DOCTYPE html><html><head><title>Admin Pages Test</title></head><body>";
echo "<h1>Cloudflare Stream Admin Pages Test</h1>";

echo "<h2>Test Results:</h2>";
echo "<ul>";

// Test 1: Check if we can access this page
echo "<li>✅ Admin authentication working</li>";

// Test 2: Check if context is set correctly
$context = context_system::instance();
echo "<li>✅ System context available: " . $context->id . "</li>";

// Test 3: Check if language strings exist
$strings_to_check = [
    'dashboard',
    'videomanagement',
    'uploadstatistics',
    'totaluploads'
];

$missing_strings = [];
foreach ($strings_to_check as $string) {
    if (!get_string_manager()->string_exists($string, 'assignsubmission_cloudflarestream')) {
        $missing_strings[] = $string;
    }
}

if (empty($missing_strings)) {
    echo "<li>✅ All required language strings exist</li>";
} else {
    echo "<li>❌ Missing language strings: " . implode(', ', $missing_strings) . "</li>";
}

// Test 4: Check if logger class exists
if (class_exists('assignsubmission_cloudflarestream\logger')) {
    echo "<li>✅ Logger class exists</li>";
} else {
    echo "<li>❌ Logger class not found</li>";
}

echo "</ul>";

echo "<h2>Access Admin Pages:</h2>";
echo "<ul>";
echo "<li><a href='dashboard.php'>Dashboard</a></li>";
echo "<li><a href='videomanagement.php'>Video Management</a></li>";
echo "</ul>";

echo "<p><a href='" . $CFG->wwwroot . "/admin/settings.php?section=assignsubmission_cloudflarestream'>Back to Plugin Settings</a></p>";

echo "</body></html>";
