# S3 + CloudFront Video Plugin - Implementation Tasks

## Task List

- [x] 1. Set up plugin structure and configuration


  - Create plugin directory structure following Moodle conventions
  - Implement version.php with plugin metadata
  - Create settings.php for AWS credentials configuration
  - Implement encrypted storage for AWS credentials
  - Create language strings file
  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 2. Implement database schema and core plugin class






  - [x] 2.1 Create database schema

    - Write db/install.xml defining tables
    - Create db/upgrade.php for migrations
    - _Requirements: 5.2_
  

  - [x] 2.2 Implement core plugin class

    - Create lib.php with assign_submission_s3video class
    - Implement get_name(), get_settings(), is_enabled()
    - Implement save() method
    - Implement view() method for video player
    - Implement get_form_elements() for upload interface
    - _Requirements: 1.1, 3.1, 5.1_
  

  - [x] 2.3 Create locallib.php

    - Implement plugin detection class
    - _Requirements: 1.1_

- [-] 3. Build AWS S3 client


  - [x] 3.1 Create S3 client class



    - Implement classes/api/s3_client.php
    - Implement get_presigned_post() method
    - Implement object_exists() method
    - Implement delete_object() method
    - Implement get_object_metadata() method
    - Add error handling for AWS API failures
    - _Requirements: 1.3, 1.4, 6.1, 8.3_
  
  - [x] 3.2 Write unit tests for S3 client






    - Create tests/s3_client_test.php
    - Mock AWS responses
    - Test error scenarios
    - _Requirements: 1.3_

- [x] 4. Build CloudFront client




  - [x] 4.1 Create CloudFront client class


    - Implement classes/api/cloudfront_client.php
    - Implement get_signed_url() method
    - Implement create_invalidation() method
    - Add signature generation logic
    - _Requirements: 3.2, 4.2_
  
  - [x] 4.2 Write unit tests for CloudFront client






    - Create tests/cloudfront_client_test.php
    - Test signed URL generation
    - Test invalidation
    - _Requirements: 3.2_

- [x] 5. Implement upload workflow backend






  - [x] 5.1 Create upload URL endpoint

    - Create ajax/get_upload_url.php
    - Verify user authentication and permissions
    - Generate unique S3 key
    - Call s3_client->get_presigned_post()
    - Create database record with status 'pending'
    - Return presigned POST data
    - _Requirements: 1.3, 4.1, 6.1_
  
  - [x] 5.2 Create upload confirmation endpoint


    - Create ajax/confirm_upload.php
    - Accept S3 key from frontend
    - Verify file exists in S3
    - Update database with status 'ready'
    - Store file metadata
    - _Requirements: 1.5, 5.1, 5.2_
  
  - [x] 5.3 Add error logging


    - Implement logging for upload failures
    - Create admin dashboard for statistics
    - _Requirements: 9.1, 9.2, 9.3_

- [x] 6. Build frontend upload interface







  - [x] 6.1 Create upload JavaScript module



    - Implement amd/src/uploader.js
    - Implement file validation (size, type)
    - Implement requestUploadUrl() method
    - Implement direct POST to S3
    - Implement progress tracking
    - Implement error handling with retry
    - Call confirm_upload.php on success
    - _Requirements: 1.1, 1.2, 1.4, 2.1, 2.2, 2.3, 2.4_
  
  - [x] 6.2 Create upload UI template


    - Create templates/upload_form.mustache
    - Add file input with drag-and-drop
    - Add progress bar
    - Add success/error messages
    - _Requirements: 1.1, 2.1, 2.4_
  

  - [x] 6.3 Add CSS styling

    - Create styles.css
    - Style upload interface
    - Style progress bar
    - Ensure responsive design
    - _Requirements: 1.1, 2.1_

- [x] 7. Implement playback workflow backend





  - [x] 7.1 Create playback URL endpoint


    - Create ajax/get_playback_url.php
    - Verify user authentication
    - Implement access control logic
    - Call cloudfront_client->get_signed_url()
    - Log playback access
    - Return signed URL
    - _Requirements: 3.2, 4.1, 4.2, 4.3, 5.3_
  


  - [x] 7.2 Implement access control

    - Create helper function verify_video_access()
    - Check user roles (student/teacher/admin)
    - Verify S3 key matches submission
    - _Requirements: 4.4, 4.5, 5.3, 5.4_

- [x] 8. Build frontend playback interface




  - [x] 8.1 Create player JavaScript module


    - Implement amd/src/player.js
    - Implement getSignedUrl() method
    - Initialize Video.js player
    - Handle token expiration
    - Implement error handling
    - _Requirements: 3.3, 3.4_
  
  - [x] 8.2 Create player UI template


    - Create templates/player.mustache
    - Add Video.js player container
    - Add loading indicator
    - Add error messages
    - _Requirements: 3.3_
  
  - [x] 8.3 Integrate with grading interface


    - Modify view() method in lib.php
    - Render player alongside grading interface
    - Maintain existing grading features
    - _Requirements: 3.1, 3.3, 10.1, 10.2, 10.3_

- [x] 9. Implement video cleanup and retention






  - [x] 9.1 Create scheduled cleanup task

    - Create classes/task/cleanup_videos.php
    - Implement execute() method
    - Find videos older than retention period
    - Call s3_client->delete_object()
    - Call cloudfront_client->create_invalidation()
    - Update database with status 'deleted'
    - Log cleanup results
    - _Requirements: 8.1, 8.2, 8.3, 8.4_
  

  - [x] 9.2 Register scheduled task

    - Create db/tasks.php
    - Register cleanup task (daily at 2 AM)
    - _Requirements: 8.2_
  
  - [x] 9.3 Add manual deletion interface





    - Create videomanagement.php page
    - Add delete confirmation dialog
    - _Requirements: 8.3_

- [x] 10. Implement GDPR compliance




  - [x] 10.1 Create privacy provider


    - Create classes/privacy/provider.php
    - Implement get_metadata()
    - Implement export_user_data()
    - Implement delete_data_for_user()
    - _Requirements: 5.2, 8.3_
  
  - [x] 10.2 Test GDPR workflows






    - Test data export
    - Test user deletion
    - _Requirements: 5.2_

- [x] 11. Add monitoring and analytics






  - [x] 11.1 Implement logging system

    - Create classes/logger.php
    - Log upload events
    - Log playback access
    - Log API errors
    - _Requirements: 9.1, 9.2_
  

  - [x] 11.2 Create admin dashboard

    - Create dashboard.php
    - Display upload statistics
    - Show recent failures
    - Display storage usage
    - Calculate estimated costs
    - _Requirements: 9.3, 9.4_

- [x] 12. Security hardening






  - [x] 12.1 Implement input validation

    - Create classes/validator.php
    - Validate file size, type, S3 keys
    - Sanitize all inputs
    - _Requirements: 4.1, 4.4_
  


  - [x] 12.2 Add rate limiting





    - Create classes/rate_limiter.php
    - Limit upload URL requests
    - Limit playback URL requests
    - _Requirements: 4.2_
  
  - [x] 12.3 Security audit






    - Review for SQL injection
    - Test for XSS vulnerabilities
    - Verify credentials not exposed
    - Test unauthorized access
    - _Requirements: 4.4, 7.4_

- [x] 13. Error handling and user experience






  - [x] 13.1 Implement comprehensive error handling

    - Add try-catch blocks around AWS calls
    - Display user-friendly error messages
    - Provide actionable guidance
    - Log technical details
    - _Requirements: 2.3, 4.4_
  

  - [x] 13.2 Add retry mechanisms

    - Implement automatic retry for transient failures
    - Allow manual retry for failed uploads
    - _Requirements: 2.3_

- [x] 14. Documentation and deployment





  - [x] 14.1 Write user documentation


    - Create README.md with installation instructions
    - Document AWS setup (S3, CloudFront, IAM)
    - Write student user guide
    - Write teacher user guide
    - _Requirements: 7.1_
  
  - [x] 14.2 Write developer documentation


    - Document plugin architecture
    - Create API documentation
    - Document database schema
    - Add inline code comments
    - _Requirements: All_
  


  - [x] 14.3 Create deployment checklist
    - List prerequisites
    - Document AWS account setup
    - Create staging deployment procedure
    - Create production rollout plan
    - _Requirements: All_

- [ ] 15. Testing and quality assurance
  - [ ]* 15.1 Write integration tests
    - Test upload workflow
    - Test playback workflow
    - Test access control
    - _Requirements: All_
  
  - [ ] 15.2 Perform manual testing
    - Test with various file sizes
    - Test on different browsers
    - Test mobile compatibility
    - Test concurrent uploads
    - _Requirements: All_
  
  - [ ]* 15.3 Performance testing
    - Test 5 GB upload
    - Test playback performance
    - Verify minimal server load
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

## Notes

- Tasks marked with `*` are optional (testing/documentation)
- Core functionality tasks must be completed
- Test with AWS free tier before production
- Follow Moodle coding standards throughout
