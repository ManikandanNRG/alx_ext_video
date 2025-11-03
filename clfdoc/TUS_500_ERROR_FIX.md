# TUS 500 Error - Root Cause Analysis & Fix

## Error Observed

```
POST https://dev.aktrea.net/mod/assign/submission/cloudflarestream/ajax/upload_tus.php?action=chunk&assignmentid=9&offset=0&sesskey=uVDsU2WMCy 500 (Internal Server Error)
Upload error: Error: TUS chunk upload failed: 500
```

## Root Cause Analysis

### The Flow

1. **JavaScript calls** `createTusSession(file)`
   - Sends: `action=create`, `filesize`, `filename`
   - PHP creates TUS session with Cloudflare
   - PHP stores `upload_url` in user preferences
   - PHP returns: `{success: true, uid: "...", submissionid: ...}`

2. **JavaScript calls** `uploadViaTus(file, uploadData)`
   - Loops through file chunks
   - For each chunk, calls `uploadTusChunk(uploadUrl, chunkData, offset)`

3. **JavaScript sends chunk** to PHP
   - URL: `/ajax/upload_tus.php?action=chunk&offset=0`
   - Method: POST
   - Body: Binary chunk data (ArrayBuffer)
   - Header: `Content-Type: application/octet-stream`

4. **PHP receives chunk request**
   - Gets `upload_url` from user preferences
   - Reads chunk data from `php://input`
   - Forwards to Cloudflare via PATCH

### Possible Issues

#### Issue 1: User Preferences Not Set
**Symptom**: "TUS session not found"
**Cause**: `set_user_preference()` failed or user session changed
**Fix**: Added error logging to identify this

#### Issue 2: No Chunk Data Received
**Symptom**: "No chunk data"
**Cause**: `file_get_contents('php://input')` returns empty
**Possible reasons**:
- Content-Type mismatch
- Data already consumed by Moodle
- POST data parsed as form data instead of raw

#### Issue 3: Cloudflare API Error
**Symptom**: "Chunk upload failed: HTTP XXX"
**Cause**: Cloudflare rejected the chunk
**Possible reasons**:
- Invalid offset
- Invalid chunk size
- TUS session expired
- Network error

## Fixes Applied

### Fix 1: Return upload_url in Response
```php
// OLD
echo json_encode([
    'success' => true,
    'uid' => $result->uid,
    'submissionid' => $submission->id
]);

// NEW
echo json_encode([
    'success' => true,
    'uid' => $result->uid,
    'upload_url' => $result->upload_url,  // Added
    'submissionid' => $submission->id
]);
```

**Why**: For debugging and potential future use

### Fix 2: Added Debug Logging
```php
// Log upload URL retrieval
if (empty($uploadurl)) {
    error_log('TUS Error: No upload URL found in preferences for user ' . $USER->id);
    throw new moodle_exception(...);
}

// Log chunk data reception
error_log('TUS Chunk: offset=' . $offset . ', data_length=' . strlen($chunkdata) . ', upload_url=' . substr($uploadurl, 0, 50) . '...');

// Log empty chunk data
if (empty($chunkdata)) {
    error_log('TUS Error: No chunk data received. Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    throw new moodle_exception(...);
}
```

**Why**: To identify exactly where the 500 error occurs

## Testing Instructions

### Step 1: Deploy Files
```bash
# Copy fixed files to server
scp mod/assign/submission/cloudflarestream/ajax/upload_tus.php \
    ubuntu@dev.aktrea.net:/tmp/

scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js \
    ubuntu@dev.aktrea.net:/tmp/

# On server
sudo mv /tmp/upload_tus.php \
    /var/www/html/mod/assign/submission/cloudflarestream/ajax/

sudo mv /tmp/uploader.min.js \
    /var/www/html/mod/assign/submission/cloudflarestream/amd/build/

sudo chown www-data:www-data \
    /var/www/html/mod/assign/submission/cloudflarestream/ajax/upload_tus.php \
    /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js

# Clear cache
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

### Step 2: Watch Apache Logs
```bash
sudo tail -f /var/log/apache2/error.log | grep -i tus
```

### Step 3: Test Upload
1. Go to assignment submission page
2. Select a large video file (>200MB)
3. Click upload
4. Watch browser console and Apache logs

### Step 4: Analyze Logs

**Expected logs (success)**:
```
TUS Chunk: offset=0, data_length=52428800, upload_url=https://edge-production...
TUS Chunk: offset=52428800, data_length=52428800, upload_url=https://edge-production...
...
```

**Possible error logs**:

**Error A: No upload URL**
```
TUS Error: No upload URL found in preferences for user 123
```
**Solution**: Check if `set_user_preference()` is working. May need to use session storage instead.

**Error B: No chunk data**
```
TUS Chunk: offset=0, data_length=0, upload_url=https://edge-production...
TUS Error: No chunk data received. Content-Type: application/octet-stream
```
**Solution**: The issue is with `file_get_contents('php://input')`. May need to use `$HTTP_RAW_POST_DATA` or check Moodle's request handling.

**Error C: Cloudflare error**
```
TUS Chunk: offset=0, data_length=52428800, upload_url=https://edge-production...
Chunk upload failed: HTTP 400
```
**Solution**: Check Cloudflare API response for details.

## Next Steps Based on Logs

### If "No upload URL found"
The issue is with user preferences. Solution:
```php
// Instead of user preferences, use session
$_SESSION['tus_upload_url'] = $result->upload_url;
$_SESSION['tus_video_uid'] = $result->uid;

// Later retrieve
$uploadurl = $_SESSION['tus_upload_url'] ?? null;
```

### If "No chunk data received"
The issue is with reading POST data. Solution:
```php
// Try alternative methods
$chunkdata = file_get_contents('php://input');
if (empty($chunkdata) && isset($HTTP_RAW_POST_DATA)) {
    $chunkdata = $HTTP_RAW_POST_DATA;
}
if (empty($chunkdata)) {
    // Check if Moodle consumed it
    $chunkdata = $REQUEST->get_raw_post_data();
}
```

### If Cloudflare returns error
Check the response body for details:
```php
$body = substr($response, $headersize);
error_log('Cloudflare error response: ' . $body);
```

## Files Modified

1. **mod/assign/submission/cloudflarestream/ajax/upload_tus.php**
   - Added `upload_url` to create response
   - Added debug logging for chunk uploads
   - Added error logging for troubleshooting

2. **mod/assign/submission/cloudflarestream/amd/build/uploader.min.js**
   - Rebuilt (no code changes)

## Confidence Level

**70%** - We've added logging to identify the exact issue, but haven't fixed the root cause yet.

The 500 error could be caused by:
1. User preferences not working (30% likely)
2. POST data not readable (50% likely)
3. Cloudflare API error (20% likely)

Once we see the Apache logs, we'll know exactly which issue it is and can fix it immediately.

## Quick Fix Options

If user preferences don't work, here's a quick alternative:

### Option A: Use Session Storage
```php
// In create action
$_SESSION['tus_upload_url_' . $USER->id] = $result->upload_url;

// In chunk action
$uploadurl = $_SESSION['tus_upload_url_' . $USER->id] ?? null;
```

### Option B: Pass upload_url in Each Request
```javascript
// JavaScript sends upload_url with each chunk
const url = M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/upload_tus.php' +
    '?action=chunk' +
    '&assignmentid=' + this.assignmentId +
    '&offset=' + offset +
    '&uploadurl=' + encodeURIComponent(uploadUrl) +  // Add this
    '&sesskey=' + M.cfg.sesskey;
```

```php
// PHP gets it from URL parameter
$uploadurl = required_param('uploadurl', PARAM_URL);
```

### Option C: Store in Database
```php
// In create action
$DB->set_field('assignsubmission_cfstream', 'upload_url', $result->upload_url, 
    ['submission' => $submission->id]);

// In chunk action
$record = $DB->get_record('assignsubmission_cfstream', ['submission' => $submission->id]);
$uploadurl = $record->upload_url;
```

## Recommendation

**Deploy the debug version first**, see what the logs say, then apply the appropriate fix.

Most likely issue: **POST data not readable** due to Moodle's request handling.

---

**Deploy and test, then report back with Apache log output!**
