# Process Flow & Validation Roadmap

**Version**: 1.2  
**Last Updated**: January 26, 2026  
**Status**: 🟢 Live Beta Testing  
**Purpose**: Map system architecture to user journey for end-to-end validation

---

## Overview

This document provides a comprehensive process flow that connects:
1. **User Actions** (what the user does)
2. **System Components** (which parts of the system respond)
3. **Data Flow** (how information moves through the system)
4. **Validation Checkpoints** (what to test/verify at each stage)

Use this as a roadmap to ensure all user journey touchpoints are properly implemented and functioning.

---

## Process Flow Map

### Phase 1: Discovery & Activation

#### 1.1 Discovery (User finds Forseti)

**User Action**: Searches "St. Louis crime map" on Google

**System Components**:
- `forseti.life` website (Drupal 11)
- SEO metadata and structured data
- Landing page with interactive map

**Data Flow**:
```
Google Search → forseti.life landing page
                ↓
            Load Leaflet.js map
                ↓
            Fetch initial hexagon data from API
```

**Validation Checkpoints**:
- [ ] Website appears in top 10 Google results for target keywords
- [ ] Page loads in <3 seconds
- [ ] Map renders correctly on desktop, mobile, tablet
- [ ] Initial viewport centers on St. Louis metro area
- [ ] Color-coded hexagons are visible and responsive

**Technical Dependencies**:
- Drupal website deployed and accessible
- API endpoint: `/api/amisafe/aggregated` responding
- Leaflet.js library loaded correctly
- H3 hexagon GeoJSON data available

**Success Metrics**:
- Bounce rate <60%
- Time on page >2 minutes
- Map interaction rate >40%

---

#### 1.2 Aha Moment (User compares routes)

**User Action**: Clicks on hexagons along two different routes to compare z-scores

**System Components**:
- Interactive map (Leaflet.js)
- H3 hexagon click handler
- API endpoint: `/api/amisafe/aggregated?lat=X&lng=Y`
- MySQL database (aggregated crime data)

**Data Flow**:
```
User clicks hexagon
    ↓
Leaflet click event triggered
    ↓
Extract H3 index from hexagon properties
    ↓
API call: GET /api/amisafe/aggregated?h3=<index>
    ↓
MySQL query: SELECT z_score, crime_count FROM hexagon_stats WHERE h3_index = ?
    ↓
Return JSON: {h3_index, z_score, crime_count, risk_level}
    ↓
Display popup with z-score and color-coded risk level
```

**Validation Checkpoints**:
- [ ] Hexagons respond to click/tap events
- [ ] API returns z-score within 500ms
- [ ] Popup displays: z-score, crime count, risk level, color coding
- [ ] Risk levels match thresholds: <1.0 low, 1.0-2.0 moderate, 2.0-3.0 elevated, >3.0 high
- [ ] Colors match risk levels (green → yellow → orange → red)
- [ ] User can click multiple hexagons to compare
- [ ] Mobile touch interactions work smoothly

**Technical Dependencies**:
- H3 library correctly indexes hexagons
- Database has aggregated crime data for all St. Louis hexagons
- API authentication/rate limiting doesn't block public access
- Z-score calculation is accurate (mean and std dev correct)

**Success Metrics**:
- Users click ≥3 hexagons in first session (Aha Moment indicator)
- Popup view duration >5 seconds
- Route comparison behavior detected (clicks along linear path)

---

#### 1.3 Signup Decision (User creates account)

**User Action**: Clicks "Get Mobile Alerts" CTA, fills out signup form

**System Components**:
- Signup form (Drupal user registration)
- Email verification system
- User database (MySQL)

**Data Flow**:
```
User submits signup form (email, password)
    ↓
Drupal user registration endpoint
    ↓
Validate email format, password strength
    ↓
Check if email already exists
    ↓
Create user record in MySQL
    ↓
Generate verification token
    ↓
Send verification email
    ↓
User clicks email link
    ↓
Verify token, activate account
    ↓
Redirect to "Download App" page
```

**Validation Checkpoints**:
- [ ] Signup form validates input (email format, password strength)
- [ ] Duplicate email detection works
- [ ] User record created in database with correct fields
- [ ] Verification email sent within 1 minute
- [ ] Email contains working verification link
- [ ] Token expires after 24 hours
- [ ] Account activation works on first click
- [ ] Download page shows correct app store links (iOS/Android)

**Technical Dependencies**:
- Drupal user module configured
- SMTP/email service configured and working
- SSL certificate valid (https required for secure forms)
- Password hashing (bcrypt/Argon2)

**Success Metrics**:
- Signup completion rate >60%
- Email verification rate >80%
- Time from signup to verification <5 minutes (median)

---

#### 1.4 Mobile App Download & Login

**User Action**: Downloads AmISafe app from App Store/Play Store, logs in

**System Components**:
- iOS/Android app stores
- React Native mobile app
- Authentication API (JWT/session tokens)

**Data Flow**:
```
User downloads AmISafe app
    ↓
Opens app (first launch)
    ↓
Login screen appears
    ↓
User enters email/password
    ↓
API call: POST /api/user/login {email, password}
    ↓
Server validates credentials
    ↓
Generate JWT token or session ID
    ↓
Return token + user profile data
    ↓
Store token in AsyncStorage (persistent)
    ↓
Navigate to Home screen
```

**Validation Checkpoints**:
- [ ] App available on iOS App Store (Apple approval)
- [ ] App available on Google Play Store (Google approval)
- [ ] App icon and screenshots display correctly
- [ ] Login API accepts website credentials
- [ ] Invalid credentials show clear error message
- [ ] Successful login stores token persistently
- [ ] App remembers login state after closing/reopening
- [ ] "Forgot Password" flow works

**Technical Dependencies**:
- React Native app built and published to stores
- API endpoint `/api/user/login` implemented
- JWT/session token generation secure
- AsyncStorage working on iOS and Android

**Success Metrics**:
- Download-to-install rate >70%
- Login success rate >90%
- First-session retention >80%

---

#### 1.5 Onboarding: Location Permissions

**User Action**: Grants location permissions ("Allow While Using" → "Allow Always")

**System Components**:
- React Native location permission API
- iOS/Android permission dialogs
- App onboarding flow

**Data Flow**:
```
App requests location permission
    ↓
OS shows permission dialog
    ↓
User taps "Allow While Using App"
    ↓
App detects permission granted
    ↓
Show explanation: "Enable Always Allow for background alerts"
    ↓
User opens settings or grants "Always Allow"
    ↓
App verifies background permission granted
    ↓
Show confirmation: "You're protected!"
```

**Validation Checkpoints**:
- [ ] Permission request appears at appropriate time (not immediately on launch)
- [ ] Permission dialog shows clear explanation of why location is needed
- [ ] "Allow While Using" permission works for foreground features
- [ ] App detects when background permission is missing
- [ ] Prompt to upgrade to "Always Allow" is clear and non-intrusive
- [ ] Deep link to iOS/Android settings works
- [ ] App handles permission denial gracefully (shows limited functionality)
- [ ] Permission status persists correctly

**Technical Dependencies**:
- `react-native-permissions` library configured
- iOS Info.plist has location usage descriptions
- Android Manifest has location permissions declared
- Background location permission requested separately (iOS requirement)

**Success Metrics**:
- "While Using" permission grant rate >85%
- "Always Allow" permission grant rate >60%
- Time from install to full permissions <3 minutes

---

#### 1.6 Premium Trial Setup

**User Action**: Configures alert threshold and cooldown settings

**System Components**:
- Settings screen in mobile app
- AsyncStorage (local preferences)
- User preferences in database (optional sync)

**Data Flow**:
```
User navigates to Settings
    ↓
Sees "Alert Settings" section
    ↓
Adjusts z-score threshold slider (1.0-3.0, default 2.0)
    ↓
Adjusts cooldown period slider (1-30 min, default 15 min)
    ↓
Toggles "Enable Background Monitoring" (default ON for trial)
    ↓
Settings saved to AsyncStorage
    ↓
Optional: Sync to server for multi-device access
    ↓
BackgroundLocationService reads settings
```

**Validation Checkpoints**:
- [ ] Settings screen loads with sensible defaults
- [ ] Sliders are responsive and show current values
- [ ] Threshold slider labeled: "Low Risk (1.0)" → "High Risk (3.0)"
- [ ] Cooldown slider labeled: "1 min" → "30 min"
- [ ] Toggle switches work correctly
- [ ] Settings save immediately on change
- [ ] Settings persist after app restart
- [ ] Background service respects updated settings without app restart

**Technical Dependencies**:
- AsyncStorage working correctly
- Settings UI components (Slider, Switch) from React Native
- BackgroundLocationService can read AsyncStorage values

**Success Metrics**:
- Settings customization rate >40%
- Default settings retention rate >50% (defaults are good)
- Background monitoring enabled rate >95% (trial users)

---

### Phase 2: Retention & Engagement

#### 2.1 Background Location Monitoring (Core Feature)

**User Action**: User goes about daily life with app in background

**System Components**:
- `BackgroundLocationService.ts` (React Native)
- iOS Background Location API
- Android Background Location Service
- Device GPS

**Data Flow**:
```
[Continuous loop while app is in background]

BackgroundLocationService starts
    ↓
Set interval timer (5-15 minutes based on battery optimization)
    ↓
Get current GPS coordinates (lat, lng)
    ↓
Convert lat/lng to H3 index (resolution 11)
    ↓
Check if H3 index changed from last check
    ↓
If changed:
    ↓
    API call: GET /api/amisafe/aggregated?h3=<index>
    ↓
    Receive z-score for current hexagon
    ↓
    Compare z-score to user threshold
    ↓
    If z-score >= threshold:
        ↓
        Check last notification time (cooldown)
        ↓
        If cooldown expired:
            ↓
            Trigger push notification
            ↓
            Update last notification timestamp
    ↓
Repeat after interval
```

**Validation Checkpoints**:
- [ ] Service starts automatically after onboarding
- [ ] Service runs when app is closed/backgrounded
- [ ] GPS coordinates retrieved accurately (±10 meters)
- [ ] H3 index calculation matches server-side calculations
- [ ] API calls succeed even in background mode
- [ ] Battery drain acceptable (3-5% per hour target)
- [ ] Service respects user threshold setting
- [ ] Service respects cooldown period
- [ ] Service handles network failures gracefully (retry logic)
- [ ] Service stops if user disables background monitoring
- [ ] Service survives phone restart (auto-start on boot)

**iOS-Specific Validations**:
- [ ] Background location updates register with iOS API
- [ ] Service complies with iOS background execution limits
- [ ] Blue status bar indicator appears when tracking (iOS standard)
- [ ] Service doesn't get suspended by iOS (proper capability flags)

**Android-Specific Validations**:
- [ ] Foreground service notification shows "AmISafe is protecting you"
- [ ] Service marked as foreground (won't be killed by Android)
- [ ] Battery optimization exemption requested if needed
- [ ] Service handles Doze mode correctly

**Technical Dependencies**:
- `react-native-background-geolocation` or equivalent library
- H3 library available in React Native (uber/h3-js)
- API accessible from mobile devices (CORS configured)
- Push notification permissions granted

**Success Metrics**:
- Background service uptime >95%
- Location update accuracy >90%
- API call success rate >98%
- Battery drain <5% per hour
- User doesn't disable background monitoring (retention indicator)

---

#### 2.2 Push Notification Trigger

**User Action**: User enters higher-risk area, receives alert

**System Components**:
- `NotificationService.ts` (React Native)
- `react-native-push-notification` library
- iOS/Android notification system
- Deep linking handler

**Data Flow**:
```
BackgroundLocationService detects z-score >= threshold
    ↓
Call NotificationService.sendAlert()
    ↓
Generate notification payload:
    - Title: "⚠️ Entering Elevated Risk Area"
    - Body: "Current location has z-score 2.3. Tap to view safety map."
    - URL: "https://forseti.life/map?lat=X&lng=Y"
    - Sound: default alert sound
    - Badge: increment app badge count
    ↓
Schedule local notification (immediate)
    ↓
OS delivers notification to lock screen / notification center
    ↓
User sees notification
```

**Validation Checkpoints**:
- [ ] Notification appears within 5 seconds of trigger
- [ ] Title and body text are clear and actionable
- [ ] Alert sound plays (respects user's phone settings)
- [ ] Notification shows on lock screen
- [ ] Notification shows in notification center
- [ ] Badge count increments on app icon
- [ ] Notification includes z-score value
- [ ] Notification doesn't spam (cooldown works)
- [ ] Multiple notifications queue properly (don't overlap)
- [ ] Notifications work when phone is locked
- [ ] Notifications work in Do Not Disturb mode (critical alert option)

**iOS-Specific Validations**:
- [ ] Notification permission granted during onboarding
- [ ] Banner style displays correctly
- [ ] Critical alert option available for high-risk zones (>3.0)
- [ ] Notification appears on Apple Watch if paired

**Android-Specific Validations**:
- [ ] Notification channel created with high priority
- [ ] Heads-up notification displays
- [ ] LED/vibration pattern works
- [ ] Notification icon displays correctly

**Technical Dependencies**:
- Push notification permissions granted
- `react-native-push-notification` configured
- iOS APNs certificate valid (if using remote push)
- Android FCM configured (if using remote push)

**Success Metrics**:
- Notification delivery rate >99%
- Notification interaction rate >60%
- User doesn't disable notifications (retention indicator)

---

#### 2.3 Notification Interaction & Deep Linking

**User Action**: User taps notification

**System Components**:
- Deep linking handler
- Browser (opens forseti.life)
- Interactive map with URL parameters

**Data Flow**:
```
User taps notification
    ↓
OS triggers deep link URL handler
    ↓
Parse URL: https://forseti.life/map?lat=38.627&lng=-90.198
    ↓
If app is installed and supports deep links:
    ↓
    Open app to Map screen
    ↓
    Center map on provided lat/lng
    ↓
Else:
    ↓
    Open default browser
    ↓
    Navigate to forseti.life/map?lat=X&lng=Y
    ↓
    Load interactive map
    ↓
    Center map on provided coordinates
    ↓
Highlight current hexagon
    ↓
Show z-score popup automatically
```

**Validation Checkpoints**:
- [ ] Deep link URL format correct and parseable
- [ ] Browser opens if deep link fails
- [ ] Map loads at correct location (lat/lng from notification)
- [ ] Current hexagon is highlighted/emphasized
- [ ] Z-score popup shows automatically (no click required)
- [ ] Map zoom level appropriate (can see surrounding hexagons)
- [ ] User can interact with map after viewing current location
- [ ] Back button/navigation works correctly
- [ ] Works on both iOS and Android
- [ ] Works when app is closed, backgrounded, or foreground

**Technical Dependencies**:
- Deep linking configured in app (iOS Universal Links, Android App Links)
- Website supports URL parameters (lat, lng, zoom)
- Map JavaScript reads URL parameters on load
- H3 library converts lat/lng to hexagon for highlighting

**Success Metrics**:
- Deep link success rate >95%
- Map interaction after notification tap >70%
- User takes alternate route (GPS track analysis)

---

#### 2.4 User Response to Alert

**User Action**: User views map, decides to take alternate route

**System Components**:
- Interactive map (browser or app)
- User's spatial reasoning
- GPS tracking (optional for validation)

**Data Flow**:
```
User views current location z-score (e.g., 2.3)
    ↓
Scans surrounding hexagons visually
    ↓
Identifies alternate route with lower z-scores
    ↓
Makes decision to change route
    ↓
Starts walking on new route
    ↓
[Optional analytics]:
    ↓
    GPS tracks user path
    ↓
    Compare actual path vs. predicted path
    ↓
    Log "route deviation after alert" event
```

**Validation Checkpoints**:
- [ ] Map loads quickly enough for real-time decision (<3 seconds)
- [ ] Surrounding hexagons are visible (zoom level appropriate)
- [ ] Color coding makes alternate routes obvious
- [ ] User can zoom/pan to explore options
- [ ] Mobile map is usable while walking (large touch targets)
- [ ] Map shows street names for navigation
- [ ] User's current location updates in real-time (GPS marker)

**Optional Analytics Validations**:
- [ ] GPS tracking logs path after alert
- [ ] Path deviation detection algorithm works
- [ ] Analytics show correlation between alerts and route changes
- [ ] Privacy: GPS tracks are anonymized/aggregated

**Success Metrics**:
- Route deviation rate >50% after alert
- User reports feeling safer (qualitative survey)
- Repeat usage (user trusts the system)

---

### Phase 3: Conversion & Monetization

#### 3.1 Trial End Notification

**User Action**: Receives reminder that 7-day trial is ending

**System Components**:
- Trial expiration logic (server-side)
- Email notification system
- In-app notification

**Data Flow**:
```
Server cron job runs daily
    ↓
Query: SELECT * FROM users WHERE trial_end_date = NOW() + 1 DAY
    ↓
For each user:
    ↓
    Send email: "Your trial ends tomorrow"
    ↓
    Send in-app notification
    ↓
    Include: value recap, pricing, subscription CTA
    ↓
User opens email or app
    ↓
Clicks "Subscribe Now" button
    ↓
Navigate to billing page
```

**Validation Checkpoints**:
- [ ] Trial end date calculated correctly (7 days from signup)
- [ ] Reminder sent exactly 24 hours before expiration
- [ ] Email delivered successfully
- [ ] In-app notification appears (not just email)
- [ ] Messaging emphasizes value received during trial
- [ ] CTA button is prominent and clear
- [ ] User can extend trial if needed (customer support option)

**Technical Dependencies**:
- Cron job or scheduled task configured
- Email service working
- User timezone handling correct
- Trial status tracking accurate

**Success Metrics**:
- Email open rate >40%
- Click-through rate >20%
- Conversion rate from reminder >30%

---

#### 3.2 Subscription & Payment

**User Action**: Adds payment method, subscribes to premium

**System Components**:
- Payment gateway (Stripe, Apple IAP, Google Play Billing)
- Subscription management system
- User account database

**Data Flow**:
```
User navigates to subscription page
    ↓
Sees pricing: $4.99/month or $49.99/year
    ↓
Selects plan
    ↓
Enter payment method:
    - Credit card (Stripe)
    - Apple Pay / App Store (iOS)
    - Google Pay / Play Store (Android)
    ↓
Payment gateway validates card/account
    ↓
Create subscription in payment system
    ↓
Webhook: Payment successful
    ↓
Update user record: premium = true, subscription_id = X
    ↓
Send confirmation email
    ↓
Show success screen: "You're subscribed! Background monitoring continues."
```

**Validation Checkpoints**:
- [ ] Pricing displayed clearly ($4.99/month, $49.99/year)
- [ ] Annual plan shows savings ("Save $10/year")
- [ ] Payment form is secure (SSL, PCI compliant)
- [ ] Card validation works (Stripe Elements)
- [ ] Apple IAP works (iOS in-app purchase flow)
- [ ] Google Play Billing works (Android in-app purchase)
- [ ] Subscription activated immediately after payment
- [ ] Background monitoring continues uninterrupted
- [ ] Confirmation email sent with receipt
- [ ] User can manage subscription (cancel, change plan)
- [ ] Refund policy displayed clearly

**Technical Dependencies**:
- Stripe account configured (if using Stripe)
- Apple Developer account with IAP configured
- Google Play Developer account with subscriptions enabled
- Webhook endpoints implemented for payment events
- Database fields for subscription tracking

**Success Metrics**:
- Payment success rate >95%
- Free-to-paid conversion rate >2% (minimum), >5% (stretch)
- Churn rate <5% per month

---

#### 3.3 Subscription Management

**User Action**: User manages subscription (cancel, reactivate, change plan)

**System Components**:
- Account settings page
- Payment gateway subscription management API
- Database subscription status

**Data Flow**:
```
User navigates to "Manage Subscription"
    ↓
Fetches current subscription status from payment gateway
    ↓
Displays: Plan type, next billing date, payment method
    ↓
User clicks "Cancel Subscription"
    ↓
Show confirmation: "You'll lose background monitoring. Cancel anyway?"
    ↓
If confirmed:
    ↓
    API call to payment gateway: Cancel subscription
    ↓
    Subscription ends at period end (not immediate)
    ↓
    Update database: subscription_status = "cancelled"
    ↓
    Send email: "Your subscription has been cancelled"
    ↓
    Show retention offer: "Come back anytime!"
```

**Validation Checkpoints**:
- [ ] Subscription status loads correctly
- [ ] Cancel button works
- [ ] Cancellation doesn't take effect immediately (access until period end)
- [ ] User can reactivate before period ends
- [ ] Reactivation works smoothly (no new trial needed)
- [ ] Downgrade to free version happens automatically at period end
- [ ] Background monitoring stops after cancellation (grace period)
- [ ] User data retained for reactivation
- [ ] Refund requests handled (manual or automatic policy)

**Technical Dependencies**:
- Payment gateway API supports subscription management
- Webhook handles subscription cancellation events
- Background service checks subscription status before running

**Success Metrics**:
- Cancellation rate <10% per month
- Reactivation rate >20% of cancellations
- Voluntary churn rate <5% per month

---

### Phase 4: Retention & Advocacy

#### 4.1 Ongoing Value Delivery

**User Action**: User continues daily life with ambient background monitoring

**System Components**:
- Background location service (always running)
- Push notifications (periodic alerts)
- Map website (occasional checks)

**Data Flow**:
```
[Continuous loop - Month 2, 3, 4+]

User goes about daily activities
    ↓
Background service monitors location silently
    ↓
Alerts triggered 0-3 times per month (varies by user behavior)
    ↓
User adjusts routes based on alerts
    ↓
Feels safer in daily activities
    ↓
Occasionally checks map when planning new destinations
    ↓
Shares with friends organically
```

**Validation Checkpoints**:
- [ ] Background service remains stable over weeks/months
- [ ] Battery drain stays acceptable long-term
- [ ] API uptime >99.9%
- [ ] Data quality remains accurate (ETL pipeline running)
- [ ] User doesn't disable background monitoring
- [ ] User doesn't uninstall app
- [ ] User renews subscription each month/year

**Success Metrics**:
- Day 30 retention >10% (minimum), >20% (stretch)
- Day 90 retention >5%
- LTV:CAC ratio >3:1
- NPS score >30

---

#### 4.2 Referral & Advocacy

**User Action**: User recommends Forseti to friends, family, coworkers

**System Components**:
- Referral tracking system (optional)
- Social sharing features
- App Store reviews
- Organic word-of-mouth

**Data Flow**:
```
User has positive experience
    ↓
Mentions to friend: "I use this app that alerts me about unsafe areas"
    ↓
Friend: "What's it called?"
    ↓
User: "Forseti - check out forseti.life or AmISafe app"
    ↓
Friend searches, discovers, signs up
    ↓
[Optional referral tracking]:
    ↓
    User shares referral link
    ↓
    Friend clicks link (cookie/tracking code)
    ↓
    Friend signs up
    ↓
    Referrer gets credit (free month, discount)
```

**Validation Checkpoints**:
- [ ] Referral links generate correctly
- [ ] Tracking cookies work (30-day window)
- [ ] Referral credit applied automatically
- [ ] Referrer notified when friend signs up
- [ ] Social sharing works (WhatsApp, SMS, email)
- [ ] App Store review prompt appears (after positive experiences)
- [ ] Review link navigates to correct app store page

**Success Metrics**:
- NPS score >30 (promoters outnumber detractors)
- Referral rate >10% of users
- Viral coefficient >0.5 (each user brings 0.5 new users)
- App Store rating >4.0 stars

---

## Data Pipeline Validation

### ETL Process (Crime Data → H3 Hexagons)

**Data Flow**:
```
[Daily automated process]

Crime incident CSV from St. Louis PD
    ↓
Download to /h3-geolocation/data/raw/
    ↓
Python ETL script: database/etl/process_crime_data.py
    ↓
For each incident:
    ↓
    Extract lat, lng, crime_type, date
    ↓
    Convert lat/lng to H3 index (resolution 11)
    ↓
    Insert into incidents table
    ↓
Aggregation step:
    ↓
    GROUP BY h3_index, COUNT(*) as crime_count
    ↓
    Calculate city-wide mean and std dev
    ↓
    For each hexagon: z_score = (count - mean) / std_dev
    ↓
    Update hexagon_stats table
    ↓
API reads from hexagon_stats table
```

**Validation Checkpoints**:
- [ ] CSV file downloads successfully (cron job)
- [ ] CSV format hasn't changed (schema validation)
- [ ] Lat/lng values are valid (within St. Louis bounds)
- [ ] H3 index calculation correct (spot-check samples)
- [ ] Duplicate incidents deduplicated (same location + time)
- [ ] Aggregation counts match (SUM(crime_count) = total incidents)
- [ ] Z-score calculation accurate (mean and std dev correct)
- [ ] Database updates complete without errors
- [ ] Old data archived (data older than 2 years)
- [ ] Process completes within time window (overnight)

**Technical Dependencies**:
- Python 3.9+ with required libraries (h3, pandas, mysql-connector)
- MySQL database accessible
- Sufficient disk space for raw CSV files
- Cron job or scheduler configured

**Success Metrics**:
- ETL success rate >99%
- Data freshness <24 hours
- Zero data quality incidents per month

---

## Phase 2: NFR Questionnaire Validation (CDC Requirements)

### 2.1 Section 1: Demographics

**User Action**: Fills out demographic information including race/ethnicity

**System Components**:
- NFRQuestionnaireSection1Form.php
- nfr_questionnaire table (race_ethnicity JSON column)

**Data Flow**:
```
User selects demographics →
Form validation →
Save to race_ethnicity JSON →
Store in nfr_questionnaire.race_ethnicity
```

**Validation Checkpoints**:
- [ ] All CDC-required race options available:
  - [ ] White
  - [ ] Black/African American
  - [ ] Asian
  - [ ] American Indian/Alaska Native
  - [ ] Native Hawaiian/Pacific Islander
  - [ ] **Middle Eastern/North African** (added Jan 2026)
  - [ ] Hispanic/Latino ethnicity option
- [ ] Multiple selection allowed (checkboxes)
- [ ] Data saves as JSON array
- [ ] Values load correctly on form re-entry

**Database Verification**:
```sql
SELECT uid, race_ethnicity FROM nfr_questionnaire WHERE uid = ?;
-- Expected: JSON array like ["white","middle_eastern_north_african"]
```

---

### 2.2 Section 2: Work History & Incident Types

**User Action**: Records fire department employment and incident response

**System Components**:
- NFRQuestionnaireSection2Form.php
- nfr_fire_departments table
- nfr_exposures table (incident types)

**Data Flow**:
```
User adds department →
Add job titles →
Select incident types responded to →
Save to nfr_exposures
```

**Validation Checkpoints**:
- [ ] All CDC-required incident types available:
  - [ ] Structure fires
  - [ ] Vehicle fires
  - [ ] Wildland fires
  - [ ] Hazmat incidents
  - [ ] Medical/EMS calls
  - [ ] **Rubbish/dumpster fires** (added Jan 2026)
  - [ ] WUI fires
  - [ ] Prescribed burns
  - [ ] Fire investigations
  - [ ] Training exercises
  - [ ] Other incidents
- [ ] Frequency tracking for each incident type
- [ ] Multiple departments supported (repeating fields)
- [ ] Date ranges validated (start < end)

**Database Verification**:
```sql
SELECT incident_type, frequency FROM nfr_exposures WHERE uid = ? AND incident_type = 'rubbish_dumpster';
```

---

### 2.3 Section 6: PPE & Protective Equipment

**User Action**: Records PPE usage patterns across different fire scenarios

**System Components**:
- NFRQuestionnaireSection6Form.php
- nfr_questionnaire table (multiple PPE columns)

**Data Flow**:
```
User selects PPE usage for scenario →
Enters year started using →
Optionally checks "Always done this" →
Save to scenario-specific columns
```

**Validation Checkpoints**:
- [ ] All 8 PPE equipment types present:
  - [ ] SCBA
  - [ ] Gloves
  - [ ] Hood
  - [ ] Helmet
  - [ ] Turnout coat
  - [ ] Turnout pants
  - [ ] Boots
  - [ ] PASS device
- [ ] All 6+ CDC-required scenarios tracked:
  - [ ] **Interior structural attack** (added Jan 2026)
  - [ ] **Exterior structural attack** (added Jan 2026)
  - [ ] **Vehicle fires** (added Jan 2026)
  - [ ] **Brush/vegetation fires** (added Jan 2026)
  - [ ] **Wildland suppression** (added Jan 2026)
  - [ ] **Fire investigations** (added Jan 2026)
  - [ ] **WUI fires** (added Jan 2026)
  - [ ] Prescribed burns
  - [ ] Overhaul operations
  - [ ] Training
- [ ] "Always done this" checkbox for each equipment type (8 checkboxes)
- [ ] Year started field (number input, 1900-current year)
- [ ] Conditional logic: if "always done this" checked, year_started auto-fills

**Database Verification**:
```sql
-- Check new PPE scenario columns (added via nfr_update_9021)
SELECT 
  scba_interior_structural_attack_year_started,
  scba_exterior_structural_attack_year_started,
  scba_vehicle_fires_year_started,
  respirator_brush_veg_fires_year_started,
  respirator_wildland_suppression_year_started,
  respirator_fire_investigations_year_started,
  respirator_wui_fires_year_started
FROM nfr_questionnaire WHERE uid = ?;

-- Check "always done this" checkboxes (added via nfr_update_9022)
SELECT 
  scba_interior_structural_attack_always_used,
  scba_exterior_structural_attack_always_used,
  scba_vehicle_fires_always_used,
  respirator_brush_veg_fires_always_used,
  respirator_wildland_suppression_always_used,
  respirator_fire_investigations_always_used,
  respirator_wui_fires_always_used,
  respirator_prescribed_burns_always_used
FROM nfr_questionnaire WHERE uid = ?;
-- Expected: 1 or NULL for each field
```

---

### 2.4 Section 8: Health Conditions & Family History

**User Action**: Reports health conditions and family cancer history

**System Components**:
- NFRQuestionnaireSection8Form.php
- nfr_questionnaire table (health fields)
- **nfr_family_cancer_history table** (added Jan 2026)

**Data Flow**:
```
User reports health conditions →
If family history: Add family member (AJAX) →
Select relationship, cancer type, age at diagnosis →
Save to nfr_family_cancer_history table
```

**Validation Checkpoints**:
- [ ] Personal cancer diagnosis tracking
- [ ] Non-cancer health conditions (heart disease, diabetes, etc.)
- [ ] **Family cancer history with repeating fields**:
  - [ ] Relationship dropdown (mother, father, brother, sister, son, daughter)
  - [ ] Cancer type text field
  - [ ] Age at diagnosis (number 0-120)
  - [ ] "Add Another Family Member" button (AJAX)
  - [ ] Remove family member functionality
- [ ] Data saves to separate nfr_family_cancer_history table
- [ ] Multiple family members supported
- [ ] Form loads existing family history on re-entry

**Database Verification**:
```sql
-- Check family cancer history table (created via nfr_update_9023)
SELECT 
  relationship,
  cancer_type,
  age_at_diagnosis,
  created,
  updated
FROM nfr_family_cancer_history 
WHERE uid = ?
ORDER BY created;

-- Verify table structure
DESCRIBE nfr_family_cancer_history;
-- Expected columns: id, uid, relationship, cancer_type, age_at_diagnosis, created, updated
```

---

### 2.5 Section 9: Lifestyle & Sleep Tracking

**User Action**: Reports tobacco use, alcohol, physical activity, and sleep patterns

**System Components**:
- NFRQuestionnaireSection9Form.php
- nfr_questionnaire table (smoking_history JSON, sleep columns)

**Data Flow**:
```
User selects tobacco types used →
Enters start/stop ages for each type →
Reports sleep hours, quality, disorders →
Save to smoking_history JSON + sleep columns
```

**Validation Checkpoints - Tobacco**:
- [ ] All 5 CDC-required tobacco types tracked:
  - [ ] Cigarettes (existing)
  - [ ] **Cigars** (added Jan 2026)
  - [ ] **Pipes** (added Jan 2026)
  - [ ] **E-cigarettes/vaping** (added Jan 2026)
  - [ ] **Smokeless tobacco** (added Jan 2026)
- [ ] For each tobacco type:
  - [ ] Ever used status (never/former/current)
  - [ ] Age started (conditional field)
  - [ ] Age stopped (conditional field, only if former)
- [ ] Cigarettes include additional fields:
  - [ ] Cigarettes per day
- [ ] Conditional field visibility (#states)
- [ ] Data stores in expanded smoking_history JSON

**Validation Checkpoints - Sleep** (added Jan 2026):
- [ ] **Sleep hours per night** (number field, 0.5 increments, 0-24 range)
- [ ] **Sleep quality** (select: excellent/good/fair/poor/very poor)
- [ ] **Sleep disorders** (checkboxes):
  - [ ] Sleep apnea
  - [ ] Insomnia
  - [ ] Restless leg syndrome
  - [ ] Narcolepsy
  - [ ] Shift work sleep disorder
  - [ ] Other
  - [ ] None (exclusive option)
- [ ] Data saves to dedicated columns (not JSON)

**Database Verification**:
```sql
-- Check expanded smoking_history JSON
SELECT smoking_history FROM nfr_questionnaire WHERE uid = ?;
-- Expected JSON structure:
-- {
--   "smoking_status": "former",
--   "smoking_age_started": 18,
--   "smoking_age_stopped": 30,
--   "cigarettes_per_day": 10,
--   "cigars_ever_used": "never",
--   "cigars_age_started": "",
--   "cigars_age_stopped": "",
--   "pipes_ever_used": "current",
--   "pipes_age_started": 25,
--   "pipes_age_stopped": "",
--   "ecigs_ever_used": "former",
--   "ecigs_age_started": 35,
--   "ecigs_age_stopped": 40,
--   "smokeless_ever_used": "never",
--   "smokeless_age_started": "",
--   "smokeless_age_stopped": ""
-- }

-- Check sleep tracking columns (added via nfr_update_9024)
SELECT 
  sleep_hours_per_night,
  sleep_quality,
  sleep_disorders
FROM nfr_questionnaire WHERE uid = ?;
-- Expected: 
-- sleep_hours_per_night: float (e.g., 7.5)
-- sleep_quality: varchar (e.g., 'good')
-- sleep_disorders: JSON array (e.g., ["sleep_apnea","insomnia"])

-- Verify column types
SHOW COLUMNS FROM nfr_questionnaire LIKE 'sleep%';
```

---

## Phase 3: Validation Dashboard Testing

### 3.1 NFR Validation Controller

**System Components**:
- NFRValidationController.php
- Automated test data generation
- Field verification methods

**Validation Checkpoints**:
- [ ] Dashboard accessible at /nfr/validation
- [ ] All test data generation methods updated:
  - [ ] generateMaxValuesData() includes new fields
  - [ ] generateMinValuesData() includes new fields
  - [ ] generateYesMinimalData() includes new fields
- [ ] Database verification checks new tables:
  - [ ] nfr_family_cancer_history records
  - [ ] sleep_hours_per_night, sleep_quality, sleep_disorders
  - [ ] Expanded smoking_history JSON with 5 tobacco types
  - [ ] PPE always_used checkboxes (8 fields)
  - [ ] PPE scenario year_started columns (7+ fields)
- [ ] Validation reports show:
  - [ ] Family cancer history count
  - [ ] Sleep tracking completeness
  - [ ] Tobacco types used (all 5 types)
  - [ ] PPE "always done this" practices count

**Test Commands**:
```bash
# Run validation dashboard
cd /home/keithaumiller/forseti.life/sites/forseti
vendor/bin/drush cr

# Access dashboard
# Navigate to: /nfr/validation

# Generate test data with max values
# Should include:
# - 2 family cancer history records
# - Sleep: 8.5 hours, excellent quality, no disorders
# - All 5 tobacco types with ages
# - Multiple PPE always_used checkboxes

# Generate test data with min values
# Should include:
# - 0 family cancer history records
# - Sleep: 4.0 hours, poor quality, multiple disorders
# - No tobacco use (all "never")
# - No PPE always_used checkboxes
```

---

## System Health Monitoring

### Real-Time Monitoring Dashboards

**Metrics to Track**:

1. **API Performance**
   - Response time (p50, p95, p99)
   - Request rate (requests per minute)
   - Error rate (4xx, 5xx responses)
   - Uptime percentage

2. **Database Performance**
   - Query execution time
   - Connection pool utilization
   - Disk space usage
   - Index performance

3. **Background Service Health** (from app telemetry)
   - Service uptime percentage
   - Location update frequency
   - API call success rate from mobile
   - Battery drain average

4. **User Engagement**
   - Daily active users (DAU)
   - Monthly active users (MAU)
   - DAU/MAU ratio (stickiness)
   - Session duration
   - Map interactions per session

5. **Business Metrics**
   - New signups per day
   - Trial starts
   - Free-to-paid conversion rate
   - Churn rate
   - Monthly recurring revenue (MRR)

**Validation Checkpoints**:
- [ ] Monitoring dashboard accessible to team
- [ ] Alerts configured for anomalies
- [ ] Data collected from all components
- [ ] Historical data retained for trends
- [ ] Real-time updates (1-5 minute latency)

**Tools**:
- Application monitoring: New Relic, Datadog, or self-hosted Grafana
- Error tracking: Sentry
- User analytics: Mixpanel, Amplitude, or self-hosted
- Uptime monitoring: Pingdom, UptimeRobot

---

## Testing Roadmap

### 1. Unit Tests

**Components to Test**:
- [ ] H3 index calculation (lat/lng → H3)
- [ ] Z-score calculation (crime count → z-score)
- [ ] Risk level categorization (z-score → low/moderate/elevated/high)
- [ ] Background service interval logic
- [ ] Notification cooldown logic
- [ ] Deep link URL parsing

**NFR Questionnaire Unit Tests** (added Jan 2026):
- [ ] Race/ethnicity JSON array serialization
- [ ] Family cancer history AJAX add/remove functionality
- [ ] Tobacco type conditional field visibility (#states)
- [ ] Sleep hours validation (0.5 increments, 0-24 range)
- [ ] PPE "always done this" auto-fill year_started logic
- [ ] Smoking history JSON expansion (5 tobacco types)
- [ ] Sleep disorders array filtering (mutually exclusive options)

**Framework**: Jest (React Native), pytest (Python), PHPUnit (Drupal)

---

### 2. Integration Tests

**Workflows to Test**:
- [ ] User signup → email verification → login
- [ ] Map click → API call → popup display
- [ ] Location update → z-score fetch → notification trigger
- [ ] Notification tap → deep link → map opens
- [ ] Trial end → reminder → subscription → payment

**NFR Questionnaire Integration Tests** (added Jan 2026):
- [ ] Section 1 form submit → race_ethnicity saves to database
- [ ] Section 6 PPE form → multiple scenario columns updated
- [ ] Section 8 family cancer → nfr_family_cancer_history table inserts
- [ ] Section 9 lifestyle → smoking_history JSON + sleep columns update
- [ ] Multi-section navigation → all data persists across sections
- [ ] Save & Exit → partial completion data preserved
- [ ] Final submission → questionnaire_completed flag set

**Framework**: Cypress (web), Detox (React Native), Drupal Functional Tests

---

### 3. End-to-End Tests

**Full User Journeys to Test**:
- [ ] Discovery → Signup → Download → Onboarding → First Alert
- [ ] Trial user → Conversion → Subscription management
- [ ] Power user → Referral → Review

**NFR Enrollment E2E Tests** (added Jan 2026):
- [ ] New firefighter → Complete all 9 sections → Submit questionnaire
- [ ] Firefighter with family cancer history → Add 3 family members → Verify table records
- [ ] Firefighter using all tobacco types → Verify smoking_history JSON structure
- [ ] Firefighter with sleep disorders → Verify sleep tracking columns
- [ ] NFR Admin → Validation dashboard → Verify all new fields display
- [ ] Researcher → Data export → Verify new fields in CSV export

**Framework**: Manual testing + automated scripts

---

### 4. Load Testing

**Scenarios to Test**:
- [ ] 100 concurrent API requests (simulate 100 users checking map)
- [ ] 1,000 concurrent background location updates
- [ ] Database query performance with 1M+ incidents
- [ ] API rate limiting under stress

**Framework**: JMeter, k6, or Locust

---

### 5. User Acceptance Testing (UAT)

**Beta Tester Feedback**:
- [ ] Recruit 50-100 beta testers from target demographic
- [ ] Provide onboarding instructions
- [ ] Collect feedback surveys after 1 week, 1 month
- [ ] Track metrics: activation, retention, NPS
- [ ] Iterate based on feedback

---

## Validation Checklist (Go/No-Go Criteria)

### Pre-Launch Checklist

**Infrastructure**:
- [ ] Website (forseti.life) deployed and accessible
- [ ] API endpoints responding with <500ms latency
- [ ] Database populated with St. Louis crime data
- [ ] ETL pipeline running daily without errors
- [ ] SSL certificates valid
- [ ] Backups configured (daily database backups)

**Mobile App**:
- [ ] iOS app approved and live on App Store
- [ ] Android app approved and live on Google Play
- [ ] Push notifications working on both platforms
- [ ] Background location tracking stable
- [ ] Battery drain acceptable (<5% per hour)

**Payment System**:
- [ ] Stripe account active (or Apple/Google IAP configured)
- [ ] Subscription plans created ($4.99/month, $49.99/year)
- [ ] Webhooks tested and working
- [ ] Refund policy documented

**Legal & Compliance**:
- [ ] Privacy policy published
- [ ] Terms of service published
- [ ] GDPR compliance (if applicable)
- [ ] Data retention policy defined
- [ ] User data deletion process implemented

**Monitoring**:
- [ ] Error tracking configured (Sentry)
- [ ] Uptime monitoring active
- [ ] User analytics tracking events
- [ ] Alerts configured for critical failures

---

### Post-Launch Monitoring (First 30 Days)

**Week 1 Checks**:
- [ ] No critical bugs reported
- [ ] API uptime >99%
- [ ] Signup flow working (conversion >50%)
- [ ] First alerts triggered successfully
- [ ] No widespread battery drain complaints

**Week 2-4 Checks**:
- [ ] Day 7 retention >20%
- [ ] Day 30 retention >10%
- [ ] No payment failures
- [ ] Background service stable (no crashes)
- [ ] ETL pipeline running without issues

**Red Flags to Watch**:
- ❌ Activation rate <30% (onboarding friction)
- ❌ Day 7 retention <10% (value not delivered)
- ❌ Multiple reports of inaccurate data (trust broken)
- ❌ High churn rate >15% per month (product not sticky)
- ❌ App uninstall rate >50% (critical failure)

---

## CDC Requirements Compliance Summary (January 2026 Update)

### Database Schema Changes

**New Tables Created**:
1. `nfr_family_cancer_history` (nfr_update_9023)
   - Tracks immediate family cancer diagnoses
   - Fields: id, uid, relationship, cancer_type, age_at_diagnosis, created, updated
   - Replaces JSON storage with structured table for better querying

**New Columns Added**:
1. PPE Scenarios (nfr_update_9021) - 7 columns:
   - scba_interior_structural_attack_year_started
   - scba_exterior_structural_attack_year_started
   - scba_vehicle_fires_year_started
   - respirator_brush_veg_fires_year_started
   - respirator_wildland_suppression_year_started
   - respirator_fire_investigations_year_started
   - respirator_wui_fires_year_started

2. PPE "Always Used" Flags (nfr_update_9022) - 8 columns:
   - scba_interior_structural_attack_always_used
   - scba_exterior_structural_attack_always_used
   - scba_vehicle_fires_always_used
   - respirator_brush_veg_fires_always_used
   - respirator_wildland_suppression_always_used
   - respirator_fire_investigations_always_used
   - respirator_wui_fires_always_used
   - respirator_prescribed_burns_always_used

3. Sleep Tracking (nfr_update_9024) - 3 columns:
   - sleep_hours_per_night (float)
   - sleep_quality (varchar 50)
   - sleep_disorders (text - JSON array)

**JSON Field Expansions**:
1. `smoking_history` - Expanded from 4 to 16 fields:
   - Cigarettes (existing): smoking_status, smoking_age_started, smoking_age_stopped, cigarettes_per_day
   - Cigars: cigars_ever_used, cigars_age_started, cigars_age_stopped
   - Pipes: pipes_ever_used, pipes_age_started, pipes_age_stopped
   - E-cigarettes: ecigs_ever_used, ecigs_age_started, ecigs_age_stopped
   - Smokeless: smokeless_ever_used, smokeless_age_started, smokeless_age_stopped

2. `race_ethnicity` - Added option:
   - middle_eastern_north_african

### Form Modifications

**Section 1 (NFRQuestionnaireSection1Form.php)**:
- Added "Middle Eastern or North African" to race_ethnicity checkbox options

**Section 2 (NFRQuestionnaireSection2Form.php)**:
- Added "Rubbish/dumpster fires" to incident_type options

**Section 6 (NFRQuestionnaireSection6Form.php)**:
- Added 7 PPE scenario fields (year_started for each)
- Added 8 "Always done this" checkboxes with AJAX functionality
- Conditional logic: auto-populate year_started when "always used" checked

**Section 8 (NFRQuestionnaireSection8Form.php)**:
- Added repeating family cancer history section
- AJAX "Add Another Family Member" button
- Fields: relationship (select), cancer_type (text), age_at_diagnosis (number)
- Helper methods: saveFamilyCancerHistory(), addFamilyCancer(), updateFamilyCancerFields()

**Section 9 (NFRQuestionnaireSection9Form.php)**:
- Added 4 tobacco types × 3 fields = 12 new fields
- Each type: ever_used (radio), age_started (number), age_stopped (number)
- Added 3 sleep tracking fields: hours (0.5 increments), quality (select), disorders (checkboxes)
- Conditional field visibility using #states API

### Validation Controller Updates

**NFRValidationController.php Changes**:
- Database verification checks nfr_family_cancer_history table
- Tracks all 5 tobacco types (not just cigarettes)
- Validates sleep_hours_per_night, sleep_quality, sleep_disorders
- Checks PPE always_used checkboxes (8 fields)
- Test data generation includes all new fields:
  - Max values: 2 family members, all tobacco types, 8.5 hours sleep
  - Min values: 0 family members, no tobacco, 4 hours sleep with disorders
  - Yes minimal: 1 family member, e-cigarettes only, fair sleep

### Testing Priorities

**High Priority** (User-Facing):
1. ✅ Family cancer history: Add 3 members, verify table inserts, test delete
2. ✅ Sleep tracking: Test 0.5 hour increments, verify all disorder options
3. ✅ Tobacco types: Test conditional fields, verify JSON structure
4. ✅ PPE always_used: Test auto-fill year_started, verify all 8 checkboxes

**Medium Priority** (Admin):
5. Update validation dashboard to display new field counts
6. Test CSV export includes new columns
7. Verify data quality reports track new fields

**Low Priority** (Documentation):
8. Update user help documentation for new sections
9. Create training videos for complex workflows (family history AJAX)

### Compliance Status

**CDC NFR Requirements** (as of January 26, 2026):
- ✅ Core demographics: 100% complete
- ✅ Incident types: 100% complete (13+ types)
- ✅ PPE scenarios: 100% complete (8 equipment × 10+ scenarios)
- ✅ Family health history: 100% complete (structured table)
- ✅ Lifestyle factors: 100% complete (5 tobacco types + sleep)

**Remaining Gaps** (Infrastructure):
- ❌ Text message notifications (planned Q2 2026)
- ❌ Multi-factor authentication (planned Q2 2026)
- ❌ UUID identifiers (using integer IDs)
- ❌ External system integrations (cancer registry, NDI)

---

## Iteration & Optimization Roadmap

### Q1 2025: MVP Validation
- Launch to first 100 users
- Validate core loop: alert → action → safety
- Fix critical bugs
- Optimize onboarding (goal: 40%+ activation)

### Q2 2025: Growth & Retention
- Implement referral program
- Add saved locations feature
- Optimize battery usage
- Expand to 500+ users
- Iterate based on feedback

### Q3 2025: Monetization Optimization
- Test pricing tiers ($3.99, $4.99, $5.99)
- Test annual plan discount
- Add family plan option
- Improve conversion funnel (goal: 5% free-to-paid)

### Q4 2025: Scale & Expansion
- Add multi-city support (Chicago, NYC)
- Implement crime type filtering
- Build B2B white-label version
- Target 5,000+ users

---

## Related Documents

- [MVP Definition](./mvp/mvp-definition.md) - Product scope and features
- [User Journey](./user-journey/sarah-urban-commuter.md) - Detailed persona walkthrough
- [Architecture Documentation](/docs/ARCHITECTURE.md) - Technical implementation
- [Success Metrics](./metrics/key-metrics-dashboard.md) - KPI tracking

---

**Next Steps**:
1. Review this process flow with engineering team
2. Assign validation checkpoints to team members
3. Set up monitoring dashboards
4. Schedule weekly review meetings during beta
5. Create bug tracking board for issues discovered
