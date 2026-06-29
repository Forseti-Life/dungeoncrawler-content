# Version Management System

## Overview

Forseti Mobile uses a centralized version management system to ensure all version references throughout the application stay in sync.

## Single Source of Truth

**File**: `src/config/AppVersion.ts`

This file contains all version information:
- Base version (from package.json)
- Build code (auto-incremented)
- Build date (auto-updated)
- Formatted display versions

## How It Works

### 1. Centralized Configuration
```typescript
export const APP_VERSION = {
  VERSION: '1.0.3',           // Base version
  BUILD_CODE: 24,             // Auto-incremented
  BUILD_DATE: '2026-01-20',   // Auto-updated
  
  // Computed properties
  get DISPLAY_VERSION(): string {
    return `v${this.VERSION}-${this.BUILD_CODE} (${this.BUILD_DATE})`;
  }
};
```

### 2. Usage Throughout App
All files import and use this centralized config:

```typescript
import APP_VERSION from '../config/AppVersion';

// Display version in UI
<Text>{APP_VERSION.DISPLAY_VERSION}</Text>

// Log version info
console.log('Version:', APP_VERSION.DISPLAY_VERSION);

// API version
const apiVersion = APP_VERSION.API_VERSION;
```

### 3. Automated Updates
The build script (`increment-build.sh`) automatically updates:
- `AppVersion.ts` - Updates BUILD_CODE and BUILD_DATE
- `android/app/build.gradle` - Updates versionCode
- `App.tsx` - Updates display text (for backward compatibility)

## Files That Use Version Info

### Core Application Files
- ✅ `App.tsx` - Header version display and startup logs
- ✅ `src/services/location/BackgroundLocationService.ts` - Error logging
- ✅ `src/services/logging/ConsoleLogService.ts` - Log metadata

### Build System
- ✅ `android/app/build.gradle` - Android versionCode and versionName
- ✅ `package.json` - Base version number
- ✅ `increment-build.sh` - Build script that updates all version references

### Documentation
- ✅ `README.md` - Version info in header
- ✅ `TODO.md` - Current version reference
- ✅ `ANDROID_BUILD.md` - Build examples

## Migration Complete ✅

All hardcoded version references have been replaced with imports from the centralized `AppVersion.ts` file. The system now:

1. **Automatically stays in sync** - No more version mismatches
2. **Updates all references** - Build script updates everything at once
3. **Provides consistent formatting** - All versions use same format
4. **Enables easy maintenance** - Change version in one place

## Adding New Version References

When adding version info to new files:

```typescript
// ✅ DO THIS
import APP_VERSION from '../path/to/config/AppVersion';
const version = APP_VERSION.DISPLAY_VERSION;

// ❌ DON'T DO THIS
const version = 'v1.0.3-24 (2026-01-20)'; // Will become outdated
```

## Version Format Standards

- **Display Version**: `v1.0.3-24 (2026-01-20)` - For UI and user-facing displays
- **Short Version**: `v1.0.3-24` - For compact displays
- **API Version**: `1.0.3.24` - For API calls and technical integrations