# Changelog

All notable changes to the Cloudflare Stream Moodle Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-26

### Added
- Initial release of Cloudflare Stream integration for Moodle assignments
- Direct browser-to-Cloudflare video uploads (up to 5 GB)
- Secure video playback with signed tokens (24-hour expiration)
- Admin dashboard for monitoring upload statistics and errors
- Video management interface for manual video deletion
- Automatic video cleanup task (configurable retention period)
- GDPR compliance with data export and deletion support
- Rate limiting to prevent abuse (configurable limits)
- Comprehensive error handling and logging
- Input validation and security hardening
- Support for resumable uploads using tus protocol
- Real-time upload progress tracking
- Embedded Cloudflare Stream player in grading interface
- Multi-language support (English included)
- Database schema with proper indexing
- Unit and integration tests
- Privacy provider for GDPR compliance
- Scheduled cleanup task for old videos
- Admin settings page for API configuration
- Support for video formats: MP4, MOV, AVI, MKV, WebM

### Security
- API tokens stored encrypted in database
- Signed playback tokens with expiration
- Role-based access control (students, teachers, admins)
- Rate limiting on upload and playback requests
- Input validation on all user inputs
- SQL injection prevention
- XSS protection in templates
- CSRF token validation

### Documentation
- Complete README with installation instructions
- Deployment guide for EC2/Ubuntu servers
- API documentation
- User guides for students, teachers, and administrators
- Security audit report
- GDPR verification documentation
- Integration test documentation

### Technical Details
- Compatible with Moodle 3.9+
- PHP 7.4+ required
- Requires Cloudflare Stream account
- Database tables: assignsubmission_cfstream, assignsubmission_cfs_log
- Implements Moodle assignment submission plugin API
- Uses Moodle's standard caching system
- Follows Moodle coding standards

### Known Limitations
- Requires Cloudflare Stream subscription ($5/month minimum)
- Video processing time depends on file size and Cloudflare's processing queue
- Maximum file size limited by PHP upload_max_filesize and Cloudflare limits
- Playback tokens expire after 24 hours (configurable in future versions)

### Notes
- This is the initial release and has not been tested with live Cloudflare Stream API
- Full end-to-end testing requires active Cloudflare Stream account
- Plugin structure and code are production-ready
- Recommended to test in staging environment before production deployment

---

## [Unreleased]

### Planned for Future Releases
- Support for video transcoding variants (360p, 720p, 1080p)
- Automatic thumbnail generation
- Video analytics integration
- Batch video operations
- AI-powered video summarization
- Closed captions/subtitles support
- Video annotations for teacher feedback
- Mobile app integration
- Webhook support for video processing events
- Advanced analytics dashboard
- Export video metadata to CSV
- Bulk video deletion
- Video watermarking
- Custom player themes
