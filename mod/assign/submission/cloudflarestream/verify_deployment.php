<?php
/**
 * Verify that all cloudflarestream plugin fixes are deployed correctly.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/cloudflarestream/verify_deployment.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Verify Cloudflarestream Deployment');
$PAGE->set_heading('Verify Cloudflarestream Plugin Deployment');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Cloudflarestream Plugin Deployment Verification');

$checks = [];

// Check 1: lib.php has is_grading_context method
$libfile = __DIR__ . '/lib.php';
$libcontent = file_get_contents($libfile);
$checks['lib_grading_context'] = [
    'name' => 'lib.php - is_grading_context() method',
    'pass' => strpos($libcontent, 'protected function is_grading_context()') !== false,
    'file' => 'lib.php'
];

// Check 2: locallib.php has plugin_detector class
$locallibfile = __DIR__ . '/locallib.php';
$locallibcontent = file_get_contents($locallibfile);
$checks['locallib_detector'] = [
    'name' => 'locallib.php - plugin_detector class',
    'pass' => strpos($locallibcontent, 'class assignsubmission_cloudflarestream_plugin_detector') !== false,
    'file' => 'locallib.php'
];

// Check 3: get_upload_url.php has correct permission check
$ajaxfile = __DIR__ . '/ajax/get_upload_url.php';
$ajaxcontent = file_get_contents($ajaxfile);
$checks['ajax_permission'] = [
    'name' => 'ajax/get_upload_url.php - Permission check fixed',
    'pass' => strpos($ajaxcontent, "require_capability('mod/assign:submit'") !== false,
    'file' => 'ajax/get_upload_url.php'
];

// Check 4: upload_form.mustache has container selector
$templatefile = __DIR__ . '/templates/upload_form.mustache';
$templatecontent = file_get_contents($templatefile);
$checks['template_container'] = [
    'name' => 'templates/upload_form.mustache - Container selector parameter',
    'pass' => strpos($templatecontent, "'.cloudflarestream-upload-interface'") !== false,
    'file' => 'templates/upload_form.mustache'
];

// Check 5: uploader.js source has debug console.log
$uploaderfile = __DIR__ . '/amd/src/uploader.js';
$uploadercontent = file_get_contents($uploaderfile);
$checks['uploader_debug'] = [
    'name' => 'amd/src/uploader.js - Debug console.log added',
    'pass' => strpos($uploadercontent, 'console.log(\'Confirming upload with uid:\'') !== false,
    'file' => 'amd/src/uploader.js'
];

// Check 6: uploader.js has AMD fix for tus
$checks['uploader_amd_fix'] = [
    'name' => 'amd/src/uploader.js - AMD conflict fix for tus library',
    'pass' => strpos($uploadercontent, 'window.define = undefined') !== false,
    'file' => 'amd/src/uploader.js'
];

// Check 7: uploader.js uses $.ajax instead of Ajax.call
$checks['uploader_ajax'] = [
    'name' => 'amd/src/uploader.js - Uses $.ajax() for API calls',
    'pass' => strpos($uploadercontent, '$.ajax({') !== false && strpos($uploadercontent, 'url: M.cfg.wwwroot') !== false,
    'file' => 'amd/src/uploader.js'
];

// Check 8: uploader.min.js build file has debug console.log
$uploaderminfile = __DIR__ . '/amd/build/uploader.min.js';
if (file_exists($uploaderminfile)) {
    $uploadermincontent = file_get_contents($uploaderminfile);
    $checks['uploader_min_debug'] = [
        'name' => 'amd/build/uploader.min.js - Debug console.log present',
        'pass' => strpos($uploadermincontent, 'Confirming upload with uid') !== false,
        'file' => 'amd/build/uploader.min.js'
    ];
} else {
    $checks['uploader_min_debug'] = [
        'name' => 'amd/build/uploader.min.js - File exists',
        'pass' => false,
        'file' => 'amd/build/uploader.min.js',
        'error' => 'File not found!'
    ];
}

// Display results
echo html_writer::start_div('alert alert-info');
echo html_writer::tag('p', 'Checking ' . count($checks) . ' deployment requirements...');
echo html_writer::end_div();

$allpass = true;
foreach ($checks as $key => $check) {
    $class = $check['pass'] ? 'alert-success' : 'alert-danger';
    $icon = $check['pass'] ? '✅' : '❌';
    
    echo html_writer::start_div('alert ' . $class);
    echo html_writer::tag('strong', $icon . ' ' . $check['name']);
    echo html_writer::tag('p', 'File: ' . $check['file'], ['class' => 'small']);
    
    if (!$check['pass']) {
        $allpass = false;
        if (isset($check['error'])) {
            echo html_writer::tag('p', 'Error: ' . $check['error'], ['class' => 'text-danger']);
        }
    }
    
    echo html_writer::end_div();
}

// Overall status
if ($allpass) {
    echo html_writer::start_div('alert alert-success');
    echo html_writer::tag('h3', '✅ ALL CHECKS PASSED!');
    echo html_writer::tag('p', 'All fixes are correctly deployed. You can now test uploading.');
    echo html_writer::tag('p', '<strong>Next steps:</strong>');
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', 'Clear Moodle cache: php admin/cli/purge_caches.php');
    echo html_writer::tag('li', 'Restart Apache: sudo systemctl restart apache2');
    echo html_writer::tag('li', 'Clear browser cache (Ctrl+Shift+Delete) or use Incognito mode');
    echo html_writer::tag('li', 'Try uploading a video');
    echo html_writer::tag('li', 'Check browser console (F12) for debug message: "Confirming upload with uid:..."');
    echo html_writer::end_tag('ol');
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('alert alert-danger');
    echo html_writer::tag('h3', '❌ SOME CHECKS FAILED');
    echo html_writer::tag('p', 'Please copy the missing/incorrect files to your server and run this check again.');
    echo html_writer::end_div();
}

// File modification times
echo html_writer::tag('h3', 'File Modification Times');
echo html_writer::tag('p', 'Check if files were recently updated:', ['class' => 'text-muted']);

$files = [
    'lib.php',
    'locallib.php',
    'ajax/get_upload_url.php',
    'templates/upload_form.mustache',
    'amd/src/uploader.js',
    'amd/build/uploader.min.js'
];

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'File');
echo html_writer::tag('th', 'Last Modified');
echo html_writer::tag('th', 'Size');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($files as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        $mtime = filemtime($filepath);
        $size = filesize($filepath);
        $timeago = format_time(time() - $mtime);
        
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $file);
        echo html_writer::tag('td', userdate($mtime) . ' (' . $timeago . ' ago)');
        echo html_writer::tag('td', display_size($size));
        echo html_writer::end_tag('tr');
    } else {
        echo html_writer::start_tag('tr', ['class' => 'table-danger']);
        echo html_writer::tag('td', $file);
        echo html_writer::tag('td', 'FILE NOT FOUND', ['colspan' => 2, 'class' => 'text-danger']);
        echo html_writer::end_tag('tr');
    }
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo $OUTPUT->footer();
