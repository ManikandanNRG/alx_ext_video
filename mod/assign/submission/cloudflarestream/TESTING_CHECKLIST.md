# Testing Checklist - Private Video Implementation

## ✅ Pre-Testing (Complete)

- [x] Code changes implemented
- [x] Backups created
- [x] Documentation written
- [x] No syntax errors
- [x] Build files updated

---

## 📋 Testing Steps

### Step 1: Clear Moodle Cache ⏳
- [ ] Go to: **Site administration > Development > Purge all caches**
- [ ] Click "Purge all caches"
- [ ] Wait for confirmation

### Step 2: Run Automated Test ⏳
- [ ] Open: `https://dev.aktrea.net/mod/assign/submission/cloudflarestream/test_private_video.php`
- [ ] Check Test 1: Should show "requireSignedURLs: TRUE (PRIVATE)"
- [ ] Check Test 2: Should show "Token generated successfully"
- [ ] Verify all tests pass

### Step 3: Manual Upload Test ⏳
- [ ] Go to any assignment
- [ ] Click "Add submission"
- [ ] Upload a video file
- [ ] Wait for upload to complete
- [ ] Check status shows "Processing" then "Ready"

### Step 4: Playback Test ⏳
- [ ] View your submission
- [ ] Video should appear in player
- [ ] Video should play when clicked
- [ ] No errors visible on page

### Step 5: Browser Console Check ⏳
- [ ] Press F12 to open developer tools
- [ ] Go to Console tab
- [ ] Look for these logs:
  ```
  Cloudflare player embedded with IFRAME method
  Video UID: [32-character string]
  Token length: [500+ characters]
  ```
- [ ] No 401 errors
- [ ] No red error messages

### Step 6: Inspect Player Element ⏳
- [ ] Right-click on video player
- [ ] Select "Inspect" or "Inspect Element"
- [ ] Find the `<iframe>` tag
- [ ] Verify src attribute contains:
  ```html
  <iframe src="https://iframe.videodelivery.net/VIDEO_UID?token=eyJhbGc...">
  ```
- [ ] Token parameter should be present

### Step 7: Cloudflare Dashboard Check ⏳
- [ ] Log into Cloudflare dashboard
- [ ] Go to Stream section
- [ ] Find your uploaded video
- [ ] Check video details
- [ ] Verify: **"Signed URLs Required: Yes"**

---

## ✅ Success Criteria

All of these should be true:

- [ ] Test script shows "PRIVATE" videos
- [ ] Token generates successfully
- [ ] Video uploads without errors
- [ ] Video plays in browser
- [ ] Console shows "IFRAME method"
- [ ] Iframe src contains token parameter
- [ ] No 401 errors in console
- [ ] Cloudflare shows "Signed URLs Required: Yes"

---

## ❌ If Tests Fail

### Problem: Video doesn't play

**Check:**
1. Browser console for errors
2. Video status in database (should be 'ready')
3. Token is being generated (check console logs)
4. Cloudflare dashboard shows video is ready

**Solution:**
- Wait for video processing to complete
- Refresh the page
- Check signing keys are configured

### Problem: 401 Unauthorized errors

**Check:**
1. Signing keys configured in settings
2. Token expiry hasn't passed
3. Token claims match video UID

**Solution:**
- Verify signing key ID and private key in settings
- Check token generation in test script
- Ensure time is synchronized on server

### Problem: Black screen / No video

**Check:**
1. Browser console for CSP errors
2. Domain in Cloudflare allowedOrigins
3. Iframe not blocked by browser

**Solution:**
- Add your domain to Cloudflare allowedOrigins
- Check browser extensions aren't blocking iframe
- Try different browser

### Problem: Test script shows "PUBLIC" instead of "PRIVATE"

**Check:**
1. Line 131 in cloudflare_client.php
2. Should be: `'requireSignedURLs' => true`

**Solution:**
- Verify the file was saved correctly
- Check backups weren't accidentally restored
- Re-apply the change if needed

---

## 🔄 Rollback Procedure

If nothing works and you need to go back to PUBLIC videos:

```bash
# 1. Restore backups
copy mod/assign/submission/cloudflarestream/backups/player.js.backup mod/assign/submission/cloudflarestream/amd/src/player.js

copy mod/assign/submission/cloudflarestream/backups/cloudflare_client.php.backup mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php

# 2. Rebuild
copy mod/assign/submission/cloudflarestream/amd/src/player.js mod/assign/submission/cloudflarestream/amd/build/player.min.js

# 3. Clear cache
Site administration > Development > Purge all caches
```

Then test again with PUBLIC videos (should work as before).

---

## 📊 Test Results

### Test Date: _______________

| Test | Status | Notes |
|------|--------|-------|
| Cache cleared | ⬜ | |
| Automated test | ⬜ | |
| Video upload | ⬜ | |
| Video playback | ⬜ | |
| Console check | ⬜ | |
| Element inspect | ⬜ | |
| Cloudflare check | ⬜ | |

### Overall Result: ⬜ PASS / ⬜ FAIL

### Notes:
```
[Add any observations or issues here]
```

---

## 📞 Support Resources

- **Full Details:** `PRIVATE_VIDEO_IMPLEMENTATION.md`
- **Quick Guide:** `QUICK_TEST_GUIDE.md`
- **Analysis:** `CURRENT_STATE_ANALYSIS.md`
- **Rollback:** `backups/BACKUP_INFO.md`

---

**Ready to test! Follow the steps above and check off each item.** ✅
