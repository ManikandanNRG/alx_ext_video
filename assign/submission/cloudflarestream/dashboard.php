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
 * Admin dashboard for Cloudflare Stream monitoring and analytics.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use assignsubmission_cloudflarestream\logger;

/**
 * Format time duration in seconds to human readable format.
 *
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function format_duration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } else if ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remainingseconds = $seconds % 60;
        return $minutes . ' minutes' . ($remainingseconds > 0 ? ', ' . $remainingseconds . ' seconds' : '');
    } else {
        $hours = floor($seconds / 3600);
        $remainingminutes = floor(($seconds % 3600) / 60);
        return $hours . ' hours' . ($remainingminutes > 0 ? ', ' . $remainingminutes . ' minutes' : '');
    }
}

// Require admin login.
admin_externalpage_setup('assignsubmission_cloudflarestream_dashboard');

// Get optional time period parameter.
$days = optional_param('days', 30, PARAM_INT);

// Validate days parameter.
if ($days < 1 || $days > 365) {
    $days = 30;
}

// Page setup.
$PAGE->set_url('/mod/assign/submission/cloudflarestream/dashboard.php', ['days' => $days]);
$PAGE->set_title(get_string('dashboard', 'assignsubmission_cloudflarestream'));
$PAGE->set_heading(get_string('dashboard', 'assignsubmission_cloudflarestream'));

// Get statistics.
$stats = logger::get_upload_statistics($days);
$recentfailures = logger::get_recent_failures(20);
$errorbreakdown = logger::get_error_breakdown($days);

// Calculate estimated costs (Cloudflare Stream pricing).
$storagecost = ($stats->total_duration_seconds / 60 / 1000) * 5; // $5 per 1000 minutes stored.
$deliverycost = ($stats->total_duration_seconds / 60 / 1000) * 1; // $1 per 1000 minutes delivered (assuming 1 view per video).
$estimatedcost = $storagecost + $deliverycost;

// Output header.
echo $OUTPUT->header();

// Display time period selector.
echo html_writer::start_div('dashboard-period-selector');
echo html_writer::tag('h3', get_string('timeperiod', 'assignsubmission_cloudflarestream'));
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring()]);
echo html_writer::select(
    [
        7 => get_string('last7days', 'assignsubmission_cloudflarestream'),
        30 => get_string('last30days', 'assignsubmission_cloudflarestream'),
        90 => get_string('last90days', 'assignsubmission_cloudflarestream'),
        365 => get_string('lastyear', 'assignsubmission_cloudflarestream')
    ],
    'days',
    $days,
    false
);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('update'), 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Display statistics overview.
echo html_writer::start_div('dashboard-stats mt-4');
echo html_writer::tag('h3', get_string('uploadstatistics', 'assignsubmission_cloudflarestream'));

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('tbody');

// Total uploads.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('totaluploads', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', $stats->total_uploads);
echo html_writer::end_tag('tr');

// Successful uploads.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('successfuluploads', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', $stats->successful_uploads, ['class' => 'text-success']);
echo html_writer::end_tag('tr');

// Failed uploads.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('faileduploads', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', $stats->failed_uploads, ['class' => $stats->failed_uploads > 0 ? 'text-danger' : '']);
echo html_writer::end_tag('tr');

// Success rate.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('successrate', 'assignsubmission_cloudflarestream'));
$successrateclass = $stats->success_rate >= 95 ? 'text-success' : ($stats->success_rate >= 80 ? 'text-warning' : 'text-danger');
echo html_writer::tag('td', $stats->success_rate . '%', ['class' => $successrateclass]);
echo html_writer::end_tag('tr');

// Total storage.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('totalstorage', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', display_size($stats->total_storage_bytes));
echo html_writer::end_tag('tr');

// Total duration.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('totalduration', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', format_duration($stats->total_duration_seconds));
echo html_writer::end_tag('tr');

// Estimated cost.
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('estimatedcost', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('td', '$' . number_format($estimatedcost, 2) . ' USD/month');
echo html_writer::end_tag('tr');

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

// Display error breakdown.
if (!empty($errorbreakdown)) {
    echo html_writer::start_div('dashboard-errors mt-4');
    echo html_writer::tag('h3', get_string('errorbreakdown', 'assignsubmission_cloudflarestream'));
    
    echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('errorcode', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('count', 'assignsubmission_cloudflarestream'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($errorbreakdown as $error) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $error->error_code);
        echo html_writer::tag('td', $error->count);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// Display recent failures.
if (!empty($recentfailures)) {
    echo html_writer::start_div('dashboard-recent-failures mt-4');
    echo html_writer::tag('h3', get_string('recentfailures', 'assignsubmission_cloudflarestream'));
    
    echo html_writer::start_tag('table', ['class' => 'table table-bordered table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('timestamp', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('user'));
    echo html_writer::tag('th', get_string('assignment', 'assign'));
    echo html_writer::tag('th', get_string('errorcode', 'assignsubmission_cloudflarestream'));
    echo html_writer::tag('th', get_string('errormessage', 'assignsubmission_cloudflarestream'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($recentfailures as $failure) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', userdate($failure->timestamp, get_string('strftimedatetime', 'langconfig')));
        
        $username = $failure->firstname && $failure->lastname 
            ? fullname($failure) 
            : get_string('unknownuser', 'assignsubmission_cloudflarestream');
        echo html_writer::tag('td', $username);
        
        $assignmentname = $failure->assignmentname ?? get_string('unknown', 'assignsubmission_cloudflarestream');
        echo html_writer::tag('td', $assignmentname);
        
        echo html_writer::tag('td', $failure->error_code);
        echo html_writer::tag('td', s(substr($failure->error_message, 0, 100)) . (strlen($failure->error_message) > 100 ? '...' : ''));
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
} else {
    echo html_writer::div(
        get_string('norecentfailures', 'assignsubmission_cloudflarestream'),
        'alert alert-success mt-4'
    );
}

// Link to Cloudflare dashboard.
echo html_writer::start_div('dashboard-external-links mt-4');
echo html_writer::tag('h3', get_string('externalresources', 'assignsubmission_cloudflarestream'));
echo html_writer::tag('p', 
    html_writer::link(
        'https://dash.cloudflare.com/?to=/:account/stream',
        get_string('viewcloudflarestats', 'assignsubmission_cloudflarestream'),
        ['target' => '_blank', 'class' => 'btn btn-secondary']
    )
);
echo html_writer::end_div();

// Output footer.
echo $OUTPUT->footer();
