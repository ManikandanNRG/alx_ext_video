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
 * Admin dashboard for S3 video plugin statistics and monitoring.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use assignsubmission_s3video\logger;

// Require admin login.
admin_externalpage_setup('assignsubmission_s3video_dashboard');

// Get time range parameter (default: last 30 days).
$range = optional_param('range', 30, PARAM_INT);
$endtime = time();
$starttime = $endtime - ($range * 24 * 60 * 60);

// Page setup.
$PAGE->set_url('/mod/assign/submission/s3video/dashboard.php', ['range' => $range]);
$PAGE->set_title(get_string('dashboard', 'assignsubmission_s3video'));
$PAGE->set_heading(get_string('dashboard', 'assignsubmission_s3video'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'assignsubmission_s3video'));

// Time range selector.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('timerange', 'assignsubmission_s3video') . ': ', 
    ['for' => 'range-select', 'class' => 'mr-2']);

$rangeoptions = [
    7 => get_string('last7days', 'assignsubmission_s3video'),
    30 => get_string('last30days', 'assignsubmission_s3video'),
    90 => get_string('last90days', 'assignsubmission_s3video'),
    365 => get_string('lastyear', 'assignsubmission_s3video'),
];

echo html_writer::start_tag('select', [
    'id' => 'range-select',
    'class' => 'custom-select',
    'onchange' => 'window.location.href="?range=" + this.value'
]);

foreach ($rangeoptions as $days => $label) {
    $selected = ($days == $range) ? 'selected' : '';
    echo html_writer::tag('option', $label, ['value' => $days, 'selected' => $selected]);
}

echo html_writer::end_tag('select');
echo html_writer::end_div();

// Get statistics.
$uploadstats = logger::get_upload_statistics($starttime, $endtime);
$playbackstats = logger::get_playback_statistics($starttime, $endtime);
$storageusage = logger::get_storage_usage();
$costs = logger::estimate_costs($starttime, $endtime);

// Upload Statistics Section.
echo $OUTPUT->heading(get_string('uploadstatistics', 'assignsubmission_s3video'), 3);

echo html_writer::start_div('row');

// Total Uploads Requested.
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalrequested', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $uploadstats->total_requested, 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Total Uploads Completed.
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalcompleted', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $uploadstats->total_completed, 
    ['class' => 'card-text display-4 text-success']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Total Uploads Failed.
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalfailed', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $uploadstats->total_failed, 
    ['class' => 'card-text display-4 text-danger']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Success Rate.
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('successrate', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $uploadstats->success_rate . '%', 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Storage Statistics Section.
echo $OUTPUT->heading(get_string('storagestatistics', 'assignsubmission_s3video'), 3);

echo html_writer::start_div('row');

// Total Videos.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalvideos', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $storageusage->total_videos, 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Total Storage.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalstorage', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', display_size($storageusage->total_storage_bytes), 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Average File Size.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('averagefilesize', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', display_size($storageusage->average_file_size), 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Playback Statistics Section.
echo $OUTPUT->heading(get_string('playbackstatistics', 'assignsubmission_s3video'), 3);

echo html_writer::start_div('row');

// Total Views.
echo html_writer::start_div('col-md-6 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalviews', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', $playbackstats->total_views, 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Views by Role.
echo html_writer::start_div('col-md-6 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('viewsbyrole', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
if (!empty($playbackstats->views_by_role)) {
    echo html_writer::start_tag('ul', ['class' => 'list-unstyled']);
    foreach ($playbackstats->views_by_role as $role => $count) {
        echo html_writer::tag('li', ucfirst($role) . ': ' . $count);
    }
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::tag('p', get_string('nodata', 'assignsubmission_s3video'));
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Cost Estimates Section.
echo $OUTPUT->heading(get_string('costestimates', 'assignsubmission_s3video'), 3);

echo html_writer::start_div('alert alert-info');
echo html_writer::tag('p', get_string('costdisclaimer', 'assignsubmission_s3video'));
echo html_writer::end_div();

echo html_writer::start_div('row');

// Storage Cost.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('storagecost', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', '$' . $costs->storage_monthly . ' / month', 
    ['class' => 'card-text display-4']);
echo html_writer::tag('small', $costs->storage_gb . ' GB stored', 
    ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Transfer Cost.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('transfercost', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', '$' . $costs->transfer, 
    ['class' => 'card-text display-4']);
echo html_writer::tag('small', $costs->transfer_gb . ' GB transferred', 
    ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Total Cost.
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::start_div('card bg-primary text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('totalcost', 'assignsubmission_s3video'), 
    ['class' => 'card-title']);
echo html_writer::tag('p', '$' . $costs->total, 
    ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Recent Failures Section.
echo $OUTPUT->heading(get_string('recentfailures', 'assignsubmission_s3video'), 3);

$failures = logger::get_recent_failures(10);

if (!empty($failures)) {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('time', 'core'));
    echo html_writer::tag('th', get_string('user', 'core'));
    echo html_writer::tag('th', get_string('assignment', 'core'));
    echo html_writer::tag('th', get_string('error', 'core'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($failures as $failure) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', userdate($failure->timestamp, get_string('strftimedatetime', 'core')));
        echo html_writer::tag('td', fullname($failure));
        echo html_writer::tag('td', s($failure->assignmentname ?? 'N/A'));
        echo html_writer::tag('td', s($failure->error_message));
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::tag('p', get_string('nofailures', 'assignsubmission_s3video'), 
        ['class' => 'alert alert-success']);
}

echo $OUTPUT->footer();
