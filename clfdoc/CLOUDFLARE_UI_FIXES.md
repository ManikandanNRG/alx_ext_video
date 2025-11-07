# Cloudflare Stream Plugin - UI Fixes Tracker

## Overview
This document tracks all UI improvements for the Cloudflare Stream submission plugin.

---

## ✅ Issue 1: Remove "Video" Word from Upload Interface

**Status:** FIXED

**Problem:**
Upload interface showed "Upload Video file" and "Select video" which was too specific. Should be generic "Upload file" and "Select file".

**Solution:**
Changed language strings in `lang/en/assignsubmission_cloudflarestream.php`:
- `uploadvideofile` → "Upload file" (was "Upload video file")
- `selectvideo` → "Select file" (was "Select video")
- `dragdrop` → "Drag and drop file here or click to select" (was "...video file...")

**Files Changed:**
- `mod/assign/submission/cloudflarestream/lang/en/assignsubmission_cloudflarestream.php`

**Deployment:**
```bash
scp mod/assign/submission/cloudflarestream/lang/en/assignsubmission_cloudflarestream.php ubuntu@dev.aktrea.net:/tmp/
sudo mv /tmp/assignsubmission_cloudflarestream.php /var/www/html/mod/assign/submission/cloudflarestream/lang/en/
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/lang/en/assignsubmission_cloudflarestream.php
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

---

## ✅ Issue 2: Remove Confusing Attempt Counter

**Status:** FIXED

**Problem:**
After upload, progress showed "Processing video... (1/5)", "(2/5)", etc. Users thought 5 videos were uploading when it was just retry attempts for ONE video.

**Solution:**
Changed progress message from showing attempt numbers to animated dots:
- Before: "Processing video... (1/5)", "Processing video... (2/5)"
- After: "Processing video.", "Processing video..", "Processing video..."

**Code Change:**
```javascript
// Before
this.updateProgress(100, `Processing video... (${attempt}/${maxAttempts})`);

// After
const dots = '.'.repeat((attempt % 3) + 1);
this.updateProgress(100, `Processing video${dots}`);
```

**Files Changed:**
- `mod/assign/submission/cloudflarestream/amd/src/uploader.js` (line 459)
- `mod/assign/submission/cloudflarestream/amd/build/uploader.min.js`

**Deployment:**
```bash
scp mod/assign/submission/cloudflarestream/amd/src/uploader.js ubuntu@dev.aktrea.net:/tmp/
scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js ubuntu@dev.aktrea.net:/tmp/
sudo mv /tmp/uploader.js /var/www/html/mod/assign/submission/cloudflarestream/amd/src/
sudo mv /tmp/uploader.min.js /var/www/html/mod/assign/submission/cloudflarestream/amd/build/
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/src/uploader.js
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

---

## ⏸️ Issue 3: Cancel Button Doesn't Delete Video

**Status:** ON HOLD (Pending Manager Discussion)

**Problem:**
When user uploads video and clicks "Cancel" instead of "Save changes", the video remains in Cloudflare and database. This creates orphaned videos.

**Current Flow:**
1. User uploads video → Immediately goes to Cloudflare (permanent)
2. User clicks "Save" → Just updates database
3. User clicks "Cancel" → Video STILL in Cloudflare (orphaned!)

**Recommended Solution: Draft Flag Pattern (Moodle Way)**

Based on analysis of Moodle's default file submission plugin, the proper solution is to use a draft/temporary state:

1. **Upload** → Mark video as `is_draft = 1` in database
2. **Save** → Set `is_draft = 0` (permanent)
3. **Cancel** → Draft videos cleaned up by scheduled task

**Implementation Required:**
- Add `is_draft` column to database
- Add `draft_created` timestamp column
- Mark videos as draft on upload
- Mark as permanent in `save()` method
- Create scheduled task to cleanup old drafts (24 hours)
- Update `view()` to only show non-draft videos

**Files to Modify:**
- `db/install.xml` - Add columns
- `db/upgrade.php` - Migration script
- `lib.php` - Update `save()` method
- `ajax/confirm_upload.php` - Mark as draft
- `classes/task/cleanup_drafts.php` - New scheduled task
- `db/tasks.php` - Register task

**Reference:**
See `clfdoc/MOODLE_FILE_SUBMISSION_ANALYSIS.md` for detailed analysis of how Moodle's file submission handles this.

**Decision Needed:**
Requires manager approval before implementation due to database schema changes.

---

## ✅ Issue 4: Duplicate Enable Dropdown

**Status:** FIXED (Hidden)

**Problem:**
Assignment settings page showed TWO controls for enabling Cloudflare Stream:
1. Toggle switch (top) - Created by Moodle core
2. Dropdown Yes/No (bottom) - Created by our plugin

Both controls write to the SAME database location, causing confusion.

**Solution:**
Hidden (commented out) the duplicate dropdown in `get_settings()` method. Code is preserved for safety and can be easily restored.

**Code Change:**
```php
public function get_settings(MoodleQuickForm $mform) {
    // HIDDEN: Duplicate "enabled" dropdown removed to avoid confusion
    // The toggle switch in "Submission types" section is sufficient
    // To restore: uncomment the code below
    
    /* [original code commented out] */
    
    // Future: Add useful per-assignment settings here
}
```

**Why This Works:**
- Toggle switch writes to: `assign_plugin_config.enabled`
- Dropdown wrote to: `assign_plugin_config.enabled` (same location!)
- `is_enabled()` reads from: `assign_plugin_config.enabled`
- Result: No functionality lost, cleaner UI

**Files Changed:**
- `mod/assign/submission/cloudflarestream/lib.php`

**Deployment:**
```bash
scp mod/assign/submission/cloudflarestream/lib.php ubuntu@dev.aktrea.net:/tmp/
sudo mv /tmp/lib.php /var/www/html/mod/assign/submission/cloudflarestream/
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/lib.php
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

**Rollback:**
To restore dropdown, edit `lib.php` and remove the `/*` and `*/` comment markers.

---

## 📋 Future UI Improvements (Not Started)

### Potential Enhancements:
1. Better error messages with actionable suggestions
2. Upload progress with file size and speed
3. Video preview before submission
4. Thumbnail generation and display
5. Video quality selector
6. Playback speed controls
7. Keyboard shortcuts for player
8. Mobile-optimized upload interface
9. Drag-and-drop improvements
10. Better loading states and animations

---

## Deployment Summary

### All Fixed Issues (1, 2, 4):
```bash
# Copy all changed files
scp mod/assign/submission/cloudflarestream/lang/en/assignsubmission_cloudflarestream.php ubuntu@dev.aktrea.net:/tmp/
scp mod/assign/submission/cloudflarestream/amd/src/uploader.js ubuntu@dev.aktrea.net:/tmp/
scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js ubuntu@dev.aktrea.net:/tmp/
scp mod/assign/submission/cloudflarestream/lib.php ubuntu@dev.aktrea.net:/tmp/

# On server - deploy all files
sudo mv /tmp/assignsubmission_cloudflarestream.php /var/www/html/mod/assign/submission/cloudflarestream/lang/en/
sudo mv /tmp/uploader.js /var/www/html/mod/assign/submission/cloudflarestream/amd/src/
sudo mv /tmp/uploader.min.js /var/www/html/mod/assign/submission/cloudflarestream/amd/build/
sudo mv /tmp/lib.php /var/www/html/mod/assign/submission/cloudflarestream/

# Set permissions
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/lang/en/assignsubmission_cloudflarestream.php
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/src/uploader.js
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/lib.php

# Purge caches
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

---

## Testing Checklist

After deployment, verify:

- [ ] Upload interface shows "Upload file" (not "Upload video file")
- [ ] Button shows "Select file" (not "Select video")
- [ ] Drag-drop text shows "file" (not "video file")
- [ ] Processing shows animated dots (not "1/5", "2/5")
- [ ] Assignment settings show only toggle switch (no dropdown)
- [ ] Toggle ON → Plugin appears in submission form
- [ ] Toggle OFF → Plugin hidden
- [ ] Student can upload video successfully
- [ ] Teacher can view and grade video
- [ ] All existing assignments work normally

---

## Notes

- Issue 3 requires database schema changes and manager approval
- All fixes are backward compatible
- Code is preserved (commented) for easy rollback
- No functionality lost in any fix
- Follows Moodle conventions and best practices
