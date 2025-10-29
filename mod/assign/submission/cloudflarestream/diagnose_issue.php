<?php
/**
 * Diagnostic script to check current implementation status.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/cloudflarestream/diagnose_issue.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Diagnose Implementation');
$PAGE->set_heading('Diagnose Private Video Implementation');

echo $OUTPUT->header();

echo '<div class="container-fluid">';
echo '<h2>Diagnostic Report</h2>';

// Check 1: Configuration
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>1. Configuration Check</h4></div>';
echo '<div class="card-body">';

$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');
$signingkeyid = get_config('assignsubmission_cloudflarestream', 'signingkeyid');
$signingkey = get_config('assignsubmission_cloudflarestream', 'signingkey');

echo '<table class="table">';
echo '<tr><td>API Token:</td><td>' . (empty($apitoken) ? '❌ Not set' : '✅ Set (' . strlen($apitoken) . ' chars)') . '</td></tr>';
echo '<tr><td>Account ID:</td><td>' . (empty($accountid) ? '❌ Not set' : '✅ Set (' . $accountid . ')') . '</td></tr>';
echo '<tr><td>Signing Key ID:</td><td>' . (empty($signingkeyid) ? '❌ Not set' : '✅ Set (' . $signingkeyid . ')') . '</td></tr>';
echo '<tr><td>Signing Key:</td><td>' . (empty($signingkey) ? '❌ Not set' : '✅ Set (' . strlen($signingkey) . ' chars)') . '</td></tr>';
echo '</table>';

if (empty($signingkeyid) || empty($signingkey)) {
    echo '<div class="alert alert-danger">';
    echo '<strong>⚠️ CRITICAL:</strong> Signing keys are NOT configured!<br><br>';
    echo '<strong>This is why private videos won\'t work.</strong><br><br>';
    echo '<strong>To fix:</strong><br>';
    echo '1. Go to: Site administration > Plugins > Assignment > Submission plugins > Cloudflare Stream Video<br>';
    echo '2. Set "Signing Key ID" (from Cloudflare Stream > Signing Keys)<br>';
    echo '3. Set "Signing Key" (the private key PEM content)<br>';
    echo '4. Save changes<br>';
    echo '</div>';
}

echo '</div></div>';

// Check 2: File Check
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>2. File Check</h4></div>';
echo '<div class="card-body">';

$files_to_check = [
    'classes/api/cloudflare_client.php',
    'amd/src/player.js',
    'amd/build/player.min.js',
];

echo '<table class="table">';
foreach ($files_to_check as $file) {
    $fullpath = __DIR__ . '/' . $file;
    $exists = file_exists($fullpath);
    $size = $exists ? filesize($fullpath) : 0;
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($fullpath)) : 'N/A';
    
    echo '<tr>';
    echo '<td>' . $file . '</td>';
    echo '<td>' . ($exists ? '✅ Exists' : '❌ Missing') . '</td>';
    echo '<td>' . ($size > 0 ? number_format($size) . ' bytes' : 'N/A') . '</td>';
    echo '<td>Modified: ' . $modified . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '</div></div>';

// Check 3: Code Check
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>3. Code Implementation Check</h4></div>';
echo '<div class="card-body">';

// Check cloudflare_client.php
$client_file = __DIR__ . '/classes/api/cloudflare_client.php';
if (file_exists($client_file)) {
    $content = file_get_contents($client_file);
    
    if (strpos($content, "'requireSignedURLs' => true") !== false) {
        echo '<div class="alert alert-success">';
        echo '✅ <strong>cloudflare_client.php:</strong> Correctly set to upload PRIVATE videos';
        echo '</div>';
    } else if (strpos($content, "'requireSignedURLs' => false") !== false) {
        echo '<div class="alert alert-danger">';
        echo '❌ <strong>cloudflare_client.php:</strong> Still set to upload PUBLIC videos<br>';
        echo 'File was NOT updated correctly!';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">';
        echo '⚠️ <strong>cloudflare_client.php:</strong> Cannot find requireSignedURLs setting';
        echo '</div>';
    }
}

// Check player.js
$player_file = __DIR__ . '/amd/src/player.js';
if (file_exists($player_file)) {
    $content = file_get_contents($player_file);
    
    if (strpos($content, 'iframe.videodelivery.net') !== false) {
        echo '<div class="alert alert-success">';
        echo '✅ <strong>player.js:</strong> IFRAME method implemented';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '❌ <strong>player.js:</strong> IFRAME method NOT found<br>';
        echo 'File was NOT updated correctly!';
        echo '</div>';
    }
}

// Check player.min.js
$player_min_file = __DIR__ . '/amd/build/player.min.js';
if (file_exists($player_min_file)) {
    $content = file_get_contents($player_min_file);
    
    if (strpos($content, 'iframe.videodelivery.net') !== false) {
        echo '<div class="alert alert-success">';
        echo '✅ <strong>player.min.js:</strong> IFRAME method implemented (REBUILT)';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '❌ <strong>player.min.js:</strong> IFRAME method NOT found<br>';
        echo 'File was NOT rebuilt correctly!<br>';
        echo '<strong>This is why you\'re not seeing console output!</strong>';
        echo '</div>';
    }
}

echo '</div></div>';

// Check 4: Video Check
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>4. Your Uploaded Video Check</h4></div>';
echo '<div class="card-body">';

$videoid = '23b7a0e3b30068adbaa0692cc1b10724';

echo '<p>Checking video: <code>' . $videoid . '</code></p>';

if (!empty($apitoken) && !empty($accountid)) {
    try {
        require_once(__DIR__ . '/classes/api/cloudflare_client.php');
        $client = new \assignsubmission_cloudflarestream\api\cloudflare_client($apitoken, $accountid);
        
        $details = $client->get_video_details($videoid);
        
        echo '<table class="table">';
        echo '<tr><td>Video UID:</td><td>' . $details->uid . '</td></tr>';
        echo '<tr><td>Status:</td><td>' . $details->status->state . '</td></tr>';
        echo '<tr><td>Duration:</td><td>' . ($details->duration ?? 'Processing') . ' seconds</td></tr>';
        echo '<tr><td>Require Signed URLs:</td><td>' . ($details->requireSignedURLs ? '✅ TRUE (PRIVATE)' : '❌ FALSE (PUBLIC)') . '</td></tr>';
        echo '</table>';
        
        if ($details->requireSignedURLs) {
            echo '<div class="alert alert-success">';
            echo '<strong>✅ SUCCESS:</strong> Video was uploaded as PRIVATE!<br>';
            echo 'This means the cloudflare_client.php file IS working correctly.';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '<strong>❌ PROBLEM:</strong> Video was uploaded as PUBLIC!<br>';
            echo 'This means the cloudflare_client.php file was NOT updated correctly.';
            echo '</div>';
        }
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Error:</strong> ' . $e->getMessage();
        echo '</div>';
    }
} else {
    echo '<div class="alert alert-warning">Cannot check video - API credentials not configured</div>';
}

echo '</div></div>';

// Summary
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>5. Summary & Next Steps</h4></div>';
echo '<div class="card-body">';

echo '<h5>Issues Found:</h5>';
echo '<ol>';

if (empty($signingkeyid) || empty($signingkey)) {
    echo '<li class="text-danger"><strong>CRITICAL:</strong> Signing keys not configured</li>';
}

$player_min_file = __DIR__ . '/amd/build/player.min.js';
if (file_exists($player_min_file)) {
    $content = file_get_contents($player_min_file);
    if (strpos($content, 'iframe.videodelivery.net') === false) {
        echo '<li class="text-danger"><strong>CRITICAL:</strong> player.min.js not rebuilt correctly</li>';
    }
}

echo '</ol>';

echo '<h5>What to Do:</h5>';
echo '<ol>';
echo '<li><strong>Configure Signing Keys:</strong><br>';
echo '   Go to: Site administration > Plugins > Assignment > Submission plugins > Cloudflare Stream Video<br>';
echo '   Set both "Signing Key ID" and "Signing Key"<br><br></li>';

echo '<li><strong>Clear Moodle Cache:</strong><br>';
echo '   Go to: Site administration > Development > Purge all caches<br><br></li>';

echo '<li><strong>Clear Browser Cache:</strong><br>';
echo '   Press Ctrl+F5 (or Cmd+Shift+R on Mac) to hard refresh<br><br></li>';

echo '<li><strong>Test Again:</strong><br>';
echo '   Upload a new video and check console (F12)<br>';
echo '   Should see: "Cloudflare player embedded with IFRAME method"</li>';
echo '</ol>';

echo '</div></div>';

echo '</div>'; // container-fluid

echo $OUTPUT->footer();
