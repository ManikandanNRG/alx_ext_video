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
 * AJAX endpoint to generate presigned POST URL for video upload.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\s3_api_exception;
use assignsubmission_s3video\api\s3_auth_exception;
use assignsubmission_s3video\logger;
use assignsubmission_s3video\rate_limiter;
use assignsubmission_s3video\retry_handler;

// Get parameters.
$assignmentid = required_param('assignmentid', PARAM_INT);
$filename = required_param('filename', PARAM_FILE);
$filesize = required_param('filesize', PARAM_INT);
$mimetype = required_param('mimetype', PARAM_TEXT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Load assignment context.
    list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
    $context = context_module::instance($cm->id);
    
    // Verify user has permission to submit.
    require_capability('mod/assign:submit', $context);
    
    // Apply rate limiting for upload URL requests.
    $ratelimiter = new rate_limiter();
    try {
        $ratelimiter->apply_rate_limit('upload', $USER->id, $assignmentid);
    } catch (\assignsubmission_s3video\rate_limit_exception $e) {
        throw new moodle_exception('upload_rate_limit_exceeded', 'assignsubmission_s3video');
    }
    
    // Load assignment.
    $assign = new assign($context, $cm, $course);
    
    // Check if submissions are allowed.
    if (!$assign->submissions_open($USER->id)) {
        throw new moodle_exception('submissionsclosed', 'assign');
    }
    
    // Validate file size (max 5 GB).
    $maxsize = 5368709120; // 5 GB in bytes.
    if ($filesize > $maxsize) {
        throw new moodle_exception('filesizeexceeded', 'assignsubmission_s3video', '', 
            ['max' => display_size($maxsize), 'actual' => display_size($filesize)]);
    }
    
    // Validate MIME type (must be video).
    if (strpos($mimetype, 'video/') !== 0) {
        throw new moodle_exception('invalidmimetype', 'assignsubmission_s3video', '', $mimetype);
    }
    
    // Get AWS configuration.
    $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
    $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
    $bucket = get_config('assignsubmission_s3video', 's3_bucket');
    $region = get_config('assignsubmission_s3video', 's3_region');
    
    if (empty($accesskey) || empty($secretkey) || empty($bucket) || empty($region)) {
        throw new moodle_exception('config_missing', 'assignsubmission_s3video');
    }
    
    // Generate unique S3 key.
    // Format: videos/{userid}/{timestamp}_{random}/{filename}
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $cleanfilename = clean_param($filename, PARAM_FILE);
    $s3key = "videos/{$USER->id}/{$timestamp}_{$random}/{$cleanfilename}";
    
    // Initialize S3 client.
    $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
    
    // Generate presigned POST with retry logic.
    $retryhandler = new retry_handler(3, 100, 5000);
    $presigneddata = $retryhandler->execute_with_retry(
        function() use ($s3client, $s3key, $maxsize, $mimetype) {
            return $s3client->get_presigned_post($s3key, $maxsize, $mimetype, 3600);
        },
        'generate_presigned_post',
        [
            'userid' => $USER->id,
            'assignmentid' => $assignmentid,
            's3_key' => $s3key,
        ]
    );
    
    // Get or create submission.
    $submission = $assign->get_user_submission($USER->id, true);
    
    // Create database record with status 'pending'.
    $record = new stdClass();
    $record->assignment = $assignmentid;
    $record->submission = $submission->id;
    $record->s3_key = $s3key;
    $record->s3_bucket = $bucket;
    $record->upload_status = 'pending';
    $record->upload_timestamp = $timestamp;
    $record->mime_type = $mimetype;
    
    // Check if record already exists.
    $existing = $DB->get_record('assignsubmission_s3video', 
        ['submission' => $submission->id]);
    
    if ($existing) {
        // Update existing record.
        $record->id = $existing->id;
        $DB->update_record('assignsubmission_s3video', $record);
    } else {
        // Insert new record.
        $DB->insert_record('assignsubmission_s3video', $record);
    }
    
    // Log the upload request.
    logger::log_event($USER->id, $assignmentid, $submission->id, $s3key, 
        'upload_requested', ['file_size' => $filesize]);
    
    // Return success response.
    echo json_encode([
        'success' => true,
        'data' => [
            'url' => $presigneddata['url'],
            'fields' => $presigneddata['fields'],
            's3_key' => $s3key,
            'submission_id' => $submission->id,
        ],
    ]);
    
} catch (Exception $e) {
    // Log error.
    if (isset($USER->id) && isset($assignmentid)) {
        logger::log_upload_failure(
            $USER->id, 
            $assignmentid, 
            isset($submission) ? $submission->id : null,
            isset($s3key) ? $s3key : null,
            $e->getMessage(),
            get_class($e)
        );
    }
    
    // Determine error type and user-friendly message.
    $errorinfo = get_error_info($e);
    
    // Return error response.
    http_response_code($errorinfo['http_code']);
    echo json_encode([
        'success' => false,
        'error' => $errorinfo['user_message'],
        'error_type' => $errorinfo['error_type'],
        'can_retry' => $errorinfo['can_retry'],
        'guidance' => $errorinfo['guidance'],
        'technical_details' => debugging('', DEBUG_DEVELOPER) ? $e->getMessage() : null,
    ]);
}

/**
 * Get user-friendly error information from exception.
 *
 * @param Exception $e The exception
 * @return array Error information
 */
function get_error_info(Exception $e) {
    $errortype = 'unknown';
    $canretry = false;
    $httpcode = 400;
    $usermessage = get_string('error_unknown', 'assignsubmission_s3video');
    $guidance = get_string('error_unknown_guidance', 'assignsubmission_s3video');
    
    // Check exception type and message.
    if ($e instanceof s3_auth_exception) {
        $errortype = 'auth_error';
        $canretry = false;
        $httpcode = 401;
        $usermessage = get_string('error_auth', 'assignsubmission_s3video');
        $guidance = get_string('error_auth_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof \assignsubmission_s3video\max_retries_exceeded_exception) {
        $errortype = 'max_retries';
        $canretry = false;
        $httpcode = 503;
        $usermessage = get_string('error_max_retries', 'assignsubmission_s3video');
        $guidance = get_string('error_max_retries_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof s3_api_exception) {
        $errortype = 's3_error';
        $canretry = true;
        $httpcode = 503;
        $usermessage = get_string('error_s3_upload', 'assignsubmission_s3video');
        $guidance = get_string('error_s3_upload_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof \assignsubmission_s3video\rate_limit_exception) {
        $errortype = 'rate_limit';
        $canretry = true;
        $httpcode = 429;
        $usermessage = get_string('error_throttling', 'assignsubmission_s3video');
        $guidance = get_string('error_throttling_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof moodle_exception) {
        $errorcode = $e->errorcode;
        if ($errorcode === 'nopermission' || $errorcode === 'requireloginerror') {
            $errortype = 'permission_error';
            $canretry = false;
            $httpcode = 403;
            $usermessage = get_string('error_permission', 'assignsubmission_s3video');
            $guidance = get_string('error_permission_guidance', 'assignsubmission_s3video');
        } else if ($errorcode === 'config_missing') {
            $errortype = 'config_error';
            $canretry = false;
            $httpcode = 500;
            $usermessage = get_string('error_config', 'assignsubmission_s3video');
            $guidance = get_string('error_config_guidance', 'assignsubmission_s3video');
        } else if (strpos($errorcode, 'invalid') !== false || strpos($errorcode, 'validation') !== false) {
            $errortype = 'validation_error';
            $canretry = false;
            $httpcode = 400;
            $usermessage = get_string('error_validation', 'assignsubmission_s3video');
            $guidance = get_string('error_validation_guidance', 'assignsubmission_s3video');
        } else {
            $usermessage = $e->getMessage();
        }
    }
    
    // Check for network errors in message.
    $message = strtolower($e->getMessage());
    if (strpos($message, 'network') !== false || strpos($message, 'connection') !== false) {
        $errortype = 'network_error';
        $canretry = true;
        $httpcode = 503;
        $usermessage = get_string('error_network', 'assignsubmission_s3video');
        $guidance = get_string('error_network_guidance', 'assignsubmission_s3video');
    } else if (strpos($message, 'timeout') !== false || strpos($message, 'timed out') !== false) {
        $errortype = 'timeout_error';
        $canretry = true;
        $httpcode = 504;
        $usermessage = get_string('error_timeout', 'assignsubmission_s3video');
        $guidance = get_string('error_timeout_guidance', 'assignsubmission_s3video');
    }
    
    return [
        'error_type' => $errortype,
        'can_retry' => $canretry,
        'http_code' => $httpcode,
        'user_message' => $usermessage,
        'guidance' => $guidance,
    ];
}
