<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Require the parent class.
require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * Library class for Cloudflare Stream assignment submission plugin.
 *
 * @package   assignsubmission_cloudflarestream
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_cloudflarestream extends assign_submission_plugin {

    /**
     * Get the name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cloudflarestream', 'assignsubmission_cloudflarestream');
    }

    /**
     * Get the settings for the plugin.
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG;

        $defaultenabled = $this->get_config('enabled');
        $mform->addElement('selectyesno', 'assignsubmission_cloudflarestream_enabled',
            get_string('enabled', 'assignsubmission_cloudflarestream'));
        $mform->addHelpButton('assignsubmission_cloudflarestream_enabled',
            'enabled', 'assignsubmission_cloudflarestream');
        $mform->setDefault('assignsubmission_cloudflarestream_enabled', $defaultenabled);
    }

    /**
     * Check if the plugin is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->get_config('enabled');
    }

    /**
     * Save the submission data.
     *
     * @param stdClass $submission The submission object
     * @param stdClass $data The form data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;

        // Check if video_uid is provided in the form data
        if (empty($data->cloudflarestream_video_uid)) {
            return true; // No video to save
        }

        $video_uid = $data->cloudflarestream_video_uid;
        $upload_status = isset($data->cloudflarestream_status) ? $data->cloudflarestream_status : 'ready';

        // Check if a record already exists for this submission
        $existing = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));

        if ($existing) {
            // Update existing record
            $existing->video_uid = $video_uid;
            $existing->upload_status = $upload_status;
            
            if (isset($data->cloudflarestream_file_size)) {
                $existing->file_size = $data->cloudflarestream_file_size;
            }
            if (isset($data->cloudflarestream_duration)) {
                $existing->duration = $data->cloudflarestream_duration;
            }
            if (isset($data->cloudflarestream_error_message)) {
                $existing->error_message = $data->cloudflarestream_error_message;
            }

            return $DB->update_record('assignsubmission_cfstream', $existing);
        } else {
            // Create new record
            $record = new stdClass();
            $record->assignment = $this->assignment->get_instance()->id;
            $record->submission = $submission->id;
            $record->video_uid = $video_uid;
            $record->upload_status = $upload_status;
            $record->upload_timestamp = time();
            
            if (isset($data->cloudflarestream_file_size)) {
                $record->file_size = $data->cloudflarestream_file_size;
            }
            if (isset($data->cloudflarestream_duration)) {
                $record->duration = $data->cloudflarestream_duration;
            }
            if (isset($data->cloudflarestream_error_message)) {
                $record->error_message = $data->cloudflarestream_error_message;
            }

            return $DB->insert_record('assignsubmission_cfstream', $record) > 0;
        }
    }

    /**
     * Display the submission for grading/review.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view the full submission
     * @return string HTML to display
     */
    public function view(stdClass $submission, $showviewlink = true) {
        global $DB, $OUTPUT;

        // Get the video record for this submission
        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        $output = '';

        // Display video status
        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_cloudflarestream');
        
        $output .= html_writer::tag('div', 
            html_writer::tag('strong', get_string('cloudflarestream', 'assignsubmission_cloudflarestream') . ': ') . 
            $statustext,
            array('class' => 'cloudflarestream-status mb-3')
        );

        // If video is ready, display the player using the template
        if ($video->upload_status === 'ready') {
            // Generate unique container ID for this player instance
            $containerid = 'cloudflarestream-player-' . $submission->id . '-' . uniqid();
            
            // Prepare template context
            $context = [
                'videouid' => $video->video_uid,
                'submissionid' => $submission->id,
                'containerid' => $containerid
            ];
            
            // Render player template
            $output .= $OUTPUT->render_from_template('assignsubmission_cloudflarestream/player', $context);

            // Add video metadata if available
            if ($video->duration || $video->file_size) {
                $output .= html_writer::start_div('cloudflarestream-metadata mt-3');
                
                if ($video->duration) {
                    $duration = format_time($video->duration);
                    $output .= html_writer::tag('div', 
                        html_writer::tag('strong', get_string('duration', 'core') . ': ') . $duration,
                        array('class' => 'cloudflarestream-metadata-item')
                    );
                }
                
                if ($video->file_size) {
                    $filesize = display_size($video->file_size);
                    $output .= html_writer::tag('div', 
                        html_writer::tag('strong', get_string('size', 'core') . ': ') . $filesize,
                        array('class' => 'cloudflarestream-metadata-item')
                    );
                }
                
                $output .= html_writer::end_div();
            }
        } else if ($video->upload_status === 'error' && !empty($video->error_message)) {
            // Display error message
            $output .= html_writer::tag('div', 
                $video->error_message,
                array('class' => 'alert alert-danger')
            );
        } else if ($video->upload_status === 'deleted') {
            // Video has been deleted
            $output .= html_writer::tag('div', 
                get_string('videonotavailable', 'assignsubmission_cloudflarestream'),
                array('class' => 'alert alert-warning')
            );
        }

        return $output;
    }

    /**
     * Get form elements for the submission form.
     *
     * @param stdClass $submission The submission object
     * @param MoodleQuickForm $mform The form to add elements to
     * @param stdClass $data The form data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $DB;

        // Check if plugin is properly configured
        if (!$this->is_configured()) {
            $mform->addElement('static', 'cloudflarestream_error', '',
                html_writer::tag('div', 
                    get_string('config_missing', 'assignsubmission_cloudflarestream'),
                    array('class' => 'alert alert-danger')
                )
            );
            return true;
        }

        // Get existing video if any
        $video = null;
        if ($submission) {
            $video = $DB->get_record('assignsubmission_cfstream', 
                array('submission' => $submission->id));
        }

        // Add upload interface container
        $mform->addElement('static', 'cloudflarestream_upload_container', 
            get_string('uploadvideofile', 'assignsubmission_cloudflarestream'),
            $this->get_upload_interface_html($video)
        );

        // Hidden fields to store video data
        $mform->addElement('hidden', 'cloudflarestream_video_uid');
        $mform->setType('cloudflarestream_video_uid', PARAM_TEXT);
        
        $mform->addElement('hidden', 'cloudflarestream_status');
        $mform->setType('cloudflarestream_status', PARAM_TEXT);
        
        $mform->addElement('hidden', 'cloudflarestream_file_size');
        $mform->setType('cloudflarestream_file_size', PARAM_INT);
        
        $mform->addElement('hidden', 'cloudflarestream_duration');
        $mform->setType('cloudflarestream_duration', PARAM_INT);
        
        $mform->addElement('hidden', 'cloudflarestream_error_message');
        $mform->setType('cloudflarestream_error_message', PARAM_TEXT);

        // Set default values if video exists
        if ($video) {
            $mform->setDefault('cloudflarestream_video_uid', $video->video_uid);
            $mform->setDefault('cloudflarestream_status', $video->upload_status);
            $mform->setDefault('cloudflarestream_file_size', $video->file_size);
            $mform->setDefault('cloudflarestream_duration', $video->duration);
        }

        return true;
    }

    /**
     * Generate HTML for the upload interface.
     *
     * @param stdClass|null $video Existing video record if any
     * @return string HTML for upload interface
     */
    protected function get_upload_interface_html($video = null) {
        global $OUTPUT;

        // Prepare template context
        $context = [
            'assignmentid' => $this->assignment->get_instance()->id,
            'submissionid' => 0,
            'maxfilesize' => $this->get_max_file_size(),
            'maxfilesizeformatted' => display_size($this->get_max_file_size()),
            'hasvideo' => !empty($video),
        ];

        // Add video information if available
        if ($video) {
            $context['videostatus'] = $video->upload_status;
            $context['videouid'] = $video->video_uid;
            $context['videostatus_ready'] = ($video->upload_status === 'ready');
            
            if ($video->duration) {
                $context['duration'] = $video->duration;
                $context['durationformatted'] = format_time($video->duration);
            }
            
            if ($video->file_size) {
                $context['filesize'] = $video->file_size;
                $context['filesizeformatted'] = display_size($video->file_size);
            }
        }

        return $OUTPUT->render_from_template('assignsubmission_cloudflarestream/upload_form', $context);
    }

    /**
     * Get encrypted API token from config.
     *
     * @return string|null The decrypted API token or null if not set
     */
    protected function get_api_token() {
        $encryptedtoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
        
        if (empty($encryptedtoken)) {
            return null;
        }

        // Moodle stores passwords with configpasswordunmask in plain text in config_plugins table
        // For production use, consider implementing additional encryption layer
        return $encryptedtoken;
    }

    /**
     * Get account ID from config.
     *
     * @return string|null The account ID or null if not set
     */
    protected function get_account_id() {
        return get_config('assignsubmission_cloudflarestream', 'accountid');
    }

    /**
     * Check if the plugin is properly configured.
     *
     * @return bool
     */
    public function is_configured() {
        $apitoken = $this->get_api_token();
        $accountid = $this->get_account_id();
        
        return !empty($apitoken) && !empty($accountid);
    }

    /**
     * Get the maximum file size allowed.
     *
     * @return int Maximum file size in bytes
     */
    public function get_max_file_size() {
        $maxsize = get_config('assignsubmission_cloudflarestream', 'max_file_size');
        return !empty($maxsize) ? (int)$maxsize : 5368709120; // Default 5GB
    }

    /**
     * Get the retention period in days.
     *
     * @return int Retention period in days
     */
    public function get_retention_days() {
        $days = get_config('assignsubmission_cloudflarestream', 'retention_days');
        return !empty($days) ? (int)$days : 90; // Default 90 days
    }

    /**
     * Check if the submission has data.
     *
     * @param stdClass $submission The submission object
     * @return bool True if submission has video data
     */
    public function is_empty(stdClass $submission) {
        global $DB;
        
        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));
        
        return empty($video) || empty($video->video_uid);
    }

    /**
     * Get file areas for this plugin.
     *
     * @return array Array of file area names
     */
    public function get_file_areas() {
        // This plugin doesn't use Moodle's file storage
        return array();
    }

    /**
     * Copy submission data from one submission to another.
     *
     * @param stdClass $sourcesubmission Source submission
     * @param stdClass $destsubmission Destination submission
     * @return bool Success
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        $source = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $sourcesubmission->id));

        if (!$source) {
            return true;
        }

        // Create a copy of the record for the new submission
        $dest = clone $source;
        unset($dest->id);
        $dest->submission = $destsubmission->id;

        return $DB->insert_record('assignsubmission_cfstream', $dest) > 0;
    }

    /**
     * Remove submission data.
     *
     * @param stdClass $submission The submission object
     * @return bool Success
     */
    public function remove(stdClass $submission) {
        global $DB;

        // Note: This only removes the database record, not the video from Cloudflare
        // Video cleanup is handled by the scheduled task based on retention policy
        return $DB->delete_records('assignsubmission_cfstream', 
            array('submission' => $submission->id));
    }

    /**
     * Get a summary of the submission for display.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view
     * @return string Summary text
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB;

        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_cloudflarestream');

        return get_string('cloudflarestream', 'assignsubmission_cloudflarestream') . ': ' . $statustext;
    }

    /**
     * Format submission for display in the submission status table.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view
     * @return string HTML to display
     */
    public function view_summary_table(stdClass $submission, & $showviewlink) {
        return $this->view_summary($submission, $showviewlink);
    }
}

/**
 * Verify that a user has access to view a specific video submission.
 *
 * @param int $user_id The user ID requesting access
 * @param int $submission_id The submission ID
 * @param string $video_uid The Cloudflare video UID
 * @return bool True if access is granted
 * @throws moodle_exception If access is denied
 */
function verify_video_access($user_id, $submission_id, $video_uid) {
    global $DB;
    
    // Validate input parameters.
    try {
        $user_id = \assignsubmission_cloudflarestream\validator::validate_user_id($user_id);
        $submission_id = \assignsubmission_cloudflarestream\validator::validate_submission_id($submission_id);
        $video_uid = \assignsubmission_cloudflarestream\validator::validate_video_uid($video_uid);
    } catch (\assignsubmission_cloudflarestream\validation_exception $e) {
        throw new moodle_exception('invalidparameters', 'assignsubmission_cloudflarestream', '', null, $e->getMessage());
    }

    // Get the submission record
    $submission = $DB->get_record('assign_submission', array('id' => $submission_id), '*', MUST_EXIST);
    
    // Get the assignment
    $assignment = $DB->get_record('assign', array('id' => $submission->assignment), '*', MUST_EXIST);
    
    // Get the course module
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
    
    // Get the context
    $context = context_module::instance($cm->id);
    
    // Get the video record to verify video UID matches
    $video = $DB->get_record('assignsubmission_cfstream', 
        array('submission' => $submission_id), '*', MUST_EXIST);
    
    // Verify video UID matches the submission record
    if ($video->video_uid !== $video_uid) {
        throw new moodle_exception('invalidvideouid', 'assignsubmission_cloudflarestream');
    }
    
    // Check if user is the submission owner
    if ($submission->userid == $user_id) {
        // Student can view their own submission
        return true;
    }
    
    // Check if user has grading capability (teacher or admin)
    if (has_capability('mod/assign:grade', $context, $user_id)) {
        return true;
    }
    
    // Check if user is an admin
    if (is_siteadmin($user_id)) {
        return true;
    }
    
    // Access denied
    throw new moodle_exception('nopermissiontoviewvideo', 'assignsubmission_cloudflarestream');
}

/**
 * Check if a user can view a specific submission.
 *
 * @param int $user_id The user ID
 * @param int $submission_id The submission ID
 * @return bool True if user can view the submission
 */
function can_view_submission($user_id, $submission_id) {
    global $DB;

    // Get the submission record
    $submission = $DB->get_record('assign_submission', array('id' => $submission_id));
    
    if (!$submission) {
        return false;
    }
    
    // Get the assignment
    $assignment = $DB->get_record('assign', array('id' => $submission->assignment));
    
    if (!$assignment) {
        return false;
    }
    
    // Get the course module
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false);
    
    if (!$cm) {
        return false;
    }
    
    // Get the context
    $context = context_module::instance($cm->id);
    
    // Check if user is the submission owner
    if ($submission->userid == $user_id) {
        return true;
    }
    
    // Check if user has grading capability (teacher or admin)
    if (has_capability('mod/assign:grade', $context, $user_id)) {
        return true;
    }
    
    // Check if user is an admin
    if (is_siteadmin($user_id)) {
        return true;
    }
    
    return false;
}
