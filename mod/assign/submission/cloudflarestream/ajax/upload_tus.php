<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for TUS upload - handles entire upload in PHP.
 * JavaScript sends file chunks, PHP uploads to Cloudflare via TUS.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;

// Get parameters.
$action = required_param('action', PARAM_ALPHA);
$assignmentid = required_param('assignmentid', PARAM_INT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

// Declare globals.
global $DB, $USER;

try {
    // Load the assignment.
    list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
    $context = context_module::instance($cm->id);
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
    
    // Handle different actions.
    if ($action === 'create') {
        // Create TUS session.
        $filesize = required_param('filesize', PARAM_INT);
        $filename = required_param('filename', PARAM_TEXT);
        
        // Validate file size against configured maximum
        try {
            validator::validate_file_size($filesize);
        } catch (validation_exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
        
        $result = $client->create_tus_upload($filesize, $filename);
        
        // Get or create submission.
        $submission = $assign->get_user_submission($USER->id, true);
        
        // Save to database.
        $record = new stdClass();
        $record->assignment = $assignmentid;
        $record->submission = $submission->id;
        $record->video_uid = $result->uid;
        $record->upload_status = 'pending';
        $record->upload_timestamp = time();
        
        $existing = $DB->get_record('assignsubmission_cfstream', ['submission' => $submission->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('assignsubmission_cfstream', $record);
        } else {
            $DB->insert_record('assignsubmission_cfstream', $record);
        }
        
        // Store upload URL in user preferences for subsequent chunk uploads.
        set_user_preference('tus_upload_url_' . $USER->id, $result->upload_url);
        set_user_preference('tus_video_uid_' . $USER->id, $result->uid);
        
        echo json_encode([
            'success' => true,
            'uid' => $result->uid,
            'upload_url' => $result->upload_url,
            'submissionid' => $submission->id
        ]);
        
    } else if ($action === 'chunk') {
        // Upload chunk.
        $offset = required_param('offset', PARAM_INT);
        
        // Get upload URL from user preferences.
        $uploadurl = get_user_preferences('tus_upload_url_' . $USER->id);
        
        if (empty($uploadurl)) {
            error_log('TUS Error: No upload URL found in preferences for user ' . $USER->id);
            throw new moodle_exception('error', 'assignsubmission_cloudflarestream', '', null, 'TUS session not found');
        }
        
        // Read chunk data from request body.
        $chunkdata = file_get_contents('php://input');
        
        error_log('TUS Chunk: offset=' . $offset . ', data_length=' . strlen($chunkdata) . ', upload_url=' . substr($uploadurl, 0, 50) . '...');
        
        if (empty($chunkdata)) {
            error_log('TUS Error: No chunk data received. Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
            throw new moodle_exception('error', 'assignsubmission_cloudflarestream', '', null, 'No chunk data');
        }
        
        // Upload chunk to Cloudflare.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadurl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apitoken,  // CRITICAL: Must include auth!
            'Tus-Resumable: 1.0.0',
            'Upload-Offset: ' . $offset,
            'Content-Type: application/offset+octet-stream',
            'Content-Length: ' . strlen($chunkdata)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $chunkdata);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlerror = curl_error($ch);
        curl_close($ch);
        
        // Log Cloudflare response
        error_log('Cloudflare TUS Response: HTTP ' . $httpcode);
        if (!empty($curlerror)) {
            error_log('cURL Error: ' . $curlerror);
        }
        
        if ($httpcode === 204) {
            // Parse headers to get new offset.
            $headertext = substr($response, 0, $headersize);
            $newoffset = $offset + strlen($chunkdata);
            
            foreach (explode("\r\n", $headertext) as $line) {
                if (stripos($line, 'Upload-Offset:') === 0) {
                    $newoffset = (int)trim(substr($line, 14));
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'offset' => $newoffset
            ]);
        } else {
            // Log response body for debugging
            $body = substr($response, $headersize);
            error_log('Cloudflare Error Body: ' . $body);
            throw new moodle_exception('error', 'assignsubmission_cloudflarestream', '', null, 'Chunk upload failed: HTTP ' . $httpcode . ' - ' . $body);
        }
        
    } else {
        throw new moodle_exception('error', 'assignsubmission_cloudflarestream', '', null, 'Invalid action');
    }
    
} catch (Exception $e) {
    // Log the full error for debugging.
    error_log('TUS Upload Error: ' . $e->getMessage());
    error_log('TUS Upload Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $e->getFile() . ':' . $e->getLine()
    ]);
}
