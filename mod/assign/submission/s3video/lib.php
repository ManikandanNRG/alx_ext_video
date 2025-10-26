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
 * Library class for S3 + CloudFront video assignment submission plugin.
 *
 * @package   assignsubmission_s3video
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Require the parent class.
require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * Library class for S3 video assignment submission plugin.
 *
 * @package   assignsubmission_s3video
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_s3video extends assign_submission_plugin {

    /**
     * Get the name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('s3video', 'assignsubmission_s3video');
    }

    /**
     * Get the settings for the plugin.
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG;

        // Default to enabled (1) if not explicitly set.
        $defaultenabled = $this->get_config('enabled');
        if ($defaultenabled === false || $defaultenabled === null) {
            $defaultenabled = 1;
        }
        
        $mform->addElement('selectyesno', 'assignsubmission_s3video_enabled',
            get_string('enabled', 'assignsubmission_s3video'));
        $mform->addHelpButton('assignsubmission_s3video_enabled',
            'enabled', 'assignsubmission_s3video');
        $mform->setDefault('assignsubmission_s3video_enabled', $defaultenabled);
    }

    /**
     * Check if the plugin is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        // Get the enabled config, default to 1 (enabled) if not set.
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
        // Default to enabled (1) if not explicitly set.
        $defaultenabled = $this->get_config('enabled');
        if ($defaultenabled === false || $defaultenabled === null) {
            $defaultenabled = 1;
        }
        $mform->setDefault('assignsubmission_s3video_enabled', $defaultenabled);
    }

    /**
     * Check if the plugin is properly configured with AWS credentials.
     *
     * @return bool
     */
    protected function is_configured() {
        $accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
        $secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
        $bucket = get_config('assignsubmission_s3video', 's3_bucket');
        $region = get_config('assignsubmission_s3video', 's3_region');
        $cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');

        return !empty($accesskey) && !empty($secretkey) && !empty($bucket) && 
               !empty($region) && !empty($cloudfrontdomain);
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

        // Check if s3_key is provided in the form data.
        if (empty($data->s3video_s3_key)) {
            return true; // No video to save.
        }

        $s3key = $data->s3video_s3_key;
        $s3bucket = isset($data->s3video_s3_bucket) ? $data->s3video_s3_bucket : 
                    get_config('assignsubmission_s3video', 's3_bucket');
        $uploadstatus = isset($data->s3video_status) ? $data->s3video_status : 'ready';

        // Check if a record already exists for this submission.
        $existing = $DB->get_record('assignsubmission_s3video', 
            array('submission' => $submission->id));

        if ($existing) {
            // Update existing record.
            $existing->s3_key = $s3key;
            $existing->s3_bucket = $s3bucket;
            $existing->upload_status = $uploadstatus;
            
            if (isset($data->s3video_file_size)) {
                $existing->file_size = $data->s3video_file_size;
            }
            if (isset($data->s3video_duration)) {
                $existing->duration = $data->s3video_duration;
            }
            if (isset($data->s3video_mime_type)) {
                $existing->mime_type = $data->s3video_mime_type;
            }
            if (isset($data->s3video_error_message)) {
                $existing->error_message = $data->s3video_error_message;
            }

            return $DB->update_record('assignsubmission_s3video', $existing);
        } else {
            // Create new record.
            $record = new stdClass();
            $record->assignment = $this->assignment->get_instance()->id;
            $record->submission = $submission->id;
            $record->s3_key = $s3key;
            $record->s3_bucket = $s3bucket;
            $record->upload_status = $uploadstatus;
            $record->upload_timestamp = time();
            
            if (isset($data->s3video_file_size)) {
                $record->file_size = $data->s3video_file_size;
            }
            if (isset($data->s3video_duration)) {
                $record->duration = $data->s3video_duration;
            }
            if (isset($data->s3video_mime_type)) {
                $record->mime_type = $data->s3video_mime_type;
            }
            if (isset($data->s3video_error_message)) {
                $record->error_message = $data->s3video_error_message;
            }

            return $DB->insert_record('assignsubmission_s3video', $record) > 0;
        }
    }

    /**
     * Display a summary of the submission in the grading table.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view the full submission
     * @return string HTML to display
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB, $CFG;

        // Get the video record for this submission.
        $video = $DB->get_record('assignsubmission_s3video', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        // Display status with icon.
        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_s3video');
        
        $icon = '';
        $output = '';
        
        switch ($video->upload_status) {
            case 'ready':
                // Create a link to view the video in a new tab.
                $viewurl = new moodle_url('/mod/assign/submission/s3video/view_video.php', [
                    'id' => $submission->id,
                    's3key' => $video->s3_key
                ]);
                
                $icon = '<i class="fa fa-video-camera text-success" aria-hidden="true"></i>';
                $output = html_writer::link(
                    $viewurl,
                    $icon . ' ' . $statustext,
                    [
                        'target' => '_blank',
                        'title' => get_string('watchvideo', 'assignsubmission_s3video'),
                        'class' => 's3video-watch-link'
                    ]
                );
                
                // Add file size if available.
                if ($video->file_size) {
                    $output .= ' (' . display_size($video->file_size) . ')';
                }
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
     * Display the submission for grading/review.
     *
     * @param stdClass $submission The submission object
     * @param bool $showviewlink Whether to show a link to view the full submission
     * @return string HTML to display
     */
    public function view(stdClass $submission, $showviewlink = true) {
        global $DB, $OUTPUT;

        // Get the video record for this submission.
        $video = $DB->get_record('assignsubmission_s3video', 
            array('submission' => $submission->id));

        if (!$video) {
            return '';
        }

        $output = '';
        
        // Add a visible header to confirm this method is being called.
        $output .= html_writer::start_div('s3video-submission-container', ['style' => 'border: 2px solid #0f6cbf; padding: 15px; margin: 10px 0; border-radius: 5px; background-color: #f9f9f9;']);
        $output .= html_writer::tag('h3', get_string('s3video', 'assignsubmission_s3video'), ['style' => 'margin-top: 0; color: #0f6cbf;']);

        // Display video status.
        $statuskey = 'status_' . $video->upload_status;
        $statustext = get_string($statuskey, 'assignsubmission_s3video');
        
        $output .= html_writer::tag('div', 
            html_writer::tag('strong', get_string('status', 'core') . ': ') . 
            $statustext,
            array('class' => 's3video-status mb-3')
        );

        // If video is ready, display the player using the template.
        if ($video->upload_status === 'ready') {
            // Generate unique container ID for this player instance.
            $containerid = 's3video-player-' . $submission->id . '-' . uniqid();
            
            // Prepare template context.
            $context = [
                's3key' => $video->s3_key,
                'submissionid' => $submission->id,
                'containerid' => $containerid
            ];
            
            // Render player template.
            $output .= $OUTPUT->render_from_template('assignsubmission_s3video/player', $context);

            // Add video metadata if available.
            if ($video->duration || $video->file_size) {
                $output .= html_writer::start_div('s3video-metadata mt-3');
                
                if ($video->duration) {
                    $duration = format_time($video->duration);
                    $output .= html_writer::tag('div', 
                        html_writer::tag('strong', get_string('duration', 'core') . ': ') . $duration,
                        array('class' => 's3video-metadata-item')
                    );
                }
                
                if ($video->file_size) {
                    $filesize = display_size($video->file_size);
                    $output .= html_writer::tag('div', 
                        html_writer::tag('strong', get_string('size', 'core') . ': ') . $filesize,
                        array('class' => 's3video-metadata-item')
                    );
                }
                
                $output .= html_writer::end_div();
            }
        } else if ($video->upload_status === 'pending') {
            // Video is still uploading.
            $output .= html_writer::tag('div', 
                get_string('videopending', 'assignsubmission_s3video'),
                array('class' => 'alert alert-info')
            );
        } else if ($video->upload_status === 'error' && !empty($video->error_message)) {
            // Display error message.
            $output .= html_writer::tag('div', 
                $video->error_message,
                array('class' => 'alert alert-danger')
            );
        } else if ($video->upload_status === 'deleted') {
            // Video has been deleted.
            $output .= html_writer::tag('div', 
                get_string('videonotavailable', 'assignsubmission_s3video'),
                array('class' => 'alert alert-warning')
            );
        }
        
        // Close the container div.
        $output .= html_writer::end_div();

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

        // Check if plugin is properly configured.
        if (!$this->is_configured()) {
            $mform->addElement('static', 's3video_error', '',
                html_writer::tag('div', 
                    get_string('config_missing', 'assignsubmission_s3video'),
                    array('class' => 'alert alert-danger')
                )
            );
            return true;
        }

        // Get existing video if any.
        $video = null;
        if ($submission) {
            $video = $DB->get_record('assignsubmission_s3video', 
                array('submission' => $submission->id));
        }

        // Add upload interface container.
        $mform->addElement('static', 's3video_upload_container', 
            get_string('uploadvideofile', 'assignsubmission_s3video'),
            $this->get_upload_interface_html($video)
        );

        // Hidden fields to store video data.
        $mform->addElement('hidden', 's3video_s3_key');
        $mform->setType('s3video_s3_key', PARAM_TEXT);
        
        $mform->addElement('hidden', 's3video_s3_bucket');
        $mform->setType('s3video_s3_bucket', PARAM_TEXT);
        
        $mform->addElement('hidden', 's3video_status');
        $mform->setType('s3video_status', PARAM_TEXT);
        
        $mform->addElement('hidden', 's3video_file_size');
        $mform->setType('s3video_file_size', PARAM_INT);
        
        $mform->addElement('hidden', 's3video_duration');
        $mform->setType('s3video_duration', PARAM_INT);
        
        $mform->addElement('hidden', 's3video_mime_type');
        $mform->setType('s3video_mime_type', PARAM_TEXT);
        
        $mform->addElement('hidden', 's3video_error_message');
        $mform->setType('s3video_error_message', PARAM_TEXT);

        // Set default values if video exists.
        if ($video) {
            $mform->setDefault('s3video_s3_key', $video->s3_key);
            $mform->setDefault('s3video_s3_bucket', $video->s3_bucket);
            $mform->setDefault('s3video_status', $video->upload_status);
            $mform->setDefault('s3video_file_size', $video->file_size);
            $mform->setDefault('s3video_duration', $video->duration);
            $mform->setDefault('s3video_mime_type', $video->mime_type);
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

        // Prepare template context.
        $context = [
            'assignmentid' => $this->assignment->get_instance()->id,
            'maxfilesize' => 5368709120, // 5 GB in bytes
            'acceptedtypes' => 'video/*'
        ];

        // Add existing video info if available.
        if ($video) {
            $context['hasvideo'] = true;
            $context['s3key'] = $video->s3_key;
            $context['uploadstatus'] = $video->upload_status;
            
            if ($video->file_size) {
                $context['filesize'] = display_size($video->file_size);
            }
            if ($video->duration) {
                $context['duration'] = format_time($video->duration);
            }
        } else {
            $context['hasvideo'] = false;
        }

        // Render upload form template.
        return $OUTPUT->render_from_template('assignsubmission_s3video/upload_form', $context);
    }

    /**
     * Check if the submission has data.
     *
     * @param stdClass $submission The submission object
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        global $DB;
        
        $video = $DB->get_record('assignsubmission_s3video', 
            array('submission' => $submission->id));
        
        return empty($video) || empty($video->s3_key);
    }

    /**
     * Get file areas for this plugin.
     *
     * @return array
     */
    public function get_file_areas() {
        return array(); // No file areas as videos are stored in S3.
    }

    /**
     * Copy submission data from one submission to another.
     *
     * @param stdClass $sourcesubmission Source submission
     * @param stdClass $destsubmission Destination submission
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        $sourcevideo = $DB->get_record('assignsubmission_s3video',
            array('submission' => $sourcesubmission->id));

        if ($sourcevideo) {
            unset($sourcevideo->id);
            $sourcevideo->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_s3video', $sourcevideo);
        }

        return true;
    }

    /**
     * Remove submission data.
     *
     * @param stdClass $submission The submission object
     * @return bool
     */
    public function remove(stdClass $submission) {
        global $DB;

        $DB->delete_records('assignsubmission_s3video',
            array('submission' => $submission->id));

        return true;
    }
}
