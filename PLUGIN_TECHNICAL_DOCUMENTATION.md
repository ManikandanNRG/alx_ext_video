# Moodle Video Submission Plugins - Technical Documentation

## Executive Summary

This document provides comprehensive technical documentation for two Moodle assignment submission plugins designed for video uploads. Both plugins enable students to submit video assignments and teachers to grade them with integrated video playback.

**Plugins:**
1. S3 Video Plugin (assignsubmission_s3video)
2. Cloudflare Stream Plugin (assignsubmission_cloudflarestream)

**Version:** 1.0  
**Moodle Compatibility:** 3.9+  
**Last Updated:** October 30, 2025

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [S3 Video Plugin](#s3-video-plugin)
3. [Cloudflare Stream Plugin](#cloudflare-stream-plugin)
4. [Core Features](#core-features)
5. [Security](#security)
6. [Performance](#performance)
7. [API Reference](#api-reference)

---

## Architecture Overview

### System Architecture

Both plugins follow a similar architecture pattern:

```
┌─────────────┐
│   Student   │
└──────┬──────┘
       │ 1. Request Upload URL
       ↓
┌─────────────────────┐
│  Moodle Server      │
│  - Validate user    │
│  - Check permissions│
│  - Generate URL     │
└──────┬──────────────┘
       │ 2. Return Upload URL
       ↓
┌─────────────┐
│   Student   │
└──────┬──────┘
       │ 3. Upload Video (Direct)
       ↓
┌─────────────────────┐
│  Cloud Storage      │
│  - S3 or Cloudflare │
│  - Process video    │
└──────┬──────────────┘
       │ 4. Confirm Upload
       ↓
┌─────────────────────┐
│  Moodle Server      │
│  - Update database  │
│  - Mark as ready    │
└─────────────────────┘
```

### Key Design Principles

1. **Direct Upload:** Videos upload directly to cloud storage, bypassing Moodle server
2. **Secure Access:** Signed URLs ensure only authorized users can view videos
3. **Scalability:** Cloud CDN handles video delivery globally
4. **GDPR Compliance:** Privacy providers handle data export and deletion
5. **Performance:** Two-column grading interface for efficient workflow

---


## S3 Video Plugin

### Overview

The S3 Video Plugin enables video submissions using Amazon S3 for storage and CloudFront for delivery.

### Architecture Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                     S3 VIDEO PLUGIN WORKFLOW                  │
└──────────────────────────────────────────────────────────────┘

UPLOAD PHASE:
┌─────────┐    ┌──────────┐    ┌─────────┐    ┌────────────┐
│ Student │───→│  Moodle  │───→│   S3    │───→│ CloudFront │
│         │    │  Server  │    │ Bucket  │    │    CDN     │
└─────────┘    └──────────┘    └─────────┘    └────────────┘
    │              │                │
    │ 1. Request   │                │
    │    Upload    │                │
    │              │ 2. Generate    │
    │              │    Pre-signed  │
    │              │    URL         │
    │              │                │
    │ 3. Upload    │                │
    │    Direct────┼───────────────→│
    │              │                │
    │ 4. Confirm   │                │
    └─────────────→│                │
                   │ 5. Update DB   │
                   │                │

PLAYBACK PHASE:
┌─────────┐    ┌──────────┐    ┌────────────┐
│ Teacher │───→│  Moodle  │───→│ CloudFront │
│         │    │  Server  │    │    CDN     │
└─────────┘    └──────────┘    └────────────┘
    │              │                │
    │ 1. Request   │                │
    │    Video     │                │
    │              │ 2. Generate    │
    │              │    Signed URL  │
    │              │                │
    │ 3. Stream────┼───────────────→│
    │    Video     │                │
```

### Component Details

#### 1. Upload Flow

**Step 1: Request Upload URL**
- File: `ajax/get_upload_url.php`
- Validates user permissions
- Checks assignment submission status
- Generates S3 pre-signed URL (valid for 1 hour)
- Creates database record with "pending" status

**Step 2: Direct Upload**
- File: `amd/src/uploader.js`
- Uploads video directly to S3 using pre-signed URL
- Shows progress bar with percentage and ETA
- Handles errors and retries
- No data passes through Moodle server

**Step 3: Confirm Upload**
- File: `ajax/confirm_upload.php`
- Verifies video exists in S3
- Updates database record to "ready" status
- Stores video metadata (size, duration)

#### 2. Playback Flow

**Step 1: Generate Signed URL**
- File: `ajax/get_playback_url.php`
- Validates user access permissions
- Generates CloudFront signed URL (valid for 24 hours)
- Uses RSA-SHA1 signature with private key

**Step 2: Video Player**
- File: `amd/src/player.js`
- Embeds HTML5 video player
- Handles token refresh before expiry
- Provides error handling and retry logic

#### 3. Grading Interface

**Two-Column Layout**
- File: `amd/src/grading_injector.js`
- Automatically injects on grading pages
- Left column (65%): Video player
- Right column (35%): Grading form
- Responsive design for mobile devices

### Database Schema

```sql
CREATE TABLE mdl_assignsubmission_s3video (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    assignment BIGINT NOT NULL,
    submission BIGINT NOT NULL,
    s3_key VARCHAR(255) NOT NULL,
    s3_bucket VARCHAR(255),
    file_size BIGINT,
    mime_type VARCHAR(100),
    duration INT,
    upload_status VARCHAR(20),
    upload_timestamp BIGINT,
    deleted_timestamp BIGINT,
    error_message TEXT,
    UNIQUE KEY (submission)
);
```

### API Classes

#### S3 Client (`classes/api/s3_client.php`)

**Methods:**
- `get_presigned_upload_url()` - Generate pre-signed URL for upload
- `delete_object()` - Delete video from S3
- `object_exists()` - Check if video exists
- `get_object_metadata()` - Get video metadata

#### CloudFront Client (`classes/api/cloudfront_client.php`)

**Methods:**
- `get_signed_url()` - Generate signed URL for playback
- `create_invalidation()` - Invalidate CDN cache

### Security Features

1. **Pre-signed URLs:** Time-limited URLs for uploads (1 hour)
2. **Signed URLs:** CloudFront signed URLs for playback (24 hours)
3. **Access Control:** Permission checks before URL generation
4. **Rate Limiting:** Prevents abuse with configurable limits
5. **Input Validation:** All inputs validated and sanitized
6. **CORS Policy:** Restricts uploads to Moodle domain

---


## Cloudflare Stream Plugin

### Overview

The Cloudflare Stream Plugin enables video submissions using Cloudflare Stream for storage, transcoding, and delivery.

### Architecture Diagram

```
┌──────────────────────────────────────────────────────────────┐
│              CLOUDFLARE STREAM PLUGIN WORKFLOW                │
└──────────────────────────────────────────────────────────────┘

UPLOAD PHASE:
┌─────────┐    ┌──────────┐    ┌────────────┐    ┌─────────┐
│ Student │───→│  Moodle  │───→│ Cloudflare │───→│   CDN   │
│         │    │  Server  │    │   Stream   │    │         │
└─────────┘    └──────────┘    └────────────┘    └─────────┘
    │              │                │                │
    │ 1. Request   │                │                │
    │    Upload    │                │                │
    │              │ 2. Get Direct  │                │
    │              │    Upload URL  │                │
    │              │                │                │
    │ 3. Upload    │                │                │
    │    Direct────┼───────────────→│                │
    │              │                │ 4. Transcode   │
    │              │                │    & Process   │
    │ 5. Confirm   │                │                │
    └─────────────→│                │                │
                   │ 6. Update DB   │                │
                   │    (Ready)     │                │

PLAYBACK PHASE:
┌─────────┐    ┌──────────┐    ┌─────────┐
│ Teacher │───→│  Moodle  │───→│   CDN   │
│         │    │  Server  │    │         │
└─────────┘    └──────────┘    └─────────┘
    │              │                │
    │ 1. Request   │                │
    │    Video     │                │
    │              │ 2. Return      │
    │              │    Video UID   │
    │              │                │
    │ 3. Stream────┼───────────────→│
    │    Video     │                │
    │    (Public)  │                │
```

### Component Details

#### 1. Upload Flow

**Step 1: Request Upload URL**
- File: `ajax/get_upload_url.php`
- Validates user permissions
- Calls Cloudflare API for direct upload URL
- Creates database record with "pending" status
- Returns upload URL and video UID

**Step 2: Direct Upload**
- File: `amd/src/uploader.js`
- Uploads video directly to Cloudflare Stream
- Shows progress bar
- Handles errors and retries
- Video uploaded as PUBLIC with domain restrictions

**Step 3: Confirm Upload**
- File: `ajax/confirm_upload.php`
- Polls Cloudflare API for video status
- Updates database when status is "ready"
- Stores video metadata

#### 2. Playback Flow

**Step 1: Embed Player**
- File: `amd/src/player.js`
- Embeds Cloudflare Stream IFRAME player
- Uses video UID for playback
- No token required (public videos)

**Step 2: Video Delivery**
- Cloudflare CDN delivers video globally
- Automatic adaptive bitrate streaming
- Domain restrictions prevent unauthorized embedding

#### 3. Grading Interface

**Two-Column Layout**
- File: `amd/src/grading_injector.js`
- Same as S3 plugin
- Left column (65%): Video player
- Right column (35%): Grading form

### Database Schema

```sql
CREATE TABLE mdl_assignsubmission_cfstream (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    assignment BIGINT NOT NULL,
    submission BIGINT NOT NULL,
    video_uid VARCHAR(255) NOT NULL,
    file_size BIGINT,
    duration INT,
    upload_status VARCHAR(20),
    upload_timestamp BIGINT,
    deleted_timestamp BIGINT,
    error_message TEXT,
    UNIQUE KEY (submission)
);
```

### API Classes

#### Cloudflare Client (`classes/api/cloudflare_client.php`)

**Methods:**
- `get_direct_upload_url()` - Get upload URL from Cloudflare
- `get_video_details()` - Get video metadata
- `delete_video()` - Delete video from Cloudflare
- `set_video_private()` - Change video privacy (not used)

### Security Features

1. **Public Videos:** Videos are public but with domain restrictions
2. **Domain Restrictions:** Videos only playable on Moodle domain
3. **Access Control:** Permission checks before displaying video
4. **Rate Limiting:** Prevents upload abuse
5. **Input Validation:** All inputs validated
6. **API Token:** Secure API authentication

### Sync Mechanism

**Daily Sync Task:**
- File: `classes/task/cleanup_videos.php`
- Checks all "ready" videos against Cloudflare API
- Detects manually deleted videos
- Updates database accordingly
- Runs daily at 2:00 AM

---


## Core Features

### 1. Direct Upload

Both plugins use direct upload to cloud storage, bypassing the Moodle server.

**Benefits:**
- No server bandwidth usage
- Faster uploads
- Support for large files (up to 5GB)
- No PHP upload limits

**Implementation:**
1. Client requests upload URL from Moodle
2. Moodle generates time-limited URL
3. Client uploads directly to cloud storage
4. Client confirms upload completion
5. Moodle updates database

### 2. Two-Column Grading Interface

**Layout:**
```
┌────────────────────────────────────────────────────┐
│              GRADING INTERFACE                      │
├──────────────────────────┬─────────────────────────┤
│                          │                         │
│   VIDEO PLAYER (65%)     │  GRADING PANEL (35%)   │
│                          │                         │
│  ┌────────────────────┐  │  Student: John Doe     │
│  │                    │  │  Assignment: Essay     │
│  │  [Video Playing]   │  │                        │
│  │                    │  │  Grade: [____] / 100   │
│  │                    │  │                        │
│  │  [Controls]        │  │  Feedback:             │
│  └────────────────────┘  │  ┌──────────────────┐  │
│                          │  │                  │  │
│  Duration: 5:30          │  │  [Text editor]   │  │
│  Size: 125 MB            │  │                  │  │
│                          │  └──────────────────┘  │
│                          │                        │
│                          │  [Save] [Next]         │
└──────────────────────────┴─────────────────────────┘
```

**Features:**
- Video stays visible while grading
- No tab switching required
- Responsive design (stacks on mobile)
- Preserves all Moodle grading functionality

**Implementation:**
- JavaScript injection on grading pages
- CSS Grid layout
- Automatic detection and setup
- No configuration required

### 3. Video Management Dashboard

Both plugins include an admin dashboard for monitoring.

**Features:**
- View all uploaded videos
- Filter by status (ready, pending, error, deleted)
- Search by student name or assignment
- Delete videos manually
- View upload statistics
- Monitor storage usage

**Access:**
- Site administration → Plugins → Assignment → [Plugin Name] → Video Management

### 4. Automatic Cleanup

**Scheduled Task:**
- Runs daily at 2:00 AM
- Deletes videos older than retention period (default: 90 days)
- Updates database records
- Logs cleanup results

**S3 Plugin:**
- Deletes from S3 bucket
- Invalidates CloudFront cache
- Tracks bytes freed

**Cloudflare Plugin:**
- Deletes from Cloudflare Stream
- Syncs database with Cloudflare
- Detects manually deleted videos

### 5. GDPR Compliance

Both plugins include privacy providers for GDPR compliance.

**Features:**
- Data export: Export user's video submissions
- Data deletion: Delete user's videos on account deletion
- Purpose description: Explain data usage
- Retention policy: Automatic deletion after retention period

**Implementation:**
- File: `classes/privacy/provider.php`
- Implements Moodle privacy API
- Handles data export requests
- Handles deletion requests

### 6. Rate Limiting

Prevents abuse by limiting upload frequency.

**Configuration:**
- Max uploads per hour: 10 (configurable)
- Max uploads per day: 50 (configurable)
- Per user, per assignment

**Implementation:**
- File: `classes/rate_limiter.php`
- Tracks upload attempts
- Returns 429 status code when limit exceeded
- Provides retry-after header

### 7. Error Handling

Comprehensive error handling with user-friendly messages.

**Error Types:**
- Network errors
- Permission errors
- Rate limit errors
- API errors
- Validation errors

**Features:**
- User-friendly error messages
- Suggested actions for resolution
- Retry buttons where appropriate
- Detailed logging for administrators

### 8. Logging

Both plugins include comprehensive logging.

**Logged Events:**
- Upload attempts (success/failure)
- Playback requests
- API errors
- Cleanup operations
- Rate limit violations

**Access:**
- Site administration → Reports → Logs
- Plugin-specific dashboard

---


## Security

### Authentication & Authorization

**Upload Phase:**
1. User must be logged in (Moodle session)
2. User must have `mod/assign:submit` capability
3. Assignment must accept submissions
4. Submission must be within deadline
5. Rate limiting applied

**Playback Phase:**
1. User must be logged in
2. User must be:
   - Submission owner (student), OR
   - Have `mod/assign:grade` capability (teacher), OR
   - Be site administrator

### Data Protection

**S3 Plugin:**
- Pre-signed URLs expire after 1 hour
- Signed URLs expire after 24 hours
- Videos stored in private S3 bucket
- CloudFront requires signed URLs
- HTTPS enforced

**Cloudflare Plugin:**
- Videos are public but domain-restricted
- Only playable on configured domains
- HTTPS enforced
- API token stored encrypted

### Input Validation

All user inputs are validated:
- Assignment ID: Integer, exists in database
- Submission ID: Integer, belongs to user
- Video UID: Alphanumeric, valid format
- File size: Within configured limits
- MIME type: Video formats only

**Validation Class:**
- File: `classes/validator.php`
- Validates all inputs
- Sanitizes data
- Throws exceptions on invalid data

### SQL Injection Prevention

- All database queries use parameterized statements
- No raw SQL with user input
- Moodle DML API used throughout

### XSS Prevention

- All output escaped using Moodle functions
- HTML purifier for user content
- Content Security Policy headers

### CSRF Protection

- All AJAX requests require sesskey
- Moodle CSRF tokens validated
- POST requests only for state changes

---

## Performance

### Upload Performance

**Direct Upload Benefits:**
- No Moodle server bandwidth used
- No PHP memory limits
- No PHP execution time limits
- Parallel uploads possible

**Optimization:**
- Chunked uploads for large files
- Progress tracking
- Resume capability (S3 plugin)
- Compression before upload

### Playback Performance

**CDN Delivery:**
- Global edge locations
- Low latency worldwide
- Automatic caching
- Adaptive bitrate streaming (Cloudflare)

**Optimization:**
- Lazy loading of video player
- Preload metadata only
- Efficient token refresh
- Browser caching

### Database Performance

**Indexes:**
```sql
-- S3 Plugin
CREATE INDEX idx_assignment ON mdl_assignsubmission_s3video(assignment);
CREATE INDEX idx_submission ON mdl_assignsubmission_s3video(submission);
CREATE INDEX idx_status ON mdl_assignsubmission_s3video(upload_status);
CREATE INDEX idx_timestamp ON mdl_assignsubmission_s3video(upload_timestamp);

-- Cloudflare Plugin
CREATE INDEX idx_assignment ON mdl_assignsubmission_cfstream(assignment);
CREATE INDEX idx_submission ON mdl_assignsubmission_cfstream(submission);
CREATE INDEX idx_status ON mdl_assignsubmission_cfstream(upload_status);
CREATE INDEX idx_timestamp ON mdl_assignsubmission_cfstream(upload_timestamp);
```

**Query Optimization:**
- Efficient queries with proper indexes
- Pagination for large result sets
- Caching where appropriate

### Caching

**Moodle Cache:**
- Plugin settings cached
- User permissions cached
- Assignment data cached

**CDN Cache:**
- Videos cached at edge locations
- Cache invalidation on delete
- Long cache TTL (1 year)

---


## API Reference

### S3 Video Plugin API

#### Get Upload URL

**Endpoint:** `ajax/get_upload_url.php`

**Method:** POST

**Parameters:**
```json
{
  "assignmentid": 123,
  "submissionid": 456,
  "sesskey": "abc123"
}
```

**Response:**
```json
{
  "success": true,
  "uploadURL": "https://bucket.s3.amazonaws.com/...",
  "s3key": "videos/123/456/video.mp4",
  "submissionid": 456
}
```

#### Get Playback URL

**Endpoint:** `ajax/get_playback_url.php`

**Method:** GET

**Parameters:**
```
submission_id=456
s3key=videos/123/456/video.mp4
sesskey=abc123
```

**Response:**
```json
{
  "success": true,
  "url": "https://d123.cloudfront.net/...",
  "expires": 1698765432
}
```

#### Confirm Upload

**Endpoint:** `ajax/confirm_upload.php`

**Method:** POST

**Parameters:**
```json
{
  "submissionid": 456,
  "s3key": "videos/123/456/video.mp4",
  "filesize": 125829120,
  "mimetype": "video/mp4",
  "sesskey": "abc123"
}
```

**Response:**
```json
{
  "success": true,
  "status": "ready"
}
```

---

### Cloudflare Stream Plugin API

#### Get Upload URL

**Endpoint:** `ajax/get_upload_url.php`

**Method:** POST

**Parameters:**
```json
{
  "assignmentid": 123,
  "submissionid": 456,
  "sesskey": "abc123"
}
```

**Response:**
```json
{
  "success": true,
  "uploadURL": "https://upload.cloudflarestream.com/...",
  "uid": "abc123def456",
  "submissionid": 456
}
```

#### Confirm Upload

**Endpoint:** `ajax/confirm_upload.php`

**Method:** POST

**Parameters:**
```json
{
  "submissionid": 456,
  "videouid": "abc123def456",
  "sesskey": "abc123"
}
```

**Response:**
```json
{
  "success": true,
  "status": "ready",
  "duration": 330,
  "filesize": 125829120
}
```

---

## Configuration Reference

### S3 Video Plugin Settings

**AWS Configuration:**
- `aws_access_key`: IAM user access key
- `aws_secret_key`: IAM user secret key (encrypted)
- `aws_region`: S3 bucket region (e.g., us-east-1)
- `s3_bucket`: S3 bucket name

**CloudFront Configuration:**
- `cloudfront_domain`: Distribution domain name
- `cloudfront_keypair_id`: Key pair ID for signed URLs
- `cloudfront_private_key`: Path to private key file

**Plugin Settings:**
- `max_file_size`: Maximum upload size in bytes (default: 5GB)
- `retention_days`: Days to keep videos (default: 90)
- `enabled`: Enable plugin globally
- `default`: Enable by default for new assignments

**Rate Limiting:**
- `rate_limit_per_hour`: Max uploads per hour (default: 10)
- `rate_limit_per_day`: Max uploads per day (default: 50)

### Cloudflare Stream Plugin Settings

**Cloudflare Configuration:**
- `apitoken`: Cloudflare API token (encrypted)
- `accountid`: Cloudflare account ID

**Plugin Settings:**
- `max_file_size`: Maximum upload size in bytes (default: 5GB)
- `retention_days`: Days to keep videos (default: 90)
- `enabled`: Enable plugin globally
- `default`: Enable by default for new assignments

**Rate Limiting:**
- `rate_limit_per_hour`: Max uploads per hour (default: 10)
- `rate_limit_per_day`: Max uploads per day (default: 50)

---

## Maintenance & Monitoring

### Scheduled Tasks

**S3 Video Cleanup:**
- Task: `\assignsubmission_s3video\task\cleanup_videos`
- Schedule: Daily at 2:00 AM
- Function: Delete videos older than retention period

**Cloudflare Stream Cleanup:**
- Task: `\assignsubmission_cloudflarestream\task\cleanup_videos`
- Schedule: Daily at 2:00 AM
- Function: Delete old videos and sync with Cloudflare

### Manual Task Execution

```bash
# Run S3 cleanup
php admin/cli/scheduled_task.php --execute=\\assignsubmission_s3video\\task\\cleanup_videos

# Run Cloudflare cleanup
php admin/cli/scheduled_task.php --execute=\\assignsubmission_cloudflarestream\\task\\cleanup_videos
```

### Monitoring

**Key Metrics:**
- Total videos uploaded
- Videos by status (ready, pending, error)
- Storage usage
- Upload success rate
- Average upload time
- Playback errors

**Access:**
- Plugin dashboard
- Moodle logs
- Cloud provider dashboards

### Backup & Recovery

**Database Backup:**
- Include plugin tables in Moodle backup
- Regular automated backups recommended

**Video Backup:**
- S3: Enable versioning and lifecycle policies
- Cloudflare: Videos stored redundantly by Cloudflare

**Configuration Backup:**
- Document all settings
- Keep copy of CloudFront private key (S3 plugin)
- Keep copy of API tokens (encrypted)

---

## Troubleshooting Guide

### Common Issues

**Upload Fails:**
1. Check user permissions
2. Verify API credentials
3. Check rate limiting
4. Review browser console for errors
5. Check Moodle logs

**Video Won't Play:**
1. Verify video status is "ready"
2. Check user permissions
3. Verify signed URL generation (S3)
4. Check domain restrictions (Cloudflare)
5. Clear browser cache

**Two-Column Layout Not Working:**
1. Clear Moodle cache
2. Clear browser cache
3. Check JavaScript console for errors
4. Verify grading_injector.js is loaded

**Cleanup Not Running:**
1. Verify cron is configured
2. Check scheduled task is enabled
3. Run task manually to test
4. Review task logs

### Debug Mode

Enable debugging in Moodle:
1. Site administration → Development → Debugging
2. Set to DEVELOPER level
3. Display debug messages: Yes
4. Review detailed error messages

### Log Files

**Moodle Logs:**
- Site administration → Reports → Logs
- Filter by plugin component

**Server Logs:**
- Apache/Nginx error logs
- PHP error logs
- Check for permission issues

**Cloud Provider Logs:**
- AWS CloudWatch (S3 plugin)
- Cloudflare Analytics (Cloudflare plugin)

---

## Appendix

### File Structure

**S3 Video Plugin:**
```
mod/assign/submission/s3video/
├── ajax/
│   ├── confirm_upload.php
│   ├── get_playback_url.php
│   └── get_upload_url.php
├── amd/
│   ├── build/
│   │   ├── grading_injector.min.js
│   │   ├── player.min.js
│   │   └── uploader.min.js
│   └── src/
│       ├── grading_injector.js
│       ├── player.js
│       └── uploader.js
├── classes/
│   ├── api/
│   │   ├── cloudfront_client.php
│   │   └── s3_client.php
│   ├── privacy/
│   │   └── provider.php
│   ├── task/
│   │   └── cleanup_videos.php
│   ├── logger.php
│   ├── rate_limiter.php
│   └── validator.php
├── db/
│   ├── access.php
│   ├── install.xml
│   └── upgrade.php
├── lang/en/
│   └── assignsubmission_s3video.php
├── templates/
│   ├── player.mustache
│   └── upload_form.mustache
├── dashboard.php
├── lib.php
├── locallib.php
├── settings.php
├── styles.css
├── version.php
└── videomanagement.php
```

**Cloudflare Stream Plugin:**
```
mod/assign/submission/cloudflarestream/
├── ajax/
│   ├── confirm_upload.php
│   ├── get_playback_token.php
│   └── get_upload_url.php
├── amd/
│   ├── build/
│   │   ├── grading_injector.min.js
│   │   ├── player.min.js
│   │   └── uploader.min.js
│   └── src/
│       ├── grading_injector.js
│       ├── player.js
│       └── uploader.js
├── classes/
│   ├── api/
│   │   └── cloudflare_client.php
│   ├── privacy/
│   │   └── provider.php
│   ├── task/
│   │   └── cleanup_videos.php
│   ├── logger.php
│   ├── rate_limiter.php
│   └── validator.php
├── db/
│   ├── install.xml
│   ├── tasks.php
│   └── upgrade.php
├── lang/en/
│   └── assignsubmission_cloudflarestream.php
├── templates/
│   ├── player.mustache
│   └── upload_form.mustache
├── dashboard.php
├── lib.php
├── locallib.php
├── settings.php
├── styles.css
├── version.php
├── videomanagement.php
└── view_video.php
```

### Version History

**Version 1.0 (October 2025)**
- Initial release
- Direct upload to cloud storage
- Two-column grading interface
- Automatic cleanup
- GDPR compliance
- Rate limiting
- Comprehensive logging

---

**Document Version:** 1.0  
**Last Updated:** October 30, 2025  
**Prepared for:** Development Team  
**Contact:** [Your Contact Information]
