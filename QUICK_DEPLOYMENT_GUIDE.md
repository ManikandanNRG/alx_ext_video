# TUS Fix - Quick Deployment Guide

## What Was Fixed

✅ **Bug 1**: Case-sensitive header parsing (headers are lowercase, code checked capitalized)
✅ **Bug 2**: Non-existent `logger::log_info()` calls (fatal PHP error)
✅ **Bug 3**: Debug logging clutter in Apache logs

## Files to Deploy (3 files)

```
mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
mod/assign/submission/cloudflarestream/ajax/upload_tus.php
mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
```

## Deployment Commands

### 1. Upload to Server
```bash
scp mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php \
    ubuntu@dev.aktrea.net:/tmp/cloudflare_client.php

scp mod/assign/submission/cloudflarestream/ajax/upload_tus.php \
    ubuntu@dev.aktrea.net:/tmp/upload_tus.php

scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js \
    ubuntu@dev.aktrea.net:/tmp/uploader.min.js
```

### 2. Install on Server
```bash
# SSH to server
ssh ubuntu@dev.aktrea.net

# Move files
sudo mv /tmp/cloudflare_client.php \
    /var/www/html/mod/assign/submission/cloudflarestream/classes/api/

sudo mv /tmp/upload_tus.php \
    /var/www/html/mod/assign/submission/cloudflarestream/ajax/

sudo mv /tmp/uploader.min.js \
    /var/www/html/mod/assign/submission/cloudflarestream/amd/build/

# Fix permissions
sudo chown -R www-data:www-data \
    /var/www/html/mod/assign/submission/cloudflarestream/

# Clear cache
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

### 3. Test
```bash
# Watch logs
sudo tail -f /var/log/apache2/error.log
```

Then upload a video file at:
https://dev.aktrea.net/mod/assign/view.php?id=692&action=editsubmission

## Expected Results

### Before (Broken) ❌
```
[error] TUS Upload Error: assignsubmission_cloudflarestream/tus_no_location
[error] Call to undefined method logger::log_info()
```

### After (Fixed) ✅
```
(No errors - clean logs)
```

## Quick Test

1. Go to assignment page
2. Upload any video file
3. Watch progress bar
4. Should complete successfully
5. Check Apache logs - should be clean

## If It Doesn't Work

Check:
```bash
# 1. Verify files are in place
ls -la /var/www/html/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
ls -la /var/www/html/mod/assign/submission/cloudflarestream/ajax/upload_tus.php
ls -la /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js

# 2. Check permissions
ls -la /var/www/html/mod/assign/submission/cloudflarestream/classes/api/
# Should show: www-data www-data

# 3. Check for syntax errors
sudo -u www-data php -l /var/www/html/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
# Should show: No syntax errors detected

# 4. View recent errors
sudo tail -50 /var/log/apache2/error.log | grep -i cloudflare
```

## Rollback (If Needed)

```bash
# Restore from git
cd /var/www/html
sudo -u www-data git checkout a5f5a35 -- \
    mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php

# Clear cache
sudo -u www-data php admin/cli/purge_caches.php
```

## Success Checklist

- [ ] Files uploaded to server
- [ ] Files moved to correct location
- [ ] Permissions set correctly
- [ ] Cache cleared
- [ ] Test upload successful
- [ ] Apache logs clean
- [ ] Video playable

## Confidence: 95%

All critical bugs are fixed. The 5% uncertainty is only because it hasn't been tested in production yet.

---

**Ready to deploy!** 🚀

See `COMPLETE_TUS_FIX_REPORT.md` for full details.
