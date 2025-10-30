<?php
/**
 * Manual cleanup tool for orphaned Cloudflare videos.
 * Access via browser: https://yoursite.com/mod/assign/submission/cloudflarestream/manual_cleanup.php
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;

// Require admin login.
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/cloudflarestream/manual_cleanup.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Cloudflare Video Cleanup');
$PAGE->set_heading('Cloudflare Video Cleanup Tool');

// Get parameters.
$videouid = optional_param('videouid', '', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Cloudflare Video Cleanup Tool');
echo html_writer::tag('p', 'This tool helps you clean up orphaned videos from Cloudflare Stream.');

// Get plugin configuration.
$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

if (empty($apitoken) || empty($accountid)) {
    echo $OUTPUT->notification('Plugin not configured. Please set API token and Account ID in plugin settings.', 'error');
    echo $OUTPUT->footer();
    exit;
}

// Handle delete action.
if ($action === 'delete' && !empty($videouid) && $confirm && confirm_sesskey()) {
    try {
        $client = new cloudflare_client($apitoken, $accountid);
        
        // Delete from Cloudflare.
        $client->delete_video($videouid);
        echo $OUTPUT->notification("✓ Video {$videouid} deleted from Cloudflare", 'success');
        
        // Delete from database.
        $deleted = $DB->delete_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
        if ($deleted) {
            echo $OUTPUT->notification("✓ Database record deleted", 'success');
        }
        
        $videouid = ''; // Clear for next search
        
    } catch (Exception $e) {
        echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'error');
    }
}

// Show search form.
echo html_writer::start_tag('div', ['class' => 'card mb-3']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h4', 'Search for Video', ['class' => 'card-title']);

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::start_tag('div', ['class' => 'form-group']);
echo html_writer::tag('label', 'Video UID:', ['for' => 'videouid']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'videouid',
    'id' => 'videouid',
    'class' => 'form-control',
    'value' => $videouid,
    'placeholder' => 'e.g., e3bcdbc2b7d8cc345aeff504562e5817',
    'required' => 'required'
]);
echo html_writer::end_tag('div');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Search', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Show video details if searched.
if (!empty($videouid) && $action !== 'delete') {
    try {
        $client = new cloudflare_client($apitoken, $accountid);
        
        echo html_writer::start_tag('div', ['class' => 'card mb-3']);
        echo html_writer::start_tag('div', ['class' => 'card-body']);
        echo html_writer::tag('h4', 'Video Details', ['class' => 'card-title']);
        
        // Get video from Cloudflare.
        try {
            $video = $client->get_video_details($videouid);
            
            echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
            echo html_writer::start_tag('tbody');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'UID');
            echo html_writer::tag('td', $video->uid);
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Status');
            echo html_writer::tag('td', $video->status->state ?? 'Unknown');
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Duration');
            echo html_writer::tag('td', ($video->duration ?? 'N/A') . ' seconds');
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Size');
            echo html_writer::tag('td', isset($video->size) ? display_size($video->size) : 'N/A');
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Created');
            echo html_writer::tag('td', $video->created ?? 'N/A');
            echo html_writer::end_tag('tr');
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            echo $OUTPUT->notification('✓ Video found in Cloudflare', 'success');
            
        } catch (Exception $e) {
            echo $OUTPUT->notification('Video NOT found in Cloudflare: ' . $e->getMessage(), 'warning');
        }
        
        // Check database.
        $dbrecord = $DB->get_record('assignsubmission_cfstream', ['video_uid' => $videouid]);
        
        if ($dbrecord) {
            echo html_writer::tag('h5', 'Database Record', ['class' => 'mt-3']);
            echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
            echo html_writer::start_tag('tbody');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'ID');
            echo html_writer::tag('td', $dbrecord->id);
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Assignment');
            echo html_writer::tag('td', $dbrecord->assignment);
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Submission');
            echo html_writer::tag('td', $dbrecord->submission);
            echo html_writer::end_tag('tr');
            
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Status');
            echo html_writer::tag('td', $dbrecord->upload_status);
            echo html_writer::end_tag('tr');
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            echo $OUTPUT->notification('✓ Video found in database', 'success');
        } else {
            echo $OUTPUT->notification('⚠ Video NOT found in database (orphaned video)', 'warning');
        }
        
        // Show delete button.
        echo html_writer::tag('h5', 'Delete Video', ['class' => 'mt-4']);
        echo html_writer::tag('p', 'This will permanently delete the video from Cloudflare and the database.', ['class' => 'text-danger']);
        
        $deleteurl = new moodle_url($PAGE->url, [
            'videouid' => $videouid,
            'action' => 'delete',
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::link(
            $deleteurl,
            'Delete Video',
            [
                'class' => 'btn btn-danger',
                'onclick' => 'return confirm("Are you sure you want to delete this video? This cannot be undone!");'
            ]
        );
        
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
        
    } catch (Exception $e) {
        echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'error');
    }
}

// Show list of orphaned videos.
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h4', 'Orphaned Database Records', ['class' => 'card-title']);

$orphaned = $DB->get_records_sql(
    "SELECT * FROM {assignsubmission_cfstream} 
     WHERE (upload_status = 'pending' OR upload_status = 'uploading')
     AND (video_uid = '' OR video_uid IS NULL OR upload_timestamp < ?)",
    [time() - 86400] // Older than 24 hours
);

if (empty($orphaned)) {
    echo html_writer::tag('p', '✓ No orphaned records found', ['class' => 'text-success']);
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Assignment');
    echo html_writer::tag('th', 'Submission');
    echo html_writer::tag('th', 'Video UID');
    echo html_writer::tag('th', 'Status');
    echo html_writer::tag('th', 'Timestamp');
    echo html_writer::tag('th', 'Action');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($orphaned as $record) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $record->id);
        echo html_writer::tag('td', $record->assignment);
        echo html_writer::tag('td', $record->submission);
        echo html_writer::tag('td', $record->video_uid ?: '(empty)');
        echo html_writer::tag('td', $record->upload_status);
        echo html_writer::tag('td', userdate($record->upload_timestamp));
        
        if (!empty($record->video_uid)) {
            $searchurl = new moodle_url($PAGE->url, ['videouid' => $record->video_uid]);
            echo html_writer::tag('td', html_writer::link($searchurl, 'View', ['class' => 'btn btn-sm btn-primary']));
        } else {
            echo html_writer::tag('td', 'No UID');
        }
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
