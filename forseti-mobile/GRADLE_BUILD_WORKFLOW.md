# Gradle Build Workflow - Forseti Mobile APK Build Process

## Complete Build Pipeline

### 1. Pre-Build: Version Increment (Optional)

```bash
npm run version:increment
# OR directly:
./increment-build.sh
```

**Actions:**

- Reads current `versionCode` from `android/app/build.gradle`
- Increments `versionCode` by 1 (e.g., 2 → 3)
- Updates `versionName` to match `package.json` version
- Updates version display in `App.tsx` header: `v1.0.3(2026-01-15)`
- Outputs new version info to console

---

### 2. Gradle Initialization Phase

```bash
cd android
./gradlew assembleRelease
```

**Step 2.1: Gradle Wrapper Initialization**

- Executes `./gradlew` wrapper script
- Verifies Gradle 8.0.1 installation
- Sets up JVM with parameters from `gradle.properties`:
  - `-Xmx4096m` (4GB heap)
  - `-XX:MaxMetaspaceSize=1024m`
  - Daemon mode enabled
  - Parallel execution enabled
  - Build caching enabled

**Step 2.2: Settings Configuration (`settings.gradle`)**

```gradle
pluginManagement {
    repositories {
        mavenCentral()
        gradlePluginPortal()
        maven { url 'https://maven.google.com' }
    }
}
rootProject.name = 'Forseti'
apply from: file("../node_modules/@react-native-community/cli-platform-android/native_modules.gradle")
applyNativeModulesSettingsGradle(settings)
include ':app'
includeBuild('../node_modules/@react-native/gradle-plugin')
```

**Actions:**

- Configures plugin repositories (Maven Central, Gradle Portal, Google Maven)
- Sets root project name to "Forseti"
- Discovers and includes React Native native modules (auto-linking)
- Includes `:app` module (main application)
- Includes React Native Gradle Plugin as composite build

---

### 3. Project Configuration Phase

**Step 3.1: Root `build.gradle` Configuration**

```gradle
buildscript {
    ext {
        buildToolsVersion = "34.0.0"
        minSdkVersion = 23          // Android 6.0
        compileSdkVersion = 34      // Android 14
        targetSdkVersion = 34
        kotlinVersion = "1.8.22"
        ndkVersion = "25.1.8937393"
    }
    dependencies {
        classpath("com.android.tools.build:gradle:7.4.2")  // AGP 7.4.2
        classpath("com.facebook.react:react-native-gradle-plugin")
        classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:1.8.22")
    }
}
```

**Actions:**

- Defines build variables (SDK versions, Kotlin version, NDK version)
- Downloads Android Gradle Plugin 7.4.2
- Downloads React Native Gradle Plugin
- Downloads Kotlin Gradle Plugin 1.8.22
- Configures all subprojects:
  - Forces Kotlin JVM target to Java 11
  - Forces androidx.core version to 1.13.1
  - Sets up Maven/Google repositories

---

### 4. App Module Configuration Phase

**Step 4.1: Apply Plugins (`app/build.gradle`)**

```gradle
apply plugin: "com.android.application"
apply plugin: "com.facebook.react"
```

**Actions:**

- Applies Android Application plugin (AGP 7.4.2)
- Applies React Native plugin (configures JS bundling, Hermes, native modules)

**Step 4.2: React Native Configuration**

```gradle
react {
    // Uses defaults:
    // - root = file("../")
    // - reactNativeDir = file("../node_modules/react-native")
    // - entryFile = "index.js"
    // - hermesEnabled = true (from gradle.properties)
}
```

**Actions:**

- Configures React Native bundler (Metro)
- Enables Hermes bytecode compilation
- Sets JavaScript entry point to `index.js`

**Step 4.3: Android Configuration**

```gradle
android {
    compileSdkVersion 34
    namespace "com.stlouisintegration.forseti"

    defaultConfig {
        applicationId "com.stlouisintegration.forseti"
        minSdkVersion 23
        targetSdkVersion 34
        versionCode 3
        versionName "1.0.3"
    }

    signingConfigs {
        release {
            // Uses release.keystore if FORSETI_RELEASE_STORE_FILE defined
            // Otherwise falls back to debug.keystore
        }
    }

    buildTypes {
        release {
            signingConfig = release or debug
            minifyEnabled false      // R8/ProGuard disabled
            shrinkResources false    // Resource shrinking disabled
            debuggable true          // Enabled for testing
        }
    }

    splits {
        abi {
            enable true
            universalApk false
            include "armeabi-v7a", "arm64-v8a", "x86", "x86_64"
        }
    }
}
```

**Actions:**

- Sets compilation target to Android 14 (SDK 34)
- Configures app namespace and application ID
- Sets min SDK to Android 6.0 (API 23)
- Configures release signing (keystore)
- Enables ABI splits (separate APKs per architecture)
- Sets output APK naming pattern: `forseti-release-1.0.3-3-arm64-v8a.apk`

**Step 4.4: Dependency Resolution**

```gradle
dependencies {
    implementation("com.facebook.react:react-android")
    implementation("com.facebook.react:hermes-android")

    // Auto-linked native modules from node_modules:
    // - react-native-vector-icons
    // - react-native-geolocation-service
    // - react-native-permissions
    // - @react-native-async-storage/async-storage
    // - react-native-maps
    // etc.
}
```

**Actions:**

- Downloads React Native Android library
- Downloads Hermes Android runtime
- Auto-discovers and links all native modules via `applyNativeModulesAppBuildGradle()`
- Resolves all transitive dependencies
- Downloads from Maven Central, Google Maven, npm registry

---

### 5. JavaScript Bundling Phase

**Step 5.1: Metro Bundler Invocation**

The React Native Gradle Plugin automatically invokes Metro bundler:

```bash
# Equivalent Metro command:
npx react-native bundle \
  --platform android \
  --dev false \
  --entry-file index.js \
  --bundle-output android/app/build/generated/assets/react/release/index.android.bundle \
  --assets-dest android/app/build/generated/res/react/release
```

**Actions:**

- Starts Metro bundler
- Resolves all JavaScript imports starting from `index.js`
- Bundles all TypeScript/JavaScript files:
  - `App.tsx` → transpiled to JS
  - All `src/**/*.tsx` files → transpiled to JS
  - `node_modules` dependencies → bundled
- Creates single `index.android.bundle` file (~2-3 MB)
- Copies image assets to `res/` directory
- Generates source maps (for debugging)

**Step 5.2: Hermes Bytecode Compilation**

```bash
# Hermes compiler is automatically invoked
hermesc -O -output-source-map \
  -emit-binary \
  -out android/app/build/generated/assets/react/release/index.android.bundle
```

**Actions:**

- Takes JavaScript bundle as input
- Compiles to Hermes bytecode (.hbc format)
- Optimizes bytecode (-O flag)
- Generates bytecode source map
- Replaces JS bundle with bytecode bundle (~1.5-2 MB, smaller than JS)
- Result: Faster app startup, lower memory usage

---

### 6. Native Code Compilation Phase

**Step 6.1: Java/Kotlin Compilation**

```bash
# Gradle tasks executed:
compileReleaseJavaWithJavac
compileReleaseKotlin
```

**Actions:**

- Compiles Java source files:
  - `MainActivity.java`
  - `MainApplication.java`
  - Native module bridge classes
  - Auto-generated BuildConfig.java
- Compiles Kotlin source files:
  - `LocationServiceModule.kt`
  - `LocationTrackingService.kt`
- Targets Java 11 bytecode
- Outputs `.class` files to `build/intermediates/javac/release/classes/`

**Step 6.2: Native Library Compilation (NDK)**

```bash
# If app uses C/C++ native code (not currently used in Forseti)
ndkBuild or CMake compilation would happen here
```

**Actions (for React Native native modules):**

- Extracts pre-built `.so` files from AAR dependencies
- Organizes per architecture:
  - `lib/armeabi-v7a/` (32-bit ARM)
  - `lib/arm64-v8a/` (64-bit ARM)
  - `lib/x86/` (32-bit x86)
  - `lib/x86_64/` (64-bit x86)
- Native libraries included:
  - `libreactnativejni.so` (React Native JNI bridge)
  - `libhermes.so` (Hermes JavaScript engine)
  - `libjsi.so` (JavaScript Interface)
  - Native module `.so` files (maps, geolocation, etc.)

---

### 7. Resource Processing Phase

**Step 7.1: AAPT2 (Android Asset Packaging Tool)**

```bash
# Gradle tasks:
processReleaseResources
generateReleaseResources
```

**Actions:**

- Processes XML resources:
  - `AndroidManifest.xml` → merged with library manifests
  - `res/values/strings.xml`
  - `res/values/colors.xml`
  - `res/layout/*.xml` (if any)
  - `res/drawable/*.xml` (vector drawables)
- Processes image assets:
  - `mipmap-*dpi/ic_launcher.png` (app icons)
  - JavaScript-bundled images from Metro
  - `drawable-*dpi/*.png` resources
- Generates `resources.arsc` (compiled resources)
- Assigns resource IDs (R.java generation)
- Optimizes PNG images (lossless compression)

**Step 7.2: Manifest Merging**

```xml
<!-- Merges manifests from: -->
1. app/src/main/AndroidManifest.xml (main)
2. react-native library manifest
3. All native module manifests (permissions, services, etc.)
```

**Final merged manifest includes:**

- Package name: `com.stlouisintegration.forseti`
- Min/Target SDK versions
- Permissions:
  - `ACCESS_FINE_LOCATION`
  - `ACCESS_COARSE_LOCATION`
  - `ACCESS_BACKGROUND_LOCATION`
  - `POST_NOTIFICATIONS` (Android 13+)
  - `FOREGROUND_SERVICE`
  - `FOREGROUND_SERVICE_LOCATION`
  - `INTERNET`
  - `ACCESS_NETWORK_STATE`
- Components:
  - `MainActivity`
  - `LocationTrackingService`
  - Broadcast receivers
  - Content providers (if any)

---

### 8. DEX Compilation Phase

**Step 8.1: D8 Dexer (Java Bytecode → DEX)**

```bash
# Gradle task:
dexBuilderRelease
mergeDexRelease
```

**Actions:**

- Converts all `.class` files to Dalvik bytecode (`.dex`)
- Merges DEX files from:
  - App classes
  - React Native classes
  - Kotlin stdlib
  - All dependency libraries (AndroidX, OkHttp, etc.)
- Multi-dex enabled (if method count > 65,536)
- Outputs `classes.dex`, `classes2.dex`, etc. to `build/intermediates/dex/release/`
- Total DEX size: ~5-8 MB

**Step 8.2: R8 Optimizer (Disabled for Forseti)**

```gradle
minifyEnabled false
shrinkResources false
```

**If enabled, R8 would:**

- Remove unused code (tree shaking)
- Obfuscate class/method names
- Optimize bytecode
- Reduce APK size by 30-40%
- Currently disabled for easier debugging

---

### 9. APK Assembly Phase

**Step 9.1: Package APK (per ABI)**

```bash
# Gradle task:
packageRelease
```

**Actions:**
For each ABI (arm64-v8a, armeabi-v7a, x86, x86_64):

1. **Create APK structure:**

   ```
   forseti-release-1.0.3-3-arm64-v8a.apk
   ├── AndroidManifest.xml (compiled)
   ├── classes.dex
   ├── classes2.dex
   ├── resources.arsc
   ├── assets/
   │   └── index.android.bundle (Hermes bytecode)
   ├── lib/
   │   └── arm64-v8a/
   │       ├── libreactnativejni.so
   │       ├── libhermes.so
   │       ├── libjsi.so
   │       └── [other native modules]
   ├── res/
   │   ├── drawable-*/
   │   ├── mipmap-*/
   │   └── [other resources]
   └── META-INF/
       └── (signing certificates)
   ```

2. **Compress contents:**
   - ZIP compression (store or deflate)
   - `.so` files: stored uncompressed (extractNativeLibs)
   - `.dex` files: deflate compressed
   - Resources: deflate compressed
   - Assets: stored uncompressed

3. **Output location:**
   ```
   android/app/build/outputs/apk/release/
   ├── forseti-release-1.0.3-3-arm64-v8a.apk (26 MB)
   ├── forseti-release-1.0.3-3-armeabi-v7a.apk (22 MB)
   ├── forseti-release-1.0.3-3-x86.apk (28 MB)
   └── forseti-release-1.0.3-3-x86_64.apk (28 MB)
   ```

---

### 10. APK Signing Phase

**Step 10.1: Keystore Selection**

```gradle
signingConfig = project.hasProperty('FORSETI_RELEASE_STORE_FILE')
    ? signingConfigs.release
    : signingConfigs.debug
```

**Actions:**

- Checks for `FORSETI_RELEASE_STORE_FILE` in `gradle.properties`
- If found: Uses production `release.keystore`
- If not found: Falls back to `debug.keystore`

**Step 10.2: APK Signing (V1 + V2 + V3)**

```bash
# APK Signer tool (part of Android SDK build-tools)
apksigner sign \
  --ks debug.keystore \
  --ks-pass pass:android \
  --key-pass pass:android \
  --v1-signing-enabled true \
  --v2-signing-enabled true \
  --v3-signing-enabled true \
  --out forseti-release-1.0.3-3-arm64-v8a.apk
```

**Signing Schemes:**

- **V1 (JAR signing)**: Legacy, AndroidManifest signatures
- **V2 (APK signing)**: Faster verification, whole-file hashing
- **V3 (APK signing)**: Supports key rotation, introduced in Android 9

**Actions:**

- Generates SHA-256 hash of APK contents
- Signs hash with keystore private key
- Embeds signature in `META-INF/` directory:
  - `MANIFEST.MF` (V1)
  - `CERT.SF` (V1)
  - `CERT.RSA` (V1)
  - APK Signing Block (V2/V3, embedded in APK)
- Aligns APK on 4-byte boundaries (zipalign)

---

### 11. APK Optimization Phase

**Step 11.1: Zipalign**

```bash
zipalign -v 4 input.apk output.apk
```

**Actions:**

- Aligns uncompressed data on 4-byte boundaries
- Enables memory-mapped file access (faster resource loading)
- Reduces RAM usage when app is running
- Required for Google Play Store upload

---

### 12. Build Verification Phase

**Step 12.1: APK Analyzer**

Gradle automatically verifies:

- APK is properly signed
- Manifest is valid
- Resources are accessible
- DEX files are valid
- Native libraries are present for all declared ABIs

**Step 12.2: Output Summary**

```
BUILD SUCCESSFUL in 2m 4s
355 tasks: 109 executed, 246 up-to-date

Output Files:
android/app/build/outputs/apk/release/
├── forseti-release-1.0.3-3-arm64-v8a.apk (26 MB)
├── forseti-release-1.0.3-3-armeabi-v7a.apk (22 MB)
├── forseti-release-1.0.3-3-x86.apk (28 MB)
└── forseti-release-1.0.3-3-x86_64.apk (28 MB)
```

---

## Complete Task Execution Order

```
1. :app:preBuild
2. :app:preReleaseBuild
3. :react-native-community_async-storage:compileReleaseKotlin
4. :react-native-geolocation-service:compileReleaseKotlin
5. :app:bundleReleaseJsAndAssets (Metro + Hermes)
6. :app:mergeReleaseResources
7. :app:processReleaseManifest
8. :app:processReleaseResources (AAPT2)
9. :app:compileReleaseKotlin
10. :app:compileReleaseJavaWithJavac
11. :app:dexBuilderRelease (D8)
12. :app:mergeDexRelease
13. :app:mergeReleaseNativeLibs
14. :app:packageRelease (APK assembly)
15. :app:createReleaseApkListingFileRedirect
16. :app:assembleRelease (signing + alignment)
```

---

## Build Output Breakdown

### APK Contents (arm64-v8a example, 26 MB):

| Component                      | Size       | Description                                                  |
| ------------------------------ | ---------- | ------------------------------------------------------------ |
| `lib/arm64-v8a/*.so`           | 12 MB      | Native libraries (Hermes, JSI, React Native, native modules) |
| `assets/index.android.bundle`  | 1.8 MB     | Hermes bytecode (JavaScript app code)                        |
| `classes.dex` + `classes2.dex` | 5 MB       | Dalvik bytecode (Java/Kotlin code)                           |
| `resources.arsc`               | 2 MB       | Compiled XML resources                                       |
| `res/` drawables/mipmaps       | 3 MB       | Images and app icons                                         |
| `META-INF/`                    | 1 MB       | Signing certificates and metadata                            |
| `AndroidManifest.xml`          | 10 KB      | Compiled manifest                                            |
| **Total**                      | **~26 MB** |                                                              |

---

## Gradle Build Cache

**Cached Between Builds:**

- Unchanged compiled Java/Kotlin classes
- Unchanged DEX files
- Unchanged resources
- Unchanged native libraries
- Unchanged JavaScript bundle (if no code changes)

**Cache Location:**

```
~/.gradle/caches/
android/build/intermediates/
android/app/build/intermediates/
```

**Cache Benefits:**

- First build: ~2 minutes
- Incremental build (code change only): ~30 seconds
- Clean build (./gradlew clean): ~2 minutes

---

## Build Performance Optimization

**Current Settings (`gradle.properties`):**

```properties
org.gradle.daemon=true              # Keep Gradle daemon running
org.gradle.parallel=true            # Parallel module compilation
org.gradle.configureondemand=true   # Only configure needed modules
org.gradle.jvmargs=-Xmx4096m        # 4GB heap for Gradle
org.gradle.caching=true             # Enable build cache
```

**Typical Build Times:**

- **Clean build**: 2-3 minutes
- **Incremental build**: 20-40 seconds
- **No-op build**: 5-10 seconds

---

## Summary: Complete Build Flow

```
npm run android:build
    ↓
./increment-build.sh (auto version increment)
    ↓
cd android && ./gradlew assembleRelease
    ↓
Gradle Initialization (Wrapper, JVM, Daemon)
    ↓
Project Configuration (AGP, React Native Plugin, Dependencies)
    ↓
JavaScript Bundling (Metro → index.android.bundle)
    ↓
Hermes Compilation (JS → Bytecode)
    ↓
Java/Kotlin Compilation (→ .class files)
    ↓
Native Library Extraction (→ .so files)
    ↓
Resource Processing (AAPT2 → resources.arsc)
    ↓
Manifest Merging (→ AndroidManifest.xml)
    ↓
DEX Compilation (D8 → classes.dex)
    ↓
APK Assembly (per ABI → .apk files)
    ↓
APK Signing (V1+V2+V3 → signed .apk)
    ↓
Zipalign (4-byte alignment)
    ↓
Output: android/app/build/outputs/apk/release/
        forseti-release-1.0.3-3-arm64-v8a.apk (26 MB)
```

---

## Key Build Artifacts

**Input Files:**

- `package.json` (version)
- `android/app/build.gradle` (versionCode, versionName)
- `App.tsx` (version display)
- `index.js` (JavaScript entry)
- `src/**/*.tsx` (app source code)
- `android/app/src/main/java/**/*.java` (native modules)
- `android/app/src/main/AndroidManifest.xml`
- `android/app/src/main/res/**` (resources)
- `debug.keystore` or `release.keystore`

**Output Files:**

- `forseti-release-1.0.3-3-arm64-v8a.apk` (production ARM 64-bit)
- `forseti-release-1.0.3-3-armeabi-v7a.apk` (legacy ARM 32-bit)
- `forseti-release-1.0.3-3-x86.apk` (emulator 32-bit)
- `forseti-release-1.0.3-3-x86_64.apk` (emulator 64-bit)
- `output-metadata.json` (build metadata)

**Intermediate Files (cached):**

- `build/intermediates/javac/release/classes/`
- `build/intermediates/dex/release/`
- `build/generated/assets/react/release/`
- `build/generated/res/react/release/`
