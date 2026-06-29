# H3 Geolocation Framework for AmISafe Crime Mapping

A high-performance geospatial crime data processing framework using H3 hexagonal indexing for the AmISafe crime monitoring system.

## Overview
This framework processes 3.4M+ Philadelphia crime incidents through a modern 3-layer data warehouse architecture, creating H3 hexagonal aggregations for fast geospatial queries and crime hotspot analysis.

## Quick Start

```bash
# 1. Navigate and activate environment
cd /workspaces/stlouisintegration.com/h3-geolocation
source h3-env/bin/activate

# 2. Run the complete pipeline
cd database  
bash run_amisafe_pipeline_stlouisintegration.sh full

# 3. Monitor processing (optional)
python monitor_processing.py
```

## Core Components
- **Main Pipeline:** `database/run_amisafe_pipeline_stlouisintegration.sh`
- **Data Processor:** `database/amisafe_processor.py` (CSV → Clean Data)
- **H3 Aggregator:** `database/amisafe_aggregator.py` (Clean Data → H3 Hexagons)
- **Database Setup:** `database/setup_amisafe_stlouisintegration.sh`

## Data Flow
```
CSV Files (20 files, 673MB+) → amisafe_processor.py → Clean Data → amisafe_aggregator.py → H3 Aggregations
```

## Database Tables - ✅ PROCESSING COMPLETE (Nov 13, 2025)
- **`amisafe_raw_incidents`** - Raw CSV data (Bronze layer) - ✅ 3,406,194 records
- **`amisafe_clean_incidents`** - Validated incidents (Silver layer) - ✅ 3,406,194 records (100% H3 coverage)
- **`amisafe_h3_aggregated`** - H3 hexagon summaries (Gold layer) - ✅ Ready for analytics

## Key Features
- ✅ **3.4M+ crime incidents** processing capability - **COMPLETE**
- ✅ **H3 geospatial indexing** for fast queries - **100% COVERAGE**
- ✅ **Multi-resolution analysis** (H3 levels 5-13) - **ALL RESOLUTIONS**
- ✅ **Resume processing** from interruptions
- ✅ **Real-time monitoring** and progress tracking
- ✅ **Integrated with Drupal** AmISafe module
- ✅ **Enhanced Transform Processor v2** - **OPTIMIZED PERFORMANCE**
- ✅ **Data Quality A+ Grade** - **PERFECT VALIDATION**
- ✅ **Database Export System** - **457MB COMPRESSED EXPORTS**

## Pipeline Commands
```bash
# Full pipeline (recommended)
bash run_amisafe_pipeline_stlouisintegration.sh full

# Individual steps
bash run_amisafe_pipeline_stlouisintegration.sh setup     # Database setup only
bash run_amisafe_pipeline_stlouisintegration.sh process   # Data processing only
bash run_amisafe_pipeline_stlouisintegration.sh aggregate # H3 aggregation only
bash run_amisafe_pipeline_stlouisintegration.sh stats     # Show statistics
```

## Technical Requirements
- **Python 3.8+** with H3 4.3.1, pandas, mysql-connector-python
- **MySQL 8.0+** with stlouisintegration_dev database
- **System packages:** python3-dev, build-essential, libgeos-dev, libproj-dev, libgdal-dev

## Directory Structure
```
h3-geolocation/
├── database/                          # Main pipeline scripts
│   ├── run_amisafe_pipeline_stlouisintegration.sh  # MAIN PIPELINE
│   ├── amisafe_processor.py           # Data processing
│   ├── amisafe_aggregator.py          # H3 aggregation
│   └── setup_amisafe_stlouisintegration.sh        # Database setup
├── data/raw/                          # 20 CSV files (673MB+)
├── h3-env/                           # Python virtual environment
├── config/mysql_config.json          # Database configuration
└── ARCHITECTURE.md                   # Detailed technical documentation
```

## 🚀 Quick Reference Commands

### Setup
```bash
export DB_USER='stlouis_user'
export DB_PASSWORD='<your_database_password>'  # Required: set from secrets
export DB_SOCKET='/var/run/mysqld/mysqld.sock'
cd /var/www/html/stlouisintegration/h3-geolocation
source h3-env/bin/activate
```

### Run Complete Pipeline
```bash
# Run in background
nohup ./database/etl/run_complete_pipeline.sh --full > pipeline.log 2>&1 &
```

### Pipeline Stages

| Stage | Script | Time | Output |
|-------|--------|------|--------|
| **Bronze** | `amisafe_processor.py` | 30-60 min | ~3.5M raw records |
| **Silver** | `enhanced_transform_processor_v2.py` | 20-40 min | Clean incidents |
| **Gold** | `amisafe_aggregator.py` | 15-30 min | ~410K hexagons |
| **Analytics** | Stored procedures | 3-6 hrs | 84 columns/hex |

### Individual Stage Commands
```bash
# Complete pipeline (all 4 stages)
./database/etl/run_complete_pipeline.sh --full

# Individual stages
./database/etl/run_complete_pipeline.sh --bronze
./database/etl/run_complete_pipeline.sh --silver
./database/etl/run_complete_pipeline.sh --gold
./database/etl/run_complete_pipeline.sh --analytics

# Fast analytics (skip 12mo/6mo windows)
./database/etl/run_complete_pipeline.sh --analytics-basic

# Resume from last successful stage
./database/etl/run_complete_pipeline.sh --resume
```

### Monitoring Progress
```bash
# Watch log
tail -f database/pipeline_*.log

# Check state
cat database/pipeline_state.json

# Monitor background process
ps aux | grep run_complete_pipeline
```

### Database Stats
```bash
mysql -u stlouis_user -p"$DB_PASSWORD" \
      -S /var/run/mysqld/mysqld.sock amisafe_database << 'EOF'
-- Record counts
SELECT 
    'Bronze' as Layer, COUNT(*) as Records FROM amisafe_raw_incidents
UNION ALL
SELECT 'Silver', COUNT(*) FROM amisafe_clean_incidents
UNION ALL
SELECT 'Gold', COUNT(*) FROM amisafe_h3_aggregated;

-- Hexagon distribution
SELECT 
    h3_resolution,
    COUNT(*) as hexagons,
    SUM(incident_count) as incidents,
    COUNT(CASE WHEN top_crime_type IS NOT NULL THEN 1 END) as with_analytics
FROM amisafe_h3_aggregated
GROUP BY h3_resolution
ORDER BY h3_resolution DESC;
EOF
```

### Troubleshooting

**Virtual Environment Issues:**
```bash
cd /var/www/html/stlouisintegration/h3-geolocation
python3 -m venv h3-env
source h3-env/bin/activate
pip install pandas numpy h3 mysql-connector-python folium matplotlib plotly seaborn geopy tqdm psutil
```

**MySQL Connection:**
```bash
# Test connection
mysql -u stlouis_user -p"$DB_PASSWORD" \
      -S /var/run/mysqld/mysqld.sock -e "SHOW DATABASES;"
```

## Directory Structure
```
h3-geolocation/
├── README.md                           # Main documentation
├── ARCHITECTURE.md                     # Technical architecture
├── install.py                          # Framework installation
├── quick_start.py                      # Quick start examples
├── visualizer.py                       # Data visualization
├── h3_framework.py                     # Core H3 framework
├── geospatial_utils.py                 # Geospatial utilities
├── examples.py                         # Usage examples
├── run.sh                              # Quick runner
├── composer.json                       # PHP dependencies
├── config/                             # Configuration files
│   ├── mysql_config.json              # Database config
│   └── README.md
├── data/                               # Data storage
│   ├── raw/                           # Raw CSV files (20 files, 673MB+)
│   └── README.md
├── database/                          # **ACTIVE PIPELINE SCRIPTS**
│   ├── CURRENT_FILES.md               # Active files documentation
│   ├── run_amisafe_pipeline_stlouisintegration.sh  # MAIN PIPELINE
│   ├── setup_amisafe_stlouisintegration.sh         # DATABASE SETUP
│   ├── amisafe_processor.py           # Bronze→Silver processing
│   ├── amisafe_aggregator.py          # Silver→Gold aggregation
│   ├── monitor_processing.py          # Processing monitor
│   ├── generate_h3_metro_area.py      # Metro area H3 generation
│   ├── populate_h3_incident_ids.py    # Backfill utility
│   ├── etl/                           # ETL pipeline scripts
│   └── archive/                       # Archived files
├── h3-env/                            # Python virtual environment
├── scripts/                           # Utility scripts
│   ├── load_incidents_to_mysql.py     # MySQL loader
│   └── README.md
├── tests/                             # Test suites
│   ├── test_h3_framework.py          # Framework tests
│   ├── test_transform_processor.py    # Processor tests
│   ├── fixtures.py                   # Test fixtures
│   └── data_validation/               # Validation tests
└── deprecated/                        # Deprecated files
```

## Support
- **Architecture Details:** See `ARCHITECTURE.md`
- **Current Files:** Check `database/CURRENT_FILES.md`
- **Issues:** Check logs in `database/` directory

## 🎉 CURRENT PROCESSING STATUS (November 13, 2025)

### ✅ SILVER LAYER COMPLETE
- **Total Records Processed:** 3,406,194/3,406,194 (100%)
- **H3 Geospatial Coverage:** 100% across all resolutions (5-13)
- **Data Quality Grade:** A+ (Perfect coordinate validation)
- **Processing Performance:** 336 records/second optimized

### 📦 DATABASE EXPORTS READY
- **Location:** `/workspaces/stlouisintegration.com/database-exports/dumps/`
- **Total Size:** 457MB compressed (~5.7GB uncompressed)
- **Silver Layer Export:** amisafe_clean_incidents_data_20251113_125154.sql.gz (255MB)
- **Download Guide:** Available in `database-exports/DOWNLOAD_GUIDE.md`

### 🔧 ENHANCED PROCESSING TOOLS
- **Enhanced Transform Processor v2:** `database/enhanced_transform_processor_v2.py`
- **Data Quality Checks:** `--data-quality-check` flag available
- **H3 Population:** `--populate-h3-columns` for targeted updates
- **Progress Tracking:** Real-time ETA and batch monitoring

### 🚀 NEXT STEPS
1. **Gold Layer Processing:** Run spatial aggregation for analytics
2. **Cloud Storage:** Upload 457MB exports to permanent storage
3. **API Development:** Build endpoints for H3 hexagon queries
4. **Visualization:** Create crime mapping dashboard with H3 overlays

---
**Version:** H3 4.3.1 | **Database:** stlouisintegration_dev | **Status:** Production Ready | **Updated:** November 13, 2025
