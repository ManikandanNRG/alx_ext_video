# Final Fix for Cloudflare Stream Plugin Not Appearing

## Root Cause
The plugin is fully installed and enabled in the database, but PHP's opcode cache is preventing the assignment module from seeing the updated configuration.

## The Fix (Choose ONE method)

### Method 1: Restart Web Server (RECOMMENDED)
```bash
# For Apache
sudo systemctl restart apache2
# OR
sudo service apache2 restart

# For Nginx + PHP-FPM
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### Method 2: Clear PHP Opcode Cache via PHP
Run this SQL command in your MySQL database:

```sql
-- Force update the enabled config to trigger cache invalidation
UPDATE mdl_config_plugins 
SET value = '1' 
WHERE plugin = 'assignsubmission_cloudflarestream' 
AND name = 'enabled';

-- Also ensure default is set
INSERT INTO mdl_config_plugins (plugin, name, value) 
VALUES ('assignsubmission_cloudflarestream', 'default', '1')
ON DUPLICATE KEY UPDATE value = '1';
```

Then access this URL to clear PHP cache:
```
http://your-site/admin/purgecaches.php
```

### Method 3: Modify lib.php to Force Enable (TEMPORARY WORKAROUND)

If you can't restart the server, modify the `is_enabled()` method to always return true:

```php
public function is_enabled() {
    // Temporary fix: always return true
    return true;
}
```

And modify `get_config()` calls to return default values.

## After Applying Fix

1. Clear browser cache or use incognito mode
2. Go to any assignment
3. Click "Edit settings"
4. Scroll to "Submission types"
5. Cloudflare Stream should now appear

## Why This Happened

When you copied the plugin from another server, the PHP opcode cache (OPcache/APC) cached the old state where the plugin wasn't enabled. Even though the database shows `enabled=1`, PHP is serving cached configuration data.

## Verification

After restarting, run this to verify:
```bash
# Check if web server restarted
sudo systemctl status apache2
# OR
sudo systemctl status nginx
sudo systemctl status php-fpm
```

Then test by creating/editing an assignment.
