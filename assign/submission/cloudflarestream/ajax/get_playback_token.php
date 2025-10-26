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
 * AJAX endpoint to get a signed playback token for a video.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/lib.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/logger.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\logger;
use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;
use assignsubmission_cloudflarestream\rate_limiter;
use assignsubmission_cloudflarestream\rate_limit_exception;

// Verify user is authenticated via Moodle session.
require_login();

// Get and validate parameters.
try {
    $submission_id = validator::validate_submission_id(required_param('submission_id', PARAM_INT));
    $video_uid = validator::validate_video_uid(required_param('video_uid', PARAM_TEXT));
} catch (validation_exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'validation_error',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Set up response headers.
header('Content-Type: application/json');

try {
    global $USER, $DB;
    
    // Apply rate limiting.
    $ratelimiter = new rate_limiter();
    $ratelimiter->apply_rate_limit('playback', $USER->id, $video_uid);
    
    // Verify user has access to view this submission.
    verify_video_access($USER->id, $submission_id, $video_uid);
    
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (empty($apitoken) || empty($accountid)) {
        throw new moodle_exception('config_missing', 'assignsubmission_cloudflarestream');
    }
    
    // Create Cloudflare API client.
    $client = new cloudflare_client($apitoken, $accountid);
    
    // Generate signed token with 24-hour expiry.
    $expiry_seconds = 24 * 3600; // 24 hours
    $token = $client->generate_signed_token($video_uid, $expiry_seconds);
    
    // Get submission and assignment details for logging.
    $submission = $DB->get_record('assign_submission', array('id' => $submission_id), '*', MUST_EXIST);
    $assignment = $DB->get_record('assign', array('id' => $submission->assignment), '*', MUST_EXIST);
    
    // Determine user role for logging.
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    
    $userrole = 'student';
    if (is_siteadmin($USER->id)) {
        $userrole = 'admin';
    } else if (has_capability('mod/assign:grade', $context, $USER->id)) {
        $userrole = 'teacher';
    }
    
    // Log video access for audit trail.
    logger::log_playback_access(
        $USER->id,
        $assignment->id,
        $submission_id,
        $video_uid,
        $userrole
    );
    
    // Return signed token to frontend.
    echo json_encode([
        'success' => true,
        'token' => $token,
        'video_uid' => $video_uid,
        'expiry_seconds' => $expiry_seconds
    ]);
    
} catch (rate_limit_exception $e) {
    // Handle rate limit exceeded.
    http_response_code(429);
    if (!empty($e->debuginfo) && preg_match('/Retry after (\d+) seconds/', $e->debuginfo, $matches)) {
        header('Retry-After: ' . $matches[1]);
    }
    
    // Log rate limit event for monitoring.
    logger::log_playback_failure(
        $USER->id,
        $submission->assignment ?? 0,
        $submission_id,
        $video_uid,
        'rate_limit_exceeded',
        'Playback rate limit exceeded',
        $e->debuginfo
    );
    
    echo json_encode([
        'success' => false,
        'error' => 'rate_limit_exceeded',
        'error_type' => 'rate_limit',
        'message' => get_string('playback_rate_limit_exceeded', 'assignsubmission_cloudflarestream'),
        'user_message' => get_string('error_rate_limit_playback', 'assignsubmission_cloudflarestream'),
        'suggestions' => [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream')
        ],
        'retry_after' => $matches[1] ?? 60,
        'can_retry' => true
    ]);
    
} catch (moodle_exception $e) {
    // Determine error type and provide specific guidance.
    $errorType = 'permission_error';
    $userMessage = $e->getMessage();
    $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
    $canRetry = true;
    $httpCode = 403;
    
    // Handle specific Moodle exceptions.
    if ($e->errorcode === 'nopermission' || $e->errorcode === 'nopermissiontoviewvideo') {
        $errorType = 'permission_error';
        $userMessage = get_string('error_permission_denied', 'assignsubmission_cloudflarestream');
        $suggestions = [get_string('retry_contact_support', 'assignsubmission_cloudflarestream')];
        $canRetry = false;
        $httpCode = 403;
    } else if ($e->errorcode === 'config_missing') {
        $errorType = 'config_error';
        $userMessage = get_string('error_server_unavailable', 'assignsubmission_cloudflarestream');
        $suggestions = [
            get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream'),
            get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
        ];
        $httpCode = 503;
    } else if ($e->errorcode === 'invalidvideouid' || $e->errorcode === 'invalidparameters') {
        $errorType = 'validation_error';
        $userMessage = get_string('error_video_not_ready', 'assignsubmission_cloudflarestream');
        $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
        $httpCode = 400;
    }
    
    // Log the error with structured logging.
    logger::log_playback_failure(
        $USER->id,
        $submission->assignment ?? 0,
        $submission_id,
        $video_uid,
        $e->errorcode,
        $e->getMessage(),
        $e->debuginfo ?? null
    );
    
    // Handle Moodle exceptions (permission denied, etc.).
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $e->errorcode,
        'error_type' => $errorType,
        'message' => $userMessage,
        'user_message' => $userMessage,
        'suggestions' => $suggestions,
        'can_retry' => $canRetry
    ]);
    
} catch (Exception $e) {
    // Determine if this is a Cloudflare API error.
    $errorType = 'server_error';
    $userMessage = get_string('playback_token_error', 'assignsubmission_cloudflarestream');
    $suggestions = [
        get_string('retry_refresh_page', 'assignsubmission_cloudflarestream'),
        get_string('retry_wait_and_retry', 'assignsubmission_cloudflarestream')
    ];
    
    // Check for specific error patterns.
    if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_api_exception) {
        if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception) {
            $errorType = 'video_not_found';
            $userMessage = get_string('videonotavailable', 'assignsubmission_cloudflarestream');
            $suggestions = [
                get_string('retry_refresh_page', 'assignsubmission_cloudflarestream'),
                get_string('retry_contact_support', 'assignsubmission_cloudflarestream')
            ];
        } else if ($e instanceof \assignsubmission_cloudflarestream\api\cloudflare_auth_exception) {
            $errorType = 'auth_error';
            $userMessage = get_string('error_token_expired', 'assignsubmission_cloudflarestream');
            $suggestions = [get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')];
        } else if (strpos($e->getMessage(), 'network') !== false || strpos($e->getMessage(), 'timeout') !== false) {
            $errorType = 'network_error';
            $userMessage = get_string('error_network_connection', 'assignsubmission_cloudflarestream');
            $suggestions = [
                get_string('retry_check_connection', 'assignsubmission_cloudflarestream'),
                get_string('retry_refresh_page', 'assignsubmission_cloudflarestream')
            ];
        }
    }
    
    // Log the error with structured logging.
    logger::log_playback_failure(
        $USER->id,
        $submission->assignment ?? 0,
        $submission_id,
        $video_uid,
        'api_error',
        $e->getMessage(),
        $e->getTraceAsString()
    );
    
    // Handle other exceptions (API errors, etc.).
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'error_type' => $errorType,
        'message' => $userMessage,
        'user_message' => $userMessage,
        'suggestions' => $suggestions,
        'can_retry' => true
    ]);
    
    // Log the error for debugging.
    error_log('Cloudflare Stream: Error generating playback token - ' . $e->getMessage());
}
