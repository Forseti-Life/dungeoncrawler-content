#!/bin/bash
#
# Android Build Environment Setup Script
# Sets up Android SDK and build tools for React Native APK builds
#

set -e

echo "=========================================="
echo "Android Build Environment Setup"
echo "=========================================="
echo ""

# Check disk space
echo "Checking disk space..."
AVAILABLE_SPACE=$(df -BG / | tail -1 | awk '{print $4}' | sed 's/G//')
if [ "$AVAILABLE_SPACE" -lt 2 ]; then
    echo "WARNING: Less than 2GB available. Android SDK requires ~1-2GB."
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi
echo "✓ Sufficient disk space available"
echo ""

# Install Java if not present
echo "Checking for Java..."
if ! command -v java &> /dev/null; then
    echo "Installing OpenJDK 17..."
    sudo apt-get update -qq
    sudo apt-get install -y openjdk-17-jdk
    echo "✓ Java installed"
else
    echo "✓ Java already installed"
    java -version
fi
echo ""

# Set Java environment
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
echo "✓ JAVA_HOME set to: $JAVA_HOME"
echo ""

# Setup Android SDK directory
ANDROID_HOME="$HOME/Android"
echo "Setting up Android SDK in $ANDROID_HOME..."

# Download Android command-line tools if not present
if [ ! -d "$ANDROID_HOME/cmdline-tools/latest" ]; then
    echo "Downloading Android command-line tools (~150MB)..."
    mkdir -p "$ANDROID_HOME/cmdline-tools"
    cd "$ANDROID_HOME/cmdline-tools"
    
    wget -q --show-progress -O cmdline-tools.zip \
        https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
    
    echo "Extracting tools..."
    unzip -q cmdline-tools.zip
    mv cmdline-tools latest
    rm cmdline-tools.zip
    echo "✓ Android command-line tools installed"
else
    echo "✓ Android command-line tools already installed"
fi
echo ""

# Set Android environment variables
export ANDROID_HOME="$HOME/Android"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"

echo "Android environment variables:"
echo "  ANDROID_HOME=$ANDROID_HOME"
echo "  PATH includes SDK tools"
echo ""

# Accept licenses
echo "Accepting Android SDK licenses..."
yes | sdkmanager --licenses > /dev/null 2>&1 || true
echo "✓ Licenses accepted"
echo ""

# Install required SDK components
echo "Installing Android SDK components..."
echo "  - Platform Tools (adb, fastboot)"
echo "  - Android API 33"
echo "  - Build Tools 33.0.0"
echo ""
echo "This may take several minutes and download ~500MB..."
echo ""

sdkmanager "platform-tools" "platforms;android-33" "build-tools;33.0.0"

echo ""
echo "✓ Android SDK components installed"
echo ""

# Create local.properties for project
echo "Configuring project..."
cd /home/keithaumiller/stlouisintegration.com/amisafe-mobile/android
cat > local.properties << EOF
sdk.dir=$ANDROID_HOME
EOF
echo "✓ Created android/local.properties"
echo ""

# Create React Native gradle files if missing
if [ ! -f "../node_modules/react-native/gradle/libs.versions.toml" ]; then
    echo "Creating missing React Native gradle configuration..."
    mkdir -p ../node_modules/react-native/gradle
    cat > ../node_modules/react-native/gradle/libs.versions.toml << 'EOF'
[versions]
android-gradle-plugin = "7.4.2"
kotlin = "1.8.0"

[libraries]

[plugins]
android-application = { id = "com.android.application", version.ref = "android-gradle-plugin" }
android-library = { id = "com.android.library", version.ref = "android-gradle-plugin" }
kotlin-android = { id = "org.jetbrains.kotlin.android", version.ref = "kotlin" }
EOF
    echo "✓ Created libs.versions.toml"
fi
echo ""

# Create environment setup file for future builds
cat > /home/keithaumiller/stlouisintegration.com/amisafe-mobile/android-env.sh << 'EOF'
#!/bin/bash
# Android build environment variables
# Source this file before building: source android-env.sh

export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export ANDROID_HOME="$HOME/Android"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"

echo "Android build environment configured:"
echo "  JAVA_HOME=$JAVA_HOME"
echo "  ANDROID_HOME=$ANDROID_HOME"
EOF

chmod +x /home/keithaumiller/stlouisintegration.com/amisafe-mobile/android-env.sh
echo "✓ Created android-env.sh for future builds"
echo ""

echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "To build the Android APK:"
echo "  1. cd /home/keithaumiller/stlouisintegration.com/amisafe-mobile"
echo "  2. source android-env.sh"
echo "  3. cd android && ./gradlew assembleRelease"
echo ""
echo "APK will be at:"
echo "  android/app/build/outputs/apk/release/app-release.apk"
echo ""
