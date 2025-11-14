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
 * AJAX endpoint to confirm video upload completion and fetch video details.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\api\cloudflare_api_exception;
use assignsubmission_cloudflarestream\logger;
use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;

// Get and validate parameters.
// DEBUG: Log ALL incoming data
error_log('=== CONFIRM_UPLOAD.PHP DEBUG ===');
error_log('$_POST data: ' . print_r($_POST, true));
error_log('$_GET data: ' . print_r($_GET, true));
error_log('$_REQUEST data: ' . print_r($_REQUEST, true));

// Log all received parameters for debugging
$received_videouid = optional_param('videouid', '', PARAM_TEXT);
$received_submissionid = optional_param('submissionid', 0, PARAM_INT);
error_log('confirm_upload.php received: videouid=' . $received_videouid . ', submissionid=' . $received_submissionid);
error_log('videouid is empty: ' . (empty($received_videouid) ? 'YES' : 'NO'));
error_log('================================');

try {
    $videouid = validator::validate_video_uid(required_param('videouid', PARAM_TEXT));
    $submissionid = validator::validate_submission_id(required_param('submissionid', PARAM_INT));
    error_log('confirm_upload.php validated: videouid=' . $videouid . ', submissionid=' . $submissionid);
} catch (validation_exception $e) {
    error_log('confirm_upload.php validation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Get the submission record.
    $submission = $DB->get_record('assign_submission', array('id' => $submissionid), '*', MUST_EXIST);
    
    // Get the assignment to verify context.
    list($course, $cm) = get_course_and_cm_from_instance($submission->assignment, 'assign');
    $context = context_module::instance($cm->id);
    
    // Verify user has permission to confirm this upload.
    // Allow: submission owner, teachers who can grade, or site admins
    $canconfirm = ($submission->userid == $USER->id) || 
                  has_capability('mod/assign:grade', $context) || 
                  is_siteadmin();
    
    if (!$canconfirm) {
        throw new moodle_exception('nopermission', 'assignsubmission_cloudflarestream');
    }
    
    // Create assignment object.
    $assign = new assign($context, $cm, $course);
    
    // Note: We don't check can_edit_submission() here because:
    // 1. The upload has already completed successfully to Cloudflare
    // 2. We only need to verify ownership (already checked above)
    // 3. This matches the behavior of other submission plugins
    
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (empty($apitoken) || empty($accountid)) {
        throw new moodle_exception('config_missing', 'assignsubmission_cloudflarestream');
    }
    
    // Create Cloudflare client.
    $client = new cloudflare_client($apitoken, $accountid);
    
    // Fetch video details from Cloudflare.
    $videodetails = $client->get_video_details($videouid);
    
    // Validate the API response.
    validator::validate_video_details($videodetails);
    
    // Extract relevant information.
    $duration = isset($videodetails->duration) ? (int)$videodetails->duration : null;
    $filesize = isset($videodetails->size) ? (int)$videodetails->size : null;
    $status = isset($videodetails->status->state) ? $videodetails->status->state : 'unknown';
    
    // Map Cloudflare status to our status.
    if ($status === 'ready') {
        $uploadstatus = 'ready';
    } else if ($status === 'queued' || $status === 'inprogress') {
        $uploadstatus = 'uploading';
    } else if ($status === 'error') {
        $uploadstatus = 'error';
    } else {
        // Default to ready if status is unknown but video details were fetched successfully
        $uploadstatus = 'ready';
    }
    
    // Log the status mapping for debugging
    error_log("Cloudflare status: $status -> DB status: $uploadstatus");
    
    // Get the database record for THIS video (temporary record with submission=0)
    $record = $DB->get_record('assignsubmission_cfstream', 
        array('video_uid' => $videouid));
    
    if (!$record) {
        throw new moodle_exception('error', 'assignsubmission_cloudflarestream', '', 
            'No database record found for video ' . $videouid);
    }
    
    error_log("Cloudflare confirm_upload: Found record ID {$record->id} (submission={$record->submission}, video_uid={$record->video_uid})");
    
    // Note: Old video deletion is handled in lib.php save() method
    // when user clicks "Save changes" button.
    
    // Update the record with video details
    $record->upload_status = $uploadstatus;
    
    if ($duration !== null) {
        $record->duration = $duration;
    }
    
    if ($filesize !== null) {
        $record->file_size = $filesize;
    }
    
    // Clear any previous error message if upload is successful.
    if ($uploadstatus === 'ready') {
        $record->error_message = null;
    }
    
    // Validate and sanitize the record before database update.
    $record = validator::validate_database_record($record);
    
    // Update the database.
    $DB->update_record('assignsubmission_cfstream', $record);
    
    // Videos are uploaded as PUBLIC with domain restrictions for security
    // No need to set requireSignedURLs=true since we don't have signing keys
    
    // Log successful upload.
    if ($uploadstatus === 'ready') {
        logger::log_upload_success(
            $USER->id,
            $submission->assignment,
            $submissionid,
            $videouid,
            $filesize,
            $duration
        );
    }
    
    // Return success response with video details.
    echo json_encode([
        'success' => true,
        'videouid' => $videouid,
        'status' => $uploadstatus,
        'duration' => $duration,
        'filesize' => $filesize,
        'message' => get_string('uploadsuccess', 'assignsubmission_cloudflarestream')
    ]);
    
} catch (cloudflare_api_exception $e) {
    // Determine specific error type and provide targeted guidance.
    $errorType = 'api_error';
    $userMessage = get_string('cloudflare_unavailable', 'assignsubmission_cloudflarestream');
    $suggestions = [
        get_string('retry_check_connection', 'assignsubmission_cloudflarestream'),
        get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream')
    ];
    
    // Analyze specific Cloudflare API errors.
    if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception) {
        $errorType = 'video_not_found';
        $userMessage = get_string('error_processing_failed', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_refresh_page', 'assignsubmission_cloudflarestream'),
            get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
        ];
    } else if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_auth_exception) {
        $errorType = 'auth_error';
        $userMessage = get_string('error_authentication', 'assignsubmission_cloudflarestream');
        $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
    } else if (strpos($e->getMessage(), 'processing') !== false) {
        $errorType = 'processing_error';
        $userMessage = get_string('error_processing_failed', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream'),
            get_string('retry_different_file', 'assignsubmission_cloudflarestream')
        ];
    }
    
    // Try to update the database record with error status.
    try {
        if (isset($submissionid) && isset($submission)) {
            $record = $DB->get_record('assignsubmission_cfstream', 
                array('submission' => $submissionid));
            
            if ($record) {
                $record->upload_status = 'error';
                $record->error_message = $userMessage;
                $DB->update_record('assignsubmission_cfstream', $record);
            }
            
            // Log the error with structured logging.
            logger::log_upload_failure(
                $USER->id,
                $submission->assignment,
                $submissionid,
                'cloudflare_api_error',
                $e->getMessage(),
                $e->debuginfo
            );
        }
    } catch (Exception $dberror) {
        error_log('Failed to update error status in database: ' . $dberror->getMessage());
        // Log database error for admin troubleshooting.
        logger::log_upload_failure(
            $USER->id ?? 0,
            $submission->assignment ?? 0,
            $submissionid ?? 0,
            'database_error',
            'Failed to update error status: ' . $dberror->getMessage(),
            $dberror->getTraceAsString()
        );
    }
    
    // Return comprehensive error response.
    echo json_encode([
        'success' => false,
        'error' => $userMessage,
        'error_type' => $errorType,
        'user_message' => $userMessage,
        'suggestions' => $suggestions,
        'debug' => $e->getMessage(),
        'can_retry' => true
    ]);
    
} catch (moodle_exception $e) {
    // Determine error type and provide specific guidance.
    $errorType = 'permission_error';
    $userMessage = $e->getMessage();
    $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
    $canRetry = true;
    
    // Handle specific Moodle exceptions.
    if ($e->errorcode === 'nopermission') {
        $errorType = 'permission_error';
        $userMessage = get_string('error_permission_denied', 'assignsubmission_cloudflarestream');
        $suggestions = [get_string('retry_contact_support', 'assignsubmission_cloudflarestream')];
        $canRetry = false;
    } else if ($e->errorcode === 'config_missing') {
        $errorType = 'config_error';
        $userMessage = get_string('error_server_unavailable', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream'),
            get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
        ];
    }
    
    // Log the error with structured logging.
    if (isset($submissionid) && isset($submission)) {
        logger::log_upload_failure(
            $USER->id,
            $submission->assignment,
            $submissionid,
            $e->errorcode,
            $e->getMessage(),
            $e->debuginfo ?? null
        );
    }
    
    // Return comprehensive error response.
    echo json_encode([
        'success' => false,
        'error' => $userMessage,
        'error_type' => $errorType,
        'user_message' => $userMessage,
        'suggestions' => $suggestions,
        'can_retry' => $canRetry
    ]);
    
} catch (Exception $e) {
    // Log unexpected errors with structured logging.
    if (isset($submissionid) && isset($submission)) {
        logger::log_upload_failure(
            $USER->id,
            $submission->assignment,
            $submissionid,
            'unexpected_error',
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
    
    // Return comprehensive generic error response.
    echo json_encode([
        'success' => false,
        'error' => get_string('upload_error', 'assignsubmission_cloudflarestream'),
        'error_type' => 'unexpected_error',
        'user_message' => get_string('error_server_unavailable', 'assignsubmission_cloudflarestream'),
        'suggestions' => [
            get_string('retry_refresh_page', 'assignsubmission_cloudflarestream'),
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream'),
            get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
        ],
        'can_retry' => true
    ]);
}
