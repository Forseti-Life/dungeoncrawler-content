# H3 Geolocation Framework for AmISafe Crime Dashboard

**Philadelphia Crime Mapping with Ultra-Precision H3 Spatial Analysis**

## 🎯 Overview

This H3 (Hexagonal Hierarchical Spatial Index) framework powers the AmISafe crime dashboard with **ultra-fine spatial precision**. The system processes **3.4M+ incident records** across **8 resolution levels (6-13)**, delivering unprecedented analytical capabilities from city-wide insights to individual building precision.

**Current Status**: ✅ Production-ready pipeline with clean database architecture for stlouisintegration_dev integration.

## 🚀 Quick Start

### Prerequisites
- ✅ Python 3.12+ with h3-env virtual environment
- ✅ MySQL 8.0 with spatial indexing support  
- ✅ H3 Python library v4.3.1
- ✅ Drupal 11.2.5 with AmISafe module

### Environment Setup
```bash
# Navigate to H3 directory
cd /workspaces/stlouisintegration.com/h3-geolocation

# Activate Python environment  
source h3-env/bin/activate

# Run the complete pipeline
cd database
bash run_amisafe_pipeline_stlouisintegration.sh full

# Monitor processing
python monitor_processing.py
```

### Test H3 Framework
```python
import h3
from h3_framework import H3GeolocationFramework

# Initialize framework
framework = H3GeolocationFramework()

# Philadelphia coordinates (City Hall)
lat, lng = 39.9526, -75.1652

# Convert to H3 index
h3_index = framework.coords_to_h3(lat, lng, resolution=9)
print(f"Philadelphia City Hall H3: {h3_index}")
```

## 📊 Data Architecture

### Current Dataset
- **20 CSV files** (673MB+) with **3.4M+ Philadelphia crime incidents** (2015-2025)
- **Processing Target**: stlouisintegration_dev database
- **Pipeline Status**: Production-ready with full data validation

### 3-Layer Data Warehouse
```
📁 CSV Source Files (673MB)
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    🗄️ MySQL Data Warehouse                       │
│                 (stlouisintegration_dev)                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  BRONZE LAYER - Raw Data (Immutable)                           │
│  ├── amisafe_raw_incidents: 3.4M+ records                      │
│  └── Complete CSV data preservation                             │
│                              │                                  │
│                              ▼                                  │
│  SILVER LAYER - Cleaned Data (Business Rules)                  │
│  ├── amisafe_clean_incidents: ~3.4M validated records          │
│  └── H3 spatial indexing (resolutions 6-15)                    │
│                              │                                  │
│                              ▼                                  │
│  GOLD LAYER - Analytics Ready (Optimized)                      │
│  ├── amisafe_h3_aggregated: ~413K hexagons                     │
│  └── Multi-resolution spatial aggregations                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### H3 Resolution Coverage (COMPLETE DATASET)
```
Resolution │ Hex Area   │ Hexagon Count │ Total Incidents │ Coverage │ Use Case
-----------|------------|---------------|-----------------|----------|------------------
6          │ 36.1 km²   │        22     │    3,406,175    │ City     │ District analysis
7          │ 5.2 km²    │        93     │    3,406,175    │ Region   │ Neighborhood  
8          │ 0.7 km²    │       545     │    3,406,175    │ District │ Large blocks
9          │ 0.1 km²    │     3,150     │    3,406,175    │ Locality │ Street level
10         │ 15,047 m²  │    16,739     │    3,406,175    │ Block    │ Building groups
11         │ 2,150 m²   │    69,513     │    3,406,175    │ Building │ Individual buildings
12         │ 307 m²     │   145,982     │    3,406,175    │ Structure│ Building parts
13         │ 44 m²      │   177,128     │    3,406,175    │ Room     │ ULTRA-PRECISION
-----------|------------|---------------|-----------------|----------|------------------
TOTAL      │ Multi-Scale│   413,172     │  3.4M+ incidents│ Complete │ 20.1x Precision
```

### **🏗️ Production Data Architecture - COMPLETE SCHEMA**

#### Gold Layer Table Schema: `amisafe_h3_aggregated`
```sql
CREATE TABLE amisafe_h3_aggregated (
  id                        INT AUTO_INCREMENT PRIMARY KEY,
  h3_index                  VARCHAR(20) NOT NULL,           -- H3 hexagon identifier
  h3_resolution             TINYINT NOT NULL,               -- Resolution level (6-13)
  incident_count            INT DEFAULT 0,                  -- Total incidents in hexagon
  unique_incident_types     INT DEFAULT 0,                  -- Number of unique crime types
  avg_response_time_minutes DECIMAL(8,2),                   -- Average emergency response time
  total_units               INT DEFAULT 0,                  -- Police units involved
  earliest_incident         DATETIME,                       -- First recorded incident
  latest_incident           DATETIME,                       -- Most recent incident
  incidents_last_30_days    INT DEFAULT 0,                  -- Recent activity (30 days)
  incidents_last_year       INT DEFAULT 0,                  -- Annual activity tracker
  center_latitude           DECIMAL(10,8),                  -- Hexagon center coordinates
  center_longitude          DECIMAL(11,8),                  -- Hexagon center coordinates
  coverage_area_km2         DECIMAL(10,6),                  -- Geographic area covered
  incident_type_counts      JSON,                           -- Crime type breakdown {"1400": 5, "300": 12}
  district_counts           JSON,                           -- Police district breakdown {"15": 8, "12": 4}
  avg_data_quality_score    DECIMAL(3,2),                   -- Data validation score (0.00-1.00)
  total_valid_records       INT DEFAULT 0,                  -- Valid data points
  total_invalid_records     INT DEFAULT 0,                  -- Invalid/excluded data points
  last_aggregation          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  source_record_count       INT DEFAULT 0,                  -- Source incidents aggregated
  aggregation_method        VARCHAR(50) DEFAULT 'standard', -- Processing algorithm used
  
  INDEX idx_h3_index (h3_index),
  INDEX idx_h3_resolution (h3_resolution),
  INDEX idx_incident_count (incident_count),
  INDEX idx_latest_incident (latest_incident),
  INDEX idx_center_coords (center_latitude, center_longitude),
  INDEX idx_last_aggregation (last_aggregation)
);
```

#### **PRODUCTION STATISTICS**:
```sql
-- ACHIEVED PRODUCTION ARCHITECTURE:
amisafe_raw_incidents:     3,406,192 records (Bronze Layer - Raw CSV imports)
amisafe_clean_incidents:   3,406,175 records (Silver Layer - H3-indexed, validated)  
amisafe_h3_aggregated:     413,172 hexagons (Gold Layer - Multi-resolution analytics)
-- Ultra-Precision Business Intelligence: Resolution 13 = 44m² spatial detail
-- 🆕 H3:13 Granular Filtering: 177,128 hexagons with individual incident IDs
```

## 🔍 H3:13 Granular Filtering System (NEW)

### **✅ INDIVIDUAL INCIDENT ACCESS - PRODUCTION READY**
Enhanced H3:13 resolution with **room-level precision** (7m × 7m hexagons) now supports **individual incident retrieval** within each hexagon:

#### **Enhanced Database Schema**
- **`incident_ids` JSON Column**: Each H3:13 hexagon stores array of incident IDs
- **177,128 H3:13 Hexagons**: All populated with individual incident collections
- **High-Density Support**: Efficiently handles hexagons with 8,000+ incidents
- **Database Performance**: Sub-200ms queries for granular incident access

#### **New API Endpoint**
```bash
# Get individual incidents for specific H3:13 hexagon
GET /api/amisafe/hexagon/{h3_index}/incidents

# Filter by crime type
GET /api/amisafe/hexagon/{h3_index}/incidents?crime_types=600,700

# Filter by district and time period  
GET /api/amisafe/hexagon/{h3_index}/incidents?districts=15&time_periods=morning

# Limit results for performance
GET /api/amisafe/hexagon/{h3_index}/incidents?limit=500
```

#### **Enhanced Aggregation Pipeline**
- **Updated `amisafe_aggregator.py`**: Collects incident IDs during H3:13 aggregation
- **Conditional Processing**: Only stores incident IDs for resolution ≥13
- **JSON Array Storage**: Efficient storage using MySQL's `JSON_ARRAYAGG()`
- **Batch Processing**: Optimized for large-scale incident collections

#### **API Response Format**
```json
{
  "hexagon_summary": {
    "h3_index": "8d2a1341e791a7f",
    "h3_resolution": 13,
    "total_incidents": 8362,
    "returned_incidents": 500
  },
  "incidents": [
    {
      "incident_id": "2024032112345",
      "ucr_general": "600",
      "crime_description": "Theft",
      "incident_datetime": "2024-03-21 14:30:00",
      "dc_dist": "15",
      "lat": 39.952583,
      "lng": -75.165222
    }
  ]
}
```

## 🚀 Production Data Processing Pipeline

### **✅ COMPLETE DATASET PROCESSED**
**Production Dataset**: 20 CSV files totaling **680MB** with **3.4M+ Philadelphia crime incidents** (2015-2025) - **FULLY PROCESSED**
```bash
# File structure in /h3-geolocation/data/raw/
incidents_part1_part2.csv      (43MB)  # Base file
incidents_part1_part2 (1).csv  (43MB)  # Part 1 
incidents_part1_part2 (2).csv  (31MB)  # Part 2
...through...
incidents_part1_part2 (19).csv (32MB)  # Part 19
```

**CSV Data Structure** (Philadelphia Police Incidents):
```csv
the_geom,cartodb_id,objectid,dc_dist,psa,dispatch_date_time,dispatch_date,dispatch_time,hour,dc_key,location_block,ucr_general,text_general_code,point_x,point_y,lat,lng
```

### Pipeline Architecture

#### Stage 1: Raw Data Processing
**Script**: `database/amisafe_processor.py` (360 lines)
```python
class AmISafeDataProcessor:
    """Processes incident CSV files and loads them into MySQL with H3 geospatial indexing"""
    
    # Key Functions:
    # • CSV parsing and validation (20 files × ~125K records each)  
    # • Geographic coordinate normalization (lat/lng → H3 index)
    # • Data cleaning and deduplication
    # • Batch MySQL insertion with spatial indexing
```

**Input**: 20 CSV files (680MB) → **Output**: `amisafe_raw_incidents` table

#### Stage 2: H3 Spatial Aggregation  
**Script**: `database/amisafe_aggregator.py` (292 lines)
```python
class AmISafeDataAggregator:
    """Creates aggregated analytics from raw incident data"""
    
    # Key Functions:
    # • Multi-resolution H3 hexagon generation (resolutions 6-15)
    # • Crime type classification and severity scoring
    # • Temporal aggregation (hourly/daily/monthly patterns)
    # • Statistical analysis and trend calculation
```

**Input**: Raw incidents table → **Output**: `amisafe_h3_aggregated` table

#### Stage 3: Utility Loading
**Script**: `scripts/load_incidents_to_mysql.py` (300 lines)
```python
# Specialized loader for direct CSV → MySQL import
# Features:
# • Automatic column detection and mapping
# • H3 index computation during import
# • JSON properties storage for unknown columns
# • Batch processing with progress monitoring
```

### Master Pipeline Script

**Script**: `database/run_amisafe_pipeline.sh` (254 lines)
```bash
# Comprehensive pipeline orchestration with:
# • Environment validation (H3 virtual env, MySQL service)
# • Database setup and table creation
# • Multi-stage processing with error handling  
# • Progress logging and status reporting
# • Modular execution (full, setup, process, aggregate, status, stats)
```

#### Pipeline Commands

```bash
cd /workspaces/stlouisintegration.com/h3-geolocation

# Full pipeline (recommended for initial setup)
./database/run_amisafe_pipeline.sh full

# Individual stages:
./database/run_amisafe_pipeline.sh setup     # Database setup only
./database/run_amisafe_pipeline.sh process   # CSV processing only  
./database/run_amisafe_pipeline.sh aggregate # H3 aggregation only
./database/run_amisafe_pipeline.sh status    # Processing status
./database/run_amisafe_pipeline.sh stats     # Database statistics
```

### ETL Data Flow Architecture

```
📁 CSV Source Files (680MB)
├── incidents_part1_part2.csv (20 files)
│   ├── the_geom, cartodb_id, objectid
│   ├── dc_dist, psa, dispatch_date_time
│   └── lat, lng, ucr_general, etc.
│
┌─────────────────────────────────────────────────────────────────┐
│                    🗄️ MySQL Data Warehouse                       │
│                 (theoryofconspiracies_dev)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LAYER 1: RAW (BRONZE) - Immutable Source Data                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Stage 1: EXTRACT & LOAD                                 │   │
│  │ amisafe_raw_processor.py                                │   │
│  │ • CSV parsing (preserve all fields)                     │   │
│  │ • NO validation, NO deduplication                       │   │
│  │ • Batch insert with source tracking                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ amisafe_raw_incidents                                   │   │
│  │ • 2.5M+ records (ALL original CSV data)                │   │
│  │ • Source file tracking & ingestion timestamps          │   │
│  │ • Duplicate records preserved                           │   │
│  │ • Invalid coordinates preserved                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│  ═══════════════════════════════════════════════════════════   │
│                              │                                  │
│  LAYER 2: TRANSFORMED (SILVER) - Business Rules Applied        │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Stage 2: TRANSFORM                                      │   │
│  │ amisafe_transform_processor.py                          │   │
│  │ • Data quality validation                               │   │
│  │ • Deduplication (cartodb_id, objectid, dc_key)         │   │
│  │ • Coordinate validation & geocoding                     │   │
│  │ • Crime classification standardization                  │   │
│  │ • H3 spatial indexing (multi-resolution)               │   │
│  │ • Temporal normalization                                │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ amisafe_clean_incidents                                 │   │
│  │ • ~2.3M deduplicated, validated incidents              │   │
│  │ • High data quality with lineage tracking              │   │
│  │ • Multi-resolution H3 indexes (res 6-10)               │   │
│  │ • Standardized crime categories & severity              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│  ═══════════════════════════════════════════════════════════   │
│                              │                                  │
│  LAYER 3: FINAL (GOLD) - Analytics Optimized                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Stage 3: AGGREGATE & MODEL                              │   │
│  │ amisafe_analytics_processor.py                          │   │
│  │ • H3 hexagon aggregation (res 6-15)                    │   │
│  │ • Statistical calculations & patterns                   │   │
│  │ • Temporal trend analysis                               │   │
│  │ • Crime hotspot identification                          │   │
│  │ • Performance optimization for queries                  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ amisafe_h3_aggregated                                   │   │
│  │ • ~50K pre-computed hexagons                            │   │
│  │ • Sub-second query performance                          │   │
│  │ • Multi-dimensional analytics                           │   │
│  │ • Real-time dashboard ready                             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
🌐 AmISafe Drupal API (Production Ready)
├── /api/amisafe/aggregated     # Gold layer - H3 hexagon data
├── /api/amisafe/incidents      # Silver layer - clean incident queries  
├── /api/amisafe/citywide-stats # Gold layer - dashboard statistics
└── /api/amisafe/raw-incidents  # Bronze layer - audit/debug access
```

### Data Quality & Governance Pipeline

#### **Deduplication Strategy (Silver Layer)**
```sql
-- Multi-field deduplication logic:
1. Primary: cartodb_id (CartoDB unique identifier)
2. Secondary: objectid + dc_key (Philadelphia incident system)  
3. Tertiary: lat/lng + dispatch_datetime + ucr_general (spatial-temporal match)
4. Fuzzy: location_block + datetime_window + crime_type (geocoding tolerance)
```

#### **Data Quality Metrics**
- **Completeness**: Required fields present (lat/lng, datetime, crime_type)
- **Validity**: Coordinate bounds, date ranges, UCR code validation
- **Consistency**: Cross-field validation (district vs coordinates)
- **Accuracy**: Address geocoding confidence, duplicate detection
- **Timeliness**: Data freshness, processing lag monitoring

## Data Warehouse Architecture

### ETL/ELT Methodology Implementation
Following industry-standard data warehouse practices with **Raw → Transformed → Final** layers:

| **Stage** | **Layer** | **Table** | **Purpose** | **Data Quality** |
|-----------|-----------|-----------|-------------|------------------|
| **Ingestion** | Raw (Bronze) | `amisafe_raw_incidents` | Immutable source data "as-is" | None - preserve original |
| **Processing** | Transformed (Silver) | `amisafe_clean_incidents` | Cleaned, deduplicated, validated | High - business rules applied |
| **Analytics** | Final (Gold) | `amisafe_h3_aggregated` | Optimized for queries/dashboards | Highest - analytics-ready |

### Database Schema Architecture

#### Layer 1: Raw Data Storage (Bronze)
**Table**: `amisafe_raw_incidents` - **Immutable historical record**
```sql
-- Complete incident records exactly as received from CSV sources
-- NO data quality checks, NO deduplication - preserve everything
CREATE TABLE amisafe_raw_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Source tracking
    source_file VARCHAR(255) NOT NULL,     -- CSV filename for lineage
    ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Original CSV fields (preserved exactly as-is)
    the_geom TEXT,                         -- PostGIS geometry field
    cartodb_id INT,                        -- CartoDB unique identifier
    the_geom_webmercator TEXT,             -- Web Mercator projection
    objectid BIGINT,                       -- Philadelphia incident ID
    dc_dist VARCHAR(10),                   -- Police district (1-35)
    psa VARCHAR(10),                       -- Police service area
    dispatch_date_time DATETIME,           -- Original incident datetime
    dispatch_date DATE,                    -- Date component
    dispatch_time TIME,                    -- Time component
    hour TINYINT,                          -- Hour of day (0-23)
    dc_key VARCHAR(50),                    -- Dispatch/incident key
    location_block TEXT,                   -- Street address block
    ucr_general VARCHAR(10),               -- UCR crime code (100-800)
    text_general_code VARCHAR(255),        -- Crime type description
    point_x DECIMAL(15,10),                -- X coordinate
    point_y DECIMAL(15,10),                -- Y coordinate  
    lat DECIMAL(12,9),                     -- Latitude (WGS84)
    lng DECIMAL(12,9),                     -- Longitude (WGS84)
    
    -- Minimal indexing for raw layer
    INDEX idx_source_file (source_file),
    INDEX idx_ingested_at (ingested_at),
    INDEX idx_cartodb_id (cartodb_id),
    INDEX idx_objectid (objectid)
);
```

#### Layer 2: Transformed Data (Silver)
**Table**: `amisafe_clean_incidents` - **Business rules applied**
```sql
-- Cleaned, validated, deduplicated incidents ready for analysis
CREATE TABLE amisafe_clean_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Data lineage
    raw_incident_ids JSON,                 -- Reference to source raw records
    processing_batch_id VARCHAR(50),       -- Deduplication batch tracking
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Validated business fields
    incident_id VARCHAR(50) UNIQUE,        -- Master incident identifier
    cartodb_id INT,                        -- Validated CartoDB ID
    objectid BIGINT,                       -- Validated incident ID
    dc_key VARCHAR(50),                    -- Validated dispatch key
    
    -- Cleaned location data
    dc_dist VARCHAR(10) NOT NULL,          -- Validated district (1-35)
    psa VARCHAR(10),                       -- Police service area
    location_block VARCHAR(500),           -- Normalized address
    lat DECIMAL(10,7) NOT NULL,            -- Validated latitude
    lng DECIMAL(11,7) NOT NULL,            -- Validated longitude
    coordinate_quality ENUM('HIGH', 'MEDIUM', 'LOW'), -- Coordinate confidence
    
    -- Normalized temporal data
    incident_datetime DATETIME NOT NULL,   -- Standardized timestamp
    incident_date DATE NOT NULL,           -- Date component
    incident_hour TINYINT NOT NULL,        -- Hour (0-23)
    incident_month TINYINT NOT NULL,       -- Month (1-12)
    incident_year SMALLINT NOT NULL,       -- Year
    day_of_week TINYINT,                   -- Day of week (1=Monday)
    
    -- Crime classification
    ucr_general VARCHAR(10) NOT NULL,      -- Validated UCR code
    crime_category VARCHAR(50),            -- Standardized category
    crime_description VARCHAR(255),        -- Cleaned description
    severity_level TINYINT DEFAULT 3,      -- Calculated severity (1-5)
    
    -- H3 spatial indexing (multiple resolutions)
    h3_res_6 VARCHAR(16),                  -- District level (~3.2km)
    h3_res_7 VARCHAR(16),                  -- Neighborhood level (~1.2km)
    h3_res_8 VARCHAR(16),                  -- Block level (~460m)
    h3_res_9 VARCHAR(16),                  -- Street level (~174m)
    h3_res_10 VARCHAR(16),                 -- Building level (~65m)
    
    -- Quality and governance
    data_quality_score DECIMAL(3,2),       -- Overall quality (0.00-1.00)
    duplicate_group_id VARCHAR(50),        -- Deduplication group
    is_duplicate BOOLEAN DEFAULT FALSE,    -- Duplicate flag
    is_valid BOOLEAN DEFAULT TRUE,         -- Validation flag
    
    -- Optimized indexes for analytics
    UNIQUE KEY unique_incident (incident_id),
    INDEX idx_location (lat, lng),
    INDEX idx_h3_res8 (h3_res_8),
    INDEX idx_h3_res9 (h3_res_9),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_district (dc_dist),
    INDEX idx_crime_type (ucr_general),
    INDEX idx_quality (data_quality_score),
    INDEX idx_duplicates (duplicate_group_id, is_duplicate)
);
```

#### Layer 3: Analytics-Ready Data (Gold)
**Table**: `amisafe_h3_aggregated` - **Optimized for dashboards**
```sql
-- Pre-computed H3 hexagon aggregations for sub-second API responses
CREATE TABLE amisafe_h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- H3 spatial identifier
    h3_index VARCHAR(16) NOT NULL,         -- H3 hexagon identifier
    h3_resolution TINYINT NOT NULL,        -- Resolution level (6-15)
    
    -- Aggregated metrics
    incident_count INT DEFAULT 0,          -- Total incidents in hexagon
    unique_incidents INT DEFAULT 0,        -- Deduplicated count
    severity_avg DECIMAL(4,2),             -- Average severity (1.00-5.00)
    severity_max TINYINT,                  -- Maximum severity in hex
    data_quality_avg DECIMAL(3,2),         -- Average data quality
    
    -- Crime analysis
    crime_types JSON,                      -- Array of UCR codes with counts
    crime_categories JSON,                 -- Category distribution
    top_crime_type VARCHAR(10),            -- Most frequent UCR code
    crime_diversity_index DECIMAL(3,2),    -- Simpson's diversity index
    
    -- Temporal patterns
    incidents_by_hour JSON,                -- Hourly distribution [24 values]
    incidents_by_dow JSON,                 -- Day of week [7 values]
    incidents_by_month JSON,               -- Monthly distribution [12 values]
    peak_hour TINYINT,                     -- Hour with most incidents
    peak_dow TINYINT,                      -- Day with most incidents
    
    -- Geographic context
    district_list JSON,                    -- Police districts in hexagon
    center_lat DECIMAL(10,7),              -- Hexagon center latitude
    center_lng DECIMAL(11,7),              -- Hexagon center longitude
    boundary_geojson JSON,                 -- Hexagon boundary coordinates
    
    -- Date range coverage
    date_range_start DATE,                 -- Earliest incident date
    date_range_end DATE,                   -- Latest incident date
    data_freshness_days INT,               -- Days since last incident
    
    -- Metadata
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aggregation_batch_id VARCHAR(50),      -- Processing batch reference
    
    -- Performance indexes
    UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
    INDEX idx_resolution (h3_resolution),
    INDEX idx_incident_count (incident_count),
    INDEX idx_severity (severity_avg),
    INDEX idx_center (center_lat, center_lng),
    INDEX idx_freshness (data_freshness_days),
    INDEX idx_updated (last_updated)
);
```

### Pipeline Configuration

#### Environment Requirements (✅ Ready)
- **Python Environment**: `/h3-geolocation/h3-env/` virtual environment
- **H3 Library**: v4.1.0+ for hexagonal spatial indexing  
- **MySQL**: 8.0+ with spatial indexing support
- **Dependencies**: pandas, numpy, mysql-connector-python, h3, tqdm

#### Database Connection
```json
# config/mysql_config.json (update for production)
{
  "host": "127.0.0.1",
  "port": 3306,
  "user": "drupal_user", 
  "password": "drupal_secure_password",
  "database": "theoryofconspiracies_dev",  # Use existing Drupal database
  "charset": "utf8mb4"
}
```

### Pipeline Performance Estimates

#### Processing Capacity
- **CSV Parsing**: ~6,000 records/second
- **H3 Index Generation**: ~10,000 coordinates/second  
- **MySQL Batch Insert**: ~5,000 records/second
- **Total Processing Time**: ~8-12 minutes for full 2.5M dataset

#### Expected Results (After Full Pipeline)
```sql
-- Database size after processing:
amisafe_raw_incidents:     2,500,000+ records (~500MB)
amisafe_h3_aggregated:     ~50,000 hexagons (~25MB)

-- Coverage by resolution:
Resolution 6-8:   ~1,000 hexagons    (districts/neighborhoods)
Resolution 9-11:  ~15,000 hexagons   (blocks/buildings)  
Resolution 12-15: ~35,000 hexagons   (sub-building precision)
```

### Pipeline Status & Troubleshooting

#### Current Issue: Virtual Environment Path
```bash
# Pipeline log shows:
[ERROR] H3 virtual environment not found at .../h3-env
```

#### Resolution Steps:
1. **Fix H3 Environment Path** 
   ```bash
   cd /workspaces/stlouisintegration.com/h3-geolocation
   # Verify environment exists
   ls -la h3-env/bin/activate
   # Update pipeline script if path differs
   ```

2. **Update Database Configuration**
   ```bash
   # Update config/mysql_config.json with current credentials
   vim config/mysql_config.json
   ```

3. **Execute Full Pipeline**
   ```bash
   # Run complete processing pipeline  
   ./database/run_amisafe_pipeline.sh full
   ```

#### Expected Pipeline Output:
```bash
[SUCCESS] Virtual environment activated
[SUCCESS] MySQL is running and accessible  
[SUCCESS] Found 20 CSV files to process
[INFO] Processing incidents_part1_part2.csv (125,443 records)
[INFO] Processing incidents_part1_part2 (1).csv (118,567 records)
...
[SUCCESS] Data processing completed successfully
[INFO] Creating H3 aggregations at multiple resolutions...
[SUCCESS] Data aggregation completed successfully
[SUCCESS] Full pipeline completed successfully!

Database Statistics:
Raw incidents: 2,547,891 records
H3 hexagons: 52,341 hexagons across 10 resolution levels
Processing time: 11m 23s
```

### H3 Processing Pipeline Commands

#### 1. Environment Setup
```bash
cd /workspaces/stlouisintegration.com/h3-geolocation
source h3-env/bin/activate  # Activate Python environment
```

#### 2. Database Configuration
```bash
# Update config/mysql_config.json with correct credentials:
{
  "host": "127.0.0.1",
  "port": 3306,
  "user": "drupal_user", 
  "password": "drupal_secure_password",
  "database": "theoryofconspiracies_dev",  # Use existing Drupal database
  "charset": "utf8mb4"
}
```

#### 3. Full Pipeline Processing
```bash
# Process all 20 CSV files through H3 pipeline
./database/run_amisafe_pipeline.sh

# Expected output:
# - Load 2,500,000+ raw incidents
# - Generate H3 indexes for each incident
# - Create multi-resolution aggregations
# - Build spatial hierarchy tables
# - Generate performance indexes
```

## Framework Components

### Core Python Modules
- **`h3_framework.py`** (400+ lines) - Main H3 operations and spatial analysis
- **`geospatial_utils.py`** - Coordinate transformations and distance calculations
- **`data_processor.py`** - CSV import, validation, and H3 indexing
- **`visualizer.py`** - Folium-based mapping and visualization
- **`large_dataset_processor.py`** - Optimized processing for 2.5M+ records

### Database Integration
- **`amisafe_processor.py`** - Individual incident processing with H3 indexing
- **`amisafe_aggregator.py`** - Multi-resolution spatial aggregation
- **`load_incidents_to_mysql.py`** - Database loading with spatial optimization

### Visualization & Analysis
- **Interactive Maps**: Folium + H3 hexagon overlays
- **Heatmaps**: Crime density visualization at multiple resolutions
- **3D Mapping**: Plotly-based 3D crime visualization
- **Statistical Analysis**: Temporal patterns and trend analysis

## Installation & Setup

### Prerequisites
- ✅ Python 3.12+ with h3-env virtual environment (complete)
- ✅ MySQL 8.0 with spatial indexing support (available)  
- ✅ H3 Python library v4.3.1 (installed)
- ✅ Drupal 11.2.5 with AmISafe module (ready)

### Environment Activation
```bash
cd /workspaces/stlouisintegration.com/h3-geolocation
source h3-env/bin/activate
```

### Quick H3 Framework Test
```python
import h3
from h3_framework import H3GeolocationFramework

# Initialize framework
framework = H3GeolocationFramework()

# Philadelphia coordinates (City Hall)
lat, lng = 39.9526, -75.1652

# Convert to H3 index
h3_index = framework.coords_to_h3(lat, lng, resolution=9)
print(f"Philadelphia City Hall H3: {h3_index}")

# Get hexagon boundary
boundary = framework.get_hexagon_boundary(h3_index)
print(f"Hexagon has {len(boundary)} boundary points")

# Create visualization
map_viz = framework.visualize_hexagons([h3_index], center=[lat, lng])
map_viz.save('/tmp/philadelphia_h3_test.html')
```

## Data Processing Instructions

### Step 1: Database Configuration
```bash
# Update MySQL connection in config/mysql_config.json
vim /workspaces/stlouisintegration.com/h3-geolocation/config/mysql_config.json

# Set to use existing Drupal database:
{
  "host": "127.0.0.1",
  "port": 3306, 
  "user": "drupal_user",
  "password": "drupal_secure_password",
  "database": "theoryofconspiracies_dev",
  "charset": "utf8mb4"
}
```

### Step 2: Run Full Data Pipeline
```bash
cd /workspaces/stlouisintegration.com/h3-geolocation
source h3-env/bin/activate

# Process all 20 CSV files
./database/run_amisafe_pipeline.sh

# Monitor progress:
# - Should process ~125K incidents per file
# - Generate H3 indexes at resolutions 6-15  
# - Create aggregated statistics
# - Build spatial relationship tables
```

### Step 3: Verify Data Loading
```sql
-- Check raw incidents (should be 2.5M+)
SELECT COUNT(*) FROM amisafe_raw_incidents;

-- Check H3 aggregation coverage
SELECT h3_resolution, COUNT(*) as hex_count, SUM(crime_count) as total_crimes
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;

-- Verify geographic distribution
SELECT dc_dist, COUNT(*) as incidents 
FROM amisafe_raw_incidents 
GROUP BY dc_dist 
ORDER BY incidents DESC;
```

### Step 4: API Integration Test
```bash
# Test Drupal API endpoints after data loading
curl http://localhost:8080/api/amisafe/citywide-stats | jq .
curl http://localhost:8080/api/amisafe/aggregated?resolution=8 | jq .
```

## H3 Spatial Resolution Guide

### Philadelphia Coverage Estimates
| Resolution | Edge Length | Hexagons in Philly | Use Case |
|------------|-------------|-------------------|----------|
| 6          | ~3.2 km     | ~15 hexagons     | Districts |
| 7          | ~1.2 km     | ~105 hexagons    | Neighborhoods |  
| 8          | ~460 m      | ~735 hexagons    | City blocks |
| 9          | ~174 m      | ~5,145 hexagons  | Street segments |
| 10         | ~66 m       | ~36,015 hexagons | Buildings |
| 11         | ~25 m       | ~252,105 hexagons| Individual structures |
| 12         | ~9 m        | ~1.76M hexagons  | Building parts |
| 13         | ~3.4 m      | ~12.3M hexagons  | Rooms/parking |
| 14         | ~1.3 m      | ~86.1M hexagons  | Sub-building |
| 15         | ~0.5 m      | ~602.7M hexagons | Sub-meter precision |

### Multi-Resolution Strategy
```python
def get_optimal_resolution(zoom_level):
    """Map Leaflet zoom levels to H3 resolutions for optimal performance"""
    if zoom_level <= 10: return 7    # Neighborhood view
    if zoom_level <= 12: return 8    # Block view  
    if zoom_level <= 14: return 9    # Street view
    if zoom_level <= 16: return 10   # Building view
    if zoom_level <= 18: return 11   # Structure view
    return 12  # Maximum detail for performance
```

## API Integration

### REST Endpoints (Drupal Integration)
- **`/api/amisafe/aggregated`** - H3 hexagon data for mapping
- **`/api/amisafe/incidents`** - Raw incident data with filtering  
- **`/api/amisafe/hexagon/{h3_index}/incidents`** - **🆕 Granular incident access for H3:13 hexagons**
  - **Purpose**: Individual incident retrieval within room-level precision hexagons
  - **Filters**: `crime_types`, `districts`, `time_periods`, `limit`
  - **Performance**: Sub-200ms response for 8,000+ incident hexagons
- **`/api/amisafe/citywide-stats`** - Dashboard statistics
- **`/api/amisafe/districts`** - Police district boundaries
- **`/api/amisafe/crime-types`** - Crime category taxonomy

### Data Format Example
```json
{
  "hexagons": [
    {
      "h3_index": "892aacb2e57ffff",
      "crime_count": 25,
      "severity_avg": 3.2,
      "center_lat": 39.9526,
      "center_lng": -75.1652,
      "crime_types": ["400", "300", "200"],
      "last_incident": "2025-10-30 14:30:00",
      "boundary_json": {...}
    }
  ],
  "meta": {
    "resolution": 8,
    "total_hexagons": 8, 
    "total_crimes": 205
  }
}
```

## Performance & Optimization

### Current Performance (Sample Data)
- **Database Size**: 20 hexagons, 325 aggregated crimes
- **Query Speed**: <50ms for viewport queries
- **Memory Usage**: <10MB for current dataset
- **Cache Hit Rate**: 18.2% (frontend), improving with usage

### Expected Performance (Full Dataset)
- **Database Size**: ~50,000 hexagons, 2.5M+ raw incidents
- **Storage**: ~500MB database size
- **Query Speed**: <100ms for complex spatial queries
- **Memory**: <50MB for extended mapping sessions
- **Throughput**: 1,000+ API requests/minute

### Optimization Features
- **Spatial Indexes**: H3 index, lat/lng, composite indexes
- **Multi-level Caching**: Frontend JS cache + Drupal API cache
- **Lazy Loading**: Load hexagons only for current viewport
- **Resolution Optimization**: Automatic resolution switching by zoom level
- **Preloading**: Adjacent hexagons for smooth navigation

## Next Steps for Data Pipeline Completion

### Immediate Tasks
1. **Fix H3 Environment**: Resolve virtual environment path issues
2. **Update Database Config**: Point to existing Drupal database
3. **Run Full Pipeline**: Process all 20 CSV files (2.5M+ records)
4. **Verify H3 Aggregation**: Ensure multi-resolution hexagon coverage
5. **Test API Endpoints**: Validate data consistency between raw and aggregated

### Expected Results After Pipeline
- **Raw Incidents**: 2,500,000+ records across all Philadelphia districts
- **H3 Coverage**: ~50,000 hexagons at resolutions 6-15
- **Citywide Stats**: Accurate incident counts matching aggregated data
- **API Performance**: Sub-second response times for all endpoints
- **Dashboard Ready**: Full Philadelphia 2085 crime map functionality

## Documentation References

### Related Documentation Files
- **[DRUPAL_IMPLEMENTATION_GUIDE.md](DRUPAL_IMPLEMENTATION_GUIDE.md)** - Step-by-step Drupal module integration
- **[FRONTEND_SPECIFICATION.md](FRONTEND_SPECIFICATION.md)** - UI/UX specifications and tech stack

### AmISafe Module Integration
- **[AmISafe Module README](../sites/theoryofconspiracies/web/modules/custom/amisafe/README.md)** - Drupal module documentation
- **[AmISafe Database Architecture](../sites/theoryofconspiracies/web/modules/custom/amisafe/DATABASE_ARCHITECTURE.md)** - Database integration details
- **[AmISafe Performance Guide](../sites/theoryofconspiracies/web/modules/custom/amisafe/PERFORMANCE_OPTIMIZATION.md)** - Caching and optimization

## Support & Development

This H3 framework is specifically designed for the AmISafe Philadelphia 2085 crime dashboard. For technical support, integration questions, or pipeline issues, refer to the AmISafe module documentation and ensure the full H3 data processing pipeline is completed before frontend integration.

**Priority**: Complete the H3 data pipeline to process all 2.5M incidents before focusing on API or frontend development.