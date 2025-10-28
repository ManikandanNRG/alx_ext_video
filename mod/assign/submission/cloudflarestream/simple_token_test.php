<?php
/**
 * Simple Cloudflare token test without complex dependencies
 */

require_once(__DIR__ . '/../../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$videouid = required_param('videouid', PARAM_TEXT);

echo "<!DOCTYPE html><html><head><title>Simple Token Test</title>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body>";
echo "<h1>Simple Cloudflare Token Test</h1>";
echo "<p class='info'>Testing video: $videouid</p>";

// Get plugin configuration
$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

if (empty($apitoken) || empty($accountid)) {
    echo "<p class='error'>❌ Plugin not configured</p>";
    echo "<p class='info'>API Token: " . (empty($apitoken) ? 'Missing' : 'Present') . "</p>";
    echo "<p class='info'>Account ID: " . (empty($accountid) ? 'Missing' : 'Present') . "</p>";
} else {
    echo "<p class='success'>✅ Plugin configuration found</p>";
    echo "<p class='info'>Account ID: $accountid</p>";
    echo "<p class='info'>API Token: " . substr($apitoken, 0, 10) . "...</p>";
    
    // Test direct API call to get video details
    echo "<h2>Test: Get Video Details (Direct API)</h2>";
    
    $url = "https://api.cloudflare.com/client/v4/accounts/$accountid/stream/$videouid";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apitoken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p class='info'>HTTP Status: $httpCode</p>";
    
    if ($error) {
        echo "<p class='error'>cURL Error: $error</p>";
    } else if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<p class='success'>✅ Video found on Cloudflare</p>";
            $video = $data['result'];
            echo "<p class='info'>Status: " . ($video['status']['state'] ?? 'unknown') . "</p>";
            echo "<p class='info'>Duration: " . ($video['duration'] ?? 'unknown') . "s</p>";
            echo "<p class='info'>Size: " . ($video['size'] ?? 'unknown') . " bytes</p>";
            
            if (isset($video['status']['state']) && $video['status']['state'] === 'ready') {
                echo "<p class='success'>✅ Video is ready for playback</p>";
                
                // Test player without token first
                echo "<h2>Test: Basic Player (No Token)</h2>";
                echo "<div id='player-no-token' style='width:100%; max-width:800px; height:450px; border:1px solid #ccc; margin:10px 0;'>";
                echo "<p>Loading basic player...</p>";
                echo "</div>";
                
                echo "<script src='https://embed.cloudflarestream.com/embed/sdk.latest.js'></script>";
                echo "<script>";
                echo "
                // Test basic player without token
                try {
                    const basicPlayer = Stream(document.getElementById('player-no-token'), '$videouid');
                    
                    basicPlayer.addEventListener('error', (e) => {
                        console.error('Basic player error:', e);
                        document.getElementById('player-no-token').innerHTML = '<p style=\"color:red;\">❌ Basic player failed: ' + (e.message || 'Unknown error') + '</p>';
                    });
                    
                    basicPlayer.addEventListener('loadeddata', () => {
                        console.log('Basic player: Video loaded');
                        document.getElementById('player-no-token').innerHTML = '<p style=\"color:green;\">✅ Basic player loaded successfully!</p>';
                    });
                } catch (e) {
                    document.getElementById('player-no-token').innerHTML = '<p style=\"color:red;\">❌ Basic player exception: ' + e.message + '</p>';
                }
                ";
                echo "</script>";
                
            } else {
                echo "<p class='error'>❌ Video not ready (status: " . ($video['status']['state'] ?? 'unknown') . ")</p>";
            }
        } else {
            echo "<p class='error'>❌ API returned error</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<p class='error'>❌ API call failed (HTTP $httpCode)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

echo "</body></html>";