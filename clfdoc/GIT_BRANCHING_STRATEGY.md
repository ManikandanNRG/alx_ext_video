# Git Branching Strategy - Cloudflare Stream Plugin

## Version Strategy

### Current State (v1.1 - Stable)
- **Branch**: `main`
- **Version**: 1.1.0
- **Status**: Stable, Production-Ready
- **Features**:
  - ✅ Direct upload (up to 200MB)
  - ✅ Video playback
  - ✅ Cleanup system
  - ✅ Rate limiting
  - ✅ GDPR compliance
  - ✅ Grading interface

### Next Version (v1.2 - TUS Upload)
- **Branch**: `feature/tus-upload-v1.2`
- **Version**: 1.2.0
- **Status**: Development
- **New Features**:
  - ✅ TUS resumable upload (up to 30GB)
  - ✅ Hybrid upload system (direct <200MB, TUS >200MB)
  - ✅ Resume capability
  - ✅ Improved progress tracking
  - ✅ All v1.1 features maintained

## Branch Structure

```
main (v1.1.0 - Stable)
  │
  ├─── feature/tus-upload-v1.2 (Development)
  │     │
  │     ├─── feature/tus-backend (Backend implementation)
  │     ├─── feature/tus-frontend (Frontend implementation)
  │     └─── feature/tus-testing (Testing & optimization)
  │
  └─── hotfix/v1.1.x (Emergency fixes for v1.1)
```

## Git Workflow

### Step 1: Tag Current Stable Version

```bash
# Ensure you're on main branch
git checkout main

# Create v1.1.0 tag
git tag -a v1.1.0 -m "Version 1.1.0 - Stable direct upload implementation"

# Push tag to remote
git push origin v1.1.0

# List tags to verify
git tag -l
```

### Step 2: Create Feature Branch for v1.2

```bash
# Create and checkout new branch from main
git checkout -b feature/tus-upload-v1.2

# Verify you're on the new branch
git branch

# Push branch to remote
git push -u origin feature/tus-upload-v1.2
```

### Step 3: Update Version in Feature Branch

```bash
# Edit version.php to reflect v1.2.0-dev
# (We'll do this in the next step)

# Commit version change
git add mod/assign/submission/cloudflarestream/version.php
git commit -m "Bump version to 1.2.0-dev for TUS upload feature"
git push
```

### Step 4: Development Workflow

```bash
# Always work on feature branch
git checkout feature/tus-upload-v1.2

# Make changes
# ... implement TUS upload ...

# Commit frequently with clear messages
git add .
git commit -m "feat: Add TUS session creation to cloudflare_client.php"
git push

# Continue development
git add .
git commit -m "feat: Add TUS chunk upload method"
git push
```

### Step 5: Testing Phase

```bash
# Create testing sub-branch if needed
git checkout -b feature/tus-testing feature/tus-upload-v1.2

# Run tests, fix bugs
git add .
git commit -m "test: Add TUS upload integration tests"
git push

# Merge back to main feature branch
git checkout feature/tus-upload-v1.2
git merge feature/tus-testing
git push
```

### Step 6: Merge to Main (When Ready)

```bash
# Ensure feature branch is up to date
git checkout feature/tus-upload-v1.2
git pull

# Switch to main
git checkout main
git pull

# Merge feature branch
git merge --no-ff feature/tus-upload-v1.2 -m "Merge v1.2.0 - TUS resumable upload feature"

# Update version to stable
# Edit version.php: change '1.2.0-dev' to '1.2.0'

git add mod/assign/submission/cloudflarestream/version.php
git commit -m "Release version 1.2.0"

# Tag the release
git tag -a v1.2.0 -m "Version 1.2.0 - TUS resumable upload support"

# Push everything
git push origin main
git push origin v1.2.0
```

## Hotfix Workflow (If Needed)

If a critical bug is found in v1.1 while developing v1.2:

```bash
# Create hotfix branch from v1.1.0 tag
git checkout -b hotfix/v1.1.1 v1.1.0

# Fix the bug
# ... make changes ...

git add .
git commit -m "fix: Critical bug in direct upload"

# Update version to 1.1.1
# Edit version.php

git add mod/assign/submission/cloudflarestream/version.php
git commit -m "Bump version to 1.1.1"

# Merge to main
git checkout main
git merge --no-ff hotfix/v1.1.1
git tag -a v1.1.1 -m "Version 1.1.1 - Hotfix"
git push origin main
git push origin v1.1.1

# Also merge to feature branch to include fix
git checkout feature/tus-upload-v1.2
git merge hotfix/v1.1.1
git push

# Delete hotfix branch
git branch -d hotfix/v1.1.1
```

## Commit Message Convention

Use conventional commits for clear history:

```bash
# Features
git commit -m "feat: Add TUS upload session creation"
git commit -m "feat: Implement chunk upload with progress tracking"

# Bug fixes
git commit -m "fix: Correct UID extraction from stream-media-id header"
git commit -m "fix: Handle network interruption during upload"

# Documentation
git commit -m "docs: Add TUS implementation guide"
git commit -m "docs: Update README with v1.2 features"

# Tests
git commit -m "test: Add TUS upload integration tests"
git commit -m "test: Add chunk size validation tests"

# Refactoring
git commit -m "refactor: Extract TUS logic into separate class"

# Performance
git commit -m "perf: Optimize chunk size to 50MB"

# Chores
git commit -m "chore: Update dependencies"
git commit -m "chore: Build AMD modules"
```

## Version Numbering

### Format: MAJOR.MINOR.PATCH

- **MAJOR** (1.x.x): Breaking changes, major features
- **MINOR** (x.1.x): New features, backward compatible
- **PATCH** (x.x.1): Bug fixes, minor improvements

### Examples:
- `1.0.0`: Initial release
- `1.0.1`: Bug fix (current)
- `1.1.0`: Direct upload stable version
- `1.2.0`: TUS upload feature (new)
- `1.2.1`: Bug fix for TUS
- `2.0.0`: Major rewrite (future)

### Development Versions:
- `1.2.0-dev`: Development version
- `1.2.0-beta`: Beta testing
- `1.2.0-rc1`: Release candidate
- `1.2.0`: Stable release

## Branch Protection Rules

### Main Branch
- ✅ Require pull request reviews
- ✅ Require status checks to pass
- ✅ No direct commits (except hotfixes)
- ✅ Require linear history

### Feature Branches
- ✅ Allow direct commits
- ✅ Regular pushes encouraged
- ✅ Squash commits before merging to main

## Release Checklist

Before merging v1.2 to main:

- [ ] All TUS features implemented
- [ ] All tests passing
- [ ] Documentation updated
- [ ] Version bumped to 1.2.0
- [ ] CHANGELOG.md updated
- [ ] No breaking changes to v1.1 features
- [ ] Tested on staging environment
- [ ] Code review completed
- [ ] Performance benchmarks met

## Rollback Plan

If v1.2 has critical issues after release:

```bash
# Option 1: Revert the merge commit
git checkout main
git revert -m 1 <merge-commit-hash>
git push

# Option 2: Reset to v1.1.0 tag
git checkout main
git reset --hard v1.1.0
git push --force

# Option 3: Create hotfix from v1.1.0
git checkout -b hotfix/v1.1.2 v1.1.0
# ... fix issues ...
git checkout main
git merge hotfix/v1.1.2
git tag v1.1.2
git push
```

## Current Status

```
✅ v1.0.1 - Initial release (old)
✅ v1.1.0 - Direct upload stable (current - to be tagged)
🚧 v1.2.0 - TUS upload (in development)
```

## Next Steps

1. ✅ Tag current main as v1.1.0
2. ✅ Create feature/tus-upload-v1.2 branch
3. ✅ Update version.php to 1.2.0-dev
4. ✅ Implement TUS upload
5. ✅ Test thoroughly
6. ✅ Merge to main when ready
7. ✅ Tag as v1.2.0

---

**Document Created**: 2025-11-01  
**Status**: Ready to execute
