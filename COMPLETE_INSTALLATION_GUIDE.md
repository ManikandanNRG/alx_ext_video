# Moodle Video Submission Plugins - Complete Installation Guide

## Table of Contents
1. [Overview](#overview)
2. [Plugin Information](#plugin-information)
3. [Prerequisites](#prerequisites)
4. [S3 Video Plugin Installation](#s3-video-plugin-installation)
5. [Cloudflare Stream Plugin Installation](#cloudflare-stream-plugin-installation)
6. [Post-Installation Configuration](#post-installation-configuration)
7. [Testing & Verification](#testing--verification)
8. [Troubleshooting](#troubleshooting)

---

## Overview

This guide provides complete step-by-step instructions for installing and configuring two Moodle assignment submission plugins for video uploads:

1. **S3 Video Plugin** - Uses Amazon S3 for storage and CloudFront for delivery
2. **Cloudflare Stream Plugin** - Uses Cloudflare Stream for storage and delivery

Both plugins allow students to submit video assignments and teachers to grade them with an integrated video player.

---

## Plugin Information

### S3 Video Plugin (assignsubmission_s3video)

**Purpose:** Enable students to upload video assignments directly to Amazon S3 with CloudFront CDN delivery.

**Key Features:**
- Direct upload to AWS S3 (bypasses Moodle server)
- CloudFront CDN for fast global video delivery
- Signed URLs for secure video access
- Two-column grading interface (video + grading panel)
- Automatic video cleanup after retention period
- GDPR compliant with privacy provider
- Rate limiting and security features
- Video management dashboard

**Core Functions:**
1. **Upload:** Students upload videos directly to S3 using pre-signed URLs
2. **Storage:** Videos stored in S3 bucket with automatic lifecycle management
3. **Delivery:** CloudFront CDN delivers videos with signed URLs for security
4. **Grading:** Teachers view videos in two-column layout while grading
5. **Cleanup:** Scheduled task deletes old videos based on retention policy

**Workflow:**
```
Student → Upload Video → S3 Bucket → CloudFront CDN → Teacher Views → Grade
                ↓
         Database Record
                ↓
         Scheduled Cleanup (after retention period)
```

---

### Cloudflare Stream Plugin (assignsubmission_cloudflarestream)

**Purpose:** Enable students to upload video assignments to Cloudflare Stream with automatic transcoding and delivery.

**Key Features:**
- Direct upload to Cloudflare Stream
- Automatic video transcoding and optimization
- Global CDN delivery
- Public videos with domain restrictions
- Two-column grading interface
- Automatic video cleanup and sync
- GDPR compliant
- Rate limiting and security features

**Core Functions:**
1. **Upload:** Students upload videos directly to Cloudflare Stream
2. **Processing:** Cloudflare automatically transcodes videos
3. **Storage:** Videos stored in Cloudflare Stream
4. **Delivery:** Cloudflare CDN delivers videos globally
5. **Grading:** Teachers view videos in two-column layout
6. **Sync:** Daily sync detects manually deleted videos

**Workflow:**
```
Student → Upload Video → Cloudflare Stream → Transcoding → CDN → Teacher Views → Grade
                ↓
         Database Record
                ↓
         Daily Sync + Cleanup
```

---

## Prerequisites

### System Requirements
- Moodle 3.9 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher / PostgreSQL 9.6 or higher
- HTTPS enabled (required for secure uploads)
- Cron configured for scheduled tasks

### For S3 Video Plugin
- AWS Account with:
  - S3 bucket created
  - CloudFront distribution configured
  - IAM user with appropriate permissions
  - CloudFront key pair for signed URLs

### For Cloudflare Stream Plugin
- Cloudflare Account with:
  - Stream enabled
  - API token with Stream permissions
  - Account ID

---

## S3 Video Plugin Installation

### Step 1: AWS S3 Configuration

#### 1.1 Create S3 Bucket

1. Log in to AWS Console
2. Navigate to S3 service
3. Click "Create bucket"
4. Configure bucket:
   ```
   Bucket name: your-moodle-videos
   Region: Choose closest to your users
   Block Public Access: Keep all enabled
   Versioning: Disabled
   Encryption: Enable (AES-256)
   ```
5. Click "Create bucket"

#### 1.2 Configure CORS Policy

1. Select your bucket
2. Go to "Permissions" tab
3. Scroll to "Cross-origin resource sharing (CORS)"
4. Click "Edit" and add:

```json
[
    {
        "AllowedHeaders": [
            "*"
        ],
        "AllowedMethods": [
            "GET",
            "PUT",
            "POST",
            "DELETE",
            "HEAD"
        ],
        "AllowedOrigins": [
            "https://your-moodle-domain.com"
        ],
        "ExposeHeaders": [
            "ETag"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

Replace `https://your-moodle-domain.com` with your actual Moodle URL.

#### 1.3 Create IAM User

1. Navigate to IAM service
2. Click "Users" → "Add users"
3. User name: `moodle-s3-uploader`
4. Access type: Programmatic access
5. Click "Next: Permissions"
6. Click "Attach policies directly"
7. Click "Create policy" and use this JSON:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-moodle-videos",
                "arn:aws:s3:::your-moodle-videos/*"
            ]
        }
    ]
}
```

8. Name the policy: `MoodleS3VideoAccess`
9. Attach the policy to the user
10. **Save the Access Key ID and Secret Access Key** (you'll need these later)

### Step 2: CloudFront Configuration

#### 2.1 Create CloudFront Distribution

1. Navigate to CloudFront service
2. Click "Create distribution"
3. Configure:
   ```
   Origin domain: your-moodle-videos.s3.amazonaws.com
   Origin access: Legacy access identities
   Create new OAI: Yes
   Bucket policy: Yes, update the bucket policy
   Viewer protocol policy: Redirect HTTP to HTTPS
   Allowed HTTP methods: GET, HEAD, OPTIONS
   Cache policy: CachingOptimized
   ```
4. Click "Create distribution"
5. **Save the Distribution Domain Name** (e.g., d1234567890.cloudfront.net)

#### 2.2 Create CloudFront Key Pair

1. Log in as **root user** (not IAM user)
2. Click account name → "Security credentials"
3. Scroll to "CloudFront key pairs"
4. Click "Create key pair"
5. **Download the private key file** (.pem file)
6. **Save the Key Pair ID**

**Important:** Keep the private key file secure. You'll need to upload it to your Moodle server.

### Step 3: Install S3 Video Plugin

#### 3.1 Upload Plugin Files

```bash
# SSH to your Moodle server
cd /var/www/html/mod/assign/submission/

# Upload the s3video folder
# (Use SCP, SFTP, or your preferred method)

# Set correct permissions
chown -R www-data:www-data s3video
chmod -R 755 s3video
```

#### 3.2 Install Plugin via Moodle

1. Log in to Moodle as administrator
2. Navigate to: **Site administration → Notifications**
3. Moodle will detect the new plugin
4. Click "Upgrade Moodle database now"
5. Wait for installation to complete
6. Click "Continue"

### Step 4: Configure S3 Video Plugin

#### 4.1 Upload CloudFront Private Key

```bash
# SSH to your Moodle server
cd /var/www/html/mod/assign/submission/s3video/

# Create keys directory
mkdir -p keys
chmod 700 keys

# Upload your CloudFront private key
# Rename it to: cloudfront-private-key.pem
mv /path/to/downloaded-key.pem keys/cloudfront-private-key.pem

# Set permissions
chmod 600 keys/cloudfront-private-key.pem
chown www-data:www-data keys/cloudfront-private-key.pem
```

#### 4.2 Configure Plugin Settings

1. Navigate to: **Site administration → Plugins → Activity modules → Assignment → Submission plugins → S3 Video**

2. Configure AWS Settings:
   ```
   AWS Access Key: [Your IAM Access Key ID]
   AWS Secret Key: [Your IAM Secret Access Key]
   AWS Region: [Your S3 bucket region, e.g., us-east-1]
   S3 Bucket: [Your bucket name, e.g., your-moodle-videos]
   ```

3. Configure CloudFront Settings:
   ```
   CloudFront Domain: [Your distribution domain, e.g., d1234567890.cloudfront.net]
   CloudFront Key Pair ID: [Your CloudFront key pair ID]
   CloudFront Private Key Path: keys/cloudfront-private-key.pem
   ```

4. Configure Plugin Settings:
   ```
   Maximum File Size: 5GB (or your preference)
   Retention Days: 90 (videos deleted after 90 days)
   Enabled by Default: Yes (optional)
   ```

5. Click "Save changes"

### Step 5: Test S3 Video Plugin

1. Create a test assignment:
   - Go to a course
   - Turn editing on
   - Add an activity → Assignment
   - Name: "Test Video Assignment"
   - Submission types: Enable "S3 Video"
   - Save

2. Test upload as student:
   - Log in as a student
   - Go to the assignment
   - Click "Add submission"
   - Upload a small test video
   - Verify upload completes successfully

3. Test grading as teacher:
   - Log in as teacher
   - Go to assignment → View all submissions
   - Click "Grade" on the test submission
   - Verify video plays in two-column layout

---

## Cloudflare Stream Plugin Installation

### Step 1: Cloudflare Stream Configuration

#### 1.1 Enable Cloudflare Stream

1. Log in to Cloudflare Dashboard
2. Select your account
3. Navigate to "Stream" in the left sidebar
4. If not enabled, click "Enable Stream"
5. Accept the pricing terms

#### 1.2 Get Account ID

1. In Cloudflare Dashboard
2. Click on your profile icon (top right)
3. Go to "Account Home"
4. Your Account ID is displayed on the right side
5. **Copy and save this ID**

#### 1.3 Create API Token

1. Go to "My Profile" → "API Tokens"
2. Click "Create Token"
3. Click "Create Custom Token"
4. Configure:
   ```
   Token name: Moodle Stream Access
   Permissions:
     - Account | Stream | Edit
   Account Resources:
     - Include | [Your Account]
   ```
5. Click "Continue to summary"
6. Click "Create Token"
7. **Copy and save the API token** (you won't see it again)

#### 1.4 Configure Domain Restrictions (Optional but Recommended)

1. Go to Stream → Settings
2. Under "Allowed domains"
3. Add your Moodle domain: `https://your-moodle-domain.com`
4. This prevents videos from being embedded on other sites

### Step 2: Install Cloudflare Stream Plugin

#### 2.1 Upload Plugin Files

```bash
# SSH to your Moodle server
cd /var/www/html/mod/assign/submission/

# Upload the cloudflarestream folder
# (Use SCP, SFTP, or your preferred method)

# Set correct permissions
chown -R www-data:www-data cloudflarestream
chmod -R 755 cloudflarestream
```

#### 2.2 Install Plugin via Moodle

1. Log in to Moodle as administrator
2. Navigate to: **Site administration → Notifications**
3. Moodle will detect the new plugin
4. Click "Upgrade Moodle database now"
5. Wait for installation to complete
6. Click "Continue"

### Step 3: Configure Cloudflare Stream Plugin

1. Navigate to: **Site administration → Plugins → Activity modules → Assignment → Submission plugins → Cloudflare Stream**

2. Configure Cloudflare Settings:
   ```
   Cloudflare API Token: [Your API token from Step 1.3]
   Cloudflare Account ID: [Your Account ID from Step 1.2]
   ```

3. Configure Plugin Settings:
   ```
   Maximum File Size: 5GB (or your preference)
   Retention Days: 90 (videos deleted after 90 days)
   Enabled by Default: Yes (optional)
   ```

4. Click "Save changes"

### Step 4: Test Cloudflare Stream Plugin

1. Create a test assignment:
   - Go to a course
   - Turn editing on
   - Add an activity → Assignment
   - Name: "Test Cloudflare Video Assignment"
   - Submission types: Enable "Cloudflare Stream"
   - Save

2. Test upload as student:
   - Log in as a student
   - Go to the assignment
   - Click "Add submission"
   - Upload a small test video
   - Wait for "Ready" status (may take 1-2 minutes for processing)

3. Test grading as teacher:
   - Log in as teacher
   - Go to assignment → View all submissions
   - Click "Grade" on the test submission
   - Verify video plays in two-column layout

---

## Post-Installation Configuration

### Enable Scheduled Tasks

Both plugins require scheduled tasks for cleanup and maintenance.

1. Navigate to: **Site administration → Server → Scheduled tasks**
2. Find these tasks:
   - "Clean up old S3 videos" (for S3 plugin)
   - "Clean up old Cloudflare Stream videos" (for Cloudflare plugin)
3. Verify they are enabled
4. Default schedule: Daily at 2:00 AM

### Configure Cron

Ensure Moodle cron is running:

```bash
# Add to crontab
*/5 * * * * /usr/bin/php /var/www/html/admin/cli/cron.php
```

### Clear Moodle Cache

After installation and configuration:

1. Navigate to: **Site administration → Development → Purge all caches**
2. Click "Purge all caches"

---

## Testing & Verification

### S3 Video Plugin Tests

1. **Upload Test:**
   - Upload a video as student
   - Verify it appears in S3 bucket
   - Check CloudFront distribution for the file

2. **Playback Test:**
   - View video as student (own submission)
   - View video as teacher (grading)
   - Verify signed URLs are working

3. **Grading Interface Test:**
   - Open grading page
   - Verify two-column layout
   - Verify video plays while grading

4. **Cleanup Test:**
   - Run scheduled task manually:
     ```bash
     php admin/cli/scheduled_task.php --execute=\\assignsubmission_s3video\\task\\cleanup_videos
     ```
   - Verify old videos are deleted

### Cloudflare Stream Plugin Tests

1. **Upload Test:**
   - Upload a video as student
   - Verify it appears in Cloudflare Stream dashboard
   - Wait for "Ready" status

2. **Playback Test:**
   - View video as student
   - View video as teacher
   - Verify video is public (no permission errors)

3. **Grading Interface Test:**
   - Open grading page
   - Verify two-column layout
   - Verify video plays while grading

4. **Sync Test:**
   - Delete a video from Cloudflare dashboard
   - Run scheduled task:
     ```bash
     php admin/cli/scheduled_task.php --execute=\\assignsubmission_cloudflarestream\\task\\cleanup_videos
     ```
   - Verify database is updated

---

## Troubleshooting

### S3 Video Plugin Issues

**Problem:** Upload fails with "Access Denied"
- **Solution:** Check IAM user permissions and S3 bucket policy

**Problem:** Video won't play - "Access Denied"
- **Solution:** Verify CloudFront private key is correctly uploaded and permissions are set

**Problem:** CORS errors in browser console
- **Solution:** Check S3 bucket CORS configuration includes your Moodle domain

**Problem:** Videos not being deleted
- **Solution:** Verify scheduled task is enabled and cron is running

### Cloudflare Stream Plugin Issues

**Problem:** Upload fails
- **Solution:** Verify API token has Stream Edit permissions

**Problem:** Video shows "Permission denied"
- **Solution:** Check that videos are uploading as public (not private)

**Problem:** Video stuck in "Pending" status
- **Solution:** Wait 1-2 minutes for Cloudflare processing, or check Cloudflare dashboard for errors

**Problem:** Sync not detecting deleted videos
- **Solution:** Run scheduled task manually to verify it's working

### General Issues

**Problem:** Plugin not appearing in assignment settings
- **Solution:** Clear Moodle cache and verify plugin is installed

**Problem:** Two-column layout not working
- **Solution:** Clear browser cache and Moodle cache

**Problem:** Rate limit errors
- **Solution:** Adjust rate limiting settings in plugin configuration

---

## Support & Maintenance

### Regular Maintenance Tasks

1. **Monitor Storage:**
   - Check S3 bucket size
   - Check Cloudflare Stream usage
   - Adjust retention period if needed

2. **Review Logs:**
   - Check Moodle logs for errors
   - Review plugin dashboards for failed uploads

3. **Update Plugins:**
   - Keep plugins updated
   - Test updates in staging environment first

### Security Best Practices

1. **Rotate Credentials:**
   - Rotate AWS access keys annually
   - Rotate Cloudflare API tokens annually

2. **Monitor Access:**
   - Review CloudFront access logs
   - Monitor Cloudflare Stream analytics

3. **Backup Configuration:**
   - Document all settings
   - Keep backup of CloudFront private key

---

## Conclusion

Both plugins are now installed and configured. Students can submit video assignments, and teachers can grade them with an integrated video player.

For additional support or questions, refer to:
- Plugin README files
- Moodle documentation
- AWS/Cloudflare documentation

---

**Document Version:** 1.0  
**Last Updated:** October 30, 2025  
**Prepared for:** Development Team
