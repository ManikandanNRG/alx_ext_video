<?php
/**
 * Test if plugin now appears after adding locallib.php
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/test_after_locallib_fix.php');
$PAGE->set_title('Test After locallib.php Fix');
$PAGE->set_heading('Test After locallib.php Fix');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Testing After Adding locallib.php');

// Purge caches first
echo html_writer::tag('h3', 'Step 1: Purging Caches');
purge_all_caches();
echo html_writer::tag('p', '✓ Caches purged', ['class' => 'alert alert-success']);

// Test plugin loading
echo html_writer::tag('h3', 'Step 2: Test Plugin Loading');

$test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);

if ($test_assignment) {
    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    
    $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
    $context = context_module::instance($cm->id);
    $assignment = new assign($context, $cm, $test_assignment->course);
    
    $plugins = $assignment->get_submission_plugins();
    
    echo html_writer::tag('p', 'Total submission plugins loaded: ' . count($plugins));
    echo html_writer::start_tag('ul');
    
    $found = false;
    foreach ($plugins as $plugin) {
        $class_name = get_class($plugin);
        $plugin_name = $plugin->get_name();
        
        if ($class_name === 'assign_submission_cloudflarestream') {
            $found = true;
            echo html_writer::tag('li', 
                html_writer::tag('strong', '✓ ' . $plugin_name . ' - FOUND!', ['class' => 'text-success font-weight-bold'])
            );
        } else {
            echo html_writer::tag('li', $plugin_name);
        }
    }
    echo html_writer::end_tag('ul');
    
    if ($found) {
        echo html_writer::tag('div',
            html_writer::tag('h3', '🎉 SUCCESS!') .
            html_writer::tag('p', 'The Cloudflare Stream plugin is now loading correctly!') .
            html_writer::tag('p', 'The issue was that the assign module requires a locallib.php file in each submission plugin directory.'),
            ['class' => 'alert alert-success']
        );
        
        echo html_writer::tag('h3', 'Next Steps');
        echo html_writer::start_tag('ol');
        echo html_writer::tag('li', 'Go to any course');
        echo html_writer::tag('li', 'Create or edit an assignment');
        echo html_writer::tag('li', 'Scroll to "Submission types"');
        echo html_writer::tag('li', 'You should now see "Cloudflare Stream" as an option!');
        echo html_writer::end_tag('ol');
        
    } else {
        echo html_writer::tag('div',
            html_writer::tag('h4', '⚠ Still Not Loading') .
            html_writer::tag('p', 'Plugin still not appearing. Additional investigation needed.'),
            ['class' => 'alert alert-warning']
        );
    }
}

echo $OUTPUT->footer();
