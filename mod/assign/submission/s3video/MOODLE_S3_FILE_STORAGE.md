# Moodle S3 File Storage - Complete Guide

## 🎯 Your Question

**Q:** "Standard file submission stores files locally, not S3, right?"

**A:** By default YES, but Moodle CAN be configured to store ALL files in S3!

---

## ✅ Solution: Use Moodle's S3 File System Plugin

Moodle has built-in support for storing files in S3 using the **Object File System (ObjectFS)** plugin.

### What This Does:
- ✅ ALL file uploads go to S3 (not local storage)
- ✅ Works with standard file submissions
- ✅ Supports ALL file types (ZIP, PPT, PDF, videos, etc.)
- ✅ Uses your existing AWS S3 bucket
- ✅ Transparent to users - they don't see any difference

---

## 📦 Option 1: Object File System Plugin (Recommended)

### Plugin Information
- **Name:** Object File System (ObjectFS)
- **Plugin Type:** tool_objectfs
- **Download:** https://moodle.org/plugins/tool_objectfs
- **Supports:** S3, Azure, OpenStack, and more

### Features:
- ✅ Stores files in S3 automatically
- ✅ Can migrate existing files to S3
- ✅ Supports file deduplication
- ✅ Configurable storage policies
- ✅ Can keep local cache for performance

---

## 🚀 Installation Steps

### Step 1: Install ObjectFS Plugin

**Option A: Via Moodle Admin Interface**
1. Log in as admin
2. Go to: **Site administration > Plugins > Install plugins**
3. Search for "Object File System" or "tool_objectfs"
4. Click "Install"
5. Follow installation wizard

**Option B: Manual Installation**
```bash
# SSH to your server
cd /path/to/moodle/admin/tool/
git clone https://github.com/catalyst/moodle-tool_objectfs.git objectfs

# Or download and extract
wget https://moodle.org/plugins/download.php/[version]/tool_objectfs.zip
unzip tool_objectfs.zip -d /path/to/moodle/admin/tool/

# Set permissions
chown -R www-data:www-data /path/to/moodle/admin/tool/objectfs
```

### Step 2: Configure S3 Storage

1. Go to: **Site administration > Plugins > Admin tools > Object storage**

2. **Enable Object Storage:**
   - Enable object storage: **Yes**

3. **Select Storage Type:**
   - File system: **Amazon S3**

4. **Configure S3 Settings:**
   ```
   AWS Access Key: [Your AWS Access Key]
   AWS Secret Key: [Your AWS Secret Key]
   S3 Bucket: [Your S3 Bucket Name]
   S3 Region: [Your AWS Region, e.g., us-east-1]
   ```

5. **Storage Settings:**
   - Prefer external objects: **Yes** (use S3 first)
   - Delete local objects: **Yes** (save local disk space)
   - Consistency delay: **0** (immediate)

6. **Save changes**

### Step 3: Test Configuration

1. Go to: **Site administration > Plugins > Admin tools > Object storage > Settings**
2. Click **"Test connection"**
3. Should show: ✅ "Connection successful"

### Step 4: Migrate Existing Files (Optional)

If you have existing files in local storage:

1. Go to: **Site administration > Plugins > Admin tools > Object storage > Push to external**
2. Click **"Push files to external storage"**
3. Wait for migration to complete

---

## 📋 Configuration Options

### Storage Policies

**Option A: S3 Only (Recommended for your use case)**
```
Prefer external objects: Yes
Delete local objects: Yes
Minimum size: 0 bytes
```
**Result:** All files stored in S3, minimal local storage

**Option B: Hybrid (Local + S3)**
```
Prefer external objects: Yes
Delete local objects: No
Minimum size: 1048576 (1MB)
```
**Result:** Small files local, large files in S3

**Option C: S3 with Local Cache**
```
Prefer external objects: Yes
Delete local objects: After 7 days
Minimum size: 0 bytes
```
**Result:** Files in S3, local cache for performance

---

## 🎯 How It Works for Your Client

### Workflow After Configuration:

1. **Client uploads file** (ZIP, PPT, PDF, etc.)
   - Via standard Moodle file submission
   - No special plugin needed

2. **Moodle receives file**
   - Processes upload normally

3. **ObjectFS automatically:**
   - Uploads file to S3
   - Stores metadata in Moodle database
   - Optionally removes local copy

4. **When file is accessed:**
   - Moodle retrieves from S3
   - Serves to user
   - Optionally caches locally

### Your Client's Experience:
- ✅ Upload any file type (ZIP, PPT, PDF)
- ✅ Files stored in S3 (not local)
- ✅ No difference in user interface
- ✅ Works with existing assignments

---

## 💰 Cost Comparison

### Your Current S3 Video Plugin:
- Custom development
- Maintenance required
- Video files only
- Direct S3 upload from browser

### ObjectFS Plugin:
- Free plugin
- Maintained by community
- ALL file types
- Upload through Moodle server (then to S3)

### Storage Costs (Same):
- Both use AWS S3
- Same storage pricing
- Same bandwidth pricing

---

## 🔒 Security & Permissions

### S3 Bucket Permissions

ObjectFS needs these S3 permissions:
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
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name/*",
                "arn:aws:s3:::your-bucket-name"
            ]
        }
    ]
}
```

### File Access Control:
- ✅ Moodle handles permissions (not S3)
- ✅ Users must log in to Moodle
- ✅ Assignment permissions apply
- ✅ Files not publicly accessible

---

## 📊 Comparison: Custom Plugin vs ObjectFS

| Feature | S3 Video Plugin | ObjectFS + File Submission |
|---------|----------------|---------------------------|
| **File Types** | Videos only | ALL types (ZIP, PPT, PDF, etc.) |
| **Storage** | S3 | S3 |
| **Upload Method** | Direct to S3 | Through Moodle → S3 |
| **Development** | Custom code | Standard plugin |
| **Maintenance** | You maintain | Community maintains |
| **Cost** | Development time | Free |
| **Setup Time** | Weeks | 30 minutes |
| **File Size Limit** | 5GB | Configurable |
| **Works with** | Custom assignments | ALL Moodle features |

---

## 🚀 Quick Setup for Your Client

### Immediate Solution (30 minutes):

1. **Install ObjectFS plugin** (10 min)
   ```bash
   cd /path/to/moodle/admin/tool/
   git clone https://github.com/catalyst/moodle-tool_objectfs.git objectfs
   ```

2. **Configure S3** (10 min)
   - Site admin > Plugins > Object storage
   - Enter AWS credentials
   - Select S3 bucket
   - Enable storage

3. **Create assignment** (5 min)
   - Enable "File submissions"
   - Set "Accepted file types" to "Any"
   - Set max file size

4. **Give client URL** (5 min)
   - Send assignment URL
   - Client can upload ZIP, PPT, PDF
   - Files automatically go to S3

**Total time: 30 minutes**
**Cost: $0 (free plugin)**
**Result: Client can upload any file type to S3**

---

## ⚠️ Important Considerations

### Upload Flow Difference:

**S3 Video Plugin (Direct Upload):**
```
Browser → S3 (direct)
```
**Pros:** Faster, no server load
**Cons:** Video files only

**ObjectFS (Through Server):**
```
Browser → Moodle Server → S3
```
**Pros:** All file types, standard Moodle
**Cons:** Uses server bandwidth

### For Large Files:

If your client uploads very large files (>100MB):
- Consider increasing PHP upload limits
- Consider increasing server timeout
- Or keep S3 video plugin for videos
- Use ObjectFS for documents

---

## 🎯 My Recommendation

### For Your Client's Use Case:

**Use ObjectFS + Standard File Submission**

**Why:**
1. ✅ Supports ZIP, PPT, PDF (what they need)
2. ✅ Files stored in S3 (what you want)
3. ✅ No custom development (saves time)
4. ✅ Works immediately (solves problem now)
5. ✅ Free solution (saves money)

### Keep S3 Video Plugin For:
- Video streaming with CloudFront
- Large video files (direct upload)
- Video-specific features

### Use ObjectFS For:
- Documents (ZIP, PPT, PDF)
- General file storage
- Standard Moodle features

---

## 📞 Alternative: Use Both Plugins

### Hybrid Approach:

**Assignment 1: Video Submission**
- Use S3 Video plugin
- For video files only
- Direct upload to S3
- CloudFront streaming

**Assignment 2: Document Submission**
- Use standard File submission
- ObjectFS stores in S3
- For ZIP, PPT, PDF
- All file types supported

**Benefits:**
- ✅ Best of both worlds
- ✅ Videos optimized for streaming
- ✅ Documents supported
- ✅ All files in S3

---

## 🔗 Resources

### ObjectFS Plugin:
- **Moodle Plugin:** https://moodle.org/plugins/tool_objectfs
- **GitHub:** https://github.com/catalyst/moodle-tool_objectfs
- **Documentation:** https://github.com/catalyst/moodle-tool_objectfs/wiki

### AWS S3 Configuration:
- **IAM Permissions:** https://docs.aws.amazon.com/IAM/latest/UserGuide/
- **S3 Bucket Policy:** https://docs.aws.amazon.com/AmazonS3/latest/userguide/

### Moodle File Storage:
- **File System API:** https://docs.moodle.org/dev/File_System_API
- **Alternative File Systems:** https://docs.moodle.org/en/File_system

---

## ✅ Summary

**Your Question:** "Files stored locally, not S3, right?"

**Answer:** 
- Default Moodle: ✅ Yes, local storage
- With ObjectFS: ❌ No, S3 storage!

**Solution for Your Client:**
1. Install ObjectFS plugin
2. Configure S3 storage
3. Use standard file submission
4. Client uploads ZIP, PPT, PDF
5. Files automatically stored in S3

**Time:** 30 minutes
**Cost:** Free
**Result:** Problem solved! 🎉
