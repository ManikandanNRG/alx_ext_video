<?php
/**
 * Test CloudFront signed URL generation.
 *
 * This script tests if CloudFront signed URLs are being generated correctly.
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/test_cloudfront_signing.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\cloudfront_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/test_cloudfront_signing.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('CloudFront Signing Test');
$PAGE->set_heading('CloudFront Signed URL Test');

echo $OUTPUT->header();

echo '<h2>CloudFront Configuration Test</h2>';

// Get configuration.
$cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

echo '<h3>1. Configuration Check</h3>';
echo '<table class="generaltable">';
echo '<tr><th>Setting</th><th>Status</th><th>Value</th></tr>';

echo '<tr><td>CloudFront Domain</td>';
if (!empty($cloudfrontdomain)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>' . htmlspecialchars($cloudfrontdomain) . '</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '<tr><td>Key Pair ID</td>';
if (!empty($keypairid)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>' . htmlspecialchars($keypairid) . '</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '<tr><td>Private Key</td>';
if (!empty($privatekey)) {
    echo '<td style="color: green;">✓ Configured</td>';
    $keylines = explode("\n", trim($privatekey));
    echo '<td>Length: ' . strlen($privatekey) . ' bytes<br>';
    echo 'First line: ' . htmlspecialchars($keylines[0]) . '<br>';
    echo 'Last line: ' . htmlspecialchars($keylines[count($keylines) - 1]) . '</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '</table>';

if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
    echo '<p style="color: red;"><strong>Error:</strong> CloudFront configuration is incomplete. Please configure all settings.</p>';
    echo $OUTPUT->footer();
    exit;
}

// Test private key validity.
echo '<h3>2. Private Key Validation</h3>';
$pkeyid = openssl_pkey_get_private($privatekey);
if ($pkeyid === false) {
    echo '<p style="color: red;">✗ <strong>Private key is INVALID!</strong></p>';
    echo '<p>OpenSSL error: ' . openssl_error_string() . '</p>';
    echo '<p><strong>Solution:</strong> Make sure your private key is in PEM format and includes the header/footer:</p>';
    echo '<pre>-----BEGIN RSA PRIVATE KEY-----
...key content...
-----END RSA PRIVATE KEY-----</pre>';
} else {
    echo '<p style="color: green;">✓ Private key is valid</p>';
    $keydetails = openssl_pkey_get_details($pkeyid);
    echo '<p>Key type: ' . $keydetails['type'] . ' (should be 0 for RSA)</p>';
    echo '<p>Key bits: ' . $keydetails['bits'] . '</p>';
    openssl_free_key($pkeyid);
}

// Test signed URL generation.
echo '<h3>3. Signed URL Generation Test</h3>';

try {
    $cfclient = new cloudfront_client($cloudfrontdomain, $keypairid, $privatekey);
    
    // Test with a sample S3 key.
    $tests3key = 'videos/test/sample.mp4';
    $signedurl = $cfclient->get_signed_url($tests3key, 3600);
    
    echo '<p style="color: green;">✓ Signed URL generated successfully!</p>';
    echo '<p><strong>Test S3 Key:</strong> ' . htmlspecialchars($tests3key) . '</p>';
    echo '<p><strong>Generated URL:</strong></p>';
    echo '<textarea style="width: 100%; height: 100px; font-family: monospace; font-size: 11px;">' . htmlspecialchars($signedurl) . '</textarea>';
    
    // Parse the URL to show components.
    $urlparts = parse_url($signedurl);
    parse_str($urlparts['query'], $queryparams);
    
    echo '<h4>URL Components:</h4>';
    echo '<table class="generaltable">';
    echo '<tr><th>Component</th><th>Value</th></tr>';
    echo '<tr><td>Domain</td><td>' . htmlspecialchars($urlparts['host']) . '</td></tr>';
    echo '<tr><td>Path</td><td>' . htmlspecialchars($urlparts['path']) . '</td></tr>';
    echo '<tr><td>Expires</td><td>' . htmlspecialchars($queryparams['Expires']) . ' (' . date('Y-m-d H:i:s', $queryparams['Expires']) . ')</td></tr>';
    echo '<tr><td>Key-Pair-Id</td><td>' . htmlspecialchars($queryparams['Key-Pair-Id']) . '</td></tr>';
    echo '<tr><td>Signature</td><td>' . htmlspecialchars(substr($queryparams['Signature'], 0, 50)) . '... (truncated)</td></tr>';
    echo '</table>';
    
    echo '<h4>Troubleshooting Steps:</h4>';
    echo '<ol>';
    echo '<li><strong>Verify Key Pair ID matches:</strong> The Key-Pair-Id above should match your CloudFront trusted key group configuration</li>';
    echo '<li><strong>Check CloudFront Distribution:</strong> Make sure your distribution is configured with a trusted key group that includes this key pair ID</li>';
    echo '<li><strong>Test the URL:</strong> Try accessing the URL above in a new browser tab (it will 404 if the video doesn\'t exist, but should NOT give 403 if signing is correct)</li>';
    echo '<li><strong>Check CloudFront logs:</strong> Enable CloudFront logging to see detailed error messages</li>';
    echo '</ol>';
    
    echo '<h4>Common Issues:</h4>';
    echo '<ul>';
    echo '<li><strong>403 Forbidden:</strong> Key Pair ID doesn\'t match CloudFront configuration, or private key doesn\'t match public key</li>';
    echo '<li><strong>Signature mismatch:</strong> Private key format is incorrect or corrupted</li>';
    echo '<li><strong>Expired:</strong> Server time is incorrect (check with: ' . date('Y-m-d H:i:s') . ')</li>';
    echo '</ul>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">✗ <strong>Error generating signed URL:</strong></p>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '<hr>';
echo '<h3>Next Steps</h3>';
echo '<p>If the signed URL generation succeeded but you still get 403 errors when playing videos:</p>';
echo '<ol>';
echo '<li>Go to AWS CloudFront Console</li>';
echo '<li>Select your distribution (domain: ' . htmlspecialchars($cloudfrontdomain) . ')</li>';
echo '<li>Go to "Security" tab → "Key management"</li>';
echo '<li>Verify that a trusted key group exists with your Key Pair ID: <strong>' . htmlspecialchars($keypairid) . '</strong></li>';
echo '<li>If not, create a trusted key group and add your public key</li>';
echo '<li>Associate the trusted key group with your distribution</li>';
echo '</ol>';

echo $OUTPUT->footer();
