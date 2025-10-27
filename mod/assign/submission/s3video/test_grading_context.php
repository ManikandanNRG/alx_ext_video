<?php
/**
 * Test script to verify grading context detection
 *
 * @package   assignsubmission_s3video
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/s3video/test_grading_context.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test Grading Context Detection');
$PAGE->set_heading('Test Grading Context Detection');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Grading Context Detection Test');

// Test different URL patterns
$test_cases = [
    [
        'description' => 'Grading interface (action=grader)',
        'url' => '/mod/assign/view.php?id=123&action=grader',
        'expected' => true
    ],
    [
        'description' => 'Grading interface (action=grade)',
        'url' => '/mod/assign/view.php?id=123&action=grade',
        'expected' => true
    ],
    [
        'description' => 'Grading interface (action=grading)',
        'url' => '/mod/assign/view.php?id=123&action=grading',
        'expected' => true
    ],
    [
        'description' => 'Submission page (no action)',
        'url' => '/mod/assign/view.php?id=123',
        'expected' => false
    ],
    [
        'description' => 'Submission page (action=view)',
        'url' => '/mod/assign/view.php?id=123&action=view',
        'expected' => false
    ],
    [
        'description' => 'Submission page (action=editsubmission)',
        'url' => '/mod/assign/view.php?id=123&action=editsubmission',
        'expected' => false
    ]
];

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Test Case');
echo html_writer::tag('th', 'URL Pattern');
echo html_writer::tag('th', 'Expected Result');
echo html_writer::tag('th', 'Detection Logic');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($test_cases as $test) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $test['description']);
    echo html_writer::tag('td', html_writer::tag('code', $test['url']));
    echo html_writer::tag('td', $test['expected'] ? 
        html_writer::tag('span', 'Grading Context', ['class' => 'badge badge-success']) : 
        html_writer::tag('span', 'Submission Context', ['class' => 'badge badge-info'])
    );
    
    // Parse URL to extract action parameter
    $url_parts = parse_url($test['url']);
    parse_str($url_parts['query'] ?? '', $params);
    $action = $params['action'] ?? '';
    
    $is_grading = in_array($action, ['grader', 'grade', 'grading']);
    
    $logic = 'Action parameter: ' . ($action ? $action : '(none)') . '<br>';
    $logic .= 'Is grading: ' . ($is_grading ? 'YES' : 'NO');
    
    echo html_writer::tag('td', $logic);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::start_div('alert alert-info mt-4');
echo html_writer::tag('h4', 'How It Works');
echo html_writer::tag('p', 'The <code>is_grading_context()</code> method detects the context by checking:');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'URL action parameter (grader, grade, grading)');
echo html_writer::tag('li', 'Page path contains /mod/assign/view.php');
echo html_writer::tag('li', 'Page body classes contain grading-related classes');
echo html_writer::tag('li', 'Presence of rownum parameter (used in grading navigation)');
echo html_writer::end_tag('ol');
echo html_writer::end_div();

echo html_writer::start_div('alert alert-success mt-4');
echo html_writer::tag('h4', 'Implementation Summary');
echo html_writer::tag('p', '<strong>Grading Page:</strong> Shows full-width video player (like PDF annotation)');
echo html_writer::tag('p', '<strong>Submission Page:</strong> Shows boxed view with status (original behavior)');
echo html_writer::end_div();

echo html_writer::start_div('alert alert-warning mt-4');
echo html_writer::tag('h4', 'Testing Instructions');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Create an assignment with S3 Video submission enabled');
echo html_writer::tag('li', 'As a student, submit a video');
echo html_writer::tag('li', 'View your submission - should see boxed view with blue border');
echo html_writer::tag('li', 'As a teacher, go to grading interface');
echo html_writer::tag('li', 'Click "Grade" on a submission - should see full-width video player');
echo html_writer::end_tag('ol');
echo html_writer::end_div();

echo $OUTPUT->footer();
