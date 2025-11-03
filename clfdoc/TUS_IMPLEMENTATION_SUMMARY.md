# TUS Implementation - Quick Summary

## The Problem
- **Current Limit**: 200MB (Cloudflare direct upload hard limit)
- **Your Need**: Upload 1.7GB+ files
- **Only Solution**: TUS resumable upload (supports up to 30GB)

## Why Previous Attempt Failed (Yesterday's Error)

**What Happened**:
```
TUS Progress: 100% (1721.3 / 1721.3 MB) ✅
Upload URL: https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
❌ Failed to extract video UID from URL
❌ Error: Failed to extract video UID from upload URL
❌ Falling back to direct upload
```

**Root Causes**:
1. ❌ **UID Extraction Failed** (CRITICAL) - Upload succeeded but couldn't parse UID
   - Expected: `https://upload.cloudflarestream.com/{uid}`
   - Got: `https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/{account}/media/{uid}?tusv2=true`
   - Code couldn't handle complex URL format

2. ❌ Tried to use external `tus-js-client` library → Conflicts with Moodle AMD

3. ❌ Used wrong endpoint (`/stream/direct_upload` instead of `/stream`)

4. ❌ Didn't implement proper chunk upload loop

**The Fix**:
```javascript
// Extract UID from: .../media/d9eb8bf_?tusv2=true
const pathParts = new URL(url).pathname.split('/');
const mediaIndex = pathParts.indexOf('media');
const uid = pathParts[mediaIndex + 1].replace(/_+$/, ''); // Remove trailing _
// Result: 'd9eb8bf'
```

## The Solution: Pure Native Implementation

### Key Approach
```
if (fileSize <= 200MB) {
    → Use Direct Upload (existing code - no changes)
} else {
    → Use TUS Upload (new code)
}
```

**Benefits**:
- ✅ No external libraries (pure JavaScript)
- ✅ Backward compatible
- ✅ No breaking changes
- ✅ Works with Moodle's AMD system

## TUS Protocol (3 Steps)

### Step 1: Create Upload Session
```http
POST /accounts/{account_id}/stream
Headers:
  Tus-Resumable: 1.0.0
  Upload-Length: {file_size}
  Upload-Metadata: name {base64_filename}

Response:
  Location: https://upload.cloudflarestream.com/{upload_url}
```

### Step 2: Upload Chunks (Loop)
```http
PATCH {upload_url}
Headers:
  Tus-Resumable: 1.0.0
  Upload-Offset: {current_offset}
  Content-Type: application/offset+octet-stream
Body: {binary_chunk_data}

Response:
  Upload-Offset: {new_offset}
```

### Step 3: Verify Complete
```http
HEAD {upload_url}
Response:
  Upload-Offset: {total_uploaded}
  Upload-Length: {file_size}
```

## Implementation Plan

### Phase 1: Backend (PHP) - Days 1-2
**Files to Modify**:
- `classes/api/cloudflare_client.php` - Add 3 new methods

**Files to Create**:
- `ajax/create_tus_upload.php` - Create TUS session
- `ajax/upload_tus_chunk.php` - Upload chunks (not used - direct to Cloudflare)

**New Methods**:
```php
create_tus_upload($filesize, $filename)  // Returns upload_url and uid
upload_tus_chunk($url, $data, $offset)   // Returns new offset
get_tus_status($url)                     // Returns current offset
```

### Phase 2: Frontend (JavaScript) - Days 3-4
**Files to Modify**:
- `amd/src/uploader.js` - Add TUS upload logic

**New Methods**:
```javascript
uploadViaTus(file, uploadData)           // Main TUS upload loop
uploadTusChunk(url, data, offset)        // Upload single chunk
readChunkAsArrayBuffer(chunk)            // Read file chunk
requestTusUploadUrl(file)                // Get TUS session from backend
```

### Phase 3: Error Handling - Days 5-6
- Retry logic with exponential backoff
- Resume capability (localStorage)
- Network interruption handling
- Integration with cleanup system

### Phase 4: Testing - Days 7-8
- Test various file sizes (100MB - 2GB)
- Test on different browsers
- Performance optimization
- Memory usage profiling

## Critical Implementation Details

### Chunk Size
```javascript
const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
```
- Too small: Too many requests, slow
- Too large: Memory issues, timeout risk
- 5MB: Optimal balance

### Upload Loop
```javascript
let offset = 0;
while (offset < file.size) {
    const chunk = file.slice(offset, offset + CHUNK_SIZE);
    const data = await readChunkAsArrayBuffer(chunk);
    offset = await uploadTusChunk(uploadUrl, data, offset);
    updateProgress((offset / file.size) * 100);
}
```

### Error Handling
```javascript
try {
    await uploadViaTus(file, uploadData);
} catch (error) {
    // Cleanup failed upload
    await cleanupFailedUpload(uploadData.uid);
    // Show retry option
    showError(error);
}
```

## Testing Checklist

### Functional Tests
- [ ] Upload 10MB file → Uses direct upload
- [ ] Upload 250MB file → Uses TUS upload
- [ ] Upload 1GB file → Uses TUS upload
- [ ] Progress bar updates correctly
- [ ] Cleanup works on failure
- [ ] Resume works after network interruption

### Browser Tests
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

### Performance Tests
- [ ] Memory usage <100MB
- [ ] CPU usage <10%
- [ ] Upload speed matches network bandwidth
- [ ] Multiple concurrent uploads work

## Risk Mitigation

### High Risk: Memory Usage
**Solution**: Use `File.slice()` to read chunks on-demand, release immediately

### High Risk: Network Interruptions
**Solution**: Store state in localStorage, implement resume capability

### Medium Risk: Browser Compatibility
**Solution**: Feature detection, graceful fallback to direct upload

### Medium Risk: Offset Mismatch
**Solution**: Verify offset with HEAD request before each chunk

## Success Criteria

- ✅ Upload files up to 30GB
- ✅ Resume interrupted uploads
- ✅ Progress accuracy within 1%
- ✅ Memory usage <100MB
- ✅ User-friendly error messages
- ✅ No breaking changes to existing functionality

## Rollback Plan

If issues occur:
1. Comment out TUS code in `startUpload()`
2. Force direct upload for all files
3. Display 200MB size limit warning
4. Fix issues and redeploy

## Timeline

- **Days 1-2**: Backend implementation
- **Days 3-4**: Frontend implementation
- **Days 5-6**: Error handling & integration
- **Days 7-8**: Testing & optimization

**Total**: 8 days

## Critical: UID Extraction Test (MUST DO FIRST)

Before implementing anything, test UID extraction:

```javascript
// Test with yesterday's actual URL
const testUrl = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';

function extractUid(url) {
    const pathParts = new URL(url).pathname.split('/');
    const mediaIndex = pathParts.indexOf('media');
    if (mediaIndex === -1) throw new Error('No media segment');
    const uid = pathParts[mediaIndex + 1].replace(/_+$/, '');
    if (!/^[a-zA-Z0-9]+$/.test(uid)) throw new Error('Invalid UID');
    return uid;
}

const uid = extractUid(testUrl);
console.log('Extracted UID:', uid); // Should be: d9eb8bf
```

**This MUST work before proceeding!**

## Next Steps

1. ✅ Review this plan
2. ✅ Test UID extraction with real URLs (CRITICAL)
3. ✅ Confirm approach
4. ✅ Start Phase 1 (Backend implementation)
5. ✅ Test with curl/Postman
6. ✅ Move to Phase 2 (Frontend)

---

## Yesterday's Error - Never Forget

```
Upload: 100% complete ✅
UID Extraction: FAILED ❌
Result: 1.7GB video orphaned in Cloudflare
```

**Prevention**: Test UID extraction FIRST, then implement upload.

---

**Ready to start implementation?** 🚀

See `CLOUDFLARE_TUS_IMPLEMENTATION_PLAN.md` for full details.
