# Live Server Testing Guide - Private Video Implementation

## 🎯 Quick Testing Steps

Follow these steps in order to verify the private video implementation is working on your live server.

---

## Step 1: Clear Moodle Cache ⚠️ REQUIRED

**Option A: Via Web Interface (Recommended)**
1. Log into your Moodle as admin
2. Go to: **Site administration > Development > Purge all caches**
3. Click **"Purge all caches"** button
4. Wait for confirmation message

**Option B: Via Command Line**
```bash
ssh user@your-ec2-server
cd /path/to/moodle
php admin/cli/purge_caches.php
```

---

## Step 2: Run Automated Test Script

**URL:** `https://dev.aktrea.net/mod/assign/submission/cloudflarestream/test_private_video.php`

### What to Check:

#### ✅ Test 1: Upload Configuration
Look for:
```
requireSignedURLs: ✅ TRUE (PRIVATE)
✅ PASS: Videos are being uploaded as PRIVATE
```

❌ If you see "FALSE (PUBLIC)" - the file wasn't updated correctly

#### ✅ Test 2: Token Generation
Look for:
```
✅ Token generated successfully
Token length: 500+ characters
✅ PASS: Token is valid and properly formatted
```

❌ If you see errors - check signing keys in plugin settings

---

## Step 3: Manual Upload Test

### 3.1 Upload a New Video

1. Go to any assignment in your Moodle
2. Click **"Add submission"**
3. Select **"Cloudflare Stream Video"**
4. Click **"Choose file"** and upload a video
5. Wait for upload to complete
6. Check status shows **"Processing"** then **"Ready"**

### 3.2 View the Submission

1. Click **"View submission"** or refresh the page
2. You should see the video player

---

## Step 4: Browser Console Check (IMPORTANT)

### 4.1 Open Developer Tools
- **Chrome/Edge:** Press `F12` or `Ctrl+Shift+I`
- **Firefox:** Press `F12` or `Ctrl+Shift+K`
- **Safari:** Press `Cmd+Option+I`

### 4.2 Go to Console Tab
Click on the **"Console"** tab in developer tools

### 4.3 Look for These Messages:
```
Cloudflare player embedded with IFRAME method
Video UID: [32-character string like: d5325f69625b6902bae68922a16485e3]
Token length: [number like: 523]
```

### ✅ Success Indicators:
- ✅ You see "IFRAME method" message
- ✅ Video UID is shown (32 characters)
- ✅ Token length is 500+ characters
- ✅ No red error messages
- ✅ No 401 errors

### ❌ Failure Indicators:
- ❌ Red error messages
- ❌ 401 Unauthorized errors
- ❌ "Failed to get playback token"
- ❌ No "IFRAME method" message

---

## Step 5: Inspect Video Element

### 5.1 Right-Click on Video Player
Right-click directly on the video player area

### 5.2 Select "Inspect" or "Inspect Element"
This opens the HTML inspector

### 5.3 Find the `<iframe>` Tag
Look for something like:
```html
<iframe 
  src="https://iframe.videodelivery.net/d5325f69625b6902bae68922a16485e3?token=eyJhbGciOiJSUzI1NiIsImtpZCI6ImM2OTViNzU4MmRkYjAyYzE0NjFkNmMxYzAyNGQzM2FiIn0..."
  style="border: none; width: 100%; aspect-ratio: 16/9; min-height: 400px;"
  allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
  allowfullscreen="true">
</iframe>
```

### ✅ What to Verify:
- ✅ Tag is `<iframe>` (not `<stream>`)
- ✅ src starts with `https://iframe.videodelivery.net/`
- ✅ src contains `?token=` parameter
- ✅ Token is a long string (500+ chars)

### ❌ Wrong Implementation:
- ❌ Tag is `<stream>` instead of `<iframe>`
- ❌ No `?token=` in the src
- ❌ src is just the token without video UID

---

## Step 6: Verify in Cloudflare Dashboard

### 6.1 Log into Cloudflare
1. Go to https://dash.cloudflare.com
2. Select your account
3. Go to **Stream** section

### 6.2 Find Your Video
1. Look for the video you just uploaded
2. Click on it to see details

### 6.3 Check Privacy Setting
Look for:
```
Signed URLs Required: Yes ✅
```

If it says "No" - the video was uploaded as PUBLIC (old code still running)

---

## Step 7: Test Video Playback

### 7.1 Play the Video
Click the play button on the video

### ✅ Success:
- Video plays smoothly
- No errors appear
- Controls work (play, pause, volume)
- Fullscreen works

### ❌ Issues:
- Black screen
- "Video not found" error
- 401 Unauthorized error
- Video doesn't load

---

## 📊 Quick Results Checklist

Mark each item as you test:

- [ ] **Cache cleared** on live server
- [ ] **Test script** shows "PRIVATE" videos
- [ ] **Test script** shows token generated
- [ ] **Video uploaded** successfully
- [ ] **Console shows** "IFRAME method"
- [ ] **Console shows** Video UID
- [ ] **Console shows** Token length
- [ ] **No 401 errors** in console
- [ ] **Inspect shows** `<iframe>` tag
- [ ] **Inspect shows** `?token=` parameter
- [ ] **Cloudflare shows** "Signed URLs Required: Yes"
- [ ] **Video plays** without errors

---

## ✅ If All Tests Pass

**Congratulations!** 🎉 Private video implementation is working correctly!

Your videos are now:
- ✅ Uploaded as PRIVATE
- ✅ Require signed tokens for playback
- ✅ Using IFRAME method
- ✅ Tokens expire after 24 hours
- ✅ Secure and protected

---

## ❌ If Tests Fail

### Problem 1: Test Script Shows "PUBLIC" Instead of "PRIVATE"

**Cause:** `cloudflare_client.php` wasn't updated correctly

**Solution:**
1. Check line 131 in `classes/api/cloudflare_client.php`
2. Should be: `'requireSignedURLs' => true`
3. Re-upload the file if needed
4. Clear cache again

### Problem 2: Console Shows Old `<stream>` Method

**Cause:** `player.min.js` wasn't updated correctly

**Solution:**
1. Verify you copied `amd/build/player.min.js` to live server
2. Check file size - should be ~20KB
3. Re-upload if needed
4. Clear cache again
5. Hard refresh browser: `Ctrl+F5` or `Cmd+Shift+R`

### Problem 3: 401 Unauthorized Errors

**Cause:** Token generation failing or signing keys not configured

**Solution:**
1. Go to plugin settings
2. Verify **Signing Key ID** is set
3. Verify **Signing Key** (private key) is set
4. Check test script Test 2 for token generation errors

### Problem 4: Video Doesn't Play (Black Screen)

**Possible Causes:**
- Video still processing (wait a few minutes)
- Token expired (refresh page)
- Domain not in Cloudflare allowedOrigins
- Browser blocking iframe

**Solution:**
1. Wait 2-3 minutes for video processing
2. Refresh the page
3. Check Cloudflare Stream settings > Allowed Origins
4. Add your domain: `https://dev.aktrea.net`
5. Try different browser

### Problem 5: "Failed to Get Playback Token"

**Cause:** Backend token generation failing

**Solution:**
1. Check `ajax/get_playback_token.php` exists
2. Check signing keys in plugin settings
3. Check PHP error logs on server
4. Run test script to see detailed error

---

## 🔄 Rollback (If Nothing Works)

If private videos don't work and you need to go back to PUBLIC videos:

```bash
# SSH to your server
ssh user@your-ec2-server

# Go to plugin directory
cd /path/to/moodle/mod/assign/submission/cloudflarestream

# Restore backups
cp backups/cloudflare_client.php.backup classes/api/cloudflare_client.php
cp backups/player.js.backup amd/src/player.js
cp amd/src/player.js amd/build/player.min.js

# Clear cache
cd /path/to/moodle
php admin/cli/purge_caches.php
```

Then test again - PUBLIC videos should work as before.

---

## 📞 Need Help?

### Check These Files:
- `QUICK_TEST_GUIDE.md` - Quick reference
- `TESTING_CHECKLIST.md` - Detailed checklist
- `PRIVATE_VIDEO_IMPLEMENTATION.md` - Full implementation details
- `FILES_MODIFIED.md` - Deployment guide

### Common Issues:
1. **Cache not cleared** - Most common issue!
2. **Wrong file uploaded** - Verify file sizes
3. **Browser cache** - Hard refresh with `Ctrl+F5`
4. **Signing keys missing** - Check plugin settings

---

## 🎬 Testing Summary

**Total Time:** ~10 minutes

**Steps:**
1. Clear cache (1 min)
2. Run test script (2 min)
3. Upload video (3 min)
4. Check console (1 min)
5. Inspect element (1 min)
6. Verify Cloudflare (2 min)

**Expected Result:** Private videos working with IFRAME method! 🚀
