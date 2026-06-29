# AmISafe Analytics Pipeline Architecture

## Complete Pipeline Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          BRONZE LAYER (Stage 1)                          │
│                                                                          │
│  Input: 19 CSV files (2006-2024) ~950MB                                │
│  Script: amisafe_processor.py                                           │
│  Output: amisafe_raw_incidents (~3.5M records)                          │
│                                                                          │
│  Operations:                                                             │
│  • Load CSV files from data/raw/*.csv                                   │
│  • Generate H3 indexes for resolutions 5-13                             │
│  • Track exclusions (missing coords, duplicates)                        │
│  • Time: ~30-60 minutes                                                 │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          SILVER LAYER (Stage 2)                          │
│                                                                          │
│  Input: amisafe_raw_incidents                                           │
│  Script: enhanced_transform_processor_v2.py                             │
│  Output: amisafe_clean_incidents                                        │
│                                                                          │
│  Operations:                                                             │
│  • Validate coordinates (lat/lng bounds)                                │
│  • Deduplicate records                                                  │
│  • Standardize crime type codes                                         │
│  • Verify H3 index accuracy                                             │
│  • Time: ~20-40 minutes                                                 │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           GOLD LAYER (Stage 3)                           │
│                                                                          │
│  Input: amisafe_clean_incidents                                         │
│  Script: amisafe_aggregator.py                                          │
│  Output: amisafe_h3_aggregated (~410K hexagons)                         │
│                                                                          │
│  Operations:                                                             │
│  • Group incidents by H3 hexagon                                        │
│  • Aggregate counts by crime type                                       │
│  • Process 9 resolutions: 13→12→11→10→9→8→7→6→5                       │
│  • Create hexagon-level incident summaries                              │
│  • Time: ~15-30 minutes                                                 │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        ANALYTICS LAYER (Stage 4)                         │
│                                                                          │
│  Input: amisafe_h3_aggregated                                           │
│  Stored Procedures: sp_complete_all_windows(resolution)                 │
│  Output: 84 analytical columns per hexagon                              │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │         ALL-TIME ANALYTICS (28 columns)                           │ │
│  │  ─────────────────────────────────────────────────────────────    │ │
│  │  Basic Metrics:                                                   │ │
│  │    • top_crime_type              Most common crime               │ │
│  │    • crime_diversity_index       Shannon diversity               │ │
│  │    • incidents_by_hour           JSON: hourly distribution       │ │
│  │    • incidents_by_dow            JSON: day of week               │ │
│  │    • incidents_by_month          JSON: monthly pattern           │ │
│  │    • peak_hour                   Hour with most incidents        │ │
│  │    • peak_dow                    Day with most incidents         │ │
│  │    • violent_crime_count         # violent incidents             │ │
│  │    • nonviolent_crime_count      # nonviolent incidents          │ │
│  │    • violent_crime_percentage    % violent crimes                │ │
│  │                                                                   │ │
│  │  Statistical Metrics:                                             │ │
│  │    • violent_zscore              Z-score for violent crimes      │ │
│  │    • nonviolent_zscore           Z-score for nonviolent          │ │
│  │    • incident_zscore             Z-score for total               │ │
│  │    • violent_percentile          Percentile rank (violent)       │ │
│  │    • nonviolent_percentile       Percentile rank (nonviolent)    │ │
│  │    • incident_percentile         Percentile rank (total)         │ │
│  │                                                                   │ │
│  │  Risk Assessment:                                                 │ │
│  │    • risk_score                  Composite risk (0-100)          │ │
│  │    • risk_category               LOW/MEDIUM/HIGH/CRITICAL        │ │
│  │                                                                   │ │
│  │  Data Quality:                                                    │ │
│  │    • date_range_start            Earliest incident               │ │
│  │    • date_range_end              Latest incident                 │ │
│  │    • data_freshness_days         Days since last incident        │ │
│  │    • aggregation_batch_id        Processing batch ID             │ │
│  │                                                                   │ │
│  │  Plus: incident_type_counts, district_counts (JSON)             │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │         12-MONTH WINDOWED ANALYTICS (28 columns)                  │ │
│  │  ─────────────────────────────────────────────────────────────    │ │
│  │  Same 28 metrics as all-time, but calculated for:                │ │
│  │    • Last 12 months of data only                                  │ │
│  │    • Recent trends and patterns                                   │ │
│  │    • Seasonal variations                                          │ │
│  │    • Emerging hotspots                                            │ │
│  │                                                                   │ │
│  │  Column naming: *_12mo suffix                                     │ │
│  │    (e.g., top_crime_type_12mo, risk_score_12mo)                  │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │         6-MONTH WINDOWED ANALYTICS (28 columns)                   │ │
│  │  ─────────────────────────────────────────────────────────────    │ │
│  │  Same 28 metrics as all-time, but calculated for:                │ │
│  │    • Last 6 months of data only                                   │ │
│  │    • Current activity levels                                      │ │
│  │    • Immediate tactical intelligence                              │ │
│  │    • Real-time threat assessment                                  │ │
│  │                                                                   │ │
│  │  Column naming: *_6mo suffix                                      │ │
│  │    (e.g., top_crime_type_6mo, risk_score_6mo)                    │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                          │
│  Processing Time:                                                        │
│    • Basic (all-time only): ~1-2 hours                                  │
│    • Complete (all windows): ~3-6 hours                                 │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Stored Procedure Dependency Tree

```
sp_complete_all_windows(resolution)  ← MASTER PROCEDURE (84 columns)
│
├─► All-Time Analytics Pass
│   │
│   ├─► sp_update_resolution_analytics(resolution)
│   │   │
│   │   └─► sp_update_hex_analytics(h3_index, resolution)
│   │       ├─► sp_calculate_top_crime_type()
│   │       ├─► sp_calculate_crime_diversity()
│   │       ├─► sp_calculate_temporal_patterns()
│   │       ├─► sp_calculate_violent_stats()
│   │       ├─► sp_calculate_crime_district_counts()
│   │       └─► sp_calculate_date_freshness()
│   │
│   ├─► sp_calculate_statistical_metrics(resolution)
│   │   • Calculates z-scores for population
│   │   • Calculates percentile rankings
│   │   • Uses temp table for O(n log n) performance
│   │
│   └─► sp_calculate_risk_scores(resolution)
│       • Composite risk score (0-100)
│       • Risk categories (LOW/MEDIUM/HIGH/CRITICAL)
│
├─► 12-Month Windowed Analytics Pass
│   │
│   ├─► sp_update_resolution_analytics_windowed(resolution)
│   │   │
│   │   └─► sp_update_hex_analytics_windowed(h3_index, resolution)
│   │       ├─► sp_calculate_top_crime_type_windowed()
│   │       ├─► sp_calculate_crime_diversity_windowed()
│   │       ├─► sp_calculate_temporal_patterns_windowed()
│   │       ├─► sp_calculate_violent_stats_windowed()
│   │       └─► sp_calculate_unique_types_windowed()
│   │
│   ├─► sp_calculate_statistical_metrics_windowed(resolution, 12)
│   │   • 12-month z-scores and percentiles
│   │
│   └─► sp_calculate_risk_scores_windowed(resolution, 12)
│       • 12-month risk assessment
│
└─► 6-Month Windowed Analytics Pass
    │
    ├─► sp_calculate_statistical_metrics_windowed(resolution, 6)
    │   • 6-month z-scores and percentiles
    │
    └─► sp_calculate_risk_scores_windowed(resolution, 6)
        • 6-month risk assessment
```

## H3 Resolution Hierarchy

```
Resolution 5:  ~5 hexagons        (Entire Philadelphia metro area)
           │   Avg: 252.9 km² per hex
           │   Use: Regional overview, city-wide comparisons
           │
           ▼
Resolution 6:  ~23 hexagons       (Large districts)
           │   Avg: 36.1 km² per hex
           │   Use: District-level planning
           │
           ▼
Resolution 7:  ~93 hexagons       (Neighborhoods)
           │   Avg: 5.2 km² per hex
           │   Use: Neighborhood safety profiles
           │
           ▼
Resolution 8:  ~545 hexagons      (Sub-neighborhoods)
           │   Avg: 0.74 km² per hex
           │   Use: Community policing zones
           │
           ▼
Resolution 9:  ~3K hexagons       (Multi-block areas)
           │   Avg: 0.10 km² per hex
           │   Use: Patrol route planning
           │
           ▼
Resolution 10: ~17K hexagons      (City blocks)
           │   Avg: 0.015 km² per hex
           │   Use: Tactical deployment
           │
           ▼
Resolution 11: ~70K hexagons      (Street segments)
           │   Avg: 0.002 km² per hex
           │   Use: Hotspot identification
           │
           ▼
Resolution 12: ~146K hexagons     (Building clusters)
           │   Avg: 0.0003 km² per hex
           │   Use: Incident-level precision
           │
           ▼
Resolution 13: ~177K hexagons     (Individual buildings)
               Avg: 0.00004 km² per hex
               Use: Property-specific data
```

## Data Volume by Resolution

```
Total Hexagons: ~410,000 across all resolutions

Resolution 13: 177,000 hexagons  (43%)  ████████████████████████████
Resolution 12: 146,000 hexagons  (36%)  ███████████████████████
Resolution 11:  70,000 hexagons  (17%)  ███████████
Resolution 10:  17,000 hexagons   (4%)  ███
Resolution 9:    3,000 hexagons   (<1%) █
Resolution 8:      545 hexagons   (<1%) 
Resolution 7:       93 hexagons   (<1%) 
Resolution 6:       23 hexagons   (<1%) 
Resolution 5:        5 hexagons   (<1%) 
```

## Analytics Column Count

```
Per Hexagon:
├─ All-Time Analytics:    28 columns
├─ 12-Month Window:       28 columns
└─ 6-Month Window:        28 columns
                          ──────────
Total Analytical Columns: 84 columns

Across all resolutions (~410K hexagons):
Total analytical data points: ~34.4 million
```

## Command Summary

### Full Pipeline
```bash
export DB_USER='stlouis_user'
export DB_PASSWORD='StLouis2024!Secure#DB'
export DB_SOCKET='/var/run/mysqld/mysqld.sock'

cd /var/www/html/stlouisintegration/h3-geolocation
source h3-env/bin/activate

# Complete pipeline (Bronze → Silver → Gold → Analytics)
nohup ./database/etl/run_complete_pipeline.sh --full > pipeline.log 2>&1 &
```

### Individual Stages
```bash
# Stage by stage
./database/etl/run_complete_pipeline.sh --bronze           # ~30-60 min
./database/etl/run_complete_pipeline.sh --silver           # ~20-40 min
./database/etl/run_complete_pipeline.sh --gold             # ~15-30 min
./database/etl/run_complete_pipeline.sh --analytics        # ~3-6 hours

# Faster analytics (all-time only, skip windowed)
./database/etl/run_complete_pipeline.sh --analytics-basic  # ~1-2 hours
```

### Resume After Interruption
```bash
# Automatically continues from last successful stage
./database/etl/run_complete_pipeline.sh --resume
```

## Files Created

```
h3-geolocation/
├── database/
│   ├── etl/
│   │   ├── run_complete_pipeline.sh        ← MASTER ORCHESTRATOR
│   │   ├── amisafe_processor.py            ← Bronze layer
│   │   ├── enhanced_transform_processor_v2.py ← Silver layer
│   │   ├── amisafe_aggregator.py           ← Gold layer
│   │   └── run_analytics.py                ← Alternative analytics runner
│   │
│   ├── setup/
│   │   ├── stored_procedures_h3_analytics.sql          ← All-time (11 procs)
│   │   └── stored_procedures_h3_analytics_windowed.sql ← Windowed (10 procs)
│   │
│   ├── pipeline_state.json                 ← Checkpoint/resume state
│   └── pipeline_YYYYMMDD_HHMMSS.log       ← Execution logs
│
├── PIPELINE_GUIDE.md                       ← Complete documentation
└── PIPELINE_ARCHITECTURE.md                ← This file
```

## Dependencies Summary

```
Bronze Layer:
├─ Requires: CSV files in data/raw/
├─ Produces: amisafe_raw_incidents
└─ No dependencies on other stages

Silver Layer:
├─ Requires: amisafe_raw_incidents (Bronze complete)
├─ Produces: amisafe_clean_incidents
└─ Depends on: Bronze Layer

Gold Layer:
├─ Requires: amisafe_clean_incidents (Silver complete)
├─ Produces: amisafe_h3_aggregated
└─ Depends on: Silver Layer

Analytics Layer:
├─ Requires: amisafe_h3_aggregated (Gold complete)
├─ Produces: 84 analytical columns per hexagon
└─ Depends on: Gold Layer
```

## Next Steps

1. **Run the pipeline** (choose one):
   - Full: `./database/etl/run_complete_pipeline.sh --full`
   - Step-by-step: Run each stage individually
   - Fast analytics: Use `--analytics-basic` first

2. **Monitor progress**:
   - Watch log: `tail -f database/pipeline_*.log`
   - Check state: `cat database/pipeline_state.json`

3. **Enable Drupal module** after pipeline completion:
   ```bash
   drush en amisafe -y
   drush cr
   ```

4. **Test API endpoints**:
   ```bash
   curl "https://stlouisintegration.com/amisafe/api/v1/crime-data?lat=39.9526&lng=-75.1652&resolution=13"
   ```
