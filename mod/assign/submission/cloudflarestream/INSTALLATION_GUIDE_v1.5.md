# Cloudflare Stream Plugin v1.5 - Installation Guide

## Pre-Installation Checklist

✅ Moodle 4.1 or higher  
✅ Cloudflare Stream account with API access  
✅ SSH/SFTP access to your Moodle server  
✅ Admin access to Moodle

---

## Installation Steps

### Step 1: Upload Plugin Files

**Option A: Via SSH/Terminal**
```bash
# Navigate to your Moodle root directory
cd /path/to/moodle

# Upload the plugin folder to:
# mod/assign/submission/cloudflarestream/

# Set correct permissions
sudo chown -R www-data:www-data mod/assign/submission/cloudflarestream
sudo chmod -R 755 mod/assign/submission/cloudflarestream
```

**Option B: Via SFTP/FTP**
1. Connect to your server via SFTP
2. Navigate to: `/path/to/moodle/mod/assign/submission/`
3. Upload the `cloudflarestream` folder
4. Ensure permissions are set correctly (755 for folders, 644 for files)

---

### Step 2: Install Plugin via Moodle Admin

1. **Login as Admin** to your Moodle site
2. **Navigate to:** Site administration → Notifications
3. Moodle will detect the new plugin
4. **Click "Upgrade Moodle database now"**
5. **Confirm** the installation
6. **Click "Continue"** when installation completes

---

### Step 3: Configure Cloudflare API Credentials

1. **Navigate to:** Site administration → Plugins → Activity modules → Assignment → Submission plugins → Cloudflare Stream video submission

2. **Enter your Cloudflare credentials:**
   - **Cloudflare API Token:** Your API token with Stream permissions
   - **Cloudflare Account ID:** Your Cloudflare account ID

3. **Configure settings:**
   - **Maximum file size:** Default is 5GB (adjust as needed)
   - **Video retention period:** Default is 90 days (or "Always" to keep forever)
   - **Allowed video formats:** Default formats are pre-configured

4. **Click "Save changes"**

---

### Step 4: Purge All Caches

**Via Admin Interface:**
1. Navigate to: Site administration → Development → Purge all caches
2. Click "Purge all caches"

**Via Command Line (Recommended):**
```bash
cd /path/to/moodle
php admin/cli/purge_caches.php
```

---

### Step 5: Enable Plugin in Assignment Settings

1. **Create or edit an assignment**
2. **Expand "Submission types"** section
3. **Enable "Cloudflare Stream video submission"**
4. **Save the assignment**

---

## Verification Steps

### Test 1: Small Video Upload (< 200MB)
1. As a student, go to the assignment
2. Click "Add submission"
3. Upload a small video file
4. Verify upload completes successfully
5. Click "Save changes"
6. Verify video appears in submission

### Test 2: Large Video Upload (> 200MB)
1. Upload a large video file (tests TUS resumable upload)
2. Monitor console (F12) for chunk upload progress
3. Verify upload completes with retry logic if needed
4. Click "Save changes"

### Test 3: Video Replacement
1. Edit an existing submission with a video
2. Upload a new video
3. **DO NOT click "Save changes" yet**
4. Verify old video is still visible
5. Click "Save changes"
6. Verify old video is replaced with new video

### Test 4: Grading Interface
1. As a teacher, go to assignment
2. Click "View all submissions"
3. Click "Grade" on a submission with video
4. Verify video player displays correctly

---

## Post-Installation Configuration (Optional)

### Configure Scheduled Cleanup Task

The plugin includes an automatic cleanup task for old videos.

1. **Navigate to:** Site administration → Server → Scheduled tasks
2. **Find:** "Clean up old Cloudflare Stream videos"
3. **Configure schedule** (default runs daily at 2 AM)
4. **Save changes**

### Configure Rate Limiting (Optional)

1. **Navigate to:** Plugin settings page
2. **Set upload rate limit:** Default is 10 uploads per user per hour
3. **Set playback rate limit:** Default is 100 playback requests per user per hour

---

## Troubleshooting

### Issue: Plugin not appearing in notifications
**Solution:** 
```bash
# Check file permissions
ls -la mod/assign/submission/cloudflarestream/version.php

# Should show: -rw-r--r-- (644)
# If not, fix permissions:
chmod 644 mod/assign/submission/cloudflarestream/version.php
```

### Issue: Upload fails with 500 error
**Solution:**
1. Check Apache/PHP error logs: `sudo tail -f /var/log/apache2/error.log`
2. Verify Cloudflare API credentials are correct
3. Check PHP max upload size: `php -i | grep upload_max_filesize`
4. Increase if needed in php.ini

### Issue: Videos not playing
**Solution:**
1. Purge all caches
2. Check browser console (F12) for JavaScript errors
3. Verify video status is "Ready" in database
4. Check Cloudflare dashboard for video processing status

### Issue: Old videos not being deleted
**Solution:**
1. Check scheduled task is running
2. Verify retention period setting
3. Run cleanup manually: Navigate to plugin folder and run cleanup script

---

## Important Notes

### Version 1.5 Features

✅ **Direct Upload** for files < 200MB  
✅ **TUS Resumable Upload** for files ≥ 200MB  
✅ **Automatic Retry Logic** (3 attempts with exponential backoff)  
✅ **Video Replacement** (old video preserved until "Save changes")  
✅ **Automatic Cleanup** of failed uploads  
✅ **Full-Width UI** with improved layout  
✅ **Rate Limiting** to prevent abuse  
✅ **GDPR Compliant** with privacy provider  

### Security Recommendations

1. ✅ Use HTTPS for your Moodle site
2. ✅ Restrict API token permissions to Stream only
3. ✅ Enable rate limiting in plugin settings
4. ✅ Regularly review upload logs
5. ✅ Set appropriate retention periods

### Performance Tips

1. ✅ Enable Moodle caching (Redis/Memcached recommended)
2. ✅ Use CDN for static assets
3. ✅ Monitor Cloudflare Stream usage and costs
4. ✅ Set reasonable file size limits based on your needs

---

## Support & Documentation

- **Plugin Documentation:** See README.md in plugin folder
- **Testing Scenarios:** See TESTING_SCENARIOS.md
- **Deployment Checklist:** See DEPLOYMENT_CHECKLIST.md
- **Cloudflare Stream Docs:** https://developers.cloudflare.com/stream/

---

## Upgrade from Previous Version

If upgrading from version 1.2 or earlier:

1. **Backup your database** before upgrading
2. **Upload new plugin files** (overwrite existing)
3. **Visit:** Site administration → Notifications
4. **Click "Upgrade Moodle database now"**
5. **Purge all caches**
6. **Test thoroughly** before production use

---

## Version History

**v1.5.0** (2025-11-17)
- Added TUS resumable upload with automatic retry logic
- Improved video replacement workflow
- Enhanced UI with full-width layout
- Fixed upload_url missing in JavaScript
- Changed display name to "File Submitted"
- Production-ready stable release

**v1.2.0** (2025-11-01)
- Initial TUS upload implementation
- Beta release

---

**Installation Complete!** 🎉

Your Cloudflare Stream plugin v1.5 is now ready to use.
