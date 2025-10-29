# Quick Test Guide - Private Video Implementation

## 🚀 Quick Start

### Step 1: Clear Moodle Cache
```
Site administration > Development > Purge all caches
```

### Step 2: Run Test Script
```
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/test_private_video.php
```

This will verify:
- ✅ Videos upload as PRIVATE
- ✅ Tokens generate correctly
- ✅ Configuration is correct

### Step 3: Test Upload & Playback

1. **Upload a video:**
   - Go to any assignment
   - Upload a video
   - Wait for processing

2. **View the video:**
   - View your submission
   - Video should play in IFRAME

3. **Check browser console (F12):**
   ```
   Cloudflare player embedded with IFRAME method
   Video UID: [32-char string]
   Token length: [500+ chars]
   ```

4. **Inspect video element:**
   - Right-click on video
   - Inspect element
   - Should see:
   ```html
   <iframe src="https://iframe.videodelivery.net/VIDEO_UID?token=eyJhbGc...">
   ```

---

## ✅ Success Checklist

- [ ] Test script shows "requireSignedURLs: TRUE (PRIVATE)"
- [ ] Token generates successfully
- [ ] Video plays in browser
- [ ] Console shows "IFRAME method"
- [ ] No 401 errors in console
- [ ] Iframe src contains token parameter

---

## ❌ If Something Goes Wrong

### Rollback to PUBLIC videos:

```bash
# 1. Restore backups
copy mod/assign/submission/cloudflarestream/backups/player.js.backup mod/assign/submission/cloudflarestream/amd/src/player.js
copy mod/assign/submission/cloudflarestream/backups/cloudflare_client.php.backup mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php

# 2. Rebuild
copy mod/assign/submission/cloudflarestream/amd/src/player.js mod/assign/submission/cloudflarestream/amd/build/player.min.js

# 3. Clear cache
Site administration > Development > Purge all caches
```

---

## 🔍 Troubleshooting

### Video doesn't play
- Check console for errors
- Verify token is generated (check console logs)
- Ensure video status is 'ready'

### 401 Unauthorized
- Token might be invalid
- Check signing keys in settings
- Verify token expiry hasn't passed

### Black screen
- Check browser console for CSP errors
- Verify domain in Cloudflare allowedOrigins
- Check iframe is not blocked

---

## 📊 What Changed

| Component | Before (PUBLIC) | After (PRIVATE) |
|-----------|----------------|-----------------|
| Upload | requireSignedURLs: false | requireSignedURLs: true |
| Player | `<stream src="TOKEN">` | `<iframe src="...?token=TOKEN">` |
| Security | Domain whitelist only | Token-based + expiry |
| Access | Anyone with UID | Only with valid token |

---

## 📞 Need Help?

Check these files:
- `PRIVATE_VIDEO_IMPLEMENTATION.md` - Full implementation details
- `CURRENT_STATE_ANALYSIS.md` - Before/after comparison
- `backups/BACKUP_INFO.md` - Rollback instructions
