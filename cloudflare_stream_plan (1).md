## Implementation Plan: Using Cloudflare Stream for Video Upload & Review in IOMAD-Moodle

### 1. Objective
Integrate **Cloudflare Stream** with our existing IOMAD-based Moodle environment to enable seamless, cost-effective handling of large video uploads (up to 5 GB each) submitted as fieldwork assignments. Teachers should be able to view, approve, or provide feedback without performance or bandwidth issues.

---

### 2. Core Requirements
- Allow users (students) to upload large video files directly from Moodle.
- Ensure uploads bypass Moodle’s server storage to prevent load and storage overhead.
- Videos must be processed, stored, and streamed via Cloudflare Stream.
- Teachers should review and grade submissions within the Moodle assignment activity.
- Maintain full user tracking and metadata (e.g., user ID, course ID, submission timestamp) within Moodle.

---

### 3. Technical Approach
#### A. Upload Workflow
1. **User initiates upload** from the assignment submission page.
2. Moodle’s front-end makes a secure API request to a custom backend endpoint (PHP/Node/Python plugin) to generate a **direct upload URL** from Cloudflare Stream API.
3. The video is uploaded **directly from the user’s browser** to Cloudflare Stream via the presigned upload URL.
4. Once upload completes, Cloudflare Stream returns a **video UID**.
5. Moodle saves this UID in the assignment submission record (custom field or table) for tracking and playback.

#### B. Playback & Review
1. In the assignment grading interface, Moodle retrieves the video UID.
2. The video is rendered using the Cloudflare Stream **embedded player** (iframe or JS snippet) inside the grading screen.
3. Teachers can play, pause, or stream the video directly from Cloudflare’s CDN without any Moodle bandwidth load.

#### C. Security & Access Control
- Only authenticated users (teachers and the submitting student) can access the embedded player.
- Use **signed playback tokens** (JWT) from Cloudflare to restrict viewing.
- Optional: expire playback URLs after a defined time window (e.g., 24 hours).
- No public listing of Cloudflare videos — all are private assets.

#### D. User-Video Mapping and Access Control
1. **Video Ownership Tracking**  
   - Each uploaded video generates a unique **Cloudflare Stream Video UID**.  
   - Moodle stores the UID alongside **user_id**, **course_id**, **assignment_id**, and **timestamp** in a dedicated table (or extended `mdl_assign_submission`).  
   - This ensures each video submission is uniquely tied to the submitting user and assignment.

2. **Scoped Playback for Users**  
   - When a student views their submission, Moodle fetches only their stored video UID and renders it.  
   - If a teacher opens a student submission for grading, the system retrieves and embeds the video corresponding to that specific `submission_id`.  
   - Cross-access is impossible because the player embed is generated per user and submission context.

3. **Playback Security**  
   - The playback link is generated dynamically using **Cloudflare-signed JWT tokens** that include:  
     - `video_uid`  
     - `user_id`  
     - `expiry` timestamp  
   - Tokens are short-lived, preventing sharing or unauthorized access.  
   - No direct Cloudflare or S3 URLs are exposed to the front end.

---

### 4. Data Flow Summary
```
User → Moodle (request upload) → Custom API → Cloudflare Stream (upload URL)
User → Cloudflare Stream (direct upload)
Cloudflare → Moodle (returns video UID)
Teacher/Student → Moodle → Cloudflare Stream (secure playback)
```

---

### 5. Integration Components
| Component | Description | Implementation Detail |
|------------|-------------|------------------------|
| Moodle Plugin (Custom) | Handles API calls, stores video UIDs, and renders playback player. | Can extend the Assignment module or create a new sub-plugin under mod_assign/submission. |
| Cloudflare Stream API | Handles uploads, processing, and streaming. | Use REST endpoints: `/stream/direct_upload` for upload, `/stream/{uid}` for retrieval. |
| Authentication | Secure API requests to Cloudflare. | Use Cloudflare API Token scoped to Stream operations. |
| Frontend JS | Handles upload progress bar, direct upload, and error states. | Implement resumable uploads using Fetch API or Axios with progress tracking. |
| Moodle Database | Stores metadata (video UID, submission time, user ID). | Create a custom DB table or extend mdl_assign_submission. |
| Player Embed | For playback in teacher review UI. | Use Cloudflare’s `<iframe src="https://watch.cloudflarestream.com/{UID}">` or custom player embed. |

---

### 6. Estimated Costs (100 users × 5 GB each = 500 GB total)
| Month | Users Onboarded | Approx. Stored Minutes | Storage Cost | Delivery Cost (1 view/video) | Cumulative Cost |
|--------|-----------------|-----------------------:|--------------:|-----------------------------:|----------------:|
| Month 1 | 33 | ~4,400 min | $22.00 | $4.40 | $26.40 |
| Month 2 | 66 | ~8,800 min | $44.00 | $4.40 | $48.40 |
| Month 3 | 100 | ~13,333 min | $66.67 | $4.53 | $71.20 |
| **Total (3 months)** | — | — | **$132.67** | **$13.33** | **~$146.00** |

*(Assuming 5 Mbps average bitrate, 1 view per video)*

---

### 7. Developer Tasks Breakdown
| Phase | Task | Responsibility |
|-------|------|----------------|
| **1. Prototype** | Test direct upload using Cloudflare Stream API and retrieve video UID. | Backend Dev |
|  | Create sample upload page integrated with Moodle auth. | Frontend Dev |
| **2. Integration** | Build Moodle plugin to handle upload requests, store UID, and embed player. | Moodle Dev |
| **3. Security Setup** | Implement signed playback tokens, access control, and role validation. | Full-stack Dev |
| **4. Testing** | Validate uploads, playback, and performance under 500 MB – 5 GB load. | QA |
| **5. Deployment** | Rollout to staging → production with logging and cost tracking. | DevOps |

---

### 8. Monitoring & Maintenance
- Use Cloudflare’s dashboard or API to track upload success/failure and bandwidth usage.
- Maintain a cleanup policy: archive or delete videos 90 days after upload.
- Add Moodle-side cron job for video deletion triggers via Cloudflare API.
- Enable error alerts for upload failures or token expiry.

---

### 9. Future Enhancements
- Enable transcoding variants (360p, 720p, 1080p) automatically for adaptive streaming.
- Support batch export/report of video review status.
- Integrate AI-assisted video summarization or tagging for faster teacher reviews.

---

### 10. References
- Cloudflare Stream API Docs: https://developers.cloudflare.com/stream/
- Moodle Plugin Dev Docs: https://moodledev.io/docs/apis/subsystems/plugin
- Example Integration Flow: https://developers.cloudflare.com/stream/upload/direct-creator-uploads/

---
**Next Steps:**
1. Confirm Cloudflare account setup (Stream enabled + API token).
2. Decide whether to embed within Assignment module or build a standalone submission plugin.
3. Begin prototype build within staging Moodle for end-to-end test.

---
**Prepared for internal dev team discussion — October 2025**

