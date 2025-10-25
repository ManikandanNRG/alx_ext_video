# Deploy Cloudflare Stream Plugin to EC2 Ubuntu Server

## Your Setup
- **Server**: EC2 Ubuntu
- **Moodle**: IOMAD-based installation
- **Issue**: ZIP upload fails with "Unknown plugin type [assignsubmission]"
- **Solution**: Manual deployment via SSH/SFTP

---

## Deployment Steps

### Step 1: Package the Plugin

On your local machine, create a tar.gz of just the plugin folder:

```bash
cd mod/assign/submission
tar -czf cloudflarestream.tar.gz cloudflarestream/
```

This creates `cloudflarestream.tar.gz` containing the plugin.

### Step 2: Upload to EC2

**Option A - Using SCP:**
```bash
scp cloudflarestream.tar.gz ubuntu@your-ec2-ip:/tmp/
```

**Option B - Using SFTP:**
```bash
sftp ubuntu@your-ec2-ip
put cloudflarestream.tar.gz /tmp/
exit
```

### Step 3: SSH into EC2

```bash
ssh ubuntu@your-ec2-ip
```

### Step 4: Extract to Moodle Directory

```bash
# Navigate to Moodle's assignment submission directory
cd /var/www/html/moodle/mod/assign/submission/

# If your Moodle is in a different location, adjust the path
# Common locations:
# /var/www/html/moodle/
# /opt/moodle/
# /usr/share/nginx/html/moodle/

# Extract the plugin
sudo tar -xzf /tmp/cloudflarestream.tar.gz

# Set correct ownership (replace www-data if your web server user is different)
sudo chown -R www-data:www-data cloudflarestream/

# Set correct permissions
sudo chmod -R 755 cloudflarestream/

# Verify files are in place
ls -la cloudflarestream/
```

### Step 5: Trigger Moodle Installation

1. Open your browser
2. Go to your Moodle URL
3. Log in as administrator
4. Navigate to: **Site Administration → Notifications**
5. Moodle will detect the new plugin
6. Click: **"Upgrade Moodle database now"**
7. Follow the prompts

### Step 6: Verify Installation

1. Go to: **Site Administration → Plugins → Activity modules → Assignment → Submission plugins**
2. You should see **"Cloudflare Stream"** listed
3. If disabled, click the eye icon to enable it

### Step 7: Configure Plugin

1. Click on **"Cloudflare Stream"** settings
2. Enter your **Cloudflare API Token**
3. Enter your **Cloudflare Account ID**
4. Set **Video Retention Period** (default: 90 days)
5. Set **Maximum File Size** (default: 5 GB)
6. Click **Save changes**

---

## Quick Commands (Copy-Paste Ready)

### If Moodle is in `/var/www/html/moodle/`:

```bash
# On your local machine
cd mod/assign/submission
tar -czf cloudflarestream.tar.gz cloudflarestream/
scp cloudflarestream.tar.gz ubuntu@YOUR_EC2_IP:/tmp/

# On EC2 server
ssh ubuntu@YOUR_EC2_IP
cd /var/www/html/moodle/mod/assign/submission/
sudo tar -xzf /tmp/cloudflarestream.tar.gz
sudo chown -R www-data:www-data cloudflarestream/
sudo chmod -R 755 cloudflarestream/
sudo rm /tmp/cloudflarestream.tar.gz
```

Then visit: **Site Administration → Notifications** in Moodle

---

## Finding Your Moodle Directory

If you're not sure where Moodle is installed:

```bash
# Search for Moodle config file
sudo find / -name "config.php" -path "*/moodle/*" 2>/dev/null

# Or check Apache/Nginx config
sudo cat /etc/apache2/sites-enabled/000-default.conf
# or
sudo cat /etc/nginx/sites-enabled/default
```

---

## Finding Your Web Server User

```bash
# Check Apache
ps aux | grep apache2 | head -1

# Check Nginx
ps aux | grep nginx | head -1

# Common users: www-data, apache, nginx
```

---

## Troubleshooting

### Issue: Permission Denied

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/html/moodle/mod/assign/submission/cloudflarestream/

# Fix permissions
sudo chmod -R 755 /var/www/html/moodle/mod/assign/submission/cloudflarestream/
```

### Issue: Plugin Not Detected

```bash
# Clear Moodle cache
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

# Or via browser: Site Administration → Development → Purge all caches
```

### Issue: Database Tables Not Created

1. Check Moodle error logs:
   ```bash
   sudo tail -f /var/www/html/moodle/error.log
   ```

2. Check Apache/Nginx error logs:
   ```bash
   sudo tail -f /var/log/apache2/error.log
   # or
   sudo tail -f /var/log/nginx/error.log
   ```

3. Manually trigger upgrade:
   ```bash
   sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php
   ```

---

## Alternative: Git Clone (For Development)

If you're actively developing:

```bash
# On EC2
cd /var/www/html/moodle/mod/assign/submission/
sudo git clone YOUR_REPO_URL cloudflarestream
sudo chown -R www-data:www-data cloudflarestream/
sudo chmod -R 755 cloudflarestream/
```

Then visit: **Site Administration → Notifications**

---

## Security Considerations

### File Permissions
- **Directories**: 755 (rwxr-xr-x)
- **Files**: 644 (rw-r--r--)
- **Owner**: www-data:www-data (or your web server user)

### Verify Permissions
```bash
# Check current permissions
ls -la /var/www/html/moodle/mod/assign/submission/cloudflarestream/

# Should show:
# drwxr-xr-x  www-data www-data  (directories)
# -rw-r--r--  www-data www-data  (files)
```

---

## Post-Installation Testing

### Test 1: Create Assignment
1. Go to a course
2. Add new Assignment activity
3. In settings, enable "Cloudflare Stream" submission type
4. Save

### Test 2: Upload Video
1. As a student, open the assignment
2. Upload a small test video (< 100 MB)
3. Verify upload completes successfully
4. Check that video UID is saved in database

### Test 3: View Submission
1. As teacher, view the submission
2. Verify video player loads
3. Verify video plays correctly

### Test 4: Check Logs
```bash
# Check Moodle logs
sudo tail -f /var/www/html/moodle/error.log

# Check database
mysql -u moodle_user -p moodle_db
SELECT * FROM mdl_assignsubmission_cfstream LIMIT 5;
```

---

## Monitoring

### Check Plugin Status
```bash
# Via CLI
sudo -u www-data php /var/www/html/moodle/admin/cli/plugin_info.php assignsubmission_cloudflarestream
```

### View Upload Statistics
- Go to: **Site Administration → Plugins → Assignment → Submission plugins → Cloudflare Stream → Dashboard**

---

## Backup Before Deployment

Always backup before deploying:

```bash
# Backup Moodle database
mysqldump -u moodle_user -p moodle_db > moodle_backup_$(date +%Y%m%d).sql

# Backup Moodle files (optional)
sudo tar -czf moodle_backup_$(date +%Y%m%d).tar.gz /var/www/html/moodle/
```

---

## Summary for EC2 Ubuntu

1. **Package**: `tar -czf cloudflarestream.tar.gz cloudflarestream/`
2. **Upload**: `scp cloudflarestream.tar.gz ubuntu@ec2-ip:/tmp/`
3. **Extract**: `sudo tar -xzf /tmp/cloudflarestream.tar.gz` (in moodle/mod/assign/submission/)
4. **Permissions**: `sudo chown -R www-data:www-data cloudflarestream/`
5. **Install**: Visit Site Administration → Notifications in Moodle
6. **Configure**: Enter Cloudflare API credentials

That's it! No ZIP upload needed.
