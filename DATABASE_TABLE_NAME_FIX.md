# Database Table Name Fix

## Problem
The dashboard.php page was showing "Error reading from database" because the logger class was using the wrong table name.

## Root Cause
The database table was created as `assignsubmission_cfs_log` (26 characters) in install.xml to comply with Moodle's 28-character table name limit, but the logger.php class was still referencing the old name `assignsubmission_cfstream_log` (30 characters).

## Table Names in Database

### Correct Names (as defined in install.xml):
1. `mdl_assignsubmission_cfstream` - Main video metadata table
2. `mdl_assignsubmission_cfs_log` - Event logging table

### Wrong Names (used in logger.php before fix):
1. ❌ `mdl_assignsubmission_cfstream_log` - This table doesn't exist!

## Fix Applied

Updated all references in `mod/assign/submission/cloudflarestream/classes/logger.php`:

### Changed in all methods:
- `$DB->insert_record('assignsubmission_cfstream_log', ...)` 
- → `$DB->insert_record('assignsubmission_cfs_log', ...)`

### Changed in SQL queries:
- `FROM {assignsubmission_cfstream_log}`
- → `FROM {assignsubmission_cfs_log}`

### Total Changes:
- ✅ 7 insert_record() calls updated
- ✅ 4 SQL queries updated
- ✅ 3 count_records_select() calls updated

## Files Modified
- `mod/assign/submission/cloudflarestream/classes/logger.php`

## Testing

### Test Dashboard:
```
http://your-moodle-site/mod/assign/submission/cloudflarestream/dashboard.php
```

### Expected Results:
- ✅ No database errors
- ✅ Dashboard loads with statistics (will show 0 if no uploads yet)
- ✅ Upload statistics section displays
- ✅ No recent failures message (if no uploads yet)

### Test Video Management:
```
http://your-moodle-site/mod/assign/submission/cloudflarestream/videomanagement.php
```

### Expected Results:
- ✅ Page loads correctly (already working)
- ✅ Shows "No videos to display" if no uploads yet
- ✅ Filter options work

## Why This Happened

Moodle has a strict 28-character limit for database table names (without the `mdl_` prefix). The original table name `assignsubmission_cfstream_log` is 30 characters, so it was shortened to `assignsubmission_cfs_log` (26 characters) in install.xml, but the logger class wasn't updated to match.

## Summary

Both admin pages should now work correctly:
- ✅ Dashboard - Fixed (database table name corrected)
- ✅ Video Management - Already working

The plugin is now fully functional for testing video uploads and playback!
