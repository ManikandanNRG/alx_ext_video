# TUS Final Debug - What We Know

## Current Status

**Good news**: The chunk data IS being received by PHP (52MB)
**Bad news**: Cloudflare is rejecting it with an error

## From the Logs

```
TUS Chunk: offset=0, data_length=52428800, upload_url=https://edge-production.gateway.api.cloudflare.com...
TUS Upload Error: assignsubmission_cloudflarestream/error
```

This means:
1. ✅ JavaScript successfully sends chunk to PHP
2. ✅ PHP successfully reads chunk data (52MB)
3. ✅ PHP has the upload URL
4. ❌ Cloudflare rejects the PATCH request

## What I Added

More detailed logging to see Cloudflare's response:
- HTTP status code from Cloudflare
- cURL error (if any)
- Response body from Cloudflare

## Deploy and Test

```bash
scp mod/assign/submission/cloudflarestream/ajax/upload_tus.php \
    ubuntu@dev.aktrea.net:/tmp/

# On server
sudo mv /tmp/upload_tus.php \
    /var/www/html/mod/assign/submission/cloudflarestream/ajax/
sudo chown www-data:www-data \
    /var/www/html/mod/assign/submission/cloudflarestream/ajax/upload_tus.php
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

Then test upload and check logs:
```bash
sudo tail -f /var/log/apache2/error.log | grep -E "(TUS|Cloudflare)"
```

## Expected Log Output

You should now see:
```
TUS Chunk: offset=0, data_length=52428800, upload_url=https://...
Cloudflare TUS Response: HTTP 400 (or 403, or 401, etc.)
Cloudflare Error Body: {"error": "..."}
```

This will tell us exactly why Cloudflare is rejecting the upload.

## Possible Cloudflare Errors

### HTTP 400 - Bad Request
- Invalid TUS headers
- Invalid offset
- Invalid chunk size

### HTTP 401 - Unauthorized
- API token expired or invalid
- Missing authorization header

### HTTP 403 - Forbidden
- TUS session expired
- Upload URL no longer valid

### HTTP 404 - Not Found
- TUS session doesn't exist
- Upload URL is wrong

### HTTP 409 - Conflict
- Offset mismatch
- Chunk already uploaded

Once we see the actual error, I'll fix it immediately.
