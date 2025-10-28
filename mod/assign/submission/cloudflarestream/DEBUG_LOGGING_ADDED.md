# Debug Logging Added - Cloudflare Stream Upload Issue

## Problem
Upload fails with error: "Video identifier is required" (error code: `missing_video_uid`)

Apache log shows:
```
Cloudflare Stream: Upload failure - User: 15030, Assignment: 5, Error: missing_video_uid - Video identifier is required
```

This means the `videouid` parameter is being sent to `confirm_upload.php` but it's **empty**.

## Root Cause Analysis

The issue is that the `uid` from Cloudflare API is either:
1. Not being returned by `get_upload_url.php`
2. Not being extracted correctly by JavaScript
3. Being lost between `requestUploadUrl()` and `confirmUpload()`

## Debug Logging Added

### Files Modified
1. `amd/src/uploader.js` - Added extensive console logging
2. `amd/build/uploader.min.js` - Rebuilt with new logging

### What the Logs Will Show

#### In Browser Console (F12):
```
=== UPLOAD DATA RECEIVED ===
Full uploadData object: {...}
uploadData.uid: [value]
uploadData.uploadURL: [value]
uploadData.submissionid: [value]
===========================

=== BEFORE CONFIRM UPLOAD ===
Confirming upload with uid: [value]
submissionid: [value]
typeof uid: [type]
uid length: [length]
============================

=== CONFIRM UPLOAD AJAX CALL ===
videoUid parameter: [value]
submissionId parameter: [value]
typeof videoUid: [type]
videoUid length: [length]
Data being sent: {...}
================================
```

#### In Apache Error Log:
```
Cloudflare upload URL response: uploadURL=[url], uid=[uid], submissionid=[id]
confirm_upload.php validated: videouid=[uid], submissionid=[id]
```

## Next Steps

1. **Copy these files to your server:**
   - `mod/assign/submission/cloudflarestream/amd/build/uploader.min.js`

2. **Clear ALL caches:**
   ```bash
   # In Moodle admin
   Site administration > Development > Purge all caches
   
   # Or via CLI
   php admin/cli/purge_caches.php
   ```

3. **Clear browser cache completely:**
   - Close ALL browser windows
   - Open a NEW browser window (or use Incognito/Private mode)
   - Or use Ctrl+Shift+Delete and clear cached files

4. **Try uploading a video**

5. **Check the logs:**
   - Open browser console (F12)
   - Look for the debug messages starting with `===`
   - Check Apache error log: `sudo tail -f /var/log/apache2/error.log`

6. **Report back with:**
   - What the browser console shows (especially the `uploadData.uid` value)
   - What the Apache log shows
   - Whether the uid is empty, null, undefined, or has a value

## Expected Outcomes

### If uid is present in browser console but empty in PHP:
- JavaScript is working correctly
- Problem is in the AJAX transmission
- Need to check jQuery version or AJAX encoding

### If uid is empty in browser console:
- Problem is in `get_upload_url.php` response
- Need to check Cloudflare API response
- May need to check `cloudflare_client.php`

### If uid is present everywhere but still fails:
- Problem is in the validator
- Need to check `validator::validate_video_uid()` logic

## Files Involved

- `amd/src/uploader.js` - JavaScript uploader (source)
- `amd/build/uploader.min.js` - JavaScript uploader (built)
- `ajax/get_upload_url.php` - Returns upload URL and uid
- `ajax/confirm_upload.php` - Receives uid and confirms upload
- `classes/api/cloudflare_client.php` - Calls Cloudflare API
- `classes/validator.php` - Validates uid parameter

## Important Notes

- The JavaScript cache is VERY aggressive in Moodle
- You MUST purge caches after updating JavaScript
- Using Incognito/Private browsing mode helps avoid cache issues
- The debug logs will tell us EXACTLY where the uid is being lost
