<?php
/**
 * Test AWS credentials by making a simple S3 API call.
 *
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/test_aws_credentials.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/test_aws_credentials.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('AWS Credentials Test');
$PAGE->set_heading('AWS Credentials Test');

echo $OUTPUT->header();

echo '<h2>AWS Credentials Test</h2>';

// Get credentials.
$accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
$secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
$bucket = get_config('assignsubmission_s3video', 's3_bucket');
$region = get_config('assignsubmission_s3video', 's3_region');

echo '<h3>Configuration</h3>';
echo '<table class="generaltable">';
echo '<tr><td>Access Key:</td><td>' . htmlspecialchars($accesskey) . '</td></tr>';
echo '<tr><td>Secret Key:</td><td>' . str_repeat('*', strlen($secretkey)) . ' (' . strlen($secretkey) . ' characters)</td></tr>';
echo '<tr><td>Bucket:</td><td>' . htmlspecialchars($bucket) . '</td></tr>';
echo '<tr><td>Region:</td><td>' . htmlspecialchars($region) . '</td></tr>';
echo '</table>';

echo '<h3>Test 1: Initialize S3 Client</h3>';

try {
    $s3client = new S3Client([
        'version' => 'latest',
        'region' => $region,
        'credentials' => [
            'key' => $accesskey,
            'secret' => $secretkey,
        ],
    ]);
    echo '<p style="color: green;">✓ S3 client initialized successfully</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Failed to initialize S3 client:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Test 2: List Buckets (Verify Credentials)</h3>';

$credentialsvalid = false;
try {
    $result = $s3client->listBuckets();
    echo '<p style="color: green;">✓ Successfully authenticated with AWS!</p>';
    echo '<p>Your IAM user has access to the following buckets:</p>';
    echo '<ul>';
    foreach ($result['Buckets'] as $bucketinfo) {
        $bucketname = $bucketinfo['Name'];
        if ($bucketname === $bucket) {
            echo '<li><strong>' . htmlspecialchars($bucketname) . '</strong> (configured bucket) ✓</li>';
        } else {
            echo '<li>' . htmlspecialchars($bucketname) . '</li>';
        }
    }
    echo '</ul>';
    $credentialsvalid = true;
} catch (AwsException $e) {
    echo '<p style="color: red;">✗ AWS API call failed:</p>';
    echo '<p><strong>Error Code:</strong> ' . htmlspecialchars($e->getAwsErrorCode()) . '</p>';
    echo '<p><strong>Error Message:</strong> ' . htmlspecialchars($e->getAwsErrorMessage()) . '</p>';
    echo '<p><strong>HTTP Status:</strong> ' . $e->getStatusCode() . '</p>';
    
    echo '<h4>Common Issues:</h4>';
    echo '<ul>';
    echo '<li><strong>InvalidAccessKeyId:</strong> The Access Key ID is incorrect or doesn\'t exist</li>';
    echo '<li><strong>SignatureDoesNotMatch:</strong> The Secret Key is incorrect</li>';
    echo '<li><strong>AccessDenied:</strong> The IAM user doesn\'t have s3:ListAllMyBuckets permission (this is okay, we don\'t need it)</li>';
    echo '</ul>';
    
    if ($e->getAwsErrorCode() === 'AccessDenied') {
        echo '<p style="color: orange;">⚠ This error is okay - we don\'t need ListAllMyBuckets permission for uploads. Continuing to important tests...</p>';
        $credentialsvalid = true; // Credentials are valid, just missing this permission.
    } else {
        echo '<p style="color: red;"><strong>CRITICAL:</strong> Your credentials are invalid. Fix them before continuing.</p>';
        echo $OUTPUT->footer();
        exit;
    }
}

// Only continue if credentials are valid.
if (!$credentialsvalid) {
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Test 3: Check Bucket Access</h3>';

try {
    $result = $s3client->headBucket(['Bucket' => $bucket]);
    echo '<p style="color: green;">✓ Successfully accessed bucket: <strong>' . htmlspecialchars($bucket) . '</strong></p>';
} catch (AwsException $e) {
    echo '<p style="color: red;">✗ Cannot access bucket: <strong>' . htmlspecialchars($bucket) . '</strong></p>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getAwsErrorMessage()) . '</p>';
    
    if ($e->getAwsErrorCode() === 'NoSuchBucket') {
        echo '<p>The bucket does not exist or is in a different region.</p>';
    } else if ($e->getAwsErrorCode() === 'AccessDenied' || $e->getAwsErrorCode() === '403') {
        echo '<p>Your IAM user doesn\'t have permission to access this bucket.</p>';
    }
}

echo '<h3>Test 4: Check Upload Permission</h3>';

$testkey = 'test-uploads/credential-test-' . time() . '.txt';
$testcontent = 'This is a test file to verify upload permissions.';

try {
    $result = $s3client->putObject([
        'Bucket' => $bucket,
        'Key' => $testkey,
        'Body' => $testcontent,
        'ACL' => 'private',
    ]);
    echo '<p style="color: green;">✓ Successfully uploaded test file!</p>';
    echo '<p>Test file key: <code>' . htmlspecialchars($testkey) . '</code></p>';
    
    // Clean up test file.
    try {
        $s3client->deleteObject([
            'Bucket' => $bucket,
            'Key' => $testkey,
        ]);
        echo '<p style="color: green;">✓ Test file deleted successfully</p>';
    } catch (Exception $e) {
        echo '<p style="color: orange;">⚠ Could not delete test file (this is okay)</p>';
    }
    
} catch (AwsException $e) {
    echo '<p style="color: red;">✗ Upload test failed:</p>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getAwsErrorMessage()) . '</p>';
    
    echo '<h4>Required IAM Permissions:</h4>';
    echo '<p>Your IAM user needs these permissions on the bucket:</p>';
    echo '<ul>';
    echo '<li><code>s3:PutObject</code> - Upload files</li>';
    echo '<li><code>s3:GetObject</code> - Read files</li>';
    echo '<li><code>s3:DeleteObject</code> - Delete files (optional)</li>';
    echo '</ul>';
}

echo '<hr>';
echo '<h3>Summary</h3>';
echo '<p>If all tests passed, your AWS credentials are configured correctly and the plugin should work!</p>';
echo '<p>If any tests failed, check the error messages above and fix the IAM permissions in AWS.</p>';

echo $OUTPUT->footer();
