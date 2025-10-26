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
 * Logger class for S3 video plugin events.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

defined('MOODLE_INTERNAL') || die();

/**
 * Logger class for tracking upload events, errors, and statistics.
 */
class logger {
    
    /**
     * Log an upload event.
     *
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $s3key S3 key
     * @param string $eventtype Event type (e.g., 'upload_requested', 'upload_completed')
     * @param array $options Optional parameters (file_size, duration, error_message, etc.)
     * @return bool Success status
     */
    public static function log_event($userid, $assignmentid, $submissionid, $s3key, $eventtype, $options = []) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->assignmentid = $assignmentid;
        $record->submissionid = $submissionid;
        $record->s3_key = $s3key;
        $record->event_type = $eventtype;
        $record->timestamp = time();
        
        // Add optional fields.
        if (isset($options['file_size'])) {
            $record->file_size = $options['file_size'];
        }
        if (isset($options['duration'])) {
            $record->duration = $options['duration'];
        }
        if (isset($options['error_code'])) {
            $record->error_code = $options['error_code'];
        }
        if (isset($options['error_message'])) {
            $record->error_message = $options['error_message'];
        }
        if (isset($options['error_context'])) {
            $record->error_context = $options['error_context'];
        }
        if (isset($options['retry_count'])) {
            $record->retry_count = $options['retry_count'];
        }
        if (isset($options['user_role'])) {
            $record->user_role = $options['user_role'];
        }
        
        try {
            return $DB->insert_record('assignsubmission_s3v_log', $record) > 0;
        } catch (\Exception $e) {
            // Log to Moodle error log if database insert fails.
            debugging('Failed to log S3 video event: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
    
    /**
     * Log an upload failure.
     *
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID
     * @param int|null $submissionid Submission ID (may be null if submission not created)
     * @param string|null $s3key S3 key (may be null if not generated)
     * @param string $errormessage Error message
     * @param string|null $errorcode Error code
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function log_upload_failure($userid, $assignmentid, $submissionid, $s3key, 
                                              $errormessage, $errorcode = null, $context = []) {
        return self::log_event($userid, $assignmentid, $submissionid, $s3key, 'upload_failed', [
            'error_message' => $errormessage,
            'error_code' => $errorcode,
            'error_context' => json_encode($context),
        ]);
    }
    
    /**
     * Log a playback access event.
     *
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID
     * @param int $submissionid Submission ID
     * @param string $s3key S3 key
     * @param string $userrole User role (student, teacher, admin)
     * @return bool Success status
     */
    public static function log_playback_access($userid, $assignmentid, $submissionid, $s3key, $userrole) {
        return self::log_event($userid, $assignmentid, $submissionid, $s3key, 'playback_accessed', [
            'user_role' => $userrole,
        ]);
    }
    
    /**
     * Log an API error.
     *
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID
     * @param int|null $submissionid Submission ID
     * @param string|null $s3key S3 key
     * @param string $errortype Error type (e.g., 's3_error', 'cloudfront_error')
     * @param string $errormessage Error message
     * @param string|null $errorcode Error code
     * @return bool Success status
     */
    public static function log_api_error($userid, $assignmentid, $submissionid, $s3key, 
                                        $errortype, $errormessage, $errorcode = null) {
        return self::log_event($userid, $assignmentid, $submissionid, $s3key, $errortype, [
            'error_message' => $errormessage,
            'error_code' => $errorcode,
        ]);
    }
    
    /**
     * Get upload statistics for a date range.
     *
     * @param int $starttime Start timestamp
     * @param int $endtime End timestamp
     * @return object Statistics object
     */
    public static function get_upload_statistics($starttime, $endtime) {
        global $DB;
        
        $stats = new \stdClass();
        
        // Total uploads requested.
        $stats->total_requested = $DB->count_records_select(
            'assignsubmission_s3v_log',
            'event_type = ? AND timestamp >= ? AND timestamp <= ?',
            ['upload_requested', $starttime, $endtime]
        );
        
        // Total uploads completed.
        $stats->total_completed = $DB->count_records_select(
            'assignsubmission_s3v_log',
            'event_type = ? AND timestamp >= ? AND timestamp <= ?',
            ['upload_completed', $starttime, $endtime]
        );
        
        // Total uploads failed.
        $stats->total_failed = $DB->count_records_select(
            'assignsubmission_s3v_log',
            'event_type IN (?, ?) AND timestamp >= ? AND timestamp <= ?',
            ['upload_failed', 'upload_confirmation_failed', $starttime, $endtime]
        );
        
        // Success rate.
        $stats->success_rate = $stats->total_requested > 0 
            ? round(($stats->total_completed / $stats->total_requested) * 100, 2) 
            : 0;
        
        // Total storage used (sum of file sizes).
        $sql = "SELECT SUM(file_size) as total_size
                FROM {assignsubmission_s3v_log}
                WHERE event_type = ? AND timestamp >= ? AND timestamp <= ?";
        $result = $DB->get_record_sql($sql, ['upload_completed', $starttime, $endtime]);
        $stats->total_storage_bytes = $result->total_size ?? 0;
        
        // Average file size.
        $stats->average_file_size = $stats->total_completed > 0 
            ? round($stats->total_storage_bytes / $stats->total_completed) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Get recent upload failures.
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of failure records
     */
    public static function get_recent_failures($limit = 50, $offset = 0) {
        global $DB;
        
        $sql = "SELECT l.*, u.firstname, u.lastname, u.email, a.name as assignmentname
                FROM {assignsubmission_s3v_log} l
                LEFT JOIN {user} u ON l.userid = u.id
                LEFT JOIN {assign} a ON l.assignmentid = a.id
                WHERE l.event_type IN (?, ?, ?)
                ORDER BY l.timestamp DESC";
        
        return $DB->get_records_sql($sql, 
            ['upload_failed', 'upload_confirmation_failed', 's3_error'], 
            $offset, $limit);
    }
    
    /**
     * Get playback statistics.
     *
     * @param int $starttime Start timestamp
     * @param int $endtime End timestamp
     * @return object Statistics object
     */
    public static function get_playback_statistics($starttime, $endtime) {
        global $DB;
        
        $stats = new \stdClass();
        
        // Total playback accesses.
        $stats->total_views = $DB->count_records_select(
            'assignsubmission_s3v_log',
            'event_type = ? AND timestamp >= ? AND timestamp <= ?',
            ['playback_accessed', $starttime, $endtime]
        );
        
        // Views by role.
        $sql = "SELECT user_role, COUNT(*) as count
                FROM {assignsubmission_s3v_log}
                WHERE event_type = ? AND timestamp >= ? AND timestamp <= ?
                GROUP BY user_role";
        $roleviews = $DB->get_records_sql($sql, ['playback_accessed', $starttime, $endtime]);
        
        $stats->views_by_role = [];
        foreach ($roleviews as $rv) {
            $stats->views_by_role[$rv->user_role] = $rv->count;
        }
        
        return $stats;
    }
    
    /**
     * Get current storage usage.
     *
     * @return object Storage statistics
     */
    public static function get_storage_usage() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Total videos currently stored.
        $stats->total_videos = $DB->count_records_select(
            'assignsubmission_s3video',
            'upload_status = ?',
            ['ready']
        );
        
        // Total storage used.
        $sql = "SELECT SUM(file_size) as total_size
                FROM {assignsubmission_s3video}
                WHERE upload_status = ?";
        $result = $DB->get_record_sql($sql, ['ready']);
        $stats->total_storage_bytes = $result->total_size ?? 0;
        
        // Average file size.
        $stats->average_file_size = $stats->total_videos > 0 
            ? round($stats->total_storage_bytes / $stats->total_videos) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Estimate AWS costs based on usage.
     *
     * @param int $starttime Start timestamp
     * @param int $endtime End timestamp
     * @return object Cost estimates
     */
    public static function estimate_costs($starttime, $endtime) {
        global $DB;
        
        $costs = new \stdClass();
        
        // Get storage usage.
        $storageusage = self::get_storage_usage();
        $storagegb = $storageusage->total_storage_bytes / (1024 * 1024 * 1024);
        
        // S3 storage cost: $0.023 per GB/month.
        $costs->storage_monthly = round($storagegb * 0.023, 2);
        
        // Get total data transferred (playback views).
        $sql = "SELECT SUM(v.file_size) as total_transfer
                FROM {assignsubmission_s3v_log} l
                JOIN {assignsubmission_s3video} v ON l.s3_key = v.s3_key
                WHERE l.event_type = ? AND l.timestamp >= ? AND l.timestamp <= ?";
        $result = $DB->get_record_sql($sql, ['playback_accessed', $starttime, $endtime]);
        $transferbytes = $result->total_transfer ?? 0;
        $transfergb = $transferbytes / (1024 * 1024 * 1024);
        
        // CloudFront transfer cost: $0.085 per GB.
        $costs->transfer = round($transfergb * 0.085, 2);
        
        // Total estimated cost.
        $costs->total = $costs->storage_monthly + $costs->transfer;
        
        // Add context.
        $costs->storage_gb = round($storagegb, 2);
        $costs->transfer_gb = round($transfergb, 2);
        
        return $costs;
    }
}
