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

// Get parameters.
$videouid = required_param('videouid', PARAM_ALPHANUMEXT);
$submissionid = required_param('submissionid', PARAM_INT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    $deleted_from_cloudflare = false;
    $deleted_from_database = false;
    
    // Delete from Cloudflare if configured.
    if (!empty($apitoken) && !empty($accountid) && !empty($videouid)) {
        try {
            $client = new cloudflare_client($apitoken, $accountid);
            $client->delete_video($videouid);
            $deleted_from_cloudflare = true;
            
            // Log the cleanup.
            logger::log_video_deleted(
                $USER->id,
                $videouid,
                'cleanup_failed_upload',
                'Video deleted due to failed upload'
            );
            
            error_log("Cleaned up failed upload from Cloudflare: {$videouid}");
        } catch (Exception $e) {
            // Video might not exist in Cloudflare (already deleted or never created)
            // This is OK - continue with database cleanup
            error_log('Failed to delete video from Cloudflare (might not exist): ' . $e->getMessage());
        }
    }
    
    // Delete from database.
    if ($submissionid > 0) {
        // Try to delete by submission and video_uid
        $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', [
            'submission' => $submissionid,
            'video_uid' => $videouid
        ]);
        
        // If not found, try deleting by submission and empty video_uid (old records)
        if (!$deleted_from_database && !empty($videouid)) {
            $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', [
                'submission' => $submissionid,
                'video_uid' => ''
            ]);
            
            if ($deleted_from_database) {
                error_log("Cleaned up failed upload from database (empty video_uid): submission={$submissionid}");
            }
        }
        
        if ($deleted_from_database) {
            error_log("Cleaned up failed upload from database: submission={$submissionid}, video_uid={$videouid}");
        }
    }
    
    echo json_encode([
        'success' => true,
        'deleted_from_cloudflare' => $deleted_from_cloudflare,
        'deleted_from_database' => $deleted_from_database,
        'message' => 'Cleanup completed'
    ]);
    
} catch (Exception $e) {
    // Log error but return success (cleanup is best-effort)
    error_log('Cleanup failed upload error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => true, // Return success even on error
        'error' => $e->getMessage(),
        'message' => 'Cleanup attempted (errors logged)'
    ]);
}
