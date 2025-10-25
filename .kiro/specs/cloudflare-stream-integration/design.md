# Design Document: Cloudflare Stream Integration for Moodle

## Overview

This design document outlines the technical architecture for integrating Cloudflare Stream with an IOMAD-based Moodle environment. The solution enables students to upload large video files (up to 5 GB) as assignment submissions while offloading storage and streaming to Cloudflare's infrastructure. The design ensures seamless integration with Moodle's existing assignment workflow while maintaining security, performance, and cost-effectiveness.

### Key Design Principles

1. **Zero Server Load**: Video data never touches the Moodle server during upload or playback
2. **Security First**: All video access is authenticated and scoped to authorized users only
3. **Seamless Integration**: The feature integrates naturally into Moodle's existing assignment submission and grading workflows
4. **Minimal Complexity**: Leverage Cloudflare's managed services to minimize custom infrastructure

## Architecture

### High-Level Architecture

```
┌─────────────────┐
│  Student/Teacher│
│     Browser     │
└────────┬────────┘
         │
         ├─────────────────────────────────────┐
         │                                     │
         │ (1) Request Upload URL              │ (4) Direct Video Upload
         │ (5) Playback Request                │
         │                                     │
         ▼                                     ▼
┌─────────────────┐                  ┌──────────────────┐
│  Moodle Server  │                  │   Cloudflare     │
│                 │                  │  Stream Service  │
│  ┌───────────┐  │                  │                  │
│  │  Custom   │  │ (2) Get Upload   │                  │
│  │  Plugin   │◄─┼──────URL─────────┤                  │
│  │           │  │                  │                  │
│  │           │  │ (3) Return URL   │                  │
│  │           ├──┼─────────────────►│                  │
│  └───────────┘  │                  │                  │
│                 │ (6) Get Signed   │                  │
│  ┌───────────┐  │     Token        │                  │
│  │  Database │  │◄─────────────────┤                  │
│  │           │  │                  │                  │
│  └───────────┘  │                  └──────────────────┘
└─────────────────┘
```

### Component Architecture

The solution consists of three main layers:

1. **Presentation Layer** (Browser)
   - Upload interface with progress tracking
   - Embedded video player for playback
   - Standard Moodle grading interface

2. **Application Layer** (Moodle Server)
   - Custom Moodle plugin (assignsubmission_cloudflarestream)
   - API integration with Cloudflare Stream
   - Authentication and authorization logic
   - Database operations for metadata storage

3. **Infrastructure Layer** (Cloudflare)
   - Video storage and processing
   - CDN-based video delivery
   - Signed URL generation

## Components and Interfaces

### 1. Moodle Plugin Structure

The plugin will be implemented as an assignment submission plugin following Moodle's plugin architecture:

**Plugin Type**: `mod_assign/submission/cloudflarestream`

**Directory Structure**:
```
mod/assign/submission/cloudflarestream/
├── version.php                 # Plugin metadata
├── lib.php                     # Core plugin class
├── settings.php                # Admin settings
├── lang/
│   └── en/
│       └── assignsubmission_cloudflarestream.php
├── classes/
│   ├── api/
│   │   └── cloudflare_client.php      # Cloudflare API wrapper
│   ├── privacy/
│   │   └── provider.php               # GDPR compliance
│   └── task/
│       └── cleanup_videos.php         # Scheduled cleanup task
├── db/
│   ├── install.xml             # Database schema
│   ├── upgrade.php             # Database upgrades
│   └── tasks.php               # Scheduled tasks
├── amd/
│   └── src/
│       ├── uploader.js         # Upload handling
│       └── player.js           # Player integration
└── styles.css
```

### 2. Database Schema

**New Table**: `mdl_assignsubmission_cfstream`

```sql
CREATE TABLE mdl_assignsubmission_cfstream (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    assignment BIGINT NOT NULL,           -- Foreign key to mdl_assign
    submission BIGINT NOT NULL,           -- Foreign key to mdl_assign_submission
    video_uid VARCHAR(255) NOT NULL,      -- Cloudflare Stream video UID
    upload_status VARCHAR(50) NOT NULL,   -- 'uploading', 'ready', 'error', 'deleted'
    file_size BIGINT,                     -- Original file size in bytes
    duration INT,                         -- Video duration in seconds
    upload_timestamp BIGINT NOT NULL,     -- Unix timestamp
    deleted_timestamp BIGINT,             -- Unix timestamp when deleted
    error_message TEXT,                   -- Error details if upload failed
    UNIQUE KEY (submission),
    INDEX idx_video_uid (video_uid),
    INDEX idx_upload_timestamp (upload_timestamp)
);
```

**Plugin Settings Table**: Use Moodle's standard config_plugins table

- `assignsubmission_cloudflarestream/apitoken` - Encrypted Cloudflare API token
- `assignsubmission_cloudflarestream/accountid` - Cloudflare account ID
- `assignsubmission_cloudflarestream/retention_days` - Video retention period (default: 90)
- `assignsubmission_cloudflarestream/max_file_size` - Maximum upload size in bytes (default: 5GB)

### 3. Cloudflare Stream API Integration

**API Client Class**: `cloudflare_client.php`

```php
class cloudflare_client {
    private $api_token;
    private $account_id;
    private $base_url = 'https://api.cloudflare.com/client/v4';
    
    // Core methods:
    public function get_direct_upload_url($max_duration_seconds = 21600);
    public function get_video_details($video_uid);
    public function delete_video($video_uid);
    public function generate_signed_token($video_uid, $expiry_seconds = 86400);
}
```

**API Endpoints Used**:

1. **Direct Upload** (POST `/accounts/{account_id}/stream/direct_upload`)
   - Request: `maxDurationSeconds` parameter
   - Response: `uploadURL`, `uid`

2. **Video Details** (GET `/accounts/{account_id}/stream/{video_uid}`)
   - Response: Video metadata including status, duration, thumbnail

3. **Delete Video** (DELETE `/accounts/{account_id}/stream/{video_uid}`)
   - Response: Success confirmation

4. **Signed Tokens** (POST `/accounts/{account_id}/stream/{video_uid}/token`)
   - Request: `exp` (expiration timestamp)
   - Response: JWT token for playback

### 4. Frontend Components

#### Upload Interface (`uploader.js`)

**Responsibilities**:
- Request direct upload URL from Moodle backend
- Handle file selection and validation
- Upload video directly to Cloudflare using tus protocol (resumable uploads)
- Display progress bar with percentage and estimated time
- Handle upload errors and retry logic
- Notify Moodle backend when upload completes

**Key Functions**:
```javascript
class CloudflareUploader {
    constructor(assignmentId, userId);
    async requestUploadUrl();
    async uploadFile(file);
    updateProgress(percentage);
    handleError(error);
    notifyComplete(videoUid);
}
```

**Upload Flow**:
1. User selects video file
2. JavaScript validates file size (≤ 5GB)
3. AJAX request to Moodle: `get_upload_url.php`
4. Moodle returns Cloudflare direct upload URL
5. JavaScript uploads directly to Cloudflare using tus-js-client
6. Progress updates displayed in real-time
7. On completion, AJAX request to Moodle: `confirm_upload.php` with video UID
8. Moodle saves video UID to database

#### Player Interface (`player.js`)

**Responsibilities**:
- Request signed playback token from Moodle
- Embed Cloudflare Stream player
- Handle player events and errors

**Key Functions**:
```javascript
class CloudflarePlayer {
    constructor(videoUid, containerId);
    async loadPlayer();
    async getSignedToken();
    embedPlayer(token);
}
```

**Playback Flow**:
1. Teacher/student opens submission page
2. JavaScript requests signed token from Moodle
3. Moodle validates user permissions and generates token
4. JavaScript embeds Cloudflare Stream player with signed token
5. Video streams directly from Cloudflare CDN

### 5. Security Implementation

#### Authentication Flow

```
User Request → Moodle Session Check → Role Verification → Token Generation
```

**Access Control Rules**:
1. Students can only view their own submissions
2. Teachers can view submissions for courses they teach
3. Admins can view all submissions
4. All playback requires valid Moodle session

#### Signed Token Generation

```php
function generate_playback_token($video_uid, $user_id, $submission_id) {
    // Verify user has permission to view this submission
    if (!can_view_submission($user_id, $submission_id)) {
        throw new moodle_exception('nopermission');
    }
    
    // Generate signed token via Cloudflare API
    $expiry = time() + (24 * 3600); // 24 hours
    $token = $cloudflare_client->generate_signed_token($video_uid, $expiry);
    
    // Log access for audit trail
    log_video_access($user_id, $video_uid, $submission_id);
    
    return $token;
}
```

#### API Token Security

- Store Cloudflare API token encrypted in Moodle database
- Use Moodle's built-in encryption functions
- Never expose token in client-side code
- Restrict API token to Stream operations only (principle of least privilege)

## Data Models

### Video Submission Lifecycle

```
┌──────────┐     ┌───────────┐     ┌───────┐     ┌─────────┐
│ Pending  │────►│ Uploading │────►│ Ready │────►│ Deleted │
└──────────┘     └───────────┘     └───────┘     └─────────┘
                        │                              ▲
                        │                              │
                        └──────►┌───────┐──────────────┘
                                │ Error │
                                └───────┘
```

**Status Definitions**:
- `pending`: Upload URL requested but upload not started
- `uploading`: Upload in progress
- `ready`: Video successfully uploaded and ready for playback
- `error`: Upload failed or video processing error
- `deleted`: Video removed from Cloudflare (retention policy or manual deletion)

### Data Relationships

```
mdl_assign (Assignment)
    ↓ 1:N
mdl_assign_submission (Submission)
    ↓ 1:1
mdl_assignsubmission_cfstream (Video Metadata)
    ↓ references
Cloudflare Stream (Video UID)
```

## Error Handling

### Upload Errors

| Error Type | Handling Strategy |
|------------|-------------------|
| File too large | Client-side validation before upload; display error message |
| Network interruption | Automatic retry using tus resumable uploads |
| Cloudflare API error | Display error to user; log details for admin review |
| Invalid file format | Client-side validation; only allow video MIME types |
| Quota exceeded | Check Cloudflare quota before generating upload URL; display friendly error |

### Playback Errors

| Error Type | Handling Strategy |
|------------|-------------------|
| Video not found | Display message that video is no longer available |
| Token expired | Automatically request new token and reload player |
| Permission denied | Display access denied message |
| Network error | Display retry button; log error for monitoring |

### API Integration Errors

```php
try {
    $upload_url = $cloudflare_client->get_direct_upload_url();
} catch (cloudflare_api_exception $e) {
    // Log error with context
    error_log("Cloudflare API error: " . $e->getMessage());
    
    // Display user-friendly message
    throw new moodle_exception(
        'cloudflare_unavailable',
        'assignsubmission_cloudflarestream'
    );
}
```

## Testing Strategy

### Unit Tests

**PHP Unit Tests** (PHPUnit):
- `cloudflare_client_test.php`: Test API client methods with mocked responses
- `lib_test.php`: Test plugin core functions
- `privacy_provider_test.php`: Test GDPR compliance

**JavaScript Unit Tests** (Jest):
- `uploader_test.js`: Test upload logic and error handling
- `player_test.js`: Test player initialization and token handling

### Integration Tests

1. **Upload Flow Test**:
   - Create test assignment
   - Simulate student upload
   - Verify video UID stored in database
   - Verify video exists in Cloudflare

2. **Playback Flow Test**:
   - Create submission with video UID
   - Request playback as teacher
   - Verify signed token generated
   - Verify player loads successfully

3. **Access Control Test**:
   - Attempt to access video as unauthorized user
   - Verify access denied
   - Attempt to access own video as student
   - Verify access granted

### Performance Tests

1. **Large File Upload** (5 GB):
   - Test upload completion
   - Verify progress tracking accuracy
   - Test resume after interruption

2. **Concurrent Uploads**:
   - Simulate 50 concurrent uploads
   - Verify all complete successfully
   - Monitor API rate limits

3. **Playback Performance**:
   - Test video load time
   - Verify CDN delivery (no Moodle server load)
   - Test adaptive bitrate streaming

### Manual Testing Checklist

- [ ] Student can upload video up to 5 GB
- [ ] Progress bar displays accurately
- [ ] Upload can resume after network interruption
- [ ] Teacher can view student submission
- [ ] Video plays smoothly without buffering
- [ ] Student cannot view other students' videos
- [ ] Admin can configure API credentials
- [ ] Cleanup task deletes old videos
- [ ] Error messages are user-friendly
- [ ] Mobile browser compatibility

## Performance Considerations

### Upload Performance

- **Direct Browser Upload**: Eliminates Moodle server as bottleneck
- **Resumable Uploads**: Uses tus protocol for reliability with large files
- **Client-Side Validation**: Reduces failed uploads by validating before starting

### Playback Performance

- **CDN Delivery**: Cloudflare's global CDN ensures low latency
- **Adaptive Bitrate**: Automatic quality adjustment based on network conditions
- **No Server Load**: Moodle server only generates tokens, not video data

### Database Performance

- **Indexed Queries**: video_uid and upload_timestamp indexed for fast lookups
- **Minimal Storage**: Only metadata stored, not video data
- **Efficient Cleanup**: Scheduled task uses batch operations

## Scalability

### Horizontal Scalability

- Plugin is stateless; works with multiple Moodle web servers
- No shared file storage required
- Database is only bottleneck (standard Moodle scaling applies)

### Cost Scalability

**Cloudflare Stream Pricing** (as of October 2025):
- Storage: $5 per 1,000 minutes stored per month
- Delivery: $1 per 1,000 minutes delivered per month

**Example Cost Calculation** (100 users, 5 GB each):
- Assuming 5 Mbps average bitrate
- 5 GB ≈ 133 minutes per video
- 100 videos = 13,300 minutes
- Storage: $66.50/month
- Delivery (1 view per video): $13.30/month
- **Total: ~$80/month**

### Monitoring and Optimization

- Track upload success/failure rates
- Monitor Cloudflare API usage and costs
- Set up alerts for quota thresholds
- Implement retention policy to control storage costs

## Deployment Strategy

### Phase 1: Prototype (Week 1-2)

- Set up Cloudflare Stream account
- Create basic plugin structure
- Implement direct upload flow
- Test with small video files

### Phase 2: Core Implementation (Week 3-4)

- Complete database schema
- Implement playback with signed tokens
- Build upload and player UI components
- Implement access control

### Phase 3: Security & Testing (Week 5)

- Security audit
- Comprehensive testing
- Performance optimization
- Documentation

### Phase 4: Staging Deployment (Week 6)

- Deploy to staging environment
- User acceptance testing
- Bug fixes and refinements

### Phase 5: Production Rollout (Week 7-8)

- Deploy to production
- Monitor closely for issues
- Gather user feedback
- Implement retention/cleanup policy

## Maintenance and Operations

### Monitoring

- **Upload Metrics**: Success rate, average upload time, failure reasons
- **Playback Metrics**: Video views, playback errors, token generation rate
- **Cost Tracking**: Monthly Cloudflare charges, storage usage trends
- **Error Logs**: API failures, permission denials, upload failures

### Scheduled Tasks

1. **Video Cleanup** (Daily at 2 AM):
   - Identify videos older than retention period
   - Delete from Cloudflare via API
   - Update database status to 'deleted'

2. **Orphan Detection** (Weekly):
   - Find videos in Cloudflare not in Moodle database
   - Alert admin for manual review

3. **Usage Report** (Monthly):
   - Generate report of storage and delivery usage
   - Email to administrators

### Backup and Recovery

- **Database Backup**: Standard Moodle backup includes video metadata
- **Video Recovery**: Videos stored in Cloudflare (managed by Cloudflare)
- **Disaster Recovery**: Re-sync video UIDs from Cloudflare API if database lost

## Future Enhancements

### Phase 2 Features

1. **Transcoding Variants**: Enable multiple quality levels (360p, 720p, 1080p)
2. **Thumbnail Generation**: Display video thumbnails in submission list
3. **Batch Operations**: Bulk download/export of video metadata
4. **Analytics Dashboard**: Detailed usage and cost analytics

### Phase 3 Features

1. **AI Integration**: Automatic video summarization or tagging
2. **Captions/Subtitles**: Support for closed captions
3. **Video Annotations**: Allow teachers to add timestamped comments
4. **Mobile App**: Native upload support in Moodle mobile app

## Security Considerations

### Data Privacy (GDPR Compliance)

- Implement privacy provider for data export/deletion requests
- When user data deleted, remove videos from Cloudflare
- Log video access for audit trail
- Provide data export including video metadata

### Penetration Testing

- Test for unauthorized video access
- Verify token expiration enforcement
- Test for API token exposure
- Validate file upload restrictions

### Compliance

- Ensure video content complies with institutional policies
- Implement content moderation hooks if required
- Support legal hold requirements (prevent deletion)

## Dependencies

### External Services

- **Cloudflare Stream**: Core video infrastructure
- **Cloudflare API**: Must be accessible from Moodle server

### Moodle Requirements

- Moodle 3.9 or higher (LTS version)
- PHP 7.4 or higher
- HTTPS required (for secure token transmission)
- Modern browser with JavaScript enabled

### PHP Libraries

- `guzzlehttp/guzzle`: HTTP client for API requests
- `firebase/php-jwt`: JWT token handling (if not using Cloudflare's token generation)

### JavaScript Libraries

- `tus-js-client`: Resumable upload protocol
- Cloudflare Stream Player (loaded from CDN)

## Conclusion

This design provides a robust, scalable solution for integrating Cloudflare Stream with Moodle. By offloading video storage and delivery to Cloudflare's infrastructure, the system eliminates server load while maintaining seamless integration with Moodle's assignment workflow. The security model ensures that video access is properly controlled, and the architecture supports future enhancements without major refactoring.
