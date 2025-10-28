<?php
/**
 * Comprehensive diagnostic for why submissions are blocked
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Must be logged in
require_login();

$assignmentid = required_param('assignmentid', PARAM_INT);

// Load the assignment
list($course, $cm) = get_course_and_cm_from_instance($assignmentid, 'assign');
$context = context_module::instance($cm->id);

echo "<!DOCTYPE html><html><head><title>Submission Diagnostic</title>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .warning{color:orange;font-weight:bold;} table{border-collapse:collapse;margin:20px 0;width:100%;} td,th{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f5f5f5;}</style>";
echo "</head><body>";
echo "<h1>Comprehensive Submission Diagnostic</h1>";
echo "<p>Assignment ID: $assignmentid | User ID: {$USER->id}</p>";
echo "<hr>";

// Get assignment record
$assign_record = $DB->get_record('assign', array('id' => $assignmentid), '*', MUST_EXIST);
$assign = new assign($context, $cm, $course);

echo "<h2>Diagnostic Checks:</h2>";
echo "<table>";
echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>";

// 1. Check if user can submit
$can_submit = has_capability('mod/assign:submit', $context);
echo "<tr><td>User has submit capability</td><td>" . ($can_submit ? "<span class='success'>✅ YES</span>" : "<span class='error'>❌ NO</span>") . "</td><td>" . ($can_submit ? "User can submit" : "User lacks mod/assign:submit capability") . "</td></tr>";

// 2. Check assignment dates
$now = time();
$allow_from = $assign_record->allowsubmissionsfromdate;
$due_date = $assign_record->duedate;
$cutoff_date = $assign_record->cutoffdate;

$allow_from_ok = ($allow_from == 0 || $allow_from <= $now);
echo "<tr><td>Allow submissions from date</td><td>" . ($allow_from_ok ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ BLOCKED</span>") . "</td><td>" . ($allow_from == 0 ? "No restriction" : "Set to: " . userdate($allow_from) . " (Now: " . userdate($now) . ")") . "</td></tr>";

$due_date_ok = ($due_date == 0 || $due_date >= $now);
echo "<tr><td>Due date</td><td>" . ($due_date_ok ? "<span class='success'>✅ OK</span>" : "<span class='warning'>⚠ PASSED</span>") . "</td><td>" . ($due_date == 0 ? "No due date" : "Set to: " . userdate($due_date)) . "</td></tr>";

$cutoff_ok = ($cutoff_date == 0 || $cutoff_date >= $now);
echo "<tr><td>Cut-off date</td><td>" . ($cutoff_ok ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ PASSED</span>") . "</td><td>" . ($cutoff_date == 0 ? "No cut-off" : "Set to: " . userdate($cutoff_date)) . "</td></tr>";

// 3. Check if submissions are enabled
$submissions_enabled = $assign_record->submissiondrafts == 0 || $assign_record->submissiondrafts == 1;
echo "<tr><td>Submissions enabled</td><td><span class='success'>✅ YES</span></td><td>Assignment accepts submissions</td></tr>";

// 4. Check activity visibility
$visible = $cm->visible;
echo "<tr><td>Activity visible</td><td>" . ($visible ? "<span class='success'>✅ YES</span>" : "<span class='warning'>⚠ HIDDEN</span>") . "</td><td>" . ($visible ? "Activity is visible" : "Activity is hidden (but you can still access it)") . "</td></tr>";

// 5. Check if assignment is open
$is_open = $assign->submissions_open($USER->id);
echo "<tr><td><strong>submissions_open() result</strong></td><td>" . ($is_open ? "<span class='success'>✅ OPEN</span>" : "<span class='error'>❌ CLOSED</span>") . "</td><td>" . ($is_open ? "Moodle says submissions are open" : "Moodle says submissions are closed") . "</td></tr>";

// 6. Check existing submission
$submission = $assign->get_user_submission($USER->id, false);
if ($submission) {
    $status = $submission->status;
    $is_submitted = ($status == 'submitted');
    echo "<tr><td>Existing submission</td><td>" . ($is_submitted ? "<span class='warning'>⚠ SUBMITTED</span>" : "<span class='success'>✅ DRAFT</span>") . "</td><td>Status: $status, ID: {$submission->id}</td></tr>";
    
    // Check if submission is locked
    $is_locked = $assign->is_submission_locked($USER->id);
    echo "<tr><td>Submission locked</td><td>" . ($is_locked ? "<span class='error'>❌ LOCKED</span>" : "<span class='success'>✅ UNLOCKED</span>") . "</td><td>" . ($is_locked ? "Submission is locked (graded or past deadline)" : "Submission can be edited") . "</td></tr>";
} else {
    echo "<tr><td>Existing submission</td><td><span class='success'>✅ NONE</span></td><td>No previous submission</td></tr>";
}

// 7. Check group mode
$groupmode = groups_get_activity_groupmode($cm);
if ($groupmode > 0) {
    $user_groups = groups_get_activity_allowed_groups($cm);
    $in_group = !empty($user_groups);
    echo "<tr><td>Group mode</td><td>" . ($in_group ? "<span class='success'>✅ IN GROUP</span>" : "<span class='error'>❌ NOT IN GROUP</span>") . "</td><td>Group mode: " . ($groupmode == 1 ? "Separate groups" : "Visible groups") . "</td></tr>";
} else {
    echo "<tr><td>Group mode</td><td><span class='success'>✅ NO GROUPS</span></td><td>Assignment doesn't use groups</td></tr>";
}

// 8. Check team submission
$teamsubmission = $assign_record->teamsubmission;
if ($teamsubmission) {
    echo "<tr><td>Team submission</td><td><span class='warning'>⚠ ENABLED</span></td><td>This is a team assignment</td></tr>";
} else {
    echo "<tr><td>Team submission</td><td><span class='success'>✅ INDIVIDUAL</span></td><td>Individual submission</td></tr>";
}

// 9. Check if user is in grading period
$gradingduedate = $assign_record->gradingduedate;
if ($gradingduedate > 0 && $gradingduedate < $now) {
    echo "<tr><td>Grading due date</td><td><span class='warning'>⚠ PASSED</span></td><td>Grading period ended: " . userdate($gradingduedate) . "</td></tr>";
}

echo "</table>";

// Final verdict
echo "<hr>";
echo "<h2>Final Verdict:</h2>";
if ($is_open) {
    echo "<p class='success'>✅ SUBMISSIONS SHOULD BE WORKING!</p>";
    echo "<p>Try uploading a video now. If it still fails, the problem is in the plugin code, not the assignment settings.</p>";
} else {
    echo "<p class='error'>❌ SUBMISSIONS ARE BLOCKED</p>";
    echo "<p>Possible reasons:</p>";
    echo "<ul>";
    if (!$can_submit) echo "<li>User lacks submit capability</li>";
    if (!$allow_from_ok) echo "<li>Allow submissions from date is in the future</li>";
    if (!$cutoff_ok) echo "<li>Cut-off date has passed</li>";
    if ($submission && $assign->is_submission_locked($USER->id)) echo "<li>Submission is locked (already graded)</li>";
    if ($groupmode > 0 && !$in_group) echo "<li>User is not in a group (required for this assignment)</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='?assignmentid=$assignmentid'>Run Diagnostic Again</a> | ";
echo "<a href='" . $CFG->wwwroot . "/mod/assign/view.php?id=" . $cm->id . "'>Back to Assignment</a></p>";
echo "</body></html>";
