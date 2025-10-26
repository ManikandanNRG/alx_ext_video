# S3 Video Plugin - Deployment Checklist

This checklist guides you through deploying the S3 Video submission plugin from development to production.

## Prerequisites

### System Requirements

- [ ] Moodle 3.9 or higher installed
- [ ] PHP 7.4 or higher
- [ ] PHP extensions installed:
  - [ ] curl
  - [ ] openssl
  - [ ] json
  - [ ] mbstring
- [ ] Composer installed (for AWS SDK)
- [ ] HTTPS enabled on Moodle site
- [ ] Sufficient disk space for plugin files (~5 MB)

### AWS Account Requirements

- [ ] AWS account created and billing enabled
- [ ] Credit card on file (for AWS charges)
- [ ] AWS CLI installed (optional, for automation)
- [ ] Root account access (for CloudFront key pair creation)

### Access Requirements

- [ ] Moodle administrator access
- [ ] Server SSH/FTP access
- [ ] AWS Console access
- [ ] DNS management access (if using custom domain)

## Phase 1: AWS Account Setup

### Step 1.1: Create S3 Bucket

- [ ] Log in to AWS Console
- [ ] Navigate to S3 service
- [ ] Click "Create bucket"
- [ ] Configure bucket:
  - [ ] Bucket name: `[your-org]-moodle-videos-[env]` (e.g., `acme-moodle-videos-prod`)
  - [ ] Region: Select closest to users (e.g., `us-east-1`)
  - [ ] Block all public access: **Enabled**
  - [ ] Bucket versioning: **Disabled** (optional: enable for backup)
  - [ ] Default encryption: **Enabled** (SSE-S3)
  - [ ] Object lock: **Disabled**
- [ ] Click "Create bucket"
- [ ] Note bucket name and region

**Bucket Naming Convention:**
- Production: `[org]-moodle-videos-prod`
- Staging: `[org]-moodle-videos-staging`
- Development: `[org]-moodle-videos-dev`

### Step 1.2: Configure S3 CORS

- [ ] Select your bucket
- [ ] Go to "Permissions" tab
- [ ] Scroll to "Cross-origin resource sharing (CORS)"
- [ ] Click "Edit"
- [ ] Paste CORS configuration:

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "POST", "PUT"],
    "AllowedOrigins": ["https://your-moodle-site.com"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3000
  }
]
```

- [ ] Replace `https://your-moodle-site.com` with actual Moodle URL
- [ ] For multiple domains (staging + production), add multiple origins:

```json
"AllowedOrigins": [
  "https://moodle.example.com",
  "https://staging.moodle.example.com"
]
```

- [ ] Click "Save changes"
- [ ] Test CORS with browser developer tools

### Step 1.3: Configure S3 Lifecycle Policy (Optional)

For automatic cleanup of incomplete uploads:

- [ ] Go to "Management" tab
- [ ] Click "Create lifecycle rule"
- [ ] Configure:
  - [ ] Rule name: `cleanup-incomplete-uploads`
  - [ ] Rule scope: Apply to all objects
  - [ ] Lifecycle rule actions: "Delete expired object delete markers or incomplete multipart uploads"
  - [ ] Days after initiation: `7`
- [ ] Click "Create rule"

### Step 1.4: Create CloudFront Distribution

- [ ] Navigate to CloudFront in AWS Console
- [ ] Click "Create distribution"
- [ ] Configure origin:
  - [ ] Origin domain: Select your S3 bucket
  - [ ] Origin path: Leave empty
  - [ ] Name: Auto-generated (keep default)
  - [ ] Origin access: **Legacy access identities**
  - [ ] Click "Create new OAI"
  - [ ] OAI name: `moodle-videos-oai`
  - [ ] Bucket policy: **Yes, update the bucket policy**
- [ ] Configure default cache behavior:
  - [ ] Viewer protocol policy: **Redirect HTTP to HTTPS**
  - [ ] Allowed HTTP methods: **GET, HEAD, OPTIONS**
  - [ ] Restrict viewer access: **Yes**
  - [ ] Trusted signers: **Self** (AWS account)
  - [ ] Cache policy: **CachingOptimized**
- [ ] Configure settings:
  - [ ] Price class: Choose based on budget (All edge locations recommended)
  - [ ] Alternate domain name (CNAME): Optional (e.g., `videos.moodle.example.com`)
  - [ ] Custom SSL certificate: If using CNAME, upload certificate
  - [ ] Default root object: Leave empty
  - [ ] Logging: **On** (recommended for production)
  - [ ] Log bucket: Create or select S3 bucket for logs
- [ ] Click "Create distribution"
- [ ] Wait for deployment (Status: "Deployed") - takes 15-20 minutes
- [ ] Note the distribution domain name (e.g., `d123abc456def.cloudfront.net`)

**Important:** Do not proceed until distribution status is "Deployed"

### Step 1.5: Create CloudFront Key Pair

**Note:** Must be done as root user

- [ ] Log out of AWS Console
- [ ] Log in as **root user** (email + password, not IAM user)
- [ ] Click account name (top right) > "Security credentials"
- [ ] Scroll to "CloudFront key pairs" section
- [ ] Click "Create new key pair"
- [ ] Download both files immediately:
  - [ ] `pk-APKAXXXXXXXXXXXXXXXX.pem` (private key)
  - [ ] `rsa-APKAXXXXXXXXXXXXXXXX.pem` (public key)
- [ ] Note the Access Key ID (e.g., `APKAXXXXXXXXXXXXXXXX`)
- [ ] Store private key securely (password manager, encrypted storage)
- [ ] **Never commit private key to version control**
- [ ] Set file permissions: `chmod 600 pk-*.pem`

**Security Note:** The private key cannot be downloaded again. If lost, you must create a new key pair.

### Step 1.6: Create IAM User

- [ ] Navigate to IAM > Users
- [ ] Click "Add users"
- [ ] User name: `moodle-s3video-[env]` (e.g., `moodle-s3video-prod`)
- [ ] Access type: **Access key - Programmatic access**
- [ ] Click "Next: Permissions"
- [ ] Click "Attach policies directly"
- [ ] Click "Create policy"
- [ ] Select JSON tab
- [ ] Paste policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "S3VideoAccess",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:HeadObject"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name/*"
    },
    {
      "Sid": "CloudFrontInvalidation",
      "Effect": "Allow",
      "Action": [
        "cloudfront:CreateInvalidation"
      ],
      "Resource": "arn:aws:cloudfront::*:distribution/*"
    }
  ]
}
```

- [ ] Replace `your-bucket-name` with actual bucket name
- [ ] Click "Next: Tags"
- [ ] Add tags (optional):
  - [ ] Key: `Environment`, Value: `Production`
  - [ ] Key: `Application`, Value: `Moodle`
- [ ] Click "Next: Review"
- [ ] Policy name: `MoodleS3VideoPolicy-[env]`
- [ ] Click "Create policy"
- [ ] Return to user creation, refresh policies, and attach the new policy
- [ ] Click "Next: Tags" (add same tags as policy)
- [ ] Click "Next: Review"
- [ ] Click "Create user"
- [ ] **Download credentials CSV** - contains Access Key ID and Secret Access Key
- [ ] Store credentials securely (password manager)
- [ ] **Never commit credentials to version control**

### Step 1.7: Test AWS Setup

- [ ] Install AWS CLI (if not already installed)
- [ ] Configure AWS CLI with IAM user credentials:

```bash
aws configure --profile moodle-s3video
# Enter Access Key ID
# Enter Secret Access Key
# Enter region (e.g., us-east-1)
# Enter output format: json
```

- [ ] Test S3 access:

```bash
# Upload test file
echo "test" > test.txt
aws s3 cp test.txt s3://your-bucket-name/test.txt --profile moodle-s3video

# Verify upload
aws s3 ls s3://your-bucket-name/ --profile moodle-s3video

# Delete test file
aws s3 rm s3://your-bucket-name/test.txt --profile moodle-s3video
```

- [ ] Verify CloudFront distribution is deployed:

```bash
aws cloudfront list-distributions --profile moodle-s3video
```

- [ ] All tests passed successfully

## Phase 2: Staging Deployment

### Step 2.1: Prepare Staging Environment

- [ ] Staging Moodle instance is running
- [ ] Staging database is backed up
- [ ] Staging site is in maintenance mode (optional)
- [ ] Create staging AWS resources (separate bucket/distribution) OR use production with different prefix

### Step 2.2: Install Plugin Files

**Option A: Manual Installation**

- [ ] Download plugin files
- [ ] Connect to server via SSH/FTP
- [ ] Navigate to Moodle root directory
- [ ] Create plugin directory:

```bash
mkdir -p mod/assign/submission/s3video
```

- [ ] Upload all plugin files to `mod/assign/submission/s3video/`
- [ ] Set correct permissions:

```bash
chown -R www-data:www-data mod/assign/submission/s3video
chmod -R 755 mod/assign/submission/s3video
```

**Option B: Git Installation**

- [ ] SSH into server
- [ ] Navigate to Moodle root
- [ ] Clone repository:

```bash
cd mod/assign/submission
git clone https://github.com/your-org/moodle-assignsubmission_s3video.git s3video
```

- [ ] Checkout specific version:

```bash
cd s3video
git checkout v1.0.0
```

### Step 2.3: Install AWS SDK

- [ ] Navigate to Moodle root directory
- [ ] Install AWS SDK via Composer:

```bash
composer require aws/aws-sdk-php
```

- [ ] Verify installation:

```bash
composer show aws/aws-sdk-php
```

- [ ] Ensure `vendor/` directory is not publicly accessible (check .htaccess)

### Step 2.4: Run Moodle Upgrade

- [ ] Log in to Moodle as administrator
- [ ] Navigate to **Site administration > Notifications**
- [ ] Review upgrade information
- [ ] Click "Upgrade Moodle database now"
- [ ] Wait for upgrade to complete
- [ ] Verify no errors in upgrade log
- [ ] Check database tables created:

```sql
SELECT * FROM mdl_assignsubmission_s3video LIMIT 1;
SELECT * FROM mdl_assignsubmission_s3v_log LIMIT 1;
```

### Step 2.5: Configure Plugin Settings

- [ ] Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video**
- [ ] Configure AWS credentials:
  - [ ] AWS Access Key ID: `[from IAM user]`
  - [ ] AWS Secret Access Key: `[from IAM user]`
  - [ ] S3 Bucket Name: `[your-bucket-name]`
  - [ ] S3 Region: `[e.g., us-east-1]`
  - [ ] CloudFront Domain: `[e.g., d123abc456def.cloudfront.net]`
  - [ ] CloudFront Key Pair ID: `[e.g., APKAXXXXXXXXXXXXXXXX]`
  - [ ] CloudFront Private Key: `[paste contents of pk-*.pem file]`
- [ ] Configure retention settings:
  - [ ] Video Retention Days: `90` (or as per policy)
  - [ ] Enable Automatic Cleanup: **Yes**
- [ ] Configure upload limits:
  - [ ] Maximum File Size: `5368709120` (5 GB, default)
  - [ ] Allowed MIME Types: `video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm`
- [ ] Configure rate limiting:
  - [ ] Upload URL Requests Per Minute: `10`
  - [ ] Playback URL Requests Per Minute: `30`
- [ ] Click "Save changes"
- [ ] Verify no error messages

### Step 2.6: Enable Plugin

- [ ] Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > Manage submission plugins**
- [ ] Find "S3 Video" in the list
- [ ] Click the eye icon to enable it (if disabled)
- [ ] Optionally move it up/down in the list to set priority
- [ ] Optionally set as default for new assignments

### Step 2.7: Test Upload Workflow

- [ ] Create test course (if not exists)
- [ ] Create test assignment:
  - [ ] Name: "Test Video Upload"
  - [ ] Submission types: Enable "S3 Video"
  - [ ] Maximum file size: 5 GB
  - [ ] Due date: Future date
- [ ] Enroll test student account
- [ ] Log in as test student
- [ ] Navigate to assignment
- [ ] Click "Add submission"
- [ ] Upload small test video (< 100 MB):
  - [ ] Select video file
  - [ ] Verify file validation works
  - [ ] Click "Upload"
  - [ ] Verify progress bar appears
  - [ ] Wait for upload to complete
  - [ ] Verify success message
- [ ] Click "Save changes"
- [ ] Verify submission saved

### Step 2.8: Test Playback Workflow

- [ ] Log in as teacher
- [ ] Navigate to assignment
- [ ] Click "View all submissions"
- [ ] Click on test student's submission
- [ ] Verify video player loads
- [ ] Click play button
- [ ] Verify video plays without errors
- [ ] Test video controls (pause, seek, volume)
- [ ] Check browser console for errors (should be none)

### Step 2.9: Test Access Control

- [ ] Create second test student account
- [ ] Log in as second student
- [ ] Try to access first student's submission URL directly
- [ ] Verify access is denied
- [ ] Log in as first student
- [ ] Verify they can view their own submission

### Step 2.10: Test Admin Features

- [ ] Log in as administrator
- [ ] Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video > Dashboard**
- [ ] Verify dashboard displays:
  - [ ] Upload statistics
  - [ ] Storage usage
  - [ ] Recent uploads
  - [ ] Error logs (should be empty)
- [ ] Navigate to **Manage Videos**
- [ ] Verify video list displays test upload
- [ ] Test search functionality
- [ ] Test manual deletion (optional)

### Step 2.11: Test Error Handling

- [ ] Test invalid file type upload (e.g., .txt file)
- [ ] Verify error message displays
- [ ] Test file size limit (upload file > 5 GB)
- [ ] Verify error message displays
- [ ] Temporarily break AWS credentials (change one character)
- [ ] Try to upload
- [ ] Verify user-friendly error message (not AWS error)
- [ ] Restore correct credentials

### Step 2.12: Performance Testing

- [ ] Upload larger video (1-2 GB)
- [ ] Verify progress tracking works
- [ ] Verify upload completes successfully
- [ ] Test playback of large video
- [ ] Verify no buffering issues
- [ ] Check server load during upload (should be minimal)

### Step 2.13: Browser Compatibility Testing

- [ ] Test upload in Chrome
- [ ] Test upload in Firefox
- [ ] Test upload in Safari
- [ ] Test upload in Edge
- [ ] Test playback in all browsers
- [ ] Test on mobile devices (iOS, Android)

### Step 2.14: Staging Sign-Off

- [ ] All tests passed
- [ ] No errors in Moodle logs
- [ ] No errors in browser console
- [ ] Performance is acceptable
- [ ] Stakeholders have reviewed and approved
- [ ] Document any issues found and resolved

## Phase 3: Production Deployment

### Step 3.1: Pre-Deployment Preparation

- [ ] Schedule deployment window (low-traffic period recommended)
- [ ] Notify users of potential downtime
- [ ] Create deployment rollback plan
- [ ] Backup production database:

```bash
mysqldump -u root -p moodle > moodle_backup_$(date +%Y%m%d_%H%M%S).sql
```

- [ ] Backup Moodle files:

```bash
tar -czf moodle_files_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/moodle
```

- [ ] Verify backups are complete and accessible
- [ ] Document current Moodle version
- [ ] Review staging test results

### Step 3.2: Enable Maintenance Mode

- [ ] Log in to Moodle as administrator
- [ ] Navigate to **Site administration > Server > Maintenance mode**
- [ ] Enable maintenance mode
- [ ] Set maintenance message (optional)
- [ ] Verify users see maintenance page

**Alternative (CLI):**

```bash
php admin/cli/maintenance.php --enable
```

### Step 3.3: Install Plugin Files

Follow same steps as staging (Step 2.2):

- [ ] Upload plugin files to `mod/assign/submission/s3video/`
- [ ] Set correct permissions
- [ ] Install AWS SDK via Composer
- [ ] Verify file integrity

### Step 3.4: Run Database Upgrade

- [ ] Navigate to **Site administration > Notifications**
- [ ] Review upgrade information carefully
- [ ] Click "Upgrade Moodle database now"
- [ ] Monitor upgrade progress
- [ ] Verify no errors
- [ ] Check upgrade log for warnings

**Alternative (CLI):**

```bash
php admin/cli/upgrade.php --non-interactive
```

### Step 3.5: Configure Plugin Settings

- [ ] Navigate to plugin settings page
- [ ] Configure AWS credentials (production values)
- [ ] Configure retention settings
- [ ] Configure upload limits
- [ ] Configure rate limiting
- [ ] Save settings
- [ ] Verify no errors

### Step 3.6: Enable Plugin

- [ ] Navigate to submission plugins management page
- [ ] Enable S3 Video plugin
- [ ] Set as default (if desired)
- [ ] Verify plugin appears in list

### Step 3.7: Smoke Testing

Perform quick tests to verify basic functionality:

- [ ] Create test assignment with S3 Video enabled
- [ ] Upload small test video as student
- [ ] View video as teacher
- [ ] Check dashboard for upload statistics
- [ ] Verify no errors in Moodle logs
- [ ] Delete test assignment

### Step 3.8: Disable Maintenance Mode

- [ ] Navigate to **Site administration > Server > Maintenance mode**
- [ ] Disable maintenance mode
- [ ] Verify users can access site

**Alternative (CLI):**

```bash
php admin/cli/maintenance.php --disable
```

### Step 3.9: Monitor Initial Usage

- [ ] Monitor Moodle error logs:

```bash
tail -f /path/to/moodle/error.log
```

- [ ] Monitor web server logs:

```bash
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # Nginx
```

- [ ] Monitor AWS CloudWatch for S3/CloudFront errors
- [ ] Check plugin dashboard for upload statistics
- [ ] Monitor user feedback channels

### Step 3.10: Post-Deployment Verification

- [ ] Verify plugin appears in installed plugins list
- [ ] Check database tables exist and are populated
- [ ] Test upload with real user account
- [ ] Test playback with real user account
- [ ] Verify email notifications work (if configured)
- [ ] Check scheduled task is registered:

```bash
php admin/cli/scheduled_task.php --list | grep cleanup_videos
```

### Step 3.11: Performance Monitoring

- [ ] Monitor server CPU usage (should not increase)
- [ ] Monitor server memory usage (should not increase)
- [ ] Monitor server disk usage (should not increase)
- [ ] Monitor AWS costs in billing dashboard
- [ ] Set up AWS billing alerts:
  - [ ] Alert at $50/month
  - [ ] Alert at $100/month
  - [ ] Alert at $200/month

### Step 3.12: Documentation Updates

- [ ] Update internal documentation with production URLs
- [ ] Create user guide for teachers
- [ ] Create user guide for students
- [ ] Document support procedures
- [ ] Update runbook with troubleshooting steps

### Step 3.13: User Communication

- [ ] Announce new feature to users
- [ ] Provide link to user guides
- [ ] Highlight key benefits (large file support, fast playback)
- [ ] Provide support contact information
- [ ] Schedule training sessions (if needed)

### Step 3.14: Production Sign-Off

- [ ] All smoke tests passed
- [ ] No critical errors in logs
- [ ] Performance is acceptable
- [ ] Users can upload and view videos
- [ ] Stakeholders have approved deployment
- [ ] Document deployment completion date/time

## Phase 4: Post-Deployment

### Step 4.1: Ongoing Monitoring (First Week)

- [ ] Day 1: Check logs every 2 hours
- [ ] Day 2-3: Check logs every 4 hours
- [ ] Day 4-7: Check logs daily
- [ ] Monitor upload success rate (target: >95%)
- [ ] Monitor playback errors (target: <1%)
- [ ] Monitor AWS costs daily
- [ ] Review user feedback and support tickets

### Step 4.2: Scheduled Task Verification

- [ ] Wait for first scheduled cleanup task to run (2 AM)
- [ ] Verify task executed successfully:

```bash
php admin/cli/scheduled_task.php --execute=\\assignsubmission_s3video\\task\\cleanup_videos
```

- [ ] Check logs for cleanup results
- [ ] Verify old videos were deleted (if any)
- [ ] Verify CloudFront invalidations created

### Step 4.3: GDPR Compliance Verification

- [ ] Test data export for user with video submissions
- [ ] Verify video metadata included in export
- [ ] Test user deletion
- [ ] Verify videos deleted from S3
- [ ] Verify database records deleted
- [ ] Document GDPR compliance procedures

### Step 4.4: Security Audit

- [ ] Verify AWS credentials not exposed in HTML/JavaScript
- [ ] Test unauthorized access attempts
- [ ] Verify rate limiting is working
- [ ] Check for SQL injection vulnerabilities
- [ ] Check for XSS vulnerabilities
- [ ] Review CloudFront access logs for suspicious activity
- [ ] Verify HTTPS is enforced

### Step 4.5: Performance Optimization

- [ ] Review CloudFront cache hit ratio (target: >80%)
- [ ] Optimize cache settings if needed
- [ ] Review S3 request patterns
- [ ] Consider S3 Transfer Acceleration if users are global
- [ ] Review and optimize database queries
- [ ] Consider adding database indexes if needed

### Step 4.6: Cost Optimization

- [ ] Review actual AWS costs vs. estimates
- [ ] Adjust retention period if costs are high
- [ ] Consider S3 Intelligent-Tiering for long-term storage
- [ ] Review CloudFront price class (reduce if acceptable)
- [ ] Set up AWS Cost Explorer for detailed analysis
- [ ] Document cost optimization recommendations

### Step 4.7: User Training and Support

- [ ] Conduct training sessions for teachers
- [ ] Create video tutorials for students
- [ ] Update FAQ with common questions
- [ ] Monitor support tickets for patterns
- [ ] Create troubleshooting guide for support team
- [ ] Gather user feedback for improvements

### Step 4.8: Backup and Disaster Recovery

- [ ] Document backup procedures
- [ ] Test database restore procedure
- [ ] Test plugin reinstallation procedure
- [ ] Document AWS resource recreation steps
- [ ] Create disaster recovery runbook
- [ ] Schedule regular backup tests

### Step 4.9: Update Monitoring and Alerts

- [ ] Set up Moodle log monitoring alerts
- [ ] Set up AWS CloudWatch alarms:
  - [ ] S3 4xx/5xx errors
  - [ ] CloudFront 4xx/5xx errors
  - [ ] High request rates
  - [ ] Unusual data transfer
- [ ] Set up uptime monitoring for video playback
- [ ] Configure alert notifications (email, Slack, etc.)

### Step 4.10: Documentation Review

- [ ] Review and update README.md
- [ ] Review and update DEVELOPER_GUIDE.md
- [ ] Review and update this deployment checklist
- [ ] Document lessons learned
- [ ] Update version numbers
- [ ] Tag release in version control

## Rollback Procedure

If critical issues are discovered after deployment:

### Immediate Rollback Steps

1. **Enable Maintenance Mode**
   ```bash
   php admin/cli/maintenance.php --enable
   ```

2. **Disable Plugin**
   - Navigate to plugin management page
   - Disable S3 Video plugin
   - This prevents new uploads but preserves existing data

3. **Restore Database (if needed)**
   ```bash
   mysql -u root -p moodle < moodle_backup_YYYYMMDD_HHMMSS.sql
   ```

4. **Remove Plugin Files (if needed)**
   ```bash
   rm -rf mod/assign/submission/s3video
   ```

5. **Purge Caches**
   ```bash
   php admin/cli/purge_caches.php
   ```

6. **Disable Maintenance Mode**
   ```bash
   php admin/cli/maintenance.php --disable
   ```

7. **Notify Users**
   - Inform users of rollback
   - Provide timeline for resolution
   - Offer alternative submission methods

### Post-Rollback Actions

- [ ] Document reason for rollback
- [ ] Analyze root cause of issues
- [ ] Fix issues in staging environment
- [ ] Re-test thoroughly
- [ ] Schedule new deployment

## Troubleshooting Common Issues

### Issue: Plugin Not Appearing After Installation

**Symptoms:** Plugin not visible in submission plugins list

**Solutions:**
- [ ] Purge all caches: `php admin/cli/purge_caches.php`
- [ ] Check file permissions: `ls -la mod/assign/submission/s3video`
- [ ] Verify version.php exists and is readable
- [ ] Check Moodle error logs for PHP errors

### Issue: Database Tables Not Created

**Symptoms:** Error "Table 'mdl_assignsubmission_s3video' doesn't exist"

**Solutions:**
- [ ] Run upgrade manually: `php admin/cli/upgrade.php`
- [ ] Check database user has CREATE TABLE permission
- [ ] Review install.xml for syntax errors
- [ ] Check Moodle error logs for SQL errors

### Issue: AWS Credentials Invalid

**Symptoms:** "Access Denied" errors when uploading

**Solutions:**
- [ ] Verify Access Key ID and Secret Access Key are correct
- [ ] Check IAM user has required permissions
- [ ] Verify IAM policy is attached to user
- [ ] Test credentials with AWS CLI
- [ ] Check for typos in bucket name or region

### Issue: CORS Errors in Browser

**Symptoms:** "CORS policy" error in browser console

**Solutions:**
- [ ] Verify S3 CORS configuration includes Moodle domain
- [ ] Check for typos in AllowedOrigins
- [ ] Ensure HTTPS is used (not HTTP)
- [ ] Clear browser cache
- [ ] Test with different browser

### Issue: Videos Won't Play

**Symptoms:** Player loads but video doesn't play

**Solutions:**
- [ ] Verify CloudFront distribution is deployed (not "In Progress")
- [ ] Check CloudFront private key is correct (no extra spaces/newlines)
- [ ] Verify CloudFront Key Pair ID matches
- [ ] Test signed URL directly in browser
- [ ] Check browser console for errors
- [ ] Verify video file exists in S3

### Issue: High AWS Costs

**Symptoms:** AWS bill higher than expected

**Solutions:**
- [ ] Review AWS Cost Explorer for breakdown
- [ ] Check for unusually large files
- [ ] Verify cleanup task is running
- [ ] Reduce retention period
- [ ] Check for repeated failed uploads (wasting requests)
- [ ] Consider CloudFront price class optimization

## Support Contacts

### Internal Contacts
- **Moodle Administrator:** [Name, Email, Phone]
- **System Administrator:** [Name, Email, Phone]
- **AWS Account Owner:** [Name, Email, Phone]

### External Support
- **Moodle Community:** https://moodle.org/support/
- **AWS Support:** https://console.aws.amazon.com/support/
- **Plugin Developer:** [GitHub Issues URL]

## Appendix

### A. AWS Resource Naming Convention

| Resource | Naming Pattern | Example |
|----------|---------------|---------|
| S3 Bucket | `[org]-moodle-videos-[env]` | `acme-moodle-videos-prod` |
| CloudFront Distribution | Auto-generated | `d123abc456def.cloudfront.net` |
| IAM User | `moodle-s3video-[env]` | `moodle-s3video-prod` |
| IAM Policy | `MoodleS3VideoPolicy-[env]` | `MoodleS3VideoPolicy-prod` |

### B. Required AWS Permissions

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
        "s3:HeadObject"
      ],
      "Resource": "arn:aws:s3:::bucket-name/*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "cloudfront:CreateInvalidation"
      ],
      "Resource": "arn:aws:cloudfront::*:distribution/*"
    }
  ]
}
```

### C. Moodle Capabilities

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `assignsubmission/s3video:use` | Use S3 video submission | Student, Teacher |
| `assignsubmission/s3video:view` | View video submissions | Teacher, Manager |
| `assignsubmission/s3video:manage` | Manage videos (delete, etc.) | Manager, Admin |

### D. Database Schema Reference

See DEVELOPER_GUIDE.md for complete database schema documentation.

### E. Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-10-26 | Initial release |

---

**Deployment Checklist Version:** 1.0.0  
**Last Updated:** 2025-10-26  
**Next Review Date:** 2026-01-26
