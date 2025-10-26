# S3 + CloudFront Video Plugin - Design Document

## Overview

This plugin enables students to upload large video files (up to 5 GB) directly to AWS S3, with delivery via CloudFront CDN. Videos are played using Video.js HTML5 player.

### Key Design Principles

1. **Zero Server Load**: Video data never touches Moodle server
2. **AWS Native**: Leverage AWS S3 and CloudFront services
3. **Security First**: CloudFront signed URLs for access control
4. **Cost Effective**: Pay only for what you use (no minimum fees)
5. **Seamless Integration**: Works within Moodle's assignment workflow

## Architecture

### High-Level Architecture

```
Student Browser → Moodle (get presigned POST) → Direct upload to S3
Teacher Browser → Moodle (get signed URL) → CloudFront → S3 → Video playback
```

### Component Architecture

1. **Presentation Layer** (Browser)
   - Upload interface with progress tracking
   - Video.js player for playback
   - Standard Moodle grading interface

2. **Application Layer** (Moodle Server)
   - Plugin: assignsubmission_s3video
   - AWS SDK integration (S3 + CloudFront)
   - Authentication and authorization
   - Database operations

3. **Infrastructure Layer** (AWS)
   - S3 bucket for video storage
   - CloudFront distribution for delivery
   - IAM credentials for API access

## Components and Interfaces

### 1. Plugin Structure

```
mod/assign/submission/s3video/
├── version.php
├── lib.php
├── locallib.php
├── settings.php
├── dashboard.php
├── videomanagement.php
├── styles.css
│
├── db/
│   ├── install.xml
│   ├── upgrade.php
│   ├── access.php
│   ├── tasks.php
│   └── caches.php
│
├── classes/
│   ├── api/
│   │   ├── s3_client.php
│   │   └── cloudfront_client.php
│   ├── privacy/
│   │   └── provider.php
│   ├── task/
│   │   └── cleanup_videos.php
│   ├── logger.php
│   ├── validator.php
│   ├── rate_limiter.php
│   └── retry_handler.php
│
├── ajax/
│   ├── get_upload_url.php
│   ├── confirm_upload.php
│   └── get_playback_url.php
│
├── amd/src/
│   ├── uploader.js
│   └── player.js
│
├── templates/
│   ├── upload_form.mustache
│   └── player.mustache
│
├── lang/en/
│   └── assignsubmission_s3video.php
│
└── tests/
    ├── s3_client_test.php
    ├── cloudfront_client_test.php
    └── privacy_provider_test.php
```

### 2. Database Schema

**Table**: `mdl_assignsubmission_s3video`

```sql
CREATE TABLE mdl_assignsubmission_s3video (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    assignment BIGINT NOT NULL,
    submission BIGINT NOT NULL,
    s3_key VARCHAR(500) NOT NULL,
    s3_bucket VARCHAR(255) NOT NULL,
    upload_status VARCHAR(50) NOT NULL,
    file_size BIGINT,
    duration INT,
    mime_type VARCHAR(100),
    upload_timestamp BIGINT NOT NULL,
    deleted_timestamp BIGINT,
    error_message TEXT,
    UNIQUE KEY (submission),
    INDEX idx_s3_key (s3_key),
    INDEX idx_upload_timestamp (upload_timestamp)
);
```

**Table**: `mdl_assignsubmission_s3v_log`

```sql
CREATE TABLE mdl_assignsubmission_s3v_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    userid BIGINT,
    assignmentid BIGINT,
    submissionid BIGINT,
    s3_key VARCHAR(500),
    event_type VARCHAR(50) NOT NULL,
    error_code VARCHAR(100),
    error_message TEXT,
    error_context TEXT,
    file_size BIGINT,
    duration INT,
    retry_count INT,
    user_role VARCHAR(50),
    timestamp BIGINT NOT NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_timestamp (timestamp)
);
```

### 3. AWS S3 Client

**Class**: `s3_client.php`

```php
class s3_client {
    private $s3_client;
    private $bucket;
    private $region;
    
    public function __construct($access_key, $secret_key, $bucket, $region);
    public function get_presigned_post($s3_key, $max_size, $mime_type, $expiry = 3600);
    public function object_exists($s3_key);
    public function delete_object($s3_key);
    public function get_object_metadata($s3_key);
}
```

**Methods**:
- `get_presigned_post()`: Generate presigned POST for browser upload
- `object_exists()`: Check if video exists in S3
- `delete_object()`: Delete video from S3
- `get_object_metadata()`: Get file size, content type, etc.

### 4. CloudFront Client

**Class**: `cloudfront_client.php`

```php
class cloudfront_client {
    private $domain;
    private $keypair_id;
    private $private_key;
    
    public function __construct($domain, $keypair_id, $private_key);
    public function get_signed_url($s3_key, $expiry_seconds = 86400);
    public function create_invalidation($s3_key);
}
```

**Methods**:
- `get_signed_url()`: Generate CloudFront signed URL
- `create_invalidation()`: Invalidate CloudFront cache after deletion

### 5. Upload Flow

```
1. Student selects video file
2. JavaScript validates file (size, type)
3. AJAX request to get_upload_url.php
4. PHP generates S3 presigned POST:
   - S3 key: videos/{userid}/{timestamp}_{random}/{filename}
   - Policy: max size, content type, expiration
5. Return presigned POST data to browser
6. JavaScript uploads directly to S3 using POST
7. Monitor progress with XMLHttpRequest
8. On success, AJAX request to confirm_upload.php
9. PHP verifies file exists in S3
10. Store S3 key in database
```

### 6. Playback Flow

```
1. Teacher opens submission
2. PHP retrieves S3 key from database
3. Verify user has permission
4. AJAX request to get_playback_url.php
5. PHP generates CloudFront signed URL:
   - Resource: https://d123.cloudfront.net/{s3_key}
   - Expiration: now + 24 hours
   - Signature: RSA-SHA1 with private key
6. Return signed URL to browser
7. JavaScript initializes Video.js player
8. Player loads video from CloudFront
9. CloudFront validates signature and serves video
```

### 7. Security Implementation

**Authentication**:
- All requests require valid Moodle session
- User must be logged in

**Authorization**:
- Students: Can upload to own submissions only
- Teachers: Can view submissions in their courses
- Admins: Can view all submissions

**CloudFront Signed URLs**:
```php
function generate_signed_url($s3_key, $expiry_seconds) {
    $resource = "https://{$cloudfront_domain}/{$s3_key}";
    $expires = time() + $expiry_seconds;
    
    $policy = json_encode([
        'Statement' => [[
            'Resource' => $resource,
            'Condition' => [
                'DateLessThan' => ['AWS:EpochTime' => $expires]
            ]
        ]]
    ]);
    
    $signature = base64_encode(
        openssl_sign($policy, $private_key, OPENSSL_ALGO_SHA1)
    );
    
    return "{$resource}?Expires={$expires}&Signature={$signature}&Key-Pair-Id={$keypair_id}";
}
```

## Data Models

### Video Lifecycle

```
pending → uploading → ready → deleted
              ↓
            error
```

### S3 Key Format

```
videos/{userid}/{timestamp}_{random}/{filename}

Example:
videos/123/1698345600_a7b3c/myvideo.mp4
```

## Error Handling

### Upload Errors

| Error | Handling |
|-------|----------|
| File too large | Client-side validation, show error |
| Invalid file type | Client-side validation, show error |
| Network failure | Show retry button |
| S3 API error | Log error, show user-friendly message |
| Presigned POST expired | Request new POST, retry |

### Playback Errors

| Error | Handling |
|-------|----------|
| Video not found | Show "Video unavailable" message |
| Signed URL expired | Request new URL, reload player |
| Permission denied | Show "Access denied" message |
| CloudFront error | Show retry button |

## Testing Strategy

### Unit Tests
- S3 client methods (mocked)
- CloudFront client methods (mocked)
- Validator functions
- Logger functions

### Integration Tests
- Upload workflow (with test S3 bucket)
- Playback workflow
- Access control
- Cleanup task

### Manual Tests
- Upload 5 GB video
- Test on multiple browsers
- Test mobile devices
- Test concurrent uploads

## Performance Considerations

### Upload
- Direct browser-to-S3 (no server bottleneck)
- Progress tracking via XMLHttpRequest
- Client-side validation reduces failed uploads

### Playback
- CloudFront CDN (low latency globally)
- Video.js adaptive streaming
- No Moodle server load

### Database
- Indexed queries (s3_key, timestamp)
- Minimal storage (metadata only)

## Cost Estimation

### AWS Pricing (as of 2025)
- **S3 Storage**: $0.023 per GB/month
- **CloudFront Transfer**: $0.085 per GB
- **S3 Requests**: Negligible

### Example (100 videos, 500 MB each, 1 view each)
- Storage: 50 GB × $0.023 = $1.15/month
- Transfer: 50 GB × $0.085 = $4.25/month
- **Total: ~$5.40/month**

### Free Tier (First 12 months)
- S3: 5 GB storage
- CloudFront: 50 GB transfer
- **Cost: $0 for small deployments**

## Deployment Strategy

### Phase 1: Core Implementation
- Plugin structure
- S3 client
- CloudFront client
- Upload workflow
- Playback workflow

### Phase 2: Features
- Admin dashboard
- Video management
- Cleanup task
- GDPR compliance

### Phase 3: Testing
- Unit tests
- Integration tests
- Manual testing
- AWS free tier testing

### Phase 4: Documentation
- Installation guide
- AWS setup guide
- User guides
- API documentation

## AWS Setup Requirements

### 1. S3 Bucket
```bash
# Create bucket
aws s3 mb s3://your-moodle-videos --region us-east-1

# Enable CORS
aws s3api put-bucket-cors --bucket your-moodle-videos --cors-configuration file://cors.json
```

**cors.json**:
```json
{
  "CORSRules": [{
    "AllowedOrigins": ["https://your-moodle-site.com"],
    "AllowedMethods": ["GET", "POST", "PUT"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3000
  }]
}
```

### 2. CloudFront Distribution
- Origin: S3 bucket
- Restrict Bucket Access: Yes (OAI)
- Trusted Signers: Self
- Create CloudFront Key Pair

### 3. IAM User
Permissions:
- `s3:PutObject`
- `s3:GetObject`
- `s3:DeleteObject`
- `s3:HeadObject`
- `cloudfront:CreateInvalidation`

## Future Enhancements

### Phase 2
- Video thumbnails
- Multiple quality levels
- Batch operations
- Advanced analytics

### Phase 3
- Video transcoding (AWS MediaConvert)
- Captions/subtitles
- Video annotations
- Mobile app support

## Conclusion

This design provides a cost-effective, scalable solution for large video submissions using AWS S3 and CloudFront. The architecture eliminates server load while maintaining seamless Moodle integration and strong security.
