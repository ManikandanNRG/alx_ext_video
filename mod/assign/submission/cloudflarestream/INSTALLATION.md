# Cloudflare Stream Plugin - Installation Guide

## Quick Start

### For Windows Users

1. Open Command Prompt in the plugin directory
2. Run: `create_release_zip.bat`
3. Find `cloudflarestream.zip` in the `releases` folder
4. Upload via Moodle: **Site Administration → Plugins → Install plugins**

### For Linux/Mac Users

1. Open terminal in the plugin directory
2. Run: `chmod +x create_release_zip.sh && ./create_release_zip.sh`
3. Find `cloudflarestream.zip` in the `releases` folder
4. Upload via Moodle: **Site Administration → Plugins → Install plugins**

---

## Detailed Installation Instructions

### Understanding Moodle Plugin Structure

Moodle plugins must be installed in specific directories based on their type. This is an **Assignment Submission Plugin**, so it belongs in:

```
[moodle-root]/mod/assign/submission/cloudflarestream/
```

When creating a ZIP for installation, the structure must be:

```
cloudflarestream.zip
└── cloudflarestream/          ← Plugin folder at ZIP root
    ├── version.php
    ├── lib.php
    ├── settings.php
    └── ... (all other files)
```

**❌ WRONG** (will be rejected):
```
cloudflarestream.zip
└── mod/
    └── assign/
        └── submission/
            └── cloudflarestream/
```

**✅ CORRECT**:
```
cloudflarestream.zip
└── cloudflarestream/
    ├── version.php
    └── ...
```

---

## Installation Method 1: ZIP Upload (Easiest)

### Step 1: Create Release ZIP

**Option A - Using Provided Scripts:**

**Windows:**
```cmd
cd mod\assign\submission\cloudflarestream
create_release_zip.bat
```

**Linux/Mac:**
```bash
cd mod/assign/submission/cloudflarestream
chmod +x create_release_zip.sh
./create_release_zip.sh
```

The script will:
- Create a properly structured ZIP file
- Remove development files (tests, docs, etc.)
- Place the ZIP in `releases/cloudflarestream_[version].zip`

**Option B - Manual ZIP Creation:**

**Windows (PowerShell):**
```powershell
cd mod\assign\submission
Compress-Archive -Path cloudflarestream -DestinationPath cloudflarestream.zip
```

**Linux/Mac:**
```bash
cd mod/assign/submission
zip -r cloudflarestream.zip cloudflarestream/
```

### Step 2: Upload to Moodle

1. **Log in as Administrator**
2. **Navigate to**: Site Administration → Plugins → Install plugins
3. **Upload ZIP**: Click "Choose a file" and select `cloudflarestream.zip`
4. **Verify Detection**: Moodle should show:
   - Plugin name: `Cloudflare Stream`
   - Plugin type: `Assignment submission (assignsubmission)`
   - Source: `Uploaded ZIP file`
5. **Install**: Click "Install plugin from the ZIP file"
6. **Review**: Check the validation report for any issues
7. **Continue**: Click "Continue" if validation passes
8. **Upgrade Database**: Click "Upgrade Moodle database now"
9. **Success**: You should see "Success" messages for database table creation

### Step 3: Verify Installation

1. Go to: **Site Administration → Plugins → Assignment → Submission plugins**
2. Find "Cloudflare Stream" in the list
3. Status should show as "Enabled" with a green checkmark
4. If disabled, click the eye icon to enable

---

## Installation Method 2: Manual File Copy

### Step 1: Copy Files

**Windows:**
```cmd
xcopy /E /I cloudflarestream C:\path\to\moodle\mod\assign\submission\cloudflarestream
```

**Linux/Mac:**
```bash
cp -r cloudflarestream /path/to/moodle/mod/assign/submission/
```

### Step 2: Set Permissions (Linux/Mac only)

```bash
cd /path/to/moodle/mod/assign/submission
chmod -R 755 cloudflarestream
chown -R www-data:www-data cloudflarestream
```

Replace `www-data` with your web server user (might be `apache`, `nginx`, etc.)

### Step 3: Trigger Installation

1. **Navigate to**: Site Administration → Notifications
2. Moodle will detect the new plugin
3. **Click**: "Upgrade Moodle database now"
4. Database tables will be created automatically

---

## Installation Method 3: Git Clone (Development)

For developers who want to work on the plugin:

```bash
cd /path/to/moodle/mod/assign/submission
git clone [repository-url] cloudflarestream
cd cloudflarestream
```

Then follow Step 2 and 3 from Manual File Copy method above.

---

## Post-Installation Configuration

### Step 1: Set Up Cloudflare Stream Account

1. **Create Account**: Visit [cloudflare.com](https://cloudflare.com) and sign up
2. **Enable Stream**: In dashboard, navigate to Stream and enable the service
3. **Note Account ID**: Found in dashboard URL or right sidebar

### Step 2: Generate API Token

1. **Navigate to**: Cloudflare Dashboard → My Profile → API Tokens
2. **Create Token**: Click "Create Token"
3. **Use Template**: Select "Custom token"
4. **Set Permissions**:
   - Account: `Cloudflare Stream:Edit`
   - Zone Resources: `Include All zones`
5. **Create**: Click "Continue to summary" → "Create Token"
6. **Copy Token**: Save it immediately (won't be shown again)

### Step 3: Configure Plugin in Moodle

1. **Navigate to**: Site Administration → Plugins → Assignment → Submission plugins → Cloudflare Stream
2. **Enter Settings**:
   - **Cloudflare API Token**: Paste the token from Step 2
   - **Cloudflare Account ID**: Enter your account ID from Step 1
   - **Video Retention Period**: Set days to keep videos (default: 90)
   - **Maximum File Size**: Set max upload size in bytes (default: 5 GB)
   - **Enable Plugin**: Check to enable globally
3. **Save Changes**

### Step 4: Test Installation

1. **Create Test Assignment**:
   - Go to a course
   - Add new Assignment activity
   - In settings, enable "Cloudflare Stream" submission type
2. **Test Upload**:
   - As a student, submit a small test video
   - Verify upload completes successfully
3. **Test Playback**:
   - As teacher, view the submission
   - Verify video plays correctly

---

## Troubleshooting Installation

### Issue: "Plugin validation failed"

**Cause**: ZIP structure is incorrect

**Solution**: Ensure ZIP has `cloudflarestream/` at root, not nested paths

### Issue: "Plugin already installed"

**Cause**: Plugin directory already exists

**Solution**: 
1. Delete existing directory: `rm -rf mod/assign/submission/cloudflarestream`
2. Try installation again

### Issue: "Permission denied" errors

**Cause**: Web server can't read plugin files

**Solution** (Linux/Mac):
```bash
chmod -R 755 mod/assign/submission/cloudflarestream
chown -R www-data:www-data mod/assign/submission/cloudflarestream
```

### Issue: Database tables not created

**Cause**: Installation didn't complete

**Solution**:
1. Go to: Site Administration → Notifications
2. Click "Upgrade Moodle database now"
3. Check for error messages

### Issue: Plugin doesn't appear in list

**Cause**: Moodle cache needs clearing

**Solution**:
1. Go to: Site Administration → Development → Purge all caches
2. Refresh the plugins page

---

## Uninstallation

### Via Moodle UI

1. **Navigate to**: Site Administration → Plugins → Assignment → Submission plugins
2. **Find**: "Cloudflare Stream" in the list
3. **Click**: "Uninstall" link
4. **Confirm**: Review what will be deleted
5. **Uninstall**: Click "Continue"

**Note**: This will:
- Remove all database tables and data
- Delete videos from Cloudflare (if configured)
- Remove plugin files

### Manual Uninstallation

1. **Delete plugin directory**:
   ```bash
   rm -rf mod/assign/submission/cloudflarestream
   ```
2. **Clean database** (optional, if Moodle UI uninstall didn't work):
   ```sql
   DROP TABLE mdl_assignsubmission_cfstream;
   DROP TABLE mdl_assignsubmission_cfstream_log;
   DELETE FROM mdl_config_plugins WHERE plugin = 'assignsubmission_cloudflarestream';
   ```
3. **Purge caches**: Site Administration → Development → Purge all caches

---

## Upgrading

### From Previous Version

1. **Backup**: Always backup database and files first
2. **Install New Version**: Use ZIP upload method with new version
3. **Moodle will**:
   - Detect version change
   - Run upgrade scripts automatically
   - Preserve existing data and settings
4. **Verify**: Check that settings are still correct

---

## System Requirements

- **Moodle**: 3.9 or higher (LTS recommended)
- **PHP**: 7.4 or higher
- **HTTPS**: Required (for secure token transmission)
- **Browser**: Modern browser with JavaScript enabled
- **Cloudflare**: Active Stream account with API access

---

## Support

For installation issues:
1. Check Moodle error logs: `[moodle]/admin/tool/log/`
2. Check web server error logs
3. Enable debugging: Site Administration → Development → Debugging
4. Contact your system administrator

---

## Additional Resources

- **Moodle Plugin Installation**: https://docs.moodle.org/en/Installing_plugins
- **Cloudflare Stream Docs**: https://developers.cloudflare.com/stream/
- **Plugin README**: See `README.md` for full documentation
