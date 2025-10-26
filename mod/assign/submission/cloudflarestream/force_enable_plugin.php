<?php
/**
 * Force enable the plugin by ensuring it's loaded by the assignment module
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/cloudflarestream/force_enable_plugin.php');
$PAGE->set_title('Force Enable Plugin');
$PAGE->set_heading('Force Enable Plugin');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Force Enable Cloudflare Stream Plugin');

if (!$confirm) {
    echo html_writer::tag('div',
        'This will enable the Cloudflare Stream plugin for ALL assignments by adding the necessary database entries.',
        ['class' => 'alert alert-info']
    );
    
    $confirm_url = new moodle_url('/mod/assign/submission/cloudflarestream/force_enable_plugin.php', ['confirm' => 1]);
    
    echo html_writer::link($confirm_url, 'Confirm and Enable', ['class' => 'btn btn-primary btn-lg']);
    
} else {
    
    echo html_writer::tag('h3', 'Step 1: Enable Plugin Globally');
    
    // Ensure global settings are correct
    set_config('enabled', 1, 'assignsubmission_cloudflarestream');
    set_config('default', 1, 'assignsubmission_cloudflarestream');
    
    echo html_writer::tag('p', '✓ Global settings configured', ['class' => 'alert alert-success']);
    
    echo html_writer::tag('h3', 'Step 2: Enable for All Assignments');
    
    // Get all assignments
    $assignments = $DB->get_records('assign');
    $enabled_count = 0;
    $already_enabled = 0;
    
    foreach ($assignments as $assignment) {
        // Check if already enabled
        $existing = $DB->get_record('assign_plugin_config', [
            'assignment' => $assignment->id,
            'plugin' => 'cloudflarestream',
            'subtype' => 'assignsubmission',
            'name' => 'enabled'
        ]);
        
        if ($existing) {
            if ($existing->value != '1') {
                $existing->value = '1';
                $DB->update_record('assign_plugin_config', $existing);
                $enabled_count++;
            } else {
                $already_enabled++;
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
    }
    
    echo html_writer::tag('p', 
        'Enabled for ' . $enabled_count . ' assignments, ' . $already_enabled . ' already enabled',
        ['class' => 'alert alert-success']
    );
    
    echo html_writer::tag('h3', 'Step 3: Purge Caches');
    purge_all_caches();
    echo html_writer::tag('p', '✓ All caches purged', ['class' => 'alert alert-success']);
    
    echo html_writer::tag('h3', 'Step 4: Verify Plugin Appears');
    
    // Test with an assignment
    $test_assignment = $DB->get_record('assign', [], '*', IGNORE_MULTIPLE);
    
    if ($test_assignment) {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        
        $cm = get_coursemodule_from_instance('assign', $test_assignment->id);
        $context = context_module::instance($cm->id);
        $assignment = new assign($context, $cm, $test_assignment->course);
        
        $plugins = $assignment->get_submission_plugins();
        
        echo html_writer::tag('p', 'Plugins loaded by assignment:');
        echo html_writer::start_tag('ul');
        
        $found = false;
        foreach ($plugins as $plugin) {
            $class_name = get_class($plugin);
            $plugin_name = $plugin->get_name();
            
            if ($class_name === 'assign_submission_cloudflarestream') {
                $found = true;
                echo html_writer::tag('li', 
                    html_writer::tag('strong', $plugin_name . ' ✓ FOUND!', ['class' => 'text-success'])
                );
            } else {
                echo html_writer::tag('li', $plugin_name);
            }
        }
        echo html_writer::end_tag('ul');
        
        if ($found) {
            echo html_writer::tag('div',
                html_writer::tag('h4', '🎉 SUCCESS!') .
                html_writer::tag('p', 'The Cloudflare Stream plugin is now appearing in assignments!'),
                ['class' => 'alert alert-success']
            );
        } else {
            echo html_writer::tag('div',
                html_writer::tag('h4', '⚠ Still Not Appearing') .
                html_writer::tag('p', 'The plugin is enabled but still not loading. This may be a caching issue.'),
                ['class' => 'alert alert-warning']
            );
            
            echo html_writer::tag('h4', 'Additional Troubleshooting:');
            echo html_writer::start_tag('ol');
            echo html_writer::tag('li', 'Restart your web server (Apache/Nginx)');
            echo html_writer::tag('li', 'Restart PHP-FPM if using it');
            echo html_writer::tag('li', 'Clear your browser cache');
            echo html_writer::tag('li', 'Try accessing an assignment in a private/incognito window');
            echo html_writer::end_tag('ol');
        }
    }
    
    echo html_writer::tag('h3', 'Final Step: Test in Assignment');
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', 'Go to any course');
    echo html_writer::tag('li', 'Create a new assignment or edit an existing one');
    echo html_writer::tag('li', 'Scroll to the "Submission types" section');
    echo html_writer::tag('li', 'You should now see "Cloudflare Stream" as an available option');
    echo html_writer::end_tag('ol');
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
