<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to save TUS session to database.
 * Called after JavaScript creates TUS session with Cloudflare.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');

use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;

// Get and validate parameters.
try {
    $videouid = validator::validate_video_uid(required_param('videouid', PARAM_TEXT));
    $submissionid = validator::validate_submission_id(required_param('submissionid', PARAM_INT));
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
    // Create database record with pending status.
    $record = new stdClass();
    $record->submission = $submissionid;
    $record->video_uid = $videouid;
    $record->upload_status = 'pending';
    $record->upload_timestamp = time();
    
    // Validate and sanitize the record.
    $record = validator::validate_database_record($record);
    
    // Check if record already exists for this submission.
    $existing = $DB->get_record('assignsubmission_cfstream', 
        ['submission' => $submissionid]);
    
    if ($existing) {
        // Update existing record.
        $record->id = $existing->id;
        $DB->update_record('assignsubmission_cfstream', $record);
    } else {
        // Get assignment ID from submission.
        $submission = $DB->get_record('assign_submission', ['id' => $submissionid], 'assignment', MUST_EXIST);
        $record->assignment = $submission->assignment;
        
        // Insert new record.
        $DB->insert_record('assignsubmission_cfstream', $record);
    }
    
    echo json_encode([
        'success' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
