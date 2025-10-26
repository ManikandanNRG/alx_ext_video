# S3 + CloudFront Video Submission Plugin for Moodle

A Moodle assignment submission plugin that enables students to upload large video files (up to 5 GB) directly to AWS S3, with secure delivery via CloudFront CDN.

## Features

- **Large File Support**: Upload videos up to 5 GB
- **Zero Server Load**: Videos upload directly from browser to S3
- **CDN Delivery**: Fast video playback via CloudFront
- **Secure Access**: Time-limited signed URLs for video access
- **Progress Tracking**: Real-time upload progress indicators
- **Automatic Cleanup**: Configurable retention periods
- **GDPR Compliant**: Full data export and deletion support
- **Cost Effective**: Pay only for what you use (AWS free tier available)

## Requirements

### Moodle
- Moodle 3.9 or higher
- PHP 7.4 or higher
- PHP extensions: curl, openssl, json

### AWS Account
- AWS account with billing enabled
- S3 bucket for video storage
- CloudFront distribution with signed URLs enabled
- IAM user with appropriate permissions

## Installation

### Step 1: Install Plugin Files

1. Download or clone this plugin
2. Copy the `s3video` folder to `mod/assign/submission/s3video`
3. Log in to Moodle as administrator
4. Navigate to **Site administration > Notifications**
5. Click **Upgrade Moodle database now**
6. The plugin will be installed and database tables created

### Step 2: AWS Setup

#### 2.1 Create S3 Bucket

1. Log in to AWS Console
2. Navigate to **S3**
3. Click **Create bucket**
4. Configure:
   - **Bucket name**: `your-moodle-videos` (must be globally unique)
   - **Region**: Choose closest to your users (e.g., `us-east-1`)
   - **Block Public Access**: Keep all enabled (videos will use signed URLs)
5. Click **Create bucket**

#### 2.2 Configure S3 CORS

1. Select your bucket
2. Go to **Permissions** tab
3. Scroll to **Cross-origin resource sharing (CORS)**
4. Click **Edit** and paste:

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "POST", "PUT"],
    "AllowedOrigins": ["https://your-moodle-site.com"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3000
  }
]
```

5. Replace `https://your-moodle-site.com` with your actual Moodle URL
6. Click **Save changes**

#### 2.3 Create CloudFront Distribution

1. Navigate to **CloudFront** in AWS Console
2. Click **Create distribution**
3. Configure:
   - **Origin domain**: Select your S3 bucket
   - **Origin access**: **Legacy access identities**
   - Click **Create new OAI** and select it
   - **Bucket policy**: **Yes, update the bucket policy**
   - **Viewer protocol policy**: **Redirect HTTP to HTTPS**
   - **Restrict viewer access**: **Yes**
   - **Trusted signers**: **Self**
4. Click **Create distribution**
5. Wait for deployment (Status: Deployed) - takes 15-20 minutes
6. Note the **Distribution domain name** (e.g., `d123abc456def.cloudfront.net`)

#### 2.4 Create CloudFront Key Pair

1. Log in as **root user** (required for key pair creation)
2. Click account name > **Security credentials**
3. Scroll to **CloudFront key pairs**
4. Click **Create new key pair**
5. Download both files:
   - `pk-APKAXXXXXXXXXXXXXXXX.pem` (private key)
   - `rsa-APKAXXXXXXXXXXXXXXXX.pem` (public key)
6. Note the **Access Key ID** (e.g., `APKAXXXXXXXXXXXXXXXX`)

**Important**: Store the private key securely. You cannot download it again.

#### 2.5 Create IAM User

1. Navigate to **IAM** > **Users**
2. Click **Add users**
3. User name: `moodle-s3video`
4. Select **Access key - Programmatic access**
5. Click **Next: Permissions**
6. Click **Attach policies directly**
7. Click **Create policy** and use JSON:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:HeadObject"
      ],
      "Resource": "arn:aws:s3:::your-moodle-videos/*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "cloudfront:CreateInvalidation"
      ],
      "Resource": "arn:aws:cloudfront::*:distribution/*"
    }
  ]
}
```

8. Replace `your-moodle-videos` with your bucket name
9. Name the policy: `MoodleS3VideoPolicy`
10. Attach the policy to the user
11. Click **Create user**
12. **Download credentials** (CSV file) - contains Access Key ID and Secret Access Key

### Step 3: Configure Plugin in Moodle

1. Log in to Moodle as administrator
2. Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video**
3. Configure AWS credentials:

   - **AWS Access Key ID**: From IAM user credentials
   - **AWS Secret Access Key**: From IAM user credentials
   - **S3 Bucket Name**: `your-moodle-videos`
   - **S3 Region**: `us-east-1` (or your chosen region)
   - **CloudFront Domain**: `d123abc456def.cloudfront.net` (from step 2.3)
   - **CloudFront Key Pair ID**: `APKAXXXXXXXXXXXXXXXX` (from step 2.4)
   - **CloudFront Private Key**: Paste contents of `pk-APKAXXXXXXXXXXXXXXXX.pem`

4. Configure retention settings:
   - **Video Retention Days**: `90` (default)
   - **Enable Automatic Cleanup**: Yes

5. Click **Save changes**

### Step 4: Enable Plugin for Assignments

1. Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > Manage submission plugins**
2. Find **S3 Video** in the list
3. Click the eye icon to enable it
4. Optionally set as default for new assignments

### Step 5: Test Installation

1. Create a test assignment
2. In assignment settings, enable **S3 Video** submission type
3. As a student, try uploading a small video file
4. As a teacher, verify you can view the video

## User Guides

### For Students: Submitting Video Assignments

#### Uploading a Video

1. Navigate to your assignment
2. Click **Add submission**
3. You'll see the video upload interface
4. Click **Choose file** or drag and drop a video file
5. Supported formats: MP4, MOV, AVI, MKV, WebM
6. Maximum size: 5 GB
7. Click **Upload**
8. Watch the progress bar - large files may take several minutes
9. When complete, you'll see a confirmation message
10. Click **Save changes** to submit

#### Tips for Students

- **Internet Connection**: Use a stable connection for large uploads
- **File Format**: MP4 (H.264) works best for compatibility
- **File Size**: Compress videos if possible to reduce upload time
- **Progress**: Don't close the browser during upload
- **Retry**: If upload fails, click the retry button

#### Viewing Your Submission

1. Return to the assignment page
2. Your video will appear in the submission area
3. Click play to preview your submission
4. You can delete and re-upload before the deadline

### For Teachers: Viewing and Grading Video Submissions

#### Viewing Student Videos

1. Navigate to the assignment
2. Click **View all submissions**
3. Click on a student's name
4. The video player will load automatically
5. Use standard video controls (play, pause, seek, volume)
6. Videos stream directly from CloudFront (no server load)

#### Grading Video Submissions

1. Watch the student's video
2. Use the grading interface on the same page:
   - Enter grade
   - Add feedback comments
   - Attach feedback files if needed
3. Click **Save changes**
4. The student will be notified of their grade

#### Bulk Operations

1. From **View all submissions** page
2. Select multiple students
3. Use **With selected** dropdown for bulk actions:
   - Download submissions (downloads metadata, not videos)
   - Set marking workflow state
   - Grant extension

#### Managing Videos

1. Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video > Manage Videos**
2. View all uploaded videos
3. Search by student, assignment, or date
4. Manually delete videos if needed
5. View storage usage and estimated costs

### For Administrators: Monitoring and Maintenance

#### Dashboard

1. Navigate to **Site administration > Plugins > Activity modules > Assignment > Submission plugins > S3 Video > Dashboard**
2. View statistics:
   - Total uploads (successful/failed)
   - Storage usage
   - Estimated monthly costs
   - Recent errors

#### Monitoring Uploads

- Check dashboard regularly for failed uploads
- Review error logs for patterns
- Contact students if their uploads fail repeatedly

#### Managing Storage Costs

1. Set appropriate retention period (default: 90 days)
2. Enable automatic cleanup
3. Monitor storage usage in dashboard
4. Consider shorter retention for high-volume courses

#### Troubleshooting

**Students can't upload:**
- Verify AWS credentials are correct
- Check S3 CORS configuration
- Ensure IAM user has PutObject permission
- Check Moodle error logs

**Videos won't play:**
- Verify CloudFront distribution is deployed
- Check CloudFront private key is correct
- Ensure CloudFront signed URLs are enabled
- Check browser console for errors

**High AWS costs:**
- Review retention period
- Enable automatic cleanup
- Check for unusually large files
- Consider compression guidelines for students

#### GDPR Compliance

The plugin is fully GDPR compliant:

- **Data Export**: User data includes video metadata (not video files)
- **Right to Erasure**: Deleting a user deletes their videos from S3
- **Data Retention**: Configurable retention periods
- **Privacy Policy**: Update your privacy policy to mention AWS storage

To export user data:
1. Navigate to **Site administration > Users > Privacy and policies > Data requests**
2. Create export request for user
3. Video metadata will be included in export

To delete user data:
1. Delete the user account in Moodle
2. Plugin automatically deletes their videos from S3
3. CloudFront cache is invalidated

## AWS Cost Estimation

### Pricing (as of 2025)

- **S3 Storage**: $0.023 per GB/month
- **S3 Requests**: $0.005 per 1,000 PUT requests
- **CloudFront Data Transfer**: $0.085 per GB
- **CloudFront Requests**: $0.0075 per 10,000 requests

### Example Scenarios

**Small Course (50 students, 500 MB videos each)**
- Storage: 25 GB × $0.023 = $0.58/month
- Upload: 50 × $0.000005 = $0.0003
- Playback (1 view each): 25 GB × $0.085 = $2.13
- **Total: ~$2.71/month**

**Large Course (500 students, 1 GB videos each)**
- Storage: 500 GB × $0.023 = $11.50/month
- Upload: 500 × $0.000005 = $0.003
- Playback (2 views each): 1000 GB × $0.085 = $85
- **Total: ~$96.50/month**

### AWS Free Tier (First 12 Months)

- **S3**: 5 GB storage, 20,000 GET requests, 2,000 PUT requests
- **CloudFront**: 50 GB data transfer, 2,000,000 requests
- **Ideal for**: Testing and small deployments

### Cost Optimization Tips

1. **Set Retention Period**: Delete old videos automatically
2. **Compress Videos**: Encourage students to compress before upload
3. **Limit File Size**: Set lower limits if 5 GB isn't needed
4. **Monitor Usage**: Check dashboard regularly
5. **Use Free Tier**: Test thoroughly before scaling

## Security

### Data Protection

- Videos stored in private S3 bucket (not publicly accessible)
- CloudFront signed URLs expire after 24 hours
- AWS credentials encrypted in Moodle database
- All communication over HTTPS

### Access Control

- Students can only upload to their own submissions
- Teachers can only view submissions in their courses
- Administrators can view all submissions
- Moodle's role-based permissions enforced

### Best Practices

1. **Rotate Credentials**: Change AWS credentials periodically
2. **Monitor Access**: Review CloudFront access logs
3. **Limit Permissions**: IAM user has minimal required permissions
4. **Secure Private Key**: Store CloudFront private key securely
5. **Update Regularly**: Keep Moodle and plugin updated

## Support

### Getting Help

- **Moodle Forums**: Post in Assignment activity forum
- **GitHub Issues**: Report bugs and feature requests
- **AWS Support**: For AWS-specific issues

### Common Issues

**Upload fails immediately:**
- Check file size (max 5 GB)
- Verify file type is supported
- Check browser console for errors

**Upload stalls at 99%:**
- Wait a few minutes (S3 finalizing)
- Check network connection
- Try again if it doesn't complete

**Video won't play:**
- Wait 15-20 minutes after CloudFront setup
- Clear browser cache
- Try different browser
- Check CloudFront distribution status

**"Access Denied" error:**
- Verify you have permission to view submission
- Check if video was deleted
- Contact administrator

## Changelog

See [CHANGELOG.md](../../../../../../CHANGELOG.md) for version history.

## License

This plugin is licensed under the GNU GPL v3 or later.

See [LICENSE](../../../../../../LICENSE) for details.

## Credits

Developed for Moodle assignment submissions with AWS S3 and CloudFront integration.

## Contributing

See [CONTRIBUTING.md](../../../../../../CONTRIBUTING.md) for contribution guidelines.
