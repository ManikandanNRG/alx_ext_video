# How Moodle's Default File Submission Handles Cancel

## Key Discovery: Moodle Uses DRAFT Area!

After analyzing `assign/submission/file/locallib.php`, here's how Moodle's default file submission plugin handles the cancel scenario:

## The Moodle Way

### 1. **Draft File Area (User Context)**
```php
// Files are uploaded to USER'S DRAFT area first
$data = file_prepare_standard_filemanager($data,
    'files',
    $fileoptions,
    $this->assignment->get_context(),
    'assignsubmission_file',
    ASSIGNSUBMISSION_FILE_FILEAREA,
    $submissionid
);
```

### 2. **Files Stay in Draft Until Save**
- When user uploads files → They go to **`user/draft/`** area (temporary)
- Files are NOT moved to final location until **`save()` is called**
- If user clicks Cancel → Draft files are automatically cleaned up by Moodle

### 3. **Save Method Moves Files**
```php
public function save(stdClass $submission, stdClass $data) {
    // This MOVES files from draft area to final area
    $data = file_postupdate_standard_filemanager($data,
        'files',
        $fileoptions,
        $this->assignment->get_context(),
        'assignsubmission_file',
        ASSIGNSUBMISSION_FILE_FILEAREA,
        $submission->id
    );
    
    // Only NOW are files permanently stored
}
```

### 4. **Automatic Cleanup**
- Moodle has a scheduled task that cleans up old draft files
- If user cancels → Draft files are orphaned and cleaned up automatically
- No manual cleanup needed!

## Why This Works

**Flow:**
```
1. User uploads file → Stored in context_user (draft area)
2. User clicks "Save" → Files moved to context_module (permanent)
3. User clicks "Cancel" → Draft files left behind, cleaned by Moodle cron
```

**Key Points:**
- ✅ Files are NOT in final location until save
- ✅ Cancel = do nothing, Moodle cleans up drafts
- ✅ No orphaned files in final storage
- ✅ No manual cleanup code needed

## How This Applies to Cloudflare Stream Plugin

### Current Problem
```
1. User uploads video → IMMEDIATELY goes to Cloudflare (permanent)
2. User clicks "Save" → Just updates database
3. User clicks "Cancel" → Video STILL in Cloudflare (orphaned!)
```

### Solution: Use Moodle's Draft Pattern

**Option A: Use Moodle's File API (Recommended)**
```php
// Store video reference in draft area
public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
    // Add hidden field for video UID (stored in draft)
    $mform->addElement('hidden', 'cloudflarestream_video_uid_draft');
    
    // JavaScript uploads to Cloudflare but marks as draft
}

public function save(stdClass $submission, stdClass $data) {
    // Only NOW mark video as permanent in Cloudflare
    if (!empty($data->cloudflarestream_video_uid_draft)) {
        // Move from draft to permanent
        $this->confirm_video_permanent($data->cloudflarestream_video_uid_draft);
    }
}
```

**Option B: Database Draft Flag (Simpler)**
```php
// Add is_draft column to database
public function save(stdClass $submission, stdClass $data) {
    // Mark video as NOT draft
    $DB->set_field('assignsubmission_cfstream', 'is_draft', 0, 
        ['submission' => $submission->id]);
}

// Scheduled task cleans up drafts
public function cleanup_drafts() {
    $drafts = $DB->get_records_select(
        'assignsubmission_cfstream',
        'is_draft = 1 AND timecreated < ?',
        [time() - 86400] // 24 hours old
    );
    
    foreach ($drafts as $draft) {
        // Delete from Cloudflare
        $this->cloudflare_client->delete_video($draft->video_uid);
        // Delete from database
        $DB->delete_records('assignsubmission_cfstream', ['id' => $draft->id]);
    }
}
```

## Recommended Solution

**Use Database Draft Flag** (Option B) because:

1. ✅ **Follows Moodle pattern** - Draft → Permanent on save
2. ✅ **Simple implementation** - Just add one column
3. ✅ **Automatic cleanup** - Scheduled task like Moodle's draft cleanup
4. ✅ **No JavaScript complexity** - Works even if browser closes
5. ✅ **Handles all cancel scenarios** - Button, back, close, timeout

## Implementation Steps

### Step 1: Database Schema
```sql
ALTER TABLE mdl_assignsubmission_cfstream 
ADD COLUMN is_draft TINYINT(1) DEFAULT 1 AFTER upload_status;

ALTER TABLE mdl_assignsubmission_cfstream 
ADD COLUMN draft_created BIGINT DEFAULT NULL AFTER is_draft;
```

### Step 2: Upload (confirm_upload.php)
```php
// When video uploads successfully
$record = new stdClass();
$record->submission = $submissionid;
$record->video_uid = $videouid;
$record->is_draft = 1;  // Mark as draft
$record->draft_created = time();
$record->upload_status = 'ready';
$DB->insert_record('assignsubmission_cfstream', $record);
```

### Step 3: Save (locallib.php)
```php
public function save(stdClass $submission, stdClass $data) {
    global $DB;
    
    // Mark video as permanent (not draft)
    $DB->set_field('assignsubmission_cfstream', 'is_draft', 0, 
        ['submission' => $submission->id]);
    
    return true;
}
```

### Step 4: View (locallib.php)
```php
public function view(stdClass $submission) {
    global $DB;
    
    // Only show NON-DRAFT videos
    $video = $DB->get_record('assignsubmission_cfstream', [
        'submission' => $submission->id,
        'is_draft' => 0  // Only permanent videos
    ]);
    
    if (!$video) {
        return '';
    }
    
    // Render player...
}
```

### Step 5: Cleanup Task (classes/task/cleanup_drafts.php)
```php
class cleanup_drafts extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('cleanup_drafts_task', 'assignsubmission_cloudflarestream');
    }
    
    public function execute() {
        global $DB;
        
        // Delete drafts older than 24 hours
        $cutoff = time() - (24 * 3600);
        
        $drafts = $DB->get_records_select(
            'assignsubmission_cfstream',
            'is_draft = 1 AND draft_created < ?',
            [$cutoff]
        );
        
        foreach ($drafts as $draft) {
            // Delete from Cloudflare
            try {
                $client = new cloudflare_client();
                $client->delete_video($draft->video_uid);
            } catch (Exception $e) {
                // Log but continue
                mtrace('Failed to delete video ' . $draft->video_uid . ': ' . $e->getMessage());
            }
            
            // Delete from database
            $DB->delete_records('assignsubmission_cfstream', ['id' => $draft->id]);
            mtrace('Cleaned up draft video: ' . $draft->video_uid);
        }
    }
}
```

### Step 6: Register Task (db/tasks.php)
```php
$tasks = [
    [
        'classname' => 'assignsubmission_cloudflarestream\task\cleanup_drafts',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',  // Run at 2 AM daily
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];
```

## Benefits of This Approach

1. **Follows Moodle conventions** - Same pattern as file submission
2. **No orphaned videos** - Drafts cleaned up automatically
3. **Handles all cancel scenarios:**
   - ✅ Cancel button
   - ✅ Browser close
   - ✅ Back button
   - ✅ Session timeout
   - ✅ Page refresh
4. **Simple implementation** - Just one column + scheduled task
5. **No JavaScript complexity** - Works server-side
6. **Automatic cleanup** - Like Moodle's draft file cleanup

## Comparison

| Scenario | Current Behavior | With Draft Flag |
|----------|------------------|-----------------|
| Upload + Save | ✅ Works | ✅ Works |
| Upload + Cancel | ❌ Video orphaned | ✅ Cleaned up |
| Upload + Browser close | ❌ Video orphaned | ✅ Cleaned up |
| Upload + Back button | ❌ Video orphaned | ✅ Cleaned up |
| Upload + Timeout | ❌ Video orphaned | ✅ Cleaned up |

## Conclusion

**The Moodle way is to use a draft/temporary state that only becomes permanent when the form is saved.**

This is exactly what we need to implement for the Cloudflare Stream plugin!
