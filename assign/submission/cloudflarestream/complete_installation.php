<?php
/**
 * Complete the plugin installation after fixing version.php
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/complete_installation.php');
$PAGE->set_title('Complete Plugin Installation');
$PAGE->set_heading('Complete Plugin Installation');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Complete Cloudflare Stream Plugin Installation');

// Step 1: Verify version.php is fixed
echo html_writer::tag('h3', 'Step 1: Verify version.php Fix');

$version_file = __DIR__ . '/version.php';
$plugin = null;

try {
    include($version_file);
    
    if (isset($plugin) && is_object($plugin)) {
        echo html_writer::tag('p', '✓ version.php is now correct', ['class' => 'alert alert-success']);
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', 'Version: ' . $plugin->version);
        echo html_writer::tag('li', 'Component: ' . $plugin->component);
        echo html_writer::tag('li', 'Release: ' . $plugin->release);
        echo html_writer::end_tag('ul');
    } else {
        echo html_writer::tag('p', '✗ version.php still has issues', ['class' => 'alert alert-danger']);
        echo $OUTPUT->footer();
        exit;
    }
} catch (Exception $e) {
    echo html_writer::tag('p', '✗ Error loading version.php: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
    echo $OUTPUT->footer();
    exit;
}

// Step 2: Purge caches
echo html_writer::tag('h3', 'Step 2: Purge All Caches');

try {
    purge_all_caches();
    echo html_writer::tag('p', '✓ All caches purged successfully', ['class' => 'alert alert-success']);
} catch (Exception $e) {
    echo html_writer::tag('p', '⚠ Warning: ' . $e->getMessage(), ['class' => 'alert alert-warning']);
}

// Step 3: Check plugin manager
echo html_writer::tag('h3', 'Step 3: Verify Plugin Manager Registration');

$plugin_manager = core_plugin_manager::instance();
$plugin_info = $plugin_manager->get_plugin_info('assignsubmission_cloudflarestream');

if ($plugin_info) {
    echo html_writer::tag('p', '✓ Plugin is registered in plugin manager', ['class' => 'alert alert-success']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Name: ' . $plugin_info->displayname);
    echo html_writer::tag('li', 'Version: ' . $plugin_info->versiondb);
    echo html_writer::tag('li', 'Enabled: ' . ($plugin_info->is_enabled() ? 'YES' : 'NO'));
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', '⚠ Plugin not yet in plugin manager - needs database upgrade', ['class' => 'alert alert-warning']);
}

// Step 4: Set default configuration
echo html_writer::tag('h3', 'Step 4: Set Default Configuration');

$configs_to_set = [
    'enabled' => 1,
    'default' => 1
];

foreach ($configs_to_set as $name => $value) {
    $current = get_config('assignsubmission_cloudflarestream', $name);
    
    if ($current === false || $current != $value) {
        set_config($name, $value, 'assignsubmission_cloudflarestream');
        echo html_writer::tag('p', '✓ Set ' . $name . ' = ' . $value);
    } else {
        echo html_writer::tag('p', '✓ ' . $name . ' already set to ' . $value);
    }
}

echo html_writer::tag('p', '✓ Configuration complete', ['class' => 'alert alert-success']);

// Step 5: Test plugin loading
echo html_writer::tag('h3', 'Step 5: Test Plugin Loading');

$test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);

if ($test_assignment) {
    try {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once(__DIR__ . '/lib.php');
        
        $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
        $context = context_module::instance($cm->id);
        $assignment = new assign($context, $cm, $test_assignment->course);
        
        // Try to instantiate plugin
        $plugin = new assign_submission_cloudflarestream($assignment, 'cloudflarestream');
        
        echo html_writer::tag('p', '✓ Plugin class instantiated successfully', ['class' => 'alert alert-success']);
        
        // Check methods
        $name = $plugin->get_name();
        $enabled = $plugin->is_enabled();
        
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', 'Plugin name: ' . $name);
        echo html_writer::tag('li', 'Is enabled: ' . ($enabled ? 'YES' : 'NO'));
        echo html_writer::end_tag('ul');
        
        // Check if it appears in assignment plugins
        $all_plugins = $assignment->get_submission_plugins();
        $found = false;
        
        foreach ($all_plugins as $p) {
            if (get_class($p) === 'assign_submission_cloudflarestream') {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            echo html_writer::tag('p', '✓ SUCCESS! Plugin appears in assignment submission plugins!', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '⚠ Plugin loaded but not appearing in assignment yet', ['class' => 'alert alert-warning']);
            echo html_writer::tag('p', 'This may require enabling it per assignment or running the database upgrade.');
        }
        
    } catch (Exception $e) {
        echo html_writer::tag('p', '✗ Error: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
    }
}

// Final instructions
echo html_writer::tag('h3', 'Next Steps');

echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::tag('h4', 'To complete the installation:');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', html_writer::tag('strong', 'Go to: Site administration → Notifications'));
echo html_writer::tag('li', 'Moodle should now detect the plugin and show an upgrade screen');
echo html_writer::tag('li', 'Click "Upgrade Moodle database now" to complete the installation');
echo html_writer::tag('li', 'After upgrade, the plugin will appear in assignment submission types');
echo html_writer::end_tag('ol');

echo html_writer::tag('p', 
    html_writer::link(
        new moodle_url('/admin/index.php'),
        'Go to Site Administration → Notifications',
        ['class' => 'btn btn-primary btn-lg']
    )
);

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
