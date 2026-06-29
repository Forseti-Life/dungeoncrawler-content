# AmISafe H3 Complete ETL Pipeline

**Last Updated:** February 6, 2026

## Overview

Complete 4-stage ETL pipeline for processing crime data with H3 geospatial indexing:

```
Bronze → Silver → Gold → Analytics
  ↓       ↓       ↓         ↓
 CSV    Clean   H3 Agg    Metrics
```

## Pipeline Stages

### 1. Bronze Layer
**Script**: `amisafe_processor.py`  
**Input**: CSV files from `data/raw/*.csv`  
**Output**: `amisafe_raw_incidents` table  
**Function**: Load raw data, add H3 indexes at all resolutions (5-13)  
**Records**: ~3.5M incidents (19 years: 2006-2024)

### 2. Silver Layer
**Script**: `enhanced_transform_processor_v2.py`  
**Input**: `amisafe_raw_incidents`  
**Output**: `amisafe_clean_incidents`  
**Function**: Validate coordinates, deduplicate, standardize crime types  
**Requires**: Bronze layer completion

### 3. Gold Layer
**Script**: `amisafe_aggregator.py`  
**Input**: `amisafe_clean_incidents`  
**Output**: `amisafe_h3_aggregated`  
**Function**: Aggregate incidents by H3 hexagon  
**Resolutions**: 13, 12, 11, 10, 9, 8, 7, 6, 5 (from finest to coarsest)  
**Requires**: Silver layer completion

### 4. Analytics Layer
**Stored Procedures**: `sp_complete_resolution_analytics()` or `sp_complete_all_windows()`  
**Input**: `amisafe_h3_aggregated`  
**Output**: 84 analytical columns per hexagon  
**Function**: Calculate crime statistics, z-scores, percentiles, risk scores  
**Requires**: Gold layer completion

#### Analytics Breakdown:
- **All-Time Metrics** (28 columns):
  - Basic: top crime type, diversity index, temporal patterns
  - Statistical: z-scores, percentiles for violent/nonviolent crimes
  - Risk: risk scores and categories
  
- **12-Month Window** (28 columns):
  - Recent trends, seasonal patterns, emerging hotspots
  
- **6-Month Window** (28 columns):
  - Current activity, immediate threats, tactical intelligence

## Quick Start

### Prerequisites
```bash
# 1. Activate virtual environment
cd /var/www/html/stlouisintegration/h3-geolocation
source h3-env/bin/activate

# 2. Set environment variables
export DB_USER='stlouis_user'
export DB_PASSWORD='<your_database_password>'  # Required: set from secrets
export DB_SOCKET='/var/run/mysqld/mysqld.sock'
```

### Run Complete Pipeline
```bash
# Run all 4 stages (Bronze → Silver → Gold → Analytics)
./database/etl/run_complete_pipeline.sh --full

# Background execution with nohup
nohup ./database/etl/run_complete_pipeline.sh --full > pipeline.log 2>&1 &
```

### Individual Stage Execution
```bash
# Run specific stage only
./database/etl/run_complete_pipeline.sh --bronze
./database/etl/run_complete_pipeline.sh --silver
./database/etl/run_complete_pipeline.sh --gold
./database/etl/run_complete_pipeline.sh --analytics

# Run basic analytics only (skip 12mo/6mo windows)
./database/etl/run_complete_pipeline.sh --analytics-basic
```

### Resume Interrupted Pipeline
```bash
# Automatically resume from last successful stage
./database/etl/run_complete_pipeline.sh --resume
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_USER` | `stlouis_user` | MySQL username |
| `DB_PASSWORD` | - | MySQL password (required) |
| `DB_SOCKET` | - | MySQL socket path (recommended) |
| `DB_HOST` | `127.0.0.1` | MySQL host (if not using socket) |
| `DB_NAME` | `amisafe_database` | Target database |
| `H3_RESOLUTIONS` | `13 12 11 10 9 8 7 6 5` | H3 resolutions to process |

### Custom Configuration Example
```bash
# Use specific resolutions only
export H3_RESOLUTIONS="13 12 11 10"

# Use TCP connection instead of socket
export DB_HOST="localhost"
unset DB_SOCKET

./database/etl/run_complete_pipeline.sh --full
```

## Pipeline State Management

The pipeline saves checkpoint state to `database/pipeline_state.json`:

```json
{
    "stage": "gold",
    "status": "completed",
    "timestamp": "2024-11-25T13:45:00-05:00",
    "last_successful": "gold"
}
```

Use `--resume` to continue from the last successful stage after interruption.

## Expected Processing Times

### Bronze Layer
- **Data**: 19 CSV files (~950MB compressed, ~3.5M records)
- **Time**: ~30-60 minutes
- **Database**: ~1.5GB data + indexes

### Silver Layer
- **Processing**: ~3.5M records
- **Time**: ~20-40 minutes
- **Operations**: Deduplication, validation, H3 indexing

### Gold Layer
- **Aggregations**: ~410K hexagons across 9 resolutions
  - Resolution 13: ~177K hexagons
  - Resolution 12: ~146K hexagons
  - Resolution 11: ~70K hexagons
  - Resolution 10: ~17K hexagons
  - Lower resolutions: <5K hexagons
- **Time**: ~15-30 minutes

### Analytics Layer

#### All-Time Analytics Only (`--analytics-basic`)
- **Time**: ~1-2 hours for all resolutions
- **Rate**: ~100-200 hexagons/minute

#### Complete Analytics (`--analytics`)
- **Time**: ~3-6 hours for all resolutions
- **Includes**: All-time + 12-month + 6-month windows
- **Rate**: ~50-100 hexagons/minute

**Recommended**: Start with `--analytics-basic` for faster initial results, then run windowed analytics separately if needed.

## Database Schema

### Bronze Layer Table
```sql
amisafe_raw_incidents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_file VARCHAR(255),
    cartodb_id INT,
    objectid INT,
    dispatch_date_time DATETIME,
    lat DECIMAL(10,8),
    lng DECIMAL(11,8),
    location_block TEXT,
    ucr_general VARCHAR(10),
    text_general_code VARCHAR(50),
    h3_5, h3_6, h3_7, h3_8, h3_9, h3_10, h3_11, h3_12, h3_13 VARCHAR(20),
    ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

### Silver Layer Table
```sql
amisafe_clean_incidents (
    -- Same structure as raw_incidents
    -- But with validated, deduplicated data
)
```

### Gold Layer Table
```sql
amisafe_h3_aggregated (
    id INT PRIMARY KEY AUTO_INCREMENT,
    h3_index VARCHAR(20),
    h3_resolution INT,
    incident_count INT,
    
    -- All-time analytics (28 columns)
    top_crime_type VARCHAR(10),
    crime_diversity_index DECIMAL(10,3),
    incidents_by_hour JSON,
    peak_hour INT,
    violent_crime_count INT,
    violent_crime_percentage DECIMAL(5,2),
    violent_zscore DECIMAL(10,4),
    violent_percentile DECIMAL(5,2),
    risk_score DECIMAL(10,4),
    risk_category VARCHAR(20),
    -- ... (18 more all-time columns)
    
    -- 12-month window (28 columns)
    top_crime_type_12mo VARCHAR(10),
    crime_diversity_index_12mo DECIMAL(10,3),
    -- ... (26 more 12mo columns)
    
    -- 6-month window (28 columns)
    top_crime_type_6mo VARCHAR(10),
    crime_diversity_index_6mo DECIMAL(10,3),
    -- ... (26 more 6mo columns)
    
    UNIQUE KEY (h3_index, h3_resolution)
)
```

## Monitoring Progress

### Check Stage Completion
```bash
# View pipeline state
cat database/pipeline_state.json

# Check log file
tail -f database/pipeline_*.log
```

### Database Statistics
```bash
# Run stats query
mysql -u stlouis_user -p\"$DB_PASSWORD\" \
      -S /var/run/mysqld/mysqld.sock amisafe_database << 'EOF'
SELECT 
    'Bronze' as Layer, COUNT(*) as Records 
FROM amisafe_raw_incidents
UNION ALL
SELECT 'Silver', COUNT(*) FROM amisafe_clean_incidents
UNION ALL
SELECT 'Gold', COUNT(*) FROM amisafe_h3_aggregated;

SELECT 
    h3_resolution,
    COUNT(*) as hexagons,
    SUM(incident_count) as total_incidents,
    COUNT(CASE WHEN top_crime_type IS NOT NULL THEN 1 END) as with_analytics
FROM amisafe_h3_aggregated
GROUP BY h3_resolution
ORDER BY h3_resolution DESC;
EOF
```

## Troubleshooting

### Issue: Virtual Environment Not Found
```bash
cd /var/www/html/stlouisintegration/h3-geolocation
python3 -m venv h3-env
source h3-env/bin/activate
pip install pandas numpy h3 mysql-connector-python folium matplotlib plotly seaborn geopy tqdm psutil
```

### Issue: MySQL Connection Failed
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test socket connection (set DB_PASSWORD environment variable first)
mysql -u stlouis_user -p\"$DB_PASSWORD\" \
      -S /var/run/mysqld/mysqld.sock amisafe_database -e \"SELECT 1;\"

# Verify socket path
mysqladmin -u stlouis_user -p\"$DB_PASSWORD\" variables | grep socket
```

### Issue: CSV Files Not Found
```bash
# Download crime data
cd data/raw
./download_crime_data.sh

# Verify files
ls -lh *.csv
```

### Issue: Out of Memory
```bash
# Process resolutions individually
export H3_RESOLUTIONS="13"
./database/etl/run_complete_pipeline.sh --gold

export H3_RESOLUTIONS="12"
./database/etl/run_complete_pipeline.sh --gold
# ... etc
```

### Issue: Analytics Taking Too Long
```bash
# Use basic analytics instead of full windowed
./database/etl/run_complete_pipeline.sh --analytics-basic

# Or process one resolution at a time
mysql -u stlouis_user -p'StLouis2024!Secure#DB' \
      -S /var/run/mysqld/mysqld.sock amisafe_database \
      -e "CALL sp_complete_resolution_analytics(13);"
```

## Files Reference

| File | Purpose |
|------|---------|
| `run_complete_pipeline.sh` | Master orchestration script |
| `amisafe_processor.py` | Bronze layer ETL |
| `enhanced_transform_processor_v2.py` | Silver layer ETL |
| `amisafe_aggregator.py` | Gold layer ETL |
| `run_analytics.py` | Alternative Python-based analytics runner |
| `stored_procedures_h3_analytics.sql` | All-time analytics procedures |
| `stored_procedures_h3_analytics_windowed.sql` | Windowed analytics procedures |

## Next Steps After Pipeline Completion

1. **Enable AmISafe Drupal Module**
   ```bash
   drush en amisafe -y
   drush cr
   ```

2. **Configure API Endpoints**
   - Navigate to Configuration → AmISafe Settings
   - Configure H3 resolution defaults
   - Test API endpoints

3. **Test Crime Map Integration**
   ```bash
   # Test API
   curl -X GET "https://stlouisintegration.com/amisafe/api/v1/crime-data?lat=39.9526&lng=-75.1652&resolution=13"
   ```

4. **Performance Optimization**
   - Add additional indexes if query performance is slow
   - Consider materialized views for frequently accessed data
   - Monitor query execution times

## Support

For issues or questions:
- Check logs in `database/pipeline_*.log`
- Review pipeline state in `database/pipeline_state.json`
- Consult individual ETL script documentation
- Check stored procedure comments in SQL files
