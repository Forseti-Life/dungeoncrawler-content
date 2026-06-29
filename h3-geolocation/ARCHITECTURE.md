# H3 Geolocation Framework - Architecture Documentation

**Last Updated:** February 6, 2026

## System Architecture Overview

The H3 Geolocation Framework implements a modern 3-layer data warehouse architecture optimized for geospatial crime data analysis.

## Data Warehouse Architecture

### Layer Structure
```
📊 3-Layer Data Warehouse (stlouisintegration_dev)

┌─────────────────────────────────────────────────────────┐
│                    GOLD LAYER                           │
│              (Analytics-Ready Data)                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │     amisafe_h3_aggregated                       │   │
│  │  • H3 hexagon summaries                         │   │
│  │  • Multi-resolution aggregations (8-13)         │   │
│  │  • Incident counts per hexagon                   │   │
│  │  • Statistical rankings                          │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                           ↑
                    [amisafe_aggregator.py]
                           ↑
┌─────────────────────────────────────────────────────────┐
│                   SILVER LAYER                          │
│               (Business-Ready Data)                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │     amisafe_clean_incidents                     │   │
│  │  • Validated incident records                   │   │
│  │  • Standardized coordinates                     │   │
│  │  • H3 spatial indexing                          │   │
│  │  • Data quality checks passed                   │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                           ↑
                    [amisafe_processor.py]
                           ↑
┌─────────────────────────────────────────────────────────┐
│                   BRONZE LAYER                          │
│                (Immutable Raw Data)                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │     amisafe_raw_incidents                       │   │
│  │  • Complete CSV preservation                    │   │
│  │  • Source file tracking                         │   │
│  │  • Ingestion timestamps                         │   │
│  │  • No data transformation                       │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                           ↑
                    [CSV Source Files]
                    20 files, 673MB+, 3.4M+ records
```

## Database Schema

### Bronze Layer: `amisafe_raw_incidents`
```sql
CREATE TABLE amisafe_raw_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_file VARCHAR(255) NOT NULL,
    cartodb_id INT,
    objectid BIGINT,
    dc_dist VARCHAR(10),
    dispatch_date_time DATETIME,
    lat DECIMAL(10,7),
    lng DECIMAL(11,7),
    location_block TEXT,
    ucr_general VARCHAR(10),
    text_general_code VARCHAR(255),
    ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_coordinates (lat, lng),
    INDEX idx_district (dc_dist),
    INDEX idx_crime_type (ucr_general)
);
```

### Silver Layer: `amisafe_clean_incidents`
```sql
CREATE TABLE amisafe_clean_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    incident_id VARCHAR(50) UNIQUE,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(11,7) NOT NULL,
    incident_datetime DATETIME NOT NULL,
    ucr_general VARCHAR(10) NOT NULL,
    dc_dist VARCHAR(10),
    h3_index_8 VARCHAR(16),
    h3_index_9 VARCHAR(16),
    h3_index_10 VARCHAR(16),
    h3_index_11 VARCHAR(16),
    h3_index_12 VARCHAR(16),
    h3_index_13 VARCHAR(16),
    severity_level TINYINT DEFAULT 3,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_location (lat, lng),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_crime_type (ucr_general),
    INDEX idx_h3_8 (h3_index_8),
    INDEX idx_h3_9 (h3_index_9),
    INDEX idx_h3_10 (h3_index_10)
);
```

### Gold Layer: `amisafe_h3_aggregated`
```sql
CREATE TABLE amisafe_h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    incident_count INT DEFAULT 0,
    center_lat DECIMAL(10,7),
    center_lng DECIMAL(11,7),
    hotspot_rank INT,
    crime_density DECIMAL(8,4),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
    INDEX idx_resolution (h3_resolution),
    INDEX idx_incident_count (incident_count),
    INDEX idx_hotspot_rank (hotspot_rank)
);
```

## H3 Spatial Indexing Strategy

### Resolution Coverage
```
Resolution │ Hex Area    │ Use Case           │ Performance Notes
-----------|-------------|--------------------|-----------------
8          │ 0.7 km²     │ District analysis  │ Fast queries
9          │ 0.1 km²     │ Street level       │ Balanced
10         │ 15,047 m²   │ Block level        │ Detailed
11         │ 2,150 m²    │ Building groups    │ High precision
12         │ 307 m²      │ Building parts     │ Ultra-detailed
13         │ 44 m²       │ Room-level         │ Maximum precision
```

### Philadelphia Metro Bounds
```python
PHILLY_METRO_BOUNDS = {
    'north': 40.1379,    # Bucks County
    'south': 39.8670,    # Delaware County  
    'east': -74.9557,    # New Jersey border
    'west': -75.2803     # Montgomery County
}
```

## Processing Pipeline Architecture

### Stage 1: Data Ingestion (`amisafe_processor.py`)
- **Input:** 20 CSV files (673MB+)
- **Process:** Data validation, coordinate cleaning, deduplication
- **Output:** Clean incidents in Silver layer
- **Performance:** ~100K records/minute with validation

### Stage 2: H3 Aggregation (`amisafe_aggregator.py`)
- **Input:** Clean incidents from Silver layer
- **Process:** Multi-resolution H3 indexing, statistical analysis
- **Output:** Hexagon summaries in Gold layer
- **Performance:** ~50K hexagons/minute across all resolutions

### Stage 3: Analytics Optimization
- **Hotspot ranking** based on incident density
- **Temporal aggregations** for trend analysis
- **Spatial clustering** for crime pattern detection
- **Performance indexing** for fast query response

## Performance Characteristics

### Processing Metrics
- **Total Records:** 3.4M+ Philadelphia crime incidents
- **Processing Time:** ~45 minutes for full pipeline
- **Memory Usage:** Peak 2GB during aggregation
- **Disk Usage:** ~1.2GB for complete dataset

### Query Performance
- **Single hexagon lookup:** <1ms
- **Spatial range queries:** <50ms for 1km radius
- **Aggregation queries:** <200ms for city-wide statistics
- **Hotspot analysis:** <500ms for top 100 locations

## Integration Points

### Drupal AmISafe Module
- **API Endpoints:** H3 hexagon data via REST
- **Caching:** Redis integration for fast response
- **Authentication:** Drupal user permissions
- **Data Export:** JSON/GeoJSON formats

### Development Environment
- **Database:** stlouisintegration_dev (MySQL 8.0)
- **Python Environment:** h3-env with H3 4.3.1
- **Monitoring:** Real-time processing logs
- **Backup:** Automated daily snapshots

## Security Considerations

### Data Protection
- **Raw data preservation** in Bronze layer (immutable)
- **Access control** via database user permissions
- **Audit logging** for all data modifications
- **Backup encryption** for sensitive location data

### API Security  
- **Rate limiting** on H3 data endpoints
- **Input validation** for all spatial queries
- **SQL injection protection** via parameterized queries
- **Geographic bounds checking** to prevent data leakage

---
**Architecture Version:** 1.0 | **H3 Framework:** 4.3.1 | **Database:** stlouisintegration_dev