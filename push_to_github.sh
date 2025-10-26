#!/bin/bash

# Cloudflare Stream Plugin - GitHub Push Script
# This script helps you push the plugin to GitHub

echo "========================================="
echo "Cloudflare Stream Plugin - GitHub Setup"
echo "========================================="
echo ""

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "❌ Error: Git is not installed"
    echo "Please install git first: https://git-scm.com/downloads"
    exit 1
fi

echo "✅ Git is installed"
echo ""

# Get GitHub username
read -p "Enter your GitHub username: " GITHUB_USERNAME

if [ -z "$GITHUB_USERNAME" ]; then
    echo "❌ Error: GitHub username is required"
    exit 1
fi

REPO_NAME="moodle-assignsubmission_cloudflarestream"
REPO_URL="https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"

echo ""
echo "Repository will be created at:"
echo "$REPO_URL"
echo ""

# Confirm
read -p "Continue? (y/n): " CONFIRM

if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "Step 1: Initializing Git repository..."

# Initialize git if not already done
if [ ! -d ".git" ]; then
    git init
    echo "✅ Git repository initialized"
else
    echo "✅ Git repository already exists"
fi

echo ""
echo "Step 2: Adding files..."

# Add all files
git add .

echo "✅ Files added"

echo ""
echo "Step 3: Creating initial commit..."

# Create initial commit
git commit -m "Initial commit: Cloudflare Stream Plugin v1.0.0

- Complete Moodle assignment submission plugin
- Direct browser-to-Cloudflare video uploads (up to 5GB)
- Secure playback with signed tokens
- Admin dashboard and video management
- GDPR compliant with data export/deletion
- Rate limiting and security hardening
- Comprehensive error handling and logging
- Full documentation and tests included"

echo "✅ Initial commit created"

echo ""
echo "Step 4: Adding remote repository..."

# Check if remote already exists
if git remote | grep -q "origin"; then
    echo "⚠️  Remote 'origin' already exists"
    read -p "Remove existing remote and add new one? (y/n): " REMOVE_REMOTE
    if [ "$REMOVE_REMOTE" = "y" ] || [ "$REMOVE_REMOTE" = "Y" ]; then
        git remote remove origin
        git remote add origin "$REPO_URL"
        echo "✅ Remote updated"
    fi
else
    git remote add origin "$REPO_URL"
    echo "✅ Remote added"
fi

echo ""
echo "Step 5: Checking branch name..."

# Get current branch name
CURRENT_BRANCH=$(git branch --show-current)

if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "Current branch: $CURRENT_BRANCH"
    read -p "Rename to 'main'? (y/n): " RENAME_BRANCH
    if [ "$RENAME_BRANCH" = "y" ] || [ "$RENAME_BRANCH" = "Y" ]; then
        git branch -M main
        echo "✅ Branch renamed to 'main'"
    fi
fi

echo ""
echo "========================================="
echo "IMPORTANT: Before pushing to GitHub"
echo "========================================="
echo ""
echo "1. Go to https://github.com/new"
echo "2. Repository name: $REPO_NAME"
echo "3. Description: Moodle plugin for large video submissions using Cloudflare Stream"
echo "4. Choose Public or Private"
echo "5. Do NOT initialize with README, .gitignore, or license"
echo "6. Click 'Create repository'"
echo ""
read -p "Have you created the repository on GitHub? (y/n): " REPO_CREATED

if [ "$REPO_CREATED" != "y" ] && [ "$REPO_CREATED" != "Y" ]; then
    echo ""
    echo "Please create the repository on GitHub first, then run this script again."
    echo "Or manually run: git push -u origin main"
    exit 0
fi

echo ""
echo "Step 6: Pushing to GitHub..."

# Push to GitHub
git push -u origin main

if [ $? -eq 0 ]; then
    echo "✅ Successfully pushed to GitHub!"
else
    echo "❌ Error pushing to GitHub"
    echo "You may need to authenticate or check your repository settings"
    exit 1
fi

echo ""
echo "Step 7: Creating version tag..."

# Create and push tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial Release

Features:
- Direct browser-to-Cloudflare uploads
- Secure video playback
- Admin dashboard
- GDPR compliance
- Full documentation"

git push origin v1.0.0

if [ $? -eq 0 ]; then
    echo "✅ Version tag v1.0.0 created and pushed"
else
    echo "⚠️  Error creating tag (this is optional)"
fi

echo ""
echo "========================================="
echo "✅ SUCCESS!"
echo "========================================="
echo ""
echo "Your plugin is now on GitHub:"
echo "$REPO_URL"
echo ""
echo "Next steps:"
echo "1. Go to your repository on GitHub"
echo "2. Create a release from tag v1.0.0"
echo "3. Add topics: moodle, moodle-plugin, cloudflare-stream, video-upload"
echo "4. Update README.md to replace YOUR_USERNAME with $GITHUB_USERNAME"
echo ""
echo "See GITHUB_SETUP.md for more details"
echo ""
