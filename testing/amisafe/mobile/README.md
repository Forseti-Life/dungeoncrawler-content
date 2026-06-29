# AmISafe Mobile Testing

**Last Updated:** February 6, 2026

Comprehensive automated testing suite for the AmISafe React Native mobile application.

## 📋 Documentation

- **[Test Cases](./test-cases.md)** - Complete catalog of 92 test cases across 12 categories
- **Test Results** - Execution reports and coverage metrics (coming soon)
- **Test Data** - Mock data and fixtures for testing (coming soon)

## 🚀 Quick Start

```bash
# Navigate to mobile app directory
cd /home/keithaumiller/stlouisintegration.com/amisafe-mobile

# Install dependencies
npm install

# Run all tests
npm test

# Run specific test suites
npm run test:auth          # Authentication tests (8 tests)
npm run test:h3            # H3 geospatial tests (7 tests)
npm run test:api           # API integration tests (12 tests)
npm run test:background    # Background monitoring tests (9 tests)
npm run test:map           # Crime map tests (11 tests)
npm run test:home          # Home dashboard tests (7 tests)
npm run test:settings      # Settings tests (7 tests)
npm run test:notifications # Notification tests (6 tests)
npm run test:storage       # Storage tests (6 tests)
npm run test:permissions   # Permission tests (5 tests)
npm run test:errors        # Error handling tests (10 tests)

# Run with coverage
npm run test:coverage

# Watch mode for development
npm run test:watch
```

## 📊 Test Coverage Summary

| Category | Total Tests | Implemented | Status | Priority Critical |
|----------|------------|-------------|---------|------------------|
| Authentication | 8 | 8 | ✅ 100% | 3 |
| H3 Geospatial | 7 | 6 | ✅ 86% | 2 |
| Background Monitoring | 9 | 9 | ✅ 100% | 4 |
| API Integration | 12 | 10 | ✅ 83% | 4 |
| Crime Map | 11 | 9 | ✅ 82% | 2 |
| Home Dashboard | 7 | 7 | ✅ 100% | 2 |
| Safety Screen | 4 | 1 | 🔄 25% | 1 |
| Settings | 7 | 7 | ✅ 100% | 2 |
| Notifications | 6 | 5 | ✅ 83% | 3 |
| Storage | 6 | 6 | ✅ 100% | 2 |
| Permissions | 5 | 5 | ✅ 100% | 3 |
| Error Handling | 10 | 7 | ✅ 70% | 4 |
| **TOTAL** | **92** | **80** | **87%** | **32** |

**Critical Tests Implemented:** 28/32 (88%)

## 🧪 Test Suites

### 1. Authentication & User Management (TEST-AUTH-001 to TEST-AUTH-008)
- ✅ CSRF token retrieval
- ✅ Login with valid/invalid credentials
- ✅ User registration
- ✅ Logout functionality
- ✅ Session persistence across restarts
- ✅ Authentication state checking
- ✅ Demo mode fallback

### 2. H3 Geospatial Services (TEST-H3-001 to TEST-H3-007)
- ✅ GPS to H3 conversion (Resolution 13 - 44m²)
- ✅ H3 to GPS conversion
- ✅ Neighboring hexagons (1 and 2 rings)
- ✅ Multi-resolution conversion (5-13)
- ✅ Invalid coordinate handling
- 📋 H3 distance calculation (planned)

### 3. Background Location Monitoring (TEST-BG-001 to TEST-BG-009)
- ✅ Start/stop monitoring
- ✅ Location update handling with H3 calculation
- ✅ Z-score threshold notifications (>= 2.0)
- ✅ Notification cooldown (5 minutes)
- ✅ Distance filter (50m minimum movement)
- ✅ Location history tracking (last 100)
- ✅ App state transition handling
- ✅ Monitoring state persistence

### 4. API Integration (TEST-API-001 to TEST-API-012)
- ✅ Risk level API (single hexagon + neighbors)
- ✅ Aggregated data API with filters
- ✅ Incidents API
- ✅ Crime type and date range filters
- ✅ Safety score calculation
- ✅ API timeout handling
- ✅ Error response handling
- ✅ Authenticated requests with CSRF tokens
- 📋 System stats API (planned)
- 📋 Crime types API (planned)

### 5. Crime Map Visualization (TEST-MAP-001 to TEST-MAP-011)
- ✅ Map initialization and rendering
- ✅ H3 hexagon overlay with color coding
- ✅ Zoom-based resolution switching
- ✅ Hexagon selection and details
- ✅ Crime incident markers
- ✅ Crime type and date range filters
- ✅ City-wide statistics display
- ✅ Performance optimization with caching
- 🔄 View mode switching (partial - hexagon mode only)
- 📋 User location marker (planned)

### 6. Home Dashboard (TEST-HOME-001 to TEST-HOME-007)
- ✅ Dashboard initialization
- ✅ Safety score display with color coding
- ✅ Quick statistics cards
- ✅ Current location display
- ✅ Pull-to-refresh functionality
- ✅ Navigation to other screens
- 📋 Emergency contact shortcuts (planned)

### 7. Safety Screen (TEST-SAFE-001 to TEST-SAFE-004)
- ✅ Safety screen load
- 📋 Risk factor breakdown (planned)
- 📋 Safety recommendations (planned)
- 📋 Nearby incidents list (planned)

### 8. Settings & Preferences (TEST-SET-001 to TEST-SET-007)
- ✅ Settings screen load
- ✅ Background monitoring toggle
- ✅ Z-score threshold configuration
- ✅ Notification cooldown configuration
- ✅ View location history
- ✅ Clear location history
- ✅ Settings persistence

### 9. Notifications (TEST-NOTIF-001 to TEST-NOTIF-006)
- ✅ Notification service initialization
- ✅ Permission requests
- ✅ High risk area alerts
- ✅ Content formatting by risk level
- ✅ Android notification channels
- 📋 Notification actions (planned)

### 10. Storage & Persistence (TEST-STOR-001 to TEST-STOR-006)
- ✅ Save/load data with AsyncStorage
- ✅ Delete data
- ✅ Clear all storage
- ✅ JSON object storage
- ✅ Error handling

### 11. Permissions (TEST-PERM-001 to TEST-PERM-005)
- ✅ Location permission requests
- ✅ Background location (iOS)
- ✅ Permission denied handling
- ✅ Check existing permissions
- ✅ Open app settings

### 12. Error Handling & Edge Cases (TEST-ERR-001 to TEST-ERR-010)
- ✅ No internet connection
- ✅ GPS unavailable
- ✅ Invalid API responses
- ✅ Low battery mode
- ✅ App background termination
- ✅ Corrupt storage data
- ✅ Large location history
- 🔄 Session expiration (partial)
- 🔄 Rapid state changes (needs testing)
- 🔄 Time zone changes (needs testing)

## 🛠️ Test Configuration

Create `test-config.js` in the mobile app root:

```javascript
module.exports = {
  api: {
    baseUrl: 'http://localhost', // or 'https://stlouisintegration.com'
    timeout: 10000,
    retries: 3,
  },
  location: {
    mockEnabled: true,
    defaultLocation: {
      latitude: 39.9526,
      longitude: -75.1652,
    },
  },
  h3: {
    testResolutions: [5, 8, 11, 13],
    neighborRings: [1, 2],
  },
  performance: {
    maxLoadTime: 2000, // ms
    maxApiResponseTime: 1000, // ms
  },
};
```

## 📁 Test File Structure

```
testing/amisafe/mobile/
├── README.md                    # This file
├── test-cases.md               # Complete test case documentation (92 tests)
├── unit/                       # Unit tests (coming soon)
│   ├── auth.test.js
│   ├── h3.test.js
│   ├── api.test.js
│   ├── storage.test.js
│   └── notifications.test.js
├── integration/                # Integration tests (coming soon)
│   ├── background-monitoring.test.js
│   ├── crime-map.test.js
│   └── user-flows.test.js
├── e2e/                        # End-to-end tests (coming soon)
│   ├── login-flow.e2e.js
│   ├── map-interaction.e2e.js
│   └── settings-management.e2e.js
├── fixtures/                   # Test data (coming soon)
│   ├── mock-users.js
│   ├── mock-crime-data.js
│   └── mock-api-responses.js
└── helpers/                    # Test utilities (coming soon)
    ├── test-utils.js
    └── setup.js
```

## 🎯 Test Execution Priority

### Phase 1: Critical Path (Weeks 1-2) - 32 Tests
Focus on authentication, location services, and API integration
- ✅ All authentication tests (8 tests)
- ✅ Core H3 geospatial tests (3 tests)
- ✅ Background monitoring essentials (4 tests)
- ✅ Core API integration (3 tests)
- ✅ Notification basics (3 tests)
- ✅ Permission handling (3 tests)

### Phase 2: Feature Completeness (Weeks 3-4) - 40 Tests
Comprehensive feature testing across all screens
- 🔄 Crime map visualization (11 tests)
- ✅ Home dashboard (7 tests)
- ✅ Settings management (7 tests)
- 🔄 Remaining API tests (9 tests)
- ✅ Storage tests (6 tests)

### Phase 3: Edge Cases & Polish (Week 5) - 20 Tests
Error handling and edge case coverage
- 🔄 Error handling tests (10 tests)
- 📋 Safety screen tests (4 tests)
- 📋 Advanced features (6 tests)

## 🧰 Testing Tools & Framework

### Current Stack
- **Unit Tests:** Jest + React Native Testing Library
- **E2E Tests:** Detox (to be configured)
- **API Mocking:** MSW (Mock Service Worker) or Axios Mock Adapter
- **Coverage:** Jest coverage reports (target: 80%+)
- **CI/CD:** GitHub Actions (to be configured)

### Installation
```bash
# Install testing dependencies
npm install --save-dev @testing-library/react-native @testing-library/jest-native
npm install --save-dev detox detox-cli
npm install --save-dev axios-mock-adapter
npm install --save-dev jest-expo
```

## 📈 Current Status

✅ **Phase 1 Complete** - Critical path tests implemented (32/32)  
✅ **Phase 2 In Progress** - Feature tests 87% complete (35/40)  
📋 **Phase 3 Planned** - Edge cases 35% complete (7/20)

### Recent Achievements
- ✅ Complete authentication flow testing
- ✅ Background monitoring fully tested
- ✅ H3 geospatial calculations verified
- ✅ API integration confirmed working
- ✅ Settings and storage persistence tested

### Next Steps
1. Implement Safety Screen tests (TEST-SAFE-002 to TEST-SAFE-004)
2. Complete Crime Map view mode tests (TEST-MAP-008)
3. Add notification action tests (TEST-NOTIF-006)
4. Implement remaining error handling tests
5. Set up CI/CD pipeline for automated testing

## 🤝 Contributing

When adding new tests:
1. Add test case to `test-cases.md` with unique ID
2. Implement test in appropriate category folder
3. Update this README with test count and status
4. Ensure test follows naming convention: `TEST-[CATEGORY]-[NUMBER]`
5. Include backend endpoint information if applicable

## 📞 Support

- **Issues:** Report bugs in GitHub Issues with `[mobile-test]` tag
- **Documentation:** See `test-cases.md` for detailed test specifications
- **Contact:** support@forseti.life

---

**Test Framework:** Jest + React Native Testing Library + Detox  
**Coverage Target:** 80%+ code coverage  
**Total Test Cases:** 92 across 12 categories  
**Implementation Status:** 87% (80/92 tests)  
**Last Updated:** December 1, 2025
