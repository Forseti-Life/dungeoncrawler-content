#!/bin/bash

# Android SDK setup script for forseti-mobile
# This script installs Android SDK Command Line Tools to the SD card to save disk space

set -e

ANDROID_HOME="/mnt/chromeos/removable/SD Card/Android/sdk"
CMDLINE_TOOLS_VERSION="9477386"
CMDLINE_TOOLS_URL="https://dl.google.com/android/repository/commandlinetools-linux-${CMDLINE_TOOLS_VERSION}_latest.zip"

echo "Setting up Android SDK..."
echo "Android SDK will be installed to: $ANDROID_HOME"

# Create SDK directory structure
mkdir -p "$ANDROID_HOME/cmdline-tools"
cd "$ANDROID_HOME"

# Download command line tools if not already present
if [ ! -f "cmdline-tools.zip" ]; then
    echo "Downloading Android Command Line Tools..."
    wget -q --show-progress "$CMDLINE_TOOLS_URL" -O cmdline-tools.zip
fi

# Extract to temporary location then move to correct structure
if [ ! -d "cmdline-tools/latest" ]; then
    echo "Extracting Command Line Tools..."
    unzip -q cmdline-tools.zip
    mv cmdline-tools latest
    mkdir -p cmdline-tools
    mv latest cmdline-tools/
    rm -f cmdline-tools.zip
fi

# Add to PATH for this session
export ANDROID_HOME
export PATH="$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"

echo "Installing required SDK components..."
yes | sdkmanager --licenses || true
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0" "ndk;25.1.8937393"

# Create local.properties file for Android project
LOCAL_PROPS="/home/keithaumiller/forseti.life/forseti-mobile/android/local.properties"
echo "Creating local.properties..."
cat > "$LOCAL_PROPS" << EOF
sdk.dir=$ANDROID_HOME
ndk.dir=$ANDROID_HOME/ndk/25.1.8937393
EOF

echo ""
echo "✓ Android SDK setup complete!"
echo ""
echo "Add these lines to your ~/.bashrc to make ANDROID_HOME permanent:"
echo "  export ANDROID_HOME=\"$ANDROID_HOME\""
echo "  export PATH=\"\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools:\$PATH\""
echo ""
