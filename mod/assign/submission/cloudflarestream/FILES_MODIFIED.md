# Files Modified for Private Video Implementation

**Date:** 2025-10-29  
**Implementation:** IFRAME Method for Private Videos

---

## 📝 Files You Need to Copy to Live Server

### 1. Core Implementation Files (REQUIRED)

#### A. PHP Backend File
```
mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
```
**Change:** Line 131  
**Before:** `'requireSignedURLs' => false`  
**After:** `'requireSignedURLs' => true`

#### B. JavaScript Source File
```
mod/assign/submission/cloudflarestream/amd/src/player.js
```
**Changes:**
- Line 157-180: embedPlayer() method - Changed to IFRAME
- Line 210-240: refreshToken() method - Updated for IFRAME

#### C. JavaScript Build File (REQUIRED for Moodle)
```
mod/assign/submission/cloudflarestream/amd/build/player.min.js
```
**Status:** ✅ Rebuilt - This is a COPY of player.js (Moodle uses this file)

---

### 2. Backup Files (for safety)

```
mod/assign/submission/cloudflarestream/backups/player.js.backup
mod/assign/submission/cloudflarestream/backups/cloudflare_client.php.backup
mod/assign/submission/cloudflarestream/backups/BACKUP_INFO.md
```

---

### 3. Documentation Files (optional but recommended)

```
mod/assign/submission/cloudflarestream/PRIVATE_VIDEO_IMPLEMENTATION.md
mod/assign/submission/cloudflarestream/CURRENT_STATE_ANALYSIS.md
mod/assign/submission/cloudflarestream/QUICK_TEST_GUIDE.md
mod/assign/submission/cloudflarestream/TESTING_CHECKLIST.md
mod/assign/submission/cloudflarestream/IMPLEMENTATION_COMPLETE.md
mod/assign/submission/cloudflarestream/CHANGES_SUMMARY.txt
mod/assign/submission/cloudflarestream/FILES_MODIFIED.md (this file)
```

---

### 4. Test Script (optional)

```
mod/assign/submission/cloudflarestream/test_private_video.php
```

---

## 🚀 Deployment Steps for EC2 Live Server

### Step 1: Copy Files to Live Server

Copy these 3 REQUIRED files:

```bash
# From local to EC2
scp mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/classes/api/

scp mod/assign/submission/cloudflarestream/amd/src/player.js user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/amd/src/

scp mod/assign/submission/cloudflarestream/amd/build/player.min.js user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/amd/build/
```

### Step 2: Copy Backup Files (recommended)

```bash
# Create backups directory on EC2
ssh user@ec2 "mkdir -p /path/to/moodle/mod/assign/submission/cloudflarestream/backups"

# Copy backup files
scp mod/assign/submission/cloudflarestream/backups/* user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/backups/
```

### Step 3: Copy Documentation (optional)

```bash
scp mod/assign/submission/cloudflarestream/PRIVATE_VIDEO_IMPLEMENTATION.md user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/

scp mod/assign/submission/cloudflarestream/QUICK_TEST_GUIDE.md user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/

scp mod/assign/submission/cloudflarestream/test_private_video.php user@ec2:/path/to/moodle/mod/assign/submission/cloudflarestream/
```

### Step 4: Clear Moodle Cache on Live Server

```bash
# SSH to EC2
ssh user@ec2

# Clear Moodle cache
cd /path/to/moodle
php admin/cli/purge_caches.php
```

Or via web interface:
```
Site administration > Development > Purge all caches
```

### Step 5: Test on Live Server

1. Run test script: `https://your-domain.com/mod/assign/submission/cloudflarestream/test_private_video.php`
2. Upload a video
3. View submission
4. Check browser console for "IFRAME method"

---

## 📊 File Comparison

### cloudflare_client.php

**Line 131 Change:**
```php
// OLD (PUBLIC videos)
'requireSignedURLs' => false  // Make videos public for easier testing

// NEW (PRIVATE videos)
'requireSignedURLs' => true  // Upload as PRIVATE - requires signed tokens for playback
```

### player.js - embedPlayer() Method

**OLD (didn't work for private):**
```javascript
embedPlayer() {
    this.container.empty();
    
    const streamElement = $('<stream>')
        .attr('src', this.token)  // ❌ Wrong
        .attr('controls', true);
    
    this.container.append(streamElement);
}
```

**NEW (IFRAME method - correct):**
```javascript
embedPlayer() {
    this.container.empty();
    
    // Create IFRAME with video UID and token parameter
    const iframeUrl = `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`;
    
    const iframe = $('<iframe>')
        .attr('src', iframeUrl)  // ✅ Correct
        .attr('style', 'border: none; width: 100%; aspect-ratio: 16/9; min-height: 400px;')
        .attr('allowfullscreen', true);
    
    this.container.append(iframe);
    this.playerIframe = iframe[0];
}
```

### player.js - refreshToken() Method

**OLD:**
```javascript
// Reload player with new token
this.embedPlayer();
```

**NEW:**
```javascript
// Update iframe src with new token (seamless refresh)
if (this.playerIframe) {
    const newUrl = `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`;
    $(this.playerIframe).attr('src', newUrl);
}
```

---

## ✅ Verification Checklist

After deploying to live server:

- [ ] Files copied to EC2
- [ ] Moodle cache cleared
- [ ] Test script runs successfully
- [ ] Test script shows "requireSignedURLs: TRUE (PRIVATE)"
- [ ] New video uploads successfully
- [ ] Video plays in browser
- [ ] Browser console shows "IFRAME method"
- [ ] No 401 errors in console
- [ ] Cloudflare dashboard shows "Signed URLs Required: Yes"

---

## 🔄 Rollback on Live Server (if needed)

```bash
# SSH to EC2
ssh user@ec2

# Restore backups
cd /path/to/moodle/mod/assign/submission/cloudflarestream

cp backups/cloudflare_client.php.backup classes/api/cloudflare_client.php
cp backups/player.js.backup amd/src/player.js
cp amd/src/player.js amd/build/player.min.js

# Clear cache
cd /path/to/moodle
php admin/cli/purge_caches.php
```

---

## 📞 Summary

**3 files modified:**
1. `classes/api/cloudflare_client.php` - Upload as PRIVATE
2. `amd/src/player.js` - IFRAME player method
3. `amd/build/player.min.js` - Rebuilt (copy of src)

**All changes backed up in `backups/` directory**

**Ready to deploy to EC2 live server!** 🚀
