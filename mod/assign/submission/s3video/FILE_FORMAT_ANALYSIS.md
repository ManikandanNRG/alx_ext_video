# S3 Video Plugin - File Format Analysis

## 🎯 Current Status: VIDEO FILES ONLY

**Answer: NO - You CANNOT give this to your client for ZIP, PPT, PDF uploads**

The S3 plugin is **strictly designed for VIDEO files only** and will **REJECT** any non-video files.

---

## 📋 What Files Are Currently Accepted

### ✅ Allowed Video Formats

**Frontend Validation (JavaScript):**
- Location: `amd/src/uploader.js` lines 28-38

```javascript
const ALLOWED_MIME_TYPES = [
    'video/mp4',           // .mp4
    'video/quicktime',     // .mov
    'video/x-msvideo',     // .avi
    'video/x-matroska',    // .mkv
    'video/webm',          // .webm
    'video/mpeg',          // .mpeg, .mpg
    'video/ogg',           // .ogv
    'video/3gpp',          // .3gp
    'video/x-flv'          // .flv
];
```

**Allowed Extensions:**
```javascript
const allowedExtensions = [
    'mp4', 'mov', 'avi', 'mkv', 'webm', 
    'mpeg', 'mpg', 'ogv', '3gp', 'flv'
];
```

### ❌ Rejected File Types

**Your client wants to upload:**
- ❌ ZIP files (application/zip)
- ❌ PPT files (application/vnd.ms-powerpoint)
- ❌ PDF files (application/pdf)

**All of these will be REJECTED with error:**
```
"Unsupported file type: [mime-type]. Please upload a video file."
```

---

## 🔍 Where Validation Happens

### 1. Frontend Validation (JavaScript)

**File:** `amd/src/uploader.js` lines 180-194

```javascript
// Check MIME type
if (!file.type) {
    return {
        valid: false,
        error: 'Unable to determine file type'
    };
}

if (!ALLOWED_MIME_TYPES.includes(file.type) && !file.type.startsWith('video/')) {
    return {
        valid: false,
        error: 'Unsupported file type: ' + file.type + '. Please upload a video file.'
    };
}
```

**This means:**
- File MUST have MIME type starting with `video/`
- OR be in the ALLOWED_MIME_TYPES list
- ZIP, PPT, PDF will be rejected immediately

### 2. Backend Validation (PHP)

**File:** `ajax/get_upload_url.php` lines 86-89

```php
// Validate MIME type (must be video).
if (strpos($mimetype, 'video/') !== 0) {
    throw new moodle_exception('invalidmimetype', 'assignsubmission_s3video', '', $mimetype);
}
```

**This means:**
- Even if someone bypasses frontend validation
- Backend will reject any file not starting with `video/`
- Double protection against non-video files

---

## 🚫 Why It Won't Work for Your Client

### Problem 1: File Type Validation
```
Client uploads: document.zip
MIME type: application/zip
Result: ❌ REJECTED - "Unsupported file type"
```

### Problem 2: Extension Validation
```
Client uploads: presentation.ppt
Extension: ppt
Result: ❌ REJECTED - "Invalid file extension"
```

### Problem 3: Backend Validation
```
Even if frontend bypassed:
MIME type check: strpos($mimetype, 'video/') !== 0
Result: ❌ REJECTED - "Invalid MIME type"
```

---

## 📊 What Would Need to Change

To support ZIP, PPT, PDF files, you would need to modify:

### 1. Frontend (uploader.js)
```javascript
// Add to ALLOWED_MIME_TYPES
const ALLOWED_MIME_TYPES = [
    // ... existing video types ...
    'application/zip',
    'application/x-zip-compressed',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/pdf'
];

// Add to allowedExtensions
const allowedExtensions = [
    // ... existing video extensions ...
    'zip', 'ppt', 'pptx', 'pdf'
];

// Change validation logic
if (!ALLOWED_MIME_TYPES.includes(file.type) && 
    !file.type.startsWith('video/') &&
    !file.type.startsWith('application/')) {
    // reject
}
```

### 2. Backend (get_upload_url.php)
```php
// Change validation from:
if (strpos($mimetype, 'video/') !== 0) {
    throw new moodle_exception('invalidmimetype', ...);
}

// To:
$allowed_types = ['video/', 'application/zip', 'application/pdf', 'application/vnd.'];
$is_allowed = false;
foreach ($allowed_types as $type) {
    if (strpos($mimetype, $type) === 0) {
        $is_allowed = true;
        break;
    }
}
if (!$is_allowed) {
    throw new moodle_exception('invalidmimetype', ...);
}
```

### 3. Player/Viewer
- Current player only works for videos
- Would need document viewer for PDF
- Would need download link for ZIP
- Would need PowerPoint viewer or download for PPT

### 4. Database Schema
- Currently stores video-specific metadata
- Would need to handle different file types

### 5. S3 Storage
- Currently optimized for video streaming
- Would need different handling for documents

---

## 💡 Recommendation

### Option 1: Use Standard Moodle File Submission
**Best for your client's use case**

Moodle has a built-in "File submissions" plugin that supports ALL file types:
- ✅ ZIP files
- ✅ PPT files
- ✅ PDF files
- ✅ Any file type
- ✅ Already working
- ✅ No development needed

**How to enable:**
1. Go to assignment settings
2. Enable "File submissions" plugin
3. Set allowed file types (or allow all)
4. Set max file size

**This is what Moodle is designed for!**

### Option 2: Create New Plugin for Documents
**If you need S3 storage for documents**

Create a separate plugin: `assignsubmission_s3documents`
- Handles ZIP, PPT, PDF, DOC, etc.
- Uses S3 for storage
- Provides download links
- Separate from video plugin

**Pros:**
- Clean separation of concerns
- Video plugin stays focused
- Document plugin can be optimized for documents

**Cons:**
- Development time required
- Two plugins to maintain

### Option 3: Modify S3 Video Plugin
**Not recommended**

Modify the existing S3 video plugin to accept all file types
- Would need extensive changes
- Player wouldn't work for non-videos
- Plugin name would be misleading
- Complex to maintain

---

## 🎯 My Strong Recommendation

**Use Moodle's built-in "File submissions" plugin**

**Why:**
1. ✅ Already supports ALL file types
2. ✅ No development needed
3. ✅ Works immediately
4. ✅ Tested and reliable
5. ✅ Your client can upload ZIP, PPT, PDF right now
6. ✅ Files stored in Moodle's file system (or can be configured for S3)

**Your client's problem:**
- Can't access OneDrive/Google Drive
- Needs to upload files

**Solution:**
- Give them Moodle assignment with "File submissions" enabled
- They can upload ANY file type
- Works immediately without any code changes

---

## 📝 Summary

**Question:** Can I give my client access to S3 video plugin for ZIP, PPT, PDF uploads?

**Answer:** ❌ **NO**

**Current Plugin:**
- ✅ Videos only (mp4, mov, avi, mkv, webm, etc.)
- ❌ Rejects ZIP files
- ❌ Rejects PPT files
- ❌ Rejects PDF files

**Validation:**
- Frontend: Checks MIME type must be `video/*`
- Backend: Checks MIME type must start with `video/`
- Both will reject non-video files

**Best Solution:**
- Use Moodle's built-in "File submissions" plugin
- Supports ALL file types
- No development needed
- Works immediately

**If you want S3 storage for documents:**
- Create separate `assignsubmission_s3documents` plugin
- Don't modify video plugin

---

## 🚀 Quick Action for Your Client

**Right Now (No Code Changes):**

1. Create an assignment
2. Enable "File submissions" plugin
3. Set "Accepted file types" to "Any file type" or specific types
4. Set max file size
5. Give client the assignment URL
6. They can upload ZIP, PPT, PDF immediately

**This solves their problem TODAY without any development!**
