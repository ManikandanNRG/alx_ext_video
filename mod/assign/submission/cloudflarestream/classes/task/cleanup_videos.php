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
 * Scheduled task for cleaning up expired videos from Cloudflare Stream.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream\task;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\api\cloudflare_api_exception;
use assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception;
use assignsubmission_cloudflarestream\logger;

/**
 * Scheduled task to clean up expired videos.
 *
 * This task runs daily to identify videos that have exceeded the retention period
 * and deletes them from Cloudflare Stream to manage storage costs.
 */
class cleanup_videos extends \core\task\scheduled_task {

    /**
     * Get the name of this task.
     *
     * @return string Task name for display in admin interface
     */
    public function get_name() {
        return get_string('cleanup_videos_task', 'assignsubmission_cloudflarestream');
    }

    /**
     * Execute the cleanup task.
     *
     * Finds videos older than the retention period and deletes them from
     * Cloudflare Stream, updating the database records accordingly.
     * Also syncs database with Cloudflare to detect manually deleted videos.
     */
    public function execute() {
        global $DB;

        // Get plugin configuration.
        $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
        $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
        $retentiondays = get_config('assignsubmission_cloudflarestream', 'retention_days');

        // Validate configuration.
        if (empty($apitoken) || empty($accountid)) {
            mtrace('Cloudflare Stream cleanup: API token or account ID not configured. Skipping cleanup.');
            return;
        }

        if (empty($retentiondays) || $retentiondays <= 0) {
            mtrace('Cloudflare Stream cleanup: Invalid retention period configured. Skipping cleanup.');
            return;
        }

        // Initialize Cloudflare client (used by both cleanup and sync).
        $cloudflare = new cloudflare_client($apitoken, $accountid);

        // Step 1: Clean up stuck/failed uploads (pending/uploading for > 1 hour).
        $this->cleanup_stuck_uploads($cloudflare);

        // Step 2: Sync database with Cloudflare (detect manually deleted videos).
        $this->sync_with_cloudflare($cloudflare);

        // Step 3: Clean up expired videos.
        $this->cleanup_expired_videos($cloudflare, $retentiondays);
    }

    /**
     * Clean up stuck uploads (pending/uploading for more than 30 minutes).
     * These are uploads that failed but JavaScript cleanup didn't run (e.g., browser closed).
     *
     * @param cloudflare_client $cloudflare Cloudflare API client
     */
    private function cleanup_stuck_uploads($cloudflare) {
        global $DB;

        // Find uploads stuck for more than 30 minutes
        $waittime = 1800; // 30 minutes (production setting)
        $cutofftimestamp = time() - $waittime;
        
        mtrace("Cloudflare Stream cleanup: Checking for stuck uploads (pending/uploading > " . ($waittime/60) . " minutes)...");
        mtrace("Current time: " . time() . " (" . date('Y-m-d H:i:s') . ")");
        mtrace("Cutoff time: " . $cutofftimestamp . " (" . date('Y-m-d H:i:s', $cutofftimestamp) . ")");
        
        // Debug: Check all pending/uploading records
        $allpending = $DB->get_records_sql(
            "SELECT id, video_uid, upload_status, upload_timestamp FROM {assignsubmission_cfstream} 
             WHERE upload_status IN ('pending', 'uploading')"
        );
        mtrace("Total pending/uploading records in database: " . count($allpending));
        foreach ($allpending as $record) {
            $age = time() - $record->upload_timestamp;
            mtrace("  - ID {$record->id}: {$record->upload_status}, timestamp {$record->upload_timestamp} (" . 
                   date('Y-m-d H:i:s', $record->upload_timestamp) . "), age: " . round($age/60) . " minutes");
        }

        $sql = "SELECT id, video_uid, assignment, submission, upload_status, upload_timestamp
                FROM {assignsubmission_cfstream}
                WHERE upload_status IN ('pending', 'uploading')
                AND upload_timestamp < ?
                ORDER BY upload_timestamp ASC";

        $stuckuploads = $DB->get_records_sql($sql, [$cutofftimestamp]);
        
        mtrace("SQL query returned " . count($stuckuploads) . " records");

        if (empty($stuckuploads)) {
            mtrace('Cloudflare Stream cleanup: No stuck uploads found.');
            return;
        }

        mtrace('Cloudflare Stream cleanup: Found ' . count($stuckuploads) . ' stuck uploads to clean up.');

        $deletedcount = 0;
        $notfoundcount = 0;
        $failedcount = 0;

        foreach ($stuckuploads as $upload) {
            // Skip if video_uid is empty (very old records)
            if (empty($upload->video_uid)) {
                // Just delete the database record
                $DB->delete_records('assignsubmission_cfstream', ['id' => $upload->id]);
                $deletedcount++;
                mtrace("Cloudflare Stream cleanup: Deleted database record {$upload->id} (empty video_uid)");
                continue;
            }

            try {
                // Try to delete from Cloudflare
                $cloudflare->delete_video($upload->video_uid);
                $deletedcount++;
                mtrace("Cloudflare Stream cleanup: Deleted stuck upload {$upload->video_uid} from Cloudflare");

            } catch (cloudflare_video_not_found_exception $e) {
                // Video doesn't exist in Cloudflare (already deleted or never created)
                $notfoundcount++;
                mtrace("Cloudflare Stream cleanup: Stuck upload {$upload->video_uid} not found in Cloudflare");

            } catch (cloudflare_api_exception $e) {
                // API error - log and continue
                $failedcount++;
                mtrace("Cloudflare Stream cleanup: ERROR - Failed to delete {$upload->video_uid}: " . $e->getMessage());
            }

            // Delete database record regardless of Cloudflare result
            $DB->delete_records('assignsubmission_cfstream', ['id' => $upload->id]);
        }

        mtrace("Cloudflare Stream cleanup: Stuck uploads cleanup completed. " .
               "{$deletedcount} deleted, {$notfoundcount} not found, {$failedcount} failed");
    }

    /**
     * Sync database with Cloudflare to detect manually deleted videos.
     *
     * @param cloudflare_client $cloudflare Cloudflare API client
     */
    private function sync_with_cloudflare($cloudflare) {
        global $DB;

        mtrace('Cloudflare Stream sync: Checking for videos deleted from Cloudflare dashboard...');

        // Get all videos marked as 'ready' in database.
        $readyvideos = $DB->get_records('assignsubmission_cfstream', 
            ['upload_status' => 'ready'], 
            '', 
            'id, video_uid, upload_timestamp'
        );

        if (empty($readyvideos)) {
            mtrace('Cloudflare Stream sync: No ready videos to check.');
            return;
        }

        mtrace('Cloudflare Stream sync: Checking ' . count($readyvideos) . ' videos...');

        $syncedcount = 0;
        $notfoundcount = 0;

        foreach ($readyvideos as $video) {
            try {
                // Check if video still exists in Cloudflare.
                $cloudflare->get_video_details($video->video_uid);
                // Video exists, no action needed.
                $syncedcount++;

            } catch (cloudflare_video_not_found_exception $e) {
                // Video was deleted from Cloudflare, update our database.
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $updaterecord->error_message = 'Video deleted from Cloudflare dashboard';
                $DB->update_record('assignsubmission_cfstream', $updaterecord);

                $notfoundcount++;
                mtrace("Cloudflare Stream sync: Video {$video->video_uid} was deleted from Cloudflare, updated database");

            } catch (cloudflare_api_exception $e) {
                // API error, skip this video and continue.
                mtrace("Cloudflare Stream sync: WARNING - Could not check video {$video->video_uid}: " . $e->getMessage());
            }
        }

        mtrace("Cloudflare Stream sync: Completed. {$syncedcount} videos verified, {$notfoundcount} marked as deleted");
    }

    /**
     * Clean up expired videos based on retention period.
     *
     * @param cloudflare_client $cloudflare Cloudflare API client
     * @param int $retentiondays Retention period in days
     */
    private function cleanup_expired_videos($cloudflare, $retentiondays) {
        global $DB;

        // Calculate cutoff timestamp.
        $cutofftimestamp = time() - ($retentiondays * 86400);
        
        mtrace("Cloudflare Stream cleanup: Starting cleanup of videos older than {$retentiondays} days (before " . 
               date('Y-m-d H:i:s', $cutofftimestamp) . ")");

        // Find videos that need to be deleted.
        $sql = "SELECT id, video_uid, assignment, submission, upload_timestamp
                FROM {assignsubmission_cfstream}
                WHERE upload_status IN ('ready', 'error')
                AND upload_timestamp < ?
                AND deleted_timestamp IS NULL
                ORDER BY upload_timestamp ASC";

        $expiredvideos = $DB->get_records_sql($sql, [$cutofftimestamp]);

        if (empty($expiredvideos)) {
            mtrace('Cloudflare Stream cleanup: No expired videos found.');
            return;
        }

        mtrace('Cloudflare Stream cleanup: Found ' . count($expiredvideos) . ' expired videos to delete.');

        // Track cleanup results.
        $deletedcount = 0;
        $failedcount = 0;
        $notfoundcount = 0;
        $errors = [];

        // Process each expired video.
        foreach ($expiredvideos as $video) {
            try {
                // Attempt to delete video from Cloudflare.
                $cloudflare->delete_video($video->video_uid);
                
                // Update database record.
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $DB->update_record('assignsubmission_cfstream', $updaterecord);

                $deletedcount++;
                mtrace("Cloudflare Stream cleanup: Deleted video {$video->video_uid} (uploaded " . 
                       date('Y-m-d', $video->upload_timestamp) . ")");

            } catch (cloudflare_video_not_found_exception $e) {
                // Video already deleted from Cloudflare, just update our database.
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $updaterecord->error_message = 'Video not found in Cloudflare (already deleted)';
                $DB->update_record('assignsubmission_cfstream', $updaterecord);

                $notfoundcount++;
                mtrace("Cloudflare Stream cleanup: Video {$video->video_uid} not found in Cloudflare (already deleted)");

            } catch (cloudflare_api_exception $e) {
                // API error - log and continue with next video.
                $errormessage = "Failed to delete video {$video->video_uid}: " . $e->getMessage();
                $errors[] = $errormessage;
                $failedcount++;
                
                mtrace("Cloudflare Stream cleanup: ERROR - {$errormessage}");
                
                // Log the error for admin review.
                logger::log_api_error(
                    "/accounts/{$accountid}/stream/{$video->video_uid}",
                    'DELETE',
                    'cleanup_failed',
                    $e->getMessage()
                );

            } catch (\Exception $e) {
                // Unexpected error - log and continue.
                $errormessage = "Unexpected error deleting video {$video->video_uid}: " . $e->getMessage();
                $errors[] = $errormessage;
                $failedcount++;
                
                mtrace("Cloudflare Stream cleanup: ERROR - {$errormessage}");
            }
        }

        // Log cleanup summary.
        $totalprocessed = $deletedcount + $notfoundcount + $failedcount;
        $summary = "Cleanup completed: {$totalprocessed} videos processed, {$deletedcount} deleted, " .
                  "{$notfoundcount} already missing, {$failedcount} failed";
        
        mtrace("Cloudflare Stream cleanup: {$summary}");

        // Log cleanup results to database for dashboard reporting.
        $this->log_cleanup_results($deletedcount, $notfoundcount, $failedcount, $errors);

        // If there were failures, log them for admin attention.
        if ($failedcount > 0) {
            error_log("Cloudflare Stream cleanup had {$failedcount} failures. Check admin dashboard for details.");
        }
    }

    /**
     * Log cleanup results to the database for dashboard reporting.
     *
     * @param int $deletedcount Number of videos successfully deleted
     * @param int $notfoundcount Number of videos not found (already deleted)
     * @param int $failedcount Number of videos that failed to delete
     * @param array $errors Array of error messages
     */
    private function log_cleanup_results($deletedcount, $notfoundcount, $failedcount, $errors) {
        global $DB;

        // Create a summary log entry.
        $record = new \stdClass();
        $record->event_type = 'cleanup_summary';
        $record->error_message = "Deleted: {$deletedcount}, Not found: {$notfoundcount}, Failed: {$failedcount}";
        $record->error_context = json_encode([
            'deleted_count' => $deletedcount,
            'not_found_count' => $notfoundcount,
            'failed_count' => $failedcount,
            'errors' => $errors
        ]);
        $record->timestamp = time();

        $DB->insert_record('assignsubmission_cfstream_log', $record);

        // Log individual failures for detailed tracking.
        foreach ($errors as $error) {
            $errorrecord = new \stdClass();
            $errorrecord->event_type = 'cleanup_failure';
            $errorrecord->error_code = 'cleanup_error';
            $errorrecord->error_message = $error;
            $errorrecord->timestamp = time();

            $DB->insert_record('assignsubmission_cfstream_log', $errorrecord);
        }
    }
}