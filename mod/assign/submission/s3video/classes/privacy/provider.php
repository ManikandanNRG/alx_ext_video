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
 * Privacy Subsystem implementation for assignsubmission_s3video.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\cloudfront_client;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for assignsubmission_s3video implementing GDPR compliance.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\subplugin_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'assignsubmission_s3video',
            [
                'assignment' => 'privacy:metadata:assignsubmission_s3video:assignment',
                'submission' => 'privacy:metadata:assignsubmission_s3video:submission',
                's3_key' => 'privacy:metadata:assignsubmission_s3video:s3_key',
                's3_bucket' => 'privacy:metadata:assignsubmission_s3video:s3_bucket',
                'upload_status' => 'privacy:metadata:assignsubmission_s3video:upload_status',
                'file_size' => 'privacy:metadata:assignsubmission_s3video:file_size',
                'duration' => 'privacy:metadata:assignsubmission_s3video:duration',
                'mime_type' => 'privacy:metadata:assignsubmission_s3video:mime_type',
                'upload_timestamp' => 'privacy:metadata:assignsubmission_s3video:upload_timestamp',
                'deleted_timestamp' => 'privacy:metadata:assignsubmission_s3video:deleted_timestamp',
                'error_message' => 'privacy:metadata:assignsubmission_s3video:error_message',
            ],
            'privacy:metadata:assignsubmission_s3video'
        );

        $items->add_database_table(
            'assignsubmission_s3v_log',
            [
                'userid' => 'privacy:metadata:assignsubmission_s3v_log:userid',
                'assignmentid' => 'privacy:metadata:assignsubmission_s3v_log:assignmentid',
                'submissionid' => 'privacy:metadata:assignsubmission_s3v_log:submissionid',
                's3_key' => 'privacy:metadata:assignsubmission_s3v_log:s3_key',
                'event_type' => 'privacy:metadata:assignsubmission_s3v_log:event_type',
                'error_code' => 'privacy:metadata:assignsubmission_s3v_log:error_code',
                'error_message' => 'privacy:metadata:assignsubmission_s3v_log:error_message',
                'error_context' => 'privacy:metadata:assignsubmission_s3v_log:error_context',
                'file_size' => 'privacy:metadata:assignsubmission_s3v_log:file_size',
                'duration' => 'privacy:metadata:assignsubmission_s3v_log:duration',
                'retry_count' => 'privacy:metadata:assignsubmission_s3v_log:retry_count',
                'user_role' => 'privacy:metadata:assignsubmission_s3v_log:user_role',
                'timestamp' => 'privacy:metadata:assignsubmission_s3v_log:timestamp',
            ],
            'privacy:metadata:assignsubmission_s3v_log'
        );

        $items->add_external_location_link(
            'aws_s3',
            [
                'video_content' => 'privacy:metadata:aws_s3:video_content',
                'video_metadata' => 'privacy:metadata:aws_s3:video_metadata',
                'user_identifier' => 'privacy:metadata:aws_s3:user_identifier',
            ],
            'privacy:metadata:aws_s3'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Get contexts where the user has submitted videos.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {assignsubmission_s3video} s3v
                  JOIN {assign_submission} s ON s.id = s3v.submission
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE s.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Get contexts where the user has log entries.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {assignsubmission_s3v_log} s3vl
                  JOIN {assign} a ON a.id = s3vl.assignmentid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE s3vl.userid = :userid";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // Get users who have submitted videos in this context.
        $sql = "SELECT s.userid
                  FROM {assignsubmission_s3video} s3v
                  JOIN {assign_submission} s ON s.id = s3v.submission
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE cm.id = :cmid";

        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);

        // Get users who have log entries in this context.
        $sql = "SELECT s3vl.userid
                  FROM {assignsubmission_s3v_log} s3vl
                  JOIN {assign} a ON a.id = s3vl.assignmentid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE cm.id = :cmid AND s3vl.userid IS NOT NULL";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            // Get the assignment from the context.
            $cm = get_coursemodule_from_id('assign', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $assign = $DB->get_record('assign', ['id' => $cm->instance]);
            if (!$assign) {
                continue;
            }

            // Export video submission data.
            static::export_video_submissions($context, $user, $assign);

            // Export log data.
            static::export_log_data($context, $user, $assign);
        }
    }

    /**
     * Export video submission data for a user in a specific context.
     *
     * @param \context_module $context The module context.
     * @param \stdClass $user The user object.
     * @param \stdClass $assign The assignment object.
     */
    protected static function export_video_submissions(\context_module $context, \stdClass $user, \stdClass $assign) {
        global $DB;

        $sql = "SELECT s3v.*, s.id as submission_id, s.timecreated, s.timemodified
                  FROM {assignsubmission_s3video} s3v
                  JOIN {assign_submission} s ON s.id = s3v.submission
                 WHERE s.assignment = :assignmentid AND s.userid = :userid";

        $params = [
            'assignmentid' => $assign->id,
            'userid' => $user->id,
        ];

        $videos = $DB->get_records_sql($sql, $params);

        if (!empty($videos)) {
            $data = [];
            foreach ($videos as $video) {
                $data[] = [
                    's3_key' => $video->s3_key,
                    's3_bucket' => $video->s3_bucket,
                    'upload_status' => $video->upload_status,
                    'file_size' => $video->file_size ? display_size($video->file_size) : null,
                    'duration' => $video->duration ? format_time($video->duration) : null,
                    'mime_type' => $video->mime_type,
                    'upload_timestamp' => $video->upload_timestamp ? 
                        transform::datetime($video->upload_timestamp) : null,
                    'deleted_timestamp' => $video->deleted_timestamp ? 
                        transform::datetime($video->deleted_timestamp) : null,
                    'error_message' => $video->error_message,
                    'submission_created' => transform::datetime($video->timecreated),
                    'submission_modified' => transform::datetime($video->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'assignsubmission_s3video'), 
                 get_string('videosubmissions', 'assignsubmission_s3video')],
                (object) ['videos' => $data]
            );
        }
    }

    /**
     * Export log data for a user in a specific context.
     *
     * @param \context_module $context The module context.
     * @param \stdClass $user The user object.
     * @param \stdClass $assign The assignment object.
     */
    protected static function export_log_data(\context_module $context, \stdClass $user, \stdClass $assign) {
        global $DB;

        $sql = "SELECT *
                  FROM {assignsubmission_s3v_log}
                 WHERE assignmentid = :assignmentid AND userid = :userid
                 ORDER BY timestamp DESC";

        $params = [
            'assignmentid' => $assign->id,
            'userid' => $user->id,
        ];

        $logs = $DB->get_records_sql($sql, $params);

        if (!empty($logs)) {
            $data = [];
            foreach ($logs as $log) {
                $data[] = [
                    'event_type' => $log->event_type,
                    's3_key' => $log->s3_key,
                    'error_code' => $log->error_code,
                    'error_message' => $log->error_message,
                    'error_context' => $log->error_context,
                    'file_size' => $log->file_size ? display_size($log->file_size) : null,
                    'duration' => $log->duration ? format_time($log->duration) : null,
                    'retry_count' => $log->retry_count,
                    'user_role' => $log->user_role,
                    'timestamp' => transform::datetime($log->timestamp),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'assignsubmission_s3video'), 
                 get_string('activitylogs', 'assignsubmission_s3video')],
                (object) ['logs' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        // Get the assignment from the context.
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        if (!$cm) {
            return;
        }

        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return;
        }

        // Get all video submissions for this assignment.
        $sql = "SELECT s3v.*
                  FROM {assignsubmission_s3video} s3v
                  JOIN {assign_submission} s ON s.id = s3v.submission
                 WHERE s.assignment = :assignmentid";

        $videos = $DB->get_records_sql($sql, ['assignmentid' => $assign->id]);

        // Delete videos from S3 and update database.
        static::delete_videos_from_s3($videos);

        // Delete log entries for this assignment.
        $DB->delete_records('assignsubmission_s3v_log', ['assignmentid' => $assign->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            // Get the assignment from the context.
            $cm = get_coursemodule_from_id('assign', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $assign = $DB->get_record('assign', ['id' => $cm->instance]);
            if (!$assign) {
                continue;
            }

            // Get user's video submissions for this assignment.
            $sql = "SELECT s3v.*
                      FROM {assignsubmission_s3video} s3v
                      JOIN {assign_submission} s ON s.id = s3v.submission
                     WHERE s.assignment = :assignmentid AND s.userid = :userid";

            $params = [
                'assignmentid' => $assign->id,
                'userid' => $user->id,
            ];

            $videos = $DB->get_records_sql($sql, $params);

            // Delete videos from S3 and update database.
            static::delete_videos_from_s3($videos);

            // Delete user's log entries for this assignment.
            $DB->delete_records('assignsubmission_s3v_log', [
                'assignmentid' => $assign->id,
                'userid' => $user->id,
            ]);
        }
    }

    /**
     * Delete all user data for the specified users, in the specified context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // Get the assignment from the context.
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        if (!$cm) {
            return;
        }

        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Get video submissions for these users in this assignment.
        $sql = "SELECT s3v.*
                  FROM {assignsubmission_s3video} s3v
                  JOIN {assign_submission} s ON s.id = s3v.submission
                 WHERE s.assignment = :assignmentid AND s.userid {$usersql}";

        $params = array_merge(['assignmentid' => $assign->id], $userparams);
        $videos = $DB->get_records_sql($sql, $params);

        // Delete videos from S3 and update database.
        static::delete_videos_from_s3($videos);

        // Delete log entries for these users in this assignment.
        $sql = "DELETE FROM {assignsubmission_s3v_log}
                 WHERE assignmentid = :assignmentid AND userid {$usersql}";

        $DB->execute($sql, $params);
    }

    /**
     * Delete videos from AWS S3 and update database records.
     *
     * @param array $videos Array of video records from assignsubmission_s3video table
     */
    protected static function delete_videos_from_s3(array $videos) {
        global $DB;

        if (empty($videos)) {
            return;
        }

        // Get AWS credentials.
        $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
        $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
        $bucket = get_config('assignsubmission_s3video', 's3_bucket');
        $region = get_config('assignsubmission_s3video', 's3_region');

        if (empty($accesskey) || empty($secretkey) || empty($bucket) || empty($region)) {
            // Cannot delete from S3 without credentials, but still update database.
            foreach ($videos as $video) {
                if ($video->upload_status !== 'deleted') {
                    $video->upload_status = 'deleted';
                    $video->deleted_timestamp = time();
                    $DB->update_record('assignsubmission_s3video', $video);
                }
            }
            return;
        }

        try {
            $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);

            // Get CloudFront credentials for cache invalidation.
            $cfdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
            $cfkeypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
            $cfprivatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

            $cfclient = null;
            if (!empty($cfdomain) && !empty($cfkeypairid) && !empty($cfprivatekey)) {
                try {
                    $cfclient = new cloudfront_client($cfdomain, $cfkeypairid, $cfprivatekey);
                } catch (\Exception $e) {
                    // CloudFront client initialization failed, continue without invalidation.
                    $cfclient = null;
                }
            }

            foreach ($videos as $video) {
                if ($video->upload_status === 'deleted') {
                    continue; // Already deleted.
                }

                try {
                    // Delete from S3.
                    $s3client->delete_object($video->s3_key);

                    // Invalidate CloudFront cache if client is available.
                    if ($cfclient !== null) {
                        try {
                            $cfclient->create_invalidation($video->s3_key);
                        } catch (\Exception $e) {
                            // Log but don't fail if invalidation fails.
                            \assignsubmission_s3video\logger::log_api_error(
                                'cloudfront_invalidation',
                                'POST',
                                'invalidation_failed',
                                $e->getMessage(),
                                0,
                                $video->s3_key
                            );
                        }
                    }

                    // Update database record.
                    $video->upload_status = 'deleted';
                    $video->deleted_timestamp = time();
                    $DB->update_record('assignsubmission_s3video', $video);

                    // Log the deletion.
                    \assignsubmission_s3video\logger::log_video_deletion(
                        $video->s3_key,
                        null, // No specific user for privacy deletion
                        $video->assignment,
                        $video->submission,
                        'privacy_deletion'
                    );

                } catch (\Exception $e) {
                    // Log the error but continue with other videos.
                    \assignsubmission_s3video\logger::log_api_error(
                        'delete_video',
                        'DELETE',
                        'privacy_deletion_failed',
                        $e->getMessage(),
                        0,
                        $video->s3_key
                    );

                    // Still mark as deleted in database even if S3 deletion failed.
                    $video->upload_status = 'deleted';
                    $video->deleted_timestamp = time();
                    $video->error_message = 'Privacy deletion failed: ' . $e->getMessage();
                    $DB->update_record('assignsubmission_s3video', $video);
                }
            }
        } catch (\Exception $e) {
            // S3 client initialization failed, mark all as deleted in database.
            foreach ($videos as $video) {
                if ($video->upload_status !== 'deleted') {
                    $video->upload_status = 'deleted';
                    $video->deleted_timestamp = time();
                    $video->error_message = 'Privacy deletion failed: ' . $e->getMessage();
                    $DB->update_record('assignsubmission_s3video', $video);
                }
            }
        }
    }
}
