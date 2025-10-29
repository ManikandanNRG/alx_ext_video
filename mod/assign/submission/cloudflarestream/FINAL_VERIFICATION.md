# Final Verification Steps

## ✅ Files Are Correct Locally

I've verified that both files are identical locally:
- `amd/src/player.js` - Hash: E5A10037D3A7415FDB8F8C716F0050217033B74F84D4B4A36A8E0621EB2C944E
- `amd/build/player.min.js` - Hash: E5A10037D3A7415FDB8F8C716F0050217033B74F84D4B4A36A8E0621EB2C944E

Both contain the IFRAME implementation.

---

## 🔍 Verify On Your Live Server

### Step 1: Run Verification Script

**URL:** `https://dev.aktrea.net/mod/assign/submission/cloudflarestream/verify_files.php`

This will check:
- ✅ If cloudflare_client.php has `requireSignedURLs => true`
- ✅ If player.js has IFRAME code
- ✅ If player.min.js has IFRAME code (CRITICAL!)
- ✅ If files match each other

### Step 2: Check Results

**If all green (✅):**
- Files are correct on server
- Issue is cache-related
- Clear Moodle cache + browser cache
- Test again

**If any red (❌):**
- Files not uploaded correctly
- Follow the actions shown in the script
- Re-upload files or run copy command

---

## 🔧 If player.min.js Is Wrong

### Option A: Re-upload File

From your local machine:
```bash
scp mod/assign/submission/cloudflarestream/amd/build/player.min.js user@server:/path/to/moodle/mod/assign/submission/cloudflarestream/amd/build/
```

### Option B: Copy On Server

SSH to server and run:
```bash
cd /path/to/moodle/mod/assign/submission/cloudflarestream
cp amd/src/player.js amd/build/player.min.js
```

---

## 🎯 After Files Are Verified

### 1. Clear Moodle Cache
```
Site administration > Development > Purge all caches
```

### 2. Clear Browser Cache
- Press `Ctrl+F5` (Windows)
- Or `Cmd+Shift+R` (Mac)

### 3. Test With NEW Video
1. Upload a NEW video (don't use old one)
2. Open console (F12)
3. Look for:
   ```
   Cloudflare player embedded with IFRAME method
   Video UID: [your video ID]
   Token length: [number]
   ```

### 4. Check Video Element
- Right-click on video
- Inspect element
- Should see: `<iframe src="https://iframe.videodelivery.net/...?token=...">`

---

## 📊 Expected Console Output

```javascript
// When page loads:
Cloudflare player embedded with IFRAME method
Video UID: 23b7a0e3b30068adbaa0692cc1b10724
Token length: 523

// No errors:
(no 401 errors)
(no "Failed to get playback token" errors)
```

---

## ❌ If Still No Console Output

### Possible Causes:

1. **Browser cache not cleared**
   - Solution: Hard refresh (Ctrl+F5)
   - Or: Open in incognito/private window

2. **Moodle cache not cleared**
   - Solution: Purge all caches again
   - Check: Site administration > Development > Caching

3. **Old video being used**
   - Solution: Upload a BRAND NEW video
   - Old videos might have different database records

4. **JavaScript error preventing execution**
   - Solution: Check console for ANY red errors
   - Fix those errors first

5. **Player not being initialized**
   - Solution: Check if player container exists
   - Check if JavaScript is loading

---

## 🔍 Debug Steps

### 1. Check If JavaScript Loads

In console, type:
```javascript
require(['assignsubmission_cloudflarestream/player'], function(player) {
    console.log('Player module loaded:', player);
});
```

Should show: `Player module loaded: Object`

### 2. Check If Container Exists

In console, type:
```javascript
$('#cloudflarestream-player-container').length
```

Should show: `1` (or higher)

### 3. Check Network Tab

1. Open Network tab (F12)
2. Filter: XHR
3. Look for: `get_playback_token.php`
4. Check response:
   - Should be: `{"success":true,"token":"...","expiry_seconds":86400}`
   - If error: Check what the error says

---

## 📞 What To Report Back

Please run the verification script and tell me:

1. **cloudflare_client.php:** ✅ or ❌
2. **player.js:** ✅ or ❌
3. **player.min.js:** ✅ or ❌
4. **Files match:** ✅ or ❌

If all ✅:
- Clear caches
- Test with new video
- Report console output

If any ❌:
- Follow the fix shown in verification script
- Run verification again
- Then test

---

## 🎯 Quick Checklist

- [ ] Run verify_files.php
- [ ] All files show ✅
- [ ] Clear Moodle cache
- [ ] Clear browser cache (Ctrl+F5)
- [ ] Upload NEW video
- [ ] Open console (F12)
- [ ] See "IFRAME method" message
- [ ] Video plays

---

**Start here:** `https://dev.aktrea.net/mod/assign/submission/cloudflarestream/verify_files.php`

Tell me what it shows! 🚀
