<?php
/**
 * Test S3 upload configuration
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

use assignsubmission_s3video\api\s3_client;

echo "<h2>S3 Upload Configuration Test</h2>";
echo "<pre>";

// Get configuration
$accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
$secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');
$bucket = get_config('assignsubmission_s3video', 's3_bucket');
$region = get_config('assignsubmission_s3video', 's3_region');

echo "Configuration:\n";
echo "==============\n";
echo "Bucket: $bucket\n";
echo "Region: $region\n";
echo "Access Key: " . substr($accesskey, 0, 8) . "...\n";
echo "Secret Key: " . (empty($secretkey) ? 'NOT SET' : 'SET') . "\n\n";

// Test S3 client initialization
echo "Testing S3 Client:\n";
echo "==================\n";
try {
    $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
    echo "✓ S3 Client initialized successfully\n\n";
    
    // Generate test presigned POST
    echo "Testing Presigned POST Generation:\n";
    echo "===================================\n";
    $testkey = "test/" . time() . "/test.mp4";
    $presigned = $s3client->get_presigned_post($testkey, 1048576, 'video/mp4', 3600);
    
    echo "✓ Presigned POST generated successfully\n";
    echo "URL: " . $presigned['url'] . "\n";
    echo "Key: " . $presigned['key'] . "\n";
    echo "Fields:\n";
    foreach ($presigned['fields'] as $key => $value) {
        if ($key === 'policy' || $key === 'x-amz-signature') {
            echo "  $key: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "  $key: $value\n";
        }
    }
    echo "\n";
    
    // Check S3 bucket CORS
    echo "S3 CORS Configuration Check:\n";
    echo "============================\n";
    echo "Your S3 bucket CORS should allow:\n";
    echo "- Origin: " . $CFG->wwwroot . "\n";
    echo "- Methods: GET, POST, PUT\n";
    echo "- Headers: *\n";
    echo "- ExposeHeaders: ETag\n\n";
    
    echo "To fix 403 errors, verify in AWS Console:\n";
    echo "1. Go to S3 > $bucket > Permissions > CORS\n";
    echo "2. Ensure your Moodle URL is in AllowedOrigins\n";
    echo "3. Current Moodle URL: " . $CFG->wwwroot . "\n\n";
    
    // Check IAM permissions
    echo "IAM Permissions Check:\n";
    echo "======================\n";
    echo "Your IAM user needs these permissions:\n";
    echo "- s3:PutObject on arn:aws:s3:::$bucket/*\n";
    echo "- s3:GetObject on arn:aws:s3:::$bucket/*\n";
    echo "- s3:DeleteObject on arn:aws:s3:::$bucket/*\n\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";

echo "<h3>Recommended CORS Configuration for S3:</h3>";
echo "<pre>";
echo json_encode([
    [
        "AllowedHeaders" => ["*"],
        "AllowedMethods" => ["GET", "POST", "PUT"],
        "AllowedOrigins" => [$CFG->wwwroot],
        "ExposeHeaders" => ["ETag"],
        "MaxAgeSeconds" => 3000
    ]
], JSON_PRETTY_PRINT);
echo "</pre>";

echo "<p><a href='{$CFG->wwwroot}/admin/settings.php?section=assignsubmission_s3video'>Back to Plugin Settings</a></p>";
