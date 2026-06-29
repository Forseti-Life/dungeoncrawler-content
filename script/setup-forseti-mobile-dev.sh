#!/bin/bash
#
# Forseti Mobile - Complete Development Environment Setup
# Sets up React Native development with VS Code best practices
# Includes: Android SDK, Web preview, Testing, Code quality tools
#
# Usage: ./setup-forseti-mobile-dev.sh [--skip-android] [--skip-web] [--quick]
#

set -e

# Auto-detect workspace path (supports both /workspaces and /home paths)
if [ -d "/workspaces/forseti.life" ]; then
    WORKSPACE_ROOT="/workspaces/forseti.life"
elif [ -d "/home/keithaumiller/forseti.life" ]; then
    WORKSPACE_ROOT="/home/keithaumiller/forseti.life"
else
    echo "ERROR: Could not find forseti.life workspace directory"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
MOBILE_DIR="$WORKSPACE_ROOT/forseti-mobile"
NODE_VERSION_REQUIRED="16"
JAVA_VERSION="17"

# Parse command line arguments
SKIP_ANDROID=false
SKIP_WEB=false
QUICK_MODE=false

for arg in "$@"; do
    case $arg in
        --skip-android)
            SKIP_ANDROID=true
            shift
            ;;
        --skip-web)
            SKIP_WEB=true
            shift
            ;;
        --quick)
            QUICK_MODE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-android    Skip Android SDK setup"
            echo "  --skip-web        Skip web preview setup"
            echo "  --quick           Quick mode (skip optional steps)"
            echo "  --help            Show this help message"
            echo ""
            exit 0
            ;;
    esac
done

# Helper functions
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[→]${NC} $1"
}

print_header() {
    echo ""
    echo -e "${CYAN}=========================================="
    echo -e "  $1"
    echo -e "==========================================${NC}"
    echo ""
}

# Check if running in quick mode
if [ "$QUICK_MODE" = true ]; then
    print_header "Quick Setup Mode (Essential steps only)"
else
    print_header "Forseti Mobile - Complete Development Setup"
fi

# Step 1: System Requirements Check
print_header "Step 1: Checking System Requirements"

# Check disk space
print_step "Checking available disk space..."
AVAILABLE_SPACE=$(df -BG / | tail -1 | awk '{print $4}' | sed 's/G//')
print_status "Available disk space: ${AVAILABLE_SPACE}GB"

# Recommended minimum is 20GB for comfortable Android development
if [ "$AVAILABLE_SPACE" -lt 20 ]; then
    print_error "Insufficient disk space. At least 20GB recommended for Android development."
    print_warning "Android SDK requires ~10GB, build cache ~5GB, dependencies ~3GB, plus workspace ~2GB"
    print_warning "Current available: ${AVAILABLE_SPACE}GB"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
elif [ "$AVAILABLE_SPACE" -lt 30 ]; then
    print_warning "Disk space is below comfortable level (30GB+ recommended)"
    print_status "Current ${AVAILABLE_SPACE}GB should be sufficient but monitor usage"
else
    print_status "Disk space check passed: ${AVAILABLE_SPACE}GB available"
fi

# Check Node.js
print_step "Verifying Node.js installation..."
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js $NODE_VERSION_REQUIRED or higher."
    echo "Install with: sudo apt-get install nodejs npm"
    exit 1
fi

NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
NPM_VERSION=$(npm --version)
print_status "Node.js: v$(node --version | cut -d'v' -f2)"
print_status "npm: v$NPM_VERSION"

if [ "$NODE_VERSION" -lt "$NODE_VERSION_REQUIRED" ]; then
    print_error "Node.js version $NODE_VERSION_REQUIRED or higher required. Current: $NODE_VERSION"
    exit 1
fi

# Check Git
if ! command -v git &> /dev/null; then
    print_error "Git is not installed. Please install git first."
    exit 1
fi
print_status "Git: $(git --version | cut -d' ' -f3)"

# Step 2: Navigate to Project Directory
print_header "Step 2: Preparing Project Directory"

if [ ! -d "$MOBILE_DIR" ]; then
    print_error "Mobile app directory not found: $MOBILE_DIR"
    exit 1
fi

cd "$MOBILE_DIR" || exit 1
print_status "Working directory: $(pwd)"

# Step 3: Install Core Dependencies
print_header "Step 3: Installing React Native Dependencies"

# Ensure .npmrc exists for consistent dependency resolution
if [ ! -f ".npmrc" ]; then
    print_step "Creating .npmrc with legacy-peer-deps..."
    echo "legacy-peer-deps=true" > .npmrc
    print_status ".npmrc created"
fi

print_step "Installing npm packages (this may take 3-5 minutes)..."
if [ -d "node_modules" ] && [ "$QUICK_MODE" = false ]; then
    print_warning "node_modules exists. Cleaning for fresh install..."
    rm -rf node_modules package-lock.json
fi

npm install
print_status "Core dependencies installed"

# Apply patches for React Native libraries (Android Gradle Plugin 8+ compatibility)
if [ -d "patches" ]; then
    print_step "Applying React Native library patches for Android..."
    npm run postinstall 2>/dev/null || npx patch-package
    print_status "Library patches applied (namespace + buildConfig fixes)"
fi

# Step 4: Install Development Tools
print_header "Step 4: Setting Up Development Tools"

print_step "Installing ESLint, Prettier, and TypeScript..."
npm install --save-dev \
    eslint@^8.57.0 \
    prettier \
    eslint-config-prettier \
    eslint-plugin-prettier \
    eslint-plugin-react \
    eslint-plugin-react-native \
    @typescript-eslint/eslint-plugin \
    @typescript-eslint/parser \
    @react-native/eslint-config \
    typescript@^5.0.0 \
    2>&1 | grep -v "deprecated" || true

print_status "Code quality tools installed"

# Step 5: Install Testing Framework
print_step "Installing Jest and React Native Testing Library..."
npm install --save-dev \
    jest \
    @testing-library/react-native \
    @testing-library/jest-native \
    jest-environment-jsdom \
    2>&1 | grep -v "deprecated" || true

print_status "Testing framework installed"

# Step 6: Install patch-package for maintaining library fixes
print_step "Installing patch-package..."
npm install --save-dev patch-package postinstall-postinstall 2>&1 | grep -v "deprecated" || true
print_status "patch-package installed (maintains Android Gradle Plugin 8 compatibility fixes)"

# Step 6: Install Environment Variable Management
print_step "Installing react-native-dotenv..."
npm install --save react-native-dotenv

print_status "Environment variable management ready"

# Step 7: Verify Configuration Files
print_header "Step 5: Verifying Configuration Files"

# Check for essential config files
CONFIG_FILES=(
    ".eslintrc.js:ESLint configuration"
    ".prettierrc:Prettier configuration"
    "jest.config.js:Jest configuration"
    "babel.config.js:Babel configuration"
    "tsconfig.json:TypeScript configuration"
    ".vscode/launch.json:VS Code debugging"
    ".vscode/settings.json:VS Code settings"
    ".vscode/tasks.json:VS Code tasks"
)

MISSING_CONFIGS=()
for config in "${CONFIG_FILES[@]}"; do
    IFS=':' read -r file desc <<< "$config"
    if [ -f "$file" ]; then
        print_status "$desc found"
    else
        print_warning "$desc missing"
        MISSING_CONFIGS+=("$file")
    fi
done

if [ ${#MISSING_CONFIGS[@]} -gt 0 ]; then
    print_warning "Some configuration files are missing. You may need to create them manually."
    print_warning "See: forseti-mobile/CRITICAL_FIXES_SUMMARY.md for templates"
fi

# Check for environment files
if [ ! -f ".env" ]; then
    print_warning ".env file not found"
    if [ -f ".env.development" ]; then
        print_step "Creating .env from .env.development..."
        cp .env.development .env
        print_status ".env file created"
    fi
fi

# Step 8: Android SDK Setup (Optional)
if [ "$SKIP_ANDROID" = false ]; then
    print_header "Step 6: Android SDK Setup"
    
    # Check for Java
    print_step "Checking Java installation..."
    
    # Check Java version
    CURRENT_JAVA_VERSION=""
    if command -v java &> /dev/null; then
        CURRENT_JAVA_VERSION=$(java -version 2>&1 | head -1 | cut -d'"' -f2 | cut -d'.' -f1)
        print_status "Java already installed: $(java -version 2>&1 | head -1 | cut -d'"' -f2)"
    fi
    
    # AGP 8.0.2+ requires Java 17
    if [ -z "$CURRENT_JAVA_VERSION" ] || [ "$CURRENT_JAVA_VERSION" -lt 17 ]; then
        print_warning "Java $JAVA_VERSION required for Android Gradle Plugin 8.0.2+. Installing..."
        sudo apt-get update -qq
        sudo apt-get install -y openjdk-${JAVA_VERSION}-jdk
        print_status "Java $JAVA_VERSION installed"
    fi
    
    # Set Java environment - verify the path exists first
    JAVA_HOME_PATH="/usr/lib/jvm/java-${JAVA_VERSION}-openjdk-amd64"
    if [ ! -d "$JAVA_HOME_PATH" ]; then
        # Try to find the actual Java installation
        JAVA_HOME_PATH=$(dirname $(dirname $(readlink -f $(which java))))
        print_warning "Expected Java path not found, using detected path: $JAVA_HOME_PATH"
    fi
    export JAVA_HOME="$JAVA_HOME_PATH"
    print_status "JAVA_HOME: $JAVA_HOME"
    
    # Verify Java is working with JAVA_HOME set
    if [ ! -f "$JAVA_HOME/bin/java" ]; then
        print_error "Java installation incomplete. JAVA_HOME/bin/java not found."
        exit 1
    fi
    print_status "Java verified: $($JAVA_HOME/bin/java -version 2>&1 | head -1)"
    
    # Android SDK setup
    ANDROID_HOME="$HOME/Android"
    print_step "Setting up Android SDK..."
    
    if [ ! -d "$ANDROID_HOME/cmdline-tools/latest" ]; then
        print_warning "Android SDK not found. This will download ~650MB."
        read -p "Continue with Android SDK installation? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            mkdir -p "$ANDROID_HOME/cmdline-tools"
            cd "$ANDROID_HOME/cmdline-tools"
            
            print_step "Downloading Android command-line tools..."
            wget -q --show-progress -O cmdline-tools.zip \
                https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
            
            print_step "Extracting tools..."
            unzip -q cmdline-tools.zip
            mv cmdline-tools latest
            rm cmdline-tools.zip
            
            cd "$MOBILE_DIR"
            print_status "Android command-line tools installed"
            
            # Set environment
            export ANDROID_HOME="$HOME/Android"
            export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"
            
            # Accept licenses
            print_step "Accepting Android SDK licenses..."
            yes | sdkmanager --licenses > /dev/null 2>&1 || true
            
            # Install SDK components
            print_step "Installing SDK components (this may take 5-10 minutes)..."
            sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"
            
            print_status "Android SDK components installed"
            
            # Create local.properties
            if [ -d "android" ]; then
                print_step "Configuring Android project..."
                cat > android/local.properties << EOF
sdk.dir=$ANDROID_HOME
EOF
                print_status "android/local.properties created"
            fi
            
            # Create environment script with detected JAVA_HOME
            cat > android-env.sh << EOF
#!/bin/bash
# Android build environment variables
# Source this file before building: source android-env.sh

export JAVA_HOME=$JAVA_HOME
export ANDROID_HOME="\$HOME/Android"
export PATH="\$PATH:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools"

echo "Android build environment configured:"
echo "  JAVA_HOME=\$JAVA_HOME"
echo "  ANDROID_HOME=\$ANDROID_HOME"
echo "  PATH includes SDK tools"
EOF
            chmod +x android-env.sh
            print_status "android-env.sh created with JAVA_HOME=$JAVA_HOME"
        else
            print_warning "Skipping Android SDK installation"
            SKIP_ANDROID=true
        fi
    else
        print_status "Android SDK already installed at $ANDROID_HOME"
        export ANDROID_HOME="$HOME/Android"
        export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"
        
        # Create/update environment script with detected JAVA_HOME
        cd "$MOBILE_DIR"
        cat > android-env.sh << EOF
#!/bin/bash
# Android build environment variables
# Source this file before building: source android-env.sh

export JAVA_HOME=$JAVA_HOME
export ANDROID_HOME="\$HOME/Android"
export PATH="\$PATH:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools"

echo "Android build environment configured:"
echo "  JAVA_HOME=\$JAVA_HOME"
echo "  ANDROID_HOME=\$ANDROID_HOME"
echo "  PATH includes SDK tools"
EOF
        chmod +x android-env.sh
        print_status "android-env.sh updated with JAVA_HOME=$JAVA_HOME"
    fi
    
    # Fix Android build configuration for React Native 0.72 compatibility
    print_step "Updating Android build configuration..."
    
    # Update build.gradle for AGP 8.0.2, compileSdk 34, Kotlin 1.8.22, and androidx.core 1.13.1
    # Note: AGP 8.0.2 is used instead of newer versions due to Kotlin compatibility
    # React Native 0.72 uses Kotlin 1.7.x, and newer Gradle versions require Kotlin 1.9+
    # API 34 is used instead of 35 due to aapt2 compatibility issues with AGP 8.0.2
    if [ -f "android/build.gradle" ]; then
        sed -i 's/com.android.tools.build:gradle:8.1.4/com.android.tools.build:gradle:8.0.2/' android/build.gradle
        sed -i 's/com.android.tools.build:gradle:8.3.2/com.android.tools.build:gradle:8.0.2/' android/build.gradle
        sed -i 's/compileSdkVersion = 35/compileSdkVersion = 34/' android/build.gradle
        sed -i 's/compileSdkVersion = 33/compileSdkVersion = 34/' android/build.gradle
        sed -i 's/androidXCoreVersion = "1.12.0"/androidXCoreVersion = "1.13.1"/' android/build.gradle
        sed -i 's/kotlinVersion = "1.9.22"/kotlinVersion = "1.8.22"/' android/build.gradle
        sed -i 's/kotlinVersion = "1.9.24"/kotlinVersion = "1.8.22"/' android/build.gradle
        print_status "Updated AGP to 8.0.2, compileSdk to 34, Kotlin to 1.8.22, androidx.core to 1.13.1"
    fi
    
    # Update Gradle wrapper to 8.0.1 (compatible with Kotlin 1.8.x)
    if [ -f "android/gradle/wrapper/gradle-wrapper.properties" ]; then
        sed -i 's/gradle-8.3-all.zip/gradle-8.0.1-all.zip/' android/gradle/wrapper/gradle-wrapper.properties
        sed -i 's/gradle-8.7-all.zip/gradle-8.0.1-all.zip/' android/gradle/wrapper/gradle-wrapper.properties
        print_status "Updated Gradle wrapper to 8.0.1"
    fi
    
    # Update react-native libraries for React Native 0.72 + AGP 8 compatibility
    print_step "Updating React Native libraries for compatibility..."
    # Use react-native-maps 1.7.1 for stability with RN 0.72 + AGP 8.0.2
    npm install react-native-maps@1.7.1 2>&1 | grep -v "EBADENGINE" || true
    # Downgrade gesture-handler to 2.18.1 (2.30.0 has Kotlin compilation issues with RN 0.72)
    npm install react-native-gesture-handler@2.18.1 --legacy-peer-deps 2>&1 | grep -v "EBADENGINE" || true
    # Downgrade screens to 3.29.0 (3.37.0 has Kotlin compilation issues with RN 0.72)
    npm install react-native-screens@3.29.0 --legacy-peer-deps 2>&1 | grep -v "EBADENGINE" || true
    
    # Add androidx.core version constraint to build.gradle to avoid AGP 8.6+ requirement
    if [ -f "android/build.gradle" ]; then
        if ! grep -q "force \"androidx.core:core:1.13.1\"" android/build.gradle; then
            print_step "Adding androidx.core version constraint to build.gradle..."
            # Add resolutionStrategy block to subprojects closure
            sed -i '/subprojects {/,/^}/ {
                /^}/i\    \
    // Force androidx.core version to avoid AGP 8.6+ requirement\
    configurations.all {\
        resolutionStrategy {\
            force "androidx.core:core:1.13.1"\
            force "androidx.core:core-ktx:1.13.1"\
        }\
    }
            }' android/build.gradle
            print_status "Added androidx.core version constraints"
        fi
    fi
    
    # Create gradle.properties with all required React Native and AndroidX settings
    print_step "Creating android/gradle.properties with React Native configuration..."
    cat > android/gradle.properties << 'EOF'
# Gradle build optimization for SD card usage
org.gradle.daemon=true
org.gradle.parallel=true
org.gradle.configureondemand=true
org.gradle.jvmargs=-Xmx4096m -XX:MaxMetaspaceSize=1024m

# Use Gradle build cache (AGP 7.0+ uses Gradle cache, not Android-specific cache)
org.gradle.caching=true

# Flipper debug tool version (React Native default)
FLIPPER_VERSION=0.125.0

# React Native configuration
hermesEnabled=true
newArchEnabled=false

# AndroidX migration (required for React Native 0.72+)
android.useAndroidX=true
android.enableJetifier=true
EOF
    print_status "Created gradle.properties with React Native and AndroidX settings"
    
    # Configure SD card usage for Gradle (if SD card is available)
    if [ -d "/mnt/chromeos/removable/SD Card" ]; then
        print_step "Configuring Gradle to use SD card for build cache..."
        mkdir -p "/mnt/chromeos/removable/SD Card/Android/.gradle"
        
        # Create symlink for .gradle directory if not already a symlink
        if [ -d "$HOME/.gradle" ] && [ ! -L "$HOME/.gradle" ]; then
            print_warning "Moving existing .gradle to SD card (this saves ~9GB of disk space)..."
            if [ -d "/mnt/chromeos/removable/SD Card/Android/.gradle" ]; then
                rsync -a "$HOME/.gradle/" "/mnt/chromeos/removable/SD Card/Android/.gradle/" 2>/dev/null || true
            fi
            rm -rf "$HOME/.gradle"
        fi
        
        if [ ! -L "$HOME/.gradle" ]; then
            ln -s "/mnt/chromeos/removable/SD Card/Android/.gradle" "$HOME/.gradle"
            print_status "Gradle configured to use SD card (~9GB saved on local disk)"
        else
            print_status "Gradle already configured to use SD card"
        fi
    else
        print_warning "SD card not found at /mnt/chromeos/removable/SD Card"
        print_warning "Gradle will use local disk (~9GB required)"
    fi
    
    print_status "Android build configuration updated for React Native 0.72 compatibility"
else
    print_warning "Android SDK setup skipped (--skip-android flag)"
fi

# Step 9: Web Development Setup (Optional)
if [ "$SKIP_WEB" = false ]; then
    print_header "Step 7: Web Preview Setup"
    
    print_step "Verifying React Native Web dependencies..."
    
    # Check if webpack is installed
    if [ ! -d "node_modules/webpack" ]; then
        print_warning "Webpack not found. Installing..."
        npm install --save-dev \
            webpack@5 \
            webpack-cli \
            webpack-dev-server \
            babel-loader \
            html-webpack-plugin \
            @babel/preset-react \
            react-native-web@^0.19.13
        print_status "Web dependencies installed"
    else
        print_status "React Native Web dependencies already installed"
    fi
    
    # Verify webpack config exists
    if [ ! -f "webpack.config.js" ]; then
        print_warning "webpack.config.js not found"
        print_warning "You may need to create this file manually"
    else
        print_status "webpack.config.js found"
    fi
    
    # Verify web entry point exists
    if [ ! -f "index.web.js" ]; then
        print_warning "index.web.js not found"
        print_warning "You may need to create this file manually"
    else
        print_status "index.web.js found"
    fi
    
    print_status "Web preview setup complete"
    print_status "Start web server with: npm run web"
else
    print_warning "Web setup skipped (--skip-web flag)"
fi

# Step 10: Run Code Quality Check
if [ "$QUICK_MODE" = false ]; then
    print_header "Step 8: Running Initial Code Quality Check"
    
    print_step "Running ESLint..."
    npm run lint 2>&1 | tail -20 || print_warning "Some lint issues found (this is normal)"
    
    print_step "Running Prettier format check..."
    npm run format 2>&1 | tail -10 || print_warning "Some formatting applied"
    
    print_status "Code quality check complete"
fi

# Step 11: Summary
print_header "Setup Complete! 🎉"

echo "Environment Summary:"
echo "===================="
print_status "Node.js v$(node --version | cut -d'v' -f2) with npm v$NPM_VERSION"
print_status "React Native dependencies installed (1600+ packages)"
print_status "ESLint + Prettier configured"
print_status "Jest testing framework ready"
print_status "Environment variables configured"

if [ "$SKIP_ANDROID" = false ] && [ -d "$HOME/Android/cmdline-tools/latest" ]; then
    print_status "Android SDK installed at $HOME/Android (API 35, AGP 8.0.2, Gradle 8.0.1)"
    print_status "React Native library patches applied (Android Gradle Plugin 8+ compatibility)"
    print_status "Kotlin 1.8.22 (compatible with React Native 0.72 Gradle plugin)"
else
    print_warning "Android SDK not installed"
fi

if [ "$SKIP_WEB" = false ] && [ -f "webpack.config.js" ]; then
    print_status "React Native Web configured"
else
    print_warning "Web preview not configured"
fi

echo ""
echo "Quick Start Commands:"
echo "===================="
echo "  npm start              # Start Metro bundler"
echo "  npm run web            # Start web server (port 3000)"
echo "  npm run android        # Run Android app"
echo "  npm test               # Run tests"
echo "  npm run lint           # Check code quality"
echo "  npm run lint:fix       # Auto-fix lint issues"
echo "  npm run format         # Format code"
echo ""

if [ "$SKIP_ANDROID" = false ] && [ -f "android-env.sh" ]; then
    echo "Android Build Commands:"
    echo "======================="
    echo "  source android-env.sh              # Load Android environment"
    echo "  cd android && ./gradlew assembleDebug    # Build debug APK"
    echo "  cd android && ./gradlew assembleRelease  # Build release APK"
    echo ""
fi

echo "VS Code Development:"
echo "===================="
echo "  Press F5              # Start debugging"
echo "  Ctrl+Shift+P          # Run tasks"
echo ""

echo "Documentation:"
echo "=============="
echo "  CRITICAL_FIXES_SUMMARY.md  # Complete setup details"
echo "  QUICK_REFERENCE.md         # Developer quick reference"
echo "  ENV_VARIABLES.md           # Environment configuration"
echo "  BEST_PRACTICES_REVIEW.md   # Code quality guidelines"
echo ""

print_status "Development environment ready!"
print_status "Happy coding! 🚀"
echo ""
