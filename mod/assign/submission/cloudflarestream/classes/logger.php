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
 * Logger for Cloudflare Stream plugin events.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream;

defined('MOODLE_INTERNAL') || die();

/**
 * Logger class for tracking upload events and errors.
 */
class logger {
    
    /**
     * Log an upload success event.
     *
     * @param int $userid User ID who uploaded the video
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $videouid Cloudflare video UID
     * @param int $filesize File size in bytes
     * @param int $duration Video duration in seconds
     */
    public static function log_upload_success($userid, $assignmentid, $submissionid, $videouid, $filesize = null, $duration = null) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->video_uid = $videouid;
        $record->event_type = 'upload_success';
        $record->file_size = $filesize;
        $record->duration = $duration;
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
        
        // Also log to standard error log for debugging.
        error_log(sprintf(
            'Cloudflare Stream: Upload success - User: %d, Assignment: %d, Video UID: %s, Size: %d bytes',
            $userid, $assignmentid, $videouid, $filesize ?? 0
        ));
    }
    
    /**
     * Log an upload failure event.
     *
     * @param int $userid User ID who attempted the upload
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID (if available)
     * @param string $errorcode Error code
     * @param string $errormessage Detailed error message
     * @param string $errorcontext Additional context (e.g., API response)
     */
    public static function log_upload_failure($userid, $assignmentid, $submissionid, $errorcode, $errormessage, $errorcontext = null) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->event_type = 'upload_failure';
        $record->error_code = $errorcode;
        $record->error_message = $errormessage;
        $record->error_context = $errorcontext;
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
        
        // Also log to standard error log for debugging.
        error_log(sprintf(
            'Cloudflare Stream: Upload failure - User: %d, Assignment: %d, Error: %s - %s',
            $userid, $assignmentid, $errorcode, $errormessage
        ));
    }
    
    /**
     * Log an upload retry event.
     *
     * @param int $userid User ID who is retrying
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $videouid Cloudflare video UID (if available)
     * @param int $retrycount Number of retries attempted
     */
    public static function log_upload_retry($userid, $assignmentid, $submissionid, $videouid = null, $retrycount = 1) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->video_uid = $videouid;
        $record->event_type = 'upload_retry';
        $record->retry_count = $retrycount;
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
    }
    
    /**
     * Log a playback access event.
     *
     * @param int $userid User ID who accessed the video
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $videouid Cloudflare video UID
     * @param string $userrole User's role (student, teacher, admin)
     */
    public static function log_playback_access($userid, $assignmentid, $submissionid, $videouid, $userrole = 'unknown') {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->video_uid = $videouid;
        $record->event_type = 'playback_access';
        $record->user_role = $userrole;
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
    }
    
    /**
     * Log a playback failure event.
     *
     * @param int $userid User ID who attempted playback
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $videouid Cloudflare video UID
     * @param string $errorcode Error code
     * @param string $errormessage Detailed error message
     * @param string $errorcontext Additional context (e.g., API response)
     */
    public static function log_playback_failure($userid, $assignmentid, $submissionid, $videouid, $errorcode, $errormessage, $errorcontext = null) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->video_uid = $videouid;
        $record->event_type = 'playback_failure';
        $record->error_code = $errorcode;
        $record->error_message = $errormessage;
        $record->error_context = $errorcontext;
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
        
        // Also log to standard error log for debugging.
        error_log(sprintf(
            'Cloudflare Stream: Playback failure - User: %d, Assignment: %d, Video: %s, Error: %s - %s',
            $userid, $assignmentid, $videouid, $errorcode, $errormessage
        ));
    }
    
    /**
     * Log a video deletion event.
     *
     * @param string $videouid Cloudflare video UID that was deleted
     * @param int $userid User ID who performed the deletion (can be null for privacy deletions)
     * @param int $assignmentid Assignment ID (if available)
     * @param int $submissionid Submission ID (if available)
     * @param string $deletiontype Type of deletion ('manual', 'automatic', 'cleanup', 'privacy_deletion')
     */
    public static function log_video_deletion($videouid, $userid, $assignmentid = null, $submissionid = null, $deletiontype = 'manual') {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->video_uid = $videouid;
        $record->event_type = 'video_deletion';
        $record->error_context = json_encode(['deletion_type' => $deletiontype]);
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
        
        // Also log to standard error log for debugging.
        error_log(sprintf(
            'Cloudflare Stream: Video deletion - Video UID: %s, User: %d, Type: %s',
            $videouid, $userid, $deletiontype
        ));
    }
    
    /**
     * Log an API error event.
     *
     * @param string $endpoint API endpoint that failed
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $errorcode Error code
     * @param string $errormessage Detailed error message
     * @param int $httpcode HTTP status code
     */
    public static function log_api_error($endpoint, $method, $errorcode, $errormessage, $httpcode = null) {
        global $DB;
        
        $record = new \stdClass();
        $record->event_type = 'api_error';
        $record->error_code = $errorcode;
        $record->error_message = $errormessage;
        $record->error_context = json_encode([
            'endpoint' => $endpoint,
            'method' => $method,
            'http_code' => $httpcode
        ]);
        $record->timestamp = time();
        
        $DB->insert_record('assignsubmission_cfs_log', $record);
        
        // Also log to standard error log.
        error_log(sprintf(
            'Cloudflare Stream API Error: %s %s - HTTP %d - %s: %s',
            $method, $endpoint, $httpcode ?? 0, $errorcode, $errormessage
        ));
    }
    
    /**
     * Get upload statistics for the dashboard.
     *
     * @param int $days Number of days to look back (default: 30)
     * @return object Statistics object with success/failure counts and rates
     */
    public static function get_upload_statistics($days = 30) {
        global $DB;
        
        $since = time() - ($days * 86400);
        
        // Get total uploads (success + failure).
        $totaluploads = $DB->count_records_select(
            'assignsubmission_cfs_log',
            "event_type IN ('upload_success', 'upload_failure') AND timestamp >= ?",
            [$since]
        );
        
        // Get successful uploads.
        $successfuluploads = $DB->count_records_select(
            'assignsubmission_cfs_log',
            "event_type = 'upload_success' AND timestamp >= ?",
            [$since]
        );
        
        // Get failed uploads.
        $faileduploads = $DB->count_records_select(
            'assignsubmission_cfs_log',
            "event_type = 'upload_failure' AND timestamp >= ?",
            [$since]
        );
        
        // Calculate success rate.
        $successrate = $totaluploads > 0 ? ($successfuluploads / $totaluploads) * 100 : 0;
        
        // Get total storage used (sum of file sizes).
        $sql = "SELECT SUM(file_size) as total_size
                FROM {assignsubmission_cfs_log}
                WHERE event_type = 'upload_success' 
                AND file_size IS NOT NULL
                AND timestamp >= ?";
        $result = $DB->get_record_sql($sql, [$since]);
        $totalstorage = $result->total_size ?? 0;
        
        // Get total video duration.
        $sql = "SELECT SUM(duration) as total_duration
                FROM {assignsubmission_cfs_log}
                WHERE event_type = 'upload_success' 
                AND duration IS NOT NULL
                AND timestamp >= ?";
        $result = $DB->get_record_sql($sql, [$since]);
        $totalduration = $result->total_duration ?? 0;
        
        return (object)[
            'total_uploads' => $totaluploads,
            'successful_uploads' => $successfuluploads,
            'failed_uploads' => $faileduploads,
            'success_rate' => round($successrate, 2),
            'total_storage_bytes' => $totalstorage,
            'total_duration_seconds' => $totalduration,
            'period_days' => $days
        ];
    }
    
    /**
     * Get recent upload failures for troubleshooting.
     *
     * @param int $limit Maximum number of failures to return (default: 20)
     * @return array Array of failure records
     */
    public static function get_recent_failures($limit = 20) {
        global $DB;
        
        $sql = "SELECT l.*, u.firstname, u.lastname, u.email, a.name as assignmentname
                FROM {assignsubmission_cfs_log} l
                LEFT JOIN {user} u ON l.userid = u.id
                LEFT JOIN {assign} a ON l.assignmentid = a.id
                WHERE l.event_type = 'upload_failure'
                ORDER BY l.timestamp DESC";
        
        return $DB->get_records_sql($sql, [], 0, $limit);
    }
    
    /**
     * Get error breakdown by error code.
     *
     * @param int $days Number of days to look back (default: 30)
     * @return array Array of error codes with counts
     */
    public static function get_error_breakdown($days = 30) {
        global $DB;
        
        $since = time() - ($days * 86400);
        
        $sql = "SELECT error_code, COUNT(*) as count
                FROM {assignsubmission_cfs_log}
                WHERE event_type = 'upload_failure'
                AND timestamp >= ?
                GROUP BY error_code
                ORDER BY count DESC";
        
        return $DB->get_records_sql($sql, [$since]);
    }
}
