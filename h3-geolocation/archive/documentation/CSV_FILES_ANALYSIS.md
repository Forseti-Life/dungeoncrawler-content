# CSV Files Analysis Report

## File Structure Analysis

Based on detailed examination of the CSV files in `/data/raw/`, here are the key findings:

### 📁 File Inventory
- **20 CSV files total**: 1 base file + 19 numbered files
- **File sizes**: Range from 26MB to 45MB
- **Date range**: 2006-2025 (spanning 19 years)

### 🔍 File Structure Variations

#### **Type 1: Base File (`incidents_part1_part2.csv`)**
- **Fields**: 18 columns with full geospatial data
- **Date Range**: 2025 data (most recent)
- **Key Features**:
  - Full geometry fields: `the_geom`, `the_geom_webmercator`
  - Complete coordinate data: `lat`, `lng`, `point_x`, `point_y`
  - Rich geospatial PostGIS geometry strings
- **Record Count**: 125,554 records
- **Sample Record**: Has complete lat/lng coordinates (-75.24274174, 39.91244264)

#### **Type 2: Numbered Files (1-18) (`incidents_part1_part2 (1).csv` to `incidents_part1_part2 (18).csv`)**
- **Fields**: 18 columns but missing geospatial data
- **Date Range**: 2024 data (previous year)
- **Key Differences**:
  - Empty geometry fields (blank)
  - Coordinates set to 0.00000000 (missing geospatial data)
  - Same column structure but null geometry values
- **Record Counts**: ~154K-190K records per file
- **Sample Record**: Missing coordinates (0.00000000, 0.00000000)

#### **Type 3: File 19 (`incidents_part1_part2 (19).csv`)**
- **Fields**: 15 columns (reduced field set)
- **Date Range**: 2006 data (historical)
- **Key Differences**:
  - **Missing 3 columns**: `the_geom`, `cartodb_id`, `the_geom_webmercator`
  - Starts directly with `objectid` as first field
  - Historical data format (different schema)
- **Record Count**: ~190K records
- **Sample Record**: Completely different schema structure

### 📊 Most Similar vs Most Different Files

#### **MOST SIMILAR FILES**: `incidents_part1_part2 (1).csv` and `incidents_part1_part2 (2).csv`
```diff
Differences: ~0-5 lines (only data content, same structure)
- Same 18-column schema
- Same 2024 date range  
- Same missing geospatial data pattern
- Only individual record differences
```

#### **MOST DIFFERENT FILES**: `incidents_part1_part2.csv` and `incidents_part1_part2 (19).csv`
```diff
Differences: Fundamental schema and data differences
Schema:
- Base: 18 fields with full geospatial data
+ File 19: 15 fields with no geospatial data

Date Range:
- Base: 2025 data (current year)
+ File 19: 2006 data (19 years older)

Geospatial Data:
- Base: Full PostGIS geometry + coordinates
+ File 19: No geometry fields at all

Field Structure:
- Base: the_geom,cartodb_id,the_geom_webmercator,objectid,...
+ File 19: objectid,dc_dist,psa,dispatch_date_time,...
```

### 🎯 Key Insights

1. **Temporal Segmentation**: Files are organized by year (2006, 2024, 2025)
2. **Geospatial Evolution**: Newer files have richer geospatial data
3. **Schema Evolution**: Database schema changed over time (15→18 fields)
4. **Data Quality**: Most recent data (base file) has complete coordinates
5. **Missing Data Issue**: 2024 files have structural geospatial fields but null coordinate values

### 💡 Implications for Processing

1. **Different Processing Logic Needed**: Each file type requires different validation rules
2. **Coordinate Handling**: Only base file has valid coordinates for H3 indexing
3. **Schema Mapping**: File 19 needs field mapping due to missing columns
4. **Data Quality**: 2024 files might need coordinate reconstruction or geocoding
5. **Historical Processing**: 2006 data represents different era of data collection

### 🔧 Recommended Processing Strategy

1. **Type 1 (Base)**: Full H3 processing with complete validation
2. **Type 2 (Numbered 1-18)**: Skip coordinate validation, flag for geocoding
3. **Type 3 (File 19)**: Schema transformation before processing