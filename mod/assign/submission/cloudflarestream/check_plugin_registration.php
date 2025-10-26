<?php
/**
 * Check if Moodle knows about the cloudflarestream submission plugin
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/check_plugin_registration.php');
$PAGE->set_title('Plugin Registration Check');
$PAGE->set_heading('Plugin Registration Check');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Cloudflare Stream Plugin Registration Check');

// 1. Check if plugin is in the plugin manager
echo html_writer::tag('h3', '1. Plugin Manager Registration');

$plugin_manager = core_plugin_manager::instance();
$all_plugins = $plugin_manager->get_plugins();

echo html_writer::tag('p', 'Checking if Moodle\'s plugin manager knows about assignsubmission plugins...');

if (isset($all_plugins['assignsubmission'])) {
    echo html_writer::tag('p', '✓ assignsubmission plugin type is registered', ['class' => 'alert alert-success']);
    
    $submission_plugins = $all_plugins['assignsubmission'];
    echo html_writer::tag('p', 'Total assignsubmission plugins found: ' . count($submission_plugins));
    
    echo html_writer::start_tag('ul');
    $cloudflare_found = false;
    foreach ($submission_plugins as $plugin_name => $plugin_info) {
        if ($plugin_name === 'cloudflarestream') {
            $cloudflare_found = true;
            echo html_writer::tag('li', 
                html_writer::tag('strong', $plugin_name . ' (THIS PLUGIN)', ['class' => 'text-primary']) .
                ' - Version: ' . $plugin_info->versiondb .
                ' - Enabled: ' . ($plugin_info->is_enabled() ? 'YES' : 'NO'),
                ['class' => 'text-primary']
            );
        } else {
            echo html_writer::tag('li', $plugin_name . ' - Enabled: ' . ($plugin_info->is_enabled() ? 'YES' : 'NO'));
        }
    }
    echo html_writer::end_tag('ul');
    
    if ($cloudflare_found) {
        echo html_writer::tag('p', '✓ Cloudflare Stream IS registered in plugin manager', ['class' => 'alert alert-success']);
    } else {
        echo html_writer::tag('p', '✗ Cloudflare Stream NOT registered in plugin manager', ['class' => 'alert alert-danger']);
    }
} else {
    echo html_writer::tag('p', '✗ assignsubmission plugin type NOT found!', ['class' => 'alert alert-danger']);
}

// 2. Check plugin directory structure
echo html_writer::tag('h3', '2. Plugin Directory Structure');

$plugin_dir = $CFG->dirroot . '/mod/assign/submission/cloudflarestream';

if (is_dir($plugin_dir)) {
    echo html_writer::tag('p', '✓ Plugin directory exists: ' . $plugin_dir, ['class' => 'alert alert-success']);
    
    $required_files = [
        'version.php' => 'Plugin version information',
        'lib.php' => 'Main plugin class',
        'lang/en/assignsubmission_cloudflarestream.php' => 'Language strings',
        'db/install.xml' => 'Database schema'
    ];
    
    echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'File');
    echo html_writer::tag('th', 'Description');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($required_files as $file => $description) {
        $filepath = $plugin_dir . '/' . $file;
        $exists = file_exists($filepath);
        
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $file);
        echo html_writer::tag('td', $description);
        echo html_writer::tag('td', 
            $exists ? '✓ EXISTS' : '✗ MISSING',
            ['class' => $exists ? 'text-success' : 'text-danger']
        );
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::tag('p', '✗ Plugin directory NOT found: ' . $plugin_dir, ['class' => 'alert alert-danger']);
}

// 3. Check if assign module knows about submission plugins
echo html_writer::tag('h3', '3. Assignment Module Plugin Discovery');

require_once($CFG->dirroot . '/mod/assign/locallib.php');

echo html_writer::tag('p', 'Checking how assign module discovers submission plugins...');

// Get submission plugin directories
$submission_dir = $CFG->dirroot . '/mod/assign/submission';
$subdirs = [];

if (is_dir($submission_dir)) {
    $dir_handle = opendir($submission_dir);
    while (($subdir = readdir($dir_handle)) !== false) {
        if ($subdir !== '.' && $subdir !== '..' && is_dir($submission_dir . '/' . $subdir)) {
            $has_version = file_exists($submission_dir . '/' . $subdir . '/version.php');
            $has_lib = file_exists($submission_dir . '/' . $subdir . '/lib.php');
            
            $subdirs[] = [
                'name' => $subdir,
                'has_version' => $has_version,
                'has_lib' => $has_lib,
                'is_cloudflare' => ($subdir === 'cloudflarestream')
            ];
        }
    }
    closedir($dir_handle);
}

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Plugin Directory');
echo html_writer::tag('th', 'version.php');
echo html_writer::tag('th', 'lib.php');
echo html_writer::tag('th', 'Valid Plugin');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($subdirs as $dir) {
    $is_valid = $dir['has_version'] && $dir['has_lib'];
    $row_class = $dir['is_cloudflare'] ? 'table-primary' : '';
    
    echo html_writer::start_tag('tr', ['class' => $row_class]);
    echo html_writer::tag('td', 
        $dir['is_cloudflare'] ? html_writer::tag('strong', $dir['name'] . ' (THIS PLUGIN)') : $dir['name']
    );
    echo html_writer::tag('td', $dir['has_version'] ? '✓' : '✗');
    echo html_writer::tag('td', $dir['has_lib'] ? '✓' : '✗');
    echo html_writer::tag('td', 
        $is_valid ? '✓ VALID' : '✗ INVALID',
        ['class' => $is_valid ? 'text-success' : 'text-danger']
    );
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// 4. Check database plugin info
echo html_writer::tag('h3', '4. Database Plugin Information');

$plugin_record = $DB->get_record('config_plugins', [
    'plugin' => 'assignsubmission_cloudflarestream',
    'name' => 'version'
]);

if ($plugin_record) {
    echo html_writer::tag('p', '✓ Plugin version found in database: ' . $plugin_record->value, ['class' => 'alert alert-success']);
} else {
    echo html_writer::tag('p', '✗ Plugin version NOT found in database', ['class' => 'alert alert-danger']);
    echo html_writer::tag('p', 'This means Moodle has not installed the plugin properly.');
}

// 5. Check if we need to reinstall
echo html_writer::tag('h3', '5. Installation Status');

$version_file = $plugin_dir . '/version.php';
if (file_exists($version_file)) {
    include($version_file);
    $file_version = isset($plugin->version) ? $plugin->version : 'unknown';
    
    echo html_writer::tag('p', 'Version in version.php file: ' . $file_version);
    
    if ($plugin_record) {
        $db_version = $plugin_record->value;
        echo html_writer::tag('p', 'Version in database: ' . $db_version);
        
        if ($file_version == $db_version) {
            echo html_writer::tag('p', '✓ Versions match - plugin is properly installed', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '⚠ Version mismatch - may need upgrade', ['class' => 'alert alert-warning']);
        }
    } else {
        echo html_writer::tag('p', '✗ Plugin not installed in database', ['class' => 'alert alert-danger']);
    }
}

// 6. Recommendations
echo html_writer::tag('h3', '6. Diagnosis and Recommendations');

echo html_writer::start_tag('div', ['class' => 'alert alert-info']);

if (!$plugin_record) {
    echo html_writer::tag('h4', '⚠ PROBLEM FOUND: Plugin Not Properly Installed');
    echo html_writer::tag('p', 'The plugin files exist but Moodle hasn\'t registered it in the database.');
    echo html_writer::tag('h5', 'Solution:');
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', 'Go to: Site administration → Notifications');
    echo html_writer::tag('li', 'Moodle should detect the new plugin and show an upgrade screen');
    echo html_writer::tag('li', 'Click "Upgrade Moodle database now"');
    echo html_writer::tag('li', 'This will properly install the plugin');
    echo html_writer::end_tag('ol');
    
    echo html_writer::tag('p', html_writer::tag('strong', 'Alternative: Run CLI upgrade'));
    echo html_writer::tag('pre', 'php admin/cli/upgrade.php');
} else if (!isset($cloudflare_found) || !$cloudflare_found) {
    echo html_writer::tag('h4', '⚠ PROBLEM FOUND: Plugin Not in Plugin Manager');
    echo html_writer::tag('p', 'The plugin is in the database but not loaded by the plugin manager.');
    echo html_writer::tag('h5', 'Solution:');
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', 'Purge all caches: Site administration → Development → Purge all caches');
    echo html_writer::tag('li', 'Check file permissions - web server must be able to read all plugin files');
    echo html_writer::tag('li', 'Verify the plugin directory name is exactly "cloudflarestream" (lowercase, no spaces)');
    echo html_writer::end_tag('ol');
} else {
    echo html_writer::tag('h4', '✓ Plugin Appears to be Properly Registered');
    echo html_writer::tag('p', 'The plugin is registered in Moodle. If it\'s still not appearing in assignments, the issue is with assignment-level configuration.');
}

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
