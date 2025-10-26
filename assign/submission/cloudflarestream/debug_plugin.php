<?php
/**
 * Debug script to check why Cloudflare Stream plugin is not appearing in submission types
 * 
 * Access this file via: http://your-moodle-site/mod/assign/submission/cloudflarestream/debug_plugin.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set page context
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/debug_plugin.php');
$PAGE->set_title('Cloudflare Stream Plugin Debug');
$PAGE->set_heading('Cloudflare Stream Plugin Debug Information');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Cloudflare Stream Plugin Debug Information');

// 1. Check if plugin files exist
echo html_writer::tag('h3', '1. Plugin Files Check');
$files_to_check = [
    'version.php',
    'lib.php',
    'settings.php',
    'lang/en/assignsubmission_cloudflarestream.php',
    'db/install.xml'
];

echo html_writer::start_tag('ul');
foreach ($files_to_check as $file) {
    $filepath = __DIR__ . '/' . $file;
    $exists = file_exists($filepath);
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    $class = $exists ? 'text-success' : 'text-danger';
    echo html_writer::tag('li', 
        html_writer::tag('strong', $file . ': ', ['class' => $class]) . $status
    );
}
echo html_writer::end_tag('ul');

// 2. Check plugin registration in Moodle
echo html_writer::tag('h3', '2. Plugin Registration');
$plugin_manager = core_plugin_manager::instance();
$plugin_info = $plugin_manager->get_plugin_info('assignsubmission_cloudflarestream');

if ($plugin_info) {
    echo html_writer::tag('p', '✓ Plugin is registered in Moodle', ['class' => 'alert alert-success']);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Component: ' . $plugin_info->component);
    echo html_writer::tag('li', 'Version: ' . $plugin_info->versiondb);
    echo html_writer::tag('li', 'Release: ' . $plugin_info->release);
    echo html_writer::tag('li', 'Type: ' . $plugin_info->type);
    echo html_writer::tag('li', 'Root Path: ' . $plugin_info->rootdir);
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', '✗ Plugin is NOT registered in Moodle', ['class' => 'alert alert-danger']);
    echo html_writer::tag('p', 'This means Moodle does not recognize the plugin. You may need to run the upgrade process.');
}

// 3. Check database configuration
echo html_writer::tag('h3', '3. Database Configuration');
$configs = $DB->get_records('config_plugins', ['plugin' => 'assignsubmission_cloudflarestream']);

if (empty($configs)) {
    echo html_writer::tag('p', '✗ No configuration found in database', ['class' => 'alert alert-danger']);
} else {
    echo html_writer::tag('p', '✓ Configuration found in database', ['class' => 'alert alert-success']);
    echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Setting Name');
    echo html_writer::tag('th', 'Value');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($configs as $config) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $config->name);
        $value = $config->value;
        if ($config->name === 'apitoken' && !empty($value)) {
            $value = '****** (hidden)';
        }
        echo html_writer::tag('td', $value);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Check for 'enabled' setting specifically
    $enabled_config = $DB->get_record('config_plugins', [
        'plugin' => 'assignsubmission_cloudflarestream',
        'name' => 'enabled'
    ]);
    
    if ($enabled_config) {
        $enabled_value = $enabled_config->value;
        if ($enabled_value == 1) {
            echo html_writer::tag('p', '✓ Plugin is ENABLED (value: ' . $enabled_value . ')', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '✗ Plugin is DISABLED (value: ' . $enabled_value . ')', ['class' => 'alert alert-warning']);
        }
    } else {
        echo html_writer::tag('p', '⚠ "enabled" setting not found in database', ['class' => 'alert alert-warning']);
    }
}

// 4. Check database tables
echo html_writer::tag('h3', '4. Database Tables');
$tables_to_check = [
    'assignsubmission_cfstream',
    'assignsubmission_cfs_log'
];

echo html_writer::start_tag('ul');
foreach ($tables_to_check as $table) {
    $table_exists = $DB->get_manager()->table_exists($table);
    $status = $table_exists ? '✓ EXISTS' : '✗ MISSING';
    $class = $table_exists ? 'text-success' : 'text-danger';
    echo html_writer::tag('li', 
        html_writer::tag('strong', $table . ': ', ['class' => $class]) . $status
    );
    
    if ($table_exists) {
        $count = $DB->count_records($table);
        echo ' (' . $count . ' records)';
    }
}
echo html_writer::end_tag('ul');

// 5. Check if plugin class can be loaded
echo html_writer::tag('h3', '5. Plugin Class Loading');
try {
    require_once(__DIR__ . '/lib.php');
    
    if (class_exists('assign_submission_cloudflarestream')) {
        echo html_writer::tag('p', '✓ Plugin class "assign_submission_cloudflarestream" loaded successfully', ['class' => 'alert alert-success']);
        
        // Try to instantiate the class (requires assignment context)
        echo html_writer::tag('p', 'Class methods available:');
        $methods = get_class_methods('assign_submission_cloudflarestream');
        echo html_writer::start_tag('ul');
        foreach ($methods as $method) {
            echo html_writer::tag('li', $method);
        }
        echo html_writer::end_tag('ul');
    } else {
        echo html_writer::tag('p', '✗ Plugin class "assign_submission_cloudflarestream" NOT found', ['class' => 'alert alert-danger']);
    }
} catch (Exception $e) {
    echo html_writer::tag('p', '✗ Error loading plugin class: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
}

// 6. Check submission plugin manager
echo html_writer::tag('h3', '6. Assignment Submission Plugins');
$submission_plugins = core_plugin_manager::instance()->get_plugins_of_type('assignsubmission');

echo html_writer::tag('p', 'Total submission plugins found: ' . count($submission_plugins));
echo html_writer::start_tag('ul');

$cloudflare_found = false;
foreach ($submission_plugins as $plugin) {
    $is_cloudflare = ($plugin->name === 'cloudflarestream');
    if ($is_cloudflare) {
        $cloudflare_found = true;
        echo html_writer::tag('li', 
            html_writer::tag('strong', $plugin->name . ' (THIS PLUGIN)', ['class' => 'text-primary']) . 
            ' - Enabled: ' . ($plugin->is_enabled() ? 'YES' : 'NO')
        );
    } else {
        echo html_writer::tag('li', $plugin->name . ' - Enabled: ' . ($plugin->is_enabled() ? 'YES' : 'NO'));
    }
}
echo html_writer::end_tag('ul');

if ($cloudflare_found) {
    echo html_writer::tag('p', '✓ Cloudflare Stream plugin found in submission plugins list', ['class' => 'alert alert-success']);
} else {
    echo html_writer::tag('p', '✗ Cloudflare Stream plugin NOT found in submission plugins list', ['class' => 'alert alert-danger']);
}

// 7. Check if plugin appears in a test assignment context
echo html_writer::tag('h3', '7. Plugin Availability Test');
echo html_writer::tag('p', 'Checking if plugin would appear in assignment settings...');

// Get any assignment to test with
$test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);

if ($test_assignment) {
    try {
        $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
        $context = context_module::instance($cm->id);
        $assignment = new assign($context, $cm, $test_assignment->course);
        
        // Get available submission plugins
        $submission_plugins = $assignment->get_submission_plugins();
        
        echo html_writer::tag('p', 'Testing with assignment: ' . $test_assignment->name);
        echo html_writer::tag('p', 'Available submission plugins in this assignment:');
        echo html_writer::start_tag('ul');
        
        $cloudflare_available = false;
        foreach ($submission_plugins as $plugin) {
            $plugin_name = $plugin->get_name();
            $is_enabled = $plugin->is_enabled();
            $is_visible = $plugin->is_visible();
            
            if (get_class($plugin) === 'assign_submission_cloudflarestream') {
                $cloudflare_available = true;
                echo html_writer::tag('li', 
                    html_writer::tag('strong', $plugin_name . ' (THIS PLUGIN)', ['class' => 'text-primary']) . 
                    ' - Enabled: ' . ($is_enabled ? 'YES' : 'NO') . 
                    ', Visible: ' . ($is_visible ? 'YES' : 'NO'),
                    ['class' => 'text-primary']
                );
            } else {
                echo html_writer::tag('li', 
                    $plugin_name . ' - Enabled: ' . ($is_enabled ? 'YES' : 'NO') . 
                    ', Visible: ' . ($is_visible ? 'YES' : 'NO')
                );
            }
        }
        echo html_writer::end_tag('ul');
        
        if ($cloudflare_available) {
            echo html_writer::tag('p', '✓ Plugin IS available in assignment context', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '✗ Plugin is NOT available in assignment context', ['class' => 'alert alert-danger']);
        }
        
    } catch (Exception $e) {
        echo html_writer::tag('p', 'Error testing assignment context: ' . $e->getMessage(), ['class' => 'alert alert-warning']);
    }
} else {
    echo html_writer::tag('p', 'No assignments found in database to test with', ['class' => 'alert alert-info']);
}

// 8. Recommendations
echo html_writer::tag('h3', '8. Troubleshooting Recommendations');
echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::tag('h4', 'If plugin is not appearing:');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Purge all caches: Site administration → Development → Purge all caches');
echo html_writer::tag('li', 'Check that the plugin is enabled: Site administration → Plugins → Activity modules → Assignment → Submission plugins');
echo html_writer::tag('li', 'Verify file permissions: Ensure web server can read all plugin files');
echo html_writer::tag('li', 'Check PHP error logs for any loading errors');
echo html_writer::tag('li', 'Try reinstalling: Uninstall the plugin and reinstall it via Site administration → Notifications');
echo html_writer::end_tag('ol');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
