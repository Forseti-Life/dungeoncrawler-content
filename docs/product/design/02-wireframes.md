# Wireframes - Key Pages & Screens

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete

---

## Overview

This document provides ASCII wireframes for all key screens and pages in the Forseti/AmISafe application. These wireframes focus on layout, hierarchy, and functionality rather than visual design details.

---

## Mobile App Wireframes

### 1. Home Screen (Dashboard)

```
┌────────────────────────────────────┐
│  ← Forseti          🔔 ⚙️  🚪     │  ← Header Bar
├────────────────────────────────────┤
│                                     │
│  ┌──────────────────────────────┐ │
│  │  📍 Your Current Location     │ │  ← Location Card
│  │  1234 Market St, Philadelphia│ │
│  │                               │ │
│  │  Safety Status: 🟢 SAFE      │ │  ← Risk Status
│  │  Risk Level: Low (Z: 0.8)    │ │
│  │                               │ │
│  │  Last Updated: 2 min ago      │ │
│  └──────────────────────────────┘ │
│                                     │
│  Recent Alerts                      │  ← Section Header
│  ┌──────────────────────────────┐ │
│  │ 🟡 Medium Risk Alert          │ │
│  │ 15th & Chestnut              │ │  ← Alert Item
│  │ 5 minutes ago                │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 🟢 Area Safe                  │ │
│  │ Your neighborhood            │ │
│  │ 1 hour ago                   │ │
│  └──────────────────────────────┘ │
│                                     │
│  Quick Actions                      │  ← Section Header
│  ┌────────┐ ┌────────┐ ┌────────┐ │
│  │ 🗺️     │ │ 🛡️     │ │ 📊     │ │  ← Action Cards
│  │ View   │ │ Enable │ │ Check  │ │
│  │ Map    │ │Monitor │ │ Stats  │ │
│  └────────┘ └────────┘ └────────┘ │
│                                     │
│  Safety Tips                        │
│  • Stay aware of surroundings      │
│  • Trust your instincts             │
│  • Share location with trusted...  │
│                                     │
├────────────────────────────────────┤
│ 🏠 Home  🗺️ Map  🛡️ Safety 👤    │  ← Bottom Tabs
└────────────────────────────────────┘
```

**Key Elements:**
- Current location safety status (prominent)
- Real-time risk level with color coding
- Recent alerts timeline
- Quick access actions
- Safety tips and guidance

---

### 2. Crime Map Screen

```
┌────────────────────────────────────┐
│  ← Crime Map        🔍 🎯 ⚙️      │  ← Header
├────────────────────────────────────┤
│ ┌────────────────────────────────┐│
│ │                                 ││
│ │         🗺️ MAP CANVAS          ││  ← Interactive Map
│ │                                 ││
│ │   ⬢ ⬢ ⬢ ⬢  (H3 Hexagons)      ││  ← H3 Layers
│ │     ⬢ ⬢ ⬢ ⬢                    ││
│ │   ⬢ ⬢ 📍⬢ ⬢  (User Location)  ││
│ │     ⬢ ⬢ ⬢ ⬢                    ││
│ │   ⬢ ⬢ ⬢ ⬢                      ││
│ │                                 ││
│ │   Legend:                       ││
│ │   🟢 Safe  🟡 Medium  🔴 High  ││  ← Legend
│ │                                 ││
│ └────────────────────────────────┘│
│                                     │
│ ┌──────────┐ ┌──────────┐         │
│ │    ➕    │ │    📍    │         │  ← Map Controls
│ │  Zoom    │ │  Center  │         │
│ └──────────┘ └──────────┘         │
│                                     │
│ Filters: [Last 7 Days ▼] [All ▼]  │  ← Filter Bar
│                                     │
├────────────────────────────────────┤
│ 🏠 Home  🗺️ Map  🛡️ Safety 👤    │
└────────────────────────────────────┘
```

**Key Elements:**
- Full-screen interactive map
- H3 hexagon overlay with color-coded risk
- User location marker
- Zoom and centering controls
- Time range and crime type filters
- Clear legend for risk levels

---

### 3. Crime Detail Modal (Overlay on Map)

```
┌────────────────────────────────────┐
│                                     │
│         🗺️ MAP (Dimmed)            │
│                                     │
│ ┌──────────────────────────────┐  │
│ │  Crime Details            ✕  │  │  ← Modal Header
│ ├──────────────────────────────┤  │
│ │                               │  │
│ │  🔴 Robbery - Armed           │  │  ← Crime Type
│ │                               │  │
│ │  📅 January 15, 2026          │  │
│ │  🕐 2:30 PM                   │  │
│ │                               │  │
│ │  📍 Location                  │  │  ← Location
│ │  15th & Market Street         │  │
│ │  Philadelphia, PA             │  │
│ │                               │  │
│ │  📊 Risk Assessment           │  │  ← Risk Info
│ │  Z-Score: 2.8 (Elevated)      │  │
│ │  Area Risk: High              │  │
│ │                               │  │
│ │  ℹ️ Additional Info            │  │
│ │  District: Center City        │  │
│ │  Case #: 2026-001234          │  │
│ │                               │  │
│ │  ┌───────────┐ ┌───────────┐ │  │
│ │  │ View on   │ │  Share    │ │  │  ← Actions
│ │  │   Map     │ │  Details  │ │  │
│ │  └───────────┘ └───────────┘ │  │
│ │                               │  │
│ └──────────────────────────────┘  │
│                                     │
└────────────────────────────────────┘
```

**Key Elements:**
- Modal overlay (dismissible)
- Crime type and severity
- Date, time, and location
- Risk assessment metrics
- Case information
- Action buttons (view, share)

---

### 4. Safety Screen (Background Monitoring)

```
┌────────────────────────────────────┐
│  ← Safety                    ⚙️    │  ← Header
├────────────────────────────────────┤
│                                     │
│  Background Monitoring              │  ← Section
│  ┌──────────────────────────────┐ │
│  │                               │ │
│  │  🛡️ Status: ACTIVE  [⚪─]   │ │  ← Toggle Switch
│  │                               │ │
│  │  Currently monitoring your    │ │
│  │  location for safety risks    │ │
│  │                               │ │
│  │  🟢 Current Risk: Low         │ │  ← Current Status
│  │  Last Check: Just now         │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  Alert Settings                     │  ← Section
│  ┌──────────────────────────────┐ │
│  │  Risk Threshold              │ │
│  │  [─────⚪─────] 2.0           │ │  ← Slider
│  │  Low          Medium     High │ │
│  │                               │ │
│  │  Alert Cooldown: 15 min  ▼   │ │  ← Dropdown
│  │                               │ │
│  │  Notification Sound: On  [⚪─]│ │  ← Toggle
│  │  Vibration: On  [⚪─]         │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  Recent Alerts (3)           View  │  ← Section Header
│  ┌──────────────────────────────┐ │
│  │ 🟡 Medium Risk                │ │
│  │ 15th & Chestnut - 5 min ago  │ │  ← Alert Item
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 🟢 Risk Decreased             │ │
│  │ Your area - 1 hour ago       │ │
│  └──────────────────────────────┘ │
│                                     │
├────────────────────────────────────┤
│ 🏠 Home  🗺️ Map  🛡️ Safety 👤    │
└────────────────────────────────────┘
```

**Key Elements:**
- Monitoring toggle (on/off)
- Current monitoring status
- Risk threshold slider
- Alert cooldown settings
- Notification preferences
- Recent alerts list

---

### 5. Profile Screen

```
┌────────────────────────────────────┐
│  ← Profile                   ⚙️    │  ← Header
├────────────────────────────────────┤
│                                     │
│  ┌──────────────────────────────┐ │
│  │         👤                    │ │  ← Avatar
│  │                               │ │
│  │    John Smith                 │ │  ← User Name
│  │    john.smith@email.com       │ │  ← Email
│  │                               │ │
│  │    Member since Jan 2026      │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  Account                            │  ← Section
│  ┌──────────────────────────────┐ │
│  │ ✏️  Edit Profile           > │ │  ← Menu Item
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 🔔  Notifications          > │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 🔒  Privacy Settings       > │ │
│  └──────────────────────────────┘ │
│                                     │
│  About                              │  ← Section
│  ┌──────────────────────────────┐ │
│  │ ℹ️  How It Works           > │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 📖  About Forseti          > │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 💬  Contact Support        > │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │ 📜  Privacy Policy         > │ │
│  └──────────────────────────────┘ │
│                                     │
│  ┌──────────────────────────────┐ │
│  │          🚪 Logout            │ │  ← Logout Button
│  └──────────────────────────────┘ │
│                                     │
│  Version 1.0.0 (Build 55)          │  ← App Version
│                                     │
├────────────────────────────────────┤
│ 🏠 Home  🗺️ Map  🛡️ Safety 👤    │
└────────────────────────────────────┘
```

**Key Elements:**
- User profile summary
- Account settings menu
- Notification preferences
- Privacy controls
- Help and support links
- Logout action
- App version info

---

### 6. Login Screen

```
┌────────────────────────────────────┐
│                                     │
│                                     │
│           ┌─────────┐              │
│           │ Forseti │              │  ← Logo
│           └─────────┘              │
│                                     │
│          Stay Safe,                 │
│          Stay Informed              │  ← Tagline
│                                     │
│  ┌──────────────────────────────┐ │
│  │ 📧 Email                      │ │  ← Email Input
│  │ _____________________________ │ │
│  └──────────────────────────────┘ │
│                                     │
│  ┌──────────────────────────────┐ │
│  │ 🔒 Password                   │ │  ← Password Input
│  │ _____________________________ │ │
│  └──────────────────────────────┘ │
│                                     │
│  [ ] Remember me                    │  ← Checkbox
│                                     │
│  ┌──────────────────────────────┐ │
│  │         LOGIN                 │ │  ← Login Button
│  └──────────────────────────────┘ │
│                                     │
│  Forgot Password?                   │  ← Link
│                                     │
│  ─────────── or ───────────        │
│                                     │
│  Don't have an account?             │
│  Register                           │  ← Register Link
│                                     │
│                                     │
└────────────────────────────────────┘
```

**Key Elements:**
- Clean, centered layout
- Email and password inputs
- Remember me option
- Forgot password link
- Registration link
- Social login (future)

---

### 7. Onboarding Flow (First Launch)

#### Welcome Screen

```
┌────────────────────────────────────┐
│                                     │
│           ┌─────────┐              │
│           │ Forseti │              │  ← Logo
│           └─────────┘              │
│                                     │
│        Welcome to Forseti           │  ← Welcome
│                                     │
│  ┌──────────────────────────────┐ │
│  │                               │ │
│  │          🗺️                   │ │  ← Illustration
│  │                               │ │
│  │   Real-time crime data and    │ │
│  │   risk assessment for your    │ │  ← Description
│  │   personal safety             │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  ● ○ ○ ○                           │  ← Progress Dots
│                                     │
│  ┌──────────────────────────────┐ │
│  │         NEXT                  │ │  ← Next Button
│  └──────────────────────────────┘ │
│                                     │
│  Skip                               │  ← Skip Link
│                                     │
└────────────────────────────────────┘
```

#### Permissions Screen

```
┌────────────────────────────────────┐
│                                     │
│        Permissions Needed           │
│                                     │
│  ┌──────────────────────────────┐ │
│  │                               │ │
│  │          📍                   │ │  ← Icon
│  │                               │ │
│  │    Enable Location Access     │ │
│  │                               │ │
│  │   We need your location to    │ │  ← Explanation
│  │   show relevant safety data   │ │
│  │   and send you alerts         │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  ┌──────────────────────────────┐ │
│  │                               │ │
│  │          🔔                   │ │
│  │                               │ │
│  │   Enable Notifications        │ │
│  │                               │ │
│  │   Stay informed with real-    │ │
│  │   time safety alerts          │ │
│  │                               │ │
│  └──────────────────────────────┘ │
│                                     │
│  ○ ○ ● ○                           │  ← Progress
│                                     │
│  ┌──────────────────────────────┐ │
│  │    GRANT PERMISSIONS          │ │  ← Action Button
│  └──────────────────────────────┘ │
│                                     │
└────────────────────────────────────┘
```

---

## Web Platform Wireframes

### 8. Homepage (Desktop)

```
┌────────────────────────────────────────────────────────────┐
│  ┌─────────┐                                                │
│  │ Forseti │  Safety Map  │  About  │  How It Works  │  Login │  ← Header
│  └─────────┘                                                │
├────────────────────────────────────────────────────────────┤
│                                                             │
│                    HERO SECTION                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │        Stay Safe with Real-Time Crime Data           │ │  ← Headline
│  │                                                       │ │
│  │     AI-powered safety monitoring for Philadelphia    │ │  ← Subhead
│  │                                                       │ │
│  │   ┌─────────────────┐  ┌─────────────────┐          │ │
│  │   │  View Live Map  │  │  Download App   │          │ │  ← CTA Buttons
│  │   └─────────────────┘  └─────────────────┘          │ │
│  │                                                       │ │
│  │                  [Hero Image/Map Preview]            │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
│                    FEATURES SECTION                         │
│  ┌──────────┐      ┌──────────┐      ┌──────────┐        │
│  │   🗺️    │      │   📊     │      │   🛡️    │        │  ← Feature Cards
│  │ Real-Time│      │  Risk    │      │Background│        │
│  │   Map    │      │Assessment│      │Monitoring│        │
│  └──────────┘      └──────────┘      └──────────┘        │
│                                                             │
│                    HOW IT WORKS                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  Step 1 → Step 2 → Step 3 → Step 4                   │ │  ← Process Flow
│  │  Enable   View     Get        Stay                    │ │
│  │  Location Safety   Alerts     Safe                    │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
├────────────────────────────────────────────────────────────┤
│  Footer: Product | Company | Legal | Support | Social      │  ← Footer
└────────────────────────────────────────────────────────────┘
```

---

### 9. Safety Map Page (Desktop)

```
┌────────────────────────────────────────────────────────────┐
│  Forseti │ Safety Map │ About │ How It Works │ Login       │  ← Header
├────────────────────────────────────────────────────────────┤
│                       │                                     │
│  SIDEBAR (30%)        │    MAP CANVAS (70%)                │
│                       │                                     │
│  Search Location      │                                     │
│  ┌─────────────────┐ │         🗺️                         │
│  │ Enter address.. │ │                                     │
│  └─────────────────┘ │    ⬢ ⬢ ⬢ ⬢ ⬢                      │
│                       │      ⬢ ⬢ ⬢ ⬢                      │
│  Risk Summary         │    ⬢ ⬢ 📍⬢ ⬢                      │
│  ┌─────────────────┐ │      ⬢ ⬢ ⬢ ⬢                      │
│  │ 🟢 Low Risk     │ │    ⬢ ⬢ ⬢ ⬢ ⬢                      │
│  │ Z-Score: 0.8    │ │                                     │
│  └─────────────────┘ │                                     │
│                       │    Legend:                          │
│  Filters              │    🟢 Low  🟡 Med  🔴 High         │
│  Time Range:          │                                     │
│  [ Last 7 Days ▼  ]  │                                     │
│                       │    ┌──────┐  ┌──────┐             │
│  Crime Type:          │    │  ➕  │  │  📍  │             │
│  [ All Types ▼    ]  │    └──────┘  └──────┘             │
│                       │                                     │
│  Crime Statistics     │                                     │
│  ┌─────────────────┐ │                                     │
│  │ Total: 1,234    │ │                                     │
│  │ This Week: 45   │ │                                     │
│  │ Trend: ↓ -8%    │ │                                     │
│  └─────────────────┘ │                                     │
│                       │                                     │
│  Recent Crimes        │                                     │
│  • Robbery - 2h ago   │                                     │
│  • Theft - 4h ago     │                                     │
│  • Assault - 6h ago   │                                     │
│                       │                                     │
└───────────────────────┴─────────────────────────────────────┘
```

**Key Elements:**
- Sidebar with search and filters
- Full-width map canvas
- Risk summary card
- Crime statistics
- Recent crimes list
- Map controls

---

### 10. About Page (Desktop)

```
┌────────────────────────────────────────────────────────────┐
│  Forseti │ Safety Map │ About │ How It Works │ Login       │
├────────────────────────────────────────────────────────────┤
│                                                             │
│                      ABOUT FORSETI                          │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │              Our Mission                              │ │  ← Section
│  │                                                       │ │
│  │  Making communities safer through accessible,        │ │
│  │  real-time crime data and AI-powered risk            │ │
│  │  assessment.                                          │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │              How We Work                              │ │
│  │                                                       │ │
│  │  • Aggregate crime data from official sources        │ │
│  │  • Use H3 geolocation for precise mapping            │ │
│  │  • Apply statistical analysis (Z-scores)             │ │
│  │  • Deliver real-time alerts to users                 │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │              Our Data Sources                         │ │
│  │                                                       │ │
│  │  Philadelphia Police Department - OpenData           │ │
│  │  Updated daily with official crime reports           │ │
│  │  3.4M+ crime records analyzed                        │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │           Privacy & Security                          │ │
│  │                                                       │ │
│  │  Your privacy is paramount. We never share your      │ │
│  │  personal data or location history.                  │ │
│  │                                                       │ │
│  │  [Read Full Privacy Policy]                          │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
├────────────────────────────────────────────────────────────┤
│  Footer                                                     │
└────────────────────────────────────────────────────────────┘
```

---

## Responsive Design Breakpoints

### Mobile (Portrait)
- **Width**: 320px - 428px
- **Layout**: Single column
- **Navigation**: Bottom tabs
- **Font Scale**: 16px base

### Tablet (Portrait)
- **Width**: 768px - 834px
- **Layout**: Single column (wider)
- **Navigation**: Bottom tabs or side nav
- **Font Scale**: 16px base

### Desktop (Small)
- **Width**: 1024px - 1366px
- **Layout**: Two columns (sidebar + main)
- **Navigation**: Header menu
- **Font Scale**: 16px base

### Desktop (Large)
- **Width**: 1920px+
- **Layout**: Three columns or wider two-column
- **Navigation**: Header menu
- **Font Scale**: 18px base

---

## Design Patterns Used

### 1. **Card Pattern**
Used for grouping related information (status cards, alert cards, feature cards)

### 2. **List Pattern**
Used for scrollable content (alerts, crimes, menu items)

### 3. **Modal Overlay**
Used for detailed views and focused tasks (crime details, settings)

### 4. **Tab Bar Navigation**
Used for primary app navigation (mobile)

### 5. **Form Pattern**
Used for user input (login, registration, settings)

### 6. **Hero Section**
Used for landing page (web homepage)

### 7. **Sidebar Layout**
Used for filtering and navigation (web map page)

---

## Accessibility Considerations

### Visual Hierarchy
- Clear heading structure (H1-H4)
- Consistent spacing and alignment
- High contrast text and backgrounds

### Touch Targets
- Minimum 44x44pt tap areas
- Adequate spacing between interactive elements
- Clear visual feedback on interaction

### Screen Reader Support
- Semantic HTML structure
- Alt text for images/icons
- ARIA labels where needed
- Logical tab order

### Color Independence
- Don't rely on color alone
- Use icons with color coding
- Text labels for all statuses

---

## Loading States

### Skeleton Screens

```
┌────────────────────────────────────┐
│  Loading...                         │
├────────────────────────────────────┤
│  ┌──────────────────────────────┐ │
│  │  ▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒  │ │  ← Skeleton
│  │  ▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒          │ │     Placeholder
│  │                               │ │
│  │  ▒▒▒▒▒▒▒▒▒▒▒▒  ▒▒▒▒▒▒▒▒▒   │ │
│  └──────────────────────────────┘ │
│  ┌──────────────────────────────┐ │
│  │  ▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒        │ │
│  │  ▒▒▒▒▒▒▒▒▒▒                  │ │
│  └──────────────────────────────┘ │
└────────────────────────────────────┘
```

### Empty States

```
┌────────────────────────────────────┐
│                                     │
│           📭                        │  ← Empty Icon
│                                     │
│      No Alerts Yet                  │  ← Message
│                                     │
│  You'll see alerts here when       │  ← Description
│  you enable background monitoring   │
│                                     │
│  ┌──────────────────────────────┐ │
│  │    Enable Monitoring          │ │  ← Action
│  └──────────────────────────────┘ │
│                                     │
└────────────────────────────────────┘
```

### Error States

```
┌────────────────────────────────────┐
│                                     │
│           ⚠️                        │  ← Error Icon
│                                     │
│      Unable to Load Data            │  ← Error Message
│                                     │
│  Please check your connection       │  ← Description
│  and try again                      │
│                                     │
│  ┌──────────────────────────────┐ │
│  │       Try Again               │ │  ← Retry Action
│  └──────────────────────────────┘ │
│                                     │
└────────────────────────────────────┘
```

---

## Interactive Elements

### Buttons
- **Primary**: Filled background, high contrast
- **Secondary**: Outlined, medium contrast
- **Tertiary**: Text only, low contrast
- **States**: Default, hover, active, disabled

### Form Inputs
- **Text Input**: Border, clear label, placeholder
- **Dropdown**: Arrow indicator, clear options
- **Toggle**: On/off states, clear labels
- **Slider**: Draggable thumb, value display

### Cards
- **Elevated**: Shadow for depth
- **Bordered**: Subtle outline
- **Interactive**: Hover/press states
- **Selectable**: Selected state indicator

---

## Animation Guidelines

### Transitions
- **Screen Changes**: 300ms ease-in-out
- **Modal Open/Close**: 250ms ease-out
- **Tab Switching**: Instant (< 100ms)
- **Loading Spinners**: Continuous rotation

### Microinteractions
- **Button Press**: Scale down slightly (95%)
- **Card Tap**: Subtle highlight
- **Toggle Switch**: Slide animation
- **Pull to Refresh**: Spinner animation

---

## Related Documents

- [Site Map & Navigation](./01-sitemap-navigation.md) - Navigation structure
- [User Flows](./03-user-flows.md) - User journey diagrams
- [Mobile-First Design](./04-mobile-first-approach.md) - Responsive strategy

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial wireframes for all key screens | Copilot |
