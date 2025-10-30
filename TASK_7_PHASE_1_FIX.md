# Task 7 Phase 1 - Critical Fix

## 🐛 Problem Found During Testing

**Test Results:**
- ✅ Test 1: 70 MB video uploaded successfully
- ❌ Test 2: 1.7 GB video at 50%, closed window
  - Video `e3bcdbc2b7d8cc345aeff504562e5817` still in Cloudflare
  - NO database entry for this video
  - Cleanup did NOT work

## 🔍 Root Cause

**The Issue:**
1. User starts upload → `get_upload_url.php` creates database record
2. Database record has `video_uid = ''` (empty)
3. Upload starts to Cloudflare with UID `e3bcdbc2b7d8cc345aeff504562e5817`
4. User closes window at 50%
5. Cleanup tries to delete by `video_uid`, but database has empty value
6. Cleanup can't find the record → Video stays in Cloudflare

**Why video_uid was empty:**
```php
$record->video_uid = ''; // Will be filled in when upload completes.
```

The UID was only stored AFTER upload completed, not when it was generated!

## ✅ Solution

### Fix 1: Store UID Immediately
**File:** `get_upload_url.php` (Line 102)

**Before:**
```php
$record->video_uid = ''; // Will be filled in when upload completes.
```

**After:**
```php
$record->video_uid = $result->uid; // Store UID immediately for cleanup
```

### Fix 2: Improved Cleanup Logic
**File:** `cleanup_failed_upload.php` (Line 70-88)

**Added fallback cleanup:**
```php
// Try to delete by submission and video_uid
$deleted_from_database = $DB->delete_records('assignsubmission_cfstream', [
    'submission' => $submissionid,
    'video_uid' => $videouid
]);

// If not found, try deleting by submission and empty video_uid (old records)
if (!$deleted_from_database && !empty($videouid)) {
    $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', [
        'submission' => $submissionid,
        'video_uid' => ''
    ]);
}
```

This handles both:
- New records (with UID stored immediately)
- Old records (with empty UID from before the fix)

## 📤 Files to Upload

Upload these 3 files to your server:

1. **mod/assign/submission/cloudflarestream/ajax/get_upload_url.php**
   - Stores video UID immediately

2. **mod/assign/submission/cloudflarestream/ajax/cleanup_failed_upload.php**
   - Improved cleanup logic with fallback

3. **mod/assign/submission/cloudflarestream/manual_cleanup.php** (NEW)
   - Manual cleanup script for orphaned videos

## 🧹 Clean Up Orphaned Video

To delete the orphaned video `e3bcdbc2b7d8cc345aeff504562e5817`:

### Option 1: Using Manual Cleanup Script (Recommended)

```bash
# SSH to your server
cd /path/to/moodle/mod/assign/submission/cloudflarestream

# Check video info (safe, doesn't delete)
php manual_cleanup.php --videouid=e3bcdbc2b7d8cc345aeff504562e5817

# Delete the video
php manual_cleanup.php --videouid=e3bcdbc2b7d8cc345aeff504562e5817 --delete
```

### Option 2: Using Cloudflare Dashboard

1. Go to Cloudflare Dashboard → Stream
2. Find video `e3bcdbc2b7d8cc345aeff504562e5817`
3. Click Delete

### Option 3: Using curl (if you have API credentials)

```bash
curl -X DELETE "https://api.cloudflare.com/client/v4/accounts/YOUR_ACCOUNT_ID/stream/e3bcdbc2b7d8cc345aeff504562e5817" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

## 🧪 Re-Test After Fix

### Test 1: Normal Upload
1. Upload the 3 fixed files
2. Clear Moodle cache
3. Upload a small video (100 MB)
4. Let it complete
5. Should work normally

### Test 2: Failed Upload Cleanup
1. Upload a large video (1 GB+)
2. At 50%, close the browser window
3. Check browser console (before closing):
   ```
   Upload failed, cleaning up video: abc123...
   Cleaning up failed upload: abc123...
   Successfully cleaned up failed upload: abc123...
   ```
4. Check Cloudflare dashboard → Should be clean (no dummy video)
5. Check database:
   ```sql
   SELECT * FROM mdl_assignsubmission_cfstream 
   WHERE upload_status = 'pending' OR upload_status = 'uploading';
   ```
   Should return NO records for this upload

### Test 3: Verify Database Has UID
1. Start uploading a video
2. Immediately check database:
   ```sql
   SELECT * FROM mdl_assignsubmission_cfstream 
   ORDER BY id DESC LIMIT 1;
   ```
3. Should see `video_uid` is NOT empty (has the Cloudflare UID)

## 📊 Database Cleanup

Clean up the 3 orphaned records you found:

```sql
-- Check what will be deleted
SELECT * FROM mdl_assignsubmission_cfstream 
WHERE (upload_status = 'pending' OR upload_status = 'uploading')
AND (video_uid = '' OR video_uid IS NULL);

-- Delete orphaned records (be careful!)
DELETE FROM mdl_assignsubmission_cfstream 
WHERE id IN (1, 12, 15);
```

**Records to delete:**
- ID 1: `video_uid = 9fd561c49594a262957f86c65245a5c6` (status: uploading)
- ID 12: `video_uid = ''` (status: pending)
- ID 15: `video_uid = ''` (status: pending)

## ✅ Expected Results After Fix

### Before Fix:
❌ Database has empty `video_uid`  
❌ Cleanup can't find record  
❌ Video stays in Cloudflare  
❌ Orphaned videos accumulate  

### After Fix:
✅ Database has `video_uid` immediately  
✅ Cleanup finds and deletes record  
✅ Video deleted from Cloudflare  
✅ No orphaned videos  

## 🎯 Success Criteria

1. ✅ Upload small file → Works normally
2. ✅ Close window during upload → Video deleted from Cloudflare
3. ✅ Check database → No orphaned records
4. ✅ Check Cloudflare → No dummy videos
5. ✅ Retry upload → Works without issues

---

**Status:** ✅ Fixed
**Files Changed:** 3 (2 fixes + 1 new cleanup script)
**Impact:** Critical - Prevents orphaned videos in Cloudflare
**Priority:** 🔴 HIGH - Upload immediately after testing
