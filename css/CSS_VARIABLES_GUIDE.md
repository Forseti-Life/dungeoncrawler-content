# Character Sheet CSS - Quick Reference

## CSS Custom Properties Quick Guide

### Border Radius
```css
--dc-radius-sm: 4px;    /* Small: badges, tags, sense labels */
--dc-radius-md: 8px;    /* Medium: buttons, inputs, ability boxes */
--dc-radius-lg: 12px;   /* Large: cards, sections, containers */
```

### Spacing
```css
--dc-space-xs: 0.25rem; /* 4px - Minimal gaps, tight padding */
--dc-space-sm: 0.5rem;  /* 8px - Small gaps, compact padding */
--dc-space-md: 0.75rem; /* 12px - Medium gaps, standard padding */
--dc-space-lg: 1rem;    /* 16px - Large gaps, generous padding */
--dc-space-xl: 1.5rem;  /* 24px - Extra large gaps, spacious padding */
```

### Alpha Colors
```css
--dc-border-alpha-light: rgba(61, 61, 85, 0.3);   /* Subtle dividers */
--dc-border-alpha-medium: rgba(61, 61, 85, 0.5);  /* Standard dividers */
--dc-gold-glow: rgba(245, 158, 11, 0.15);         /* Hover glow effect */
--dc-gold-focus: rgba(245, 158, 11, 0.2);         /* Focus ring shadow */
```

## Usage Examples

### Rounded Corners
```css
/* Small rounded corners for compact elements */
.my-badge {
  border-radius: var(--dc-radius-sm);
}

/* Medium rounded corners for interactive elements */
.my-button {
  border-radius: var(--dc-radius-md);
}

/* Large rounded corners for card-like elements */
.my-card {
  border-radius: var(--dc-radius-lg);
}
```

### Consistent Spacing
```css
/* Small gap between related items */
.my-list {
  gap: var(--dc-space-sm);
}

/* Standard padding for containers */
.my-container {
  padding: var(--dc-space-lg);
}

/* Generous margin for major sections */
.my-section {
  margin-bottom: var(--dc-space-xl);
}
```

### Transparent Borders
```css
/* Subtle separator line */
.my-item {
  border-bottom: 1px solid var(--dc-border-alpha-light);
}

/* More visible separator */
.my-divider {
  border-bottom: 1px solid var(--dc-border-alpha-medium);
}
```

### Focus States
```css
/* Standard focus indicator for interactive elements */
.my-interactive:focus {
  outline: 2px solid var(--dc-torch-gold);
  outline-offset: 2px;
}

/* Focus with subtle glow */
.my-input:focus {
  border-color: var(--dc-torch-gold);
  box-shadow: 0 0 0 2px var(--dc-gold-focus);
}
```

## Common Patterns

### Card Component
```css
.my-card {
  background: var(--dc-deep-cavern);
  border: 1px solid var(--dc-border);
  border-radius: var(--dc-radius-lg);
  padding: var(--dc-space-xl);
  gap: var(--dc-space-md);
}

.my-card:hover {
  border-color: var(--dc-torch-gold);
  box-shadow: 0 4px 20px var(--dc-gold-glow);
}
```

### Form Input
```css
.my-input {
  background: var(--dc-abyss);
  border: 1px solid var(--dc-border);
  border-radius: var(--dc-radius-md);
  padding: var(--dc-space-sm) var(--dc-space-md);
}

.my-input:focus {
  border-color: var(--dc-torch-gold);
  outline: none;
  box-shadow: 0 0 0 2px var(--dc-gold-focus);
}
```

### List with Dividers
```css
.my-list-item {
  padding: var(--dc-space-sm) 0;
  border-bottom: 1px solid var(--dc-border-alpha-medium);
}

.my-list-item:last-child {
  border-bottom: none;
}
```

### Tag/Badge
```css
.my-tag {
  display: inline-block;
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: var(--dc-radius-sm);
  background: var(--dc-mystic-purple);
  color: #fff;
}
```

## When NOT to Use Variables

### Percentage Values
```css
/* Correct: Keep 50% for circular elements */
.circle {
  border-radius: 50%;
}
```

### Unique One-Off Values
```css
/* Acceptable: 6px is used only once for currency coins */
.currency-coin {
  border-radius: 6px;
}
```

### Asymmetric Values
```css
/* Correct: Asymmetric borders for specific design patterns */
.left-accent {
  border-radius: 0 var(--dc-radius-sm) var(--dc-radius-sm) 0;
}
```

### Zero Values
```css
/* Correct: Zero is zero, no variable needed */
.my-element {
  margin: 0;
  padding: 0;
}
```

## Best Practices

### Do ✅
- Use variables for repeated values (3+ occurrences)
- Use semantic variable names that describe purpose
- Group related variables with comments
- Document special cases that don't use variables

### Don't ❌
- Don't create variables for single-use values
- Don't use variables for semantically important values (like 50% for circles)
- Don't mix variables and hardcoded values for the same concept
- Don't remove variables when updating - deprecate first

## Browser Support

All modern browsers support CSS custom properties:
- Chrome 49+ ✅
- Firefox 31+ ✅
- Safari 9.1+ ✅
- Edge 15+ ✅

IE11 does NOT support CSS custom properties. For IE11 support, use a PostCSS plugin to transpile variables.

## Debugging Tips

### View Computed Values
In browser DevTools:
1. Inspect element
2. Check "Computed" tab
3. See resolved variable values

### Override in DevTools
```css
/* Temporarily override for testing */
:root {
  --dc-radius-lg: 20px !important;
}
```

### Find All Variable Usage
```bash
# Search for all uses of a variable
grep -n "var(--dc-radius-lg)" character-sheet.css
```

## Migration Guide

### Adding a New Variable
1. Add to `:root` with clear comment
2. Group with related variables
3. Replace hardcoded values throughout file
4. Document in this guide

### Modifying a Variable
1. Check all usages won't break: `grep -n "var(--your-variable)" *.css`
2. Test in multiple contexts
3. Update documentation if behavior changes

### Removing a Variable
1. Check for usages: `grep -n "var(--your-variable)" *.css`
2. If used, deprecate first (comment as deprecated)
3. Migrate all usages to new pattern
4. Remove after confirming no usages

## Need Help?

- See: `REFACTORING_SUMMARY.md` for detailed changes
- See: `CHARACTER_SHEET_CSS_TESTING.md` for testing procedures
- Issue: DCC-0034
- Date: 2026-02-17
