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
 * Scheduled task for cleaning up expired videos from AWS S3.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video\task;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\cloudfront_client;
use assignsubmission_s3video\api\s3_api_exception;
use assignsubmission_s3video\api\s3_object_not_found_exception;
use assignsubmission_s3video\api\cloudfront_api_exception;
use assignsubmission_s3video\logger;

/**
 * Scheduled task to clean up expired videos.
 *
 * This task runs daily to identify videos that have exceeded the retention period
 * and deletes them from AWS S3 and invalidates CloudFront cache to manage storage costs.
 */
class cleanup_videos extends \core\task\scheduled_task {

    /**
     * Get the name of this task.
     *
     * @return string Task name for display in admin interface
     */
    public function get_name() {
        return get_string('cleanup_videos_task', 'assignsubmission_s3video');
    }

    /**
     * Execute the cleanup task.
     *
     * Finds videos older than the retention period and deletes them from
     * AWS S3, invalidates CloudFront cache, and updates database records.
     */
    public function execute() {
        global $DB;

        // Get plugin configuration.
        $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
        $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
        $bucket = get_config('assignsubmission_s3video', 's3_bucket');
        $region = get_config('assignsubmission_s3video', 'aws_region');
        $cfdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
        $cfkeypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
        $cfprivatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');
        $retentiondays = get_config('assignsubmission_s3video', 'retention_days');

        // Validate configuration.
        if (empty($accesskey) || empty($secretkey) || empty($bucket)) {
            mtrace('S3 Video cleanup: AWS credentials or bucket not configured. Skipping cleanup.');
            return;
        }

        if (empty($retentiondays) || $retentiondays <= 0) {
            mtrace('S3 Video cleanup: Invalid retention period configured. Skipping cleanup.');
            return;
        }

        // Calculate cutoff timestamp.
        $cutofftimestamp = time() - ($retentiondays * 86400);
        
        mtrace("S3 Video cleanup: Starting cleanup of videos older than {$retentiondays} days (before " . 
               date('Y-m-d H:i:s', $cutofftimestamp) . ")");

        // Find videos that need to be deleted.
        $sql = "SELECT id, s3_key, s3_bucket, assignment, submission, upload_timestamp, file_size
                FROM {assignsubmission_s3video}
                WHERE upload_status IN ('ready', 'error')
                AND upload_timestamp < ?
                AND deleted_timestamp IS NULL
                ORDER BY upload_timestamp ASC";

        $expiredvideos = $DB->get_records_sql($sql, [$cutofftimestamp]);

        if (empty($expiredvideos)) {
            mtrace('S3 Video cleanup: No expired videos found.');
            return;
        }

        mtrace('S3 Video cleanup: Found ' . count($expiredvideos) . ' expired videos to delete.');

        // Initialize S3 client.
        try {
            $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
        } catch (\Exception $e) {
            mtrace('S3 Video cleanup: Failed to initialize S3 client: ' . $e->getMessage());
            return;
        }

        // Initialize CloudFront client (if configured).
        $cfclient = null;
        if (!empty($cfdomain) && !empty($cfkeypairid) && !empty($cfprivatekey)) {
            try {
                $cfclient = new cloudfront_client(
                    $cfdomain,
                    $cfkeypairid,
                    $cfprivatekey,
                    $accesskey,
                    $secretkey,
                    $region
                );
            } catch (\Exception $e) {
                mtrace('S3 Video cleanup: Failed to initialize CloudFront client: ' . $e->getMessage());
                mtrace('S3 Video cleanup: Continuing without CloudFront invalidation.');
            }
        }

        // Track cleanup results.
        $deletedcount = 0;
        $failedcount = 0;
        $notfoundcount = 0;
        $invalidatedcount = 0;
        $errors = [];
        $totalbytesfreed = 0;

        // Process each expired video.
        foreach ($expiredvideos as $video) {
            try {
                // Attempt to delete video from S3.
                $s3client->delete_object($video->s3_key);
                
                // Track bytes freed.
                if (!empty($video->file_size)) {
                    $totalbytesfreed += $video->file_size;
                }

                // Invalidate CloudFront cache if client is available.
                if ($cfclient !== null) {
                    try {
                        $cfclient->create_invalidation($video->s3_key);
                        $invalidatedcount++;
                        mtrace("S3 Video cleanup: Invalidated CloudFront cache for {$video->s3_key}");
                    } catch (cloudfront_api_exception $e) {
                        // Log but don't fail the cleanup if invalidation fails.
                        mtrace("S3 Video cleanup: WARNING - Failed to invalidate CloudFront cache: " . $e->getMessage());
                    }
                }
                
                // Update database record.
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $DB->update_record('assignsubmission_s3video', $updaterecord);

                $deletedcount++;
                $sizeMB = !empty($video->file_size) ? round($video->file_size / (1024 * 1024), 2) : 0;
                mtrace("S3 Video cleanup: Deleted video {$video->s3_key} ({$sizeMB} MB, uploaded " . 
                       date('Y-m-d', $video->upload_timestamp) . ")");

            } catch (s3_object_not_found_exception $e) {
                // Video already deleted from S3, just update our database.
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $updaterecord->error_message = 'Video not found in S3 (already deleted)';
                $DB->update_record('assignsubmission_s3video', $updaterecord);

                $notfoundcount++;
                mtrace("S3 Video cleanup: Video {$video->s3_key} not found in S3 (already deleted)");

            } catch (s3_api_exception $e) {
                // API error - log and continue with next video.
                $errormessage = "Failed to delete video {$video->s3_key}: " . $e->getMessage();
                $errors[] = $errormessage;
                $failedcount++;
                
                mtrace("S3 Video cleanup: ERROR - {$errormessage}");
                
                // Log the error for admin review.
                logger::log_event(
                    0, // System task, no specific user.
                    $video->assignment,
                    $video->submission,
                    $video->s3_key,
                    'cleanup_failed',
                    [
                        'error_code' => 's3_delete_failed',
                        'error_message' => $e->getMessage(),
                    ]
                );

            } catch (\Exception $e) {
                // Unexpected error - log and continue.
                $errormessage = "Unexpected error deleting video {$video->s3_key}: " . $e->getMessage();
                $errors[] = $errormessage;
                $failedcount++;
                
                mtrace("S3 Video cleanup: ERROR - {$errormessage}");
            }
        }

        // Log cleanup summary.
        $totalprocessed = $deletedcount + $notfoundcount + $failedcount;
        $freedGB = round($totalbytesfreed / (1024 * 1024 * 1024), 2);
        $summary = "Cleanup completed: {$totalprocessed} videos processed, {$deletedcount} deleted, " .
                  "{$notfoundcount} already missing, {$failedcount} failed, {$freedGB} GB freed";
        
        if ($cfclient !== null) {
            $summary .= ", {$invalidatedcount} CloudFront invalidations created";
        }
        
        mtrace("S3 Video cleanup: {$summary}");

        // Log cleanup results to database for dashboard reporting.
        $this->log_cleanup_results($deletedcount, $notfoundcount, $failedcount, $invalidatedcount, 
                                   $totalbytesfreed, $errors);

        // If there were failures, log them for admin attention.
        if ($failedcount > 0) {
            error_log("S3 Video cleanup had {$failedcount} failures. Check admin dashboard for details.");
        }
    }

    /**
     * Log cleanup results to the database for dashboard reporting.
     *
     * @param int $deletedcount Number of videos successfully deleted
     * @param int $notfoundcount Number of videos not found (already deleted)
     * @param int $failedcount Number of videos that failed to delete
     * @param int $invalidatedcount Number of CloudFront invalidations created
     * @param int $bytesfreed Total bytes freed from storage
     * @param array $errors Array of error messages
     */
    private function log_cleanup_results($deletedcount, $notfoundcount, $failedcount, 
                                        $invalidatedcount, $bytesfreed, $errors) {
        global $DB;

        // Create a summary log entry.
        $record = new \stdClass();
        $record->userid = null;
        $record->assignmentid = null;
        $record->submissionid = null;
        $record->s3_key = null;
        $record->event_type = 'cleanup_summary';
        $record->error_message = "Deleted: {$deletedcount}, Not found: {$notfoundcount}, " .
                                "Failed: {$failedcount}, Invalidated: {$invalidatedcount}";
        $record->error_context = json_encode([
            'deleted_count' => $deletedcount,
            'not_found_count' => $notfoundcount,
            'failed_count' => $failedcount,
            'invalidated_count' => $invalidatedcount,
            'bytes_freed' => $bytesfreed,
            'errors' => $errors
        ]);
        $record->timestamp = time();

        $DB->insert_record('assignsubmission_s3v_log', $record);

        // Log individual failures for detailed tracking.
        foreach ($errors as $error) {
            $errorrecord = new \stdClass();
            $errorrecord->userid = null;
            $errorrecord->assignmentid = null;
            $errorrecord->submissionid = null;
            $errorrecord->s3_key = null;
            $errorrecord->event_type = 'cleanup_failure';
            $errorrecord->error_code = 'cleanup_error';
            $errorrecord->error_message = $error;
            $errorrecord->timestamp = time();

            $DB->insert_record('assignsubmission_s3v_log', $errorrecord);
        }
    }
}
