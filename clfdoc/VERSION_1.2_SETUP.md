# Version 1.2 Setup Guide

## Quick Start Commands

### 1. Tag Current Stable Version (v1.1.0)

```bash
# Make sure you're on main branch with latest changes
git checkout main
git pull

# Tag the current stable version
git tag -a v1.1.0 -m "Version 1.1.0 - Stable direct upload implementation

Features:
- Direct upload up to 200MB
- Video playback with signed URLs
- Automatic cleanup system
- Rate limiting
- GDPR compliance
- Grading interface integration
- Security audit completed"

# Push the tag
git push origin v1.1.0

# Verify tag was created
git tag -l
git show v1.1.0
```

### 2. Create Feature Branch for v1.2

```bash
# Create new branch from main
git checkout -b feature/tus-upload-v1.2

# Verify you're on the new branch
git branch
# Should show: * feature/tus-upload-v1.2

# Push branch to remote
git push -u origin feature/tus-upload-v1.2
```

### 3. Update Version File

The version.php file needs to be updated to reflect v1.2.0-dev:

```php
<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->version   = 2025110101;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2022112800;        // Requires Moodle 4.1 or higher
$plugin->component = 'assignsubmission_cloudflarestream';
$plugin->maturity  = MATURITY_BETA;     // Changed to BETA for development
$plugin->release   = '1.2.0-dev';       // Development version
$plugin->dependencies = array();
```

**Changes**:
- `version`: 2025110101 (today's date + 01)
- `maturity`: MATURITY_BETA (was MATURITY_STABLE)
- `release`: '1.2.0-dev' (was '1.0.1')

### 4. Commit Version Change

```bash
# Stage the version file
git add mod/assign/submission/cloudflarestream/version.php

# Commit with clear message
git commit -m "chore: Bump version to 1.2.0-dev for TUS upload feature

- Changed version to 2025110101
- Set maturity to BETA for development
- Updated release to 1.2.0-dev
- Starting TUS resumable upload implementation"

# Push to remote
git push
```

## Version Comparison

### v1.1.0 (Main Branch - Stable)
```php
$plugin->version   = 2025102701;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
```

**Features**:
- ✅ Direct upload (max 200MB)
- ✅ Video playback
- ✅ Cleanup system
- ✅ Rate limiting
- ✅ GDPR compliance

### v1.2.0-dev (Feature Branch - Development)
```php
$plugin->version   = 2025110101;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.0-dev';
```

**New Features**:
- 🚧 TUS resumable upload (max 30GB)
- 🚧 Hybrid upload system
- 🚧 Resume capability
- 🚧 Improved progress tracking
- ✅ All v1.1.0 features maintained

## Development Workflow

### Daily Development

```bash
# Start your day
git checkout feature/tus-upload-v1.2
git pull

# Make changes
# ... implement TUS features ...

# Commit frequently
git add .
git commit -m "feat: Add TUS session creation method"
git push

# Continue development
# ... more changes ...

git add .
git commit -m "feat: Add chunk upload with progress tracking"
git push
```

### Switching Between Branches

```bash
# Switch to stable version (v1.1.0)
git checkout main
# Now you have the stable direct upload version

# Switch back to development (v1.2.0-dev)
git checkout feature/tus-upload-v1.2
# Now you have the TUS upload development version
```

### Testing Both Versions

```bash
# Test stable version
git checkout main
# Deploy to test server
# Test direct upload (up to 200MB)

# Test development version
git checkout feature/tus-upload-v1.2
# Deploy to dev server
# Test TUS upload (up to 30GB)
```

## File Changes for v1.2

### Files to Modify

1. **version.php** ✅ (Already updated)
2. **classes/api/cloudflare_client.php** (Add TUS methods)
3. **amd/src/uploader.js** (Add TUS upload logic)
4. **README.md** (Update with v1.2 features)

### Files to Create

1. **ajax/get_tus_credentials.php** (New endpoint)
2. **classes/tus_uploader.php** (Optional: TUS logic class)
3. **CHANGELOG.md** (Document changes)

### Files to Keep Unchanged

- All existing functionality
- Database schema (no changes needed)
- Templates
- Language files (add new strings only)

## Changelog Template

Create `CHANGELOG.md`:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0-dev] - In Development

### Added
- TUS resumable upload protocol support
- Upload files up to 30GB
- Hybrid upload system (direct <200MB, TUS >200MB)
- Resume capability for interrupted uploads
- Improved progress tracking with MB uploaded
- Chunk-based upload with 50MB chunks

### Changed
- Upload logic now routes based on file size
- Progress reporting shows MB uploaded

### Fixed
- UID extraction now uses stream-media-id header (official method)

## [1.1.0] - 2025-10-27

### Added
- Direct upload up to 200MB
- Video playback with signed URLs
- Automatic cleanup system
- Rate limiting
- GDPR compliance
- Grading interface integration

### Fixed
- Cleanup false error messages
- Status display bugs
- Quota reservation timing

## [1.0.1] - 2025-10-26

### Fixed
- Initial bug fixes

## [1.0.0] - 2025-10-25

### Added
- Initial release
```

## Branch Status Tracking

### Main Branch (v1.1.0)
```
Status: ✅ Stable, Production-Ready
Last Updated: 2025-10-27
Features: Direct upload, Playback, Cleanup
Max File Size: 200MB
```

### Feature Branch (v1.2.0-dev)
```
Status: 🚧 In Development
Created: 2025-11-01
Features: TUS upload, Hybrid system, Resume
Max File Size: 30GB
Target Release: TBD
```

## Safety Checks

### Before Starting Development

```bash
# Verify you're on the right branch
git branch
# Should show: * feature/tus-upload-v1.2

# Verify version file
cat mod/assign/submission/cloudflarestream/version.php | grep release
# Should show: $plugin->release   = '1.2.0-dev';

# Verify main branch is safe
git checkout main
cat mod/assign/submission/cloudflarestream/version.php | grep release
# Should show: $plugin->release   = '1.1.0';

# Switch back to development
git checkout feature/tus-upload-v1.2
```

### Before Committing

```bash
# Check which branch you're on
git branch

# Check what files changed
git status

# Review changes
git diff

# Stage and commit
git add <files>
git commit -m "feat: descriptive message"
git push
```

## Merge Strategy (When v1.2 is Ready)

### Pre-Merge Checklist

- [ ] All TUS features implemented and tested
- [ ] All existing v1.1 features still work
- [ ] No breaking changes
- [ ] Documentation updated
- [ ] Tests passing
- [ ] Code reviewed
- [ ] Tested on staging environment

### Merge Commands

```bash
# Update feature branch with latest main
git checkout feature/tus-upload-v1.2
git merge main
# Resolve any conflicts
git push

# Switch to main
git checkout main
git pull

# Merge feature branch (no fast-forward to preserve history)
git merge --no-ff feature/tus-upload-v1.2 -m "Merge v1.2.0 - TUS resumable upload feature"

# Update version to stable
# Edit version.php:
# - maturity: MATURITY_STABLE
# - release: '1.2.0'
# - version: 2025110102

git add mod/assign/submission/cloudflarestream/version.php
git commit -m "chore: Release version 1.2.0"

# Tag the release
git tag -a v1.2.0 -m "Version 1.2.0 - TUS resumable upload support"

# Push everything
git push origin main
git push origin v1.2.0
```

## Rollback Procedure

If something goes wrong:

```bash
# Option 1: Switch back to stable
git checkout main
# Deploy v1.1.0

# Option 2: Reset feature branch
git checkout feature/tus-upload-v1.2
git reset --hard origin/main
git push --force

# Option 3: Create new branch from v1.1.0
git checkout -b feature/tus-upload-v1.2-retry v1.1.0
```

## Summary

**Current Setup**:
```
main branch (v1.1.0) ← Stable, production-ready
  └── feature/tus-upload-v1.2 (v1.2.0-dev) ← Development
```

**Next Steps**:
1. ✅ Execute commands above to set up branches
2. ✅ Start implementing TUS upload
3. ✅ Test thoroughly
4. ✅ Merge when ready

---

**Ready to execute!** 🚀
