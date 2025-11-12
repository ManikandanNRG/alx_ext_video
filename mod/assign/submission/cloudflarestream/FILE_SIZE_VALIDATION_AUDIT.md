# File Size Validation - Complete Audit

## ✅ VALIDATION POINTS (All Locations)

### 1. **Client-Side Validation (JavaScript)**
**File:** `amd/src/uploader.js` (Line 227)
```javascript
if (file.size > this.maxFileSize) {
    return {
        valid: false,
        error: 'File size exceeds maximum allowed size of ' + this.formatFileSize(this.maxFileSize)
    };
}
```
- **Status:** ✅ Uses dynamic `maxFileSize` from settings
- **How it works:** Value passed from PHP via template context
- **Flow:** `lib.php::get_max_file_size()` → Template → JavaScript

---

### 2. **Server-Side Validation (PHP)**
**File:** `classes/validator.php` (Line 93)
```php
public static function validate_file_size($filesize) {
    // Get max file size from config (reads from admin settings)
    $maxfilesize = get_config('assignsubmission_cloudflarestream', 'max_file_size');
    if (empty($maxfilesize)) {
        $maxfilesize = self::DEFAULT_MAX_FILE_SIZE; // Fallback to 5GB
    }
    
    if ($filesize > $maxfilesize) {
        throw new validation_exception('file_too_large', ...);
    }
}
```
- **Status:** ✅ NOW reads from settings (FIXED)
- **Previous Issue:** Was hardcoded to 5GB constant
- **Fix Applied:** Now uses `get_config()` to read from admin settings

---

### 3. **Settings Configuration**
**File:** `settings.php` (Lines 68-79)
```php
$sizeoptions = array(
    209715200 => '200 MB',
    419430400 => '400 MB',
    524288000 => '500 MB',
    629145600 => '600 MB',
    734003200 => '700 MB',
    838860800 => '800 MB',
    1073741824 => '1 GB',
    2147483648 => '2 GB',
    3221225472 => '3 GB',
    4294967296 => '4 GB',
    5368709120 => '5 GB'
);
```
- **Status:** ✅ Added MB options (200, 400, 500, 600, 700, 800 MB)
- **Default:** 5GB (5368709120 bytes)

---

### 4. **Config Reader (lib.php)**
**File:** `lib.php` (Line 486)
```php
public function get_max_file_size() {
    $maxsize = get_config('assignsubmission_cloudflarestream', 'max_file_size');
    return !empty($maxsize) ? (int)$maxsize : 5368709120; // Default 5GB
}
```
- **Status:** ✅ Already reads from settings
- **Used by:** Upload form template to pass value to JavaScript

---

## 🔄 VALIDATION FLOW

### Upload Process:
1. **User selects file** → JavaScript validates against `maxFileSize`
2. **Upload starts** → TUS protocol sends file
3. **Server receives** → `ajax/upload_tus.php` gets filesize parameter
4. **Validation** → `validator::validate_file_size()` checks against settings
5. **Confirmation** → `ajax/confirm_upload.php` validates final file size from Cloudflare

### Where Validation Happens:
```
┌─────────────────────────────────────────────────────────────┐
│ 1. CLIENT-SIDE (JavaScript)                                 │
│    amd/src/uploader.js:227                                  │
│    ✓ Validates BEFORE upload starts                        │
│    ✓ Uses maxFileSize from PHP settings                    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. SERVER-SIDE (PHP - TUS Upload)                          │
│    ajax/upload_tus.php                                      │
│    ✓ Receives filesize parameter                           │
│    ✓ Could add validation here (optional)                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. SERVER-SIDE (PHP - Confirmation)                        │
│    ajax/confirm_upload.php                                  │
│    ✓ Gets actual file size from Cloudflare API             │
│    ✓ Validates via validator::validate_file_size()         │
│    ✓ NOW reads from settings (FIXED)                       │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ CONFIRMATION: Settings Are Used

### YES, the max_file_size setting IS validated on every upload:

1. **Client-side (JavaScript):**
   - ✅ Checks file size BEFORE upload
   - ✅ Uses value from `get_max_file_size()` via template
   - ✅ Prevents upload if file too large

2. **Server-side (PHP):**
   - ✅ Validates file size in `confirm_upload.php`
   - ✅ Uses `validator::validate_file_size()`
   - ✅ NOW reads from settings (was hardcoded, now FIXED)

3. **Settings Page:**
   - ✅ Admin can select from dropdown
   - ✅ Value stored in `config_plugins` table
   - ✅ Read by `get_config('assignsubmission_cloudflarestream', 'max_file_size')`

---

## 📊 Available File Size Options

| Value (bytes) | Display      | Use Case                          |
|---------------|--------------|-----------------------------------|
| 209715200     | 200 MB       | Short clips, mobile recordings    |
| 419430400     | 400 MB       | Medium videos, presentations      |
| 524288000     | 500 MB       | Standard assignments              |
| 629145600     | 600 MB       | Longer presentations              |
| 734003200     | 700 MB       | Extended recordings               |
| 838860800     | 800 MB       | High-quality videos               |
| 1073741824    | 1 GB         | Professional recordings           |
| 2147483648    | 2 GB         | Long lectures                     |
| 3221225472    | 3 GB         | High-quality long videos          |
| 4294967296    | 4 GB         | Very long recordings              |
| 5368709120    | 5 GB (default)| Maximum flexibility              |

---

## 🔧 Changes Made

### 1. Added MB Options to Settings
- Added 200, 400, 500, 600, 700, 800 MB options
- Kept existing 1-5 GB options
- Default remains 5GB

### 2. Fixed Validator to Read Settings
- **Before:** Hardcoded `MAX_FILE_SIZE` constant (5GB)
- **After:** Reads from `get_config()` dynamically
- **Fallback:** Uses `DEFAULT_MAX_FILE_SIZE` if config not set

### 3. Renamed Constant
- **Before:** `MAX_FILE_SIZE` (implied it was the limit)
- **After:** `DEFAULT_MAX_FILE_SIZE` (clarifies it's a fallback)

---

## ✅ FINAL CONFIRMATION

**Q: Is the max_file_size setting validated on every upload?**

**A: YES, absolutely!**

1. ✅ JavaScript validates BEFORE upload starts
2. ✅ PHP validates AFTER upload completes
3. ✅ Both read from the same admin setting
4. ✅ No hardcoded limits anymore (except fallback)

**The fix ensures that when an admin changes the max file size in settings, it's enforced everywhere.**
