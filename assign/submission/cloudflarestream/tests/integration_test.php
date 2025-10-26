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
 * Integration tests for Cloudflare Stream plugin workflows.
 *
 * Tests complete upload workflow, playback workflow, and access control.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream;

use assignsubmission_cloudflarestream\api\cloudflare_client;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/lib.php');

/**
 * Integration tests for complete workflows.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integration_test extends \advanced_testcase {

    /** @var stdClass Course object */
    private $course;

    /** @var stdClass Assignment object */
    private $assignment;

    /** @var stdClass Course module object */
    private $cm;

    /** @var stdClass Student user object */
    private $student;

    /** @var stdClass Teacher user object */
    private $teacher;

    /** @var stdClass Another student user object */
    private $otherstudent;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        global $DB;
        
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create users.
        $this->student = $this->getDataGenerator()->create_user(['username' => 'student1']);
        $this->teacher = $this->getDataGenerator()->create_user(['username' => 'teacher1']);
        $this->otherstudent = $this->getDataGenerator()->create_user(['username' => 'student2']);

        // Enrol users.
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($this->otherstudent->id, $this->course->id, 'student');

        // Create assignment with cloudflarestream plugin enabled.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $params = [
            'course' => $this->course->id,
            'assignsubmission_cloudflarestream_enabled' => 1,
        ];
        $instance = $generator->create_instance($params);
        $this->assignment = $DB->get_record('assign', ['id' => $instance->id], '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_instance('assign', $this->assignment->id);

        // Configure plugin.
        set_config('apitoken', 'test_api_token_12345', 'assignsubmission_cloudflarestream');
        set_config('accountid', 'test_account_id', 'assignsubmission_cloudflarestream');
        set_config('retention_days', 90, 'assignsubmission_cloudflarestream');
        set_config('max_file_size', 5368709120, 'assignsubmission_cloudflarestream');
    }

    /**
     * Test complete upload workflow: request URL → upload → confirm.
     *
     * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 5.1, 5.2
     */
    public function test_complete_upload_workflow() {
        global $DB, $USER;

        $this->setUser($this->student);

        // Step 1: Create submission.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $this->assertNotEmpty($submission);
        $this->assertEquals($this->student->id, $submission->userid);

        // Step 2: Simulate get_upload_url.php - Request upload URL.
        // Create pending database record.
        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = '';
        $record->upload_status = 'pending';
        $record->upload_timestamp = time();

        $recordid = $DB->insert_record('assignsubmission_cfstream', $record);
        $this->assertNotEmpty($recordid);

        // Verify record was created with pending status.
        $dbrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertEquals('pending', $dbrecord->upload_status);
        $this->assertEquals($submission->id, $dbrecord->submission);
        $this->assertEquals('', $dbrecord->video_uid);

        // Step 3: Simulate upload to Cloudflare (this happens in browser).
        // In real workflow, tus-js-client uploads directly to Cloudflare.
        $videouid = 'test-video-uid-' . uniqid();

        // Step 4: Simulate confirm_upload.php - Confirm upload completion.
        // Update record with video UID and status.
        $dbrecord->video_uid = $videouid;
        $dbrecord->upload_status = 'ready';
        $dbrecord->duration = 120; // 2 minutes.
        $dbrecord->file_size = 1024000; // ~1MB.

        $DB->update_record('assignsubmission_cfstream', $dbrecord);

        // Verify record was updated correctly.
        $updatedrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertEquals('ready', $updatedrecord->upload_status);
        $this->assertEquals($videouid, $updatedrecord->video_uid);
        $this->assertEquals(120, $updatedrecord->duration);
        $this->assertEquals(1024000, $updatedrecord->file_size);

        // Step 5: Verify submission is not empty.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $this->assertFalse($plugin->is_empty($submission));

        // Step 6: Verify view displays video information.
        $output = $plugin->view($submission);
        $this->assertStringContainsString($videouid, $output);
        $this->assertStringContainsString('cloudflarestream-player', $output);
    }

    /**
     * Test complete playback workflow: request token → embed player.
     *
     * Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2
     */
    public function test_complete_playback_workflow() {
        global $DB;

        $this->setUser($this->student);

        // Create submission with video.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-playback-' . uniqid();

        // Create video record.
        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->duration = 180;
        $record->file_size = 2048000;
        $record->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record);

        // Step 1: Verify user can view their own submission.
        $canview = can_view_submission($this->student->id, $submission->id);
        $this->assertTrue($canview);

        // Step 2: Verify video access.
        try {
            verify_video_access($this->student->id, $submission->id, $videouid);
            $accessgranted = true;
        } catch (\moodle_exception $e) {
            $accessgranted = false;
        }
        $this->assertTrue($accessgranted);

        // Step 3: Verify player is rendered in view.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $output = $plugin->view($submission);

        // Check that player container is present.
        $this->assertStringContainsString('cloudflarestream-player', $output);
        $this->assertStringContainsString($videouid, $output);
        $this->assertStringContainsString($submission->id, $output);

        // Check that metadata is displayed.
        $this->assertStringContainsString('3 mins', $output); // Duration formatted.
        $this->assertStringContainsString('2MB', $output); // File size formatted.

        // Step 4: Verify teacher can also view the submission.
        $this->setUser($this->teacher);
        $canviewteacher = can_view_submission($this->teacher->id, $submission->id);
        $this->assertTrue($canviewteacher);

        try {
            verify_video_access($this->teacher->id, $submission->id, $videouid);
            $teacheraccessgranted = true;
        } catch (\moodle_exception $e) {
            $teacheraccessgranted = false;
        }
        $this->assertTrue($teacheraccessgranted);
    }

    /**
     * Test access control: unauthorized access denied, authorized access granted.
     *
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 5.3, 5.4
     */
    public function test_access_control() {
        global $DB;

        $this->setUser($this->student);

        // Create submission with video for student1.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-access-' . uniqid();

        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record);

        // Test 1: Student can view their own submission.
        $this->setUser($this->student);
        $canview = can_view_submission($this->student->id, $submission->id);
        $this->assertTrue($canview, 'Student should be able to view their own submission');

        try {
            verify_video_access($this->student->id, $submission->id, $videouid);
            $accessgranted = true;
        } catch (\moodle_exception $e) {
            $accessgranted = false;
        }
        $this->assertTrue($accessgranted, 'Student should have access to their own video');

        // Test 2: Other student CANNOT view the submission.
        $this->setUser($this->otherstudent);
        $canviewother = can_view_submission($this->otherstudent->id, $submission->id);
        $this->assertFalse($canviewother, 'Other student should NOT be able to view submission');

        try {
            verify_video_access($this->otherstudent->id, $submission->id, $videouid);
            $otheraccessgranted = true;
        } catch (\moodle_exception $e) {
            $otheraccessgranted = false;
            $this->assertEquals('nopermissiontoviewvideo', $e->errorcode);
        }
        $this->assertFalse($otheraccessgranted, 'Other student should NOT have access to video');

        // Test 3: Teacher CAN view the submission.
        $this->setUser($this->teacher);
        $canviewteacher = can_view_submission($this->teacher->id, $submission->id);
        $this->assertTrue($canviewteacher, 'Teacher should be able to view student submission');

        try {
            verify_video_access($this->teacher->id, $submission->id, $videouid);
            $teacheraccessgranted = true;
        } catch (\moodle_exception $e) {
            $teacheraccessgranted = false;
        }
        $this->assertTrue($teacheraccessgranted, 'Teacher should have access to student video');

        // Test 4: Admin CAN view the submission.
        $this->setAdminUser();
        $admin = get_admin();
        $canviewadmin = can_view_submission($admin->id, $submission->id);
        $this->assertTrue($canviewadmin, 'Admin should be able to view any submission');

        try {
            verify_video_access($admin->id, $submission->id, $videouid);
            $adminaccessgranted = true;
        } catch (\moodle_exception $e) {
            $adminaccessgranted = false;
        }
        $this->assertTrue($adminaccessgranted, 'Admin should have access to any video');

        // Test 5: Invalid video UID is rejected.
        $this->setUser($this->student);
        $invaliduid = 'invalid-video-uid-12345';

        try {
            verify_video_access($this->student->id, $submission->id, $invaliduid);
            $invalidaccessgranted = true;
        } catch (\moodle_exception $e) {
            $invalidaccessgranted = false;
            $this->assertEquals('invalidvideouid', $e->errorcode);
        }
        $this->assertFalse($invalidaccessgranted, 'Invalid video UID should be rejected');
    }

    /**
     * Test upload workflow with error handling.
     *
     * Requirements: 2.3, 2.4, 9.1, 9.2
     */
    public function test_upload_workflow_with_errors() {
        global $DB;

        $this->setUser($this->student);

        // Create submission.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        // Simulate upload failure.
        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = '';
        $record->upload_status = 'error';
        $record->error_message = 'Network connection failed';
        $record->upload_timestamp = time();

        $recordid = $DB->insert_record('assignsubmission_cfstream', $record);

        // Verify error status is stored.
        $dbrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertEquals('error', $dbrecord->upload_status);
        $this->assertEquals('Network connection failed', $dbrecord->error_message);

        // Verify view displays error message.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $output = $plugin->view($submission);
        $this->assertStringContainsString('Network connection failed', $output);
        $this->assertStringContainsString('alert-danger', $output);

        // Simulate retry - update to pending.
        $dbrecord->upload_status = 'pending';
        $dbrecord->error_message = null;
        $DB->update_record('assignsubmission_cfstream', $dbrecord);

        // Simulate successful upload after retry.
        $videouid = 'test-video-uid-retry-' . uniqid();
        $dbrecord->video_uid = $videouid;
        $dbrecord->upload_status = 'ready';
        $dbrecord->duration = 90;
        $dbrecord->file_size = 512000;
        $DB->update_record('assignsubmission_cfstream', $dbrecord);

        // Verify successful upload.
        $updatedrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertEquals('ready', $updatedrecord->upload_status);
        $this->assertEquals($videouid, $updatedrecord->video_uid);
        $this->assertNull($updatedrecord->error_message);
    }

    /**
     * Test video deletion and status tracking.
     *
     * Requirements: 8.1, 8.2, 8.3, 8.4
     */
    public function test_video_deletion_workflow() {
        global $DB;

        $this->setUser($this->student);

        // Create submission with video.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-delete-' . uniqid();

        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->duration = 60;
        $record->file_size = 256000;
        $record->upload_timestamp = time() - (100 * 24 * 3600); // 100 days ago.

        $recordid = $DB->insert_record('assignsubmission_cfstream', $record);

        // Simulate video deletion (by cleanup task).
        $dbrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $dbrecord->upload_status = 'deleted';
        $dbrecord->deleted_timestamp = time();
        $DB->update_record('assignsubmission_cfstream', $dbrecord);

        // Verify deletion status.
        $deletedrecord = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertEquals('deleted', $deletedrecord->upload_status);
        $this->assertNotEmpty($deletedrecord->deleted_timestamp);

        // Verify view displays appropriate message.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $output = $plugin->view($submission);
        $this->assertStringContainsString('videonotavailable', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    /**
     * Test multiple submissions and concurrent access.
     *
     * Requirements: 5.1, 5.2, 5.3, 5.4
     */
    public function test_multiple_submissions() {
        global $DB;

        // Create submissions for both students.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);

        // Student 1 submission.
        $this->setUser($this->student);
        $submission1 = $assign->get_user_submission($this->student->id, true);
        $videouid1 = 'test-video-uid-multi-1-' . uniqid();

        $record1 = new \stdClass();
        $record1->assignment = $this->assignment->id;
        $record1->submission = $submission1->id;
        $record1->video_uid = $videouid1;
        $record1->upload_status = 'ready';
        $record1->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record1);

        // Student 2 submission.
        $this->setUser($this->otherstudent);
        $submission2 = $assign->get_user_submission($this->otherstudent->id, true);
        $videouid2 = 'test-video-uid-multi-2-' . uniqid();

        $record2 = new \stdClass();
        $record2->assignment = $this->assignment->id;
        $record2->submission = $submission2->id;
        $record2->video_uid = $videouid2;
        $record2->upload_status = 'ready';
        $record2->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record2);

        // Verify each student can only access their own video.
        $this->setUser($this->student);
        
        // Student 1 can access video 1.
        try {
            verify_video_access($this->student->id, $submission1->id, $videouid1);
            $access1to1 = true;
        } catch (\moodle_exception $e) {
            $access1to1 = false;
        }
        $this->assertTrue($access1to1);

        // Student 1 cannot access video 2.
        try {
            verify_video_access($this->student->id, $submission2->id, $videouid2);
            $access1to2 = true;
        } catch (\moodle_exception $e) {
            $access1to2 = false;
        }
        $this->assertFalse($access1to2);

        // Verify teacher can access both videos.
        $this->setUser($this->teacher);

        try {
            verify_video_access($this->teacher->id, $submission1->id, $videouid1);
            $teacheraccess1 = true;
        } catch (\moodle_exception $e) {
            $teacheraccess1 = false;
        }
        $this->assertTrue($teacheraccess1);

        try {
            verify_video_access($this->teacher->id, $submission2->id, $videouid2);
            $teacheraccess2 = true;
        } catch (\moodle_exception $e) {
            $teacheraccess2 = false;
        }
        $this->assertTrue($teacheraccess2);

        // Verify correct video UIDs are associated with correct submissions.
        $video1 = $DB->get_record('assignsubmission_cfstream', ['submission' => $submission1->id]);
        $this->assertEquals($videouid1, $video1->video_uid);

        $video2 = $DB->get_record('assignsubmission_cfstream', ['submission' => $submission2->id]);
        $this->assertEquals($videouid2, $video2->video_uid);
    }

    /**
     * Test plugin integration with assignment workflow.
     *
     * Requirements: 10.1, 10.2, 10.3
     */
    public function test_assignment_integration() {
        global $DB;

        $this->setUser($this->student);

        // Create submission with video.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-integration-' . uniqid();

        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->duration = 150;
        $record->file_size = 1536000;
        $record->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record);

        // Get plugin instance.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');

        // Test is_empty returns false for submission with video.
        $isempty = $plugin->is_empty($submission);
        $this->assertFalse($isempty);

        // Test view_summary returns status.
        $showviewlink = true;
        $summary = $plugin->view_summary($submission, $showviewlink);
        $this->assertStringContainsString('Cloudflare Stream', $summary);
        $this->assertStringContainsString('ready', $summary);

        // Test view returns player HTML.
        $view = $plugin->view($submission);
        $this->assertStringContainsString('cloudflarestream-player', $view);
        $this->assertStringContainsString($videouid, $view);

        // Simulate grading by teacher.
        $this->setUser($this->teacher);

        // Teacher can view the submission.
        $teacherview = $plugin->view($submission);
        $this->assertStringContainsString('cloudflarestream-player', $teacherview);

        // Verify grading interface is accessible (standard Moodle functionality).
        $gradeitem = $assign->get_grade_item();
        $this->assertNotEmpty($gradeitem);
    }

    /**
     * Test submission copy functionality.
     *
     * Requirements: 5.1, 5.2
     */
    public function test_submission_copy() {
        global $DB;

        $this->setUser($this->student);

        // Create original submission with video.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $sourcesubmission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-copy-' . uniqid();

        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $sourcesubmission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->duration = 200;
        $record->file_size = 2048000;
        $record->upload_timestamp = time();

        $DB->insert_record('assignsubmission_cfstream', $record);

        // Create destination submission.
        $destsubmission = new \stdClass();
        $destsubmission->assignment = $this->assignment->id;
        $destsubmission->userid = $this->student->id;
        $destsubmission->timecreated = time();
        $destsubmission->timemodified = time();
        $destsubmission->status = 'draft';
        $destsubmission->id = $DB->insert_record('assign_submission', $destsubmission);

        // Copy submission.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $result = $plugin->copy_submission($sourcesubmission, $destsubmission);
        $this->assertTrue($result);

        // Verify copy was created.
        $copiedrecord = $DB->get_record('assignsubmission_cfstream', 
            ['submission' => $destsubmission->id]);
        $this->assertNotEmpty($copiedrecord);
        $this->assertEquals($videouid, $copiedrecord->video_uid);
        $this->assertEquals('ready', $copiedrecord->upload_status);
        $this->assertEquals(200, $copiedrecord->duration);
        $this->assertEquals(2048000, $copiedrecord->file_size);
    }

    /**
     * Test submission removal.
     *
     * Requirements: 5.2, 8.3
     */
    public function test_submission_removal() {
        global $DB;

        $this->setUser($this->student);

        // Create submission with video.
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);

        $videouid = 'test-video-uid-remove-' . uniqid();

        $record = new \stdClass();
        $record->assignment = $this->assignment->id;
        $record->submission = $submission->id;
        $record->video_uid = $videouid;
        $record->upload_status = 'ready';
        $record->upload_timestamp = time();

        $recordid = $DB->insert_record('assignsubmission_cfstream', $record);

        // Verify record exists.
        $exists = $DB->record_exists('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertTrue($exists);

        // Remove submission.
        $plugin = $assign->get_submission_plugin_by_type('cloudflarestream');
        $result = $plugin->remove($submission);
        $this->assertTrue($result);

        // Verify record was removed.
        $existsafter = $DB->record_exists('assignsubmission_cfstream', ['id' => $recordid]);
        $this->assertFalse($existsafter);
    }
}
