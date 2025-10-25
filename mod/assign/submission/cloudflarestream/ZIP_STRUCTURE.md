# Moodle Plugin ZIP Structure Guide

## ✅ CORRECT Structure

When you create a ZIP file for Moodle plugin installation, the plugin folder must be at the **root** of the ZIP:

```
cloudflarestream.zip
│
└── cloudflarestream/                    ← Plugin folder at ZIP root
    ├── version.php                      ← Plugin metadata
    ├── lib.php                          ← Core plugin class
    ├── settings.php                     ← Admin settings
    ├── styles.css
    ├── README.md
    ├── INSTALLATION.md
    ├── DEPLOYMENT_CHECKLIST.md
    │
    ├── lang/
    │   └── en/
    │       └── assignsubmission_cloudflarestream.php
    │
    ├── db/
    │   ├── install.xml
    │   ├── upgrade.php
    │   ├── tasks.php
    │   ├── access.php
    │   └── caches.php
    │
    ├── classes/
    │   ├── api/
    │   │   └── cloudflare_client.php
    │   ├── privacy/
    │   │   └── provider.php
    │   ├── task/
    │   │   └── cleanup_videos.php
    │   ├── logger.php
    │   ├── validator.php
    │   ├── rate_limiter.php
    │   └── retry_handler.php
    │
    ├── amd/
    │   └── src/
    │       ├── uploader.js
    │       └── player.js
    │
    ├── templates/
    │   ├── upload_form.mustache
    │   └── player.mustache
    │
    ├── ajax/
    │   ├── get_upload_url.php
    │   ├── confirm_upload.php
    │   └── get_playback_token.php
    │
    ├── tests/
    │   ├── cloudflare_client_test.php
    │   ├── privacy_provider_test.php
    │   └── integration_test.php
    │
    ├── dashboard.php
    └── videomanagement.php
```

---

## ❌ WRONG Structure (Will Be Rejected)

**DO NOT** include the full path structure in the ZIP:

```
cloudflarestream.zip
│
└── mod/                                 ← ❌ WRONG: Path structure included
    └── assign/
        └── submission/
            └── cloudflarestream/
                ├── version.php
                └── ...
```

**Why this fails:**
- Moodle expects the plugin folder at the ZIP root
- Moodle uses `version.php` to detect plugin type and destination
- The `$plugin->component = 'assignsubmission_cloudflarestream'` tells Moodle where to install it

---

## How Moodle Processes the ZIP

### Step 1: Upload
You upload `cloudflarestream.zip` via Moodle UI

### Step 2: Detection
Moodle extracts and reads `cloudflarestream/version.php`:
```php
$plugin->component = 'assignsubmission_cloudflarestream';
```

### Step 3: Type Recognition
Moodle parses the component name:
- `assignsubmission_` = Assignment submission plugin type
- `cloudflarestream` = Plugin name

### Step 4: Automatic Placement
Moodle automatically places files in:
```
[moodle-root]/mod/assign/submission/cloudflarestream/
```

---

## Creating the Correct ZIP

### Method 1: Use Provided Scripts (Recommended)

**Windows:**
```cmd
create_release_zip.bat
```

**Linux/Mac:**
```bash
./create_release_zip.sh
```

These scripts automatically create the correct structure.

### Method 2: Manual Creation

**From the parent directory:**

```bash
# Navigate to the parent directory containing cloudflarestream
cd mod/assign/submission

# Create ZIP with cloudflarestream at root
zip -r cloudflarestream.zip cloudflarestream/

# Verify structure
unzip -l cloudflarestream.zip | head -20
```

**Expected output:**
```
Archive:  cloudflarestream.zip
  Length      Date    Time    Name
---------  ---------- -----   ----
        0  2025-10-23 10:00   cloudflarestream/
     1234  2025-10-23 10:00   cloudflarestream/version.php
     5678  2025-10-23 10:00   cloudflarestream/lib.php
     ...
```

**Windows PowerShell:**
```powershell
# Navigate to parent directory
cd mod\assign\submission

# Create ZIP
Compress-Archive -Path cloudflarestream -DestinationPath cloudflarestream.zip

# Verify structure
Expand-Archive -Path cloudflarestream.zip -DestinationPath temp_verify
dir temp_verify
```

---

## Verification Checklist

Before uploading to Moodle, verify your ZIP:

- [ ] ZIP file is named `cloudflarestream.zip` (or similar)
- [ ] When you open the ZIP, you see `cloudflarestream/` folder immediately
- [ ] `cloudflarestream/version.php` exists at the root level
- [ ] No `mod/assign/submission/` path structure in the ZIP
- [ ] File size is reasonable (should be < 5 MB)

---

## Testing Your ZIP

### Quick Test (Before Uploading to Production)

1. **Extract locally:**
   ```bash
   unzip cloudflarestream.zip -d test_extract
   ls test_extract
   ```

2. **Expected result:**
   ```
   cloudflarestream/
   ```

3. **NOT this:**
   ```
   mod/
   ```

### Test in Moodle

1. **Use a test/staging Moodle instance first**
2. Upload via: Site Administration → Plugins → Install plugins
3. Moodle should show:
   - Plugin name: `Cloudflare Stream`
   - Plugin type: `Assignment submission (assignsubmission)`
   - Root directory: `cloudflarestream`
4. If Moodle shows errors, check the ZIP structure

---

## Common Mistakes

### Mistake 1: Zipping from wrong directory

**Wrong:**
```bash
# From project root
zip -r cloudflarestream.zip mod/assign/submission/cloudflarestream/
```
**Result:** ZIP contains `mod/assign/submission/cloudflarestream/`

**Correct:**
```bash
# From mod/assign/submission/
cd mod/assign/submission
zip -r cloudflarestream.zip cloudflarestream/
```
**Result:** ZIP contains `cloudflarestream/`

### Mistake 2: Including parent folders

**Wrong:**
```bash
cd mod/assign/submission/cloudflarestream
zip -r ../cloudflarestream.zip .
```
**Result:** Files at ZIP root (no cloudflarestream folder)

**Correct:**
```bash
cd mod/assign/submission
zip -r cloudflarestream.zip cloudflarestream/
```

### Mistake 3: Multiple root folders

**Wrong:**
```
cloudflarestream.zip
├── cloudflarestream/
└── some_other_folder/
```

**Correct:**
```
cloudflarestream.zip
└── cloudflarestream/
```

---

## File Exclusions

The provided scripts automatically exclude these files from the release ZIP:

- `.git/` - Git repository data
- `.gitignore` - Git ignore file
- `.DS_Store` - Mac system files
- `create_release_zip.sh` - Build script
- `create_release_zip.bat` - Build script
- `security_test_results.md` - Internal test docs
- `security_audit_report.md` - Internal audit docs
- `gdpr_verification.md` - Internal verification docs
- `tests/INTEGRATION_TESTS.md` - Internal test docs

These files are useful for development but not needed in production.

---

## Summary

**Key Points:**
1. ✅ Plugin folder (`cloudflarestream/`) must be at ZIP root
2. ✅ Use provided scripts for automatic correct structure
3. ✅ Moodle auto-detects plugin type from `version.php`
4. ✅ Test in staging environment first
5. ❌ Never include full path structure (`mod/assign/submission/`)

**Quick Command:**
```bash
cd mod/assign/submission
zip -r cloudflarestream.zip cloudflarestream/
```

**Or just run:**
```bash
./create_release_zip.sh    # Linux/Mac
create_release_zip.bat     # Windows
```
