<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Download video file from S3 via CloudFront.
 * This script forces the browser to download the file instead of playing it.
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

// Get CloudFront configuration.
$cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
    throw new moodle_exception('config_missing', 'assignsubmission_s3video');
}

// Generate signed URL with Content-Disposition header to force download.
$cfclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);

// Get the filename from the S3 key.
$filename = basename($s3key);

// Generate signed URL with response-content-disposition parameter to force download.
$baseurl = 'https://' . $cloudfrontdomain . '/' . $s3key;
$baseurl .= '?response-content-disposition=' . urlencode('attachment; filename="' . $filename . '"');

// Sign the URL with the query parameter included.
$signedurl = $cfclient->sign_url_with_canned_policy($baseurl, time() + 86400);

// Redirect to the signed URL.
// The response-content-disposition parameter will force the browser to download.
redirect($signedurl);
