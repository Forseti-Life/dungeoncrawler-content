# Google Analytics 4 (GA4) Setup Guide for Forseti

## Overview
This guide walks you through setting up Google Analytics 4 for both:
- **Drupal Website** (sites/forseti) using the `google_tag` module
- **React Native Mobile App** (forseti-mobile)

---

## Part 1: Google Analytics Account Setup

### Step 1: Create GA4 Property

1. **Go to Google Analytics**: https://analytics.google.com
2. **Sign in** with your Google account
3. **Create Account** (if you don't have one):
   - Click "Admin" (gear icon in bottom left)
   - Click "Create Account"
   - Enter account name: `Forseti`
   - Configure data-sharing settings as desired
   - Click "Next"

4. **Create Property**:
   - Property name: `Forseti Platform`
   - Time zone: `United States - Eastern Time`
   - Currency: `US Dollar ($)`
   - Click "Next"

5. **Business Information**:
   - Industry: `Public Safety / Government`
   - Business size: Select appropriate size
   - Click "Create"
   - Accept Terms of Service

### Step 2: Get Your Measurement IDs

You'll get **TWO measurement IDs**:

1. **Web Stream** (for Drupal site):
   - Click "Web" under "Choose a platform"
   - Website URL: `https://forseti.life` (or your domain)
   - Stream name: `Forseti Website`
   - Click "Create stream"
   - **Copy the Measurement ID**: Format is `G-XXXXXXXXXX`
   - Save this as: **WEB_MEASUREMENT_ID**

2. **Mobile App Streams**:
   - Go back to Admin → Data Streams
   - Click "Add stream" → "iOS app" OR "Android app"
   
   **For Android**:
   - App name: `Forseti Mobile`
   - Package name: `com.forsetimobile` (check your android/app/build.gradle)
   - Click "Register app"
   - **Download google-services.json**
   - **Copy the Measurement ID** (starts with G-)
   - Save this as: **MOBILE_MEASUREMENT_ID**
   
   **For iOS** (optional if supporting iOS):
   - App name: `Forseti Mobile`
   - Bundle ID: Check your ios project
   - Click "Register app"
   - **Download GoogleService-Info.plist**

---

## Part 2: Drupal Website Setup

### Step 1: Enable the Google Tag Module

The module is already installed via Composer. Now enable it:

```bash
cd /home/keithaumiller/forseti.life/sites/forseti
drush en google_tag -y
drush cr
```

### Step 2: Configure Google Tag

1. **Access the configuration page**:
   - Log into your Drupal site as admin
   - Navigate to: **Configuration → Services → Google Tag**
   - URL: `https://your-domain.com/admin/config/services/google-tag`

2. **Add Your Measurement ID**:
   - In the "Measurement IDs" field, enter: `G-XXXXXXXXXX` (your WEB_MEASUREMENT_ID)
   - If you have multiple IDs, add them one per line

3. **Configure Events** (recommended):
   - Click "Configure events"
   - Enable events like:
     - ✅ Page view (usually enabled by default)
     - ✅ User login
     - ✅ Form submission
     - ✅ File download
   - Customize event parameters as needed

4. **Set Conditions** (optional):
   - Configure when tags should load
   - By default, loads on all pages for all users
   - You might want to exclude admin pages:
     - Add condition "Request Path"
     - Negate: Yes
     - Pages: `/admin*`

5. **Click "Save configuration"**

### Step 3: Verify Drupal Installation

1. **Clear cache**:
```bash
drush cr
```

2. **Check your website**:
   - Open your website in a browser
   - Open Developer Tools (F12)
   - Go to Console tab
   - Look for `dataLayer` object
   - Type: `dataLayer` and press Enter
   - You should see an array with gtag data

3. **Use Google Tag Assistant**:
   - Install: [Tag Assistant Chrome Extension](https://chrome.google.com/webstore/detail/tag-assistant-legacy-by-g/kejbdjndbnbjgmefkgdddjlbokphdefk)
   - Visit your website
   - Click the extension icon
   - You should see your GA4 tag firing

4. **Check in GA4 Real-Time**:
   - Go to GA4 → Reports → Realtime
   - Visit your website
   - Within 30 seconds, you should see yourself in real-time reports

---

## Part 3: React Native Mobile App Setup

### Step 1: Install Required Packages

```bash
cd /home/keithaumiller/forseti.life/forseti-mobile
npm install @react-native-firebase/app @react-native-firebase/analytics
```

### Step 2: Configure Android

1. **Add google-services.json**:
   - Copy the `google-services.json` file you downloaded earlier
   - Place it in: `forseti-mobile/android/app/google-services.json`

2. **Update android/build.gradle**:
   - Open `forseti-mobile/android/build.gradle`
   - Add Google services classpath:

```gradle
buildscript {
    dependencies {
        // Add this line:
        classpath 'com.google.gms:google-services:4.4.0'
    }
}
```

3. **Update android/app/build.gradle**:
   - Open `forseti-mobile/android/app/build.gradle`
   - Add at the bottom:

```gradle
apply plugin: 'com.google.gms.google-services'
```

### Step 3: Configure iOS (if supporting iOS)

1. **Add GoogleService-Info.plist**:
   - Copy the file you downloaded
   - Add to: `forseti-mobile/ios/AmISafeTempInit/GoogleService-Info.plist`
   - Make sure it's added to your Xcode project

2. **Update Podfile**:
```ruby
# Add Firebase Analytics
pod 'Firebase/Analytics', '~> 10.0'
```

3. **Install pods**:
```bash
cd forseti-mobile/ios
pod install
```

### Step 4: Initialize Analytics in Your App

Create a new analytics utility file:

**File**: `forseti-mobile/src/utils/analytics.ts`

```typescript
import analytics from '@react-native-firebase/analytics';

class AnalyticsService {
  /**
   * Log an event
   */
  async logEvent(eventName: string, params?: { [key: string]: any }) {
    try {
      await analytics().logEvent(eventName, params);
      console.log(`Analytics event logged: ${eventName}`, params);
    } catch (error) {
      console.error('Analytics error:', error);
    }
  }

  /**
   * Log screen view
   */
  async logScreenView(screenName: string, screenClass?: string) {
    try {
      await analytics().logScreenView({
        screen_name: screenName,
        screen_class: screenClass || screenName,
      });
    } catch (error) {
      console.error('Analytics screen view error:', error);
    }
  }

  /**
   * Set user properties
   */
  async setUserProperty(name: string, value: string) {
    try {
      await analytics().setUserProperty(name, value);
    } catch (error) {
      console.error('Analytics user property error:', error);
    }
  }

  /**
   * Set user ID
   */
  async setUserId(userId: string) {
    try {
      await analytics().setUserId(userId);
    } catch (error) {
      console.error('Analytics set user ID error:', error);
    }
  }

  /**
   * Log safety check event
   */
  async logSafetyCheck(hexagon: string, safetyLevel: string) {
    return this.logEvent('safety_check', {
      hexagon_id: hexagon,
      safety_level: safetyLevel,
    });
  }

  /**
   * Log location permission
   */
  async logLocationPermission(granted: boolean) {
    return this.logEvent('location_permission', {
      permission_granted: granted,
    });
  }

  /**
   * Log notification interaction
   */
  async logNotificationInteraction(action: string) {
    return this.logEvent('notification_interaction', {
      action,
    });
  }
}

export default new AnalyticsService();
```

### Step 5: Update App.tsx

Add analytics initialization in your main App file:

```typescript
import analytics from '@react-native-firebase/analytics';
import Analytics from './src/utils/analytics';

function App(): React.JSX.Element {
  useEffect(() => {
    // Initialize analytics
    analytics().setAnalyticsCollectionEnabled(true);
    Analytics.logEvent('app_opened');
  }, []);

  // Rest of your app code...
}
```

### Step 6: Add Screen Tracking

For React Navigation, add automatic screen tracking:

```typescript
import { useNavigationContainerRef } from '@react-navigation/native';
import Analytics from './src/utils/analytics';

function App(): React.JSX.Element {
  const navigationRef = useNavigationContainerRef();
  const routeNameRef = useRef<string>();

  return (
    <NavigationContainer
      ref={navigationRef}
      onReady={() => {
        routeNameRef.current = navigationRef.getCurrentRoute()?.name;
      }}
      onStateChange={async () => {
        const previousRouteName = routeNameRef.current;
        const currentRouteName = navigationRef.getCurrentRoute()?.name;

        if (previousRouteName !== currentRouteName) {
          // Log screen view
          await Analytics.logScreenView(currentRouteName || 'Unknown');
        }

        routeNameRef.current = currentRouteName;
      }}
    >
      {/* Your navigation structure */}
    </NavigationContainer>
  );
}
```

### Step 7: Build and Test Mobile App

1. **Rebuild the app**:
```bash
cd /home/keithaumiller/forseti.life/forseti-mobile

# For Android
npm run android:clean
npm run android
```

2. **Check logs**:
```bash
# Android
adb logcat | grep -E "Analytics|Firebase"
```

3. **Verify in GA4**:
   - Open GA4 dashboard
   - Go to Realtime reports
   - Open the app on your device
   - You should see events appearing in real-time

---

## Part 4: Custom Event Tracking

### Drupal Custom Events

You can track custom events in Drupal using JavaScript:

```javascript
// In a custom theme or module
if (typeof gtag === 'function') {
  // Track button click
  document.querySelector('#my-button').addEventListener('click', function() {
    gtag('event', 'button_click', {
      'button_name': 'safety_report',
      'page_location': window.location.href
    });
  });

  // Track form submission
  gtag('event', 'form_submission', {
    'form_name': 'safety_report_form'
  });
}
```

### React Native Custom Events

```typescript
import Analytics from './utils/analytics';

// Track specific user actions
const handleSafetyCheck = async (location) => {
  // Your safety check logic...
  
  // Log to analytics
  await Analytics.logEvent('safety_check_completed', {
    hexagon_resolution: 13,
    location_accuracy: 'high',
    result: 'safe',
  });
};

// Track errors
const handleError = async (error) => {
  await Analytics.logEvent('app_error', {
    error_message: error.message,
    error_code: error.code,
  });
};
```

---

## Part 5: Privacy & Compliance

### Important Considerations

1. **User Consent**:
   - Add a cookie/privacy consent banner to your Drupal site
   - Consider using the [EU Cookie Compliance](https://www.drupal.org/project/eu_cookie_compliance) module
   - Only enable tracking after user consent

2. **Privacy Policy**:
   - Update your privacy policy to mention Google Analytics
   - Explain what data is collected and why
   - Provide opt-out instructions

3. **Data Retention**:
   - Go to GA4 Admin → Data Settings → Data Retention
   - Set appropriate retention period (default is 2 months)
   - Consider your regulatory requirements

4. **IP Anonymization**:
   - GA4 automatically anonymizes IP addresses
   - No additional configuration needed

5. **Mobile Privacy**:
   - Add analytics opt-out in your app settings
   - Implement:

```typescript
// In app settings
const handleAnalyticsToggle = async (enabled: boolean) => {
  await analytics().setAnalyticsCollectionEnabled(enabled);
  // Save preference to AsyncStorage
};
```

---

## Part 6: Testing Checklist

### Drupal Website Testing

- [ ] GA4 tag loads on all pages
- [ ] Events appear in GA4 Real-time reports
- [ ] Page views are tracked
- [ ] User interactions are logged
- [ ] No console errors related to gtag
- [ ] Tag doesn't load on admin pages (if configured)

### Mobile App Testing

- [ ] App builds successfully with Firebase
- [ ] Analytics initialized on app start
- [ ] Screen views tracked automatically
- [ ] Custom events logged correctly
- [ ] Events appear in GA4 Real-time reports
- [ ] No crashes related to analytics
- [ ] Works on both debug and release builds

### Analytics Dashboard

- [ ] Real-time reports show data
- [ ] Events are appearing with correct parameters
- [ ] User counts are accurate
- [ ] Geographic data is showing
- [ ] Device/platform breakdown is correct

---

## Part 7: Troubleshooting

### Drupal Issues

**Tag not loading**:
- Clear cache: `drush cr`
- Check browser console for errors
- Verify measurement ID is correct
- Check condition settings aren't blocking

**Events not tracking**:
- Check Network tab in DevTools
- Look for requests to google-analytics.com
- Verify events are configured in module settings

### Mobile App Issues

**Build failures**:
```bash
# Clean and rebuild
cd android
./gradlew clean
cd ..
npm run android
```

**google-services.json not found**:
- Verify file is in `android/app/` directory
- Check the package name matches your app

**Analytics not initializing**:
- Check Firebase configuration
- Verify google-services.json is valid
- Look for initialization errors in logs

**Events not appearing in GA4**:
- Wait 24-48 hours for initial data processing
- Check DebugView in GA4 (enable debug mode)
- Verify internet connection on device

### Debug Mode for Mobile

Enable debug mode to see events immediately:

```bash
# Android
adb shell setprop debug.firebase.analytics.app com.forsetimobile

# To disable
adb shell setprop debug.firebase.analytics.app .none.
```

Then check GA4 → Configure → DebugView

---

## Part 8: Next Steps

1. **Set up Goals/Conversions**:
   - Define key actions (sign-ups, safety reports, etc.)
   - Mark important events as conversions in GA4

2. **Create Custom Reports**:
   - Build reports specific to your needs
   - Track safety metrics, user engagement, etc.

3. **Set up Alerts**:
   - Get notified of traffic spikes
   - Monitor critical events

4. **Link to Google Search Console** (for website):
   - Connect GA4 to Search Console
   - Track organic search performance

5. **Set up Data Export**:
   - Export to BigQuery for advanced analysis
   - Create custom dashboards

---

## Resources

- [GA4 Documentation](https://support.google.com/analytics/answer/9304153)
- [Drupal google_tag module](https://www.drupal.org/project/google_tag)
- [React Native Firebase Analytics](https://rnfirebase.io/analytics/usage)
- [GA4 Events Reference](https://developers.google.com/analytics/devguides/collection/ga4/reference/events)

---

## Questions or Issues?

If you encounter any issues during setup, check:
1. Module/package versions are compatible
2. Configuration files are in correct locations
3. Measurement IDs are correctly copied
4. Cache is cleared (Drupal) / app is rebuilt (mobile)

Good luck with your analytics setup!
