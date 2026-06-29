# Site Map & Navigation Hierarchy

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete

---

## Overview

This document outlines the complete information architecture and navigation structure for Forseti/AmISafe across both mobile and web platforms. The design prioritizes immediate access to critical safety information while maintaining a clear, intuitive hierarchy.

---

## Mobile App Navigation

### Primary Navigation (Bottom Tab Bar)

The mobile app uses a **4-tab bottom navigation** pattern for primary wayfinding:

```
┌────────────────────────────────────────────────────┐
│                   Header Bar                        │
│  ┌─────────┐                            ⚙️  🚪    │
│  │ Forseti │    [Dynamic Title]                    │
│  └─────────┘                                        │
├────────────────────────────────────────────────────┤
│                                                     │
│                                                     │
│              Main Content Area                      │
│                                                     │
│                                                     │
│                                                     │
├────────────────────────────────────────────────────┤
│   🏠        🗺️         🛡️         👤              │
│  Home      Map      Safety    Profile              │
└────────────────────────────────────────────────────┘
```

### Navigation Structure

```
Mobile App (Root)
│
├── 🏠 Home (Tab 1)
│   ├── Dashboard Overview
│   │   ├── Current Location Safety Status
│   │   ├── Recent Alerts Summary
│   │   ├── Quick Actions
│   │   └── Safety Tips
│   │
│   └── Quick Actions
│       ├── View Nearby Crime
│       ├── Check Route Safety
│       ├── Enable Monitoring
│       └── View History
│
├── 🗺️ Map (Tab 2)
│   ├── Interactive Crime Map
│   │   ├── H3 Hexagon Layer
│   │   ├── Crime Markers
│   │   ├── Heat Map Overlay
│   │   └── User Location
│   │
│   ├── Map Controls
│   │   ├── Zoom In/Out
│   │   ├── Center on User
│   │   ├── Toggle Layers
│   │   └── Time Range Filter
│   │
│   └── Crime Detail View (Modal)
│       ├── Crime Type
│       ├── Date & Time
│       ├── Location Details
│       └── Risk Assessment
│
├── 🛡️ Safety (Tab 3)
│   ├── Background Monitoring
│   │   ├── Monitoring Status (On/Off)
│   │   ├── Current Risk Level
│   │   ├── Alert Settings
│   │   └── Monitoring History
│   │
│   ├── Alert Configuration
│   │   ├── Risk Threshold (Z-score)
│   │   ├── Alert Cooldown Period
│   │   ├── Notification Preferences
│   │   └── Quiet Hours
│   │
│   ├── Safety Resources
│   │   ├── Emergency Contacts
│   │   ├── Safety Tips
│   │   ├── Report Incident
│   │   └── Community Resources
│   │
│   └── Alert History
│       ├── Past Alerts List
│       ├── Alert Details
│       └── Map View of Alert
│
└── 👤 Profile (Tab 4)
    ├── User Account
    │   ├── Profile Information
    │   ├── Email/Username
    │   ├── Account Created Date
    │   └── Edit Profile
    │
    ├── Settings
    │   ├── App Preferences
    │   ├── Notification Settings
    │   ├── Privacy Settings
    │   └── Data & Storage
    │
    ├── About & Help
    │   ├── How It Works
    │   ├── About Forseti
    │   ├── FAQ
    │   ├── Contact Support
    │   └── Privacy Policy
    │
    ├── Debug Tools (Development)
    │   ├── System Information
    │   ├── Feature Flags
    │   ├── Log Viewer
    │   └── Test Notifications
    │
    └── Account Actions
        ├── Change Password
        ├── Logout
        └── Delete Account
```

### Authentication Flow

```
App Launch
│
├── First Time User
│   ├── Splash Screen
│   ├── Welcome/Tutorial
│   ├── Registration
│   │   ├── Email & Password
│   │   ├── Account Creation
│   │   └── Email Verification
│   │
│   ├── Permissions Request
│   │   ├── Location (Required)
│   │   ├── Notifications (Recommended)
│   │   └── Background Location (Optional)
│   │
│   └── Onboarding
│       ├── How It Works
│       ├── Safety Features Tour
│       └── Start Using App
│
└── Returning User
    ├── Auto-Login (AsyncStorage)
    ├── Session Validation
    └── Navigate to Home
```

### Modal/Overlay Screens

```
Modals (Overlay Navigation)
│
├── Crime Detail Modal
│   ├── Crime Information
│   ├── Location Map
│   └── Related Crimes
│
├── Alert Detail Modal
│   ├── Alert Information
│   ├── Risk Assessment
│   └── Actions (View on Map)
│
├── Settings Modal
│   ├── App Settings
│   ├── Notification Preferences
│   └── Privacy Controls
│
├── How It Works Modal
│   ├── Feature Explanation
│   ├── Video/Tutorial
│   └── FAQ Links
│
└── Feedback Modal
    ├── Report Issue
    ├── Feature Request
    └── General Feedback
```

---

## Web Platform Navigation

### Header Navigation (Global)

```
┌────────────────────────────────────────────────────┐
│  ┌─────────┐                                        │
│  │ Forseti │  Safety Map  │  About  │  How It Works │  Login/Profile  │
│  └─────────┘                                        │
└────────────────────────────────────────────────────┘
```

### Web Site Structure

```
Website (forseti.life)
│
├── / (Home)
│   ├── Hero Section
│   │   ├── Value Proposition
│   │   ├── CTA: "Get Started" / "View Map"
│   │   └── Key Features Preview
│   │
│   ├── Features Section
│   │   ├── Real-Time Crime Data
│   │   ├── Risk Assessment
│   │   ├── Background Monitoring
│   │   └── Community Safety
│   │
│   ├── How It Works
│   │   ├── Step 1: Enable Location
│   │   ├── Step 2: View Safety Data
│   │   ├── Step 3: Stay Informed
│   │   └── Video/Demo
│   │
│   ├── Testimonials
│   │   ├── User Reviews
│   │   └── Success Stories
│   │
│   └── CTA Section
│       ├── Download Mobile App
│       └── View Live Map
│
├── /safety-map (Interactive Map)
│   ├── Full-Screen Map
│   │   ├── H3 Hexagon Visualization
│   │   ├── Search Location
│   │   ├── Filter Controls
│   │   └── Legend
│   │
│   ├── Sidebar Panel
│   │   ├── Location Search
│   │   ├── Risk Summary
│   │   ├── Crime Statistics
│   │   └── Time Range Filter
│   │
│   └── Crime Detail Panel
│       ├── Selected Crime Info
│       ├── Related Crimes
│       └── Share/Export
│
├── /about
│   ├── Mission & Vision
│   ├── How We Work
│   ├── Data Sources
│   ├── Privacy & Security
│   ├── Team
│   └── Contact
│
├── /how-it-works
│   ├── Overview
│   ├── Technology Explanation
│   │   ├── H3 Geolocation
│   │   ├── Z-Score Analysis
│   │   └── Risk Assessment
│   │
│   ├── Features Deep Dive
│   │   ├── Crime Visualization
│   │   ├── Background Monitoring
│   │   └── Alert System
│   │
│   ├── FAQ
│   └── Getting Started Guide
│
├── /privacy
│   ├── Privacy Policy
│   ├── Terms of Service
│   ├── Data Usage
│   └── Cookie Policy
│
├── /login
│   ├── Login Form
│   ├── Forgot Password
│   └── Register Link
│
├── /register
│   ├── Registration Form
│   ├── Email Verification
│   └── Login Link
│
└── /account (Authenticated)
    ├── Dashboard
    │   ├── Account Overview
    │   ├── Recent Activity
    │   └── Quick Actions
    │
    ├── Settings
    │   ├── Profile Settings
    │   ├── Notification Preferences
    │   └── Privacy Controls
    │
    └── Saved Locations
        ├── Monitored Areas
        ├── Favorite Locations
        └── Alert History
```

### Footer Navigation

```
Footer
│
├── Product
│   ├── Features
│   ├── Safety Map
│   ├── Mobile App
│   └── Pricing (Future)
│
├── Company
│   ├── About Us
│   ├── How It Works
│   ├── Blog (Future)
│   └── Contact
│
├── Legal
│   ├── Privacy Policy
│   ├── Terms of Service
│   └── Cookie Policy
│
├── Support
│   ├── FAQ
│   ├── Help Center
│   └── Contact Support
│
└── Social
    ├── Twitter
    ├── Facebook
    └── LinkedIn
```

---

## Navigation Principles

### 1. **Flat Hierarchy**
- Maximum 3 levels deep on mobile
- Minimize navigation steps to critical features
- Primary actions accessible within 2 taps

### 2. **Persistent Navigation**
- Bottom tab bar always visible (mobile)
- Header navigation always accessible (web)
- Back button follows platform conventions

### 3. **Context Awareness**
- Show relevant options based on user state
- Hide unavailable features gracefully
- Provide clear feedback on current location

### 4. **Progressive Disclosure**
- Show most important information first
- Use modals for secondary actions
- Drill-down for detailed information

### 5. **Consistency**
- Same terminology across platforms
- Consistent icon usage
- Predictable interaction patterns

---

## Navigation Patterns

### Tab Navigation (Mobile)

**When to Use:**
- Primary app navigation
- 3-5 top-level sections
- Frequent switching between sections

**Best Practices:**
- Icons + labels (better accessibility)
- Active state clearly indicated
- Consistent order across sessions

### Stack Navigation (Mobile)

**When to Use:**
- Hierarchical content flow
- Multi-step processes
- Detail views from list screens

**Best Practices:**
- Back button in header
- Clear screen titles
- Breadcrumb for deep stacks (optional)

### Modal Navigation (Mobile & Web)

**When to Use:**
- Focused task completion
- Temporary content overlay
- Settings and configuration

**Best Practices:**
- Clear close/dismiss action
- Don't nest modals
- Save state before closing

### Deep Linking

**Supported Routes:**
```
Mobile:
- forseti://home
- forseti://map?lat={lat}&lng={lng}&zoom={zoom}
- forseti://safety
- forseti://profile
- forseti://crime/{crimeId}
- forseti://alert/{alertId}

Web:
- https://forseti.life/
- https://forseti.life/safety-map?lat={lat}&lng={lng}
- https://forseti.life/about
- https://forseti.life/how-it-works
```

---

## Information Architecture Priorities

### Priority 1 (Most Critical)
1. Current location safety status
2. Crime map visualization
3. Alert notifications
4. Emergency contacts

### Priority 2 (Important)
1. Historical crime data
2. Risk threshold settings
3. User profile & preferences
4. How it works / Help

### Priority 3 (Nice to Have)
1. Advanced filtering
2. Crime statistics
3. Social features
4. Community resources

---

## Mobile vs. Web Differences

| Feature | Mobile | Web |
|---------|--------|-----|
| **Primary Nav** | Bottom tabs | Header menu |
| **Map Interaction** | Touch gestures | Mouse + keyboard |
| **Screen Real Estate** | Limited, single column | Large, multi-column |
| **Offline Support** | Full offline capability | Limited offline |
| **Push Notifications** | Native push | Web push (limited) |
| **Location Tracking** | Background tracking | Session-only |
| **Deep Linking** | Full app linking | URL-based navigation |

---

## Navigation Accessibility

### Keyboard Navigation (Web)
- **Tab**: Navigate forward through interactive elements
- **Shift+Tab**: Navigate backward
- **Enter/Space**: Activate buttons/links
- **Arrow Keys**: Navigate within groups (tabs, menus)
- **Esc**: Close modals/dialogs

### Screen Reader Support
- Semantic HTML structure
- ARIA labels for icons
- Announced page changes
- Skip to main content link

### Touch Targets (Mobile)
- Minimum 44x44pt tap targets
- Adequate spacing between elements
- Clear visual feedback on tap
- No double-tap requirements

---

## Navigation Performance

### Mobile App
- Tab switching: < 100ms
- Screen transitions: < 300ms (animated)
- Deep link handling: < 500ms
- Back navigation: Instant (cached state)

### Web
- Page load: < 2 seconds
- Navigation clicks: < 100ms
- SPA transitions: < 300ms
- Browser back: Instant (history API)

---

## Future Navigation Enhancements

### Planned Improvements
- 🔮 Search functionality (global search)
- 🔮 Saved places/favorites quick access
- 🔮 Recent locations history
- 🔮 Voice navigation commands
- 🔮 Gesture-based navigation (mobile)
- 🔮 Progressive Web App (PWA) support

### Under Consideration
- 💡 AI Chat assistant (navigation help)
- 💡 Contextual quick actions
- 💡 Adaptive navigation (learns usage patterns)
- 💡 Multi-language support
- 💡 Customizable home screen

---

## Implementation Notes

### Current Implementation
- **Mobile**: React Navigation v6.x
- **Web**: Drupal 11 menu system + Radix theme
- **Icons**: React Native Vector Icons (MaterialCommunityIcons)
- **State**: AsyncStorage (mobile), Drupal session (web)

### Technical Considerations
- Tab state preserved during app lifecycle
- Deep links handle app not running
- Navigation analytics tracking
- Performance monitoring on route changes

---

## Related Documents

- [Wireframes](./02-wireframes.md) - Visual navigation layouts
- [User Flows](./03-user-flows.md) - Navigation user journeys
- [Mobile-First Design](./04-mobile-first-approach.md) - Responsive navigation

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial site map and navigation hierarchy | Copilot |
