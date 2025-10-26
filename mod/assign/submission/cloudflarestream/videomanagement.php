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
 * Video management page for manually deleting Cloudflare Stream videos.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\api\cloudflare_api_exception;
use assignsubmission_cloudflarestream\logger;

// Require admin login.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get parameters.
$action = optional_param('action', '', PARAM_ALPHA);
$videouid = optional_param('videouid', '', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);

// Page setup.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/mod/assign/submission/cloudflarestream/videomanagement.php', [
    'page' => $page,
    'perpage' => $perpage,
    'courseid' => $courseid,
    'status' => $status,
    'search' => $search
]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('videomanagement', 'assignsubmission_cloudflarestream'));
$PAGE->set_heading(get_string('videomanagement', 'assignsubmission_cloudflarestream'));

// Handle delete action.
if ($action === 'delete' && !empty($videouid)) {
    if ($confirm && confirm_sesskey()) {
        // Perform deletion.
        try {
            // Get API credentials.
            $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
            $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
            
            if (empty($apitoken) || empty($accountid)) {
                throw new moodle_exception('config_missing', 'assignsubmission_cloudflarestream');
            }
            
            // Initialize API client.
            $client = new cloudflare_client($apitoken, $accountid);
            
            // Delete video from Cloudflare.
            $client->delete_video($videouid);
            
            // Update database record.
            $DB->set_field('assignsubmission_cfstream', 'upload_status', 'deleted', ['video_uid' => $videouid]);
            $DB->set_field('assignsubmission_cfstream', 'deleted_timestamp', time(), ['video_uid' => $videouid]);
            
            // Log the deletion.
            logger::log_video_deletion($videouid, $USER->id, 'manual');
            
            // Show success message.
            \core\notification::success(get_string('videodeletesuccess', 'assignsubmission_cloudflarestream'));
            
        } catch (cloudflare_api_exception $e) {
            \core\notification::error(get_string('videodeletefailed', 'assignsubmission_cloudflarestream', $e->getMessage()));
        } catch (Exception $e) {
            \core\notification::error(get_string('videodeletefailed', 'assignsubmission_cloudflarestream', $e->getMessage()));
        }
        
        // Redirect to avoid resubmission.
        redirect($PAGE->url);
    }
}

// Output header.
echo $OUTPUT->header();

// Show description.
echo html_writer::div(
    get_string('videomanagement_desc', 'assignsubmission_cloudflarestream'),
    'alert alert-info'
);

// Handle delete confirmation dialog.
if ($action === 'delete' && !empty($videouid) && !$confirm) {
    // Get video details for confirmation.
    $video = $DB->get_record('assignsubmission_cfstream', ['video_uid' => $videouid]);
    
    if ($video) {
        // Get additional context (assignment, user).
        $sql = "SELECT v.*, a.name as assignmentname, u.firstname, u.lastname, c.fullname as coursename
                FROM {assignsubmission_cfstream} v
                JOIN {assign_submission} s ON s.id = v.submission
                JOIN {assign} a ON a.id = v.assignment
                JOIN {course} c ON c.id = a.course
                JOIN {user} u ON u.id = s.userid
                WHERE v.video_uid = ?";
        
        $videodetails = $DB->get_record_sql($sql, [$videouid]);
        
        if ($videodetails) {
            echo $OUTPUT->confirm(
                get_string('deleteconfirm_desc', 'assignsubmission_cloudflarestream') . '<br><br>' .
                '<strong>' . get_string('course') . ':</strong> ' . s($videodetails->coursename) . '<br>' .
                '<strong>' . get_string('assignment', 'assign') . ':</strong> ' . s($videodetails->assignmentname) . '<br>' .
                '<strong>' . get_string('student', 'assignsubmission_cloudflarestream') . ':</strong> ' . 
                fullname($videodetails) . '<br>' .
                '<strong>' . get_string('videouid', 'assignsubmission_cloudflarestream') . ':</strong> ' . s($videouid) . '<br>' .
                '<strong>' . get_string('uploaddate', 'assignsubmission_cloudflarestream') . ':</strong> ' . 
                userdate($videodetails->upload_timestamp),
                new moodle_url($PAGE->url, ['action' => 'delete', 'videouid' => $videouid, 'confirm' => 1, 'sesskey' => sesskey()]),
                $PAGE->url
            );
            
            echo $OUTPUT->footer();
            exit;
        }
    }
    
    // Video not found.
    \core\notification::error(get_string('videonotfound', 'assignsubmission_cloudflarestream'));
}

// Build filter form.
echo html_writer::start_div('video-filters mb-3');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'form-inline']);

// Course filter.
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname 
    FROM {course} c
    JOIN {assign} a ON a.course = c.id
    JOIN {assignsubmission_cfstream} v ON v.assignment = a.id
    WHERE v.upload_status != 'deleted'
    ORDER BY c.fullname
");

if (!empty($courses)) {
    $courseoptions = [0 => get_string('allcourses', 'moodle')];
    foreach ($courses as $course) {
        $courseoptions[$course->id] = $course->fullname;
    }
    
    echo html_writer::label(get_string('filterbycourse', 'assignsubmission_cloudflarestream'), 'courseid', false, ['class' => 'mr-2']);
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control mr-3']);
}

// Status filter.
$statusoptions = [
    '' => get_string('allstatuses', 'moodle'),
    'ready' => get_string('status_ready', 'assignsubmission_cloudflarestream'),
    'uploading' => get_string('status_uploading', 'assignsubmission_cloudflarestream'),
    'error' => get_string('status_error', 'assignsubmission_cloudflarestream')
];

echo html_writer::label(get_string('filterbystatus', 'assignsubmission_cloudflarestream'), 'status', false, ['class' => 'mr-2']);
echo html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-control mr-3']);

// Search field.
echo html_writer::label(get_string('search'), 'search', false, ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'search',
    'value' => s($search),
    'placeholder' => get_string('searchvideos', 'assignsubmission_cloudflarestream'),
    'class' => 'form-control mr-3'
]);

// Per page selector.
$perpageoptions = [10 => 10, 25 => 25, 50 => 50, 100 => 100];
echo html_writer::label(get_string('videosperpage', 'assignsubmission_cloudflarestream'), 'perpage', false, ['class' => 'mr-2']);
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
    $whereconditions[] = "(a.name LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR v.video_uid LIKE ?)";
    $searchparam = '%' . $search . '%';
    $params[] = $searchparam;
    $params[] = $searchparam;
    $params[] = $searchparam;
    $params[] = $searchparam;
}

$where = implode(' AND ', $whereconditions);

// Count total records.
$countsql = "SELECT COUNT(*)
             FROM {assignsubmission_cfstream} v
             JOIN {assign_submission} s ON s.id = v.submission
             JOIN {assign} a ON a.id = v.assignment
             JOIN {course} c ON c.id = a.course
             JOIN {user} u ON u.id = s.userid
             WHERE $where";

$totalcount = $DB->count_records_sql($countsql, $params);

// Get videos for current page.
$sql = "SELECT v.*, a.name as assignmentname, u.firstname, u.lastname, c.fullname as coursename, c.id as courseid
        FROM {assignsubmission_cfstream} v
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
        get_string('novideostoshow', 'assignsubmission_cloudflarestream'),
        'alert alert-info'
    );
} else {
    // Display table.
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('course'));
    echo html_writer::tag('th', get_string('assignment', 'assign'));
    echo html_writer::tag('th', get_string('student', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('videouid', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('status', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('uploaddate', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('actions', 'assignsubmission_cloudflarestream'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($videos as $video) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($video->coursename));
        echo html_writer::tag('td', s($video->assignmentname));
        echo html_writer::tag('td', fullname($video));
        echo html_writer::tag('td', s($video->video_uid), ['class' => 'font-monospace']);
        
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
            get_string('status_' . $video->upload_status, 'assignsubmission_cloudflarestream'),
            ['class' => $statusclass]
        );
        
        echo html_writer::tag('td', userdate($video->upload_timestamp));
        
        // Actions.
        $actions = '';
        if ($video->upload_status === 'ready' || $video->upload_status === 'error') {
            $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'videouid' => $video->video_uid]);
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