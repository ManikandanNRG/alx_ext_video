<?php
/**
 * Get submission IDs for testing
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/s3video/get_submission_id.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Get Submission IDs');

echo $OUTPUT->header();
echo html_writer::tag('h2', 'S3 Video Submissions');

// Get all submissions with videos
$sql = "SELECT s.id, s.assignment, s.userid, s.status, v.s3_key, v.upload_status, v.file_size
        FROM {assign_submission} s
        JOIN {assignsubmission_s3video} v ON v.submission = s.id
        ORDER BY s.id DESC
        LIMIT 20";

$submissions = $DB->get_records_sql($sql);

if (empty($submissions)) {
    echo html_writer::div('No submissions found', 'alert alert-warning');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Submission ID');
    echo html_writer::tag('th', 'Assignment ID');
    echo html_writer::tag('th', 'User ID');
    echo html_writer::tag('th', 'Status');
    echo html_writer::tag('th', 'Video Status');
    echo html_writer::tag('th', 'File Size');
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($submissions as $sub) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $sub->id);
        echo html_writer::tag('td', $sub->assignment);
        echo html_writer::tag('td', $sub->userid);
        echo html_writer::tag('td', $sub->status);
        echo html_writer::tag('td', $sub->upload_status);
        echo html_writer::tag('td', $sub->file_size ? display_size($sub->file_size) : 'N/A');
        
        $debug_url = new moodle_url('/mod/assign/submission/s3video/debug_grading_view.php', ['id' => $sub->id]);
        $actions = html_writer::link($debug_url, 'Debug', ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
        
        echo html_writer::tag('td', $actions);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
