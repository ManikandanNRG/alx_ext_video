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
 * AJAX endpoint to confirm video upload completion.
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
use assignsubmission_s3video\api\s3_object_not_found_exception;
use assignsubmission_s3video\logger;
use assignsubmission_s3video\retry_handler;

// Get parameters.
$s3key = required_param('s3_key', PARAM_TEXT);
$submissionid = required_param('submissionid', PARAM_INT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Get the submission record.
    $videorecord = $DB->get_record('assignsubmission_s3video', 
        ['submission' => $submissionid], '*', MUST_EXIST);
    
    // Verify the S3 key matches.
    if ($videorecord->s3_key !== $s3key) {
        throw new moodle_exception('invalids3key', 'assignsubmission_s3video');
    }
    
    // Get submission to verify ownership.
    $submission = $DB->get_record('assign_submission', 
        ['id' => $submissionid], '*', MUST_EXIST);
    
    // Verify user owns this submission.
    if ($submission->userid != $USER->id) {
        throw new moodle_exception('nopermission', 'error');
    }
    
    // Get AWS configuration.
    $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
    $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
    $bucket = get_config('assignsubmission_s3video', 's3_bucket');
    $region = get_config('assignsubmission_s3video', 's3_region');
    
    if (empty($accesskey) || empty($secretkey) || empty($bucket) || empty($region)) {
        throw new moodle_exception('config_missing', 'assignsubmission_s3video');
    }
    
    // Initialize S3 client.
    $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
    
    // Verify file exists in S3 with retry logic.
    $retryhandler = new retry_handler(3, 100, 5000);
    $exists = $retryhandler->execute_with_retry(
        function() use ($s3client, $s3key) {
            return $s3client->object_exists($s3key);
        },
        'verify_s3_object',
        [
            'userid' => $USER->id,
            'assignmentid' => $videorecord->assignment,
            'submissionid' => $submissionid,
            's3_key' => $s3key,
        ]
    );
    
    if (!$exists) {
        throw new s3_object_not_found_exception('Object not found: ' . $s3key);
    }
    
    // Get file metadata from S3 with retry logic.
    $metadata = $retryhandler->execute_with_retry(
        function() use ($s3client, $s3key) {
            return $s3client->get_object_metadata($s3key);
        },
        'get_s3_metadata',
        [
            'userid' => $USER->id,
            'assignmentid' => $videorecord->assignment,
            'submissionid' => $submissionid,
            's3_key' => $s3key,
        ]
    );
    
    // Update database record with status 'ready' and metadata.
    $videorecord->upload_status = 'ready';
    $videorecord->file_size = $metadata->size;
    $videorecord->mime_type = $metadata->content_type;
    
    // Clear any previous error message.
    $videorecord->error_message = null;
    
    $DB->update_record('assignsubmission_s3video', $videorecord);
    
    // Update the submission timestamp to trigger grading notifications.
    $submission->timemodified = time();
    $DB->update_record('assign_submission', $submission);
    
    // Log successful upload.
    logger::log_event($USER->id, $videorecord->assignment, $submissionid, $s3key,
        'upload_completed', ['file_size' => $metadata->size]);
    
    // Return success response.
    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'ready',
            'file_size' => $metadata->size,
            'file_size_formatted' => display_size($metadata->size),
            'content_type' => $metadata->content_type,
        ],
    ]);
    
} catch (Exception $e) {
    // Update database record with error status.
    if (isset($videorecord)) {
        $videorecord->upload_status = 'error';
        $videorecord->error_message = $e->getMessage();
        $DB->update_record('assignsubmission_s3video', $videorecord);
    }
    
    // Log error.
    if (isset($USER->id)) {
        logger::log_upload_failure(
            $USER->id,
            isset($videorecord) ? $videorecord->assignment : null,
            $submissionid,
            $s3key,
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
    } else if ($e instanceof s3_object_not_found_exception) {
        $errortype = 's3_not_found';
        $canretry = true;
        $httpcode = 404;
        $usermessage = get_string('error_s3_verify', 'assignsubmission_s3video');
        $guidance = get_string('error_s3_verify_guidance', 'assignsubmission_s3video');
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
        $usermessage = get_string('error_s3_verify', 'assignsubmission_s3video');
        $guidance = get_string('error_s3_verify_guidance', 'assignsubmission_s3video');
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
