# S3 Video Submission Plugin - Installation Guide

Complete step-by-step guide to install and configure the S3 Video submission plugin for Moodle.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [AWS Setup](#aws-setup)
   - [Step 1: Create S3 Bucket](#step-1-create-s3-bucket)
   - [Step 2: Configure S3 Bucket](#step-2-configure-s3-bucket)
   - [Step 3: Create IAM User](#step-3-create-iam-user)
   - [Step 4: Create CloudFront Distribution](#step-4-create-cloudfront-distribution)
   - [Step 5: Create CloudFront Key Pair](#step-5-create-cloudfront-key-pair)
   - [Step 6: Configure CloudFront Distribution](#step-6-configure-cloudfront-distribution)
3. [Moodle Installation](#moodle-installation)
4. [Plugin Configuration](#plugin-configuration)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before you begin, ensure you have:

- **Moodle 3.9 or higher** installed
- **AWS Account** with billing enabled
- **Administrator access** to your Moodle site
- **AWS SDK for PHP** installed in `/local/aws/sdk/`
- Basic knowledge of AWS services (S3, CloudFront, IAM)

**Estimated Setup Time:** 30-45 minutes

**Estimated Monthly Cost:** 
- S3 Storage: ~$0.023 per GB
- CloudFront: ~$0.085 per GB transferred
- Example: 100 GB storage + 500 GB transfer = ~$45/month

---

## AWS Setup

### Step 1: Create S3 Bucket

1. **Log in to AWS Console**
   - Go to https://console.aws.amazon.com/
   - Navigate to **S3** service

2. **Create New Bucket**
   - Click **"Create bucket"**
   - **Bucket name:** Choose a unique name (e.g., `moodle-video-submissions`)
   - **Region:** Select closest to your users (e.g., `us-east-1`, `eu-west-1`)
   - Click **"Create bucket"**

3. **Note Your Bucket Details**
   ```
   Bucket Name: moodle-video-submissions
   Region: us-east-1
   ```

---

### Step 2: Configure S3 Bucket

#### 2.1 Block Public Access Settings

1. Go to your bucket → **Permissions** tab
2. **Block Public Access** section
3. **Keep all blocks enabled** (videos should not be publicly accessible)
4. Click **"Save changes"**

#### 2.2 Configure CORS

1. Go to **Permissions** tab → **CORS** section
2. Click **"Edit"**
3. Add the following CORS configuration:

```json
[
    {
        "AllowedHeaders": [
            "*"
        ],
        "AllowedMethods": [
            "GET",
            "POST",
            "PUT"
        ],
        "AllowedOrigins": [
            "https://your-moodle-site.com"
        ],
        "ExposeHeaders": [
            "ETag"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

**Important:** Replace `https://your-moodle-site.com` with your actual Moodle URL.

4. Click **"Save changes"**

#### 2.3 Configure Bucket Policy

1. Go to **Permissions** tab → **Bucket policy** section
2. Click **"Edit"**
3. Add the following policy (replace placeholders):

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowCloudFrontServicePrincipal",
            "Effect": "Allow",
            "Principal": {
                "Service": "cloudfront.amazonaws.com"
            },
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::moodle-video-submissions/*",
            "Condition": {
                "StringEquals": {
                    "AWS:SourceArn": "arn:aws:cloudfront::YOUR_ACCOUNT_ID:distribution/YOUR_DISTRIBUTION_ID"
                }
            }
        }
    ]
}
```

**Note:** You'll update `YOUR_ACCOUNT_ID` and `YOUR_DISTRIBUTION_ID` after creating CloudFront distribution.

---

### Step 3: Create IAM User

#### 3.1 Create User

1. Navigate to **IAM** service in AWS Console
2. Click **"Users"** → **"Add users"**
3. **User name:** `moodle-s3-uploader`
4. **Access type:** Select **"Access key - Programmatic access"**
5. Click **"Next: Permissions"**

#### 3.2 Attach Permissions

1. Select **"Attach existing policies directly"**
2. Click **"Create policy"**
3. Switch to **JSON** tab
4. Paste the following policy:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "S3VideoUploadPermissions",
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::moodle-video-submissions",
                "arn:aws:s3:::moodle-video-submissions/*"
            ]
        }
    ]
}
```

5. Click **"Next: Tags"** → **"Next: Review"**
6. **Name:** `MoodleS3VideoUploadPolicy`
7. Click **"Create policy"**
8. Go back to user creation, refresh policies, and select `MoodleS3VideoUploadPolicy`
9. Click **"Next: Tags"** → **"Next: Review"** → **"Create user"**

#### 3.3 Save Credentials

**IMPORTANT:** Save these credentials securely - they won't be shown again!

```
Access Key ID: AKIAIOSFODNN7EXAMPLE
Secret Access Key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
```

---

### Step 4: Create CloudFront Distribution

#### 4.1 Create Distribution

1. Navigate to **CloudFront** service
2. Click **"Create distribution"**
3. **Origin Settings:**
   - **Origin domain:** Select your S3 bucket from dropdown
   - **Origin access:** Select **"Origin access control settings (recommended)"**
   - Click **"Create control setting"**
   - **Name:** `moodle-video-oac`
   - Click **"Create"**
   - **Note:** AWS will show a policy to update in S3 - we'll do this later

4. **Default Cache Behavior Settings:**
   - **Viewer protocol policy:** **"Redirect HTTP to HTTPS"**
   - **Allowed HTTP methods:** **"GET, HEAD, OPTIONS"**
   - **Restrict viewer access:** **"Yes"** (for signed URLs)
   - **Trusted key groups:** We'll configure this in Step 5

5. **Settings:**
   - **Price class:** Choose based on your needs (e.g., "Use all edge locations")
   - **Alternate domain name (CNAME):** Optional (e.g., `video.yourdomain.com`)
   - **Custom SSL certificate:** If using CNAME, select your certificate

6. Click **"Create distribution"**

#### 4.2 Note Distribution Details

After creation, note these details:

```
Distribution Domain Name: d1234567890abc.cloudfront.net
Distribution ID: E1234567890ABC
Distribution ARN: arn:aws:cloudfront::123456789012:distribution/E1234567890ABC
```

#### 4.3 Update S3 Bucket Policy

1. Go back to your S3 bucket → **Permissions** → **Bucket policy**
2. Update the policy with your actual values:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowCloudFrontServicePrincipal",
            "Effect": "Allow",
            "Principal": {
                "Service": "cloudfront.amazonaws.com"
            },
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::moodle-video-submissions/*",
            "Condition": {
                "StringEquals": {
                    "AWS:SourceArn": "arn:aws:cloudfront::123456789012:distribution/E1234567890ABC"
                }
            }
        }
    ]
}
```

3. Click **"Save changes"**

---

### Step 5: Create CloudFront Key Pair

#### 5.1 Create Key Pair (Root Account Required)

**Important:** CloudFront key pairs can only be created by the AWS root account user.

1. **Log in as root user** (not IAM user)
2. Click your account name → **"Security credentials"**
3. Scroll to **"CloudFront key pairs"** section
4. Click **"Create key pair"**
5. **Download both files:**
   - `pk-APKAXXXXXXXXXXXXXXXX.pem` (Private key)
   - `rsa-APKAXXXXXXXXXXXXXXXX.pem` (Public key)

6. **Note the Key Pair ID:** `APKAXXXXXXXXXXXXXXXX`

#### 5.2 Secure the Private Key

**CRITICAL:** Keep the private key secure!

```bash
# Set proper permissions (Linux/Mac)
chmod 600 pk-APKAXXXXXXXXXXXXXXXX.pem

# Store in a secure location
# You'll need the contents of this file for Moodle configuration
```

---

### Step 6: Configure CloudFront Distribution

#### 6.1 Create Trusted Key Group

1. Go to **CloudFront** → **Key management** → **Public keys**
2. Click **"Create public key"**
3. **Name:** `moodle-video-key`
4. **Key:** Paste the contents of `rsa-APKAXXXXXXXXXXXXXXXX.pem`
5. Click **"Create public key"**

6. Go to **Key groups** tab
7. Click **"Create key group"**
8. **Name:** `moodle-video-key-group`
9. **Public keys:** Select the key you just created
10. Click **"Create key group"**

#### 6.2 Update Distribution Settings

1. Go to **CloudFront** → **Distributions**
2. Select your distribution → **Behaviors** tab
3. Select the default behavior → Click **"Edit"**
4. **Restrict viewer access:** **"Yes"**
5. **Trusted key groups:** Select `moodle-video-key-group`
6. Click **"Save changes"**

#### 6.3 Wait for Deployment

- Status will show **"Deploying"**
- Wait 5-15 minutes for deployment to complete
- Status will change to **"Enabled"**

---

## Moodle Installation

### Step 1: Install AWS SDK

1. **Download AWS SDK for PHP:**
   ```bash
   cd /path/to/moodle
   mkdir -p local/aws/sdk
   cd local/aws/sdk
   
   # Download from: https://github.com/aws/aws-sdk-php/releases
   # Or use Composer:
   composer require aws/aws-sdk-php
   ```

2. **Verify installation:**
   ```bash
   ls local/aws/sdk/aws-autoloader.php
   ```

### Step 2: Install Plugin

1. **Copy plugin files:**
   ```bash
   cd /path/to/moodle
   cp -r /path/to/plugin mod/assign/submission/s3video
   ```

2. **Set permissions:**
   ```bash
   chown -R www-data:www-data mod/assign/submission/s3video
   chmod -R 755 mod/assign/submission/s3video
   ```

3. **Install via Moodle:**
   - Log in as administrator
   - Go to **Site administration** → **Notifications**
   - Click **"Upgrade Moodle database now"**
   - Follow the installation prompts

---

## Plugin Configuration

### Step 1: Configure AWS S3 Settings

1. Go to **Site administration** → **Plugins** → **Assignment** → **Submission plugins** → **S3 Video submission**

2. **AWS S3 Configuration:**
   ```
   AWS Access Key ID: AKIAIOSFODNN7EXAMPLE
   AWS Secret Access Key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
   S3 Bucket Name: moodle-video-submissions
   S3 Region: us-east-1
   ```

### Step 2: Configure CloudFront Settings

1. **CloudFront Configuration:**
   ```
   CloudFront Domain: d1234567890abc.cloudfront.net
   CloudFront Key Pair ID: APKAXXXXXXXXXXXXXXXX
   ```

2. **CloudFront Private Key:**
   - Open `pk-APKAXXXXXXXXXXXXXXXX.pem` in a text editor
   - Copy the **entire contents** including:
     ```
     -----BEGIN RSA PRIVATE KEY-----
     [key content]
     -----END RSA PRIVATE KEY-----
     ```
   - Paste into the **CloudFront Private Key** field

3. Click **"Save changes"**

### Step 3: Enable Plugin

1. Go to **Site administration** → **Plugins** → **Assignment** → **Submission plugins**
2. Find **"S3 Video submission"**
3. Click the **eye icon** to enable (if disabled)

---

## Testing

### Test 1: Verify AWS Credentials

1. Go to: `https://your-moodle-site.com/mod/assign/submission/s3video/test_aws_credentials.php`
2. Check all tests pass:
   - ✓ S3 client initialized successfully
   - ✓ Successfully accessed bucket
   - ✓ Successfully uploaded test file

### Test 2: Verify CloudFront Signing

1. Go to: `https://your-moodle-site.com/mod/assign/submission/s3video/test_cloudfront_signing.php`
2. Check:
   - ✓ CloudFront client initialized successfully
   - ✓ Signed URL generated successfully
   - ✓ URL signature is valid

### Test 3: Test Video Upload

1. Create a test assignment with S3 Video submission enabled
2. As a student, upload a small test video
3. Verify:
   - Upload completes successfully
   - Video appears in S3 bucket
   - Status shows "Ready"

### Test 4: Test Video Playback

1. As a teacher, go to the assignment grading page
2. Click the video icon for a submission
3. Verify:
   - Video opens in new tab
   - Video plays successfully
   - CloudFront URL is used (check browser network tab)

---

## Troubleshooting

### Issue: "AWS S3 authentication failed"

**Cause:** Invalid AWS credentials or permissions

**Solution:**
1. Verify Access Key ID and Secret Key are correct
2. Check IAM user has required permissions
3. Run test script: `test_aws_credentials.php`

### Issue: "Failed to upload to S3"

**Cause:** CORS not configured or bucket policy incorrect

**Solution:**
1. Check CORS configuration includes your Moodle domain
2. Verify bucket policy allows IAM user to PutObject
3. Check browser console for CORS errors

### Issue: "Video playback error"

**Cause:** CloudFront not configured correctly

**Solution:**
1. Verify CloudFront distribution is deployed (Status: Enabled)
2. Check trusted key group is configured
3. Verify private key is correct (including BEGIN/END lines)
4. Run test script: `test_cloudfront_signing.php`

### Issue: "403 Forbidden" when playing video

**Cause:** CloudFront signed URL invalid or expired

**Solution:**
1. Check CloudFront Key Pair ID is correct
2. Verify private key matches the public key in CloudFront
3. Ensure system time is synchronized (signed URLs are time-sensitive)

### Issue: Videos not appearing in grading interface

**Cause:** Plugin not enabled for assignment

**Solution:**
1. Edit assignment settings
2. Expand **"Submission types"**
3. Enable **"S3 Video submission"**
4. Save changes

---

## Security Best Practices

1. **Never commit AWS credentials to version control**
2. **Use IAM user with minimal required permissions**
3. **Rotate AWS credentials regularly** (every 90 days)
4. **Enable CloudWatch logging** for S3 and CloudFront
5. **Set up billing alerts** to monitor costs
6. **Use HTTPS only** for all video access
7. **Regularly review S3 bucket contents** and delete old videos
8. **Enable MFA** on AWS root account

---

## Maintenance

### Regular Tasks

**Weekly:**
- Check error logs for failed uploads
- Monitor AWS costs

**Monthly:**
- Review storage usage
- Clean up old test videos
- Check for plugin updates

**Quarterly:**
- Rotate AWS credentials
- Review IAM permissions
- Update documentation

### Backup Strategy

**S3 Bucket:**
- Enable versioning for accidental deletion protection
- Set up lifecycle rules to archive old videos to Glacier
- Consider cross-region replication for disaster recovery

**Moodle Database:**
- Regular backups of `mdl_assignsubmission_s3video` table
- Include in standard Moodle backup procedures

---

## Support

For issues or questions:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review Moodle logs: **Site administration** → **Reports** → **Logs**
3. Check AWS CloudWatch logs
4. Contact your system administrator

---

## Additional Resources

- [AWS S3 Documentation](https://docs.aws.amazon.com/s3/)
- [AWS CloudFront Documentation](https://docs.aws.amazon.com/cloudfront/)
- [Moodle Assignment Documentation](https://docs.moodle.org/en/Assignment_activity)
- [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/)

---

**Last Updated:** 2025-01-26  
**Plugin Version:** 1.0.0  
**Moodle Version:** 3.9+
