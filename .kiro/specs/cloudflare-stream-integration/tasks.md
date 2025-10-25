# Implementation Plan

- [x] 1. Set up plugin structure and configuration





  - Create the plugin directory structure following Moodle's assignment submission plugin conventions
  - Implement version.php with plugin metadata and dependencies
  - Create settings.php for admin configuration (API token, account ID, retention days, max file size)
  - Implement encrypted storage for Cloudflare API credentials using Moodle's encryption API
  - Create language strings file (en/assignsubmission_cloudflarestream.php) for all UI text
  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 2. Implement database schema and core plugin class






  - [x] 2.1 Create database schema

    - Write db/install.xml defining mdl_assignsubmission_cfstream table with all required fields
    - Create db/upgrade.php for future schema migrations
    - _Requirements: 5.2_


  - [x] 2.2 Implement core plugin class

    - Create lib.php with assign_submission_cloudflarestream class extending assign_submission_plugin
    - Implement get_name(), get_settings(), is_enabled() methods
    - Implement save() method to handle submission data
    - Implement view() method to display video player in submission view
    - Implement get_form_elements() to add upload interface to submission form
    - _Requirements: 1.1, 3.1, 5.1_

- [x] 3. Build Cloudflare API client






  - [x] 3.1 Create API client class



    - Implement classes/api/cloudflare_client.php with constructor accepting API token and account ID
    - Implement get_direct_upload_url() method calling POST /stream/direct_upload endpoint
    - Implement get_video_details() method calling GET /stream/{video_uid} endpoint
    - Implement delete_video() method calling DELETE /stream/{video_uid} endpoint
    - Implement generate_signed_token() method calling POST /stream/{video_uid}/token endpoint
    - Add error handling for API failures with specific exception types
    - _Requirements: 1.3, 3.2, 4.2, 8.3_

  - [x] 3.2 Write unit tests for API client






    - Create tests/cloudflare_client_test.php with PHPUnit test cases
    - Mock API responses for all client methods
    - Test error handling scenarios (network failures, invalid credentials, quota exceeded)
    - _Requirements: 1.3, 3.2_

- [x] 4. Implement upload workflow backend






  - [x] 4.1 Create upload URL endpoint

    - Create ajax/get_upload_url.php to handle upload URL requests
    - Verify user is authenticated and has permission to submit assignment
    - Call cloudflare_client->get_direct_upload_url() and return URL to frontend
    - Create database record with status 'pending'
    - _Requirements: 1.3, 4.1_


  - [x] 4.2 Create upload confirmation endpoint

    - Create ajax/confirm_upload.php to handle upload completion notifications
    - Accept video UID from frontend
    - Update database record with video UID and status 'ready'
    - Fetch video details from Cloudflare (duration, file size) and store in database
    - _Requirements: 1.5, 5.1, 5.2_

  - [x] 4.3 Add error logging and monitoring






    - Implement logging for upload failures with error details
    - Create admin dashboard page showing upload statistics
    - _Requirements: 9.1, 9.2, 9.3_

- [x] 5. Build frontend upload interface





  - [x] 5.1 Create upload JavaScript module


    - Implement amd/src/uploader.js with CloudflareUploader class
    - Implement file selection and client-side validation (file size ≤ 5GB, video MIME types)
    - Implement requestUploadUrl() method calling get_upload_url.php via AJAX
    - Integrate tus-js-client library for resumable uploads to Cloudflare
    - Implement progress tracking with percentage calculation
    - Implement error handling with user-friendly messages and retry logic
    - Call confirm_upload.php when upload completes successfully
    - _Requirements: 1.1, 1.2, 1.4, 2.1, 2.2, 2.3, 2.4_

  - [x] 5.2 Create upload UI template


    - Create templates/upload_form.mustache with file input and progress bar
    - Add drag-and-drop support for file selection
    - Display upload progress with percentage and estimated time remaining
    - Show success/error messages based on upload status
    - _Requirements: 1.1, 2.1, 2.4_

  - [x] 5.3 Add CSS styling


    - Create styles.css with styling for upload interface
    - Style progress bar with smooth animations
    - Ensure responsive design for mobile devices
    - _Requirements: 1.1, 2.1_

- [x] 6. Implement playback workflow backend





  - [x] 6.1 Create signed token endpoint


    - Create ajax/get_playback_token.php to handle token requests
    - Verify user is authenticated via Moodle session
    - Implement can_view_submission() function checking if user is submission owner or authorized grader
    - Call cloudflare_client->generate_signed_token() with 24-hour expiry
    - Log video access for audit trail
    - Return signed token to frontend
    - _Requirements: 3.2, 4.1, 4.2, 4.3, 4.4, 5.3_

  - [x] 6.2 Implement access control logic


    - Create helper function verify_video_access($user_id, $submission_id, $video_uid)
    - Check that video UID matches the submission record
    - Verify user has appropriate role (student owner, teacher, or admin)
    - Throw permission exception if access denied
    - _Requirements: 4.4, 4.5, 5.3, 5.4_

- [x] 7. Build frontend playback interface






  - [x] 7.1 Create player JavaScript module

    - Implement amd/src/player.js with CloudflarePlayer class
    - Implement getSignedToken() method calling get_playback_token.php via AJAX
    - Implement embedPlayer() method rendering Cloudflare Stream iframe player
    - Handle token expiration by automatically requesting new token and reloading player
    - Implement error handling for playback failures
    - _Requirements: 3.3, 3.4_


  - [x] 7.2 Create player UI template

    - Create templates/player.mustache with container for embedded player
    - Add loading indicator while token is being fetched
    - Display error messages if playback fails
    - _Requirements: 3.3_

  - [x] 7.3 Integrate player with grading interface


    - Modify view() method in lib.php to render player template
    - Ensure player displays alongside standard Moodle grading interface
    - Maintain all existing grading features (comments, rubrics, grade scales)
    - _Requirements: 3.1, 3.3, 10.1, 10.2, 10.3_

- [x] 8. Implement video cleanup and retention





  - [x] 8.1 Create scheduled cleanup task


    - Create classes/task/cleanup_videos.php extending \core\task\scheduled_task
    - Implement execute() method to find videos older than retention period
    - Call cloudflare_client->delete_video() for each expired video
    - Update database records with status 'deleted' and deleted_timestamp
    - Log cleanup results (number of videos deleted, any failures)
    - _Requirements: 8.1, 8.2, 8.3, 8.4_


  - [x] 8.2 Register scheduled task

    - Create db/tasks.php registering cleanup_videos task to run daily at 2 AM
    - _Requirements: 8.2_

  - [x] 8.3 Add manual deletion interface






    - Create admin page for manually deleting specific videos
    - Add confirmation dialog before deletion
    - _Requirements: 8.3_

- [x] 9. Implement GDPR compliance









  - [x] 9.1 Create privacy provider






    - Create classes/privacy/provider.php implementing required privacy interfaces
    - Implement get_metadata() describing what data is stored
    - Implement export_user_data() to export video metadata for data requests
    - Implement delete_data_for_user() to delete user's videos from Cloudflare and database
    - _Requirements: 5.2, 8.3_

  - [x] 9.2 Test GDPR workflows






    - Test data export functionality
    - Test user deletion removes all associated videos
    - _Requirements: 5.2_

- [x] 10. Add monitoring and analytics





  - [x] 10.1 Implement logging system


    - Create logging functions for upload events (success, failure, retry)
    - Log playback access with user ID, video UID, and timestamp
    - Log API errors with full context for troubleshooting
    - _Requirements: 9.1, 9.2_

  - [x] 10.2 Create admin dashboard


    - Create admin page displaying upload statistics (success rate, failure rate, total uploads)
    - Show recent upload failures with error details
    - Display storage usage and estimated costs
    - Add link to Cloudflare Stream analytics dashboard
    - _Requirements: 9.3, 9.4_

- [x] 11. Security hardening





  - [x] 11.1 Implement input validation


    - Validate all user inputs (file size, MIME types, video UIDs)
    - Sanitize all data before database insertion
    - Validate API responses from Cloudflare before processing
    - _Requirements: 4.1, 4.4_

  - [x] 11.2 Add rate limiting


    - Implement rate limiting for upload URL requests to prevent abuse
    - Add rate limiting for playback token requests
    - _Requirements: 4.2_

  - [x] 11.3 Security audit






    - Review all code for SQL injection vulnerabilities
    - Test for XSS vulnerabilities in templates
    - Verify API token is never exposed in client-side code
    - Test unauthorized access scenarios
    - _Requirements: 4.4, 7.4_

- [x] 12. Error handling and user experience







  - [x] 12.1 Implement comprehensive error handling



    - Add try-catch blocks around all API calls
    - Display user-friendly error messages for common failures
    - Provide actionable guidance (e.g., "Check your internet connection and try again")
    - Log technical details for admin troubleshooting
    - _Requirements: 2.3, 4.4_

  - [x] 12.2 Add retry mechanisms


    - Implement automatic retry for transient API failures
    - Allow users to manually retry failed uploads
    - Handle network interruptions gracefully with resumable uploads
    - _Requirements: 2.3_

- [x] 13. Documentation and deployment





  - [x] 13.1 Write user documentation


    - Create README.md with installation instructions
    - Document configuration steps (obtaining Cloudflare API token, setting up account)
    - Write user guide for students (how to upload videos)
    - Write user guide for teachers (how to view and grade video submissions)
    - _Requirements: 7.1_

  - [x] 13.2 Write developer documentation


    - Document plugin architecture and code structure
    - Create API documentation for all public methods
    - Document database schema and relationships
    - Add inline code comments for complex logic
    - _Requirements: All_

  - [x] 13.3 Create deployment checklist





    - List prerequisites (Moodle version, PHP version, HTTPS requirement)
    - Document Cloudflare account setup steps
    - Create staging deployment procedure
    - Create production rollout plan with rollback strategy
    - _Requirements: All_

- [x] 14. Testing and quality assurance





  - [x] 14.1 Write integration tests






    - Test complete upload workflow (request URL → upload → confirm)
    - Test complete playback workflow (request token → embed player)
    - Test access control (unauthorized access denied, authorized access granted)
    - _Requirements: All_

  - [x] 14.2 Perform manual testing




    - Test upload with various file sizes (100 MB, 1 GB, 5 GB)
    - Test upload interruption and resume
    - Test playback on different browsers (Chrome, Firefox, Safari, Edge)
    - Test mobile device compatibility
    - Test concurrent uploads from multiple users
    - _Requirements: All_

  - [ ]* 14.3 Performance testing
    - Test upload performance with 5 GB file
    - Test playback performance and CDN delivery
    - Verify Moodle server load remains minimal during uploads and playback
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
