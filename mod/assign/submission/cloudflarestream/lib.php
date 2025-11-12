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

        // HIDDEN: Duplicate "enabled" dropdown removed to avoid confusion
        // The toggle switch in "Submission types" section (created by Moodle core) is sufficient
        // Both controls write to the same database location (assign_plugin_config.enabled)
        // To restore: uncomment the code below
        
        /*
        // Default to enabled (1) if not explicitly set
        $defaultenabled = $this->get_config('enabled');
        if ($defaultenabled === false || $defaultenabled === null) {
            $defaultenabled = 1;
        }
        
        $mform->addElement('selectyesno', 'assignsubmission_cloudflarestream_enabled',
            get_string('enabled', 'assignsubmission_cloudflarestream'));
        $mform->addHelpButton('assignsubmission_cloudflarestream_enabled',
            'enabled', 'assignsubmission_cloudflarestream');
        $mform->setDefault('assignsubmission_cloudflarestream_enabled', $defaultenabled);
        */
        
        // Future: Add useful per-assignment settings here (e.g., max duration, file size limits)
    }

    /**
     * Check if the plugin is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        // Get the enabled config, default to 1 (enabled) if not set
        $enabled = $this->get_config('enabled');
        return $enabled !== false ? (bool)$enabled : true;
    }

    /**
     * Get the default setting for this plugin.
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @param MoodleQuickForm $data The form data
     * @param array $files The files
     * @return void
     */
    public function get_default_setting(MoodleQuickForm $mform, $data, $files) {
        // Default to enabled (1) if not explicitly set
        $defaultenabled = $this->get_config('enabled');
        if ($defaultenabled === false || $defaultenabled === null) {
            $defaultenabled = 1;
        }
        $mform->setDefault('assignsubmission_cloudflarestream_enabled', $defaultenabled);
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
            // Note: Old video deletion is handled in confirm_upload.php
            // because by the time save() runs, the database already has the new UID
            
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
        global $DB, $OUTPUT, $PAGE;

        // Get the video record for this submission
        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        // Detect if we're in the grading interface
        $is_grading = $this->is_grading_context();

        $output = '';
        
        // GRADING INTERFACE: Show full-width video player
        if ($is_grading && $video->upload_status === 'ready') {
            // Generate unique container ID for this player instance
            $containerid = 'cloudflarestream-player-' . $submission->id . '-' . uniqid();
            
            // Prepare template context
            $context = [
                'videouid' => $video->video_uid,
                'submissionid' => $submission->id,
                'containerid' => $containerid
            ];
            
            // Render player template (full-width, no container)
            $output .= html_writer::start_div('cloudflarestream-grading-view');
            $output .= $OUTPUT->render_from_template('assignsubmission_cloudflarestream/player', $context);
            
            // Add video metadata below player
            if ($video->duration || $video->file_size) {
                $output .= html_writer::start_div('cloudflarestream-metadata-display mt-3');
                
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
            
            $output .= html_writer::end_div();
            
            return $output;
        }
        
        // GRADING INTERFACE: Show status for non-ready videos
        if ($is_grading) {
            $statuskey = 'status_' . $video->upload_status;
            $statustext = get_string($statuskey, 'assignsubmission_cloudflarestream');
            
            $output .= html_writer::start_div('cloudflarestream-grading-status');
            $output .= html_writer::tag('p', $statustext, ['class' => 'alert alert-info']);
            
            if ($video->upload_status === 'error' && !empty($video->error_message)) {
                $output .= html_writer::tag('p', $video->error_message, ['class' => 'alert alert-danger']);
            }
            
            $output .= html_writer::end_div();
            
            return $output;
        }
        
        // SUBMISSION PAGE: Show boxed view with status (original behavior)
        $output .= html_writer::start_div('cloudflarestream-submission-container', ['style' => 'border: 2px solid #0f6cbf; padding: 15px; margin: 10px 0; border-radius: 5px; background-color: #f9f9f9;']);
        $output .= html_writer::tag('h3', get_string('cloudflarestream', 'assignsubmission_cloudflarestream'), ['style' => 'margin-top: 0; color: #0f6cbf;']);

        // Display video status
        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_cloudflarestream');
        
        $output .= html_writer::tag('div', 
            html_writer::tag('strong', get_string('status', 'core') . ': ') . 
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
        } else if ($video->upload_status === 'pending') {
            // Video is still uploading
            $output .= html_writer::tag('div', 
                get_string('videopending', 'assignsubmission_cloudflarestream'),
                array('class' => 'alert alert-info')
            );
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
        
        // Close the container div
        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Detect if we're in the grading context (teacher grading) vs submission context (student viewing).
     *
     * @return bool True if in grading context
     */
    protected function is_grading_context() {
        global $PAGE;
        
        // Check if we're on the INDIVIDUAL grading page (not the grading table)
        $action = optional_param('action', '', PARAM_ALPHA);
        $rownum = optional_param('rownum', -1, PARAM_INT);
        $userid = optional_param('userid', 0, PARAM_INT);
        
        // Individual grading page has action=grader AND (rownum OR userid)
        if ($action === 'grader' && ($rownum >= 0 || $userid > 0)) {
            return true;
        }
        
        // Also check for action=grade (some Moodle versions use this)
        if ($action === 'grade' && $userid > 0) {
            return true;
        }
        
        return false;
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

        // Get allowed formats from settings
        $allowedformats = get_config('assignsubmission_cloudflarestream', 'allowed_formats');
        if (empty($allowedformats)) {
            // Default formats if not configured
            $allowedformats = "video/mp4\nvideo/quicktime\nvideo/x-msvideo\nvideo/x-matroska\nvideo/webm\nvideo/mpeg\nvideo/ogg\nvideo/3gpp\nvideo/x-flv";
        }
        
        // Prepare template context
        $context = [
            'assignmentid' => $this->assignment->get_instance()->id,
            'submissionid' => 0,
            'maxfilesize' => $this->get_max_file_size(),
            'maxfilesizeformatted' => display_size($this->get_max_file_size()),
            'allowedformats' => json_encode(array_filter(array_map('trim', explode("\n", $allowedformats)))),
            'hasvideo' => !empty($video),
        ];

        // Add video information if available
        if ($video) {
            $context['videostatus'] = $video->upload_status;
            $context['videouid'] = $video->video_uid;
            $context['videostatus_ready'] = ($video->upload_status === 'ready');
            
            // Get translated status text
            $statuskey = 'status_' . $video->upload_status;
            $context['videostatustext'] = get_string($statuskey, 'assignsubmission_cloudflarestream');
            
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
     * @return int Retention period in days (-1 = always keep, 0 or empty = invalid, >0 = days)
     */
    public function get_retention_days() {
        $days = get_config('assignsubmission_cloudflarestream', 'retention_days');
        // Handle explicit values including -1 (always keep)
        if ($days !== false && $days !== null && $days !== '') {
            return (int)$days;
        }
        return 90; // Default 90 days
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
        global $DB, $CFG;

        // Get the video record before deleting from database
        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));
        
        // Delete video from Cloudflare if it exists
        if ($video && !empty($video->video_uid)) {
            error_log("Cloudflare remove(): Deleting video {$video->video_uid} for removed submission {$submission->id}");
            
            try {
                $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
                $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
                
                if (!empty($apitoken) && !empty($accountid)) {
                    require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
                    $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
                    $client->delete_video($video->video_uid);
                    
                    error_log("Cloudflare remove(): ✓ Successfully deleted video {$video->video_uid} from Cloudflare");
                }
            } catch (\assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception $e) {
                error_log("Cloudflare remove(): Video {$video->video_uid} already deleted (404)");
            } catch (Exception $e) {
                error_log("Cloudflare remove(): ✗ Failed to delete video {$video->video_uid}: " . $e->getMessage());
            }
        }

        // Remove the database record
        return $DB->delete_records('assignsubmission_cfstream', 
            array('submission' => $submission->id));
    }

    /**
     * Display a summary of the submission in the grading table.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view the full submission
     * @return string HTML to display
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB, $CFG, $OUTPUT, $PAGE;

        // Get the video record for this submission
        $video = $DB->get_record('assignsubmission_cfstream', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        // TASK 4: Check Cloudflare for updated status if video is not ready
        // This fixes videos stuck in "uploading" or "pending" status
        if (($video->upload_status === 'uploading' || $video->upload_status === 'pending') && !empty($video->video_uid)) {
            // Only check if at least 60 seconds have passed since upload (avoid too frequent API calls)
            if (time() - $video->upload_timestamp > 60) {
                try {
                    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
                    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
                    
                    if (!empty($apitoken) && !empty($accountid)) {
                        require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
                        
                        $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
                        $details = $client->get_video_details($video->video_uid);
                        
                        // Update DB if status changed to ready
                        if (isset($details->readyToStream) && $details->readyToStream === true) {
                            $video->upload_status = 'ready';
                            
                            // Update metadata if available
                            if (isset($details->duration)) {
                                $video->duration = (int)$details->duration;
                            }
                            if (isset($details->size)) {
                                $video->file_size = (int)$details->size;
                            }
                            
                            $DB->update_record('assignsubmission_cfstream', $video);
                            
                            // Log the status update for debugging
                            error_log("Cloudflare video {$video->video_uid} status updated to ready on page view");
                        }
                    }
                } catch (\assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception $e) {
                    // Video was deleted from Cloudflare
                    $video->upload_status = 'deleted';
                    $video->deleted_timestamp = time();
                    $video->error_message = 'Video not found in Cloudflare';
                    $DB->update_record('assignsubmission_cfstream', $video);
                    error_log("Cloudflare video {$video->video_uid} not found, marked as deleted");
                } catch (Exception $e) {
                    // Silently fail, will try again next time page is viewed
                    error_log("Failed to check Cloudflare status for video {$video->video_uid}: " . $e->getMessage());
                }
            }
        }

        // Load JavaScript for grading interface injection
        $PAGE->requires->js_call_amd('assignsubmission_cloudflarestream/grading_injector', 'init');

        // Check if we're in grading context
        $is_grading = $this->is_grading_context();
        
        if ($is_grading && $video->upload_status === 'ready') {
            // In grading interface, show the full video player
            $containerid = 'cloudflarestream-player-' . $submission->id . '-' . uniqid();
            
            $context = [
                'videouid' => $video->video_uid,
                'submissionid' => $submission->id,
                'containerid' => $containerid
            ];
            
            $output = html_writer::start_div('cloudflarestream-grading-view');
            $output .= $OUTPUT->render_from_template('assignsubmission_cloudflarestream/player', $context);
            
            // Add metadata below player
            if ($video->duration || $video->file_size) {
                $output .= html_writer::start_div('cloudflarestream-metadata-display mt-3');
                
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
            
            $output .= html_writer::end_div();
            
            return $output;
        }

        // For grading table or non-ready videos, show summary with icon
        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_cloudflarestream');
        
        $icon = '';
        $output = '';
        
        switch ($video->upload_status) {
            case 'ready':
                // Create a link to view the video in a new tab.
                $viewurl = new moodle_url('/mod/assign/submission/cloudflarestream/view_video.php', [
                    'id' => $submission->id,
                    'video_uid' => $video->video_uid
                ]);
                
                $icon = '<i class="fa fa-video-camera text-success" aria-hidden="true"></i>';
                
                // Get filename from Cloudflare metadata or use UID as fallback
                $filename = 'Video_' . substr($video->video_uid, 0, 8); // Default fallback
                
                // Try to get real filename from Cloudflare metadata
                try {
                    $apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
                    $accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
                    
                    if (!empty($apitoken) && !empty($accountid)) {
                        require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');
                        $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
                        $details = $client->get_video_details($video->video_uid);
                        
                        // Get filename from metadata
                        if (isset($details->meta->name) && !empty($details->meta->name)) {
                            $filename = $details->meta->name;
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail, use fallback filename
                }
                
                $truncated_filename = $this->truncate_filename($filename, 25);
                
                // Build multi-line display
                $output = html_writer::start_div('cfstream-grading-summary');
                
                // Line 1: Icon + Filename
                $output .= html_writer::div(
                    $icon . ' ' . html_writer::span($truncated_filename, 'cfstream-filename'),
                    'cfstream-title-line'
                );
                
                // Line 2: Status badge + Size
                $status_badge = '<i class="fa fa-check-circle" aria-hidden="true" style="font-size: 11px; color: #28a745;"></i>';
                $size_text = $video->file_size ? display_size($video->file_size) : '';
                $meta_content = html_writer::span($status_badge . ' ' . $statustext, 'cfstream-status');
                if ($size_text) {
                    $meta_content .= ' • ' . html_writer::span($size_text, 'cfstream-size');
                }
                $output .= html_writer::div($meta_content, 'cfstream-meta-line');
                
                $output .= html_writer::end_div();
                
                // Make entire block clickable
                $output = html_writer::link(
                    $viewurl,
                    $output,
                    [
                        'target' => '_blank',
                        'title' => $filename . ' - ' . get_string('watchvideo', 'assignsubmission_cloudflarestream'),
                        'class' => 'cfstream-grading-link'
                    ]
                );
                break;
                
            case 'uploading':
                $icon = '<i class="fa fa-clock-o text-warning" aria-hidden="true"></i> ';
                $output = $icon . $statustext;
                
                // TASK 6: Add helpful message telling user to refresh
                $output .= '<br><small class="text-muted">';
                $output .= get_string('video_processing_message', 'assignsubmission_cloudflarestream');
                $output .= '</small>';
                break;
                
            case 'pending':
                $icon = '<i class="fa fa-clock-o text-warning" aria-hidden="true"></i> ';
                $output = $icon . $statustext;
                break;
                
            case 'error':
                $icon = '<i class="fa fa-exclamation-triangle text-danger" aria-hidden="true"></i> ';
                $output = $icon . $statustext;
                break;
                
            case 'deleted':
                $icon = '<i class="fa fa-trash text-muted" aria-hidden="true"></i> ';
                $output = $icon . $statustext;
                break;
        }
        
        return $output;
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

    /**
     * Truncate filename for display in grading table.
     *
     * @param string $filename The filename to truncate
     * @param int $maxlength Maximum length before truncation
     * @return string Truncated filename with ellipsis if needed
     */
    private function truncate_filename($filename, $maxlength = 25) {
        if (strlen($filename) <= $maxlength) {
            return $filename;
        }
        
        // Try to preserve file extension
        $extension = '';
        if (preg_match('/\.(mp4|mov|avi|mkv|webm|flv)$/i', $filename, $matches)) {
            $extension = $matches[0];
            $filename = substr($filename, 0, -strlen($extension));
        }
        
        // Truncate and add ellipsis
        $truncated = substr($filename, 0, $maxlength - strlen($extension) - 3) . '...';
        
        return $truncated . $extension;
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
