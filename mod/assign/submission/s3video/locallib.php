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
 * Local library functions for S3 video submission plugin.
 *
 * @package   assignsubmission_s3video
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include the main plugin class to ensure it's always available
require_once($CFG->dirroot . '/mod/assign/submission/s3video/lib.php');

/**
 * Plugin detection and utility class for S3 video submission.
 *
 * This class provides utility functions for detecting and managing the S3 video
 * submission plugin within the Moodle assignment module.
 *
 * @package   assignsubmission_s3video
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_s3video_plugin_detector {

    /**
     * Check if the S3 video plugin is installed.
     *
     * @return bool True if plugin is installed
     */
    public static function is_installed() {
        global $DB;
        
        try {
            // Check if the plugin tables exist.
            $dbman = $DB->get_manager();
            $table = new xmldb_table('assignsubmission_s3video');
            return $dbman->table_exists($table);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if the S3 video plugin is enabled globally.
     *
     * @return bool True if plugin is enabled
     */
    public static function is_enabled_globally() {
        $enabled = get_config('assignsubmission_s3video', 'disabled');
        // If disabled is not set or is 0, plugin is enabled.
        return empty($enabled);
    }

    /**
     * Check if the S3 video plugin is properly configured.
     *
     * @return bool True if plugin has all required AWS settings
     */
    public static function is_configured() {
        $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
        $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
        $bucket = get_config('assignsubmission_s3video', 's3_bucket');
        $region = get_config('assignsubmission_s3video', 's3_region');
        $cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');

        return !empty($accesskey) && !empty($secretkey) && !empty($bucket) && 
               !empty($region) && !empty($cloudfrontdomain);
    }

    /**
     * Check if the S3 video plugin is enabled for a specific assignment.
     *
     * @param int $assignmentid The assignment ID
     * @return bool True if plugin is enabled for this assignment
     */
    public static function is_enabled_for_assignment($assignmentid) {
        global $DB;

        try {
            $enabled = $DB->get_field('assign_plugin_config', 'value',
                array(
                    'assignment' => $assignmentid,
                    'plugin' => 's3video',
                    'subtype' => 'assignsubmission',
                    'name' => 'enabled'
                )
            );
            
            return !empty($enabled);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the plugin version information.
     *
     * @return stdClass|null Plugin version object or null if not found
     */
    public static function get_plugin_version() {
        $pluginman = core_plugin_manager::instance();
        $plugin = $pluginman->get_plugin_info('assignsubmission_s3video');
        
        return $plugin;
    }

    /**
     * Get configuration status for admin dashboard.
     *
     * @return array Configuration status details
     */
    public static function get_config_status() {
        $status = array(
            'installed' => self::is_installed(),
            'enabled' => self::is_enabled_globally(),
            'configured' => self::is_configured(),
            'aws_access_key' => !empty(get_config('assignsubmission_s3video', 'aws_access_key')),
            'aws_secret_key' => !empty(get_config('assignsubmission_s3video', 'aws_secret_key')),
            's3_bucket' => !empty(get_config('assignsubmission_s3video', 's3_bucket')),
            's3_region' => !empty(get_config('assignsubmission_s3video', 's3_region')),
            'cloudfront_domain' => !empty(get_config('assignsubmission_s3video', 'cloudfront_domain')),
            'cloudfront_keypair_id' => !empty(get_config('assignsubmission_s3video', 'cloudfront_keypair_id')),
            'cloudfront_private_key' => !empty(get_config('assignsubmission_s3video', 'cloudfront_private_key'))
        );

        return $status;
    }

    /**
     * Get list of assignments using the S3 video plugin.
     *
     * @return array Array of assignment objects
     */
    public static function get_assignments_using_plugin() {
        global $DB;

        $sql = "SELECT DISTINCT a.id, a.name, c.fullname as coursename
                FROM {assign} a
                JOIN {course} c ON a.course = c.id
                JOIN {assign_plugin_config} apc ON apc.assignment = a.id
                WHERE apc.plugin = 's3video'
                  AND apc.subtype = 'assignsubmission'
                  AND apc.name = 'enabled'
                  AND apc.value = '1'
                ORDER BY c.fullname, a.name";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get statistics about S3 video usage.
     *
     * @return array Usage statistics
     */
    public static function get_usage_statistics() {
        global $DB;

        $stats = array();

        try {
            // Total videos uploaded.
            $stats['total_videos'] = $DB->count_records('assignsubmission_s3video');

            // Videos by status.
            $stats['ready'] = $DB->count_records('assignsubmission_s3video', 
                array('upload_status' => 'ready'));
            $stats['pending'] = $DB->count_records('assignsubmission_s3video', 
                array('upload_status' => 'pending'));
            $stats['error'] = $DB->count_records('assignsubmission_s3video', 
                array('upload_status' => 'error'));
            $stats['deleted'] = $DB->count_records('assignsubmission_s3video', 
                array('upload_status' => 'deleted'));

            // Total storage used (in bytes).
            $result = $DB->get_record_sql(
                "SELECT SUM(file_size) as total_size 
                 FROM {assignsubmission_s3video} 
                 WHERE upload_status = 'ready' AND file_size IS NOT NULL"
            );
            $stats['total_storage_bytes'] = $result ? (int)$result->total_size : 0;

            // Total video duration (in seconds).
            $result = $DB->get_record_sql(
                "SELECT SUM(duration) as total_duration 
                 FROM {assignsubmission_s3video} 
                 WHERE upload_status = 'ready' AND duration IS NOT NULL"
            );
            $stats['total_duration_seconds'] = $result ? (int)$result->total_duration : 0;

            // Recent uploads (last 7 days).
            $weekago = time() - (7 * 24 * 60 * 60);
            $stats['recent_uploads'] = $DB->count_records_select('assignsubmission_s3video',
                'upload_timestamp > ?', array($weekago));

        } catch (Exception $e) {
            // Return empty stats if tables don't exist yet.
            $stats = array(
                'total_videos' => 0,
                'ready' => 0,
                'pending' => 0,
                'error' => 0,
                'deleted' => 0,
                'total_storage_bytes' => 0,
                'total_duration_seconds' => 0,
                'recent_uploads' => 0
            );
        }

        return $stats;
    }
}

/**
 * Verify if a user has access to view a specific video.
 *
 * This function implements access control logic for video playback:
 * - Students can only view their own submissions
 * - Teachers can view submissions in courses they teach
 * - Admins can view all submissions
 *
 * @param int $submissionid The submission ID
 * @param string $s3key The S3 key to verify
 * @param int $userid The user ID requesting access (defaults to current user)
 * @return array Array with 'allowed' (bool) and 'reason' (string) keys
 */
function assignsubmission_s3video_verify_video_access($submissionid, $s3key, $userid = null) {
    global $DB, $USER;

    // Use current user if not specified.
    if ($userid === null) {
        $userid = $USER->id;
    }

    // Get the submission record.
    $submission = $DB->get_record('assign_submission', array('id' => $submissionid), '*', IGNORE_MISSING);
    if (!$submission) {
        return array(
            'allowed' => false,
            'reason' => 'Submission not found'
        );
    }

    // Get the video record.
    $video = $DB->get_record('assignsubmission_s3video', 
        array('submission' => $submissionid), '*', IGNORE_MISSING);
    if (!$video) {
        return array(
            'allowed' => false,
            'reason' => 'Video not found'
        );
    }

    // Verify S3 key matches the submission.
    if ($video->s3_key !== $s3key) {
        return array(
            'allowed' => false,
            'reason' => 'S3 key does not match submission'
        );
    }

    // Check if video is in ready state.
    if ($video->upload_status !== 'ready') {
        return array(
            'allowed' => false,
            'reason' => 'Video is not ready for playback (status: ' . $video->upload_status . ')'
        );
    }

    // Get the assignment.
    $assignment = $DB->get_record('assign', array('id' => $submission->assignment), '*', IGNORE_MISSING);
    if (!$assignment) {
        return array(
            'allowed' => false,
            'reason' => 'Assignment not found'
        );
    }

    // Get the course module.
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Check if user is the submission owner (student viewing their own submission).
    if ($submission->userid == $userid) {
        // Student can view their own submission if they have the capability.
        if (has_capability('mod/assign:submit', $context, $userid)) {
            return array(
                'allowed' => true,
                'reason' => 'User is submission owner'
            );
        }
    }

    // Check if user is a teacher/grader.
    if (has_capability('mod/assign:grade', $context, $userid)) {
        return array(
            'allowed' => true,
            'reason' => 'User has grading capability'
        );
    }

    // Check if user is an admin.
    if (is_siteadmin($userid)) {
        return array(
            'allowed' => true,
            'reason' => 'User is site administrator'
        );
    }

    // Default deny.
    return array(
        'allowed' => false,
        'reason' => 'User does not have permission to view this video'
    );
}
