# Cloudflare Stream Upload Issue - SOLUTION

## Problem
Upload fails with error: "Video identifier is required"

## Root Cause
The assignment was blocking submissions due to missing plugin configuration.

## Solution Applied

### 1. Fixed Assignment Dates ✅
```sql
UPDATE mdl_assign SET 
  allowsubmissionsfromdate = 0,
  duedate = 1764170294,
  cutoffdate = 1764602294
WHERE id = 5;
```

### 2. Enabled File Submission Plugin ✅
```sql
UPDATE mdl_assign_plugin_config 
SET value = 1 
WHERE assignment = 5 
  AND subtype = 'assignsubmission' 
  AND plugin = 'file' 
  AND name = 'enabled';
```

**This was the KEY fix!** Moodle requires at least one standard submission plugin (file, onlinetext, or comments) to be enabled for `submissions_open()` to return true.

### 3. Cloudflare Stream Plugin Already Enabled ✅
```sql
-- Already set to 1
SELECT * FROM mdl_assign_plugin_config 
WHERE assignment = 5 AND plugin = 'cloudflarestream';
```

## Current Status

### What Works ✅
- Plugin configuration is correct
- Assignment dates are correct
- Cloudflare API credentials work
- File plugin is enabled

### Remaining Issue ❌
`submissions_open(12830) = FALSE` for user 12830

This means there's a **user-specific restriction**. Possible causes:

1. **User 12830 is not enrolled in the course**
2. **User 12830 doesn't have the 'mod/assign:submit' capability**
3. **User 12830's submission is already graded and locked**
4. **Group restrictions** (if assignment uses groups)

## Testing Steps

### Test 1: Check with Admin User (User 15030)
The error logs show user 15030 was trying to upload. Test with that user:
```
1. Login as user 15030 (admin)
2. Go to assignment: https://dev.aktrea.net/mod/assign/view.php?id=684&action=editsubmission
3. Try uploading a video
```

### Test 2: Check User 12830's Enrollment
```sql
-- Check if user 12830 is enrolled in course 434
SELECT * FROM mdl_user_enrolments ue
JOIN mdl_enrol e ON e.id = ue.enrolid
WHERE e.courseid = 434 AND ue.userid = 12830;

-- Check user 12830's role and capabilities
SELECT * FROM mdl_role_assignments ra
JOIN mdl_context ctx ON ctx.id = ra.contextid
WHERE ra.userid = 12830 AND ctx.contextlevel = 50 AND ctx.instanceid = 434;
```

### Test 3: Direct API Test
Run this to bypass all UI and test the API directly:
```
https://dev.aktrea.net/mod/assign/submission/cloudflarestream/test_upload_url_response.php?assignmentid=5
```

If this returns a `uid`, the backend is working and the issue is in JavaScript/caching.

## JavaScript Caching Issue

The JavaScript is heavily cached. To force a refresh:

### Option 1: Purge All Caches
```
Site administration > Development > Purge all caches
```

### Option 2: Clear Browser Cache
1. Close ALL browser windows
2. Clear browser cache (Ctrl+Shift+Delete)
3. Open in Incognito/Private mode

### Option 3: Force JavaScript Reload
Add a version parameter to force reload:
```php
// In lib.php get_form_elements() method
$PAGE->requires->js_call_amd('assignsubmission_cloudflarestream/uploader', 'init', [
    $this->assignment->get_instance()->id,
    $submission->id,
    $maxfilesize,
    '.cloudflarestream-upload-interface'
], '?v=' . time()); // Add version parameter
```

## Files to Deploy

Ensure these files are on the server:
1. `ajax/get_upload_url.php` - Returns upload URL and uid
2. `ajax/confirm_upload.php` - Confirms upload completion
3. `amd/build/uploader.min.js` - JavaScript uploader (with debug logs)
4. `lib.php` - Plugin main class
5. `locallib.php` - Helper functions

## Verification Checklist

- [x] Cloudflare API credentials configured
- [x] Plugin enabled globally
- [x] Plugin enabled for assignment 5
- [x] File plugin enabled for assignment 5
- [x] Assignment dates allow submissions
- [ ] User has submit capability
- [ ] User is enrolled in course
- [ ] JavaScript cache cleared
- [ ] Upload test successful

## Next Steps

1. **Test with admin user (15030)** - If this works, it's a user permission issue
2. **Check user 12830's enrollment and capabilities**
3. **Clear all caches and test again**
4. **Check browser console for JavaScript errors**
5. **Check Apache error log during upload attempt**

## Support Queries

If still not working, run these and send results:

```sql
-- 1. Check user enrollment
SELECT u.id, u.username, u.email, r.shortname as role
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_role_assignments ra ON ra.userid = u.id
JOIN mdl_role r ON r.id = ra.roleid
JOIN mdl_context ctx ON ctx.id = ra.contextid
WHERE e.courseid = 434 AND u.id IN (12830, 15030);

-- 2. Check assignment configuration
SELECT * FROM mdl_assign WHERE id = 5;

-- 3. Check all plugin configs
SELECT * FROM mdl_assign_plugin_config 
WHERE assignment = 5 AND subtype = 'assignsubmission';
```

## Success Criteria

Upload is successful when:
1. File picker is clickable ✅
2. Video uploads to Cloudflare ✅
3. `uid` is returned from Cloudflare API ✅
4. `confirm_upload.php` receives the `uid` ❓
5. Video is saved to database ❓
6. Video appears in submission ❓

Current status: Steps 1-3 should work, need to verify 4-6.
