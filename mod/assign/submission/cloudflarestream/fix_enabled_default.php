<?php
/**
 * Fix script to set the default enabled value for Cloudflare Stream plugin
 * 
 * This ensures the plugin appears in assignment submission types by default
 */

require_once(__DIR__ . '/../../../../config.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/fix_enabled_default.php');
$PAGE->set_title('Fix Cloudflare Stream Default Settings');
$PAGE->set_heading('Fix Cloudflare Stream Default Settings');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Fixing Cloudflare Stream Plugin Default Settings');

// Check current state
$current_enabled = get_config('assignsubmission_cloudflarestream', 'enabled');
$current_default = get_config('assignsubmission_cloudflarestream', 'default');

echo html_writer::tag('h3', 'Current State');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'enabled: ' . var_export($current_enabled, true));
echo html_writer::tag('li', 'default: ' . var_export($current_default, true));
echo html_writer::end_tag('ul');

// Set the values
echo html_writer::tag('h3', 'Applying Fixes');

$fixes_applied = [];
$errors = [];

// Fix 1: Set 'enabled' to 1 if not set or false
if ($current_enabled === false || $current_enabled == 0) {
    try {
        set_config('enabled', 1, 'assignsubmission_cloudflarestream');
        $fixes_applied[] = 'Set "enabled" to 1';
    } catch (Exception $e) {
        $errors[] = 'Failed to set "enabled": ' . $e->getMessage();
    }
} else {
    $fixes_applied[] = '"enabled" already set to ' . $current_enabled;
}

// Fix 2: Set 'default' to 1 if not set
if ($current_default === false) {
    try {
        set_config('default', 1, 'assignsubmission_cloudflarestream');
        $fixes_applied[] = 'Set "default" to 1';
    } catch (Exception $e) {
        $errors[] = 'Failed to set "default": ' . $e->getMessage();
    }
} else {
    $fixes_applied[] = '"default" already set to ' . $current_default;
}

// Display results
if (!empty($fixes_applied)) {
    echo html_writer::tag('div', 
        html_writer::tag('h4', 'Fixes Applied:') . 
        html_writer::alist($fixes_applied),
        ['class' => 'alert alert-success']
    );
}

if (!empty($errors)) {
    echo html_writer::tag('div', 
        html_writer::tag('h4', 'Errors:') . 
        html_writer::alist($errors),
        ['class' => 'alert alert-danger']
    );
}

// Verify new state
$new_enabled = get_config('assignsubmission_cloudflarestream', 'enabled');
$new_default = get_config('assignsubmission_cloudflarestream', 'default');

echo html_writer::tag('h3', 'New State');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'enabled: ' . var_export($new_enabled, true));
echo html_writer::tag('li', 'default: ' . var_export($new_default, true));
echo html_writer::end_tag('ul');

// Purge caches
echo html_writer::tag('h3', 'Purging Caches');
try {
    purge_all_caches();
    echo html_writer::tag('p', '✓ All caches purged successfully', ['class' => 'alert alert-success']);
} catch (Exception $e) {
    echo html_writer::tag('p', '✗ Failed to purge caches: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
}

// Test with assignment
echo html_writer::tag('h3', 'Testing Plugin Availability');

$test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);

if ($test_assignment) {
    try {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once(__DIR__ . '/lib.php');
        
        $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
        $context = context_module::instance($cm->id);
        $assignment = new assign($context, $cm, $test_assignment->course);
        
        // Try direct instantiation
        $plugin = new assign_submission_cloudflarestream($assignment, 'cloudflarestream');
        $config_enabled = $plugin->get_config('enabled');
        $is_enabled = $plugin->is_enabled();
        
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', 'get_config("enabled"): ' . var_export($config_enabled, true));
        echo html_writer::tag('li', 'is_enabled(): ' . var_export($is_enabled, true));
        echo html_writer::end_tag('ul');
        
        // Check if plugin appears in assignment's plugin list
        $all_plugins = $assignment->get_submission_plugins();
        $found = false;
        
        foreach ($all_plugins as $p) {
            if (get_class($p) === 'assign_submission_cloudflarestream') {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            echo html_writer::tag('p', '✓ Plugin NOW appears in assignment plugin list!', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '✗ Plugin still NOT in assignment plugin list', ['class' => 'alert alert-warning']);
            echo html_writer::tag('p', 'Total plugins loaded: ' . count($all_plugins));
        }
        
    } catch (Exception $e) {
        echo html_writer::tag('p', 'Error testing: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
    }
} else {
    echo html_writer::tag('p', 'No assignments found to test with', ['class' => 'alert alert-info']);
}

echo html_writer::tag('h3', 'Next Steps');
echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Go to Site administration → Plugins → Activity modules → Assignment → Submission plugins');
echo html_writer::tag('li', 'Verify that "Cloudflare Stream video submission" appears in the list');
echo html_writer::tag('li', 'Create or edit an assignment');
echo html_writer::tag('li', 'Check the "Submission types" section - "Cloudflare Stream" should now appear');
echo html_writer::end_tag('ol');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
