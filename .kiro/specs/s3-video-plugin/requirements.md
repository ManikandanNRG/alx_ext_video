# S3 + CloudFront Video Plugin - Requirements

## Introduction

This document specifies the requirements for a Moodle assignment submission plugin that integrates with AWS S3 and CloudFront to enable large video file uploads (up to 5 GB) with CDN delivery.

## Glossary

- **Moodle System**: The learning management system where students submit assignments
- **S3 Bucket**: AWS Simple Storage Service bucket for video storage
- **CloudFront Distribution**: AWS CDN for video delivery
- **S3 Key**: Unique identifier for objects stored in S3 (path/filename)
- **Presigned POST**: Temporary credentials allowing direct browser upload to S3
- **CloudFront Signed URL**: Time-limited URL with cryptographic signature for secure video access
- **Video.js Player**: Open-source HTML5 video player
- **Assignment Submission Record**: Moodle database entry tracking student submissions

## Requirements

### Requirement 1: Large Video Upload

**User Story:** As a student, I want to upload large video files (up to 5 GB) for my assignments, so that I can submit fieldwork videos without file size limitations.

#### Acceptance Criteria

1. WHEN a student accesses the assignment submission page, THE Moodle System SHALL display a video upload interface
2. WHEN a student selects a video file up to 5 GB, THE Moodle System SHALL accept the file
3. WHEN upload is initiated, THE Moodle System SHALL request presigned POST credentials from AWS S3
4. WHEN presigned POST is received, THE Moodle System SHALL upload video directly from browser to S3 Bucket
5. WHEN upload completes, THE Moodle System SHALL store the S3 Key in the database

### Requirement 2: Upload Progress Tracking

**User Story:** As a student, I want to see real-time upload progress, so that I know the upload is working and can estimate completion time.

#### Acceptance Criteria

1. WHEN video upload is in progress, THE Moodle System SHALL display progress percentage
2. WHILE upload is active, THE Moodle System SHALL update progress in real-time
3. IF upload fails, THEN THE Moodle System SHALL display error message with retry option
4. WHEN upload completes, THE Moodle System SHALL display confirmation message

### Requirement 3: Secure Video Playback

**User Story:** As a teacher, I want to view student video submissions within Moodle's grading interface, so that I can review assignments without leaving the platform.

#### Acceptance Criteria

1. WHEN teacher opens student submission, THE Moodle System SHALL retrieve S3 Key from database
2. WHEN S3 Key is retrieved, THE Moodle System SHALL generate CloudFront Signed URL
3. WHEN Signed URL is generated, THE Moodle System SHALL render Video.js Player with the video
4. WHILE viewing video, THE Moodle System SHALL stream content from CloudFront without using Moodle server bandwidth

### Requirement 4: Access Control

**User Story:** As a system administrator, I want video access restricted to authorized users only, so that student submissions remain private.

#### Acceptance Criteria

1. WHEN playback is requested, THE Moodle System SHALL verify user is authenticated
2. WHEN generating playback URL, THE Moodle System SHALL create CloudFront Signed URL with 24-hour expiration
3. IF unauthorized user attempts access, THEN THE Moodle System SHALL deny access
4. WHEN student views own submission, THE Moodle System SHALL display only their videos

### Requirement 5: Video Metadata Tracking

**User Story:** As a system administrator, I want to track which videos belong to which students and assignments, so that data integrity is maintained.

#### Acceptance Criteria

1. WHEN video upload completes, THE Moodle System SHALL store S3 Key in database
2. WHEN storing S3 Key, THE Moodle System SHALL associate it with user ID, course ID, assignment ID, and timestamp
3. WHEN retrieving video, THE Moodle System SHALL verify S3 Key matches submission context
4. THE Moodle System SHALL prevent cross-access by enforcing role-based permissions

### Requirement 6: Direct Upload (No Server Storage)

**User Story:** As a system administrator, I want videos to bypass Moodle server storage, so that we avoid server load and storage costs.

#### Acceptance Criteria

1. WHEN video upload is initiated, THE Moodle System SHALL transmit video directly from browser to S3 Bucket
2. THE Moodle System SHALL NOT store video data on Moodle server
3. WHEN video is played, THE Moodle System SHALL stream from CloudFront directly to browser
4. THE Moodle System SHALL NOT proxy or cache video content

### Requirement 7: AWS Credentials Configuration

**User Story:** As a system administrator, I want to configure AWS credentials securely, so that the plugin can authenticate with AWS services.

#### Acceptance Criteria

1. THE Moodle System SHALL provide configuration interface for AWS credentials
2. WHEN credentials are saved, THE Moodle System SHALL store them encrypted
3. WHEN making AWS API requests, THE Moodle System SHALL use stored credentials
4. THE Moodle System SHALL NOT expose credentials in client-side code

### Requirement 8: Automatic Video Cleanup

**User Story:** As a system administrator, I want automatic cleanup of old videos, so that storage costs remain manageable.

#### Acceptance Criteria

1. THE Moodle System SHALL provide configurable retention period (default 90 days)
2. WHEN video exceeds retention period, THE Moodle System SHALL identify it for deletion
3. WHEN cleanup task runs, THE Moodle System SHALL delete expired videos from S3
4. WHEN video is deleted, THE Moodle System SHALL update database to reflect deletion

### Requirement 9: Upload Monitoring

**User Story:** As a system administrator, I want to monitor upload success rates and storage usage, so that I can track system performance.

#### Acceptance Criteria

1. WHEN video upload completes, THE Moodle System SHALL log upload status with timestamp
2. WHEN upload fails, THE Moodle System SHALL log error details
3. THE Moodle System SHALL provide admin dashboard displaying upload statistics
4. THE Moodle System SHALL calculate estimated AWS costs based on usage

### Requirement 10: Grading Integration

**User Story:** As a teacher, I want to provide feedback on video submissions within the grading interface, so that I can efficiently grade assignments.

#### Acceptance Criteria

1. WHEN teacher views video submission, THE Moodle System SHALL display standard grading interface alongside player
2. WHEN teacher enters grade or feedback, THE Moodle System SHALL save to database
3. THE Moodle System SHALL maintain all existing Moodle grading features with video submissions
