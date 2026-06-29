# Technical Documentation

**Last Updated**: 2024-12-13  
**Status**: 🟡 Organized

---

## Overview

This directory contains technical architecture, API documentation, and implementation guides for Forseti products.

---

## Documentation Structure

```
docs/technical/
├── README.md (this file)
├── architecture.md → links to existing docs
├── api-documentation.md
├── data-models.md
└── integration-guides.md
```

---

## Existing Technical Documentation

### System Architecture

**Location**: `/docs/ARCHITECTURE.md`

Comprehensive overview of:
- System components (Frontend, Backend, Data Layer)
- Technology stack
- Data flow and processing
- Integration points

**Also See**:
- `/amisafe-mobile/ARCHITECTURE.md` - Mobile app architecture
- `/h3-geolocation/ARCHITECTURE.md` - Geospatial processing system

---

### Background Services

**Location**: `/amisafe-mobile/BACKGROUND_SERVICE_DOCUMENTATION.md`

Detailed documentation of mobile background monitoring:
- 12-step process flow
- Platform-specific implementation (iOS, Android)
- API integration
- Troubleshooting guide

---

## Quick Reference

### Technology Stack

**Frontend**:
- Web: Drupal 11, Radix theme + custom Forseti theme
- Mobile: React Native 0.72.6, TypeScript
- Maps: Leaflet.js, H3 hexagon visualization

**Backend**:
- CMS: Drupal 11 with custom modules
- API: RESTful endpoints (Drupal REST module)
- Background Processing: Python scripts, cron jobs

**Data Layer**:
- Database: MySQL
- Geospatial: H3 (Uber's Hexagonal Hierarchical Spatial Index)
- ETL: Python scripts for crime data ingestion

**Infrastructure**:
- Hosting: [To be documented]
- Domain: forseti.life
- SSL: [To be documented]

---

## API Endpoints

### Public Endpoints

**Base URL**: `https://forseti.life`

#### Get Aggregated Crime Data for Hexagon

```
GET /api/amisafe/aggregated
```

**Parameters**:
- `lat` (required): Latitude (decimal)
- `lng` (required): Longitude (decimal)

**Response**:
```json
{
  "hexagon": "8b2a1072b59ffff",
  "resolution": 11,
  "crime_count": 42,
  "z_score": 2.3,
  "risk_level": "elevated",
  "recent_crimes": [
    {
      "type": "theft",
      "date": "2024-12-10",
      "description": "..."
    }
  ]
}
```

**Full Documentation**: `docs/technical/api-documentation.md`

---

## Data Models

### H3 Hexagon Properties

- **Resolution**: 11 (~700m edge length)
- **Coverage**: City-wide grid
- **Update Frequency**: Daily aggregation

### Crime Data Schema

**See**: `docs/technical/data-models.md`

---

## Integration Guides

### Mobile App Integration

**Background Service Setup**:
1. Install dependencies
2. Configure permissions (iOS/Android)
3. Initialize service
4. Handle notifications

**See**: `/amisafe-mobile/BACKGROUND_SERVICE_DOCUMENTATION.md`

### Web Integration

**Embedding Safety Map**:
```html
<iframe src="https://forseti.life/safety-map" width="100%" height="600"></iframe>
```

---

## Development Workflow

### Local Development Setup

**Prerequisites**:
- PHP 8.x
- Composer
- MySQL 8.x
- Node.js 18+
- Python 3.11+

**Setup Scripts**:
- `/script/setup.sh` - Full environment setup
- `/script/setup-mobile.sh` - Mobile app setup
- `/script/setup-android-build.sh` - Android build environment

**See**: `/script/README.md`

---

## Deployment

### Web Deployment

**Production**: forseti.life  
**Deployment Method**: [To be documented]

### Mobile Deployment

**iOS**: TestFlight → App Store  
**Android**: Internal Testing → Production

**See**: `/amisafe-mobile/README.md`

---

## Testing

### Web Testing

**Location**: `/testing/`

### Mobile Testing

**Location**: `/amisafe-mobile/src/` (various test files)

---

## Performance Considerations

### Database Optimization
- H3 hexagon index on crime table
- Caching frequently accessed aggregations
- Query optimization for spatial lookups

### API Rate Limiting
- [To be documented]

### Mobile Battery Optimization
- Configurable location update intervals
- Notification cooldown periods
- Geofence-based monitoring (future enhancement)

---

## Security

### Authentication
- Drupal user system
- JWT tokens for API (if applicable)
- OAuth for third-party integrations (future)

### Data Privacy
- Location data encrypted in transit (HTTPS)
- User data stored securely
- GDPR/CCPA compliance considerations

### API Security
- Rate limiting
- Input validation
- SQL injection prevention

**See**: Privacy documentation in `/sites/forseti/web/modules/custom/forseti_safety_content/`

---

## Monitoring & Analytics

### Application Monitoring
- [To be documented]

### User Analytics
- [To be documented]

### Error Tracking
- [To be documented]

---

## Related Documentation

### Product Documentation
- Product overview: `docs/product/README.md`
- Lean Canvas: `docs/product/lean-canvas/forseti-lean-canvas.md`
- MVP definition: `docs/product/mvp/mvp-definition.md`

### Market Documentation
- Market analysis: `docs/market/README.md`

### Database Documentation
- H3 framework: `/h3-geolocation/README.md`
- ETL pipeline: `/h3-geolocation/database/README.md`

---

## Contributing

### Code Standards
- [To be documented]

### Git Workflow
- [To be documented]

### Pull Request Process
- [To be documented]

---

## Troubleshooting

### Common Issues

**Background Service Not Working**:
- See `/amisafe-mobile/BACKGROUND_SERVICE_DOCUMENTATION.md` (Section 13)

**Map Not Loading**:
- Check API endpoint availability
- Verify H3 hexagon data exists for location

**API Errors**:
- Check API documentation for correct parameters
- Verify authentication if required

---

## Resources

### External Documentation
- Drupal 11: https://www.drupal.org/docs
- React Native: https://reactnative.dev/docs
- H3 Geospatial Index: https://h3geo.org
- Leaflet.js: https://leafletjs.com

### Community
- [To be documented]

