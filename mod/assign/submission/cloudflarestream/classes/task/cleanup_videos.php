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

        // Initialize Cloudflare client.
        $cloudflare = new cloudflare_client($apitoken, $accountid);

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