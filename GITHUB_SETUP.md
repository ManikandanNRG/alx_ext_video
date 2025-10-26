# GitHub Setup Guide

## Preparing for GitHub Push

### Step 1: Clean Up Test Files

The following test files should be deleted before pushing to GitHub (they're already in .gitignore):

```bash
# Navigate to plugin directory
cd mod/assign/submission/cloudflarestream/

# Remove test files
rm -f test_*.php
rm -f debug_*.php
rm -f check_*.php
rm -f force_*.php
rm -f enable_*.php
rm -f fix_*.php
rm -f complete_*.php
```

Or keep them locally but they won't be pushed (they're in .gitignore).

### Step 2: Initialize Git Repository

```bash
# Navigate to your project root
cd /path/to/your/project

# Initialize git (if not already done)
git init

# Add all files
git add .

# Check what will be committed
git status

# Make initial commit
git commit -m "Initial commit: Cloudflare Stream Plugin v1.0.0

- Complete Moodle assignment submission plugin
- Direct browser-to-Cloudflare video uploads (up to 5GB)
- Secure playback with signed tokens
- Admin dashboard and video management
- GDPR compliant with data export/deletion
- Rate limiting and security hardening
- Comprehensive error handling and logging
- Full documentation and tests included"
```

### Step 3: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `moodle-assignsubmission_cloudflarestream`
3. Description: `Moodle plugin for large video submissions using Cloudflare Stream`
4. Choose: **Public** (or Private if you prefer)
5. **Do NOT** initialize with README, .gitignore, or license (we already have them)
6. Click "Create repository"

### Step 4: Push to GitHub

```bash
# Add GitHub remote (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream.git

# Verify remote
git remote -v

# Push to GitHub
git push -u origin main

# If your default branch is 'master' instead of 'main':
# git branch -M main
# git push -u origin main
```

### Step 5: Create Release Tag

```bash
# Create version tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial Release

Features:
- Direct browser-to-Cloudflare uploads
- Secure video playback
- Admin dashboard
- GDPR compliance
- Full documentation"

# Push tag to GitHub
git push origin v1.0.0
```

### Step 6: Create GitHub Release

1. Go to your repository on GitHub
2. Click "Releases" → "Create a new release"
3. Choose tag: `v1.0.0`
4. Release title: `v1.0.0 - Initial Release`
5. Description: Copy from CHANGELOG.md
6. Click "Publish release"

## Repository Structure

Your GitHub repository will have this structure:

```
moodle-assignsubmission_cloudflarestream/
├── .gitignore
├── LICENSE
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── GITHUB_SETUP.md (this file)
├── PLUGIN_STATUS_SUMMARY.md
├── DEPLOYMENT_SUMMARY.md
├── EC2_DEPLOYMENT.txt
├── DEPLOY_TO_EC2.md
├── deploy_to_ec2.sh
│
├── .kiro/
│   └── specs/
│       └── cloudflare-stream-integration/
│           ├── requirements.md
│           ├── design.md
│           └── tasks.md
│
└── mod/assign/submission/cloudflarestream/
    ├── version.php
    ├── lib.php
    ├── locallib.php
    ├── settings.php
    ├── dashboard.php
    ├── videomanagement.php
    ├── styles.css
    ├── README.md
    │
    ├── db/
    │   ├── install.xml
    │   ├── upgrade.php
    │   ├── access.php
    │   ├── tasks.php
    │   └── caches.php
    │
    ├── classes/
    │   ├── api/
    │   ├── privacy/
    │   ├── task/
    │   └── *.php
    │
    ├── ajax/
    │   └── *.php
    │
    ├── amd/src/
    │   └── *.js
    │
    ├── templates/
    │   └── *.mustache
    │
    ├── lang/en/
    │   └── assignsubmission_cloudflarestream.php
    │
    └── tests/
        └── *_test.php
```

## Recommended GitHub Settings

### Repository Settings

1. **About Section**:
   - Description: "Moodle plugin for large video submissions using Cloudflare Stream"
   - Website: Your Moodle site or documentation URL
   - Topics: `moodle`, `moodle-plugin`, `cloudflare`, `video-upload`, `education`, `lms`

2. **Features**:
   - ✅ Issues
   - ✅ Discussions
   - ✅ Wiki (optional)
   - ✅ Projects (optional)

3. **Branch Protection** (for main branch):
   - Require pull request reviews
   - Require status checks to pass
   - Require branches to be up to date

### Issue Templates

Create `.github/ISSUE_TEMPLATE/` with:
- `bug_report.md`
- `feature_request.md`

### Pull Request Template

Create `.github/pull_request_template.md`

## After Pushing

### Update README Links

Replace `YOUR_USERNAME` in README.md with your actual GitHub username:

```bash
# In README.md, find and replace:
YOUR_USERNAME → your-actual-username
```

### Add Topics/Tags

On GitHub repository page:
- Click the gear icon next to "About"
- Add topics: `moodle`, `moodle-plugin`, `cloudflare-stream`, `video-upload`, `php`, `javascript`, `education`

### Enable GitHub Pages (Optional)

If you want to host documentation:
1. Settings → Pages
2. Source: Deploy from a branch
3. Branch: main, folder: /docs (if you create a docs folder)

## Sharing Your Plugin

### Moodle Plugins Directory

To submit to https://moodle.org/plugins/:

1. Create account on moodle.org
2. Go to Plugins → Register a new plugin
3. Fill in details:
   - Plugin type: Assignment submission (assignsubmission)
   - Plugin name: cloudflarestream
   - Source control URL: Your GitHub repository
4. Submit for review

### Social Media

Share on:
- Moodle forums
- Twitter/X with #Moodle hashtag
- LinkedIn
- Reddit r/moodle

## Maintenance

### Regular Updates

```bash
# Make changes
git add .
git commit -m "Description of changes"
git push origin main

# For new versions
git tag -a v1.1.0 -m "Version 1.1.0 - Description"
git push origin v1.1.0
```

### Responding to Issues

- Respond within 1 week
- Label issues appropriately (bug, enhancement, question)
- Close resolved issues
- Link PRs to issues

## Security

For security issues:
- Create SECURITY.md with reporting instructions
- Don't discuss security issues publicly
- Patch quickly and release security updates

## License Compliance

- All code must be GPL v3 compatible
- Credit third-party libraries
- Include license headers in new files

---

## Quick Command Reference

```bash
# Initial setup
git init
git add .
git commit -m "Initial commit: v1.0.0"
git remote add origin https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream.git
git push -u origin main

# Create release
git tag -a v1.0.0 -m "Version 1.0.0"
git push origin v1.0.0

# Regular updates
git add .
git commit -m "Your commit message"
git push

# New version
git tag -a v1.x.x -m "Version 1.x.x"
git push origin v1.x.x
```

---

Good luck with your GitHub repository! 🚀
