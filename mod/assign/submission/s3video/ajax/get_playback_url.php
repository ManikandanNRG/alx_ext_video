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
 * AJAX endpoint to generate CloudFront signed URL for video playback.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\cloudfront_client;
use assignsubmission_s3video\api\cloudfront_api_exception;
use assignsubmission_s3video\api\cloudfront_signature_exception;
use assignsubmission_s3video\logger;
use assignsubmission_s3video\rate_limiter;
use assignsubmission_s3video\retry_handler;

// Get parameters.
$submissionid = required_param('submissionid', PARAM_INT);
$s3key = required_param('s3key', PARAM_TEXT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Verify user authentication.
    if (!isloggedin() || isguestuser()) {
        throw new moodle_exception('requireloginerror', 'error');
    }
    
    // Apply rate limiting for playback URL requests.
    $ratelimiter = new rate_limiter();
    try {
        $ratelimiter->apply_rate_limit('playback', $USER->id, $s3key);
    } catch (\assignsubmission_s3video\rate_limit_exception $e) {
        throw new moodle_exception('playback_rate_limit_exceeded', 'assignsubmission_s3video');
    }
    
    // Implement access control logic.
    $accesscheck = assignsubmission_s3video_verify_video_access($submissionid, $s3key, $USER->id);
    
    if (!$accesscheck['allowed']) {
        throw new moodle_exception('nopermissions', 'error', '', $accesscheck['reason']);
    }
    
    // Get the submission and assignment details.
    $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
    $assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
    
    // Get CloudFront configuration.
    $cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
    $keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
    $privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');
    
    if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
        throw new moodle_exception('config_missing', 'assignsubmission_s3video');
    }
    
    // Initialize CloudFront client.
    $cfclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);
    
    // Generate signed URL (24 hour expiration) with retry logic.
    $retryhandler = new retry_handler(3, 100, 5000);
    $signedurl = $retryhandler->execute_with_retry(
        function() use ($cfclient, $s3key) {
            return $cfclient->get_signed_url($s3key, 86400);
        },
        'generate_signed_url',
        [
            'userid' => $USER->id,
            'assignmentid' => $assignment->id,
            'submissionid' => $submissionid,
            's3_key' => $s3key,
        ]
    );
    
    // Determine user role for logging.
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course);
    $context = context_module::instance($cm->id);
    
    $userrole = 'student';
    if (is_siteadmin($USER->id)) {
        $userrole = 'admin';
    } else if (has_capability('mod/assign:grade', $context, $USER->id)) {
        $userrole = 'teacher';
    }
    
    // Log playback access.
    logger::log_playback_access($USER->id, $assignment->id, $submissionid, $s3key, $userrole);
    
    // Return success response.
    echo json_encode([
        'success' => true,
        'data' => [
            'signed_url' => $signedurl,
            'expires_in' => 86400,
        ],
    ]);
    
} catch (Exception $e) {
    // Log error.
    if (isset($USER->id) && isset($submissionid) && isset($s3key)) {
        logger::log_api_error(
            $USER->id,
            isset($assignment) ? $assignment->id : 0,
            $submissionid,
            $s3key,
            'playback_error',
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
    $httpcode = 403;
    $usermessage = get_string('error_unknown', 'assignsubmission_s3video');
    $guidance = get_string('error_unknown_guidance', 'assignsubmission_s3video');
    
    // Check exception type and message.
    if ($e instanceof cloudfront_signature_exception) {
        $errortype = 'cloudfront_error';
        $canretry = true;
        $httpcode = 503;
        $usermessage = get_string('error_cloudfront', 'assignsubmission_s3video');
        $guidance = get_string('error_cloudfront_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof cloudfront_api_exception) {
        $errortype = 'cloudfront_error';
        $canretry = true;
        $httpcode = 503;
        $usermessage = get_string('error_cloudfront', 'assignsubmission_s3video');
        $guidance = get_string('error_cloudfront_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof \assignsubmission_s3video\max_retries_exceeded_exception) {
        $errortype = 'max_retries';
        $canretry = false;
        $httpcode = 503;
        $usermessage = get_string('error_max_retries', 'assignsubmission_s3video');
        $guidance = get_string('error_max_retries_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof \assignsubmission_s3video\rate_limit_exception) {
        $errortype = 'rate_limit';
        $canretry = true;
        $httpcode = 429;
        $usermessage = get_string('error_throttling', 'assignsubmission_s3video');
        $guidance = get_string('error_throttling_guidance', 'assignsubmission_s3video');
    } else if ($e instanceof moodle_exception) {
        $errorcode = $e->errorcode;
        if ($errorcode === 'nopermission' || $errorcode === 'nopermissions' || $errorcode === 'requireloginerror') {
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
