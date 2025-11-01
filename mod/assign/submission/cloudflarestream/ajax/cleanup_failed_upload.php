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
    if ($submissionid > 0 && !empty($videouid)) {
        // Try to delete by video_uid first (most reliable)
        $count = $DB->count_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
        error_log("Cleanup: Found {$count} database records with video_uid={$videouid}");
        
        if ($count > 0) {
            $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', ['video_uid' => $videouid]);
            if ($deleted_from_database) {
                error_log("Cleaned up failed upload from database: video_uid={$videouid}, records_deleted={$count}");
            } else {
                error_log("WARNING: Failed to delete database records for video_uid={$videouid}");
            }
        } else {
            // Try by submission if video_uid not found
            $count2 = $DB->count_records('assignsubmission_cfstream', ['submission' => $submissionid]);
            error_log("Cleanup: Found {$count2} database records with submission={$submissionid}");
            
            if ($count2 > 0) {
                $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', ['submission' => $submissionid]);
                if ($deleted_from_database) {
                    error_log("Cleaned up failed upload from database: submission={$submissionid}, records_deleted={$count2}");
                } else {
                    error_log("WARNING: Failed to delete database records for submission={$submissionid}");
                }
            } else {
                error_log("Cleanup: No database records found for video_uid={$videouid} or submission={$submissionid}");
            }
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
