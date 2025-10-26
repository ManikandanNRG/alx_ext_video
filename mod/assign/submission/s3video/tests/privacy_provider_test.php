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
 * Unit tests for GDPR privacy provider.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

use assignsubmission_s3video\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for privacy provider GDPR compliance.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_provider_test extends provider_testcase {

    /**
     * Set up test data.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        
        // Set up plugin configuration.
        set_config('aws_access_key', 'test_access_key', 'assignsubmission_s3video');
        set_config('aws_secret_key', 'test_secret_key', 'assignsubmission_s3video');
        set_config('s3_bucket', 'test-bucket', 'assignsubmission_s3video');
        set_config('s3_region', 'us-east-1', 'assignsubmission_s3video');
    }

    /**
     * Create test assignment and submission data.
     *
     * @return array Array containing course, assignment, users, and submissions
     */
    private function create_test_data() {
        global $DB;

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Create assignment.
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        // Enroll users.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        // Create submissions.
        $submission1 = new \stdClass();
        $submission1->assignment = $assignment->id;
        $submission1->userid = $student1->id;
        $submission1->timecreated = time() - 3600;
        $submission1->timemodified = time() - 1800;
        $submission1->status = 'submitted';
        $submission1->groupid = 0;
        $submission1->attemptnumber = 0;
        $submission1->latest = 1;
        $submission1->id = $DB->insert_record('assign_submission', $submission1);

        $submission2 = new \stdClass();
        $submission2->assignment = $assignment->id;
        $submission2->userid = $student2->id;
        $submission2->timecreated = time() - 7200;
        $submission2->timemodified = time() - 3600;
        $submission2->status = 'submitted';
        $submission2->groupid = 0;
        $submission2->attemptnumber = 0;
        $submission2->latest = 1;
        $submission2->id = $DB->insert_record('assign_submission', $submission2);

        // Create video records.
        $video1 = new \stdClass();
        $video1->assignment = $assignment->id;
        $video1->submission = $submission1->id;
        $video1->s3_key = 'videos/123/1698345600_a7b3c/video1.mp4';
        $video1->s3_bucket = 'test-bucket';
        $video1->upload_status = 'ready';
        $video1->file_size = 1024000;
        $video1->duration = 120;
        $video1->mime_type = 'video/mp4';
        $video1->upload_timestamp = time() - 3600;
        $video1->deleted_timestamp = null;
        $video1->error_message = null;
        $DB->insert_record('assignsubmission_s3video', $video1);

        $video2 = new \stdClass();
        $video2->assignment = $assignment->id;
        $video2->submission = $submission2->id;
        $video2->s3_key = 'videos/456/1698345700_b8c4d/video2.mp4';
        $video2->s3_bucket = 'test-bucket';
        $video2->upload_status = 'ready';
        $video2->file_size = 2048000;
        $video2->duration = 240;
        $video2->mime_type = 'video/mp4';
        $video2->upload_timestamp = time() - 7200;
        $video2->deleted_timestamp = null;
        $video2->error_message = null;
        $DB->insert_record('assignsubmission_s3video', $video2);

        // Create log entries.
        $log1 = new \stdClass();
        $log1->userid = $student1->id;
        $log1->assignmentid = $assignment->id;
        $log1->submissionid = $submission1->id;
        $log1->s3_key = 'videos/123/1698345600_a7b3c/video1.mp4';
        $log1->event_type = 'upload_success';
        $log1->error_code = null;
        $log1->error_message = null;
        $log1->error_context = null;
        $log1->file_size = 1024000;
        $log1->duration = 120;
        $log1->retry_count = 0;
        $log1->user_role = 'student';
        $log1->timestamp = time() - 3600;
        $DB->insert_record('assignsubmission_s3v_log', $log1);

        $log2 = new \stdClass();
        $log2->userid = $student2->id;
        $log2->assignmentid = $assignment->id;
        $log2->submissionid = $submission2->id;
        $log2->s3_key = 'videos/456/1698345700_b8c4d/video2.mp4';
        $log2->event_type = 'upload_success';
        $log2->error_code = null;
        $log2->error_message = null;
        $log2->error_context = null;
        $log2->file_size = 2048000;
        $log2->duration = 240;
        $log2->retry_count = 0;
        $log2->user_role = 'student';
        $log2->timestamp = time() - 7200;
        $DB->insert_record('assignsubmission_s3v_log', $log2);

        return [
            'course' => $course,
            'assignment' => $assignment,
            'student1' => $student1,
            'student2' => $student2,
            'teacher' => $teacher,
            'submission1' => $submission1,
            'submission2' => $submission2,
        ];
    }

    /**
     * Test get_metadata returns correct metadata.
     */
    public function test_get_metadata() {
        $collection = new \core_privacy\local\metadata\collection('assignsubmission_s3video');
        $metadata = provider::get_metadata($collection);

        $this->assertInstanceOf(\core_privacy\local\metadata\collection::class, $metadata);
        
        // Check that required tables are included.
        $items = $metadata->get_collection();
        $tablenames = [];
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        $this->assertContains('assignsubmission_s3video', $tablenames);
        $this->assertContains('assignsubmission_s3v_log', $tablenames);
    }

    /**
     * Test get_contexts_for_userid returns correct contexts.
     */
    public function test_get_contexts_for_userid() {
        $data = $this->create_test_data();

        // Get contexts for student1.
        $contextlist = provider::get_contexts_for_userid($data['student1']->id);

        $this->assertInstanceOf(contextlist::class, $contextlist);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Verify it's the correct context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_MODULE, $context->contextlevel);
        
        // Get the course module for the assignment.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $this->assertEquals($cm->id, $context->instanceid);
    }

    /**
     * Test get_users_in_context returns correct users.
     */
    public function test_get_users_in_context() {
        $data = $this->create_test_data();

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        $userlist = new userlist($context, 'assignsubmission_s3video');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertCount(2, $userids);
        $this->assertContains($data['student1']->id, $userids);
        $this->assertContains($data['student2']->id, $userids);
    }

    /**
     * Test export_user_data exports correct data.
     */
    public function test_export_user_data() {
        $data = $this->create_test_data();

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        // Create approved contextlist.
        $contextlist = new approved_contextlist($data['student1'], 'assignsubmission_s3video', [$context->id]);

        // Export data.
        provider::export_user_data($contextlist);

        // Get the exported data.
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // Check video submissions data.
        $pluginname = get_string('pluginname', 'assignsubmission_s3video');
        $videosubmissions = get_string('videosubmissions', 'assignsubmission_s3video');
        $data_export = $writer->get_data([$pluginname, $videosubmissions]);

        $this->assertNotEmpty($data_export);
        $this->assertObjectHasAttribute('videos', $data_export);
        $this->assertCount(1, $data_export->videos);

        $video = $data_export->videos[0];
        $this->assertEquals('videos/123/1698345600_a7b3c/video1.mp4', $video['s3_key']);
        $this->assertEquals('test-bucket', $video['s3_bucket']);
        $this->assertEquals('ready', $video['upload_status']);
        $this->assertEquals('1000 KB', $video['file_size']);
        $this->assertEquals('2 mins', $video['duration']);
        $this->assertEquals('video/mp4', $video['mime_type']);

        // Check log data.
        $activitylogs = get_string('activitylogs', 'assignsubmission_s3video');
        $log_export = $writer->get_data([$pluginname, $activitylogs]);

        $this->assertNotEmpty($log_export);
        $this->assertObjectHasAttribute('logs', $log_export);
        $this->assertCount(1, $log_export->logs);

        $log = $log_export->logs[0];
        $this->assertEquals('upload_success', $log['event_type']);
        $this->assertEquals('videos/123/1698345600_a7b3c/video1.mp4', $log['s3_key']);
        $this->assertEquals('student', $log['user_role']);
    }

    /**
     * Test delete_data_for_user removes user data and videos.
     */
    public function test_delete_data_for_user() {
        global $DB;

        $data = $this->create_test_data();

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        // Create approved contextlist.
        $contextlist = new approved_contextlist($data['student1'], 'assignsubmission_s3video', [$context->id]);

        // Verify data exists before deletion.
        $videos_before = $DB->get_records('assignsubmission_s3video', ['submission' => $data['submission1']->id]);
        $logs_before = $DB->get_records('assignsubmission_s3v_log', [
            'userid' => $data['student1']->id,
            'assignmentid' => $data['assignment']->id
        ]);
        $this->assertCount(1, $videos_before);
        $this->assertCount(1, $logs_before);

        // Delete data for user.
        provider::delete_data_for_user($contextlist);

        // Verify video record is marked as deleted.
        $videos_after = $DB->get_records('assignsubmission_s3video', ['submission' => $data['submission1']->id]);
        $this->assertCount(1, $videos_after);
        $video = reset($videos_after);
        $this->assertEquals('deleted', $video->upload_status);
        $this->assertNotNull($video->deleted_timestamp);

        // Verify log records are deleted.
        $logs_after = $DB->get_records('assignsubmission_s3v_log', [
            'userid' => $data['student1']->id,
            'assignmentid' => $data['assignment']->id
        ]);
        $this->assertCount(0, $logs_after);

        // Verify other user's data is not affected.
        $other_videos = $DB->get_records('assignsubmission_s3video', ['submission' => $data['submission2']->id]);
        $this->assertCount(1, $other_videos);
        $other_video = reset($other_videos);
        $this->assertEquals('ready', $other_video->upload_status);

        $other_logs = $DB->get_records('assignsubmission_s3v_log', [
            'userid' => $data['student2']->id,
            'assignmentid' => $data['assignment']->id
        ]);
        $this->assertCount(1, $other_logs);
    }

    /**
     * Test delete_data_for_users removes multiple users' data.
     */
    public function test_delete_data_for_users() {
        global $DB;

        $data = $this->create_test_data();

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        // Create approved userlist with both students.
        $userlist = new approved_userlist($context, 'assignsubmission_s3video', [
            $data['student1']->id,
            $data['student2']->id
        ]);

        // Verify data exists before deletion.
        $videos_before = $DB->get_records('assignsubmission_s3video');
        $logs_before = $DB->get_records('assignsubmission_s3v_log');
        $this->assertCount(2, $videos_before);
        $this->assertCount(2, $logs_before);

        // Delete data for users.
        provider::delete_data_for_users($userlist);

        // Verify all video records are marked as deleted.
        $videos_after = $DB->get_records('assignsubmission_s3video');
        $this->assertCount(2, $videos_after);
        foreach ($videos_after as $video) {
            $this->assertEquals('deleted', $video->upload_status);
            $this->assertNotNull($video->deleted_timestamp);
        }

        // Verify all log records are deleted.
        $logs_after = $DB->get_records('assignsubmission_s3v_log');
        $this->assertCount(0, $logs_after);
    }

    /**
     * Test delete_data_for_all_users_in_context removes all data in context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $data = $this->create_test_data();

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        // Verify data exists before deletion.
        $videos_before = $DB->get_records('assignsubmission_s3video');
        $logs_before = $DB->get_records('assignsubmission_s3v_log');
        $this->assertCount(2, $videos_before);
        $this->assertCount(2, $logs_before);

        // Delete all data in context.
        provider::delete_data_for_all_users_in_context($context);

        // Verify all video records are marked as deleted.
        $videos_after = $DB->get_records('assignsubmission_s3video');
        $this->assertCount(2, $videos_after);
        foreach ($videos_after as $video) {
            $this->assertEquals('deleted', $video->upload_status);
            $this->assertNotNull($video->deleted_timestamp);
        }

        // Verify all log records are deleted.
        $logs_after = $DB->get_records('assignsubmission_s3v_log', ['assignmentid' => $data['assignment']->id]);
        $this->assertCount(0, $logs_after);
    }

    /**
     * Test deletion handles API failures gracefully.
     */
    public function test_delete_with_api_failure() {
        global $DB;

        $data = $this->create_test_data();

        // Remove API credentials to simulate failure.
        set_config('aws_access_key', '', 'assignsubmission_s3video');

        // Get the context.
        $cm = get_coursemodule_from_instance('assign', $data['assignment']->id);
        $context = \context_module::instance($cm->id);

        // Create approved contextlist.
        $contextlist = new approved_contextlist($data['student1'], 'assignsubmission_s3video', [$context->id]);

        // Delete data (should not throw exception even without API credentials).
        provider::delete_data_for_user($contextlist);

        // Verify video record is still marked as deleted in database.
        $videos_after = $DB->get_records('assignsubmission_s3video', ['submission' => $data['submission1']->id]);
        $this->assertCount(1, $videos_after);
        $video = reset($videos_after);
        $this->assertEquals('deleted', $video->upload_status);
        $this->assertNotNull($video->deleted_timestamp);

        // Verify log records are deleted.
        $logs_after = $DB->get_records('assignsubmission_s3v_log', [
            'userid' => $data['student1']->id,
            'assignmentid' => $data['assignment']->id
        ]);
        $this->assertCount(0, $logs_after);
    }

    /**
     * Test export with no data returns empty result.
     */
    public function test_export_user_data_no_data() {
        // Create user with no video submissions.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('assign', $assignment->id);
        $context = \context_module::instance($cm->id);

        // Create approved contextlist.
        $contextlist = new approved_contextlist($user, 'assignsubmission_s3video', [$context->id]);

        // Export data.
        provider::export_user_data($contextlist);

        // Verify no data was exported.
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test get_contexts_for_userid with no data returns empty contextlist.
     */
    public function test_get_contexts_for_userid_no_data() {
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertInstanceOf(contextlist::class, $contextlist);
        $this->assertCount(0, $contextlist->get_contexts());
    }

    /**
     * Test get_users_in_context with no data returns empty userlist.
     */
    public function test_get_users_in_context_no_data() {
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('assign', $assignment->id);
        $context = \context_module::instance($cm->id);

        $userlist = new userlist($context, 'assignsubmission_s3video');
        provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist->get_userids());
    }
}
