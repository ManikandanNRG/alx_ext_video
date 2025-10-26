<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for S3 video submission plugin.
 *
 * @package   assignsubmission_s3video
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'S3 Video Submissions';
$string['s3video'] = 'S3 Video';
$string['enabled'] = 'S3 Video Submissions';
$string['enabled_help'] = 'If enabled, students can upload large video files (up to 5 GB) directly to AWS S3 for their assignment submissions.';

// Configuration strings.
$string['config_missing'] = 'S3 Video plugin is not properly configured. Please contact your administrator.';
$string['uploadvideofile'] = 'Upload Video File';

// Status strings.
$string['status_pending'] = 'Pending';
$string['status_uploading'] = 'Uploading';
$string['status_ready'] = 'Ready';
$string['status_error'] = 'Error';
$string['status_deleted'] = 'Deleted';

// Messages.
$string['videonotavailable'] = 'This video is no longer available.';

// Settings strings.
$string['aws_s3_settings'] = 'AWS S3 Configuration';
$string['aws_s3_settings_desc'] = 'Configure AWS S3 bucket settings for video storage';
$string['aws_access_key'] = 'AWS Access Key ID';
$string['aws_access_key_desc'] = 'AWS IAM access key with S3 and CloudFront permissions';
$string['aws_secret_key'] = 'AWS Secret Access Key';
$string['aws_secret_key_desc'] = 'AWS IAM secret key';
$string['s3_bucket'] = 'S3 Bucket Name';
$string['s3_bucket_desc'] = 'Name of the S3 bucket for video storage';
$string['s3_region'] = 'S3 Region';
$string['s3_region_desc'] = 'AWS region where the S3 bucket is located (e.g., us-east-1)';
$string['cloudfront_settings'] = 'CloudFront Configuration';
$string['cloudfront_settings_desc'] = 'Configure CloudFront CDN settings for video delivery';
$string['cloudfront_domain'] = 'CloudFront Domain';
$string['cloudfront_domain_desc'] = 'CloudFront distribution domain (e.g., d123456abcdef.cloudfront.net)';
$string['cloudfront_keypair_id'] = 'CloudFront Key Pair ID';
$string['cloudfront_keypair_id_desc'] = 'CloudFront trusted signer key pair ID';
$string['cloudfront_private_key'] = 'CloudFront Private Key';
$string['cloudfront_private_key_desc'] = 'CloudFront private key for signing URLs (PEM format)';
$string['retention_settings'] = 'Video Retention Configuration';
$string['retention_settings_desc'] = 'Configure automatic video cleanup settings';
$string['retention_days'] = 'Video Retention Period (days)';
$string['retention_days_desc'] = 'Number of days to keep videos before automatic deletion (default: 90)';

// Error strings - S3.
$string['s3_auth_failed'] = 'AWS S3 authentication failed. Please check your credentials.';
$string['s3_object_not_found'] = 'The requested video file was not found in S3.';
$string['s3_presigned_post_failed'] = 'Failed to generate upload URL. Please try again.';
$string['s3_object_check_failed'] = 'Failed to verify video file in S3.';
$string['s3_delete_failed'] = 'Failed to delete video from S3.';
$string['s3_metadata_failed'] = 'Failed to retrieve video metadata from S3.';
$string['invalid_s3_key'] = 'Invalid S3 key provided.';
$string['invalid_max_size'] = 'Invalid maximum file size.';
$string['invalid_expiry'] = 'Invalid expiration time.';

// Error strings - CloudFront.
$string['cloudfront_signature_failed'] = 'Failed to generate CloudFront signed URL.';
$string['cloudfront_init_failed'] = 'Failed to initialize CloudFront client.';
$string['cloudfront_invalidation_failed'] = 'Failed to create CloudFront cache invalidation.';
$string['cloudfront_client_not_initialized'] = 'CloudFront client not initialized. Credentials required.';
$string['distribution_not_found'] = 'CloudFront distribution not found for the configured domain.';
$string['distribution_lookup_failed'] = 'Failed to lookup CloudFront distribution.';

// Privacy strings.
$string['privacy:metadata:assignsubmission_s3video'] = 'Information about video submissions stored in S3';
$string['privacy:metadata:assignsubmission_s3video:assignment'] = 'The assignment ID';
$string['privacy:metadata:assignsubmission_s3video:submission'] = 'The submission ID';
$string['privacy:metadata:assignsubmission_s3video:s3_key'] = 'The S3 object key for the video';
$string['privacy:metadata:assignsubmission_s3video:s3_bucket'] = 'The S3 bucket name where the video is stored';
$string['privacy:metadata:assignsubmission_s3video:upload_status'] = 'The current status of the video upload';
$string['privacy:metadata:assignsubmission_s3video:file_size'] = 'The size of the uploaded video file';
$string['privacy:metadata:assignsubmission_s3video:duration'] = 'The duration of the video in seconds';
$string['privacy:metadata:assignsubmission_s3video:mime_type'] = 'The MIME type of the video file';
$string['privacy:metadata:assignsubmission_s3video:upload_timestamp'] = 'When the video was uploaded';
$string['privacy:metadata:assignsubmission_s3video:deleted_timestamp'] = 'When the video was deleted';
$string['privacy:metadata:assignsubmission_s3video:error_message'] = 'Any error message associated with the video';

$string['privacy:metadata:assignsubmission_s3v_log'] = 'Log of video upload and playback events';
$string['privacy:metadata:assignsubmission_s3v_log:userid'] = 'The ID of the user who performed the action';
$string['privacy:metadata:assignsubmission_s3v_log:assignmentid'] = 'The assignment ID';
$string['privacy:metadata:assignsubmission_s3v_log:submissionid'] = 'The submission ID';
$string['privacy:metadata:assignsubmission_s3v_log:s3_key'] = 'The S3 object key for the video';
$string['privacy:metadata:assignsubmission_s3v_log:event_type'] = 'The type of event (upload, playback, error, etc.)';
$string['privacy:metadata:assignsubmission_s3v_log:error_code'] = 'Error code if the event was an error';
$string['privacy:metadata:assignsubmission_s3v_log:error_message'] = 'Error message if the event was an error';
$string['privacy:metadata:assignsubmission_s3v_log:error_context'] = 'Additional context about the error';
$string['privacy:metadata:assignsubmission_s3v_log:file_size'] = 'The size of the video file';
$string['privacy:metadata:assignsubmission_s3v_log:duration'] = 'The duration of the video';
$string['privacy:metadata:assignsubmission_s3v_log:retry_count'] = 'Number of retry attempts';
$string['privacy:metadata:assignsubmission_s3v_log:user_role'] = 'The role of the user (student, teacher, admin)';
$string['privacy:metadata:assignsubmission_s3v_log:timestamp'] = 'When the event occurred';

$string['privacy:metadata:aws_s3'] = 'Video files are stored in AWS S3 and delivered via CloudFront CDN';
$string['privacy:metadata:aws_s3:video_content'] = 'The actual video file content uploaded by the user';
$string['privacy:metadata:aws_s3:video_metadata'] = 'Metadata about the video (size, duration, content type)';
$string['privacy:metadata:aws_s3:user_identifier'] = 'User ID embedded in the S3 key path for organization';

$string['videosubmissions'] = 'Video Submissions';
$string['activitylogs'] = 'Activity Logs';

// Dashboard strings.
$string['dashboard'] = 'S3 Video Dashboard';
$string['timerange'] = 'Time Range';
$string['last7days'] = 'Last 7 Days';
$string['last30days'] = 'Last 30 Days';
$string['last90days'] = 'Last 90 Days';
$string['lastyear'] = 'Last Year';
$string['uploadstatistics'] = 'Upload Statistics';
$string['totalrequested'] = 'Total Requested';
$string['totalcompleted'] = 'Total Completed';
$string['totalfailed'] = 'Total Failed';
$string['successrate'] = 'Success Rate';
$string['storagestatistics'] = 'Storage Statistics';
$string['totalvideos'] = 'Total Videos';
$string['totalstorage'] = 'Total Storage';
$string['averagefilesize'] = 'Average File Size';
$string['playbackstatistics'] = 'Playback Statistics';
$string['totalviews'] = 'Total Views';
$string['viewsbyrole'] = 'Views by Role';
$string['nodata'] = 'No data available';
$string['costestimates'] = 'Cost Estimates';
$string['costdisclaimer'] = 'Cost estimates are approximate and based on current AWS pricing. Actual costs may vary.';
$string['storagecost'] = 'Storage Cost';
$string['transfercost'] = 'Transfer Cost';
$string['totalcost'] = 'Total Cost';
$string['recentfailures'] = 'Recent Failures';
$string['nofailures'] = 'No failures recorded in this time period.';

// Additional error strings.
$string['invalids3key'] = 'Invalid S3 key provided';
$string['filesizeexceeded'] = 'File size exceeds maximum allowed size of {$a->max}. Your file is {$a->actual}.';
$string['invalidmimetype'] = 'Invalid file type: {$a}. Only video files are allowed.';

// Validation error strings.
$string['invalid_file_size'] = 'Invalid file size';
$string['file_too_large'] = 'File size exceeds maximum allowed size';
$string['missing_mime_type'] = 'MIME type is required';
$string['invalid_mime_type'] = 'Invalid or unsupported MIME type';
$string['missing_filename'] = 'Filename is required';
$string['invalid_file_extension'] = 'Invalid or unsupported file extension';
$string['missing_s3_key'] = 'S3 key is required';
$string['invalid_s3_key_format'] = 'S3 key contains invalid characters or format';
$string['invalid_s3_key_prefix'] = 'S3 key must start with "videos/" prefix';
$string['s3_key_too_long'] = 'S3 key exceeds maximum length';
$string['missing_bucket_name'] = 'S3 bucket name is required';
$string['invalid_bucket_name_length'] = 'S3 bucket name length is invalid';
$string['invalid_bucket_name_format'] = 'S3 bucket name must be DNS-compliant';
$string['invalid_duration'] = 'Invalid video duration';
$string['duration_too_long'] = 'Video duration exceeds maximum allowed duration';
$string['invalid_assignment_id'] = 'Invalid assignment ID';
$string['invalid_submission_id'] = 'Invalid submission ID';
$string['invalid_user_id'] = 'Invalid user ID';
$string['invalid_upload_status'] = 'Invalid upload status';
$string['invalid_record'] = 'Invalid database record';
$string['invalid_aws_response'] = 'Invalid AWS API response';
$string['aws_api_error'] = 'AWS API error';
$string['missing_url'] = 'URL is required';
$string['invalid_url_format'] = 'Invalid URL format';
$string['invalid_expiry'] = 'Invalid expiry timestamp';
$string['expiry_too_long'] = 'Expiry cannot be more than 7 days in the future';
$string['missing_access_key'] = 'AWS access key is required';
$string['missing_secret_key'] = 'AWS secret key is required';
$string['missing_region'] = 'AWS region is required';
$string['invalid_access_key_format'] = 'Invalid AWS access key format';
$string['invalid_secret_key_format'] = 'Invalid AWS secret key format';
$string['invalid_region_format'] = 'Invalid AWS region format';

// Rate limiting error strings.
$string['upload_rate_limit_exceeded'] = 'Upload rate limit exceeded. Please try again later.';
$string['playback_rate_limit_exceeded'] = 'Playback rate limit exceeded. Please try again later.';
$string['rate_limit_settings'] = 'Rate Limiting Configuration';
$string['rate_limit_settings_desc'] = 'Configure rate limits to prevent abuse of upload and playback requests';
$string['upload_rate_limit'] = 'Upload Rate Limit (per hour)';
$string['upload_rate_limit_desc'] = 'Maximum number of upload URL requests per user per hour (default: 10)';
$string['playback_rate_limit'] = 'Playback Rate Limit (per hour)';
$string['playback_rate_limit_desc'] = 'Maximum number of playback URL requests per user per hour (default: 100)';

// Capability strings.
$string['s3video:bypassratelimit'] = 'Bypass rate limiting for S3 video operations';

// Upload interface strings.
$string['dragdrop'] = 'Drag and drop your video file here';
$string['selectvideo'] = 'Select Video File';
$string['uploading'] = 'Uploading video...';
$string['uploadsuccess'] = 'Video uploaded successfully!';

// Playback interface strings.
$string['videoloading'] = 'Loading video...';
$string['playbackerror'] = 'Video Playback Error';
$string['retryplayback'] = 'Try Again';

// Scheduled task strings.
$string['cleanup_videos_task'] = 'Clean up expired S3 videos';

// Video management strings.
$string['videomanagement'] = 'S3 Video Management';
$string['videomanagement_desc'] = 'Manage and delete S3 video submissions. Use this page to manually delete videos from S3 storage.';
$string['deleteconfirm_desc'] = 'Are you sure you want to permanently delete this video? This action cannot be undone.';
$string['videodeletesuccess'] = 'Video deleted successfully';
$string['videodeletefailed'] = 'Failed to delete video: {$a}';
$string['videonotfound'] = 'Video not found';
$string['videouid'] = 'S3 Key';
$string['student'] = 'Student';
$string['uploaddate'] = 'Upload Date';
$string['actions'] = 'Actions';
$string['filterbycourse'] = 'Filter by Course';
$string['filterbystatus'] = 'Filter by Status';
$string['searchvideos'] = 'Search by assignment, student, or S3 key';
$string['videosperpage'] = 'Videos per page';
$string['novideostoshow'] = 'No videos to display';
$string['allstatuses'] = 'All Statuses';

// Comprehensive error handling strings.
$string['error_network'] = 'Network error occurred. Please check your internet connection and try again.';
$string['error_network_guidance'] = 'If the problem persists, contact your network administrator.';
$string['error_timeout'] = 'The request timed out. Please try again.';
$string['error_timeout_guidance'] = 'Large files may take longer to upload. Ensure you have a stable internet connection.';
$string['error_auth'] = 'Authentication failed. Please refresh the page and log in again.';
$string['error_auth_guidance'] = 'If the problem persists, contact your system administrator.';
$string['error_permission'] = 'You do not have permission to perform this action.';
$string['error_permission_guidance'] = 'Contact your teacher or administrator if you believe this is an error.';
$string['error_validation'] = 'Invalid data provided. Please check your input and try again.';
$string['error_validation_guidance'] = 'Ensure your file meets all requirements (size, type, format).';
$string['error_server'] = 'Server error occurred. Please try again later.';
$string['error_server_guidance'] = 'If the problem persists, contact your system administrator.';
$string['error_aws_service'] = 'AWS service temporarily unavailable. Please try again in a few moments.';
$string['error_aws_service_guidance'] = 'This is usually a temporary issue that resolves quickly.';
$string['error_throttling'] = 'Too many requests. Please wait a moment and try again.';
$string['error_throttling_guidance'] = 'You may be uploading or accessing videos too frequently.';
$string['error_s3_upload'] = 'Failed to upload video to storage. Please try again.';
$string['error_s3_upload_guidance'] = 'Check your internet connection and ensure the file is not corrupted.';
$string['error_s3_verify'] = 'Failed to verify uploaded video. Please try uploading again.';
$string['error_s3_verify_guidance'] = 'The upload may have been interrupted. Try again with a stable connection.';
$string['error_cloudfront'] = 'Failed to generate video playback URL. Please try again.';
$string['error_cloudfront_guidance'] = 'If the problem persists, contact your system administrator.';
$string['error_config'] = 'System configuration error. Please contact your administrator.';
$string['error_config_guidance'] = 'The S3 video plugin needs to be configured by an administrator.';
$string['error_unknown'] = 'An unexpected error occurred. Please try again.';
$string['error_unknown_guidance'] = 'If the problem persists, contact your system administrator with details about what you were doing.';
$string['error_max_retries'] = 'Maximum retry attempts exceeded. The operation could not be completed.';
$string['error_max_retries_guidance'] = 'Please wait a few minutes and try again. If the problem persists, contact support.';
$string['error_file_corrupted'] = 'The file appears to be corrupted or incomplete.';
$string['error_file_corrupted_guidance'] = 'Try uploading a different file or re-export your video.';
$string['error_quota_exceeded'] = 'Storage quota exceeded. Please contact your administrator.';
$string['error_quota_exceeded_guidance'] = 'Your institution may need to increase storage capacity or clean up old videos.';

// Retry mechanism strings.
$string['retrying'] = 'Retrying... (Attempt {$a->attempt} of {$a->max})';
$string['retry_upload'] = 'Retry Upload';
$string['retry_failed'] = 'All retry attempts failed. Please try again later.';
$string['retry_success'] = 'Operation succeeded after {$a} attempts.';
$string['automatic_retry'] = 'Automatically retrying...';
$string['manual_retry_available'] = 'Click "Retry Upload" to try again.';

// User guidance strings.
$string['upload_tips'] = 'Upload Tips';
$string['upload_tip_connection'] = 'Ensure you have a stable internet connection';
$string['upload_tip_size'] = 'Maximum file size is 5 GB';
$string['upload_tip_format'] = 'Supported formats: MP4, MOV, AVI, MKV, WebM, MPEG, OGV, 3GP, FLV';
$string['upload_tip_time'] = 'Large files may take several minutes to upload';
$string['upload_tip_browser'] = 'Keep this browser tab open during upload';
$string['playback_tips'] = 'Playback Tips';
$string['playback_tip_browser'] = 'Use a modern browser (Chrome, Firefox, Safari, Edge)';
$string['playback_tip_connection'] = 'Ensure you have a stable internet connection';
$string['playback_tip_refresh'] = 'If video does not load, try refreshing the page';

// Error recovery strings.
$string['error_recovery_title'] = 'What to do next';
$string['error_recovery_check_connection'] = 'Check your internet connection';
$string['error_recovery_refresh_page'] = 'Refresh the page and try again';
$string['error_recovery_try_smaller_file'] = 'Try uploading a smaller file';
$string['error_recovery_try_different_format'] = 'Try converting to a different video format';
$string['error_recovery_contact_support'] = 'Contact support if the problem persists';
$string['error_recovery_wait'] = 'Wait a few minutes before trying again';

// Max retries exceeded error.
$string['max_retries_exceeded'] = 'Maximum retry attempts exceeded. Operation: {$a->operation}, Attempts: {$a->attempts}, Last error: {$a->error}';

// Video viewing strings.
$string['watchvideo'] = 'Watch video';
$string['videonotsupported'] = 'Your browser does not support the video tag.';
$string['downloadvideo'] = 'Download video';
$string['openinnewwindow'] = 'Open in new window';
$string['downloadhint'] = 'Tip: Right-click the video and select "Save video as..." to download';
$string['videopending'] = 'Video is still being processed.';
$string['videoloading'] = 'Loading video...';
$string['playbackerror'] = 'Video playback error';
$string['retryplayback'] = 'Retry';
