# AmISafe H3 Hexagon Statistics Database Schema

## Overview
The `amisafe_h3_aggregated` table contains comprehensive crime statistics for each H3 hexagon across multiple resolution levels (4-13). This data powers the interactive tooltip and popup systems in the crime map visualization.

## Available Statistics for Each Hexagon

### Core Identification
- **h3_index** (varchar 20): Unique H3 hexagon identifier
- **h3_resolution** (tinyint): H3 resolution level (4-13)
- **id** (int): Auto-incrementing primary key

### Incident Statistics
- **incident_count** (int): Total number of crime incidents in this hexagon
- **unique_incident_types** (int): Number of distinct crime types occurring in this hexagon
- **source_record_count** (int): Number of source records used for aggregation

### Temporal Analysis
- **earliest_incident** (datetime): Date/time of first recorded incident
- **latest_incident** (datetime): Date/time of most recent incident  
- **incidents_last_30_days** (int): Number of incidents in the past 30 days
- **incidents_last_year** (int): Number of incidents in the past 365 days
- **last_aggregation** (timestamp): When this aggregation was last updated

### Geographic Information
- **center_latitude** (decimal 10,8): Latitude of hexagon center point
- **center_longitude** (decimal 11,8): Longitude of hexagon center point
- **coverage_area_km2** (decimal 10,6): Geographic area covered by hexagon in square kilometers

### Crime Type Breakdown
- **incident_type_counts** (JSON): Detailed breakdown of incident counts by crime type code
  - Example: `{"200": 45, "300": 23, "1400": 12, "2600": 8}`
  - Crime type codes correspond to Philadelphia Police Department classifications
  - Available for all resolution levels with populated data

### District Information
- **district_counts** (JSON): Breakdown of incidents by police district
  - Example: `{"14": 67, "22": 23, "3": 8}`
  - Shows which police districts overlap with this hexagon
  - Useful for jurisdictional analysis

### Response & Quality Metrics
- **avg_response_time_minutes** (decimal 8,2): Average police response time (currently NULL)
- **total_units** (int): Total police units involved (currently 0)
- **avg_data_quality_score** (decimal 3,2): Average quality score of source data
- **total_valid_records** (int): Number of valid/clean source records
- **total_invalid_records** (int): Number of invalid/problematic source records

### Processing Metadata
- **aggregation_method** (varchar 50): Method used for aggregation (default: "standard")

## Data Coverage by Resolution Level

| Resolution | Total Hexagons | Min Incidents | Max Incidents | Avg Incidents | Crime Types | Has Crime Breakdown |
|------------|----------------|---------------|---------------|---------------|-------------|-------------------|
| 4          | 2              | 151,258       | 3,254,917     | 1,703,088     | 26          | ✅ All records    |
| 5          | 5              | 6,749         | 1,603,213     | 681,235       | 25-26       | ✅ All records    |
| 6          | 22             | 2             | 577,149       | 154,826       | 2-26        | ✅ All records    |
| 7          | 93             | 4             | 183,668       | 36,626        | 4-26        | ✅ All records    |
| 8          | 545            | 1             | 78,794        | 6,250         | 1-26        | ✅ All records    |
| 9          | 3,150          | 1             | 16,948        | 1,081         | 1-26        | ✅ All records    |
| 10         | 16,739         | 1             | 9,096         | 203           | 1-26        | ✅ All records    |
| 11         | 69,513         | 1             | 8,907         | 49            | 1-26        | ✅ All records    |
| 12         | 145,982        | 1             | 8,359         | 23            | 1-25        | ✅ All records    |
| 13         | 177,128        | 1             | 8,362         | 19            | 1-25        | ✅ All records    |

**Total Records:** 413,179 hexagons across all resolution levels

## Crime Type Code Mapping
The `incident_type_counts` JSON field uses Philadelphia Police Department crime classification codes:

- **100-199**: Violent Crimes
- **200**: Burglary
- **300**: Theft
- **400-499**: Robbery variants
- **500-599**: Violence/Assault
- **600-699**: Drug offenses
- **700-799**: Vandalism
- **800-899**: Weapons violations
- **900-999**: Other property crimes
- **1000+**: Various specialized categories

## Usage in AmISafe Crime Map

### Hover Tooltips
Quick statistics displayed on hexagon hover:
- H3 resolution level
- Incident count (formatted with thousands separators)
- Number of unique crime types
- Risk level calculation based on incident count

### Detailed Popups
Comprehensive statistics shown on hexagon click:
- **Geographic**: Center coordinates, coverage area, precision level
- **Temporal**: Date range, recent activity (30 days, 1 year)
- **Crime Analysis**: Top crime types, incident type breakdown
- **Administrative**: Police districts involved
- **Quality Metrics**: Data quality score, valid record counts

### Risk Level Calculation
Based on incident_count values:
- **CRITICAL**: ≥ 1,000 incidents
- **HIGH**: 500-999 incidents  
- **MEDIUM**: 100-499 incidents
- **LOW**: 10-99 incidents
- **MINIMAL**: 1-9 incidents

## Data Quality Notes

1. **Response Time Data**: Currently NULL across all records - may be populated in future updates
2. **Police Units**: Currently 0 across all records - placeholder for future enhancement
3. **Coverage Area**: NULL for most records - H3 library can calculate this dynamically
4. **Quality Scores**: Available for resolution 8+ (finer-grained hexagons)
5. **Crime Type Codes**: All records have detailed JSON breakdown
6. **District Mapping**: All records include police district associations

## Query Examples

### Get hexagon statistics for tooltips:
```sql
SELECT h3_index, incident_count, unique_incident_types, h3_resolution
FROM amisafe_h3_aggregated 
WHERE h3_resolution = 7 AND incident_count > 100;
```

### Get detailed popup information:
```sql
SELECT h3_index, incident_count, unique_incident_types, 
       earliest_incident, latest_incident, incidents_last_30_days,
       center_latitude, center_longitude, 
       JSON_PRETTY(incident_type_counts) as crime_breakdown,
       JSON_PRETTY(district_counts) as district_breakdown
FROM amisafe_h3_aggregated 
WHERE h3_index = '872a13402ffffff';
```

### Get resolution-level summary:
```sql
SELECT h3_resolution, COUNT(*) as hexagon_count, 
       SUM(incident_count) as total_incidents,
       AVG(incident_count) as avg_per_hexagon
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;
```

This comprehensive statistics database enables rich, interactive crime analysis at multiple geographic scales with detailed temporal and categorical breakdowns.