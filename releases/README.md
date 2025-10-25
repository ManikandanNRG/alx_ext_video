# Cloudflare Stream Plugin - Releases

This directory contains release ZIP files ready for Moodle installation.

## Creating a Release

### Windows
```cmd
cd mod\assign\submission\cloudflarestream
create_release_zip.bat
```

### Linux/Mac
```bash
cd mod/assign/submission/cloudflarestream
chmod +x create_release_zip.sh
./create_release_zip.sh
```

The release ZIP will be created in this directory.

## Installing in Moodle

1. **Via Moodle UI** (Recommended):
   - Go to: Site Administration → Plugins → Install plugins
   - Upload the ZIP file
   - Follow the installation wizard

2. **Manual Installation**:
   - Extract ZIP to: `[moodle-root]/mod/assign/submission/cloudflarestream/`
   - Visit: Site Administration → Notifications
   - Click "Upgrade Moodle database now"

## Release Files

Release files are named: `cloudflarestream_[version].zip`

Example: `cloudflarestream_1.0.0.zip`

## Documentation

See the following files in the plugin directory for detailed instructions:
- `INSTALLATION.md` - Complete installation guide
- `ZIP_STRUCTURE.md` - Understanding the ZIP structure
- `README.md` - Full plugin documentation
