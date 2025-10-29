<?php
/**
 * Verify that files are updated correctly on the server.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/assign/submission/cloudflarestream/verify_files.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Verify Files');
$PAGE->set_heading('Verify File Updates');

echo $OUTPUT->header();

echo '<div class="container-fluid">';
echo '<h2>File Verification Report</h2>';

// Check 1: cloudflare_client.php
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>1. cloudflare_client.php</h4></div>';
echo '<div class="card-body">';

$client_file = __DIR__ . '/classes/api/cloudflare_client.php';
if (file_exists($client_file)) {
    $content = file_get_contents($client_file);
    $size = filesize($client_file);
    $modified = date('Y-m-d H:i:s', filemtime($client_file));
    
    echo '<p><strong>File exists:</strong> ✅ Yes</p>';
    echo '<p><strong>Size:</strong> ' . number_format($size) . ' bytes</p>';
    echo '<p><strong>Last modified:</strong> ' . $modified . '</p>';
    
    if (strpos($content, "'requireSignedURLs' => true") !== false) {
        echo '<div class="alert alert-success">';
        echo '<strong>✅ CORRECT:</strong> File contains <code>\'requireSignedURLs\' => true</code><br>';
        echo 'Videos will be uploaded as PRIVATE';
        echo '</div>';
    } else if (strpos($content, "'requireSignedURLs' => false") !== false) {
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ WRONG:</strong> File contains <code>\'requireSignedURLs\' => false</code><br>';
        echo 'Videos will be uploaded as PUBLIC<br>';
        echo '<strong>ACTION:</strong> Re-upload this file from your local machine';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">';
        echo '<strong>⚠️ UNKNOWN:</strong> Cannot find requireSignedURLs setting';
        echo '</div>';
    }
} else {
    echo '<div class="alert alert-danger">❌ File does not exist!</div>';
}

echo '</div></div>';

// Check 2: player.js
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>2. amd/src/player.js</h4></div>';
echo '<div class="card-body">';

$player_file = __DIR__ . '/amd/src/player.js';
if (file_exists($player_file)) {
    $content = file_get_contents($player_file);
    $size = filesize($player_file);
    $modified = date('Y-m-d H:i:s', filemtime($player_file));
    
    echo '<p><strong>File exists:</strong> ✅ Yes</p>';
    echo '<p><strong>Size:</strong> ' . number_format($size) . ' bytes</p>';
    echo '<p><strong>Last modified:</strong> ' . $modified . '</p>';
    
    if (strpos($content, 'iframe.videodelivery.net') !== false) {
        $count = substr_count($content, 'iframe.videodelivery.net');
        echo '<div class="alert alert-success">';
        echo '<strong>✅ CORRECT:</strong> File contains IFRAME method<br>';
        echo 'Found ' . $count . ' occurrences of "iframe.videodelivery.net"';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ WRONG:</strong> File does NOT contain IFRAME method<br>';
        echo '<strong>ACTION:</strong> Re-upload this file from your local machine';
        echo '</div>';
    }
} else {
    echo '<div class="alert alert-danger">❌ File does not exist!</div>';
}

echo '</div></div>';

// Check 3: player.min.js (THE IMPORTANT ONE!)
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>3. amd/build/player.min.js (CRITICAL!)</h4></div>';
echo '<div class="card-body">';

echo '<p class="alert alert-info"><strong>NOTE:</strong> This is the file Moodle actually uses! If this file is wrong, nothing will work.</p>';

$player_min_file = __DIR__ . '/amd/build/player.min.js';
if (file_exists($player_min_file)) {
    $content = file_get_contents($player_min_file);
    $size = filesize($player_min_file);
    $modified = date('Y-m-d H:i:s', filemtime($player_min_file));
    
    echo '<p><strong>File exists:</strong> ✅ Yes</p>';
    echo '<p><strong>Size:</strong> ' . number_format($size) . ' bytes</p>';
    echo '<p><strong>Last modified:</strong> ' . $modified . '</p>';
    
    if (strpos($content, 'iframe.videodelivery.net') !== false) {
        $count = substr_count($content, 'iframe.videodelivery.net');
        echo '<div class="alert alert-success">';
        echo '<strong>✅ CORRECT:</strong> File contains IFRAME method<br>';
        echo 'Found ' . $count . ' occurrences of "iframe.videodelivery.net"<br>';
        echo '<strong>This file is UPDATED correctly!</strong>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ CRITICAL ERROR:</strong> File does NOT contain IFRAME method<br>';
        echo '<strong>This is why you\'re not seeing console output!</strong><br><br>';
        echo '<strong>ACTION REQUIRED:</strong><br>';
        echo '1. Re-upload this file from your local machine<br>';
        echo '2. OR run this command on your server:<br>';
        echo '<code>cp ' . __DIR__ . '/amd/src/player.js ' . __DIR__ . '/amd/build/player.min.js</code>';
        echo '</div>';
    }
    
    // Compare with source file
    if (file_exists($player_file)) {
        $source_content = file_get_contents($player_file);
        if ($content === $source_content) {
            echo '<div class="alert alert-success">';
            echo '<strong>✅ FILES MATCH:</strong> player.min.js is identical to player.js';
            echo '</div>';
        } else {
            echo '<div class="alert alert-warning">';
            echo '<strong>⚠️ FILES DIFFER:</strong> player.min.js is different from player.js<br>';
            echo 'Source file size: ' . number_format(strlen($source_content)) . ' bytes<br>';
            echo 'Build file size: ' . number_format(strlen($content)) . ' bytes<br>';
            echo '<strong>ACTION:</strong> Copy player.js to player.min.js';
            echo '</div>';
        }
    }
} else {
    echo '<div class="alert alert-danger">❌ File does not exist!</div>';
}

echo '</div></div>';

// Check 4: File hashes
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>4. File Integrity Check</h4></div>';
echo '<div class="card-body">';

if (file_exists($player_file) && file_exists($player_min_file)) {
    $source_hash = md5_file($player_file);
    $build_hash = md5_file($player_min_file);
    
    echo '<table class="table">';
    echo '<tr><th>File</th><th>MD5 Hash</th></tr>';
    echo '<tr><td>amd/src/player.js</td><td><code>' . $source_hash . '</code></td></tr>';
    echo '<tr><td>amd/build/player.min.js</td><td><code>' . $build_hash . '</code></td></tr>';
    echo '</table>';
    
    if ($source_hash === $build_hash) {
        echo '<div class="alert alert-success">';
        echo '<strong>✅ HASHES MATCH:</strong> Files are identical';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ HASHES DIFFER:</strong> Files are NOT identical<br>';
        echo '<strong>ACTION:</strong> Copy player.js to player.min.js';
        echo '</div>';
    }
}

echo '</div></div>';

// Summary
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>5. Summary & Actions</h4></div>';
echo '<div class="card-body">';

$all_good = true;

// Check cloudflare_client.php
if (file_exists($client_file)) {
    $content = file_get_contents($client_file);
    if (strpos($content, "'requireSignedURLs' => true") === false) {
        $all_good = false;
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ ACTION REQUIRED:</strong> Re-upload cloudflare_client.php';
        echo '</div>';
    }
}

// Check player.min.js
if (file_exists($player_min_file)) {
    $content = file_get_contents($player_min_file);
    if (strpos($content, 'iframe.videodelivery.net') === false) {
        $all_good = false;
        echo '<div class="alert alert-danger">';
        echo '<strong>❌ ACTION REQUIRED:</strong> Update player.min.js<br>';
        echo '<strong>Run this command on your server:</strong><br>';
        echo '<code>cp ' . dirname(__FILE__) . '/amd/src/player.js ' . dirname(__FILE__) . '/amd/build/player.min.js</code>';
        echo '</div>';
    }
}

if ($all_good) {
    echo '<div class="alert alert-success">';
    echo '<h4>✅ ALL FILES ARE CORRECT!</h4>';
    echo '<p>If you\'re still not seeing console output:</p>';
    echo '<ol>';
    echo '<li><strong>Clear Moodle cache:</strong> Site administration > Development > Purge all caches</li>';
    echo '<li><strong>Clear browser cache:</strong> Press Ctrl+F5 (or Cmd+Shift+R on Mac)</li>';
    echo '<li><strong>Upload a NEW video</strong> (don\'t use old one)</li>';
    echo '<li><strong>Check console (F12)</strong> for "IFRAME method" message</li>';
    echo '</ol>';
    echo '</div>';
}

echo '</div></div>';

echo '</div>'; // container-fluid

echo $OUTPUT->footer();
