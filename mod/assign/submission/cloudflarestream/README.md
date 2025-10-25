# Cloudflare Stream Assignment Submission Plugin

This Moodle plugin enables students to upload large video files (up to 5 GB) as assignment submissions using Cloudflare Stream for storage and delivery.

## Features

- Direct browser-to-Cloudflare uploads (no server storage required)
- Resumable uploads for large files with automatic retry
- Secure video playback with signed tokens
- Automatic video cleanup based on retention policy
- Full integration with Moodle's assignment grading workflow
- Comprehensive error logging and monitoring
- Admin dashboard with upload statistics and failure tracking
- GDPR compliant with data export and deletion support

## Requirements

- Moodle 3.9 or higher (LTS version recommended)
- PHP 7.4 or higher
- HTTPS enabled (required for secure token transmission)
- Modern web browser with JavaScript enabled
- Cloudflare Stream account with API access
- Sufficient Cloudflare Stream quota for expected usage

## Installation for EC2 Ubuntu Server

### ⚠️ IMPORTANT: ZIP Upload Does NOT Work

**Assignment submission plugins cannot be installed via ZIP upload in Moodle/IOMAD.**

If you try ZIP upload, you'll get:
```
[Error] Unknown plugin type [assignsubmission]
```

This is normal Moodle behavior for subplugins. Use the deployment method below.

---

### Deployment Method 1: Automated Script (Recommended)

From your local machine, run:

```bash
chmod +x deploy_to_ec2.sh
./deploy_to_ec2.sh
```

The script will:
- Package the plugin
- Upload to your EC2 server via SCP
- Extract and set correct permissions
- Guide you through completion

---

### Deployment Method 2: Manual Commands

**On your local machine:**
```bash
cd mod/assign/submission
tar -czf cloudflarestream.tar.gz cloudflarestream/
scp cloudflarestream.tar.gz ubuntu@YOUR_EC2_IP:/tmp/
```

**On your EC2 server:**
```bash
ssh ubuntu@YOUR_EC2_IP
cd /var/www/html/moodle/mod/assign/submission/
sudo tar -xzf /tmp/cloudflarestream.tar.gz
sudo chown -R www-data:www-data cloudflarestream/
sudo chmod -R 755 cloudflarestream/
sudo rm /tmp/cloudflarestream.tar.gz
```

---

### Complete Installation in Moodle

After deploying files:

1. Open your web browser
2. Go to your Moodle URL
3. Log in as **administrator**
4. Navigate to: **Site Administration → Notifications**
5. Moodle will detect the new plugin
6. Click: **"Upgrade Moodle database now"**
7. Follow the prompts

---

### Verify Installation

1. Go to: **Site Administration → Plugins → Activity modules → Assignment → Submission plugins**
2. Find **"Cloudflare Stream"** in the list
3. Ensure it shows as **"Enabled"**
4. If disabled, click the eye icon to enable it

---

### Troubleshooting

**Plugin not detected?**
```bash
# Clear Moodle cache
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php
```

**Permission errors?**
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/html/moodle/mod/assign/submission/cloudflarestream/
sudo chmod -R 755 /var/www/html/moodle/mod/assign/submission/cloudflarestream/
```

**Need detailed help?**
- See **EC2_DEPLOYMENT.txt** for quick reference
- See **DEPLOY_TO_EC2.md** for complete guide with troubleshooting

## Configuration

### Step 1: Set Up Cloudflare Stream Account

Before configuring the plugin, you need a Cloudflare Stream account:

1. **Create Cloudflare Account**: Visit [cloudflare.com](https://cloudflare.com) and create an account if you don't have one
2. **Enable Stream**: In your Cloudflare dashboard, navigate to **Stream** and enable the service
3. **Note Your Account ID**: Found in the right sidebar of your Cloudflare dashboard or in the URL (e.g., `dash.cloudflare.com/abc123def456`)

### Step 2: Generate API Token

1. In your Cloudflare dashboard, go to **My Profile > API Tokens**
2. Click **Create Token**
3. Use the **Custom token** template
4. Configure the token with these permissions:
   - **Account**: `Cloudflare Stream:Edit`
   - **Zone Resources**: `Include All zones` (or specific zones if preferred)
5. Click **Continue to summary** and then **Create Token**
6. **Important**: Copy the token immediately - it won't be shown again

### Step 3: Configure Plugin Settings

1. Navigate to **Site Administration > Plugins > Assignment > Submission plugins > Cloudflare Stream**
2. Configure the following required settings:

#### Required Settings

- **Cloudflare API Token**: Paste the API token you generated above
- **Cloudflare Account ID**: Enter your account ID from the Cloudflare dashboard

#### Optional Settings

- **Video Retention Period**: Number of days to keep videos before automatic deletion (default: 90 days)
- **Maximum File Size**: Maximum video file size allowed in bytes (default: 5,368,709,120 = 5 GB)
- **Enable Plugin**: Check to enable the plugin globally
- **Default Setting**: Whether the plugin is enabled by default for new assignments

3. Click **Save changes**

### Step 4: Test Configuration

1. Create a test assignment and enable the Cloudflare Stream submission type
2. Try uploading a small video file to verify the integration works
3. Check the admin dashboard for upload statistics and any errors

## User Guides

### For Students: How to Upload Video Assignments

#### Accessing Your Assignment

1. **Log into Moodle** and navigate to your course
2. **Find your assignment** in the course content or activities list
3. **Click on the assignment title** to open the assignment page
4. **Read the assignment instructions** carefully to understand requirements

#### Uploading Your Video

1. **Click "Add submission"** or "Edit submission" if you've already started
2. **Locate the video upload section** - you'll see a drag-and-drop area labeled "Upload Video"
3. **Select your video file** using one of these methods:
   - **Drag and drop**: Drag your video file directly onto the upload area
   - **Click to browse**: Click the upload area and select your file from the file browser
4. **Wait for validation**: The system will check your file size and format
   - Maximum file size: 5 GB
   - Supported formats: MP4, MOV, AVI, WMV, and other common video formats

#### During Upload

1. **Monitor progress**: A progress bar will show upload percentage and estimated time remaining
2. **Stay on the page**: Don't navigate away or close the browser during upload
3. **If upload fails**: 
   - The system will automatically retry failed uploads
   - You can manually retry by clicking the "Retry" button
   - Check your internet connection if problems persist

#### After Upload

1. **Verify upload success**: You'll see a green checkmark and "Upload complete" message
2. **Preview your video**: A player will appear showing your uploaded video
3. **Add other submission content**: Complete any other required fields (text, files, etc.)
4. **Submit your assignment**: Click "Save changes" or "Submit assignment"
5. **Confirmation**: You'll receive confirmation that your submission was saved

#### Troubleshooting Common Issues

**File Too Large**
- Maximum size is 5 GB
- Compress your video using video editing software if needed
- Consider reducing video quality or duration

**Upload Keeps Failing**
- Check your internet connection stability
- Try uploading during off-peak hours
- Contact your instructor if problems persist

**Video Won't Play**
- Ensure your browser supports HTML5 video
- Try refreshing the page
- Contact technical support if the issue continues

**Can't See Upload Option**
- Verify the assignment allows video submissions
- Check that you're within the submission deadline
- Ensure you have the required permissions

### For Teachers: How to View and Grade Video Submissions

#### Accessing Student Submissions

1. **Navigate to your course** and find the assignment
2. **Click on the assignment title** to open the assignment page
3. **Click "View all submissions"** to see the grading interface
4. **Select a student** by clicking on their name or "Grade" button

#### Viewing Video Submissions

1. **Automatic loading**: The video player will load automatically when you open a submission
2. **Player controls**: Use standard video controls (play, pause, seek, volume, fullscreen)
3. **Video information**: File size, duration, and upload date are displayed below the player
4. **Multiple attempts**: If students submitted multiple times, use the attempt selector to view different versions

#### Grading Video Submissions

1. **Watch the video**: Review the student's submission thoroughly
2. **Use grading tools**: All standard Moodle grading features are available:
   - **Grade scale**: Select from your configured grade scale
   - **Rubrics**: Use rubrics if configured for the assignment
   - **Quick grading**: Enter grades directly in the submissions table
3. **Provide feedback**:
   - **Written feedback**: Add comments in the feedback text area
   - **Audio feedback**: Record audio feedback using Moodle's audio tools
   - **Annotated feedback**: Upload annotated documents or images
4. **Save your grade**: Click "Save changes" to record the grade and feedback

#### Advanced Grading Features

**Viewing Multiple Submissions**
- Use the navigation arrows to move between students
- The "Next" button automatically saves your current grade

**Batch Operations**
- Download grades for offline processing
- Upload grades from spreadsheet
- Send feedback notifications to multiple students

**Video-Specific Features**
- **Playback speed**: Adjust playback speed for efficient review
- **Timestamp comments**: Reference specific moments in the video in your feedback
- **Video analytics**: View basic playback statistics (if enabled)

#### Troubleshooting for Teachers

**Video Won't Load**
- Refresh the page and try again
- Check that the student's upload completed successfully
- Verify your internet connection

**Player Issues**
- Ensure your browser is up to date
- Try a different browser if problems persist
- Contact technical support for persistent issues

**Grading Interface Problems**
- Clear your browser cache and cookies
- Ensure JavaScript is enabled
- Try accessing from a different device

**Student Can't Upload**
- Verify the assignment settings allow video submissions
- Check submission deadlines and availability dates
- Confirm the student has the necessary permissions

### For Administrators

#### Monitoring Dashboard

Access the monitoring dashboard at: Site Administration > Plugins > Assignment > Submission plugins > Cloudflare Stream Dashboard

The dashboard provides:
- **Upload Statistics**: Total uploads, success rate, failure rate
- **Storage Metrics**: Total storage used and video duration
- **Cost Estimates**: Estimated monthly Cloudflare costs
- **Error Breakdown**: Analysis of error types and frequencies
- **Recent Failures**: Detailed list of recent upload failures for troubleshooting

#### Error Logging

The plugin automatically logs:
- Upload success events with file size and duration
- Upload failure events with detailed error messages
- Upload retry attempts
- API errors with full context
- Playback access events for audit trails

All logs are stored in the `mdl_assignsubmission_cfstream_log` table and can be queried for analysis.

## Developer Documentation

### Plugin Architecture

This plugin follows Moodle's assignment submission plugin architecture and implements the following key components:

#### Core Plugin Class (`lib.php`)
The main plugin class `assign_submission_cloudflarestream` extends `assign_submission_plugin` and provides:
- **Form integration**: Adds upload interface to assignment submission forms
- **Submission handling**: Processes video submissions and stores metadata
- **Display logic**: Renders video players in submission views
- **Settings management**: Handles plugin configuration

#### API Integration (`classes/api/cloudflare_client.php`)
Handles all communication with Cloudflare Stream API:
- **Direct upload URLs**: Generates presigned URLs for browser-to-Cloudflare uploads
- **Video management**: Retrieves video details, generates playback tokens, deletes videos
- **Error handling**: Comprehensive error handling with retry logic
- **Authentication**: Secure API token management

#### Frontend Components
- **Upload handler** (`amd/src/uploader.js`): Manages file uploads with progress tracking
- **Player integration** (`amd/src/player.js`): Embeds Cloudflare Stream player
- **Templates**: Mustache templates for UI components

#### Database Schema
- **Main table**: `mdl_assignsubmission_cfstream` stores video metadata
- **Logging table**: `mdl_assignsubmission_cfstream_log` for audit trails
- **Settings**: Encrypted storage of API credentials

### API Documentation

#### Cloudflare Client Class

```php
class cloudflare_client {
    /**
     * Constructor
     * @param string $api_token Cloudflare API token
     * @param string $account_id Cloudflare account ID
     */
    public function __construct($api_token, $account_id);

    /**
     * Get direct upload URL for browser-based uploads
     * @param int $max_duration_seconds Maximum video duration (default: 21600)
     * @return array Contains 'uploadURL' and 'uid'
     * @throws cloudflare_api_exception
     */
    public function get_direct_upload_url($max_duration_seconds = 21600);

    /**
     * Get video details from Cloudflare
     * @param string $video_uid Video unique identifier
     * @return array Video metadata including status, duration, size
     * @throws cloudflare_api_exception
     */
    public function get_video_details($video_uid);

    /**
     * Delete video from Cloudflare Stream
     * @param string $video_uid Video unique identifier
     * @return bool Success status
     * @throws cloudflare_api_exception
     */
    public function delete_video($video_uid);

    /**
     * Generate signed playback token
     * @param string $video_uid Video unique identifier
     * @param int $expiry_seconds Token validity period (default: 86400)
     * @return string JWT token for video playback
     * @throws cloudflare_api_exception
     */
    public function generate_signed_token($video_uid, $expiry_seconds = 86400);
}
```

#### Core Plugin Methods

```php
class assign_submission_cloudflarestream extends assign_submission_plugin {
    /**
     * Get plugin name
     * @return string Plugin name for display
     */
    public function get_name();

    /**
     * Add form elements for video upload
     * @param mixed $submission Current submission data
     * @param MoodleQuickForm $mform Form object
     * @param stdClass $data Form data
     * @return bool Success status
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data);

    /**
     * Save submission data
     * @param stdClass $submission Submission object
     * @param stdClass $data Form data
     * @return bool Success status
     */
    public function save(stdClass $submission, stdClass $data);

    /**
     * Display submission for grading
     * @param stdClass $submission Submission object
     * @return string HTML output
     */
    public function view(stdClass $submission);

    /**
     * Check if plugin is enabled for assignment
     * @return bool Enabled status
     */
    public function is_enabled();

    /**
     * Get plugin settings for assignment
     * @param MoodleQuickForm $mform Settings form
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform);
}
```

### Database Schema and Relationships

#### Main Table: `mdl_assignsubmission_cfstream`

```sql
CREATE TABLE mdl_assignsubmission_cfstream (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    assignment BIGINT NOT NULL,           -- FK to mdl_assign.id
    submission BIGINT NOT NULL,           -- FK to mdl_assign_submission.id
    video_uid VARCHAR(255) NOT NULL,      -- Cloudflare Stream video UID
    upload_status VARCHAR(50) NOT NULL,   -- 'pending', 'uploading', 'ready', 'error', 'deleted'
    file_size BIGINT,                     -- Original file size in bytes
    duration INT,                         -- Video duration in seconds
    upload_timestamp BIGINT NOT NULL,     -- Unix timestamp of upload
    deleted_timestamp BIGINT,             -- Unix timestamp when deleted (NULL if not deleted)
    error_message TEXT,                   -- Error details if upload failed
    
    UNIQUE KEY uk_submission (submission),
    INDEX idx_video_uid (video_uid),
    INDEX idx_upload_timestamp (upload_timestamp),
    INDEX idx_status (upload_status)
);
```

#### Logging Table: `mdl_assignsubmission_cfstream_log`

```sql
CREATE TABLE mdl_assignsubmission_cfstream_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    video_uid VARCHAR(255),               -- Related video UID (NULL for general events)
    user_id BIGINT,                       -- User who triggered the event
    event_type VARCHAR(100) NOT NULL,     -- Event type (upload_start, upload_complete, etc.)
    event_data TEXT,                      -- JSON data with event details
    timestamp BIGINT NOT NULL,            -- Unix timestamp
    
    INDEX idx_video_uid (video_uid),
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_timestamp (timestamp)
);
```

#### Relationships

```
mdl_assign (1) ←→ (N) mdl_assign_submission (1) ←→ (1) mdl_assignsubmission_cfstream
                                                ↓
                                        (N) mdl_assignsubmission_cfstream_log
```

### Directory Structure

```
cloudflarestream/
├── version.php              # Plugin metadata and version info
├── lib.php                  # Core plugin class implementation
├── settings.php             # Admin configuration interface
├── styles.css               # CSS styles for UI components
├── README.md                # User and developer documentation
├── lang/
│   └── en/
│       └── assignsubmission_cloudflarestream.php  # English language strings
├── db/
│   ├── access.php           # Capability definitions
│   ├── install.xml          # Database schema for installation
│   ├── upgrade.php          # Database upgrade scripts
│   ├── tasks.php            # Scheduled task definitions
│   └── caches.php           # Cache definitions
├── classes/
│   ├── api/
│   │   └── cloudflare_client.php      # Cloudflare Stream API wrapper
│   ├── logger.php           # Event and error logging system
│   ├── validator.php        # Input validation utilities
│   ├── rate_limiter.php     # Rate limiting for API calls
│   ├── retry_handler.php    # Retry logic for failed operations
│   ├── privacy/
│   │   └── provider.php               # GDPR compliance implementation
│   └── task/
│       └── cleanup_videos.php         # Scheduled video cleanup task
├── dashboard.php            # Admin monitoring dashboard
├── videomanagement.php      # Manual video management interface
├── amd/
│   └── src/
│       ├── uploader.js      # Frontend upload handling with tus.js
│       └── player.js        # Cloudflare Stream player integration
├── templates/
│   ├── upload_form.mustache # Upload interface template
│   └── player.mustache      # Video player template
├── ajax/
│   ├── get_upload_url.php   # AJAX endpoint for upload URL generation
│   ├── confirm_upload.php   # AJAX endpoint for upload confirmation
│   └── get_playback_token.php  # AJAX endpoint for playback tokens
└── tests/
    ├── cloudflare_client_test.php  # Unit tests for API client
    └── privacy_provider_test.php   # Tests for GDPR compliance
```

## Security

- API tokens are stored encrypted in the Moodle database
- Video access is restricted to authorized users only
- Signed playback tokens expire after 24 hours
- All API communication uses HTTPS

## Privacy and GDPR

This plugin implements Moodle's privacy API for GDPR compliance:
- User data can be exported on request
- User data is deleted when the user is deleted
- Videos are removed from Cloudflare when user data is deleted

## Support

For issues and feature requests, please contact your system administrator.

## License

This plugin is licensed under the GNU GPL v3 or later.

## Credits

Developed for IOMAD-based Moodle environments to support fieldwork video assignments.

### Technical Implementation Details

#### Upload Workflow

The video upload process follows this sequence:

1. **Client-side validation**: JavaScript validates file size and type
2. **Upload URL request**: AJAX call to `get_upload_url.php`
3. **Database record creation**: Creates pending record with submission metadata
4. **Direct upload**: Browser uploads directly to Cloudflare using tus protocol
5. **Upload confirmation**: AJAX call to `confirm_upload.php` with video UID
6. **Metadata update**: Fetches video details from Cloudflare and updates database

```javascript
// Upload workflow implementation
class CloudflareUploader {
    async uploadFile(file) {
        // 1. Validate file
        this.validateFile(file);
        
        // 2. Request upload URL
        const uploadData = await this.requestUploadUrl();
        
        // 3. Upload to Cloudflare
        const upload = new tus.Upload(file, {
            endpoint: uploadData.uploadURL,
            onProgress: this.updateProgress.bind(this),
            onSuccess: () => this.confirmUpload(uploadData.uid)
        });
        
        upload.start();
    }
}
```

#### Playback Workflow

Video playback uses signed tokens for security:

1. **Access verification**: Check user permissions for the submission
2. **Token generation**: Create signed JWT token via Cloudflare API
3. **Player embedding**: Embed Cloudflare Stream player with token
4. **Token refresh**: Automatically refresh expired tokens

```php
// Playback token generation
function generate_playback_token($video_uid, $user_id, $submission_id) {
    // Verify user can access this submission
    if (!can_view_submission($user_id, $submission_id)) {
        throw new moodle_exception('nopermission');
    }
    
    // Generate token with 24-hour expiry
    $client = new cloudflare_client($api_token, $account_id);
    $token = $client->generate_signed_token($video_uid, 86400);
    
    // Log access for audit
    logger::log_video_access($user_id, $video_uid, $submission_id);
    
    return $token;
}
```

#### Error Handling Strategy

The plugin implements comprehensive error handling:

**Upload Errors**:
- Network failures: Automatic retry with exponential backoff
- File validation: Client-side validation with user-friendly messages
- API errors: Detailed logging with user-friendly error display
- Quota exceeded: Graceful degradation with admin notification

**Playback Errors**:
- Token expiration: Automatic token refresh
- Video not found: Clear error message with support contact
- Permission denied: Appropriate access denied message
- Network issues: Retry mechanism with manual retry option

```php
// Error handling example
try {
    $upload_url = $this->cloudflare_client->get_direct_upload_url();
} catch (cloudflare_quota_exceeded_exception $e) {
    // Log for admin attention
    logger::log_error('quota_exceeded', $e->getMessage());
    
    // Display user-friendly message
    throw new moodle_exception(
        'quota_exceeded',
        'assignsubmission_cloudflarestream',
        '',
        null,
        'Contact your administrator - video storage quota exceeded'
    );
} catch (cloudflare_api_exception $e) {
    // Log technical details
    logger::log_error('api_error', $e->getMessage(), $e->getContext());
    
    // Display generic error to user
    throw new moodle_exception(
        'upload_failed',
        'assignsubmission_cloudflarestream'
    );
}
```

#### Security Implementation

**API Token Security**:
- Tokens stored encrypted using Moodle's encryption API
- Never exposed in client-side code or browser requests
- Scoped to minimum required permissions

**Access Control**:
- All video access requires valid Moodle session
- Permission checks verify user can access specific submissions
- Signed playback tokens include user context and expiration

**Input Validation**:
- All user inputs sanitized and validated
- File uploads restricted by size and type
- Video UIDs validated against database records

```php
// Access control implementation
function verify_video_access($user_id, $submission_id, $video_uid) {
    global $DB;
    
    // Get submission details
    $submission = $DB->get_record('assign_submission', ['id' => $submission_id]);
    if (!$submission) {
        throw new moodle_exception('invalidsubmission');
    }
    
    // Verify video belongs to this submission
    $video_record = $DB->get_record('assignsubmission_cfstream', [
        'submission' => $submission_id,
        'video_uid' => $video_uid
    ]);
    if (!$video_record) {
        throw new moodle_exception('invalidvideo');
    }
    
    // Check user permissions
    $context = context_module::instance($submission->assignment);
    
    if ($submission->userid == $user_id) {
        // Student viewing own submission
        require_capability('mod/assign:submit', $context);
    } else {
        // Teacher/admin viewing student submission
        require_capability('mod/assign:grade', $context);
    }
    
    return true;
}
```

#### Performance Optimizations

**Database Queries**:
- Indexed columns for fast lookups
- Efficient joins to minimize query count
- Prepared statements for security and performance

**Caching**:
- Video metadata cached to reduce API calls
- Playback tokens cached with appropriate TTL
- Configuration settings cached

**Frontend Performance**:
- Lazy loading of video players
- Progressive enhancement for upload interface
- Efficient progress tracking without excessive updates

#### GDPR Compliance

The plugin implements Moodle's privacy API:

```php
class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    
    /**
     * Export user data for GDPR requests
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // Export video metadata and access logs
        // Videos remain in Cloudflare but metadata is exported
    }
    
    /**
     * Delete user data for GDPR compliance
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Delete videos from Cloudflare
        // Remove database records
        // Clean up access logs
    }
}
```

### Extending the Plugin

#### Adding New Features

To extend the plugin functionality:

1. **New API endpoints**: Add files to `ajax/` directory
2. **Frontend components**: Add AMD modules to `amd/src/`
3. **Database changes**: Update `db/upgrade.php` and increment version
4. **New capabilities**: Define in `db/access.php`
5. **Language strings**: Add to language files

#### Custom Event Handlers

The plugin triggers custom events that can be handled by other plugins:

```php
// Trigger custom event after successful upload
$event = \assignsubmission_cloudflarestream\event\video_uploaded::create([
    'context' => $context,
    'objectid' => $submission->id,
    'other' => [
        'video_uid' => $video_uid,
        'file_size' => $file_size,
        'duration' => $duration
    ]
]);
$event->trigger();
```

#### Integration Points

The plugin provides hooks for integration with other systems:

- **Webhook support**: Can be extended to handle Cloudflare webhooks
- **Analytics integration**: Events can be captured by analytics plugins
- **Backup integration**: Video metadata included in Moodle backups
- **Mobile app support**: AJAX endpoints compatible with mobile app

### Testing and Quality Assurance

#### Unit Tests

Run the included unit tests:

```bash
# From Moodle root directory
vendor/bin/phpunit mod/assign/submission/cloudflarestream/tests/
```

#### Integration Testing

Test the complete workflow:

1. **Upload test**: Create assignment, upload video, verify database record
2. **Playback test**: Generate token, embed player, verify video loads
3. **Permission test**: Verify access control works correctly
4. **Cleanup test**: Run scheduled task, verify old videos deleted

#### Performance Testing

Monitor these metrics:

- **Upload success rate**: Should be >95% under normal conditions
- **API response time**: Cloudflare API calls should complete <2 seconds
- **Database query performance**: Monitor slow query log
- **Frontend performance**: Upload progress should update smoothly

### Troubleshooting

#### Common Issues

**Upload failures**:
- Check Cloudflare API token permissions
- Verify account quota not exceeded
- Check network connectivity from server
- Review error logs for specific failures

**Playback issues**:
- Verify video exists in Cloudflare
- Check token generation and expiration
- Ensure user has proper permissions
- Test with different browsers

**Performance problems**:
- Monitor database query performance
- Check Cloudflare API response times
- Verify adequate server resources
- Review error logs for bottlenecks

#### Debug Mode

Enable debug logging by adding to config.php:

```php
$CFG->assignsubmission_cloudflarestream_debug = true;
```

This enables detailed logging of all API calls and internal operations.

### Maintenance

#### Regular Tasks

- **Monitor upload success rates** via admin dashboard
- **Review error logs** for recurring issues
- **Check Cloudflare usage** against quotas and billing
- **Update API tokens** before expiration
- **Review retention policy** and adjust as needed

#### Updates

When updating the plugin:

1. **Backup database** before applying updates
2. **Test in staging** environment first
3. **Review upgrade scripts** in `db/upgrade.php`
4. **Monitor logs** after deployment
5. **Verify functionality** with test uploads
