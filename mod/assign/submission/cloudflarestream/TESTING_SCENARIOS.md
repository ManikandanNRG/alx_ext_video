# Cloudflare Stream Plugin - Complete Testing Scenarios

## Pre-Testing Setup

1. **Purge Moodle Caches:**
   ```bash
   php admin/cli/purge_caches.php
   ```

2. **Prepare Test Videos:**
   - Small video: < 200MB (tests direct upload)
   - Large video: > 200MB (tests TUS upload)
   - Very large video: > 1GB (tests TUS with retry logic)

3. **Open Browser Console (F12)** to monitor upload progress and errors

---

## Test Scenario 1: New Submission - Small Video (Direct Upload)

**Purpose:** Test basic upload functionality with direct upload method

### Steps:
1. As a **student**, go to an assignment
2. Click "Add submission"
3. Upload a **small video** (< 200MB)
4. Wait for upload to complete
5. Verify "Video uploaded successfully!" message appears
6. Click "Save changes"
7. Verify submission is saved

### Expected Results:
- ✅ Upload progress bar shows percentage
- ✅ Console shows: "Using direct upload for X MB file"
- ✅ Success message appears
- ✅ Save button is enabled after upload
- ✅ Video appears in submission view
- ✅ Video status shows "Ready"

### Database Check:
```sql
SELECT id, assignment, submission, video_uid, upload_status 
FROM mdl_assignsubmission_cfstream 
WHERE submission = [submission_id];
```
Expected: 1 record with `submission=[submission_id]`, `upload_status='ready'`

---

## Test Scenario 2: New Submission - Large Video (TUS Upload)

**Purpose:** Test TUS resumable upload for large files

### Steps:
1. As a **student**, go to an assignment
2. Click "Add submission"
3. Upload a **large video** (> 200MB)
4. Monitor console for chunk upload progress
5. Wait for upload to complete
6. Click "Save changes"

### Expected Results:
- ✅ Console shows: "Using TUS upload for X GB file"
- ✅ Progress bar updates smoothly (0% → 100%)
- ✅ Console shows chunk uploads: "TUS Chunk: offset=X"
- ✅ If 504 error occurs, automatic retry happens (check console)
- ✅ Success message appears after completion
- ✅ Video status shows "Ready"

### Console Messages to Look For:
```
Using TUS upload for 1.5 GB file
TUS Chunk: offset=0, data_length=52428800
TUS Chunk: offset=52428800, data_length=52428800
...
[If retry needed]: Chunk upload failed (attempt 1/3): TUS chunk upload failed: 500
[If retry needed]: Retrying in 2 seconds...
```

---

## Test Scenario 3: Video Replacement - Old Video Preserved Until Save

**Purpose:** Test that old video is NOT deleted until user clicks "Save changes"

### Steps:
1. As a **student**, go to existing submission with video
2. Note the current video UID from database
3. Click "Edit submission"
4. Upload a **new video** (any size)
5. **DO NOT click "Save changes" yet**
6. Check database for temporary record

### Expected Results:
- ✅ New video uploads successfully
- ✅ Old video still visible in form
- ✅ Database has TWO records:
  - Old: `submission=[submission_id]`, `video_uid=[old_uid]`
  - New: `submission=0`, `video_uid=[new_uid]` (temporary)

### Database Check:
```sql
SELECT id, assignment, submission, video_uid, upload_status 
FROM mdl_assignsubmission_cfstream 
WHERE assignment = [assignment_id]
ORDER BY id DESC;
```

### Continue Test:
7. Click "Save changes"
8. Check database again

### Expected After Save:
- ✅ Old video record DELETED
- ✅ New video record updated: `submission=[submission_id]`
- ✅ Only 1 record remains (the new video)

---

## Test Scenario 4: Video Replacement - Cancel Without Saving

**Purpose:** Test that new video is cleaned up if user cancels

### Steps:
1. As a **student**, go to existing submission with video
2. Note the current video UID
3. Click "Edit submission"
4. Upload a **new video**
5. Wait for upload to complete
6. **Click "Cancel" button** (or browser back button)

### Expected Results:
- ✅ Cleanup runs automatically (check console)
- ✅ Console shows: "Cleaning up failed upload: [new_uid]"
- ✅ Old video remains in database
- ✅ Temporary record (submission=0) is deleted
- ✅ Old video still plays in submission view

### Database Check:
```sql
SELECT id, assignment, submission, video_uid, upload_status 
FROM mdl_assignsubmission_cfstream 
WHERE assignment = [assignment_id];
```
Expected: Only old video record remains

---

## Test Scenario 5: Upload Failure - Automatic Cleanup

**Purpose:** Test cleanup when upload fails

### Steps:
1. As a **student**, go to an assignment
2. Click "Add submission"
3. Start uploading a video
4. **Simulate failure** by:
   - Closing browser tab mid-upload, OR
   - Disconnecting network, OR
   - Waiting for a natural upload error

### Expected Results:
- ✅ Error message appears
- ✅ Console shows: "Upload failed, cleaning up video: [uid]"
- ✅ Console shows: "Successfully cleaned up failed upload"
- ✅ No orphaned records in database
- ✅ No "Pending Upload" videos in Cloudflare dashboard

---

## Test Scenario 6: Grading Interface - Video Player Display

**Purpose:** Test video player in grading interface

### Steps:
1. As a **teacher**, go to assignment
2. Click "View all submissions"
3. Click "Grade" on a submission with video
4. Verify video player appears

### Expected Results:
- ✅ Video player loads automatically
- ✅ Player is full-width (no container box)
- ✅ Video plays when clicked
- ✅ Video metadata shows below player (duration, file size)
- ✅ No "Watch Video" link (player is embedded)

---

## Test Scenario 7: Grading Table - Video Status Display

**Purpose:** Test video status in grading table

### Steps:
1. As a **teacher**, go to assignment
2. Click "View all submissions"
3. Look at the "Cloudflare Stream" column

### Expected Results:
- ✅ Shows "Ready (X MB)" for completed uploads
- ✅ Shows "Uploading..." for pending uploads
- ✅ Shows "Error" for failed uploads
- ✅ Shows "-" for no submission

---

## Test Scenario 8: File Validation

**Purpose:** Test file size and format validation

### Test 8a: File Too Large
1. Try to upload a video larger than configured max size
2. Expected: Error message "File size exceeds maximum allowed size"

### Test 8b: Invalid Format
1. Try to upload a non-video file (e.g., .txt, .pdf)
2. Expected: Error message "Unsupported file type"

### Test 8c: Valid Formats
Test these formats (should all work):
- ✅ .mp4
- ✅ .mov
- ✅ .avi
- ✅ .webm
- ✅ .mkv

---

## Test Scenario 9: Retry Logic for TUS Upload

**Purpose:** Test automatic retry on Cloudflare timeout

### Steps:
1. Upload a **very large video** (> 1GB)
2. Monitor console for retry messages
3. If Cloudflare returns 504, verify retry happens

### Expected Results:
- ✅ Console shows: "Chunk upload failed (attempt 1/3)"
- ✅ Console shows: "Retrying in 2 seconds..."
- ✅ Upload continues after retry
- ✅ Upload completes successfully after retries
- ✅ Maximum 3 retry attempts per chunk

---

## Test Scenario 10: Cleanup Task (Scheduled)

**Purpose:** Test scheduled cleanup of old videos

### Steps:
1. Create submissions with videos
2. Wait for retention period to pass (or modify retention setting)
3. Run cleanup task manually:
   ```bash
   php admin/cli/scheduled_task.php --execute=\\assignsubmission_cloudflarestream\\task\\cleanup_videos
   ```

### Expected Results:
- ✅ Old videos deleted from Cloudflare
- ✅ Database records marked as deleted
- ✅ Recent videos NOT deleted
- ✅ Task log shows deleted video count

---

## Test Scenario 11: Multiple Concurrent Uploads

**Purpose:** Test system stability with multiple users

### Steps:
1. Have 3-5 students upload videos simultaneously
2. Monitor server logs and database

### Expected Results:
- ✅ All uploads complete successfully
- ✅ No database conflicts
- ✅ No orphaned records
- ✅ Each video gets unique UID

---

## Test Scenario 12: Browser Compatibility

**Purpose:** Test across different browsers

### Test in Each Browser:
- Chrome/Edge
- Firefox
- Safari (if available)

### For Each Browser:
1. Upload small video
2. Upload large video (TUS)
3. Replace video
4. Cancel upload

### Expected Results:
- ✅ All features work in all browsers
- ✅ Progress bars display correctly
- ✅ Video player works
- ✅ Cleanup works on page navigation

---

## Quick Verification Checklist

After completing tests, verify:

- [ ] No orphaned videos in Cloudflare dashboard
- [ ] No temporary records (submission=0) in database
- [ ] All uploaded videos are playable
- [ ] No JavaScript errors in console
- [ ] No PHP errors in server logs
- [ ] Video metadata (size, duration) is accurate
- [ ] Grading interface shows videos correctly
- [ ] Students can view their own submissions

---

## Database Queries for Verification

### Check for temporary records (should be 0):
```sql
SELECT COUNT(*) FROM mdl_assignsubmission_cfstream WHERE submission = 0;
```

### Check for orphaned records (videos without submission):
```sql
SELECT * FROM mdl_assignsubmission_cfstream 
WHERE submission NOT IN (SELECT id FROM mdl_assign_submission);
```

### Check video status distribution:
```sql
SELECT upload_status, COUNT(*) 
FROM mdl_assignsubmission_cfstream 
GROUP BY upload_status;
```

### Check recent uploads:
```sql
SELECT id, assignment, submission, video_uid, upload_status, 
       FROM_UNIXTIME(upload_timestamp) as uploaded_at
FROM mdl_assignsubmission_cfstream 
ORDER BY upload_timestamp DESC 
LIMIT 10;
```

---

## Troubleshooting Common Issues

### Issue: Upload stuck at 0%
- Check browser console for errors
- Verify Cloudflare API credentials
- Check network connectivity

### Issue: 500 error during upload
- Check PHP error logs
- Verify file size limits (PHP, Apache, Cloudflare)
- Check database connection

### Issue: Video not playing
- Verify video is "ready" status in Cloudflare
- Check browser console for player errors
- Verify video UID is correct

### Issue: Old video not deleted
- Check if "Save changes" was clicked
- Verify cleanup task is running
- Check server logs for deletion errors

---

## Performance Benchmarks

Expected upload times (approximate):
- 100MB video: 1-2 minutes
- 500MB video: 3-5 minutes
- 1GB video: 6-10 minutes
- 2GB video: 12-20 minutes

*Times vary based on network speed and Cloudflare processing*

---

## Success Criteria

All tests pass if:
1. ✅ Videos upload successfully (both direct and TUS)
2. ✅ Old videos only deleted on "Save changes"
3. ✅ Failed uploads cleaned up automatically
4. ✅ No orphaned records in database
5. ✅ Grading interface displays videos correctly
6. ✅ Retry logic handles Cloudflare timeouts
7. ✅ All browser compatibility tests pass
8. ✅ No JavaScript or PHP errors

---

## Final Deployment Checklist

Before going to production:
- [ ] All test scenarios passed
- [ ] Database has no orphaned records
- [ ] Cloudflare dashboard shows no pending uploads
- [ ] Server logs show no errors
- [ ] Backup database before deployment
- [ ] Document any configuration changes
- [ ] Train teachers on grading interface
- [ ] Provide student upload guidelines

---

**Plugin Version:** 1.2  
**Last Updated:** 2025-11-15  
**Test Environment:** Moodle 4.x with Cloudflare Stream
