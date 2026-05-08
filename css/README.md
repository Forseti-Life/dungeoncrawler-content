# CSS Directory Documentation

This directory contains stylesheets for the DungeonCrawler content module.

## Files

### Stylesheets
- **character-sheet.css** - Character sheet and character list page styles (903 lines)
- **character-creation.css** - Character creation wizard styles
- **character-steps.css** - Individual step styles for character creation
- **dungeoncrawler-content.css** - Base module styles
- **game-cards.css** - Card-based UI components
- **hexmap.css** - Hex map visualization styles
- **credits.css** - Credits page styles

### Documentation (Character Sheet CSS)
- **CSS_VARIABLES_GUIDE.md** - Quick reference for CSS custom properties
- **REFACTORING_SUMMARY.md** - Detailed analysis of DCC-0034 refactoring
- **CHARACTER_SHEET_CSS_TESTING.md** - Comprehensive testing procedures

## Recent Changes

### DCC-0034: Character Sheet CSS Refactoring (2026-02-17)

The character-sheet.css file was refactored to improve maintainability and accessibility:

**What Changed:**
- Added 16 CSS custom properties for commonly repeated values
- Replaced 30+ hardcoded values with variable references
- Added keyboard focus states for accessibility (WCAG 2.1 AA)
- Improved code organization with better comments

**What Didn't Change:**
- Visual design remains 100% identical
- No functional changes
- No breaking changes
- All templates and JavaScript work unchanged

**For Developers:**
- See `CSS_VARIABLES_GUIDE.md` for quick reference on using the new variables
- See `REFACTORING_SUMMARY.md` for detailed change rationale
- See `CHARACTER_SHEET_CSS_TESTING.md` for testing procedures

## CSS Custom Properties

The character-sheet.css file now uses a comprehensive set of CSS custom properties for maintainability:

### Border Radius
```css
--dc-radius-sm: 4px;    /* Small elements */
--dc-radius-md: 8px;    /* Medium elements */
--dc-radius-lg: 12px;   /* Large elements */
```

### Spacing
```css
--dc-space-xs: 0.25rem;
--dc-space-sm: 0.5rem;
--dc-space-md: 0.75rem;
--dc-space-lg: 1rem;
--dc-space-xl: 1.5rem;
```

### Alpha Colors
```css
--dc-border-alpha-light: rgba(61, 61, 85, 0.3);
--dc-border-alpha-medium: rgba(61, 61, 85, 0.5);
--dc-gold-glow: rgba(245, 158, 11, 0.15);
--dc-gold-focus: rgba(245, 158, 11, 0.2);
```

See `CSS_VARIABLES_GUIDE.md` for complete documentation and usage examples.

## Color Palette

All stylesheets use the DungeonCrawler dark theme palette:

### Backgrounds
- `--dc-stone`: #2d2d3d
- `--dc-deep-cavern`: #1a1a2e
- `--dc-abyss`: #0f0f1a

### Accents
- `--dc-torch-gold`: #f59e0b (primary)
- `--dc-mystic-purple`: #7c3aed (secondary)
- `--dc-blood-red`: #dc2626 (danger/alert)

### Text
- `--dc-text`: #e2e8f0 (primary text)
- `--dc-text-muted`: #94a3b8 (secondary text)

### UI Elements
- `--dc-border`: #3d3d55
- `--dc-success`: #22c55e
- `--dc-danger`: #ef4444

## Library Loading

CSS files are loaded via `dungeoncrawler_content.libraries.yml`:

```yaml
character-sheet:
  css:
    component:
      css/character-sheet.css: {}
  js:
    js/character-sheet.js: {}
  dependencies:
    - core/drupal
    - core/once
```

## Development Guidelines

### Making CSS Changes

1. **Edit the CSS file** with your changes
2. **Clear Drupal cache** to see changes:
   ```bash
   ./vendor/bin/drush cr
   ```
3. **Test thoroughly** across browsers and screen sizes
4. **Update documentation** if adding new patterns or variables

### Adding New CSS Variables

1. Add to `:root` section with clear comment
2. Group with related variables
3. Replace existing hardcoded values
4. Update `CSS_VARIABLES_GUIDE.md`
5. Clear cache and test

### Browser Support

All modern browsers are supported:
- Chrome 49+
- Firefox 31+
- Safari 9.1+
- Edge 15+

CSS custom properties are NOT supported in IE11.

## Testing

### After CSS Changes

1. Clear Drupal cache: `./vendor/bin/drush cr`
2. Test affected pages visually
3. Test keyboard navigation (Tab key)
4. Test on mobile/tablet viewports
5. Verify in multiple browsers

### Comprehensive Testing

For major changes (like the DCC-0034 refactoring), follow:
- `CHARACTER_SHEET_CSS_TESTING.md` for character sheet changes
- Similar procedures for other stylesheets

## Accessibility

### Focus States

Interactive elements should have visible focus indicators:

```css
.my-element:focus {
  outline: 2px solid var(--dc-torch-gold);
  outline-offset: 2px;
}
```

### Color Contrast

Ensure text meets WCAG AA standards:
- Normal text: 4.5:1 contrast ratio minimum
- Large text (18pt+): 3:1 contrast ratio minimum

### Keyboard Navigation

All interactive elements must be keyboard accessible:
- Focusable via Tab key
- Activatable via Enter/Space
- Clear focus indicators

## Performance

### Best Practices

- Use CSS custom properties for repeated values (reduces file size)
- Avoid overly specific selectors (improves render performance)
- Group related rules together (improves cache efficiency)
- Minify for production (handled by build process)

### Loading Strategy

CSS is loaded as component libraries, meaning:
- Only loaded when needed
- Can be aggregated and minified by Drupal
- Cached efficiently by browsers

## Maintenance

### Code Review Checklist

Before committing CSS changes:
- [ ] Visual design is correct
- [ ] Responsive layouts work
- [ ] Focus states are visible
- [ ] Browser compatibility maintained
- [ ] Variables used instead of hardcoded values
- [ ] Comments explain complex rules
- [ ] Documentation updated

### Common Issues

**Styles not updating?**
```bash
./vendor/bin/drush cr
```

**Variables not working?**
- Check browser supports CSS custom properties
- Verify variable is defined in `:root`
- Check for typos in variable names

**Focus states not visible?**
- Ensure `:focus` styles are defined
- Verify outline isn't set to `none`
- Test with keyboard (Tab key)

## Resources

- [CSS Custom Properties (MDN)](https://developer.mozilla.org/en-US/docs/Web/CSS/--*)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [Drupal CSS Coding Standards](https://www.drupal.org/docs/develop/standards/css)

## Support

For questions or issues:
1. Check relevant documentation file in this directory
2. Review module README: `../README.md`
3. Check Drupal logs: `/admin/reports/dblog`
4. Create an issue with reproduction steps

## Related Issues

- DCC-0034: Character Sheet CSS Refactoring (2026-02-17) ✅ Complete

---

**Last Updated**: 2026-02-17  
**Maintained By**: DungeonCrawler Content Module Team
