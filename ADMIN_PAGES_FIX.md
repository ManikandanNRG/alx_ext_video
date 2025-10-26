# Admin Pages "Section Error" Fix

## Problem
The dashboard.php and videomanagement.php pages were showing "Section error" because they were calling `admin_externalpage_setup()` with page names that weren't registered in Moodle's admin tree.

## Root Cause
```php
// This was causing the error:
admin_externalpage_setup('assignsubmission_cloudflarestream_dashboard');
```

These external page names need to be registered in `settings.php` first, OR we need to use a different authentication method.

## Solution Applied
Changed both files to use standard authentication instead of `admin_externalpage_setup()`:

### Before:
```php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('assignsubmission_cloudflarestream_dashboard');
```

### After:
```php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
```

## Files Modified

1. **mod/assign/submission/cloudflarestream/dashboard.php**
   - Replaced `admin_externalpage_setup()` with manual authentication
   - Added proper context setup
   - Set admin page layout

2. **mod/assign/submission/cloudflarestream/videomanagement.php**
   - Replaced `admin_externalpage_setup()` with manual authentication
   - Added proper context setup
   - Set admin page layout

## Testing

### Test the Fix:

1. **Access test page:**
   ```
   http://your-moodle-site/mod/assign/submission/cloudflarestream/test_admin_pages.php
   ```

2. **Access dashboard directly:**
   ```
   http://your-moodle-site/mod/assign/submission/cloudflarestream/dashboard.php
   ```

3. **Access video management directly:**
   ```
   http://your-moodle-site/mod/assign/submission/cloudflarestream/videomanagement.php
   ```

### Expected Results:
- ✅ No "Section error"
- ✅ Pages load with admin layout
- ✅ Only admins can access (requires moodle/site:config capability)
- ✅ All language strings display correctly

## How to Access These Pages

Since these pages are not in the admin menu tree, you can access them:

### Option 1: Direct URL
Bookmark or link to:
- Dashboard: `/mod/assign/submission/cloudflarestream/dashboard.php`
- Video Management: `/mod/assign/submission/cloudflarestream/videomanagement.php`

### Option 2: Add to Admin Menu (Optional)
If you want these in the admin menu, add to `settings.php`:

```php
if ($ADMIN->fulltree) {
    // ... existing settings ...
    
    // Add external pages to admin menu
    $ADMIN->add('assignsubmissionplugins', 
        new admin_externalpage(
            'assignsubmission_cloudflarestream_dashboard',
            get_string('dashboard', 'assignsubmission_cloudflarestream'),
            new moodle_url('/mod/assign/submission/cloudflarestream/dashboard.php')
        )
    );
    
    $ADMIN->add('assignsubmissionplugins',
        new admin_externalpage(
            'assignsubmission_cloudflarestream_videomanagement',
            get_string('videomanagement', 'assignsubmission_cloudflarestream'),
            new moodle_url('/mod/assign/submission/cloudflarestream/videomanagement.php')
        )
    );
}
```

## Security
Both pages now properly check:
- ✅ User is logged in (`require_login()`)
- ✅ User has admin capability (`require_capability('moodle/site:config', ...)`)
- ✅ Proper context is set
- ✅ Admin page layout is used

## Summary
The "Section error" is now fixed. Both admin pages work correctly and can be accessed directly by administrators. The pages maintain proper security checks and use the standard Moodle admin layout.
