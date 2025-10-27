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
 * Local library functions for Cloudflare Stream submission plugin.
 *
 * @package   assignsubmission_cloudflarestream
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include the main plugin class to ensure it's always available
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/lib.php');

/**
 * Plugin detection and utility class for Cloudflare Stream submission.
 *
 * This class provides utility functions for detecting and managing the Cloudflare Stream
 * submission plugin within the Moodle assignment module.
 *
 * @package   assignsubmission_cloudflarestream
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_cloudflarestream_plugin_detector {

    /**
     * Check if the Cloudflare Stream plugin is installed.
     *
     * @return bool True if plugin is installed
     */
    public static function is_installed() {
        global $DB;
        
        try {
            // Check if the plugin tables exist
            $dbman = $DB->get_manager();
            $table = new xmldb_table('assignsubmission_cfstream');
            return $dbman->table_exists($table);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if the Cloudflare Stream plugin is enabled globally.
     *
     * @return bool True if plugin is enabled
     */
    public static function is_enabled_globally() {
        $enabled = get_config('assignsubmission_cloudflarestream', 'disabled');
        // If disabled is not set or is 0, plugin is enabled
        return empty($enabled);
    }

    /**
     * Check if the Cloudflare Stream plugin is properly configured.
     *
     * @return bool True if plugin has all required Cloudflare settings
     */
    public static function is_configured() {
        $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
        $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

        return !empty($apitoken) && !empty($accountid);
    }

    /**
     * Check if the Cloudflare Stream plugin is enabled for a specific assignment.
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
                    'plugin' => 'cloudflarestream',
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
        $plugin = $pluginman->get_plugin_info('assignsubmission_cloudflarestream');
        
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
            'apitoken' => !empty(get_config('assignsubmission_cloudflarestream', 'apitoken')),
            'accountid' => !empty(get_config('assignsubmission_cloudflarestream', 'accountid'))
        );

        return $status;
    }

    /**
     * Get list of assignments using the Cloudflare Stream plugin.
     *
     * @return array Array of assignment objects
     */
    public static function get_assignments_using_plugin() {
        global $DB;

        $sql = "SELECT DISTINCT a.id, a.name, c.fullname as coursename
                FROM {assign} a
                JOIN {course} c ON a.course = c.id
                JOIN {assign_plugin_config} apc ON apc.assignment = a.id
                WHERE apc.plugin = 'cloudflarestream'
                  AND apc.subtype = 'assignsubmission'
                  AND apc.name = 'enabled'
                  AND apc.value = '1'
                ORDER BY c.fullname, a.name";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get statistics about Cloudflare Stream usage.
     *
     * @return array Usage statistics
     */
    public static function get_usage_statistics() {
        global $DB;

        $stats = array();

        try {
            // Total videos uploaded
            $stats['total_videos'] = $DB->count_records('assignsubmission_cfstream');

            // Videos by status
            $stats['ready'] = $DB->count_records('assignsubmission_cfstream', 
                array('upload_status' => 'ready'));
            $stats['pending'] = $DB->count_records('assignsubmission_cfstream', 
                array('upload_status' => 'pending'));
            $stats['error'] = $DB->count_records('assignsubmission_cfstream', 
                array('upload_status' => 'error'));
            $stats['deleted'] = $DB->count_records('assignsubmission_cfstream', 
                array('upload_status' => 'deleted'));

            // Total storage used (in bytes)
            $result = $DB->get_record_sql(
                "SELECT SUM(file_size) as total_size 
                 FROM {assignsubmission_cfstream} 
                 WHERE upload_status = 'ready' AND file_size IS NOT NULL"
            );
            $stats['total_storage_bytes'] = $result ? (int)$result->total_size : 0;

            // Total video duration (in seconds)
            $result = $DB->get_record_sql(
                "SELECT SUM(duration) as total_duration 
                 FROM {assignsubmission_cfstream} 
                 WHERE upload_status = 'ready' AND duration IS NOT NULL"
            );
            $stats['total_duration_seconds'] = $result ? (int)$result->total_duration : 0;

            // Recent uploads (last 7 days)
            $weekago = time() - (7 * 24 * 60 * 60);
            $stats['recent_uploads'] = $DB->count_records_select('assignsubmission_cfstream',
                'upload_timestamp > ?', array($weekago));

        } catch (Exception $e) {
            // Return empty stats if tables don't exist yet
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
 * @param string $videouid The Cloudflare video UID to verify
 * @param int $userid The user ID requesting access (defaults to current user)
 * @return array Array with 'allowed' (bool) and 'reason' (string) keys
 */
function assignsubmission_cloudflarestream_verify_video_access($submissionid, $videouid, $userid = null) {
    global $DB, $USER;

    // Use current user if not specified
    if ($userid === null) {
        $userid = $USER->id;
    }

    // Get the submission record
    $submission = $DB->get_record('assign_submission', array('id' => $submissionid), '*', IGNORE_MISSING);
    if (!$submission) {
        return array(
            'allowed' => false,
            'reason' => 'Submission not found'
        );
    }

    // Get the video record
    $video = $DB->get_record('assignsubmission_cfstream', 
        array('submission' => $submissionid), '*', IGNORE_MISSING);
    if (!$video) {
        return array(
            'allowed' => false,
            'reason' => 'Video not found'
        );
    }

    // Verify video UID matches the submission
    if ($video->video_uid !== $videouid) {
        return array(
            'allowed' => false,
            'reason' => 'Video UID does not match submission'
        );
    }

    // Check if video is in ready state
    if ($video->upload_status !== 'ready') {
        return array(
            'allowed' => false,
            'reason' => 'Video is not ready for playback (status: ' . $video->upload_status . ')'
        );
    }

    // Get the assignment
    $assignment = $DB->get_record('assign', array('id' => $submission->assignment), '*', IGNORE_MISSING);
    if (!$assignment) {
        return array(
            'allowed' => false,
            'reason' => 'Assignment not found'
        );
    }

    // Get the course module
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Check if user is the submission owner (student viewing their own submission)
    if ($submission->userid == $userid) {
        // Student can view their own submission if they have the capability
        if (has_capability('mod/assign:submit', $context, $userid)) {
            return array(
                'allowed' => true,
                'reason' => 'User is submission owner'
            );
        }
    }

    // Check if user is a teacher/grader
    if (has_capability('mod/assign:grade', $context, $userid)) {
        return array(
            'allowed' => true,
            'reason' => 'User has grading capability'
        );
    }

    // Check if user is an admin
    if (is_siteadmin($userid)) {
        return array(
            'allowed' => true,
            'reason' => 'User is site administrator'
        );
    }

    // Default deny
    return array(
        'allowed' => false,
        'reason' => 'User does not have permission to view this video'
    );
}
