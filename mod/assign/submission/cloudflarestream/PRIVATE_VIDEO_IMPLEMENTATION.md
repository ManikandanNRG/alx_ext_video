# Private Video Implementation - IFRAME Method

**Date:** 2025-10-29
**Status:** ✅ Implemented
**Method:** IFRAME with Signed Tokens

---

## What Changed

### 1. Upload Configuration (cloudflare_client.php)

**Before (PUBLIC videos):**
```php
'requireSignedURLs' => false  // Videos are public
```

**After (PRIVATE videos):**
```php
'requireSignedURLs' => true  // Videos require signed tokens
```

**File:** `classes/api/cloudflare_client.php` line 131

---

### 2. Player Implementation (player.js)

**Before (WRONG method for private videos):**
```javascript
// Used <stream> element with token as src
const streamElement = $('<stream>')
    .attr('src', this.token)  // ❌ Doesn't work for private videos
    .attr('controls', true);
```

**After (CORRECT IFRAME method):**
```javascript
// Use IFRAME with video UID and token parameter
const iframeUrl = `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`;

const iframe = $('<iframe>')
    .attr('src', iframeUrl)  // ✅ Correct per Cloudflare docs
    .attr('style', 'border: none; width: 100%; aspect-ratio: 16/9;')
    .attr('allowfullscreen', true);
```

**File:** `amd/src/player.js` embedPlayer() method

---

### 3. Token Refresh (player.js)

**Updated to work with IFRAME:**
```javascript
// Update iframe src with new token (seamless refresh)
const newUrl = `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`;
$(this.playerIframe).attr('src', newUrl);
```

**File:** `amd/src/player.js` refreshToken() method

---

## How It Works Now

### Upload Flow (PRIVATE videos)
```
1. Student uploads video
2. Cloudflare creates video with requireSignedURLs: true
3. Video is PRIVATE - cannot be accessed without token
4. Video UID saved to database
```

### Playback Flow (with signed tokens)
```
1. Student views submission
2. player.js requests signed token from backend
3. get_playback_token.php generates JWT token (24hr expiry)
4. IFRAME created: https://iframe.videodelivery.net/VIDEO_UID?token=TOKEN
5. Cloudflare validates token and plays video
6. Token auto-refreshes before expiry
```

---

## Security Benefits

### Before (PUBLIC videos):
- ✅ Moodle login required
- ✅ Assignment permissions checked
- ✅ Domain whitelist (allowedOrigins)
- ❌ Video URL accessible if someone has UID
- ❌ No expiring access

### After (PRIVATE videos):
- ✅ Moodle login required
- ✅ Assignment permissions checked
- ✅ Domain whitelist (allowedOrigins)
- ✅ **Video requires signed token**
- ✅ **Tokens expire (24 hours)**
- ✅ **Cannot access video without valid token**
- ✅ **Token tied to specific video UID**

---

## Testing the Implementation

### Test 1: Upload New Video (will be PRIVATE)

1. Go to assignment submission page
2. Upload a video
3. Video will be uploaded as PRIVATE (requireSignedURLs: true)
4. Check Cloudflare dashboard - video should show "Signed URLs Required: Yes"

### Test 2: Playback with Signed Token

1. View the submission with video
2. Open browser console (F12)
3. Look for logs:
   ```
   Cloudflare player embedded with IFRAME method
   Video UID: [32-char string]
   Token length: [~500+ chars]
   ```
4. Video should play in IFRAME

### Test 3: Verify IFRAME URL

1. Right-click on video player
2. Inspect element
3. Find the `<iframe>` tag
4. Check src attribute:
   ```html
   <iframe src="https://iframe.videodelivery.net/VIDEO_UID?token=eyJhbGc...">
   ```
5. Should see video UID and token parameter

### Test 4: Token Expiry (optional - takes 24 hours)

1. Leave video page open for 23 hours 55 minutes
2. Token should auto-refresh at 23:55 (5 min before expiry)
3. Video continues playing without interruption

---

## Existing Videos (Uploaded as PUBLIC)

### Option A: Keep as PUBLIC
- Existing videos still work
- They don't require tokens
- Less secure but functional

### Option B: Convert to PRIVATE
Run this script for each video:
```bash
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/update_video_security.php?videouid=VIDEO_UID&confirm=1
```

---

## Rollback Instructions

If private videos don't work, restore backups:

```bash
# Restore player.js
copy mod/assign/submission/cloudflarestream/backups/player.js.backup mod/assign/submission/cloudflarestream/amd/src/player.js

# Restore cloudflare_client.php
copy mod/assign/submission/cloudflarestream/backups/cloudflare_client.php.backup mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php

# Copy to build directory
copy mod/assign/submission/cloudflarestream/amd/src/player.js mod/assign/submission/cloudflarestream/amd/build/player.min.js

# Clear Moodle cache
# Go to: Site administration > Development > Purge all caches
```

---

## Files Modified

1. ✅ `classes/api/cloudflare_client.php` - Changed requireSignedURLs to true
2. ✅ `amd/src/player.js` - Changed to IFRAME method
3. ✅ `amd/build/player.min.js` - Rebuilt with new code

## Files Backed Up

1. ✅ `backups/player.js.backup` - Original working player
2. ✅ `backups/cloudflare_client.php.backup` - Original upload config
3. ✅ `backups/BACKUP_INFO.md` - Backup documentation

---

## Next Steps

1. **Clear Moodle cache** (Site administration > Development > Purge all caches)
2. **Test upload** - Upload a new video
3. **Test playback** - View the video in submission
4. **Check console** - Verify IFRAME method is being used
5. **Verify security** - Check video is private in Cloudflare dashboard

---

## Troubleshooting

### Video doesn't play
- Check browser console for errors
- Verify token is being generated (check console logs)
- Ensure video is ready (status = 'ready' in database)
- Check Cloudflare dashboard - video should be "ready"

### 401 Unauthorized errors
- Token might be invalid or expired
- Check get_playback_token.php is generating tokens correctly
- Verify signing keys are configured in Cloudflare

### Black screen
- IFRAME might be blocked by browser
- Check browser console for CSP errors
- Verify allowedOrigins in Cloudflare includes your domain

### Token not refreshing
- Check tokenExpiry is being set correctly
- Verify scheduleTokenRefresh() is being called
- Check browser console for refresh logs

---

## Success Criteria

✅ New videos upload as PRIVATE (requireSignedURLs: true)
✅ Videos play using IFRAME with token parameter
✅ Tokens auto-refresh before expiry
✅ No 401 errors in console
✅ Video cannot be accessed without valid token
✅ All existing functionality still works

---

## Implementation Complete!

The plugin now supports **PRIVATE videos with signed tokens** using the **IFRAME method** as recommended by Cloudflare documentation.

This provides:
- ✅ True video privacy
- ✅ Expiring access tokens
- ✅ Token-based authentication
- ✅ Automatic token refresh
- ✅ Secure video delivery
