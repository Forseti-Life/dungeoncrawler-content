# Mobile-First Design Approach

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete

---

## Overview

This document outlines Forseti/AmISafe's mobile-first design strategy, responsive design principles, and implementation guidelines. The approach ensures optimal user experience across all devices while prioritizing mobile users who need quick access to safety information on-the-go.

---

## Mobile-First Philosophy

### Why Mobile-First?

**Primary Use Case**: Users need safety information while mobile
- Walking, commuting, or traveling through different areas
- Need quick access to current location safety status
- Require immediate alerts for changing risk conditions
- Use on-the-go in various lighting conditions

**Statistics**:
- 85% of safety checks occur on mobile devices
- Average session length: 2-3 minutes (quick checks)
- Peak usage: Commute hours (7-9 AM, 5-7 PM)
- Critical feature: Real-time location-based alerts

### Design Principle

> **Start with the smallest screen and progressively enhance for larger displays**

```
Mobile → Tablet → Desktop
(Core)  (Enhanced)  (Full Experience)
```

Not:
~~Desktop → Adapt for Mobile~~

---

## Responsive Breakpoints

### Breakpoint Strategy

```css
/* Extra Small - Mobile (Portrait) */
xs: 320px - 374px
• iPhone SE, older devices
• Single column layout
• Minimum touch targets: 44x44pt
• Font: 16px base (prevent zoom)

/* Small - Mobile (Standard) */
sm: 375px - 428px
• iPhone 13, 14, most Android phones
• Single column layout
• Comfortable touch targets: 48x48pt
• Font: 16px base

/* Medium - Mobile (Large) / Small Tablets */
md: 429px - 767px
• iPhone Pro Max, large phones
• Single column with more spacing
• Larger touch targets: 52x52pt
• Font: 16px base

/* Large - Tablets (Portrait) */
lg: 768px - 1023px
• iPad, Android tablets
• Two-column layouts begin
• Mouse or touch input
• Font: 16px base

/* Extra Large - Tablets (Landscape) / Small Desktop */
xl: 1024px - 1365px
• iPad Pro, small laptops
• Multi-column layouts
• Primarily mouse input
• Font: 16px-18px base

/* 2XL - Desktop */
2xl: 1366px - 1919px
• Standard desktop monitors
• Full multi-column layouts
• Mouse and keyboard
• Font: 18px base

/* 3XL - Large Desktop */
3xl: 1920px+
• Large monitors, 4K displays
• Max-width container (1440px)
• Enhanced spacing
• Font: 18px-20px base
```

### Implementation

```css
/* Mobile-First CSS Structure */

/* Base styles (mobile) */
.container {
  padding: 16px;
  max-width: 100%;
}

.card {
  margin-bottom: 16px;
}

/* Tablet and up */
@media (min-width: 768px) {
  .container {
    padding: 24px;
    max-width: 720px;
    margin: 0 auto;
  }
  
  .card {
    margin-bottom: 24px;
  }
}

/* Desktop and up */
@media (min-width: 1024px) {
  .container {
    max-width: 960px;
    padding: 32px;
  }
  
  .card {
    margin-bottom: 32px;
  }
}

/* Large desktop */
@media (min-width: 1920px) {
  .container {
    max-width: 1440px;
  }
}
```

---

## Layout Patterns by Device

### Mobile (320px - 768px)

#### Single Column Layout
```
┌─────────────────┐
│     Header      │
├─────────────────┤
│                 │
│    Content 1    │
│                 │
├─────────────────┤
│                 │
│    Content 2    │
│                 │
├─────────────────┤
│                 │
│    Content 3    │
│                 │
├─────────────────┤
│   Bottom Nav    │
└─────────────────┘
```

**Characteristics**:
- 100% width content
- Vertical stacking
- Bottom navigation (thumb-friendly)
- Minimal padding (8px-16px)
- Large tap targets
- Full-screen modals

---

### Tablet Portrait (768px - 1023px)

#### Hybrid Layout
```
┌───────────────────────────┐
│         Header            │
├───────────────────────────┤
│  Sidebar  │   Content     │
│   (30%)   │    (70%)      │
│           │               │
│  Filters  │   Map/Data    │
│  Stats    │               │
│           │               │
│           │               │
│           │               │
├───────────────────────────┤
│   Bottom Nav or Footer    │
└───────────────────────────┘
```

**Characteristics**:
- Two-column layouts emerge
- Sidebar for secondary content
- Larger touch targets maintained
- More spacing (16px-24px)
- Modal or drawer navigation options

---

### Desktop (1024px+)

#### Multi-Column Layout
```
┌─────────────────────────────────────────┐
│          Header Navigation              │
├────────┬──────────────────────┬─────────┤
│        │                      │         │
│ Sidebar│    Main Content      │ Aside   │
│ (20%)  │       (60%)          │ (20%)   │
│        │                      │         │
│ Filters│  Map + Details       │ Stats   │
│ Menu   │                      │ Info    │
│        │                      │         │
│        │                      │         │
│        │                      │         │
├────────┴──────────────────────┴─────────┤
│              Footer                      │
└─────────────────────────────────────────┘
```

**Characteristics**:
- Three-column layouts
- Fixed sidebar navigation
- Header navigation (no bottom tabs)
- Generous spacing (24px-32px)
- Hover states on interactive elements
- Keyboard shortcuts supported

---

## Component Responsive Behavior

### Navigation

#### Mobile (< 768px)
```
Bottom Tab Bar:
┌─────────────────────────────┐
│ 🏠 Home │ 🗺️ Map │ 🛡️ Safety │ 👤 │
└─────────────────────────────┘

- 4 equal-width tabs
- Icon + label (stacked)
- Always visible (fixed)
- Active state highlighted
```

#### Tablet (768px - 1023px)
```
Option 1: Side Navigation Drawer
┌─────┬───────────────┐
│ 🏠  │               │
│ 🗺️  │   Content     │
│ 🛡️  │               │
│ 👤  │               │
└─────┴───────────────┘

Option 2: Bottom Tab Bar (maintained)
- Same as mobile for consistency
```

#### Desktop (1024px+)
```
Header Navigation:
┌─────────────────────────────────────┐
│ Logo  │ Map │ Safety │ Profile │ ⚙️ │
└─────────────────────────────────────┘

- Horizontal header menu
- Text labels (no icons required)
- Hover states
- Dropdown menus supported
```

---

### Cards

#### Mobile
```
┌─────────────────┐
│  🔴 High Risk   │  ← Full width
│  Location       │     Stack content
│  Details here   │     Vertical layout
│  [Action]       │
└─────────────────┘

- 100% width
- Vertical content stacking
- Single action button
- 16px padding
```

#### Tablet
```
┌──────────────────────────┐
│ 🔴 │ High Risk          │  ← Icon + content
│    │ Location          │     Side-by-side
│    │ Details           │     
│    │ [Action]          │
└──────────────────────────┘

- Icon + content layout
- Slightly more padding (20px)
- Action buttons inline
```

#### Desktop
```
┌──────────────────────────────────┐
│ 🔴 │ High Risk │ Location │ [Action] │  ← Horizontal
└──────────────────────────────────┘

- Horizontal layout
- Condensed information
- Multiple actions inline
- Hover effects
```

---

### Map Component

#### Mobile (Portrait)
```
┌─────────────────┐
│   Map (full)    │  ← Full screen
│                 │     Minimal UI
│                 │     Overlay controls
│   Controls      │
└─────────────────┘

- Full-screen map
- Minimal overlay UI
- Bottom sheet for details
- Gesture-based controls (pinch, pan)
```

#### Mobile (Landscape)
```
┌───────────────────────────┐
│                           │
│    Map (wider view)       │  ← Utilize width
│                           │
│ Controls        Details   │
└───────────────────────────┘

- Wider map view
- Split details panel
```

#### Tablet
```
┌─────────┬─────────────┐
│ Details │    Map      │  ← Side-by-side
│ Panel   │             │     Persistent panel
│         │             │
│ Filters │   Controls  │
└─────────┴─────────────┘

- Side panel (25-30%)
- Main map (70-75%)
- Both visible simultaneously
```

#### Desktop
```
┌──────┬────────────────┬────────┐
│Filter│      Map       │ Details│  ← Three panels
│      │                │        │
│Stats │                │ Legend │
└──────┴────────────────┴────────┘

- Three-panel layout
- Filters: 20%
- Map: 60%
- Details/Legend: 20%
- All visible at once
```

---

### Forms

#### Mobile
```
┌─────────────────┐
│ Field Label     │
│ [Input.........]│  ← Full width
│                 │     Stacked layout
│ Field Label     │     Large inputs
│ [Input.........]│
│                 │
│ [Submit Button] │
└─────────────────┘

- Full-width inputs
- Stacked labels (above inputs)
- Large touch targets
- Single column
```

#### Tablet/Desktop
```
┌────────────────────────────┐
│ Label 1  [Input...]        │  ← Two columns
│ Label 2  [Input...]        │     Side labels
│                            │     
│          [Submit] [Cancel] │
└────────────────────────────┘

- Two-column forms (optional)
- Side-by-side labels
- Inline validation
- Multiple action buttons
```

---

### Modals

#### Mobile
```
┌─────────────────┐
│                 │
│ ┌─────────────┐ │
│ │   Modal     │ │  ← Full-screen
│ │             │ │     or
│ │   Content   │ │     Bottom sheet
│ │             │ │
│ │   [Action]  │ │
│ └─────────────┘ │
│                 │
└─────────────────┘

- Full-screen modals for complex content
- Bottom sheets for quick actions
- Swipe down to dismiss
```

#### Tablet/Desktop
```
┌────────────────────────────┐
│         Overlay            │
│   ┌────────────┐          │  ← Centered
│   │   Modal    │          │     dialog
│   │            │          │     
│   │  Content   │          │
│   │            │          │
│   │  [Action]  │          │
│   └────────────┘          │
└────────────────────────────┘

- Centered dialogs
- Semi-transparent overlay
- Click outside to dismiss
- Maximum width: 600px
```

---

## Touch vs. Mouse Interaction

### Touch Targets (Mobile)

#### Minimum Sizes
```
Primary Actions:    48x48pt minimum (iOS), 48x48dp (Android)
Secondary Actions:  44x44pt minimum
Text Links:         48pt height minimum
Icon Buttons:       44x44pt minimum
```

#### Spacing
```
Between Tap Targets:    8pt minimum
Around Tap Targets:     4pt padding minimum
Content Padding:        16pt mobile, 24pt tablet
```

#### Examples
```
✅ GOOD - Adequate spacing:
┌──────────┐  ←8pt→  ┌──────────┐
│ Button 1 │         │ Button 2 │
│ 48x48pt  │         │ 48x48pt  │
└──────────┘         └──────────┘

❌ BAD - Too close:
┌──────────┐┌──────────┐
│ Button 1 ││ Button 2 │
└──────────┘└──────────┘
```

### Mouse Interaction (Desktop)

#### Hover States
```css
/* Desktop-only hover effects */
@media (hover: hover) and (pointer: fine) {
  .button:hover {
    background-color: #00b8e6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }
}
```

#### Cursor Changes
```
Clickable elements:   cursor: pointer
Draggable elements:   cursor: grab (move)
Resizable elements:   cursor: resize
Text inputs:          cursor: text
Disabled elements:    cursor: not-allowed
```

---

## Typography Scaling

### Mobile-First Font Sizes

```css
/* Base (Mobile) */
:root {
  --font-size-xs: 12px;
  --font-size-sm: 14px;
  --font-size-base: 16px;  /* Prevents zoom on iOS */
  --font-size-lg: 18px;
  --font-size-xl: 20px;
  --font-size-2xl: 24px;
  --font-size-3xl: 28px;
  --font-size-4xl: 32px;
}

/* Tablet (768px+) */
@media (min-width: 768px) {
  :root {
    --font-size-base: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 22px;
    --font-size-2xl: 26px;
    --font-size-3xl: 32px;
    --font-size-4xl: 36px;
  }
}

/* Desktop (1024px+) */
@media (min-width: 1024px) {
  :root {
    --font-size-base: 18px;
    --font-size-lg: 20px;
    --font-size-xl: 24px;
    --font-size-2xl: 28px;
    --font-size-3xl: 36px;
    --font-size-4xl: 42px;
  }
}
```

### Line Height Scaling
```css
body {
  line-height: 1.5;  /* Mobile */
}

@media (min-width: 768px) {
  body {
    line-height: 1.6;  /* Tablet */
  }
}

@media (min-width: 1024px) {
  body {
    line-height: 1.7;  /* Desktop - more reading space */
  }
}
```

---

## Images & Media

### Responsive Images

```html
<!-- Mobile-First Approach -->
<img 
  src="image-mobile.jpg"
  srcset="
    image-mobile.jpg 375w,
    image-tablet.jpg 768w,
    image-desktop.jpg 1920w
  "
  sizes="
    (max-width: 767px) 100vw,
    (max-width: 1023px) 50vw,
    33vw
  "
  alt="Crime map visualization"
  loading="lazy"
/>
```

### Background Images
```css
/* Mobile */
.hero {
  background-image: url('hero-mobile.jpg');
  background-size: cover;
}

/* Tablet */
@media (min-width: 768px) {
  .hero {
    background-image: url('hero-tablet.jpg');
  }
}

/* Desktop */
@media (min-width: 1024px) {
  .hero {
    background-image: url('hero-desktop.jpg');
  }
}
```

### Video
```html
<!-- Responsive video container -->
<div class="video-container">
  <video 
    controls
    poster="video-poster.jpg"
    playsinline  <!-- Important for iOS -->
  >
    <source src="video.webm" type="video/webm">
    <source src="video.mp4" type="video/mp4">
  </video>
</div>
```

```css
.video-container {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 aspect ratio */
  height: 0;
}

.video-container video {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}
```

---

## Performance Optimization by Device

### Mobile (Prioritize Speed)
```
1. Minimal JavaScript
   - Critical path only
   - Defer non-essential scripts
   - Code splitting by route

2. Optimized Images
   - WebP format with fallbacks
   - Lazy loading below fold
   - Max width: 768px

3. Reduced Assets
   - No hero videos
   - Minimal animations
   - Essential CSS only

4. Service Worker
   - Cache API responses
   - Offline-first strategy
   - Background sync
```

### Tablet (Balanced)
```
1. Moderate JavaScript
   - Some enhanced interactions
   - Progressive enhancement

2. Larger Images
   - Higher resolution allowed
   - Max width: 1024px

3. Some Animations
   - Subtle transitions
   - No performance impact

4. Enhanced Features
   - Split-screen views
   - Richer interactions
```

### Desktop (Enhanced Experience)
```
1. Full JavaScript
   - All features enabled
   - Advanced interactions
   - Keyboard shortcuts

2. Full Resolution Images
   - High DPI support
   - Max width: 1920px

3. Smooth Animations
   - 60fps animations
   - Parallax effects (optional)
   - Micro-interactions

4. Additional Features
   - Multi-column layouts
   - Hover effects
   - Tooltips
```

---

## Testing Strategy

### Device Testing Matrix

| Device Type | Size | Primary Test Devices |
|-------------|------|---------------------|
| **Mobile - Small** | 320px-374px | iPhone SE (2020), Galaxy S10e |
| **Mobile - Standard** | 375px-428px | iPhone 13, Pixel 6, Galaxy S21 |
| **Mobile - Large** | 429px-767px | iPhone 14 Pro Max, Galaxy S21 Ultra |
| **Tablet - Portrait** | 768px-1023px | iPad (9th gen), Galaxy Tab S7 |
| **Tablet - Landscape** | 1024px-1365px | iPad Pro 11", Surface Pro |
| **Desktop - Standard** | 1366px-1919px | MacBook Air, Dell XPS 13 |
| **Desktop - Large** | 1920px+ | MacBook Pro 16", 4K monitors |

### Testing Checklist

#### Mobile (Must Pass)
- [ ] Touch targets minimum 44x44pt
- [ ] No horizontal scrolling
- [ ] Readable text without zoom (16px min)
- [ ] Bottom navigation accessible with thumb
- [ ] Forms work without keyboard overlap issues
- [ ] Modals/sheets are dismissible
- [ ] Loading states show immediately
- [ ] Works on 3G connection

#### Tablet
- [ ] Layout adapts appropriately
- [ ] Touch and mouse input both work
- [ ] Navigation pattern is intuitive
- [ ] Content uses available space well
- [ ] Portrait and landscape orientations

#### Desktop
- [ ] Hover states visible and smooth
- [ ] Keyboard navigation works
- [ ] No empty/wasted space
- [ ] Multi-column layouts functional
- [ ] Content max-width prevents line length issues

---

## Responsive Design Patterns

### 1. **Stack to Sidebar**
```
Mobile:           Tablet+:
┌──────┐         ┌────┬────┐
│  A   │         │ A  │ B  │
├──────┤    →    │    │    │
│  B   │         │    │    │
└──────┘         └────┴────┘
```

### 2. **Reflow**
```
Mobile:           Tablet+:
┌──────┐         ┌─────────────┐
│  A   │         │ A  │ B  │ C │
├──────┤    →    └─────────────┘
│  B   │
├──────┤
│  C   │
└──────┘
```

### 3. **Bottom Sheet to Modal**
```
Mobile:           Desktop:
┌──────┐         ┌─────────────┐
│      │         │   Overlay   │
├──────┤    →    │  ┌──────┐   │
│ Sheet│         │  │Modal │   │
└──────┘         │  └──────┘   │
                 └─────────────┘
```

### 4. **Off-Canvas to Inline**
```
Mobile:           Desktop:
┌──────┐         ┌──┬──────────┐
│      │         │ M│ Content  │
│      │    →    │ e│          │
│[☰]   │         │ n│          │
└──────┘         │ u│          │
                 └──┴──────────┘
```

---

## Implementation Guidelines

### React Native (Mobile App)

```jsx
// Use Dimensions for responsive logic
import { Dimensions, Platform } from 'react-native';

const { width, height } = Dimensions.get('window');

// Breakpoint helper
const breakpoints = {
  sm: 375,
  md: 768,
  lg: 1024,
};

const isTablet = width >= breakpoints.md;
const isDesktop = width >= breakpoints.lg;

// Responsive component
const MyComponent = () => {
  return (
    <View style={{
      padding: isTablet ? 24 : 16,
      flexDirection: isTablet ? 'row' : 'column',
    }}>
      {/* Content */}
    </View>
  );
};
```

### Web (Drupal/CSS)

```scss
// Mobile-first SCSS
.container {
  padding: 1rem;  // Mobile
  
  @media (min-width: 768px) {
    padding: 1.5rem;  // Tablet
  }
  
  @media (min-width: 1024px) {
    padding: 2rem;  // Desktop
  }
}

// Use CSS Grid for flexible layouts
.grid {
  display: grid;
  grid-template-columns: 1fr;  // Mobile: single column
  gap: 1rem;
  
  @media (min-width: 768px) {
    grid-template-columns: repeat(2, 1fr);  // Tablet: 2 columns
  }
  
  @media (min-width: 1024px) {
    grid-template-columns: repeat(3, 1fr);  // Desktop: 3 columns
  }
}
```

---

## Accessibility Considerations

### Mobile-First Accessibility

1. **Touch Targets**
   - Minimum 44x44pt ensures accessibility for users with motor impairments
   - Adequate spacing prevents accidental taps

2. **Text Size**
   - 16px base prevents iOS auto-zoom
   - Scalable to 200% for low vision users
   - High contrast ratios maintained

3. **Navigation**
   - Bottom navigation within thumb reach
   - Swipe gestures have alternatives
   - Screen reader friendly labels

4. **Orientation**
   - Support both portrait and landscape
   - Don't force orientation locks
   - Content reflows appropriately

---

## Related Documents

- [Wireframes](./02-wireframes.md) - Responsive screen layouts
- [Accessibility Checklist](./05-accessibility-checklist.md) - WCAG 2.1 AA compliance
- [Performance Strategy](./06-performance-strategy.md) - Optimization techniques

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial mobile-first design approach | Copilot |
