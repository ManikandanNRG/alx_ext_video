# Current State Analysis - Cloudflare Stream Plugin

## Executive Summary

After 2 days of debugging, here's the complete picture of what's working and what's not.

---

## 🎯 CURRENT UPLOAD CONFIGURATION

### Line 131 in `cloudflare_client.php`:
```php
'requireSignedURLs' => false  // Videos uploaded as PUBLIC
```

**This means:**
- ✅ All videos are uploaded as **PUBLIC** by default
- ✅ No signed URLs required for playback
- ✅ Videos work immediately after upload
- ❌ You've been manually converting them to PRIVATE using test scripts

---

## 🎬 CURRENT PLAYER IMPLEMENTATION

### Method: `<stream>` Element with Token (WRONG)

**Location:** `amd/src/player.js` line 189-199

```javascript
embedPlayer() {
    // Create <stream> element with token as src
    const streamElement = $('<stream>')
        .attr('src', this.token)  // ❌ WRONG - Token as src doesn't work
        .attr('controls', true)
        .css({width: '100%', height: '100%'});
    
    this.container.append(streamElement);
}
```

**Why This Fails:**
1. The `<stream>` element SDK extracts the video UID from the token
2. Then tries to access the video directly using that UID
3. Gets 401 error because video is private (requireSignedURLs: true)
4. The SDK doesn't actually use the token for authentication

**Evidence from Console Errors:**
```
401 errors on: customer-h1fjam2t1q5d55si.cloudflarestream.com/103366d38ef2bd1ea4b02e6ec6e0dcde/
                                                                    ↑ Video UID, not token
```

---

## ✅ WHAT'S WORKING

### 1. Upload Workflow (100% Working)
```
Student clicks upload
    ↓
uploader.js requests upload URL
    ↓
get_upload_url.php calls cloudflare_client.php
    ↓
Cloudflare creates direct upload URL (PUBLIC video)
    ↓
Browser uploads directly to Cloudflare
    ↓
confirm_upload.php saves to database
    ↓
Status polling checks video processing
    ↓
Video ready for playback
```

### 2. Public Video Playback (100% Working)
- When videos are PUBLIC (requireSignedURLs: false)
- Simple `<stream src="VIDEO_UID">` works perfectly
- No tokens needed
- Already tested and confirmed working

### 3. Database & Access Control (100% Working)
- Submission tracking
- Permission checks
- Video management
- GDPR compliance

---

## ❌ WHAT'S NOT WORKING

### Private Video Playback with Signed URLs

**Current Implementation:**
```javascript
// ❌ WRONG - Doesn't work
<stream src="TOKEN"></stream>
```

**Why It Fails:**
- SDK extracts UID from token
- Tries to access video directly
- Gets 401 because video is private
- Token is never used for authentication

---

## 🔧 THE TRANSFORMATION WORKFLOW

### Current Reality:
```
1. Video uploaded as PUBLIC (line 131: requireSignedURLs: false)
2. Video works immediately with simple player
3. You manually run test script to make it PRIVATE
4. Video stops working because player implementation is wrong
```

### What You've Been Doing:
```bash
# Manual conversion to private
make_video_public.php?videouid=XXX&confirm=1  # Make public
update_video_security.php?videouid=XXX        # Make private
```

**This is NOT the normal workflow!** You're manually changing video privacy after upload for testing.

---

## 📊 THREE IMPLEMENTATION OPTIONS

### Option 1: Keep PUBLIC Videos (Recommended)
**No code changes needed - already working!**

```php
// Line 131 - Keep as is
'requireSignedURLs' => false
```

**Security:**
- ✅ Moodle login required
- ✅ Assignment permissions checked
- ✅ Domain whitelist (allowedOrigins)
- ✅ Video UID is not guessable

**Player:**
```javascript
// Simple, works now
<stream src="VIDEO_UID" controls></stream>
```

**Pros:**
- ✅ Already working
- ✅ No changes needed
- ✅ Fast playback
- ✅ No token complexity

**Cons:**
- ❌ Video URLs are technically accessible if someone has the UID
- ❌ No expiring access

---

### Option 2: IFRAME with Signed Tokens (Recommended for Private)
**Requires player.js changes**

```php
// Line 131 - Change to private
'requireSignedURLs' => true
```

**Player Implementation:**
```javascript
embedPlayer() {
    // ✅ CORRECT - IFRAME with token parameter
    const iframe = $('<iframe>')
        .attr('src', `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`)
        .attr('style', 'width:100%; aspect-ratio:16/9;')
        .attr('allow', 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture')
        .attr('allowfullscreen', true);
    
    this.container.append(iframe);
}
```

**Pros:**
- ✅ True private videos
- ✅ Expiring tokens
- ✅ Simple implementation
- ✅ Moodle-friendly (just HTML)
- ✅ Per Cloudflare documentation

**Cons:**
- ❌ Requires code changes
- ❌ Token management overhead
- ❌ Less player control

---

### Option 3: Stream SDK with Config Object (Advanced)
**Requires player.js changes + SDK loading**

```php
// Line 131 - Change to private
'requireSignedURLs' => true
```

**Player Implementation:**
```javascript
embedPlayer() {
    // ✅ CORRECT - SDK with config object
    const container = $('<div>').attr('id', 'stream-player');
    this.container.append(container);
    
    // Load SDK then initialize
    const player = Stream(container[0], {
        video: this.videoUid,
        token: this.token
    });
}
```

**Pros:**
- ✅ True private videos
- ✅ Full player control (events, methods)
- ✅ Advanced features

**Cons:**
- ❌ More complex
- ❌ SDK dependency
- ❌ Requires careful token handling

---

## 🎯 MY RECOMMENDATION

### For Your Use Case: **Option 1 (Keep PUBLIC)**

**Why:**
1. **Already working** - No code changes needed
2. **Secure enough** - Moodle handles access control
3. **Fast** - No token generation overhead
4. **Reliable** - No token expiry issues
5. **Simple** - No complexity

**Security is adequate because:**
- Users must log into Moodle
- Assignment permissions are checked
- Video UIDs are not guessable (32-char random strings)
- Domain whitelist prevents embedding elsewhere
- Videos are educational content, not highly sensitive

### If You Need Private Videos: **Option 2 (IFRAME)**

**Why:**
- Simple to implement
- Works reliably per Cloudflare docs
- Moodle-friendly (just HTML)
- Provides expiring access

---

## 🔄 HYBRID APPROACH (Best of Both Worlds)

### Implementation Strategy:

```php
// Add admin setting in settings.php
$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_cloudflarestream/requiresignedurls',
    'Require Signed URLs',
    'Upload videos as private (requires signed tokens for playback)',
    0  // Default: public
));
```

```php
// In cloudflare_client.php line 131
$requiresigned = get_config('assignsubmission_cloudflarestream', 'requiresignedurls');
'requireSignedURLs' => (bool)$requiresigned
```

```javascript
// In player.js - detect and use appropriate method
embedPlayer() {
    if (this.token) {
        // Private video - use IFRAME with token
        this.embedPrivatePlayer();
    } else {
        // Public video - use simple stream element
        this.embedPublicPlayer();
    }
}

embedPublicPlayer() {
    const streamElement = $('<stream>')
        .attr('src', this.videoUid)
        .attr('controls', true);
    this.container.append(streamElement);
}

embedPrivatePlayer() {
    const iframe = $('<iframe>')
        .attr('src', `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`)
        .attr('style', 'width:100%; aspect-ratio:16/9;')
        .attr('allowfullscreen', true);
    this.container.append(iframe);
}
```

**Benefits:**
- ✅ Admin chooses security level
- ✅ Backward compatible
- ✅ Flexible for different use cases
- ✅ Both methods work correctly

---

## 📝 ANSWERS TO YOUR QUESTIONS

### Q1: "Will IFRAME remove currently working public video function?"

**Answer:** NO - We can implement HYBRID approach:
- Detect if video requires signed URL
- Use IFRAME for private videos
- Use `<stream>` element for public videos
- Both work simultaneously

### Q2: "How is video uploaded? Is it private or public?"

**Answer:** Currently **PUBLIC** (line 131: `requireSignedURLs: false`)
- Videos are uploaded as PUBLIC
- They work immediately
- You've been manually converting to PRIVATE for testing
- This is why you're seeing issues - the player doesn't support private videos correctly

### Q3: "How does transformation work?"

**Answer:** There is NO automatic transformation:
- Upload creates PUBLIC video
- It stays PUBLIC unless you manually change it
- Your test scripts (`make_video_public.php`, `update_video_security.php`) manually change privacy
- Normal workflow: video stays as uploaded (PUBLIC)

---

## 🎬 FINAL DECISION NEEDED

**Choose ONE:**

### A) Keep Current (PUBLIC videos) ✅ RECOMMENDED
- No changes needed
- Already working
- Secure enough for educational content

### B) Implement IFRAME for PRIVATE videos
- Change line 131 to `requireSignedURLs: true`
- Update player.js to use IFRAME method
- ~30 minutes of work

### C) Implement HYBRID approach
- Add admin setting
- Support both PUBLIC and PRIVATE
- Auto-detect and use correct player
- ~1 hour of work

**What's your decision?**
