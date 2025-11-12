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
 * AJAX endpoint to get a direct upload URL from Cloudflare Stream.
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
use assignsubmission_cloudflarestream\rate_limiter;
use assignsubmission_cloudflarestream\rate_limit_exception;

// Get and validate parameters.
try {
    $assignmentid = validator::validate_assignment_id(required_param('assignmentid', PARAM_INT));
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
    if ($submissionid > 0) {
        $submissionid = validator::validate_submission_id($submissionid);
    }
} catch (validation_exception $e) {
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
    // Apply rate limiting.
    $ratelimiter = new rate_limiter();
    $ratelimiter->apply_rate_limit('upload', $USER->id, $assignmentid);
    
    // Load the assignment.
    list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
    $context = context_module::instance($cm->id);
    
    // Create assignment object.
    $assign = new assign($context, $cm, $course);
    
    // Verify user has permission to submit.
    require_capability('mod/assign:submit', $context);
    
    // Check if submissions are allowed.
    if (!$assign->submissions_open($USER->id)) {
        throw new moodle_exception('submissionsclosed', 'assign');
    }
    
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (empty($apitoken) || empty($accountid)) {
        throw new moodle_exception('config_missing', 'assignsubmission_cloudflarestream');
    }
    
    // Create Cloudflare client.
    $client = new cloudflare_client($apitoken, $accountid);
    
    // Request direct upload URL from Cloudflare.
    $result = $client->get_direct_upload_url(); // Use default settings
    
    // Get or create submission record.
    $submission = $assign->get_user_submission($USER->id, true);
    
    // Check if record already exists for this submission.
    $existing = $DB->get_record('assignsubmission_cfstream', 
        array('submission' => $submission->id));
    
    // Delete old video from Cloudflare if this is a replacement
    if ($existing && !empty($existing->video_uid) && $existing->video_uid !== $result->uid) {
        error_log("Cloudflare get_upload_url: Detected video replacement - Old UID: {$existing->video_uid}, New UID: {$result->uid}");
        
        try {
            $client->delete_video($existing->video_uid);
            error_log("Cloudflare get_upload_url: ✓ Successfully deleted old video {$existing->video_uid}");
        } catch (cloudflare_video_not_found_exception $e) {
            error_log("Cloudflare get_upload_url: Old video {$existing->video_uid} already deleted (404)");
        } catch (Exception $e) {
            error_log("Cloudflare get_upload_url: ✗ Failed to delete old video {$existing->video_uid}: " . $e->getMessage());
        }
    }
    
    // Create database record with pending status.
    // IMPORTANT: Store video_uid immediately so cleanup can find it if upload fails
    $record = new stdClass();
    $record->assignment = $assignmentid;
    $record->submission = $submission->id;
    $record->video_uid = $result->uid; // Store UID immediately for cleanup
    $record->upload_status = 'pending';
    $record->upload_timestamp = time();
    
    // Validate and sanitize the record before database operations.
    $record = validator::validate_database_record($record);
    
    if ($existing) {
        // Update existing record.
        $record->id = $existing->id;
        $DB->update_record('assignsubmission_cfstream', $record);
    } else {
        // Insert new record.
        $DB->insert_record('assignsubmission_cfstream', $record);
    }
    
    // Log the response for debugging
    error_log('Cloudflare upload URL response: uploadURL=' . $result->uploadURL . ', uid=' . $result->uid . ', submissionid=' . $submission->id);
    
    // Return success response with upload URL and UID.
    echo json_encode([
        'success' => true,
        'uploadURL' => $result->uploadURL,
        'uid' => $result->uid,
        'submissionid' => $submission->id
    ]);
    
} catch (rate_limit_exception $e) {
    // Return rate limit error with retry-after header.
    http_response_code(429);
    if (!empty($e->debuginfo) && preg_match('/Retry after (\d+) seconds/', $e->debuginfo, $matches)) {
        header('Retry-After: ' . $matches[1]);
    }
    
    // Log rate limit event for monitoring.
    logger::log_upload_failure(
        $USER->id,
        $assignmentid,
        isset($submission) ? $submission->id : null,
        'rate_limit_exceeded',
        'Upload rate limit exceeded',
        $e->debuginfo
    );
    
    echo json_encode([
        'success' => false,
        'error' => get_string('upload_rate_limit_exceeded', 'assignsubmission_cloudflarestream'),
        'error_type' => 'rate_limit',
        'retry_after' => $matches[1] ?? 60,
        'user_message' => get_string('error_rate_limit_upload', 'assignsubmission_cloudflarestream'),
        'suggestions' => [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream')
        ]
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
    if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_auth_exception) {
        $errorType = 'auth_error';
        $userMessage = get_string('error_authentication', 'assignsubmission_cloudflarestream');
        $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
    } else if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_quota_exception) {
        $errorType = 'quota_error';
        $userMessage = get_string('error_quota_exceeded', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream'),
            get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
        ];
    } else if (strpos($e->getMessage(), 'network') !== false || strpos($e->getMessage(), 'timeout') !== false) {
        $errorType = 'network_error';
        $userMessage = get_string('error_network_connection', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_check_connection', 'assignsubmission_cloudflarestream'),
            get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')
        ];
    }
    
    // Log the error with structured logging.
    logger::log_upload_failure(
        $USER->id,
        $assignmentid,
        isset($submission) ? $submission->id : null,
        'cloudflare_api_error',
        $e->getMessage(),
        $e->debuginfo
    );
    
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
    
} catch (assignsubmission_cloudflarestream\validation_exception $e) {
    // Handle validation errors from the validator class.
    $errorType = 'validation_error';
    $userMessage = 'Invalid request data: ' . $e->getMessage();
    $suggestions = [
        get_string('retry_refresh_page', 'assignsubmission_cloudflarestream'),
        get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
    ];
    
    // Log the validation error.
    logger::log_upload_failure(
        $USER->id,
        $assignmentid,
        isset($submission) ? $submission->id : null,
        'validation_error',
        $e->getMessage(),
        $e->debuginfo ?? null
    );
    
    // Return validation error response.
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
    logger::log_upload_failure(
        $USER->id,
        $assignmentid,
        isset($submission) ? $submission->id : null,
        $e->errorcode,
        $e->getMessage(),
        $e->debuginfo ?? null
    );
    
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
    logger::log_upload_failure(
        $USER->id,
        $assignmentid,
        isset($submission) ? $submission->id : null,
        'unexpected_error',
        $e->getMessage(),
        $e->getTraceAsString()
    );
    
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
