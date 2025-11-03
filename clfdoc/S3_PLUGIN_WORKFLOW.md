# S3 + CloudFront Plugin - Complete Workflow

## Plugin Name: `assignsubmission_s3video`

---

## 🎯 Overview

This plugin enables students to upload large video files (up to 5 GB) directly to AWS S3, with delivery via CloudFront CDN. Videos are played using an HTML5 video player (Video.js).

---

## 📊 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         UPLOAD WORKFLOW                          │
└─────────────────────────────────────────────────────────────────┘

Student Browser
      │
      │ 1. Click "Add submission"
      ▼
┌──────────────────┐
│  Moodle Server   │
│  (PHP Backend)   │
└────────┬─────────┘
         │
         │ 2. Request presigned POST URL
         │    ajax/get_upload_url.php
         │    ↓
         │    - Generate unique S3 key: videos/{userid}/{timestamp}/{filename}
         │    - Create S3 presigned POST (valid 1 hour)
         │    - Store pending record in database
         │    - Return presigned POST data to browser
         │
         ▼
Student Browser (JavaScript)
      │
      │ 3. Upload video directly to S3
      │    using presigned POST
      │
      ▼
┌──────────────────┐
│   AWS S3 Bucket  │
│  (Video Storage) │
└────────┬─────────┘
         │
         │ 4. S3 stores video
         │    Returns success response
         │
         ▼
Student Browser (JavaScript)
      │
      │ 5. Notify Moodle of successful upload
      │    ajax/confirm_upload.php
      │    - Send S3 key
      │    - Send file metadata
      │
      ▼
┌──────────────────┐
│  Moodle Server   │
│  (PHP Backend)   │
└────────┬─────────┘
         │
         │ 6. Update database
         │    - Set status to 'ready'
         │    - Store S3 key, file size, etc.
         │    - Log success event
         │
         ▼
    [Complete]


┌─────────────────────────────────────────────────────────────────┐
│                        PLAYBACK WORKFLOW                         │
└─────────────────────────────────────────────────────────────────┘

Teacher Browser
      │
      │ 1. Open student submission
      │
      ▼
┌──────────────────┐
│  Moodle Server   │
│  (PHP Backend)   │
└────────┬─────────┘
         │
         │ 2. Retrieve S3 key from database
         │    - Verify user has permission
         │    - Check video status is 'ready'
         │
         │ 3. Generate CloudFront signed URL
         │    - Create signed URL (valid 24 hours)
         │    - Include security policy
         │    - Log access event
         │
         ▼
Teacher Browser (JavaScript)
      │
      │ 4. Load Video.js player
      │    - Initialize player
      │    - Set source to signed URL
      │
      ▼
┌──────────────────┐
│  CloudFront CDN  │
│ (Video Delivery) │
└────────┬─────────┘
         │
         │ 5. Validate signed URL
         │    - Check signature
         │    - Check expiration
         │    - Check IP (optional)
         │
         ▼
┌──────────────────┐
│   AWS S3 Bucket  │
│  (Video Storage) │
└────────┬─────────┘
         │
         │ 6. Retrieve video file
         │
         ▼
┌──────────────────┐
│  CloudFront CDN  │
│ (Cache & Deliver)│
└────────┬─────────┘
         │
         │ 7. Stream video to browser
         │
         ▼
Teacher Browser (Video.js Player)
      │
      │ 8. Play video
      │
      ▼
    [Complete]
```

---

## 🔄 Detailed Workflows

### 1. Upload Workflow (Step-by-Step)

#### Step 1: Student Initiates Upload
```
Location: Student's browser
Action: Student clicks "Add submission" and selects video file
Validation: 
  - File size ≤ 5 GB
  - File type is video (MP4, MOV, AVI, MKV, WebM)
```

#### Step 2: Request Presigned POST URL
```
Request: AJAX POST to ajax/get_upload_url.php
Parameters:
  - assignmentid: int
  - filename: string
  - filesize: int
  - mimetype: string

Backend Process (PHP):
  1. Verify user is authenticated
  2. Check user has permission to submit
  3. Validate file parameters
  4. Check rate limiting (max 10 uploads/hour)
  5. Generate unique S3 key:
     Format: videos/{userid}/{timestamp}_{random}/{filename}
     Example: videos/123/1698345600_a7b3c/myvideo.mp4
  6. Create S3 presigned POST:
     - Bucket: your-moodle-videos
     - Key: generated above
     - Expiration: 1 hour
     - Max file size: 5 GB
     - Content-Type: video/*
  7. Create database record:
     - status: 'pending'
     - s3_key: generated key
     - upload_timestamp: now
  8. Return to browser:
     {
       "presigned_url": "https://your-bucket.s3.amazonaws.com",
       "fields": {
         "key": "videos/123/...",
         "policy": "...",
         "signature": "...",
         "x-amz-algorithm": "...",
         "x-amz-credential": "...",
         "x-amz-date": "..."
       },
       "s3_key": "videos/123/..."
     }
```

#### Step 3: Upload to S3
```
Location: Student's browser (JavaScript)
Process:
  1. Create FormData with presigned POST fields
  2. Append video file
  3. Send POST request to S3
  4. Monitor upload progress
  5. Update progress bar (0-100%)
  6. Handle errors (network, timeout, etc.)

JavaScript Code Flow:
  const formData = new FormData();
  
  // Add presigned POST fields
  Object.keys(presignedData.fields).forEach(key => {
    formData.append(key, presignedData.fields[key]);
  });
  
  // Add file (must be last)
  formData.append('file', videoFile);
  
  // Upload with progress tracking
  const xhr = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', (e) => {
    const percent = (e.loaded / e.total) * 100;
    updateProgressBar(percent);
  });
  
  xhr.open('POST', presignedData.presigned_url);
  xhr.send(formData);
```

#### Step 4: S3 Stores Video
```
Location: AWS S3
Process:
  1. Validate presigned POST signature
  2. Check policy (expiration, file size, etc.)
  3. Store video file in bucket
  4. Return HTTP 204 (success) or 403 (error)

S3 Response:
  Success: HTTP 204 No Content
  Error: HTTP 403 Forbidden with XML error details
```

#### Step 5: Confirm Upload to Moodle
```
Request: AJAX POST to ajax/confirm_upload.php
Parameters:
  - assignmentid: int
  - submissionid: int
  - s3_key: string
  - filesize: int
  - duration: int (optional, from client-side detection)

Backend Process (PHP):
  1. Verify user is authenticated
  2. Verify S3 key matches pending record
  3. Verify file exists in S3 (HeadObject API call)
  4. Update database record:
     - status: 'ready'
     - file_size: actual size
     - duration: if provided
  5. Log success event
  6. Return success response

Response:
  {
    "success": true,
    "message": "Video uploaded successfully"
  }
```

---

### 2. Playback Workflow (Step-by-Step)

#### Step 1: Teacher Opens Submission
```
Location: Teacher's browser
Action: Teacher clicks on student submission to grade
URL: /mod/assign/view.php?id={cmid}&action=grading&userid={userid}
```

#### Step 2: Retrieve Video Metadata
```
Location: Moodle Server (PHP)
Process (in lib.php view() method):
  1. Get submission ID
  2. Query database for video record:
     SELECT * FROM mdl_assignsubmission_s3video
     WHERE submission = {submissionid}
     AND upload_status = 'ready'
  3. If found, get S3 key
  4. If not found or status != 'ready', show error
```

#### Step 3: Verify Access Permission
```
Location: Moodle Server (PHP)
Process:
  1. Check user is authenticated
  2. Verify user has permission to view submission:
     - Is student viewing own submission? → Allow
     - Is teacher in same course? → Allow
     - Is admin? → Allow
     - Otherwise → Deny
  3. If denied, throw permission exception
```

#### Step 4: Generate CloudFront Signed URL
```
Request: AJAX GET to ajax/get_playback_url.php
Parameters:
  - submissionid: int
  - s3_key: string

Backend Process (PHP):
  1. Verify access permission (as above)
  2. Get CloudFront configuration:
     - Distribution domain
     - Key pair ID
     - Private key
  3. Create CloudFront signed URL:
     - Resource: https://d123456.cloudfront.net/videos/123/...
     - Expiration: now + 24 hours
     - IP restriction: optional
  4. Generate signature using private key
  5. Log playback access event
  6. Return signed URL

CloudFront Signed URL Format:
  https://d123456.cloudfront.net/videos/123/myvideo.mp4
    ?Expires=1698432000
    &Signature=ABC123...
    &Key-Pair-Id=APKA...

Response:
  {
    "signed_url": "https://d123456.cloudfront.net/...",
    "expires_at": 1698432000
  }
```

#### Step 5: Load Video Player
```
Location: Teacher's browser (JavaScript)
Process:
  1. Initialize Video.js player
  2. Set video source to signed URL
  3. Configure player options:
     - Controls: true
     - Autoplay: false
     - Preload: 'metadata'
     - Responsive: true
  4. Handle player events:
     - loadstart
     - canplay
     - error
  5. Display player in submission view

JavaScript Code:
  const player = videojs('video-player', {
    controls: true,
    autoplay: false,
    preload: 'metadata',
    fluid: true,
    sources: [{
      src: signedUrl,
      type: 'video/mp4'
    }]
  });
  
  player.on('error', function() {
    // Handle playback error
    showError('Video playback failed');
  });
```

#### Step 6: CloudFront Validates & Delivers
```
Location: AWS CloudFront
Process:
  1. Receive request with signed URL
  2. Validate signature:
     - Check signature matches
     - Check expiration time
     - Check IP (if restricted)
  3. If valid:
     - Check cache for video
     - If cached, serve from edge location
     - If not cached, fetch from S3
  4. If invalid:
     - Return 403 Forbidden

CloudFront Behavior:
  - First request: Fetch from S3, cache at edge
  - Subsequent requests: Serve from cache (faster)
  - Cache TTL: 24 hours (configurable)
```

#### Step 7: Stream Video
```
Location: Teacher's browser
Process:
  1. Video.js player requests video
  2. CloudFront streams video chunks
  3. Player buffers and plays
  4. User can:
     - Play/pause
     - Seek to any position
     - Adjust volume
     - Toggle fullscreen
```

---

### 3. Video Deletion Workflow

#### Manual Deletion (Admin)
```
Location: Admin dashboard (videomanagement.php)
Process:
  1. Admin searches for video
  2. Clicks "Delete" button
  3. Confirmation dialog appears
  4. On confirm:
     a. Delete from S3 (DeleteObject API)
     b. Invalidate CloudFront cache
     c. Update database: status = 'deleted'
     d. Log deletion event
```

#### Automatic Cleanup (Scheduled Task)
```
Location: Moodle cron (classes/task/cleanup_videos.php)
Schedule: Daily at 2 AM
Process:
  1. Find videos older than retention period:
     SELECT * FROM mdl_assignsubmission_s3video
     WHERE upload_status = 'ready'
     AND upload_timestamp < (NOW() - retention_days)
  2. For each video:
     a. Delete from S3
     b. Invalidate CloudFront cache
     c. Update database: status = 'deleted'
     d. Log deletion
  3. Generate cleanup report
```

---

### 4. Error Handling Workflows

#### Upload Errors

**Error: File Too Large**
```
Detection: Client-side (JavaScript)
Action:
  1. Show error message: "File size exceeds 5 GB limit"
  2. Suggest compressing video
  3. Don't attempt upload
```

**Error: Invalid File Type**
```
Detection: Client-side (JavaScript)
Action:
  1. Show error message: "Only video files are allowed"
  2. List supported formats
  3. Don't attempt upload
```

**Error: Network Interruption**
```
Detection: Upload progress stalls or fails
Action:
  1. Show error message: "Upload interrupted"
  2. Offer "Retry" button
  3. On retry:
     - Request new presigned POST
     - Resume upload from beginning
     - (Note: S3 doesn't support resumable uploads natively)
```

**Error: S3 Upload Failed**
```
Detection: S3 returns 403 or 500
Action:
  1. Parse S3 error response
  2. Show user-friendly message
  3. Log error details
  4. Offer retry option
```

**Error: Presigned POST Expired**
```
Detection: S3 returns 403 with "Request has expired"
Action:
  1. Show message: "Upload session expired"
  2. Request new presigned POST
  3. Retry upload automatically
```

#### Playback Errors

**Error: Video Not Found**
```
Detection: Database query returns no record
Action:
  1. Show message: "Video not available"
  2. Check if video was deleted
  3. Log error
```

**Error: Signed URL Expired**
```
Detection: CloudFront returns 403
Action:
  1. Detect expiration
  2. Request new signed URL automatically
  3. Reload player with new URL
  4. Resume playback
```

**Error: CloudFront Access Denied**
```
Detection: CloudFront returns 403
Action:
  1. Check if user still has permission
  2. If yes, regenerate signed URL
  3. If no, show "Access denied" message
```

**Error: Video Playback Failed**
```
Detection: Video.js fires 'error' event
Action:
  1. Get error code from player
  2. Show appropriate message:
     - Network error: "Check your connection"
     - Format error: "Video format not supported"
     - Unknown: "Playback failed, try refreshing"
  3. Log error details
```

---

## 🔐 Security Workflows

### 1. Authentication
```
Every Request:
  1. Check Moodle session exists
  2. Verify user is logged in
  3. Get user ID and roles
  4. If not authenticated → Redirect to login
```

### 2. Authorization
```
Upload:
  - User must be enrolled in course
  - User must have 'mod/assign:submit' capability
  - Assignment must accept submissions

Playback:
  - Student: Can view own submissions only
  - Teacher: Can view submissions in courses they teach
  - Admin: Can view all submissions
```

### 3. Rate Limiting
```
Upload URL Requests:
  - Max 10 requests per user per hour
  - Track in cache or database
  - Return 429 if exceeded

Playback URL Requests:
  - Max 100 requests per user per hour
  - Track in cache or database
  - Return 429 if exceeded
```

### 4. Input Validation
```
All User Inputs:
  1. Validate data types
  2. Sanitize strings
  3. Check ranges (file size, etc.)
  4. Verify IDs exist in database
  5. Escape for SQL (use parameterized queries)
  6. Escape for HTML output
```

---

## 📊 Database Workflows

### Tables

#### mdl_assignsubmission_s3video
```sql
Stores video metadata for each submission

Fields:
  - id: Primary key
  - assignment: Foreign key to mdl_assign
  - submission: Foreign key to mdl_assign_submission
  - s3_key: S3 object key (videos/123/...)
  - s3_bucket: Bucket name
  - upload_status: 'pending', 'ready', 'error', 'deleted'
  - file_size: Bytes
  - duration: Seconds (optional)
  - mime_type: video/mp4, etc.
  - upload_timestamp: Unix timestamp
  - deleted_timestamp: Unix timestamp (when deleted)
  - error_message: Error details if failed

Indexes:
  - submission (unique)
  - s3_key
  - upload_timestamp
  - upload_status
```

#### mdl_assignsubmission_s3v_log
```sql
Logs all events for monitoring

Fields:
  - id: Primary key
  - userid: User who triggered event
  - assignmentid: Assignment ID
  - submissionid: Submission ID
  - s3_key: S3 object key
  - event_type: 'upload_success', 'upload_failure', 'playback_access', etc.
  - error_code: Error code if applicable
  - error_message: Error details
  - error_context: JSON with additional info
  - file_size: Bytes
  - duration: Seconds
  - retry_count: Number of retries
  - user_role: 'student', 'teacher', 'admin'
  - timestamp: Unix timestamp

Indexes:
  - event_type
  - timestamp
  - s3_key
  - userid
```

### Database Operations

#### Insert (Upload Start)
```sql
INSERT INTO mdl_assignsubmission_s3video
(assignment, submission, s3_key, s3_bucket, upload_status, upload_timestamp)
VALUES (?, ?, ?, ?, 'pending', ?)
```

#### Update (Upload Complete)
```sql
UPDATE mdl_assignsubmission_s3video
SET upload_status = 'ready',
    file_size = ?,
    duration = ?,
    mime_type = ?
WHERE s3_key = ?
```

#### Select (Get Video for Playback)
```sql
SELECT * FROM mdl_assignsubmission_s3video
WHERE submission = ?
AND upload_status = 'ready'
```

#### Update (Mark as Deleted)
```sql
UPDATE mdl_assignsubmission_s3video
SET upload_status = 'deleted',
    deleted_timestamp = ?
WHERE s3_key = ?
```

---

## 🎨 User Interface Workflows

### Upload Interface
```
Components:
  1. File input (drag & drop or click)
  2. File validation feedback
  3. Progress bar (0-100%)
  4. Upload status messages
  5. Cancel button
  6. Retry button (on error)

States:
  - Initial: Show file input
  - Validating: Check file size/type
  - Uploading: Show progress bar
  - Processing: "Finalizing upload..."
  - Success: "Upload complete!"
  - Error: Show error message + retry button
```

### Playback Interface
```
Components:
  1. Video.js player
  2. Play/pause controls
  3. Progress bar
  4. Volume control
  5. Fullscreen button
  6. Loading indicator
  7. Error message area

States:
  - Loading: Show spinner
  - Ready: Show player controls
  - Playing: Update progress
  - Paused: Show play button
  - Error: Show error message
  - Buffering: Show loading indicator
```

### Admin Dashboard
```
Components:
  1. Statistics cards:
     - Total uploads
     - Success rate
     - Total storage
     - Estimated cost
  2. Time period selector (7/30/90/365 days)
  3. Recent failures table
  4. Error breakdown chart
  5. Link to video management

Refresh: On page load and time period change
```

### Video Management
```
Components:
  1. Search/filter form:
     - Course dropdown
     - Status dropdown
     - Text search
     - Videos per page
  2. Results table:
     - Course
     - Assignment
     - Student
     - S3 key
     - Status
     - Upload date
     - Actions (Delete button)
  3. Pagination
  4. Delete confirmation dialog

Actions:
  - Filter: Reload table with filters
  - Delete: Show confirmation → Delete → Reload
  - Paginate: Load next/previous page
```

---

## 📈 Monitoring & Logging Workflows

### Events to Log

**Upload Events:**
- upload_start: When presigned POST requested
- upload_success: When upload confirmed
- upload_failure: When upload fails
- upload_retry: When user retries

**Playback Events:**
- playback_access: When signed URL generated
- playback_failure: When playback fails

**Admin Events:**
- video_deletion: When video deleted (manual or automatic)
- cleanup_run: When cleanup task runs

**API Events:**
- s3_api_error: When S3 API call fails
- cloudfront_api_error: When CloudFront API call fails

### Log Entry Format
```json
{
  "event_type": "upload_success",
  "timestamp": 1698345600,
  "userid": 123,
  "assignmentid": 456,
  "submissionid": 789,
  "s3_key": "videos/123/1698345600_a7b3c/video.mp4",
  "file_size": 52428800,
  "duration": 300,
  "user_role": "student",
  "context": {
    "browser": "Chrome 118",
    "ip": "192.168.1.1"
  }
}
```

---

## 🔄 Comparison: Cloudflare Stream vs S3 + CloudFront

| Workflow Step | Cloudflare Stream | S3 + CloudFront |
|---------------|-------------------|-----------------|
| **Upload URL** | Direct upload URL via API | Presigned POST via SDK |
| **Upload Protocol** | tus (resumable) | HTTP POST (not resumable) |
| **Upload Destination** | Cloudflare servers | S3 bucket |
| **Video Processing** | Automatic transcoding | None (raw file) |
| **Storage** | Cloudflare managed | S3 bucket |
| **Delivery** | Cloudflare CDN | CloudFront CDN |
| **Player** | Cloudflare Stream player | Video.js (custom) |
| **Signed URLs** | JWT tokens | CloudFront signed URLs |
| **Token Expiration** | Configurable | Configurable |
| **Analytics** | Built-in | CloudWatch (separate) |
| **Cost** | $5/month + usage | Pay-as-you-go |

---

## ✅ Workflow Summary

This plugin follows these key workflows:

1. **Upload**: Student → Moodle → S3 (direct) → Moodle (confirm)
2. **Playback**: Teacher → Moodle → CloudFront → Browser
3. **Security**: Authentication → Authorization → Signed URLs
4. **Cleanup**: Scheduled task → Delete from S3 → Update database
5. **Monitoring**: Log all events → Dashboard → Analytics

All workflows prioritize:
- **Security**: Signed URLs, access control, rate limiting
- **Performance**: Direct uploads, CDN delivery, caching
- **Reliability**: Error handling, logging, retry logic
- **User Experience**: Progress tracking, clear messages, responsive UI

---

**Ready to proceed with implementation?** 

Let me know if you need any clarification on these workflows!
