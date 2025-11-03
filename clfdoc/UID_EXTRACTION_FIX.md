# UID Extraction Fix - Visual Guide

## The Problem (Yesterday's Error)

```
❌ Failed to extract video UID from URL:
https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
```

## URL Structure Breakdown

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                 │
│  https://edge-production.gateway.api.cloudflare.com                            │
│  └─────────────────────────────────────────────────┘                           │
│                    Domain                                                       │
│                                                                                 │
│  /client/v4/accounts/01962_e37899c_/media/d9eb8bf_                             │
│  └──────┘ └─┘ └───────┘ └────────────┘ └───┘ └──────┘                         │
│     │      │      │           │          │       │                             │
│     │      │      │           │          │       └─ UID (with trailing _)      │
│     │      │      │           │          └───────── 'media' segment            │
│     │      │      │           └──────────────────── Account ID                 │
│     │      │      └──────────────────────────────── 'accounts' segment         │
│     │      └─────────────────────────────────────── API version                │
│     └────────────────────────────────────────────── 'client' segment           │
│                                                                                 │
│  ?tusv2=true                                                                    │
│  └──────────┘                                                                   │
│  Query parameter (ignore)                                                       │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## Extraction Steps

```
Step 1: Parse URL
────────────────
Input:  https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true
Action: new URL(url)
Result: URL object with pathname, search, etc.

Step 2: Get pathname
─────────────────────
Input:  URL object
Action: urlObj.pathname
Result: /client/v4/accounts/01962_e37899c_/media/d9eb8bf_

Step 3: Split into segments
────────────────────────────
Input:  /client/v4/accounts/01962_e37899c_/media/d9eb8bf_
Action: pathname.split('/').filter(p => p.length > 0)
Result: ['client', 'v4', 'accounts', '01962_e37899c_', 'media', 'd9eb8bf_']
         [0]       [1]   [2]        [3]              [4]      [5]

Step 4: Find 'media' index
───────────────────────────
Input:  ['client', 'v4', 'accounts', '01962_e37899c_', 'media', 'd9eb8bf_']
Action: pathParts.indexOf('media')
Result: 4

Step 5: Get next segment (UID)
───────────────────────────────
Input:  pathParts[4 + 1]
Action: pathParts[5]
Result: 'd9eb8bf_'

Step 6: Remove trailing underscore
───────────────────────────────────
Input:  'd9eb8bf_'
Action: uid.replace(/_+$/, '')
Result: 'd9eb8bf'

Step 7: Validate UID
────────────────────
Input:  'd9eb8bf'
Action: /^[a-zA-Z0-9]+$/.test(uid)
Result: true ✅

Step 8: Return UID
──────────────────
Output: 'd9eb8bf'
```

## Code Implementation

### JavaScript Version

```javascript
/**
 * Extract video UID from Cloudflare TUS upload URL.
 * 
 * @param {string} url - TUS upload URL from Location header
 * @returns {string} - Video UID
 * @throws {Error} - If UID cannot be extracted or is invalid
 */
function extractUidFromTusUrl(url) {
    try {
        // Step 1: Parse URL
        const urlObj = new URL(url);
        
        // Step 2 & 3: Get pathname and split into segments
        const pathParts = urlObj.pathname.split('/').filter(p => p.length > 0);
        
        // Step 4: Find 'media' segment
        const mediaIndex = pathParts.indexOf('media');
        if (mediaIndex === -1) {
            throw new Error('Cannot find "media" segment in URL path');
        }
        
        // Step 5: Get next segment (UID)
        if (!pathParts[mediaIndex + 1]) {
            throw new Error('No segment found after "media" in URL path');
        }
        let uid = pathParts[mediaIndex + 1];
        
        // Step 6: Remove trailing underscores
        uid = uid.replace(/_+$/, '');
        
        // Step 7: Validate UID format
        if (!uid || !/^[a-zA-Z0-9]+$/.test(uid)) {
            throw new Error('Invalid UID format: ' + uid);
        }
        
        // Step 8: Return UID
        console.log('✅ Extracted UID:', uid, 'from URL:', url);
        return uid;
        
    } catch (error) {
        console.error('❌ UID extraction failed:', error.message);
        throw new Error('Failed to extract video UID from URL: ' + url + ' - ' + error.message);
    }
}
```

### PHP Version

```php
/**
 * Extract video UID from Cloudflare TUS upload URL.
 * 
 * @param string $url TUS upload URL from Location header
 * @return string Video UID
 * @throws cloudflare_api_exception If UID cannot be extracted or is invalid
 */
private function extract_uid_from_tus_url($url) {
    // Step 1: Parse URL
    $parts = parse_url($url);
    if (!isset($parts['path'])) {
        throw new cloudflare_api_exception(
            'tus_invalid_url',
            'Cannot parse TUS URL: ' . $url
        );
    }
    
    // Step 2 & 3: Get path and split into segments
    $pathsegments = explode('/', trim($parts['path'], '/'));
    
    // Step 4: Find 'media' segment
    $mediaindex = array_search('media', $pathsegments);
    if ($mediaindex === false) {
        throw new cloudflare_api_exception(
            'tus_invalid_url',
            'Cannot find "media" segment in TUS URL: ' . $url
        );
    }
    
    // Step 5: Get next segment (UID)
    if (!isset($pathsegments[$mediaindex + 1])) {
        throw new cloudflare_api_exception(
            'tus_invalid_url',
            'No segment found after "media" in TUS URL: ' . $url
        );
    }
    $uid = $pathsegments[$mediaindex + 1];
    
    // Step 6: Remove trailing underscores
    $uid = rtrim($uid, '_');
    
    // Step 7: Validate UID format
    if (empty($uid) || !preg_match('/^[a-zA-Z0-9]+$/', $uid)) {
        throw new cloudflare_api_exception(
            'tus_invalid_uid',
            'Invalid UID format: ' . $uid . ' (URL: ' . $url . ')'
        );
    }
    
    // Step 8: Return UID
    return $uid;
}
```

## Test Cases

### Test 1: Yesterday's Actual URL
```javascript
const url1 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/d9eb8bf_?tusv2=true';
const uid1 = extractUidFromTusUrl(url1);
console.assert(uid1 === 'd9eb8bf', 'Test 1 failed');
// ✅ Expected: d9eb8bf
```

### Test 2: Without Query Parameter
```javascript
const url2 = 'https://edge-production.gateway.api.cloudflare.com/client/v4/accounts/01962_e37899c_/media/abc123def';
const uid2 = extractUidFromTusUrl(url2);
console.assert(uid2 === 'abc123def', 'Test 2 failed');
// ✅ Expected: abc123def
```

### Test 3: Multiple Trailing Underscores
```javascript
const url3 = 'https://api.cloudflare.com/client/v4/accounts/test/media/xyz789___';
const uid3 = extractUidFromTusUrl(url3);
console.assert(uid3 === 'xyz789', 'Test 3 failed');
// ✅ Expected: xyz789
```

### Test 4: Different Domain
```javascript
const url4 = 'https://api.cloudflare.com/client/v4/accounts/account123/media/video456';
const uid4 = extractUidFromTusUrl(url4);
console.assert(uid4 === 'video456', 'Test 4 failed');
// ✅ Expected: video456
```

### Test 5: Invalid URL (Should Throw Error)
```javascript
try {
    const url5 = 'https://example.com/invalid/path';
    extractUidFromTusUrl(url5);
    console.error('❌ Test 5 failed: Should have thrown error');
} catch (error) {
    console.log('✅ Test 5 passed: Error thrown as expected');
}
```

## Before vs After

### ❌ Old Code (Failed)
```javascript
// Assumed simple format
function extractUid(url) {
    return url.split('/').pop().split('?')[0];
}

// Result: 'd9eb8bf_' (with underscore, not validated)
// Or: Failed to match expected pattern
```

### ✅ New Code (Works)
```javascript
// Handles complex format
function extractUidFromTusUrl(url) {
    const pathParts = new URL(url).pathname.split('/');
    const mediaIndex = pathParts.indexOf('media');
    const uid = pathParts[mediaIndex + 1].replace(/_+$/, '');
    if (!/^[a-zA-Z0-9]+$/.test(uid)) throw new Error('Invalid UID');
    return uid;
}

// Result: 'd9eb8bf' (clean, validated)
```

## Integration Example

```javascript
// In your TUS upload code
async function startTusUpload(file) {
    try {
        // Create TUS session
        const response = await createTusSession(file);
        const uploadUrl = response.headers['Location'];
        
        console.log('=== TUS Session Created ===');
        console.log('Upload URL:', uploadUrl);
        
        // Extract UID (CRITICAL STEP)
        console.log('Extracting UID...');
        const uid = extractUidFromTusUrl(uploadUrl);
        console.log('✅ Extracted UID:', uid);
        
        // Upload file chunks
        await uploadChunks(uploadUrl, file);
        
        // Confirm upload
        await confirmUpload(uid);
        
        console.log('✅ Upload complete!');
        
    } catch (error) {
        console.error('❌ Upload failed:', error.message);
        
        // Attempt cleanup even if UID extraction failed
        if (uploadUrl) {
            try {
                const uidMatch = uploadUrl.match(/\/media\/([a-zA-Z0-9_]+)/);
                if (uidMatch) {
                    const uidForCleanup = uidMatch[1].replace(/_+$/, '');
                    await cleanupFailedUpload(uidForCleanup);
                }
            } catch (cleanupError) {
                console.error('Cleanup also failed:', cleanupError);
            }
        }
        
        throw error;
    }
}
```

## Debugging Tips

### If UID Extraction Fails

1. **Log the full URL**:
```javascript
console.log('Full URL:', url);
console.log('Pathname:', new URL(url).pathname);
console.log('Path parts:', new URL(url).pathname.split('/'));
```

2. **Check for 'media' segment**:
```javascript
const pathParts = new URL(url).pathname.split('/');
console.log('Media index:', pathParts.indexOf('media'));
console.log('Segment after media:', pathParts[pathParts.indexOf('media') + 1]);
```

3. **Verify UID format**:
```javascript
const uid = extractedValue.replace(/_+$/, '');
console.log('UID after cleanup:', uid);
console.log('Is valid format:', /^[a-zA-Z0-9]+$/.test(uid));
```

## Success Checklist

Before deploying:

- [ ] Implemented extraction function
- [ ] All 5 test cases pass
- [ ] Tested with yesterday's actual URL
- [ ] Logging shows full URL and extracted UID
- [ ] Error messages include full URL
- [ ] Validation confirms UID format
- [ ] Cleanup works even if extraction fails
- [ ] Integration test with real TUS upload succeeds

---

**This fix prevents yesterday's error from ever happening again.**

**Status**: Ready to implement ✅
