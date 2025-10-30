# Cloudflare Stream Plugin - TODO List

## Status: Pending Review & Implementation

---

## Task 1: Add File Format & Size Information (Phase 1.1)

### Priority: HIGH
### Status: ⏳ Pending
### Estimated Time: 15 minutes

### Description:
Add clear information about accepted file formats and maximum file size on the upload page.

### Files to Modify:

#### 1. `templates/upload_form.mustache`
**Location:** After line 72 (before dropzone div)

**Add:**
```html
<div class="cloudflarestream-upload-info alert alert-info">
    <div class="row">
        <div class="col-md-6">
            <strong><i class="fa fa-file-video-o"></i> {{#str}}acceptedformats, assignsubmission_cloudflarestream{{/str}}:</strong>
            <br>
            <small>MP4, MOV, AVI, MKV, WebM, MPEG, OGG, 3GP, FLV</small>
        </div>
        <div class="col-md-6">
            <strong><i class="fa fa-database"></i> {{#str}}maxfilesize, core{{/str}}:</strong>
            <br>
            <small>{{maxfilesizeformatted}}</small>
        </div>
    </div>
</div>
```

#### 2. `lang/en/assignsubmission_cloudflarestream.php`
**Location:** End of file

**Add:**
```php
// File format information
$string['acceptedformats'] = 'Accepted formats';
$string['uploadinfo'] = 'Upload information';
```

#### 3. `styles.css`
**Location:** End of file

**Add:**
```css
/* Upload information box */
.cloudflarestream-upload-info {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #e7f3ff;
    border-left: 4px solid #0066cc;
}

.cloudflarestream-upload-info .row {
    margin: 0;
}

.cloudflarestream-upload-info strong {
    color: #0066cc;
    font-size: 14px;
}

.cloudflarestream-upload-info small {
    color: #666;
    font-size: 13px;
}
```

### Expected Result:
Users will see a clear info box showing:
- Accepted formats: MP4, MOV, AVI, MKV, WebM, MPEG, OGG, 3GP, FLV
- Maximum file size: 5 GB (or configured value)

---

## Task 2: Improve Processing Status Messages (Phase 1.2)

### Priority: HIGH
### Status: ⏳ Pending
### Estimated Time: 25 minutes

### Description:
Replace confusing "Processing 1/5" messages with clear, descriptive status messages that explain what's happening.

### Current Issue:
- Shows "Processing video... (2/5)" - users don't understand what this means
- Message appears briefly then shows success
- No explanation of timing or what's being checked

### Files to Modify:

#### 1. `amd/src/uploader.js`

**Change 1 - Line 237:**
```javascript
// CURRENT:
this.updateProgress(100, 'Finalizing upload...');

// CHANGE TO:
this.updateProgress(100, 'Upload complete! Verifying video...');
```

**Change 2 - Line 374:**
```javascript
// CURRENT:
this.updateProgress(100, `Processing video... (${attempt}/${maxAttempts})`);

// CHANGE TO:
const statusMessages = [
    'Verifying upload... (this may take a moment)',
    'Checking video status... (attempt 2 of 5)',
    'Still processing... (attempt 3 of 5)',
    'Almost ready... (attempt 4 of 5)',
    'Final check... (attempt 5 of 5)'
];
this.updateProgress(100, statusMessages[attempt] || 'Processing video...');
```

**Change 3 - Line 480:**
```javascript
// CURRENT:
this.statusMessage.html('Video uploaded successfully!')
    .addClass('alert alert-success')
    .show();

// CHANGE TO:
this.statusMessage.html(
    '<strong><i class="fa fa-check-circle"></i> Video uploaded successfully!</strong><br>' +
    '<small class="text-muted">Your video is ready and has been saved.</small>'
)
    .addClass('alert alert-success')
    .show();
```

#### 2. `lang/en/assignsubmission_cloudflarestream.php`
**Location:** End of file

**Add:**
```php
// Processing status messages
$string['verifying_upload'] = 'Verifying upload...';
$string['checking_status'] = 'Checking video status...';
$string['still_processing'] = 'Still processing...';
$string['almost_ready'] = 'Almost ready...';
$string['final_check'] = 'Final check...';
$string['upload_complete_verifying'] = 'Upload complete! Verifying video...';
$string['video_ready_saved'] = 'Your video is ready and has been saved.';
$string['processing_explanation'] = 'We check the video status multiple times to ensure it\'s ready. This usually takes 10-30 seconds.';
$string['large_file_note'] = 'Large files may take 1-2 minutes to process. You can leave this page and come back later.';
```

#### 3. `styles.css`
**Location:** End of file

**Add:**
```css
/* Enhanced status messages */
.cloudflarestream-status-message strong {
    font-size: 16px;
}

.cloudflarestream-status-message small {
    display: block;
    margin-top: 5px;
    font-size: 13px;
}

.cloudflarestream-status-message .fa-check-circle {
    color: #28a745;
    margin-right: 5px;
}
```

#### 4. `amd/build/uploader.min.js`
**Action:** Copy from `amd/src/uploader.js` after changes

### Expected Result:
- Clear messages: "Verifying upload...", "Checking video status (attempt 2 of 5)"
- Success message with icon and confirmation text
- Users understand what's happening at each step

---

## Task 3: Increase Polling Time to 60 Seconds

### Priority: HIGH
### Status: ⏳ Pending
### Estimated Time: 10 minutes

### Description:
Increase the video status checking time from 40 seconds to 60 seconds to catch more videos that process slowly.

### Current Timing:
```javascript
const delays = [3000, 5000, 7000, 10000, 15000]; // Total: 40 seconds
```

### Files to Modify:

#### 1. `amd/src/uploader.js`
**Location:** Line 370

**Change:**
```javascript
// CURRENT:
const delays = [3000, 5000, 7000, 10000, 15000]; // Total: ~40 seconds

// CHANGE TO (Option 2 - Progressive, Recommended):
const delays = [5000, 10000, 15000, 15000, 15000]; // Total: 60 seconds

// Timing breakdown:
// Attempt 1: Wait 5s  → Check → Total elapsed: 5s   (quick check for small files)
// Attempt 2: Wait 10s → Check → Total elapsed: 15s
// Attempt 3: Wait 15s → Check → Total elapsed: 30s
// Attempt 4: Wait 15s → Check → Total elapsed: 45s
// Attempt 5: Wait 15s → Check → Total elapsed: 60s
```

#### 2. `amd/build/uploader.min.js`
**Action:** Copy from `amd/src/uploader.js` after changes

### Expected Result:
- More videos will be marked as "ready" during initial upload
- Fewer videos stuck in "uploading" status
- Better user experience for medium-sized files

---

## Task 4: Add Status Check on Page View (CRITICAL FIX)

### Priority: 🔴 CRITICAL
### Status: ⏳ Pending
### Estimated Time: 30 minutes

### Description:
**CRITICAL BUG:** Videos stuck in "uploading" status never update to "ready" when user refreshes the page. This fix adds automatic status checking when viewing the submission.

### Current Problem:
1. Video uploads but takes > 60 seconds to process in Cloudflare
2. DB saved with status="uploading"
3. User refreshes page → Status NEVER updates
4. Video stuck as "uploading" forever
5. Video never becomes playable

### Root Cause:
The `view_summary()` method in `lib.php` only reads from database, never checks Cloudflare API.

### Files to Modify:

#### 1. `lib.php`
**Location:** In `view_summary()` method, after line 568 (after getting video record)

**Add this code:**
```php
public function view_summary(stdClass $submission, & $showviewlink) {
    global $DB, $CFG, $OUTPUT, $PAGE;

    // Get the video record for this submission
    $video = $DB->get_record('assignsubmission_cfstream', 
        array('submission' => $submission->id));

    if (!$video) {
        return '';
    }

    // ===== ADD THIS NEW CODE BLOCK =====
    // If video is not ready, check Cloudflare for updated status
    if ($video->upload_status === 'uploading' || $video->upload_status === 'pending') {
        // Only check if it's been at least 1 minute since upload (avoid too frequent checks)
        if (time() - $video->upload_timestamp > 60) {
            try {
                $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
                $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
                
                if (!empty($apitoken) && !empty($accountid)) {
                    require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
                    
                    $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
                    $details = $client->get_video_details($video->video_uid);
                    
                    // Update DB if status changed to ready
                    if (isset($details->readyToStream) && $details->readyToStream === true) {
                        $video->upload_status = 'ready';
                        
                        // Update metadata if available
                        if (isset($details->duration)) {
                            $video->duration = (int)$details->duration;
                        }
                        if (isset($details->size)) {
                            $video->file_size = (int)$details->size;
                        }
                        
                        $DB->update_record('assignsubmission_cfstream', $video);
                        
                        // Log the status update
                        error_log("Cloudflare video {$video->video_uid} status updated to ready on page view");
                    }
                }
            } catch (\assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception $e) {
                // Video was deleted from Cloudflare
                $video->upload_status = 'deleted';
                $video->deleted_timestamp = time();
                $video->error_message = 'Video not found in Cloudflare';
                $DB->update_record('assignsubmission_cfstream', $video);
            } catch (Exception $e) {
                // Silently fail, will try again next time page is viewed
                error_log("Failed to check Cloudflare status for video {$video->video_uid}: " . $e->getMessage());
            }
        }
    }
    // ===== END NEW CODE BLOCK =====

    // Load JavaScript for grading interface injection
    $PAGE->requires->js_call_amd('assignsubmission_cloudflarestream/grading_injector', 'init');

    // ... rest of existing code continues ...
```

### Function Logic:
1. Check if video status is "uploading" or "pending"
2. Check if at least 60 seconds have passed since upload (avoid too frequent API calls)
3. Call Cloudflare API to get current video status
4. If `readyToStream === true`, update DB to status="ready"
5. Also update duration and file_size if available
6. Handle errors gracefully (video not found, API errors)

### Expected Result:
- Videos stuck in "uploading" status will automatically update to "ready" when page is viewed
- Works for:
  - Student viewing their own submission
  - Teacher viewing submission in grading interface
  - Anyone viewing the submission after 1 minute
- No more permanently stuck videos!

---

## Task 5: Add Background Sync Task (Long-term Solution)

### Priority: MEDIUM
### Status: ⏳ Pending
### Estimated Time: 45 minutes

### Description:
Add a scheduled task that runs every 5-10 minutes to check all "uploading" videos and update their status. This catches videos that nobody views.

### Files to Create/Modify:

#### 1. `classes/task/sync_video_status.php` (NEW FILE)
**Create new file:**

```php
<?php
namespace assignsubmission_cloudflarestream\task;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_cloudflarestream\api\cloudflare_client;

/**
 * Scheduled task to sync video status with Cloudflare.
 */
class sync_video_status extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sync_video_status_task', 'assignsubmission_cloudflarestream');
    }

    public function execute() {
        global $DB;

        $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
        $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

        if (empty($apitoken) || empty($accountid)) {
            mtrace('Cloudflare Stream sync: API not configured. Skipping.');
            return;
        }

        // Find videos that are still "uploading" or "pending" and older than 1 minute
        $cutoff = time() - 60;
        $sql = "SELECT id, video_uid, upload_timestamp
                FROM {assignsubmission_cfstream}
                WHERE upload_status IN ('uploading', 'pending')
                AND upload_timestamp < ?
                ORDER BY upload_timestamp ASC";

        $videos = $DB->get_records_sql($sql, [$cutoff]);

        if (empty($videos)) {
            mtrace('Cloudflare Stream sync: No videos to check.');
            return;
        }

        mtrace('Cloudflare Stream sync: Checking ' . count($videos) . ' videos...');

        $client = new cloudflare_client($apitoken, $accountid);
        $updated = 0;
        $notfound = 0;
        $errors = 0;

        foreach ($videos as $video) {
            try {
                $details = $client->get_video_details($video->video_uid);

                if (isset($details->readyToStream) && $details->readyToStream === true) {
                    $updaterecord = new \stdClass();
                    $updaterecord->id = $video->id;
                    $updaterecord->upload_status = 'ready';
                    
                    if (isset($details->duration)) {
                        $updaterecord->duration = (int)$details->duration;
                    }
                    if (isset($details->size)) {
                        $updaterecord->file_size = (int)$details->size;
                    }
                    
                    $DB->update_record('assignsubmission_cfstream', $updaterecord);
                    $updated++;
                    mtrace("Video {$video->video_uid} updated to ready");
                }
            } catch (\assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception $e) {
                $updaterecord = new \stdClass();
                $updaterecord->id = $video->id;
                $updaterecord->upload_status = 'deleted';
                $updaterecord->deleted_timestamp = time();
                $updaterecord->error_message = 'Video not found in Cloudflare';
                $DB->update_record('assignsubmission_cfstream', $updaterecord);
                $notfound++;
                mtrace("Video {$video->video_uid} not found, marked as deleted");
            } catch (Exception $e) {
                $errors++;
                mtrace("Error checking video {$video->video_uid}: " . $e->getMessage());
            }
        }

        mtrace("Cloudflare Stream sync complete: {$updated} updated, {$notfound} not found, {$errors} errors");
    }
}
```

#### 2. `db/tasks.php`
**Modify existing file, add:**

```php
$tasks = array(
    // Existing cleanup task
    array(
        'classname' => 'assignsubmission_cloudflarestream\task\cleanup_videos',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ),
    // NEW: Sync task - runs every 10 minutes
    array(
        'classname' => 'assignsubmission_cloudflarestream\task\sync_video_status',
        'blocking' => 0,
        'minute' => '*/10',  // Every 10 minutes
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);
```

#### 3. `lang/en/assignsubmission_cloudflarestream.php`
**Add:**

```php
$string['sync_video_status_task'] = 'Sync video status with Cloudflare';
```

#### 4. `db/upgrade.php`
**Add version bump to register new task:**

```php
// Add after existing upgrade steps
if ($oldversion < 2025103001) {
    // New sync task registered in db/tasks.php
    upgrade_plugin_savepoint(true, 2025103001, 'assignsubmission', 'cloudflarestream');
}
```

#### 5. `version.php`
**Update version number:**

```php
$plugin->version = 2025103001;  // Increment version
```

### Expected Result:
- Background task runs every 10 minutes
- Automatically updates "uploading" videos to "ready"
- Catches videos that nobody views
- Marks deleted videos appropriately
- Logs all actions for monitoring

---

## Task 6: Add User Message for Processing Videos

### Priority: MEDIUM
### Status: ⏳ Pending
### Estimated Time: 15 minutes

### Description:
Add a helpful message when video is still processing, telling user to refresh the page.

### Files to Modify:

#### 1. `lib.php`
**Location:** In `view_summary()` method, in the section that shows status for non-ready videos

**Find (around line 620):**
```php
case 'uploading':
    $icon = '<i class="fa fa-clock-o text-warning" aria-hidden="true"></i> ';
    $output = $icon . $statustext;
    break;
```

**Change to:**
```php
case 'uploading':
    $icon = '<i class="fa fa-clock-o text-warning" aria-hidden="true"></i> ';
    $output = $icon . $statustext;
    
    // Add helpful message
    $output .= '<br><small class="text-muted">';
    $output .= get_string('video_processing_message', 'assignsubmission_cloudflarestream');
    $output .= '</small>';
    break;
```

#### 2. `lang/en/assignsubmission_cloudflarestream.php`
**Add:**

```php
$string['video_processing_message'] = 'Your video is being processed by Cloudflare. This usually takes 1-2 minutes. Please refresh the page in a moment.';
```

### Expected Result:
When video status is "uploading", user sees:
```
⏰ Uploading
Your video is being processed by Cloudflare. This usually takes 1-2 minutes. Please refresh the page in a moment.
```

---

## Implementation Order (Recommended):

1. ✅ **Task 4** (CRITICAL) - Fix stuck videos issue
2. ✅ **Task 3** - Increase polling time to 60 seconds
3. ✅ **Task 1** - Add file format info
4. ✅ **Task 2** - Improve status messages
5. ✅ **Task 6** - Add user message
6. ✅ **Task 5** - Add background sync (optional, for long-term)

---

## Testing Checklist:

After implementing each task:

- [ ] Upload small video (< 100MB) - should be ready quickly
- [ ] Upload large video (> 500MB) - test 60-second polling
- [ ] Upload video, close page before ready, refresh after 2 minutes - should update to ready
- [ ] Check database - verify status updates correctly
- [ ] Test as student and as teacher
- [ ] Check browser console for errors
- [ ] Verify all language strings display correctly
- [ ] Test on mobile/tablet (responsive design)

---

## Notes:

- All tasks are independent and can be implemented separately
- Task 4 is CRITICAL and should be done first
- Task 5 (background sync) is optional but recommended for production
- Clear Moodle cache after each change
- Test thoroughly before deploying to production

---

**Document Version:** 1.0  
**Created:** October 30, 2025  
**Status:** Ready for Review


---

## Task 7: Fix Upload Failures & Implement Resumable Upload (CRITICAL)

### Priority: 🔴 CRITICAL
### Status: ⏳ Pending
### Estimated Time: 6 hours total (Phase 1: 1 hour, Phase 2: 5 hours)

### Description:
**CRITICAL ISSUES:** 
1. Large files (1.7 GB+) fail during upload due to network disconnections
2. Small files (70 MB) also fail sometimes, creating dummy entries in Cloudflare
3. Retry button starts from 0%, wasting upload time
4. Failed uploads create "Pending Upload" entries in Cloudflare dashboard

### Current Problems:
1. ❌ **Network Disconnection:** Any file can fail with "network error during upload"
2. ❌ **No Resume:** Retry button starts from 0%, not from where it failed
3. ❌ **Dummy Entries:** Failed uploads create broken entries in Cloudflare (ALL file sizes)
4. ❌ **Time Waste:** Users can't complete large uploads, wasting hours
5. ❌ **Database Pollution:** Failed uploads leave orphaned records

### Root Cause Analysis:

#### Current Upload Method (Direct Upload):
```javascript
// uploader.js - Line 280
xhr.open('POST', uploadData.uploadURL);
xhr.send(formData);
```

**Problems:**
- Uses single HTTP POST request
- No chunking or resume capability
- If network drops for 1 second → entire upload fails
- No way to resume from last successful chunk
- Cloudflare creates video UID immediately (before upload starts)
- If upload fails → Dummy entry remains in Cloudflare

#### Why Dummy Entries Are Created (ALL File Sizes):
```
Step 1: get_upload_url.php calls Cloudflare API
    ↓
Step 2: Cloudflare creates video UID: "abc123" (entry created!)
    ↓
Step 3: Database record created: status="pending"
    ↓
Step 4: Upload starts...
    ↓
Step 5: Network fails at 50% ❌
    ↓
Result:
- Cloudflare has dummy entry "abc123" (Pending Upload) ❌
- Database has broken record ❌
- User clicks retry → Creates NEW dummy entry "def456" ❌
```

**This happens with BOTH small (70 MB) and large (1.7 GB) files!**

### Solution: Hybrid Approach (Two-Phase Implementation)

#### Phase 1: Cleanup Failed Uploads (Quick Fix - ALL Files)
Add cleanup logic to delete failed uploads from Cloudflare and database.
- ✅ Prevents dummy entries for ALL file sizes
- ✅ Works with current Direct Upload API
- ✅ Easy to implement (1 hour)

#### Phase 2: TUS Resumable Upload (Best Solution - Large Files)
Implement TUS protocol for files > 200 MB.
- ✅ Chunked upload (5 MB chunks)
- ✅ Resume capability
- ✅ Video created only after first chunk succeeds
- ✅ Better reliability for large files
- ✅ Pause/resume capability

### Implementation Plan:

---

## PHASE 1: Cleanup Failed Uploads (Quick Fix - 1 hour)

### Fixes dummy entries for ALL file sizes (small and large)

#### Step 1.1: Add Cleanup Method to Uploader

**File:** `amd/src/uploader.js`

**Location:** After `startUpload()` method (around line 240)

**Add this method:**

```javascript
/**
 * Clean up failed upload - delete video from Cloudflare and database.
 * This prevents dummy "Pending Upload" entries in Cloudflare.
 *
 * @param {string} videoUid The Cloudflare video UID to delete
 * @param {number} submissionId The submission ID
 * @return {Promise<void>}
 */
async cleanupFailedUpload(videoUid, submissionId) {
    if (!videoUid) {
        return; // Nothing to clean up
    }
    
    try {
        console.log('Cleaning up failed upload: ' + videoUid);
        
        await $.ajax({
            url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/cleanup_failed_upload.php',
            method: 'POST',
            data: {
                videouid: videoUid,
                submissionid: submissionId,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        });
        
        console.log('Successfully cleaned up failed upload: ' + videoUid);
    } catch (error) {
        // Silently fail - cleanup will be handled by scheduled task
        console.error('Failed to cleanup video ' + videoUid + ':', error);
    }
}
```

#### Step 1.2: Modify startUpload to Use Cleanup

**File:** `amd/src/uploader.js`

**Location:** Replace `startUpload()` method (around line 220)

**Replace with:**

```javascript
/**
 * Start the upload process with automatic cleanup on failure.
 *
 * @param {File} file The file to upload
 */
async startUpload(file) {
    if (this.uploadInProgress) {
        this.showError('An upload is already in progress.');
        return;
    }

    let uploadData = null; // Store upload data for cleanup

    try {
        this.uploadInProgress = true;
        this.showProgress();
        this.updateProgress(0);

        // Request upload URL from Moodle
        uploadData = await this.requestUploadUrl(file);

        // Upload file directly to Cloudflare
        await this.uploadToCloudflare(file, uploadData);

        // Confirm upload with retry - checks Cloudflare status multiple times
        this.updateProgress(100, 'Finalizing upload...');
        await this.confirmUploadWithRetry(uploadData.uid, uploadData.submissionid);

        // Show success message
        this.showSuccess();

    } catch (error) {
        // Upload failed - clean up the dummy entry
        if (uploadData && uploadData.uid) {
            console.log('Upload failed, cleaning up video: ' + uploadData.uid);
            await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
        }
        
        this.handleError(error);
    }
}
```

#### Step 1.3: Create Cleanup Backend Endpoint

**File:** `ajax/cleanup_failed_upload.php` (NEW FILE)

**Create new file:**

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to clean up failed uploads.
 * Deletes video from Cloudflare and database when upload fails.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\logger;

// Get parameters.
$videouid = required_param('videouid', PARAM_ALPHANUMEXT);
$submissionid = required_param('submissionid', PARAM_INT);

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    $deleted_from_cloudflare = false;
    $deleted_from_database = false;
    
    // Delete from Cloudflare if configured.
    if (!empty($apitoken) && !empty($accountid) && !empty($videouid)) {
        try {
            $client = new cloudflare_client($apitoken, $accountid);
            $client->delete_video($videouid);
            $deleted_from_cloudflare = true;
            
            // Log the cleanup.
            logger::log_video_deleted(
                $USER->id,
                $videouid,
                'cleanup_failed_upload',
                'Video deleted due to failed upload'
            );
        } catch (Exception $e) {
            // Video might not exist in Cloudflare (already deleted or never created)
            // This is OK - continue with database cleanup
            error_log('Failed to delete video from Cloudflare (might not exist): ' . $e->getMessage());
        }
    }
    
    // Delete from database.
    if ($submissionid > 0) {
        $deleted_from_database = $DB->delete_records('assignsubmission_cfstream', [
            'submission' => $submissionid,
            'video_uid' => $videouid
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'deleted_from_cloudflare' => $deleted_from_cloudflare,
        'deleted_from_database' => $deleted_from_database,
        'message' => 'Cleanup completed'
    ]);
    
} catch (Exception $e) {
    // Log error but return success (cleanup is best-effort)
    error_log('Cleanup failed upload error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => true, // Return success even on error
        'error' => $e->getMessage(),
        'message' => 'Cleanup attempted (errors logged)'
    ]);
}
```

#### Step 1.4: Rebuild JavaScript

**Command:**
```bash
cd mod/assign/submission/cloudflarestream
npx grunt amd
```

Or manually copy:
```bash
cp amd/src/uploader.js amd/build/uploader.min.js
```

### Expected Result (Phase 1):
- ✅ Upload fails → Video automatically deleted from Cloudflare
- ✅ Database record automatically deleted
- ✅ No dummy "Pending Upload" entries
- ✅ Works for ALL file sizes (small and large)
- ✅ User can retry without creating multiple dummy entries

---

## PHASE 2: TUS Resumable Upload (Best Solution - 5 hours)

### Implements resumable uploads for large files (> 200 MB)

#### Phase 2.1: Add TUS Client Library

**File:** `amd/src/tus-uploader.js` (NEW FILE)

Use the official TUS JavaScript client:
- Library: https://github.com/tus/tus-js-client
- CDN: https://cdn.jsdelivr.net/npm/tus-js-client@latest/dist/tus.min.js

```javascript
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * TUS resumable uploader for Cloudflare Stream.
 *
 * @module     assignsubmission_cloudflarestream/tus-uploader
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'https://cdn.jsdelivr.net/npm/tus-js-client@latest/dist/tus.min.js'], 
function ($, tus) {

    /**
     * TUS Resumable Uploader class.
     */
    class TUSUploader {
        /**
         * Constructor.
         *
         * @param {File} file The file to upload
         * @param {string} tusEndpoint TUS endpoint URL
         * @param {Object} options Upload options
         */
        constructor(file, tusEndpoint, options) {
            this.file = file;
            this.tusEndpoint = tusEndpoint;
            this.options = options || {};
            this.upload = null;
            this.isPaused = false;
        }

        /**
         * Start the upload.
         *
         * @return {Promise} Upload promise
         */
        start() {
            return new Promise((resolve, reject) => {
                // Create TUS upload instance
                this.upload = new tus.Upload(this.file, {
                    // Cloudflare Stream TUS endpoint
                    endpoint: this.tusEndpoint,
                    
                    // Retry configuration
                    retryDelays: [0, 3000, 5000, 10000, 20000], // Retry delays in ms
                    
                    // Chunk size (5 MB recommended for large files)
                    chunkSize: 5 * 1024 * 1024, // 5 MB chunks
                    
                    // Metadata
                    metadata: {
                        filename: this.file.name,
                        filetype: this.file.type
                    },
                    
                    // Callbacks
                    onError: (error) => {
                        console.error('TUS upload error:', error);
                        reject(error);
                    },
                    
                    onProgress: (bytesUploaded, bytesTotal) => {
                        const percentage = Math.round((bytesUploaded / bytesTotal) * 100);
                        const uploadedMB = (bytesUploaded / (1024 * 1024)).toFixed(1);
                        const totalMB = (bytesTotal / (1024 * 1024)).toFixed(1);
                        
                        if (this.options.onProgress) {
                            this.options.onProgress(percentage, uploadedMB, totalMB, bytesUploaded, bytesTotal);
                        }
                    },
                    
                    onSuccess: () => {
                        console.log('TUS upload completed successfully');
                        
                        // Extract video UID from upload URL
                        const uploadUrl = this.upload.url;
                        const videoUid = this.extractVideoUid(uploadUrl);
                        
                        resolve({
                            success: true,
                            videoUid: videoUid,
                            uploadUrl: uploadUrl
                        });
                    },
                    
                    onAfterResponse: (req, res) => {
                        // Log response for debugging
                        console.log('TUS response:', res.getStatus(), res.getHeader('Upload-Offset'));
                    }
                });

                // Start the upload
                this.upload.start();
            });
        }

        /**
         * Pause the upload.
         */
        pause() {
            if (this.upload && !this.isPaused) {
                this.upload.abort();
                this.isPaused = true;
            }
        }

        /**
         * Resume the upload.
         */
        resume() {
            if (this.upload && this.isPaused) {
                this.upload.start();
                this.isPaused = false;
            }
        }

        /**
         * Cancel the upload.
         */
        cancel() {
            if (this.upload) {
                this.upload.abort();
                this.upload = null;
            }
        }

        /**
         * Extract video UID from TUS upload URL.
         *
         * @param {string} uploadUrl TUS upload URL
         * @return {string} Video UID
         */
        extractVideoUid(uploadUrl) {
            // TUS URL format: https://api.cloudflare.com/client/v4/accounts/{account_id}/stream/{video_uid}
            const matches = uploadUrl.match(/\/stream\/([a-f0-9]+)/);
            return matches ? matches[1] : null;
        }
    }

    return {
        /**
         * Create a new TUS uploader instance.
         *
         * @param {File} file The file to upload
         * @param {string} tusEndpoint TUS endpoint URL
         * @param {Object} options Upload options
         * @return {TUSUploader} Uploader instance
         */
        create: function(file, tusEndpoint, options) {
            return new TUSUploader(file, tusEndpoint, options);
        }
    };
});
```

#### Phase 2.2: Modify Backend to Support TUS

**File:** `ajax/get_tus_endpoint.php` (NEW FILE)

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to get TUS endpoint URL for resumable uploads.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;
use assignsubmission_cloudflarestream\rate_limiter;
use assignsubmission_cloudflarestream\rate_limit_exception;

// Get and validate parameters.
try {
    $assignmentid = validator::validate_assignment_id(required_param('assignmentid', PARAM_INT));
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
    if ($submissionid > 0) {
        $submissionid = validator::validate_submission_id($submissionid);
    }
} catch (validation_exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

// Require login and valid session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Apply rate limiting.
    $ratelimiter = new rate_limiter();
    $ratelimiter->apply_rate_limit('upload', $USER->id, $assignmentid);
    
    // Load the assignment.
    list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
    $context = context_module::instance($cm->id);
    
    // Create assignment object.
    $assign = new assign($context, $cm, $course);
    
    // Verify user has permission to submit.
    require_capability('mod/assign:submit', $context);
    
    // Check if submissions are allowed.
    if (!$assign->submissions_open($USER->id)) {
        throw new moodle_exception('submissionsclosed', 'assign');
    }
    
    // Get plugin configuration.
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    if (empty($apitoken) || empty($accountid)) {
        throw new moodle_exception('config_missing', 'assignsubmission_cloudflarestream');
    }
    
    // Get or create submission record.
    $submission = $assign->get_user_submission($USER->id, true);
    
    // Create database record with pending status.
    $record = new stdClass();
    $record->assignment = $assignmentid;
    $record->submission = $submission->id;
    $record->video_uid = ''; // Will be filled when TUS upload starts
    $record->upload_status = 'pending';
    $record->upload_timestamp = time();
    
    // Validate and sanitize the record.
    $record = validator::validate_database_record($record);
    
    // Check if record already exists.
    $existing = $DB->get_record('assignsubmission_cfstream', 
        array('submission' => $submission->id));
    
    if ($existing) {
        // Update existing record.
        $record->id = $existing->id;
        $DB->update_record('assignsubmission_cfstream', $record);
    } else {
        // Insert new record.
        $DB->insert_record('assignsubmission_cfstream', $record);
    }
    
    // Build TUS endpoint URL
    // Format: https://api.cloudflare.com/client/v4/accounts/{account_id}/stream?direct_user=true
    $tusEndpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountid}/stream?direct_user=true";
    
    // Return TUS endpoint and auth header
    echo json_encode([
        'success' => true,
        'tusEndpoint' => $tusEndpoint,
        'authHeader' => 'Bearer ' . $apitoken,
        'submissionid' => $submission->id,
        'maxChunkSize' => 5 * 1024 * 1024 // 5 MB
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'can_retry' => true
    ]);
}
```

#### Phase 2.3: Update Main Uploader to Use TUS

**File:** `amd/src/uploader.js`

**Add method to detect large files:**

```javascript
/**
 * Determine if file should use TUS resumable upload.
 *
 * @param {File} file The file to check
 * @return {boolean} True if should use TUS
 */
shouldUseTUS(file) {
    // Use TUS for files larger than 200 MB
    const TUS_THRESHOLD = 200 * 1024 * 1024; // 200 MB
    return file.size > TUS_THRESHOLD;
}
```

**Modify startUpload method:**

```javascript
async startUpload(file) {
    if (this.uploadInProgress) {
        this.showError('An upload is already in progress.');
        return;
    }

    try {
        this.uploadInProgress = true;
        this.showProgress();
        this.updateProgress(0);

        // Determine upload method based on file size
        if (this.shouldUseTUS(file)) {
            // Use TUS for large files (> 200 MB)
            await this.uploadWithTUS(file);
        } else {
            // Use direct upload for small files (< 200 MB)
            await this.uploadDirect(file);
        }

        // Show success message
        this.showSuccess();

    } catch (error) {
        this.handleError(error);
    }
}

/**
 * Upload file using TUS resumable protocol (for large files).
 *
 * @param {File} file The file to upload
 */
async uploadWithTUS(file) {
    // Request TUS endpoint
    const tusData = await this.requestTUSEndpoint(file);
    
    // Load TUS uploader module
    const TUSUploader = await new Promise((resolve) => {
        require(['assignsubmission_cloudflarestream/tus-uploader'], resolve);
    });
    
    // Create TUS uploader
    const uploader = TUSUploader.create(file, tusData.tusEndpoint, {
        onProgress: (percentage, uploadedMB, totalMB) => {
            this.updateProgress(percentage, uploadedMB + 'MB / ' + totalMB + 'MB (Resumable)');
        }
    });
    
    // Configure TUS headers
    uploader.upload.options.headers = {
        'Authorization': tusData.authHeader
    };
    
    // Start upload
    const result = await uploader.start();
    
    // Confirm upload with Moodle
    this.updateProgress(100, 'Finalizing upload...');
    await this.confirmUploadWithRetry(result.videoUid, tusData.submissionid);
}

/**
 * Upload file using direct upload (for small files).
 *
 * @param {File} file The file to upload
 */
async uploadDirect(file) {
    // Request upload URL from Moodle
    const uploadData = await this.requestUploadUrl(file);

    // Upload file directly to Cloudflare
    await this.uploadToCloudflare(file, uploadData);

    // Confirm upload with retry
    this.updateProgress(100, 'Finalizing upload...');
    await this.confirmUploadWithRetry(uploadData.uid, uploadData.submissionid);
}

/**
 * Request TUS endpoint from Moodle backend.
 *
 * @param {File} file The file to upload
 * @return {Promise<Object>} TUS endpoint data
 */
async requestTUSEndpoint(file) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_tus_endpoint.php',
            method: 'POST',
            data: {
                assignmentid: this.assignmentId,
                submissionid: this.submissionId,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        }).done((data) => {
            if (data.success) {
                resolve(data);
            } else {
                reject(new Error(data.error || 'Failed to get TUS endpoint'));
            }
        }).fail(() => {
            reject(new Error('Network error occurred while requesting TUS endpoint'));
        });
    });
}
```

#### Phase 2.4: Add Pause/Resume UI

**File:** `templates/upload_form.mustache`

**Add pause/resume buttons:**

```html
<div class="cloudflarestream-progress-container" style="display: none;">
    <div class="progress">
        <div class="progress-bar progress-bar-striped progress-bar-animated cloudflarestream-progress-bar" 
             role="progressbar" 
             aria-valuenow="0" 
             aria-valuemin="0" 
             aria-valuemax="100" 
             style="width: 0%">
            0%
        </div>
    </div>
    <div class="cloudflarestream-progress-percentage text-center mt-2">0%</div>
    
    <!-- NEW: Pause/Resume/Cancel buttons for TUS uploads -->
    <div class="cloudflarestream-upload-controls mt-3 text-center" style="display: none;">
        <button type="button" class="btn btn-warning cloudflarestream-pause-btn">
            <i class="fa fa-pause"></i> Pause
        </button>
        <button type="button" class="btn btn-success cloudflarestream-resume-btn" style="display: none;">
            <i class="fa fa-play"></i> Resume
        </button>
        <button type="button" class="btn btn-danger cloudflarestream-cancel-btn">
            <i class="fa fa-times"></i> Cancel
        </button>
    </div>
</div>
```

#### Phase 2.5: Enhanced Cleanup for Orphaned Videos

**File:** `classes/task/cleanup_videos.php`

**Add method to clean up pending uploads (safety net):**

```php
/**
 * Clean up orphaned pending uploads (failed TUS uploads).
 */
private function cleanup_orphaned_uploads() {
    global $DB;
    
    // Find videos stuck in "pending" status for more than 24 hours
    $cutoff = time() - (24 * 60 * 60);
    $sql = "SELECT id, video_uid, upload_timestamp
            FROM {assignsubmission_cfstream}
            WHERE upload_status = 'pending'
            AND upload_timestamp < ?";
    
    $orphaned = $DB->get_records_sql($sql, [$cutoff]);
    
    if (empty($orphaned)) {
        return;
    }
    
    mtrace('Found ' . count($orphaned) . ' orphaned pending uploads');
    
    foreach ($orphaned as $video) {
        // Delete from Cloudflare if video_uid exists
        if (!empty($video->video_uid)) {
            try {
                $this->client->delete_video($video->video_uid);
                mtrace("Deleted orphaned video: {$video->video_uid}");
            } catch (Exception $e) {
                mtrace("Failed to delete orphaned video {$video->video_uid}: " . $e->getMessage());
            }
        }
        
        // Delete from database
        $DB->delete_records('assignsubmission_cfstream', ['id' => $video->id]);
    }
}
```

---

## Implementation Summary:

### Phase 1 Files (Quick Fix - 1 hour):

**Files to Create:**
1. ✅ `ajax/cleanup_failed_upload.php` - Cleanup endpoint

**Files to Modify:**
1. ✅ `amd/src/uploader.js` - Add cleanup logic
2. ✅ `amd/build/uploader.min.js` - Rebuild

### Phase 2 Files (TUS Implementation - 5 hours):

**Files to Create:**
1. ✅ `amd/src/tus-uploader.js` - TUS client wrapper
2. ✅ `ajax/get_tus_endpoint.php` - TUS endpoint provider
3. ✅ `amd/build/tus-uploader.min.js` - Minified version

**Files to Modify:**
1. ✅ `amd/src/uploader.js` - Add TUS support
2. ✅ `templates/upload_form.mustache` - Add pause/resume UI
3. ✅ `classes/task/cleanup_videos.php` - Enhanced orphan cleanup
4. ✅ `lang/en/assignsubmission_cloudflarestream.php` - Add strings
5. ✅ `styles.css` - Style pause/resume buttons

### Language Strings to Add:

```php
// TUS resumable upload
$string['resumable_upload'] = 'Resumable upload';
$string['upload_paused'] = 'Upload paused';
$string['upload_resumed'] = 'Upload resumed';
$string['upload_cancelled'] = 'Upload cancelled';
$string['large_file_resumable'] = 'Large file detected. Using resumable upload for reliability.';
$string['pause_upload'] = 'Pause upload';
$string['resume_upload'] = 'Resume upload';
$string['cancel_upload'] = 'Cancel upload';
$string['upload_can_resume'] = 'You can pause and resume this upload at any time.';
```

### Expected Results:

#### Before (Current - ALL Files):
- ❌ 70 MB file fails → Dummy entry in Cloudflare
- ❌ 1.7 GB file fails with network error
- ❌ Retry starts from 0%
- ❌ Multiple dummy entries accumulate
- ❌ Wasted upload time

#### After Phase 1 (Cleanup - ALL Files):
- ✅ 70 MB file fails → Automatically deleted from Cloudflare
- ✅ No dummy entries for any file size
- ✅ Database stays clean
- ✅ User can retry without creating duplicates
- ⚠️ Still no resume capability (starts from 0%)

#### After Phase 2 (TUS - Large Files):
- ✅ Small files (< 200 MB) → Direct upload + cleanup
- ✅ Large files (> 200 MB) → TUS resumable upload
- ✅ Network disconnection → Automatic resume from last chunk
- ✅ Manual pause/resume capability
- ✅ No dummy entries (video created only after first chunk succeeds)
- ✅ Progress saved, can close browser and continue later
- ✅ Orphaned uploads cleaned up automatically

### Testing Plan:

#### Phase 1 Testing (Cleanup):

1. **Small File Upload Failure:**
   - Upload 70 MB file
   - Disconnect network at 50%
   - Verify: Video deleted from Cloudflare ✅
   - Verify: Database record deleted ✅
   - Verify: No dummy entry in Cloudflare dashboard ✅

2. **Retry After Failure:**
   - Upload fails (network error)
   - Click retry button
   - Verify: Only ONE video in Cloudflare (not multiple) ✅

3. **Multiple Retry Test:**
   - Upload fails 3 times
   - Retry 3 times
   - Verify: No accumulation of dummy entries ✅

#### Phase 2 Testing (TUS):

1. **Small File Test (< 200 MB):**
   - Should use direct upload + cleanup
   - Fast and simple
   - If fails → Cleanup works

2. **Large File Test (> 200 MB):**
   - Should use TUS resumable upload
   - Shows "Resumable" in progress text
   - Pause/Resume buttons visible

3. **Network Interruption Test:**
   - Start 1.7 GB file upload
   - Disconnect network at 50%
   - Reconnect network
   - Upload should resume automatically from 50% ✅

4. **Manual Pause Test:**
   - Start large file upload
   - Click "Pause" button
   - Wait 30 seconds
   - Click "Resume" button
   - Upload continues from where it paused ✅

5. **Browser Close Test:**
   - Start large file upload
   - Close browser tab
   - Reopen page
   - Upload should resume (if TUS client supports it)

6. **Orphan Cleanup Test:**
   - Create failed upload (pending status)
   - Wait 24 hours (or manually run cleanup task)
   - Verify orphaned video deleted from Cloudflare
   - Verify database record deleted

### Alternative: Cloudflare Direct Creator Upload

If TUS implementation is too complex, consider Cloudflare's Direct Creator Upload:
- https://developers.cloudflare.com/stream/uploading-videos/direct-creator-uploads/

**Pros:**
- Simpler than TUS
- Still supports large files
- Better error handling

**Cons:**
- No pause/resume capability
- No chunk-level retry
- Still vulnerable to network issues

### Recommendation:

**Implement BOTH phases for complete solution:**

#### Phase 1 (MUST DO - 1 hour):
- Fixes dummy entries for ALL file sizes
- Quick to implement
- Immediate improvement
- Works with current code

#### Phase 2 (HIGHLY RECOMMENDED - 5 hours):
- Handles large files reliably
- Automatic resume on network failure
- Better user experience
- Industry standard (used by YouTube, Vimeo, etc.)
- Prevents dummy entries naturally

**Estimated Implementation Time:**

**Phase 1: Cleanup Failed Uploads**
- Add cleanup method: 15 minutes
- Modify startUpload: 15 minutes
- Create cleanup endpoint: 20 minutes
- Testing: 10 minutes
- **Phase 1 Total: 1 hour**

**Phase 2: TUS Implementation**
- TUS Client: 1 hour
- Backend: 1 hour
- Integration: 1.5 hours
- UI (Pause/Resume): 30 minutes
- Enhanced Cleanup: 30 minutes
- Testing: 30 minutes
- **Phase 2 Total: 5 hours**

**Grand Total: 6 hours**

**Implementation Order:**
1. ✅ Phase 1 first (1 hour) - Immediate fix for dummy entries
2. ✅ Test Phase 1 thoroughly
3. ✅ Phase 2 next (5 hours) - Add resumable uploads
4. ✅ Test Phase 2 thoroughly

---

## Summary of All Tasks:

| Task | Priority | Time | Status | Description |
|------|----------|------|--------|-------------|
| Task 1 | HIGH | 15 min | ⏳ Pending | Add file format info |
| Task 2 | HIGH | 25 min | ⏳ Pending | Improve status messages |
| Task 3 | HIGH | 10 min | ⏳ Pending | Increase polling to 60s |
| Task 4 | 🔴 CRITICAL | 30 min | ⏳ Pending | Fix stuck videos on refresh |
| Task 5 | MEDIUM | 45 min | ⏳ Pending | Background sync task |
| Task 6 | MEDIUM | 15 min | ⏳ Pending | Add processing message |
| Task 7 Phase 1 | 🔴 CRITICAL | 1 hour | ⏳ Pending | Cleanup failed uploads (ALL files) |
| Task 7 Phase 2 | 🔴 CRITICAL | 5 hours | ⏳ Pending | TUS resumable upload (large files) |

**Total Estimated Time:** ~8.5 hours

**Critical Path (Must Do First):**
1. ✅ Task 4 - Fix stuck videos (30 min)
2. ✅ Task 7 Phase 1 - Cleanup failed uploads (1 hour)
3. ✅ Task 3 - Increase polling (10 min)
4. ✅ Task 7 Phase 2 - TUS resumable upload (5 hours)

**Subtotal Critical: ~6.5 hours**

**Nice to Have (UX Improvements):**
- Task 1, 2, 6 - UX improvements (55 min)
- Task 5 - Background sync (45 min)

**Subtotal Nice to Have: ~1.5 hours**

**Recommended Implementation Order:**
1. Task 4 (30 min) - Fixes videos stuck as "uploading"
2. Task 7 Phase 1 (1 hour) - Fixes dummy entries for ALL files
3. Task 3 (10 min) - Better polling for status checks
4. Task 1, 2, 6 (55 min) - Better user experience
5. Task 7 Phase 2 (5 hours) - Resumable uploads for large files
6. Task 5 (45 min) - Background sync (optional)

---

**Document Updated:** October 30, 2025  
**Version:** 3.0 (Updated with Hybrid Approach)  
**Status:** Ready for Implementation

**Key Changes in v3.0:**
- Task 7 split into Phase 1 (Cleanup) and Phase 2 (TUS)
- Phase 1 fixes dummy entries for ALL file sizes (1 hour)
- Phase 2 adds resumable uploads for large files (5 hours)
- Both phases work together for complete solution
- Updated implementation order and testing plan
