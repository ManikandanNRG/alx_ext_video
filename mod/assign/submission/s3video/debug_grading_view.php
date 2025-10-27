<?php
/**
 * Debug script to check what's happening in the grading view
 *
 * @package   assignsubmission_s3video
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Get parameters first
$id = optional_param('id', 0, PARAM_INT); // Submission ID

if (!$id) {
    echo "Usage: debug_grading_view.php?id=[submission_id]<br>";
    echo "Example: debug_grading_view.php?id=123<br>";
    die();
}

require_login();

$PAGE->set_url(new moodle_url('/mod/assign/submission/s3video/debug_grading_view.php', ['id' => $id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Debug Grading View');
$PAGE->set_heading('Debug Grading View');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Debug: Grading View for Submission ID: ' . $id);

// Get submission
$submission = $DB->get_record('assign_submission', ['id' => $id], '*', MUST_EXIST);

echo html_writer::start_div('alert alert-info');
echo html_writer::tag('h4', 'Submission Details');
echo html_writer::tag('p', '<strong>ID:</strong> ' . $submission->id);
echo html_writer::tag('p', '<strong>Assignment:</strong> ' . $submission->assignment);
echo html_writer::tag('p', '<strong>User ID:</strong> ' . $submission->userid);
echo html_writer::tag('p', '<strong>Status:</strong> ' . $submission->status);
echo html_writer::end_div();

// Get video record
$video = $DB->get_record('assignsubmission_s3video', ['submission' => $submission->id]);

if (!$video) {
    echo html_writer::div('No video record found for this submission', 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('alert alert-success');
echo html_writer::tag('h4', 'Video Record Details');
echo html_writer::tag('p', '<strong>S3 Key:</strong> ' . $video->s3_key);
echo html_writer::tag('p', '<strong>Upload Status:</strong> ' . $video->upload_status);
echo html_writer::tag('p', '<strong>File Size:</strong> ' . ($video->file_size ? display_size($video->file_size) : 'N/A'));
echo html_writer::tag('p', '<strong>Duration:</strong> ' . ($video->duration ? format_time($video->duration) : 'N/A'));
echo html_writer::end_div();

// Get assignment
$assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Create assign object
$assign = new assign($context, $cm, $assignment->course);

// Get plugin instance
$plugin = $assign->get_submission_plugin_by_type('s3video');

if (!$plugin) {
    echo html_writer::div('Plugin not found', 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('alert alert-warning');
echo html_writer::tag('h4', 'Context Detection Test');

// Simulate grading context
$_GET['action'] = 'grader';

// Call is_grading_context using reflection
$reflection = new ReflectionClass($plugin);
$method = $reflection->getMethod('is_grading_context');
$method->setAccessible(true);
$is_grading = $method->invoke($plugin);

echo html_writer::tag('p', '<strong>Action Parameter:</strong> ' . optional_param('action', 'none', PARAM_ALPHA));
echo html_writer::tag('p', '<strong>Is Grading Context:</strong> ' . ($is_grading ? 'YES' : 'NO'));
echo html_writer::tag('p', '<strong>Page URL:</strong> ' . $PAGE->url->out());
echo html_writer::tag('p', '<strong>Page Path:</strong> ' . $PAGE->url->get_path());
echo html_writer::end_div();

// Test view() method
echo html_writer::start_div('alert alert-primary');
echo html_writer::tag('h4', 'View Method Output');
echo html_writer::tag('p', 'Calling view() method with grading context...');
echo html_writer::end_div();

echo html_writer::start_div('border p-3 bg-light');
$view_output = $plugin->view($submission);
echo $view_output;
echo html_writer::end_div();

echo html_writer::start_div('alert alert-info mt-3');
echo html_writer::tag('h4', 'Raw HTML Output');
echo html_writer::tag('pre', htmlspecialchars($view_output));
echo html_writer::end_div();

// Check if player template exists
$template_path = $CFG->dirroot . '/mod/assign/submission/s3video/templates/player.mustache';
echo html_writer::start_div('alert ' . (file_exists($template_path) ? 'alert-success' : 'alert-danger'));
echo html_writer::tag('h4', 'Template Check');
echo html_writer::tag('p', '<strong>Template Path:</strong> ' . $template_path);
echo html_writer::tag('p', '<strong>Exists:</strong> ' . (file_exists($template_path) ? 'YES' : 'NO'));
echo html_writer::end_div();

// Check CSS
$css_path = $CFG->dirroot . '/mod/assign/submission/s3video/styles.css';
echo html_writer::start_div('alert ' . (file_exists($css_path) ? 'alert-success' : 'alert-danger'));
echo html_writer::tag('h4', 'CSS Check');
echo html_writer::tag('p', '<strong>CSS Path:</strong> ' . $css_path);
echo html_writer::tag('p', '<strong>Exists:</strong> ' . (file_exists($css_path) ? 'YES' : 'NO'));
if (file_exists($css_path)) {
    $css_content = file_get_contents($css_path);
    $has_grading_styles = strpos($css_content, 's3video-grading-view') !== false;
    echo html_writer::tag('p', '<strong>Has Grading Styles:</strong> ' . ($has_grading_styles ? 'YES' : 'NO'));
}
echo html_writer::end_div();

echo html_writer::start_div('alert alert-warning mt-4');
echo html_writer::tag('h4', 'Next Steps');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Clear Moodle caches: <code>php admin/cli/purge_caches.php</code>');
echo html_writer::tag('li', 'Go to actual grading page: Assignment → View all submissions → Grade');
echo html_writer::tag('li', 'Check browser console for JavaScript errors');
echo html_writer::tag('li', 'Check if video status is "ready"');
echo html_writer::end_tag('ol');
echo html_writer::end_div();

echo $OUTPUT->footer();
