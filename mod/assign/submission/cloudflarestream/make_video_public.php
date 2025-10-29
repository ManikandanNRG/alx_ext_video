<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/cloudflarestream/classes/api/cloudflare_client.php');

use assignsubmission_cloudflarestream\api\cloudflare_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$video_uid = required_param('videouid', PARAM_TEXT);

$apitoken = get_config('assignsubmission_cloudflarestream', 'apitoken');
$accountid = get_config('assignsubmission_cloudflarestream', 'accountid');

echo '<h2>Make Video Public (Disable Signed URLs)</h2>';
echo '<p>Video UID: <strong>' . htmlspecialchars($video_uid) . '</strong></p>';

if (optional_param('confirm', 0, PARAM_INT)) {
    // Make video public
    $endpoint = "/accounts/{$accountid}/stream/{$video_uid}";
    $data = ['requireSignedURLs' => false];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4{$endpoint}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apitoken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response);
    
    if ($httpcode == 200 && $result->success) {
        echo '<div style="color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb;">';
        echo '✅ <strong>SUCCESS!</strong> Video is now PUBLIC (requireSignedURLs: false)';
        echo '</div>';
        
        echo '<h3>Test Public Playback:</h3>';
        echo '<iframe src="https://customer-h1fjam2t1q5d55si.cloudflarestream.com/' . htmlspecialchars($video_uid) . '/iframe" 
              style="border: none; width: 800px; height: 450px;" 
              allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" 
              allowfullscreen="true"></iframe>';
        
        echo '<p><a href="test_complete_workflow.php?videouid=' . urlencode($video_uid) . '">Test with Workflow</a></p>';
    } else {
        echo '<div style="color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb;">';
        echo '❌ <strong>FAILED!</strong> ' . htmlspecialchars($result->errors[0]->message ?? 'Unknown error');
        echo '</div>';
    }
} else {
    echo '<div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107;">';
    echo '<h3>⚠️ Warning</h3>';
    echo '<p>This will make the video PUBLIC and accessible without authentication.</p>';
    echo '<p>Anyone with the video URL will be able to watch it.</p>';
    echo '</div>';
    
    echo '<form method="get">';
    echo '<input type="hidden" name="videouid" value="' . htmlspecialchars($video_uid) . '">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" style="padding: 10px 20px; background: #ffc107; color: #000; border: none; cursor: pointer;">Make Video Public</button>';
    echo '</form>';
}
?>
