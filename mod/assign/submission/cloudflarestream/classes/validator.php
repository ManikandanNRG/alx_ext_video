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
 * Input validation helper class for Cloudflare Stream plugin.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream;

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
        parent::__construct($errorcode, 'assignsubmission_cloudflarestream', '', null, $debuginfo);
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
    
    /** @var int Default maximum file size (5GB) - used as fallback only */
    const DEFAULT_MAX_FILE_SIZE = 5368709120; // 5 * 1024 * 1024 * 1024
    
    /** @var int Maximum video duration (6 hours) */
    const MAX_DURATION_SECONDS = 21600; // 6 * 60 * 60
    
    /** @var int Maximum video UID length */
    const MAX_VIDEO_UID_LENGTH = 255;
    
    /** @var string Video UID pattern (alphanumeric and hyphens) */
    const VIDEO_UID_PATTERN = '/^[a-zA-Z0-9\-]{1,255}$/';
    
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
        
        // Get max file size from config (reads from admin settings)
        $maxfilesize = get_config('assignsubmission_cloudflarestream', 'max_file_size');
        if (empty($maxfilesize)) {
            $maxfilesize = self::DEFAULT_MAX_FILE_SIZE; // Fallback to 5GB
        }
        
        if ($filesize > $maxfilesize) {
            $maxsizeformatted = function_exists('display_size') ? display_size($maxfilesize) : ($maxfilesize . ' bytes');
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
     * Validate video UID.
     *
     * @param string $videouid Video UID to validate
     * @throws validation_exception If video UID is invalid
     */
    public static function validate_video_uid($videouid) {
        if (empty($videouid)) {
            throw new validation_exception('missing_video_uid', 'Video UID is required');
        }
        
        // Sanitize the video UID
        $cleaned_uid = clean_param($videouid, PARAM_ALPHANUMEXT);
        
        if (strlen($cleaned_uid) > self::MAX_VIDEO_UID_LENGTH) {
            throw new validation_exception('video_uid_too_long', 
                'Video UID exceeds maximum length of ' . self::MAX_VIDEO_UID_LENGTH . ' characters');
        }
        
        if (!preg_match(self::VIDEO_UID_PATTERN, $cleaned_uid)) {
            throw new validation_exception('invalid_video_uid_format', 
                'Video UID contains invalid characters');
        }
        
        return $cleaned_uid;
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
     * Validate Cloudflare API response structure.
     *
     * @param object $response API response object
     * @param array $requiredfields Required fields in the response
     * @throws validation_exception If response is invalid
     */
    public static function validate_api_response($response, $requiredfields = []) {
        if (!is_object($response)) {
            throw new validation_exception('invalid_api_response', 'API response must be an object');
        }
        
        // Check for success field
        if (!isset($response->success)) {
            throw new validation_exception('invalid_api_response', 'API response missing success field');
        }
        
        if ($response->success !== true) {
            $errormessage = 'API request failed';
            if (isset($response->errors) && is_array($response->errors) && !empty($response->errors)) {
                $errormessage = $response->errors[0]->message ?? $errormessage;
            }
            throw new validation_exception('api_request_failed', $errormessage);
        }
        
        // Check for required fields in result
        if (!empty($requiredfields) && isset($response->result)) {
            foreach ($requiredfields as $field) {
                if (!isset($response->result->$field)) {
                    throw new validation_exception('invalid_api_response', 
                        'API response missing required field: ' . $field);
                }
            }
        }
    }
    
    /**
     * Validate video details from Cloudflare API.
     *
     * @param object $videodetails Video details object from API
     * @throws validation_exception If video details are invalid
     */
    public static function validate_video_details($videodetails) {
        if (!is_object($videodetails)) {
            throw new validation_exception('invalid_video_details', 'Video details must be an object');
        }
        
        // Validate UID if present
        if (isset($videodetails->uid)) {
            self::validate_video_uid($videodetails->uid);
        }
        
        // Validate duration if present
        if (isset($videodetails->duration)) {
            self::validate_duration($videodetails->duration);
        }
        
        // Validate file size if present
        if (isset($videodetails->size)) {
            self::validate_file_size($videodetails->size);
        }
        
        // Validate status if present
        if (isset($videodetails->status) && isset($videodetails->status->state)) {
            $validstates = ['queued', 'inprogress', 'ready', 'error'];
            $state = clean_param($videodetails->status->state, PARAM_ALPHA);
            
            if (!in_array($state, $validstates)) {
                throw new validation_exception('invalid_video_state', 
                    'Video state must be one of: ' . implode(', ', $validstates));
            }
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
        
        if (isset($record->video_uid) && $record->video_uid !== '') {
            $sanitized->video_uid = self::validate_video_uid($record->video_uid);
        } else if (isset($record->video_uid)) {
            // Allow empty video_uid for pending uploads
            $sanitized->video_uid = '';
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
}