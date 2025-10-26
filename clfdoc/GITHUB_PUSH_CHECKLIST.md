# GitHub Push Checklist

Use this checklist to ensure everything is ready before pushing to GitHub.

## ✅ Pre-Push Checklist

### Files and Documentation
- [x] `.gitignore` created
- [x] `LICENSE` file added (GPL v3)
- [x] `README.md` updated with badges and links
- [x] `CHANGELOG.md` created with v1.0.0 details
- [x] `CONTRIBUTING.md` added
- [x] `GITHUB_SETUP.md` created
- [x] `RELEASE_v1.0.0.md` created
- [x] All test files listed in `.gitignore`

### Code Quality
- [x] Plugin structure complete
- [x] All PHP files have proper headers
- [x] Database schema correct (table names ≤ 28 chars)
- [x] Language strings complete
- [x] No syntax errors
- [x] Admin pages working
- [x] Dashboard working
- [x] Video management working

### Version Information
- [x] `version.php` shows version 2025102600
- [x] Version number is 1.0.0
- [x] Release date is 2025-10-26
- [x] Requires Moodle 3.9+

### Security
- [x] No hardcoded credentials
- [x] No sensitive data in code
- [x] API tokens stored encrypted
- [x] Input validation implemented
- [x] Rate limiting implemented

## 📋 Push Steps

### Step 1: Review Files
```bash
# Check what will be committed
git status

# Review changes
git diff
```

### Step 2: Clean Up (Optional)
```bash
# Remove test files (they're in .gitignore anyway)
cd mod/assign/submission/cloudflarestream/
rm -f test_*.php debug_*.php check_*.php force_*.php enable_*.php fix_*.php complete_*.php
cd ../../../../
```

### Step 3: Initialize Git
```bash
# If not already initialized
git init

# Add all files
git add .

# Check what's staged
git status
```

### Step 4: Create Initial Commit
```bash
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

### Step 5: Create GitHub Repository
1. Go to https://github.com/new
2. Repository name: `moodle-assignsubmission_cloudflarestream`
3. Description: `Moodle plugin for large video submissions using Cloudflare Stream`
4. Choose: **Public** (recommended for open source)
5. **Do NOT** check:
   - [ ] Add a README file
   - [ ] Add .gitignore
   - [ ] Choose a license
6. Click "Create repository"

### Step 6: Add Remote and Push
```bash
# Add remote (replace YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream.git

# Verify remote
git remote -v

# Rename branch to main (if needed)
git branch -M main

# Push to GitHub
git push -u origin main
```

### Step 7: Create Release Tag
```bash
# Create tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial Release

Features:
- Direct browser-to-Cloudflare uploads
- Secure video playback
- Admin dashboard
- GDPR compliance
- Full documentation"

# Push tag
git push origin v1.0.0
```

## 🎯 Post-Push Tasks

### On GitHub Website

#### 1. Update Repository Settings
- [ ] Go to repository Settings
- [ ] Update "About" section:
  - Description: "Moodle plugin for large video submissions using Cloudflare Stream"
  - Website: (your documentation URL if any)
  - Topics: `moodle`, `moodle-plugin`, `cloudflare-stream`, `video-upload`, `php`, `javascript`, `education`, `lms`

#### 2. Create GitHub Release
- [ ] Go to "Releases" → "Create a new release"
- [ ] Choose tag: `v1.0.0`
- [ ] Release title: `v1.0.0 - Initial Release`
- [ ] Description: Copy from `RELEASE_v1.0.0.md`
- [ ] Check "Set as the latest release"
- [ ] Click "Publish release"

#### 3. Enable Features
- [ ] Enable Issues
- [ ] Enable Discussions
- [ ] Enable Wiki (optional)
- [ ] Enable Projects (optional)

#### 4. Update README Links
- [ ] Replace `YOUR_USERNAME` with your actual GitHub username in:
  - `README.md`
  - `CONTRIBUTING.md`
  - `GITHUB_SETUP.md`
  - `RELEASE_v1.0.0.md`

```bash
# Quick find and replace (Linux/Mac)
find . -type f -name "*.md" -exec sed -i 's/YOUR_USERNAME/your-actual-username/g' {} +

# Then commit and push
git add .
git commit -m "docs: Update GitHub username in documentation"
git push
```

#### 5. Add Repository Topics
Click the gear icon next to "About" and add:
- `moodle`
- `moodle-plugin`
- `cloudflare-stream`
- `video-upload`
- `php`
- `javascript`
- `education`
- `lms`
- `assignment`
- `e-learning`

#### 6. Create Issue Templates (Optional)
Create `.github/ISSUE_TEMPLATE/bug_report.md`:
```markdown
---
name: Bug report
about: Create a report to help us improve
title: '[BUG] '
labels: bug
assignees: ''
---

**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- Moodle version:
- PHP version:
- Plugin version:
- Browser:

**Additional context**
Any other context about the problem.
```

Create `.github/ISSUE_TEMPLATE/feature_request.md`:
```markdown
---
name: Feature request
about: Suggest an idea for this project
title: '[FEATURE] '
labels: enhancement
assignees: ''
---

**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Any alternative solutions or features you've considered.

**Additional context**
Any other context or screenshots about the feature request.
```

## 🚀 Automated Push (Easy Way)

### Windows
```bash
push_to_github.bat
```

### Linux/Mac
```bash
chmod +x push_to_github.sh
./push_to_github.sh
```

## 📢 Sharing Your Plugin

### Moodle Community
- [ ] Post in Moodle forums: https://moodle.org/mod/forum/
- [ ] Submit to Moodle Plugins Directory: https://moodle.org/plugins/
- [ ] Share in Moodle Slack/Discord communities

### Social Media
- [ ] Tweet with #Moodle hashtag
- [ ] Post on LinkedIn
- [ ] Share in relevant Reddit communities (r/moodle, r/opensource)
- [ ] Post in education technology forums

### Documentation Sites
- [ ] Add to awesome-moodle lists
- [ ] Create demo video (YouTube)
- [ ] Write blog post about the plugin

## 🔄 Regular Maintenance

### Weekly
- [ ] Check for new issues
- [ ] Respond to questions
- [ ] Review pull requests

### Monthly
- [ ] Update dependencies
- [ ] Check for Moodle updates
- [ ] Review security advisories

### Per Release
- [ ] Update CHANGELOG.md
- [ ] Create release notes
- [ ] Tag new version
- [ ] Test thoroughly
- [ ] Announce release

## ✅ Final Verification

After pushing, verify:
- [ ] Repository is accessible
- [ ] README displays correctly
- [ ] License is visible
- [ ] All files are present
- [ ] .gitignore is working (test files not pushed)
- [ ] Release is created
- [ ] Topics are added
- [ ] Links work correctly

## 🎉 You're Done!

Your plugin is now on GitHub and ready to share with the world!

Repository URL: `https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream`

---

**Need Help?**
- See `GITHUB_SETUP.md` for detailed instructions
- Check `CONTRIBUTING.md` for contribution guidelines
- Review `RELEASE_v1.0.0.md` for release details
