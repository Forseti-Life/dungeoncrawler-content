# User Flow Diagrams

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete

---

## Overview

This document provides detailed user flow diagrams for all major user journeys in the Forseti/AmISafe platform. These flows map out how users interact with the application to accomplish their goals.

---

## Primary User Personas

### Sarah - Urban Commuter (Primary)
**Goal**: Check route safety during daily commute  
**Key Behavior**: Quick checks, wants instant risk assessment

### Marcus - Parent (Secondary)
**Goal**: Monitor children's neighborhood safety  
**Key Behavior**: Regular monitoring, detailed analysis

### Jessica - Real Estate Professional (Tertiary)
**Goal**: Research neighborhood safety for clients  
**Key Behavior**: Comparative analysis, data export

---

## User Flow 1: New User Onboarding

```
START: User downloads and opens app
│
├─► Splash Screen (2 seconds)
│   └─► Show Forseti logo and branding
│
├─► Welcome Screen 1/4
│   ├─► Headline: "Welcome to Forseti"
│   ├─► Description: Real-time crime data
│   └─► Action: [Next] or [Skip]
│       │
│       ├─► [Next] → Welcome Screen 2/4
│       └─► [Skip] → Jump to Login
│
├─► Welcome Screen 2/4
│   ├─► Feature: Crime Map Visualization
│   ├─► Description: See crime hotspots
│   └─► Action: [Next] or [Skip]
│       │
│       └─► [Next] → Welcome Screen 3/4
│
├─► Welcome Screen 3/4
│   ├─► Feature: Background Monitoring
│   ├─► Description: Get real-time alerts
│   └─► Action: [Next] or [Skip]
│       │
│       └─► [Next] → Welcome Screen 4/4
│
├─► Welcome Screen 4/4
│   ├─► Feature: Personalized Safety
│   ├─► Description: Customize your experience
│   └─► Action: [Get Started]
│       │
│       └─► [Get Started] → Registration
│
├─► Registration Screen
│   ├─► Input: Email
│   ├─► Input: Password
│   ├─► Input: Confirm Password
│   ├─► Checkbox: Terms & Conditions
│   └─► Action: [Create Account]
│       │
│       ├─► Validation Error? → Show error message
│       │   └─► User corrects → Retry
│       │
│       └─► Success → Email Verification
│
├─► Email Verification
│   ├─► Message: "Check your email"
│   ├─► User checks email
│   └─► Clicks verification link
│       │
│       └─► Success → Permissions Request
│
├─► Location Permission Request
│   ├─► Explanation: Why we need location
│   ├─► Action: [Allow] or [Don't Allow]
│   │
│   ├─► [Allow] → Location Granted
│   │   └─► Proceed to Notification Permission
│   │
│   └─► [Don't Allow] → Location Denied
│       ├─► Warning: "Limited functionality"
│       └─► Option to continue or retry
│           └─► Proceed to Notification Permission
│
├─► Notification Permission Request
│   ├─► Explanation: Stay informed with alerts
│   ├─► Action: [Allow] or [Don't Allow]
│   │
│   ├─► [Allow] → Notifications Granted
│   └─► [Don't Allow] → Notifications Denied
│       └─► Can enable later in settings
│
├─► Tutorial: How to Use App (Optional)
│   ├─► Screen 1: Home Dashboard
│   ├─► Screen 2: Crime Map
│   ├─► Screen 3: Safety Monitoring
│   └─► Action: [Finish] or [Skip]
│       │
│       └─► [Finish] → Home Screen
│
END: User lands on Home Dashboard (Logged In)
```

**Success Criteria**:
- User completes registration
- Location permission granted
- User reaches home dashboard

**Failure Points**:
- Email already registered
- Weak password
- Permission denied
- Email verification fails

---

## User Flow 2: Check Current Location Safety (Quick Use)

```
START: User opens app (already logged in)
│
├─► Home Dashboard Loads
│   ├─► Show loading skeleton
│   ├─► Fetch current location
│   └─► Fetch safety data
│       │
│       ├─► Success → Display Data
│       └─► Error → Show error state
│           └─► Action: [Retry]
│
├─► Home Dashboard (Loaded)
│   ├─► Display: Current Location
│   ├─► Display: Safety Status (Color + Text)
│   │   ├─► 🟢 Safe (Z-score < 1.0)
│   │   ├─► 🟡 Medium (Z-score 1.0-2.0)
│   │   ├─► 🟠 Elevated (Z-score 2.0-3.0)
│   │   └─► 🔴 High Risk (Z-score > 3.0)
│   │
│   ├─► Display: Recent Alerts (if any)
│   └─► Display: Quick Actions
│
├─► User Reads Safety Status
│   ├─► Is location safe? → Decision Made
│   └─► Wants more details? → Tap "View Map"
│       │
│       └─► Navigate to Map Tab
│
├─► Map Tab
│   ├─► Show map centered on user
│   ├─► Display H3 hexagons (color-coded)
│   ├─► Display user location marker
│   └─► Display nearby crime markers
│       │
│       └─► User views crime distribution
│           │
│           ├─► Tap hexagon → Crime Detail Modal
│           └─► Zoom/Pan → Explore area
│
├─► Crime Detail Modal (if tapped)
│   ├─► Display: Crime type
│   ├─► Display: Date & time
│   ├─► Display: Location
│   ├─► Display: Risk assessment
│   └─► Actions: [View on Map] [Share] [Close]
│       │
│       └─► User reads details → [Close]
│           └─► Back to Map
│
END: User has assessed safety and made decision
```

**Success Criteria**:
- Location data loads < 2 seconds
- Safety status clearly displayed
- User can interpret risk level

**Failure Points**:
- Location permission denied
- Network error loading data
- GPS unavailable

---

## User Flow 3: Enable Background Monitoring

```
START: User wants continuous monitoring
│
├─► Navigate to Safety Tab
│   └─► Tap "Safety" in bottom navigation
│
├─► Safety Screen Loads
│   ├─► Display: Monitoring Status (OFF)
│   ├─► Display: Alert Settings
│   └─► Display: Recent Alerts (empty)
│
├─► User Reviews Settings
│   ├─► Check Risk Threshold (default: 2.0)
│   ├─► Check Alert Cooldown (default: 15 min)
│   └─► Satisfied? → Toggle Monitoring ON
│       │
│       └─► OR: Adjust Settings First
│           ├─► Slide Risk Threshold
│           ├─► Change Cooldown Period
│           └─► Then: Toggle Monitoring ON
│
├─► Toggle Monitoring Switch
│   ├─► User taps toggle to ON position
│   └─► System checks permissions
│       │
│       ├─► Location Permission Granted?
│       │   └─► YES → Check Notification Permission
│       │
│       └─► NO → Request Permission
│           ├─► Show explanation dialog
│           ├─► User taps [Go to Settings]
│           └─► User enables in system settings
│               └─► Returns to app
│                   └─► Retry toggle
│
├─► Notification Permission Check
│   ├─► Notification Permission Granted?
│   │   └─► YES → Enable Monitoring
│   │
│   └─► NO → Request Permission
│       ├─► Show explanation
│       ├─► User grants or denies
│       └─► Monitoring enabled (may not get alerts)
│
├─► Background Monitoring ENABLED
│   ├─► Update UI: Toggle shows ON
│   ├─► Display: "Monitoring Active" status
│   ├─► Display: Current risk level
│   ├─► Start background location tracking
│   └─► Show confirmation message
│       "✓ Background monitoring enabled"
│
├─► System Monitors in Background
│   ├─► Check location periodically
│   ├─► Calculate risk for current location
│   └─► Compare to threshold
│       │
│       ├─► Risk < Threshold → No alert
│       └─► Risk > Threshold → Send Alert
│           │
│           └─► Push Notification Flow
│
├─► User Receives Alert Notification
│   ├─► Notification appears on device
│   ├─► Shows: Risk level and location
│   └─► User taps notification
│       │
│       └─► App opens to Alert Detail
│           ├─► Display: Alert information
│           ├─► Display: Current location
│           ├─► Action: [View on Map]
│           └─► Action: [Dismiss]
│               │
│               └─► User reviews and takes action
│
END: Monitoring active, alerts functioning
```

**Success Criteria**:
- Monitoring successfully enabled
- Permissions granted
- User receives test/first alert
- Alert cooldown prevents spam

**Failure Points**:
- Location permission denied
- Notification permission denied
- Background location restricted (iOS)
- Battery optimization kills service (Android)

---

## User Flow 4: Check Route Safety (Planning Journey)

```
START: User planning to travel to destination
│
├─► Navigate to Map Tab
│   └─► Tap "Map" in bottom navigation
│
├─► Map Screen Loads
│   ├─► Display: User's current location
│   ├─► Display: Crime heatmap (H3 hexagons)
│   └─► Display: Map controls
│
├─► User Explores Route Options
│   ├─► Method 1: Visual Inspection
│   │   ├─► Pan map along planned route
│   │   ├─► Look for red/orange hexagons
│   │   └─► Identify safer alternative routes
│   │
│   └─► Method 2: Search Destination
│       ├─► Tap search icon
│       ├─► Enter destination address
│       ├─► Map centers on destination
│       └─► View area risk level
│
├─► Analyze Route Safety
│   ├─► Identify High-Risk Areas
│   │   ├─► Red hexagons (Z-score > 3.0)
│   │   ├─► Orange hexagons (Z-score 2.0-3.0)
│   │   └─► Tap hexagon for details
│   │       │
│   │       └─► Crime Detail Modal
│   │           ├─► Crime types in area
│   │           ├─► Frequency
│   │           └─► Time patterns
│   │
│   └─► Compare Alternative Routes
│       ├─► Visual comparison of colors
│       ├─► Mental note of safer paths
│       └─► Decision: Choose route
│
├─► Apply Time Filters (Optional)
│   ├─► Tap filter icon
│   ├─► Select time range
│   │   ├─► Last 24 hours
│   │   ├─► Last 7 days (default)
│   │   ├─► Last 30 days
│   │   └─► Last 3 months
│   │
│   └─► Map updates with filtered data
│       └─► Re-assess route with new data
│
├─► Apply Crime Type Filters (Optional)
│   ├─► Tap filter icon
│   ├─► Select crime types
│   │   ├─► Violent crimes
│   │   ├─► Property crimes
│   │   ├─► All crimes (default)
│   │   └─► Custom selection
│   │
│   └─► Map updates with filtered data
│       └─► Focus on relevant crime types
│
├─► User Makes Decision
│   ├─► Route appears safe (mostly green)
│   │   └─► Proceed with planned route
│   │
│   ├─► Route has risk areas (yellow/orange)
│   │   └─► Consider alternatives or precautions
│   │
│   └─► Route is high-risk (red)
│       └─► Choose alternative route
│           └─► Repeat analysis for new route
│
├─► Optional: Enable Monitoring for Journey
│   ├─► Navigate to Safety tab
│   ├─► Enable background monitoring
│   └─► Get alerts during journey
│
END: User has assessed route and made informed decision
```

**Success Criteria**:
- Map loads quickly (< 1 second)
- Route visualization is clear
- User can identify risk levels
- User makes informed decision

**Failure Points**:
- Map fails to load
- Search doesn't work
- Hexagons not visible
- Can't interpret risk colors

---

## User Flow 5: Respond to High-Risk Alert

```
START: User in area that becomes high-risk
│
├─► Background Monitoring Detects Risk
│   ├─► User location: 39.9526, -75.1652
│   ├─► H3 hexagon risk: Z-score 3.2 (HIGH)
│   ├─► Threshold: 2.0
│   └─► Risk exceeds threshold → Trigger Alert
│
├─► Check Alert Cooldown
│   ├─► Last alert: > 15 minutes ago?
│   │   └─► YES → Send Alert
│   │
│   └─► NO → Suppress alert
│       └─► Log event, don't notify
│
├─► Send Push Notification
│   ├─► Notification Title: "⚠️ High Risk Area"
│   ├─► Message: "You're in a high-risk area"
│   ├─► Additional: Risk level (Z: 3.2)
│   ├─► Sound: Alert tone (if enabled)
│   └─► Vibration: Pattern (if enabled)
│
├─► User Receives Notification
│   ├─► Scenario 1: App is closed
│   │   ├─► Notification appears in tray
│   │   └─► User taps notification
│   │       └─► App launches to Alert Detail
│   │
│   ├─► Scenario 2: App is in background
│   │   ├─► Notification appears
│   │   └─► User taps notification
│   │       └─► App foregrounds to Alert Detail
│   │
│   └─► Scenario 3: App is active
│       ├─► In-app alert banner
│       └─► User taps banner
│           └─► Navigate to Alert Detail
│
├─► Alert Detail Screen
│   ├─► Display: Alert severity (HIGH - Red)
│   ├─► Display: Current location
│   ├─► Display: Risk score (Z: 3.2)
│   ├─► Display: Time of alert
│   ├─► Display: Nearby crime summary
│   └─► Actions Available
│       ├─► [View on Map]
│       ├─► [Get Directions Out]
│       ├─► [Call Emergency] (if critical)
│       └─► [Dismiss]
│
├─► User Reviews Alert
│   └─► Decision Point: What to do?
│       │
│       ├─► Option 1: View on Map
│       │   └─► Navigate to Map Tab
│       │       ├─► Map centered on alert location
│       │       ├─► Show risk heatmap
│       │       ├─► Show nearby safe areas
│       │       └─► User identifies safe direction
│       │           └─► Leaves high-risk area
│       │
│       ├─► Option 2: Get Directions Out
│       │   └─► Open external navigation app
│       │       ├─► Suggest nearest safe area
│       │       ├─► Avoid high-risk hexagons
│       │       └─► User follows directions
│       │
│       ├─► Option 3: Call Emergency (Critical)
│       │   └─► Immediate danger situation
│       │       ├─► Tap [Call 911]
│       │       ├─► Phone dialer opens
│       │       └─► User calls emergency services
│       │
│       └─► Option 4: Dismiss
│           └─► User acknowledges risk
│               ├─► Alert marked as read
│               ├─► Stored in alert history
│               └─► User stays aware
│
├─► User Takes Action
│   ├─► Moves to safer area
│   ├─► Changes route
│   └─► Maintains awareness
│
├─► Background Monitoring Continues
│   ├─► Check new location
│   ├─► Calculate new risk
│   └─► Risk decreased? → Send update
│       │
│       └─► Update Notification
│           ├─► Title: "✓ Risk Decreased"
│           ├─► Message: "You're now in a safer area"
│           └─► User feels reassured
│
END: User safely navigated away from high-risk area
```

**Success Criteria**:
- Alert sent within 30 seconds of entering risk area
- User receives and understands alert
- User takes appropriate action
- Risk level updates as user moves

**Failure Points**:
- Notification not delivered
- User doesn't see/hear notification
- Location tracking fails
- User can't interpret guidance

---

## User Flow 6: Login (Returning User)

```
START: User opens app (not logged in)
│
├─► App Launch
│   ├─► Check for saved session
│   └─► Session valid?
│       │
│       ├─► YES → Auto-login
│       │   └─► Navigate to Home Dashboard
│       │       └─► END: User logged in
│       │
│       └─► NO → Show Login Screen
│
├─► Login Screen
│   ├─► Display: Email input field
│   ├─► Display: Password input field
│   ├─► Display: "Remember me" checkbox
│   ├─► Display: [Login] button
│   ├─► Display: "Forgot password?" link
│   └─► Display: "Register" link
│
├─► User Enters Credentials
│   ├─► Enter email
│   ├─► Enter password
│   ├─► Optional: Check "Remember me"
│   └─► Tap [Login]
│
├─► Validate Credentials
│   ├─► Show loading indicator
│   ├─► Send request to server
│   └─► Wait for response
│       │
│       ├─► SUCCESS → Credentials Valid
│       │   ├─► Save session token
│       │   ├─► Save "Remember me" preference
│       │   └─► Navigate to Home Dashboard
│       │       └─► END: User logged in
│       │
│       └─► ERROR → Credentials Invalid
│           ├─► Error: "Invalid email or password"
│           ├─► Highlight error fields
│           └─► User corrects and retries
│               │
│               └─► OR: Forgot Password Flow
│
├─► Forgot Password Flow (If User Clicks Link)
│   ├─► Navigate to Forgot Password Screen
│   ├─► User enters email
│   ├─► Tap [Send Reset Link]
│   ├─► System sends password reset email
│   ├─► Confirmation: "Check your email"
│   └─► User clicks reset link in email
│       ├─► Opens reset password page
│       ├─► Enter new password
│       ├─► Confirm new password
│       ├─► Submit new password
│       └─► Success → Return to Login
│           └─► Login with new password
│
END: User successfully logged in
```

**Success Criteria**:
- Login completes in < 2 seconds
- Auto-login works for returning users
- Password reset flow is clear

**Failure Points**:
- Wrong credentials
- Network error
- Email not verified
- Account locked

---

## User Flow 7: Adjust Safety Settings

```
START: User wants to customize alert preferences
│
├─► Navigate to Safety Tab
│   └─► Tap "Safety" in bottom navigation
│
├─► Safety Screen Loads
│   ├─► Display: Monitoring status
│   ├─► Display: Current settings
│   │   ├─► Risk threshold: 2.0
│   │   ├─► Alert cooldown: 15 min
│   │   ├─► Notification sound: ON
│   │   └─► Vibration: ON
│   │
│   └─► User reviews settings
│
├─► User Decides to Adjust
│   └─► Change Which Setting?
│       │
│       ├─► Option 1: Risk Threshold
│       │   ├─► Current: 2.0 (Medium)
│       │   ├─► User slides control
│       │   │   ├─► Left (Lower): More alerts
│       │   │   └─► Right (Higher): Fewer alerts
│       │   │
│       │   ├─► New Value: 1.5
│       │   ├─► System saves setting
│       │   └─► Confirmation: "Threshold updated"
│       │       └─► Will now alert at Z-score > 1.5
│       │
│       ├─► Option 2: Alert Cooldown
│       │   ├─► Current: 15 minutes
│       │   ├─► Tap dropdown
│       │   ├─► Select new value
│       │   │   ├─► 1 minute
│       │   │   ├─► 5 minutes
│       │   │   ├─► 15 minutes (default)
│       │   │   └─► 30 minutes
│       │   │
│       │   ├─► Select: 5 minutes
│       │   ├─► System saves setting
│       │   └─► Confirmation: "Cooldown updated"
│       │       └─► Will alert every 5 min max
│       │
│       ├─► Option 3: Notification Sound
│       │   ├─► Current: ON
│       │   ├─► Toggle switch
│       │   ├─► New: OFF
│       │   ├─► System saves setting
│       │   └─► Confirmation: "Sound disabled"
│       │       └─► Silent notifications only
│       │
│       └─► Option 4: Vibration
│           ├─► Current: ON
│           ├─► Toggle switch
│           ├─► New: OFF
│           ├─► System saves setting
│           └─► Confirmation: "Vibration disabled"
│
├─► Test Settings (Optional)
│   ├─► Tap [Test Alert] button
│   ├─► System sends test notification
│   ├─► User receives notification
│   └─► Verify: Settings working as expected
│       │
│       ├─► YES → Settings confirmed
│       └─► NO → Adjust further
│
├─► Settings Applied
│   ├─► All changes saved to user profile
│   ├─► Background monitoring uses new settings
│   └─► User continues using app
│
END: Settings customized to user preference
```

**Success Criteria**:
- Settings save successfully
- Changes take effect immediately
- Test alerts work
- User satisfied with customization

---

## User Flow 8: View Crime History

```
START: User wants to see past crime data
│
├─► Navigate to Map Tab
│   └─► Tap "Map" in bottom navigation
│
├─► Map Screen Loads
│   ├─► Default: Last 7 days
│   └─► Display: Current crime heatmap
│
├─► User Opens Filters
│   ├─► Tap filter icon (top right)
│   └─► Filter panel appears
│       │
│       └─► Options Available
│           ├─► Time Range
│           ├─► Crime Types
│           └─► Risk Level
│
├─► Select Time Range
│   ├─► Current: Last 7 days
│   ├─► Tap time range dropdown
│   └─► Options:
│       ├─► Last 24 hours
│       ├─► Last 7 days (current)
│       ├─► Last 30 days
│       ├─► Last 3 months
│       └─► Last 6 months
│           │
│           └─► User selects: Last 30 days
│
├─► Apply Filter
│   ├─► Tap [Apply] button
│   ├─► Show loading indicator
│   ├─► Fetch historical data
│   └─► Update map visualization
│       │
│       ├─► SUCCESS → Map Updated
│       │   ├─► Hexagons show 30-day data
│       │   ├─► Crime markers updated
│       │   └─► User sees trends
│       │
│       └─► ERROR → Show error message
│           └─► Retry or use cached data
│
├─► User Analyzes Trends
│   ├─► Compare different time periods
│   ├─► Identify pattern changes
│   ├─► Note seasonal variations
│   └─► Make informed decisions
│
├─► View Specific Crime Details
│   ├─► Tap hexagon or crime marker
│   └─► Crime Detail Modal
│       ├─► Crime type
│       ├─► Date (within selected range)
│       ├─► Location
│       └─► Additional context
│
END: User has reviewed historical crime data
```

**Success Criteria**:
- Historical data loads correctly
- Time range filter works
- User can compare different periods
- Performance remains good with large datasets

---

## User Flow 9: Logout

```
START: User wants to log out
│
├─► Navigate to Profile Tab
│   └─► Tap "Profile" in bottom navigation
│
├─► Profile Screen Loads
│   ├─► Display: User information
│   ├─► Display: Settings menu
│   └─► Display: [Logout] button (bottom)
│
├─► User Taps Logout
│   └─► Tap [Logout] button
│
├─► Confirmation Dialog
│   ├─► Message: "Are you sure?"
│   ├─► Warning: "Background monitoring will stop"
│   └─► Actions:
│       ├─► [Cancel] → Stay logged in
│       └─► [Logout] → Proceed
│
├─► User Confirms Logout
│   └─► Tap [Logout] in dialog
│
├─► System Logs Out User
│   ├─► Stop background monitoring
│   ├─► Clear session token
│   ├─► Clear cached user data
│   ├─► Cancel pending notifications
│   └─► Navigate to Login Screen
│
├─► Login Screen Shown
│   ├─► User logged out successfully
│   └─► Can log in again anytime
│
END: User successfully logged out
```

**Success Criteria**:
- Logout completes successfully
- Background monitoring stops
- User data cleared
- Can log in again

---

## Error Handling Flows

### Network Error

```
User Action (Any)
│
├─► System makes API request
│   └─► Network unavailable or timeout
│       │
│       └─► Error State
│           ├─► Show error message
│           │   "Unable to connect"
│           │
│           ├─► Display error icon
│           ├─► Explain the issue
│           │   "Check your internet connection"
│           │
│           └─► Actions:
│               ├─► [Try Again] → Retry request
│               └─► [Use Offline Data] → Load cached data
```

### Location Permission Denied

```
User enables feature requiring location
│
├─► System checks permission
│   └─► Permission denied
│       │
│       └─► Permission Dialog
│           ├─► Explain why needed
│           │   "Location required for safety data"
│           │
│           ├─► Actions:
│           │   ├─► [Go to Settings]
│           │   │   └─► Opens system settings
│           │   │       └─► User manually enables
│           │   │           └─► Returns to app
│           │   │
│           │   └─► [Cancel]
│           │       └─► Feature disabled
│           │           └─► Show limited functionality
│           │
│           └─► Alternative:
│               └─► Manual location entry
```

---

## Navigation Transitions

### Between Tabs (Mobile)

```
User on Home Tab
│
├─► Tap Map Tab
│   ├─► Home tab fades out
│   ├─► Map tab fades in
│   └─► Tab indicator updates
│       └─► Duration: < 100ms
│           └─► User sees Map Screen
```

### Modal Open/Close

```
User taps element to open modal
│
├─► Modal Animation
│   ├─► Background dims (fade)
│   ├─► Modal slides up from bottom
│   └─► Duration: 250ms
│       └─► Modal fully visible
│
User closes modal (tap X or outside)
│
├─► Close Animation
│   ├─► Modal slides down
│   ├─► Background lightens
│   └─► Duration: 200ms
│       └─► Back to previous screen
```

---

## Related Documents

- [Site Map & Navigation](./01-sitemap-navigation.md) - Navigation structure
- [Wireframes](./02-wireframes.md) - Screen layouts
- [User Journey](../user-journey/sarah-urban-commuter.md) - Primary persona

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial user flow diagrams | Copilot |
