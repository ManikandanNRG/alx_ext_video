# Requirements Document

## Introduction

This document defines the requirements for ALX Universal Storage - a Repository Plugin for Moodle. The plugin will integrate with Moodle's native file picker system to enable users to upload, browse, and manage any file type in external storage providers (AWS S3, Cloudflare R2, Google Cloud Storage, Azure Blob Storage). Unlike specialized video-only plugins, this repository plugin will work across all Moodle activities (assignments, resources, forums, quizzes, etc.) and support all file types while reducing local storage and bandwidth costs.

**Plugin Name:** ALX Universal Storage  
**Plugin Type:** Repository Plugin  
**Technical Name:** repository_alxuniversalstorage  
**Location:** repository/alxuniversalstorage/

## Glossary

- **Repository Plugin**: A Moodle plugin type that integrates with the file picker to provide file storage and retrieval capabilities
- **File Picker**: Moodle's native interface for selecting and uploading files across all activities
- **External Storage Provider**: Cloud storage services like AWS S3, Cloudflare R2, Google Cloud Storage, or Azure Blob Storage
- **Storage Provider Interface**: An abstraction layer that defines common operations (upload, download, delete, list) for different storage backends
- **Signed URL**: A time-limited, secure URL that grants temporary access to a private file in external storage
- **Content Hash**: Moodle's SHA1 hash used to uniquely identify file content
- **File System Integration**: Moodle's core file storage abstraction layer
- **Context**: Moodle's permission boundary (system, course, module, user, etc.)
- **Capability**: Moodle's permission system for controlling user actions
- **Quota Management**: System for limiting storage usage per user, course, or organization
- **Lifecycle Policy**: Automated rules for archiving or deleting old files

## Requirements

### Requirement 1

**User Story:** As a Moodle teacher, I want to upload any file type to external storage through the standard file picker, so that I can reduce server storage costs while maintaining a familiar workflow

#### Acceptance Criteria

1. WHEN a teacher clicks the file picker in any Moodle activity, THE Repository Plugin SHALL display "External Storage" as an available repository option
2. WHEN a teacher selects "External Storage" from the file picker, THE Repository Plugin SHALL display a file browser interface showing existing external files organized by folders
3. WHEN a teacher clicks "Upload New File" in the external storage browser, THE Repository Plugin SHALL accept any file type (documents, images, videos, audio, archives, code files)
4. WHEN a teacher uploads a file, THE Repository Plugin SHALL transfer the file directly to the configured external storage provider without storing it locally
5. WHEN the upload completes, THE Repository Plugin SHALL register the file in Moodle's file system with a reference to the external location

### Requirement 2

**User Story:** As a Moodle student, I want to access files stored in external storage seamlessly, so that I can view or download course materials without noticing they are stored externally

#### Acceptance Criteria

1. WHEN a student clicks a link to an externally stored file, THE Repository Plugin SHALL generate a signed URL with appropriate expiration time
2. WHEN a student accesses an externally stored image or video, THE Repository Plugin SHALL display a preview or thumbnail without requiring download
3. IF the signed URL expires, THEN THE Repository Plugin SHALL automatically regenerate a new signed URL when the student refreshes the page
4. WHEN a student downloads an externally stored file, THE Repository Plugin SHALL stream the file directly from external storage to the student's browser
5. WHILE a file is being accessed, THE Repository Plugin SHALL log the access event for audit purposes

### Requirement 3

**User Story:** As a Moodle administrator, I want to configure multiple external storage providers, so that I can choose the most cost-effective solution for different file types or courses

#### Acceptance Criteria

1. WHEN an administrator accesses plugin settings, THE Repository Plugin SHALL provide configuration options for AWS S3, Cloudflare R2, Google Cloud Storage, and Azure Blob Storage
2. WHERE multiple storage providers are configured, THE Repository Plugin SHALL allow the administrator to set a default provider
3. WHERE multiple storage providers are configured, THE Repository Plugin SHALL allow per-course or per-context storage provider selection
4. WHEN an administrator saves storage provider credentials, THE Repository Plugin SHALL validate the credentials by attempting a test connection
5. WHEN an administrator enables a storage provider, THE Repository Plugin SHALL create necessary bucket structures and access policies automatically

### Requirement 4

**User Story:** As a Moodle administrator, I want to set storage quotas per user and per course, so that I can control external storage costs and prevent abuse

#### Acceptance Criteria

1. WHEN an administrator configures quota settings, THE Repository Plugin SHALL accept quota limits in megabytes or gigabytes for users and courses
2. WHEN a user attempts to upload a file, THE Repository Plugin SHALL check if the upload would exceed their quota limit
3. IF a quota would be exceeded, THEN THE Repository Plugin SHALL reject the upload and display a clear error message indicating the quota limit
4. WHEN an administrator views quota reports, THE Repository Plugin SHALL display current usage statistics per user, per course, and system-wide
5. WHERE a course quota is set, THE Repository Plugin SHALL aggregate all files uploaded by teachers and students in that course context

### Requirement 5

**User Story:** As a Moodle teacher, I want to browse and organize files in external storage with folders, so that I can maintain a structured file library

#### Acceptance Criteria

1. WHEN a teacher opens the external storage browser, THE Repository Plugin SHALL display files organized in a hierarchical folder structure
2. WHEN a teacher clicks "Create Folder", THE Repository Plugin SHALL create a new folder in the current directory path
3. WHEN a teacher uploads a file, THE Repository Plugin SHALL allow selection of the destination folder
4. WHEN a teacher selects a file, THE Repository Plugin SHALL provide options to move, rename, or delete the file
5. WHEN a teacher searches for files, THE Repository Plugin SHALL search by filename, file type, or upload date across all folders

### Requirement 6

**User Story:** As a Moodle administrator, I want files to be automatically cleaned up based on lifecycle policies, so that I can reduce storage costs for unused or old files

#### Acceptance Criteria

1. WHEN an administrator configures lifecycle policies, THE Repository Plugin SHALL accept rules based on file age, last access date, or file type
2. WHEN a scheduled task runs, THE Repository Plugin SHALL identify files matching lifecycle policy criteria
3. WHEN a file matches a deletion policy, THE Repository Plugin SHALL delete the file from external storage and update Moodle's file registry
4. WHEN a file matches an archival policy, THE Repository Plugin SHALL move the file to a lower-cost storage tier if the provider supports it
5. WHEN files are deleted by lifecycle policies, THE Repository Plugin SHALL log the deletion events for audit purposes

### Requirement 7

**User Story:** As a Moodle administrator, I want to ensure data privacy and GDPR compliance, so that user files are handled according to privacy regulations

#### Acceptance Criteria

1. WHEN a user requests data export, THE Repository Plugin SHALL include all files uploaded by that user in the export package
2. WHEN a user requests data deletion, THE Repository Plugin SHALL delete all files uploaded by that user from external storage
3. WHEN a user's account is deleted, THE Repository Plugin SHALL automatically delete all associated files from external storage
4. WHEN an administrator configures data retention policies, THE Repository Plugin SHALL enforce minimum and maximum retention periods
5. WHEN files contain personal data, THE Repository Plugin SHALL encrypt file metadata in the Moodle database

### Requirement 8

**User Story:** As a Moodle teacher, I want to see file previews and thumbnails in the file picker, so that I can quickly identify the correct file

#### Acceptance Criteria

1. WHEN a teacher browses external storage, THE Repository Plugin SHALL display thumbnail previews for image files
2. WHEN a teacher browses external storage, THE Repository Plugin SHALL display file type icons for non-image files
3. WHEN a teacher hovers over a file, THE Repository Plugin SHALL display a tooltip with file metadata (size, upload date, uploader)
4. WHERE a video file is stored, THE Repository Plugin SHALL generate and cache a thumbnail from the first frame
5. WHERE a PDF file is stored, THE Repository Plugin SHALL generate and cache a thumbnail from the first page

### Requirement 9

**User Story:** As a Moodle administrator, I want to monitor storage usage and costs, so that I can optimize spending and identify high-usage users or courses

#### Acceptance Criteria

1. WHEN an administrator accesses the storage dashboard, THE Repository Plugin SHALL display total storage used across all providers
2. WHEN an administrator accesses the storage dashboard, THE Repository Plugin SHALL display storage usage trends over time with graphs
3. WHEN an administrator accesses the storage dashboard, THE Repository Plugin SHALL display top users and courses by storage consumption
4. WHEN an administrator accesses the storage dashboard, THE Repository Plugin SHALL display estimated monthly costs based on provider pricing
5. WHEN an administrator exports usage reports, THE Repository Plugin SHALL generate CSV reports with detailed file-level usage data

### Requirement 10

**User Story:** As a Moodle developer, I want to extend the plugin with custom storage providers, so that I can integrate with proprietary or specialized storage systems

#### Acceptance Criteria

1. WHEN a developer creates a custom storage provider, THE Repository Plugin SHALL provide a documented PHP interface to implement
2. WHEN a developer registers a custom storage provider, THE Repository Plugin SHALL automatically detect and list it in admin settings
3. WHEN a custom storage provider is selected, THE Repository Plugin SHALL use the provider's implementation for all file operations
4. WHEN a custom storage provider throws an exception, THE Repository Plugin SHALL handle the error gracefully and log detailed error information
5. WHERE a custom storage provider requires additional configuration fields, THE Repository Plugin SHALL dynamically render the configuration form

### Requirement 11

**User Story:** As a Moodle teacher, I want to share files with specific users or groups, so that I can control access to sensitive materials

#### Acceptance Criteria

1. WHEN a teacher uploads a file, THE Repository Plugin SHALL provide access level options (private, course, public)
2. WHERE a file is marked as private, THE Repository Plugin SHALL generate signed URLs only for the file owner
3. WHERE a file is marked as course-level, THE Repository Plugin SHALL generate signed URLs only for users enrolled in the course
4. WHERE a file is marked as public, THE Repository Plugin SHALL generate signed URLs for any authenticated Moodle user
5. WHEN a teacher changes file access level, THE Repository Plugin SHALL immediately enforce the new access restrictions

### Requirement 12

**User Story:** As a Moodle administrator, I want to migrate existing local files to external storage, so that I can reduce server storage usage for existing content

#### Acceptance Criteria

1. WHEN an administrator runs the migration tool, THE Repository Plugin SHALL scan Moodle's local file storage for eligible files
2. WHEN the migration tool identifies a file, THE Repository Plugin SHALL upload the file to external storage and update the file registry
3. WHEN a file is successfully migrated, THE Repository Plugin SHALL optionally delete the local copy to free disk space
4. IF a migration fails, THEN THE Repository Plugin SHALL log the error and continue with remaining files without stopping the process
5. WHEN the migration completes, THE Repository Plugin SHALL generate a summary report showing migrated files, failures, and space saved

### Requirement 13

**User Story:** As a Moodle user, I want file uploads to be fast and reliable, so that I can upload large files without timeouts or failures

#### Acceptance Criteria

1. WHEN a user uploads a file larger than 100MB, THE Repository Plugin SHALL use multipart upload to split the file into chunks
2. WHEN a chunk upload fails, THE Repository Plugin SHALL automatically retry the failed chunk up to three times
3. WHEN a user's network connection is interrupted, THE Repository Plugin SHALL resume the upload from the last successful chunk
4. WHEN a user uploads multiple files, THE Repository Plugin SHALL upload them in parallel to reduce total upload time
5. WHILE a file is uploading, THE Repository Plugin SHALL display a progress bar showing percentage complete and estimated time remaining

### Requirement 14

**User Story:** As a Moodle administrator, I want to ensure security best practices, so that external storage credentials and file access are protected

#### Acceptance Criteria

1. WHEN an administrator enters storage credentials, THE Repository Plugin SHALL encrypt the credentials before storing them in the database
2. WHEN the plugin generates signed URLs, THE Repository Plugin SHALL set expiration times between 5 minutes and 24 hours based on file type
3. WHEN the plugin accesses external storage, THE Repository Plugin SHALL use IAM roles or service accounts instead of long-lived API keys where possible
4. WHEN a file is uploaded, THE Repository Plugin SHALL scan the filename and content for malicious patterns or executable code
5. WHEN rate limiting is enabled, THE Repository Plugin SHALL limit file operations to prevent abuse or denial-of-service attacks

### Requirement 15

**User Story:** As a Moodle teacher, I want to use external files in any Moodle activity, so that I have flexibility in how I deliver course content

#### Acceptance Criteria

1. WHEN a teacher creates an assignment, THE Repository Plugin SHALL appear as a file source option in the file picker
2. WHEN a teacher creates a resource (file, folder, page), THE Repository Plugin SHALL appear as a file source option in the file picker
3. WHEN a teacher posts in a forum, THE Repository Plugin SHALL appear as a file source option for attachments
4. WHEN a teacher creates a quiz question with media, THE Repository Plugin SHALL appear as a file source option in the file picker
5. WHEN a teacher embeds an image in the text editor, THE Repository Plugin SHALL appear as a file source option in the image dialog
