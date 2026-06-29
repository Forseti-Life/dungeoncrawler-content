# Android Build Guide - Forseti Mobile

**Last Updated:** February 6, 2026

## Quick Reference

### Environment Setup

```bash
# 1. Run the automated setup script (first time only)
cd /workspaces/forseti.life/script
./setup-forseti-mobile-dev.sh

# 2. Load the Android environment
cd /workspaces/forseti.life/forseti-mobile
source android-env.sh

# 3. Verify environment
java -version  # Should show 17.0.17
ls $ANDROID_HOME/platforms/  # Should show android-34
```

### Building Debug APK

```bash
# From forseti-mobile directory
source android-env.sh
cd android
./gradlew clean assembleDebug --no-daemon
```

**Output Location**: `android/app/build/outputs/apk/debug/`

**Generated Files**:

- `forseti-debug-1.0.0-1-arm64-v8a.apk` (51MB) - Modern ARM 64-bit devices
- `forseti-debug-1.0.0-1-armeabi-v7a.apk` (38MB) - Older ARM 32-bit devices
- `forseti-debug-1.0.0-1-x86_64.apk` (53MB) - 64-bit emulators
- `forseti-debug-1.0.0-1-x86.apk` (54MB) - 32-bit emulators

### Building Release APK

```bash
source android-env.sh
cd android
./gradlew clean assembleRelease --no-daemon
```

**Output Location**: `android/app/build/outputs/apk/release/`

## Configuration Details

### Build Versions

| Component             | Version      | Notes                   |
| --------------------- | ------------ | ----------------------- |
| React Native          | 0.72.6       | Stable LTS release      |
| Java                  | 17 (17.0.17) | Required for AGP 8.0.2+ |
| Android SDK           | 34           | Compile and Target SDK  |
| Min SDK               | 23           | Android 6.0+            |
| Gradle                | 8.0.1        | Build system            |
| Android Gradle Plugin | 8.0.2        | AGP                     |
| Kotlin                | 1.8.22       | Compatible with RN 0.72 |
| Build Tools           | 34.0.0       | Android build tools     |
| NDK                   | 25.1.8937393 | Native development kit  |

### Key Dependencies

**React Native Maps**: 1.7.1

- **Why this version?** Newer versions (1.26.20) have compilation issues with AGP 8.0.2
- **Patch applied**: Added namespace `com.rnmaps.maps` for AGP 8.0+ compatibility
- **Patch file**: `patches/react-native-maps+1.7.1.patch`

**React Native Gesture Handler**: 2.18.1

- Downgraded from 2.30.0 due to Kotlin compatibility issues

**React Native Screens**: 3.29.0

- Downgraded from 3.37.0 due to Kotlin compatibility issues

**React Native Safe Area Context**: 4.14.1

- Current version, patch conflicts are non-blocking

## Environment Variables

The `android-env.sh` file sets up:

```bash
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export ANDROID_HOME="$HOME/Android"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"
```

**Always source this file before building:**

```bash
source android-env.sh
```

## Common Build Issues

### Issue: "android.jar corrupted" or "RES_TABLE_TYPE_TYPE entry offsets overlap"

**Cause**: Android SDK Platform 35 has compatibility issues with AGP 8.0.2's aapt2

**Solution**: Use Platform 34 instead

```bash
# Uninstall API 35 (if installed)
cd $ANDROID_HOME
cmdline-tools/latest/bin/sdkmanager --uninstall "platforms;android-35"

# Install API 34
cmdline-tools/latest/bin/sdkmanager "platforms;android-34"
```

### Issue: "Namespace not specified" for react-native-maps

**Cause**: AGP 8.0+ requires explicit namespace declaration

**Solution**: Already patched! The patch adds:

```gradle
android {
  namespace 'com.rnmaps.maps'
  // ... rest of config
}
```

Apply patch if needed:

```bash
cd forseti-mobile
npx patch-package react-native-maps
```

### Issue: "Kotlin metadata parsing error"

**Cause**: Version mismatch between Kotlin compiler and libraries

**Solution**: Using Kotlin 1.8.22 which is compatible with:

- React Native 0.72.6 (uses Kotlin 1.7.x)
- AGP 8.0.2 (supports Kotlin 1.8.x)
- Gradle 8.0.1

**Don't upgrade** to newer Kotlin/AGP versions without testing thoroughly.

### Issue: Build fails with "Java 11 required"

**Cause**: AGP 8.0.2+ requires Java 17

**Solution**:

```bash
# Install Java 17
sudo apt-get update
sudo apt-get install openjdk-17-jdk

# Update android-env.sh
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64

# OR add to gradle.properties (recommended for persistent config):
org.gradle.java.home=/usr/lib/jvm/java-17-openjdk-amd64
```

### Issue: "react-native-maps not found" after npm install

**Cause**: Wrong version installed or patches not applied

**Solution**:

```bash
cd forseti-mobile
npm install react-native-maps@1.7.1
npx patch-package
```

## Build Configuration Files

### Key Files

**`android/build.gradle`** - Project-level Gradle config

```gradle
compileSdkVersion = 34
targetSdkVersion = 34
minSdkVersion = 23
kotlinVersion = "1.8.22"
androidXCoreVersion = "1.13.1"
```

**`android/app/build.gradle`** - App-level Gradle config

```gradle
android {
    namespace "com.stlouisintegration.forseti"
    compileSdkVersion rootProject.ext.compileSdkVersion
    // ...
}
```

**`android/gradle/wrapper/gradle-wrapper.properties`** - Gradle wrapper

```properties
distributionUrl=https\://services.gradle.org/distributions/gradle-8.0.1-all.zip
```

**`android/local.properties`** - Local SDK path (auto-generated)

```properties
sdk.dir=/home/codespace/Android
```

**`patches/react-native-maps+1.7.1.patch`** - Maps namespace patch

- Adds AGP 8.0+ namespace support
- Auto-applied during `npm install` via patch-package

## Verification Commands

### Check Environment

```bash
# Java version
java -version
# Expected: openjdk version "17.0.17"

# Android SDK
ls $ANDROID_HOME/platforms/
# Expected: android-34

# Build tools
ls $ANDROID_HOME/build-tools/
# Expected: 34.0.0

# Gradle
cd android
./gradlew --version
# Expected: Gradle 8.0.1
```

### Test Build

```bash
cd forseti-mobile
source android-env.sh
cd android
./gradlew tasks --all | grep assemble
# Should show assembleDebug, assembleRelease tasks
```

### Check Dependencies

```bash
cd forseti-mobile
npm list react-native-maps
# Expected: react-native-maps@1.7.1

npm list react-native
# Expected: react-native@0.72.6
```

## Build Performance

**Typical Build Times** (on Codespace VM):

- Clean build: ~50-60 seconds
- Incremental build: ~15-30 seconds
- With cache: ~10-15 seconds

**Build Cache Locations**:

- `android/.gradle/` - Gradle cache
- `android/app/build/` - Build outputs
- `~/.gradle/caches/` - Global Gradle cache

**To speed up builds**:

```bash
# Use Gradle daemon (default)
./gradlew assembleDebug

# Disable for CI/debugging
./gradlew assembleDebug --no-daemon

# Enable build cache
./gradlew assembleDebug --build-cache
```

## Deployment

### Install on Device/Emulator

```bash
# Connect device via USB or start emulator
adb devices

# Install debug APK
adb install android/app/build/outputs/apk/debug/forseti-debug-1.0.0-1-arm64-v8a.apk

# Or use Gradle
./gradlew installDebug
```

### Production Deployment

**After building release APK, verify deployment file:**

```bash
# Check that the website's download file was updated correctly
ls -la sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk

# Should show current date/time and ~26MB size
# If timestamp is old, manually replace:
cp android/app/build/outputs/apk/release/forseti-release-1.0.3-XX-arm64-v8a.apk \
   sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
```

**Common Issue**: Old APK file persists even after build script runs. Always verify the timestamp and file size match the new build.

### APK Signing (Release)

Release builds are automatically signed using the keystore configured in `android/gradle.properties`:

```properties
FORSETI_RELEASE_STORE_FILE=forseti-release.keystore
FORSETI_RELEASE_STORE_PASSWORD=forseti2024
FORSETI_RELEASE_KEY_ALIAS=forseti-key
FORSETI_RELEASE_KEY_PASSWORD=forseti2024
```

**⚠️ CRITICAL**: Back up `android/app/forseti-release.keystore` - losing this file means you cannot update the app on Google Play!

## Additional Resources

- **Setup Script**: `/workspaces/forseti.life/script/setup-forseti-mobile-dev.sh`
- **Environment File**: `/workspaces/forseti.life/forseti-mobile/android-env.sh`
- **Main README**: `/workspaces/forseti.life/forseti-mobile/README.md`
- **React Native Docs**: https://reactnative.dev/docs/environment-setup
- **Android Gradle Plugin**: https://developer.android.com/studio/releases/gradle-plugin
- **H3 Geospatial**: https://h3geo.org/
