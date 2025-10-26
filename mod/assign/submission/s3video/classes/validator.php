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
 * Input validation helper class for S3 Video plugin.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown when input validation fails.
 */
class validation_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode The error code
     * @param string $debuginfo Additional debug information
     */
    public function __construct($errorcode, $debuginfo = '') {
        parent::__construct($errorcode, 'assignsubmission_s3video', '', null, $debuginfo);
    }
}

/**
 * Input validation helper class.
 *
 * Provides comprehensive validation for all user inputs and API responses
 * to prevent security vulnerabilities and ensure data integrity.
 */
class validator {
    
    /** @var array Valid video MIME types */
    const VALID_VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/webm',
        'video/ogg',
        'video/3gpp',
        'video/x-flv'
    ];
    
    /** @var array Valid video file extensions */
    const VALID_VIDEO_EXTENSIONS = [
        'mp4', 'mpeg', 'mpg', 'mov', 'avi', 'wmv', 'webm', 'ogv', '3gp', 'flv'
    ];
    
    /** @var int Maximum file size (5GB) */
    const MAX_FILE_SIZE = 5368709120; // 5 * 1024 * 1024 * 1024
    
    /** @var int Maximum video duration (6 hours) */
    const MAX_DURATION_SECONDS = 21600; // 6 * 60 * 60
    
    /** @var int Maximum S3 key length */
    const MAX_S3_KEY_LENGTH = 500;
    
    /** @var int Maximum S3 bucket name length */
    const MAX_BUCKET_NAME_LENGTH = 255;
    
    /** @var string S3 key pattern (alphanumeric, hyphens, underscores, slashes, dots) */
    const S3_KEY_PATTERN = '/^[a-zA-Z0-9\-_\/\.]{1,500}$/';
    
    /** @var string S3 bucket name pattern (DNS-compliant) */
    const BUCKET_NAME_PATTERN = '/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/';
    
    /**
     * Validate file size.
     *
     * @param int $filesize File size in bytes
     * @throws validation_exception If file size is invalid
     */
    public static function validate_file_size($filesize) {
        if (!is_numeric($filesize) || $filesize < 0) {
            throw new validation_exception('invalid_file_size', 'File size must be a positive number');
        }
        
        if ($filesize > self::MAX_FILE_SIZE) {
            $maxsizeformatted = function_exists('display_size') ? display_size(self::MAX_FILE_SIZE) : (self::MAX_FILE_SIZE . ' bytes');
            throw new validation_exception('file_too_large', 
                'File size exceeds maximum allowed size of ' . $maxsizeformatted);
        }
    }
    
    /**
     * Validate MIME type.
     *
     * @param string $mimetype MIME type to validate
     * @throws validation_exception If MIME type is invalid
     */
    public static function validate_mime_type($mimetype) {
        $mimetype = clean_param($mimetype, PARAM_TEXT);
        
        if (empty($mimetype)) {
            throw new validation_exception('missing_mime_type', 'MIME type is required');
        }
        
        if (!in_array($mimetype, self::VALID_VIDEO_MIME_TYPES)) {
            throw new validation_exception('invalid_mime_type', 
                'MIME type "' . $mimetype . '" is not supported');
        }
    }
    
    /**
     * Validate file extension.
     *
     * @param string $filename Filename to validate
     * @throws validation_exception If file extension is invalid
     */
    public static function validate_file_extension($filename) {
        $filename = clean_param($filename, PARAM_FILE);
        
        if (empty($filename)) {
            throw new validation_exception('missing_filename', 'Filename is required');
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, self::VALID_VIDEO_EXTENSIONS)) {
            throw new validation_exception('invalid_file_extension', 
                'File extension "' . $extension . '" is not supported');
        }
    }
    
    /**
     * Validate S3 key.
     *
     * @param string $s3key S3 key to validate
     * @return string Validated S3 key
     * @throws validation_exception If S3 key is invalid
     */
    public static function validate_s3_key($s3key) {
        if (empty($s3key)) {
            throw new validation_exception('missing_s3_key', 'S3 key is required');
        }
        
        // Remove any potential path traversal attempts
        if (strpos($s3key, '..') !== false) {
            throw new validation_exception('invalid_s3_key_format', 'S3 key contains invalid path traversal');
        }
        
        if (strlen($s3key) > self::MAX_S3_KEY_LENGTH) {
            throw new validation_exception('s3_key_too_long', 
                'S3 key exceeds maximum length of ' . self::MAX_S3_KEY_LENGTH . ' characters');
        }
        
        if (!preg_match(self::S3_KEY_PATTERN, $s3key)) {
            throw new validation_exception('invalid_s3_key_format', 
                'S3 key contains invalid characters');
        }
        
        // Ensure key starts with expected prefix
        if (strpos($s3key, 'videos/') !== 0) {
            throw new validation_exception('invalid_s3_key_prefix', 
                'S3 key must start with "videos/" prefix');
        }
        
        return $s3key;
    }
    
    /**
     * Validate S3 bucket name.
     *
     * @param string $bucketname S3 bucket name to validate
     * @return string Validated bucket name
     * @throws validation_exception If bucket name is invalid
     */
    public static function validate_bucket_name($bucketname) {
        if (empty($bucketname)) {
            throw new validation_exception('missing_bucket_name', 'S3 bucket name is required');
        }
        
        $bucketname = strtolower(trim($bucketname));
        
        if (strlen($bucketname) < 3 || strlen($bucketname) > self::MAX_BUCKET_NAME_LENGTH) {
            throw new validation_exception('invalid_bucket_name_length', 
                'S3 bucket name must be between 3 and ' . self::MAX_BUCKET_NAME_LENGTH . ' characters');
        }
        
        if (!preg_match(self::BUCKET_NAME_PATTERN, $bucketname)) {
            throw new validation_exception('invalid_bucket_name_format', 
                'S3 bucket name must be DNS-compliant (lowercase letters, numbers, hyphens)');
        }
        
        return $bucketname;
    }
    
    /**
     * Validate video duration.
     *
     * @param int $duration Duration in seconds
     * @throws validation_exception If duration is invalid
     */
    public static function validate_duration($duration) {
        if (!is_numeric($duration) || $duration < 0) {
            throw new validation_exception('invalid_duration', 'Duration must be a positive number');
        }
        
        if ($duration > self::MAX_DURATION_SECONDS) {
            $maxdurationformatted = function_exists('format_time') ? format_time(self::MAX_DURATION_SECONDS) : (self::MAX_DURATION_SECONDS . ' seconds');
            throw new validation_exception('duration_too_long', 
                'Video duration exceeds maximum allowed duration of ' . $maxdurationformatted);
        }
    }
    
    /**
     * Validate assignment ID.
     *
     * @param int $assignmentid Assignment ID to validate
     * @return int Validated assignment ID
     * @throws validation_exception If assignment ID is invalid
     */
    public static function validate_assignment_id($assignmentid) {
        $assignmentid = clean_param($assignmentid, PARAM_INT);
        
        if (empty($assignmentid) || $assignmentid <= 0) {
            throw new validation_exception('invalid_assignment_id', 'Assignment ID must be a positive integer');
        }
        
        return $assignmentid;
    }
    
    /**
     * Validate submission ID.
     *
     * @param int $submissionid Submission ID to validate
     * @return int Validated submission ID
     * @throws validation_exception If submission ID is invalid
     */
    public static function validate_submission_id($submissionid) {
        $submissionid = clean_param($submissionid, PARAM_INT);
        
        if (empty($submissionid) || $submissionid <= 0) {
            throw new validation_exception('invalid_submission_id', 'Submission ID must be a positive integer');
        }
        
        return $submissionid;
    }
    
    /**
     * Validate user ID.
     *
     * @param int $userid User ID to validate
     * @return int Validated user ID
     * @throws validation_exception If user ID is invalid
     */
    public static function validate_user_id($userid) {
        $userid = clean_param($userid, PARAM_INT);
        
        if (empty($userid) || $userid <= 0) {
            throw new validation_exception('invalid_user_id', 'User ID must be a positive integer');
        }
        
        return $userid;
    }
    
    /**
     * Validate upload status.
     *
     * @param string $status Upload status to validate
     * @return string Validated status
     * @throws validation_exception If status is invalid
     */
    public static function validate_upload_status($status) {
        $validstatuses = ['pending', 'uploading', 'ready', 'error', 'deleted'];
        
        $status = clean_param($status, PARAM_ALPHA);
        
        if (!in_array($status, $validstatuses)) {
            throw new validation_exception('invalid_upload_status', 
                'Upload status must be one of: ' . implode(', ', $validstatuses));
        }
        
        return $status;
    }
    
    /**
     * Sanitize error message for database storage.
     *
     * @param string $message Error message to sanitize
     * @return string Sanitized error message
     */
    public static function sanitize_error_message($message) {
        // Remove any HTML tags and limit length
        $message = strip_tags($message);
        $message = clean_param($message, PARAM_TEXT);
        
        // Limit to 1000 characters
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 997) . '...';
        }
        
        return $message;
    }
    
    /**
     * Validate AWS API response structure.
     *
     * @param mixed $response API response
     * @param string $operation Operation name for error messages
     * @throws validation_exception If response is invalid
     */
    public static function validate_aws_response($response, $operation = 'API operation') {
        if ($response === null || $response === false) {
            throw new validation_exception('invalid_aws_response', 
                $operation . ' returned invalid response');
        }
        
        // Check if response is an error
        if (is_array($response) && isset($response['error'])) {
            throw new validation_exception('aws_api_error', 
                $operation . ' failed: ' . ($response['message'] ?? 'Unknown error'));
        }
    }
    
    /**
     * Validate CloudFront signed URL parameters.
     *
     * @param string $url URL to validate
     * @param int $expiry Expiry timestamp
     * @throws validation_exception If parameters are invalid
     */
    public static function validate_signed_url_params($url, $expiry) {
        if (empty($url)) {
            throw new validation_exception('missing_url', 'URL is required');
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new validation_exception('invalid_url_format', 'Invalid URL format');
        }
        
        // Validate expiry is in the future
        if (!is_numeric($expiry) || $expiry <= time()) {
            throw new validation_exception('invalid_expiry', 'Expiry must be a future timestamp');
        }
        
        // Validate expiry is not too far in the future (max 7 days)
        $maxexpiry = time() + (7 * 24 * 60 * 60);
        if ($expiry > $maxexpiry) {
            throw new validation_exception('expiry_too_long', 'Expiry cannot be more than 7 days in the future');
        }
    }
    
    /**
     * Validate and sanitize database record before insertion/update.
     *
     * @param object $record Database record to validate
     * @return object Sanitized record
     * @throws validation_exception If record is invalid
     */
    public static function validate_database_record($record) {
        if (!is_object($record)) {
            throw new validation_exception('invalid_record', 'Database record must be an object');
        }
        
        $sanitized = new \stdClass();
        
        // Validate required fields
        if (isset($record->assignment)) {
            $sanitized->assignment = self::validate_assignment_id($record->assignment);
        }
        
        if (isset($record->submission)) {
            $sanitized->submission = self::validate_submission_id($record->submission);
        }
        
        if (isset($record->s3_key)) {
            $sanitized->s3_key = self::validate_s3_key($record->s3_key);
        }
        
        if (isset($record->s3_bucket)) {
            $sanitized->s3_bucket = self::validate_bucket_name($record->s3_bucket);
        }
        
        if (isset($record->upload_status)) {
            $sanitized->upload_status = self::validate_upload_status($record->upload_status);
        }
        
        // Validate optional fields
        if (isset($record->file_size)) {
            if (!empty($record->file_size)) {
                self::validate_file_size($record->file_size);
                $sanitized->file_size = (int)$record->file_size;
            }
        }
        
        if (isset($record->duration)) {
            if (!empty($record->duration)) {
                self::validate_duration($record->duration);
                $sanitized->duration = (int)$record->duration;
            }
        }
        
        if (isset($record->mime_type)) {
            if (!empty($record->mime_type)) {
                self::validate_mime_type($record->mime_type);
                $sanitized->mime_type = clean_param($record->mime_type, PARAM_TEXT);
            }
        }
        
        if (isset($record->upload_timestamp)) {
            $sanitized->upload_timestamp = clean_param($record->upload_timestamp, PARAM_INT);
        }
        
        if (isset($record->deleted_timestamp)) {
            $sanitized->deleted_timestamp = clean_param($record->deleted_timestamp, PARAM_INT);
        }
        
        if (isset($record->error_message)) {
            $sanitized->error_message = self::sanitize_error_message($record->error_message);
        }
        
        // Copy ID if present (for updates)
        if (isset($record->id)) {
            $sanitized->id = clean_param($record->id, PARAM_INT);
        }
        
        return $sanitized;
    }
    
    /**
     * Validate AWS credentials configuration.
     *
     * @param string $accesskey AWS access key
     * @param string $secretkey AWS secret key
     * @param string $region AWS region
     * @throws validation_exception If credentials are invalid
     */
    public static function validate_aws_credentials($accesskey, $secretkey, $region) {
        if (empty($accesskey)) {
            throw new validation_exception('missing_access_key', 'AWS access key is required');
        }
        
        if (empty($secretkey)) {
            throw new validation_exception('missing_secret_key', 'AWS secret key is required');
        }
        
        if (empty($region)) {
            throw new validation_exception('missing_region', 'AWS region is required');
        }
        
        // Validate access key format (20 characters, alphanumeric)
        if (!preg_match('/^[A-Z0-9]{20}$/', $accesskey)) {
            throw new validation_exception('invalid_access_key_format', 
                'AWS access key must be 20 alphanumeric characters');
        }
        
        // Validate secret key format (40 characters, alphanumeric and special chars)
        if (strlen($secretkey) !== 40) {
            throw new validation_exception('invalid_secret_key_format', 
                'AWS secret key must be 40 characters');
        }
        
        // Validate region format
        if (!preg_match('/^[a-z]{2}-[a-z]+-\d{1}$/', $region)) {
            throw new validation_exception('invalid_region_format', 
                'AWS region format is invalid (e.g., us-east-1)');
        }
    }
}
