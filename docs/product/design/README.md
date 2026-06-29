# Forseti/AmISafe - Navigation & User Experience Design

**Last Updated**: February 12, 2026  
**Status**: 🎨 Design Documentation  
**Phase**: Beta Testing (Phase 4/5)

---

## Overview

This directory contains comprehensive design documentation for Forseti/AmISafe's navigation structure and user experience. The design follows a mobile-first approach, prioritizing accessibility (WCAG 2.1 AA compliance) and performance optimization.

### Project Context

**Forseti/AmISafe** is an AI-powered community safety platform that provides:
- Real-time hyperlocal crime visualization using H3 hexagonal geolocation
- Risk assessment based on Z-score statistical analysis  
- Background location monitoring with push notifications
- Interactive safety maps showing crime risk heatmaps

---

## Design Documentation Structure

| Document | Purpose | Status |
|----------|---------|--------|
| **[Site Map & Navigation](./01-sitemap-navigation.md)** | Complete navigation hierarchy and information architecture | ✅ Complete |
| **[Wireframes](./02-wireframes.md)** | Visual layouts for key pages and screens | ✅ Complete |
| **[User Flows](./03-user-flows.md)** | Detailed user journey diagrams | ✅ Complete |
| **[Mobile-First Design](./04-mobile-first-approach.md)** | Responsive design strategy and breakpoints | ✅ Complete |
| **[Accessibility Checklist](./05-accessibility-checklist.md)** | WCAG 2.1 AA compliance guidelines | ✅ Complete |
| **[Performance Strategy](./06-performance-strategy.md)** | Performance optimization approach | ✅ Complete |

---

## Design Principles

### 1. **Safety First**
- Clear, immediate communication of risk levels
- High-contrast color coding for risk assessment
- Accessible to all users regardless of ability

### 2. **Mobile-First**
- Optimized for on-the-go usage
- Touch-friendly interfaces (minimum 44x44pt tap targets)
- Responsive from 320px to 4K displays

### 3. **Performance**
- Load critical safety data first
- Progressive enhancement
- Offline capability for essential features

### 4. **Accessibility**
- WCAG 2.1 AA compliance
- Screen reader support
- Color-blind friendly palettes
- Keyboard navigation support

### 5. **Consistency**
- Unified design system across mobile and web
- Predictable navigation patterns
- Consistent feedback mechanisms

---

## Design System

### Brand Colors

```
Primary:
- Forseti Cyan: #00d4ff
- Dark Navy: #16213e
- Accent Blue: #1e3a8a

Safety Status Colors:
- Safe (Low): #22c55e (Green)
- Medium: #eab308 (Yellow)  
- Elevated: #f97316 (Orange)
- High Risk: #ef4444 (Red)

Neutrals:
- Background: #0a0e1a
- Surface: #1a1f35
- Text Primary: #ffffff
- Text Secondary: #94a3b8
```

### Typography

```
Headings:
- H1: 32px/40px, Bold
- H2: 24px/32px, Bold
- H3: 20px/28px, SemiBold
- H4: 18px/24px, SemiBold

Body:
- Large: 18px/28px, Regular
- Default: 16px/24px, Regular
- Small: 14px/20px, Regular
- Caption: 12px/16px, Regular
```

### Spacing Scale

```
xs: 4px
sm: 8px
md: 16px
lg: 24px
xl: 32px
2xl: 48px
3xl: 64px
```

---

## Key User Personas

### 1. **Sarah - Urban Commuter**
- **Age**: 28
- **Occupation**: Marketing Manager
- **Primary Use**: Check route safety during daily commute
- **Key Features**: Crime map, background monitoring, custom alerts

### 2. **Marcus - Parent**
- **Age**: 42
- **Occupation**: High School Teacher
- **Primary Use**: Monitor children's neighborhood safety
- **Key Features**: Area monitoring, historical data, alert settings

### 3. **Jessica - Real Estate Professional**
- **Age**: 35
- **Occupation**: Real Estate Agent
- **Primary Use**: Research neighborhood safety for clients
- **Key Features**: Area comparisons, detailed crime data, export/share

---

## Platform Coverage

### Mobile (Primary Platform)
- **Technology**: React Native 0.72.6 + TypeScript
- **Platforms**: iOS 12+, Android 8.0+
- **Screen Sizes**: 320px - 428px (phones), 768px - 1024px (tablets)

### Web (Secondary Platform)
- **Technology**: Drupal 11 + Radix theme
- **Browsers**: Chrome, Firefox, Safari, Edge (last 2 versions)
- **Screen Sizes**: 320px - 4K (responsive)

---

## Navigation Structure Overview

### Mobile App (Bottom Tab Navigation)

```
┌─────────────────────────────────────┐
│           Header (Fixed)             │
├─────────────────────────────────────┤
│                                      │
│         Screen Content               │
│                                      │
├─────────────────────────────────────┤
│  🏠 Home  │  🗺️ Map  │  🛡️ Safety  │  👤 Profile  │
└─────────────────────────────────────┘
```

### Web Header Navigation

```
┌─────────────────────────────────────┐
│  Forseti Logo  │  Map  About  Login  │
└─────────────────────────────────────┘
```

---

## Implementation Status

### Current (Phase 4 - Beta Testing)
✅ Mobile app with 4-tab navigation  
✅ Crime map with H3 visualization  
✅ Background location monitoring  
✅ User authentication  
✅ Dark theme branding  
⏸️ AI Chat (temporarily disabled)

### Planned Improvements
⏳ Enhanced onboarding flow  
⏳ Improved loading states  
⏳ Better error messaging  
⏳ Advanced filtering options  
⏳ Social features & sharing

---

## Design Tools & Resources

### Design Software
- **Wireframing**: ASCII diagrams (embedded in markdown)
- **User Flows**: Mermaid diagrams (version controlled)
- **Color Palette**: Documented in design system

### Development Resources
- **Design Tokens**: `/forseti-mobile/src/utils/theme.ts`
- **Components**: `/forseti-mobile/src/components/`
- **Theme Config**: Radix theme + custom Forseti styles

---

## Accessibility Commitment

Forseti is committed to providing equal access to safety information for all users:

- ✅ Screen reader compatibility (VoiceOver, TalkBack)
- ✅ Keyboard navigation support
- ✅ Color contrast ratios meeting WCAG 2.1 AA (4.5:1 minimum)
- ✅ Alternative text for all meaningful images
- ✅ Clear focus indicators
- ✅ Semantic HTML structure
- ✅ Support for text resizing up to 200%

---

## Performance Targets

### Mobile App
- **App Launch**: < 2 seconds (cold start)
- **Map Load**: < 1 second (initial render)
- **Navigation**: < 100ms (between tabs)
- **Data Sync**: < 3 seconds (background updates)

### Web
- **First Contentful Paint**: < 1.5 seconds
- **Largest Contentful Paint**: < 2.5 seconds
- **Time to Interactive**: < 3.5 seconds
- **Cumulative Layout Shift**: < 0.1

---

## Design Review Process

### Design Updates
1. Create design document in this directory
2. Link to related product requirements
3. Review with product team
4. Validate with target users
5. Update implementation backlog

### Implementation Validation
1. Compare implementation to design specs
2. Test on target devices/browsers
3. Validate accessibility compliance
4. Measure performance metrics
5. Gather user feedback

---

## Getting Started

### For Designers
1. Review existing design system (`/forseti-mobile/src/utils/`)
2. Understand user personas and journeys
3. Follow mobile-first approach
4. Ensure WCAG 2.1 AA compliance
5. Document design decisions

### For Developers
1. Review wireframes and user flows
2. Check design system for tokens/components
3. Implement mobile-first responsive design
4. Test accessibility with screen readers
5. Validate performance metrics

---

## Contributing

When contributing design documentation:

1. **Use Markdown**: All design docs in `.md` format
2. **Include Diagrams**: Use ASCII art or Mermaid for flows
3. **Version Control**: Document design evolution
4. **Link References**: Connect to related product docs
5. **Validate Accessibility**: Check against WCAG 2.1 AA

---

## Related Documentation

- [Product Documentation](../README.md) - Overall product strategy
- [MVP Definition](../mvp/mvp-definition.md) - Current feature scope
- [User Journey](../user-journey/sarah-urban-commuter.md) - Primary persona
- [Architecture](../../ARCHITECTURE.md) - Technical implementation
- [Mobile App README](../../../forseti-mobile/README.md) - Development guide

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial design documentation created | Copilot |

---

## Contact

For questions about design decisions or to contribute:
- Review existing documentation first
- Reference specific design principles
- Consider user impact and accessibility
- Align with mobile-first approach
