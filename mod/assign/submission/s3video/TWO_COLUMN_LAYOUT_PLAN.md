# Two-Column Grading Layout Implementation Plan

## Overview

This document outlines the implementation plan to create a two-column grading layout similar to Moodle's PDF annotation interface, where the video player occupies the left column and grading controls occupy the right column.

## Current State Analysis

### Current Implementation
- Video player currently displays full-width in the grading interface
- Grading controls appear below the video player
- Layout is single-column, causing vertical scrolling

### Target Layout (Based on PDF Annotation Interface)
```
┌─────────────────────────────────────────────────────────────────┐
│                    Moodle Header & Navigation                   │
├─────────────────────────────┬───────────────────────────────────┤
│                             │                                   │
│        VIDEO PLAYER         │        GRADING PANEL              │
│                             │                                   │
│  ┌─────────────────────┐    │  ┌─────────────────────────────┐  │
│  │                     │    │  │ Submission Status           │  │
│  │   S3 Video Player   │    │  │ ✓ Submitted for grading     │  │
│  │                     │    │  │                             │  │
│  │     [Play Button]   │    │  │ Not graded                  │  │
│  │                     │    │  │                             │  │
│  │                     │    │  │ Assignment was submitted    │  │
│  └─────────────────────┘    │  │ 6 days 4 hours early        │  │
│                             │  │                             │  │
│  Video Metadata:            │  │ ✓ Student can edit this     │  │
│  Duration: 2:34             │  │   submission                │  │
│  Size: 73.9 MB              │  │                             │  │
│                             │  │ Comments (0)                │  │
│                             │  │                             │  │
│                             │  │ ┌─────────────────────────┐ │  │
│                             │  │ │ Grade                   │ │  │
│                             │  │ │                         │ │  │
│                             │  │ │ Grade out of 100 ⚙      │ │  │
│                             │  │ │                         │ │  │
│                             │  │ │ [Grade Input Field]     │ │  │
│                             │  │ │                         │ │  │
│                             │  │ │ Current grade in        │ │  │
│                             │  │ │ gradebook: Not graded   │ │  │
│                             │  │ │                         │ │  │
│                             │  │ │ Feedback comments 📝    │ │  │
│                             │  │ │                         │ │  │
│                             │  │ │ [Feedback Text Area]    │ │  │
│                             │  │ └─────────────────────────┘ │  │
│                             │  └─────────────────────────────┘  │
├─────────────────────────────┴───────────────────────────────────┤
│                    Action Buttons (Save, Next, etc.)           │
└─────────────────────────────────────────────────────────────────┘
```

## Implementation Approaches

### Approach 1: CSS Grid Layout (Recommended)
**Pros:**
- Modern, flexible layout system
- Easy responsive breakpoints
- Clean separation of concerns
- Maintains existing Moodle structure

**Cons:**
- Requires CSS Grid support (IE11+)
- May need fallbacks for older browsers

### Approach 2: CSS Flexbox Layout
**Pros:**
- Better browser support
- Flexible and responsive
- Well-established pattern

**Cons:**
- More complex for two-column layouts
- Requires more CSS for equal heights

### Approach 3: JavaScript Layout Manipulation
**Pros:**
- Complete control over layout
- Can dynamically adjust to content

**Cons:**
- More complex implementation
- Potential performance issues
- Harder to maintain

## Recommended Implementation: CSS Grid Layout

### Technical Strategy

#### 1. DOM Structure Modification
```html
<!-- Current Structure -->
<div class="s3video-grading-view">
    <div class="s3video-player-wrapper">
        <!-- Video Player -->
    </div>
    <!-- Grading controls appear below -->
</div>

<!-- Target Structure -->
<div class="s3video-two-column-layout">
    <div class="s3video-left-column">
        <div class="s3video-player-wrapper">
            <!-- Video Player -->
        </div>
        <div class="s3video-metadata">
            <!-- Video metadata -->
        </div>
    </div>
    <div class="s3video-right-column">
        <!-- Existing grading interface moved here -->
    </div>
</div>
```

#### 2. CSS Grid Implementation
```css
.s3video-two-column-layout {
    display: grid;
    grid-template-columns: 65% 35%; /* Video 65%, Grading 35% - matches PDF annotation */
    gap: 20px;
    height: 100vh; /* Full viewport height */
    max-height: calc(100vh - 200px); /* Account for Moodle header */
}

.s3video-left-column {
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.s3video-right-column {
    overflow-y: auto; /* Scrollable grading panel */
    background: #f8f9fa;
    border-left: 1px solid #dee2e6;
    padding: 20px;
}
```

#### 3. JavaScript DOM Manipulation
The grading injector will need to:
1. Detect the grading interface elements
2. Restructure the DOM to create two columns
3. Move grading controls to the right column
4. Ensure proper event handling is maintained

### Implementation Steps

#### Phase 1: Layout Detection and Structure
1. **Identify Moodle's grading interface elements**
   - Grade panel containers
   - Feedback forms
   - Navigation buttons
   - Status indicators

2. **Create DOM restructuring logic**
   - Wrap existing content in grid container
   - Move grading elements to right column
   - Preserve all existing functionality

#### Phase 2: CSS Grid Styling
1. **Implement responsive grid layout**
   - Desktop: Two columns (video + grading)
   - Tablet: Stacked layout
   - Mobile: Single column

2. **Style integration**
   - Match Moodle's design system
   - Ensure accessibility compliance
   - Test with different content sizes

#### Phase 3: JavaScript Integration
1. **Enhanced grading injector**
   - Detect grading interface
   - Apply two-column layout
   - Handle responsive breakpoints

2. **Event handling preservation**
   - Ensure all Moodle events still work
   - Maintain form submissions
   - Preserve navigation functionality

### File Modifications Required

#### 1. JavaScript Files
- `amd/src/grading_injector.js` - Major modifications for layout restructuring
- `amd/build/grading_injector.min.js` - Rebuilt version

#### 2. CSS Files
- `styles.css` - Add grid layout styles and responsive breakpoints

#### 3. New Files (Optional)
- `amd/src/layout_manager.js` - Separate module for layout management
- `templates/two_column_layout.mustache` - Template for grid structure

### Responsive Breakpoints

```css
/* Desktop: Two columns - 65/35 split */
@media (min-width: 1200px) {
    .s3video-two-column-layout {
        grid-template-columns: 65% 35%;
    }
}

/* Laptop: Same proportions but smaller overall */
@media (max-width: 1199px) and (min-width: 992px) {
    .s3video-two-column-layout {
        grid-template-columns: 65% 35%;
    }
}

/* Tablet: Stacked layout */
@media (max-width: 991px) {
    .s3video-two-column-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }
}

/* Mobile: Single column */
@media (max-width: 768px) {
    .s3video-two-column-layout {
        display: block;
    }
}
```

### Challenges and Solutions

#### Challenge 1: Moodle's Existing Layout
**Problem:** Moodle's grading interface has complex nested structures
**Solution:** Use JavaScript to carefully identify and move elements without breaking functionality

#### Challenge 2: Dynamic Content Loading
**Problem:** Moodle loads grading content dynamically via AJAX
**Solution:** Use MutationObserver to detect when grading interface loads and apply layout

#### Challenge 3: Form Submissions
**Problem:** Moving form elements might break submission handling
**Solution:** Preserve all form containers and only move visual elements

#### Challenge 4: Responsive Design
**Problem:** Two-column layout may not work on smaller screens
**Solution:** Implement responsive breakpoints that fall back to single column

### Testing Strategy

#### 1. Layout Testing
- Test on different screen sizes
- Verify responsive breakpoints
- Check with different video aspect ratios

#### 2. Functionality Testing
- Ensure all grading features work
- Test form submissions
- Verify navigation between students

#### 3. Browser Compatibility
- Test CSS Grid support
- Implement fallbacks for older browsers
- Verify mobile compatibility

### Performance Considerations

#### 1. CSS Optimization
- Use efficient selectors
- Minimize layout recalculations
- Optimize for 60fps scrolling

#### 2. JavaScript Efficiency
- Minimize DOM manipulations
- Use event delegation
- Implement lazy loading where possible

## Implementation Priority

### High Priority (Must Have)
1. Basic two-column grid layout
2. Video player in left column
3. Grading controls in right column
4. Responsive mobile fallback

### Medium Priority (Should Have)
1. Smooth transitions between layouts
2. Optimized scrolling behavior
3. Enhanced visual styling
4. Accessibility improvements

### Low Priority (Nice to Have)
1. Resizable columns
2. Layout preferences storage
3. Advanced animations
4. Custom themes support

## Conclusion

The two-column layout implementation will significantly improve the grading experience by:
- Allowing simultaneous video viewing and grading
- Reducing scrolling and context switching
- Providing a more professional, desktop-like interface
- Maintaining full compatibility with existing Moodle functionality

The CSS Grid approach provides the best balance of modern functionality, maintainability, and performance while ensuring the layout works seamlessly within Moodle's existing architecture.