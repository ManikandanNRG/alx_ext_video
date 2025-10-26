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
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\cloudfront_client;

// Get parameters.
$submissionid = required_param('id', PARAM_INT);
$s3key = required_param('s3key', PARAM_TEXT);

// Require login.
require_login();

// Get submission and verify access.
$submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Verify access.
$accesscheck = assignsubmission_s3video_verify_video_access($submissionid, $s3key, $USER->id);
if (!$accesscheck['allowed']) {
    throw new moodle_exception('nopermissions', 'error', '', $accesscheck['reason']);
}

// Get video record.
$video = $DB->get_record('assignsubmission_s3video', 
    ['submission' => $submissionid], '*', MUST_EXIST);

// Set up page.
$PAGE->set_url('/mod/assign/submission/s3video/view_video.php', ['id' => $submissionid, 's3key' => $s3key]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('watchvideo', 'assignsubmission_s3video'));
$PAGE->set_heading($assignment->name);
$PAGE->set_pagelayout('embedded');

// Get CloudFront configuration.
$cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
    throw new moodle_exception('config_missing', 'assignsubmission_s3video');
}

// Generate signed URL.
$cfclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);
$signedurl = $cfclient->get_signed_url($s3key, 86400); // 24 hour expiry

echo $OUTPUT->header();

echo html_writer::start_div('s3video-viewer-container', ['style' => 'max-width: 1200px; margin: 0 auto;']);

// Video info.
echo html_writer::tag('h3', get_string('s3video', 'assignsubmission_s3video'));

if ($video->file_size) {
    echo html_writer::tag('p', get_string('size', 'core') . ': ' . display_size($video->file_size), 
        ['class' => 'text-muted']);
}

// Video player using HTML5 video tag.
echo html_writer::start_tag('video', [
    'controls' => 'controls',
    'width' => '100%',
    'style' => 'max-width: 100%; height: auto; background-color: #000;',
    'preload' => 'metadata'
]);

echo html_writer::empty_tag('source', [
    'src' => $signedurl,
    'type' => $video->mime_type ?? 'video/mp4'
]);

echo html_writer::tag('p', get_string('videonotsupported', 'assignsubmission_s3video'));

echo html_writer::end_tag('video');

// Add a helpful note about downloading.
echo html_writer::start_div('alert alert-info mt-3');
echo html_writer::tag('p', 
    '<i class="fa fa-info-circle"></i> ' . get_string('downloadhint', 'assignsubmission_s3video'), 
    ['class' => 'mb-0']
);
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
