<?php
require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

echo '<h2>Get Cloudflare Customer Subdomain</h2>';

// Get account details to find customer subdomain
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/accounts/{$accountid}/stream");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apitoken
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response);

if ($httpcode == 200 && $result->success && !empty($result->result)) {
    $first_video = $result->result[0];
    
    echo '<h3>✅ Found Customer Subdomain from Video URLs:</h3>';
    
    // Extract customer subdomain from playback URL
    if (isset($first_video->playback->hls)) {
        $hls_url = $first_video->playback->hls;
        echo '<p><strong>HLS URL:</strong> ' . htmlspecialchars($hls_url) . '</p>';
        
        // Extract subdomain
        if (preg_match('/https:\/\/(customer-[a-z0-9]+)\.cloudflarestream\.com/', $hls_url, $matches)) {
            $customer_subdomain = $matches[1];
            echo '<div style="padding: 20px; background: #d4edda; border: 2px solid #28a745;">';
            echo '<h2 style="color: #28a745;">✅ Customer Subdomain: <code>' . htmlspecialchars($customer_subdomain) . '</code></h2>';
            echo '</div>';
            
            echo '<h3>Update Your Code:</h3>';
            echo '<p>Replace all instances of <code>customer-h1fjam2t1qsd5s5i</code> with <code>' . htmlspecialchars($customer_subdomain) . '</code></p>';
        }
    }
    
    echo '<h3>Sample Video Details:</h3>';
    echo '<pre>' . print_r($first_video, true) . '</pre>';
    
} else {
    echo '<p style="color: red;">Error: ' . htmlspecialchars($result->errors[0]->message ?? 'Unknown error') . '</p>';
}
?>
