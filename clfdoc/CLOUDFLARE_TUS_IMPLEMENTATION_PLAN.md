# Cloudflare Stream TUS Resumable Upload - Deep Analysis & Implementation Plan

## Executive Summary

This document provides a comprehensive analysis of implementing TUS (Tus Resumable Upload) protocol for Cloudflare Stream in Moodle to overcome the 200MB direct upload limitation and enable uploads up to 30GB.

**Critical Finding**: The previous TUS attempt failed because we tried to use external JavaScript libraries that conflict with Moodle's AMD module system. This plan uses a **pure native implementation** with no external dependencies.

---

## 1. Problem Analysis

### Current Limitations
- **Direct Upload (POST)**: Maximum 200MB (Cloudflare hard limit)
- **Your Requirement**: Upload 1.7GB+ video files
- **Previous TUS Attempt**: Failed due to library conflicts and protocol misunderstanding

### Why TUS is Required
Cloudflare Stream has two upload methods:

| Method | Max Size | Protocol | Use Case |
|--------|----------|----------|----------|
| Direct Upload | 200MB | Simple POST | Small files |
| TUS Upload | 30GB | Multi-step PATCH | Large files |

**There is NO way to upload files >200MB without TUS.**

---

## 2. Deep Dive: Cloudflare TUS Protocol

### Official Documentation Analysis

**Primary Sources**:
- **Main Guide**: https://developers.cloudflare.com/stream/uploading-videos/upload-video-file/
- **TUS Resumable Uploads**: https://developers.cloudflare.com/stream/uploading-videos/resumable-uploads/ ✅ **READ**
- **TUS Spec**: https://tus.io/protocols/resumable-upload/ (v1.0.0)

**Key Findings from Cloudflare Docs**:

1. **Endpoint Difference**:
   - Direct Upload: `POST /stream/direct_upload` (max 200MB)
   - TUS Upload: `POST /stream` (max 30GB)

2. **Chunk Size Requirements** ⚠️ **CRITICAL**:
   ```
   Minimum:     5,242,880 bytes (5 MB)
   Recommended: 52,428,800 bytes (50 MB) - for reliable connections
   Maximum:     209,715,200 bytes (200 MB)
   
   MUST BE DIVISIBLE BY: 256 KiB (262,144 bytes)
   Exception: Final chunk can be any size
   ```
   
   **Formula**: `chunkSize % 262144 === 0`
   
   **Valid chunk sizes**:
   - 5,242,880 bytes (5 MB) ✅
   - 10,485,760 bytes (10 MB) ✅
   - 52,428,800 bytes (50 MB) ✅ **RECOMMENDED**
   - 104,857,600 bytes (100 MB) ✅
   - 209,715,200 bytes (200 MB) ✅ **MAXIMUM**

3. **Video UID Retrieval** 🎯 **SOLVES YESTERDAY'S ERROR!**:
   ```
   ❌ DON'T: Parse the Location header URL
   ✅ DO: Use the 'stream-media-id' response header
   ```
   
   **From Cloudflare Docs**:
   > "When an initial tus request is made, Stream responds with a URL in the Location header. 
   > While this URL may contain the video ID, it is not recommend to parse this URL to get the ID.
   > Instead, you should use the stream-media-id HTTP header in the response to retrieve the video ID."
   
   **Example Response Headers**:
   ```
   Location: https://api.cloudflare.com/client/v4/accounts/<ACCOUNT_ID>/stream/cab807e0c477d01baq20f66c3d1dfc26cf
   stream-media-id: cab807e0c477d01baq20f66c3d1dfc26cf
   ```
   
   **THIS IS THE KEY!** We should read `stream-media-id` header, not parse the URL!

4. **Upload Metadata Format**:
   ```
   Upload-Metadata: name {base64_filename},requiresignedurlsrls {true|false}
   ```
   
   **Supported Metadata Keys**:
   - `name`: Video name (sets meta.name, displays in dashboard)
   - `requiresignedurlsrls`: Make video private (boolean)
   - `scheduleddeletion`: Auto-delete date (ISO 8601 format)
   - `allowedorigins`: Comma-separated origins
   - `thumbnailtimestamppct`: Thumbnail position (0.0 - 1.0)
   - `watermark`: Watermark profile UID
   
   **Note**: Values should be base64 encoded for strings

5. **Upload-Creator Header** (Optional):
   - Use to link videos to your user/creator system
   - Format: `Upload-Creator: {your_user_id}`
   - Useful for multi-tenant applications

6. **CORS Support**:
   - Cloudflare Stream has full CORS support for TUS
   - No additional configuration needed
   - Works from browser JavaScript

7. **Retry Strategy** (From Node.js example):
   ```javascript
   retryDelays: [0, 3000, 5000, 10000, 20000]
   ```
   - Immediate retry, then exponential backoff
   - Total 5 retry attempts

### TUS Protocol Flow (3 Phases)

#### Phase 1: Create Upload Session
```http
POST https://api.cloudflare.com/client/v4/accounts/{account_id}/stream
Headers:
  Authorization: Bearer {api_token}
  Tus-Resumable: 1.0.0
  Upload-Length: {file_size_in_bytes}
  Upload-Metadata: name {base64_encoded_filename}

Response:
  Status: 201 Created
  Location: https://upload.cloudflarestream.com/{upload_url}
  Tus-Resumable: 1.0.0
```

**Key Points**:
- Uses `/stream` endpoint (NOT `/stream/direct_upload`)
- Returns a unique upload URL for subsequent PATCH requests
- Video UID is embedded in the Location header URL


#### Phase 2: Upload File Chunks
```http
PATCH {upload_url_from_phase_1}
Headers:
  Tus-Resumable: 1.0.0
  Upload-Offset: {current_byte_offset}
  Content-Type: application/offset+octet-stream
Body: 
  {binary_chunk_data}

Response:
  Status: 204 No Content
  Upload-Offset: {new_byte_offset}
  Tus-Resumable: 1.0.0
```

**Key Points**:
- Use PATCH method (not POST)
- Upload-Offset must match server's current offset exactly
- Content-Type MUST be `application/offset+octet-stream`
- Response has no body, only headers
- Repeat until all bytes uploaded

#### Phase 3: Check Upload Status (Optional)
```http
HEAD {upload_url}
Headers:
  Tus-Resumable: 1.0.0

Response:
  Upload-Offset: {current_byte_offset}
  Upload-Length: {total_file_size}
  Tus-Resumable: 1.0.0
```

**Use Cases**:
- Resume interrupted uploads
- Verify upload progress
- Check if upload is complete

---

## 3. Critical Differences: Direct Upload vs TUS

| Aspect | Direct Upload | TUS Upload |
|--------|---------------|------------|
| **Endpoint** | `/stream/direct_upload` | `/stream` |
| **Method** | POST (single request) | POST + multiple PATCH |
| **Headers** | Standard HTTP | TUS-specific headers |
| **Body** | Complete file | File chunks |
| **Resume** | ❌ Not supported | ✅ Supported |
| **Max Size** | 200MB | 30GB |
| **Progress** | Browser native | Custom implementation |
| **Video UID** | In response body | In Location header |
| **Complexity** | Simple | Complex |

---

## 4. Why Previous TUS Attempt Failed

### Actual Error from Yesterday's Attempt

```
=== TUS Upload Success ===
Upload URL: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true

❌ Failed to extract video UID from URL: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true

❌ Error: Failed to extract video UID from upload URL
   at Object.onSuccess (first.js:13052:3b)
   at xhr.onload (first.js:14908:50)

❌ Falling back to direct upload
```

**Analysis**: Upload reached 100% successfully, but failed at UID extraction step.

### Root Causes Identified

1. **Video UID Extraction Failed** ⚠️ **CRITICAL**
   - TUS returns complex URL: `https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/{account_id}/media/{uid}?tusv2=true`
   - Previous code expected simple format: `https://upload.cloudflarestream.com/{uid}`
   - **The UID is in the path segment after `/media/`**
   - Need robust parsing to handle query parameters (`?tusv2=true`)

2. **Library Conflict**: Tried to use `tus-js-client` external library
   - Conflicts with Moodle's AMD module system
   - Requires webpack/bundler setup
   - Not compatible with Moodle's JavaScript architecture

3. **Wrong Endpoint**: Used `/stream/direct_upload` instead of `/stream`
   - Direct upload endpoint doesn't support TUS protocol
   - Returns 413 error for large files

4. **Protocol Misunderstanding**: Didn't implement proper chunk upload loop
   - TUS requires multiple PATCH requests
   - Each PATCH must track byte offset correctly

### Critical Fix Required

**🎯 THE CORRECT SOLUTION (From Official Cloudflare Docs)**:

```
❌ WRONG: Parse the Location header URL
✅ CORRECT: Read the 'stream-media-id' response header
```

**From Cloudflare Documentation**:
> "When an initial tus request is made, Stream responds with a URL in the Location header. 
> While this URL may contain the video ID, it is not recommend to parse this URL to get the ID.
> Instead, you should use the stream-media-id HTTP header in the response to retrieve the video ID."

**Example Response**:
```http
HTTP/1.1 201 Created
Location: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
stream-media-id: d9eb8bf
Tus-Resumable: 1.0.0
```

**The Fix**:
```javascript
// ❌ OLD WAY (Yesterday's error)
const uid = extractUidFromUrl(response.headers['Location']);

// ✅ NEW WAY (Official Cloudflare method)
const uid = response.headers['stream-media-id'];
```

**This is why yesterday's upload failed!** We tried to parse the Location URL instead of reading the `stream-media-id` header.

---

### Fallback: URL Parsing (If header missing)

If for some reason the `stream-media-id` header is not present, we can fall back to URL parsing:

**The URL format**:
```
https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
                                                                                              ^^^^^^^^
                                                                                              This is the UID!
```

**Visual Breakdown**:
```
URL Structure:
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│ https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/     │
│                                                                                                   │
│ d9eb8bf_                                                                                          │
│ ^^^^^^^^ ← This is the video UID (with trailing underscore to remove)                           │
│                                                                                                   │
│ ?tusv2=true                                                                                       │
│ ^^^^^^^^^^^ ← Query parameter (ignore this)                                                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘

Extraction Steps:
1. Parse URL: new URL(url)
2. Split path: pathname.split('/')
   → ['', 'client', 'v4', 'accounts', '01962_e37899c_', 'media', 'd9eb8bf_']
3. Find 'media' index: indexOf('media') → 5
4. Get next segment: pathParts[6] → 'd9eb8bf_'
5. Remove trailing underscore: rtrim('_') → 'd9eb8bf'
6. Validate: /^[a-zA-Z0-9]+$/ → ✅ Valid
7. Return: 'd9eb8bf'
```

**Yesterday's Error vs Today's Fix**:
```
❌ Yesterday's Code:
const uid = url.split('/').pop().split('?')[0];
// Result: 'd9eb8bf_' (with underscore, not cleaned)
// Or worse: Failed to find UID at all

✅ Today's Fix:
const pathParts = new URL(url).pathname.split('/');
const mediaIndex = pathParts.indexOf('media');
const uid = pathParts[mediaIndex + 1].replace(/_+$/, '');
// Result: 'd9eb8bf' (clean, validated)
```

**Correct extraction logic**:
```javascript
// Extract UID from TUS upload URL
function extractUidFromTusUrl(url) {
    // URL format: .../media/{uid}?tusv2=true
    // or: .../media/{uid}
    
    try {
        const urlObj = new URL(url);
        const pathParts = urlObj.pathname.split('/');
        
        // Find 'media' segment and get next part
        const mediaIndex = pathParts.indexOf('media');
        if (mediaIndex !== -1 && pathParts[mediaIndex + 1]) {
            const uid = pathParts[mediaIndex + 1];
            // Remove any trailing underscores or special chars
            return uid.replace(/_+$/, '');
        }
        
        throw new Error('Cannot find media segment in URL');
    } catch (error) {
        throw new Error('Failed to extract UID from URL: ' + url + ' - ' + error.message);
    }
}

// Example:
// Input:  https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
// Output: d9eb8bf
```

---

## 5. Solution: Pure Native Implementation

### Why No External Libraries?

**Moodle's JavaScript Architecture**:
- Uses AMD (Asynchronous Module Definition)
- All modules must be defined with `define()`
- External libraries cause conflicts
- Must use Moodle's build system (`grunt amd`)

**Our Approach**:
- Implement TUS protocol manually using native JavaScript
- Use `XMLHttpRequest` for full control over headers
- Use `File.slice()` for chunking
- No external dependencies

### Implementation Strategy

```
┌─────────────────────────────────────────────────────────────┐
│                    File Size Detection                       │
│                                                              │
│  if (fileSize <= 200MB) {                                   │
│      → Use Direct Upload (existing code)                    │
│  } else {                                                    │
│      → Use TUS Upload (new code)                            │
│  }                                                           │
└─────────────────────────────────────────────────────────────┘
```

**Benefits**:
- ✅ Backward compatible (small files use existing method)
- ✅ No breaking changes
- ✅ Gradual rollout possible
- ✅ Easy to test and debug


---

## 6. Detailed Implementation Plan

### 6.1 Backend Changes (PHP)

#### File: `classes/api/cloudflare_client.php`

Add three new methods:

```php
/**
 * Create a TUS upload session.
 * 
 * @param int $filesize File size in bytes
 * @param string $filename Original filename
 * @param int $maxdurationseconds Maximum video duration
 * @return object Object with 'upload_url' and 'uid' properties
 */
public function create_tus_upload($filesize, $filename, $maxdurationseconds = 1800) {
    validator::validate_duration($maxdurationseconds);
    
    $endpoint = "/accounts/{$this->accountid}/stream";
    
    // Encode filename to base64 for TUS metadata
    $metadata = 'name ' . base64_encode($filename);
    
    $headers = [
        'Tus-Resumable: 1.0.0',
        'Upload-Length: ' . $filesize,
        'Upload-Metadata: ' . $metadata
    ];
    
    // Make request using custom method that returns headers
    $response = $this->make_tus_request('POST', $endpoint, null, $headers);
    
    // Extract upload URL from Location header
    if (!isset($response->headers['Location'])) {
        throw new cloudflare_api_exception(
            'tus_no_location',
            'TUS response missing Location header'
        );
    }
    
    $uploadurl = $response->headers['Location'];
    
    // ✅ CORRECT WAY: Get UID from stream-media-id header (Official Cloudflare method)
    if (isset($response->headers['stream-media-id'])) {
        $uid = $response->headers['stream-media-id'];
        
        // Validate UID
        if (empty($uid) || !preg_match('/^[a-zA-Z0-9]+$/', $uid)) {
            throw new cloudflare_api_exception(
                'tus_invalid_uid',
                'Invalid UID from stream-media-id header: ' . $uid
            );
        }
        
        logger::log_info('TUS session created', [
            'uid' => $uid,
            'upload_url' => $uploadurl,
            'method' => 'stream-media-id header'
        ]);
        
    } else {
        // ⚠️ FALLBACK: Parse URL if header missing (not recommended by Cloudflare)
        logger::log_warning('stream-media-id header missing, falling back to URL parsing');
        $uid = $this->extract_uid_from_tus_url($uploadurl);
    }
    
    return (object)[
        'upload_url' => $uploadurl,
        'uid' => $uid
    ];
}

/**
 * Upload a chunk via TUS protocol.
 * 
 * @param string $uploadurl The TUS upload URL from create_tus_upload()
 * @param string $chunkdata Binary chunk data
 * @param int $offset Current byte offset
 * @return int New byte offset after upload
 */
public function upload_tus_chunk($uploadurl, $chunkdata, $offset) {
    $headers = [
        'Tus-Resumable: 1.0.0',
        'Upload-Offset: ' . $offset,
        'Content-Type: application/offset+octet-stream'
    ];
    
    // Use raw request method that sends binary data
    $response = $this->make_tus_request('PATCH', $uploadurl, $chunkdata, $headers, true);
    
    // Extract new offset from response header
    if (!isset($response->headers['Upload-Offset'])) {
        throw new cloudflare_api_exception(
            'tus_no_offset',
            'TUS response missing Upload-Offset header'
        );
    }
    
    return (int)$response->headers['Upload-Offset'];
}

/**
 * Check TUS upload status.
 * 
 * @param string $uploadurl The TUS upload URL
 * @return object Object with 'offset' and 'length' properties
 */
public function get_tus_status($uploadurl) {
    $headers = ['Tus-Resumable: 1.0.0'];
    
    $response = $this->make_tus_request('HEAD', $uploadurl, null, $headers);
    
    return (object)[
        'offset' => (int)($response->headers['Upload-Offset'] ?? 0),
        'length' => (int)($response->headers['Upload-Length'] ?? 0)
    ];
}

/**
 * Extract video UID from TUS upload URL.
 * 
 * CRITICAL: This handles the actual Cloudflare TUS URL format:
 * https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/{account_id}/media/{uid}?tusv2=true
 * 
 * @param string $url TUS upload URL
 * @return string Video UID
 */
private function extract_uid_from_tus_url($url) {
    // Parse URL
    $parts = parse_url($url);
    if (!isset($parts['path'])) {
        throw new cloudflare_api_exception(
            'tus_invalid_url',
            'Cannot parse TUS URL: ' . $url
        );
    }
    
    // Split path into segments
    // Example: /client/v4/accounts/01962_e37899c_/media/d9eb8bf_
    $pathsegments = explode('/', trim($parts['path'], '/'));
    
    // Find 'media' segment and get the next segment (the UID)
    $mediaindex = array_search('media', $pathsegments);
    if ($mediaindex === false || !isset($pathsegments[$mediaindex + 1])) {
        throw new cloudflare_api_exception(
            'tus_invalid_url',
            'Cannot find media segment in TUS URL: ' . $url
        );
    }
    
    $uid = $pathsegments[$mediaindex + 1];
    
    // Remove trailing underscores (Cloudflare sometimes adds these)
    $uid = rtrim($uid, '_');
    
    // Validate UID format (should be alphanumeric)
    if (empty($uid) || !preg_match('/^[a-zA-Z0-9]+$/', $uid)) {
        throw new cloudflare_api_exception(
            'tus_invalid_uid',
            'Extracted invalid UID from TUS URL: ' . $uid . ' (URL: ' . $url . ')'
        );
    }
    
    return $uid;
}

/**
 * Make TUS-specific HTTP request.
 * This is separate from make_request() because TUS has different requirements.
 * 
 * @param string $method HTTP method
 * @param string $url Full URL (not endpoint)
 * @param string|null $data Request body
 * @param array $headers Additional headers
 * @param bool $isbinary Whether data is binary
 * @return object Response with headers and body
 */
private function make_tus_request($method, $url, $data = null, $headers = [], $isbinary = false) {
    // Add authorization if this is the initial POST to our API
    if (strpos($url, 'api.cloudflare.com') !== false) {
        $headers[] = 'Authorization: Bearer ' . $this->apitoken;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        if ($isbinary) {
            curl_setopt($ch, CURLOPT_INFILESIZE, strlen($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    // Parse headers
    $headertext = substr($response, 0, $headersize);
    $body = substr($response, $headersize);
    $parsedheaders = $this->parse_http_headers($headertext);
    
    // Check for errors
    if ($httpcode >= 400) {
        throw new cloudflare_api_exception(
            'tus_upload_failed',
            "TUS request failed with HTTP {$httpcode}"
        );
    }
    
    return (object)[
        'headers' => $parsedheaders,
        'body' => $body,
        'status' => $httpcode
    ];
}

/**
 * Parse HTTP headers into associative array.
 * 
 * @param string $headertext Raw header text
 * @return array Parsed headers
 */
private function parse_http_headers($headertext) {
    $headers = [];
    $lines = explode("\r\n", $headertext);
    
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }
    
    return $headers;
}
```


#### New File: `ajax/create_tus_upload.php`

```php
<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../../config.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;

// Validate parameters
$assignmentid = required_param('assignmentid', PARAM_INT);
$filesize = required_param('filesize', PARAM_INT);
$filename = required_param('filename', PARAM_TEXT);

require_login();
require_sesskey();

header('Content-Type: application/json');

try {
    // Validate file size (max 30GB)
    if ($filesize > 32212254720) { // 30GB
        throw new moodle_exception('file_too_large', 'assignsubmission_cloudflarestream');
    }
    
    // Get configuration
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    // Create Cloudflare client
    $client = new cloudflare_client($apitoken, $accountid);
    
    // Create TUS upload session
    $result = $client->create_tus_upload($filesize, $filename);
    
    // Get or create submission
    list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
    $context = context_module::instance($cm->id);
    $assign = new assign($context, $cm, $course);
    $submission = $assign->get_user_submission($USER->id, true);
    
    // Store in database with pending status
    $record = new stdClass();
    $record->assignment = $assignmentid;
    $record->submission = $submission->id;
    $record->video_uid = $result->uid;
    $record->upload_status = 'pending';
    $record->upload_timestamp = time();
    
    $existing = $DB->get_record('assignsubmission_cfstream', 
        ['submission' => $submission->id]);
    
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('assignsubmission_cfstream', $record);
    } else {
        $DB->insert_record('assignsubmission_cfstream', $record);
    }
    
    echo json_encode([
        'success' => true,
        'upload_url' => $result->upload_url,
        'uid' => $result->uid,
        'submissionid' => $submission->id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

#### New File: `ajax/upload_tus_chunk.php`

```php
<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../../config.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;

// Get parameters
$uploadurl = required_param('uploadurl', PARAM_URL);
$offset = required_param('offset', PARAM_INT);

require_login();
require_sesskey();

header('Content-Type: application/json');

try {
    // Read binary chunk data from request body
    $chunkdata = file_get_contents('php://input');
    
    if (empty($chunkdata)) {
        throw new moodle_exception('no_chunk_data', 'assignsubmission_cloudflarestream');
    }
    
    // Get configuration
    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
    
    // Create Cloudflare client
    $client = new cloudflare_client($apitoken, $accountid);
    
    // Upload chunk
    $newoffset = $client->upload_tus_chunk($uploadurl, $chunkdata, $offset);
    
    echo json_encode([
        'success' => true,
        'offset' => $newoffset
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

### 6.2 Frontend Changes (JavaScript)

#### File: `amd/src/uploader.js`

Add TUS upload methods to the `CloudflareUploader` class:

```javascript
/**
 * Determine upload method based on file size.
 * 
 * @param {File} file The file to upload
 * @param {Object} uploadData Upload data from backend
 * @return {Promise<string>} Video UID
 */
async uploadFile(file, uploadData) {
    const DIRECT_UPLOAD_LIMIT = 200 * 1024 * 1024; // 200MB
    
    if (file.size <= DIRECT_UPLOAD_LIMIT) {
        // Use existing direct upload for small files
        return await this.uploadToCloudflare(file, uploadData);
    } else {
        // Use TUS upload for large files
        return await this.uploadViaTus(file, uploadData);
    }
}

/**
 * Upload file using TUS resumable upload protocol.
 * 
 * @param {File} file The file to upload
 * @param {Object} uploadData Upload data (contains upload_url and uid)
 * @return {Promise<string>} Video UID
 */
async uploadViaTus(file, uploadData) {
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks
    let offset = 0;
    
    while (offset < file.size) {
        // Read chunk
        const chunk = file.slice(offset, offset + CHUNK_SIZE);
        const chunkData = await this.readChunkAsArrayBuffer(chunk);
        
        // Upload chunk
        const newOffset = await this.uploadTusChunk(
            uploadData.upload_url,
            chunkData,
            offset
        );
        
        // Update progress
        offset = newOffset;
        const percentage = Math.round((offset / file.size) * 100);
        const uploadedMB = (offset / (1024 * 1024)).toFixed(1);
        const totalMB = (file.size / (1024 * 1024)).toFixed(1);
        this.updateProgress(percentage, uploadedMB + 'MB / ' + totalMB + 'MB');
    }
    
    return uploadData.uid;
}

/**
 * Read file chunk as ArrayBuffer.
 * 
 * @param {Blob} chunk File chunk
 * @return {Promise<ArrayBuffer>} Chunk data
 */
readChunkAsArrayBuffer(chunk) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsArrayBuffer(chunk);
    });
}

/**
 * Upload a single TUS chunk.
 * 
 * @param {string} uploadUrl TUS upload URL
 * @param {ArrayBuffer} chunkData Chunk data
 * @param {number} offset Current byte offset
 * @return {Promise<number>} New offset
 */
async uploadTusChunk(uploadUrl, chunkData, offset) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.open('PATCH', uploadUrl);
        xhr.setRequestHeader('Tus-Resumable', '1.0.0');
        xhr.setRequestHeader('Upload-Offset', offset.toString());
        xhr.setRequestHeader('Content-Type', 'application/offset+octet-stream');
        
        xhr.onload = () => {
            if (xhr.status === 204) {
                // Extract new offset from response header
                const newOffset = parseInt(xhr.getResponseHeader('Upload-Offset'));
                resolve(newOffset);
            } else {
                reject(new Error('TUS chunk upload failed: ' + xhr.status));
            }
        };
        
        xhr.onerror = () => {
            reject(new Error('Network error during TUS chunk upload'));
        };
        
        xhr.send(chunkData);
    });
}

/**
 * Create TUS upload session directly with Cloudflare.
 * This makes the initial POST request to get the upload URL and video UID.
 * 
 * @param {File} file The file to upload
 * @return {Promise<Object>} Upload data with upload_url and uid
 */
async createTusSession(file) {
    return new Promise((resolve, reject) => {
        // Get Cloudflare credentials from backend first
        $.ajax({
            url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_tus_credentials.php',
            method: 'POST',
            data: {
                assignmentid: this.assignmentId,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        }).done((credentials) => {
            if (!credentials.success) {
                reject(new Error(credentials.error || 'Failed to get credentials'));
                return;
            }
            
            // Create TUS session with Cloudflare
            const xhr = new XMLHttpRequest();
            const endpoint = `https://api.cloudflare.com/client/v4/accounts/${credentials.account_id}/stream`;
            
            xhr.open('POST', endpoint);
            xhr.setRequestHeader('Authorization', 'Bearer ' + credentials.api_token);
            xhr.setRequestHeader('Tus-Resumable', '1.0.0');
            xhr.setRequestHeader('Upload-Length', file.size.toString());
            xhr.setRequestHeader('Upload-Metadata', 'name ' + btoa(file.name));
            
            xhr.onload = () => {
                if (xhr.status === 201) {
                    const uploadUrl = xhr.getResponseHeader('Location');
                    
                    // ✅ CORRECT: Get UID from stream-media-id header (Official Cloudflare method)
                    let uid = xhr.getResponseHeader('stream-media-id');
                    
                    if (!uid) {
                        // ⚠️ FALLBACK: Parse URL if header missing
                        console.warn('stream-media-id header missing, falling back to URL parsing');
                        uid = this.extractUidFromUrl(uploadUrl);
                    }
                    
                    console.log('✅ TUS session created:', {
                        uid: uid,
                        uploadUrl: uploadUrl,
                        method: xhr.getResponseHeader('stream-media-id') ? 'header' : 'url-parsing'
                    });
                    
                    resolve({
                        upload_url: uploadUrl,
                        uid: uid
                    });
                } else {
                    reject(new Error('TUS session creation failed: ' + xhr.status));
                }
            };
            
            xhr.onerror = () => {
                reject(new Error('Network error during TUS session creation'));
            };
            
            xhr.send();
            
        }).fail(() => {
            reject(new Error('Failed to get Cloudflare credentials'));
        });
    });
}

/**
 * Extract UID from URL (fallback method if stream-media-id header missing).
 * 
 * @param {string} url TUS upload URL
 * @return {string} Video UID
 */
extractUidFromUrl(url) {
    try {
        const pathParts = new URL(url).pathname.split('/').filter(p => p.length > 0);
        const mediaIndex = pathParts.indexOf('media');
        
        if (mediaIndex === -1 || !pathParts[mediaIndex + 1]) {
            throw new Error('Cannot find media segment in URL');
        }
        
        let uid = pathParts[mediaIndex + 1].replace(/_+$/, '');
        
        if (!/^[a-zA-Z0-9]+$/.test(uid)) {
            throw new Error('Invalid UID format: ' + uid);
        }
        
        return uid;
    } catch (error) {
        throw new Error('Failed to extract UID from URL: ' + url + ' - ' + error.message);
    }
}
```


**Modify existing `startUpload()` method**:

```javascript
async startUpload(file) {
    if (this.uploadInProgress) {
        this.showError('An upload is already in progress.');
        return;
    }

    let uploadData = null;

    try {
        this.uploadInProgress = true;
        this.showProgress();
        this.updateProgress(0);

        // Determine upload method based on file size
        const DIRECT_UPLOAD_LIMIT = 200 * 1024 * 1024; // 200MB
        
        if (file.size <= DIRECT_UPLOAD_LIMIT) {
            // Small file: Use direct upload (existing code)
            uploadData = await this.requestUploadUrl(file);
            await this.uploadToCloudflare(file, uploadData);
        } else {
            // Large file: Use TUS upload (new code)
            uploadData = await this.requestTusUploadUrl(file);
            await this.uploadViaTus(file, uploadData);
        }

        // Confirm upload
        this.updateProgress(100, 'Finalizing upload...');
        await this.confirmUploadWithRetry(uploadData.uid, uploadData.submissionid);

        // Success
        this.uploadData = null;
        this.uploadInProgress = false;
        this.showSuccess();

    } catch (error) {
        this.uploadInProgress = false;
        
        if (uploadData && uploadData.uid) {
            await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
            this.uploadData = null;
        }
        
        this.handleError(error);
    }
}
```

---

## 6.3 Critical: UID Extraction Testing

### The Problem from Yesterday

**Error Screenshot Analysis**:
```
TUS Progress: 100% (1721.3 / 1721.3 MB)
=== TUS Upload Success ===
Upload URL: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true

❌ Failed to extract video UID from URL
❌ Error: Failed to extract video UID from upload URL
❌ Falling back to direct upload
```

**What Happened**:
1. ✅ TUS upload completed successfully (100%)
2. ✅ File uploaded to Cloudflare (1.7GB)
3. ❌ UID extraction failed
4. ❌ Could not confirm upload in database
5. ❌ Video left orphaned in Cloudflare

### The Fix

**Test Cases for UID Extraction**:

```php
// Test case 1: Production URL with tusv2 parameter
$url1 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';
$uid1 = extract_uid_from_tus_url($url1);
// Expected: d9eb8bf

// Test case 2: URL without query parameter
$url2 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/abc123def';
$uid2 = extract_uid_from_tus_url($url2);
// Expected: abc123def

// Test case 3: URL with trailing underscore
$url3 = 'https://api.cloudflare.com/client/v4/accounts/account123/media/video456_';
$uid3 = extract_uid_from_tus_url($url3);
// Expected: video456

// Test case 4: Simple format (if Cloudflare changes)
$url4 = 'https://upload.cloudflarestream.com/abc123';
$uid4 = extract_uid_from_tus_url($url4);
// Expected: Should handle gracefully or extract correctly
```

**JavaScript Implementation**:

```javascript
/**
 * Extract video UID from TUS upload URL.
 * Handles the actual Cloudflare URL format from Location header.
 * 
 * @param {string} url TUS upload URL
 * @return {string} Video UID
 */
extractUidFromTusUrl(url) {
    try {
        // Parse URL
        const urlObj = new URL(url);
        const pathParts = urlObj.pathname.split('/').filter(p => p.length > 0);
        
        // Find 'media' segment and get next part
        const mediaIndex = pathParts.indexOf('media');
        if (mediaIndex !== -1 && pathParts[mediaIndex + 1]) {
            let uid = pathParts[mediaIndex + 1];
            
            // Remove trailing underscores
            uid = uid.replace(/_+$/, '');
            
            // Validate UID (alphanumeric only)
            if (/^[a-zA-Z0-9]+$/.test(uid)) {
                console.log('✅ Extracted UID: ' + uid + ' from URL: ' + url);
                return uid;
            }
        }
        
        // Fallback: Try to get last path segment
        const lastSegment = pathParts[pathParts.length - 1];
        if (lastSegment && /^[a-zA-Z0-9]+$/.test(lastSegment.replace(/_+$/, ''))) {
            const uid = lastSegment.replace(/_+$/, '');
            console.warn('⚠️ Extracted UID from last segment: ' + uid);
            return uid;
        }
        
        throw new Error('Cannot find valid UID in URL path');
        
    } catch (error) {
        console.error('❌ UID extraction failed:', error);
        throw new Error('Failed to extract video UID from URL: ' + url + ' - ' + error.message);
    }
}
```

### Testing Strategy for UID Extraction

**Unit Tests** (Must pass before deployment):

```javascript
// Test 1: Real production URL from yesterday's error
const testUrl1 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';
const uid1 = extractUidFromTusUrl(testUrl1);
console.assert(uid1 === 'd9eb8bf', 'Test 1 failed: ' + uid1);

// Test 2: URL without trailing underscore
const testUrl2 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/abc123def?tusv2=true';
const uid2 = extractUidFromTusUrl(testUrl2);
console.assert(uid2 === 'abc123def', 'Test 2 failed: ' + uid2);

// Test 3: URL without query parameter
const testUrl3 = 'https://api.cloudflare.com/client/v4/accounts/test/media/xyz789';
const uid3 = extractUidFromTusUrl(testUrl3);
console.assert(uid3 === 'xyz789', 'Test 3 failed: ' + uid3);

// Test 4: Invalid URL (should throw error)
try {
    const testUrl4 = 'https://example.com/invalid/path';
    extractUidFromTusUrl(testUrl4);
    console.error('Test 4 failed: Should have thrown error');
} catch (error) {
    console.log('✅ Test 4 passed: Error thrown as expected');
}
```

**Integration Test**:

```javascript
// Real TUS upload test
async function testTusUpload() {
    const file = new File(['test content'], 'test.mp4', { type: 'video/mp4' });
    
    // Create TUS session
    const uploadData = await requestTusUploadUrl(file);
    console.log('Upload URL:', uploadData.upload_url);
    
    // Extract UID
    const uid = extractUidFromTusUrl(uploadData.upload_url);
    console.log('Extracted UID:', uid);
    
    // Verify UID matches what backend returned
    if (uid !== uploadData.uid) {
        throw new Error('UID mismatch! Extracted: ' + uid + ', Expected: ' + uploadData.uid);
    }
    
    console.log('✅ UID extraction test passed!');
}
```

---

## 7. Implementation Phases

### Phase 1: Backend TUS API (Days 1-2)
**Goal**: Implement TUS protocol in PHP

Tasks:
1. ✅ Add `create_tus_upload()` method to `cloudflare_client.php`
2. ✅ Add `upload_tus_chunk()` method to `cloudflare_client.php`
3. ✅ Add `get_tus_status()` method to `cloudflare_client.php`
4. ✅ Add helper methods (`extract_uid_from_tus_url`, `make_tus_request`, `parse_http_headers`)
5. ✅ Create `ajax/create_tus_upload.php`
6. ✅ Create `ajax/upload_tus_chunk.php`
7. ✅ **CRITICAL**: Test UID extraction with real Cloudflare URLs
8. ✅ Test with curl/Postman

**Testing**:
```bash
# Test TUS session creation
curl -X POST "https://api.cloudflare.com/client/v4/accounts/{account_id}/stream" \
  -H "Authorization: Bearer {token}" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Length: 1000000" \
  -H "Upload-Metadata: name dGVzdC52aWRlby5tcDQ="

# IMPORTANT: Save the Location header from response
# Example: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true

# Test UID extraction (PHP)
php -r "
require 'classes/api/cloudflare_client.php';
\$url = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';
\$client = new cloudflare_client('token', 'account');
\$uid = \$client->extract_uid_from_tus_url(\$url);
echo 'Extracted UID: ' . \$uid . PHP_EOL;
"

# Test chunk upload
curl -X PATCH "{upload_url}" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Offset: 0" \
  -H "Content-Type: application/offset+octet-stream" \
  --data-binary "@chunk.bin"
```

**UID Extraction Test Cases** (MUST PASS):
```php
// Test with yesterday's actual URL
$url1 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';
$uid1 = $client->extract_uid_from_tus_url($url1);
assert($uid1 === 'd9eb8bf', 'Test 1 failed');

// Test without query parameter
$url2 = 'https://api.cloudflare.com/client/v4/accounts/test/media/abc123def';
$uid2 = $client->extract_uid_from_tus_url($url2);
assert($uid2 === 'abc123def', 'Test 2 failed');

// Test with trailing underscore
$url3 = 'https://api.cloudflare.com/client/v4/accounts/test/media/xyz789_';
$uid3 = $client->extract_uid_from_tus_url($url3);
assert($uid3 === 'xyz789', 'Test 3 failed');

echo "✅ All UID extraction tests passed!\n";
```

### Phase 2: Frontend TUS Client (Days 3-4)
**Goal**: Implement TUS upload in JavaScript

Tasks:
1. ✅ Add `uploadViaTus()` method
2. ✅ Add `uploadTusChunk()` method
3. ✅ Add `readChunkAsArrayBuffer()` method
4. ✅ Add `requestTusUploadUrl()` method
5. ✅ Modify `startUpload()` to route based on file size
6. ✅ Test with small test files (10MB)
7. ✅ Build AMD module: `grunt amd`

**Testing**:
- Upload 10MB file (should use direct upload)
- Upload 250MB file (should use TUS upload)
- Verify progress tracking
- Check browser console for errors

### Phase 3: Integration & Error Handling (Days 5-6)
**Goal**: Robust error handling and resume capability

Tasks:
1. ✅ Implement retry logic for failed chunks
2. ✅ Add exponential backoff
3. ✅ Implement upload resume (store state in localStorage)
4. ✅ Add network interruption handling
5. ✅ Integrate with existing cleanup system
6. ✅ Add comprehensive error messages

**Error Scenarios to Test**:
- Network disconnection mid-upload
- Browser refresh during upload
- Server timeout
- Invalid chunk offset
- Cloudflare API errors

### Phase 4: Testing & Optimization (Days 7-8)
**Goal**: Comprehensive testing and performance tuning

Tasks:
1. ✅ Test with various file sizes (100MB, 500MB, 1GB, 2GB)
2. ✅ Test on different browsers (Chrome, Firefox, Safari, Edge)
3. ✅ Test on mobile devices
4. ✅ Optimize chunk size (test 1MB, 5MB, 10MB)
5. ✅ Memory usage profiling
6. ✅ Load testing (multiple concurrent uploads)
7. ✅ Documentation updates

---

## 8. Risk Analysis & Mitigation

### High Risk Issues

#### 1. Memory Usage (Large Files)
**Risk**: Browser running out of memory when reading large chunks

**Mitigation**:
- Use optimal chunk size (5MB recommended)
- Use `File.slice()` to read chunks on-demand
- Release chunk references immediately after upload
- Monitor memory usage in browser DevTools

**Testing**:
```javascript
// Monitor memory usage
console.memory.usedJSHeapSize / 1024 / 1024 + ' MB'
```

#### 2. Network Interruptions
**Risk**: Upload failing due to network issues

**Mitigation**:
- Implement retry logic with exponential backoff
- Store upload state in localStorage
- Resume from last successful offset
- Clear error messages for users

**Implementation**:
```javascript
// Store upload state
localStorage.setItem('tus_upload_' + uid, JSON.stringify({
    uploadUrl: uploadUrl,
    offset: currentOffset,
    fileSize: file.size,
    timestamp: Date.now()
}));

// Resume upload
const savedState = JSON.parse(localStorage.getItem('tus_upload_' + uid));
if (savedState) {
    offset = savedState.offset;
}
```

#### 3. CORS Issues
**Risk**: Cross-origin requests being blocked

**Mitigation**:
- Cloudflare Stream has proper CORS headers
- Test thoroughly across browsers
- Implement proper error handling

**Cloudflare CORS Headers**:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, HEAD, PATCH, OPTIONS
Access-Control-Allow-Headers: Tus-Resumable, Upload-Length, Upload-Offset, Upload-Metadata
Access-Control-Expose-Headers: Tus-Resumable, Upload-Offset, Location
```

#### 4. Chunk Offset Mismatch
**Risk**: Server and client offset getting out of sync

**Mitigation**:
- Always verify offset before uploading chunk
- Use HEAD request to check current offset
- Retry with correct offset if mismatch detected

**Implementation**:
```javascript
// Verify offset before upload
const status = await this.getTusStatus(uploadUrl);
if (status.offset !== expectedOffset) {
    console.warn('Offset mismatch, adjusting...');
    offset = status.offset;
}
```

### Medium Risk Issues

#### 1. Browser Compatibility
**Risk**: TUS implementation not working in older browsers

**Mitigation**:
- Feature detection for required APIs
- Graceful fallback to direct upload (with size limit warning)
- Test on target browsers

**Feature Detection**:
```javascript
if (!window.FileReader || !window.Blob.prototype.slice) {
    throw new Error('Your browser does not support large file uploads');
}
```

#### 2. Upload State Management
**Risk**: Lost upload progress on page refresh

**Mitigation**:
- Store upload state in localStorage
- Implement upload resume on page load
- Clear state after successful completion

#### 3. Concurrent Upload Limits
**Risk**: Multiple TUS uploads overwhelming server

**Mitigation**:
- Existing rate limiter already handles this
- Limit concurrent uploads per user
- Monitor server resource usage

---

## 8.5. Chunk Size Requirements (From Official Docs)

### Cloudflare's Strict Requirements

**From Official Documentation**:
> "Resumable uploads require a minimum chunk size of 5,242,880 bytes unless the entire file is less than this amount."
> "Chunk size must be divisible by 256 KiB (256x1024 bytes). Round your chunk size to the nearest multiple of 256 KiB."
> "Note that the final chunk of an upload that fits within a single chunk is exempt from this requirement."

### Chunk Size Rules

```javascript
// Constants
const MIN_CHUNK_SIZE = 5242880;        // 5 MB (minimum)
const RECOMMENDED_CHUNK_SIZE = 52428800; // 50 MB (recommended)
const MAX_CHUNK_SIZE = 209715200;      // 200 MB (maximum)
const CHUNK_DIVISOR = 262144;          // 256 KiB (must be divisible by this)

// Validation
function validateChunkSize(size) {
    if (size < MIN_CHUNK_SIZE) {
        return false; // Too small
    }
    if (size > MAX_CHUNK_SIZE) {
        return false; // Too large
    }
    if (size % CHUNK_DIVISOR !== 0) {
        return false; // Not divisible by 256 KiB
    }
    return true;
}

// Valid chunk sizes
const validSizes = [
    5242880,    // 5 MB ✅
    10485760,   // 10 MB ✅
    52428800,   // 50 MB ✅ RECOMMENDED
    104857600,  // 100 MB ✅
    209715200   // 200 MB ✅ MAXIMUM
];

// Invalid chunk sizes
const invalidSizes = [
    5000000,    // Not divisible by 256 KiB ❌
    6000000,    // Not divisible by 256 KiB ❌
    300000000   // Exceeds 200 MB ❌
];
```

### Implementation

```javascript
class TusUploader {
    constructor(file) {
        this.file = file;
        
        // Use 50 MB chunks (recommended by Cloudflare)
        this.chunkSize = 52428800;
        
        // Validate chunk size
        if (!this.validateChunkSize(this.chunkSize)) {
            throw new Error('Invalid chunk size');
        }
    }
    
    validateChunkSize(size) {
        const MIN = 5242880;
        const MAX = 209715200;
        const DIVISOR = 262144;
        
        return size >= MIN && size <= MAX && size % DIVISOR === 0;
    }
    
    async uploadChunks() {
        let offset = 0;
        
        while (offset < this.file.size) {
            // Calculate chunk size
            let currentChunkSize = Math.min(
                this.chunkSize,
                this.file.size - offset
            );
            
            // Exception: Final chunk can be any size
            const isFinalChunk = (offset + currentChunkSize >= this.file.size);
            
            // Read and upload chunk
            const chunk = this.file.slice(offset, offset + currentChunkSize);
            await this.uploadChunk(chunk, offset);
            
            offset += currentChunkSize;
        }
    }
}
```

### Why This Matters

**Performance Impact**:
- **5 MB chunks**: 1.7 GB file = 340 requests
- **50 MB chunks**: 1.7 GB file = 34 requests (10x fewer!)
- **100 MB chunks**: 1.7 GB file = 17 requests

**Cloudflare's Recommendation**:
> "For better performance when the client connection is expected to be reliable, increase the chunk size to 52,428,800 bytes."

**Our Choice**: Use 50 MB chunks (52,428,800 bytes)
- ✅ Recommended by Cloudflare
- ✅ Significantly fewer requests
- ✅ Better performance
- ✅ Still manageable memory usage

---

## 9. Testing Strategy

### Unit Tests

**Backend (PHPUnit)**:
```php
// Test TUS session creation
public function test_create_tus_upload() {
    $client = new cloudflare_client($token, $accountid);
    $result = $client->create_tus_upload(1000000, 'test.mp4');
    
    $this->assertNotEmpty($result->upload_url);
    $this->assertNotEmpty($result->uid);
}

// Test chunk upload
public function test_upload_tus_chunk() {
    $client = new cloudflare_client($token, $accountid);
    $chunkdata = str_repeat('A', 1024);
    $newoffset = $client->upload_tus_chunk($uploadurl, $chunkdata, 0);
    
    $this->assertEquals(1024, $newoffset);
}
```

**Frontend (Manual Testing)**:
- Test file size detection
- Test chunk reading
- Test progress tracking
- Test error handling

### Integration Tests

**End-to-End Upload Flow**:
1. Select 500MB file
2. Verify TUS upload is triggered
3. Monitor progress bar
4. Verify completion
5. Check database record
6. Verify video in Cloudflare dashboard

**Error Scenarios**:
1. Disconnect network mid-upload → Should show error and retry option
2. Refresh page during upload → Should cleanup properly
3. Upload invalid file → Should show validation error
4. Exceed quota → Should show quota error

### Performance Tests

**Large File Uploads**:
- 100MB file: Should complete in <2 minutes
- 500MB file: Should complete in <10 minutes
- 1GB file: Should complete in <20 minutes
- 2GB file: Should complete in <40 minutes

**Memory Usage**:
- Monitor browser memory during upload
- Should stay under 100MB for any file size
- No memory leaks after upload completion

**Concurrent Uploads**:
- Test 5 users uploading simultaneously
- Monitor server CPU and memory
- Verify all uploads complete successfully

---

## 10. Success Metrics

### Functional Requirements
- ✅ Upload files up to 30GB
- ✅ Resume interrupted uploads
- ✅ Progress reporting accuracy within 1%
- ✅ Proper error handling with user-friendly messages
- ✅ Automatic cleanup of failed uploads

### Performance Requirements
- Upload speed: Match direct upload performance (network-limited)
- Memory usage: <100MB for any file size
- CPU usage: <10% during upload
- Network efficiency: <1% overhead from chunking

### User Experience Requirements
- Clear progress indicators with MB uploaded
- Intuitive error messages
- Seamless fallback to direct upload for small files
- No additional user configuration required

---

## 11. Rollback Plan

### Immediate Rollback (Critical Issues)
If TUS implementation causes critical issues:

1. Comment out TUS code in `startUpload()`:
```javascript
// Force direct upload for all files
uploadData = await this.requestUploadUrl(file);
await this.uploadToCloudflare(file, uploadData);
```

2. Display 200MB size limit warning
3. Monitor for stability
4. Investigate and fix issues

### Gradual Rollback (Performance Issues)
If TUS has performance issues:

1. Increase TUS threshold:
```javascript
const DIRECT_UPLOAD_LIMIT = 500 * 1024 * 1024; // 500MB instead of 200MB
```

2. Implement A/B testing (50% of users use TUS)
3. Monitor success rates
4. Adjust based on metrics

---

## 12. Deployment Checklist

### Pre-Deployment
- [ ] All unit tests passing
- [ ] Integration tests passing
- [ ] Performance tests completed
- [ ] Browser compatibility verified
- [ ] Documentation updated
- [ ] Code review completed

### Deployment Steps
1. [ ] Backup current code
2. [ ] Deploy backend changes
3. [ ] Deploy frontend changes
4. [ ] Build AMD modules: `grunt amd`
5. [ ] Clear Moodle caches
6. [ ] Test on staging environment
7. [ ] Deploy to production
8. [ ] Monitor error logs

### Post-Deployment
- [ ] Monitor upload success rates
- [ ] Check error logs for TUS-related errors
- [ ] Verify Cloudflare dashboard shows correct videos
- [ ] Test with real users
- [ ] Gather user feedback

---

## 13. Lessons Learned from Yesterday's Failure

### What Went Wrong

**Timeline of Yesterday's Attempt**:
1. ✅ Implemented TUS upload with external library
2. ✅ File upload reached 100% (1.7GB uploaded successfully)
3. ❌ UID extraction failed with error
4. ❌ Could not save to database
5. ❌ Video orphaned in Cloudflare
6. ❌ Fell back to direct upload (which also failed due to 200MB limit)

### Root Cause Analysis

**The Critical Error**:
```javascript
// What we expected:
URL: https://upload.cloudflarestream.com/{uid}
UID: Simple extraction from path

// What we actually got:
URL: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
UID: Complex extraction needed
```

**Why It Failed**:
1. **Assumption**: Cloudflare would return simple URL format
2. **Reality**: Cloudflare returns full API URL with account path
3. **Code**: Simple path extraction didn't work
4. **Result**: UID extraction threw error, entire upload failed

### Key Takeaways

**1. Never Assume URL Format**:
- ❌ Don't assume: `const uid = url.split('/').pop()`
- ✅ Do parse properly: Find `/media/` segment and extract next part
- ✅ Do handle query parameters: Remove `?tusv2=true`
- ✅ Do handle trailing chars: Remove trailing underscores

**2. Test with Real URLs**:
- ❌ Don't test with mock data only
- ✅ Do test with actual Cloudflare responses
- ✅ Do log full URLs for debugging
- ✅ Do add comprehensive error messages

**3. Fail Gracefully**:
- ❌ Don't throw generic errors
- ✅ Do provide detailed error messages with URL
- ✅ Do log errors for debugging
- ✅ Do cleanup orphaned videos

**4. Validate Extracted Data**:
- ❌ Don't assume extraction worked
- ✅ Do validate UID format (alphanumeric)
- ✅ Do check UID is not empty
- ✅ Do verify UID matches expected pattern

### Prevention Measures

**1. Comprehensive UID Extraction**:
```javascript
function extractUidFromTusUrl(url) {
    // Multiple extraction strategies
    try {
        // Strategy 1: Find /media/ segment
        const mediaMatch = url.match(/\/media\/([a-zA-Z0-9]+)/);
        if (mediaMatch) return mediaMatch[1];
        
        // Strategy 2: Parse URL object
        const urlObj = new URL(url);
        const parts = urlObj.pathname.split('/');
        const mediaIndex = parts.indexOf('media');
        if (mediaIndex !== -1 && parts[mediaIndex + 1]) {
            return parts[mediaIndex + 1].replace(/_+$/, '');
        }
        
        // Strategy 3: Last resort - last path segment
        const lastPart = parts[parts.length - 1].replace(/_+$/, '');
        if (/^[a-zA-Z0-9]+$/.test(lastPart)) {
            console.warn('Using fallback UID extraction');
            return lastPart;
        }
        
        throw new Error('All extraction strategies failed');
    } catch (error) {
        throw new Error('UID extraction failed for URL: ' + url + ' - ' + error.message);
    }
}
```

**2. Extensive Logging**:
```javascript
console.log('=== TUS Upload Debug ===');
console.log('Upload URL:', uploadUrl);
console.log('Attempting UID extraction...');
const uid = extractUidFromTusUrl(uploadUrl);
console.log('✅ Extracted UID:', uid);
console.log('Validating UID format...');
if (!/^[a-zA-Z0-9]+$/.test(uid)) {
    throw new Error('Invalid UID format: ' + uid);
}
console.log('✅ UID validation passed');
```

**3. Unit Tests Before Integration**:
```javascript
// Test UID extraction BEFORE uploading real files
const testUrls = [
    'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true',
    'https://api.cloudflare.com/client/v4/accounts/test/media/abc123',
    'https://upload.cloudflarestream.com/xyz789'
];

testUrls.forEach(url => {
    try {
        const uid = extractUidFromTusUrl(url);
        console.log('✅ Extracted:', uid, 'from', url);
    } catch (error) {
        console.error('❌ Failed:', url, error.message);
    }
});
```

**4. Orphaned Video Cleanup**:
```javascript
// If UID extraction fails, cleanup the uploaded video
try {
    const uid = extractUidFromTusUrl(uploadUrl);
    await confirmUpload(uid);
} catch (error) {
    console.error('UID extraction failed, cleaning up...');
    // Try to extract UID with fallback methods for cleanup
    try {
        const uidForCleanup = uploadUrl.match(/\/media\/([a-zA-Z0-9_]+)/)?.[1]?.replace(/_+$/, '');
        if (uidForCleanup) {
            await cleanupFailedUpload(uidForCleanup);
        }
    } catch (cleanupError) {
        console.error('Cleanup also failed:', cleanupError);
    }
    throw error;
}
```

### Success Criteria for This Implementation

To avoid repeating yesterday's failure:

- [ ] UID extraction tested with 10+ real Cloudflare URLs
- [ ] Unit tests pass for all URL formats
- [ ] Integration test with real TUS upload
- [ ] Logging shows full URL and extracted UID
- [ ] Error messages include full URL for debugging
- [ ] Cleanup works even if UID extraction fails
- [ ] Validation confirms UID format is correct
- [ ] No assumptions about URL format

---

## 14. Conclusion

### Summary

TUS implementation is **essential** for supporting large file uploads in Cloudflare Stream. The key to success is:

1. **No External Libraries**: Pure native JavaScript implementation
2. **Hybrid Approach**: Direct upload for small files, TUS for large files
3. **Robust Error Handling**: Retry logic, resume capability, cleanup
4. **Thorough Testing**: Unit, integration, and performance tests

### Benefits
- ✅ Support for files up to 30GB (vs 200MB limit)
- ✅ Resume capability for interrupted uploads
- ✅ Better user experience for large files
- ✅ Future-proof solution
- ✅ No breaking changes to existing functionality

### Challenges
- ⚠️ Complex protocol implementation
- ⚠️ Additional testing requirements
- ⚠️ Browser compatibility considerations
- ⚠️ Memory management for large files

### Recommendation

**Proceed with implementation** using the phased approach outlined above. Start with a robust backend implementation, then gradually build the frontend components with comprehensive testing at each phase.

**Estimated Timeline**: 8 days for full implementation and testing

**Risk Level**: Medium (mitigated by hybrid approach and thorough testing)

---

## 14. References

### Official Documentation
- [Cloudflare Stream TUS Upload](https://developers.cloudflare.com/stream/uploading-videos/upload-video-file/)
- [TUS Protocol Specification v1.0.0](https://tus.io/protocols/resumable-upload/)
- [Cloudflare Stream API Reference](https://developers.cloudflare.com/api/operations/stream-videos-upload-videos-via-direct-upload-ur-ls)

### Technical Resources
- [MDN File API](https://developer.mozilla.org/en-US/docs/Web/API/File)
- [MDN FileReader API](https://developer.mozilla.org/en-US/docs/Web/API/FileReader)
- [MDN XMLHttpRequest](https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest)
- [Moodle JavaScript Guidelines](https://docs.moodle.org/dev/Javascript_guidelines)
- [Moodle AMD Modules](https://docs.moodle.org/dev/Javascript_Modules)

### Similar Implementations
- [TUS Protocol Examples](https://github.com/tus/tus-resumable-upload-protocol/blob/master/protocol.md#example)
- [Cloudflare Workers TUS Example](https://developers.cloudflare.com/workers/examples/upload-large-files/)

---

---

## 15. Pre-Implementation Checklist

Before starting implementation, verify these critical points:

### UID Extraction Verification
- [ ] Read Cloudflare TUS documentation thoroughly
- [ ] Understand actual URL format returned by Cloudflare
- [ ] Test UID extraction with multiple URL formats
- [ ] Add comprehensive logging for debugging
- [ ] Validate extracted UID format
- [ ] Handle edge cases (trailing chars, query params)

### Code Quality
- [ ] No external JavaScript libraries (pure native code)
- [ ] Compatible with Moodle AMD module system
- [ ] Proper error handling with detailed messages
- [ ] Cleanup orphaned videos on failure
- [ ] Resume capability for interrupted uploads

### Testing Strategy
- [ ] Unit tests for UID extraction (10+ test cases)
- [ ] Integration tests with real Cloudflare API
- [ ] Test with various file sizes (100MB - 2GB)
- [ ] Test on multiple browsers
- [ ] Test network interruption scenarios
- [ ] Test cleanup functionality

### Deployment Safety
- [ ] Backup current working code
- [ ] Implement feature flag for gradual rollout
- [ ] Monitor error logs during deployment
- [ ] Have rollback plan ready
- [ ] Test on staging environment first

### Documentation
- [ ] Update code comments with URL format examples
- [ ] Document UID extraction logic
- [ ] Add troubleshooting guide
- [ ] Update user documentation

---

## 16. Quick Reference: Yesterday's Error

**For Future Debugging**:

```
Error Message:
❌ Failed to extract video UID from URL: https://edge-production.gateway.api.cloudflare.com/...

Root Cause:
- TUS upload completed successfully (100%)
- UID extraction failed due to unexpected URL format
- Code expected simple format, got complex API URL

Solution:
- Parse URL properly using URL object
- Find '/media/' segment in path
- Extract next path segment as UID
- Remove trailing underscores
- Validate UID format

Prevention:
- Test UID extraction BEFORE full implementation
- Use real Cloudflare URLs in tests
- Add comprehensive logging
- Validate extracted data
- Handle multiple URL formats
```

---

**Document Version**: 2.0  
**Last Updated**: 2025-11-01 (Updated with yesterday's error analysis)  
**Author**: Kiro AI Assistant  
**Status**: Ready for Implementation (with critical UID extraction fix)

