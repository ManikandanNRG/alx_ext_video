# TUS Implementation - Deployment Checklist

## Pre-Deployment Verification

### ✅ Code Review
- [x] Fixed case-insensitive header parsing
- [x] Removed non-existent `logger::log_info()` calls
- [x] Removed debug logging
- [x] No PHP syntax errors
- [x] JavaScript rebuilt

### ✅ Files to Deploy
```
mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
mod/assign/submission/cloudflarestream/ajax/upload_tus.php
mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
```

## Deployment Steps

### Step 1: Backup Current Files
```bash
# On server
cd /var/www/html/mod/assign/submission/cloudflarestream
sudo cp classes/api/cloudflare_client.php classes/api/cloudflare_client.php.backup
sudo cp ajax/upload_tus.php ajax/upload_tus.php.backup
sudo cp amd/build/uploader.min.js amd/build/uploader.min.js.backup
```

### Step 2: Upload Fixed Files
```bash
# From local machine
scp mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php \
    ubuntu@dev.aktrea.net:/tmp/

scp mod/assign/submission/cloudflarestream/ajax/upload_tus.php \
    ubuntu@dev.aktrea.net:/tmp/

scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js \
    ubuntu@dev.aktrea.net:/tmp/
```

### Step 3: Move Files to Correct Location
```bash
# On server
sudo mv /tmp/cloudflare_client.php /var/www/html/mod/assign/submission/cloudflarestream/classes/api/
sudo mv /tmp/upload_tus.php /var/www/html/mod/assign/submission/cloudflarestream/ajax/
sudo mv /tmp/uploader.min.js /var/www/html/mod/assign/submission/cloudflarestream/amd/build/

# Set correct permissions
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/ajax/upload_tus.php
sudo chown www-data:www-data /var/www/html/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js
```

### Step 4: Clear Moodle Cache
```bash
# On server
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

### Step 5: Restart Apache (Optional)
```bash
sudo systemctl restart apache2
```

## Post-Deployment Testing

### Test 1: Small File Upload (<200MB)
1. Go to: https://dev.aktrea.net/mod/assign/view.php?id=692&action=editsubmission
2. Select a small video file (e.g., 50MB)
3. Click upload
4. **Expected**: Uses direct upload, completes successfully

### Test 2: Large File Upload (>200MB)
1. Go to same assignment page
2. Select a large video file (e.g., 500MB or 1.7GB)
3. Click upload
4. **Expected**: 
   - Uses TUS upload
   - Progress bar shows percentage
   - No errors in console
   - Upload completes successfully

### Test 3: Check Apache Logs
```bash
sudo tail -f /var/log/apache2/error.log
```

**Expected**: No TUS-related errors

**Before (broken)**:
```
PHP message: TUS Upload Error: assignsubmission_cloudflarestream/tus_no_location
PHP message: Call to undefined method assignsubmission_cloudflarestream\logger::log_info()
```

**After (fixed)**:
```
(No errors - clean logs)
```

### Test 4: Verify Database Record
```bash
# On server
sudo -u www-data php /var/www/html/admin/cli/mysql.php
```

```sql
SELECT * FROM mdl_assignsubmission_cfstream 
WHERE upload_status = 'pending' OR upload_status = 'completed'
ORDER BY upload_timestamp DESC 
LIMIT 5;
```

**Expected**: See records with correct `video_uid` values

### Test 5: Verify Video in Cloudflare
1. Go to: https://dash.cloudflare.com/
2. Navigate to Stream
3. Find the uploaded video by UID
4. **Expected**: Video exists and is processing/ready

## Troubleshooting

### Issue: Still Getting "tus_no_location" Error

**Check**:
```bash
# View the actual headers being received
sudo tail -100 /var/log/apache2/error.log | grep -A 10 "TUS Response Headers"
```

**Solution**: Verify the case-insensitive header parsing is in place

### Issue: "Call to undefined method logger::log_info()"

**Check**:
```bash
# Search for logger calls in the file
grep -n "logger::" /var/www/html/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php
```

**Expected**: No results (all logger calls removed)

### Issue: Upload Still Fails

**Check**:
1. Browser console for JavaScript errors
2. Network tab for failed requests
3. Apache error log for PHP errors

**Debug**:
```bash
# Enable detailed error logging
sudo tail -f /var/log/apache2/error.log | grep -i cloudflare
```

## Rollback Procedure

If deployment fails:

```bash
# On server
cd /var/www/html/mod/assign/submission/cloudflarestream

# Restore backups
sudo cp classes/api/cloudflare_client.php.backup classes/api/cloudflare_client.php
sudo cp ajax/upload_tus.php.backup ajax/upload_tus.php
sudo cp amd/build/uploader.min.js.backup amd/build/uploader.min.js

# Clear cache
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php

# Restart Apache
sudo systemctl restart apache2
```

## Success Criteria

- ✅ No PHP errors in Apache logs
- ✅ TUS session created successfully
- ✅ Video UID extracted correctly
- ✅ Upload progress shows correctly
- ✅ Upload completes without errors
- ✅ Video playable after upload
- ✅ Database record created with correct UID

## Sign-Off

- [ ] Files deployed
- [ ] Cache cleared
- [ ] Small file test passed
- [ ] Large file test passed
- [ ] Apache logs clean
- [ ] Database records correct
- [ ] Video playable in Cloudflare

**Deployed by**: _________________
**Date**: _________________
**Time**: _________________

## Notes

_Add any deployment notes or issues encountered here_

---

**Ready to deploy!** 🚀
