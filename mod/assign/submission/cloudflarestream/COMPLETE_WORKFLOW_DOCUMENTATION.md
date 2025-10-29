# Cloudflare Stream Plugin - Complete Workflow Documentation

## Plugin Purpose
Moodle Assignment Submission Plugin that allows students to upload video assignments directly to Cloudflare Stream, and teachers to view/grade them.

---

## Complete Workflow (As Currently Implemented)

### 1. STUDENT UPLOADS VIDEO

**Location:** Assignment submission page

**Process:**
1. Student clicks "Add submission" on assignment
2. Plugin shows upload interface (drag-drop or file select)
3. JavaScript (`uploader.js`) handles file selection
4. **Step 1:** Request upload URL from Moodle
   - AJAX call to `ajax/get_upload_url.php`
   - Backend calls Cloudflare API to get direct upload URL
   - Returns: `{uploadURL, uid, submissionid}`

5. **Step 2:** Upload directly to Cloudflare
   - JavaScript uploads file to Cloudflare's uploadURL
   - Shows progress bar (0-100%)
   - No file touches Moodle server

6. **Step 3:** Confirm upload (with retry)
   - AJAX call to `ajax/confirm_upload.php`
   - Backend checks Cloudflare API for video status
   - Retries up to 5 times (3s, 5s, 7s, 10s, 15s delays)
   - Saves to database: `assignsubmission_cfstream` table

7. **Step 4:** Save submission
   - Hidden form fields populated with video UID
   - Moodle saves submission with video reference

**Database Record Created:**
```
assignsubmission_cfstream:
- video_uid: Cloudflare video ID
- submission: Moodle submission ID
- assignment: Moodle assignment ID
- upload_status: 'ready', 'uploading', 'pending', or 'error'
- file_size: bytes
- duration: seconds
- upload_timestamp: unix timestamp
```

---

### 2. TEACHER VIEWS/GRADES SUBMISSION

**Location:** Grading interface (`action=grader`)

**Process:**
1. Teacher opens student submission for grading
2. Plugin's `view()` method called
3. Detects grading context (checks URL parameters)
4. Renders video player using template: `templates/player.mustache`
5. **Player loads:**
   - Template includes `amd/build/player.min.js`
   - JavaScript requests playback token
   - AJAX call to `ajax/get_playback_token.php`
   - Backend generates signed token (24-hour expiry)
   - JavaScript creates player with token

**Current Player Implementation:**
```javascript
// player.js creates <stream> element
const streamElement = $('<stream>')
    .attr('src', this.token)  // Token as source
    .attr('controls', true);
```

**Access Control:**
- `verify_video_access()` function checks:
  - Is user the submission owner? (student)
  - Does user have grading capability? (teacher)
  - Is user site admin?
- Returns 403 if access denied

---

## Current Video Security Model

### Videos are set to: **requireSignedURLs: false** (PUBLIC)

**Why PUBLIC?**
- Signed URL implementation with `<stream>` element doesn't work reliably
- SDK extracts video UID from token and tries to access directly
- Results in 401 errors for private videos

**Security Layers (Even for Public Videos):**
1. **Moodle Login** - Must be logged into Moodle
2. **Assignment Permissions** - Must be enrolled in course
3. **Access Control** - `verify_video_access()` checks permissions
4. **Domain Whitelist** - `allowedOrigins` set to your domain
5. **Video UID is not guessable** - 32-character random string

---

## Key Files and Their Roles

### Core Plugin Files:
- **lib.php** - Main plugin class, handles submission save/view
- **locallib.php** - Utility functions, access control
- **settings.php** - Admin configuration (API token, account ID)

### AJAX Endpoints:
- **ajax/get_upload_url.php** - Get Cloudflare upload URL
- **ajax/confirm_upload.php** - Confirm upload and check status
- **ajax/get_playback_token.php** - Generate signed playback token

### JavaScript:
- **amd/src/uploader.js** - Handles file upload to Cloudflare
- **amd/src/player.js** - Handles video playback

### Templates:
- **templates/upload_form.mustache** - Upload interface
- **templates/player.mustache** - Video player

### API Client:
- **classes/api/cloudflare_client.php** - Cloudflare API wrapper
  - `get_direct_upload_url()` - Get upload URL
  - `get_video_details()` - Check video status
  - `generate_signed_token()` - Create playback token

### Database:
- **db/install.xml** - Table schema
- Table: `assignsubmission_cfstream`

---

## Current Issues

### ❌ PROBLEM: Private Video Playback Not Working

**What we tried:**
1. Using `<stream src="TOKEN">` - SDK ignores this
2. Using `<stream src="VIDEO_UID">` then `element.token = TOKEN` - SDK doesn't use token
3. Using `Stream(container, TOKEN)` - Wrong syntax

**Why it fails:**
- Cloudflare Stream SDK extracts video UID from token
- Then tries to access video directly without authentication
- Gets 401 Unauthorized because video is private

**What Cloudflare docs say should work:**
```javascript
// Method 1: IFRAME
<iframe src="https://iframe.videodelivery.net/VIDEO_UID?token=TOKEN"></iframe>

// Method 2: Stream SDK
const player = Stream(container, {
  video: 'VIDEO_UID',
  token: 'SIGNED_TOKEN'
});
```

**What we haven't tried yet:**
- IFRAME method with `?token=` parameter
- Stream SDK with config object `{video, token}`

---

## What Works Right Now

✅ **Upload** - Works perfectly
✅ **Status tracking** - Works with retry logic
✅ **Database storage** - Works
✅ **Access control** - Works
✅ **Public video playback** - Works (Method 1 in test_video_playback.php)
❌ **Private video playback** - Doesn't work

---

## Decision Needed

### Option 1: Keep PUBLIC videos (Current Working State)
- Videos: `requireSignedURLs: false`
- Security: Domain whitelist + Moodle permissions
- Player: Simple iframe (no token needed)
- **Pros:** Works reliably, simple, fast
- **Cons:** Videos technically accessible if someone has the UID

### Option 2: Implement PRIVATE videos with IFRAME method
- Videos: `requireSignedURLs: true`
- Player: `<iframe src="...?token=TOKEN">`
- **Pros:** True private videos, token-based access
- **Cons:** Need to implement and test iframe approach

### Option 3: Implement PRIVATE videos with Stream SDK (config object)
- Videos: `requireSignedURLs: true`
- Player: `Stream(container, {video: UID, token: TOKEN})`
- **Pros:** Full SDK features, proper implementation
- **Cons:** More complex, need to rewrite player.js

---

## Recommendation

**For Moodle plugin: Use Option 2 (IFRAME with signed tokens)**

**Reasons:**
1. Simpler than SDK (just HTML, no JS complexity)
2. Works with signed tokens per Cloudflare docs
3. Moodle-friendly (template-based)
4. Reliable and well-documented by Cloudflare

**Implementation:**
- Change `requireSignedURLs: true` on upload
- Update player template to use iframe with `?token=` parameter
- Keep existing token generation (already working)

---

## Summary

The plugin is **95% complete**. Upload, storage, access control all work perfectly. The only issue is the video player implementation for private videos. We need to switch from the `<stream>` element approach to either iframe or proper SDK config object approach.
