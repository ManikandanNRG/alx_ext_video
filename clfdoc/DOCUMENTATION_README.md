# Moodle Video Submission Plugins - Documentation

## Available Documentation

This folder contains comprehensive documentation for the Moodle Video Submission Plugins.

### 1. COMPLETE_INSTALLATION_GUIDE.md

**Purpose:** Step-by-step installation and configuration guide

**Contents:**
- Overview of both plugins
- Plugin information and workflows
- Prerequisites
- AWS S3 + CloudFront configuration (detailed)
- Cloudflare Stream configuration (detailed)
- Complete installation steps
- Post-installation configuration
- Testing and verification
- Troubleshooting

**Audience:** System administrators, DevOps engineers

**Use this for:** Installing and configuring the plugins from scratch

---

### 2. PLUGIN_TECHNICAL_DOCUMENTATION.md

**Purpose:** Technical reference and architecture documentation

**Contents:**
- Architecture overview
- Component diagrams
- Workflow diagrams
- Database schemas
- API reference
- Security features
- Performance optimization
- Core features explained
- Maintenance procedures
- File structure reference

**Audience:** Developers, technical team members

**Use this for:** Understanding how the plugins work internally

---

## Converting to PDF

### Method 1: Using Pandoc (Recommended)

```bash
# Install Pandoc
sudo apt-get install pandoc texlive-latex-base texlive-fonts-recommended

# Convert to PDF
pandoc COMPLETE_INSTALLATION_GUIDE.md -o COMPLETE_INSTALLATION_GUIDE.pdf --pdf-engine=pdflatex
pandoc PLUGIN_TECHNICAL_DOCUMENTATION.md -o PLUGIN_TECHNICAL_DOCUMENTATION.pdf --pdf-engine=pdflatex
```

### Method 2: Using Online Converters

1. Visit: https://www.markdowntopdf.com/
2. Upload the MD file
3. Download the PDF

### Method 3: Using VS Code

1. Install extension: "Markdown PDF"
2. Open MD file
3. Right-click → "Markdown PDF: Export (pdf)"

---

## Quick Reference

### Plugin Comparison

| Feature | S3 Video | Cloudflare Stream |
|---------|----------|-------------------|
| Storage | Amazon S3 | Cloudflare Stream |
| Delivery | CloudFront CDN | Cloudflare CDN |
| Transcoding | Manual | Automatic |
| Security | Signed URLs | Domain restrictions |
| Setup Complexity | Medium | Easy |
| Cost | Pay per GB | Pay per minute |

### Installation Time Estimates

- **S3 Plugin:** 2-3 hours (including AWS setup)
- **Cloudflare Plugin:** 1-2 hours (including Cloudflare setup)

### Key Files to Upload

**S3 Plugin (8 files):**
1. Complete plugin folder
2. CloudFront private key

**Cloudflare Plugin (8 files):**
1. view_video.php
2. grading_injector.js
3. grading_injector.min.js
4. confirm_upload.php
5. cleanup_videos.php
6. styles.css
7. lib.php
8. assignsubmission_cloudflarestream.php

---

## Support

For questions or issues:
1. Check the Troubleshooting section in COMPLETE_INSTALLATION_GUIDE.md
2. Review the Technical Documentation
3. Check Moodle logs
4. Contact your development team

---

## Document Versions

- **Installation Guide:** v1.0 (October 30, 2025)
- **Technical Documentation:** v1.0 (October 30, 2025)

---

## License

These plugins are licensed under GPL v3 or later, compatible with Moodle.
