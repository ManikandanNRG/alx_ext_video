<?php
/**
 * Direct test to see if plugin can be instantiated
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/test_direct_load.php');
$PAGE->set_title('Direct Plugin Load Test');
$PAGE->set_heading('Direct Plugin Load Test');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Direct Plugin Instantiation Test');

// Get a test assignment
$assignment_record = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);

if (!$assignment_record) {
    echo html_writer::tag('p', 'No assignments found. Please create an assignment first.', ['class' => 'alert alert-warning']);
    echo $OUTPUT->footer();
    exit;
}

try {
    $cm = get_coursemodule_from_instance('assign', $assignment_record->id);
    $context = context_module::instance($cm->id);
    $assignment = new assign($context, $cm, $assignment_record->course);
    
    echo html_writer::tag('p', 'Testing with assignment: ' . $assignment_record->name);
    
    // Try to directly instantiate our plugin
    echo html_writer::tag('h3', 'Attempting Direct Instantiation');
    
    $plugin = new assign_submission_cloudflarestream($assignment, 'cloudflarestream');
    
    echo html_writer::tag('p', '✓ Plugin instantiated successfully!', ['class' => 'alert alert-success']);
    
    // Test methods
    echo html_writer::tag('h3', 'Testing Plugin Methods');
    
    echo html_writer::start_tag('ul');
    
    // Test get_name()
    try {
        $name = $plugin->get_name();
        echo html_writer::tag('li', '✓ get_name(): ' . $name, ['class' => 'text-success']);
    } catch (Exception $e) {
        echo html_writer::tag('li', '✗ get_name() failed: ' . $e->getMessage(), ['class' => 'text-danger']);
    }
    
    // Test is_enabled()
    try {
        $enabled = $plugin->is_enabled();
        echo html_writer::tag('li', '✓ is_enabled(): ' . ($enabled ? 'TRUE' : 'FALSE'), ['class' => 'text-success']);
    } catch (Exception $e) {
        echo html_writer::tag('li', '✗ is_enabled() failed: ' . $e->getMessage(), ['class' => 'text-danger']);
    }
    
    // Test is_visible()
    try {
        $visible = $plugin->is_visible();
        echo html_writer::tag('li', '✓ is_visible(): ' . ($visible ? 'TRUE' : 'FALSE'), ['class' => 'text-success']);
    } catch (Exception $e) {
        echo html_writer::tag('li', '✗ is_visible() failed: ' . $e->getMessage(), ['class' => 'text-danger']);
    }
    
    // Test is_configured()
    try {
        $configured = $plugin->is_configured();
        echo html_writer::tag('li', '✓ is_configured(): ' . ($configured ? 'TRUE' : 'FALSE'), ['class' => 'text-success']);
    } catch (Exception $e) {
        echo html_writer::tag('li', '✗ is_configured() failed: ' . $e->getMessage(), ['class' => 'text-danger']);
    }
    
    // Test get_config()
    try {
        $config_enabled = $plugin->get_config('enabled');
        echo html_writer::tag('li', '✓ get_config("enabled"): ' . var_export($config_enabled, true), ['class' => 'text-success']);
    } catch (Exception $e) {
        echo html_writer::tag('li', '✗ get_config("enabled") failed: ' . $e->getMessage(), ['class' => 'text-danger']);
    }
    
    echo html_writer::end_tag('ul');
    
    // Now check what assignment sees
    echo html_writer::tag('h3', 'What Assignment Object Sees');
    
    $all_plugins = $assignment->get_submission_plugins();
    echo html_writer::tag('p', 'Total plugins loaded by assignment: ' . count($all_plugins));
    
    echo html_writer::start_tag('ul');
    $found = false;
    foreach ($all_plugins as $p) {
        $pname = get_class($p);
        if ($pname === 'assign_submission_cloudflarestream') {
            $found = true;
            echo html_writer::tag('li', '✓ ' . $pname . ' - FOUND!', ['class' => 'text-success font-weight-bold']);
        } else {
            echo html_writer::tag('li', $pname);
        }
    }
    echo html_writer::end_tag('ul');
    
    if (!$found) {
        echo html_writer::tag('div', '✗ Plugin NOT found in assignment\'s plugin list', ['class' => 'alert alert-danger']);
        
        // Check plugin directory
        echo html_writer::tag('h3', 'Checking Plugin Discovery');
        $plugin_dir = $CFG->dirroot . '/mod/assign/submission';
        $subdirs = scandir($plugin_dir);
        
        echo html_writer::tag('p', 'Directories in ' . $plugin_dir . ':');
        echo html_writer::start_tag('ul');
        foreach ($subdirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($plugin_dir . '/' . $dir)) {
                $has_version = file_exists($plugin_dir . '/' . $dir . '/version.php');
                $has_lib = file_exists($plugin_dir . '/' . $dir . '/lib.php');
                
                if ($dir === 'cloudflarestream') {
                    echo html_writer::tag('li', 
                        html_writer::tag('strong', $dir . ' (THIS PLUGIN)') . 
                        ' - version.php: ' . ($has_version ? 'YES' : 'NO') . 
                        ', lib.php: ' . ($has_lib ? 'YES' : 'NO'),
                        ['class' => 'text-primary']
                    );
                } else {
                    echo html_writer::tag('li', 
                        $dir . ' - version.php: ' . ($has_version ? 'YES' : 'NO') . 
                        ', lib.php: ' . ($has_lib ? 'YES' : 'NO')
                    );
                }
            }
        }
        echo html_writer::end_tag('ul');
    }
    
} catch (Exception $e) {
    echo html_writer::tag('p', '✗ Error: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
    echo html_writer::tag('pre', $e->getTraceAsString());
}

echo $OUTPUT->footer();
