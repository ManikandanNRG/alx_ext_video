# Design Document

## Overview

**ALX Universal Storage** is a Moodle repository plugin that integrates with Moodle's native file picker system to provide seamless external storage capabilities across all Moodle activities.

**Plugin Details:**
- Name: ALX Universal Storage
- Type: Repository Plugin
- Technical Name: repository_alxuniversalstorage
- Location: repository/alxuniversalstorage/
- Namespace: repository_alxuniversalstorage

The Universal External Storage Repository Plugin The plugin uses a provider-based architecture to support multiple cloud storage backends (AWS S3, Cloudflare R2, Google Cloud Storage, Azure Blob Storage) while presenting a unified interface to users.

The design follows Moodle's repository plugin architecture and extends it with advanced features like quota management, lifecycle policies, file previews, and comprehensive security controls. The plugin operates as a bridge between Moodle's file system and external storage providers, maintaining file metadata locally while storing actual file content externally.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     MOODLE CORE                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Assignments  │  │  Resources   │  │   Forums     │  ...    │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
│         │                  │                  │                 │
│         └──────────────────┴──────────────────┘                 │
│                            │                                    │
│                   ┌────────▼────────┐                          │
│                   │   File Picker    │                          │
│                   └────────┬────────┘                          │
└────────────────────────────┼─────────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────────┐
│          EXTERNAL STORAGE REPOSITORY PLUGIN                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              Repository Interface Layer                   │   │
│  │  • File Browser  • Upload Handler  • Download Handler    │   │
│  └──────────────────────────┬───────────────────────────────┘   │
│                             │                                    │
│  ┌──────────────────────────▼───────────────────────────────┐   │
│  │              Core Services Layer                          │   │
│  │  ┌─────────────┐ ┌──────────────┐ ┌─────────────────┐   │   │
│  │  │   Quota     │ │   Security   │ │    Preview      │   │   │
│  │  │  Manager    │ │   Manager    │ │   Generator     │   │   │
│  │  └─────────────┘ └──────────────┘ └─────────────────┘   │   │
│  │  ┌─────────────┐ ┌──────────────┐ ┌─────────────────┐   │   │
│  │  │  Lifecycle  │ │    Cache     │ │     Audit       │   │   │
│  │  │  Manager    │ │   Manager    │ │     Logger      │   │   │
│  │  └─────────────┘ └──────────────┘ └─────────────────┘   │   │
│  └──────────────────────────┬───────────────────────────────┘   │
│                             │                                    │
│  ┌──────────────────────────▼───────────────────────────────┐   │
│  │         Storage Provider Abstraction Layer               │   │
│  │              (StorageProviderInterface)                   │   │
│  └──────────────────────────┬───────────────────────────────┘   │
│         ┌──────────────┬────┴────┬──────────────┐              │
│         │              │         │              │              │
│  ┌──────▼──────┐ ┌────▼─────┐ ┌─▼──────────┐ ┌─▼──────────┐   │
│  │  S3 Provider│ │R2 Provider│ │GCS Provider│ │Azure Blob  │   │
│  └──────┬──────┘ └────┬─────┘ └─┬──────────┘ └─┬──────────┘   │
└─────────┼─────────────┼──────────┼──────────────┼──────────────┘
          │             │          │              │
┌─────────▼─────────────▼──────────▼──────────────▼──────────────┐
│              EXTERNAL STORAGE PROVIDERS                         │
│     [AWS S3]    [Cloudflare R2]    [Google Cloud]    [Azure]   │
└─────────────────────────────────────────────────────────────────┘
```


### Component Interaction Flow

**File Upload Flow:**
```
User → File Picker → Repository Plugin → Quota Check → Security Validation 
→ Storage Provider → External Storage → Metadata Registry → Cache Update 
→ Success Response → User
```

**File Access Flow:**
```
User → File Link → Repository Plugin → Permission Check → Signed URL Generation 
→ Cache Lookup → Storage Provider → Signed URL → User Browser → External Storage 
→ File Stream → User
```

**Lifecycle Management Flow:**
```
Scheduled Task → Lifecycle Manager → Policy Evaluation → File Identification 
→ Storage Provider → Delete/Archive → Metadata Update → Audit Log → Completion
```

## Components and Interfaces

### 1. Repository Plugin Core (`repository_alxuniversalstorage`)

**Main Class:** `repository/alxuniversalstorage/lib.php`

```php
class repository_alxuniversalstorage extends repository {
    
    /**
     * Display file listing in file picker
     * @param string $path Current directory path
     * @param string $page Pagination parameter
     * @return array File listing structure
     */
    public function get_listing($path = '', $page = '');
    
    /**
     * Download file from external storage
     * @param string $url File reference URL
     * @param string $filename Destination filename
     * @return array File information
     */
    public function get_file($url, $filename = '');
    
    /**
     * Upload file to external storage
     * @param string $saveas_filename Target filename
     * @param int $maxbytes Maximum file size
     * @return array Upload result
     */
    public function upload($saveas_filename, $maxbytes);
    
    /**
     * Search files in external storage
     * @param string $search_text Search query
     * @return array Search results
     */
    public function search($search_text, $page = 0);
    
    /**
     * Check if repository supports file types
     * @return string Supported types (*)
     */
    public function supported_filetypes();
    
    /**
     * Check if repository supports returning files
     * @return int Return types supported
     */
    public function supported_returntypes();
}
```


### 2. Storage Provider Interface

**Interface:** `classes/providers/storage_provider_interface.php`

```php
interface storage_provider_interface {
    
    /**
     * Upload file to storage
     * @param string $localpath Local file path
     * @param string $remotepath Remote storage path
     * @param array $options Upload options (metadata, acl, etc.)
     * @return array Upload result with URL and metadata
     */
    public function upload_file($localpath, $remotepath, $options = []);
    
    /**
     * Upload file using multipart for large files
     * @param string $localpath Local file path
     * @param string $remotepath Remote storage path
     * @param int $chunksize Chunk size in bytes
     * @return array Upload result
     */
    public function upload_multipart($localpath, $remotepath, $chunksize = 5242880);
    
    /**
     * Generate signed URL for file access
     * @param string $remotepath Remote storage path
     * @param int $expiry Expiration time in seconds
     * @param array $options Additional options
     * @return string Signed URL
     */
    public function generate_signed_url($remotepath, $expiry = 3600, $options = []);
    
    /**
     * Delete file from storage
     * @param string $remotepath Remote storage path
     * @return bool Success status
     */
    public function delete_file($remotepath);
    
    /**
     * List files in directory
     * @param string $prefix Directory prefix
     * @param int $maxkeys Maximum results
     * @param string $marker Pagination marker
     * @return array File listing
     */
    public function list_files($prefix = '', $maxkeys = 1000, $marker = '');
    
    /**
     * Get file metadata
     * @param string $remotepath Remote storage path
     * @return array File metadata (size, modified, etag, etc.)
     */
    public function get_file_metadata($remotepath);
    
    /**
     * Copy file within storage
     * @param string $sourcepath Source path
     * @param string $destpath Destination path
     * @return bool Success status
     */
    public function copy_file($sourcepath, $destpath);
    
    /**
     * Move file to different storage tier (archive)
     * @param string $remotepath Remote storage path
     * @param string $tier Storage tier (standard, infrequent, archive)
     * @return bool Success status
     */
    public function change_storage_tier($remotepath, $tier);
    
    /**
     * Test connection and credentials
     * @return array Test result with status and message
     */
    public function test_connection();
}
```


### 3. Quota Manager

**Class:** `classes/quota_manager.php`

```php
class quota_manager {
    
    /**
     * Check if user can upload file
     * @param int $userid User ID
     * @param int $filesize File size in bytes
     * @param int $contextid Context ID
     * @return array Result with allowed status and message
     */
    public function check_quota($userid, $filesize, $contextid);
    
    /**
     * Get user's current usage
     * @param int $userid User ID
     * @return int Usage in bytes
     */
    public function get_user_usage($userid);
    
    /**
     * Get course's current usage
     * @param int $courseid Course ID
     * @return int Usage in bytes
     */
    public function get_course_usage($courseid);
    
    /**
     * Get system-wide usage
     * @return int Total usage in bytes
     */
    public function get_system_usage();
    
    /**
     * Update usage after file operation
     * @param int $userid User ID
     * @param int $contextid Context ID
     * @param int $sizechange Size change (positive or negative)
     */
    public function update_usage($userid, $contextid, $sizechange);
    
    /**
     * Get quota limit for user
     * @param int $userid User ID
     * @return int Quota limit in bytes
     */
    public function get_user_quota_limit($userid);
    
    /**
     * Get quota limit for course
     * @param int $courseid Course ID
     * @return int Quota limit in bytes
     */
    public function get_course_quota_limit($courseid);
}
```

### 4. Security Manager

**Class:** `classes/security_manager.php`

```php
class security_manager {
    
    /**
     * Validate file before upload
     * @param string $filename Original filename
     * @param string $filepath Temporary file path
     * @param int $contextid Context ID
     * @return array Validation result
     */
    public function validate_file($filename, $filepath, $contextid);
    
    /**
     * Check if user can access file
     * @param int $userid User ID
     * @param int $fileid External file ID
     * @return bool Access allowed
     */
    public function check_file_access($userid, $fileid);
    
    /**
     * Sanitize filename
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public function sanitize_filename($filename);
    
    /**
     * Scan file for malicious content
     * @param string $filepath File path
     * @return array Scan result
     */
    public function scan_file($filepath);
    
    /**
     * Encrypt storage credentials
     * @param string $credentials JSON credentials
     * @return string Encrypted credentials
     */
    public function encrypt_credentials($credentials);
    
    /**
     * Decrypt storage credentials
     * @param string $encrypted Encrypted credentials
     * @return string Decrypted JSON credentials
     */
    public function decrypt_credentials($encrypted);
    
    /**
     * Generate secure file path
     * @param int $userid User ID
     * @param int $contextid Context ID
     * @param string $filename Filename
     * @return string Secure path
     */
    public function generate_secure_path($userid, $contextid, $filename);
}
```


### 5. Preview Generator

**Class:** `classes/preview_generator.php`

```php
class preview_generator {
    
    /**
     * Generate thumbnail for image
     * @param string $filepath Local file path
     * @param int $width Thumbnail width
     * @param int $height Thumbnail height
     * @return string Thumbnail path
     */
    public function generate_image_thumbnail($filepath, $width = 150, $height = 150);
    
    /**
     * Generate thumbnail for video
     * @param string $filepath Local file path
     * @param int $timestamp Frame timestamp in seconds
     * @return string Thumbnail path
     */
    public function generate_video_thumbnail($filepath, $timestamp = 1);
    
    /**
     * Generate thumbnail for PDF
     * @param string $filepath Local file path
     * @param int $page Page number
     * @return string Thumbnail path
     */
    public function generate_pdf_thumbnail($filepath, $page = 1);
    
    /**
     * Get file type icon
     * @param string $mimetype MIME type
     * @return string Icon URL
     */
    public function get_file_icon($mimetype);
    
    /**
     * Check if file type supports preview
     * @param string $mimetype MIME type
     * @return bool Supports preview
     */
    public function supports_preview($mimetype);
}
```

### 6. Lifecycle Manager

**Class:** `classes/lifecycle_manager.php`

```php
class lifecycle_manager {
    
    /**
     * Evaluate lifecycle policies
     * @return array Files matching policies
     */
    public function evaluate_policies();
    
    /**
     * Apply deletion policy
     * @param int $fileid External file ID
     * @return bool Success status
     */
    public function apply_deletion_policy($fileid);
    
    /**
     * Apply archival policy
     * @param int $fileid External file ID
     * @return bool Success status
     */
    public function apply_archival_policy($fileid);
    
    /**
     * Get configured policies
     * @return array Policy configurations
     */
    public function get_policies();
    
    /**
     * Add lifecycle policy
     * @param array $policy Policy configuration
     * @return int Policy ID
     */
    public function add_policy($policy);
    
    /**
     * Delete lifecycle policy
     * @param int $policyid Policy ID
     * @return bool Success status
     */
    public function delete_policy($policyid);
}
```

### 7. Cache Manager

**Class:** `classes/cache_manager.php`

```php
class cache_manager {
    
    /**
     * Cache signed URL
     * @param string $filehash File content hash
     * @param string $url Signed URL
     * @param int $expiry Expiration timestamp
     */
    public function cache_signed_url($filehash, $url, $expiry);
    
    /**
     * Get cached signed URL
     * @param string $filehash File content hash
     * @return string|null Cached URL or null
     */
    public function get_cached_signed_url($filehash);
    
    /**
     * Cache file metadata
     * @param int $fileid External file ID
     * @param array $metadata File metadata
     */
    public function cache_file_metadata($fileid, $metadata);
    
    /**
     * Get cached file metadata
     * @param int $fileid External file ID
     * @return array|null Cached metadata or null
     */
    public function get_cached_file_metadata($fileid);
    
    /**
     * Cache thumbnail
     * @param int $fileid External file ID
     * @param string $thumbnailpath Thumbnail file path
     */
    public function cache_thumbnail($fileid, $thumbnailpath);
    
    /**
     * Get cached thumbnail
     * @param int $fileid External file ID
     * @return string|null Thumbnail path or null
     */
    public function get_cached_thumbnail($fileid);
    
    /**
     * Invalidate cache for file
     * @param int $fileid External file ID
     */
    public function invalidate_file_cache($fileid);
}
```


### 8. Audit Logger

**Class:** `classes/audit_logger.php`

```php
class audit_logger {
    
    /**
     * Log file upload event
     * @param int $userid User ID
     * @param int $fileid External file ID
     * @param array $details Event details
     */
    public function log_upload($userid, $fileid, $details);
    
    /**
     * Log file access event
     * @param int $userid User ID
     * @param int $fileid External file ID
     * @param string $action Access action (view, download)
     */
    public function log_access($userid, $fileid, $action);
    
    /**
     * Log file deletion event
     * @param int $userid User ID
     * @param int $fileid External file ID
     * @param string $reason Deletion reason
     */
    public function log_deletion($userid, $fileid, $reason);
    
    /**
     * Log lifecycle action
     * @param int $fileid External file ID
     * @param string $action Lifecycle action
     * @param int $policyid Policy ID
     */
    public function log_lifecycle_action($fileid, $action, $policyid);
    
    /**
     * Get audit logs
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perpage Results per page
     * @return array Audit log entries
     */
    public function get_logs($filters = [], $page = 0, $perpage = 50);
    
    /**
     * Export audit logs
     * @param array $filters Filter criteria
     * @param string $format Export format (csv, json)
     * @return string Export file path
     */
    public function export_logs($filters = [], $format = 'csv');
}
```

### 9. Migration Tool

**Class:** `classes/migration_tool.php`

```php
class migration_tool {
    
    /**
     * Scan local files for migration
     * @param array $criteria Selection criteria
     * @return array Files eligible for migration
     */
    public function scan_local_files($criteria = []);
    
    /**
     * Migrate file to external storage
     * @param int $fileid Moodle file ID
     * @param bool $deletelocal Delete local copy after migration
     * @return array Migration result
     */
    public function migrate_file($fileid, $deletelocal = false);
    
    /**
     * Migrate files in batch
     * @param array $fileids Array of file IDs
     * @param bool $deletelocal Delete local copies
     * @return array Batch migration results
     */
    public function migrate_batch($fileids, $deletelocal = false);
    
    /**
     * Get migration progress
     * @param string $batchid Batch ID
     * @return array Progress information
     */
    public function get_migration_progress($batchid);
    
    /**
     * Generate migration report
     * @param string $batchid Batch ID
     * @return array Migration report
     */
    public function generate_migration_report($batchid);
    
    /**
     * Rollback migration
     * @param int $fileid External file ID
     * @return bool Success status
     */
    public function rollback_migration($fileid);
}
```


## Data Models

### Database Schema

**Table: `mdl_repository_external_files`**
```sql
CREATE TABLE mdl_repository_external_files (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    contenthash VARCHAR(40) NOT NULL,           -- Moodle file content hash
    external_path VARCHAR(500) NOT NULL,        -- Path in external storage
    provider VARCHAR(50) NOT NULL,              -- Storage provider (s3, r2, gcs, azure)
    bucket VARCHAR(255) NOT NULL,               -- Bucket/container name
    filesize BIGINT NOT NULL,                   -- File size in bytes
    mimetype VARCHAR(100) NOT NULL,             -- MIME type
    filename VARCHAR(255) NOT NULL,             -- Original filename
    metadata TEXT,                              -- JSON metadata
    access_level VARCHAR(20) DEFAULT 'private', -- Access level (private, course, public)
    contextid BIGINT NOT NULL,                  -- Moodle context ID
    userid BIGINT NOT NULL,                     -- Uploader user ID
    storage_tier VARCHAR(20) DEFAULT 'standard',-- Storage tier
    created_time BIGINT NOT NULL,               -- Creation timestamp
    modified_time BIGINT NOT NULL,              -- Last modified timestamp
    last_access_time BIGINT,                    -- Last access timestamp
    access_count INT DEFAULT 0,                 -- Access counter
    
    INDEX idx_contenthash (contenthash),
    INDEX idx_provider (provider),
    INDEX idx_contextid (contextid),
    INDEX idx_userid (userid),
    INDEX idx_access_level (access_level),
    INDEX idx_created_time (created_time),
    INDEX idx_last_access_time (last_access_time)
);
```

**Table: `mdl_repository_external_access`**
```sql
CREATE TABLE mdl_repository_external_access (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT NOT NULL,                    -- Reference to external_files
    contextid BIGINT NOT NULL,                  -- Moodle context
    capability VARCHAR(100),                    -- Required capability
    expires BIGINT,                             -- Expiry timestamp (null = no expiry)
    
    FOREIGN KEY (file_id) REFERENCES mdl_repository_external_files(id) ON DELETE CASCADE,
    INDEX idx_file_id (file_id),
    INDEX idx_contextid (contextid),
    INDEX idx_expires (expires)
);
```

**Table: `mdl_repository_external_quota`**
```sql
CREATE TABLE mdl_repository_external_quota (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    entity_type VARCHAR(20) NOT NULL,           -- Entity type (user, course, system)
    entity_id BIGINT NOT NULL,                  -- Entity ID
    quota_limit BIGINT NOT NULL,                -- Quota limit in bytes
    current_usage BIGINT DEFAULT 0,             -- Current usage in bytes
    last_updated BIGINT NOT NULL,               -- Last update timestamp
    
    UNIQUE KEY unique_entity (entity_type, entity_id),
    INDEX idx_entity (entity_type, entity_id)
);
```

**Table: `mdl_repository_external_lifecycle`**
```sql
CREATE TABLE mdl_repository_external_lifecycle (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,                 -- Policy name
    description TEXT,                           -- Policy description
    enabled TINYINT DEFAULT 1,                  -- Policy enabled status
    rule_type VARCHAR(50) NOT NULL,             -- Rule type (age, access, size)
    rule_value VARCHAR(255) NOT NULL,           -- Rule value (JSON)
    action VARCHAR(50) NOT NULL,                -- Action (delete, archive)
    priority INT DEFAULT 0,                     -- Execution priority
    created_time BIGINT NOT NULL,
    modified_time BIGINT NOT NULL,
    
    INDEX idx_enabled (enabled),
    INDEX idx_priority (priority)
);
```

**Table: `mdl_repository_external_audit`**
```sql
CREATE TABLE mdl_repository_external_audit (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT,                             -- Reference to external_files (null if deleted)
    userid BIGINT NOT NULL,                     -- User performing action
    action VARCHAR(50) NOT NULL,                -- Action (upload, access, delete, etc.)
    details TEXT,                               -- JSON details
    ip_address VARCHAR(45),                     -- User IP address
    timestamp BIGINT NOT NULL,                  -- Event timestamp
    
    INDEX idx_file_id (file_id),
    INDEX idx_userid (userid),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
);
```

**Table: `mdl_repository_external_cache`**
```sql
CREATE TABLE mdl_repository_external_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) NOT NULL,            -- Cache key
    cache_type VARCHAR(50) NOT NULL,            -- Cache type (url, metadata, thumbnail)
    cache_value TEXT NOT NULL,                  -- Cached value
    expires BIGINT NOT NULL,                    -- Expiration timestamp
    
    UNIQUE KEY unique_cache_key (cache_key, cache_type),
    INDEX idx_expires (expires)
);
```


### PHP Data Models

**File Model:** `classes/models/external_file.php`

```php
class external_file {
    public $id;
    public $contenthash;
    public $external_path;
    public $provider;
    public $bucket;
    public $filesize;
    public $mimetype;
    public $filename;
    public $metadata;
    public $access_level;
    public $contextid;
    public $userid;
    public $storage_tier;
    public $created_time;
    public $modified_time;
    public $last_access_time;
    public $access_count;
    
    /**
     * Load file from database
     * @param int $id File ID
     * @return external_file|null
     */
    public static function load($id);
    
    /**
     * Load file by content hash
     * @param string $contenthash Content hash
     * @return external_file|null
     */
    public static function load_by_hash($contenthash);
    
    /**
     * Save file to database
     * @return bool Success status
     */
    public function save();
    
    /**
     * Delete file record
     * @return bool Success status
     */
    public function delete();
    
    /**
     * Get signed URL for file
     * @param int $expiry Expiration in seconds
     * @return string Signed URL
     */
    public function get_signed_url($expiry = 3600);
    
    /**
     * Update access statistics
     */
    public function record_access();
    
    /**
     * Get file metadata as array
     * @return array Metadata
     */
    public function get_metadata();
}
```

**Quota Model:** `classes/models/quota.php`

```php
class quota {
    public $id;
    public $entity_type;
    public $entity_id;
    public $quota_limit;
    public $current_usage;
    public $last_updated;
    
    /**
     * Load quota record
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @return quota|null
     */
    public static function load($entity_type, $entity_id);
    
    /**
     * Save quota record
     * @return bool Success status
     */
    public function save();
    
    /**
     * Check if quota allows size
     * @param int $size Size in bytes
     * @return bool Allowed
     */
    public function allows($size);
    
    /**
     * Add to usage
     * @param int $size Size in bytes
     */
    public function add_usage($size);
    
    /**
     * Subtract from usage
     * @param int $size Size in bytes
     */
    public function subtract_usage($size);
    
    /**
     * Get usage percentage
     * @return float Percentage (0-100)
     */
    public function get_usage_percentage();
}
```


## Error Handling

### Error Categories

1. **Storage Provider Errors**
   - Connection failures
   - Authentication errors
   - Quota exceeded on provider
   - Network timeouts
   - Invalid credentials

2. **Validation Errors**
   - Invalid file type
   - File size exceeds limit
   - Malicious file detected
   - Invalid filename characters

3. **Quota Errors**
   - User quota exceeded
   - Course quota exceeded
   - System quota exceeded

4. **Permission Errors**
   - Insufficient capabilities
   - Context access denied
   - File access denied

5. **System Errors**
   - Database errors
   - Cache errors
   - File system errors

### Error Handling Strategy

**Retry Logic:**
```php
class retry_handler {
    /**
     * Execute operation with retry
     * @param callable $operation Operation to execute
     * @param int $maxretries Maximum retry attempts
     * @param int $delay Delay between retries in seconds
     * @return mixed Operation result
     * @throws Exception If all retries fail
     */
    public function execute_with_retry($operation, $maxretries = 3, $delay = 2);
}
```

**Error Response Format:**
```php
[
    'success' => false,
    'error_code' => 'QUOTA_EXCEEDED',
    'error_message' => 'User quota limit reached',
    'details' => [
        'current_usage' => 5368709120,
        'quota_limit' => 5368709120,
        'attempted_size' => 104857600
    ],
    'user_message' => 'You have reached your storage quota limit. Please delete some files or contact your administrator.'
]
```

**Logging Strategy:**
- All errors logged to Moodle's standard logging system
- Critical errors trigger admin notifications
- Provider-specific errors include full request/response details
- User-facing errors sanitized to prevent information disclosure

### Graceful Degradation

1. **Cache Failures:** Continue without cache, log warning
2. **Preview Generation Failures:** Show generic icon, continue operation
3. **Audit Log Failures:** Log to file system, continue operation
4. **Provider Unavailable:** Queue operations for retry, notify admin


## Testing Strategy

### Unit Tests

**Provider Tests:**
- Test each storage provider implementation
- Mock external API calls
- Test error handling and retries
- Test credential encryption/decryption

**Quota Manager Tests:**
- Test quota calculations
- Test quota enforcement
- Test usage updates
- Test edge cases (concurrent uploads)

**Security Manager Tests:**
- Test file validation
- Test filename sanitization
- Test access control checks
- Test malicious file detection

**Cache Manager Tests:**
- Test cache storage and retrieval
- Test cache expiration
- Test cache invalidation

### Integration Tests

**File Upload Flow:**
```php
public function test_complete_upload_flow() {
    // 1. Create test user and course
    // 2. Upload file through repository
    // 3. Verify file in external storage
    // 4. Verify database records
    // 5. Verify quota updated
    // 6. Verify audit log entry
}
```

**File Access Flow:**
```php
public function test_file_access_flow() {
    // 1. Upload file as teacher
    // 2. Generate signed URL
    // 3. Access file as student
    // 4. Verify access logged
    // 5. Test expired URL handling
}
```

**Lifecycle Management:**
```php
public function test_lifecycle_policy() {
    // 1. Create old test files
    // 2. Configure deletion policy
    // 3. Run scheduled task
    // 4. Verify files deleted
    // 5. Verify audit logs
}
```

### Performance Tests

**Large File Upload:**
- Test multipart upload for files > 100MB
- Test upload resume functionality
- Measure upload speed and reliability

**Concurrent Operations:**
- Test multiple simultaneous uploads
- Test quota enforcement under load
- Test cache performance

**Scalability:**
- Test with 10,000+ files
- Test file listing performance
- Test search performance

### Security Tests

**Access Control:**
- Test unauthorized access attempts
- Test capability enforcement
- Test context isolation

**File Validation:**
- Test malicious file upload attempts
- Test filename injection attacks
- Test path traversal attempts

**Credential Security:**
- Test credential encryption
- Test credential storage
- Test credential exposure prevention


## User Interface Design

### File Picker Integration

**File Browser View:**
```
┌─────────────────────────────────────────────────────────────┐
│ External Storage                                    [Search] │
├─────────────────────────────────────────────────────────────┤
│ Path: / > courses > math101 > assignments                   │
├─────────────────────────────────────────────────────────────┤
│ [📤 Upload] [📁 New Folder] [⚙️ Settings]                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ 📁 documents/                          Modified: 2 days ago │
│ 📁 images/                             Modified: 1 week ago │
│ 📁 videos/                             Modified: 3 days ago │
│                                                              │
│ 📄 syllabus.pdf                        2.3 MB   Jan 15 2025 │
│    [👁️ Preview] [⬇️ Download] [🔗 Get Link] [🗑️ Delete]     │
│                                                              │
│ 🎥 lecture_01.mp4                    150 MB   Jan 14 2025   │
│    [👁️ Preview] [⬇️ Download] [🔗 Get Link] [🗑️ Delete]     │
│                                                              │
│ 📊 grades.xlsx                       890 KB   Jan 13 2025   │
│    [👁️ Preview] [⬇️ Download] [🔗 Get Link] [🗑️ Delete]     │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│ Storage Used: 2.5 GB / 5 GB (50%)          [Page 1 of 3] ▶  │
└─────────────────────────────────────────────────────────────┘
```

**Upload Dialog:**
```
┌─────────────────────────────────────────────────────────────┐
│ Upload Files to External Storage                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌───────────────────────────────────────────────────┐    │
│   │  Drag files here or click to browse                │    │
│   │                                                     │    │
│   │  📄 document.pdf    ████████████ 100% (2.3 MB)     │    │
│   │  🎥 video.mp4       ████████░░░░  75% (45/60 MB)   │    │
│   │  📊 spreadsheet.xlsx ░░░░░░░░░░░░   0% (Queued)    │    │
│   └───────────────────────────────────────────────────┘    │
│                                                              │
│ Upload to: /courses/math101/assignments/                    │
│                                                              │
│ Access Level: ○ Private  ● Course  ○ Public                 │
│                                                              │
│ [✅ Upload] [❌ Cancel]                                      │
└─────────────────────────────────────────────────────────────┘
```

### Admin Dashboard

**Storage Overview:**
```
┌─────────────────────────────────────────────────────────────┐
│ External Storage Dashboard                                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Total Storage Used: 245 GB / 500 GB (49%)                   │
│ ████████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░   │
│                                                              │
│ Files: 12,458    Users: 342    Courses: 28                  │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ Storage Usage Trend (Last 30 Days)                  │    │
│ │                                                      │    │
│ │ 300GB ┤                                         ╭─   │    │
│ │ 250GB ┤                                    ╭────╯    │    │
│ │ 200GB ┤                          ╭─────────╯         │    │
│ │ 150GB ┤                ╭─────────╯                   │    │
│ │ 100GB ┤      ╭─────────╯                             │    │
│ │  50GB ┤──────╯                                       │    │
│ │       └────────────────────────────────────────────  │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ Top Users by Storage:                                       │
│ 1. John Smith (teacher)           15.2 GB                   │
│ 2. Sarah Johnson (teacher)        12.8 GB                   │
│ 3. Mike Davis (teacher)           10.5 GB                   │
│                                                              │
│ Top Courses by Storage:                                     │
│ 1. Advanced Mathematics            45.3 GB                  │
│ 2. Physics 101                     38.7 GB                  │
│ 3. Computer Science                32.1 GB                  │
│                                                              │
│ [📊 Detailed Reports] [⚙️ Settings] [🔄 Refresh]            │
└─────────────────────────────────────────────────────────────┘
```

### Settings Interface

**Provider Configuration:**
```
┌─────────────────────────────────────────────────────────────┐
│ Storage Provider Settings                                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Default Provider: [AWS S3 ▼]                                │
│                                                              │
│ ┌─ AWS S3 Configuration ────────────────────────────────┐   │
│ │                                                        │   │
│ │ Access Key ID:     [AKIA****************]             │   │
│ │ Secret Access Key: [****************************]     │   │
│ │ Region:            [us-east-1 ▼]                      │   │
│ │ Bucket Name:       [moodle-external-storage]          │   │
│ │                                                        │   │
│ │ [🔍 Test Connection]  Status: ✅ Connected            │   │
│ │                                                        │   │
│ └────────────────────────────────────────────────────────┘   │
│                                                              │
│ ┌─ Cloudflare R2 Configuration ─────────────────────────┐   │
│ │ [+ Add Cloudflare R2 Provider]                        │   │
│ └────────────────────────────────────────────────────────┘   │
│                                                              │
│ ┌─ Quota Settings ──────────────────────────────────────┐   │
│ │                                                        │   │
│ │ Default User Quota:   [5] GB                          │   │
│ │ Default Course Quota: [50] GB                         │   │
│ │ System Quota:         [500] GB                        │   │
│ │                                                        │   │
│ └────────────────────────────────────────────────────────┘   │
│                                                              │
│ [💾 Save Changes] [❌ Cancel]                                │
└─────────────────────────────────────────────────────────────┘
```


## Security Considerations

### Authentication & Authorization

**Moodle Capabilities:**
```php
$capabilities = [
    'repository/external_storage:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'repository/external_storage:upload' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'repository/external_storage:delete' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'repository/external_storage:manageall' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ]
];
```

### Data Protection

**Encryption:**
- Storage credentials encrypted at rest using Moodle's encryption API
- File metadata encrypted in database
- Signed URLs use HMAC-SHA256 signatures
- TLS 1.2+ required for all external storage connections

**Access Control:**
- All file access validated against Moodle context and capabilities
- Signed URLs expire after configurable time (default 1 hour)
- IP-based access restrictions optional
- User agent validation for signed URLs

**File Validation:**
```php
class file_validator {
    // Blocked extensions
    private $blocked_extensions = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js'
    ];
    
    // Maximum file sizes by type
    private $size_limits = [
        'image/*' => 10485760,      // 10 MB
        'video/*' => 524288000,     // 500 MB
        'application/*' => 52428800 // 50 MB
    ];
    
    // MIME type validation
    public function validate_mime_type($filepath, $declared_mime);
    
    // Virus scanning integration
    public function scan_for_malware($filepath);
}
```

### GDPR Compliance

**Data Subject Rights:**
- Right to access: Export all user files
- Right to erasure: Delete all user files from external storage
- Right to portability: Download all user files in standard formats
- Right to rectification: Update file metadata

**Privacy Provider Implementation:**
```php
class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    
    public static function get_metadata(collection $collection): collection;
    public static function get_contexts_for_userid(int $userid): contextlist;
    public static function export_user_data(approved_contextlist $contextlist);
    public static function delete_data_for_all_users_in_context(\context $context);
    public static function delete_data_for_user(approved_contextlist $contextlist);
}
```

### Audit Trail

**Logged Events:**
- File uploads (who, what, when, where, size)
- File access (who, what, when, IP address)
- File deletions (who, what, when, reason)
- Permission changes (who, what, when, old/new values)
- Configuration changes (who, what, when, old/new values)
- Lifecycle actions (what, when, policy, result)

**Retention:**
- Audit logs retained for minimum 1 year
- Configurable retention period
- Automatic archival to long-term storage
- Export capability for compliance reporting


## Performance Optimization

### Caching Strategy

**Multi-Level Cache:**

1. **Application Cache (Moodle Cache API):**
   - Signed URLs (TTL: URL expiry - 5 minutes)
   - File metadata (TTL: 1 hour)
   - Directory listings (TTL: 5 minutes)
   - Quota information (TTL: 5 minutes)

2. **Database Cache:**
   - Frequently accessed file records
   - User quota summaries
   - Provider configurations

3. **CDN Integration:**
   - Public files served through CloudFront/CDN
   - Automatic cache invalidation on file updates
   - Geographic distribution for global access

**Cache Invalidation:**
```php
class cache_invalidator {
    /**
     * Invalidate file cache on update
     */
    public function on_file_update($fileid) {
        $this->invalidate_signed_url($fileid);
        $this->invalidate_metadata($fileid);
        $this->invalidate_directory_listing($fileid);
    }
    
    /**
     * Invalidate quota cache on usage change
     */
    public function on_quota_change($userid, $contextid) {
        $this->invalidate_user_quota($userid);
        $this->invalidate_context_quota($contextid);
    }
}
```

### Database Optimization

**Indexes:**
- Composite indexes on frequently queried columns
- Covering indexes for common queries
- Partial indexes for filtered queries

**Query Optimization:**
```sql
-- Efficient user quota query
SELECT 
    SUM(filesize) as total_usage
FROM mdl_repository_external_files
WHERE userid = ? AND deleted = 0
HAVING total_usage < ?;

-- Efficient file listing with pagination
SELECT *
FROM mdl_repository_external_files
WHERE contextid = ? AND external_path LIKE ?
ORDER BY filename
LIMIT ? OFFSET ?;
```

**Connection Pooling:**
- Reuse storage provider connections
- Connection pool size based on concurrent users
- Automatic connection cleanup

### Upload Optimization

**Multipart Upload:**
```php
class multipart_uploader {
    private $chunk_size = 5242880; // 5 MB chunks
    
    public function upload_large_file($filepath, $remotepath) {
        $filesize = filesize($filepath);
        $num_chunks = ceil($filesize / $this->chunk_size);
        
        // Initialize multipart upload
        $upload_id = $this->provider->init_multipart($remotepath);
        
        // Upload chunks in parallel
        $parts = [];
        for ($i = 0; $i < $num_chunks; $i++) {
            $parts[] = $this->upload_chunk($filepath, $upload_id, $i);
        }
        
        // Complete multipart upload
        return $this->provider->complete_multipart($upload_id, $parts);
    }
}
```

**Parallel Processing:**
- Multiple file uploads processed concurrently
- Background job queue for large operations
- Progress tracking for long-running tasks

### Bandwidth Optimization

**Compression:**
- Automatic compression for text-based files
- Optional compression for images (lossy/lossless)
- Streaming decompression on download

**Lazy Loading:**
- Thumbnails loaded on demand
- Directory listings paginated
- Metadata fetched only when needed

**Transfer Acceleration:**
- Use provider's transfer acceleration features
- Direct browser-to-storage uploads (presigned URLs)
- Resume capability for interrupted transfers


## Deployment Considerations

### Installation Process

**Step 1: Plugin Installation**
```bash
# Copy plugin to Moodle directory
cp -r repository/external_storage /var/www/html/moodle/repository/

# Set permissions
chown -R www-data:www-data /var/www/html/moodle/repository/external_storage

# Run Moodle upgrade
php admin/cli/upgrade.php
```

**Step 2: Database Setup**
- Automatic table creation via install.xml
- Indexes created automatically
- Initial configuration records inserted

**Step 3: Provider Configuration**
- Admin navigates to Site Administration > Plugins > Repositories > External Storage
- Configure storage provider credentials
- Test connection
- Set default quotas

**Step 4: Enable Repository**
- Enable repository instance
- Configure visibility (all courses, specific courses, etc.)
- Set file type restrictions if needed

### Configuration Management

**Environment-Specific Settings:**
```php
// config.php additions
$CFG->repository_external_storage_provider = 's3';
$CFG->repository_external_storage_bucket = getenv('STORAGE_BUCKET');
$CFG->repository_external_storage_region = getenv('STORAGE_REGION');

// Credentials from environment (recommended)
$CFG->repository_external_storage_access_key = getenv('STORAGE_ACCESS_KEY');
$CFG->repository_external_storage_secret_key = getenv('STORAGE_SECRET_KEY');
```

**Multi-Tenant Configuration:**
```php
// Different storage per tenant/organization
$CFG->repository_external_storage_multi_tenant = true;
$CFG->repository_external_storage_tenant_mapping = [
    'org1' => ['provider' => 's3', 'bucket' => 'org1-storage'],
    'org2' => ['provider' => 'r2', 'bucket' => 'org2-storage']
];
```

### Monitoring & Maintenance

**Health Checks:**
```php
class health_checker {
    /**
     * Check storage provider connectivity
     */
    public function check_provider_health();
    
    /**
     * Check database performance
     */
    public function check_database_health();
    
    /**
     * Check quota usage
     */
    public function check_quota_health();
    
    /**
     * Check cache performance
     */
    public function check_cache_health();
}
```

**Scheduled Tasks:**
```php
// Cleanup expired cache entries (every hour)
$tasks[] = [
    'classname' => 'repository_external_storage\task\cleanup_cache',
    'blocking' => 0,
    'minute' => '0',
    'hour' => '*',
    'day' => '*',
    'month' => '*',
    'dayofweek' => '*'
];

// Apply lifecycle policies (daily at 2 AM)
$tasks[] = [
    'classname' => 'repository_external_storage\task\apply_lifecycle_policies',
    'blocking' => 0,
    'minute' => '0',
    'hour' => '2',
    'day' => '*',
    'month' => '*',
    'dayofweek' => '*'
];

// Update quota statistics (every 15 minutes)
$tasks[] = [
    'classname' => 'repository_external_storage\task\update_quota_stats',
    'blocking' => 0,
    'minute' => '*/15',
    'hour' => '*',
    'day' => '*',
    'month' => '*',
    'dayofweek' => '*'
];

// Generate usage reports (weekly on Sunday)
$tasks[] = [
    'classname' => 'repository_external_storage\task\generate_reports',
    'blocking' => 0,
    'minute' => '0',
    'hour' => '3',
    'day' => '*',
    'month' => '*',
    'dayofweek' => '0'
];
```

**Logging:**
```php
// Log levels
define('EXTERNAL_STORAGE_LOG_ERROR', 1);
define('EXTERNAL_STORAGE_LOG_WARNING', 2);
define('EXTERNAL_STORAGE_LOG_INFO', 3);
define('EXTERNAL_STORAGE_LOG_DEBUG', 4);

// Configurable log level
$CFG->repository_external_storage_log_level = EXTERNAL_STORAGE_LOG_INFO;

// Log destinations
$CFG->repository_external_storage_log_file = '/var/log/moodle/external_storage.log';
$CFG->repository_external_storage_log_syslog = true;
```

### Backup & Recovery

**Backup Strategy:**
1. **Database Backup:** Include all external_storage tables in Moodle backup
2. **Metadata Export:** Regular exports of file registry to JSON/CSV
3. **Storage Provider Backup:** Use provider's backup features (S3 versioning, etc.)

**Recovery Procedures:**
```php
class recovery_tool {
    /**
     * Verify file integrity
     * Check if files in database exist in storage
     */
    public function verify_integrity();
    
    /**
     * Rebuild file registry from storage
     * Scan storage and recreate database records
     */
    public function rebuild_registry();
    
    /**
     * Restore deleted files
     * Recover from storage provider versioning
     */
    public function restore_deleted_files($timeframe);
}
```

### Scaling Considerations

**Horizontal Scaling:**
- Stateless design allows multiple Moodle instances
- Shared database for file registry
- Distributed cache (Redis/Memcached)
- Load balancer for web traffic

**Vertical Scaling:**
- Increase PHP memory limit for large file operations
- Increase database connection pool
- Increase cache size

**Storage Scaling:**
- Automatic scaling with cloud storage
- Multiple buckets for load distribution
- Geographic distribution for global access


## Migration from Existing Systems

### Migration from Local Storage

**Phase 1: Assessment**
```php
class migration_assessor {
    /**
     * Analyze current storage usage
     * @return array Statistics and recommendations
     */
    public function analyze_current_storage() {
        return [
            'total_files' => 50000,
            'total_size' => 524288000000, // 500 GB
            'file_types' => [
                'video' => ['count' => 500, 'size' => 314572800000],
                'image' => ['count' => 10000, 'size' => 52428800000],
                'document' => ['count' => 39500, 'size' => 157286400000]
            ],
            'estimated_migration_time' => '48 hours',
            'estimated_cost' => '$50/month'
        ];
    }
}
```

**Phase 2: Selective Migration**
```php
// Migrate by criteria
$criteria = [
    'file_types' => ['video/*', 'application/pdf'],
    'min_size' => 10485760, // 10 MB
    'older_than' => strtotime('-30 days'),
    'contexts' => [CONTEXT_COURSE]
];

$migration_tool->migrate_by_criteria($criteria);
```

**Phase 3: Verification**
```php
// Verify migrated files
$verification_report = $migration_tool->verify_migration($batch_id);

// Rollback if issues found
if ($verification_report['errors'] > 0) {
    $migration_tool->rollback_batch($batch_id);
}
```

**Phase 4: Cleanup**
```php
// Delete local copies after successful migration
$migration_tool->cleanup_local_files($batch_id, $verify = true);
```

### Migration from Video-Specific Plugins

**Compatibility Layer:**
```php
class video_plugin_migrator {
    /**
     * Migrate from assignsubmission_s3video
     */
    public function migrate_from_s3video() {
        // 1. Read existing s3video records
        $videos = $DB->get_records('assignsubmission_s3video');
        
        // 2. Create external_files records
        foreach ($videos as $video) {
            $external_file = new external_file();
            $external_file->external_path = $video->s3_key;
            $external_file->provider = 's3';
            $external_file->bucket = $video->bucket;
            $external_file->filesize = $video->filesize;
            $external_file->mimetype = 'video/mp4';
            $external_file->save();
        }
        
        // 3. Update references
        // 4. Verify migration
        // 5. Archive old plugin data
    }
    
    /**
     * Migrate from assignsubmission_cloudflarestream
     */
    public function migrate_from_cloudflarestream() {
        // Similar process for Cloudflare Stream videos
    }
}
```

## Future Enhancements

### Phase 1 (MVP)
- AWS S3 and Cloudflare R2 support
- Basic file upload/download
- Simple quota management
- File picker integration
- Basic security and access control

### Phase 2 (Enhanced Features)
- Google Cloud Storage and Azure Blob support
- File previews and thumbnails
- Advanced search and filtering
- Lifecycle policies
- Migration tool
- Admin dashboard

### Phase 3 (Enterprise Features)
- Multi-tenant support
- Advanced analytics and reporting
- Cost optimization recommendations
- Automated backup and sync
- API for external integrations
- Mobile app support

### Phase 4 (Advanced Features)
- AI-powered file organization
- Automatic content tagging
- Duplicate file detection
- Collaborative editing integration
- Version control for files
- Advanced compliance features (retention policies, legal holds)

## Technical Debt & Risks

### Known Limitations

1. **Large File Handling:**
   - PHP memory limits may affect very large files (>2GB)
   - Mitigation: Use streaming and chunked uploads

2. **Provider API Rate Limits:**
   - Storage providers may throttle requests
   - Mitigation: Implement rate limiting and retry logic

3. **Cache Consistency:**
   - Distributed cache may have consistency issues
   - Mitigation: Use cache versioning and invalidation

4. **Migration Complexity:**
   - Large-scale migrations may take days
   - Mitigation: Phased migration with verification

### Risk Mitigation

**Provider Outages:**
- Implement fallback to local storage
- Queue operations for retry
- Monitor provider status pages

**Data Loss:**
- Enable provider versioning
- Regular backup verification
- Audit trail for all deletions

**Security Breaches:**
- Regular security audits
- Automated vulnerability scanning
- Incident response plan

**Cost Overruns:**
- Quota enforcement
- Usage monitoring and alerts
- Cost estimation tools

## Conclusion

This design provides a comprehensive, scalable, and secure solution for universal external storage in Moodle. The plugin integrates seamlessly with Moodle's file picker, supports multiple storage providers, and includes enterprise-grade features like quota management, lifecycle policies, and GDPR compliance.

The modular architecture allows for easy extension with new storage providers and features, while the robust error handling and security measures ensure reliable operation in production environments.

Key benefits:
- **Cost Reduction:** Offload storage to cheaper cloud providers
- **Bandwidth Savings:** Direct file delivery from external storage
- **Scalability:** Unlimited storage capacity
- **Flexibility:** Support for all file types and Moodle activities
- **Security:** Enterprise-grade access control and encryption
- **Compliance:** GDPR-ready with audit trails and data export
