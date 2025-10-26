<?php
/**
 * Test video playback URL generation.
 *
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/test_playback.php?submissionid=X
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\cloudfront_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/test_playback.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test Video Playback');
$PAGE->set_heading('Test Video Playback');

echo $OUTPUT->header();

echo '<h2>Test Video Playback URL Generation</h2>';

// Get submission ID from URL.
$submissionid = optional_param('submissionid', 0, PARAM_INT);

if (!$submissionid) {
    echo '<p>Please provide a submission ID in the URL: ?submissionid=X</p>';
    
    // List available submissions.
    $videos = $DB->get_records('assignsubmission_s3video', ['upload_status' => 'ready'], '', 'id,submission,s3_key', 0, 10);
    
    if ($videos) {
        echo '<h3>Available Submissions:</h3>';
        echo '<ul>';
        foreach ($videos as $video) {
            $url = new moodle_url('/mod/assign/submission/s3video/test_playback.php', ['submissionid' => $video->submission]);
            echo '<li><a href="' . $url . '">Submission ID: ' . $video->submission . ' (S3 Key: ' . htmlspecialchars($video->s3_key) . ')</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No videos found with status "ready".</p>';
    }
    
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Testing Submission ID: ' . $submissionid . '</h3>';

// Get video record.
$video = $DB->get_record('assignsubmission_s3video', ['submission' => $submissionid]);

if (!$video) {
    echo '<p style="color: red;">Video record not found for submission ID: ' . $submissionid . '</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<table class="generaltable">';
echo '<tr><td><strong>S3 Key:</strong></td><td>' . htmlspecialchars($video->s3_key) . '</td></tr>';
echo '<tr><td><strong>Status:</strong></td><td>' . htmlspecialchars($video->upload_status) . '</td></tr>';
echo '<tr><td><strong>File Size:</strong></td><td>' . display_size($video->file_size) . '</td></tr>';
echo '</table>';

if ($video->upload_status !== 'ready') {
    echo '<p style="color: orange;">Video status is not "ready". Current status: ' . htmlspecialchars($video->upload_status) . '</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 1: Check CloudFront Configuration</h3>';

$cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

echo '<table class="generaltable">';
echo '<tr><td>CloudFront Domain:</td><td>' . ($cloudfrontdomain ? htmlspecialchars($cloudfrontdomain) : '<span style="color:red;">Not configured</span>') . '</td></tr>';
echo '<tr><td>Key Pair ID:</td><td>' . ($keypairid ? htmlspecialchars($keypairid) : '<span style="color:red;">Not configured</span>') . '</td></tr>';
echo '<tr><td>Private Key:</td><td>' . ($privatekey ? '<span style="color:green;">Configured (' . strlen($privatekey) . ' characters)</span>' : '<span style="color:red;">Not configured</span>') . '</td></tr>';
echo '</table>';

if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
    echo '<p style="color: red;">CloudFront is not fully configured. Please configure it in plugin settings.</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 2: Generate Signed URL</h3>';

try {
    $cloudfrontclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);
    
    $expiry = 3600; // 1 hour
    $signedurl = $cloudfrontclient->get_signed_url($video->s3_key, $expiry);
    
    echo '<p style="color: green;">✓ Signed URL generated successfully!</p>';
    
    echo '<h4>Signed URL:</h4>';
    echo '<textarea class="form-control" rows="3" readonly style="font-family: monospace; font-size: 12px;">' . htmlspecialchars($signedurl) . '</textarea>';
    
    echo '<h4>URL Components:</h4>';
    $urlparts = parse_url($signedurl);
    echo '<table class="generaltable">';
    echo '<tr><td><strong>Scheme:</strong></td><td>' . htmlspecialchars($urlparts['scheme']) . '</td></tr>';
    echo '<tr><td><strong>Host:</strong></td><td>' . htmlspecialchars($urlparts['host']) . '</td></tr>';
    echo '<tr><td><strong>Path:</strong></td><td>' . htmlspecialchars($urlparts['path']) . '</td></tr>';
    if (isset($urlparts['query'])) {
        parse_str($urlparts['query'], $queryparams);
        echo '<tr><td><strong>Query Parameters:</strong></td><td>';
        echo '<ul>';
        foreach ($queryparams as $key => $value) {
            echo '<li><code>' . htmlspecialchars($key) . '</code>: ' . htmlspecialchars(substr($value, 0, 50)) . '...</li>';
        }
        echo '</ul>';
        echo '</td></tr>';
    }
    echo '</table>';
    
    echo '<h4>Test Video Playback:</h4>';
    echo '<video controls width="640" height="360" style="max-width: 100%;">';
    echo '<source src="' . htmlspecialchars($signedurl) . '" type="video/mp4">';
    echo 'Your browser does not support the video tag.';
    echo '</video>';
    
    echo '<p class="mt-3"><small>If the video doesn\'t play, check:</small></p>';
    echo '<ul>';
    echo '<li>The CloudFront distribution is properly configured</li>';
    echo '<li>The S3 bucket policy allows CloudFront to access objects</li>';
    echo '<li>The CloudFront key pair is valid and active</li>';
    echo '<li>The video file actually exists in S3</li>';
    echo '</ul>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Failed to generate signed URL:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo $OUTPUT->footer();
