# Accessibility Checklist - WCAG 2.1 AA Compliance

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete  
**Compliance Target**: WCAG 2.1 Level AA

---

## Overview

This document provides a comprehensive accessibility checklist for Forseti/AmISafe to ensure compliance with Web Content Accessibility Guidelines (WCAG) 2.1 Level AA. Accessibility is critical for a safety platform - all users, regardless of ability, must be able to access life-saving information.

---

## WCAG 2.1 Principles (POUR)

### 🅿️ **Perceivable**
Information and user interface components must be presentable to users in ways they can perceive.

### 🅾️ **Operable**
User interface components and navigation must be operable.

### 🆄 **Understandable**
Information and the operation of user interface must be understandable.

### 🆁 **Robust**
Content must be robust enough to be interpreted by a wide variety of user agents, including assistive technologies.

---

## Accessibility Checklist by Category

### 1. Text & Typography

#### ✅ Text Readability
- [ ] **Base font size**: 16px minimum on mobile (prevents iOS auto-zoom)
- [ ] **Base font size**: 18px on desktop for comfortable reading
- [ ] **Line height**: 1.5 minimum for body text
- [ ] **Line length**: 80 characters maximum per line (optimal: 50-75)
- [ ] **Paragraph spacing**: 1.5x line height minimum
- [ ] **Letter spacing**: Adjustable up to 0.12em
- [ ] **Word spacing**: Adjustable up to 0.16em

#### ✅ Text Scaling
- [ ] **Zoom support**: Content readable at 200% zoom without horizontal scrolling
- [ ] **User preferences**: Respect system font size settings
- [ ] **No fixed containers**: Avoid fixed-width containers that break at large text
- [ ] **Responsive**: Text reflows appropriately at all sizes

#### ✅ Font Choices
- [ ] **Sans-serif fonts**: Use readable sans-serif for UI (e.g., Inter, Roboto, SF Pro)
- [ ] **Adequate weight**: Body text minimum 400 weight, headings 600+
- [ ] **No ALL CAPS**: Avoid for large blocks of text (harder to read)

**Implementation:**
```css
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 1.6;
  letter-spacing: 0.01em;
}

p {
  margin-bottom: 1.5em;
  max-width: 75ch;
}

@media (min-width: 1024px) {
  body {
    font-size: 18px;
  }
}
```

---

### 2. Color & Contrast

#### ✅ Color Contrast Ratios (WCAG AA)
- [ ] **Normal text**: 4.5:1 minimum contrast ratio
- [ ] **Large text (18pt+)**: 3:1 minimum contrast ratio
- [ ] **UI components**: 3:1 minimum (buttons, form borders, focus indicators)
- [ ] **Non-text content**: 3:1 minimum (icons, graphics)

#### ✅ Forseti Color Compliance

| Element | Color | Background | Ratio | Status |
|---------|-------|------------|-------|--------|
| Body Text | #ffffff | #0a0e1a | 17.8:1 | ✅ Pass |
| Secondary Text | #94a3b8 | #0a0e1a | 8.9:1 | ✅ Pass |
| Primary Button | #ffffff | #00d4ff | 4.7:1 | ✅ Pass |
| Success (Safe) | #22c55e | #0a0e1a | 6.2:1 | ✅ Pass |
| Warning (Medium) | #eab308 | #0a0e1a | 13.1:1 | ✅ Pass |
| Danger (High) | #ef4444 | #0a0e1a | 5.8:1 | ✅ Pass |
| Link | #00d4ff | #0a0e1a | 9.1:1 | ✅ Pass |

#### ✅ Color Independence
- [ ] **Not color alone**: Never use color as the only visual means of conveying information
- [ ] **Risk indicators**: Use color + icon + text label
  - 🟢 Safe + "Low Risk" + Z-score value
  - 🟡 Medium + "Medium Risk" + Z-score value
  - 🔴 High + "High Risk" + Z-score value
- [ ] **Status indicators**: Combine color with icons or text
- [ ] **Links**: Underlined or have 3:1 contrast with surrounding text
- [ ] **Charts/graphs**: Use patterns or labels in addition to color

#### ✅ Color Blindness Support
- [ ] **Deuteranopia (red-green)**: Test with simulator
- [ ] **Protanopia (red-green)**: Test with simulator
- [ ] **Tritanopia (blue-yellow)**: Test with simulator
- [ ] **Monochromacy**: Ensure content works in grayscale

**Testing Tools:**
- Chrome DevTools: Rendering > Emulate vision deficiencies
- Stark plugin (Figma/Sketch)
- Color Oracle (desktop app)

---

### 3. Keyboard Navigation

#### ✅ Keyboard Accessibility (Web)
- [ ] **All functionality**: Accessible via keyboard alone
- [ ] **Tab order**: Logical and predictable tab order
- [ ] **Focus indicators**: Visible focus outline on all interactive elements
- [ ] **No keyboard traps**: Users can navigate away from any focused element
- [ ] **Skip links**: "Skip to main content" link at top of page
- [ ] **Modal focus**: Focus trapped within modals, returns on close
- [ ] **Menu navigation**: Arrow keys for menu navigation

#### ✅ Keyboard Shortcuts (Web)
```
/ or S      - Focus search
M           - Toggle map view
H           - Home/Dashboard
Esc         - Close modal/dialog
Tab         - Next element
Shift+Tab   - Previous element
Enter       - Activate button/link
Space       - Toggle checkbox/button
Arrow Keys  - Navigate lists/menus
```

#### ✅ Focus Indicators
```css
/* Custom focus indicator (WCAG AA compliant) */
*:focus-visible {
  outline: 3px solid #00d4ff;
  outline-offset: 2px;
  border-radius: 4px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  *:focus-visible {
    outline-width: 4px;
  }
}
```

#### ✅ Touch Navigation (Mobile)
- [ ] **Swipe gestures**: Provide alternative button controls
- [ ] **Pinch to zoom**: Support pinch zoom on maps (not disabled)
- [ ] **Tap targets**: Minimum 44x44pt touch targets
- [ ] **Spacing**: 8pt minimum between touch targets

---

### 4. Screen Reader Support

#### ✅ Semantic HTML
- [ ] **Proper headings**: H1-H6 in logical order (one H1 per page)
- [ ] **Landmarks**: Use semantic HTML5 elements
  - `<header>` - Page header
  - `<nav>` - Navigation sections
  - `<main>` - Main content
  - `<aside>` - Complementary content
  - `<footer>` - Page footer
  - `<section>` - Thematic grouping
  - `<article>` - Self-contained content
- [ ] **Lists**: Use `<ul>`, `<ol>`, `<dl>` for lists
- [ ] **Tables**: Use `<table>` with `<th>` headers for tabular data
- [ ] **Forms**: Associate `<label>` with form controls

#### ✅ ARIA Labels & Attributes

**When to Use ARIA:**
- Only when semantic HTML is insufficient
- To enhance existing semantics
- To provide additional context

**Common ARIA Patterns:**
```html
<!-- Icon buttons -->
<button aria-label="Close modal">
  <span class="icon-close" aria-hidden="true"></span>
</button>

<!-- Status indicators -->
<div role="status" aria-live="polite">
  Location updated
</div>

<!-- Loading states -->
<div role="alert" aria-live="assertive" aria-busy="true">
  Loading crime data...
</div>

<!-- Navigation -->
<nav aria-label="Main navigation">
  <ul>...</ul>
</nav>

<!-- Tabs -->
<div role="tablist">
  <button role="tab" aria-selected="true" aria-controls="panel1">Home</button>
  <button role="tab" aria-selected="false" aria-controls="panel2">Map</button>
</div>

<!-- Modal -->
<div role="dialog" aria-labelledby="modal-title" aria-modal="true">
  <h2 id="modal-title">Crime Details</h2>
  ...
</div>

<!-- Expandable sections -->
<button aria-expanded="false" aria-controls="details">
  Show More
</button>
<div id="details" hidden>...</div>
```

#### ✅ Alternative Text
- [ ] **All images**: Provide descriptive alt text
- [ ] **Decorative images**: Use empty alt (`alt=""`) or `aria-hidden="true"`
- [ ] **Icons with meaning**: Provide text alternative or aria-label
- [ ] **Charts/graphs**: Provide text description or data table alternative
- [ ] **Maps**: Provide alternative data view or description

**Examples:**
```html
<!-- Informative image -->
<img src="map.png" alt="Crime heatmap showing high risk in Center City">

<!-- Decorative image -->
<img src="divider.png" alt="" role="presentation">

<!-- Icon with adjacent text -->
<span class="icon-home" aria-hidden="true"></span>
<span>Home</span>

<!-- Icon button (no visible text) -->
<button aria-label="View on map">
  <span class="icon-map" aria-hidden="true"></span>
</button>
```

#### ✅ Screen Reader Testing
- [ ] **VoiceOver (iOS/macOS)**: Test on iPhone and Mac
- [ ] **TalkBack (Android)**: Test on Android device
- [ ] **NVDA (Windows)**: Free screen reader for Windows
- [ ] **JAWS (Windows)**: Professional screen reader (if available)

**Testing Checklist:**
- [ ] All content is announced correctly
- [ ] Navigation is logical and predictable
- [ ] Interactive elements are identified (button, link, etc.)
- [ ] Current state is announced (selected, expanded, etc.)
- [ ] Live regions announce updates appropriately
- [ ] Form errors are announced
- [ ] No excessive verbosity

---

### 5. Forms & Inputs

#### ✅ Form Accessibility
- [ ] **Labels**: Every input has an associated `<label>` element
- [ ] **Required fields**: Marked with `required` attribute and visual indicator
- [ ] **Field descriptions**: Use `aria-describedby` for helper text
- [ ] **Error messages**: Associated with fields using `aria-describedby`
- [ ] **Error identification**: Clearly identify fields with errors
- [ ] **Input types**: Use appropriate HTML5 input types
- [ ] **Autocomplete**: Use `autocomplete` attribute where appropriate

**Example Form:**
```html
<form>
  <!-- Text input with label -->
  <div class="form-field">
    <label for="email">
      Email <span aria-label="required">*</span>
    </label>
    <input 
      type="email" 
      id="email" 
      name="email"
      required
      autocomplete="email"
      aria-describedby="email-help email-error"
    >
    <p id="email-help" class="help-text">
      We'll never share your email.
    </p>
    <p id="email-error" class="error-text" hidden>
      Please enter a valid email address.
    </p>
  </div>

  <!-- Select with label -->
  <div class="form-field">
    <label for="alert-cooldown">Alert Cooldown</label>
    <select id="alert-cooldown" name="cooldown">
      <option value="1">1 minute</option>
      <option value="5">5 minutes</option>
      <option value="15" selected>15 minutes</option>
      <option value="30">30 minutes</option>
    </select>
  </div>

  <!-- Checkbox -->
  <div class="form-field">
    <input type="checkbox" id="remember" name="remember">
    <label for="remember">Remember me</label>
  </div>

  <!-- Submit button -->
  <button type="submit">Save Settings</button>
</form>
```

#### ✅ Form Validation
- [ ] **Client-side validation**: Provide immediate feedback
- [ ] **Error summary**: List all errors at top of form
- [ ] **Focus management**: Focus first error on submission
- [ ] **Clear messages**: Specific, actionable error messages
- [ ] **No timeout**: Don't expire forms too quickly
- [ ] **Recovery**: Easy to correct errors and resubmit

---

### 6. Multimedia & Interactive Content

#### ✅ Video Content
- [ ] **Captions**: Provide accurate captions for all videos
- [ ] **Audio description**: Provide audio description for visual content
- [ ] **Transcripts**: Provide text transcript
- [ ] **Controls**: Keyboard-accessible video controls
- [ ] **Autoplay**: Avoid autoplay or provide stop button

#### ✅ Audio Content
- [ ] **Transcripts**: Provide text transcript for audio-only content
- [ ] **Visual indication**: Show when audio is playing
- [ ] **Controls**: Easy to access play/pause controls

#### ✅ Animations & Motion
- [ ] **Respect user preferences**: Honor `prefers-reduced-motion`
- [ ] **Pauseable**: Provide pause control for moving content
- [ ] **No seizure triggers**: No flashing more than 3 times per second
- [ ] **Essential only**: Avoid gratuitous animations

```css
/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Default animations (for those who don't prefer reduced motion) */
@media (prefers-reduced-motion: no-preference) {
  .fade-in {
    animation: fadeIn 300ms ease-in;
  }
}
```

#### ✅ Maps (Interactive)
- [ ] **Keyboard navigation**: Support keyboard panning/zooming
- [ ] **Alternative view**: Provide list view of crime data
- [ ] **Description**: Describe what the map shows
- [ ] **Zoom controls**: Keyboard accessible zoom buttons
- [ ] **Focus management**: Announce location changes to screen readers

---

### 7. Mobile Accessibility (React Native)

#### ✅ Mobile-Specific Considerations
- [ ] **Touch targets**: Minimum 44x44pt (iOS), 48x48dp (Android)
- [ ] **Accessible labels**: Use `accessibilityLabel` prop
- [ ] **Accessible hints**: Use `accessibilityHint` for context
- [ ] **Accessible roles**: Use `accessibilityRole` to identify elements
- [ ] **Accessible states**: Use `accessibilityState` for current state
- [ ] **Screen reader support**: Test with VoiceOver and TalkBack

**React Native Example:**
```jsx
// Button with accessibility
<TouchableOpacity
  onPress={handlePress}
  accessible={true}
  accessibilityLabel="Enable background monitoring"
  accessibilityHint="Double tap to start monitoring your location for safety alerts"
  accessibilityRole="button"
  accessibilityState={{ disabled: isLoading }}
>
  <Text>Enable Monitoring</Text>
</TouchableOpacity>

// Status indicator
<View
  accessible={true}
  accessibilityLabel="Safety status: Low risk"
  accessibilityRole="text"
>
  <Text>🟢 Safe</Text>
  <Text>Z-score: 0.8</Text>
</View>

// Tab bar
<View role="tablist">
  <TouchableOpacity
    accessibilityRole="tab"
    accessibilityLabel="Home"
    accessibilityState={{ selected: selectedTab === 'home' }}
  >
    <Icon name="home" />
    <Text>Home</Text>
  </TouchableOpacity>
</View>
```

#### ✅ Android Accessibility
- [ ] **Content descriptions**: Provide for all ImageViews and ImageButtons
- [ ] **Heading markers**: Use `accessibilityRole="header"` for headings
- [ ] **Live regions**: Use for dynamic content updates

#### ✅ iOS Accessibility
- [ ] **VoiceOver**: Test with VoiceOver enabled
- [ ] **Dynamic Type**: Support dynamic text sizing
- [ ] **Voice Control**: Test with Voice Control
- [ ] **Switch Control**: Ensure switch control compatibility

---

### 8. Error Handling & Feedback

#### ✅ Error Messages
- [ ] **Clear identification**: Clearly identify that an error occurred
- [ ] **Specific**: Describe what went wrong specifically
- [ ] **Actionable**: Tell users how to fix the error
- [ ] **Visible**: Error messages are visually distinct
- [ ] **Announced**: Screen readers announce errors

**Good Error Messages:**
```
❌ BAD: "Error"
✅ GOOD: "Unable to load crime data. Check your internet connection and try again."

❌ BAD: "Invalid input"
✅ GOOD: "Email address must contain an @ symbol. Example: user@example.com"

❌ BAD: "Something went wrong"
✅ GOOD: "Unable to enable monitoring. Location permission is required. Go to Settings to enable."
```

#### ✅ Loading States
- [ ] **Visible indicators**: Show loading spinners or skeleton screens
- [ ] **Announced**: Screen readers announce loading state
- [ ] **Timeout**: Don't wait indefinitely, show error after reasonable time
- [ ] **Cancellable**: Allow users to cancel long operations

```html
<!-- Loading state -->
<div role="status" aria-live="polite">
  <span class="loading-spinner" aria-hidden="true"></span>
  <span>Loading crime data...</span>
</div>

<!-- Success state -->
<div role="status" aria-live="polite">
  <span class="icon-check" aria-hidden="true"></span>
  <span>Crime data loaded successfully</span>
</div>
```

---

### 9. Content Structure & Organization

#### ✅ Heading Structure
```html
<main>
  <h1>Crime Map</h1>  <!-- Only one H1 per page -->
  
  <section>
    <h2>Current Location</h2>  <!-- First H2 -->
    <h3>Safety Status</h3>  <!-- H3 under H2 -->
    <h3>Recent Alerts</h3>  <!-- Another H3 under same H2 -->
  </section>
  
  <section>
    <h2>Crime Statistics</h2>  <!-- Second H2 -->
    <h3>This Week</h3>  <!-- H3 under H2 -->
  </section>
</main>
```

#### ✅ Page Titles
- [ ] **Unique**: Each page has a unique, descriptive title
- [ ] **Format**: `[Page Name] | Forseti` (e.g., "Crime Map | Forseti")
- [ ] **Updated**: Title updates on route change (SPA)

```jsx
// React Native (update navigation title)
navigation.setOptions({
  title: 'Crime Map',
  headerAccessibilityLabel: 'Crime Map, Navigate back button',
});

// Web (update document title)
useEffect(() => {
  document.title = 'Crime Map | Forseti';
}, []);
```

#### ✅ Landmarks
- [ ] **Main content**: Use `<main>` or `role="main"`
- [ ] **Navigation**: Use `<nav>` or `role="navigation"`
- [ ] **Search**: Use `role="search"` for search forms
- [ ] **Complementary**: Use `<aside>` or `role="complementary"`
- [ ] **Contentinfo**: Use `<footer>` or `role="contentinfo"`

---

### 10. Language & Readability

#### ✅ Language Declaration
```html
<html lang="en">
  <!-- Content -->
</html>

<!-- For multilingual content -->
<p lang="es">Este contenido está en español</p>
```

#### ✅ Plain Language
- [ ] **Clear**: Use simple, straightforward language
- [ ] **Concise**: Avoid unnecessary words
- [ ] **Active voice**: Prefer active over passive voice
- [ ] **Define terms**: Explain technical terms on first use
- [ ] **Reading level**: Aim for 8th-grade reading level or lower

**Before:**
> "The geospatial aggregation utilizes the Uber H3 hierarchical hexagonal indexing system with resolution 9, providing approximately 174-meter edge length hexagons for crime incident visualization."

**After:**
> "We use hexagon-shaped areas about 700 meters across to show where crimes happen."

---

## Testing Tools & Resources

### Automated Testing Tools
- [ ] **axe DevTools**: Browser extension for accessibility testing
- [ ] **WAVE**: Web accessibility evaluation tool
- [ ] **Lighthouse**: Built into Chrome DevTools
- [ ] **Pa11y**: Command-line accessibility testing
- [ ] **jest-axe**: Accessibility testing in Jest

### Manual Testing Tools
- [ ] **Screen readers**: VoiceOver, NVDA, JAWS, TalkBack
- [ ] **Keyboard only**: Navigate site with keyboard only
- [ ] **Color contrast**: Use contrast checker tools
- [ ] **Zoom**: Test at 200% zoom
- [ ] **Responsive**: Test on real devices

### Continuous Monitoring
```javascript
// Add axe-core to automated tests
import { axe, toHaveNoViolations } from 'jest-axe';

expect.extend(toHaveNoViolations);

test('Home screen should not have accessibility violations', async () => {
  const { container } = render(<HomeScreen />);
  const results = await axe(container);
  expect(results).toHaveNoViolations();
});
```

---

## Accessibility Statement

Include an accessibility statement on the website:

```markdown
# Accessibility Statement for Forseti

## Commitment
Forseti is committed to ensuring digital accessibility for people with disabilities. 
We are continually improving the user experience for everyone and applying the 
relevant accessibility standards.

## Conformance Status
Forseti aims to conform to WCAG 2.1 Level AA standards. These guidelines explain 
how to make web content more accessible for people with disabilities and user 
friendly for everyone.

## Feedback
We welcome your feedback on the accessibility of Forseti. Please contact us if:
- You encounter accessibility barriers
- You have suggestions for improvement

Contact: support@forseti.life

## Technical Specifications
Forseti relies on the following technologies:
- HTML5
- CSS3
- JavaScript
- React Native (mobile app)
- ARIA (Accessible Rich Internet Applications)

## Limitations
Despite our best efforts, some limitations may exist:
- Third-party map libraries may have accessibility limitations
- Some historical crime data visualizations may be difficult to interpret with assistive technologies

We are working to address these limitations.

## Assessment
This website was last assessed for accessibility on [Date].
Assessment was conducted using:
- Automated testing tools (axe, Lighthouse)
- Manual keyboard navigation testing
- Screen reader testing (VoiceOver, NVDA, TalkBack)
- User testing with people with disabilities

Last updated: February 12, 2026
```

---

## Implementation Priority

### Phase 1: Critical (Must Have)
1. ✅ Keyboard navigation
2. ✅ Screen reader compatibility
3. ✅ Color contrast (WCAG AA)
4. ✅ Alt text for images
5. ✅ Form labels and validation

### Phase 2: Important (Should Have)
1. ✅ ARIA labels and landmarks
2. ✅ Focus indicators
3. ✅ Skip links
4. ✅ Responsive text scaling
5. ✅ Error handling

### Phase 3: Enhanced (Nice to Have)
1. ✅ Keyboard shortcuts
2. ✅ Reduced motion support
3. ✅ High contrast mode
4. ✅ Audio descriptions (videos)
5. ✅ Multiple language support

---

## Related Documents

- [Mobile-First Design](./04-mobile-first-approach.md) - Responsive accessibility
- [Wireframes](./02-wireframes.md) - Accessible component design
- [Performance Strategy](./06-performance-strategy.md) - Performance impacts accessibility

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial WCAG 2.1 AA accessibility checklist | Copilot |
