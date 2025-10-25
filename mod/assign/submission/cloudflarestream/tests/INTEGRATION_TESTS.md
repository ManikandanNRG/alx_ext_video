# Integration Tests Documentation

## Overview

The integration tests in `integration_test.php` provide comprehensive end-to-end testing of the Cloudflare Stream plugin workflows. These tests verify that all components work together correctly and that the plugin integrates seamlessly with Moodle's assignment system.

## Test Coverage

### 1. Complete Upload Workflow (`test_complete_upload_workflow`)

**Requirements Tested:** 1.1, 1.2, 1.3, 1.4, 1.5, 5.1, 5.2

**Workflow Steps:**
1. Create a student submission
2. Request upload URL (simulates `get_upload_url.php`)
3. Create pending database record
4. Simulate video upload to Cloudflare
5. Confirm upload completion (simulates `confirm_upload.php`)
6. Update database with video UID and metadata
7. Verify submission is not empty
8. Verify video player is displayed in view

**Assertions:**
- Submission is created successfully
- Database record is created with pending status
- Record is updated with video UID and ready status
- Video metadata (duration, file size) is stored correctly
- Plugin recognizes submission as non-empty
- View output contains video player and video UID

### 2. Complete Playback Workflow (`test_complete_playback_workflow`)

**Requirements Tested:** 3.1, 3.2, 3.3, 3.4, 4.1, 4.2

**Workflow Steps:**
1. Create submission with ready video
2. Verify user can view their own submission
3. Verify video access is granted
4. Verify player is rendered in view
5. Verify metadata is displayed
6. Verify teacher can also view the submission

**Assertions:**
- Student can view their own submission
- `verify_video_access()` grants access to owner
- Player container is present in HTML output
- Video UID and submission ID are in output
- Duration and file size are formatted and displayed
- Teacher has access to student submissions

### 3. Access Control (`test_access_control`)

**Requirements Tested:** 4.1, 4.2, 4.3, 4.4, 4.5, 5.3, 5.4

**Test Scenarios:**
1. Student can view their own submission ✓
2. Other student CANNOT view the submission ✗
3. Teacher CAN view student submissions ✓
4. Admin CAN view all submissions ✓
5. Invalid video UID is rejected ✗

**Assertions:**
- `can_view_submission()` returns true for owner
- `verify_video_access()` grants access to owner
- Other students are denied access (exception thrown)
- Teachers with grading capability have access
- Site admins have access to all videos
- Invalid video UIDs throw `invalidvideouid` exception

### 4. Upload Workflow with Errors (`test_upload_workflow_with_errors`)

**Requirements Tested:** 2.3, 2.4, 9.1, 9.2

**Test Scenarios:**
1. Upload fails with error status
2. Error message is stored in database
3. Error is displayed in view
4. Retry updates status to pending
5. Successful upload after retry

**Assertions:**
- Error status and message are stored correctly
- View displays error message with alert styling
- Retry clears error message
- Successful upload updates to ready status
- Video UID and metadata are stored after successful retry

### 5. Video Deletion Workflow (`test_video_deletion_workflow`)

**Requirements Tested:** 8.1, 8.2, 8.3, 8.4

**Test Scenarios:**
1. Create video older than retention period
2. Simulate deletion by cleanup task
3. Verify deletion status is recorded
4. Verify appropriate message is displayed

**Assertions:**
- Video status is updated to 'deleted'
- Deletion timestamp is recorded
- View displays "video not available" message
- Warning alert is shown for deleted videos

### 6. Multiple Submissions (`test_multiple_submissions`)

**Requirements Tested:** 5.1, 5.2, 5.3, 5.4

**Test Scenarios:**
1. Create submissions for two different students
2. Verify each student can only access their own video
3. Verify teacher can access both videos
4. Verify correct video UIDs are associated with submissions

**Assertions:**
- Student 1 can access video 1 but not video 2
- Student 2 can access video 2 but not video 1
- Teacher can access both videos
- Video UIDs are correctly associated with submissions
- Cross-access is prevented

### 7. Assignment Integration (`test_assignment_integration`)

**Requirements Tested:** 10.1, 10.2, 10.3

**Test Scenarios:**
1. Create submission with video
2. Test `is_empty()` method
3. Test `view_summary()` method
4. Test `view()` method
5. Verify teacher grading interface

**Assertions:**
- `is_empty()` returns false for submission with video
- `view_summary()` returns status text
- `view()` returns player HTML
- Teacher can view submission
- Grading interface is accessible

### 8. Submission Copy (`test_submission_copy`)

**Requirements Tested:** 5.1, 5.2

**Test Scenarios:**
1. Create source submission with video
2. Create destination submission
3. Copy submission using plugin method
4. Verify copy was created correctly

**Assertions:**
- `copy_submission()` returns true
- Copied record exists in database
- Video UID is copied correctly
- All metadata is copied (status, duration, file size)

### 9. Submission Removal (`test_submission_removal`)

**Requirements Tested:** 5.2, 8.3

**Test Scenarios:**
1. Create submission with video
2. Remove submission using plugin method
3. Verify database record is deleted

**Assertions:**
- Record exists before removal
- `remove()` returns true
- Record does not exist after removal

## Running the Tests

### Prerequisites

1. Moodle test environment must be configured
2. PHPUnit must be initialized: `php admin/tool/phpunit/cli/init.php`
3. Database must be configured for testing

### Running All Integration Tests

```bash
vendor/bin/phpunit mod/assign/submission/cloudflarestream/tests/integration_test.php
```

### Running Specific Test

```bash
vendor/bin/phpunit --filter test_complete_upload_workflow mod/assign/submission/cloudflarestream/tests/integration_test.php
```

## Test Environment Setup

Each test uses `setUp()` to create:
- A test course
- Three users: student1, teacher1, student2
- An assignment with cloudflarestream plugin enabled
- Plugin configuration (API token, account ID, retention days, max file size)

Each test uses `resetAfterTest()` to ensure database is cleaned up after each test.

## Key Testing Patterns

### 1. User Context Switching

Tests switch between different user contexts to verify access control:

```php
$this->setUser($this->student);   // Test as student
$this->setUser($this->teacher);   // Test as teacher
$this->setAdminUser();             // Test as admin
```

### 2. Database Record Verification

Tests verify database operations:

```php
$record = $DB->get_record('assignsubmission_cfstream', ['id' => $recordid]);
$this->assertEquals('ready', $record->upload_status);
```

### 3. Exception Testing

Tests verify that exceptions are thrown for unauthorized access:

```php
try {
    verify_video_access($this->otherstudent->id, $submission->id, $videouid);
    $accessgranted = true;
} catch (\moodle_exception $e) {
    $accessgranted = false;
    $this->assertEquals('nopermissiontoviewvideo', $e->errorcode);
}
$this->assertFalse($accessgranted);
```

### 4. HTML Output Verification

Tests verify that correct HTML is generated:

```php
$output = $plugin->view($submission);
$this->assertStringContainsString('cloudflarestream-player', $output);
$this->assertStringContainsString($videouid, $output);
```

## Coverage Summary

| Requirement Category | Coverage |
|---------------------|----------|
| Upload Workflow | ✓ Complete |
| Playback Workflow | ✓ Complete |
| Access Control | ✓ Complete |
| Error Handling | ✓ Complete |
| Video Lifecycle | ✓ Complete |
| Assignment Integration | ✓ Complete |
| Data Integrity | ✓ Complete |

## Notes

- These tests do NOT make actual API calls to Cloudflare
- Tests simulate the complete workflow using database operations
- Tests verify the integration between plugin components and Moodle core
- Tests ensure proper access control and security
- Tests verify error handling and edge cases

## Future Enhancements

Potential additional tests:
- Performance testing with large numbers of submissions
- Concurrent upload testing
- Rate limiting integration tests
- GDPR compliance integration tests
- Cleanup task integration tests
