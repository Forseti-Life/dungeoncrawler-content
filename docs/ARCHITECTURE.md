# Forseti.life - Infrastructure Architecture

## System Overview

This document describes the complete infrastructure architecture for the Forseti.life platform, including the Drupal 11 CMS, AmISafe crime monitoring system, H3 geolocation framework, and mobile applications.

## 🏗️ Platform Architecture

```
┌────────────────────────────────────────────────────────────────┐
│                    Forseti.life Platform                       │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐  │
│  │   Drupal     │  │   AmISafe    │  │   Mobile App       │  │
│  │   CMS        │◄─┤   Module     │◄─┤   (React Native)   │  │
│  │              │  │              │  │                     │  │
│  └──────┬───────┘  └──────┬───────┘  └─────────────────────┘  │
│         │                 │                                    │
│         ▼                 ▼                                    │
│  ┌──────────────────────────────────┐                        │
│  │    MySQL Database                │                        │
│  │    - Drupal Content              │                        │
│  │    - User Accounts               │                        │
│  │    - Crime Data (3.4M records)   │                        │
│  │    - H3 Aggregations (413K hex)  │                        │
│  └──────────────────────────────────┘                        │
│                                                                 │
│  ┌──────────────────────────────────┐                        │
│  │    H3 Geolocation Framework      │                        │
│  │    - Python Data Processing      │                        │
│  │    - ETL Pipeline                │                        │
│  │    - Analytics Engine            │                        │
│  └──────────────────────────────────┘                        │
└────────────────────────────────────────────────────────────────┘
```

## 🌐 Web Server Architecture

### Apache/Nginx Configuration

**Document Root Structure:**
```
/var/www/html/
├── forseti/                      # Forseti Drupal site (production)
│   ├── web/                      # Public web root
│   │   ├── core/                 # Drupal 11 core
│   │   ├── modules/              # Contributed & custom modules
│   │   │   └── custom/
│   │   │       ├── amisafe/      # AmISafe crime monitoring
│   │   │       └── forseti_safety_content/  # Website pages
│   │   ├── themes/               # Drupal themes
│   │   ├── sites/
│   │   │   └── default/
│   │   │       ├── settings.php  # Main configuration
│   │   │       └── files/        # Uploaded content
│   │   └── .htaccess            # Apache configuration
│   ├── vendor/                   # Composer dependencies
│   └── config/                   # Drupal configuration
├── amisafe-mobile/               # React Native mobile app
└── h3-geolocation/               # Python geospatial framework
```

### Virtual Host Configuration

**Apache:**
```apache
<VirtualHost *:80>
    ServerName forseti.life
    DocumentRoot /var/www/html/forseti/web

    <Directory /var/www/html/forseti/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security Headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    
    ErrorLog ${APACHE_LOG_DIR}/forseti-error.log
    CustomLog ${APACHE_LOG_DIR}/forseti-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName forseti.life
    DocumentRoot /var/www/html/forseti/web

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/forseti.crt
    SSLCertificateKeyFile /etc/ssl/private/forseti.key

    # Additional SSL configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5

    # HSTS Header
    Header always set Strict-Transport-Security "max-age=31536000"
</VirtualHost>
```

## 💾 Database Architecture

### MySQL Database Structure

**Databases:**
- `drupal_main` - Primary Drupal CMS database
- `amisafe_crime` - Crime data warehouse (optional separate DB)

**Key Tables:**
```sql
-- Drupal Core Tables
users
node
taxonomy_term
file_managed

-- AmISafe Custom Tables
amisafe_incidents         -- Raw crime incidents (3.4M records)
amisafe_h3_aggregations   -- H3 hexagon aggregations (413K)
amisafe_districts         -- Police district boundaries
amisafe_crime_types       -- Crime classification
```

### Data Flow Architecture

```
┌─────────────────┐
│  Raw Crime Data │ (CSV/JSON imports)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Bronze Layer   │ (Raw ingestion)
│  ETL Process    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Silver Layer   │ (Data cleaning)
│  Validation     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Gold Layer     │ (Analytics-ready)
│  H3 Aggregation │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Drupal API     │ (REST endpoints)
│  Mobile App     │
└─────────────────┘
```

## 🔐 Security Architecture

### Authentication & Authorization

**User Roles:**
- Anonymous User (read-only public access)
- Authenticated User (registered users)
- Content Creator (create/edit content)
- Administrator (full system access)

**Authentication Methods:**
1. **Session-based** (Drupal standard)
   - CSRF token validation
   - Cookie-based sessions
   - Automatic timeout

2. **API Authentication** (for mobile app)
   - CSRF tokens for API requests
   - Session-based authentication
   - Optional OAuth2 (future enhancement)

### Security Layers

```
┌─────────────────────────────────────────────┐
│  1. Web Server Level                        │
│     - SSL/TLS encryption                    │
│     - Security headers                      │
│     - Rate limiting                         │
│     - Firewall rules                        │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  2. Application Level (Drupal)              │
│     - User authentication                   │
│     - Permission system                     │
│     - CSRF protection                       │
│     - SQL injection prevention              │
│     - XSS filtering                         │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  3. Database Level                          │
│     - Parameterized queries                 │
│     - Encrypted connections                 │
│     - User privilege separation             │
│     - Backup encryption                     │
└─────────────────────────────────────────────┘
```

### File Permissions (Production)

```bash
# Directory permissions
find . -type d -exec chmod 755 {} \;

# File permissions
find . -type f -exec chmod 644 {} \;

# Critical files (read-only)
chmod 444 sites/default/settings.php
chmod 555 sites/default/

# Writable files directory
chmod 775 sites/default/files/
find sites/default/files/ -type f -exec chmod 664 {} \;
find sites/default/files/ -type d -exec chmod 775 {} \;

# Ownership
chown -R www-data:www-data /var/www/html/
```

## 📊 Performance Architecture

### Caching Strategy

**Multi-Layer Caching:**

1. **Browser Cache** (HTTP headers)
   - Static assets: 1 year
   - CSS/JS: 30 days
   - Images: 7 days

2. **Drupal Internal Cache**
   - Page cache for anonymous users
   - Dynamic page cache
   - Render cache
   - Configuration cache

3. **External Cache** (Optional)
   - Redis/Memcached
   - Varnish reverse proxy
   - CDN for static assets

### Database Optimization

**Indexing Strategy:**
```sql
-- AmISafe performance indexes
CREATE INDEX idx_h3_index ON amisafe_incidents(h3_index);
CREATE INDEX idx_dispatch_date ON amisafe_incidents(dispatch_date);
CREATE INDEX idx_crime_type ON amisafe_incidents(text_general_code);
CREATE INDEX idx_district ON amisafe_incidents(dc_dist);

-- H3 aggregation indexes
CREATE INDEX idx_h3_resolution ON amisafe_h3_aggregations(h3_resolution);
CREATE INDEX idx_incident_count ON amisafe_h3_aggregations(incident_count);
```

**Query Optimization:**
- Prepared statements
- Query result caching
- Connection pooling
- Slow query logging

## 🔄 Backup Architecture

### Backup Tiers

**Tier 1: Daily Backups**
- Frequency: Every 24 hours
- Retention: 7 days
- Content: Database only
- Location: Local server
- Automation: Drupal Backup & Migrate module

**Tier 2: Weekly Backups**
- Frequency: Every 7 days
- Retention: 20 weeks
- Content: Full site (database + files)
- Location: Local server + off-site
- Automation: Drupal Backup & Migrate module

**Tier 3: Off-site Backups**
- Frequency: Weekly
- Retention: 3 months
- Content: Complete site archive
- Location: Remote server or cloud storage
- Automation: Rsync or cloud sync

### Backup Locations

```
/var/backups/forseti/
├── daily/
│   ├── backup-20251201-000000.sql.gz
│   ├── backup-20251202-000000.sql.gz
│   └── ...  (7 days)
├── weekly/
│   ├── backup-full-20251130.tar.gz
│   ├── backup-full-20251123.tar.gz
│   └── ...  (20 weeks)
└── monthly/
    ├── backup-archive-202511.tar.gz
    └── ...  (12 months)
```

## 🔧 Monitoring Architecture

### Health Checks

**System Monitoring:**
- CPU usage
- Memory utilization
- Disk space
- Network traffic
- Database connections

**Application Monitoring:**
- Drupal cron execution
- Backup completion
- Error log monitoring
- Security log analysis
- API response times

**AmISafe Specific:**
- H3 aggregation updates
- Crime data import status
- API endpoint availability
- Mobile app connections

### Alerting System

**Alert Priorities:**
1. **Critical** - Immediate action required
   - Site down
   - Database unreachable
   - Security breach detected
   
2. **Warning** - Review within 24 hours
   - High error rate
   - Disk space low
   - Backup failure
   
3. **Info** - Routine notifications
   - Backup completed
   - Updates available
   - Performance reports

## 📱 Mobile App Integration

### API Architecture

**REST API Endpoints:**
```
https://forseti.life/api/amisafe/
├── risk-level          # Location risk assessment
├── aggregated          # H3 hexagon crime data
├── incidents           # Individual crime records
├── hotspots            # High-crime areas
├── system-stats        # Database statistics
├── crime-types         # Crime classifications
└── districts           # Police districts
```

**Authentication Flow:**
```
Mobile App → GET /session/token → CSRF Token
          ↓
          → POST /user/login → Session Cookie
          ↓
          → GET /api/amisafe/* → Crime Data
          ↓
          ← JSON Response
```

### Data Synchronization

**Real-time Updates:**
- Location changes trigger API queries
- H3 index calculations on mobile device
- Background monitoring every 60 seconds
- Push notifications for risk changes

**Offline Capability:**
- Local cache of recent risk data
- AsyncStorage for preferences
- Queue API requests when offline
- Sync on network restore

## 🌍 H3 Geolocation Framework

### Data Processing Pipeline

**ETL Architecture:**
```
1. Extract (Bronze Layer)
   - Import raw crime CSV/JSON
   - Parse incident records
   - Store in temporary staging

2. Transform (Silver Layer)
   - Validate coordinates
   - Calculate H3 indexes
   - Clean and normalize data
   - Geocode addresses

3. Load (Gold Layer)
   - Aggregate by H3 hexagon
   - Calculate statistics
   - Update API cache
   - Generate analytics
```

### H3 Resolution Strategy

| Level | Area | Purpose | Records |
|-------|------|---------|---------|
| 5 | 251 km² | Citywide overview | ~50 |
| 8 | 0.7 km² | District analysis | ~2,000 |
| 10 | 15,047 m² | Neighborhood | ~15,000 |
| 11 | ~700 m² | Street level | ~60,000 |
| 13 | 44 m² | Building precision | ~413,000 |

## 🚀 Deployment Architecture

### Deployment Workflow

```
Development (Codespaces)
  │
  ├─► Build Assets
  │   └─► npm run production
  │
  ├─► Security Hardening
  │   └─► ./scripts/production-security-hardening.sh
  │
  ├─► Package Application
  │   └─► tar -czf deploy.tar.gz
  │
  ▼
Production Server
  │
  ├─► Extract Package
  │   └─► tar -xzf deploy.tar.gz
  │
  ├─► Set Permissions
  │   └─► chown -R www-data:www-data
  │
  ├─► Update Database
  │   ├─► drush updb -y
  │   └─► drush cim -y
  │
  ├─► Clear Caches
  │   └─► drush cr
  │
  └─► Verify Deployment
      └─► drush status
```

### Environment Configuration

**Development:**
- Debug mode enabled
- Error display on
- Development modules active
- Relaxed file permissions
- Local database

**Staging:**
- Debug mode enabled
- Error logging only
- Production-like data
- Security testing
- SSL configured

**Production:**
- Debug mode disabled
- Error logging only
- Strict file permissions
- Security hardened
- Performance optimized
- SSL enforced

---

## Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- Monitor backup completion
- Check error logs
- Review security logs

**Weekly:**
- Update security patches
- Verify backup integrity
- Performance review

**Monthly:**
- Security audit
- Backup restoration test
- Capacity planning
- Update documentation

---

**Last Updated**: February 6, 2026
