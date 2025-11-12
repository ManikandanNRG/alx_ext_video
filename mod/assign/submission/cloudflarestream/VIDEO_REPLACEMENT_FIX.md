# Video Replacement Fix - Complete Solution

## 🐛 THE BUGS

### Bug #1: JavaScript Field Name (FIXED)
**File:** `amd/src/uploader.js` line 544
**Problem:** Wrong field name `cloudflarestream_video_id` instead of `cloudflarestream_video_uid`
**Impact:** Database never updated with new video UID
**Status:** ✅ FIXED

### Bug #2: Old Video Not Deleted (FIXED)
**File:** `ajax/confirm_upload.php`
**Problem:** Old video not deleted from Cloudflare when uploading replacement
**Impact:** Orphaned videos accumulate in Cloudflareconfirm_upload
**Status:** ✅ FIXED

---

## 🔍 WHY THE FIRST FIX DIDN'T WORK

### Initial Attempt (lib.php):
```php
// In save() method
if ($existing->video_uid !== $video_uid) {
    delete_video($existing->video_uid);  // ❌ Never executes!
}
```

**Why it failed:**
1. User uploads new video → `confirm_upload.php` runs
2. `confirm_upload.php` updates DB: `video_uid = NEW_UID` ❌
3. User clicks "Save" → `save()` runs
4. `save()` compares: `existing->video_uid` (NEW) vs `video_uid` (NEW)
5. They're the same! No deletion happens

---

## ✅ THE CORRECT SOLUTION

### Move Deletion to confirm_upload.php:
```php
// In confirm_upload.php - BEFORE updating database
$record = $DB->get_record('assignsubmission_cfstream', ['submission' => $submissionid]);

// Delete old video if UID is changing
if (!empty($record->video_uid) && $record->video_uid !== $videouid) {
    $client->delete_video($record->video_uid);  // ✅ Deletes old video
}

// Now update with new UID
$record->video_uid = $videouid;
$DB->update_record('assignsubmission_cfstream', $record);
```

**Why this works:**
1. User uploads new video → `confirm_upload.php` runs
2. Gets OLD video UID from database
3. Deletes OLD video from Cloudflare ✅
4. Updates DB with NEW video UID
5. Only NEW video remains in Cloudflare ✅

---

## 📊 COMPLETE FLOW

### Before Fix:
```
1. Upload video A (UID: aaa) → Saved
2. Edit submission, upload video B (UID: bbb)
3. confirm_upload.php updates DB: video_uid = bbb
4. Click "Save" → save() compares bbb vs bbb → No deletion
5. Result: Both videos in Cloudflare ❌
```

### After Fix:
```
1. Upload video A (UID: aaa) → Saved
2. Edit submission, upload video B (UID: bbb)
3. confirm_upload.php:
   a. Reads old UID from DB: aaa
   b. Deletes video aaa from Cloudflare ✅
   c. Updates DB: video_uid = bbb
4. Click "Save" → Just saves form data
5. Result: Only video B in Cloudflare ✅
```

---

## 🔧 FILES CHANGED

### 1. amd/src/uploader.js
**Change:** Fixed field name
```javascript
// Before:
$('input[name="cloudflarestream_video_id"]').val(videoId);

// After:
$('input[name="cloudflarestream_video_uid"]').val(videoId);
```

### 2. ajax/confirm_upload.php
**Change:** Added old video deletion
```php
// Get existing record
$record = $DB->get_record('assignsubmission_cfstream', ['submission' => $submissionid]);

// NEW: Delete old video if replacing
if (!empty($record->video_uid) && $record->video_uid !== $videouid) {
    try {
        $client = new cloudflare_client($apitoken, $accountid);
        $client->delete_video($record->video_uid);
        error_log("Deleted old video {$record->video_uid}");
    } catch (Exception $e) {
        error_log("Failed to delete: " . $e->getMessage());
    }
}

// Update with new video
$record->video_uid = $videouid;
$DB->update_record('assignsubmission_cfstream', $record);
```

### 3. lib.php
**Change:** Removed deletion code (moved to confirm_upload.php)
```php
// Removed the deletion logic from save() method
// because it was too late - DB already updated
```

### 4. amd/build/uploader.min.js
**Change:** Copied from src version

---

## ✅ TESTING

### Test Case 1: New Submission
1. Upload video → Should save ✅
2. Check Cloudflare → 1 video ✅

### Test Case 2: Replace Video
1. Upload video A → Saved ✅
2. Edit submission, upload video B → Saved ✅
3. Check Moodle → Shows video B ✅
4. Check Cloudflare → Only video B (A deleted) ✅

### Test Case 3: Multiple Replacements
1. Upload video A → Saved
2. Replace with video B → A deleted, B saved
3. Replace with video C → B deleted, C saved
4. Check Cloudflare → Only video C ✅

---

## 📝 LOG MESSAGES

### Successful Deletion:
```
Cloudflare confirm_upload: Detected video replacement - Old UID: xxx, New UID: yyy
Cloudflare confirm_upload: ✓ Successfully deleted old video xxx
```

### Video Already Deleted:
```
Cloudflare confirm_upload: Old video xxx already deleted (404)
```

### Deletion Failed:
```
Cloudflare confirm_upload: ✗ Failed to delete old video xxx: [error message]
```

---

## 🎯 SUMMARY

**Problem:** When editing submission and uploading new video:
- ❌ Database not updating (Bug #1)
- ❌ Old video not deleted from Cloudflare (Bug #2)

**Solution:**
- ✅ Fixed JavaScript field name → Database updates correctly
- ✅ Added deletion in confirm_upload.php → Old videos deleted
- ✅ Moved deletion to correct location → Works reliably

**Result:**
- ✅ New video shows in Moodle
- ✅ Old video deleted from Cloudflare
- ✅ No orphaned videos
- ✅ Storage costs reduced
