# Performance Optimization Strategy

**Document Version**: 1.0  
**Last Updated**: February 12, 2026  
**Status**: ✅ Complete

---

## Overview

This document outlines the performance optimization strategy for Forseti/AmISafe across mobile and web platforms. Performance is critical for a safety application - users need instant access to potentially life-saving information.

---

## Performance Goals & Metrics

### Mobile App (React Native)

| Metric | Target | Critical Use Case |
|--------|--------|-------------------|
| **Cold Start** | < 2 seconds | App launch |
| **Warm Start** | < 1 second | Return to app |
| **Tab Switch** | < 100ms | Navigate between tabs |
| **Map Load** | < 1 second | Initial map render |
| **Location Update** | < 500ms | Get current location |
| **Data Sync** | < 3 seconds | Background data fetch |
| **Alert Delivery** | < 30 seconds | Push notification |
| **Frame Rate** | 60 FPS | Smooth animations |

### Web Platform (Drupal)

| Metric | Target | Measurement |
|--------|--------|-------------|
| **First Contentful Paint (FCP)** | < 1.5s | Time to first content |
| **Largest Contentful Paint (LCP)** | < 2.5s | Main content visible |
| **First Input Delay (FID)** | < 100ms | Interactive quickly |
| **Time to Interactive (TTI)** | < 3.5s | Fully interactive |
| **Cumulative Layout Shift (CLS)** | < 0.1 | Visual stability |
| **Speed Index** | < 3.0s | Visual completeness |
| **Total Blocking Time (TBT)** | < 300ms | Main thread free |

### Network Targets

| Connection | Load Time | Data Transfer |
|------------|-----------|---------------|
| **4G LTE** | < 2s | < 500KB initial |
| **3G** | < 5s | < 300KB initial |
| **Slow 3G** | < 10s | < 200KB initial |
| **Offline** | Instant | 0KB (cached) |

---

## Optimization Strategies

### 1. Mobile App Optimization (React Native)

#### 🚀 App Launch Optimization

**Cold Start (App Not Running)**
```javascript
// 1. Minimize initial bundle size
// Use code splitting and lazy loading
import { lazy } from 'react';

const MapScreen = lazy(() => import('./screens/MapScreen'));
const SafetyScreen = lazy(() => import('./screens/SafetyScreen'));

// 2. Optimize splash screen
// Keep splash screen visible until critical resources loaded
import SplashScreen from 'react-native-splash-screen';

// In App.tsx
useEffect(() => {
  // Hide splash after critical data loaded
  initializeApp().then(() => {
    SplashScreen.hide();
  });
}, []);

// 3. Defer non-critical initialization
const initializeApp = async () => {
  // Critical: Load user session, fetch location
  await Promise.all([
    loadUserSession(),
    getCurrentLocation(),
  ]);
  
  // Non-critical: Initialize analytics, check for updates
  // Run these in background
  setTimeout(() => {
    initializeAnalytics();
    checkForUpdates();
  }, 1000);
};
```

**Warm Start (App in Background)**
```javascript
// Persist app state to AsyncStorage
import AsyncStorage from '@react-native-async-storage/async-storage';

// Save state when app backgrounds
AppState.addEventListener('change', (nextAppState) => {
  if (nextAppState === 'background') {
    saveAppState();
  }
});

const saveAppState = async () => {
  try {
    await AsyncStorage.setItem('app_state', JSON.stringify({
      lastLocation: currentLocation,
      lastFetch: Date.now(),
      mapCenter: mapCenter,
    }));
  } catch (error) {
    console.error('Failed to save state:', error);
  }
};

// Restore state on app foreground
const restoreAppState = async () => {
  try {
    const state = await AsyncStorage.getItem('app_state');
    if (state) {
      return JSON.parse(state);
    }
  } catch (error) {
    console.error('Failed to restore state:', error);
  }
};
```

#### ⚡ Navigation Performance

```javascript
// 1. Use React Navigation native stack for best performance
import { createNativeStackNavigator } from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

// 2. Enable screen optimization
<Stack.Navigator
  screenOptions={{
    // Freeze inactive screens to save memory
    freezeOnBlur: true,
    // Lazy load screens
    lazy: true,
  }}
>
  {/* Screens */}
</Stack.Navigator>

// 3. Optimize tab bar
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';

const Tab = createBottomTabNavigator();

<Tab.Navigator
  screenOptions={{
    // Lazy load tabs
    lazy: true,
    // Unmount inactive tabs (if memory constrained)
    unmountOnBlur: false,
  }}
>
  {/* Tabs */}
</Tab.Navigator>
```

#### 🗺️ Map Performance

```javascript
// 1. Optimize React Native Maps
import MapView, { PROVIDER_GOOGLE } from 'react-native-maps';

<MapView
  provider={PROVIDER_GOOGLE}  // Use native map provider
  style={styles.map}
  // Performance optimizations
  initialRegion={region}
  onRegionChangeComplete={handleRegionChange}  // Not onRegionChange
  showsUserLocation={true}
  loadingEnabled={true}
  loadingIndicatorColor="#00d4ff"
  // Cache tiles
  cacheEnabled={true}
  // Limit markers
  maxZoomLevel={18}
  minZoomLevel={10}
/>

// 2. Cluster markers for performance
import Supercluster from 'supercluster';

const cluster = new Supercluster({
  radius: 40,
  maxZoom: 16,
});

// Only render visible markers
const getVisibleCrimes = (bounds, zoom) => {
  return cluster.getClusters(bounds, zoom);
};

// 3. Use H3 hexagons efficiently
import { h3ToGeoBoundary } from 'h3-js';

// Cache hexagon boundaries
const hexagonCache = new Map();

const getHexagonBoundary = (h3Index) => {
  if (!hexagonCache.has(h3Index)) {
    hexagonCache.set(h3Index, h3ToGeoBoundary(h3Index));
  }
  return hexagonCache.get(h3Index);
};

// Limit number of visible hexagons
const MAX_VISIBLE_HEXAGONS = 500;
```

#### 💾 Data & Caching

```javascript
// 1. Implement aggressive caching
import AsyncStorage from '@react-native-async-storage/async-storage';

const CACHE_KEYS = {
  CRIME_DATA: 'crime_data',
  USER_LOCATION: 'user_location',
  SETTINGS: 'user_settings',
};

const CACHE_DURATION = {
  CRIME_DATA: 1000 * 60 * 60,  // 1 hour
  USER_LOCATION: 1000 * 60 * 5, // 5 minutes
};

// Cache-first strategy
const getCrimeData = async (location, force = false) => {
  const cacheKey = `${CACHE_KEYS.CRIME_DATA}_${location.lat}_${location.lng}`;
  
  if (!force) {
    const cached = await AsyncStorage.getItem(cacheKey);
    if (cached) {
      const { data, timestamp } = JSON.parse(cached);
      if (Date.now() - timestamp < CACHE_DURATION.CRIME_DATA) {
        return data;
      }
    }
  }
  
  // Fetch fresh data
  const data = await fetchCrimeData(location);
  
  // Cache for later
  await AsyncStorage.setItem(cacheKey, JSON.stringify({
    data,
    timestamp: Date.now(),
  }));
  
  return data;
};

// 2. Use React Query for data fetching
import { useQuery } from '@tanstack/react-query';

const useCrimeData = (location) => {
  return useQuery({
    queryKey: ['crime', location],
    queryFn: () => fetchCrimeData(location),
    staleTime: 1000 * 60 * 60,  // Consider data fresh for 1 hour
    cacheTime: 1000 * 60 * 60 * 24,  // Keep in cache for 24 hours
    // Return cached data immediately, refetch in background
    refetchOnMount: 'always',
    refetchOnWindowFocus: false,
  });
};
```

#### 🖼️ Image Optimization

```javascript
// 1. Use React Native Fast Image
import FastImage from 'react-native-fast-image';

<FastImage
  style={styles.image}
  source={{
    uri: imageUrl,
    priority: FastImage.priority.normal,
    cache: FastImage.cacheControl.immutable,
  }}
  resizeMode={FastImage.resizeMode.cover}
/>

// 2. Lazy load images
import { LazyLoadImage } from 'react-native-lazy-load-image';

<LazyLoadImage
  source={{ uri: imageUrl }}
  style={styles.image}
  placeholder={require('./placeholder.png')}
/>

// 3. Optimize image sizes
const getOptimizedImageUrl = (url, width) => {
  // Request appropriately sized image from server
  return `${url}?w=${width}&q=80&fm=webp`;
};
```

#### 🎨 Rendering Optimization

```javascript
// 1. Use React.memo for expensive components
import React, { memo } from 'react';

const CrimeMarker = memo(({ crime, onPress }) => {
  return (
    <Marker
      coordinate={crime.location}
      onPress={onPress}
    >
      <CrimeIcon type={crime.type} />
    </Marker>
  );
}, (prevProps, nextProps) => {
  // Custom comparison
  return prevProps.crime.id === nextProps.crime.id;
});

// 2. Use FlatList for long lists
import { FlatList } from 'react-native';

<FlatList
  data={crimes}
  renderItem={({ item }) => <CrimeItem crime={item} />}
  keyExtractor={(item) => item.id}
  // Performance optimizations
  initialNumToRender={10}
  maxToRenderPerBatch={10}
  windowSize={5}
  removeClippedSubviews={true}
  getItemLayout={(data, index) => ({
    length: ITEM_HEIGHT,
    offset: ITEM_HEIGHT * index,
    index,
  })}
/>

// 3. Avoid inline functions in render
// ❌ Bad
<Button onPress={() => handlePress(item.id)} />

// ✅ Good
const handlePressCallback = useCallback(() => {
  handlePress(item.id);
}, [item.id]);

<Button onPress={handlePressCallback} />
```

#### 🔋 Battery Optimization

```javascript
// 1. Throttle location updates
import { throttle } from 'lodash';

const throttledLocationUpdate = throttle(
  (location) => {
    updateUserLocation(location);
  },
  5000  // Update every 5 seconds max
);

Geolocation.watchPosition(
  (position) => {
    throttledLocationUpdate(position.coords);
  },
  (error) => console.error(error),
  {
    enableHighAccuracy: false,  // Use low power mode when possible
    distanceFilter: 100,  // Only update if moved 100m
    interval: 10000,  // Check every 10 seconds
  }
);

// 2. Reduce background activity
import BackgroundTimer from 'react-native-background-timer';

// Use longer intervals in background
let updateInterval = 30000;  // 30 seconds

AppState.addEventListener('change', (nextAppState) => {
  if (nextAppState === 'background') {
    updateInterval = 60000;  // 1 minute in background
  } else if (nextAppState === 'active') {
    updateInterval = 30000;  // 30 seconds when active
  }
});
```

---

### 2. Web Platform Optimization (Drupal)

#### 🏗️ Initial Load Optimization

**Critical CSS Inlining**
```html
<!-- Inline critical CSS for above-the-fold content -->
<head>
  <style>
    /* Critical styles for header, hero, and initial viewport */
    .header { /* ... */ }
    .hero { /* ... */ }
    .above-fold { /* ... */ }
  </style>
  
  <!-- Load full stylesheet asynchronously -->
  <link rel="preload" href="/styles/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="/styles/main.css"></noscript>
</head>
```

**Resource Hints**
```html
<head>
  <!-- DNS prefetch for external domains -->
  <link rel="dns-prefetch" href="https://api.forseti.life">
  <link rel="dns-prefetch" href="https://cdn.forseti.life">
  
  <!-- Preconnect to critical origins -->
  <link rel="preconnect" href="https://api.forseti.life">
  
  <!-- Preload critical resources -->
  <link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/images/hero.webp" as="image">
</head>
```

**Code Splitting**
```javascript
// Webpack configuration for Drupal theme
module.exports = {
  optimization: {
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        // Vendor code in separate bundle
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendors',
          priority: 10,
        },
        // Map libraries separate (large)
        map: {
          test: /[\\/]node_modules[\\/](leaflet|h3-js)[\\/]/,
          name: 'map',
          priority: 20,
        },
      },
    },
  },
};

// Lazy load map component
const MapComponent = lazy(() => import('./components/Map'));

<Suspense fallback={<MapSkeleton />}>
  <MapComponent />
</Suspense>
```

#### 🗜️ Asset Optimization

**Image Optimization**
```nginx
# Nginx configuration for image optimization
location ~* \.(jpg|jpeg|png|gif)$ {
  # Enable gzip
  gzip on;
  gzip_types image/jpeg image/png image/gif;
  
  # WebP conversion
  # Serve WebP if browser supports it
  set $webp_suffix "";
  if ($http_accept ~* "webp") {
    set $webp_suffix ".webp";
  }
  
  # Cache for 1 year
  expires 1y;
  add_header Cache-Control "public, immutable";
  
  # Enable HTTP/2 push
  http2_push_preload on;
}
```

**JavaScript Optimization**
```javascript
// webpack.config.js
module.exports = {
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          compress: {
            drop_console: true,  // Remove console.logs in production
            dead_code: true,
            unused: true,
          },
          mangle: true,
        },
      }),
    ],
  },
  plugins: [
    // Compress bundle with Brotli
    new CompressionPlugin({
      filename: '[path][base].br',
      algorithm: 'brotliCompress',
      test: /\.(js|css|html|svg)$/,
      compressionOptions: {
        level: 11,
      },
      threshold: 10240,
      minRatio: 0.8,
    }),
  ],
};
```

#### 📦 Caching Strategy

**Service Worker (PWA)**
```javascript
// service-worker.js
const CACHE_NAME = 'forseti-v1';
const STATIC_CACHE = [
  '/',
  '/styles/main.css',
  '/scripts/main.js',
  '/images/logo.svg',
];

// Install: Cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_CACHE);
    })
  );
});

// Fetch: Network first for API, cache first for static
self.addEventListener('fetch', (event) => {
  const { request } = event;
  
  // API requests: Network first
  if (request.url.includes('/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseClone);
          });
          return response;
        })
        .catch(() => {
          return caches.match(request);
        })
    );
  }
  // Static assets: Cache first
  else {
    event.respondWith(
      caches.match(request).then((response) => {
        return response || fetch(request);
      })
    );
  }
});
```

**HTTP Caching Headers**
```apache
# .htaccess for Drupal
<IfModule mod_expires.c>
  ExpiresActive On
  
  # Images
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
  
  # CSS and JavaScript
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  
  # Fonts
  ExpiresByType font/woff2 "access plus 1 year"
  
  # HTML
  ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# Cache-Control headers
<IfModule mod_headers.c>
  # Static assets: immutable
  <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|woff2|js|css)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
  
  # HTML: no-cache
  <FilesMatch "\.html$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>
```

#### 🔍 Database Optimization (Drupal)

```php
// Drupal settings.php optimizations
$settings['cache']['bins']['render'] = 'cache.backend.database';
$settings['cache']['bins']['page'] = 'cache.backend.database';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.database';

// Enable BigPipe for faster perceived performance
$config['big_pipe.settings']['enabled'] = TRUE;

// Enable CSS and JS aggregation
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

// Configure Redis caching (if available)
if (extension_loaded('redis')) {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = '127.0.0.1';
  $settings['cache']['default'] = 'cache.backend.redis';
}

// Database query optimization
$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => 'forseti',
  'username' => 'user',
  'password' => 'pass',
  'host' => 'localhost',
  'prefix' => '',
  'pdo' => [
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
  ],
  // Enable persistent connections
  'persistent' => TRUE,
];
```

#### 🗺️ Map Optimization (Web)

```javascript
// Leaflet optimization for web
import L from 'leaflet';
import 'leaflet.markercluster';

const map = L.map('map', {
  // Performance settings
  preferCanvas: true,  // Use Canvas instead of SVG
  renderer: L.canvas(),
  // Smooth animations
  zoomAnimation: true,
  fadeAnimation: true,
  // Limit zoom
  maxZoom: 18,
  minZoom: 10,
});

// Use marker clustering
const markers = L.markerClusterGroup({
  chunkedLoading: true,
  chunkInterval: 200,
  chunkDelay: 50,
  // Optimize cluster icons
  iconCreateFunction: (cluster) => {
    return L.divIcon({
      html: `<div>${cluster.getChildCount()}</div>`,
      className: 'marker-cluster',
    });
  },
});

// Lazy load hexagons
const hexagonLayer = L.layerGroup();
map.on('moveend', () => {
  const bounds = map.getBounds();
  loadHexagonsInBounds(bounds).then((hexagons) => {
    hexagonLayer.clearLayers();
    hexagons.forEach((hex) => {
      L.polygon(hex.boundary, {
        color: hex.riskColor,
        weight: 1,
        fillOpacity: 0.5,
      }).addTo(hexagonLayer);
    });
  });
});
```

---

### 3. API & Backend Optimization

#### ⚡ API Response Optimization

**GraphQL (Efficient Data Fetching)**
```graphql
# Only request needed fields
query GetLocationSafety($lat: Float!, $lng: Float!) {
  locationSafety(lat: $lat, lng: $lng) {
    riskLevel
    zScore
    recentCrimes(limit: 5) {
      id
      type
      date
    }
  }
}
```

**Response Compression**
```javascript
// Express.js middleware
const compression = require('compression');

app.use(compression({
  level: 6,  // Balance between speed and compression
  threshold: 1024,  // Only compress responses > 1KB
  filter: (req, res) => {
    if (req.headers['x-no-compression']) {
      return false;
    }
    return compression.filter(req, res);
  },
}));
```

**Response Pagination**
```javascript
// API endpoint with pagination
app.get('/api/crimes', async (req, res) => {
  const { page = 1, limit = 50 } = req.query;
  const offset = (page - 1) * limit;
  
  const crimes = await db.query(`
    SELECT * FROM crimes
    WHERE lat BETWEEN ? AND ?
      AND lng BETWEEN ? AND ?
    LIMIT ? OFFSET ?
  `, [minLat, maxLat, minLng, maxLng, limit, offset]);
  
  res.json({
    data: crimes,
    pagination: {
      page,
      limit,
      total: await db.count('crimes'),
    },
  });
});
```

#### 🗄️ Database Optimization

```sql
-- Add indexes for frequent queries
CREATE INDEX idx_crimes_location ON crimes(lat, lng);
CREATE INDEX idx_crimes_date ON crimes(date);
CREATE INDEX idx_crimes_type ON crimes(type);

-- Composite index for common query pattern
CREATE INDEX idx_crimes_location_date ON crimes(lat, lng, date);

-- H3 hexagon index
CREATE INDEX idx_h3_hexagons_index ON h3_hexagons(h3_index);
CREATE INDEX idx_h3_hexagons_risk ON h3_hexagons(risk_score);

-- Query optimization with materialized views
CREATE MATERIALIZED VIEW crime_summary AS
SELECT 
  h3_index,
  COUNT(*) as crime_count,
  AVG(risk_score) as avg_risk,
  DATE_TRUNC('day', date) as day
FROM crimes
GROUP BY h3_index, day;

-- Refresh periodically
REFRESH MATERIALIZED VIEW CONCURRENTLY crime_summary;
```

---

## Performance Monitoring

### Mobile App Analytics

```javascript
// Track performance metrics
import { PerformanceMonitor } from '@react-native-firebase/perf';

// App launch time
const trace = PerformanceMonitor().newTrace('app_launch');
await trace.start();
// ... initialization
await trace.stop();

// Screen load time
const screenTrace = PerformanceMonitor().newTrace('home_screen_load');
await screenTrace.start();
// ... load screen
await screenTrace.stop();

// Network requests
const httpTrace = PerformanceMonitor().newHttpMetric(url, 'GET');
await httpTrace.start();
const response = await fetch(url);
await httpTrace.setHttpResponseCode(response.status);
await httpTrace.setResponseContentType(response.headers.get('content-type'));
await httpTrace.stop();
```

### Web Performance Monitoring

```javascript
// Use Performance API
const perfData = performance.getEntriesByType('navigation')[0];

// Send to analytics
sendAnalytics({
  fcp: perfData.responseEnd - perfData.fetchStart,
  domLoad: perfData.domContentLoadedEventEnd - perfData.fetchStart,
  windowLoad: perfData.loadEventEnd - perfData.fetchStart,
});

// Core Web Vitals
import { getCLS, getFID, getFCP, getLCP, getTTFB } from 'web-vitals';

getCLS(sendToAnalytics);
getFID(sendToAnalytics);
getFCP(sendToAnalytics);
getLCP(sendToAnalytics);
getTTFB(sendToAnalytics);
```

---

## Performance Budget

### Mobile Bundle Sizes
- **Main bundle**: < 2MB
- **Per-screen bundle**: < 500KB
- **Initial JS**: < 1MB
- **Images per screen**: < 500KB

### Web Bundle Sizes
- **Initial HTML**: < 50KB
- **Critical CSS**: < 20KB
- **Initial JS**: < 150KB
- **Total page weight**: < 1MB

### Performance Score Targets
- **Lighthouse Performance**: > 90
- **Lighthouse Accessibility**: > 95
- **Lighthouse Best Practices**: > 95
- **Lighthouse SEO**: > 95

---

## Related Documents

- [Mobile-First Design](./04-mobile-first-approach.md) - Responsive performance
- [Accessibility Checklist](./05-accessibility-checklist.md) - Accessibility performance

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-02-12 | Initial performance optimization strategy | Copilot |
