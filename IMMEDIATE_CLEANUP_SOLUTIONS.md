# Immediate Cleanup Solutions - Implementation Reference

**Date:** October 31, 2025  
**Issue:** Concurrent upload failures block other users  
**Status:** 📋 PLANNING - Not yet implemented

---

## The Problem

### Scenario:
1. **User 1, 2, 3** start uploading videos → 3 UIDs created in Cloudflare
2. All 3 users **close browser/lose network** before JavaScript cleanup runs
3. **User 4** tries to upload 5 minutes later → **BLOCKED!** (3 dummy videos still occupying slots)
4. Must wait **30 minutes** for cron cleanup → **Not fair to User 4!**

### Current Situation:
- ✅ JavaScript cleanup works when errors are caught
- ❌ Doesn't work when browser closes unexpectedly
- ❌ Doesn't work when computer crashes
- ❌ Doesn't work when network drops completely
- ⏰ Cron runs every 30 minutes (too slow for concurrent users)

---

## What's Already Implemented ✅

### JavaScript Automatic Cleanup (Task 7 Phase 1)

**File:** `mod/assign/submission/cloudflarestream/amd/src/uploader.js`

```javascript
async startUpload(file) {
    let uploadData = null;
    
    try {
        uploadData = await this.requestUploadUrl(file);  // Creates UID in Cloudflare
        await this.uploadToCloudflare(file, uploadData);  // Upload file
        await this.confirmUploadWithRetry(...);           // Confirm with backend
        this.showSuccess();
    } catch (error) {
        // Upload failed - CLEAN UP IMMEDIATELY!
        if (uploadData && uploadData.uid) {
            await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
        }
        this.handleError(error);
    }
}
```

**Cleanup Endpoint:** `ajax/cleanup_failed_upload.php`

**Triggers on:**
- ✅ Network error during upload
- ✅ Upload timeout
- ✅ User clicks cancel
- ✅ Cloudflare API errors
- ✅ Any caught exception

**Does NOT trigger on:**
- ❌ Browser/tab closed
- ❌ Computer crash/power loss
- ❌ Network completely drops (no error caught)

---

## Solution Options (In Order of Effectiveness)

---

## Solution 1: Browser "beforeunload" Event ⭐ BEST FOR QUICK WIN

### Description:
Trigger cleanup when user closes tab/browser/navigates away.

### Implementation:

**File:** `mod/assign/submission/cloudflarestream/amd/src/uploader.js`

```javascript
class CloudflareUploader {
    constructor(assignmentId, submissionId, maxFileSize) {
        this.assignmentId = assignmentId;
        this.submissionId = submissionId;
        this.maxFileSize = maxFileSize;
        this.uploadData = null;
        this.isUploading = false;
        
        // Register cleanup on page unload
        this.registerUnloadHandler();
    }
    
    /**
     * Register handler to cleanup on browser close/navigation
     */
    registerUnloadHandler() {
        window.addEventListener('beforeunload', (event) => {
            if (this.uploadData && this.uploadData.uid && this.isUploading) {
                // Use sendBeacon for reliable cleanup during page unload
                const url = M.cfg.wwwroot + 
                    '/mod/assign/submission/cloudflarestream/ajax/cleanup_failed_upload.php';
                
                const data = new FormData();
                data.append('videouid', this.uploadData.uid);
                data.append('submissionid', this.uploadData.submissionid);
                data.append('sesskey', M.cfg.sesskey);
                
                // sendBeacon is reliable even during page unload
                navigator.sendBeacon(url, data);
            }
        });
    }
    
    async startUpload(file) {
        this.isUploading = true;  // Set flag
        let uploadData = null;
        
        try {
            uploadData = await this.requestUploadUrl(file);
            this.uploadData = uploadData;  // Store for unload handler
            
            await this.uploadToCloudflare(file, uploadData);
            await this.confirmUploadWithRetry(...);
            
            this.isUploading = false;  // Clear flag on success
            this.showSuccess();
        } catch (error) {
            this.isUploading = false;  // Clear flag on error
            
            if (uploadData && uploadData.uid) {
                await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
            }
            this.handleError(error);
        }
    }
}
```

### Pros:
- ✅ Immediate cleanup when browser closes
- ✅ Works when user navigates away
- ✅ Works when tab is closed
- ✅ Uses `navigator.sendBeacon()` (reliable during unload)
- ✅ Simple to implement
- ✅ No backend changes needed

### Cons:
- ❌ Doesn't work if computer crashes/loses power
- ❌ Doesn't work if browser crashes
- ❌ May not work on very old browsers

### Coverage:
**~90% of failure cases**

---

## Solution 2: Heartbeat/Keepalive System ⭐⭐ MOST RELIABLE

### Description:
Track active uploads with periodic "I'm still alive" signals. Backend detects stale uploads.

### Implementation:

#### Frontend Changes:

**File:** `mod/assign/submission/cloudflarestream/amd/src/uploader.js`

```javascript
class CloudflareUploader {
    constructor(assignmentId, submissionId, maxFileSize) {
        this.assignmentId = assignmentId;
        this.submissionId = submissionId;
        this.maxFileSize = maxFileSize;
        this.uploadData = null;
        this.isUploading = false;
        this.heartbeatInterval = null;
    }
    
    /**
     * Start sending heartbeat signals
     */
    startHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
        
        // Send heartbeat every 30 seconds
        this.heartbeatInterval = setInterval(() => {
            if (this.isUploading && this.uploadData && this.uploadData.uid) {
                this.sendHeartbeat();
            }
        }, 30000);  // 30 seconds
    }
    
    /**
     * Stop sending heartbeat signals
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    /**
     * Send heartbeat to backend
     */
    async sendHeartbeat() {
        try {
            const response = await fetch(
                M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/upload_heartbeat.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        videouid: this.uploadData.uid,
                        submissionid: this.uploadData.submissionid,
                        timestamp: Date.now(),
                        sesskey: M.cfg.sesskey
                    })
                }
            );
            
            if (!response.ok) {
                console.warn('Heartbeat failed:', response.status);
            }
        } catch (error) {
            console.warn('Heartbeat error:', error);
        }
    }
    
    async startUpload(file) {
        this.isUploading = true;
        let uploadData = null;
        
        try {
            uploadData = await this.requestUploadUrl(file);
            this.uploadData = uploadData;
            
            // Start sending heartbeats
            this.startHeartbeat();
            
            await this.uploadToCloudflare(file, uploadData);
            await this.confirmUploadWithRetry(...);
            
            // Stop heartbeats on success
            this.stopHeartbeat();
            this.isUploading = false;
            this.showSuccess();
        } catch (error) {
            // Stop heartbeats on error
            this.stopHeartbeat();
            this.isUploading = false;
            
            if (uploadData && uploadData.uid) {
                await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
            }
            this.handleError(error);
        }
    }
}
```

#### Backend Changes:

**New File:** `mod/assign/submission/cloudflarestream/ajax/upload_heartbeat.php`

```php
<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../../config.php');

// Get parameters
$videouid = required_param('videouid', PARAM_ALPHANUMEXT);
$submissionid = required_param('submissionid', PARAM_INT);
$timestamp = required_param('timestamp', PARAM_INT);

require_sesskey();

// Update heartbeat timestamp in database
$DB->execute(
    "UPDATE {assignsubmission_cfstream} 
     SET heartbeat_timestamp = ? 
     WHERE video_uid = ? AND submission = ?",
    [time(), $videouid, $submissionid]
);

echo json_encode(['success' => true]);
```

**Database Change:** Add `heartbeat_timestamp` column to `mdl_assignsubmission_cfstream` table

```sql
ALTER TABLE mdl_assignsubmission_cfstream 
ADD COLUMN heartbeat_timestamp INT(10) DEFAULT NULL AFTER upload_timestamp;
```

**Cron Task Update:** `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php`

```php
private function cleanup_stuck_uploads($cloudflare) {
    global $DB;

    // Find uploads with no heartbeat in last 2 minutes
    $heartbeat_cutoff = time() - 120; // 2 minutes
    
    $sql = "SELECT id, video_uid, assignment, submission, upload_status, upload_timestamp
            FROM {assignsubmission_cfstream}
            WHERE upload_status IN ('pending', 'uploading')
            AND (heartbeat_timestamp IS NULL OR heartbeat_timestamp < ?)
            AND upload_timestamp < ?
            ORDER BY upload_timestamp ASC";

    $stuckuploads = $DB->get_records_sql($sql, [$heartbeat_cutoff, $heartbeat_cutoff]);
    
    // ... rest of cleanup logic
}
```

### Pros:
- ✅ Catches ALL failure scenarios (crash, network loss, browser close)
- ✅ Very reliable
- ✅ Can run cron every 5 minutes to check heartbeats
- ✅ Detects stale uploads quickly (2-5 minutes)
- ✅ Works even if JavaScript fails completely

### Cons:
- ❌ More complex to implement
- ❌ Requires database schema change
- ❌ Extra HTTP requests every 30 seconds during upload
- ❌ Slightly more server load

### Coverage:
**~99% of failure cases**

---

## Solution 3: Reduce Cron Interval ⭐ SIMPLEST

### Description:
Run cron more frequently to reduce wait time.

### Implementation:

**File:** `mod/assign/submission/cloudflarestream/db/tasks.php`

```php
$tasks = [
    [
        'classname' => 'assignsubmission_cloudflarestream\task\cleanup_videos',
        'blocking' => 0,
        'minute' => '*/5',   // Run every 5 minutes (instead of */30)
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];
```

**And reduce wait time:**

**File:** `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php`

```php
// Find uploads stuck for more than 10 minutes
$waittime = 600; // 10 minutes (instead of 1800)
```

### Pros:
- ✅ Very simple - just change configuration
- ✅ No code changes needed
- ✅ Works for all failure scenarios

### Cons:
- ❌ Still has 5-10 minute delay
- ❌ More server load (cron runs 6x more often)
- ❌ Doesn't solve root cause

### Coverage:
**100% of cases, but with 5-10 minute delay**

---

## Solution 4: Increase Cloudflare Concurrent Upload Limit 💰

### Description:
Contact Cloudflare support to increase concurrent upload limit from 3 to 10 or more.

### Implementation:
1. Contact Cloudflare support
2. Request increase in concurrent upload limit
3. May require plan upgrade

### Pros:
- ✅ Reduces problem frequency significantly
- ✅ No code changes needed
- ✅ Allows more concurrent users

### Cons:
- ❌ May cost more money
- ❌ Doesn't solve root cause
- ❌ Still has the problem, just less frequent

### Coverage:
**Reduces problem by ~70%, doesn't eliminate it**

---

## Recommended Implementation Strategy 🎯

### Phase 1: Quick Win (Implement First)

**Combination: Solution 1 + Solution 3**

1. ✅ Add `beforeunload` event cleanup (Solution 1)
2. ✅ Reduce cron to every 10 minutes (Solution 3)
3. ✅ Reduce wait time to 10 minutes

**Result:**
- 90% of cases: Immediate cleanup (beforeunload)
- 10% of cases: 10-minute cleanup (cron)
- **Total impact on users: Minimal**

**Implementation Time:** 1-2 hours

---

### Phase 2: Complete Solution (Implement Later)

**Add: Solution 2 (Heartbeat System)**

1. ✅ Add heartbeat system
2. ✅ Add database column
3. ✅ Update cron to check heartbeats
4. ✅ Set cron back to 30 minutes (heartbeat handles quick cleanup)

**Result:**
- 99% of cases: 2-5 minute cleanup (heartbeat detection)
- 1% of cases: 30-minute cleanup (cron safety net)
- **Total impact on users: Almost none**

**Implementation Time:** 4-6 hours

---

### Phase 3: Optional Enhancement

**Add: Solution 4 (Increase Cloudflare Limit)**

Contact Cloudflare to increase concurrent upload limit.

**Result:**
- Problem becomes extremely rare
- Better user experience overall

---

## Files That Need Changes

### Phase 1 (Quick Win):
1. `mod/assign/submission/cloudflarestream/amd/src/uploader.js` - Add beforeunload handler
2. `mod/assign/submission/cloudflarestream/amd/build/uploader.min.js` - Rebuild minified
3. `mod/assign/submission/cloudflarestream/db/tasks.php` - Change to */10
4. `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php` - Change to 600 seconds

### Phase 2 (Complete Solution):
5. `mod/assign/submission/cloudflarestream/ajax/upload_heartbeat.php` - NEW FILE
6. `mod/assign/submission/cloudflarestream/db/upgrade.php` - Add database column
7. `mod/assign/submission/cloudflarestream/classes/task/cleanup_videos.php` - Add heartbeat check

---

## Testing Instructions

### Test Phase 1:
1. Start uploading a large video
2. Close browser tab during upload
3. Check Cloudflare dashboard - video should be deleted within 10 minutes
4. Check database - record should be deleted

### Test Phase 2:
1. Start uploading a large video
2. Kill browser process (force close)
3. Wait 2-3 minutes
4. Check Cloudflare dashboard - video should be deleted
5. Check database - record should be deleted

---

## Priority Recommendation

**Start with Phase 1** - It's quick to implement and solves 90% of the problem immediately.

**Implement Phase 2** when you have more time for a complete solution.

---

**Status:** 📋 READY FOR IMPLEMENTATION  
**Recommended:** Start with Phase 1 (Quick Win)  
**Estimated Time:** 1-2 hours for Phase 1
