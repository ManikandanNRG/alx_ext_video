<?php
/**
 * Debug S3 upload presigned POST generation.
 *
 * Run this from browser: http://your-moodle-site/mod/assign/submission/s3video/debug_s3_upload.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/submission/s3video/locallib.php');

use assignsubmission_s3video\api\s3_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/assign/submission/s3video/debug_s3_upload.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('S3 Upload Debug');
$PAGE->set_heading('S3 Upload Debugging');

echo $OUTPUT->header();

echo '<h2>S3 Upload Configuration Debug</h2>';

// Get S3 configuration.
$bucket = get_config('assignsubmission_s3video', 's3_bucket');
$region = get_config('assignsubmission_s3video', 's3_region');
$accesskey = get_config('assignsubmission_s3video', 'aws_access_key');
$secretkey = get_config('assignsubmission_s3video', 'aws_secret_key');

echo '<h3>Step 1: Configuration Check</h3>';
echo '<table class="generaltable">';
echo '<tr><th>Setting</th><th>Status</th><th>Value</th></tr>';

echo '<tr><td>S3 Bucket</td>';
if (!empty($bucket)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>' . htmlspecialchars($bucket) . '</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '<tr><td>S3 Region</td>';
if (!empty($region)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>' . htmlspecialchars($region) . '</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '<tr><td>Access Key</td>';
if (!empty($accesskey)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>' . htmlspecialchars(substr($accesskey, 0, 8)) . '...</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '<tr><td>Secret Key</td>';
if (!empty($secretkey)) {
    echo '<td style="color: green;">✓ Configured</td>';
    echo '<td>****** (hidden)</td>';
} else {
    echo '<td style="color: red;">✗ Missing</td>';
    echo '<td>-</td>';
}
echo '</tr>';

echo '</table>';

if (empty($bucket) || empty($region) || empty($accesskey) || empty($secretkey)) {
    echo '<p style="color: red;"><strong>Error:</strong> S3 configuration is incomplete!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 2: Initialize S3 Client</h3>';

try {
    $s3client = new s3_client($accesskey, $secretkey, $bucket, $region);
    echo '<p style="color: green;">✓ S3 client initialized successfully</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Failed to initialize S3 client:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 3: Generate Presigned POST</h3>';

$tests3key = 'videos/test/' . time() . '_test.mp4';
$testmimetype = 'video/mp4';
$testmaxsize = 100 * 1024 * 1024; // 100 MB.

echo '<p><strong>Test S3 Key:</strong> ' . htmlspecialchars($tests3key) . '</p>';
echo '<p><strong>MIME Type:</strong> ' . htmlspecialchars($testmimetype) . '</p>';
echo '<p><strong>Max Size:</strong> ' . htmlspecialchars($testmaxsize) . ' bytes</p>';

try {
    $presignedpost = $s3client->get_presigned_post($tests3key, $testmaxsize, $testmimetype, 3600);
    echo '<p style="color: green;">✓ Presigned POST generated successfully</p>';
    
    echo '<h4>Presigned POST Details:</h4>';
    echo '<p><strong>Upload URL:</strong></p>';
    echo '<pre>' . htmlspecialchars($presignedpost['url']) . '</pre>';
    
    echo '<p><strong>Form Fields:</strong></p>';
    echo '<table class="generaltable">';
    echo '<tr><th>Field Name</th><th>Value</th></tr>';
    foreach ($presignedpost['fields'] as $fieldname => $fieldvalue) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($fieldname) . '</code></td>';
        if (strlen($fieldvalue) > 100) {
            echo '<td><code>' . htmlspecialchars(substr($fieldvalue, 0, 100)) . '...</code> (truncated)</td>';
        } else {
            echo '<td><code>' . htmlspecialchars($fieldvalue) . '</code></td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    
    // Check for required fields.
    echo '<h4>Required Fields Check:</h4>';
    echo '<table class="generaltable">';
    echo '<tr><th>Field</th><th>Status</th></tr>';
    
    $requiredfields = ['key', 'Content-Type', 'acl'];
    foreach ($requiredfields as $field) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($field) . '</td>';
        if (isset($presignedpost['fields'][$field])) {
            echo '<td style="color: green;">✓ Present</td>';
        } else {
            echo '<td style="color: red;">✗ Missing</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Failed to generate presigned POST:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h3>Step 4: Check IAM User Permissions</h3>';
echo '<p>Your IAM user (<strong>' . htmlspecialchars(substr($accesskey, 0, 8)) . '...</strong>) needs these permissions:</p>';
echo '<ul>';
echo '<li><code>s3:PutObject</code> - Upload files</li>';
echo '<li><code>s3:GetObject</code> - Read files (for verification)</li>';
echo '<li><code>s3:DeleteObject</code> - Delete files (optional)</li>';
echo '</ul>';

echo '<h3>Step 5: Check S3 Bucket Policy</h3>';
echo '<p>Your S3 bucket (<strong>' . htmlspecialchars($bucket) . '</strong>) needs a policy that allows your IAM user to upload:</p>';
echo '<pre>{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowCloudFrontServicePrincipal",
      "Effect": "Allow",
      "Principal": {
        "Service": "cloudfront.amazonaws.com"
      },
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::' . htmlspecialchars($bucket) . '/*",
      "Condition": {
        "ArnLike": {
          "AWS:SourceArn": "arn:aws:cloudfront::YOUR_ACCOUNT_ID:distribution/YOUR_DISTRIBUTION_ID"
        }
      }
    },
    {
      "Sid": "AllowMoodleIAMUserUpload",
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::YOUR_ACCOUNT_ID:user/YOUR_IAM_USERNAME"
      },
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::' . htmlspecialchars($bucket) . '/*"
    }
  ]
}</pre>';

echo '<h3>Troubleshooting 403 Errors</h3>';
echo '<p>If you\'re getting 403 errors when uploading:</p>';
echo '<ol>';
echo '<li><strong>Check IAM User Policy:</strong><ul>';
echo '<li>Go to AWS IAM Console → Users → Your user</li>';
echo '<li>Check "Permissions" tab</li>';
echo '<li>Ensure policy allows s3:PutObject on your bucket</li>';
echo '</ul></li>';
echo '<li><strong>Check S3 Bucket Policy:</strong><ul>';
echo '<li>Go to AWS S3 Console → Your bucket → Permissions → Bucket policy</li>';
echo '<li>Ensure it allows your IAM user ARN to PutObject</li>';
echo '</ul></li>';
echo '<li><strong>Check S3 Bucket ACL Settings:</strong><ul>';
echo '<li>Go to AWS S3 Console → Your bucket → Permissions → Object Ownership</li>';
echo '<li>Should be "ACLs enabled" or "Bucket owner enforced"</li>';
echo '<li>If "Bucket owner enforced", remove the "acl" field from presigned POST</li>';
echo '</ul></li>';
echo '<li><strong>Check CORS Configuration:</strong><ul>';
echo '<li>Go to AWS S3 Console → Your bucket → Permissions → CORS</li>';
echo '<li>Ensure it allows POST from your Moodle domain</li>';
echo '</ul></li>';
echo '</ol>';

echo '<h3>Next Steps</h3>';
echo '<p>1. Verify the presigned POST fields above look correct</p>';
echo '<p>2. Check your AWS IAM and S3 bucket policies</p>';
echo '<p>3. Try uploading a video through the plugin again</p>';
echo '<p>4. Check browser console for the exact error message</p>';

echo $OUTPUT->footer();
