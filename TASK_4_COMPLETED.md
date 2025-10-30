# Task 4: Fix Stuck Videos on Page Refresh - COMPLETED ✅

**Date:** October 30, 2025  
**Status:** ✅ COMPLETED  
**Time Taken:** 30 minutes  
**Risk Level:** Low  
**Priority:** 🔴 CRITICAL BUG FIX

---

## What Was Changed:

### File: `lib.php` - `view_summary()` method

**Location:** After line 568 (after getting video record, before grading interface check)

**Added:** Automatic status check code block (47 lines)

---

## The Problem (Before):

### Current Broken Flow:
```
1. User uploads video
2. Upload completes, but video still processing in Cloudflare
3. After 60 seconds of checking, status saved as "uploading"
4. User refreshes page
5. ❌ Page only reads from database
6. ❌ Status stays "uploading" forever
7. ❌ Video never becomes playable
8. ❌ User stuck with "Uploading" message
```

### Real Example:
- Student uploads 500 MB video
- Takes 2 minutes to process in Cloudflare
- Database shows status="uploading"
- Student refreshes page → Still shows "Uploading"
- Teacher views submission → Still shows "Uploading"
- **Video is actually ready in Cloudflare, but Moodle doesn't know!**

---

## The Solution (After):

### New Fixed Flow:
```
1. User uploads video
2. Upload completes, status saved as "uploading"
3. User refreshes page (or teacher views submission)
4. ✅ Code checks: Is status "uploading" or "pending"?
5. ✅ Code checks: Has 60 seconds passed since upload?
6. ✅ If yes → Call Cloudflare API to get current status
7. ✅ If video is ready → Update database to "ready"
8. ✅ Page shows video player
9. ✅ Video is now playable!
```

---

## Code Logic:

### What the Code Does:

```php
// Step 1: Check if video needs status update
if (status is "uploading" OR "pending") AND video_uid exists {
    
    // Step 2: Check if enough time has passed (avoid too frequent API calls)
    if (more than 60 seconds since upload) {
        
        // Step 3: Get Cloudflare API credentials
        if (API credentials are configured) {
            
            try {
                // Step 4: Call Cloudflare API
                $details = $client->get_video_details($video_uid);
                
                // Step 5: Check if video is ready
                if ($details->readyToStream === true) {
                    // Step 6: Update database
                    - Set status = "ready"
                    - Update duration (if available)
                    - Update file_size (if available)
                    - Save to database
                    - Log the update
                }
                
            } catch (VideoNotFoundException) {
                // Video was deleted from Cloudflare
                - Mark as "deleted" in database
                - Log the deletion
                
            } catch (AnyOtherException) {
                // Silently fail, will try again next time
                - Log the error
                - Don't break the page
            }
        }
    }
}
```

---

## Safety Features:

### 1. Time Check (60 seconds)
- **Why:** Prevents too many API calls to Cloudflare
- **How:** Only checks if 60+ seconds have passed since upload
- **Benefit:** Avoids rate limiting, reduces API costs

### 2. Only Checks Non-Ready Videos
- **Why:** No need to check videos that are already "ready"
- **How:** Only runs for status="uploading" or "pending"
- **Benefit:** Efficient, doesn't waste API calls

### 3. Graceful Error Handling
- **Why:** API calls can fail (network issues, etc.)
- **How:** Catches all exceptions, logs them, continues
- **Benefit:** Page never breaks, will try again next time

### 4. Video UID Check
- **Why:** Can't check status without video UID
- **How:** Only runs if video_uid is not empty
- **Benefit:** Prevents errors from incomplete uploads

### 5. Deleted Video Detection
- **Why:** Videos can be manually deleted from Cloudflare dashboard
- **How:** Catches "video not found" exception
- **Benefit:** Marks video as "deleted" instead of stuck as "uploading"

---

## When Status Check Runs:

### Triggers:
1. ✅ **Student views their own submission**
2. ✅ **Teacher views submission in grading interface**
3. ✅ **Anyone views the submission page**
4. ✅ **Page refresh after 60 seconds**

### Does NOT Run:
- ❌ During initial upload (handled by uploader.js)
- ❌ Within first 60 seconds of upload (too soon)
- ❌ For videos already marked as "ready"
- ❌ For videos marked as "deleted" or "error"

---

## Expected Results:

### Scenario 1: Video Ready After 2 Minutes
```
Time 0:00 - Upload completes, status="uploading"
Time 0:60 - Polling ends, still "uploading", saved to DB
Time 2:00 - Video ready in Cloudflare
Time 2:30 - User refreshes page
         → Code checks Cloudflare
         → Finds video is ready
         → Updates DB to "ready"
         → Shows video player ✅
```

### Scenario 2: Video Ready After 5 Minutes
```
Time 0:00 - Upload completes, status="uploading"
Time 0:60 - Polling ends, still "uploading", saved to DB
Time 5:00 - Video ready in Cloudflare
Time 5:30 - Teacher views submission
         → Code checks Cloudflare
         → Finds video is ready
         → Updates DB to "ready"
         → Shows video player ✅
```

### Scenario 3: Video Deleted from Cloudflare
```
Time 0:00 - Upload completes, status="uploading"
Time 0:60 - Polling ends, still "uploading", saved to DB
Time 1:00 - Admin deletes video from Cloudflare dashboard
Time 2:00 - User refreshes page
         → Code checks Cloudflare
         → Video not found
         → Updates DB to "deleted"
         → Shows "Deleted" status ✅
```

---

## Benefits:

1. ✅ **Fixes stuck videos** - No more permanently "uploading" videos
2. ✅ **Automatic updates** - No manual intervention needed
3. ✅ **Works for everyone** - Students, teachers, anyone viewing
4. ✅ **Efficient** - Only checks when needed (60+ seconds, non-ready videos)
5. ✅ **Safe** - Graceful error handling, never breaks the page
6. ✅ **Detects deletions** - Marks manually deleted videos correctly
7. ✅ **Updates metadata** - Gets duration and file size from Cloudflare

---

## Testing Instructions:

### Test 1: Normal Upload (Video Ready Quickly)
1. Upload a small video (< 100 MB)
2. Wait for upload to complete
3. Should show "Ready" immediately (within 60 seconds)
4. ✅ **Expected:** Video plays normally

### Test 2: Slow Processing (Video Ready After 60 Seconds)
1. Upload a medium video (200-500 MB)
2. Wait for upload to complete
3. If shows "Uploading" after 60 seconds:
   - Wait 1-2 minutes
   - Refresh the page
4. ✅ **Expected:** Status updates to "Ready", video plays

### Test 3: Very Slow Processing (Video Ready After 5 Minutes)
1. Upload a large video (> 1 GB)
2. Upload completes, shows "Uploading"
3. Wait 5 minutes
4. Refresh the page
5. ✅ **Expected:** Status updates to "Ready", video plays

### Test 4: Teacher Viewing Stuck Video
1. Student uploads video, shows "Uploading"
2. Student closes browser
3. 2 minutes later, teacher views submission
4. ✅ **Expected:** Status automatically updates to "Ready"

### Test 5: Deleted Video Detection
1. Upload a video
2. Manually delete it from Cloudflare dashboard
3. Refresh the Moodle page
4. ✅ **Expected:** Status updates to "Deleted"

### Test 6: Multiple Refreshes (No Excessive API Calls)
1. Upload video, shows "Uploading"
2. Refresh page 5 times within 60 seconds
3. ✅ **Expected:** No API calls made (60-second check prevents this)
4. Wait 60 seconds, refresh again
5. ✅ **Expected:** API call made, status checked

---

## No Breaking Changes:

- ✅ Only adds new functionality
- ✅ Doesn't change existing upload flow
- ✅ Doesn't change database structure
- ✅ Doesn't change API calls during upload
- ✅ Backward compatible with existing videos
- ✅ Graceful error handling (never breaks page)

---

## Files Modified:

1. ✅ `lib.php` - Added status check in `view_summary()` method

**Only 1 file changed!**

---

## Upload Instructions:

Upload this file to your EC2 server:
- `mod/assign/submission/cloudflarestream/lib.php`

**After uploading:**
- Clear Moodle cache (Site administration → Development → Purge all caches)
- Or run: `php admin/cli/purge_caches.php`

**No database changes needed!**

---

## Logging for Debugging:

The code logs these events to help with debugging:

```php
// Success:
error_log("Cloudflare video {$video_uid} status updated to ready on page view");

// Video not found:
error_log("Cloudflare video {$video_uid} not found, marked as deleted");

// Error:
error_log("Failed to check Cloudflare status for video {$video_uid}: {$error}");
```

Check your server logs to see these messages.

---

## Next Recommended Tasks:

Now that refresh works, these tasks make sense:

1. **Task 6** (15 min) - Add message telling users to refresh
2. **Task 2** (25 min) - Improve status messages
3. **Task 7 Phase 1** (1 hour) - Cleanup failed uploads

---

**Task 4 Status: ✅ COMPLETED**  
**Ready for Production: ✅ YES**  
**Requires Testing: ✅ YES (critical fix)**  
**User Impact: ✅ VERY POSITIVE (fixes major bug)**

---

## Summary:

This fix solves the critical problem of videos being stuck in "uploading" status forever. Now when users refresh the page (or teachers view submissions), the code automatically checks Cloudflare and updates the status. This makes the plugin much more reliable and user-friendly!
