# S3 + CloudFront vs Cloudflare Stream - Complete Analysis

## Overview

You want to add **AWS S3 + CloudFront** as an alternative to Cloudflare Stream for video storage and delivery.

---

## Key Differences

### Cloudflare Stream
- **Purpose**: Specialized video hosting and streaming service
- **Features**: 
  - Automatic video transcoding
  - Adaptive bitrate streaming
  - Built-in video player
  - Video processing (thumbnails, etc.)
  - Signed URLs for security
- **Cost**: $5/month minimum + usage
- **Complexity**: High-level API, handles everything

### S3 + CloudFront
- **Purpose**: General file storage + CDN delivery
- **Features**:
  - Raw file storage (no transcoding)
  - CDN delivery via CloudFront
  - Signed URLs for security
  - You handle video player
  - No automatic processing
- **Cost**: Pay-as-you-go (can be cheaper)
- **Complexity**: Lower-level, more control

---

## Architecture Comparison

### Current: Cloudflare Stream
```
Student Browser → Cloudflare Stream API → Cloudflare Storage
                                        ↓
Teacher Browser ← Cloudflare Player ← Cloudflare CDN
```

### Proposed: S3 + CloudFront
```
Student Browser → S3 Presigned URL → S3 Bucket
                                    ↓
Teacher Browser ← HTML5 Player ← CloudFront CDN
```

---

## Implementation Options

### Option 1: Separate Plugin (Recommended)
**Create a new plugin**: `assignsubmission_s3video`

**Pros**:
- ✅ Clean separation of concerns
- ✅ Users can choose which plugin to install
- ✅ Easier to maintain
- ✅ Can have different features for each
- ✅ No code conflicts

**Cons**:
- ❌ Duplicate code (can be minimized with shared library)
- ❌ Two plugins to maintain

**Effort**: Medium (can reuse 70% of current code)

---

### Option 2: Unified Plugin with Storage Backend Selection
**Modify current plugin** to support multiple storage backends

**Pros**:
- ✅ Single plugin to install
- ✅ Users can switch between backends
- ✅ Shared admin interface
- ✅ Unified statistics dashboard

**Cons**:
- ❌ More complex codebase
- ❌ Harder to maintain
- ❌ Settings page more complicated
- ❌ Risk of breaking existing installations

**Effort**: High (requires refactoring)

---

### Option 3: Hybrid Approach (Best of Both)
**Create a shared library** + two separate plugins

**Structure**:
```
local/videostorage/              (Shared library)
├── classes/
│   ├── storage_interface.php    (Interface)
│   ├── validator.php            (Shared)
│   ├── logger.php               (Shared)
│   └── rate_limiter.php         (Shared)

mod/assign/submission/cloudflarestream/  (Cloudflare plugin)
├── classes/
│   └── storage/
│       └── cloudflare_storage.php (Implements interface)

mod/assign/submission/s3video/           (S3 plugin)
├── classes/
│   └── storage/
│       └── s3_storage.php        (Implements interface)
```

**Pros**:
- ✅ Code reuse (DRY principle)
- ✅ Clean separation
- ✅ Easy to add more backends later
- ✅ Independent maintenance

**Cons**:
- ❌ Requires creating shared library plugin
- ❌ Three components to manage

**Effort**: High initially, but best long-term

---

## Recommended Approach: Option 1 (Separate Plugin)

Create `assignsubmission_s3video` as a new plugin, reusing code from Cloudflare Stream plugin.

### Why This is Best:
1. **Quick to implement** - Copy and modify existing code
2. **No risk to existing plugin** - Cloudflare Stream plugin stays intact
3. **Users choose** - Install one or both
4. **Testable now** - S3 free tier available (5GB storage, 15GB transfer/month)
5. **Independent releases** - Update each plugin separately

---

## S3 + CloudFront Plugin Architecture

### What Changes from Cloudflare Stream Plugin:

#### 1. Storage Backend
**Cloudflare Stream**:
- Uses Cloudflare Stream API
- Automatic transcoding
- Built-in player

**S3 + CloudFront**:
- Uses AWS S3 SDK
- Raw video storage
- HTML5 video player (Video.js or Plyr)

#### 2. Upload Flow
**Cloudflare Stream**:
```
1. Get direct upload URL from Cloudflare API
2. Upload via tus protocol
3. Cloudflare processes video
4. Get video UID
```

**S3 + CloudFront**:
```
1. Generate S3 presigned POST URL
2. Upload directly to S3
3. Store S3 object key
4. Serve via CloudFront
```

#### 3. Playback Flow
**Cloudflare Stream**:
```
1. Generate signed token
2. Embed Cloudflare Stream player
3. Player handles everything
```

**S3 + CloudFront**:
```
1. Generate CloudFront signed URL
2. Embed HTML5 video player (Video.js)
3. Player loads from CloudFront
```

#### 4. Security
**Both use signed URLs**, but:
- Cloudflare: JWT tokens
- CloudFront: Signed cookies or URLs

---

## Cost Comparison

### Cloudflare Stream
- **Minimum**: $5/month
- **Storage**: $5 per 1,000 minutes stored
- **Delivery**: $1 per 1,000 minutes delivered
- **Example** (100 videos, 10 min each, 1 view each):
  - Storage: 1,000 minutes = $5/month
  - Delivery: 1,000 minutes = $1/month
  - **Total: ~$6/month**

### S3 + CloudFront
- **No minimum**
- **S3 Storage**: $0.023 per GB/month
- **CloudFront**: $0.085 per GB transferred
- **Example** (100 videos, 10 min each @ 5 Mbps, 1 view each):
  - Storage: ~37.5 GB = $0.86/month
  - Transfer: ~37.5 GB = $3.19/month
  - **Total: ~$4/month**

**S3 + CloudFront is cheaper** for most use cases!

---

## Feature Comparison

| Feature | Cloudflare Stream | S3 + CloudFront |
|---------|------------------|-----------------|
| **Storage** | Managed | S3 Bucket |
| **CDN** | Built-in | CloudFront |
| **Transcoding** | ✅ Automatic | ❌ Manual (or use MediaConvert) |
| **Adaptive Bitrate** | ✅ Yes | ❌ No (unless you transcode) |
| **Player** | ✅ Built-in | Need to provide (Video.js) |
| **Thumbnails** | ✅ Automatic | ❌ Manual |
| **Analytics** | ✅ Built-in | Need CloudWatch |
| **Signed URLs** | ✅ Yes | ✅ Yes |
| **Free Tier** | ❌ No | ✅ Yes (12 months) |
| **Cost** | Higher | Lower |
| **Setup Complexity** | Lower | Higher |
| **Video Quality** | Optimized | As uploaded |

---

## Implementation Plan for S3 Plugin

### Phase 1: Core Functionality (Week 1-2)
1. Create plugin structure (copy from Cloudflare Stream)
2. Replace Cloudflare API client with AWS S3 SDK
3. Implement S3 presigned POST for uploads
4. Implement CloudFront signed URLs for playback
5. Replace Cloudflare player with Video.js
6. Update database schema (s3_key instead of video_uid)

### Phase 2: Features (Week 3)
1. Admin settings (AWS credentials, bucket, CloudFront)
2. Dashboard (reuse existing)
3. Video management (reuse existing)
4. GDPR compliance (reuse existing)

### Phase 3: Testing & Polish (Week 4)
1. Test uploads
2. Test playback
3. Test security
4. Documentation

---

## Code Changes Required

### Files to Modify:

#### 1. API Client
**From**: `classes/api/cloudflare_client.php`  
**To**: `classes/api/s3_client.php`

```php
class s3_client {
    private $s3_client;
    private $cloudfront_client;
    private $bucket;
    private $region;
    
    public function get_presigned_upload_url($filename, $content_type);
    public function delete_object($s3_key);
    public function get_signed_url($s3_key, $expiry = 86400);
}
```

#### 2. Upload JavaScript
**From**: Uses tus protocol  
**To**: Direct POST to S3

```javascript
// Upload directly to S3 using presigned POST
const formData = new FormData();
formData.append('key', s3Key);
formData.append('file', videoFile);
// ... add presigned POST fields

fetch(presignedUrl, {
    method: 'POST',
    body: formData
});
```

#### 3. Player Template
**From**: Cloudflare Stream iframe  
**To**: Video.js player

```html
<video id="video-player" class="video-js vjs-default-skin" controls>
    <source src="{{signed_url}}" type="video/mp4">
</video>
```

#### 4. Settings
**From**: Cloudflare API token, Account ID  
**To**: AWS Access Key, Secret Key, Bucket, Region, CloudFront Distribution

---

## AWS Setup Required

### 1. S3 Bucket
```bash
# Create bucket
aws s3 mb s3://your-moodle-videos

# Enable CORS
{
  "CORSRules": [{
    "AllowedOrigins": ["https://your-moodle-site.com"],
    "AllowedMethods": ["GET", "POST", "PUT"],
    "AllowedHeaders": ["*"]
  }]
}
```

### 2. CloudFront Distribution
- Origin: S3 bucket
- Restrict Bucket Access: Yes
- Trusted Signers: Self
- Create CloudFront Key Pair

### 3. IAM User
Permissions needed:
- `s3:PutObject`
- `s3:GetObject`
- `s3:DeleteObject`
- `cloudfront:CreateInvalidation`

---

## Effort Estimation

### Time Required:
- **Setup AWS infrastructure**: 2-4 hours
- **Copy and modify plugin code**: 8-12 hours
- **Replace API client**: 4-6 hours
- **Update upload flow**: 4-6 hours
- **Update playback flow**: 4-6 hours
- **Testing**: 4-8 hours
- **Documentation**: 2-4 hours

**Total: 28-46 hours (3.5 to 6 days)**

### Complexity: Medium
- You already have 70% of the code
- Main changes: API client and player
- AWS SDK is well-documented

---

## Recommendation

### For Your Situation:

**Create a separate S3 plugin** because:

1. ✅ **You can test it now** - AWS free tier available
2. ✅ **Lower cost** - No $5/month minimum
3. ✅ **Quick to implement** - Reuse existing code
4. ✅ **No risk** - Cloudflare plugin stays intact
5. ✅ **Flexibility** - Users choose which to use

### Next Steps:

1. **Create new plugin**: `assignsubmission_s3video`
2. **Copy structure** from Cloudflare Stream plugin
3. **Replace API client** with AWS S3 SDK
4. **Replace player** with Video.js
5. **Test with AWS free tier**
6. **Release as v1.0.0**

---

## Would You Like Me To:

1. **Create the S3 plugin structure** (copy and modify existing plugin)
2. **Write the S3 API client** class
3. **Create AWS setup guide** (step-by-step)
4. **Build both plugins** and let users choose

Let me know which approach you prefer!
