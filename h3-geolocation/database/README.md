# AmISafe Database - H3 Geospatial Analytics

**Last Updated:** February 6, 2026

Complete ETL pipeline and analytics system for St. Louis crime data with H3 hexagonal spatial indexing.

## Quick Start

### 1. Setup Database (Tables + Stored Procedures)

```bash
cd /home/keithaumiller/stlouisintegration.com/h3-geolocation/database
./setup/setup_amisafe_complete.sh
```

This creates:
- 3-layer ETL architecture (Bronze → Silver → Gold)
- 4 tables with 3.4M record capacity
- 21 stored procedures for analytics
- UCR crime code reference data

### 2. Run Full Pipeline (Aggregation + Analytics)

Process all resolutions (creates hexagons + populates 84 analytical columns):
```bash
cd database
source ../h3-env/bin/activate
python etl/amisafe_aggregator.py --resolutions 13 12 11 10 9 8 7 6 5
```

Or for just one resolution:
```bash
python etl/amisafe_aggregator.py --resolutions 13
```

Check status:
```bash
python etl/run_analytics.py --status --resolutions 13
```

**What it does:**
1. Creates H3 hexagons with basic metrics (Silver → Gold)
2. Automatically calls `run_analytics.py` to populate all 84 analytical columns

**Estimated Time**: 10-15 hours for all 413,182 hexagons across 9 resolutions

**Options:**
- `--skip-analytics` - Only create basic hexagons (skip analytics step)
- Run analytics separately: `python etl/run_analytics.py --resolutions 13`

---

## Database Architecture

### 3-Layer ETL Pipeline

```
Bronze (Raw) → Silver (Clean) → Gold (Aggregated)
3.4M records  → 3.4M records  → 413K hexagons
```

#### Layer 1: Bronze (Raw) - `amisafe_raw_incidents`
- **Purpose**: Immutable source data preservation
- **Key**: `objectid` (100% coverage vs 75% for CartoDB ID)
- **Capacity**: 3.4M incidents
- **Fields**: All original CSV columns preserved as-is

#### Layer 2: Silver (Clean) - `amisafe_clean_incidents`
- **Purpose**: Validated, normalized, H3-indexed incidents
- **Key**: `incident_id` (format: `obj_{objectid}`)
- **H3 Indexing**: 9 resolutions (5-13) for multi-scale analysis
- **Validated**: Coordinates, dates, UCR codes, districts

#### Layer 3: Gold (Aggregated) - `amisafe_h3_aggregated`
- **Purpose**: Pre-aggregated analytics by hexagon
- **Hexagons**: 413,182 across resolutions 5-13
- **Columns**: 84 analytical fields (all-time + 12mo + 6mo windows)
- **Analytics**: Crime counts, patterns, statistics, risk scores

### Reference Data - `amisafe_ucr_codes`
- 15 UCR crime codes with categories, severity levels, colors
- Standardized crime classification

---

## Stored Procedures System

### Overview

**21 Stored Procedures** covering all 84 analytical columns:
- **11 All-Time Analytics** procedures
- **10 Windowed Analytics** procedures (12-month + 6-month)

### Master Procedure

**`sp_complete_all_windows(resolution)`** - Complete analytics pipeline:

```sql
CALL sp_complete_all_windows(13);  -- Process Resolution 13
```

**What it does:**
1. All-time analytics (basic + statistical + risk)
2. 12-month windowed analytics (basic + statistical + risk)
3. 6-month windowed analytics (basic + statistical + risk)

**Result**: All 84 analytical columns populated for the resolution

### All-Time Analytics Procedures (11)

| Procedure | Populates | Description |
|-----------|-----------|-------------|
| `sp_calculate_top_crime_type` | `top_crime_type` | Most frequent UCR code |
| `sp_calculate_crime_diversity` | `crime_diversity_index` | Shannon diversity index |
| `sp_calculate_temporal_patterns` | `incidents_by_hour`, `incidents_by_dow`, `incidents_by_month`, `peak_hour`, `peak_dow` | Temporal distribution patterns (JSON arrays) |
| `sp_calculate_violent_stats` | `violent_crime_count`, `nonviolent_crime_count`, `violent_crime_percentage` | Violent (UCR 100-400) vs non-violent breakdown |
| `sp_calculate_crime_district_counts` | `incident_type_counts`, `district_counts` | JSON aggregations by crime type and district |
| `sp_calculate_date_freshness` | `date_range_start`, `date_range_end`, `data_freshness_days` | Date ranges and freshness metrics |
| `sp_calculate_statistical_metrics` | `*_z_score`, `*_percentile`, `*_mean`, `*_std_dev` | Population statistics (z-scores, percentiles) |
| `sp_calculate_risk_scores` | `risk_score`, `risk_category`, `hotspot_status` | Risk assessment and hotspot detection |
| `sp_update_hex_analytics` | All basic fields | Master procedure for single hexagon (all-time) |
| `sp_update_resolution_analytics` | All hexagons | Batch processor for resolution |
| `sp_complete_resolution_analytics` | All fields | Complete pipeline (basic + statistical + risk) |

### Windowed Analytics Procedures (10)

All procedures accept `p_months` parameter (NULL=all-time, 12, 6):

| Procedure | Populates | Windows |
|-----------|-----------|---------|
| `sp_calculate_top_crime_type_windowed` | `top_crime_type_12mo`, `top_crime_type_6mo` | 12mo, 6mo |
| `sp_calculate_crime_diversity_windowed` | `crime_diversity_index_12mo`, `crime_diversity_index_6mo` | 12mo, 6mo |
| `sp_calculate_temporal_patterns_windowed` | `incidents_by_hour_12mo`, `peak_hour_12mo`, etc. | 12mo, 6mo |
| `sp_calculate_violent_stats_windowed` | `violent_crime_count_12mo`, `violent_crime_percentage_12mo`, etc. | 12mo, 6mo |
| `sp_calculate_unique_types_windowed` | `unique_incident_types_12mo`, `unique_incident_types_6mo` | 12mo, 6mo |
| `sp_calculate_statistical_metrics_windowed` | `*_z_score_12mo`, `*_percentile_12mo`, etc. | 12mo, 6mo |
| `sp_calculate_risk_scores_windowed` | `risk_score_12mo`, `risk_category_12mo`, `hotspot_status_12mo`, etc. | 12mo, 6mo |
| `sp_update_hex_analytics_windowed` | All windowed basic fields | Single hexagon windowed analytics |
| `sp_update_resolution_analytics_windowed` | All hexagons windowed | Batch windowed processor |
| `sp_complete_all_windows` | Everything | Master orchestrator (all-time + 12mo + 6mo) |

---

## Analytical Columns Reference

### All 84 Analytical Columns

Each hexagon has 84 analytical fields across three time windows:

#### Crime Analysis (9 fields)
- `top_crime_type` / `_12mo` / `_6mo` - Most frequent UCR code
- `crime_diversity_index` / `_12mo` / `_6mo` - Shannon diversity
- `unique_incident_types` / `_12mo` / `_6mo` - Distinct crime types

#### Violent vs Non-Violent (18 fields)
- `violent_crime_count` / `_12mo` / `_6mo` - UCR 100-400
- `nonviolent_crime_count` / `_12mo` / `_6mo` - Other UCR codes
- `violent_crime_percentage` / `_12mo` / `_6mo` - % violent
- `nonviolent_crime_percentage` / `_12mo` / `_6mo` - % non-violent

#### Temporal Patterns (15 fields)
- `incidents_by_hour` / `_12mo` / `_6mo` - JSON [24 values]
- `incidents_by_dow` / `_12mo` / `_6mo` - JSON [7 values]
- `incidents_by_month` / `_12mo` / `_6mo` - JSON [12 or 6 values]
- `peak_hour` / `_12mo` / `_6mo` - Hour with most incidents
- `peak_dow` / `_12mo` / `_6mo` - Day with most incidents

#### Statistical Metrics (27 fields)
- `violent_crime_z_score` / `_12mo` / `_6mo` - Standard deviations from mean
- `nonviolent_crime_z_score` / `_12mo` / `_6mo` - Standard deviations
- `incident_z_score` / `_12mo` / `_6mo` - Standard deviations
- `violent_crime_percentile` / `_12mo` / `_6mo` - Rank percentile (0-100)
- `nonviolent_crime_percentile` / `_12mo` / `_6mo` - Rank percentile
- `incident_percentile` / `_12mo` / `_6mo` - Rank percentile
- `violent_crime_mean` / `_12mo` / `_6mo` - Population mean
- `violent_crime_std_dev` / `_12mo` / `_6mo` - Population std dev
- `nonviolent_crime_mean` / `_12mo` / `_6mo` - Population mean

#### Risk Assessment (9 fields)
- `risk_score` / `_12mo` / `_6mo` - Weighted composite score
- `risk_category` / `_12mo` / `_6mo` - LOW/MODERATE/HIGH/CRITICAL
- `hotspot_status` / `_12mo` / `_6mo` - COLD/WARM/HOT/EXTREME

#### Distributions (6 fields, all-time only)
- `incident_type_counts` - JSON {ucr_code: count}
- `district_counts` - JSON {district: count}
- `date_range_start` - Earliest incident date
- `date_range_end` - Latest incident date
- `data_freshness_days` - Days since last incident

---

## Scripts Reference

### Setup Scripts

| Script | Purpose |
|--------|---------|
| `setup_amisafe_complete.sh` | **Primary setup** - Creates all tables + stored procedures |
| `setup_amisafe_stlouisintegration.sh` | Legacy setup (tables only) |
| `setup_stored_procedures.sh` | Standalone stored procedure installer |

**Use `setup_amisafe_complete.sh`** - it's the unified script that does everything.

### Data Processing Scripts

| Script | Purpose |
|--------|---------|
| `enhanced_transform_processor_v2.py` | Bronze → Silver ETL (clean, validate, H3 index) |
| `amisafe_aggregator.py` | Silver → Gold aggregation (create hexagons) |
| `statistical_calculator.py` | Deprecated (replaced by stored procedures) |
| `fast_aggregator.py` | Deprecated (replaced by amisafe_aggregator.py) |

### SQL Files

| File | Purpose |
|------|---------|
| `stored_procedures_h3_analytics.sql` | 11 all-time analytics procedures |
| `stored_procedures_h3_analytics_windowed.sql` | 10 windowed analytics procedures |

---

## Performance Expectations

### By Resolution

| Resolution | Hexagons | Avg Incidents/Hex | Estimated Time |
|------------|----------|-------------------|----------------|
| 13 | 177,000 | <100 | 4-6 hours |
| 12 | 84,000 | 100-500 | 2-3 hours |
| 11 | 48,000 | 500-2K | 1-2 hours |
| 10 | 28,000 | 2K-5K | 1-2 hours |
| 9 | 16,000 | 5K-20K | 30-60 min |
| 8 | 8,500 | 20K-80K | 30-60 min |
| 7 | 4,200 | 80K-300K | 15-30 min |
| 6 | 1,800 | 300K-1M | 10-20 min |
| 5 | 5 | 584K | 5-10 min |

**Total**: ~10-15 hours for all 413,182 hexagons

### Optimization Notes

- **Start with Resolution 13** (highest, fastest) and work down to Resolution 5
- Resolutions 5-9 may need chunked processing for large hexagons
- SQL stored procedures are 10-100x faster than Python-based analytics
- All H3 resolution columns (h3_res_5 through h3_res_13) are indexed

---

## Common Operations

### Check Analytics Status

```sql
-- See which resolutions have analytics
SELECT 
    h3_resolution, 
    COUNT(*) as total_hexes,
    SUM(CASE WHEN top_crime_type IS NOT NULL THEN 1 ELSE 0 END) as has_alltime,
    SUM(CASE WHEN top_crime_type_12mo IS NOT NULL THEN 1 ELSE 0 END) as has_12mo,
    SUM(CASE WHEN top_crime_type_6mo IS NOT NULL THEN 1 ELSE 0 END) as has_6mo,
    SUM(CASE WHEN risk_category IS NOT NULL THEN 1 ELSE 0 END) as has_risk
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;
```

### Verify Stored Procedures

```sql
-- List all installed procedures
SELECT ROUTINE_NAME, ROUTINE_TYPE 
FROM information_schema.ROUTINES 
WHERE ROUTINE_SCHEMA = 'amisafe_database' 
  AND ROUTINE_NAME LIKE 'sp_%'
ORDER BY ROUTINE_NAME;
```

### Test Single Hexagon

```sql
-- Test analytics on one hexagon
CALL sp_complete_all_windows(13);

-- Check results for specific hexagon
SELECT 
    h3_index,
    incident_count,
    top_crime_type,
    risk_category,
    hotspot_status,
    risk_category_12mo,
    risk_category_6mo
FROM amisafe_h3_aggregated 
WHERE h3_resolution = 13 
  AND incident_count > 0
LIMIT 1;
```

### Manual Procedure Calls

```sql
-- Run just all-time analytics for Resolution 13
CALL sp_complete_resolution_analytics(13);

-- Run just 12mo + 6mo windowed analytics
CALL sp_update_resolution_analytics_windowed(13);

-- Custom single hexagon processing
CALL sp_update_hex_analytics('8d2a13400000c3f', 13);
CALL sp_update_hex_analytics_windowed('8d2a13400000c3f', 13);
```

---

## Integration with Main Setup

The unified setup script is automatically called from `/home/keithaumiller/stlouisintegration.com/script/setup.sh`:

```bash
# Step 5: H3 Geolocation Database Setup
AMISAFE_SETUP_SCRIPT="/home/keithaumiller/stlouisintegration.com/h3-geolocation/database/setup/setup_amisafe_complete.sh"
bash "$AMISAFE_SETUP_SCRIPT" "amisafe_database"
```

This ensures the database and analytics system are set up as part of the complete environment initialization.

---

## Data Flow

```
1. CSV Files → Bronze Layer (Raw)
   - Load: LOAD DATA INFILE or bulk import
   - Table: amisafe_raw_incidents
   - Key: objectid

2. Bronze → Silver Layer (Transform)
   - Script: etl/amisafe_processor.py or etl/enhanced_transform_processor_v2.py
   - Process: Clean, validate, H3 index
   - Table: amisafe_clean_incidents
   - Output: 3.4M validated records with H3 indexes

3. Silver → Gold Layer (Complete Pipeline)
   - Script: etl/amisafe_aggregator.py (orchestrates 3a + 3b)
   - Table: amisafe_h3_aggregated
   - Output: 413K hexagons with all 84 analytical columns
   
   Step 3a: Basic Aggregation
   - Process: Group by H3 hex, calculate basic counts
   - Output: ~20 basic metric columns
   
   Step 3b: Analytics Enrichment (via run_analytics.py)
   - Procedures: sp_complete_all_windows(resolution)
   - Process: Calculate 84 analytical columns via SQL
   - Output: Complete analytics for all windows (all-time, 12mo, 6mo)
```

---

## Script Responsibilities

### ETL Pipeline Scripts

**run_pipeline.sh**
- Master orchestrator for steps 1-3
- Runs: processor → aggregator
- Creates: Basic hexagons with counts, dates, coordinates
- Does NOT: Run advanced analytics

**amisafe_aggregator.py**
- Creates H3 hexagons from clean incidents
- Populates: incident_count, earliest/latest dates, center lat/lng, district_counts
- Does NOT: Calculate z-scores, risk scores, or windowed analytics

**run_analytics.py** ⭐ NEW
- Enriches existing hexagons with all 84 analytical columns
- Calls: sp_complete_all_windows() stored procedure
- Populates: top_crime_type, crime_diversity, z-scores, percentiles, risk_category, hotspot_status, temporal patterns
- Handles: All-time + 12-month + 6-month windows
- Features: Checkpoint/restart, progress tracking, status checking

---

## Troubleshooting

### Procedures Not Found
```bash
# Reinstall procedures
cd /home/keithaumiller/stlouisintegration.com/h3-geolocation/database
./setup/setup_amisafe_complete.sh
```

### Slow Performance
- Check indexes: `SHOW INDEX FROM amisafe_clean_incidents;`
- Monitor progress: Procedures log every 100 hexagons
- Start with high resolutions (13→5) for faster initial results

### Missing Analytics
- Run `sp_complete_all_windows(resolution)` for each resolution
- Check that base aggregation exists: `SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13;`

### Memory Issues
- Resolution 5 has only 5 hexagons but 584K incidents each
- Python scripts may OOM - use SQL procedures instead
- Increase MySQL buffer pool if needed

---

## Files in This Directory

### Directory Structure

```
database/
├── README.md                    # This documentation
├── setup/                       # One-time setup scripts
│   ├── setup_amisafe_complete.sh              # PRIMARY: Complete setup (tables + procedures)
│   ├── stored_procedures_h3_analytics.sql     # All-time analytics (11 procedures)
│   ├── stored_procedures_h3_analytics_windowed.sql  # Windowed analytics (10 procedures)
│   ├── setup_amisafe_stlouisintegration.sh    # Legacy: Tables only
│   └── setup_stored_procedures.sh             # Legacy: Procedures only
├── etl/                         # Data processing scripts
│   ├── amisafe_processor.py                   # Bronze → Silver (transform)
│   ├── enhanced_transform_processor_v2.py     # Alternative transformer
│   ├── amisafe_aggregator.py                  # Silver → Gold (aggregate)
│   └── run_pipeline.sh                        # ETL orchestrator
└── archive/                     # Deprecated scripts
    ├── fast_aggregator.py
    └── statistical_calculator.py
```

### Active Files

**Setup (run once):**
- `setup/setup_amisafe_complete.sh` - **Primary setup script** (tables + stored procedures)

**ETL Pipeline (run regularly):**
- `etl/amisafe_aggregator.py` - **Primary workflow**: Silver → Gold aggregation + analytics (calls run_analytics.py)
- `etl/run_analytics.py` - Analytics enrichment: Populate 84 columns via stored procedures (called by aggregator)
- `etl/amisafe_processor.py` - Bronze → Silver: Clean, validate, H3 index
- `etl/enhanced_transform_processor_v2.py` - Alternative Bronze → Silver transformer
- `etl/run_pipeline.sh` - Legacy orchestrator (Bronze → Silver → Gold basic only)

---

## Support

For issues or questions:
1. Check procedure status: `SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_NAME LIKE 'sp_%';`
2. Verify table structure: `DESCRIBE amisafe_h3_aggregated;`
3. Check logs: `tail -f analytics.log`
4. Re-run setup if needed: `./setup_amisafe_complete.sh`

**Status**: ✅ Complete - All 84 analytical columns across 3 time windows fully supported
