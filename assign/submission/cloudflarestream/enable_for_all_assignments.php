<?php
/**
 * Enable Cloudflare Stream plugin for all existing assignments
 * 
 * This script enables the plugin at the assignment level for all existing assignments
 */

require_once(__DIR__ . '/../../../../config.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/enable_for_all_assignments.php');
$PAGE->set_title('Enable Cloudflare Stream for All Assignments');
$PAGE->set_heading('Enable Cloudflare Stream for All Assignments');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Enable Cloudflare Stream Plugin for All Assignments');

if (!$confirm) {
    // Show confirmation page
    echo html_writer::tag('div', 
        'This will enable the Cloudflare Stream submission plugin for ALL existing assignments in your Moodle site.',
        ['class' => 'alert alert-warning']
    );
    
    // Count assignments
    $total_assignments = $DB->count_records('assign');
    
    echo html_writer::tag('p', 'Total assignments found: ' . $total_assignments);
    
    // Check how many already have the plugin enabled
    $already_enabled = $DB->count_records('assign_plugin_config', [
        'plugin' => 'cloudflarestream',
        'subtype' => 'assignsubmission',
        'name' => 'enabled',
        'value' => '1'
    ]);
    
    echo html_writer::tag('p', 'Assignments with Cloudflare Stream already enabled: ' . $already_enabled);
    echo html_writer::tag('p', 'Assignments that will be updated: ' . ($total_assignments - $already_enabled));
    
    $confirm_url = new moodle_url('/mod/assign/submission/cloudflarestream/enable_for_all_assignments.php', ['confirm' => 1]);
    
    echo html_writer::tag('div',
        html_writer::link($confirm_url, 'Confirm and Enable for All Assignments', ['class' => 'btn btn-primary']),
        ['class' => 'mt-3']
    );
    
} else {
    // Execute the enablement
    echo html_writer::tag('h3', 'Enabling Plugin for All Assignments');
    
    $assignments = $DB->get_records('assign');
    
    $enabled_count = 0;
    $already_enabled_count = 0;
    $errors = [];
    
    foreach ($assignments as $assignment) {
        try {
            // Check if already enabled
            $existing = $DB->get_record('assign_plugin_config', [
                'assignment' => $assignment->id,
                'plugin' => 'cloudflarestream',
                'subtype' => 'assignsubmission',
                'name' => 'enabled'
            ]);
            
            if ($existing) {
                if ($existing->value == '1') {
                    $already_enabled_count++;
                    continue;
                } else {
                    // Update to enabled
                    $existing->value = '1';
                    $DB->update_record('assign_plugin_config', $existing);
                    $enabled_count++;
                }
            } else {
                // Insert new record
                $record = new stdClass();
                $record->assignment = $assignment->id;
                $record->plugin = 'cloudflarestream';
                $record->subtype = 'assignsubmission';
                $record->name = 'enabled';
                $record->value = '1';
                
                $DB->insert_record('assign_plugin_config', $record);
                $enabled_count++;
            }
            
        } catch (Exception $e) {
            $errors[] = 'Assignment ID ' . $assignment->id . ': ' . $e->getMessage();
        }
    }
    
    // Display results
    echo html_writer::tag('div',
        html_writer::tag('h4', 'Results:') .
        html_writer::start_tag('ul') .
        html_writer::tag('li', 'Assignments enabled: ' . $enabled_count) .
        html_writer::tag('li', 'Already enabled: ' . $already_enabled_count) .
        html_writer::tag('li', 'Total processed: ' . count($assignments)) .
        html_writer::end_tag('ul'),
        ['class' => 'alert alert-success']
    );
    
    if (!empty($errors)) {
        echo html_writer::tag('div',
            html_writer::tag('h4', 'Errors:') .
            html_writer::alist($errors),
            ['class' => 'alert alert-danger']
        );
    }
    
    // Purge caches
    echo html_writer::tag('h3', 'Purging Caches');
    purge_all_caches();
    echo html_writer::tag('p', '✓ All caches purged', ['class' => 'alert alert-success']);
    
    // Test with an assignment
    echo html_writer::tag('h3', 'Verification Test');
    
    $test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);
    
    if ($test_assignment) {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        
        $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
        $context = context_module::instance($cm->id);
        $assignment = new assign($context, $cm, $test_assignment->course);
        
        $plugins = $assignment->get_submission_plugins();
        
        $found = false;
        foreach ($plugins as $plugin) {
            if (get_class($plugin) === 'assign_submission_cloudflarestream') {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            echo html_writer::tag('p', '✓ SUCCESS! Plugin now appears in assignment submission plugins!', ['class' => 'alert alert-success']);
        } else {
            echo html_writer::tag('p', '⚠ Plugin still not appearing. You may need to refresh the assignment page.', ['class' => 'alert alert-warning']);
        }
    }
    
    echo html_writer::tag('div',
        html_writer::tag('h4', 'Next Steps:') .
        html_writer::start_tag('ol') .
        html_writer::tag('li', 'Go to any assignment') .
        html_writer::tag('li', 'Click "Edit settings"') .
        html_writer::tag('li', 'Scroll to "Submission types"') .
        html_writer::tag('li', 'You should now see "Cloudflare Stream" as an option') .
        html_writer::end_tag('ol'),
        ['class' => 'alert alert-info']
    );
}

echo $OUTPUT->footer();
