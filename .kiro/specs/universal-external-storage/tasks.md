# Implementation Plan

- [ ] 1. Set up plugin structure and core repository class
  - Create directory structure for repository plugin
  - Implement version.php with plugin metadata
  - Create main repository class extending Moodle's repository base
  - Implement basic file picker integration methods (get_listing, supported_filetypes, supported_returntypes)
  - _Requirements: 1.1, 1.2, 15.1, 15.2, 15.3, 15.4, 15.5_

- [ ] 2. Implement database schema and models
  - Create install.xml with all required tables (external_files, external_access, external_quota, external_lifecycle, external_audit, external_cache)
  - Define indexes for performance optimization
  - Create external_file model class with CRUD operations
  - Create quota model class with usage tracking methods
  - Implement database upgrade script structure
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ] 3. Create storage provider interface and AWS S3 implementation
  - Define storage_provider_interface with all required methods
  - Implement S3 provider class with AWS SDK integration
  - Implement upload_file method with error handling
  - Implement generate_signed_url method with expiration
  - Implement delete_file, list_files, and get_file_metadata methods
  - Implement test_connection method for credential validation
  - _Requirements: 3.1, 3.4, 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 4. Implement Cloudflare R2 storage provider
  - Create R2 provider class implementing storage_provider_interface
  - Implement S3-compatible API calls for R2
  - Configure R2-specific endpoint and authentication
  - Implement multipart upload for large files
  - Add R2-specific optimizations
  - _Requirements: 3.1, 3.2, 3.3, 13.1, 13.2_

- [ ] 5. Implement security manager and file validation
  - Create security_manager class with validation methods
  - Implement validate_file method with MIME type checking
  - Implement sanitize_filename to prevent path traversal
  - Implement check_file_access with capability checking
  - Implement credential encryption/decryption methods
  - Implement generate_secure_path for file organization
  - _Requirements: 14.1, 14.2, 14.3, 14.4_

- [ ] 6. Implement quota manager and enforcement
  - Create quota_manager class with quota checking methods
  - Implement check_quota method to validate uploads
  - Implement get_user_usage and get_course_usage methods
  - Implement update_usage method for tracking changes
  - Implement quota limit retrieval methods
  - Add quota enforcement to upload workflow
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ] 7. Implement file upload functionality
  - Implement upload method in repository class
  - Add quota check before upload
  - Add security validation before upload
  - Implement direct browser-to-storage upload with presigned URLs
  - Implement upload confirmation and metadata registration
  - Add progress tracking for uploads
  - _Requirements: 1.3, 1.4, 1.5, 13.1, 13.2, 13.3, 13.4, 13.5_


- [ ] 8. Implement file download and access functionality
  - Implement get_file method in repository class
  - Implement signed URL generation with caching
  - Add permission checking before generating URLs
  - Implement URL expiration and regeneration logic
  - Add access logging for audit trail
  - Implement streaming for large file downloads
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 9. Implement cache manager
  - Create cache_manager class with caching methods
  - Implement signed URL caching with expiration
  - Implement file metadata caching
  - Implement thumbnail caching
  - Configure Moodle cache definitions
  - Implement cache invalidation logic
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 10. Implement file browser and listing functionality
  - Implement get_listing method with folder support
  - Add pagination for large file lists
  - Implement folder creation functionality
  - Implement file organization (move, rename)
  - Add breadcrumb navigation for folder hierarchy
  - _Requirements: 1.2, 5.1, 5.2, 5.3, 5.4_

- [ ] 11. Implement search functionality
  - Implement search method in repository class
  - Add filename search with wildcards
  - Add file type filtering
  - Add date range filtering
  - Implement search result pagination
  - _Requirements: 5.5_

- [ ] 12. Implement preview generator
  - Create preview_generator class
  - Implement image thumbnail generation
  - Implement video thumbnail generation (first frame)
  - Implement PDF thumbnail generation (first page)
  - Implement file type icon mapping
  - Add thumbnail caching integration
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 13. Implement access control and permissions
  - Implement access level system (private, course, public)
  - Create external_access table management
  - Implement capability-based access checking
  - Implement context-based file isolation
  - Add access level selection in upload interface
  - Implement access level modification
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ] 14. Implement audit logger
  - Create audit_logger class
  - Implement log_upload method
  - Implement log_access method
  - Implement log_deletion method
  - Implement log_lifecycle_action method
  - Implement get_logs method with filtering
  - Implement export_logs method for CSV/JSON export
  - _Requirements: 2.5, 7.5, 9.5_

- [ ] 15. Implement lifecycle manager
  - Create lifecycle_manager class
  - Implement policy configuration storage
  - Implement evaluate_policies method
  - Implement apply_deletion_policy method
  - Implement apply_archival_policy method
  - Create scheduled task for policy execution
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 16. Implement admin settings interface
  - Create settings.php with provider configuration
  - Add AWS S3 configuration fields
  - Add Cloudflare R2 configuration fields
  - Add quota configuration fields
  - Implement credential encryption in settings
  - Add test connection button functionality
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 17. Implement admin dashboard
  - Create dashboard.php page
  - Implement storage usage overview display
  - Implement usage trend graphs
  - Implement top users and courses display
  - Add cost estimation display
  - Implement detailed reports export
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ] 18. Implement JavaScript for file picker UI
  - Create file browser JavaScript module
  - Implement folder navigation
  - Implement file selection
  - Add thumbnail lazy loading
  - Implement file preview modal
  - Add file action buttons (download, delete, get link)
  - _Requirements: 1.2, 8.1, 8.2, 8.3_

- [ ] 19. Implement JavaScript for file upload
  - Create uploader JavaScript module
  - Implement drag-and-drop upload interface
  - Implement multipart upload with chunking
  - Add upload progress tracking
  - Implement upload resume on failure
  - Add parallel upload for multiple files
  - _Requirements: 1.3, 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 20. Implement AJAX endpoints
  - Create ajax/get_upload_url.php for presigned upload URLs
  - Create ajax/confirm_upload.php for upload completion
  - Create ajax/get_signed_url.php for file access
  - Create ajax/delete_file.php for file deletion
  - Create ajax/create_folder.php for folder creation
  - Create ajax/search_files.php for search functionality
  - _Requirements: 1.3, 1.4, 2.1, 5.2, 5.5_

- [ ] 21. Implement GDPR privacy provider
  - Create privacy provider class
  - Implement get_metadata method
  - Implement get_contexts_for_userid method
  - Implement export_user_data method
  - Implement delete_data_for_all_users_in_context method
  - Implement delete_data_for_user method
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 22. Implement migration tool
  - Create migration_tool class
  - Implement scan_local_files method
  - Implement migrate_file method
  - Implement migrate_batch method with progress tracking
  - Implement generate_migration_report method
  - Create CLI script for migration execution
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ] 23. Implement scheduled tasks
  - Create cleanup_cache scheduled task
  - Create apply_lifecycle_policies scheduled task
  - Create update_quota_stats scheduled task
  - Create generate_reports scheduled task
  - Configure task schedules in db/tasks.php
  - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [ ] 24. Implement retry handler and error handling
  - Create retry_handler class
  - Implement execute_with_retry method
  - Add exponential backoff for retries
  - Implement error response formatting
  - Add comprehensive error logging
  - Implement graceful degradation for non-critical failures
  - _Requirements: 13.2, 13.3, 14.4_

- [ ] 25. Implement language strings
  - Create lang/en/repository_alxuniversalstorage.php
  - Add all UI strings for file picker
  - Add all admin settings strings
  - Add all error messages
  - Add all help text strings
  - Add strings for capabilities
  - _Requirements: All requirements (UI strings)_

- [ ] 26. Implement CSS styling
  - Create styles.css for plugin
  - Style file browser interface
  - Style upload dialog
  - Style admin dashboard
  - Style settings page
  - Ensure responsive design for mobile
  - _Requirements: 1.2, 1.3, 9.1, 9.2, 9.3_

- [ ] 27. Add Google Cloud Storage provider
  - Create GCS provider class implementing storage_provider_interface
  - Implement GCS authentication
  - Implement GCS-specific upload/download methods
  - Add GCS configuration to settings
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 28. Add Azure Blob Storage provider
  - Create Azure provider class implementing storage_provider_interface
  - Implement Azure authentication
  - Implement Azure-specific upload/download methods
  - Add Azure configuration to settings
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 29. Implement capabilities and access control
  - Define capabilities in db/access.php
  - Implement repository:view capability
  - Implement repository:upload capability
  - Implement repository:delete capability
  - Implement repository:manageall capability
  - Add capability checks throughout codebase
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ] 30. Create documentation
  - Create README.md with plugin overview
  - Create INSTALLATION.md with setup instructions
  - Create CONFIGURATION.md with provider setup guides
  - Create USER_GUIDE.md with end-user instructions
  - Create MIGRATION_GUIDE.md for migrating from local storage
  - Create API_DOCUMENTATION.md for developers
  - _Requirements: All requirements (documentation)_

- [ ]* 31. Write unit tests
- [ ]* 31.1 Write tests for storage provider interface implementations
  - Test S3 provider upload/download/delete operations
  - Test R2 provider operations
  - Test signed URL generation
  - Test error handling and retries
  - _Requirements: 3.1, 3.4, 13.2, 13.3, 14.4_

- [ ]* 31.2 Write tests for security manager
  - Test file validation
  - Test filename sanitization
  - Test access control checks
  - Test credential encryption/decryption
  - _Requirements: 14.1, 14.2, 14.3, 14.4_

- [ ]* 31.3 Write tests for quota manager
  - Test quota checking
  - Test usage tracking
  - Test quota enforcement
  - Test concurrent upload scenarios
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 31.4 Write tests for cache manager
  - Test cache storage and retrieval
  - Test cache expiration
  - Test cache invalidation
  - _Requirements: 2.1, 2.2, 2.3_

- [ ]* 31.5 Write tests for lifecycle manager
  - Test policy evaluation
  - Test deletion policy application
  - Test archival policy application
  - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [ ]* 32. Write integration tests
- [ ]* 32.1 Write complete upload flow test
  - Test file upload through repository
  - Verify file in external storage
  - Verify database records
  - Verify quota updates
  - Verify audit logs
  - _Requirements: 1.3, 1.4, 1.5, 4.2, 4.3_

- [ ]* 32.2 Write complete access flow test
  - Test file upload as teacher
  - Test signed URL generation
  - Test file access as student
  - Test access logging
  - Test expired URL handling
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ]* 32.3 Write lifecycle management test
  - Create test files with various ages
  - Configure deletion policy
  - Run scheduled task
  - Verify files deleted
  - Verify audit logs
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ]* 32.4 Write GDPR compliance test
  - Test user data export
  - Test user data deletion
  - Test account deletion cascade
  - _Requirements: 7.1, 7.2, 7.3_

- [ ]* 33. Write performance tests
- [ ]* 33.1 Test large file upload performance
  - Test multipart upload for 500MB+ files
  - Test upload resume functionality
  - Measure upload speed
  - _Requirements: 13.1, 13.2, 13.3_

- [ ]* 33.2 Test concurrent operations
  - Test multiple simultaneous uploads
  - Test quota enforcement under load
  - Test cache performance under load
  - _Requirements: 4.2, 4.3, 13.4_

- [ ]* 33.3 Test scalability
  - Test with 10,000+ files
  - Test file listing performance
  - Test search performance
  - _Requirements: 5.4, 5.5_
