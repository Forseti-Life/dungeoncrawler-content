# AmISafe Installation Guide

## System Requirements

- **Drupal:** 9.5+, 10.x, or 11.x
- **PHP:** 8.1 or higher
- **Database:** MySQL 5.7+ / MariaDB 10.3+ / PostgreSQL 10+
- **Disk Space:** ~100MB minimum

## Installation Steps

### 1. Download and Install

Using Composer (recommended):
```bash
composer require drupal/amisafe
```

Or download manually and extract to `modules/contrib/amisafe`

### 2. Enable the Module

```bash
drush en amisafe
```

Or navigate to: **Admin → Extend** and enable "AmISafe" module

### 3. Run Installation Hooks

The installation process creates required database tables:
```bash
drush updatedb
```

This creates:
- `amisafe_h3_aggregated` - H3 hexagon aggregations
- `amisafe_incidents` - Raw incident data (if using local storage)
- `amisafe_log_entries` - Console logs
- `amisafe_user_locations` - User location tracking

### 4. Configure Module Permissions

Navigate to **Admin → People → Permissions** and assign:

| Permission | Role | Purpose |
|---|---|---|
| Administer AmISafe | Administrator | Full configuration access |
| Access AmISafe dashboard | Staff, Editors | View analytics |
| Access AmISafe API | Mobile app users | API endpoint access |

### 5. Initial Configuration

Navigate to **Admin → Configuration → System → AmISafe Settings**

**Data Layer** (required):
- Select "Gold Layer (Recommended)" for performance
- System will use pre-computed aggregations

**H3 Resolution Settings** (optional):
- Default resolution: 9 (block-level accuracy)
- Adjust based on performance needs
  - Lower resolution (5-7) = faster queries
  - Higher resolution (9-13) = more detail

**Features** (optional):
- Enable Log Management: Track console diagnostics
- Enable Location Tracking: User location APIs
- Enable Mobile Auth: User registration/login

### 6. Import Data (if needed)

If you have incident/event CSV data:

```bash
drush amisafe:import-data path/to/data.csv
```

CSV format (required columns):
```
date,type,latitude,longitude
2025-01-15,theft,39.9526,-75.1652
2025-01-15,robbery,39.9542,-75.1658
```

### 7. Verify Installation

- Navigate to `/amisafe/dashboard` - Should load successfully
- Check database: `drush sqlq "SHOW TABLES LIKE 'amisafe%'"`
- View logs: **Admin → Reports → Recent Log Messages**

## Troubleshooting

### Module Not Appearing in Extend Page

**Problem:** Module shows as disabled but won't enable  
**Solution:**
```bash
drush cache:rebuild
drush en amisafe
```

### Database Tables Not Created

**Problem:** Installation hook failed  
**Solution:**
```bash
drush updatedb --pending  # See pending updates
drush updatedb           # Run updates
```

### Dashboard Loads Blank

**Problem:** Dashboard page shows but no data  
**Solution:**
1. Check module configuration: **Admin → Configuration → AmISafe Settings**
2. Verify Gold Layer is enabled
3. Verify data has been imported: `drush sqlq "SELECT COUNT(*) FROM amisafe_h3_aggregated"`
4. Check logs: **Admin → Reports → Recent Log Messages**

### API Endpoints Returning 403 Permission Denied

**Problem:** API calls fail with permission error  
**Solution:**
1. Grant permissions: **Admin → People → Permissions → AmISafe**
2. Verify user has "Access AmISafe API" permission
3. For unauthenticated access: Configure API settings to allow public access

### Performance Issues (Slow Queries)

**Problem:** Dashboard/API responses slow  
**Solution:**
1. Switch to Gold Layer (pre-computed data) in settings
2. Reduce H3 resolution level if querying high resolution
3. Add database indexes: `drush amisafe:optimize-db`
4. Check performance logs: **Admin → Reports → AmISafe Performance**

### Mobile App Connection Failures

**Problem:** Mobile app cannot connect to API  
**Solution:**
1. Verify API endpoints enabled: **Admin → Configuration → AmISafe → Enable APIs**
2. Check CORS headers in API responses
3. Verify URL accessible from mobile device
4. Check firewall rules

## Post-Installation

After successful installation:

1. **Review Security Settings**
   - See [SECURITY.md](SECURITY.md) for security practices
   - Configure data access control
   - Set up log retention policies

2. **Performance Tuning**
   - See [PERFORMANCE_OPTIMIZATION.md](PERFORMANCE_OPTIMIZATION.md)
   - Configure cache settings
   - Monitor database performance

3. **Read Documentation**
   - [ARCHITECTURE.md](ARCHITECTURE.md) - System design and APIs
   - [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) - Data warehouse schema
   - [CONTRIBUTING.md](CONTRIBUTING.md) - Development setup

## Getting Help

- **Documentation:** See `/docs` directory
- **Issues:** Report on Drupal.org issue queue
- **Security:** See [SECURITY.md](SECURITY.md)

## Drush Commands

- `drush en amisafe` - Enable module
- `drush dis amisafe` - Disable module
- `drush amisafe:import-data <file>` - Import CSV data
- `drush amisafe:optimize-db` - Add database indexes
- `drush amisafe:clear-logs` - Archive old log entries

