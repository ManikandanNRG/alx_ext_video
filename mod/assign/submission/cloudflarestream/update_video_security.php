<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

use assignsubmission_cloudflarestream\api\cloudflare_client;

$videouid = '103366d38ef2bd1ea4b02e6ec6e0dcde';

echo "<h2>Update Video Security Settings</h2>";
echo "<p>Video UID: $videouid</p>";

// Get plugin configuration
$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

if (empty($apitoken) || empty($accountid)) {
    die('Plugin not configured');
}

// Create Cloudflare client
$client = new cloudflare_client($apitoken, $accountid);

if (isset($_GET['update']) && $_GET['update'] === 'yes') {
    echo "<h3>Updating video to require signed URLs...</h3>";
    
    try {
        // Update video settings to require signed URLs
        $endpoint = "/accounts/{$accountid}/stream/{$videouid}";
        $data = [
            'requireSignedURLs' => true
        ];
        
        // Make direct API call since this method might not exist in our client
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4{$endpoint}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apitoken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<h4>API Response (HTTP $httpCode):</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<h3>✅ Video updated to require signed URLs!</h3>";
            echo "<p>The video is now private and requires tokens for playback.</p>";
        } else {
            echo "<h3>❌ Failed to update video settings</h3>";
        }
        
    } catch (Exception $e) {
        echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
    }
    
} else {
    // Show current video details
    try {
        $details = $client->get_video_details($videouid);
        echo "<h3>Current Video Settings:</h3>";
        echo "<pre>";
        print_r($details);
        echo "</pre>";
        
        if (isset($details->requireSignedURLs)) {
            if ($details->requireSignedURLs) {
                echo "<p class='success'>✅ Video requires signed URLs (private)</p>";
            } else {
                echo "<p class='error'>❌ Video does NOT require signed URLs (public)</p>";
                echo "<p><a href='?update=yes'>Click here to make this video private</a></p>";
            }
        } else {
            echo "<p class='info'>ℹ️ requireSignedURLs property not found - likely public</p>";
            echo "<p><a href='?update=yes'>Click here to make this video private</a></p>";
        }
        
    } catch (Exception $e) {
        echo "<h3>❌ Error getting video details: " . $e->getMessage() . "</h3>";
    }
}

echo "<style>.success{color:green;} .error{color:red;} .info{color:blue;}</style>";
?>