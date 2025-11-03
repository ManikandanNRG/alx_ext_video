<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * View video submission in a new window.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/locallib.php');

// Get parameters.
$submissionid = required_param('id', PARAM_INT);
$videouid = required_param('video_uid', PARAM_TEXT);

// Require login.
require_login();

// Get submission and verify access.
$submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Verify access.
$accesscheck = assignsubmission_cloudflarestream_verify_video_access($submissionid, $videouid, $USER->id);
if (!$accesscheck['allowed']) {
    throw new moodle_exception('nopermissions', 'error', '', $accesscheck['reason']);
}

// Get video record.
$video = $DB->get_record('assignsubmission_cfstream', 
    ['submission' => $submissionid], '*', MUST_EXIST);

// Set up page.
$PAGE->set_url('/mod/assign/submission/cloudflarestream/view_video.php', ['id' => $submissionid, 'video_uid' => $videouid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('watchvideo', 'assignsubmission_cloudflarestream'));
$PAGE->set_heading($assignment->name);
$PAGE->set_pagelayout('embedded');

// Load player JavaScript.
$PAGE->requires->js_call_amd('assignsubmission_cloudflarestream/player', 'init', [
    $video->video_uid,
    $submission->id,
    'cloudflarestream-viewer-player'
]);

// Get video filename from Cloudflare metadata
$filename = 'Video';
try {
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (!empty($apitoken) && !empty($accountid)) {
        require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
        $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
        $details = $client->get_video_details($video->video_uid);
        
        if (isset($details->meta->name) && !empty($details->meta->name)) {
            $filename = $details->meta->name;
        }
    }
} catch (Exception $e) {
    // Use fallback
}

// Get student info
$student = $DB->get_record('user', ['id' => $submission->userid], 'id, firstname, lastname', MUST_EXIST);
$studentname = fullname($student);

echo $OUTPUT->header();

echo html_writer::start_div('cloudflarestream-viewer-container', ['style' => 'max-width: 1200px; margin: 0 auto; padding: 20px;']);

// Video title with icon
echo html_writer::start_div('mb-4');
echo html_writer::tag('h2', 
    '<i class="fa fa-video-camera text-primary"></i> ' . s($filename),
    ['class' => 'mb-3', 'style' => 'color: #333; font-weight: 500;']
);
echo html_writer::end_div();

// Video information card
echo html_writer::start_div('cloudflarestream-info-card mb-4', [
    'style' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'
]);

echo html_writer::tag('h4', 
    '<i class="fa fa-info-circle"></i> ' . get_string('videoinformation', 'assignsubmission_cloudflarestream'),
    ['class' => 'mb-3', 'style' => 'color: white; font-weight: 500;']
);

echo html_writer::start_div('row');

// Left column
echo html_writer::start_div('col-md-6');
echo html_writer::tag('div', 
    '<i class="fa fa-user"></i> <strong>' . get_string('uploadedby', 'assignsubmission_cloudflarestream') . ':</strong> ' . s($studentname),
    ['class' => 'mb-2', 'style' => 'font-size: 14px;']
);
echo html_writer::tag('div', 
    '<i class="fa fa-calendar"></i> <strong>' . get_string('uploaddate', 'assignsubmission_cloudflarestream') . ':</strong> ' . userdate($video->upload_timestamp, get_string('strftimedatetime', 'core_langconfig')),
    ['class' => 'mb-2', 'style' => 'font-size: 14px;']
);
echo html_writer::tag('div', 
    '<i class="fa fa-book"></i> <strong>' . get_string('assignment', 'core') . ':</strong> ' . s($assignment->name),
    ['class' => 'mb-2', 'style' => 'font-size: 14px;']
);
echo html_writer::end_div();

// Right column
echo html_writer::start_div('col-md-6');
if ($video->duration) {
    echo html_writer::tag('div', 
        '<i class="fa fa-clock-o"></i> <strong>' . get_string('duration', 'core') . ':</strong> ' . format_time($video->duration),
        ['class' => 'mb-2', 'style' => 'font-size: 14px;']
    );
}
if ($video->file_size) {
    echo html_writer::tag('div', 
        '<i class="fa fa-hdd-o"></i> <strong>' . get_string('size', 'core') . ':</strong> ' . display_size($video->file_size),
        ['class' => 'mb-2', 'style' => 'font-size: 14px;']
    );
}
echo html_writer::tag('div', 
    '<i class="fa fa-check-circle"></i> <strong>' . get_string('status', 'core') . ':</strong> ' . get_string('status_ready', 'assignsubmission_cloudflarestream'),
    ['class' => 'mb-2', 'style' => 'font-size: 14px;']
);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_div(); // info-card

// Video player container.
echo html_writer::start_div('cloudflarestream-player-container', [
    'id' => 'cloudflarestream-viewer-player',
    'style' => 'width: 100%; min-height: 500px; background-color: #000; border-radius: 4px;'
]);

echo html_writer::start_div('cloudflarestream-loading text-center', ['style' => 'padding: 100px 20px; color: #fff;']);
echo html_writer::start_div('spinner-border text-light', ['role' => 'status', 'style' => 'width: 3rem; height: 3rem;']);
echo html_writer::tag('span', 'Loading...', ['class' => 'sr-only']);
echo html_writer::end_div();
echo html_writer::tag('p', 'Loading video player...', ['style' => 'margin-top: 1rem;']);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
