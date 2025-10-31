# Cron Task Configuration - Production Settings ✅

**Date:** October 31, 2025  
**Status:** ✅ CONFIGURED FOR PRODUCTION

---

## Production Configuration

### Cron Schedule: Every 30 Minutes
**File:** `mod/assign/submission/cloudflarestream/db/tasks.php`

```php
'minute' => '*/30',  // Run every 30 minutes
'hour' => '*',       // Every hour
```

**Runs at:**
- 00:00, 00:30
- 01:00, 01:30
- 02:00, 02:30
- ... (every 30 minutes, 24/7)

---

### Cleanup Threshold: 30 Minutes
**File:** `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php`

```php
$waittime = 1800; // 30 minutes (production setting)
```

**Deletes videos that are:**
- Status: `pending` or `uploading`
- Age: ≥ 30 minutes old

---

## Why These Settings?

### Problem:
If 3 consecutive dummy videos accumulate in Cloudflare (stuck in pending/uploading), users cannot upload new videos.

### Solution:
1. **Cron runs every 30 minutes** → Cleans up quickly before too many dummy videos accumulate
2. **Deletes videos ≥ 30 minutes old** → Gives legitimate uploads enough time to complete, but removes stuck uploads promptly

---

## What the Cron Does

### 1. Cleanup Stuck Uploads (≥ 30 minutes)
- Finds videos in `pending` or `uploading` status older than 30 minutes
- Deletes from Cloudflare
- Deletes from database
- **Prevents dummy video accumulation**

### 2. Sync with Cloudflare
- Checks if "ready" videos still exist in Cloudflare
- Updates database if videos were manually deleted
- Keeps database in sync

### 3. Cleanup Expired Videos
- Deletes videos older than retention period (90 days)
- Manages storage costs
- Complies with data retention policies

---

## Activation

After uploading these files to your server, you need to:

1. **Upgrade Moodle database:**
   ```
   Site administration → Notifications
   ```
   This will register the new cron schedule.

2. **Verify cron task:**
   ```
   Site administration → Server → Scheduled tasks
   Search for: "cleanup_videos"
   ```
   You should see it scheduled to run every 30 minutes.

3. **Test manually (optional):**
   ```
   https://dev.aktrea.net/mod/assign/submission/cloudflarestream/run_cleanup_now.php
   ```

---

## Monitoring

**Check cron execution:**
```
Site administration → Server → Scheduled tasks → Scheduled tasks log
```

**Expected output:**
```
Cloudflare Stream cleanup: Checking for stuck uploads (pending/uploading > 30 minutes)...
Cloudflare Stream cleanup: Found X stuck uploads to clean up.
Cloudflare Stream cleanup: Deleted stuck upload [UID] from Cloudflare
Cloudflare Stream cleanup: Stuck uploads cleanup completed. X deleted, Y not found, Z failed
```

---

## Files Modified

1. ✅ `mod/assign/submission/cloudflarestream/db/tasks.php` - Cron schedule (every 30 min)
2. ✅ `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php` - Wait time (30 min)

---

**Status: ✅ READY FOR PRODUCTION**  
**Cron Frequency:** Every 30 minutes  
**Cleanup Threshold:** ≥ 30 minutes old
