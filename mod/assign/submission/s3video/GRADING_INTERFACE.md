# Grading Interface Implementation

## Overview

The S3 Video plugin now supports an embedded video player in the grading interface, similar to how PDF annotation works. This allows teachers to watch videos directly while grading, without opening new tabs.

## How It Works

### Context Detection

The plugin automatically detects whether it's being displayed in:

1. **Grading Context** (Teacher grading submissions)
   - URL contains `action=grader`, `action=grade`, or `action=grading`
   - Page is `/mod/assign/view.php` with grading parameters
   - Shows full-width video player

2. **Submission Context** (Student viewing their submission)
   - Student submission page
   - Student viewing their own work
   - Shows boxed view with status (original behavior)

### Implementation Details

#### File: `lib.php`

**Method: `view()`**
- Detects context using `is_grading_context()`
- Renders different layouts based on context:
  - **Grading**: Full-width player, no borders
  - **Submission**: Boxed view with blue border

**Method: `is_grading_context()`**
- Checks URL action parameter
- Checks page path
- Checks body classes
- Returns `true` if in grading interface

#### File: `styles.css`

**Grading View Styles:**
```css
.s3video-grading-view {
    width: 100%;
    margin: 0;
    padding: 0;
}

.s3video-grading-view .s3video-player-container {
    width: 100%;
    max-width: 100%;
    min-height: 500px;
}
```

**Responsive Design:**
- Desktop: 500px min-height
- Tablet (< 1200px): 400px min-height
- Mobile (< 768px): 300px min-height
- Small mobile (< 480px): 250px min-height

## User Experience

### For Teachers (Grading Interface)

1. Navigate to assignment grading
2. Click "Grade" on a submission with video
3. Video player appears full-width in main content area
4. Grading controls appear on the right (Moodle default)
5. Watch video while entering grade and feedback
6. Navigate to next student - new video loads

**Benefits:**
- ✅ No tab switching
- ✅ Everything in one view
- ✅ Better workflow
- ✅ Consistent with PDF annotation

### For Students (Submission Page)

1. View their own submission
2. See boxed view with blue border (original design)
3. Video player embedded in box
4. Status and metadata displayed

**Benefits:**
- ✅ No changes to student experience
- ✅ Familiar interface
- ✅ Clear status indicators

## Testing

### Test Script

Run the test script to verify context detection:

```bash
https://your-moodle-site/mod/assign/submission/s3video/test_grading_context.php
```

### Manual Testing

1. **Create Test Assignment**
   ```
   - Enable S3 Video submission
   - Set submission settings
   ```

2. **Student Submission**
   ```
   - Log in as student
   - Submit a video
   - View submission page
   - Verify: Boxed view with blue border
   ```

3. **Teacher Grading**
   ```
   - Log in as teacher
   - Go to assignment
   - Click "View all submissions"
   - Click "Grade" on a submission
   - Verify: Full-width video player
   - Verify: Grading controls on right
   ```

4. **Navigation**
   ```
   - Grade first student
   - Click "Next" to go to next student
   - Verify: New video loads
   - Verify: Previous video unloads
   ```

## Troubleshooting

### Video Not Showing in Grading Interface

**Problem:** Video doesn't appear when grading

**Solutions:**
1. Clear Moodle caches:
   ```bash
   php admin/cli/purge_caches.php
   ```

2. Check browser console for JavaScript errors

3. Verify video status is "ready":
   ```sql
   SELECT * FROM mdl_assignsubmission_s3video 
   WHERE submission = [submission_id];
   ```

### Layout Issues

**Problem:** Video player too small or too large

**Solutions:**
1. Check CSS is loaded:
   - View page source
   - Look for `s3video/styles.css`

2. Adjust min-height in `styles.css`:
   ```css
   .s3video-grading-view .s3video-player-container {
       min-height: 600px; /* Adjust as needed */
   }
   ```

3. Check responsive breakpoints for your screen size

### Context Detection Not Working

**Problem:** Wrong layout shown in grading interface

**Solutions:**
1. Check URL parameters:
   - Should contain `action=grader` or similar

2. Verify `is_grading_context()` logic:
   ```php
   // Add debug output
   error_log('Action: ' . optional_param('action', '', PARAM_ALPHA));
   error_log('Is grading: ' . ($this->is_grading_context() ? 'YES' : 'NO'));
   ```

3. Check Moodle version compatibility

## Technical Details

### Method: `is_grading_context()`

```php
protected function is_grading_context() {
    global $PAGE;
    
    // Check action parameter
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'grader' || $action === 'grade' || $action === 'grading') {
        return true;
    }
    
    // Check page path
    $pagepath = $PAGE->url->get_path();
    if (strpos($pagepath, '/mod/assign/view.php') !== false) {
        if (!empty($action)) {
            return true;
        }
    }
    
    // Check body classes
    $bodyclass = $PAGE->bodyclasses;
    if (strpos($bodyclass, 'path-mod-assign') !== false && 
        (strpos($bodyclass, 'grading') !== false || 
         optional_param('rownum', 0, PARAM_INT) > 0)) {
        return true;
    }
    
    return false;
}
```

### Rendering Logic

```php
// GRADING INTERFACE: Full-width player
if ($is_grading && $video->upload_status === 'ready') {
    return render_full_width_player();
}

// GRADING INTERFACE: Status for non-ready videos
if ($is_grading) {
    return render_status_message();
}

// SUBMISSION PAGE: Boxed view (original)
return render_boxed_view();
```

## Performance Considerations

### Video Loading

- Videos only load when grading page is opened
- Signed URLs are cached for 24 hours
- Video unloads when navigating away

### Navigation Between Students

- Previous video is unloaded
- New video loads automatically
- Minimal bandwidth usage

### Caching

- Player JavaScript is cached by browser
- Signed URLs are generated on-demand
- No server-side caching of video content

## Security

### Access Control

- Only teachers with grading capability can access grading interface
- Students cannot access other students' videos
- Signed URLs expire after 24 hours

### Context Validation

- Context detection prevents unauthorized access
- Moodle's built-in capability checks are enforced
- No direct video URL exposure

## Future Enhancements

### Potential Features

1. **Playback Speed Control**
   - Allow teachers to watch at 1.5x or 2x speed
   - Faster grading workflow

2. **Timestamp Comments**
   - Add comments at specific video timestamps
   - Reference specific moments in feedback

3. **Video Annotations**
   - Draw on video frames
   - Highlight specific areas

4. **Keyboard Shortcuts**
   - Space: Play/Pause
   - Arrow keys: Seek forward/backward
   - Number keys: Jump to percentage

5. **Picture-in-Picture**
   - Continue watching while scrolling
   - Better multitasking

## Changelog

### Version 1.1 (2025-01-XX)

- ✅ Added grading interface support
- ✅ Context-aware rendering
- ✅ Full-width video player in grading
- ✅ Responsive design for all screen sizes
- ✅ Maintained backward compatibility

### Version 1.0 (2025-01-XX)

- Initial release
- Basic video upload and playback
- S3 + CloudFront integration

## Support

For issues or questions:

1. Check this documentation
2. Run test script: `test_grading_context.php`
3. Check Moodle logs: `admin/tool/log/index.php`
4. Review browser console for JavaScript errors
5. Contact plugin maintainer

## Credits

- Developed for Moodle assignment module
- Uses Video.js for playback
- Integrates with AWS S3 and CloudFront
- Inspired by Moodle's PDF annotation feature
