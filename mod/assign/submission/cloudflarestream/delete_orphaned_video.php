<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manual script to delete orphaned videos from Cloudflare.
 * Orphaned videos are videos that exist in Cloudflare but have no database record.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$videouid = optional_param('videouid', '', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url('/mod/assign/submission/cloudflarestream/delete_orphaned_video.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Delete Orphaned Cloudflare Video');
$PAGE->set_heading('Delete Orphaned Cloudflare Video');

echo $OUTPUT->header();
echo $OUTPUT->heading('Delete Orphaned Cloudflare Video');

if (empty($videouid)) {
    echo '<div class="alert alert-info">';
    echo '<p>This tool deletes videos from Cloudflare that do NOT exist in the Moodle database (orphaned videos).</p>';
    echo '<p>Specify video UID in URL: <code>?videouid=YOUR_VIDEO_UID</code></p>';
    echo '<p>Example: <code>?videouid=e6984cff27aa78f918e17723e7cbaf60</code></p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Check if video exists in database
global $DB;
$dbrecord = $DB->get_record('assignsubmission_cfstream', ['video_uid' => $videouid]);

if ($dbrecord) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Warning:</strong> This video EXISTS in the database! Use normal cleanup instead.';
    echo '</div>';
    echo '<p><strong>Database record ID:</strong> ' . $dbrecord->id . '</p>';
    echo '<p><strong>Status:</strong> ' . $dbrecord->upload_status . '</p>';
    echo '<p><strong>Upload time:</strong> ' . userdate($dbrecord->upload_timestamp) . '</p>';
    echo '<p><a href="' . $CFG->wwwroot . '/mod/assign/submission/cloudflarestream/manual_cleanup.php" class="btn btn-primary">Go to Manual Cleanup</a></p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<div class="alert alert-info">';
echo '<p><strong>Video UID:</strong> <code>' . s($videouid) . '</code></p>';
echo '<p>This video does NOT exist in the database (orphaned video).</p>';
echo '</div>';

if (!$confirm) {
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="videouid" value="' . s($videouid) . '">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" class="btn btn-danger">Confirm: Delete from Cloudflare</button> ';
    echo '<a href="' . $CFG->wwwroot . '/mod/assign/submission/cloudflarestream/dashboard.php" class="btn btn-secondary">Cancel</a>';
    echo '</form>';
    echo $OUTPUT->footer();
    exit;
}

// Delete from Cloudflare
try {
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (empty($apitoken) || empty($accountid)) {
        throw new Exception('API token or account ID not configured');
    }
    
    $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
    $client->delete_video($videouid);
    
    echo '<div class="alert alert-success">';
    echo '<strong>✓ Success!</strong> Video deleted from Cloudflare.';
    echo '</div>';
    echo '<p>Video UID: <code>' . s($videouid) . '</code></p>';
    
    error_log("Manual deletion: Orphaned video {$videouid} deleted from Cloudflare by user " . $USER->id);
    
} catch (\assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception $e) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Notice:</strong> Video not found in Cloudflare (already deleted or never existed).';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<strong>✗ Error:</strong> ' . s($e->getMessage());
    echo '</div>';
    error_log("Manual deletion error for {$videouid}: " . $e->getMessage());
}

echo '<p><a href="' . $CFG->wwwroot . '/mod/assign/submission/cloudflarestream/dashboard.php" class="btn btn-primary">Back to Dashboard</a></p>';

echo $OUTPUT->footer();
