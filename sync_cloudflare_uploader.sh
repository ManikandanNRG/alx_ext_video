#!/bin/bash

# Sync Cloudflare uploader files to EC2
echo "Syncing Cloudflare uploader to EC2..."

# Copy the uploader files
scp mod/assign/submission/cloudflarestream/amd/src/uploader.js ubuntu@dev.aktrea.net:/var/www/html/moodle/mod/assign/submission/cloudflarestream/amd/src/uploader.js
scp mod/assign/submission/cloudflarestream/amd/build/uploader.min.js ubuntu@dev.aktrea.net:/var/www/html/moodle/mod/assign/submission/cloudflarestream/amd/build/uploader.min.js

# Purge Moodle cache on EC2
echo "Purging Moodle cache..."
ssh ubuntu@dev.aktrea.net "cd /var/www/html/moodle && sudo -u www-data php admin/cli/purge_caches.php"

echo "Done! Please hard refresh your browser (Ctrl+Shift+R)"
