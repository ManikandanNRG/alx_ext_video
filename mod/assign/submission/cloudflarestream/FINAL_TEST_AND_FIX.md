# FINAL TEST AND FIX - Cloudflare Stream Upload

## Current Status

✅ **Cloudflare API is working perfectly!**
- API returns `uid`: `f9df37fe4b39f1147da5d5aa61b7cb2b`
- API returns `uploadURL`: `https://upload.cloudflarestream.com/...`
- HTTP 200 response

✅ **Plugin configuration is correct**
- API token configured
- Account ID configured
- Plugin enabled for assignment

✅ **Assignment settings fixed**
- File plugin enabled
- Dates allow submissions
- `submissions_open()` passes

## The Problem

The upload fails with "Video identifier is required" even though Cloudflare API works. This means:

1. Either `get_upload_url.php` is not being called correctly
2. Or there's a cached error response
3. Or the response is not being parsed correctly

## Solution Steps

### Step 1: Purge ALL Caches

**On server:**
```bash
php admin/cli/purge_caches.php
```

**Or via web:**
1. Go to: Site administration > Development > Purge all caches
2. Click "Purge all caches"

### Step 2: Clear Browser Cache

1. Close ALL browser windows
2. Clear browser cache (Ctrl+Shift+Delete)
3. Open in Incognito/Private mode

### Step 3: Copy Updated Files

Make sure these files are on your server:
```
mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
mod/assign/submission/cloudflarestream/ajax/get_upload_url.php
mod/assign/submission/cloudflarestream/ajax/confirm_upload.php
```

### Step 4: Test Upload

1. Go to assignment: https://dev.aktrea.net/mod/assign/view.php?id=684&action=editsubmission
2. Select a video file
3. Upload

### Step 5: Check Logs

If it still fails, check Apache error log:
```bash
sudo tail -f /var/log/apache2/error.log
```

Look for:
```
Cloudflare upload URL response: uploadURL=..., uid=..., submissionid=...
```

If you see this log, the backend is working and the problem is in JavaScript.

If you DON'T see this log, the backend is failing before it gets the Cloudflare response.

## Expected Behavior

When upload works correctly:

1. **JavaScript calls** `get_upload_url.php`
2. **PHP returns** JSON with `uid` and `uploadURL`
3. **JavaScript uploads** file to Cloudflare using TUS
4. **JavaScript calls** `confirm_upload.php` with the `uid`
5. **PHP saves** the video record to database
6. **Success!** Video appears in submission

## If Still Failing

### Check 1: Is get_upload_url.php being called?

Add this at the TOP of `ajax/get_upload_url.php` (line 1):
```php
<?php
error_log('=== get_upload_url.php CALLED ===');
```

Then try uploading and check if you see this in the error log.

### Check 2: Is the response correct?

The response from `get_upload_url.php` should be:
```json
{
  "success": true,
  "uploadURL": "https://upload.cloudflarestream.com/...",
  "uid": "...",
  "submissionid": 123
}
```

### Check 3: Is JavaScript receiving the response?

Open browser console (F12) and look for:
```
=== UPLOAD DATA RECEIVED ===
Full uploadData object: {...}
uploadData.uid: f9df37fe4b39f1147da5d5aa61b7cb2b
```

If you see this, JavaScript is working.
If you DON'T see this, JavaScript is cached.

## Nuclear Option: Force JavaScript Reload

If caching is the issue, add a version parameter to force reload.

Edit `lib.php`, find the `get_form_elements()` method, and change the JavaScript loading to:
```php
$PAGE->requires->js_call_amd(
    'assignsubmission_cloudflarestream/uploader',
    'init',
    [$this->assignment->get_instance()->id, $submission->id, $maxfilesize, '.cloudflarestream-upload-interface'],
    '?v=' . time()  // Force reload
);
```

## Success Criteria

Upload is successful when you see:
1. ✅ File picker opens
2. ✅ Video uploads (progress bar shows)
3. ✅ "Video uploaded successfully!" message
4. ✅ Video appears in submission
5. ✅ Can play video in grading interface

## Current Blockers

Based on the tests:
- ❌ JavaScript is not receiving the `uid` from `get_upload_url.php`
- ❌ OR JavaScript is cached and using old code
- ❌ OR there's an error between the API call and the response

## Next Action

**PURGE CACHES** and try again. If it still fails, we need to see:
1. Apache error log during upload attempt
2. Browser console log during upload attempt
3. Network tab showing the AJAX request/response

This will tell us exactly where the `uid` is being lost!
