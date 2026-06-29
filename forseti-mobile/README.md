# Forseti Mobile Application - Complete Documentation

**Version**: 1.0.3  
**Build Code**: 55 (auto-incremented by build script)  
**Status**: 🟢 Beta Testing (Production Authentication Enabled)  
**Platform**: React Native 0.76.9 (iOS & Android)  
**Last Updated**: January 22, 2026

**Note**: Version information is centrally managed in `src/config/AppVersion.ts`. All other version references are automatically updated by the build script.

A cross-platform mobile application for hyperlocal crime safety awareness built with React Native. Integrates with the Forseti API (forseti.life) for real-time crime data visualization, z-score risk assessment, and continuous background monitoring with proactive alerts.

## 🆕 Recent Updates (Build 55 - January 22, 2026)

- **🔧 Fixed NotificationService Import**: Resolved critical issue preventing safety notifications from appearing
- **🗺️ Enhanced H3 Location System**: Improved per-hexagon notification cooldowns (1 hour per location)
- **📊 Comprehensive Logging**: Added detailed API call and z-score debugging for H3 test locations
- **🧪 Force Location Check**: Added manual trigger for testing real safety system in Debug Screen
- **📱 Notification Reliability**: Fixed "Property 'NotificationService' doesn't exist" error

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Application Architecture](#application-architecture)
3. [Features & Implementation Status](#features--implementation-status)
4. [Build & Deployment](#build--deployment)
5. [Background Monitoring System](#background-monitoring-system)
6. [API Integration](#api-integration)
7. [Screen-by-Screen Guide](#screen-by-screen-guide)
8. [Known Issues & Troubleshooting](#known-issues--troubleshooting)
9. [Development Workflow](#development-workflow)
10. [Branding & Assets](#branding--assets)

---

## Quick Start

### Prerequisites

- Node.js 18+
- npm or yarn package manager
- React Native CLI
- Android Studio (for Android builds)
- Xcode (for iOS builds, Mac only)
- Java JDK 17 (for Android - required for AGP 8.0.2+)
- Android SDK Platform 34 (recommended for compatibility)

### Installation

```bash
# Automated setup (recommended)
cd script
./setup-forseti-mobile-dev.sh

# Manual setup
cd forseti-mobile
npm install

# iOS only
cd ios && pod install && cd ..
```

### Development

```bash
# Start Metro bundler
npm start

# Run on Android (debug)
npm run android:dev

# Run on iOS
npm run ios

# Web development (experimental)
npm run web
```

### Production Build

#### Android Debug APK

```bash
# Load Android environment
source android-env.sh

# Build debug APK
cd android
./gradlew clean assembleDebug --no-daemon

# Output: android/app/build/outputs/apk/debug/
# Files:
#   - forseti-debug-1.0.0-1-arm64-v8a.apk (51MB) - Modern ARM 64-bit devices
#   - forseti-debug-1.0.0-1-armeabi-v7a.apk (38MB) - Older ARM 32-bit devices
#   - forseti-debug-1.0.0-1-x86_64.apk (53MB) - 64-bit emulators
#   - forseti-debug-1.0.0-1-x86.apk (54MB) - 32-bit emulators
```

#### Android Release APK (Production) - AUTOMATED WORKFLOW

**⭐ Recommended: Complete Build & Deploy (One Command)**

```bash
npm run android:deploy
# OR
./build-and-deploy.sh
```

**What this does automatically:**

1. ✅ Increments versionCode in build.gradle
2. ✅ Updates version display in App.tsx (v1.0.3(YYYY-MM-DD))
3. ✅ Builds release APK (./gradlew clean assembleRelease)
4. ✅ Copies APK to deployment location: `sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk`
5. ✅ Shows git status ready for commit

**Output:**

```
android/app/build/outputs/apk/release/
├── forseti-release-1.0.3-4-arm64-v8a.apk (26 MB) ← Primary
├── forseti-release-1.0.3-4-armeabi-v7a.apk (22 MB)
├── forseti-release-1.0.3-4-x86.apk (28 MB)
└── forseti-release-1.0.3-4-x86_64.apk (28 MB)

sites/forseti/web/sites/default/files/forseti/mobile/
└── Forseti-latest.apk (26 MB) ← Auto-copied for deployment
```

**Manual Build (if needed):**

```bash
npm run android:clean
npm run android:build

# Output: android/app/build/outputs/apk/release/
# Files will be signed with release keystore
```

**Version Increment Only:**

```bash
npm run version:increment
# OR
./increment-build.sh
```

#### Android App Bundle (for Google Play)

```bash
npm run android:bundle

# Output: android/app/build/outputs/bundle/release/app-release.aab
```

### 🔐 Release Signing Configuration

**CRITICAL**: The release keystore is required for all production builds and Google Play updates.

**Keystore Location**: `forseti-mobile/android/app/forseti-release.keystore`

**Credentials**:

- **Store Password**: `forseti2024`
- **Key Alias**: `forseti-key`
- **Key Password**: `forseti2024`
- **Validity**: 10,000 days (expires ~2052)

**Certificate Details**:

- **Organization**: St Louis Integration
- **Unit**: Mobile
- **Location**: Philadelphia, PA, USA
- **Common Name**: Forseti

**⚠️ BACKUP WARNING**:

- Store the keystore file in multiple secure locations (cloud storage, external drive, password manager)
- If you lose this keystore, you **CANNOT update the app** on Google Play
- You would need to publish a completely new app with a different package name
- All existing users, reviews, and downloads would be lost

**Configured in**: `android/gradle.properties`

```properties
FORSETI_RELEASE_STORE_FILE=forseti-release.keystore
FORSETI_RELEASE_STORE_PASSWORD=forseti2024
FORSETI_RELEASE_KEY_ALIAS=forseti-key
FORSETI_RELEASE_KEY_PASSWORD=forseti2024
```

---

## Application Architecture

### System Overview

The Forseti mobile application operates on a three-tier architecture:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Mobile App    │◄──►│   Drupal API     │◄──►│  H3 Database        │
│  React Native   │    │  forseti.life    │    │  MySQL + H3 Index   │
│                 │    │                  │    │                     │
│ • GPS Tracking  │    │ • Authentication │    │ • 3.4M+ Incidents   │
│ • Notifications │    │ • Spatial Queries│    │ • 413K+ Hexagons    │
│ • Risk Display  │    │ • Risk Calc      │    │ • Multi-Resolution  │
│ • Offline Cache │    │ • User Mgmt      │    │ • Z-Score Analytics │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
```

### Technology Stack

**Frontend (Mobile)**:

- **React Native**: 0.72.6
- **TypeScript**: 5.x
- **React**: 18.2.0
- **React Navigation**: 6.x (Stack + Bottom Tabs)
- **H3-js**: 4.1.0 (Uber's H3 geospatial indexing library)
- **react-native-maps**: 1.7.1 (Google Maps integration - patched for AGP 8.0+)
- **AsyncStorage**: 1.19.5 (local persistence)
- **Hermes**: Enabled (optimized JavaScript engine)
- **react-native-geolocation-service**: 5.3.1 (GPS tracking)
- **react-native-permissions**: 3.10.1 (runtime permissions)
- **axios**: 1.6.0 (HTTP client)

**Build Tools & Configuration**:

- **Gradle**: 8.0.1 (Android build system)
- **Android Gradle Plugin**: 8.0.2 (AGP)
- **Android SDK**: 34 (compileSdk/targetSdk), 23 (minSdk)
- **NDK Version**: 25.1.8937393
- **Kotlin**: 1.8.22 (compatible with React Native 0.72)
- **Java**: 17 (required for AGP 8.0.2+)
- **Metro Bundler**: Built-in React Native bundler
- **Flipper**: 0.182.0 (debugging - debug builds only)
- **ProGuard/R8**: Enabled for release builds (code minification)

**Package Management**:

- **npm**: Package manager (use `--legacy-peer-deps` flag)
- **CocoaPods**: iOS dependency management

**Backend API (forseti.life)**:

- **Drupal**: 9/10/11 custom modules
- **RESTful JSON APIs**: Custom endpoints for spatial queries
- **Authentication**: Session-based with CSRF token protection
- **Base URL**: `https://forseti.life`

**Database (H3 Geospatial)**:

- **MySQL**: 8.0+ with H3 spatial indexing
- **Architecture**: Bronze → Silver → Gold data warehouse layers
- **Pre-computed aggregations** for performance
- **Dataset**: 3.4M+ crime incidents, 413K+ hexagons
- **Resolutions**: H3 levels 5-13 (citywide to block-level)

**Development Environment**:

- **OS**: Linux (Chromebook/Debian-based)
- **IDE**: VS Code
- **Debugging**: Chrome DevTools, React DevTools, Flipper
- **Version Control**: Git

### Project Structure

```
forseti-mobile/
├── android/              # Android native code
├── ios/                  # iOS native code
├── src/
│   ├── components/       # Reusable UI components
│   ├── hooks/            # Custom React hooks
│   ├── screens/          # Screen components
│   │   ├── Home/
│   │   ├── Map/
│   │   ├── Chat/
│   │   ├── Community/
│   │   ├── SafetyFactors/
│   │   └── Profile/
│   ├── services/         # API & business logic
│   │   ├── location/
│   │   ├── notifications/
│   │   └── storage/
│   └── utils/            # Helper functions & theme
├── App.tsx               # Main application entry
├── app.json              # App configuration
└── package.json          # Dependencies
```

### Data Flow Architecture

**Location Tracking Flow**:

```
GPS Update → H3 Calculation → Index Comparison → Risk Query → Notification
     ↓              ↓              ↓              ↓            ↓
  Lat/Lng    H3 Level 11    Changed Hex?    API Request   Alert User
   60s or    (~700m hex)     If yes, call   /aggregated   if z≥2.0
  50m moved                   API
```

**H3 Resolution Strategy**:

| Resolution | Area Coverage | Use Case               | Update Frequency |
| ---------- | ------------- | ---------------------- | ---------------- |
| 5          | 251.1 km²     | Citywide statistics    | Daily            |
| 8          | 0.7 km²       | Neighborhood context   | Hourly           |
| 10         | 15,047 m²     | Block-level awareness  | Every 15 min     |
| 11         | ~700 m²       | Background monitoring  | Real-time        |
| 13         | 44 m²         | User position tracking | Real-time        |

---

## Features & Implementation Status

### ✅ Fully Implemented & Tested

**Core Application**:

- ✅ Bottom tab navigation (6 screens)
- ✅ Stack navigation for auxiliary screens
- ✅ TypeScript support throughout
- ✅ Theme system with Forseti branding
- ✅ Dark mode support (system-based)

**Location Services**:

- ✅ GPS tracking via react-native-geolocation-service
- ✅ Foreground + background location permissions
- ✅ H3 geospatial indexing (h3-js integration)
- ✅ Location history tracking (last 100 locations)
- ✅ Auto-restore monitoring on app restart

**Background Monitoring**:

- ✅ Continuous H3 hexagon change detection
- ✅ API queries only when user moves to new hexagon
- ✅ Z-score threshold monitoring (configurable 1.0-3.0)
- ✅ Notification cooldown system (1-30 minutes)
- ✅ Deep linking to safety map from notifications
- ✅ State persistence via AsyncStorage

**Map Features**:

- ✅ Interactive Google Maps with H3 hexagons
- ✅ Z-score color gradient (18 levels)
- ✅ Hexagon press for detailed info
- ✅ Real-time data updates on zoom/pan
- ✅ Filter system (crime type, district, date, time)
- ✅ Statistics dashboard
- ✅ User location marker

**Data & Storage**:

- ✅ AsyncStorage wrapper (StorageService)
- ✅ User preferences persistence
- ✅ Location history management
- ✅ Session token storage
- ✅ Monitoring state tracking

**Authentication**:

- ✅ Drupal session-based auth
- ✅ CSRF token management
- ✅ Auto-login on app launch
- ✅ Secure token storage
- ✅ Demo mode for development

### ⚠️ Temporarily Disabled

**Notification System**:

- ⚠️ NotificationService code complete (398 lines)
- ⚠️ Package installed: react-native-push-notification (v8.1.1)
- ⚠️ Service imports commented out in BackgroundLocationService.ts
- ⚠️ Needs re-enabling in background monitoring workflow
- ⚠️ Local notifications ready
- ⚠️ Deep linking configured

**Chat Functionality**:

- ⚠️ ChatScreen implemented but disabled due to API errors
- ⚠️ Tab commented out in App.tsx navigation
- ⚠️ Low priority - not currently being worked on

### 🔄 In Progress

**Content Parity**:

- 🔄 About screen content from website
- 🔄 How It Works screen updates
- 🔄 Privacy screen content
- 🔄 Community screen integration

**UI Polish**:

- 🔄 Loading states and error handling
- 🔄 Empty states for screens
- 🔄 Onboarding tutorial

### ❌ Future Enhancements

- ❌ Offline mode with cached hexagon data
- ❌ Route planning with safe routes
- ❌ User-submitted community reports
- ❌ Social features (share alerts)
- ❌ Wearable integration (Apple Watch, Wear OS)
- ❌ Heatmap visualization mode
- ❌ Individual incident points mode
- ❌ Push notifications (APNs/FCM)

---

## Build & Deployment

### Current Build Status

**Latest Build**: v1.0.3-24

- **Date**: January 20, 2026
- **Build Code**: Auto-incremented on each build (versionCode 24)
- **Version Management**: Centralized in `src/config/AppVersion.ts`
- **Size**: 26MB arm64-v8a APK
- **Engine**: Hermes JavaScript bytecode v94
- **Status**: Automated build & deploy workflow with verification step

### Android Build Process (Automated)

**Complete Build & Deploy Workflow:**

```bash
# One command to build and deploy
npm run android:deploy

# CRITICAL: Verify the deployment worked correctly
ls -la sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
# Should show today's date/time and correct file size (~26MB)

# Then commit and push
git add -A
git commit -m "Build and deploy Forseti Mobile v1.0.3-<new-version>"
git push origin main

# GitHub Actions will deploy to production automatically
```

**Manual Build (Legacy):**

```bash
# Load environment variables (created by setup script)
source android-env.sh

# Or manually set:
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export ANDROID_HOME=$HOME/Android
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"

# Build
cd forseti-mobile/android
./gradlew clean assembleRelease

# Manual copy to deployment location
cp app/build/outputs/apk/release/forseti-release-1.0.3-4-arm64-v8a.apk \
   ../../sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk

# CRITICAL: If users report getting old versions, verify the main download file:
ls -la sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
# If timestamp is old, manually replace it:
# rm sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
# cp <new-apk-file> sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
```

**Build Configuration**:

- Min SDK: Android 6.0 (API 23)
- Compile/Target SDK: Android 14 (API 34)
- Package: com.stlouisintegration.forseti
- Hermes: Enabled (optimized)
- ProGuard/R8: Enabled for release builds
- Architecture: arm64-v8a, armeabi-v7a, x86_64, x86

### iOS Build Process

**Prerequisites**:

- macOS with Xcode
- Apple Developer account
- CocoaPods installed

**Build**:

```bash
cd forseti-mobile/ios
pod install
open AmISafeTempInit.xcworkspace

# In Xcode:
# Product → Archive
# Distribute App → Ad Hoc or App Store
```

### Icon Generation

**Android Icons** (5 densities):

```bash
./generate-icons.sh
```

Generates:

- mdpi: 48x48px
- hdpi: 72x72px
- xhdpi: 96x96px
- xxhdpi: 144x144px
- xxxhdpi: 192x192px

**iOS Icons** (9 sizes):

```bash
./generate-ios-icons.sh
```

Generates all required iOS icon sizes from 40x40 to 1024x1024.

**Source Image**: `sites/forseti/web/themes/custom/forseti/images/logos/originals/forseti_safe.png`

### Deployment Workflow

**Git Repository Structure**:

```
forseti.life/
├── forseti-mobile/              # React Native code
└── sites/forseti/web/sites/default/files/forseti/mobile/
    └── Forseti-latest.apk       # Production APK
```

**Automated Deployment** (via GitHub Actions):

1. Commit APK to `sites/forseti/.../mobile/Forseti-latest.apk`
2. Push to main branch
3. deploy.yml workflow triggers
4. Copies APK to production `/var/www/html/forseti/.../mobile/`
5. Sets permissions (www-data:www-data, 644)

**Manual Deployment**:

```bash
# Build APK
cd forseti-mobile/android
./gradlew assembleRelease

# Copy to git repo
cp app/build/outputs/apk/release/app-release.apk \
   ../../sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk

# Commit and push
git add sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
git commit -m "Deploy Forseti Mobile v1.0.X"
git push
```

**Production URL**: https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk

---

## Background Monitoring System

### Overview

Provides continuous, real-time safety monitoring by tracking GPS location, calculating H3 hexagon position, querying the Forseti API for crime data, and sending notifications when entering high-risk areas.

### Architecture

**12-Step Process Flow**:

1. **App Initialization**: Check StorageService for previous monitoring state
2. **User Enables**: Toggle "Enable Protection" in Settings screen
3. **Permission Requests**: Foreground + background location permissions
4. **Start Tracking**: Geolocation.watchPosition() with 60s interval, 50m filter
5. **Location Update**: GPS coordinates received every 60s or 50m movement
6. **H3 Calculation**: Convert lat/lng to H3 index (resolution 11, ~700m hexagons)
7. **Hexagon Detection**: Compare with previous index, skip if same hexagon
8. **Cooldown Check**: Skip if < 5 minutes since last notification
9. **API Query**: GET `/api/amisafe/aggregated` with H3 index and resolution
10. **Z-Score Evaluation**: Compare incident_z_score with threshold (default 2.0)
11. **Send Notification**: If z≥2.0, trigger high-priority local notification
12. **State Persistence**: Save monitoring state, location history to AsyncStorage

### Key Components

**BackgroundLocationService.ts** (723 lines):

- Main monitoring service
- GPS tracking wrapper
- H3 calculation
- API communication
- Notification triggering
- State management
- Per-hexagon cooldown tracking
- Comprehensive API logging

**useBackgroundMonitoring.ts**:

- React hook for UI
- Permission handling
- Start/stop controls
- State restoration

**NotificationService.ts** (401 lines):

- Local notification delivery
- Channel management (Android)
- Deep linking configuration
- Priority settings

### 🧪 H3 Testing System (Build 55)

**Test Location**: H3 Index `8b2a134f6cb5fff` (Philadelphia high-crime area)  
**Expected Z-Score**: ~11.21 (CRITICAL risk level)  
**Testing Features**:

- **Force Real Location Check**: Manual trigger for real safety system testing
- **Show Debug Status**: View hexagon notification tracking and cooldowns
- **Comprehensive Logging**: API calls, z-scores, and notification status
- **Per-Hexagon Cooldowns**: 1-hour cooldown per H3 location (prevents spam)

**Debug Screen Navigation**: Settings → Debug Screen → H3 Location Testing

**Test Process**:
1. Enable H3 test location in Debug Screen
2. Click "Force Real Location Check" to trigger safety system
3. Monitor debug logs for API response and z-score analysis
4. Verify notification appears for high z-score (≥1.0 threshold)

**Expected Log Output**:
```
🔍 [FORCE CHECK] Simulating location update...
📍 [H3] Current H3: 8b2a134f6cb5fff (Test location enabled)
🌐 [API] Making request to /api/amisafe/aggregated
📊 [API] Response - Z-Score: 11.207, Risk: CRITICAL
✅ [LOGIC] 11.207 >= 1? YES - WILL ALERT
🔔 [NOTIFICATION] Safety alert sent successfully
```

**StorageService.ts**:

- AsyncStorage wrapper
- Monitoring state tracking
- Location history (last 100)
- User preferences

### Configuration

**Default Settings**:

```typescript
H3_RESOLUTION = 11; // ~700m hexagons
Z_SCORE_THRESHOLD = 2.0; // Alert when z≥2.0
UPDATE_INTERVAL = 60000; // 60 seconds
DISTANCE_FILTER = 50; // 50 meters
NOTIFICATION_COOLDOWN = 300000; // 5 minutes
```

**User Configurable** (Settings Screen):

- Enable/disable monitoring toggle
- Z-score threshold: 1.0 - 3.0 (sensitivity)
- Cooldown period: 1-30 minutes
- View location history (last 100)
- Clear location history

### API Integration

**Endpoint**: `GET https://forseti.life/api/amisafe/aggregated`

**Parameters**:

```
resolution=11
h3_index=8b283082d7dffff
format=json
```

**Response**:

```json
{
  "hexagons": [
    {
      "h3_index": "8b283082d7dffff",
      "incident_count": 145,
      "incident_z_score": 2.34,
      "risk_level": "HIGH",
      "last_updated": "2025-12-18T10:30:00Z"
    }
  ]
}
```

**Z-Score Interpretation**:

- `< 1.0`: Below average crime (safe)
- `1.0-2.0`: Normal range (typical)
- `2.0-3.0`: HIGH risk (2 std deviations above mean)
- `> 3.0`: CRITICAL risk (3+ std deviations)

### Notification Example

**Title**: "⚠️ High Crime Area Alert"  
**Message**: "You are entering a potentially dangerous area. 145 incidents reported here (Risk: HIGH, Z-Score: 2.3)"  
**Action**: Tap to view safety map at your location  
**URL**: `https://forseti.life/safety-map?lat=39.9526&lng=-75.1652`

---

## API Integration

### Base URL

**Production**: `https://forseti.life`  
**Authentication**: Session-based with CSRF tokens

### Endpoints

#### 1. Aggregated Hexagon Data

```http
GET /api/amisafe/aggregated?resolution=11&h3_index=8b283082d7dffff&format=json
```

**Response**:

```json
{
  "hexagons": [{
    "h3_index": "8b283082d7dffff",
    "incident_count": 145,
    "incident_z_score": 2.34,
    "risk_level": "HIGH",
    "crime_types": {...},
    "time_distribution": {...}
  }],
  "analytics": {
    "z_scores": {...}
  }
}
```

#### 2. Individual Incidents

```http
GET /api/amisafe/incidents?limit=1000&format=json
```

#### 3. Citywide Statistics

```http
GET /api/amisafe/citywide-stats?format=json
```

#### 4. Authentication

```http
GET /session/token
POST /user/login
```

### Service Classes

**DrupalCrimeService.js**:

- Complete API client for crime data
- 7+ production endpoints
- Axios-based HTTP client
- Error handling and retry logic

**DrupalAuthService.js**:

- Session-based authentication
- CSRF token management
- Auto-login support
- Demo mode fallback

**H3LocationService.js**:

- H3 spatial calculations
- Resolution mapping
- Coordinate conversions

---

## Screen-by-Screen Guide

### Home Screen

**File**: `src/screens/Home/HomeScreen.tsx`

**Features**:

- Current location display
- Real-time safety score
- Quick statistics (incidents, alerts)
- Quick action buttons:
  - View Safety Map
  - How It Works
  - Emergency 911
  - Community
  - About Forseti

**API Calls**:

- Location service for current position
- Mock safety score (will integrate real API)

### Map Screen

**File**: `src/screens/CrimeMapScreen.js`

**Features**:

- Interactive Google Maps
- H3 hexagon overlays with z-score colors
- Tap hexagon for details
- Filter panel (crime type, district, date, time)
- Statistics display
- Zoom-based resolution switching

**API Calls**:

- `/api/amisafe/aggregated` - Main hexagon data
- `/api/amisafe/citywide-stats` - Statistics

### Chat Screen

**File**: `src/screens/Chat/ChatScreen.js`

**Features**:

- AI conversation with Forseti
- Message history
- Connects to Drupal AI backend
- Save conversations (authenticated users)

**API Calls**:

- `/api/amisafe/chat` - AI conversation endpoint

### Community Screen

**File**: `src/screens/Community/CommunityScreen.tsx`

**Features**:

- Community guidelines
- Safety tips
- Links to website resources
- Get Forseti Mobile download info

### SafetyFactors Screen

**File**: `src/screens/SafetyFactors/SafetyFactorsScreen.tsx`

**Features**:

- Explanation of 7-dimension safety framework
- How safety scores are calculated
- Factor definitions (Safe, Energized, Connected, etc.)
- Links to website for detailed info

### Profile Screen

**File**: `src/screens/Profile/ProfileScreen.tsx`

**Features**:

- Login/logout
- User profile information
- Settings access
- Conversation history
- About/Privacy/Contact links

### Settings Screen

**File**: `src/screens/Settings/SettingsScreen.tsx`

**Features**:

- Background monitoring toggle
- Z-score threshold slider
- Cooldown period selector
- Location history viewer
- Clear history button
- About/How It Works/Privacy navigation

---

## Known Issues & Troubleshooting

### Android Build Configuration

**✅ Resolved Issues**:

**1. React Native Maps Compatibility**

- **Issue**: react-native-maps 1.26.20 failed to compile with AGP 8.0.2
- **Solution**: Downgraded to version 1.7.1 for compatibility with React Native 0.72.6
- **Patch Applied**: Added namespace declaration for AGP 8.0+ (`com.rnmaps.maps`)
- **Status**: Fixed with patch file `patches/react-native-maps+1.7.1.patch`

**2. Android SDK API Level**

- **Issue**: API 35 android.jar corrupted/incompatible with AGP 8.0.2 aapt2
- **Solution**: Changed compileSdkVersion from 35 to 34
- **Status**: Fixed - builds successfully with API 34

**3. Kotlin Version Compatibility**

- **Issue**: React Native 0.72 uses Kotlin 1.7.x, newer AGP versions require 1.9+
- **Solution**: Using AGP 8.0.2 with Kotlin 1.8.22
- **Status**: Stable configuration

**4. Patch Package Conflicts**

- **Issue**: Failed patches for react-native-gesture-handler, react-native-safe-area-context, react-native-screens
- **Reason**: Package versions upgraded (2.8.0→2.30.0, 4.4.1→4.14.1, 3.18.2→3.37.0)
- **Status**: Warnings only, build succeeds

### Current Issues

**✅ Recently Resolved (Build 55)**:

**1. NotificationService Import Issue** ✅ FIXED

- **Previous Issue**: NotificationService imports were commented out in BackgroundLocationService.ts
- **Error**: "Property 'NotificationService' doesn't exist" preventing safety notifications
- **Solution**: Uncommented import and tested notification delivery
- **Status**: ✅ Resolved - notifications now working with H3 test location Z-Score 11.21

**2. Notification Cooldown System** ✅ IMPROVED

- **Previous Issue**: Global 5-minute cooldown prevented notifications for different locations
- **Solution**: Implemented per-hexagon cooldown tracking (1 hour per H3 location)
- **Benefit**: Allows notifications for different areas while preventing spam
- **Status**: ✅ Enhanced with Map-based hexagon tracking

**🔄 Current Active Issues**:

**1. Chat Functionality Disabled** ⚠️

- **Status**: Tab commented out in App.tsx navigation
- **Reason**: API integration errors during testing
- **Priority**: Low - not currently being worked on
- **Code**: ChatScreen files exist but inactive

**2. Gradle Version Mismatch** ⚠️

- **Issue**: Using Gradle 8.0.1 with AGP 7.4.2 (compatibility concerns)
- **Impact**: May cause build failures in some environments
- **Priority**: Medium - monitor for issues

### Troubleshooting Guide

**Build Failures**:

```bash
# Clean everything
cd forseti-mobile
source android-env.sh
cd android
./gradlew clean
cd ..
rm -rf node_modules
npm install
cd android && ./gradlew assembleDebug --no-daemon
```

**Android SDK Issues**:

```bash
# Reinstall Android Platform 34
cd $ANDROID_HOME
cmdline-tools/latest/bin/sdkmanager --uninstall "platforms;android-34"
cmdline-tools/latest/bin/sdkmanager "platforms;android-34"

# Verify installation
ls -lh $ANDROID_HOME/platforms/android-34/android.jar
```

**Java Version Problems**:

```bash
# Check Java version (must be 17+)
java -version

# Install Java 17 if needed
sudo apt-get update
sudo apt-get install openjdk-17-jdk

# Set JAVA_HOME
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
```

**Metro Bundler Issues**:

```bash
# Clear Metro cache
npm start -- --reset-cache

# Or
rm -rf $TMPDIR/metro-*
rm -rf $TMPDIR/react-*
```

**Permission Errors**:

```bash
# Android: Check AndroidManifest.xml has:
# - ACCESS_FINE_LOCATION
# - ACCESS_COARSE_LOCATION
# - INTERNET

# iOS: Check Info.plist has:
# - NSLocationWhenInUseUsageDescription
# - NSLocationAlwaysUsageDescription
```

**APK Installation Fails**:

```bash
# Check APK signature
keytool -printcert -jarfile app-release.apk

# Uninstall old version first
adb uninstall com.stlouisintegration.forseti
adb install app-release.apk
```

### Build Environment Details

**Working Configuration**:

- Java 17 (JDK 17.0.17)
- Android SDK Platform 34
- Build Tools 34.0.0
- Gradle 8.0.1
- Android Gradle Plugin 8.0.2
- Kotlin 1.8.22
- React Native 0.72.6
- react-native-maps 1.7.1 (with namespace patch)

---

## Development Workflow

### Development Environment

**Requirements**:

- Node.js 18+ (LTS recommended)
- npm package manager
- React Native CLI (`npm install -g react-native-cli`)
- Android Studio with SDK Platform 34
- Java JDK 17 (for Android builds - required for AGP 8.0.2+)
- Android NDK 25.1.8937393
- Android Build Tools 34.0.0

**IDE Recommendations**:

- VS Code with React Native Tools extension
- Android Studio for Android-specific debugging
- Xcode for iOS (Mac only)

### Quick Setup

**Automated Setup (Recommended)**:

```bash
cd forseti.life/script
./setup-forseti-mobile-dev.sh
```

This script will:

- Install npm dependencies
- Set up Android SDK (Platform 34, Build Tools 34.0.0)
- Install Java 17 if needed
- Create `android-env.sh` with correct environment variables
- Update build configuration for compatibility
- Install react-native-maps 1.7.1
- Apply patches for AGP 8.0+ compatibility
- Create `android/local.properties`

### Running in Development

**Start Metro Bundler**:

```bash
npm start
# Or with cache reset
npm run start:reset
```

**Run on Android Emulator**:

```bash
npm run android:dev
# Or simply
npm run android
```

**Run on iOS Simulator** (Mac only):

```bash
npm run ios
```

**Run on Physical Device**:

```bash
# Android
adb devices
npm run android
```

### Build Fixes & Configuration

**Known Build Issues (Resolved)**:

1. **Flipper Version Mismatch**
   - **Issue**: `Could not find com.facebook.flipper:flipper-fresco-plugin:0.201.0`
   - **Fix**: Changed Flipper version to 0.182.0 in `android/gradle.properties`

2. **Manifest Merger Conflict**
   - **Issue**: `usesCleartextTraffic` conflict between debug and main manifest
   - **Fix**: Added `tools:replace="android:usesCleartextTraffic"` to debug manifest

3. **React Native Maps Package Import**
   - **Issue**: Wrong package path in autogenerated code
   - **Fix**: Cleaned build cache and regenerated with proper configuration

4. **npm Peer Dependencies**
   - **Issue**: Conflicting peer dependencies
   - **Fix**: Use `npm install --legacy-peer-deps` for all installations

5. **patch-package Missing**
   - **Issue**: postinstall script failing
   - **Fix**: Removed postinstall script from package.json

**If Build Fails, Try This Sequence**:

```bash
# 1. Clean everything
cd forseti-mobile
rm -rf node_modules
rm -rf android/app/build
rm -rf android/.gradle
cd android && ./gradlew clean && cd ..

# 2. Reinstall dependencies
npm install --legacy-peer-deps

# 3. Clean Metro cache
npm start -- --reset-cache

# 4. Build again
npm run android:build:debug
```

# iOS

# Open Xcode, select your device, click Run

````

### Debugging

**React Native Debugger**:

- Shake device or press Cmd+D (iOS) / Cmd+M (Android)
- Select "Debug" to open Chrome DevTools

**Console Logs**:

```bash
# Android
adb logcat | grep ReactNativeJS

# iOS
react-native log-ios
````

**Network Debugging**:

- React Native Debugger has Network tab
- Or use Flipper (Facebook's mobile debugging tool)

### Testing

**Manual Testing Checklist**:

- [ ] App launches without crashes
- [ ] Location permissions requested and granted
- [ ] Map loads with hexagons
- [ ] Background monitoring toggles on/off
- [ ] Settings persist after app restart
- [ ] Login/logout works
- [ ] Chat sends and receives messages
- [ ] All navigation works

**Automated Tests** (TODO):

```bash
npm test
```

### Code Style

**Linting**:

```bash
npm run lint
```

**Format**:

```bash
npm run format
```

**TypeScript Check**:

```bash
tsc --noEmit
```

---

## Branding & Assets

### Application Names

**Current Status**:

- **Display Name**: "Forseti" ✅
- **Android Package**: com.stlouisintegration.forseti
- **iOS Bundle**: AmISafeTempInit (needs update to Forseti)

### App Icons

**Source Image**: `forseti_safe.png`

- Location: `sites/forseti/web/themes/custom/forseti/images/logos/originals/`
- Dimensions: 407 x 462 pixels
- Format: PNG with transparency

**Android Icons** (5 densities):

- ✅ mdpi, hdpi, xhdpi, xxhdpi, xxxhdpi
- ✅ Round variants for all densities
- Location: `android/app/src/main/res/mipmap-*/`

**iOS Icons** (9 sizes):

- ✅ All required sizes (40x40 to 1024x1024)
- Location: `ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/`

**Icon Update**: December 18, 2025

- Changed from default React Native icon
- Now uses forseti_safe.png (less whitespace)
- Generated via ImageMagick scripts

### Colors

**Forseti Brand Colors**:

```typescript
primary: '#1E40AF'; // Forseti blue
secondary: '#10B981'; // Success green
accent: '#F59E0B'; // Warning amber
danger: '#EF4444'; // Error red
background: '#F9FAFB'; // Light gray
text: '#111827'; // Dark gray
```

**Previous (AmISafe)**:

- ❌ Neon green (#00FF00) - removed
- ❌ Bright orange accents - removed

### Links to Website

**All External Links Point to forseti.life** ✅:

- Safety Map: https://forseti.life/safety-map
- How It Works: https://forseti.life/how-it-works
- About: https://forseti.life/about
- Community: https://forseti.life/community
- Privacy: https://forseti.life/privacy
- Contact: https://forseti.life/contact

---

## Appendix

### Version History

**v1.0.2** (December 18, 2025):

- Fixed App.js/App.tsx conflict
- Removed old AmISafe single-file app
- Temporarily disabled NotificationService
- Updated icons to forseti_safe.png
- Simplified APK deployment (no versioning/symlinks)

**v1.0.1** (December 18, 2025):

- Updated content parity with website
- Enhanced HowItWorks screen
- Updated SafetyFactors screen
- Icon updates
- Symlink deployment strategy

**v1.0.0** (December 13, 2024):

- Initial Forseti rebranding
- Tab navigation implementation
- Background monitoring system
- H3 geospatial integration
- Authentication system

### Dependencies

**Core**:

- react: 18.2.0
- react-native: 0.76.9
- @react-navigation/native: 6.x
- @react-navigation/bottom-tabs: 6.x
- @react-navigation/stack: 6.x

**Location & Maps**:

- react-native-geolocation-service: 5.3.1
- react-native-maps: 1.7.1
- h3-js: 4.1.0

**Storage & State**:

- @react-native-async-storage/async-storage: 1.19.5
- axios: 1.6.0

**UI**:

- react-native-vector-icons: 10.x
- react-native-gesture-handler: 2.x
- react-native-safe-area-context: 4.x
- react-native-screens: 3.x

**Development**:

- typescript: 5.x
- @types/react: 18.x
- @types/react-native: 0.72.x

### Contact & Support

**Project Repository**: https://github.com/keithaumiller/forseti.life  
**Website**: https://forseti.life  
**Developer**: Keith Aumiller

---

_Last Updated: December 18, 2025_
_Document Version: 1.0_
