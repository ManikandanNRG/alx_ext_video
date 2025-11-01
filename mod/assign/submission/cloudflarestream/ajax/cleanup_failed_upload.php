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

// Validate parameters
if (empty($videouid)) {
    echo json_encode(['success' => false, 'error' => 'Missing videouid']);
    exit;
}

// Require login - but DON'T require sesskey for sendBeacon requests
require_login();

// Set JSON header.
header('Content-Type: application/json');

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
    } catch (Exception $e) {
        // Video might not exist in Cloudflare (already deleted or never created)
        // This is OK - continue with database cleanup
    }
}

// Delete from database - ALWAYS run this regardless of Cloudflare result
if ($submissionid > 0 && !empty($videouid)) {
    try {
        $DB->delete_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
        $deleted_from_database = true;
    } catch (Exception $e) {
        // Log error but continue
        error_log("Database cleanup error for {$videouid}: " . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'deleted_from_cloudflare' => $deleted_from_cloudflare,
    'deleted_from_database' => $deleted_from_database
]);
