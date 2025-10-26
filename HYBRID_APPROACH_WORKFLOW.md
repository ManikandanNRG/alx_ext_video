# Hybrid Approach - Complete Workflow & Architecture

## 🎯 Overview

The hybrid approach creates a **shared library plugin** that provides common functionality, with **separate storage-specific plugins** for Cloudflare Stream and S3 + CloudFront.

This approach maximizes code reuse while maintaining clean separation between storage backends.

---

## 📦 Three-Component Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMPONENT STRUCTURE                           │
└─────────────────────────────────────────────────────────────────┘

1. Shared Library (local/videostorage)
   ├── Storage interface
   ├── Common utilities
   ├── Shared validation
   ├── Shared logging
   └── Shared rate limiting

2. Cloudflare Stream Plugin (mod/assign/submission/cloudflarestream)
   ├── Implements storage interface
   ├── Cloudflare-specific API client
   ├── Uses shared utilities
   └── Cloudflare Stream player

3. S3 + CloudFront Plugin (mod/assign/submission/s3video)
   ├── Implements storage interface
   ├── AWS S3/CloudFront API client
   ├── Uses shared utilities
   └── Video.js player
```

---

## 🏗️ Detailed Architecture

### Component 1: Shared Library (`local/videostorage`)

```
local/videostorage/
├── version.php
├── lib.php
├── lang/en/
│   └── local_videostorage.php
│
├── classes/
│   ├── storage_interface.php          # Interface all storage backends must implement
│   ├── base_storage.php               # Abstract base class with common logic
│   ├── validator.php                  # Input validation (reused)
│   ├── logger.php                     # Event logging (reused)
│   ├── rate_limiter.php               # Rate limiting (reused)
│   ├── retry_handler.php              # Retry logic (reused)
│   └── video_metadata.php             # Video metadata handling
│
└── tests/
    ├── storage_interface_test.php
    ├── validator_test.php
    └── logger_test.php
```

#### Storage Interface Definition

```php
namespace local_videostorage;

interface storage_interface {
    /**
     * Get upload URL/credentials for direct browser upload
     * 
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID
     * @param string $filename Original filename
     * @param int $filesize File size in bytes
     * @param string $mimetype MIME type
     * @return object Upload data (URL, credentials, etc.)
     */
    public function get_upload_url($userid, $assignmentid, $filename, $filesize, $mimetype);
    
    /**
     * Confirm upload completion and get video identifier
     * 
     * @param string $upload_reference Reference from get_upload_url
     * @param array $metadata Additional metadata (duration, etc.)
     * @return string Video identifier (UID for Cloudflare, S3 key for S3)
     */
    public function confirm_upload($upload_reference, $metadata = []);
    
    /**
     * Get signed playback URL
     * 
     * @param string $video_id Video identifier
     * @param int $expiry_seconds Expiration time in seconds
     * @return string Signed playback URL
     */
    public function get_playback_url($video_id, $expiry_seconds = 86400);
    
    /**
     * Delete video from storage
     * 
     * @param string $video_id Video identifier
     * @return bool Success
     */
    public function delete_video($video_id);
    
    /**
     * Check if video exists
     * 
     * @param string $video_id Video identifier
     * @return bool Exists
     */
    public function video_exists($video_id);
    
    /**
     * Get video metadata
     * 
     * @param string $video_id Video identifier
     * @return object Metadata (size, duration, status, etc.)
     */
    public function get_video_metadata($video_id);
    
    /**
     * Get storage backend name
     * 
     * @return string Backend name ('cloudflare', 's3', etc.)
     */
    public function get_backend_name();
    
    /**
     * Get estimated storage cost
     * 
     * @param int $total_bytes Total bytes stored
     * @param int $total_views Total video views
     * @return float Estimated monthly cost in USD
     */
    public function estimate_cost($total_bytes, $total_views);
}
```

#### Base Storage Class

```php
namespace local_videostorage;

abstract class base_storage implements storage_interface {
    protected $config;
    protected $validator;
    protected $logger;
    protected $rate_limiter;
    
    public function __construct() {
        $this->validator = new validator();
        $this->logger = new logger($this->get_backend_name());
        $this->rate_limiter = new rate_limiter();
        $this->load_config();
    }
    
    /**
     * Load configuration (implemented by child classes)
     */
    abstract protected function load_config();
    
    /**
     * Validate upload request (common logic)
     */
    protected function validate_upload_request($userid, $assignmentid, $filename, $filesize, $mimetype) {
        // Check rate limiting
        if (!$this->rate_limiter->check_upload_limit($userid)) {
            throw new \moodle_exception('rate_limit_exceeded');
        }
        
        // Validate file size
        if (!$this->validator->validate_file_size($filesize)) {
            throw new \moodle_exception('file_too_large');
        }
        
        // Validate MIME type
        if (!$this->validator->validate_mime_type($mimetype)) {
            throw new \moodle_exception('invalid_mime_type');
        }
        
        // Validate filename
        if (!$this->validator->validate_filename($filename)) {
            throw new \moodle_exception('invalid_filename');
        }
        
        return true;
    }
    
    /**
     * Log upload success (common logic)
     */
    protected function log_upload_success($userid, $assignmentid, $video_id, $filesize, $duration = null) {
        $this->logger->log_upload_success($userid, $assignmentid, $video_id, $filesize, $duration);
    }
    
    /**
     * Log upload failure (common logic)
     */
    protected function log_upload_failure($userid, $assignmentid, $error_code, $error_message) {
        $this->logger->log_upload_failure($userid, $assignmentid, $error_code, $error_message);
    }
    
    /**
     * Log playback access (common logic)
     */
    protected function log_playback_access($userid, $assignmentid, $video_id, $user_role) {
        $this->logger->log_playback_access($userid, $assignmentid, $video_id, $user_role);
    }
}
```

---

### Component 2: Cloudflare Stream Plugin

```
mod/assign/submission/cloudflarestream/
├── version.php
├── lib.php                            # Main plugin class
├── locallib.php                       # Plugin detection class
├── settings.php
├── dashboard.php
├── videomanagement.php
│
├── classes/
│   └── storage/
│       └── cloudflare_storage.php     # Implements storage_interface
│
├── ajax/
│   ├── get_upload_url.php             # Uses cloudflare_storage
│   ├── confirm_upload.php             # Uses cloudflare_storage
│   └── get_playback_token.php         # Uses cloudflare_storage
│
└── ... (rest of existing structure)
```

#### Cloudflare Storage Implementation

```php
namespace assignsubmission_cloudflarestream\storage;

use local_videostorage\base_storage;
use assignsubmission_cloudflarestream\api\cloudflare_client;

class cloudflare_storage extends base_storage {
    private $api_client;
    
    protected function load_config() {
        $this->config = (object)[
            'api_token' => get_config('assignsubmission_cloudflarestream', 'apitoken'),
            'account_id' => get_config('assignsubmission_cloudflarestream', 'accountid'),
        ];
        
        $this->api_client = new cloudflare_client(
            $this->config->api_token,
            $this->config->account_id
        );
    }
    
    public function get_upload_url($userid, $assignmentid, $filename, $filesize, $mimetype) {
        // Validate using parent class
        $this->validate_upload_request($userid, $assignmentid, $filename, $filesize, $mimetype);
        
        try {
            // Get direct upload URL from Cloudflare
            $upload_data = $this->api_client->get_direct_upload_url();
            
            return (object)[
                'upload_url' => $upload_data->uploadURL,
                'video_uid' => $upload_data->uid,
                'protocol' => 'tus',
            ];
            
        } catch (\Exception $e) {
            $this->log_upload_failure($userid, $assignmentid, 'api_error', $e->getMessage());
            throw $e;
        }
    }
    
    public function confirm_upload($upload_reference, $metadata = []) {
        // Cloudflare processes video automatically
        // Just verify it exists
        if ($this->video_exists($upload_reference)) {
            return $upload_reference; // Return video UID
        }
        throw new \moodle_exception('video_not_found');
    }
    
    public function get_playback_url($video_id, $expiry_seconds = 86400) {
        try {
            $token = $this->api_client->generate_signed_token($video_id, $expiry_seconds);
            return "https://customer-{$this->config->account_id}.cloudflarestream.com/{$video_id}/iframe?token={$token}";
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    public function delete_video($video_id) {
        try {
            $this->api_client->delete_video($video_id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function video_exists($video_id) {
        try {
            $details = $this->api_client->get_video_details($video_id);
            return !empty($details);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function get_video_metadata($video_id) {
        return $this->api_client->get_video_details($video_id);
    }
    
    public function get_backend_name() {
        return 'cloudflare';
    }
    
    public function estimate_cost($total_bytes, $total_views) {
        // Cloudflare Stream pricing
        $minutes_stored = ($total_bytes / 1024 / 1024 / 5) / 60; // Assume 5 Mbps average
        $minutes_delivered = $minutes_stored * $total_views;
        
        $storage_cost = ($minutes_stored / 1000) * 5; // $5 per 1000 minutes
        $delivery_cost = ($minutes_delivered / 1000) * 1; // $1 per 1000 minutes
        
        return max(5, $storage_cost + $delivery_cost); // $5 minimum
    }
}
```

---

### Component 3: S3 + CloudFront Plugin

```
mod/assign/submission/s3video/
├── version.php
├── lib.php                            # Main plugin class
├── locallib.php                       # Plugin detection class
├── settings.php
├── dashboard.php
├── videomanagement.php
│
├── classes/
│   ├── storage/
│   │   └── s3_storage.php             # Implements storage_interface
│   └── api/
│       ├── s3_client.php              # AWS S3 SDK wrapper
│       └── cloudfront_client.php      # CloudFront SDK wrapper
│
├── ajax/
│   ├── get_upload_url.php             # Uses s3_storage
│   ├── confirm_upload.php             # Uses s3_storage
│   └── get_playback_url.php           # Uses s3_storage
│
└── ... (similar structure to cloudflarestream)
```

#### S3 Storage Implementation

```php
namespace assignsubmission_s3video\storage;

use local_videostorage\base_storage;
use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\cloudfront_client;

class s3_storage extends base_storage {
    private $s3_client;
    private $cloudfront_client;
    
    protected function load_config() {
        $this->config = (object)[
            'access_key' => get_config('assignsubmission_s3video', 'aws_access_key'),
            'secret_key' => get_config('assignsubmission_s3video', 'aws_secret_key'),
            'bucket' => get_config('assignsubmission_s3video', 's3_bucket'),
            'region' => get_config('assignsubmission_s3video', 's3_region'),
            'cloudfront_domain' => get_config('assignsubmission_s3video', 'cloudfront_domain'),
            'cloudfront_keypair_id' => get_config('assignsubmission_s3video', 'cloudfront_keypair_id'),
            'cloudfront_private_key' => get_config('assignsubmission_s3video', 'cloudfront_private_key'),
        ];
        
        $this->s3_client = new s3_client(
            $this->config->access_key,
            $this->config->secret_key,
            $this->config->bucket,
            $this->config->region
        );
        
        $this->cloudfront_client = new cloudfront_client(
            $this->config->cloudfront_domain,
            $this->config->cloudfront_keypair_id,
            $this->config->cloudfront_private_key
        );
    }
    
    public function get_upload_url($userid, $assignmentid, $filename, $filesize, $mimetype) {
        // Validate using parent class
        $this->validate_upload_request($userid, $assignmentid, $filename, $filesize, $mimetype);
        
        try {
            // Generate unique S3 key
            $s3_key = $this->generate_s3_key($userid, $filename);
            
            // Get presigned POST from S3
            $presigned_post = $this->s3_client->get_presigned_post($s3_key, $filesize, $mimetype);
            
            return (object)[
                'presigned_url' => $presigned_post['url'],
                'fields' => $presigned_post['fields'],
                's3_key' => $s3_key,
                'protocol' => 'post',
            ];
            
        } catch (\Exception $e) {
            $this->log_upload_failure($userid, $assignmentid, 'api_error', $e->getMessage());
            throw $e;
        }
    }
    
    public function confirm_upload($upload_reference, $metadata = []) {
        // Verify file exists in S3
        if ($this->video_exists($upload_reference)) {
            return $upload_reference; // Return S3 key
        }
        throw new \moodle_exception('video_not_found');
    }
    
    public function get_playback_url($video_id, $expiry_seconds = 86400) {
        try {
            $signed_url = $this->cloudfront_client->get_signed_url($video_id, $expiry_seconds);
            return $signed_url;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    public function delete_video($video_id) {
        try {
            $this->s3_client->delete_object($video_id);
            // Invalidate CloudFront cache
            $this->cloudfront_client->create_invalidation($video_id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function video_exists($video_id) {
        try {
            return $this->s3_client->object_exists($video_id);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function get_video_metadata($video_id) {
        return $this->s3_client->get_object_metadata($video_id);
    }
    
    public function get_backend_name() {
        return 's3';
    }
    
    public function estimate_cost($total_bytes, $total_views) {
        // AWS S3 + CloudFront pricing
        $storage_gb = $total_bytes / 1024 / 1024 / 1024;
        $transfer_gb = $storage_gb * $total_views;
        
        $storage_cost = $storage_gb * 0.023; // $0.023 per GB/month
        $transfer_cost = $transfer_gb * 0.085; // $0.085 per GB transferred
        
        return $storage_cost + $transfer_cost;
    }
    
    private function generate_s3_key($userid, $filename) {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return "videos/{$userid}/{$timestamp}_{$random}/{$safe_filename}";
    }
}
```

---

## 🔄 Unified Workflow

### Upload Workflow (Both Plugins)

```
┌─────────────────────────────────────────────────────────────────┐
│                    UNIFIED UPLOAD WORKFLOW                       │
└─────────────────────────────────────────────────────────────────┘

Student Browser
      │
      │ 1. Select video file
      │
      ▼
Plugin JavaScript (uploader.js)
      │
      │ 2. Validate file (size, type)
      │
      ▼
AJAX Request: get_upload_url.php
      │
      ▼
Plugin Backend (lib.php)
      │
      │ 3. Get storage instance
      │    $storage = $this->get_storage_instance();
      │
      ▼
Storage Interface
      │
      ├─────────────────┬─────────────────┐
      │                 │                 │
      ▼                 ▼                 ▼
Cloudflare Storage  S3 Storage    (Future: Azure, etc.)
      │                 │
      │ 4a. Get tus URL │ 4b. Get presigned POST
      │                 │
      └─────────────────┴─────────────────┘
                        │
                        ▼
              Return upload data
                        │
                        ▼
            Student Browser (JavaScript)
                        │
                        │ 5. Upload to storage
                        │    (tus or POST)
                        │
                        ▼
              Storage Provider
              (Cloudflare or S3)
                        │
                        │ 6. Store video
                        │
                        ▼
            Student Browser (JavaScript)
                        │
                        │ 7. Confirm upload
                        │
                        ▼
      AJAX Request: confirm_upload.php
                        │
                        ▼
            Plugin Backend (lib.php)
                        │
                        │ 8. Get storage instance
                        │    $storage = $this->get_storage_instance();
                        │
                        ▼
            Storage Interface
                        │
                        │ 9. Confirm upload
                        │    verify_exists()
                        │
                        ▼
            Update Database
            Log Success
                        │
                        ▼
                  [Complete]
```

### Playback Workflow (Both Plugins)

```
┌─────────────────────────────────────────────────────────────────┐
│                   UNIFIED PLAYBACK WORKFLOW                      │
└─────────────────────────────────────────────────────────────────┘

Teacher Browser
      │
      │ 1. Open submission
      │
      ▼
Plugin Backend (lib.php view())
      │
      │ 2. Get video_id from database
      │
      ▼
AJAX Request: get_playback_url.php
      │
      ▼
Plugin Backend
      │
      │ 3. Verify permissions
      │
      ▼
      │ 4. Get storage instance
      │    $storage = $this->get_storage_instance();
      │
      ▼
Storage Interface
      │
      ├─────────────────┬─────────────────┐
      │                 │                 │
      ▼                 ▼                 ▼
Cloudflare Storage  S3 Storage    (Future: Azure, etc.)
      │                 │
      │ 5a. JWT token   │ 5b. CloudFront signed URL
      │                 │
      └─────────────────┴─────────────────┘
                        │
                        ▼
              Return signed URL
                        │
                        ▼
            Teacher Browser (JavaScript)
                        │
                        │ 6. Load player
                        │
                        ▼
              Player Component
              │
              ├─────────────────┬─────────────────┐
              │                 │                 │
              ▼                 ▼                 ▼
      Cloudflare Player   Video.js Player   (Future: Others)
              │                 │
              │ 7. Stream video │
              │                 │
              └─────────────────┴─────────────────┘
                        │
                        ▼
                  [Play Video]
```

---

## 🔧 Implementation Details

### How Plugins Use Shared Library

#### In Plugin's lib.php

```php
namespace assignsubmission_cloudflarestream;

// Import shared library
use local_videostorage\validator;
use local_videostorage\logger;
use local_videostorage\rate_limiter;

// Import plugin's storage implementation
use assignsubmission_cloudflarestream\storage\cloudflare_storage;

class assign_submission_cloudflarestream extends assign_submission_plugin {
    private $storage;
    
    public function __construct(assign $assignment, stdClass $submission) {
        parent::__construct($assignment, $submission);
        
        // Initialize storage backend
        $this->storage = new cloudflare_storage();
    }
    
    /**
     * Get storage instance (used throughout plugin)
     */
    private function get_storage_instance() {
        return $this->storage;
    }
    
    // Rest of plugin methods use $this->storage
}
```

#### In AJAX Endpoints

```php
// ajax/get_upload_url.php

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\storage\cloudflare_storage;

// Authenticate
require_login();

// Get parameters
$assignmentid = required_param('assignmentid', PARAM_INT);
$filename = required_param('filename', PARAM_TEXT);
$filesize = required_param('filesize', PARAM_INT);
$mimetype = required_param('mimetype', PARAM_TEXT);

// Get storage instance
$storage = new cloudflare_storage();

try {
    // Use storage interface method
    $upload_data = $storage->get_upload_url(
        $USER->id,
        $assignmentid,
        $filename,
        $filesize,
        $mimetype
    );
    
    // Return JSON
    echo json_encode([
        'success' => true,
        'data' => $upload_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

## 📊 Database Schema (Shared Structure)

Both plugins use similar database structure:

### Cloudflare Plugin Tables
```
mdl_assignsubmission_cfstream
mdl_assignsubmission_cfs_log
```

### S3 Plugin Tables
```
mdl_assignsubmission_s3video
mdl_assignsubmission_s3v_log
```

### Shared Fields (Both Tables)
```
- id
- assignment
- submission
- video_id (video_uid for Cloudflare, s3_key for S3)
- upload_status
- file_size
- duration
- upload_timestamp
- deleted_timestamp
- error_message
```

---

## 🎯 Benefits of Hybrid Approach

### 1. Code Reuse
```
Shared Library (local/videostorage):
  - validator.php         ✅ Used by both plugins
  - logger.php            ✅ Used by both plugins
  - rate_limiter.php      ✅ Used by both plugins
  - retry_handler.php     ✅ Used by both plugins
  - storage_interface.php ✅ Defines contract for both

Estimated Code Reuse: 40-50%
```

### 2. Consistency
```
Both plugins:
  - Use same validation rules
  - Use same logging format
  - Use same rate limiting
  - Use same error handling
  - Follow same interface contract
```

### 3. Extensibility
```
Adding new storage backend (e.g., Azure):

1. Create new plugin: mod/assign/submission/azurevideo
2. Implement storage_interface
3. Use shared library utilities
4. Done!

No changes needed to:
  - Shared library
  - Existing plugins
```

### 4. Maintainability
```
Bug fix in validation logic:
  - Fix once in local/videostorage/classes/validator.php
  - Both plugins benefit automatically
  
New feature in logging:
  - Add once in local/videostorage/classes/logger.php
  - Both plugins get it automatically
```

---

## 🔄 Migration Path

### Phase 1: Create Shared Library (Week 1)
1. Create `local/videostorage` plugin
2. Extract common code from Cloudflare plugin:
   - validator.php
   - logger.php
   - rate_limiter.php
   - retry_handler.php
3. Create storage_interface.php
4. Create base_storage.php
5. Write tests

### Phase 2: Refactor Cloudflare Plugin (Week 2)
1. Create cloudflare_storage.php implementing interface
2. Update lib.php to use storage instance
3. Update AJAX endpoints to use storage instance
4. Remove duplicated code (now in shared library)
5. Test thoroughly

### Phase 3: Create S3 Plugin (Week 3-4)
1. Copy structure from Cloudflare plugin
2. Create s3_storage.php implementing interface
3. Create AWS SDK wrappers (s3_client, cloudfront_client)
4. Update AJAX endpoints
5. Create Video.js player integration
6. Test thoroughly

### Phase 4: Documentation & Release (Week 5)
1. Document shared library API
2. Document how to create new storage backends
3. Create migration guide
4. Release all three components

---

## 📈 Comparison: Separate vs Hybrid

| Aspect | Separate Plugins | Hybrid Approach |
|--------|-----------------|-----------------|
| **Code Reuse** | ~30% | ~50% |
| **Initial Effort** | Lower | Higher |
| **Long-term Maintenance** | Higher | Lower |
| **Adding New Backend** | Copy & modify | Implement interface |
| **Bug Fixes** | Fix in each plugin | Fix once in library |
| **Consistency** | Manual | Automatic |
| **Complexity** | Lower | Higher |
| **Best For** | 2-3 backends | 3+ backends |

---

## 🎯 Recommendation

### For Your Situation:

**Start with Separate Plugins, Migrate to Hybrid Later**

#### Phase 1 (Now): Separate S3 Plugin
- Quick to implement (3-6 days)
- Can test immediately
- No risk to existing Cloudflare plugin
- Get S3 plugin working and released

#### Phase 2 (Later): Migrate to Hybrid
- Once both plugins are stable
- Extract common code to shared library
- Refactor both plugins to use library
- Better long-term architecture

### Why This Approach?

1. **Faster Time to Market**: Get S3 plugin working now
2. **Lower Risk**: Don't refactor working Cloudflare plugin yet
3. **Proven Design**: Test separate plugins first
4. **Easier Migration**: Migrate when you have time
5. **User Value**: Users get S3 option sooner

---

## 📋 Next Steps

### Immediate (This Week):
1. Create S3 plugin as separate plugin
2. Copy structure from Cloudflare plugin
3. Implement S3-specific code
4. Test with AWS free tier
5. Release v1.0.0

### Future (Next Month):
1. Identify common code between plugins
2. Create shared library plugin
3. Refactor both plugins to use library
4. Release v2.0.0 with hybrid architecture

---

**Which approach do you prefer?**

1. **Separate S3 Plugin** (faster, simpler)
2. **Hybrid Approach** (better long-term, more complex)
3. **Start Separate, Migrate to Hybrid Later** (recommended)

Let me know and I'll create the detailed implementation plan!
