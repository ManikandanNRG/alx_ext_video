# Quick GitHub Push Guide

Since you're working on Windows but deploying to EC2 Linux, here's the simplest way to push to GitHub.

## Option 1: Push from Your Windows Machine (Recommended)

You can push directly from your Windows machine where you have the code:

```bash
# 1. Initialize git repository
git init

# 2. Add all files
git add .

# 3. Create initial commit
git commit -m "Initial commit: Cloudflare Stream Plugin v1.0.0"

# 4. Create GitHub repository
# Go to https://github.com/new
# Repository name: moodle-assignsubmission_cloudflarestream
# Don't initialize with README, .gitignore, or license
# Click "Create repository"

# 5. Add remote (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream.git

# 6. Push to GitHub
git branch -M main
git push -u origin main

# 7. Create version tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial Release"
git push origin v1.0.0
```

## Option 2: Push from EC2 Server

If you prefer to push from your EC2 server:

```bash
# 1. SSH to your EC2 server
ssh ubuntu@YOUR_EC2_IP

# 2. Navigate to your project directory
cd /path/to/your/project

# 3. Run the automated script
chmod +x push_to_github.sh
./push_to_github.sh
```

## After Pushing

1. Go to your GitHub repository
2. Click "Releases" → "Create a new release"
3. Choose tag: `v1.0.0`
4. Title: `v1.0.0 - Initial Release`
5. Copy description from `RELEASE_v1.0.0.md`
6. Publish release

## Update Links

After creating the repository, update these files to replace `YOUR_USERNAME`:
- `README.md`
- `CONTRIBUTING.md`
- `GITHUB_SETUP.md`
- `RELEASE_v1.0.0.md`

```bash
# Quick find and replace (do this before pushing, or after as a second commit)
# Replace YOUR_USERNAME with your actual GitHub username in all markdown files
```

That's it! Your plugin will be on GitHub.

## Repository URL
`https://github.com/YOUR_USERNAME/moodle-assignsubmission_cloudflarestream`

---

**Note**: The `.gitignore` file will automatically exclude test files, so you don't need to manually delete them.
