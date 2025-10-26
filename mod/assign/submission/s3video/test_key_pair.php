<?php
/**
 * Test if CloudFront public/private key pair matches.
 *
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/test_key_pair.php
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/test_key_pair.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('CloudFront Key Pair Test');
$PAGE->set_heading('CloudFront Public/Private Key Pair Verification');

echo $OUTPUT->header();

echo '<h2>CloudFront Key Pair Verification</h2>';

// Get private key from config.
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');

if (empty($privatekey)) {
    echo '<p style="color: red;">Private key not configured!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 1: Extract Public Key from Private Key</h3>';

// Load private key.
$pkeyid = openssl_pkey_get_private($privatekey);
if ($pkeyid === false) {
    echo '<p style="color: red;">✗ Failed to load private key!</p>';
    echo '<p>Error: ' . openssl_error_string() . '</p>';
    echo $OUTPUT->footer();
    exit;
}

// Get key details and extract public key.
$keydetails = openssl_pkey_get_details($pkeyid);
$publickey = $keydetails['key'];

echo '<p style="color: green;">✓ Successfully extracted public key from private key</p>';
echo '<h4>Public Key (PEM format):</h4>';
echo '<textarea style="width: 100%; height: 200px; font-family: monospace; font-size: 11px;">' . htmlspecialchars($publickey) . '</textarea>';

echo '<h3>Step 2: Verify Key Pair Works</h3>';

// Test signing and verification.
$testdata = 'Test message for CloudFront signature verification';
$signature = '';
$signresult = openssl_sign($testdata, $signature, $pkeyid, OPENSSL_ALGO_SHA1);

if (!$signresult) {
    echo '<p style="color: red;">✗ Failed to sign test data!</p>';
    openssl_free_key($pkeyid);
    echo $OUTPUT->footer();
    exit;
}

echo '<p style="color: green;">✓ Successfully signed test data</p>';

// Verify signature with public key.
$pubkeyid = openssl_pkey_get_public($publickey);
if ($pubkeyid === false) {
    echo '<p style="color: red;">✗ Failed to load extracted public key!</p>';
    openssl_free_key($pkeyid);
    echo $OUTPUT->footer();
    exit;
}

$verifyresult = openssl_verify($testdata, $signature, $pubkeyid, OPENSSL_ALGO_SHA1);
openssl_free_key($pkeyid);
openssl_free_key($pubkeyid);

if ($verifyresult === 1) {
    echo '<p style="color: green;">✓ <strong>Signature verification PASSED!</strong></p>';
    echo '<p>The private key can sign data and the public key can verify it.</p>';
} else if ($verifyresult === 0) {
    echo '<p style="color: red;">✗ <strong>Signature verification FAILED!</strong></p>';
    echo '<p>The public/private key pair does NOT match!</p>';
} else {
    echo '<p style="color: red;">✗ Error during verification: ' . openssl_error_string() . '</p>';
}

echo '<hr>';
echo '<h3>Step 3: Compare with AWS CloudFront Public Key</h3>';
echo '<p><strong>Action Required:</strong></p>';
echo '<ol>';
echo '<li>Copy the public key shown above (the entire content including BEGIN/END lines)</li>';
echo '<li>Go to <strong>AWS CloudFront Console</strong> → <strong>Key management</strong> → <strong>Public keys</strong></li>';
echo '<li>Find the public key with ID: <strong>' . htmlspecialchars($keypairid) . '</strong></li>';
echo '<li>Click on it to view the key value</li>';
echo '<li>Compare it with the public key shown above - they MUST match exactly!</li>';
echo '</ol>';

echo '<h4>If they DON\'T match:</h4>';
echo '<p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">';
echo '<strong>Problem:</strong> The public key in AWS CloudFront doesn\'t match your private key.<br><br>';
echo '<strong>Solution:</strong><br>';
echo '1. Delete the existing public key in CloudFront (ID: ' . htmlspecialchars($keypairid) . ')<br>';
echo '2. Create a NEW public key using the key shown above<br>';
echo '3. AWS will generate a NEW Key ID (like K2ABC...)<br>';
echo '4. Update your Moodle plugin settings with the NEW Key ID<br>';
echo '5. Make sure the trusted key group uses the NEW public key<br>';
echo '</p>';

echo '<h4>If they DO match:</h4>';
echo '<p style="background: #d1ecf1; padding: 15px; border-left: 4px solid #0c5460;">';
echo 'The keys match, so the issue might be:<br>';
echo '• CloudFront distribution behavior not configured correctly<br>';
echo '• Trusted key group not associated with the distribution<br>';
echo '• CloudFront cache needs to be invalidated<br>';
echo '</p>';

echo $OUTPUT->footer();
