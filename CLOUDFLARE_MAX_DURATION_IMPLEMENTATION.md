# Cloudflare Stream `maxDurationSeconds` Implementation Plan

## Problem Statement

Cloudflare Stream reserves ~300 minutes of quota per upload by default, causing quota exhaustion with concurrent uploads even though actual video durations are much shorter.

**Example:**
- Total quota: 1000 minutes
- 3 concurrent uploads = 900 minutes reserved (3 × 300)
- Only 100 minutes left for actual usage
- 4th user gets "quota exceeded" error

## Official Cloudflare Solution

Use the `maxDurationSeconds` parameter when creating upload URLs to limit quota reservation.

**Official Documentation:**
- https://developers.cloudflare.com/stream/uploading-videos/direct-creator-uploads/
- API Reference: POST `/accounts/{account_id}/stream/direct_upload`

## Implementation Changes

### 1. Add `maxDurationSeconds` to Cloudflare API Client

**File:** `mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php`

**Function to modify:** `create_direct_upload_url()`

**Current code:**
```php
public function create_direct_upload_url() {
    $endpoint = "/accounts/{$this->accountid}/stream/direct_upload";
    $data = [
        'requireSignedURLs' => true
    ];
    
    $response = $this->make_request('POST', $endpoint, $data);
    return $response->result;
}
```

**New code:**
```php
public function create_direct_upload_url($max_duration_seconds = 3600) {
    $endpoint = "/accounts/{$this->accountid}/stream/direct_upload";
    $data = [
        'requireSignedURLs' => true,
        'maxDurationSeconds' => $max_duration_seconds  // NEW PARAMETER
    ];
    
    $response = $this->make_request('POST', $endpoint, $data);
    return $response->result;
}
```

### 2. Calculate Duration from File Size

**File:** `mod/assign/submission/cloudflarestream/ajax/get_upload_url.php`

**Add duration estimation logic:**

```php
// Get file size from request (sent by JavaScript)
$filesize = optional_param('filesize', 0, PARAM_INT);

// Estimate video duration based on file size
// Average bitrate: 2 Mbps (typical for compressed video)
// Formula: duration (seconds) = (filesize in bytes × 8) / (bitrate in bits/sec)
$estimated_duration_seconds = 0;
if ($filesize > 0) {
    $bitrate_bps = 2000000; // 2 Mbps average
    $estimated_duration_seconds = ($filesize * 8) / $bitrate_bps;
    
    // Add 50% buffer for safety
    $estimated_duration_seconds = ceil($estimated_duration_seconds * 1.5);
    
    // Cap at 2 hours (7200 seconds) for safety
    $estimated_duration_seconds = min($estimated_duration_seconds, 7200);
    
    // Minimum 5 minutes (300 seconds)
    $estimated_duration_seconds = max($estimated_duration_seconds, 300);
}

// Create upload URL with duration limit
$uploaddata = $cloudflare->create_direct_upload_url($estimated_duration_seconds);
```

### 3. Send File Size from JavaScript

**File:** `mod/assign/submission/cloudflarestream/amd/src/uploader.js`

**Function to modify:** `requestUploadUrl()`

**Current code:**
```javascript
async requestUploadUrl(file) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_upload_url.php',
            method: 'POST',
            data: {
                assignmentid: this.assignmentId,
                submissionid: this.submissionId,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        }).done((data) => {
            // ...
        });
    });
}
```

**New code:**
```javascript
async requestUploadUrl(file) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_upload_url.php',
            method: 'POST',
            data: {
                assignmentid: this.assignmentId,
                submissionid: this.submissionId,
                filesize: file.size,  // NEW: Send file size
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        }).done((data) => {
            // ...
        });
    });
}
```

### 4. Add Admin Setting (Optional)

**File:** `mod/assign/submission/cloudflarestream/settings.php`

**Add configurable default duration:**

```php
$settings->add(new admin_setting_configtext(
    'assignsubmission_cloudflarestream/default_max_duration',
    get_string('default_max_duration', 'assignsubmission_cloudflarestream'),
    get_string('default_max_duration_desc', 'assignsubmission_cloudflarestream'),
    3600,  // Default: 1 hour
    PARAM_INT
));
```

## Duration Estimation Formula

### Method 1: File Size Based (Recommended)
```
Duration (seconds) = (File Size in bytes × 8) / Average Bitrate (bps)
```

**Example calculations:**
- 100 MB file ÷ 2 Mbps = ~400 seconds (6.7 minutes)
- 500 MB file ÷ 2 Mbps = ~2000 seconds (33 minutes)
- 1.7 GB file ÷ 2 Mbps = ~6800 seconds (113 minutes)

**With 50% safety buffer:**
- 100 MB → 600 seconds (10 minutes)
- 500 MB → 3000 seconds (50 minutes)
- 1.7 GB → 7200 seconds (120 minutes - capped at 2 hours)

### Method 2: Fixed Tiers (Alternative)
```php
if ($filesize < 100 * 1024 * 1024) {
    $max_duration = 600;  // <100MB = 10 minutes
} elseif ($filesize < 500 * 1024 * 1024) {
    $max_duration = 1800; // <500MB = 30 minutes
} elseif ($filesize < 1024 * 1024 * 1024) {
    $max_duration = 3600; // <1GB = 1 hour
} else {
    $max_duration = 7200; // >1GB = 2 hours
}
```

## Benefits

✅ **Prevents over-reservation:** Only reserves estimated duration instead of 300 minutes
✅ **Allows more concurrent uploads:** 3 uploads × 120 min = 360 min reserved (vs 900 min)
✅ **Better quota utilization:** Actual usage matches reservation more closely
✅ **No queue system needed:** Simple parameter change
✅ **Automatic:** No user input required

## Risks & Mitigation

**Risk 1:** Video longer than estimated duration
- **Mitigation:** Add 50% safety buffer to estimation
- **Fallback:** Upload will fail with clear error message

**Risk 2:** Inaccurate bitrate estimation
- **Mitigation:** Use conservative 2 Mbps average (works for most videos)
- **Fallback:** Cap at 2 hours maximum

**Risk 3:** Very high bitrate videos (4K, uncompressed)
- **Mitigation:** Set reasonable maximum (2 hours)
- **Fallback:** User can re-upload if rejected

## Testing Plan

1. **Small file (50MB):** Should reserve ~10 minutes
2. **Medium file (500MB):** Should reserve ~50 minutes
3. **Large file (1.7GB):** Should reserve ~120 minutes
4. **Concurrent uploads:** 3 uploads should reserve ~360 minutes total
5. **Edge case:** Very short video in large file (should still work)

## Rollback Plan

If issues occur, simply remove the `maxDurationSeconds` parameter:
```php
$data = [
    'requireSignedURLs' => true
    // Remove: 'maxDurationSeconds' => $max_duration_seconds
];
```

System will revert to default 300-minute reservation.

## Files to Modify

1. ✅ `classes/api/cloudflare_client.php` - Add parameter to API call
2. ✅ `ajax/get_upload_url.php` - Calculate duration from file size
3. ✅ `amd/src/uploader.js` - Send file size to backend
4. ✅ `amd/build/uploader.min.js` - Rebuild minified version
5. ⚠️ `settings.php` - (Optional) Add admin setting

## Estimated Implementation Time

- Code changes: 15 minutes
- Testing: 30 minutes
- Total: 45 minutes

## References

- [Cloudflare Stream Direct Upload API](https://developers.cloudflare.com/stream/uploading-videos/direct-creator-uploads/)
- [Cloudflare Stream Pricing](https://developers.cloudflare.com/stream/pricing/)
- [Video Bitrate Calculator](https://www.dr-lex.be/info-stuff/videocalc.html)
