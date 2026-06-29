# Forseti Mobile TODO List

**Priority Tasks for Current Development Cycle**
**Current Build**: v1.0.3-24 (auto-managed)
**Version Config**: `src/config/AppVersion.ts`

## 🔴 HIGH PRIORITY

### 1. Enable NotificationService

**Status**: Ready for implementation  
**Dependencies**: All packages installed, permissions configured

**Tasks:**

- [ ] Uncomment NotificationService import in `src/services/location/BackgroundLocationService.ts` (line 11)
- [ ] Uncomment notification initialization call (line 142)
- [ ] Uncomment notification scheduling calls (line 381)
- [ ] Test notification delivery on Android 13+ physical device
- [ ] Verify deep linking from notifications works properly
- [ ] Test notification cooldown functionality
- [ ] Update documentation once enabled

**Files to modify:**

- `src/services/location/BackgroundLocationService.ts`

**Testing Requirements:**

- Physical Android device with API 33+
- Location permissions granted
- Background monitoring enabled
- Move between different H3 hexagons to trigger notifications

---

## 🟡 MEDIUM PRIORITY

### 2. Gradle Version Compatibility Fix

**Status**: Build system optimization

**Options:**

- [ ] Upgrade Android Gradle Plugin from 7.4.2 to 8.0.2+ (recommended)
- [ ] OR downgrade Gradle from 8.0.1 to 7.6.4

**Files to modify:**

- `android/build.gradle` (AGP version)
- `android/gradle/wrapper/gradle-wrapper.properties` (Gradle version)

---

## 🟢 LOW PRIORITY

### 3. Chat Functionality Re-enablement

**Status**: Implemented but disabled due to API errors  
**Priority**: Low - not currently being worked on

**Tasks:**

- [ ] Debug API integration errors
- [ ] Uncomment Chat tab in `App.tsx` navigation (line 178)
- [ ] Test AI conversation functionality
- [ ] Verify message history persistence

---

## ✅ COMPLETED

- [x] Fix Android 13+ notification permissions (POST_NOTIFICATIONS added)
- [x] Add foreground service permissions (FOREGROUND_SERVICE, FOREGROUND_SERVICE_LOCATION)
- [x] Install react-native-push-notification package
- [x] Update documentation to reflect current accurate status
- [x] Fix version number display (v1.0.3-14)
- [x] **Implement console log capture and upload system**
  - [x] Create ConsoleLogService for automatic console log capture
  - [x] Add useConsoleLogs React hook for UI integration
  - [x] Integrate with Debug Console screen (upload/clear buttons)
  - [x] Add console log management to Settings screen
  - [x] Automatic upload on app background/close
  - [x] Integration with existing Drupal API endpoint `/api/amisafe/log/upload`

---

**Last Updated**: January 20, 2026  
**Current Build**: v1.0.3-19
