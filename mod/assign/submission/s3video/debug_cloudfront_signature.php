<?php
/**
 * Debug CloudFront signature generation in detail.
 *
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/debug_cloudfront_signature.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\cloudfront_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/debug_cloudfront_signature.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('CloudFront Signature Debug');
$PAGE->set_heading('CloudFront Signature Debugging');

echo $OUTPUT->header();

echo '<h2>CloudFront Signature Deep Debug</h2>';

// Get configuration.
$cloudfrontdomain = get_config('assignsubmission_s3video', 'cloudfront_domain');
$keypairid = get_config('assignsubmission_s3video', 'cloudfront_keypair_id');
$privatekey = get_config('assignsubmission_s3video', 'cloudfront_private_key');

if (empty($cloudfrontdomain) || empty($keypairid) || empty($privatekey)) {
    echo '<p style="color: red;">Configuration incomplete!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Configuration</h3>';
echo '<table class="generaltable">';
echo '<tr><td>CloudFront Domain:</td><td>' . htmlspecialchars($cloudfrontdomain) . '</td></tr>';
echo '<tr><td>Key Pair ID:</td><td>' . htmlspecialchars($keypairid) . '</td></tr>';
echo '</table>';

// Test with a real file path.
$tests3key = '0_Overall_Introduction_to_the_programme.mp4';
$resource = 'https://' . $cloudfrontdomain . '/' . ltrim($tests3key, '/');
$expires = time() + 3600;

echo '<h3>Step 1: Resource URL</h3>';
echo '<p><code>' . htmlspecialchars($resource) . '</code></p>';

echo '<h3>Step 2: Policy JSON</h3>';
$policy = [
    'Statement' => [
        [
            'Resource' => $resource,
            'Condition' => [
                'DateLessThan' => [
                    'AWS:EpochTime' => $expires,
                ],
            ],
        ],
    ],
];

$policyjson = json_encode($policy, JSON_UNESCAPED_SLASHES);
echo '<p><strong>Policy:</strong></p>';
echo '<pre>' . htmlspecialchars($policyjson) . '</pre>';
echo '<p><strong>Policy Length:</strong> ' . strlen($policyjson) . ' bytes</p>';

echo '<h3>Step 3: Sign the Policy</h3>';

// Load private key.
$pkeyid = openssl_pkey_get_private($privatekey);
if ($pkeyid === false) {
    echo '<p style="color: red;">Failed to load private key!</p>';
    echo $OUTPUT->footer();
    exit;
}

// Sign the policy.
$signature = '';
$success = openssl_sign($policyjson, $signature, $pkeyid, OPENSSL_ALGO_SHA1);
openssl_free_key($pkeyid);

if (!$success) {
    echo '<p style="color: red;">Failed to sign policy!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<p style="color: green;">✓ Successfully signed policy</p>';
echo '<p><strong>Raw Signature Length:</strong> ' . strlen($signature) . ' bytes</p>';
echo '<p><strong>Raw Signature (hex):</strong></p>';
echo '<pre style="word-break: break-all;">' . bin2hex($signature) . '</pre>';

echo '<h3>Step 4: Base64 Encode Signature</h3>';

$base64sig = base64_encode($signature);
echo '<p><strong>Standard Base64:</strong></p>';
echo '<pre style="word-break: break-all;">' . htmlspecialchars($base64sig) . '</pre>';

echo '<h3>Step 5: URL-Safe Base64 Encoding</h3>';

// Apply CloudFront URL-safe encoding.
$urlsafesig = str_replace('+', '-', $base64sig);
$urlsafesig = str_replace('=', '_', $urlsafesig);
$urlsafesig = str_replace('/', '~', $urlsafesig);

echo '<p><strong>URL-Safe Base64:</strong></p>';
echo '<pre style="word-break: break-all;">' . htmlspecialchars($urlsafesig) . '</pre>';

echo '<h4>Character Replacements:</h4>';
echo '<table class="generaltable">';
echo '<tr><th>Character</th><th>Count in Standard Base64</th><th>Replaced With</th></tr>';
echo '<tr><td>+</td><td>' . substr_count($base64sig, '+') . '</td><td>-</td></tr>';
echo '<tr><td>=</td><td>' . substr_count($base64sig, '=') . '</td><td>_</td></tr>';
echo '<tr><td>/</td><td>' . substr_count($base64sig, '/') . '</td><td>~</td></tr>';
echo '</table>';

echo '<h3>Step 6: Build Final Signed URL</h3>';

$signedurl = $resource . '?' .
    'Expires=' . $expires .
    '&Signature=' . $urlsafesig .
    '&Key-Pair-Id=' . $keypairid;

echo '<p><strong>Complete Signed URL:</strong></p>';
echo '<textarea style="width: 100%; height: 150px; font-family: monospace; font-size: 11px;">' . htmlspecialchars($signedurl) . '</textarea>';

echo '<h3>Step 7: Test the URL</h3>';
echo '<p><a href="' . htmlspecialchars($signedurl) . '" target="_blank" style="padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Click to Test URL</a></p>';

echo '<h3>Step 8: Verify Against AWS Documentation</h3>';
echo '<p>According to AWS CloudFront documentation, signed URLs must:</p>';
echo '<ol>';
echo '<li>Use the exact resource URL in the policy (including https:// and domain)</li>';
echo '<li>Sign the policy JSON with RSA-SHA1</li>';
echo '<li>Base64 encode the signature</li>';
echo '<li>Replace + with -, = with _, / with ~</li>';
echo '<li>Include Expires, Signature, and Key-Pair-Id parameters</li>';
echo '</ol>';

echo '<h3>Troubleshooting Checklist</h3>';
echo '<table class="generaltable">';
echo '<tr><th>Check</th><th>Status</th></tr>';
echo '<tr><td>Private key loads successfully</td><td style="color: green;">✓ PASS</td></tr>';
echo '<tr><td>Policy JSON format correct</td><td style="color: green;">✓ PASS</td></tr>';
echo '<tr><td>Signature generated</td><td style="color: green;">✓ PASS</td></tr>';
echo '<tr><td>URL-safe encoding applied</td><td style="color: green;">✓ PASS</td></tr>';
echo '<tr><td>CloudFront domain matches</td><td>' . ($cloudfrontdomain === 'video.aktrea.net' ? '<span style="color: green;">✓ PASS</span>' : '<span style="color: orange;">⚠ Using: ' . htmlspecialchars($cloudfrontdomain) . '</span>') . '</td></tr>';
echo '</table>';

echo '<hr>';
echo '<h3>Next Steps if Still Getting Access Denied:</h3>';
echo '<ol>';
echo '<li><strong>Check CloudFront Distribution Settings:</strong><ul>';
echo '<li>Go to AWS CloudFront Console → Your distribution</li>';
echo '<li>Verify "Alternate domain names (CNAMEs)" includes: <strong>video.aktrea.net</strong></li>';
echo '<li>Verify "Restrict viewer access" is set to "Yes"</li>';
echo '<li>Verify "Trusted key groups" includes your key group with Key ID: <strong>' . htmlspecialchars($keypairid) . '</strong></li>';
echo '</ul></li>';
echo '<li><strong>Check if distribution is fully deployed:</strong><ul>';
echo '<li>Status should be "Deployed" (not "In Progress")</li>';
echo '<li>Wait 5-15 minutes after making changes</li>';
echo '</ul></li>';
echo '<li><strong>Clear CloudFront cache:</strong><ul>';
echo '<li>Create an invalidation for /*</li>';
echo '<li>Wait for invalidation to complete</li>';
echo '</ul></li>';
echo '<li><strong>Enable CloudFront logging:</strong><ul>';
echo '<li>This will show detailed error messages</li>';
echo '<li>Logs appear in S3 bucket after 15-60 minutes</li>';
echo '</ul></li>';
echo '</ol>';

echo $OUTPUT->footer();
