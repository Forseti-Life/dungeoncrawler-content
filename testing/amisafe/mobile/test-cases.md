# AmISafe Mobile Application - Test Cases

**Created:** December 1, 2025  
**Version:** 1.0  
**Coverage:** Complete mobile app and backend integration testing

---

## Test Categories

1. [Authentication & User Management](#1-authentication--user-management)
2. [H3 Geospatial Services](#2-h3-geospatial-services)
3. [Background Location Monitoring](#3-background-location-monitoring)
4. [API Integration](#4-api-integration)
5. [Crime Map Visualization](#5-crime-map-visualization)
6. [Home Dashboard](#6-home-dashboard)
7. [Safety Screen](#7-safety-screen)
8. [Settings & Preferences](#8-settings--preferences)
9. [Notifications](#9-notifications)
10. [Storage & Persistence](#10-storage--persistence)
11. [Permissions](#11-permissions)
12. [Error Handling & Edge Cases](#12-error-handling--edge-cases)

---

## 1. Authentication & User Management

### TEST-AUTH-001: CSRF Token Retrieval
- **Component:** DrupalAuthService.getCsrfToken()
- **Backend:** GET /session/token
- **Priority:** Critical
- **Steps:**
  1. Call getCsrfToken() method
  2. Verify HTTP 200 response
  3. Verify token is non-empty string
  4. Verify token stored in service instance
- **Expected:** Valid CSRF token returned and stored
- **Status:** ✅ Implemented

### TEST-AUTH-002: User Login - Valid Credentials
- **Component:** DrupalAuthService.login()
- **Backend:** POST /user/login
- **Priority:** Critical
- **Steps:**
  1. Call login() with valid username/password
  2. Verify CSRF token retrieved first
  3. Verify session cookies received
  4. Verify currentUser object populated
  5. Verify session stored in AsyncStorage
- **Expected:** Successful authentication, user object returned
- **Status:** ✅ Implemented

### TEST-AUTH-003: User Login - Invalid Credentials
- **Component:** DrupalAuthService.login()
- **Backend:** POST /user/login
- **Priority:** Critical
- **Steps:**
  1. Call login() with invalid username/password
  2. Verify error response received
  3. Verify currentUser remains null
  4. Verify no session stored
- **Expected:** Login fails with appropriate error message
- **Status:** ✅ Implemented

### TEST-AUTH-004: User Registration
- **Component:** DrupalAuthService.register()
- **Backend:** POST /user/register
- **Priority:** High
- **Steps:**
  1. Call register() with new user data
  2. Verify all required fields sent (username, email, password)
  3. Verify CSRF token included in request
  4. Verify successful user creation response
  5. Verify user automatically logged in
- **Expected:** New user created and authenticated
- **Status:** ✅ Implemented

### TEST-AUTH-005: User Logout
- **Component:** DrupalAuthService.logout()
- **Backend:** POST /user/logout
- **Priority:** High
- **Steps:**
  1. Ensure user logged in
  2. Call logout() method
  3. Verify session cleared from AsyncStorage
  4. Verify currentUser set to null
  5. Verify session cookies cleared
- **Expected:** User logged out, session destroyed
- **Status:** ✅ Implemented

### TEST-AUTH-006: Session Persistence
- **Component:** DrupalAuthService.initializeFromStorage()
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Login user successfully
  2. Close app completely
  3. Reopen app
  4. Verify session restored from AsyncStorage
  5. Verify currentUser populated
  6. Verify authenticated API calls work
- **Expected:** User remains logged in across app restarts
- **Status:** ✅ Implemented

### TEST-AUTH-007: Authentication State Check
- **Component:** DrupalAuthService.isAuthenticated()
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Check isAuthenticated() when logged out (should be false)
  2. Login user
  3. Check isAuthenticated() when logged in (should be true)
  4. Logout user
  5. Check isAuthenticated() again (should be false)
- **Expected:** Correct authentication state returned
- **Status:** ✅ Implemented

### TEST-AUTH-008: Demo Mode Fallback
- **Component:** DrupalAuthService.login() demo mode
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. Configure app for demo mode
  2. Attempt login with any credentials
  3. Verify demo user created
  4. Verify app functions without real backend
- **Expected:** Demo authentication works for development
- **Status:** ✅ Implemented

---

## 2. H3 Geospatial Services

### TEST-H3-001: GPS to H3 Conversion (Resolution 13)
- **Component:** H3LocationService.convertToH3()
- **Backend:** N/A (Local calculation)
- **Priority:** Critical
- **Steps:**
  1. Input valid GPS coordinates (39.9526, -75.1652)
  2. Call convertToH3() with resolution 13
  3. Verify H3 index returned (15-character string)
  4. Verify index format matches expected pattern
  5. Verify conversion logged for debugging
- **Expected:** Correct H3 Level 13 index (44m² precision)
- **Status:** ✅ Implemented

### TEST-H3-002: H3 to GPS Conversion
- **Component:** H3LocationService.convertFromH3()
- **Backend:** N/A (Local calculation)
- **Priority:** High
- **Steps:**
  1. Input valid H3 index
  2. Call convertFromH3()
  3. Verify {lat, lng} object returned
  4. Verify coordinates are valid numbers
  5. Verify coordinates match expected hexagon center
- **Expected:** Correct lat/lng coordinates for hexagon center
- **Status:** ✅ Implemented

### TEST-H3-003: Get Neighboring Hexagons (1 Ring)
- **Component:** H3LocationService.getNeighbors()
- **Backend:** N/A (Local calculation)
- **Priority:** High
- **Steps:**
  1. Input valid H3 index
  2. Call getNeighbors() with ringSize=1
  3. Verify 7 hexagons returned (6 neighbors + center)
  4. Verify all indexes are valid H3 strings
  5. Verify center hexagon included in results
- **Expected:** 7 H3 indexes (center + 6 adjacent hexagons)
- **Status:** ✅ Implemented

### TEST-H3-004: Get Neighboring Hexagons (2 Rings)
- **Component:** H3LocationService.getNeighbors()
- **Backend:** N/A (Local calculation)
- **Priority:** Medium
- **Steps:**
  1. Input valid H3 index
  2. Call getNeighbors() with ringSize=2
  3. Verify 19 hexagons returned (2 rings + center)
  4. Verify all indexes are valid H3 strings
- **Expected:** 19 H3 indexes (center + 2 rings of neighbors)
- **Status:** ✅ Implemented

### TEST-H3-005: Multi-Resolution Conversion
- **Component:** H3LocationService.convertToH3()
- **Backend:** N/A (Local calculation)
- **Priority:** High
- **Steps:**
  1. Input same GPS coordinates
  2. Convert to resolution 5 (city-wide)
  3. Convert to resolution 8 (neighborhood)
  4. Convert to resolution 11 (block-level)
  5. Convert to resolution 13 (user tracking)
  6. Verify different H3 indexes for each resolution
  7. Verify all indexes represent same location
- **Expected:** Valid H3 indexes at all resolutions
- **Status:** ✅ Implemented

### TEST-H3-006: Invalid Coordinate Handling
- **Component:** H3LocationService.convertToH3()
- **Backend:** N/A (Local calculation)
- **Priority:** Medium
- **Steps:**
  1. Input invalid latitude (>90 or <-90)
  2. Input invalid longitude (>180 or <-180)
  3. Input null/undefined coordinates
  4. Verify appropriate error thrown
  5. Verify error message describes issue
- **Expected:** Error thrown with descriptive message
- **Status:** ✅ Implemented

### TEST-H3-007: H3 Distance Calculation
- **Component:** H3LocationService (if implemented)
- **Backend:** N/A (Local calculation)
- **Priority:** Low
- **Steps:**
  1. Input two H3 indexes
  2. Calculate distance between them
  3. Verify distance is positive number
  4. Verify distance matches expected value
- **Expected:** Correct distance in meters or hexagons
- **Status:** 📋 Planned

---

## 3. Background Location Monitoring

### TEST-BG-001: Start Background Monitoring
- **Component:** BackgroundLocationService.startMonitoring()
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Ensure permissions granted
  2. Call startMonitoring()
  3. Verify NotificationService initialized
  4. Verify watchId assigned
  5. Verify isMonitoring set to true
  6. Verify state saved to AsyncStorage
  7. Verify console log confirms start
- **Expected:** Background monitoring starts successfully
- **Status:** ✅ Implemented

### TEST-BG-002: Stop Background Monitoring
- **Component:** BackgroundLocationService.stopMonitoring()
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Start monitoring first
  2. Call stopMonitoring()
  3. Verify Geolocation.clearWatch() called
  4. Verify watchId cleared
  5. Verify isMonitoring set to false
  6. Verify state saved to AsyncStorage
- **Expected:** Background monitoring stops successfully
- **Status:** ✅ Implemented

### TEST-BG-003: Location Update Handling
- **Component:** BackgroundLocationService.handleLocationUpdate()
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** Critical
- **Steps:**
  1. Start monitoring
  2. Trigger location update event
  3. Verify GPS coordinates received
  4. Verify H3 index calculated (resolution 11)
  5. Verify H3 index compared to previous
  6. If changed, verify API call to get risk data
  7. Verify location saved to history
- **Expected:** Location processed and risk checked
- **Status:** ✅ Implemented

### TEST-BG-004: Z-Score Threshold Notification
- **Component:** BackgroundLocationService.handleLocationUpdate()
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** Critical
- **Steps:**
  1. Start monitoring with z-score threshold 2.0
  2. Simulate entering high-crime hexagon (z >= 2.0)
  3. Verify API returns high risk data
  4. Verify notification triggered
  5. Verify notification contains risk details
  6. Verify cooldown timer set
- **Expected:** Push notification sent for high-risk area
- **Status:** ✅ Implemented

### TEST-BG-005: Notification Cooldown
- **Component:** BackgroundLocationService notification cooldown
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Trigger high-risk notification
  2. Immediately trigger another high-risk notification
  3. Verify second notification blocked by cooldown
  4. Wait for cooldown period (5 minutes)
  5. Trigger another notification
  6. Verify notification sent after cooldown expires
- **Expected:** Notifications respect cooldown period
- **Status:** ✅ Implemented

### TEST-BG-006: Distance Filter
- **Component:** BackgroundLocationService distance filter (50m)
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Start monitoring
  2. Simulate movement <50 meters
  3. Verify no location update triggered
  4. Simulate movement >50 meters
  5. Verify location update triggered
- **Expected:** Updates only fire when moved >50m
- **Status:** ✅ Implemented

### TEST-BG-007: Location History Tracking
- **Component:** BackgroundLocationService location history
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Start monitoring
  2. Trigger multiple location updates
  3. Verify each location saved to history array
  4. Verify history limited to last 100 locations
  5. Verify oldest locations removed when limit exceeded
- **Expected:** Last 100 locations stored correctly
- **Status:** ✅ Implemented

### TEST-BG-008: App State Transitions
- **Component:** BackgroundLocationService.setupAppStateListener()
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Start monitoring with app in foreground
  2. Background the app
  3. Verify monitoring continues
  4. Trigger location update while backgrounded
  5. Verify update processed normally
  6. Return app to foreground
  7. Verify monitoring still active
- **Expected:** Monitoring persists across app states
- **Status:** ✅ Implemented

### TEST-BG-009: Monitoring State Persistence
- **Component:** BackgroundLocationService state persistence
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Start monitoring
  2. Close app completely
  3. Reopen app
  4. Verify monitoring state restored
  5. Verify monitoring resumes automatically if was enabled
- **Expected:** Monitoring state persists across restarts
- **Status:** ✅ Implemented

---

## 4. API Integration

### TEST-API-001: Risk Level API - Single Hexagon
- **Component:** DrupalCrimeService.getRiskLevel()
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** Critical
- **Steps:**
  1. Call getRiskLevel() with valid H3 index
  2. Verify query parameters include h3_index
  3. Verify response contains risk_level, risk_score
  4. Verify incident_count present
  5. Verify last_updated timestamp present
- **Expected:** Valid risk assessment data returned
- **Status:** ✅ Implemented

### TEST-API-002: Risk Level API - With Neighbors
- **Component:** DrupalCrimeService.getRiskLevel()
- **Backend:** GET /api/amisafe/risk-level?include_neighbors=true
- **Priority:** High
- **Steps:**
  1. Call getRiskLevel() with include_neighbors=true
  2. Verify neighbors object in response
  3. Verify 6 neighboring hexagons included
  4. Verify each neighbor has risk data
- **Expected:** Risk data for center + 6 neighbors returned
- **Status:** ✅ Implemented

### TEST-API-003: Aggregated Data API
- **Component:** DrupalCrimeService.getAggregatedData()
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Critical
- **Steps:**
  1. Call getAggregatedData() with resolution and bounds
  2. Verify query parameters formatted correctly
  3. Verify response contains hexagons array
  4. Verify each hexagon has h3_index, incident_count
  5. Verify meta information present (resolution, total)
- **Expected:** Array of H3 hexagons with crime data
- **Status:** ✅ Implemented

### TEST-API-004: Aggregated Data - Crime Type Filter
- **Component:** DrupalCrimeService.getAggregatedData()
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Medium
- **Steps:**
  1. Call getAggregatedData() with crime_types filter
  2. Verify crime_types parameter sent as comma-separated
  3. Verify response contains only filtered crime types
  4. Verify incident counts match filtered data
- **Expected:** Filtered hexagon data returned
- **Status:** ✅ Implemented

### TEST-API-005: Aggregated Data - Date Range Filter
- **Component:** DrupalCrimeService.getAggregatedData()
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Medium
- **Steps:**
  1. Call getAggregatedData() with start_date and end_date
  2. Verify date parameters formatted correctly
  3. Verify response contains only incidents in date range
- **Expected:** Date-filtered hexagon data returned
- **Status:** ✅ Implemented

### TEST-API-006: Incidents API
- **Component:** DrupalCrimeService.getIncidents()
- **Backend:** GET /api/amisafe/incidents
- **Priority:** High
- **Steps:**
  1. Call getIncidents() with filters
  2. Verify response contains incidents array
  3. Verify each incident has required fields (id, type, date, location)
  4. Verify incidents match filter criteria
- **Expected:** Array of individual crime incidents
- **Status:** ✅ Implemented

### TEST-API-007: System Stats API
- **Component:** DrupalCrimeService (if implemented)
- **Backend:** GET /api/amisafe/system-stats
- **Priority:** Low
- **Steps:**
  1. Call system stats endpoint
  2. Verify response contains database statistics
  3. Verify total incident count present
  4. Verify date range information present
- **Expected:** System statistics returned
- **Status:** 📋 Planned

### TEST-API-008: Crime Types API
- **Component:** DrupalCrimeService (if implemented)
- **Backend:** GET /api/amisafe/crime-types
- **Priority:** Medium
- **Steps:**
  1. Call crime types endpoint
  2. Verify response contains array of crime categories
  3. Verify each type has name and count
- **Expected:** List of available crime types
- **Status:** 📋 Planned

### TEST-API-009: Safety Score Calculation
- **Component:** DrupalCrimeService.getSafetyScore()
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** High
- **Steps:**
  1. Call getSafetyScore() with lat/lng
  2. Verify coordinates sent correctly
  3. Verify safety_score in response (0-100)
  4. Verify risk_level classification (MINIMAL/LOW/MEDIUM/HIGH/CRITICAL)
  5. Verify coverage information (radius, area, resolution)
- **Expected:** Comprehensive safety assessment returned
- **Status:** ✅ Implemented

### TEST-API-010: API Timeout Handling
- **Component:** All DrupalCrimeService methods
- **Backend:** Any endpoint
- **Priority:** High
- **Steps:**
  1. Configure short timeout (e.g., 1ms)
  2. Call any API method
  3. Verify timeout error caught
  4. Verify fallback data returned
  5. Verify error logged
- **Expected:** Graceful timeout handling with fallback
- **Status:** ✅ Implemented

### TEST-API-011: API Error Response Handling
- **Component:** All DrupalCrimeService methods
- **Backend:** Any endpoint
- **Priority:** High
- **Steps:**
  1. Trigger API error (500, 404, etc.)
  2. Verify error caught and handled
  3. Verify fallback data returned
  4. Verify user-friendly error message generated
- **Expected:** Graceful error handling, no crashes
- **Status:** ✅ Implemented

### TEST-API-012: Authenticated Request with CSRF Token
- **Component:** DrupalAuthService.authenticatedRequest()
- **Backend:** Any authenticated endpoint
- **Priority:** Critical
- **Steps:**
  1. Login user to get session
  2. Call authenticatedRequest() for protected endpoint
  3. Verify CSRF token included in headers
  4. Verify session cookies included
  5. Verify request succeeds with valid authentication
- **Expected:** Authenticated request successful
- **Status:** ✅ Implemented

---

## 5. Crime Map Visualization

### TEST-MAP-001: Map Initialization
- **Component:** InteractiveCrimeMap component
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Render InteractiveCrimeMap component
  2. Verify map loads at initial location (Philadelphia)
  3. Verify map controls present (zoom, layers)
  4. Verify loading indicator shown during data fetch
- **Expected:** Map renders successfully with default view
- **Status:** ✅ Implemented

### TEST-MAP-002: H3 Hexagon Overlay Rendering
- **Component:** InteractiveCrimeMap hexagon rendering
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Critical
- **Steps:**
  1. Load map at specific location
  2. Fetch hexagon data for visible bounds
  3. Verify hexagons rendered as polygons
  4. Verify hexagon colors match risk levels
  5. Verify hexagon boundaries accurate
- **Expected:** H3 hexagons displayed on map with color coding
- **Status:** ✅ Implemented

### TEST-MAP-003: Zoom-Based Resolution Switching
- **Component:** InteractiveCrimeMap.getOptimalResolution()
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** High
- **Steps:**
  1. Start at zoom level 10 (expect resolution 5)
  2. Zoom in to level 13 (expect resolution 8)
  3. Zoom in to level 16 (expect resolution 11)
  4. Verify correct resolution requested at each zoom
  5. Verify hexagon data updates with new resolution
- **Expected:** Hexagon resolution adapts to zoom level
- **Status:** ✅ Implemented

### TEST-MAP-004: Hexagon Selection and Details
- **Component:** InteractiveCrimeMap hexagon tap handler
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Tap on hexagon
  2. Verify hexagon highlighted
  3. Verify detail modal opens
  4. Verify modal shows incident count, risk level, H3 index
  5. Verify modal shows crime type breakdown
- **Expected:** Hexagon details displayed on tap
- **Status:** ✅ Implemented

### TEST-MAP-005: Crime Incident Markers
- **Component:** InteractiveCrimeMap incident markers
- **Backend:** GET /api/amisafe/incidents
- **Priority:** Medium
- **Steps:**
  1. Switch to "points" view mode
  2. Verify individual incident markers rendered
  3. Verify marker colors match crime severity
  4. Tap marker to see incident details
  5. Verify details include type, date, description
- **Expected:** Individual crimes shown as map markers
- **Status:** ✅ Implemented

### TEST-MAP-006: Crime Type Filter
- **Component:** InteractiveCrimeMap filter controls
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Medium
- **Steps:**
  1. Open filter panel
  2. Select specific crime types (e.g., theft only)
  3. Verify API called with crime_types parameter
  4. Verify map updates to show filtered data
  5. Verify legend updates to match filters
- **Expected:** Map data filtered by crime type
- **Status:** ✅ Implemented

### TEST-MAP-007: Date Range Filter
- **Component:** InteractiveCrimeMap date picker
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Medium
- **Steps:**
  1. Open date range picker
  2. Select start and end dates
  3. Verify API called with date parameters
  4. Verify map updates to show filtered data
  5. Verify date range displayed in UI
- **Expected:** Map data filtered by date range
- **Status:** ✅ Implemented

### TEST-MAP-008: View Mode Switching
- **Component:** InteractiveCrimeMap view mode toggle
- **Backend:** Various endpoints
- **Priority:** Low
- **Steps:**
  1. Start in "hexagon" view
  2. Switch to "heatmap" view
  3. Verify heatmap gradient rendered
  4. Switch to "points" view
  5. Verify individual markers shown
- **Expected:** Different visualization modes work correctly
- **Status:** 🔄 Partial (hexagon implemented)

### TEST-MAP-009: City-Wide Statistics Display
- **Component:** InteractiveCrimeMap citywideStats
- **Backend:** GET /api/amisafe/system-stats
- **Priority:** Low
- **Steps:**
  1. Load map
  2. Fetch citywide statistics
  3. Verify stats displayed in header/footer
  4. Verify total incidents count shown
  5. Verify date range shown
- **Expected:** Overall statistics displayed on map
- **Status:** ✅ Implemented

### TEST-MAP-010: Performance with Large Dataset
- **Component:** InteractiveCrimeMap data rendering
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** High
- **Steps:**
  1. Load map with limit=1000 hexagons
  2. Verify smooth rendering (<2 seconds)
  3. Pan and zoom map
  4. Verify no lag or stuttering
  5. Verify data cache working
- **Expected:** Smooth performance with large datasets
- **Status:** ✅ Implemented with caching

### TEST-MAP-011: User Location Marker
- **Component:** InteractiveCrimeMap user position
- **Backend:** N/A (GPS)
- **Priority:** High
- **Steps:**
  1. Enable location services
  2. Verify user marker displayed at current location
  3. Verify accuracy circle shown
  4. Move device location
  5. Verify marker updates in real-time
- **Expected:** User location accurately displayed
- **Status:** 📋 Planned

---

## 6. Home Dashboard

### TEST-HOME-001: Dashboard Initialization
- **Component:** HomeScreen
- **Backend:** Multiple endpoints
- **Priority:** Critical
- **Steps:**
  1. Navigate to Home screen
  2. Verify loading indicator shown
  3. Verify current location fetched
  4. Verify safety data loaded
  5. Verify dashboard displays after load
- **Expected:** Dashboard loads with current safety data
- **Status:** ✅ Implemented

### TEST-HOME-002: Safety Score Display
- **Component:** HomeScreen safety score
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** Critical
- **Steps:**
  1. View home screen
  2. Verify safety score shown (0-100)
  3. Verify score has appropriate color (green/yellow/red)
  4. Verify risk level text shown (MINIMAL/LOW/MEDIUM/HIGH/CRITICAL)
  5. Verify description text shown
- **Expected:** Current safety score prominently displayed
- **Status:** ✅ Implemented (with mock data)

### TEST-HOME-003: Quick Statistics Cards
- **Component:** HomeScreen quick stats
- **Backend:** GET /api/amisafe/aggregated
- **Priority:** Medium
- **Steps:**
  1. View quick stats section
  2. Verify total incidents count shown
  3. Verify recent incidents count shown (24h)
  4. Verify safety trend indicator (improving/stable/declining)
  5. Verify icons appropriate for each stat
- **Expected:** Summary statistics displayed clearly
- **Status:** ✅ Implemented (with mock data)

### TEST-HOME-004: Current Location Display
- **Component:** HomeScreen location info
- **Backend:** N/A (GPS)
- **Priority:** High
- **Steps:**
  1. View home screen
  2. Verify current address or coordinates shown
  3. Verify location accuracy displayed
  4. Verify "locate me" button works
  5. Verify location updates when moved
- **Expected:** Current location shown and updatable
- **Status:** ✅ Implemented

### TEST-HOME-005: Pull-to-Refresh
- **Component:** HomeScreen refresh control
- **Backend:** Multiple endpoints
- **Priority:** Medium
- **Steps:**
  1. Pull down on home screen
  2. Verify refresh animation shown
  3. Verify data reloaded (location, safety score, stats)
  4. Verify UI updates with new data
  5. Verify refresh completes and animation stops
- **Expected:** Dashboard data refreshes on pull-down
- **Status:** ✅ Implemented

### TEST-HOME-006: Emergency Contact Shortcuts
- **Component:** HomeScreen emergency buttons (if implemented)
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. View emergency contact section
  2. Tap 911 button
  3. Verify phone dialer opens with 911
  4. Tap emergency contact button
  5. Verify contact picker or stored contact called
- **Expected:** Quick access to emergency contacts
- **Status:** 📋 Planned

### TEST-HOME-007: Navigation to Other Screens
- **Component:** HomeScreen navigation
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. From home screen, tap "View Map" button
  2. Verify navigates to Map screen
  3. Return to home, tap "Safety Details"
  4. Verify navigates to Safety screen
  5. Test all navigation buttons
- **Expected:** Navigation works to all screens
- **Status:** ✅ Implemented

---

## 7. Safety Screen

### TEST-SAFE-001: Safety Screen Load
- **Component:** SafetyScreen
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** High
- **Steps:**
  1. Navigate to Safety screen
  2. Verify current risk assessment shown
  3. Verify detailed risk factors displayed
  4. Verify safety recommendations shown
- **Expected:** Comprehensive safety information displayed
- **Status:** ✅ Implemented

### TEST-SAFE-002: Risk Factor Breakdown
- **Component:** SafetyScreen risk factors
- **Backend:** GET /api/amisafe/risk-level
- **Priority:** Medium
- **Steps:**
  1. View risk factors section
  2. Verify each factor has description
  3. Verify factors weighted appropriately
  4. Verify visual indicators (icons, colors)
- **Expected:** Clear breakdown of risk contributors
- **Status:** 📋 Planned

### TEST-SAFE-003: Safety Recommendations
- **Component:** SafetyScreen recommendations
- **Backend:** N/A (Local logic)
- **Priority:** Medium
- **Steps:**
  1. View in high-risk area
  2. Verify appropriate warnings shown
  3. Verify recommendations context-aware
  4. View in low-risk area
  5. Verify positive safety messages shown
- **Expected:** Contextual safety advice provided
- **Status:** 📋 Planned

### TEST-SAFE-004: Nearby Incidents List
- **Component:** SafetyScreen recent incidents
- **Backend:** GET /api/amisafe/incidents
- **Priority:** Medium
- **Steps:**
  1. View recent incidents section
  2. Verify list of nearby crimes shown
  3. Verify each incident has type, date, distance
  4. Tap incident to see full details
  5. Verify detail modal opens
- **Expected:** List of recent nearby crimes shown
- **Status:** 📋 Planned

---

## 8. Settings & Preferences

### TEST-SET-001: Settings Screen Load
- **Component:** SettingsScreen
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Navigate to Settings screen
  2. Verify all settings sections displayed
  3. Verify current values loaded from storage
  4. Verify UI responsive and clear
- **Expected:** Settings screen displays correctly
- **Status:** ✅ Implemented

### TEST-SET-002: Background Monitoring Toggle
- **Component:** SettingsScreen monitoring switch
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Toggle monitoring switch ON
  2. Verify BackgroundLocationService.startMonitoring() called
  3. Verify switch state persisted
  4. Toggle switch OFF
  5. Verify BackgroundLocationService.stopMonitoring() called
- **Expected:** Monitoring starts/stops with toggle
- **Status:** ✅ Implemented

### TEST-SET-003: Z-Score Threshold Setting
- **Component:** SettingsScreen threshold selector
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. View z-score threshold setting (default 2.0)
  2. Change to different value (e.g., 1.5)
  3. Save settings
  4. Verify new value persisted to storage
  5. Verify background service uses new threshold
- **Expected:** Notification threshold configurable
- **Status:** ✅ Implemented

### TEST-SET-004: Notification Cooldown Setting
- **Component:** SettingsScreen cooldown selector
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. View notification cooldown (default 5 minutes)
  2. Change to different value (e.g., 10 minutes)
  3. Save settings
  4. Verify new value persisted
  5. Verify background service uses new cooldown
- **Expected:** Notification frequency configurable
- **Status:** ✅ Implemented

### TEST-SET-005: View Location History
- **Component:** SettingsScreen location history
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. Tap "View Location History" button
  2. Verify modal/alert shows history count
  3. Verify privacy message displayed
  4. Verify data stored locally
- **Expected:** Location history viewable
- **Status:** ✅ Implemented

### TEST-SET-006: Clear Location History
- **Component:** SettingsScreen clear history
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Tap "Clear Location History"
  2. Verify confirmation alert shown
  3. Confirm deletion
  4. Verify history cleared from storage
  5. Verify confirmation message shown
- **Expected:** Location history can be deleted
- **Status:** ✅ Implemented

### TEST-SET-007: Settings Persistence
- **Component:** SettingsScreen storage
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Change multiple settings
  2. Save changes
  3. Close app completely
  4. Reopen app
  5. Navigate to settings
  6. Verify all changes persisted
- **Expected:** Settings saved across app restarts
- **Status:** ✅ Implemented

---

## 9. Notifications

### TEST-NOTIF-001: Notification Service Initialization
- **Component:** NotificationService.initialize()
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Call NotificationService.initialize()
  2. Verify permission requested from user
  3. Verify channel created (Android)
  4. Verify service ready for notifications
- **Expected:** Notification service ready
- **Status:** ✅ Implemented

### TEST-NOTIF-002: Request Notification Permissions
- **Component:** NotificationService.requestPermissions()
- **Backend:** N/A (OS)
- **Priority:** Critical
- **Steps:**
  1. Call requestPermissions()
  2. Verify OS permission dialog shown
  3. Grant permission
  4. Verify method returns true
  5. Deny permission
  6. Verify method returns false
- **Expected:** Permission status correctly returned
- **Status:** ✅ Implemented

### TEST-NOTIF-003: High Risk Area Notification
- **Component:** NotificationService.sendRiskAlert()
- **Backend:** N/A (Local)
- **Priority:** Critical
- **Steps:**
  1. Trigger high-risk notification
  2. Verify notification shown with title
  3. Verify notification body contains risk info
  4. Verify notification has high priority
  5. Verify notification icon appropriate
  6. Tap notification
  7. Verify app opens to relevant screen
- **Expected:** Alert notification displayed properly
- **Status:** ✅ Implemented

### TEST-NOTIF-004: Notification Content Format
- **Component:** NotificationService notification content
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Send notification for risk level HIGH
  2. Verify title mentions risk level
  3. Verify body has incident count
  4. Verify body has safety advice
  5. Send notification for risk level CRITICAL
  6. Verify escalated language used
- **Expected:** Notification content appropriate to risk
- **Status:** ✅ Implemented

### TEST-NOTIF-005: Notification Channels (Android)
- **Component:** NotificationService channel setup
- **Backend:** N/A (Android)
- **Priority:** Medium
- **Steps:**
  1. Initialize on Android
  2. Verify "Safety Alerts" channel created
  3. Verify channel has high importance
  4. Verify channel allows sound
  5. Send notification
  6. Verify routed to correct channel
- **Expected:** Notification channels properly configured
- **Status:** ✅ Implemented

### TEST-NOTIF-006: Notification Actions (if implemented)
- **Component:** NotificationService action buttons
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. Send notification with actions
  2. Verify "View Map" action shown
  3. Verify "Dismiss" action shown
  4. Tap "View Map"
  5. Verify app opens to map at location
- **Expected:** Notification actions work correctly
- **Status:** 📋 Planned

---

## 10. Storage & Persistence

### TEST-STOR-001: Save Data to AsyncStorage
- **Component:** StorageService.saveData()
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Call saveData() with key-value pair
  2. Verify no errors thrown
  3. Verify data saved to AsyncStorage
  4. Verify data retrievable
- **Expected:** Data persisted successfully
- **Status:** ✅ Implemented

### TEST-STOR-002: Load Data from AsyncStorage
- **Component:** StorageService.getData()
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Save data first
  2. Call getData() with key
  3. Verify correct value returned
  4. Call getData() with non-existent key
  5. Verify null returned
- **Expected:** Data retrieved correctly
- **Status:** ✅ Implemented

### TEST-STOR-003: Delete Data from AsyncStorage
- **Component:** StorageService.removeData()
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Save data first
  2. Call removeData() with key
  3. Verify data deleted
  4. Attempt to retrieve deleted data
  5. Verify null returned
- **Expected:** Data removed successfully
- **Status:** ✅ Implemented

### TEST-STOR-004: Clear All Storage
- **Component:** StorageService.clearAll()
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. Save multiple key-value pairs
  2. Call clearAll()
  3. Verify all data deleted
  4. Attempt to retrieve any key
  5. Verify all return null
- **Expected:** All storage cleared
- **Status:** ✅ Implemented

### TEST-STOR-005: JSON Data Storage
- **Component:** StorageService complex object storage
- **Backend:** N/A (Local)
- **Priority:** High
- **Steps:**
  1. Save complex object (user profile, settings)
  2. Verify object serialized to JSON
  3. Retrieve object
  4. Verify object deserialized correctly
  5. Verify all properties intact
- **Expected:** Complex objects stored and retrieved
- **Status:** ✅ Implemented

### TEST-STOR-006: Storage Error Handling
- **Component:** StorageService error handling
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Attempt to save invalid data
  2. Verify error caught and logged
  3. Verify app doesn't crash
  4. Attempt to retrieve corrupted data
  5. Verify graceful failure
- **Expected:** Storage errors handled gracefully
- **Status:** ✅ Implemented

---

## 11. Permissions

### TEST-PERM-001: Location Permission Request
- **Component:** permissions.ts requestLocationPermission()
- **Backend:** N/A (OS)
- **Priority:** Critical
- **Steps:**
  1. Call requestLocationPermission()
  2. Verify OS permission dialog shown
  3. Grant "While Using App" permission
  4. Verify method returns 'granted'
  5. Verify location services work
- **Expected:** Location permission granted
- **Status:** ✅ Implemented

### TEST-PERM-002: Background Location Permission (iOS)
- **Component:** permissions.ts iOS background permission
- **Backend:** N/A (iOS)
- **Priority:** Critical
- **Steps:**
  1. Request foreground location first
  2. Request background location permission
  3. Verify iOS shows background permission dialog
  4. Grant "Always" permission
  5. Verify background monitoring works
- **Expected:** Background location allowed
- **Status:** ✅ Implemented

### TEST-PERM-003: Permission Denied Handling
- **Component:** permissions.ts permission denial
- **Backend:** N/A (OS)
- **Priority:** High
- **Steps:**
  1. Request location permission
  2. Deny permission
  3. Verify method returns 'denied'
  4. Verify user shown explanation
  5. Verify option to open settings
- **Expected:** Graceful handling of denial
- **Status:** ✅ Implemented

### TEST-PERM-004: Check Existing Permissions
- **Component:** permissions.ts checkLocationPermission()
- **Backend:** N/A (OS)
- **Priority:** Medium
- **Steps:**
  1. Call checkLocationPermission()
  2. Verify current permission status returned
  3. Verify no dialog shown (check only)
  4. Verify status accurate
- **Expected:** Current permission status retrieved
- **Status:** ✅ Implemented

### TEST-PERM-005: Open App Settings
- **Component:** permissions.ts openAppSettings()
- **Backend:** N/A (OS)
- **Priority:** Medium
- **Steps:**
  1. Call openAppSettings()
  2. Verify OS settings app opens
  3. Verify navigated to app's permissions page
  4. Change permission
  5. Return to app
  6. Verify app detects permission change
- **Expected:** Settings accessible for manual permission change
- **Status:** ✅ Implemented

---

## 12. Error Handling & Edge Cases

### TEST-ERR-001: No Internet Connection
- **Component:** All API services
- **Backend:** Any endpoint
- **Priority:** Critical
- **Steps:**
  1. Disable internet connection
  2. Attempt API call
  3. Verify network error caught
  4. Verify fallback/cached data shown
  5. Verify user notified about offline status
  6. Re-enable internet
  7. Verify app recovers automatically
- **Expected:** Graceful offline handling
- **Status:** ✅ Implemented

### TEST-ERR-002: GPS Unavailable
- **Component:** LocationService
- **Backend:** N/A (GPS)
- **Priority:** High
- **Steps:**
  1. Disable GPS on device
  2. Attempt to get location
  3. Verify error caught
  4. Verify user shown helpful message
  5. Verify option to enable GPS
- **Expected:** GPS error handled gracefully
- **Status:** ✅ Implemented

### TEST-ERR-003: Invalid API Response
- **Component:** DrupalCrimeService
- **Backend:** Any endpoint
- **Priority:** High
- **Steps:**
  1. Trigger malformed API response
  2. Verify parsing error caught
  3. Verify fallback data used
  4. Verify error logged for debugging
  5. Verify app doesn't crash
- **Expected:** Malformed responses don't crash app
- **Status:** ✅ Implemented

### TEST-ERR-004: Session Expiration
- **Component:** DrupalAuthService
- **Backend:** Any authenticated endpoint
- **Priority:** High
- **Steps:**
  1. Login user
  2. Expire session server-side
  3. Attempt authenticated API call
  4. Verify 401/403 error detected
  5. Verify user prompted to re-login
  6. Re-login
  7. Verify functionality restored
- **Expected:** Session expiration detected and handled
- **Status:** 🔄 Partial implementation

### TEST-ERR-005: Low Battery Mode
- **Component:** BackgroundLocationService
- **Backend:** N/A (OS)
- **Priority:** Medium
- **Steps:**
  1. Enable low battery mode
  2. Start background monitoring
  3. Verify reduced update frequency
  4. Verify monitoring still functional
  5. Disable low battery mode
  6. Verify normal frequency restored
- **Expected:** Battery optimization respected
- **Status:** ✅ Implemented (OS handles)

### TEST-ERR-006: App Background Termination
- **Component:** BackgroundLocationService
- **Backend:** N/A (OS)
- **Priority:** High
- **Steps:**
  1. Start monitoring
  2. Background app
  3. OS terminates app (simulate)
  4. Reopen app
  5. Verify monitoring state restored
  6. Verify monitoring resumes if was enabled
- **Expected:** State survives app termination
- **Status:** ✅ Implemented

### TEST-ERR-007: Corrupt Storage Data
- **Component:** StorageService
- **Backend:** N/A (Local)
- **Priority:** Medium
- **Steps:**
  1. Manually corrupt AsyncStorage data
  2. Attempt to load data
  3. Verify parsing error caught
  4. Verify default values used
  5. Verify app doesn't crash
- **Expected:** Corrupt data handled gracefully
- **Status:** ✅ Implemented

### TEST-ERR-008: Rapid State Changes
- **Component:** All components
- **Backend:** N/A
- **Priority:** Medium
- **Steps:**
  1. Rapidly switch screens
  2. Rapidly start/stop monitoring
  3. Rapidly pan/zoom map
  4. Verify no race conditions
  5. Verify no memory leaks
  6. Verify UI remains responsive
- **Expected:** App handles rapid interactions
- **Status:** 🔄 Needs testing

### TEST-ERR-009: Large Location History
- **Component:** BackgroundLocationService history
- **Backend:** N/A (Local)
- **Priority:** Low
- **Steps:**
  1. Allow location history to grow to limit (100)
  2. Continue tracking beyond limit
  3. Verify oldest entries removed
  4. Verify array size never exceeds 100
  5. Verify no performance degradation
- **Expected:** History size capped appropriately
- **Status:** ✅ Implemented

### TEST-ERR-010: Time Zone Changes
- **Component:** All timestamp handling
- **Backend:** Any endpoint with dates
- **Priority:** Low
- **Steps:**
  1. Use app in one time zone
  2. Change device time zone
  3. Verify timestamps adjust correctly
  4. Verify relative times accurate ("2 hours ago")
  5. Verify API date filters work
- **Expected:** Time zone changes handled correctly
- **Status:** 🔄 Needs testing

---

## Test Coverage Summary

| Category | Total Tests | Implemented | Partial | Planned | Priority Critical |
|----------|------------|-------------|---------|---------|------------------|
| Authentication | 8 | 8 | 0 | 0 | 3 |
| H3 Geospatial | 7 | 6 | 0 | 1 | 2 |
| Background Monitoring | 9 | 9 | 0 | 0 | 4 |
| API Integration | 12 | 10 | 0 | 2 | 4 |
| Crime Map | 11 | 9 | 1 | 1 | 2 |
| Home Dashboard | 7 | 7 | 0 | 0 | 2 |
| Safety Screen | 4 | 1 | 0 | 3 | 1 |
| Settings | 7 | 7 | 0 | 0 | 2 |
| Notifications | 6 | 5 | 0 | 1 | 3 |
| Storage | 6 | 6 | 0 | 0 | 2 |
| Permissions | 5 | 5 | 0 | 0 | 3 |
| Error Handling | 10 | 7 | 2 | 1 | 4 |
| **TOTAL** | **92** | **80** | **3** | **9** | **32** |

**Overall Implementation Status:** 87% (80/92 tests)  
**Critical Tests Implemented:** 28/32 (88%)

---

## Test Execution Priority

### Phase 1: Critical Path (Weeks 1-2)
Focus on authentication, location services, and API integration
- All TEST-AUTH-* tests (authentication flows)
- TEST-H3-001 to TEST-H3-003 (core geospatial)
- TEST-BG-001 to TEST-BG-004 (background monitoring)
- TEST-API-001 to TEST-API-003 (core API calls)
- TEST-NOTIF-001 to TEST-NOTIF-003 (notifications)
- TEST-PERM-001 to TEST-PERM-003 (permissions)

### Phase 2: Feature Completeness (Weeks 3-4)
Comprehensive feature testing across all screens
- All TEST-MAP-* tests (crime map visualization)
- All TEST-HOME-* tests (dashboard)
- All TEST-SET-* tests (settings)
- Remaining TEST-API-* tests

### Phase 3: Edge Cases & Polish (Week 5)
Error handling and edge case coverage
- All TEST-ERR-* tests
- All TEST-STOR-* tests
- Remaining planned tests

---

## Test Automation Framework

### Recommended Tools
- **Unit Tests:** Jest + React Native Testing Library
- **Integration Tests:** Detox for E2E testing
- **API Mocking:** MSW (Mock Service Worker)
- **Coverage:** Jest coverage reports (target: 80%+)

### Test File Structure
```
testing/amisafe/mobile/
├── unit/
│   ├── auth.test.js
│   ├── h3.test.js
│   ├── api.test.js
│   └── storage.test.js
├── integration/
│   ├── background-monitoring.test.js
│   ├── crime-map.test.js
│   └── user-flows.test.js
├── e2e/
│   ├── login-flow.e2e.js
│   ├── map-interaction.e2e.js
│   └── settings-management.e2e.js
├── fixtures/
│   ├── mock-users.js
│   ├── mock-crime-data.js
│   └── mock-api-responses.js
└── helpers/
    ├── test-utils.js
    └── setup.js
```

---

**Document Status:** Complete  
**Last Updated:** December 1, 2025  
**Next Review:** After Phase 1 test implementation
