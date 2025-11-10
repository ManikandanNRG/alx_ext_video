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

## ✅ Issue 3: Cancel Button Doesn't Delete Video After Successful Upload

**Status:** FIXED

**Problem:**
When user uploads video successfully (shows "Upload successful") and then clicks "Cancel" instead of "Save changes", the video remains orphaned in Cloudflare and database.

**Root Cause:**
The `beforeunload` cleanup handler only worked during upload (`uploadInProgress = true`). After successful upload, we set `uploadInProgress = false` and cleared `uploadData`, so Cancel button couldn't trigger cleanup.

**Solution:**
Extended tracking to cover the entire lifecycle from upload to form save:

1. **New flag:** `uploadCompleted` - tracks successfully uploaded but not saved videos
2. **Keep uploadData:** Don't clear it after upload, keep it until form is saved
3. **Form submit handler:** Clear tracking when user actually saves the form
4. **Enhanced beforeunload:** Cleanup if `uploadInProgress` OR `uploadCompleted`

**Implementation:**
```javascript
// Track upload completion
this.uploadCompleted = false;

// After successful upload - keep tracking
this.uploadInProgress = false;
this.uploadCompleted = true; // Mark as completed but not saved

// Enhanced beforeunload - cleanup in both states
if (this.uploadData && this.uploadData.uid && 
    (this.uploadInProgress || this.uploadCompleted)) {
    navigator.sendBeacon(url, formData);
}

// Clear tracking on form submit (Save button)
$form.on('submit', () => {
    this.uploadData = null;
    this.uploadCompleted = false;
});
```

**How It Works:**
1. User uploads video → `uploadCompleted = true`, `uploadData` kept
2. User clicks "Save" → Form submit clears tracking → No cleanup
3. User clicks "Cancel" → `beforeunload` triggers → Cleanup runs → Video deleted
4. Scheduled task catches any missed cleanups (backup safety net)

**Files Changed:**
- `mod/assign/submission/cloudflarestream/amd/src/uploader.js`
- `mod/assign/submission/cloudflarestream/amd/build/uploader.min.js`

**No database changes needed!** Uses existing cleanup infrastructure.

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

---

## ✅ Issue 5: Video Player Doesn't Update When Switching Users

**Status:** ✅ FIXED AND TESTED

**Problem:**
In the grading page with two-column layout (video left, grading form right), when teacher switches users:
1. Video player doesn't update to show new student's video
2. When switching to user without video, old video remains visible
3. When switching back to user with video, layout doesn't reappear
4. Grading form disappears for users without videos

**Root Cause:**
Multiple issues discovered during implementation:
1. Initial approach: Only ran on page load, didn't detect user switches
2. URL monitoring approach: Tried to inject before Moodle's AJAX finished loading
3. MutationObserver approach: Triggered infinite loops by observing its own changes
4. Video link detection: Incorrectly removed layout when video link moved inside it

**Final Solution:**
MutationObserver with debouncing, state tracking, and pause/resume mechanism.

**Implementation:**
```javascript
// Debounced observer with state tracking
observeGradingPanel: function() {
    this.panelObserver = new MutationObserver(function(mutations) {
        // Debounce: wait 500ms after last mutation
        if (self.debounceTimer) {
            clearTimeout(self.debounceTimer);
        }
        self.debounceTimer = setTimeout(function() {
            self.handleContentChange();
        }, 500);
    });
    
    this.panelObserver.observe($gradingPanel[0], {
        childList: true,
        subtree: true
    });
},

handleContentChange: function() {
    // Prevent concurrent processing
    if (this.processing) return;
    this.processing = true;
    
    var $existingLayout = $('.cloudflarestream-two-column-layout');
    var $videoLink = $('.cloudflarestream-watch-link, .cfstream-grading-link');
    var totalVideoLinks = $videoLink.length; // Count ALL links
    
    // Filter links outside existing layout
    var $newVideoLink = $videoLink.filter(function() {
        return $(this).closest('.cloudflarestream-two-column-layout').length === 0;
    });
    
    // Case 1: New video outside layout, no layout exists
    if ($newVideoLink.length > 0 && $existingLayout.length === 0) {
        this.pauseObserver();
        this.injectPlayer();
        this.resumeObserver();
    }
    // Case 2: NO videos at all, but layout exists
    else if (totalVideoLinks === 0 && $existingLayout.length > 0) {
        this.pauseObserver();
        this.restoreMoodleLayout();
        this.resumeObserver();
    }
    
    setTimeout(function() { self.processing = false; }, 1000);
}
```

**Key Features:**
1. **Debouncing (500ms)** - Waits for DOM mutations to settle
2. **Processing flag** - Prevents concurrent operations
3. **Pause/Resume observer** - Disconnects during changes to prevent self-triggering
4. **Total video count** - Checks ALL video links, not just ones outside layout
5. **State restoration** - Moves grading content back when removing layout

**How It Works:**
1. Teacher switches user → Moodle loads content via AJAX
2. Observer detects DOM changes → Debounce timer starts
3. After 500ms of no changes → `handleContentChange()` runs
4. Checks total video links and layout state
5. **User with video** → Injects two-column layout
6. **User without video** → Removes layout, restores Moodle's original design
7. Observer pauses during changes, resumes after 1 second

**All Scenarios Tested:**
- ✅ User with video → User with video (updates player)
- ✅ User with video → User without video (removes layout)
- ✅ User without video → User with video (injects layout)
- ✅ User without video → User without video (no action)
- ✅ No infinite loops or race conditions
- ✅ Grading form always visible and functional

**Files Changed:**
- `mod/assign/submission/cloudflarestream/amd/src/grading_injector.js`
- `mod/assign/submission/cloudflarestream/amd/build/grading_injector.min.js`

**Deployment:**
```bash
scp mod/assign/submission/cloudflarestream/amd/src/grading_injector.js ubuntu@dev.aktrea.net:/tmp/
scp mod/assign/submission/cloudflarestream/amd/build/grading_injector.min.js ubuntu@dev.aktrea.net:/tmp/
sudo mv /tmp/grading_injector.js /var/www/html/mod/assign/submission/cloudflarestream/amd/src/
sudo mv /tmp/grading_injector.min.js /var/www/html/mod/assign/submission/cloudflarestream/amd/build/
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/src/grading_injector.js
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/build/grading_injector.min.js
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

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
