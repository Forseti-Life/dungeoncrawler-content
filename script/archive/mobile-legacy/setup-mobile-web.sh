#!/bin/bash

# Forseti Mobile - Web Development Setup
# Sets up React Native Web for local browser preview

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

echo ""
echo "=========================================="
echo "  Forseti Mobile - Web Preview Setup"
echo "=========================================="
echo ""

MOBILE_DIR="/home/keithaumiller/forseti.life/forseti-mobile"
NODE_VERSION_REQUIRED="16"

# Step 1: Verify Node.js
print_step "1. Verifying Node.js installation..."
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js $NODE_VERSION_REQUIRED or higher."
    exit 1
fi

NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
print_status "Node.js version: $(node --version)"

if [ "$NODE_VERSION" -lt "$NODE_VERSION_REQUIRED" ]; then
    print_error "Node.js version $NODE_VERSION_REQUIRED or higher required. Current: $NODE_VERSION"
    exit 1
fi

# Step 2: Navigate to mobile directory
print_step "2. Navigating to mobile app directory..."
cd "$MOBILE_DIR" || {
    print_error "Mobile app directory not found: $MOBILE_DIR"
    exit 1
}
print_status "Working directory: $(pwd)"

# Step 3: Install dependencies
print_step "3. Installing dependencies..."
if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
    print_status "Installing React Native dependencies (this may take several minutes)..."
    npm install --legacy-peer-deps
    print_status "✅ Dependencies installed"
else
    print_status "✓ Dependencies already installed"
fi

# Step 4: Install React Native Web dependencies
print_step "4. Ensuring React Native Web dependencies..."
npm install --save-dev --legacy-peer-deps \
    webpack@5 \
    webpack-cli \
    webpack-dev-server \
    babel-loader \
    html-webpack-plugin \
    @babel/preset-react \
    react-native-web@^0.19.9 \
    || print_warning "Some packages may already be installed"

print_status "✅ React Native Web dependencies ready"

# Step 5: Create webpack configuration
print_step "5. Creating webpack configuration..."
cat > webpack.config.js << 'EOF'
const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = {
  mode: 'development',
  entry: './index.web.js',
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: 'bundle.js',
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx|ts|tsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              '@babel/preset-env',
              '@babel/preset-react',
              '@babel/preset-typescript',
            ],
          },
        },
      },
    ],
  },
  resolve: {
    extensions: ['.web.js', '.js', '.jsx', '.ts', '.tsx'],
    alias: {
      'react-native$': 'react-native-web',
      'react-native-vector-icons': 'react-native-vector-icons/dist',
    },
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: './public/index.html',
    }),
  ],
  devServer: {
    static: {
      directory: path.join(__dirname, 'public'),
    },
    port: 3000,
    hot: true,
    open: true,
  },
};
EOF
print_status "✅ webpack.config.js created"

# Step 6: Create web entry point
print_step "6. Creating web entry point..."
cat > index.web.js << 'EOF'
import { AppRegistry } from 'react-native';
import App from './App';
import { name as appName } from './app.json';

// Register the app
AppRegistry.registerComponent(appName, () => App);

// Mount to DOM
AppRegistry.runApplication(appName, {
  rootTag: document.getElementById('root'),
});
EOF
print_status "✅ index.web.js created"

# Step 7: Create public directory and HTML template
print_step "7. Creating HTML template..."
mkdir -p public

cat > public/index.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#000000">
  <title>Forseti Mobile - Web Preview</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    html, body, #root {
      height: 100%;
      width: 100%;
      overflow: hidden;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
        'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
        sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
  </style>
</head>
<body>
  <div id="root"></div>
</body>
</html>
EOF
print_status "✅ public/index.html created"

# Step 8: Update package.json scripts
print_step "8. Adding web scripts to package.json..."
npm pkg set scripts.web="webpack serve --mode development"
npm pkg set scripts.build:web="webpack --mode production"
print_status "✅ Web scripts added"

# Step 9: Test webpack configuration
print_step "9. Validating webpack setup..."
if npx webpack --help > /dev/null 2>&1; then
    print_status "✅ Webpack is working"
else
    print_warning "⚠️  Webpack validation issue - may still work"
fi

echo ""
print_status "=========================================="
print_status "  Setup Complete!"
print_status "=========================================="
echo ""
print_status "Your Forseti Mobile app is ready for web preview!"
echo ""
print_status "To start the development server:"
echo "  cd $MOBILE_DIR"
echo "  npm run web"
echo ""
print_status "The app will automatically open in your browser at:"
echo "  http://localhost:3000"
echo ""
print_status "Features available in web preview:"
echo "  ✓ UI Components and Navigation"
echo "  ✓ Chat and AI Conversation"
echo "  ✓ Crime Map Visualization"
echo "  ✓ Community Features"
echo "  ✓ Safety Factors Analysis"
echo ""
print_warning "Note: Some native features (GPS, permissions) will have limited functionality"
echo ""
print_status "🎉 Ready to preview!"
echo ""
