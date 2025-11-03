# ALX Universal Storage - Plugin Structure

## Plugin Information

**Name:** ALX Universal Storage  
**Type:** Repository Plugin  
**Technical Name:** repository_alxuniversalstorage  
**Location:** `repository/alxuniversalstorage/`

## Why Repository Plugin?

Repository plugins are a specific Moodle plugin type that:
- Integrate with Moodle's file picker (the dialog that appears when you click "Add file")
- Work across ALL Moodle activities (assignments, resources, forums, quizzes, pages, etc.)
- Appear as a file source option alongside "Upload a file", "Server files", etc.
- MUST be located in the `repository/` folder at Moodle root level

## Folder Structure Comparison

### Your Current Plugins (Assignment Submission Type)
```
mod/assign/submission/
├── s3video/              ← Assignment submission plugin
│   ├── lib.php           ← Only works in assignments
│   ├── version.php       ← Video-specific features
│   └── ...
├── cloudflarestream/     ← Assignment submission plugin
│   ├── lib.php           ← Only works in assignments
│   ├── version.php       ← Video-specific features
│   └── ...
```

**Characteristics:**
- Plugin Type: Assignment submission plugin
- Scope: Only assignments
- File Types: Videos only
- Integration: Assignment submission form

### New Plugin (Repository Type)
```
repository/
└── alxuniversalstorage/  ← Repository plugin
    ├── lib.php           ← Works everywhere in Moodle
    ├── version.php       ← All file types
    ├── settings.php      ← Admin configuration
    ├── classes/
    │   ├── providers/
    │   │   ├── s3_provider.php
    │   │   ├── r2_provider.php
    │   │   ├── gcs_provider.php
    │   │   └── azure_provider.php
    │   ├── quota_manager.php
    │   ├── security_manager.php
    │   ├── lifecycle_manager.php
    │   └── ...
    ├── db/
    │   ├── install.xml
    │   ├── access.php
    │   └── tasks.php
    ├── lang/en/
    │   └── repository_alxuniversalstorage.php
    ├── amd/src/
    │   ├── uploader.js
    │   └── browser.js
    └── ...
```

**Characteristics:**
- Plugin Type: Repository plugin
- Scope: Everywhere in Moodle
- File Types: All types (PDF, images, videos, documents, etc.)
- Integration: File picker (appears in all file upload dialogs)

## Complete Moodle Structure

```
/var/www/html/moodle/              ← Moodle root
├── repository/                     ← Repository plugins folder
│   ├── alxuniversalstorage/       ← NEW: Your universal storage plugin
│   ├── dropbox/                   ← Example: Dropbox repository
│   ├── googledocs/                ← Example: Google Docs repository
│   └── ...
├── mod/
│   ├── assign/
│   │   └── submission/
│   │       ├── s3video/           ← EXISTING: Your S3 video plugin
│   │       ├── cloudflarestream/  ← EXISTING: Your Cloudflare plugin
│   │       └── ...
│   └── ...
└── ...
```

## Why Keep Both?

**Keep your existing plugins** (`s3video` and `cloudflarestream`) because:
1. They have specialized video features (player, transcoding, etc.)
2. They work specifically for assignment submissions
3. They may have custom grading interfaces
4. No need to migrate existing data

**Add the new plugin** (`alxuniversalstorage`) because:
1. Works everywhere, not just assignments
2. Supports all file types, not just videos
3. Provides universal file management
4. Reduces storage costs for all content

## User Experience

### With Assignment Submission Plugins (Current)
```
Teacher creates assignment → Student submits → 
Only option: Upload video to S3/Cloudflare
```

### With Repository Plugin (New)
```
Teacher creates assignment → Clicks "Add file" → File picker opens →
Options:
- Upload a file
- Server files
- ALX Universal Storage ← NEW! Works for any file type
- Recent files

Teacher creates resource → Clicks "Add file" → Same file picker
Teacher posts in forum → Clicks "Add file" → Same file picker
Teacher creates quiz → Clicks "Add file" → Same file picker
```

## Installation Location

When you implement this plugin, create it at:
```
repository/alxuniversalstorage/
```

NOT at:
```
mod/assign/submission/alxuniversalstorage/  ← WRONG! This is for assignment plugins only
```

## Summary

- **Different plugin type** = Different folder location
- **Repository plugins** = `repository/` folder (works everywhere)
- **Assignment submission plugins** = `mod/assign/submission/` folder (works only in assignments)
- You'll have **both types** working together in your Moodle installation
