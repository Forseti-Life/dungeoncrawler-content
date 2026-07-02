# AmISafe - Spatial Analytics Dashboard

**Module:** AmISafe  
**Version:** 1.0.0  
**Drupal Compatibility:** ^9 || ^10 || ^11

## Overview

AmISafe is a comprehensive spatial analytics and data visualization system that provides ultra-fine spatial precision data analysis using H3 geospatial hexagons (Resolution 13 support with 44m² precision). The module provides interactive filtering, multi-resolution analytics, and professional visualization interfaces for location-based data.

**Key Capabilities:**
- **H3 Geospatial Analysis**: Data aggregated into hexagonal sectors with configurable precision (resolutions 5-13)
- **Multi-Layer Data Warehouse**: Bronze (raw) → Silver (processed) → Gold (aggregated) architecture
- **Ultra-Precision Analytics**: Room-level accuracy with 44m² hexagon precision
- **Interactive Visualization**: Crime maps, heatmaps, and data filtering
- **Real-time Statistics**: Dynamic dashboard with live data updates

## Features

### 🗺️ Interactive Spatial Map
- H3 hexagonal visualization with multiple zoom levels
- Multiple rendering modes (hexagon, heatmap, point cloud)
- Dynamic resolution adjustment based on view
- Flexible color schemes and styling

### 🎛️ Advanced Filtering System
- Multi-criteria filtering (category, location, date range, severity)
- Quick preset filters for common queries
- Real-time data refresh
- Temporal analysis capabilities

### �� Analytics Dashboard
- Citywide overview statistics
- Current view statistics
- Threat/risk level assessment
- Configurable data aggregation

### 🎨 Professional Interface
- Responsive design (desktop, tablet, mobile)
- Clean, accessible UI
- Real-time updates and animations
- Customizable themes

### 📱 Mobile Support
- Mobile download management
- Location tracking APIs
- User authentication system
- Console logging framework

### 📋 Administrative Tools
- Log management and audit trails
- User location history tracking
- System configuration interface
- Comprehensive API endpoints

## Installation

### Requirements
- Drupal 9, 10, or 11
- PHP 8.1+
- MySQL/MariaDB or PostgreSQL

### Steps

1. **Download the module**
   ```bash
   composer require drupal/amisafe
   ```

2. **Install the module**
   ```bash
   drush en amisafe
   ```

3. **Configure the module**
   - Navigate to Admin → Configuration → AmISafe Settings
   - Select data layer (Gold layer recommended)
   - Configure H3 resolution levels (default: 5-13)
   - Enable/disable log management and tracking features

4. **Grant permissions**
   - Assign "Access AmISafe dashboard" to appropriate roles
   - Assign "Administer AmISafe settings" to administrators

## Configuration

### Data Layer Selection
The module supports 3-tier data warehouse architecture:
- **Bronze Layer**: Raw incident/event data
- **Silver Layer**: Processed and normalized data
- **Gold Layer**: Pre-computed H3 aggregations (recommended for performance)

### H3 Resolution Levels
Configure which H3 resolution levels to use:
- **Resolution 5**: City-wide (10+ km) - fast queries
- **Resolution 9**: Block-level (174m) - default visualization
- **Resolution 13**: Ultra-precision (44m) - detailed analysis

### Optional Features
- Log Management: Track console logs and diagnostics
- Location Tracking: Enable location update APIs
- User Authentication: Configure mobile app access

## API Endpoints

### Data Access
- `GET /api/amisafe/aggregated?resolution={5-13}` - Get aggregated data
- `GET /api/amisafe/system-stats` - Get system statistics
- `GET /api/amisafe/ultra-precision` - Get ultra-precision data

### Mobile App
- `POST /api/amisafe/user/register` - Register mobile user
- `POST /api/amisafe/user/login` - Authenticate user
- `POST /api/amisafe/location/update` - Update user location
- `GET /api/amisafe/location/history` - Retrieve location history

### Log Management
- `POST /api/amisafe/log/upload` - Upload console logs
- `GET /api/amisafe/log/{log_id}` - Retrieve specific log
- `DELETE /api/amisafe/log/{log_id}/delete` - Delete log entry

## Permissions

- `administer amisafe` - Full module administration and configuration
- `access amisafe dashboard` - View the analytics dashboard
- `access amisafe api` - Use API endpoints

## Security

See [SECURITY.md](SECURITY.md) for vulnerability reporting and security practices.

## Performance

The module is optimized for production use with:
- Pre-computed Gold layer aggregations (up to 3.4M+ records)
- Efficient H3 hexagon queries
- Configurable resolution levels for speed/accuracy tradeoff
- Cache support for repeated queries

See [PERFORMANCE_OPTIMIZATION.md](PERFORMANCE_OPTIMIZATION.md) for tuning details.

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for system design, API reference, and extension points.

See [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) for data warehouse schema and relationships.

## Support

- Documentation: See `/docs` directory
- Issues: Report via Drupal.org issue queue
- Security: See [SECURITY.md](SECURITY.md)

## License

Apache 2.0 License - See LICENSE file for details.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.
