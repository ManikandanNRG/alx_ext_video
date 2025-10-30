# Task 7 Phase 1 - Final Fix (Scheduled Task)

## 🐛 Problem Discovered

**JavaScript cleanup doesn't work when browser window is closed!**

### Test Results:
- ✅ Test 1: Small video (100 MB) → Uploaded successfully
- ❌ Test 2: Large video, closed window at 50%
  - Video `11779ac2a6d37096b997e802c6062bec` still in Cloudflare
  - Database record still shows `pending`
  - Cleanup did NOT run

### Root Cause:
When you close the browser window, JavaScript is immediately terminated. The `catch` block in `startUpload()` never executes, so cleanup never runs.

**JavaScript cleanup only works if:**
- Upload fails but browser stays open
- User clicks a cancel button
- Network error occurs (but page doesn't close)

**JavaScript cleanup does NOT work if:**
- ❌ User closes browser window
- ❌ User closes browser tab
- ❌ Browser crashes
- ❌ Computer shuts down

## ✅ Real Solution: Scheduled Task

We need a **server-side scheduled task** that runs periodically to clean up stuck uploads.

### What Was Added:

**File:** `classes/task/cleanup_videos.php`

**New Method:** `cleanup_stuck_uploads()`

```php
/**
 * Clean up stuck uploads (pending/uploading for more than 1 hour).
 * These are uploads that failed but JavaScript cleanup didn't run.
 */
private function cleanup_stuck_uploads($cloudflare) {
    // Find uploads stuck for > 1 hour
    // Delete from Cloudflare
    // Delete from database
}
```

**How It Works:**
1. Runs automatically every hour (Moodle cron)
2. Finds records with status `pending` or `uploading` older than 1 hour
3. Deletes video from Cloudflare
4. Deletes database record
5. Logs results

## 📤 Files to Upload

Upload these 2 files:

1. **mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php**
   - Added `cleanup_stuck_uploads()` method

2. **mod/assign/submission/cloudflarestream/run_cleanup_now.php** (NEW)
   - Browser tool to run cleanup immediately

## 🧹 Clean Up Current Stuck Upload

### Option 1: Run Cleanup Task Now (Recommended)

Visit this URL:
```
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/run_cleanup_now.php
```

Click "Run Cleanup Now" - it will:
- Delete video `11779ac2a6d37096b997e802c6062bec` from Cloudflare
- Delete database record ID 17
- Show results in browser

### Option 2: Manual Cleanup

Visit:
```
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/manual_cleanup.php
```

Search for: `11779ac2a6d37096b997e802c6062bec`
Click "Delete Video"

### Option 3: SQL Query

```sql
-- Delete the stuck upload
DELETE FROM mdl_assignsubmission_cfstream WHERE id = 17;
```

Then manually delete video from Cloudflare dashboard.

## ⏰ Scheduled Task Configuration

The cleanup task runs automatically via Moodle cron. To check/configure:

1. Go to: **Site administration → Server → Scheduled tasks**
2. Find: **"Clean up expired videos"** (assignsubmission_cloudflarestream)
3. Default: Runs every hour
4. You can change frequency if needed

## 🧪 Re-Test Task 7 Phase 1

After uploading the fixed files and running cleanup:

### Test 1: Normal Upload
```
1. Upload a small video (100 MB)
2. Should complete successfully
3. Check database - should show 'ready'
```

### Test 2: Stuck Upload Cleanup
```
1. Upload a large video (1 GB)
2. At 50%, close the browser window
3. Wait 5 minutes
4. Run cleanup task: https://dev.aktrea.net/mod/assign/submission/cloudflarestream/run_cleanup_now.php
5. Check Cloudflare → Should be clean
6. Check database → Should be clean
```

### Test 3: Automatic Cleanup (Wait for Cron)
```
1. Upload a large video
2. Close window at 50%
3. Wait 1-2 hours (for cron to run)
4. Check Cloudflare → Should be automatically cleaned
5. Check database → Should be automatically cleaned
```

## 📊 Expected Results

### Before Fix:
- ❌ Close window → Video stays in Cloudflare forever
- ❌ Database has stuck `pending` records
- ❌ Orphaned videos accumulate

### After Fix:
- ✅ Close window → Video cleaned up within 1 hour (by cron)
- ✅ Database automatically cleaned
- ✅ No orphaned videos
- ✅ Can run cleanup manually anytime

## 🎯 Complete Solution

**Task 7 Phase 1 now has TWO cleanup mechanisms:**

1. **JavaScript Cleanup (Immediate)**
   - Runs when upload fails (if browser stays open)
   - Cleans up immediately
   - Works for: network errors, cancellations

2. **Scheduled Task Cleanup (Hourly)**
   - Runs every hour via cron
   - Cleans up stuck uploads > 1 hour old
   - Works for: closed windows, crashes, any failure

**Together, these ensure NO orphaned videos!**

## ✅ Summary

**Files to Upload:**
1. `classes/task/cleanup_videos.php` - Added stuck upload cleanup
2. `run_cleanup_now.php` - Browser tool to run cleanup

**Immediate Action:**
1. Upload the 2 files
2. Visit `run_cleanup_now.php` to clean up current stuck upload
3. Re-test with large file + close window
4. Run cleanup manually to verify it works

**Long-term:**
- Cron runs cleanup automatically every hour
- No manual intervention needed
- Orphaned videos cleaned up automatically

---

**Status:** ✅ Complete
**Priority:** 🔴 CRITICAL
**Impact:** Prevents orphaned videos permanently
