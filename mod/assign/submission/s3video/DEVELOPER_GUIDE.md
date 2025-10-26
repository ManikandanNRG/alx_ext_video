# Developer Guide - S3 Video Submission Plugin

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Plugin Structure](#plugin-structure)
3. [Database Schema](#database-schema)
4. [API Documentation](#api-documentation)
5. [Workflows](#workflows)
6. [Testing](#testing)
7. [Extending the Plugin](#extending-the-plugin)
8. [Coding Standards](#coding-standards)

## Architecture Overview

### High-Level Architecture

```
┌─────────────────┐
│  Student/Teacher│
│    Browser      │
└────────┬────────┘
         │
         ├─────────────────────────────────────┐
         │                                     │
         │ (1) Get presigned POST         (4) Get signed URL
         │                                     │
         ▼                                     ▼
┌─────────────────────────────────────────────────┐
│           Moodle Server (PHP)                   │
│  ┌──────────────────────────────────────────┐  │
│  │  assignsubmission_s3video Plugin         │  │
│  │  - lib.php (core plugin class)           │  │
│  │  - AJAX endpoints                        │  │
│  │  - AWS clients (S3, CloudFront)          │  │
│  └──────────────────────────────────────────┘  │
└────────┬────────────────────────────┬───────────┘
         │                            │
         │ (2) Direct upload      (5) Stream video
         │                            │
         ▼                            ▼
┌─────────────────┐          ┌─────────────────┐
│   AWS S3        │          │  CloudFront CDN │
│   (Storage)     │◄─────────│  (Delivery)     │
└─────────────────┘          └─────────────────┘
         (3) Origin fetch
```

### Component Layers

1. **Presentation Layer** (Browser)
   - JavaScript modules (AMD format)
   - Mustache templates
   - Video.js player

2. **Application Layer** (Moodle/PHP)
   - Plugin core (lib.php)
   - AJAX endpoints
   - AWS API clients
   - Business logic

3. **Data Layer**
   - Moodle database (metadata)
   - AWS S3 (video files)

4. **Infrastructure Layer**
   - AWS S3 (storage)
   - CloudFront (CDN)


## Plugin Structure

### Directory Layout

```
mod/assign/submission/s3video/
│
├── version.php                 # Plugin metadata and version
├── lib.php                     # Core plugin class
├── locallib.php               # Helper functions
├── settings.php               # Admin settings page
├── dashboard.php              # Admin dashboard
├── videomanagement.php        # Video management interface
├── styles.css                 # Plugin styles
│
├── db/
│   ├── install.xml           # Database schema
│   ├── upgrade.php           # Database migrations
│   ├── access.php            # Capability definitions
│   ├── tasks.php             # Scheduled tasks
│   └── caches.php            # Cache definitions
│
├── classes/
│   ├── api/
│   │   ├── s3_client.php           # AWS S3 API wrapper
│   │   └── cloudfront_client.php   # CloudFront API wrapper
│   ├── privacy/
│   │   └── provider.php            # GDPR compliance
│   ├── task/
│   │   └── cleanup_videos.php      # Scheduled cleanup task
│   ├── logger.php                  # Event logging
│   ├── validator.php               # Input validation
│   ├── rate_limiter.php            # Rate limiting
│   └── retry_handler.php           # Retry logic
│
├── ajax/
│   ├── get_upload_url.php          # Generate presigned POST
│   ├── confirm_upload.php          # Confirm upload completion
│   └── get_playback_url.php        # Generate signed URL
│
├── amd/src/
│   ├── uploader.js                 # Upload JavaScript module
│   └── player.js                   # Player JavaScript module
│
├── templates/
│   ├── upload_form.mustache        # Upload UI template
│   └── player.mustache             # Player UI template
│
├── lang/en/
│   └── assignsubmission_s3video.php  # Language strings
│
└── tests/
    ├── s3_client_test.php          # S3 client unit tests
    ├── cloudfront_client_test.php  # CloudFront unit tests
    └── privacy_provider_test.php   # GDPR tests
```

### Key Files

#### version.php
Defines plugin metadata:
- Plugin version number
- Required Moodle version
- Component name
- Dependencies

#### lib.php
Core plugin class `assign_submission_s3video` extending `assign_submission_plugin`:
- `get_name()`: Plugin name
- `get_settings()`: Plugin settings form
- `is_enabled()`: Check if plugin is enabled
- `save()`: Save submission
- `view()`: Display submission
- `get_form_elements()`: Upload form elements

#### locallib.php
Helper functions and utilities:
- Plugin detection
- Common operations
- Utility functions

## Database Schema

### Table: mdl_assignsubmission_s3video

Stores video submission metadata.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| assignment | BIGINT | Assignment ID (FK) |
| submission | BIGINT | Submission ID (FK) |
| s3_key | VARCHAR(500) | S3 object key |
| s3_bucket | VARCHAR(255) | S3 bucket name |
| upload_status | VARCHAR(50) | Status: pending, ready, error, deleted |
| file_size | BIGINT | File size in bytes |
| duration | INT | Video duration in seconds |
| mime_type | VARCHAR(100) | MIME type (e.g., video/mp4) |
| upload_timestamp | BIGINT | Unix timestamp of upload |
| deleted_timestamp | BIGINT | Unix timestamp of deletion |
| error_message | TEXT | Error message if upload failed |

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE KEY (submission)
- INDEX (s3_key)
- INDEX (upload_timestamp)

**Foreign Keys:**
- assignment → mdl_assign.id
- submission → mdl_assign_submission.id

### Table: mdl_assignsubmission_s3v_log

Stores event logs for monitoring and debugging.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| userid | BIGINT | User ID |
| assignmentid | BIGINT | Assignment ID |
| submissionid | BIGINT | Submission ID |
| s3_key | VARCHAR(500) | S3 object key |
| event_type | VARCHAR(50) | Event: upload_start, upload_success, upload_error, playback, delete |
| error_code | VARCHAR(100) | Error code if applicable |
| error_message | TEXT | Error message |
| error_context | TEXT | Additional error context (JSON) |
| file_size | BIGINT | File size in bytes |
| duration | INT | Operation duration in seconds |
| retry_count | INT | Number of retries |
| user_role | VARCHAR(50) | User role at time of event |
| timestamp | BIGINT | Unix timestamp |

**Indexes:**
- PRIMARY KEY (id)
- INDEX (event_type)
- INDEX (timestamp)

### Database Operations

#### Creating a submission record

```php
global $DB;

$record = new stdClass();
$record->assignment = $assignmentid;
$record->submission = $submissionid;
$record->s3_key = $s3_key;
$record->s3_bucket = $bucket;
$record->upload_status = 'pending';
$record->upload_timestamp = time();

$id = $DB->insert_record('assignsubmission_s3video', $record);
```

#### Updating submission status

```php
$DB->set_field('assignsubmission_s3video', 'upload_status', 'ready', 
    ['submission' => $submissionid]);
```

#### Retrieving submission

```php
$submission = $DB->get_record('assignsubmission_s3video', 
    ['submission' => $submissionid], '*', MUST_EXIST);
```

#### Logging an event

```php
$log = new stdClass();
$log->userid = $USER->id;
$log->assignmentid = $assignmentid;
$log->event_type = 'upload_success';
$log->s3_key = $s3_key;
$log->file_size = $filesize;
$log->timestamp = time();

$DB->insert_record('assignsubmission_s3v_log', $log);
```

## API Documentation

### AWS S3 Client

**Class:** `assignsubmission_s3video\api\s3_client`

#### Constructor

```php
public function __construct($access_key, $secret_key, $bucket, $region)
```

**Parameters:**
- `$access_key` (string): AWS access key ID
- `$secret_key` (string): AWS secret access key
- `$bucket` (string): S3 bucket name
- `$region` (string): AWS region (e.g., 'us-east-1')

#### get_presigned_post()

Generate presigned POST for direct browser upload.

```php
public function get_presigned_post($s3_key, $max_size, $mime_type, $expiry = 3600)
```

**Parameters:**
- `$s3_key` (string): S3 object key (path/filename)
- `$max_size` (int): Maximum file size in bytes
- `$mime_type` (string): Allowed MIME type
- `$expiry` (int): Expiration time in seconds (default: 3600)

**Returns:** Array with presigned POST data
```php
[
    'url' => 'https://bucket.s3.amazonaws.com',
    'fields' => [
        'key' => 'videos/123/file.mp4',
        'policy' => 'base64-encoded-policy',
        'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
        'x-amz-credential' => 'credentials',
        'x-amz-date' => '20250101T000000Z',
        'x-amz-signature' => 'signature'
    ]
]
```

**Throws:** `moodle_exception` on AWS API error

#### object_exists()

Check if object exists in S3.

```php
public function object_exists($s3_key)
```

**Parameters:**
- `$s3_key` (string): S3 object key

**Returns:** bool - true if exists, false otherwise

#### delete_object()

Delete object from S3.

```php
public function delete_object($s3_key)
```

**Parameters:**
- `$s3_key` (string): S3 object key

**Returns:** bool - true on success

**Throws:** `moodle_exception` on AWS API error

#### get_object_metadata()

Get object metadata (size, content type, etc.).

```php
public function get_object_metadata($s3_key)
```

**Parameters:**
- `$s3_key` (string): S3 object key

**Returns:** Array with metadata
```php
[
    'ContentLength' => 1048576,
    'ContentType' => 'video/mp4',
    'LastModified' => '2025-01-01T00:00:00Z',
    'ETag' => '"abc123"'
]
```

### CloudFront Client

**Class:** `assignsubmission_s3video\api\cloudfront_client`

#### Constructor

```php
public function __construct($domain, $keypair_id, $private_key)
```

**Parameters:**
- `$domain` (string): CloudFront distribution domain
- `$keypair_id` (string): CloudFront key pair ID
- `$private_key` (string): CloudFront private key (PEM format)

#### get_signed_url()

Generate CloudFront signed URL for secure video access.

```php
public function get_signed_url($s3_key, $expiry_seconds = 86400)
```

**Parameters:**
- `$s3_key` (string): S3 object key
- `$expiry_seconds` (int): URL expiration time (default: 86400 = 24 hours)

**Returns:** string - Signed URL
```
https://d123.cloudfront.net/videos/123/file.mp4?Expires=1234567890&Signature=...&Key-Pair-Id=APKA...
```

**Throws:** `moodle_exception` on signature generation error

#### create_invalidation()

Invalidate CloudFront cache for deleted object.

```php
public function create_invalidation($s3_key)
```

**Parameters:**
- `$s3_key` (string): S3 object key

**Returns:** string - Invalidation ID

**Throws:** `moodle_exception` on AWS API error

### AJAX Endpoints

#### get_upload_url.php

Generate presigned POST URL for upload.

**Request:**
```javascript
{
    sesskey: M.cfg.sesskey,
    assignmentid: 123,
    submissionid: 456,
    filename: 'video.mp4',
    filesize: 1048576,
    mimetype: 'video/mp4'
}
```

**Response (Success):**
```javascript
{
    success: true,
    upload_data: {
        url: 'https://bucket.s3.amazonaws.com',
        fields: { /* presigned POST fields */ },
        s3_key: 'videos/123/1234567890_abc/video.mp4'
    }
}
```

**Response (Error):**
```javascript
{
    success: false,
    error: 'Error message'
}
```

**Security:**
- Requires valid Moodle session
- Verifies user can submit to assignment
- Rate limited (10 requests per minute per user)

#### confirm_upload.php

Confirm upload completion and update database.

**Request:**
```javascript
{
    sesskey: M.cfg.sesskey,
    submissionid: 456,
    s3_key: 'videos/123/1234567890_abc/video.mp4'
}
```

**Response (Success):**
```javascript
{
    success: true,
    message: 'Upload confirmed'
}
```

**Response (Error):**
```javascript
{
    success: false,
    error: 'Error message'
}
```

**Security:**
- Requires valid Moodle session
- Verifies S3 object exists
- Verifies S3 key matches submission

#### get_playback_url.php

Generate signed URL for video playback.

**Request:**
```javascript
{
    sesskey: M.cfg.sesskey,
    submissionid: 456
}
```

**Response (Success):**
```javascript
{
    success: true,
    playback_url: 'https://d123.cloudfront.net/videos/123/file.mp4?Expires=...'
}
```

**Response (Error):**
```javascript
{
    success: false,
    error: 'Error message'
}
```

**Security:**
- Requires valid Moodle session
- Verifies user can view submission
- Rate limited (30 requests per minute per user)
- Signed URL expires in 24 hours

### Helper Classes

#### Logger

**Class:** `assignsubmission_s3video\logger`

```php
// Log upload success
logger::log_upload_success($userid, $assignmentid, $submissionid, $s3_key, $filesize);

// Log upload error
logger::log_upload_error($userid, $assignmentid, $submissionid, $error_code, $error_message);

// Log playback access
logger::log_playback($userid, $assignmentid, $submissionid, $s3_key);

// Log deletion
logger::log_deletion($userid, $assignmentid, $submissionid, $s3_key);
```

#### Validator

**Class:** `assignsubmission_s3video\validator`

```php
// Validate file size
validator::validate_file_size($size, $max_size);

// Validate MIME type
validator::validate_mime_type($mime_type);

// Validate S3 key format
validator::validate_s3_key($s3_key);

// Sanitize filename
$safe_filename = validator::sanitize_filename($filename);
```

#### Rate Limiter

**Class:** `assignsubmission_s3video\rate_limiter`

```php
// Check rate limit
if (!rate_limiter::check_limit($userid, 'upload_url', 10, 60)) {
    throw new moodle_exception('ratelimitexceeded', 'assignsubmission_s3video');
}

// Reset rate limit (for testing)
rate_limiter::reset_limit($userid, 'upload_url');
```

#### Retry Handler

**Class:** `assignsubmission_s3video\retry_handler`

```php
// Execute with retry
$result = retry_handler::execute(function() use ($s3_client, $s3_key) {
    return $s3_client->object_exists($s3_key);
}, 3, 1000); // 3 retries, 1000ms delay
```

## Workflows

### Upload Workflow

```
┌─────────────┐
│   Student   │
│   Browser   │
└──────┬──────┘
       │
       │ 1. Select video file
       │
       ▼
┌─────────────────────────────────────┐
│  uploader.js (JavaScript)           │
│  - Validate file (size, type)       │
│  - Call requestUploadUrl()          │
└──────┬──────────────────────────────┘
       │
       │ 2. AJAX: get_upload_url.php
       │    {filename, filesize, mimetype}
       │
       ▼
┌─────────────────────────────────────┐
│  get_upload_url.php (PHP)           │
│  - Verify permissions               │
│  - Generate S3 key                  │
│  - Call s3_client->get_presigned_post() │
│  - Create DB record (status: pending) │
└──────┬──────────────────────────────┘
       │
       │ 3. Return presigned POST data
       │    {url, fields, s3_key}
       │
       ▼
┌─────────────────────────────────────┐
│  uploader.js                        │
│  - POST directly to S3              │
│  - Track progress                   │
└──────┬──────────────────────────────┘
       │
       │ 4. Direct upload to S3
       │
       ▼
┌─────────────────────────────────────┐
│  AWS S3                             │
│  - Store video file                 │
└──────┬──────────────────────────────┘
       │
       │ 5. Upload complete (200 OK)
       │
       ▼
┌─────────────────────────────────────┐
│  uploader.js                        │
│  - Call confirmUpload()             │
└──────┬──────────────────────────────┘
       │
       │ 6. AJAX: confirm_upload.php
       │    {s3_key}
       │
       ▼
┌─────────────────────────────────────┐
│  confirm_upload.php (PHP)           │
│  - Verify file exists in S3         │
│  - Update DB (status: ready)        │
│  - Log success                      │
└──────┬──────────────────────────────┘
       │
       │ 7. Return success
       │
       ▼
┌─────────────────────────────────────┐
│  uploader.js                        │
│  - Show success message             │
└─────────────────────────────────────┘
```

### Playback Workflow

```
┌─────────────┐
│   Teacher   │
│   Browser   │
└──────┬──────┘
       │
       │ 1. Open submission page
       │
       ▼
┌─────────────────────────────────────┐
│  lib.php: view()                    │
│  - Retrieve S3 key from DB          │
│  - Render player template           │
└──────┬──────────────────────────────┘
       │
       │ 2. Load player.js
       │
       ▼
┌─────────────────────────────────────┐
│  player.js (JavaScript)             │
│  - Call getSignedUrl()              │
└──────┬──────────────────────────────┘
       │
       │ 3. AJAX: get_playback_url.php
       │    {submissionid}
       │
       ▼
┌─────────────────────────────────────┐
│  get_playback_url.php (PHP)         │
│  - Verify permissions               │
│  - Retrieve S3 key from DB          │
│  - Call cloudfront_client->get_signed_url() │
│  - Log playback access              │
└──────┬──────────────────────────────┘
       │
       │ 4. Return signed URL
       │    {playback_url}
       │
       ▼
┌─────────────────────────────────────┐
│  player.js                          │
│  - Initialize Video.js              │
│  - Set video source to signed URL   │
└──────┬──────────────────────────────┘
       │
       │ 5. Request video
       │
       ▼
┌─────────────────────────────────────┐
│  CloudFront                         │
│  - Validate signature               │
│  - Check expiration                 │
│  - Fetch from S3 (if not cached)    │
│  - Stream to browser                │
└─────────────────────────────────────┘
```

### Cleanup Workflow

```
┌─────────────────────────────────────┐
│  Moodle Cron                        │
│  - Runs daily at 2 AM               │
└──────┬──────────────────────────────┘
       │
       │ 1. Execute scheduled task
       │
       ▼
┌─────────────────────────────────────┐
│  cleanup_videos.php                 │
│  - Query videos older than retention│
│  - Loop through expired videos      │
└──────┬──────────────────────────────┘
       │
       │ 2. For each expired video
       │
       ▼
┌─────────────────────────────────────┐
│  - Call s3_client->delete_object()  │
│  - Call cloudfront_client->         │
│    create_invalidation()            │
│  - Update DB (status: deleted)      │
│  - Log deletion                     │
└─────────────────────────────────────┘
```

## Testing

### Unit Tests

#### Running Tests

```bash
# Run all plugin tests
php admin/tool/phpunit/cli/util.php --buildconfig
php vendor/bin/phpunit mod/assign/submission/s3video/tests/

# Run specific test
php vendor/bin/phpunit mod/assign/submission/s3video/tests/s3_client_test.php
```

#### S3 Client Tests

**File:** `tests/s3_client_test.php`

Tests S3 client methods with mocked AWS responses:
- `test_get_presigned_post()`: Verify presigned POST generation
- `test_object_exists()`: Test object existence check
- `test_delete_object()`: Test object deletion
- `test_get_object_metadata()`: Test metadata retrieval
- `test_error_handling()`: Test AWS API error handling

#### CloudFront Client Tests

**File:** `tests/cloudfront_client_test.php`

Tests CloudFront client methods:
- `test_get_signed_url()`: Verify signed URL generation
- `test_signature_format()`: Validate signature format
- `test_create_invalidation()`: Test cache invalidation
- `test_expired_url()`: Test URL expiration

#### Privacy Provider Tests

**File:** `tests/privacy_provider_test.php`

Tests GDPR compliance:
- `test_get_metadata()`: Verify metadata description
- `test_export_user_data()`: Test data export
- `test_delete_data_for_user()`: Test user deletion
- `test_delete_data_for_context()`: Test context deletion

### Integration Tests

Integration tests require actual AWS credentials and should be run in a test environment.

#### Setup Test Environment

```php
// config.php
$CFG->assignsubmission_s3video_test_bucket = 'test-bucket';
$CFG->assignsubmission_s3video_test_region = 'us-east-1';
$CFG->assignsubmission_s3video_test_access_key = 'AKIA...';
$CFG->assignsubmission_s3video_test_secret_key = 'secret...';
```

#### Upload Integration Test

```php
public function test_upload_workflow() {
    // 1. Create test assignment and submission
    $assignment = $this->create_test_assignment();
    $submission = $this->create_test_submission($assignment);
    
    // 2. Request upload URL
    $result = $this->request_upload_url($submission->id, 'test.mp4', 1048576);
    $this->assertTrue($result['success']);
    
    // 3. Upload to S3 (simulated)
    $this->upload_to_s3($result['upload_data']);
    
    // 4. Confirm upload
    $confirm = $this->confirm_upload($submission->id, $result['s3_key']);
    $this->assertTrue($confirm['success']);
    
    // 5. Verify database record
    $record = $DB->get_record('assignsubmission_s3video', 
        ['submission' => $submission->id]);
    $this->assertEquals('ready', $record->upload_status);
}
```

### Manual Testing Checklist

#### Upload Testing
- [ ] Upload small video (< 100 MB)
- [ ] Upload large video (> 1 GB)
- [ ] Upload maximum size video (5 GB)
- [ ] Test progress tracking
- [ ] Test upload cancellation
- [ ] Test network interruption recovery
- [ ] Test invalid file type rejection
- [ ] Test file size limit enforcement

#### Playback Testing
- [ ] Play video as teacher
- [ ] Play video as student (own submission)
- [ ] Verify student cannot view other submissions
- [ ] Test video controls (play, pause, seek, volume)
- [ ] Test on Chrome, Firefox, Safari, Edge
- [ ] Test on mobile devices (iOS, Android)
- [ ] Test signed URL expiration (after 24 hours)

#### Security Testing
- [ ] Attempt unauthorized upload
- [ ] Attempt unauthorized playback
- [ ] Verify AWS credentials not exposed in HTML/JS
- [ ] Test SQL injection in AJAX endpoints
- [ ] Test XSS in error messages
- [ ] Verify rate limiting works
- [ ] Test CSRF protection (sesskey)

#### Cleanup Testing
- [ ] Set retention to 1 day
- [ ] Upload test video
- [ ] Wait 2 days
- [ ] Run cron manually
- [ ] Verify video deleted from S3
- [ ] Verify database updated
- [ ] Verify CloudFront invalidation created

### Performance Testing

#### Load Testing

Test concurrent uploads:

```bash
# Use Apache Bench or similar tool
ab -n 100 -c 10 https://moodle.example.com/mod/assign/submission/s3video/ajax/get_upload_url.php
```

Expected results:
- Response time < 500ms
- No errors under normal load
- Rate limiting activates at threshold

#### Video Playback Performance

- Test 5 GB video playback
- Verify no buffering on good connection
- Test adaptive bitrate (if implemented)
- Monitor CloudFront cache hit ratio

## Extending the Plugin

### Adding New Video Formats

To support additional video formats:

1. Update validator.php:

```php
private static $allowed_mime_types = [
    'video/mp4',
    'video/quicktime',
    'video/x-msvideo',
    'video/x-matroska',
    'video/webm',
    'video/x-flv',  // Add new format
];
```

2. Update language strings:

```php
$string['allowedtypes'] = 'MP4, MOV, AVI, MKV, WebM, FLV';
```

3. Test upload and playback with new format

### Adding Video Thumbnails

To generate and display thumbnails:

1. Add thumbnail column to database:

```xml
<FIELD NAME="thumbnail_url" TYPE="text" NOTNULL="false"/>
```

2. Create thumbnail generation service:

```php
class thumbnail_generator {
    public function generate_thumbnail($s3_key) {
        // Use AWS MediaConvert or Lambda
        // Store thumbnail in S3
        // Return thumbnail URL
    }
}
```

3. Update upload confirmation to generate thumbnail:

```php
// In confirm_upload.php
$thumbnail_url = $thumbnail_generator->generate_thumbnail($s3_key);
$DB->set_field('assignsubmission_s3video', 'thumbnail_url', $thumbnail_url, 
    ['submission' => $submissionid]);
```

4. Display thumbnail in player template

### Adding Video Transcoding

To support multiple quality levels:

1. Configure AWS MediaConvert job template
2. Trigger transcoding on upload confirmation
3. Store multiple S3 keys (original, 1080p, 720p, 480p)
4. Update player to support quality selection

### Adding Analytics

To track detailed viewing analytics:

1. Add analytics table:

```sql
CREATE TABLE mdl_assignsubmission_s3v_analytics (
    id BIGINT PRIMARY KEY,
    submissionid BIGINT,
    userid BIGINT,
    event_type VARCHAR(50),  -- play, pause, seek, complete
    video_position INT,
    timestamp BIGINT
);
```

2. Add JavaScript tracking:

```javascript
player.on('play', function() {
    logAnalytics('play', player.currentTime());
});

player.on('pause', function() {
    logAnalytics('pause', player.currentTime());
});
```

3. Create analytics dashboard

### Custom Storage Backends

To support storage backends other than S3:

1. Create storage interface:

```php
interface storage_backend {
    public function get_upload_url($key, $size, $type);
    public function object_exists($key);
    public function delete_object($key);
    public function get_metadata($key);
}
```

2. Implement for different backends:
   - `s3_backend` (existing)
   - `azure_blob_backend`
   - `google_cloud_storage_backend`

3. Add backend selection in settings

### Webhooks for Upload Notifications

To notify external systems of uploads:

1. Add webhook URL setting
2. Send POST request on upload completion:

```php
// In confirm_upload.php
$webhook_url = get_config('assignsubmission_s3video', 'webhook_url');
if ($webhook_url) {
    $payload = [
        'event' => 'upload_complete',
        'submission_id' => $submissionid,
        's3_key' => $s3_key,
        'timestamp' => time()
    ];
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}
```

## Coding Standards

### Moodle Coding Style

Follow Moodle coding guidelines: https://moodledev.io/general/development/policies/codingstyle

#### PHP Standards

```php
// File header
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Class naming: component_classname
class assignsubmission_s3video_helper {
    
    // Method naming: lowercase with underscores
    public function get_upload_url($params) {
        // Code here
    }
    
    // Constants: UPPERCASE
    const MAX_FILE_SIZE = 5368709120; // 5 GB
    
    // Variables: lowercase with underscores
    $upload_status = 'pending';
}
```

#### JavaScript Standards

```javascript
// AMD module format
define(['jquery', 'core/ajax'], function($, Ajax) {
    
    // Use strict mode
    'use strict';
    
    // Object literal pattern
    var Uploader = {
        
        // Method naming: camelCase
        requestUploadUrl: function(params) {
            // Code here
        },
        
        // Private methods: prefix with underscore
        _validateFile: function(file) {
            // Code here
        }
    };
    
    return Uploader;
});
```

#### Database Queries

```php
// Use placeholders for security
$sql = "SELECT * FROM {assignsubmission_s3video} WHERE submission = :submissionid";
$params = ['submissionid' => $submissionid];
$record = $DB->get_record_sql($sql, $params);

// Use MUST_EXIST for required records
$record = $DB->get_record('assignsubmission_s3video', 
    ['submission' => $submissionid], '*', MUST_EXIST);

// Use transactions for multiple operations
$transaction = $DB->start_delegated_transaction();
try {
    $DB->insert_record('table1', $record1);
    $DB->update_record('table2', $record2);
    $transaction->allow_commit();
} catch (Exception $e) {
    $transaction->rollback($e);
}
```

#### Error Handling

```php
// Use moodle_exception for errors
throw new moodle_exception('errorkey', 'assignsubmission_s3video', '', $a);

// Log errors
debugging('Error message', DEBUG_DEVELOPER);

// Use try-catch for external API calls
try {
    $result = $s3_client->get_presigned_post($s3_key);
} catch (Exception $e) {
    logger::log_error($e->getMessage());
    throw new moodle_exception('s3error', 'assignsubmission_s3video');
}
```

#### Security Best Practices

```php
// Always require login
require_login();

// Check capabilities
require_capability('mod/assign:submit', $context);

// Validate sesskey for POST requests
require_sesskey();

// Clean parameters
$submissionid = required_param('submissionid', PARAM_INT);
$filename = required_param('filename', PARAM_FILE);

// Escape output
echo html_writer::tag('p', s($user_input));

// Use prepared statements (automatic with $DB)
$DB->get_record('table', ['id' => $id]); // Safe
```

#### Documentation

```php
/**
 * Generate presigned POST URL for S3 upload
 *
 * @param string $s3_key S3 object key
 * @param int $max_size Maximum file size in bytes
 * @param string $mime_type Allowed MIME type
 * @param int $expiry Expiration time in seconds
 * @return array Presigned POST data with url and fields
 * @throws moodle_exception If AWS API call fails
 */
public function get_presigned_post($s3_key, $max_size, $mime_type, $expiry = 3600) {
    // Implementation
}
```

### Code Review Checklist

Before submitting code:

- [ ] Follows Moodle coding style
- [ ] All strings in language file
- [ ] Database queries use placeholders
- [ ] Proper error handling
- [ ] Security checks (login, capabilities, sesskey)
- [ ] Input validation and sanitization
- [ ] PHPDoc comments for all functions
- [ ] Unit tests for new functionality
- [ ] No debugging code (var_dump, console.log)
- [ ] Tested in multiple browsers
- [ ] Passes Moodle code checker

### Running Code Checker

```bash
# Install Moodle code checker
composer require moodlehq/moodle-local_codechecker

# Run code checker
php local/codechecker/cli/run.php --path=mod/assign/submission/s3video
```

### Git Workflow

```bash
# Create feature branch
git checkout -b feature/add-thumbnails

# Make changes and commit
git add .
git commit -m "Add thumbnail generation support"

# Push to remote
git push origin feature/add-thumbnails

# Create pull request for review
```

## Troubleshooting

### Common Development Issues

#### AWS SDK Not Found

```
Error: Class 'Aws\S3\S3Client' not found
```

**Solution:** Install AWS SDK via Composer:
```bash
composer require aws/aws-sdk-php
```

#### Database Table Not Found

```
Error: Table 'mdl_assignsubmission_s3video' doesn't exist
```

**Solution:** Run database upgrade:
```bash
php admin/cli/upgrade.php
```

#### JavaScript Module Not Loading

```
Error: Script error for "mod_assign/submission_s3video/uploader"
```

**Solution:** Purge caches:
```bash
php admin/cli/purge_caches.php
```

Or in Moodle: Site administration > Development > Purge all caches

#### CORS Error in Browser

```
Access to XMLHttpRequest at 'https://bucket.s3.amazonaws.com' has been blocked by CORS policy
```

**Solution:** Update S3 bucket CORS configuration to include your Moodle domain

## Additional Resources

### Moodle Development
- [Moodle Developer Documentation](https://moodledev.io/)
- [Assignment Plugin Development](https://moodledev.io/docs/apis/plugintypes/assign)
- [Database API](https://moodledev.io/docs/apis/core/dml)
- [JavaScript Modules](https://moodledev.io/docs/guides/javascript)

### AWS Documentation
- [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/)
- [S3 Presigned POST](https://docs.aws.amazon.com/AmazonS3/latest/API/sigv4-post-example.html)
- [CloudFront Signed URLs](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-signed-urls.html)

### Video.js
- [Video.js Documentation](https://videojs.com/guides/)
- [Video.js Plugins](https://videojs.com/plugins/)

## Support

For development questions:
- Moodle Developer Forums: https://moodle.org/mod/forum/view.php?id=55
- GitHub Issues: [Your repository URL]

---

**Last Updated:** 2025-10-26
**Plugin Version:** 1.0.0
