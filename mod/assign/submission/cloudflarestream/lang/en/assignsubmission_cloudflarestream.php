<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Cloudflare Stream video submission';
$string['cloudflarestream'] = 'Cloudflare Stream';

// Settings strings.
$string['apitoken'] = 'Cloudflare API Token';
$string['apitoken_desc'] = 'Enter your Cloudflare API token with Stream permissions. This token will be stored encrypted.';
$string['accountid'] = 'Cloudflare Account ID';
$string['accountid_desc'] = 'Enter your Cloudflare account ID. You can find this in your Cloudflare dashboard.';
$string['retention_days'] = 'Video retention period (days)';
$string['retention_days_desc'] = 'Number of days to keep videos before automatic deletion. Default is 90 days.';
$string['max_file_size'] = 'Maximum file size';
$string['max_file_size_desc'] = 'Maximum video file size that can be uploaded.';

// Upload interface strings.
$string['uploadvideofile'] = 'Upload video file';
$string['selectvideo'] = 'Select video';
$string['uploading'] = 'Uploading...';
$string['uploadprogress'] = 'Upload progress: {$a}%';
$string['uploadsuccess'] = 'Video uploaded successfully';
$string['uploadfailed'] = 'Upload failed';
$string['dragdrop'] = 'Drag and drop video file here or click to select';
$string['filesizeexceeded'] = 'File size exceeds maximum allowed size of {$a}';
$string['invalidfiletype'] = 'Invalid file type. Please upload a video file.';
$string['retryupload'] = 'Retry upload';

// Playback strings.
$string['videonotavailable'] = 'Video is no longer available';
$string['videoloading'] = 'Loading video...';
$string['playbackerror'] = 'Error loading video. Please try again later.';
$string['nopermission'] = 'You do not have permission to view this video';
$string['playback_token_error'] = 'Unable to generate playback token. Please try again later.';
$string['invalidvideouid'] = 'Invalid video identifier';
$string['nopermissiontoviewvideo'] = 'You do not have permission to view this video';

// Status strings.
$string['status_pending'] = 'Pending';
$string['status_uploading'] = 'Uploading';
$string['status_ready'] = 'Ready';
$string['status_error'] = 'Error';
$string['status_deleted'] = 'Deleted';

// Error messages.
$string['cloudflare_unavailable'] = 'Cloudflare Stream service is currently unavailable. Please try again later.';
$string['api_error'] = 'Error communicating with Cloudflare Stream API';
$string['config_missing'] = 'Cloudflare Stream is not configured. Please contact your administrator.';
$string['upload_error'] = 'An error occurred during upload. Please try again.';
$string['network_error'] = 'Network error. Please check your connection and try again.';

// API client error messages.
$string['cloudflare_auth_failed'] = 'Authentication with Cloudflare failed. Please check your API credentials.';
$string['cloudflare_video_not_found'] = 'The requested video was not found in Cloudflare Stream.';
$string['cloudflare_quota_exceeded'] = 'Cloudflare quota exceeded. Please contact your administrator.';
$string['cloudflare_api_error'] = 'Cloudflare API error: {$a}';
$string['cloudflare_invalid_response'] = 'Invalid response from Cloudflare API.';
$string['cloudflare_network_error'] = 'Network error connecting to Cloudflare: {$a}';

// Admin dashboard strings.
$string['dashboard'] = 'Cloudflare Stream Dashboard';
$string['viewdashboard'] = 'View Monitoring Dashboard';
$string['uploadstatistics'] = 'Upload Statistics';
$string['totaluploads'] = 'Total uploads';
$string['successfuluploads'] = 'Successful uploads';
$string['faileduploads'] = 'Failed uploads';
$string['successrate'] = 'Success rate';
$string['totalstorage'] = 'Total storage';
$string['totalduration'] = 'Total video duration';
$string['estimatedcost'] = 'Estimated monthly cost';
$string['recentfailures'] = 'Recent Upload Failures';
$string['norecentfailures'] = 'No recent upload failures. All uploads are working correctly!';
$string['errorbreakdown'] = 'Error Breakdown';
$string['errorcode'] = 'Error Code';
$string['errormessage'] = 'Error Message';
$string['count'] = 'Count';
$string['timestamp'] = 'Time';
$string['timeperiod'] = 'Time Period';
$string['last7days'] = 'Last 7 days';
$string['last30days'] = 'Last 30 days';
$string['last90days'] = 'Last 90 days';
$string['lastyear'] = 'Last year';
$string['update'] = 'Update';
$string['externalresources'] = 'External Resources';
$string['viewcloudflarestats'] = 'View Cloudflare Stream Analytics';
$string['unknownuser'] = 'Unknown user';
$string['unknown'] = 'Unknown';

// Privacy strings.
$string['privacy:metadata:assignsubmission_cfstream'] = 'Information about video submissions stored in Cloudflare Stream';
$string['privacy:metadata:assignsubmission_cfstream:assignment'] = 'The assignment ID';
$string['privacy:metadata:assignsubmission_cfstream:submission'] = 'The submission ID';
$string['privacy:metadata:assignsubmission_cfstream:video_uid'] = 'The unique identifier for the video in Cloudflare Stream';
$string['privacy:metadata:assignsubmission_cfstream:upload_status'] = 'The current status of the video upload';
$string['privacy:metadata:assignsubmission_cfstream:file_size'] = 'The size of the uploaded video file in bytes';
$string['privacy:metadata:assignsubmission_cfstream:duration'] = 'The duration of the video in seconds';
$string['privacy:metadata:assignsubmission_cfstream:upload_timestamp'] = 'When the video was uploaded';
$string['privacy:metadata:assignsubmission_cfstream:deleted_timestamp'] = 'When the video was deleted';
$string['privacy:metadata:assignsubmission_cfstream:error_message'] = 'Error message if upload failed';

$string['privacy:metadata:assignsubmission_cfstream_log'] = 'Log entries for video upload and playback events';
$string['privacy:metadata:assignsubmission_cfstream_log:userid'] = 'The user ID associated with the event';
$string['privacy:metadata:assignsubmission_cfstream_log:assignmentid'] = 'The assignment ID';
$string['privacy:metadata:assignsubmission_cfstream_log:submissionid'] = 'The submission ID';
$string['privacy:metadata:assignsubmission_cfstream_log:video_uid'] = 'The Cloudflare video UID';
$string['privacy:metadata:assignsubmission_cfstream_log:event_type'] = 'The type of event (upload, playback, error)';
$string['privacy:metadata:assignsubmission_cfstream_log:error_code'] = 'Error code if applicable';
$string['privacy:metadata:assignsubmission_cfstream_log:error_message'] = 'Detailed error message';
$string['privacy:metadata:assignsubmission_cfstream_log:error_context'] = 'Additional error context information';
$string['privacy:metadata:assignsubmission_cfstream_log:file_size'] = 'File size in bytes';
$string['privacy:metadata:assignsubmission_cfstream_log:duration'] = 'Video duration in seconds';
$string['privacy:metadata:assignsubmission_cfstream_log:retry_count'] = 'Number of upload retries';
$string['privacy:metadata:assignsubmission_cfstream_log:user_role'] = 'User role for playback events';
$string['privacy:metadata:assignsubmission_cfstream_log:timestamp'] = 'When the event occurred';

$string['privacy:metadata:cloudflare_stream'] = 'Video files and metadata are stored in Cloudflare Stream service';
$string['privacy:metadata:cloudflare_stream:video_content'] = 'The actual video file content uploaded by the user';
$string['privacy:metadata:cloudflare_stream:video_metadata'] = 'Video metadata such as duration, file size, and processing status';

$string['videosubmissions'] = 'Video Submissions';
$string['activitylogs'] = 'Activity Logs';

// Capability strings.
$string['cloudflarestream:use'] = 'Use Cloudflare Stream video submission';

// Default settings.
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'Cloudflare Stream video submission';
$string['enabled_help'] = 'If enabled, students can upload video files to Cloudflare Stream as their assignment submission.';

// Cleanup task strings.
$string['cleanup_videos_task'] = 'Clean up old Cloudflare Stream videos';
$string['cleanupvideos'] = 'Clean up old Cloudflare Stream videos';
$string['cleanupvideos_desc'] = 'Delete videos older than the retention period from Cloudflare Stream';

// Manual deletion interface strings.
$string['videomanagement'] = 'Video Management';
$string['viewvideomanagement'] = 'Manage Videos';
$string['manualdelete'] = 'Manual Video Deletion';
$string['videouid'] = 'Video UID';
$string['videouid_help'] = 'Enter the Cloudflare Stream video UID to delete';
$string['deleteconfirm'] = 'Are you sure you want to delete this video?';
$string['deleteconfirm_desc'] = 'This action cannot be undone. The video will be permanently removed from Cloudflare Stream.';
$string['deletevideo'] = 'Delete Video';
$string['videodeletesuccess'] = 'Video deleted successfully';
$string['videodeletefailed'] = 'Failed to delete video: {$a}';
$string['invalidvideouid'] = 'Invalid video UID format';
$string['videonotfound'] = 'Video not found in Cloudflare Stream';
$string['searchvideos'] = 'Search Videos';
$string['allvideos'] = 'All Videos';
$string['course'] = 'Course';
$string['assignment'] = 'Assignment';
$string['student'] = 'Student';
$string['uploaddate'] = 'Upload Date';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['delete'] = 'Delete';
$string['confirmdelete'] = 'Confirm Delete';
$string['cancel'] = 'Cancel';
$string['novideostoshow'] = 'No videos to display';
$string['videosperpage'] = 'Videos per page';
$string['filterby'] = 'Filter by';
$string['filterbystatus'] = 'Filter by status';
$string['filterbycourse'] = 'Filter by course';
$string['search'] = 'Search';
$string['reset'] = 'Reset';
$string['videomanagement_desc'] = 'Manage and delete individual videos from Cloudflare Stream';
$string['allcourses'] = 'All courses';
$string['allstatuses'] = 'All statuses';
// Validation error strings.
$string['invalid_file_size'] = 'Invalid file size';
$string['file_too_large'] = 'File size exceeds maximum allowed size';
$string['missing_mime_type'] = 'MIME type is required';
$string['invalid_mime_type'] = 'Unsupported file type';
$string['missing_filename'] = 'Filename is required';
$string['invalid_file_extension'] = 'Unsupported file extension';
$string['missing_video_uid'] = 'Video identifier is required';
$string['video_uid_too_long'] = 'Video identifier is too long';
$string['invalid_video_uid_format'] = 'Invalid video identifier format';
$string['invalid_duration'] = 'Invalid video duration';
$string['duration_too_long'] = 'Video duration exceeds maximum allowed duration';
$string['invalid_assignment_id'] = 'Invalid assignment identifier';
$string['invalid_submission_id'] = 'Invalid submission identifier';
$string['invalid_user_id'] = 'Invalid user identifier';
$string['invalid_upload_status'] = 'Invalid upload status';
$string['invalid_api_response'] = 'Invalid API response';
$string['api_request_failed'] = 'API request failed';
$string['invalid_video_details'] = 'Invalid video details';
$string['invalid_video_state'] = 'Invalid video state';
$string['invalid_record'] = 'Invalid database record';
$string['invalidparameters'] = 'Invalid parameters provided';

// Rate limiting strings.
$string['ratelimitsettings'] = 'Rate limiting settings';
$string['ratelimitsettings_desc'] = 'Configure rate limits to prevent abuse of the video upload and playback system.';
$string['upload_rate_limit'] = 'Upload rate limit';
$string['upload_rate_limit_desc'] = 'Maximum number of upload URL requests per user per hour. Default is 10.';
$string['playback_rate_limit'] = 'Playback rate limit';
$string['playback_rate_limit_desc'] = 'Maximum number of playback token requests per user per hour. Default is 100.';
$string['rate_limit_exceeded'] = 'Rate limit exceeded. Please try again later.';
$string['upload_rate_limit_exceeded'] = 'Upload rate limit exceeded. Please wait before requesting another upload.';
$string['playback_rate_limit_exceeded'] = 'Playback rate limit exceeded. Please wait before requesting another video.';

// Capability strings.
$string['cloudflarestream:bypassratelimit'] = 'Bypass rate limiting for Cloudflare Stream';

// Enhanced error messages with actionable guidance.
$string['error_network_connection'] = 'Network connection error. Please check your internet connection and try again.';
$string['error_server_unavailable'] = 'The video service is temporarily unavailable. Please try again in a few minutes.';
$string['error_file_corrupted'] = 'The selected file appears to be corrupted. Please try uploading a different video file.';
$string['error_quota_exceeded'] = 'Storage quota exceeded. Please contact your administrator or try again later.';
$string['error_authentication'] = 'Authentication failed. Please refresh the page and try again.';
$string['error_permission_denied'] = 'You do not have permission to perform this action. Please contact your instructor.';
$string['error_file_too_large'] = 'File size ({$a->filesize}) exceeds the maximum allowed size of {$a->maxsize}. Please compress your video or use a smaller file.';
$string['error_invalid_format'] = 'Unsupported video format. Please use one of these formats: MP4, MOV, AVI, MKV, WebM.';
$string['error_upload_timeout'] = 'Upload timed out. This may be due to a slow connection or large file size. Please try again with a smaller file or better connection.';
$string['error_processing_failed'] = 'Video processing failed. Please ensure your video file is not corrupted and try again.';
$string['error_token_expired'] = 'Your session has expired. Please refresh the page and try again.';
$string['error_video_not_ready'] = 'Video is still being processed. Please wait a moment and refresh the page.';
$string['error_playback_failed'] = 'Video playback failed. Please try refreshing the page or contact support if the problem persists.';

// Rate limiting error messages.
$string['error_rate_limit_upload'] = 'Too many upload attempts. Please wait a moment before trying again.';
$string['error_rate_limit_playback'] = 'Too many playback requests. Please wait a moment before trying again.';

// Retry and recovery messages.
$string['retry_suggestion'] = 'You can try the following:';
$string['retry_refresh_page'] = 'Refresh the page and try again';
$string['retry_check_connection'] = 'Check your internet connection';
$string['retry_smaller_file'] = 'Try uploading a smaller video file';
$string['retry_different_browser'] = 'Try using a different web browser';
$string['retry_contact_support'] = 'Contact technical support if the problem continues';
$string['retry_wait_and_retry'] = 'Wait a few minutes and try again';
$string['retry_different_file'] = 'Try uploading a different video file';

// Progress and status messages.
$string['upload_preparing'] = 'Preparing upload...';
$string['upload_connecting'] = 'Connecting to server...';
$string['upload_in_progress'] = 'Uploading: {$a}% complete';
$string['upload_processing'] = 'Processing video...';
$string['upload_finalizing'] = 'Finalizing upload...';
$string['upload_resuming'] = 'Resuming upload from {$a}%...';

// Connection and network status.
$string['connection_slow'] = 'Slow connection detected. Upload may take longer than usual.';
$string['connection_unstable'] = 'Unstable connection detected. Upload will automatically resume if interrupted.';
$string['connection_restored'] = 'Connection restored. Resuming upload...';

// Browser compatibility messages.
$string['browser_unsupported'] = 'Your browser may not support all features. For best results, please use Chrome, Firefox, Safari, or Edge.';
$string['javascript_required'] = 'JavaScript is required for video upload. Please enable JavaScript and refresh the page.';

// File validation messages.
$string['file_validation_failed'] = 'File validation failed: {$a}';
$string['file_empty'] = 'The selected file is empty or corrupted.';
$string['file_name_invalid'] = 'Invalid file name. Please use only letters, numbers, and common punctuation.';
$string['file_extension_missing'] = 'File must have a valid video extension (e.g., .mp4, .mov, .avi).';

// API and service status messages.
$string['service_maintenance'] = 'The video service is currently undergoing maintenance. Please try again later.';
$string['service_overloaded'] = 'The video service is experiencing high demand. Please try again in a few minutes.';
$string['api_version_mismatch'] = 'Service compatibility issue detected. Please contact your administrator.';

// Recovery and troubleshooting.
$string['troubleshooting_title'] = 'Troubleshooting Steps';
$string['troubleshooting_step1'] = '1. Ensure your video file is in a supported format (MP4, MOV, AVI, MKV, WebM)';
$string['troubleshooting_step2'] = '2. Check that your file size is under {$a}';
$string['troubleshooting_step3'] = '3. Verify you have a stable internet connection';
$string['troubleshooting_step4'] = '4. Try refreshing the page and uploading again';
$string['troubleshooting_step5'] = '5. If problems persist, contact your instructor or technical support';

// Admin error messages.
$string['admin_config_incomplete'] = 'Cloudflare Stream configuration is incomplete. Please check API token and account ID settings.';
$string['admin_quota_warning'] = 'Cloudflare quota is approaching limits. Consider upgrading your plan or implementing stricter file size limits.';
$string['admin_api_deprecated'] = 'The Cloudflare API version in use may be deprecated. Please check for plugin updates.';