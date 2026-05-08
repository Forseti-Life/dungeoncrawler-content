# Character Sheet CSS Testing Guide

## Overview
This document provides testing instructions for validating the refactored `character-sheet.css` file (DCC-0034).

## Changes Made
1. **Added CSS Custom Properties**: 16 new variables for better maintainability
   - Border radius: `--dc-radius-sm`, `--dc-radius-md`, `--dc-radius-lg`
   - Spacing: `--dc-space-xs` through `--dc-space-xl`
   - Alpha colors: `--dc-border-alpha-light`, `--dc-border-alpha-medium`, `--dc-gold-glow`, `--dc-gold-focus`

2. **Replaced Hardcoded Values**: 30+ instances of hardcoded values replaced with variables

3. **Accessibility Improvements**: Added keyboard focus states for interactive elements
   - `.dc-btn:focus` - Button focus state
   - `.dc-character-card:focus` - Character card focus state

4. **Better Organization**: Improved comments and grouping in `:root` section

## Testing Checklist

### Prerequisites
```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
./vendor/bin/drush cr  # Clear Drupal cache after CSS changes
```

### Visual Testing

#### 1. Character List Page (`/characters`)
- [ ] Visit `/characters` page
- [ ] Verify character cards display correctly
  - [ ] Border radius is consistent (should be 12px rounded corners)
  - [ ] Hover effects work (gold border, subtle lift, glow)
  - [ ] Character card spacing is consistent
  - [ ] Status badges display correctly (4px border radius)
- [ ] Test keyboard navigation
  - [ ] Tab to character cards
  - [ ] Verify focus outline appears (2px gold outline with 2px offset)
  - [ ] Press Enter to navigate to character sheet
- [ ] Test empty state (if no characters)
  - [ ] Empty state card has dashed border
  - [ ] Border radius is correct (12px)

#### 2. Character Sheet Page (`/characters/{id}`)
- [ ] Visit a character sheet page
- [ ] Verify header section
  - [ ] Portrait has rounded corners (12px)
  - [ ] Vital boxes (HP, AC) have correct styling (8px radius)
  - [ ] Spacing between elements is consistent
- [ ] Verify ability scores section
  - [ ] All ability boxes have consistent styling (8px radius)
  - [ ] Spacing between abilities is uniform
- [ ] Verify sections
  - [ ] All main sections have consistent borders and radius (12px)
  - [ ] Subsections display correctly
  - [ ] Border colors and spacing are consistent
- [ ] Verify skills and saves
  - [ ] Border-bottom separators use alpha transparency
  - [ ] Proficiency badges display correctly (circular, 50% radius)
  - [ ] Sense tags have 4px radius
- [ ] Verify traits and feats
  - [ ] Trait tags have 4px radius
  - [ ] Feat items have asymmetric radius (left side flat, right side 4px rounded)
  - [ ] Left border accent is visible
- [ ] Verify equipment section
  - [ ] Currency coins display correctly (6px radius - unique value)
  - [ ] Equipment items have subtle bottom borders
- [ ] Verify backstory section
  - [ ] Background box has 8px radius
  - [ ] Left border accent (gold) is visible
  - [ ] Text is readable with good contrast

#### 3. Character Creation Form (`/characters/create`)
- [ ] Visit character creation page
- [ ] Verify form inputs
  - [ ] Text inputs have 8px rounded corners
  - [ ] Select dropdowns have 8px rounded corners
  - [ ] Textareas have 8px rounded corners
- [ ] Test focus states
  - [ ] Tab through form fields
  - [ ] Verify gold border appears on focus
  - [ ] Verify subtle gold glow (box-shadow) on focus
  - [ ] Ensure focus states are visible and clear

#### 4. Buttons (all pages)
- [ ] Verify button styling
  - [ ] All buttons have consistent 8px radius
  - [ ] Primary buttons (gold) display correctly
  - [ ] Secondary buttons (outlined) display correctly
  - [ ] Danger buttons (red) display correctly
  - [ ] Success buttons (green) display correctly
- [ ] Test button interactions
  - [ ] Hover states work correctly
  - [ ] Tab to buttons
  - [ ] Verify focus outline (2px gold with 2px offset)
  - [ ] Focus state is clearly visible

### Browser Compatibility Testing

Test in multiple browsers to ensure CSS custom properties work correctly:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

### Responsive Testing

Test on different screen sizes to ensure media queries work:
- [ ] Desktop (1920x1080)
- [ ] Tablet (768px and below)
- [ ] Mobile (smaller screens)
- [ ] Verify character grid collapses to single column on mobile
- [ ] Verify header layout stacks vertically on mobile

### Accessibility Testing

#### Keyboard Navigation
- [ ] Can navigate entire page with Tab key
- [ ] Can activate links/buttons with Enter/Space
- [ ] Focus indicators are clearly visible
- [ ] Focus order is logical

#### Screen Reader Testing (if available)
- [ ] Links and buttons are properly announced
- [ ] Form labels are associated with inputs
- [ ] Status badges are announced correctly

#### Color Contrast
- [ ] Text on dark backgrounds meets WCAG AA standards
- [ ] Button text is readable
- [ ] Disabled states are distinguishable but not through color alone

### Performance Testing

- [ ] Page loads quickly (CSS is not blocking)
- [ ] No visual layout shifts during page load
- [ ] Hover effects are smooth (60fps)
- [ ] Transitions work smoothly

### CSS Validation

```bash
# Optional: Use CSS validator if available
# npm install -g csslint
csslint character-sheet.css
```

Expected: No errors, only informational notices about CSS custom properties (which are valid CSS3)

## Regression Testing

### Before/After Comparison
Compare a screenshot of a page before and after the changes:
1. They should look identical
2. No visual differences expected
3. Only internal code structure changed

### Areas That Should NOT Change
- [ ] Colors remain exactly the same
- [ ] Layout remains exactly the same
- [ ] Spacing remains exactly the same
- [ ] No functional changes to JavaScript interactions

### New Features Added
- [ ] Focus states are now visible (previously missing)
- [ ] Better maintainability through CSS variables

## Known Special Cases

1. **50% border-radius**: Used for circular elements (proficiency badges) - intentionally not converted to variable
2. **6px border-radius**: Used only for currency coins - unique value between sm and md, left as-is
3. **Asymmetric border-radius**: Feat items use `0 var(--dc-radius-sm) var(--dc-radius-sm) 0` for right-only rounding

## Troubleshooting

### Issue: Styles don't update
**Solution**: Clear Drupal cache
```bash
./vendor/bin/drush cr
```

### Issue: Focus states too prominent
**Solution**: This is intentional for accessibility (WCAG 2.1 Success Criterion 2.4.7)

### Issue: Variables not working in older browsers
**Solution**: CSS custom properties require:
- Chrome 49+
- Firefox 31+
- Safari 9.1+
- Edge 15+

All modern browsers support these. If supporting IE11, a build step with PostCSS would be needed.

## Success Criteria

✅ All visual elements render identically to before refactoring  
✅ Focus states are now visible for keyboard navigation  
✅ No console errors in browser developer tools  
✅ Page performance is maintained or improved  
✅ Code is more maintainable with CSS variables  
✅ Accessibility is improved with focus indicators  

## Documentation Updates

After successful testing, update:
- [x] This testing document
- [ ] Module README.md (mention CSS refactoring in changelog)
- [ ] Add note about CSS custom property usage in ARCHITECTURE.md (if exists)

## Contact

For issues or questions about this refactoring:
- Issue: DCC-0034
- PR: [Link to PR]
- Date: 2026-02-17
