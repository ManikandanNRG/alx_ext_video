# Cloudflare Stream Plugin - Complete Installation & Configuration Guide

**Version:** 1.2  
**Last Updated:** November 2025  
**Plugin Type:** Moodle Assignment Submission Plugin  
**Moodle Version:** 4.1+

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Part 1: Cloudflare Setup](#part-1-cloudflare-setup)
4. [Part 2: Plugin Installation](#part-2-plugin-installation)
5. [Part 3: Plugin Configuration](#part-3-plugin-configuration)
6. [Part 4: Testing](#part-4-testing)
7. [Part 5: Assignment Setup](#part-5-assignment-setup)
8. [Features](#features)
9. [Troubleshooting](#troubleshooting)
10. [Maintenance](#maintenance)

---

## Overview

The Cloudflare Stream plugin allows students to upload video files directly to Cloudflare Stream as assignment submissions. Videos are stored securely in Cloudflare's infrastructure and played back using their optimized player.

### Key Benefits
- **No server storage** - Videos stored in Cloudflare, not your Moodle server
- **Automatic transcoding** - Videos optimized for all devices
- **Secure playback** - Optional signed URLs for private videos
- **Large file support** - Up to 5GB per video using TUS resumable uploads
- **Automatic cleanup** - Failed uploads and expired videos automatically deleted

---

## Prerequisites

### 1. Cloudflare Account
- Active Cloudflare account with Stream enabled
- Stream subscription (starts at $1/1000 minutes stored)
- Account ID and API Token with Stream permissions

### 2. Moodle Environment
- Moodle 4.1 or higher
- PHP 7.4 or higher
- HTTPS enabled (required for secure uploads)
- SSH/SFTP access to server

### 3. Server Requirements
- Write permissions to Moodle directory
- Ability to run CLI commands
- Cron configured for scheduled tasks

---

## Part 1: Cloudflare Setup

### Step 1.1: Get Your Cloudflare Account ID

1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Click on **Stream** in the left sidebar
3. Your **Account ID** is displayed in the URL or on the Stream page
4. Copy and save this ID (format: `abc123def456...`)

### Step 1.2: Create API Token

1. Go to **My Profile** → **API Tokens**
2. Click **Create Token**
3. Click **Use template** next to "Edit Cloudflare Stream"
4. Configure permissions:
   - **Account** → **Stream** → **Edit**
5. Click **Continue to summary**
6. Click **Create Token**
7. **IMPORTANT:** Copy the token immediately (you won't see it again!)
8. Save it securely (format: `abc123_XYZ...`)

### Step 1.3: Verify Stream is Enabled

1. Go to **Stream** in Cloudflare Dashboard
2. Ensure you see the Stream interface (not a signup page)
3. If not enabled, follow Cloudflare's instructions to enable Stream

---

## Part 2: Plugin Installation

### Step 2.1: Upload Plugin Files

**Option A: Using SSH/Terminal**

```bash
# Navigate to Moodle root
cd /var/www/html

# Upload plugin to correct location
# The plugin should be at: mod/assign/submission/cloudflarestream/

# Verify structure
ls -la mod/assign/submission/cloudflarestream/
# You should see: version.php, lib.php, settings.php, etc.
```

**Option B: Using SFTP/FTP**

1. Connect to your server via SFTP
2. Navigate to `/var/www/html/mod/assign/submission/`
3. Upload the `cloudflarestream` folder
4. Ensure all files are uploaded

### Step 2.2: Set Permissions

```bash
# Set correct ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream

# Set correct permissions
sudo chmod -R 755 /var/www/html/mod/assign/submission/cloudflarestream
```

### Step 2.3: Install Plugin via Moodle

1. Log in to Moodle as **Administrator**
2. Moodle will detect the new plugin automatically
3. You'll see: **"Plugins requiring attention"** notification
4. Click **Upgrade Moodle database now**
5. Review the plugin information
6. Click **Upgrade**
7. Wait for installation to complete
8. Click **Continue**

---

## Part 3: Plugin Configuration

### Step 3.1: Access Plugin Settings

1. Go to **Site administration**
2. Navigate to **Plugins** → **Activity modules** → **Assignment**
3. Click **Submission plugins**
4. Find **Cloudflare Stream** in the list
5. Click **Settings**

### Step 3.2: Configure Basic Settings

**API Configuration:**

1. **Cloudflare Account ID**
   - Paste your Account ID from Step 1.1
   - Example: `abc123def456ghi789`

2. **Cloudflare API Token**
   - Paste your API Token from Step 1.2
   - Example: `abc123_XYZ...`
   - Keep this secure!

3. **Enable Plugin**
   - Check the box to enable the plugin globally

4. Click **Save changes**

### Step 3.3: Configure Advanced Settings

**File Upload Settings:**

1. **Maximum File Size**
   - Default: 5GB (5368709120 bytes)
   - Adjust based on your needs
   - Note: Must not exceed PHP's upload_max_filesize

2. **Allowed File Types**
   - Default: All video formats
   - Supported: MP4, MOV, AVI, MKV, WebM, MPEG, OGG, 3GP, FLV

**Video Retention:**

1. **Retention Period (days)**
   - Default: 90 days (3 months)
   - After this period, videos are automatically deleted from Cloudflare and database
   - Options: 30, 60, 90, 180, 365, 730, 1095, 1825 days, or **Always (Keep Forever)**
   - **Always (Keep Forever)**: Videos are never automatically deleted - ideal for long-term academic records
   - Recommended: "Always" for permanent records, or 365-1825 days for temporary assignments
   - Set to 0 for no automatic deletion (keep forever)

**Security Settings:**

1. **Require Signed URLs**
   - Enable for private videos (recommended)
   - Disable for public videos
   - Signed URLs expire after a set time

2. **Token Expiration (seconds)**
   - Default: 3600 (1 hour)
   - How long signed URLs remain valid

3. Click **Save changes**

### Step 3.4: Verify Configuration

Run the verification script:

```bash
cd /var/www/html/mod/assign/submission/cloudflarestream
sudo -u www-data php verify_deployment.php
```

Expected output:
```
✓ Plugin files present
✓ Database tables created
✓ API credentials configured
✓ Cloudflare API connection successful
✓ Scheduled tasks registered
```

---

## Part 4: Testing

### Step 4.1: Create Test Assignment

1. Go to a course
2. Turn editing on
3. Add an activity → **Assignment**
4. Configure:
   - **Assignment name:** "Video Test"
   - **Submission types:** Check **Cloudflare Stream**
   - Uncheck other submission types
5. Save and display

### Step 4.2: Test Upload (as Student)

1. Log in as a student (or switch role)
2. Go to the test assignment
3. Click **Add submission**
4. Click **Upload file** or drag and drop a video
5. Wait for upload to complete
6. You should see: "Upload successful" with video preview
7. Click **Save changes**

### Step 4.3: Test Viewing (as Teacher)

1. Log in as teacher
2. Go to **View all submissions**
3. Click on the student's submission
4. Video should play in the two-column layout:
   - Video player on the left
   - Grading form on the right

### Step 4.4: Verify in Cloudflare

1. Go to Cloudflare Dashboard → **Stream**
2. You should see the uploaded video
3. Status should be "Ready"

---

## Part 5: Assignment Setup

### Creating Assignments with Video Submissions

1. **Add Assignment Activity**
   - Go to course → Turn editing on
   - Add activity → Assignment

2. **Configure Submission Types**
   - Scroll to **Submission types**
   - Check **Cloudflare Stream**
   - Optionally enable other types (File, Text, etc.)

3. **Assignment-Specific Settings**
   - The plugin uses global settings
   - No per-assignment configuration needed

4. **Save and Test**
   - Save the assignment
   - Test as a student

---

## Features

### For Students

**Upload Interface:**
- Drag and drop or click to select
- Real-time upload progress
- Support for files up to 5GB
- Automatic retry on failure
- Cancel button with cleanup

**Supported Formats:**
- MP4, MOV, AVI, MKV
- WebM, MPEG, OGG
- 3GP, FLV

**Upload Process:**
- Files under 200MB: Direct upload
- Files over 200MB: Resumable upload (TUS protocol)
- Automatic video processing
- Animated progress indicator

### For Teachers

**Grading Interface:**
- Two-column layout (video left, grading right)
- Inline video playback
- No download required
- Automatic user switching
- Full-screen video option

**Video Management:**
- Dashboard at: Site administration → Plugins → Cloudflare Stream → Video Management
- View all videos
- Search and filter
- Manual cleanup options
- Storage statistics

### Automatic Features

**Cleanup System:**
- Failed uploads deleted immediately
- Stuck uploads cleaned after 30 minutes
- Expired videos deleted based on retention period
- Runs daily via Moodle cron

**Security:**
- Optional signed URLs for private videos
- Rate limiting on API calls
- Input validation and sanitization
- GDPR compliant (privacy provider included)

**Monitoring:**
- Activity logging
- Error tracking
- API call monitoring
- Storage usage tracking

---

## Troubleshooting

### Upload Issues

**Problem:** "Upload failed" error

**Solutions:**
1. Check Cloudflare API credentials
2. Verify file size under limit
3. Check file format is supported
4. Review PHP upload limits:
   ```bash
   php -i | grep upload_max_filesize
   php -i | grep post_max_size
   ```

**Problem:** Upload stuck at "Processing"

**Solutions:**
1. Wait 2-3 minutes (Cloudflare processing time)
2. Check Cloudflare Dashboard for video status
3. Run cleanup task manually:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php --execute='\assignsubmission_cloudflarestream\task\cleanup_videos'
   ```

### Playback Issues

**Problem:** Video not playing

**Solutions:**
1. Check browser console for errors
2. Verify video status in Cloudflare (should be "Ready")
3. Check signed URL settings
4. Clear Moodle caches:
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

**Problem:** "Video not found" error

**Solutions:**
1. Video may have been deleted
2. Check retention period settings
3. Verify video exists in Cloudflare Dashboard

### Configuration Issues

**Problem:** Plugin not appearing in assignment settings

**Solutions:**
1. Verify plugin is enabled in settings
2. Check plugin installation:
   ```bash
   ls -la mod/assign/submission/cloudflarestream/version.php
   ```
3. Re-run database upgrade
4. Clear caches

**Problem:** API connection failed

**Solutions:**
1. Verify Account ID and API Token
2. Check token permissions (must have Stream Edit)
3. Test API manually:
   ```bash
   curl -X GET "https://api.cloudflare.com/client/v4/accounts/YOUR_ACCOUNT_ID/stream" \
     -H "Authorization: Bearer YOUR_API_TOKEN"
   ```

---

## Maintenance

### Regular Tasks

**Weekly:**
- Review Video Management dashboard
- Check storage usage
- Review error logs

**Monthly:**
- Verify scheduled tasks running
- Review retention policy
- Check for plugin updates

**As Needed:**
- Adjust retention period
- Update API token (if expired)
- Clean up old videos manually

### Scheduled Tasks

The plugin registers these automatic tasks:

1. **Cleanup Videos** (Daily at 2:00 AM)
   - Removes failed uploads
   - Deletes expired videos
   - Syncs with Cloudflare

2. **Sync Video Status** (Hourly)
   - Updates video processing status
   - Checks for manually deleted videos

### Manual Cleanup

If needed, run cleanup manually:

```bash
# Navigate to plugin directory
cd /var/www/html/mod/assign/submission/cloudflarestream

# Run manual cleanup
sudo -u www-data php run_cleanup_now.php
```

### Backup Considerations

**What to Backup:**
- Plugin configuration (in Moodle database)
- Video metadata (in Moodle database)

**What NOT to Backup:**
- Video files (stored in Cloudflare, not on your server)

**Restore Process:**
1. Restore Moodle database
2. Reinstall plugin if needed
3. Videos remain in Cloudflare (linked by UID)

### Updating the Plugin

1. Backup current version
2. Upload new plugin files
3. Go to Site administration → Notifications
4. Click **Upgrade database**
5. Test functionality
6. Clear caches

---

## Support & Resources

### Documentation
- Plugin README: `mod/assign/submission/cloudflarestream/README.md`
- Technical docs: `clfdoc/PLUGIN_TECHNICAL_DOCUMENTATION.md`
- Workflow guide: `mod/assign/submission/cloudflarestream/COMPLETE_WORKFLOW_DOCUMENTATION.md`

### Cloudflare Resources
- Stream Documentation: https://developers.cloudflare.com/stream/
- API Reference: https://developers.cloudflare.com/api/operations/stream-videos-list-videos
- Support: https://support.cloudflare.com/

### Common Commands

```bash
# Purge Moodle caches
sudo -u www-data php admin/cli/purge_caches.php

# Run scheduled task manually
sudo -u www-data php admin/cli/scheduled_task.php --execute='\assignsubmission_cloudflarestream\task\cleanup_videos'

# Check plugin version
grep '$plugin->version' mod/assign/submission/cloudflarestream/version.php

# View error logs
tail -f /var/log/apache2/error.log  # or your web server log
```

---

## Version History

**Version 1.2** (Current)
- ✅ TUS resumable uploads for large files (>200MB)
- ✅ Two-column grading interface
- ✅ Automatic user switching in grading
- ✅ Cancel button cleanup
- ✅ UI improvements (generic text, animated progress)
- ✅ Enhanced security (no console logging)

**Version 1.1**
- Initial TUS implementation
- Video management dashboard
- GDPR compliance

**Version 1.0**
- Initial release
- Basic upload and playback
- Cloudflare Stream integration

---

## Quick Reference Card

### Installation Checklist
- [ ] Cloudflare Account ID obtained
- [ ] API Token created with Stream permissions
- [ ] Plugin files uploaded to correct location
- [ ] Permissions set correctly
- [ ] Database upgraded via Moodle
- [ ] API credentials configured
- [ ] Settings saved
- [ ] Verification script run successfully
- [ ] Test assignment created
- [ ] Upload tested as student
- [ ] Playback tested as teacher
- [ ] Cloudflare dashboard verified

### Configuration Quick Settings
```
Account ID: [Your Account ID]
API Token: [Your API Token - Keep Secure!]
Max File Size: 5GB (default, configurable via dropdown)
Retention: 90 days (default, change to 365+ for academic records)
Upload Rate Limit: 10 uploads per hour per user
Playback Rate Limit: 100 views per hour per user
```

**Note:** All settings are configurable via the plugin settings page and are read dynamically by the plugin.

### Important Paths
```
Plugin: /var/www/html/mod/assign/submission/cloudflarestream/
Settings: Site admin → Plugins → Assignment → Submission plugins
Dashboard: Site admin → Plugins → Cloudflare Stream → Video Management
Logs: /var/log/apache2/error.log (or your web server log)
```

---

**End of Installation & Configuration Guide**

For technical support or questions, refer to the documentation files in the `clfdoc/` directory or contact your system administrator.
