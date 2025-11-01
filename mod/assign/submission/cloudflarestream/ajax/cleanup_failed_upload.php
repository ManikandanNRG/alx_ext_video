<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to clean up failed uploads.
 * Deletes video from Cloudflare and database when upload fails.
 * (TASK 7 PHASE 1)
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\logger;

// Get parameters - handle both POST and sendBeacon requests
$videouid = optional_param('videouid', '', PARAM_ALPHANUMEXT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

// Log the request for debugging
error_log("Cleanup request received: videouid={$videouid}, submissionid={$submissionid}");

// Validate parameters
if (empty($videouid)) {
    error_log("Cleanup failed: Missing videouid parameter");
    echo json_encode(['success' => false, 'error' => 'Missing videouid']);
    exit;
}

// Require login - but DON'T require sesskey for sendBeacon requests
require_login();
error_log("After require_login - user logged in successfully");

// Set JSON header.
header('Content-Type: application/json');
error_log("After setting JSON header");

$deleted_from_cloudflare = false;
$deleted_from_database = false;

// Delete from Cloudflare if configured.
$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

if (!empty($apitoken) && !empty($accountid) && !empty($videouid)) {
    try {
        $client = new cloudflare_client($apitoken, $accountid);
        $client->delete_video($videouid);
        $deleted_from_cloudflare = true;
        error_log("Cleaned up failed upload from Cloudflare: {$videouid}");
    } catch (Exception $e) {
        // Video might not exist in Cloudflare (already deleted or never created)
        error_log('Failed to delete video from Cloudflare: ' . $e->getMessage());
    }
}

// Delete from database - ALWAYS run this regardless of Cloudflare result
error_log("Attempting database cleanup: submissionid={$submissionid}, videouid={$videouid}");

if ($submissionid > 0 && !empty($videouid)) {
    try {
        // Delete by video_uid
        $count = $DB->count_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
        error_log("Found {$count} database records with video_uid={$videouid}");
        
        if ($count > 0) {
            $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
            error_log("Database deletion result: " . ($deleted_from_database ? 'SUCCESS' : 'FAILED'));
        } else {
            error_log("No database records found for video_uid={$videouid}");
        }
    } catch (Exception $e) {
        error_log("Database deletion exception: " . $e->getMessage());
    }
} else {
    error_log("Invalid parameters: submissionid={$submissionid}, videouid={$videouid}");
}

error_log("Cleanup completed: cloudflare={$deleted_from_cloudflare}, database={$deleted_from_database}");

echo json_encode([
    'success' => true,
    'deleted_from_cloudflare' => $deleted_from_cloudflare,
    'deleted_from_database' => $deleted_from_database
]);
