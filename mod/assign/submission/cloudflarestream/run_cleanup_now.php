<?php
/**
 * Run cleanup task immediately (browser-accessible).
 * Access via: https://yoursite.com/mod/assign/submission/cloudflarestream/run_cleanup_now.php
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

// Require admin login.
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/cloudflarestream/run_cleanup_now.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Run Cleanup Task');
$PAGE->set_heading('Run Cleanup Task Now');

$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Run Cleanup Task Immediately');

if (!$confirm) {
    echo html_writer::tag('p', 'This will run the cleanup task to remove stuck uploads (pending/uploading for > 1 hour).');
    echo html_writer::tag('p', 'Current stuck uploads will be deleted from Cloudflare and database.', ['class' => 'alert alert-warning']);
    
    $confirmurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
    echo html_writer::link($confirmurl, 'Run Cleanup Now', ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link(new moodle_url('/admin/tool/task/scheduledtasks.php'), 'Cancel', ['class' => 'btn btn-secondary']);
    
} else {
    require_sesskey();
    
    echo html_writer::tag('h3', 'Running Cleanup Task...');
    echo html_writer::start_tag('pre', ['class' => 'alert alert-info']);
    
    // Capture output
    ob_start();
    
    try {
        // Get the task
        $task = \core\task\manager::get_scheduled_task('assignsubmission_cloudflarestream\task\cleanup_videos');
        
        if (!$task) {
            throw new moodle_exception('Task not found');
        }
        
        // Execute the task
        echo "Starting cleanup task...\n\n";
        $task->execute();
        echo "\n✓ Cleanup task completed successfully!\n";
        
    } catch (Exception $e) {
        echo "\n✗ Error: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    echo htmlspecialchars($output);
    
    echo html_writer::end_tag('pre');
    
    echo html_writer::tag('p', '');
    echo html_writer::link($PAGE->url, 'Run Again', ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link(new moodle_url('/mod/assign/submission/cloudflarestream/manual_cleanup.php'), 
                          'Go to Manual Cleanup', ['class' => 'btn btn-secondary']);
}

echo $OUTPUT->footer();
