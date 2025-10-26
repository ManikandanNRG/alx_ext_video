<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video management page for manually deleting S3 videos.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\cloudfront_client;
use assignsubmission_s3video\logger;

// Require admin login.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get parameters.
$action = optional_param('action', '', PARAM_ALPHA);
$s3key = optional_param('s3key', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);

// Page setup.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/mod/assign/submission/s3video/videomanagement.php', [
    'page' => $page,
    'perpage' => $perpage,
    'courseid' => $courseid,
    'status' => $status,
    'search' => $search
]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('videomanagement', 'assignsubmission_s3video'));
$PAGE->set_heading(get_string('videomanagement', 'assignsubmission_s3video'));

// Handle delete action.
if ($action === 'delete' && !empty($s3key)) {
    if ($confirm && confirm_sesskey()) {
        // Perform deletion.
        try {
            // Get API credentials.
            $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
            $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
            $bucket = get_config('assignsubmission_s3video', 's3_bucket');
            $region = get_config('assignsubmission_s3video', 's3_region');
            $cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
            $keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
            $privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');
            
            if (empty($accesskey) || empty($secretkey) || empty($bucket) || empty($region)) {
                throw new moodle_exception('config_missing', 'assignsubmission_s3video');
            }
            
            // Initialize S3 client.
            $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
            
            // Delete video from S3.
            $s3client->delete_object($s3key);
            
            // Initialize CloudFront client and invalidate cache if configured.
            if (!empty($cloudfrontdomain) && !empty($keypairid) && !empty($privatekey)) {
                $cfclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);
                $cfclient->create_invalidation($s3key);
            }
            
            // Update database record.
            $DB->set_field('assignsubmission_s3video', 'upload_status', 'deleted', ['s3_key' => $s3key]);
            $DB->set_field('assignsubmission_s3video', 'deleted_timestamp', time(), ['s3_key' => $s3key]);
            
            // Log the deletion.
            $video = $DB->get_record('assignsubmission_s3video', ['s3_key' => $s3key]);
            if ($video) {
                logger::log_event('video_deleted', $USER->id, $video->assignment, 
                    $video->submission, $s3key, null, null, 'manual');
            }
            
            // Show success message.
            \core\notification::success(get_string('videodeletesuccess', 'assignsubmission_s3video'));
            
        } catch (Exception $e) {
            \core\notification::error(get_string('videodeletefailed', 'assignsubmission_s3video', $e->getMessage()));
        }
        
        // Redirect to avoid resubmission.
        redirect($PAGE->url);
    }
}

// Output header.
echo $OUTPUT->header();

// Show description.
echo html_writer::div(
    get_string('videomanagement_desc', 'assignsubmission_s3video'),
    'alert alert-info'
);

// Handle delete confirmation dialog.
if ($action === 'delete' && !empty($s3key) && !$confirm) {
    // Get video details for confirmation.
    $video = $DB->get_record('assignsubmission_s3video', ['s3_key' => $s3key]);
    
    if ($video) {
        // Get additional context (assignment, user).
        $sql = "SELECT v.*, a.name as assignmentname, u.firstname, u.lastname, c.fullname as coursename
                FROM {assignsubmission_s3video} v
                JOIN {assign_submission} s ON s.id = v.submission
                JOIN {assign} a ON a.id = v.assignment
                JOIN {course} c ON c.id = a.course
                JOIN {user} u ON u.id = s.userid
                WHERE v.s3_key = ?";
        
        $videodetails = $DB->get_record_sql($sql, [$s3key]);
        
        if ($videodetails) {
            echo $OUTPUT->confirm(
                get_string('deleteconfirm_desc', 'assignsubmission_s3video') . '<br><br>' .
                '<strong>' . get_string('course') . ':</strong> ' . s($videodetails->coursename) . '<br>' .
                '<strong>' . get_string('assignment', 'assign') . ':</strong> ' . s($videodetails->assignmentname) . '<br>' .
                '<strong>' . get_string('student', 'assignsubmission_s3video') . ':</strong> ' . 
                fullname($videodetails) . '<br>' .
                '<strong>' . get_string('videouid', 'assignsubmission_s3video') . ':</strong> ' . s($s3key) . '<br>' .
                '<strong>' . get_string('uploaddate', 'assignsubmission_s3video') . ':</strong> ' . 
                userdate($videodetails->upload_timestamp),
                new moodle_url($PAGE->url, ['action' => 'delete', 's3key' => $s3key, 'confirm' => 1, 'sesskey' => sesskey()]),
                $PAGE->url
            );
            
            echo $OUTPUT->footer();
            exit;
        }
    }
    
    // Video not found.
    \core\notification::error(get_string('videonotfound', 'assignsubmission_s3video'));
}

// Build filter form.
echo html_writer::start_div('video-filters mb-3');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'form-inline']);

// Course filter.
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname 
    FROM {course} c
    JOIN {assign} a ON a.course = c.id
    JOIN {assignsubmission_s3video} v ON v.assignment = a.id
    WHERE v.upload_status != 'deleted'
    ORDER BY c.fullname
");

if (!empty($courses)) {
    $courseoptions = [0 => get_string('allcourses', 'moodle')];
    foreach ($courses as $course) {
        $courseoptions[$course->id] = $course->fullname;
    }
    
    echo html_writer::label(get_string('filterbycourse', 'assignsubmission_s3video'), 'courseid', false, ['class' => 'mr-2']);
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control mr-3']);
}

// Status filter.
$statusoptions = [
    '' => get_string('allstatuses', 'assignsubmission_s3video'),
    'ready' => get_string('status_ready', 'assignsubmission_s3video'),
    'uploading' => get_string('status_uploading', 'assignsubmission_s3video'),
    'error' => get_string('status_error', 'assignsubmission_s3video')
];

echo html_writer::label(get_string('filterbystatus', 'assignsubmission_s3video'), 'status', false, ['class' => 'mr-2']);
echo html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-control mr-3']);

// Search field.
echo html_writer::label(get_string('search'), 'search', false, ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'search',
    'value' => s($search),
    'placeholder' => get_string('searchvideos', 'assignsubmission_s3video'),
    'class' => 'form-control mr-3'
]);

// Per page selector.
$perpageoptions = [10 => 10, 25 => 25, 50 => 50, 100 => 100];
echo html_writer::label(get_string('videosperpage', 'assignsubmission_s3video'), 'perpage', false, ['class' => 'mr-2']);
echo html_writer::select($perpageoptions, 'perpage', $perpage, false, ['class' => 'form-control mr-3']);

// Submit buttons.
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-primary mr-2']);
echo html_writer::link($PAGE->url->out_omit_querystring(), get_string('reset'), ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');
echo html_writer::end_div();

// Build query for videos.
$params = [];
$whereconditions = ["v.upload_status != 'deleted'"];

if ($courseid > 0) {
    $whereconditions[] = "a.course = ?";
    $params[] = $courseid;
}

if (!empty($status)) {
    $whereconditions[] = "v.upload_status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $whereconditions[] = "(a.name LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR v.s3_key LIKE ?)";
    $searchparam = '%' . $search . '%';
    $params[] = $searchparam;
    $params[] = $searchparam;
    $params[] = $searchparam;
    $params[] = $searchparam;
}

$where = implode(' AND ', $whereconditions);

// Count total records.
$countsql = "SELECT COUNT(*)
             FROM {assignsubmission_s3video} v
             JOIN {assign_submission} s ON s.id = v.submission
             JOIN {assign} a ON a.id = v.assignment
             JOIN {course} c ON c.id = a.course
             JOIN {user} u ON u.id = s.userid
             WHERE $where";

$totalcount = $DB->count_records_sql($countsql, $params);

// Get videos for current page.
$sql = "SELECT v.*, a.name as assignmentname, u.firstname, u.lastname, c.fullname as coursename, c.id as courseid
        FROM {assignsubmission_s3video} v
        JOIN {assign_submission} s ON s.id = v.submission
        JOIN {assign} a ON a.id = v.assignment
        JOIN {course} c ON c.id = a.course
        JOIN {user} u ON u.id = s.userid
        WHERE $where
        ORDER BY v.upload_timestamp DESC";

$videos = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Display results.
if (empty($videos)) {
    echo html_writer::div(
        get_string('novideostoshow', 'assignsubmission_s3video'),
        'alert alert-info'
    );
} else {
    // Display table.
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('course'));
    echo html_writer::tag('th', get_string('assignment', 'assign'));
    echo html_writer::tag('th', get_string('student', 'assignsubmission_s3video'));
    echo html_writer::tag('th', get_string('videouid', 'assignsubmission_s3video'));
    echo html_writer::tag('th', get_string('status', 'assignsubmission_s3video'));
    echo html_writer::tag('th', get_string('uploaddate', 'assignsubmission_s3video'));
    echo html_writer::tag('th', get_string('actions', 'assignsubmission_s3video'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($videos as $video) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($video->coursename));
        echo html_writer::tag('td', s($video->assignmentname));
        echo html_writer::tag('td', fullname($video));
        echo html_writer::tag('td', s($video->s3_key), ['class' => 'font-monospace']);
        
        // Status with color coding.
        $statusclass = '';
        switch ($video->upload_status) {
            case 'ready':
                $statusclass = 'text-success';
                break;
            case 'error':
                $statusclass = 'text-danger';
                break;
            case 'uploading':
                $statusclass = 'text-warning';
                break;
        }
        echo html_writer::tag('td', 
            get_string('status_' . $video->upload_status, 'assignsubmission_s3video'),
            ['class' => $statusclass]
        );
        
        echo html_writer::tag('td', userdate($video->upload_timestamp));
        
        // Actions.
        $actions = '';
        if ($video->upload_status === 'ready' || $video->upload_status === 'error') {
            $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 's3key' => $video->s3_key]);
            $actions = html_writer::link(
                $deleteurl,
                get_string('delete'),
                ['class' => 'btn btn-sm btn-danger']
            );
        }
        echo html_writer::tag('td', $actions);
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Pagination.
    if ($totalcount > $perpage) {
        $baseurl = new moodle_url($PAGE->url, [
            'perpage' => $perpage,
            'courseid' => $courseid,
            'status' => $status,
            'search' => $search
        ]);
        
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);
    }
}

// Output footer.
echo $OUTPUT->footer();
