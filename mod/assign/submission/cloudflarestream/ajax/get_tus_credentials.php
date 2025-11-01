<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to get Cloudflare credentials for TUS upload.
 * Returns API token and account ID so JavaScript can make TUS request directly.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;

// Get and validate parameters.
try {
    $assignmentid = validator::validate_assignment_id(required_param('assignmentid', PARAM_INT));
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
    
    // Get or create submission record.
    $submission = $assign->get_user_submission($USER->id, true);
    
    // Return credentials for JavaScript to use.
    echo json_encode([
        'success' => true,
        'api_token' => $apitoken,
        'account_id' => $accountid,
        'submissionid' => $submission->id
    ]);
    
} catch (moodle_exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
