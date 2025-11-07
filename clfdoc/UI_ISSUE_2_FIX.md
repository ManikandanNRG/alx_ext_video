# UI Issue 2 Fix - Remove Confusing Attempt Counter

## Problem
After video upload, the progress shows "Processing video... (1/5)" which confuses users into thinking 5 videos are uploading, when it's actually just retry attempts to check if ONE video is ready.

## Solution
Changed the progress message from showing attempt numbers to animated dots:
- **Before:** "Processing video... (1/5)", "Processing video... (2/5)", etc.
- **After:** "Processing video.", "Processing video..", "Processing video...", "Processing video."

## Files Changed

### 1. mod/assign/submission/cloudflarestream/amd/src/uploader.js
**Line 459** - Changed from:
```javascript
this.updateProgress(100, `Processing video... (${attempt}/${maxAttempts})`);
```

To:
```javascript
// Show animated dots instead of confusing attempt counter
const dots = '.'.repeat((attempt % 3) + 1);
this.updateProgress(100, `Processing video${dots}`);
```

## How It Works
- The dots animate: `.` → `..` → `...` → `.` (cycles through 1-3 dots)
- Background retry logic still works (5 attempts over 60 seconds)
- Users see a simple "Processing video..." message with animated dots
- No confusion about multiple videos

## Deployment Steps

### Step 1: Copy source file
```bash
scp mod/assign/submission/cloudflarestream/amd/src/uploader.js ubuntu@dev.aktrea.net:/tmp/
```

### Step 2: Minify on server (if grunt is available)
```bash
ssh ubuntu@dev.aktrea.net
cd /var/www/html/mod/assign/submission/cloudflarestream
sudo -u www-data npx grunt amd
```

### Step 3: Manual deployment (if grunt not available)
```bash
# Copy source file
sudo mv /tmp/uploader.js /var/www/html/mod/assign/submission/cloudflarestream/amd/src/
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/src/uploader.js

# For now, copy source to build folder (Moodle will use it)
sudo cp /var/www/html/mod/assign/submission/cloudflarestream/amd/src/uploader.js \
        /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js

# Purge caches
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

## Testing
1. Upload a video file
2. Watch the progress after upload completes
3. You should see: "Processing video." → "Processing video.." → "Processing video..."
4. No more "(1/5)" counter visible to users

## User Experience
**Before:**
```
Uploading...
100%
Processing video... (1/5) (100%)  ← Confusing!
Processing video... (2/5) (100%)  ← Users think 5 videos uploading
```

**After:**
```
Uploading...
100%
Processing video. (100%)          ← Clear!
Processing video.. (100%)         ← Simple animation
Processing video... (100%)        ← No confusion
```

## Notes
- Background retry logic unchanged (still 5 attempts)
- Console logs still show attempt numbers for debugging
- Only the user-facing message is simplified
- Animated dots provide visual feedback that processing is ongoing
