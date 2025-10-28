# Video Status Update - Complete Solution

## Problem
Videos were stuck in "uploading" status even though they were "ready" on Cloudflare.

## Root Cause
When a video is uploaded to Cloudflare, it goes through processing stages:
1. **Upload complete** → Cloudflare receives the file
2. **Queued** → Waiting for processing  
3. **In Progress** → Being processed
4. **Ready** → Available for playback

The `confirm_upload.php` is called immediately after upload, but Cloudflare might still be processing (status="queued"), so it correctly sets DB status to "uploading".

## Solution Implemented

### 1. ✅ Scheduled Task (Automatic Updates)
**File:** `classes/task/update_video_status.php`

**What it does:**
- Runs every 5 minutes automatically
- Checks all videos in "uploading" status
- Fetches current status from Cloudflare API
- Updates database with correct status
- Sets videos to private when they become "ready"

**Configuration:**
- Added to `db/tasks.php`
- Runs every 5 minutes: `'minute' => '*/5'`
- Non-blocking task

### 2. ✅ Manual Fix Script
**File:** `fix_video_status.php`

**What it does:**
- Allows admin to manually update video status
- Can fix one video or all stuck videos
- Useful for immediate fixes without waiting for scheduled task

**Usage:**
```
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/fix_video_status.php
```

### 3. ✅ Enhanced Player Debugging
**File:** `test_complete_workflow.php`

**What changed:**
- Added detailed console logging
- Better error reporting
- Proper Stream SDK initialization
- Token set correctly on player

## How It Works Now

### For New Uploads:
1. User uploads video → Status: "pending"
2. Upload completes → `confirm_upload.php` called
3. Cloudflare status checked:
   - If "ready" → DB status: "ready" ✅
   - If "queued/inprogress" → DB status: "uploading" ⏳
4. **Scheduled task runs every 5 minutes**
5. Task checks "uploading" videos
6. Updates status when Cloudflare shows "ready"
7. Sets video to private automatically

### Timeline:
- **Upload:** Instant
- **Cloudflare Processing:** 10-60 seconds (depends on file size)
- **Status Update:** Within 5 minutes (next scheduled task run)

## Files Modified/Created

### New Files:
1. `classes/task/update_video_status.php` - Scheduled task
2. `fix_video_status.php` - Manual fix script
3. `STATUS_UPDATE_SOLUTION.md` - This document

### Modified Files:
1. `db/tasks.php` - Added new scheduled task
2. `lang/en/assignsubmission_cloudflarestream.php` - Added task string
3. `test_complete_workflow.php` - Enhanced player debugging

## Deployment Steps

1. **Copy files to EC2:**
   ```bash
   # New files
   classes/task/update_video_status.php
   fix_video_status.php
   
   # Modified files
   db/tasks.php
   lang/en/assignsubmission_cloudflarestream.php
   test_complete_workflow.php
   ```

2. **Upgrade database** (registers the new scheduled task):
   ```bash
   cd /var/www/html/moodle
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```

3. **Clear cache:**
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

4. **Fix existing stuck videos:**
   - Go to: `fix_video_status.php`
   - Click "Fix All Videos Stuck in uploading"

5. **Verify scheduled task:**
   - Go to: Site administration → Server → Scheduled tasks
   - Search for: "Update video processing status"
   - Confirm it's enabled and scheduled to run every 5 minutes

## Testing

### Test Automatic Updates:
1. Upload a new video
2. Check DB status (might be "uploading")
3. Wait 5-10 minutes
4. Check again - should be "ready"

### Test Manual Fix:
1. Go to `fix_video_status.php`
2. Click "Fix All Videos"
3. Verify videos update to "ready"

### Test Player:
1. Go to `test_complete_workflow.php?videouid=YOUR_VIDEO_UID`
2. Check all tests pass
3. Video should play in player section
4. Check browser console for detailed logs

## Confirmation

### ✅ Future Videos Will Update Automatically

**YES!** The scheduled task will:
- Run every 5 minutes
- Check all videos in "uploading" status
- Update them when Cloudflare shows "ready"
- Set them to private automatically

**No manual intervention needed** - videos will update within 5 minutes of becoming ready on Cloudflare.

## Troubleshooting

### If videos still stuck:
1. Check scheduled task is running:
   ```bash
   cd /var/www/html/moodle
   sudo -u www-data php admin/cli/scheduled_task.php --execute='\assignsubmission_cloudflarestream\task\update_video_status'
   ```

2. Check cron is running:
   ```bash
   sudo -u www-data php admin/cli/cron.php
   ```

3. Use manual fix script as backup

### If player not working:
1. Check browser console for errors
2. Verify token is generated (Test 3 in workflow test)
3. Verify video is private (Test 2 shows "requireSignedURLs")
4. Check video UID is correct

## Summary

- ✅ Automatic status updates every 5 minutes
- ✅ Manual fix script for immediate updates
- ✅ Videos set to private automatically
- ✅ Enhanced player debugging
- ✅ No manual intervention needed for future uploads
