# Status Display Bug Fix

## 🐛 Problem

When a user first loads the submission page, they see:
```
Cloudflare Stream: [[status_{{videostatus}}]]
```

Instead of the actual status like:
```
Cloudflare Stream: Pending
```

## 🔍 Root Cause

**Mustache Template Limitation:**
The template was trying to do nested variable substitution:
```mustache
{{#str}}status_{{videostatus}}, assignsubmission_cloudflarestream{{/str}}
```

Mustache doesn't support this! It treats `status_{{videostatus}}` as a literal string key, not as a dynamic key.

## ✅ Solution

**Pass the translated status text from PHP instead of trying to translate in the template.**

### Changes Made:

**1. lib.php (Line 413-416)**
```php
// Get translated status text
$statuskey = 'status_' . $video->upload_status;
$context['videostatustext'] = get_string($statuskey, 'assignsubmission_cloudflarestream');
```

**2. upload_form.mustache (Line 62-65)**
```mustache
<div class="cloudflarestream-current-status alert alert-info">
    <strong>{{#str}}cloudflarestream, assignsubmission_cloudflarestream{{/str}}:</strong>
    {{videostatustext}}
</div>
```

## 📤 Files to Upload

Upload these 2 files to your server:
1. `mod/assign/submission/cloudflarestream/lib.php`
2. `mod/assign/submission/cloudflarestream/templates/upload_form.mustache`

## 🧪 Testing

After uploading:
1. Clear Moodle cache: `php admin/cli/purge_caches.php`
2. Login as student
3. Go to assignment submission page
4. Should see: **"Cloudflare Stream: Pending"** (or "Ready" if video exists)
5. NOT: "Cloudflare Stream: [[status_{{videostatus}}]]"

## ✅ Expected Results

**Before Fix:**
```
Cloudflare Stream: [[status_{{videostatus}}]]
```

**After Fix:**
```
Cloudflare Stream: Pending
Cloudflare Stream: Ready
Cloudflare Stream: Uploading
```

## 📝 Technical Notes

This bug only appeared when:
- A video record exists in the database
- The page is loaded for the first time
- Mustache tries to translate the status string

The fix ensures the translation happens in PHP (where it should) before passing to the template.

---

**Status:** ✅ Fixed
**Files Changed:** 2
**Impact:** Visual bug fix - no functionality change
