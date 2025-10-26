# Cloudflare Stream Plugin for Moodle/IOMAD

[![Moodle](https://img.shields.io/badge/Moodle-3.9%2B-orange.svg)](https://moodle.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg)](CHANGELOG.md)

A Moodle assignment submission plugin that enables students to upload large video files (up to 5 GB) directly to Cloudflare Stream, bypassing server storage entirely.

> **Version 1.0.0** - Initial release. Plugin structure and code are production-ready. Full end-to-end testing requires an active Cloudflare Stream account ($5/month minimum).

## Features

- **Direct Browser Upload**: Videos upload directly to Cloudflare (no server load)
- **Large File Support**: Up to 5 GB per video
- **Resumable Uploads**: Automatic retry and resume for interrupted uploads
- **Secure Playback**: Signed tokens with 24-hour expiration
- **Automatic Cleanup**: Configurable retention policy (default: 90 days)
- **GDPR Compliant**: Full data export and deletion support
- **Admin Dashboard**: Upload statistics and error monitoring

## Quick Start for EC2 Ubuntu

### 1. Deploy Plugin

**Automated:**
```bash
chmod +x deploy_to_ec2.sh
./deploy_to_ec2.sh
```

**Manual:**
```bash
# Local machine
cd mod/assign/submission
tar -czf cloudflarestream.tar.gz cloudflarestream/
scp cloudflarestream.tar.gz ubuntu@YOUR_EC2_IP:/tmp/

# EC2 server
ssh ubuntu@YOUR_EC2_IP
cd /var/www/html/moodle/mod/assign/submission/
sudo tar -xzf /tmp/cloudflarestream.tar.gz
sudo chown -R www-data:www-data cloudflarestream/
sudo chmod -R 755 cloudflarestream/
```

### 2. Complete Installation

1. Open browser → Your Moodle URL
2. Log in as administrator
3. Go to: **Site Administration → Notifications**
4. Click: **"Upgrade Moodle database now"**

### 3. Configure

1. Go to: **Site Administration → Plugins → Assignment → Submission plugins → Cloudflare Stream**
2. Enter your **Cloudflare API Token**
3. Enter your **Cloudflare Account ID**
4. Set retention period and max file size
5. Save changes

## Documentation

- **EC2_DEPLOYMENT.txt** - Quick reference for EC2 deployment
- **DEPLOY_TO_EC2.md** - Complete deployment guide with troubleshooting
- **mod/assign/submission/cloudflarestream/README.md** - Full plugin documentation

## Requirements

- Moodle 3.9 or higher (IOMAD compatible)
- PHP 7.4 or higher
- HTTPS enabled
- Cloudflare Stream account with API access

## Architecture

```
Student Browser → Cloudflare Stream (Direct Upload)
                       ↓
                  Video Storage
                       ↓
Teacher Browser ← Cloudflare CDN (Playback)
```

Moodle server only handles:
- Generating upload URLs
- Storing video metadata
- Generating playback tokens
- Access control

## Why Not ZIP Upload?

Assignment submission plugins are "subplugins" in Moodle. The ZIP installer only works for top-level plugins. This is standard Moodle behavior - all assignment submission plugins must be manually deployed.

## Support

For issues:
1. Check **DEPLOY_TO_EC2.md** troubleshooting section
2. Check Moodle error logs: `/var/www/html/moodle/error.log`
3. Check web server logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
4. Enable Moodle debugging: Site Administration → Development → Debugging

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and planned features.

## Support

- **Issues**: [GitHub Issues](https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream/issues)
- **Discussions**: [GitHub Discussions](https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream/discussions)
- **Documentation**: See [plugin documentation](mod/assign/submission/cloudflarestream/README.md)

## License

GNU GPL v3 or later. See [LICENSE](LICENSE) for full text.

## Credits

Developed for the Moodle community with ❤️

## Disclaimer

Requires Cloudflare Stream subscription ($5/month minimum). Plugin provided "as is" without warranty. Test in staging before production deployment.
