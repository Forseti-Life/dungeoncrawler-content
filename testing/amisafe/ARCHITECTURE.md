# AmISafe Testing Architecture

**Last Updated:** February 6, 2026

## Overview

This document describes the testing infrastructure for the AmISafe crime monitoring system, including API validation, filter testing, database validation, and mobile testing automation.

## Test Infrastructure

### Directory Structure
```
testing/amisafe/
├── README.md              # Test documentation and results
├── ARCHITECTURE.md        # This file - test infrastructure
├── mobile/                # Mobile app testing (NEW)
│   └── (mobile test automation)
├── validate_filters.sh    # Automated filter validation
├── validate_gold_layer.sh # Database validation
├── granular_filter_test.sh    # Granular filter testing
├── interactive_filter_test.sh # Manual test guide
├── test_hover_tooltips.js     # Frontend tooltip testing
└── package.json           # Node.js test dependencies
```

### Test Categories

#### 1. API Testing
**Script**: `validate_filters.sh`
- Automated API endpoint validation
- 22 comprehensive test cases
- Filter parameter validation
- H3 resolution testing
- Performance benchmarking
- Error handling verification

#### 2. Database Validation
**Script**: `validate_gold_layer.sh`
- Gold layer data integrity
- H3 aggregation validation
- Data quality checks
- Record count verification

#### 3. Frontend Testing
**Scripts**: `test_hover_tooltips.js`, `interactive_filter_test.sh`
- UI element validation
- User interaction testing
- Tooltip functionality
- Filter control behavior

#### 4. Mobile Testing (NEW)
**Directory**: `mobile/`
- React Native app testing
- API integration validation
- H3 geospatial function testing
- Background monitoring testing
- Authentication flow testing

## Test Execution Flow

```
┌─────────────────────────────────────────────┐
│         AmISafe Testing Pipeline            │
├─────────────────────────────────────────────┤
│                                             │
│  1. Database Validation                     │
│     └─> validate_gold_layer.sh             │
│         ├─> Check H3 aggregations           │
│         ├─> Verify data integrity           │
│         └─> Count validation                │
│                                             │
│  2. API Testing                             │
│     └─> validate_filters.sh                │
│         ├─> Endpoint availability           │
│         ├─> Filter parameters               │
│         ├─> H3 resolution switching         │
│         └─> Performance metrics             │
│                                             │
│  3. Frontend Testing                        │
│     └─> test_hover_tooltips.js             │
│         └─> interactive_filter_test.sh     │
│             ├─> UI element checks           │
│             ├─> Filter interactions         │
│             └─> User feedback validation    │
│                                             │
│  4. Mobile Testing                          │
│     └─> mobile/ test suite                 │
│         ├─> Authentication tests            │
│         ├─> H3 geospatial tests            │
│         ├─> API integration tests           │
│         └─> Background monitoring tests     │
└─────────────────────────────────────────────┘
```

## Test Environment Requirements

### Backend
- MySQL 8.0+ with stlouisintegration_dev database
- 3.4M+ crime incident records
- 413K+ H3 hexagon aggregations (resolutions 4-13)
- Drupal 11 with AmISafe module enabled
- Apache/Nginx web server

### Frontend
- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Local server access (localhost or network)

### Mobile
- Node.js 16+ with npm
- React Native 0.72.6
- 651+ npm packages installed
- Android SDK or Xcode (for native testing)

## Performance Benchmarks

### API Response Times
- Simple queries: < 500ms
- Complex filtered queries: < 2s
- Full map load (10K+ hexagons): < 5s
- H3 resolution switching: < 1s

### Database Query Performance
- Single H3 lookup: < 10ms
- Filtered aggregation (10K records): < 500ms
- Full table scan: < 3s
- Join operations: < 1s

### Mobile App Performance
- H3 calculation: < 50ms
- Location update: < 100ms
- Map rendering: < 2s
- Background monitoring: 60s interval

## Test Data Requirements

### Crime Data
- **Records**: 3.4M+ incidents
- **Date Range**: Historical data (varies by dataset)
- **H3 Coverage**: Resolutions 4-13
- **Crime Types**: 20+ categories
- **Districts**: 5+ police districts

### Expected Results
- **API Success Rate**: 100% (22/22 tests)
- **Data Integrity**: No missing H3 indexes
- **Filter Accuracy**: 100% correct filtering
- **Performance**: Within benchmarks

## Automated Test Execution

### CI/CD Integration
```bash
# Run all automated tests
./validate_gold_layer.sh && \
./validate_filters.sh && \
cd mobile && npm test
```

### Scheduled Testing
- **Hourly**: API health checks
- **Daily**: Full filter validation
- **Weekly**: Database integrity checks
- **On-demand**: Manual frontend testing

## Test Result Formats

### JSON Output (API Tests)
```json
{
  "test_name": "Filter by Crime Type",
  "status": "PASSED",
  "response_time": "234ms",
  "records_returned": 1523,
  "endpoint": "/api/amisafe/aggregated"
}
```

### Markdown Reports
- Timestamped execution reports
- Test case results with pass/fail
- Performance metrics
- Error messages and stack traces

### Console Output
- Real-time test progress
- Color-coded results (✅ ❌)
- Summary statistics
- Failure details

## Troubleshooting

### Common Issues

**API Tests Failing**
- Check Drupal site is running
- Verify AmISafe module enabled
- Confirm database connectivity
- Check Apache/Nginx configuration

**Database Validation Errors**
- Verify MySQL service running
- Check database credentials
- Confirm H3 data populated
- Run data pipeline if needed

**Mobile Tests Failing**
- Ensure dependencies installed: `npm install --legacy-peer-deps`
- Verify Node.js version 16+
- Check API endpoint configuration
- Confirm services running

**Performance Issues**
- Check database indexes
- Verify H3 aggregations current
- Monitor system resources
- Review query optimization

## Future Enhancements

### Planned Features
- [ ] Automated mobile device testing (Appium)
- [ ] Visual regression testing (Percy/Chromatic)
- [ ] Load testing (Apache JMeter)
- [ ] Security testing (OWASP ZAP)
- [ ] Accessibility testing (Axe)
- [ ] Cross-browser testing (BrowserStack)

### Mobile Testing Expansion
- [ ] iOS simulator automation
- [ ] Android emulator automation
- [ ] Real device cloud testing
- [ ] Performance profiling
- [ ] Battery usage monitoring
- [ ] Network condition simulation

---

**Last Updated**: December 1, 2025  
**Test Infrastructure Version**: 2.0  
**Coverage**: API (100%), Database (100%), Frontend (Manual), Mobile (In Progress)
