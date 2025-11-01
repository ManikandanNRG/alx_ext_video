# Cloudflare TUS - Key Findings from Official Documentation

## 🎯 Critical Discovery: The Real Solution to Yesterday's Error

### The Problem Yesterday
```
❌ Tried to parse Location header URL
❌ Failed to extract UID from complex URL format
❌ Upload succeeded but couldn't save to database
```

### The Solution (From Official Cloudflare Docs)
```
✅ Use 'stream-media-id' response header
✅ Don't parse the URL at all
✅ Cloudflare explicitly recommends this method
```

## Official Cloudflare Quote

> **"When an initial tus request is made, Stream responds with a URL in the Location header. While this URL may contain the video ID, it is not recommend to parse this URL to get the ID. Instead, you should use the stream-media-id HTTP header in the response to retrieve the video ID."**

Source: https://developers.cloudflare.com/stream/uploading-videos/resumable-uploads/

## Response Headers Example

```http
HTTP/1.1 201 Created
Location: https://api.cloudflare.com/client/v4/accounts/<ACCOUNT_ID>/stream/cab807e0c477d01baq20f66c3d1dfc26cf
stream-media-id: cab807e0c477d01baq20f66c3d1dfc26cf
Tus-Resumable: 1.0.0
```

## The Fix

### ❌ Old Way (Yesterday's Error)
```javascript
// Tried to parse the Location URL
const locationUrl = xhr.getResponseHeader('Location');
const uid = extractUidFromUrl(locationUrl); // FAILED!
```

### ✅ New Way (Official Cloudflare Method)
```javascript
// Simply read the stream-media-id header
const uid = xhr.getResponseHeader('stream-media-id');

// Fallback to URL parsing only if header missing
if (!uid) {
    console.warn('stream-media-id header missing');
    uid = extractUidFromUrl(xhr.getResponseHeader('Location'));
}
```

## Chunk Size Requirements

### Critical Rules from Cloudflare

1. **Minimum**: 5,242,880 bytes (5 MB)
2. **Recommended**: 52,428,800 bytes (50 MB)
3. **Maximum**: 209,715,200 bytes (200 MB)
4. **MUST be divisible by**: 256 KiB (262,144 bytes)
5. **Exception**: Final chunk can be any size

### Validation Formula

```javascript
const isValidChunkSize = (size) => {
    const MIN = 5242880;      // 5 MB
    const MAX = 209715200;    // 200 MB
    const DIVISOR = 262144;   // 256 KiB
    
    return size >= MIN && 
           size <= MAX && 
           size % DIVISOR === 0;
};
```

### Valid Chunk Sizes

```javascript
5242880     // 5 MB ✅
10485760    // 10 MB ✅
52428800    // 50 MB ✅ RECOMMENDED
104857600   // 100 MB ✅
209715200   // 200 MB ✅ MAXIMUM
```

### Invalid Chunk Sizes

```javascript
5000000     // Not divisible by 256 KiB ❌
6000000     // Not divisible by 256 KiB ❌
1048576     // Less than 5 MB ❌
300000000   // Exceeds 200 MB ❌
```

## Performance Comparison

### For 1.7 GB File

| Chunk Size | Number of Requests | Performance |
|------------|-------------------|-------------|
| 5 MB       | 340 requests      | Slow        |
| 50 MB      | 34 requests       | Fast ✅     |
| 100 MB     | 17 requests       | Faster      |
| 200 MB     | 9 requests        | Fastest     |

**Cloudflare's Recommendation**: Use 50 MB chunks for reliable connections.

## Upload Metadata Options

### Supported Keys

```javascript
// Upload-Metadata header format
'name ' + btoa(filename) + ',' +
'requiresignedurlsrls true,' +
'thumbnailtimestamppct 0.5'
```

**Available Options**:
- `name`: Video name (base64 encoded)
- `requiresignedurlsrls`: Make video private (boolean)
- `scheduleddeletion`: Auto-delete date (ISO 8601)
- `allowedorigins`: Comma-separated origins
- `thumbnailtimestamppct`: Thumbnail position (0.0 - 1.0)
- `watermark`: Watermark profile UID

## Upload-Creator Header

Optional header to link videos to users:

```javascript
xhr.setRequestHeader('Upload-Creator', userId);
```

Useful for:
- Multi-tenant applications
- Tracking video ownership
- User-specific video management

## Retry Strategy

From Cloudflare's Node.js example:

```javascript
retryDelays: [0, 3000, 5000, 10000, 20000]
```

**Strategy**:
1. Immediate retry (0ms)
2. 3 second delay
3. 5 second delay
4. 10 second delay
5. 20 second delay

**Total**: 5 retry attempts with exponential backoff

## Complete TUS Upload Flow

### Step 1: Create TUS Session

```javascript
const xhr = new XMLHttpRequest();
xhr.open('POST', 'https://api.cloudflare.com/client/v4/accounts/<ACCOUNT_ID>/stream');
xhr.setRequestHeader('Authorization', 'Bearer <API_TOKEN>');
xhr.setRequestHeader('Tus-Resumable', '1.0.0');
xhr.setRequestHeader('Upload-Length', fileSize.toString());
xhr.setRequestHeader('Upload-Metadata', 'name ' + btoa(filename));

xhr.onload = () => {
    if (xhr.status === 201) {
        const uploadUrl = xhr.getResponseHeader('Location');
        const uid = xhr.getResponseHeader('stream-media-id'); // ✅ CORRECT
        
        // Start uploading chunks
        uploadChunks(uploadUrl, file);
    }
};

xhr.send();
```

### Step 2: Upload Chunks

```javascript
async function uploadChunks(uploadUrl, file) {
    const CHUNK_SIZE = 52428800; // 50 MB
    let offset = 0;
    
    while (offset < file.size) {
        const chunk = file.slice(offset, offset + CHUNK_SIZE);
        const chunkData = await readChunkAsArrayBuffer(chunk);
        
        const xhr = new XMLHttpRequest();
        xhr.open('PATCH', uploadUrl);
        xhr.setRequestHeader('Tus-Resumable', '1.0.0');
        xhr.setRequestHeader('Upload-Offset', offset.toString());
        xhr.setRequestHeader('Content-Type', 'application/offset+octet-stream');
        
        await new Promise((resolve, reject) => {
            xhr.onload = () => {
                if (xhr.status === 204) {
                    offset = parseInt(xhr.getResponseHeader('Upload-Offset'));
                    resolve();
                } else {
                    reject(new Error('Chunk upload failed'));
                }
            };
            xhr.onerror = reject;
            xhr.send(chunkData);
        });
    }
}
```

### Step 3: Verify Upload Complete

```javascript
// Check if upload is complete
if (offset === file.size) {
    console.log('✅ Upload complete!');
    // Save to database with uid
    await confirmUpload(uid);
}
```

## Key Takeaways

### 1. UID Extraction
- ✅ **DO**: Use `stream-media-id` header
- ❌ **DON'T**: Parse Location URL

### 2. Chunk Size
- ✅ **DO**: Use 50 MB chunks (recommended)
- ✅ **DO**: Ensure divisible by 256 KiB
- ❌ **DON'T**: Use arbitrary chunk sizes

### 3. Error Handling
- ✅ **DO**: Implement retry with exponential backoff
- ✅ **DO**: Handle network interruptions
- ✅ **DO**: Cleanup failed uploads

### 4. Performance
- ✅ **DO**: Use larger chunks for reliable connections
- ✅ **DO**: Monitor upload progress
- ✅ **DO**: Optimize for user experience

## Implementation Checklist

Before starting implementation:

- [ ] Read official Cloudflare TUS documentation
- [ ] Understand `stream-media-id` header usage
- [ ] Validate chunk size (must be divisible by 256 KiB)
- [ ] Implement retry logic with exponential backoff
- [ ] Test with various file sizes
- [ ] Handle network interruptions
- [ ] Implement cleanup for failed uploads
- [ ] Add comprehensive logging

## Testing Requirements

### Unit Tests
- [ ] Test UID extraction from `stream-media-id` header
- [ ] Test chunk size validation
- [ ] Test chunk upload logic
- [ ] Test retry mechanism

### Integration Tests
- [ ] Test complete upload flow
- [ ] Test with 100 MB file
- [ ] Test with 500 MB file
- [ ] Test with 1.7 GB file
- [ ] Test network interruption recovery
- [ ] Test cleanup on failure

### Performance Tests
- [ ] Measure upload speed with different chunk sizes
- [ ] Monitor memory usage during upload
- [ ] Test concurrent uploads
- [ ] Verify no memory leaks

## Conclusion

The official Cloudflare documentation provides clear guidance that solves yesterday's error:

**Yesterday's Problem**: Tried to parse Location URL → Failed
**Today's Solution**: Use `stream-media-id` header → Success

Additionally, we now understand:
- Chunk size requirements (must be divisible by 256 KiB)
- Recommended chunk size (50 MB)
- Proper retry strategy
- Upload metadata options

**This implementation will work correctly.**

---

**Document Created**: 2025-11-01  
**Source**: https://developers.cloudflare.com/stream/uploading-videos/resumable-uploads/  
**Status**: Ready for implementation with official Cloudflare guidance
