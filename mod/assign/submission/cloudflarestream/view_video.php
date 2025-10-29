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

echo $OUTPUT->header();

echo html_writer::start_div('cloudflarestream-viewer-container', ['style' => 'max-width: 1200px; margin: 0 auto;']);

// Video info.
echo html_writer::tag('h3', get_string('cloudflarestream', 'assignsubmission_cloudflarestream'));

if ($video->file_size || $video->duration) {
    echo html_writer::start_div('cloudflarestream-metadata mb-3');
    
    if ($video->duration) {
        echo html_writer::tag('span', 
            '<i class="fa fa-clock-o"></i> ' . get_string('duration', 'core') . ': ' . format_time($video->duration), 
            ['class' => 'cloudflarestream-metadata mr-3']
        );
    }
    
    if ($video->file_size) {
        echo html_writer::tag('span', 
            '<i class="fa fa-file"></i> ' . get_string('size', 'core') . ': ' . display_size($video->file_size), 
            ['class' => 'cloudflarestream-metadata']
        );
    }
    
    echo html_writer::end_div();
}

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
