# UI Improvements - Cloudflare Stream Plugin

## Issue 1: Grading Table Display - Video Information

### Current State (Screenshot Analysis)

**Location**: `https://dev.aktrea.net/mod/assign/view.php?id=692&action=grading`

**Current Display** (Line 697 in lib.php):
```
🎥 Ready (1.7 GB)
```

**Problems**:
1. No video filename shown
2. Only shows status + size
3. Takes up minimal space but provides minimal information
4. User can't identify which video without clicking

### Viewport Analysis

From screenshot:
- **Table columns**: Select | User picture | First name/Last name | Email | Status | Grade | Edit | Last modified | Cloudflare Stream | Submission comments | Last modified (graded)
- **Cloudflare Stream column width**: ~150-200px (estimated)
- **Available vertical space**: Single row height (~40-50px)
- **Constraint**: Must not overlap other columns or break table layout

### Proposed Solutions

#### Option 1: Compact Multi-line (Recommended)
```
🎥 BIG_Video.mp4
   Ready • 1.7 GB
```

**Layout**:
- Line 1: Icon + Filename (truncated with ellipsis if too long)
- Line 2: Status badge + Size (smaller text)

**Pros**:
- Shows all information
- Compact vertical space (~2 lines)
- Clear visual hierarchy
- Filename helps identify video

**Cons**:
- Slightly taller than current (but still fits in row)

#### Option 2: Tooltip on Hover
```
🎥 Ready (1.7 GB)
   [Hover shows: "BIG_Video.mp4"]
```

**Pros**:
- Same space as current
- No layout changes

**Cons**:
- Filename hidden until hover
- Not mobile-friendly

#### Option 3: Filename Only with Icon Color
```
🎥 BIG_Video.mp4
   1.7 GB
```

**Pros**:
- Filename prominent
- Icon color indicates status (green=ready, yellow=processing)

**Cons**:
- Status not explicitly shown
- Relies on icon color meaning

### Recommended Solution: Option 1 (Compact Multi-line)

**Visual Design**:
```
┌─────────────────────────────────┐
│ 🎥 BIG_Video.mp4                │  ← Icon + Filename (bold, truncate at 25 chars)
│    ✓ Ready • 1.7 GB             │  ← Status badge + Size (muted, smaller)
└─────────────────────────────────┘
```

**CSS Styling**:
- Filename: `font-weight: 500; font-size: 14px; color: #333;`
- Status line: `font-size: 12px; color: #666; margin-top: 2px;`
- Status badge: `✓` checkmark for ready, `⏱` clock for processing
- Truncate filename: `text-overflow: ellipsis; overflow: hidden; white-space: nowrap;`

**Responsive Behavior**:
- Desktop (>768px): Show full layout
- Tablet (768px): Truncate filename at 20 chars
- Mobile (<576px): Stack vertically, full width

### Implementation Plan

**Files to Modify**:
1. `lib.php` (line 680-700) - Update `view_summary()` method
2. `styles.css` - Add new CSS classes
3. `lang/en/assignsubmission_cloudflarestream.php` - Add any new strings

**Code Changes**:

```php
// In lib.php, case 'ready':
$icon = '<i class="fa fa-video-camera text-success" aria-hidden="true"></i>';

// Get filename from video metadata or use default
$filename = !empty($video->filename) ? $video->filename : 'Video';
$truncated_filename = $this->truncate_filename($filename, 25);

$output = html_writer::start_div('cfstream-grading-summary');

// Line 1: Icon + Filename
$output .= html_writer::div(
    $icon . ' ' . html_writer::span($truncated_filename, 'cfstream-filename'),
    'cfstream-title-line'
);

// Line 2: Status + Size
$status_badge = '<i class="fa fa-check-circle text-success" aria-hidden="true"></i>';
$size_text = $video->file_size ? display_size($video->file_size) : '';
$output .= html_writer::div(
    html_writer::span($status_badge . ' ' . $statustext, 'cfstream-status') . 
    ($size_text ? ' • ' . html_writer::span($size_text, 'cfstream-size') : ''),
    'cfstream-meta-line'
);

$output .= html_writer::end_div();

// Make it clickable
$output = html_writer::link($viewurl, $output, [
    'target' => '_blank',
    'title' => $filename . ' - ' . $statustext,
    'class' => 'cfstream-grading-link'
]);
```

**CSS**:
```css
/* Grading table summary */
.cfstream-grading-summary {
    display: block;
    line-height: 1.4;
}

.cfstream-title-line {
    font-weight: 500;
    font-size: 14px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

.cfstream-meta-line {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.cfstream-status {
    font-weight: 400;
}

.cfstream-size {
    color: #999;
}

.cfstream-grading-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.cfstream-grading-link:hover {
    background-color: #f5f5f5;
    border-radius: 4px;
    padding: 4px;
    margin: -4px;
}

/* Responsive */
@media (max-width: 768px) {
    .cfstream-title-line {
        max-width: 150px;
    }
}
```

### Testing Checklist

- [ ] Display correct on desktop (>1200px)
- [ ] Display correct on tablet (768px-1200px)
- [ ] Display correct on mobile (<768px)
- [ ] Filename truncates properly with ellipsis
- [ ] Hover state works
- [ ] Click opens video in new tab
- [ ] No overlap with adjacent columns
- [ ] Works with long filenames (50+ chars)
- [ ] Works with short filenames (5 chars)
- [ ] Works with special characters in filename
- [ ] Status badge shows correct icon
- [ ] File size displays correctly (MB, GB)

### Edge Cases to Handle

1. **No filename in database**: Use "Video" as default
2. **Very long filename**: Truncate at 25 chars with "..."
3. **No file size**: Don't show size, only status
4. **Processing status**: Show different icon (clock instead of checkmark)
5. **Error status**: Show error icon and message

---

## Issue 2: [Placeholder for next UI issue]

*To be added when you provide the next issue*

---

## Issue 3: [Placeholder]

*To be added*

---

## Implementation Status

| Issue | Status | Files Modified | Tested | Deployed |
|-------|--------|----------------|--------|----------|
| Issue 1: Grading Table Display | ✅ Implemented | lib.php, styles.css | ⏳ Pending | ❌ |

---

## Notes

- Always check viewport constraints before implementing
- Test on multiple screen sizes
- Consider mobile users
- Keep table layout intact
- Use Moodle's existing CSS classes where possible
- Follow Moodle's design patterns
