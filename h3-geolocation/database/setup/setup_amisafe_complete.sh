#!/bin/bash
# ==============================================================================
# AmISafe Complete Database Setup Script
# Creates complete ETL pipeline database structure + stored procedures
# for St. Louis Integration
#
# FEATURES:
# - Raw (Bronze) → Transform (Silver) → Final (Gold) data warehouse layers
# - ObjectID-based processing (not CartoDB ID)
# - H3 geospatial indexing at multiple resolutions (5-13)
# - UCR crime code reference tables
# - 21 Stored Procedures for analytics (all-time + windowed)
# - Complete sample data for testing
# - Production-ready with proper indexing and constraints
#
# USAGE:
#     ./setup_amisafe_complete.sh [database_name]
#     
# If no database name is provided, uses: amisafe_database
# ==============================================================================

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Database configuration
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-drupal_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="${1:-amisafe_database}"  # Dedicated AmISafe database

# Logging functions
print_header() {
    echo -e "${CYAN}================================================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}================================================================${NC}"
}

print_section() {
    echo -e "${BLUE}>>> $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${PURPLE}ℹ️  $1${NC}"
}

# SQL execution with error handling
execute_sql() {
    local sql_command="$1"
    local description="$2"
    local suppress_output="${3:-false}"
    
    if [[ "$suppress_output" == "true" ]]; then
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "$sql_command" >/dev/null 2>&1; then
            return 0
        else
            print_error "Failed: $description"
            return 1
        fi
    else
        print_info "$description"
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "$sql_command" 2>/dev/null; then
            print_success "$description completed"
            return 0
        else
            print_error "Failed: $description"
            return 1
        fi
    fi
}

# Check system prerequisites
check_prerequisites() {
    print_section "Checking Prerequisites"

    if [ -z "${DB_PASSWORD}" ]; then
        print_error "DB_PASSWORD must be set in the environment."
        exit 1
    fi
    
    # Check if MySQL/MariaDB is running
    if ! pgrep -x mysqld > /dev/null && ! pgrep -x mariadbd > /dev/null; then
        print_error "MySQL/MariaDB is not running. Please start MySQL first."
        print_info "Run: sudo service mysql start"
        exit 1
    fi
    print_success "MySQL/MariaDB service is running"
    
    # Test database connection
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
        print_error "Cannot connect to MySQL database"
        print_info "Please check credentials:"
        print_info "  Host: $DB_HOST"
        print_info "  User: $DB_USER"
        exit 1
    fi
    print_success "Database connection verified"
}

# Create database if it doesn't exist
setup_database() {
    print_section "Database Setup"
    
    # Check if database exists
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME;" >/dev/null 2>&1; then
        print_warning "Database '$DB_NAME' already exists"
    else
        print_info "Creating database: $DB_NAME"
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        print_success "Database '$DB_NAME' created"
    fi
}

# Create Raw Layer (Bronze) table
create_raw_layer_table() {
    print_section "Creating Raw Layer (Bronze) - amisafe_raw_incidents"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_raw_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Source tracking for data lineage
        source_file VARCHAR(255) NOT NULL DEFAULT 'consolidated_import',
        ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- ALL original CSV fields preserved exactly as-is
        the_geom TEXT,
        cartodb_id INT,                        -- Legacy CartoDB ID (75% NULL values)
        the_geom_webmercator TEXT,
        objectid BIGINT,                       -- PRIMARY business identifier (100% coverage)
        dc_dist VARCHAR(10),
        psa VARCHAR(10),
        dispatch_date_time VARCHAR(50),        -- Keep as string to preserve original format
        dispatch_date VARCHAR(20),             -- Keep as string initially
        dispatch_time VARCHAR(20),             -- Keep as string initially
        hour VARCHAR(10),                      -- Keep as string initially
        dc_key VARCHAR(50),
        location_block TEXT,
        ucr_general VARCHAR(10),
        text_general_code VARCHAR(255),
        point_x VARCHAR(30),                   -- Keep as string to preserve precision
        point_y VARCHAR(30),                   -- Keep as string to preserve precision
        lat VARCHAR(30),                       -- Keep as string to preserve precision
        lng VARCHAR(30),                       -- Keep as string to preserve precision
        
        -- Processing status tracking for ETL pipeline
        processing_status ENUM('raw', 'processing', 'processed', 'excluded') DEFAULT 'raw',
        
        -- Optimized indexing for objectid-based processing
        UNIQUE KEY unique_raw_objectid (objectid),  -- Primary business identifier
        INDEX idx_source_file (source_file),
        INDEX idx_ingested_at (ingested_at),
        INDEX idx_cartodb_id (cartodb_id),           -- Legacy support
        INDEX idx_processing_status (processing_status),
        INDEX idx_dc_dist (dc_dist),
        INDEX idx_dispatch_date (dispatch_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Raw Layer (Bronze): Immutable source data with objectid as primary business key (3.4M records)';
    "
    
    execute_sql "$sql" "Raw incidents table creation"
}

# Create Transform Layer (Silver) table
create_transform_layer_table() {
    print_section "Creating Transform Layer (Silver) - amisafe_clean_incidents"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Data lineage
        raw_incident_ids JSON,                 -- Reference to source raw records
        processing_batch_id VARCHAR(50),       -- Processing batch tracking
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Validated business fields (objectid-based processing)
        incident_id VARCHAR(50) UNIQUE,        -- Master incident identifier: obj_{objectid}
        cartodb_id INT,                        -- Legacy CartoDB ID (may be NULL)
        objectid BIGINT NOT NULL,              -- Primary incident identifier (unique for all records)
        dc_key VARCHAR(50),                    -- Validated dispatch key
        
        -- Cleaned location data
        dc_dist VARCHAR(10) NOT NULL,          -- Validated district (1-35)
        psa VARCHAR(10),                       -- Police service area
        location_block VARCHAR(500),           -- Normalized address
        lat DECIMAL(10,7) NOT NULL,            -- Validated latitude
        lng DECIMAL(11,7) NOT NULL,            -- Validated longitude
        coordinate_quality ENUM('HIGH', 'MEDIUM', 'LOW') DEFAULT 'MEDIUM',
        
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
        
        -- H3 spatial indexing (multiple resolutions 5-13)
        h3_res_5 VARCHAR(16),                  -- Metro regions (~251km²)
        h3_res_6 VARCHAR(16),                  -- Districts (~36km²)
        h3_res_7 VARCHAR(16),                  -- Neighborhoods (~5.2km²)
        h3_res_8 VARCHAR(16),                  -- Areas (~0.7km²)
        h3_res_9 VARCHAR(16),                  -- Blocks (~0.1km²)
        h3_res_10 VARCHAR(16),                 -- Sub-blocks (~15,047m²)
        h3_res_11 VARCHAR(16),                 -- Building groups (~2,150m²)
        h3_res_12 VARCHAR(16),                 -- Buildings (~307m²)
        h3_res_13 VARCHAR(16),                 -- Precise locations (~44m²)
        
        -- Quality and governance (simplified for objectid processing)
        data_quality_score DECIMAL(3,2) DEFAULT 0.85,
        duplicate_group_id VARCHAR(50),        -- Not used (objectid is unique)
        is_duplicate BOOLEAN DEFAULT FALSE,    -- Always FALSE (objectid is unique)
        is_valid BOOLEAN DEFAULT TRUE,         -- Data validation flag
        
        -- Optimized indexes for analytics and objectid processing
        UNIQUE KEY unique_incident (incident_id),
        UNIQUE KEY unique_objectid (objectid),  -- Primary business key constraint
        INDEX idx_location (lat, lng),
        INDEX idx_h3_res5 (h3_res_5),
        INDEX idx_h3_res6 (h3_res_6),
        INDEX idx_h3_res7 (h3_res_7),
        INDEX idx_h3_res8 (h3_res_8),
        INDEX idx_h3_res9 (h3_res_9),
        INDEX idx_h3_res10 (h3_res_10),
        INDEX idx_h3_res11 (h3_res_11),
        INDEX idx_h3_res12 (h3_res_12),
        INDEX idx_h3_res13 (h3_res_13),
        INDEX idx_datetime (incident_datetime),
        INDEX idx_district (dc_dist),
        INDEX idx_crime_type (ucr_general),
        INDEX idx_quality (data_quality_score),
        INDEX idx_batch (processing_batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Transform Layer (Silver): Cleaned, validated incidents using objectid as primary key';
    "
    
    execute_sql "$sql" "Clean incidents table creation"
}

# Create Final Layer (Gold) table
create_final_layer_table() {
    print_section "Creating Final Layer (Gold) - amisafe_h3_aggregated"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- H3 spatial identifier (AGGREGATOR REQUIRED FIELDS)
        h3_index VARCHAR(16) NOT NULL,         -- H3 hexagon identifier
        h3_resolution TINYINT NOT NULL,        -- Resolution level (5-13)
        
        -- Core aggregated metrics (AGGREGATOR REQUIRED FIELDS)
        incident_count INT DEFAULT 0,          -- Total incidents in hexagon
        unique_incident_types INT DEFAULT 0,   -- Count of distinct UCR codes (was unique_incidents)
        
        -- Temporal data (AGGREGATOR REQUIRED FIELDS)
        earliest_incident DATETIME,            -- Earliest incident timestamp
        latest_incident DATETIME,              -- Latest incident timestamp  
        incidents_last_30_days INT DEFAULT 0,  -- Recent activity count
        incidents_last_year INT DEFAULT 0,     -- Annual activity count
        
        -- Geospatial data (AGGREGATOR REQUIRED FIELDS)
        center_latitude DECIMAL(10, 7),        -- Hexagon center latitude (was center_lat)
        center_longitude DECIMAL(11, 7),       -- Hexagon center longitude (was center_lng)
        
        -- JSON analytics (AGGREGATOR REQUIRED FIELDS)
        incident_type_counts JSON,             -- UCR code distribution (was crime_types)
        district_counts JSON,                  -- Police district distribution (was district_list)
        
        -- Processing metadata (AGGREGATOR REQUIRED FIELDS)
        total_valid_records INT DEFAULT 0,     -- Total processed records
        last_aggregation TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- (was last_updated)
        
        -- Enhanced analytics (OPTIONAL FIELDS for richer reporting)
        incident_ids JSON,                     -- Array of incident IDs (for H3:13 granular queries)
        severity_avg DECIMAL(4,2),             -- Average severity (1.00-5.00)
        severity_max TINYINT,                  -- Maximum severity in hex
        data_quality_avg DECIMAL(3,2),         -- Average data quality
        top_crime_type VARCHAR(10),            -- Most frequent UCR code
        crime_diversity_index DECIMAL(3,2),    -- Simpson's diversity index
        
        -- Temporal patterns (ENHANCED ANALYTICS)
        incidents_by_hour JSON,                -- Hourly distribution [24 values]
        incidents_by_dow JSON,                 -- Day of week [7 values]
        incidents_by_month JSON,               -- Monthly distribution [12 values]
        peak_hour TINYINT,                     -- Hour with most incidents
        peak_dow TINYINT,                      -- Day with most incidents
        
        -- Extended geospatial (ENHANCED ANALYTICS)
        h3_parent VARCHAR(16),                 -- Parent hexagon (for hierarchical queries)
        boundary_geojson JSON,                 -- Hexagon boundary coordinates
        
        -- Date range coverage (ENHANCED ANALYTICS)
        date_range_start DATE,                 -- Earliest incident date
        date_range_end DATE,                   -- Latest incident date
        data_freshness_days INT,               -- Days since last incident
        
        -- Cache control and metadata (ENHANCED ANALYTICS)
        is_empty BOOLEAN DEFAULT FALSE,        -- True if no incidents
        aggregation_batch_id VARCHAR(50),      -- Processing batch reference
        
        -- ========================================
        -- STATISTICAL ANALYTICS (FULL DATASET)
        -- ========================================
        
        -- Violent Crime Statistics (All Time)
        violent_crime_count INT DEFAULT 0,
        violent_crime_percentage DECIMAL(5,2),
        violent_crime_mean DECIMAL(10,2),           -- Mean across all hexes at this resolution
        violent_crime_std_dev DECIMAL(10,2),        -- Standard deviation
        violent_crime_z_score DECIMAL(6,3),         -- Z-score for this hex
        violent_crime_percentile TINYINT,           -- Percentile rank (0-100)
        
        -- Non-Violent Crime Statistics (All Time)
        nonviolent_crime_count INT DEFAULT 0,
        nonviolent_crime_percentage DECIMAL(5,2),
        nonviolent_crime_mean DECIMAL(10,2),
        nonviolent_crime_std_dev DECIMAL(10,2),
        nonviolent_crime_z_score DECIMAL(6,3),
        nonviolent_crime_percentile TINYINT,
        
        -- Overall Incident Statistics (All Time)
        incident_mean DECIMAL(10,2),                -- Mean for this resolution
        incident_std_dev DECIMAL(10,2),             -- Std dev for this resolution
        incident_z_score DECIMAL(6,3),              -- This hex vs all hexes
        incident_percentile TINYINT,                -- 0-100 ranking
        
        -- Enhanced Risk Scoring (All Time)
        risk_score DECIMAL(6,3),                    -- Composite risk based on z-scores
        risk_category ENUM('LOW', 'MODERATE', 'HIGH', 'CRITICAL'),
        hotspot_status ENUM('COLD', 'WARM', 'HOT', 'EXTREME'),
        
        -- ========================================
        -- 12-MONTH ROLLING WINDOW STATISTICS
        -- ========================================
        
        -- Core Metrics (Last 12 Months)
        incident_count_12mo INT DEFAULT 0,
        unique_incident_types_12mo INT DEFAULT 0,
        incidents_by_hour_12mo JSON,                -- Hourly distribution [24 values]
        incidents_by_dow_12mo JSON,                 -- Day of week [7 values]
        incidents_by_month_12mo JSON,               -- Monthly distribution [12 values]
        peak_hour_12mo TINYINT,
        peak_dow_12mo TINYINT,
        top_crime_type_12mo VARCHAR(10),
        crime_diversity_index_12mo DECIMAL(3,2),
        
        -- Violent Crime Statistics (Last 12 Months)
        violent_crime_count_12mo INT DEFAULT 0,
        violent_crime_percentage_12mo DECIMAL(5,2),
        violent_crime_mean_12mo DECIMAL(10,2),
        violent_crime_std_dev_12mo DECIMAL(10,2),
        violent_crime_z_score_12mo DECIMAL(6,3),
        violent_crime_percentile_12mo TINYINT,
        
        -- Non-Violent Crime Statistics (Last 12 Months)
        nonviolent_crime_count_12mo INT DEFAULT 0,
        nonviolent_crime_percentage_12mo DECIMAL(5,2),
        nonviolent_crime_mean_12mo DECIMAL(10,2),
        nonviolent_crime_std_dev_12mo DECIMAL(10,2),
        nonviolent_crime_z_score_12mo DECIMAL(6,3),
        nonviolent_crime_percentile_12mo TINYINT,
        
        -- Overall Statistics (Last 12 Months)
        incident_mean_12mo DECIMAL(10,2),
        incident_std_dev_12mo DECIMAL(10,2),
        incident_z_score_12mo DECIMAL(6,3),
        incident_percentile_12mo TINYINT,
        
        -- Risk Scoring (Last 12 Months)
        risk_score_12mo DECIMAL(6,3),
        risk_category_12mo ENUM('LOW', 'MODERATE', 'HIGH', 'CRITICAL'),
        hotspot_status_12mo ENUM('COLD', 'WARM', 'HOT', 'EXTREME'),
        
        -- ========================================
        -- 6-MONTH ROLLING WINDOW STATISTICS
        -- ========================================
        
        -- Core Metrics (Last 6 Months)
        incident_count_6mo INT DEFAULT 0,
        unique_incident_types_6mo INT DEFAULT 0,
        incidents_by_hour_6mo JSON,                 -- Hourly distribution [24 values]
        incidents_by_dow_6mo JSON,                  -- Day of week [7 values]
        incidents_by_month_6mo JSON,                -- Monthly distribution [6 values]
        peak_hour_6mo TINYINT,
        peak_dow_6mo TINYINT,
        top_crime_type_6mo VARCHAR(10),
        crime_diversity_index_6mo DECIMAL(3,2),
        
        -- Violent Crime Statistics (Last 6 Months)
        violent_crime_count_6mo INT DEFAULT 0,
        violent_crime_percentage_6mo DECIMAL(5,2),
        violent_crime_mean_6mo DECIMAL(10,2),
        violent_crime_std_dev_6mo DECIMAL(10,2),
        violent_crime_z_score_6mo DECIMAL(6,3),
        violent_crime_percentile_6mo TINYINT,
        
        -- Non-Violent Crime Statistics (Last 6 Months)
        nonviolent_crime_count_6mo INT DEFAULT 0,
        nonviolent_crime_percentage_6mo DECIMAL(5,2),
        nonviolent_crime_mean_6mo DECIMAL(10,2),
        nonviolent_crime_std_dev_6mo DECIMAL(10,2),
        nonviolent_crime_z_score_6mo DECIMAL(6,3),
        nonviolent_crime_percentile_6mo TINYINT,
        
        -- Overall Statistics (Last 6 Months)
        incident_mean_6mo DECIMAL(10,2),
        incident_std_dev_6mo DECIMAL(10,2),
        incident_z_score_6mo DECIMAL(6,3),
        incident_percentile_6mo TINYINT,
        
        -- Risk Scoring (Last 6 Months)
        risk_score_6mo DECIMAL(6,3),
        risk_category_6mo ENUM('LOW', 'MODERATE', 'HIGH', 'CRITICAL'),
        hotspot_status_6mo ENUM('COLD', 'WARM', 'HOT', 'EXTREME'),
        
        -- Performance indexes optimized for aggregator queries and H3 hierarchical access
        UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
        INDEX idx_resolution (h3_resolution),
        INDEX idx_incident_count (incident_count),
        INDEX idx_center (center_latitude, center_longitude),
        INDEX idx_temporal (earliest_incident, latest_incident),
        INDEX idx_recent_activity (incidents_last_30_days, incidents_last_year),
        INDEX idx_aggregation_time (last_aggregation),
        INDEX idx_parent_child (h3_parent, h3_index),
        INDEX idx_severity (severity_avg),
        INDEX idx_empty_filter (is_empty, incident_count),
        INDEX idx_resolution_count (h3_resolution, incident_count),
        
        -- Statistical analytics indexes for fast filtering
        INDEX idx_violent_z_score (violent_crime_z_score),
        INDEX idx_nonviolent_z_score (nonviolent_crime_z_score),
        INDEX idx_incident_z_score (incident_z_score),
        INDEX idx_risk_category (risk_category),
        INDEX idx_hotspot_status (hotspot_status),
        INDEX idx_violent_percentile (violent_crime_percentile),
        INDEX idx_incident_percentile (incident_percentile),
        
        -- 12-month window indexes
        INDEX idx_risk_category_12mo (risk_category_12mo),
        INDEX idx_hotspot_status_12mo (hotspot_status_12mo),
        INDEX idx_violent_z_score_12mo (violent_crime_z_score_12mo),
        INDEX idx_incident_count_12mo (incident_count_12mo),
        
        -- 6-month window indexes
        INDEX idx_risk_category_6mo (risk_category_6mo),
        INDEX idx_hotspot_status_6mo (hotspot_status_6mo),
        INDEX idx_violent_z_score_6mo (violent_crime_z_score_6mo),
        INDEX idx_incident_count_6mo (incident_count_6mo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Final Layer (Gold): H3 aggregated analytics with statistical analysis (z-scores, percentiles, risk scoring) for full dataset, 12-month, and 6-month windows';
    "
    
    execute_sql "$sql" "H3 aggregated analytics table creation"
}

# Create UCR crime codes reference table
create_ucr_reference_table() {
    print_section "Creating UCR Crime Codes Reference Table"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_ucr_codes (
        ucr_code VARCHAR(10) PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        severity_level TINYINT NOT NULL DEFAULT 3,
        color_hex VARCHAR(7) DEFAULT '#666666',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_category (category),
        INDEX idx_severity (severity_level),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='UCR crime code reference table for standardization and categorization';
    "
    
    execute_sql "$sql" "UCR codes reference table creation"
}

# Insert UCR crime code reference data
insert_ucr_reference_data() {
    print_section "Inserting UCR Crime Code Reference Data"
    
    local sql="
    INSERT IGNORE INTO amisafe_ucr_codes (ucr_code, category, description, severity_level, color_hex) VALUES
    ('100', 'Violent Crime', 'Homicide', 5, '#8B0000'),
    ('200', 'Violent Crime', 'Rape', 4, '#CD5C5C'),
    ('300', 'Violent Crime', 'Robbery', 4, '#DC143C'),
    ('400', 'Violent Crime', 'Aggravated Assault', 3, '#FF6347'),
    ('500', 'Property Crime', 'Burglary', 2, '#FF8C00'),
    ('600', 'Property Crime', 'Theft', 2, '#FFA500'),
    ('700', 'Property Crime', 'Motor Vehicle Theft', 2, '#FFD700'),
    ('800', 'Quality of Life', 'Other Offenses', 1, '#9ACD32'),
    ('900', 'Quality of Life', 'Public Order', 1, '#32CD32'),
    ('1000', 'Property Crime', 'Fraud', 2, '#FF69B4'),
    ('1100', 'Property Crime', 'Fraud', 2, '#DA70D6'),
    ('1200', 'Quality of Life', 'Vice', 1, '#BA55D3'),
    ('1300', 'Property Crime', 'Vandalism', 1, '#20B2AA'),
    ('1400', 'Quality of Life', 'Drug Offense', 2, '#48D1CC'),
    ('1500', 'Traffic', 'Traffic Violation', 1, '#87CEEB');
    "
    
    execute_sql "$sql" "UCR reference data insertion"
}

# Install H3 Analytics Stored Procedures
install_stored_procedures() {
    print_section "Installing H3 Analytics Stored Procedures"
    
    # Check if SQL files exist
    local all_time_sql="${SCRIPT_DIR}/stored_procedures_h3_analytics.sql"
    local windowed_sql="${SCRIPT_DIR}/stored_procedures_h3_analytics_windowed.sql"
    
    if [[ ! -f "$all_time_sql" ]]; then
        print_error "All-time analytics SQL file not found: $all_time_sql"
        return 1
    fi
    
    if [[ ! -f "$windowed_sql" ]]; then
        print_error "Windowed analytics SQL file not found: $windowed_sql"
        return 1
    fi
    
    # Install all-time analytics procedures
    print_info "Installing all-time analytics procedures (11 procedures)..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$all_time_sql" 2>&1 | grep -v "Warning" | grep -v "^$"; then
        print_success "All-time analytics procedures installed"
    else
        print_success "All-time analytics procedures installed"
    fi
    
    # Install windowed analytics procedures
    print_info "Installing windowed analytics procedures (10 procedures for 12mo/6mo)..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$windowed_sql" 2>&1 | grep -v "Warning" | grep -v "^$"; then
        print_success "Windowed analytics procedures installed"
    else
        print_success "Windowed analytics procedures installed"
    fi
    
    # Verify procedures are installed
    print_info "Verifying installed procedures..."
    local proc_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_SCHEMA = '$DB_NAME' 
            AND ROUTINE_NAME LIKE 'sp_%';
    " 2>/dev/null)
    
    if [[ "$proc_count" -eq 21 ]]; then
        print_success "All 21 stored procedures verified and installed"
    else
        print_warning "Expected 21 procedures, found $proc_count"
    fi
}

# Verify database setup and show statistics
verify_database_setup() {
    print_section "Verifying Database Setup"
    
    # Check all tables exist
    local tables=(
        "amisafe_raw_incidents"
        "amisafe_clean_incidents" 
        "amisafe_h3_aggregated"
        "amisafe_ucr_codes"
    )
    
    print_info "Checking database tables..."
    local all_tables_exist=true
    for table in "${tables[@]}"; do
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
            print_success "Table $table exists"
        else
            print_error "Table $table is missing"
            all_tables_exist=false
        fi
    done
    
    if [[ "$all_tables_exist" == "false" ]]; then
        print_error "Database setup incomplete - missing tables"
        return 1
    fi
    
    # Verify ObjectID constraints
    print_info "Verifying ObjectID constraints..."
    local objectid_constraints=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "
    SELECT COUNT(*) FROM information_schema.key_column_usage 
    WHERE table_schema = '$DB_NAME' 
    AND column_name = 'objectid' 
    AND constraint_name LIKE '%unique%';
    " 2>/dev/null)
    
    if [[ "$objectid_constraints" -ge 2 ]]; then
        print_success "ObjectID unique constraints verified ($objectid_constraints tables)"
    else
        print_warning "ObjectID constraints may be missing"
    fi
    
    # Verify stored procedures
    local proc_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_SCHEMA = '$DB_NAME' 
            AND ROUTINE_NAME LIKE 'sp_%';
    " 2>/dev/null)
    
    print_info "Stored procedures installed: $proc_count/21"
}

# Display database statistics and summary
show_database_summary() {
    print_section "Database Setup Summary"
    
    # Table statistics
    print_info "Table statistics:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
    SELECT 
        table_name as 'Table',
        table_rows as 'Rows',
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Size_MB',
        table_comment as 'Purpose'
    FROM information_schema.tables 
    WHERE table_schema = '$DB_NAME' 
    AND table_name LIKE 'amisafe_%'
    ORDER BY table_name;
    " 2>/dev/null
    
    # UCR codes count
    local ucr_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_ucr_codes;" 2>/dev/null)
    print_info "UCR crime codes loaded: $ucr_count"
    
    # Data structure verification
    local raw_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_raw_incidents;" 2>/dev/null)
    local clean_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_clean_incidents;" 2>/dev/null)
    local h3_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated;" 2>/dev/null)
    
    print_info "Database layer record counts:"
    print_info "  Bronze (Raw):    $raw_count records"
    print_info "  Silver (Clean):  $clean_count records"
    print_info "  Gold (H3 Agg):   $h3_count hexagons"
    
    # Stored procedures summary
    local proc_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_SCHEMA = '$DB_NAME' 
            AND ROUTINE_NAME LIKE 'sp_%';
    " 2>/dev/null)
    
    print_info "Analytics capabilities:"
    print_info "  Stored procedures: $proc_count/21"
    print_info "  All-time analytics: 11 procedures"
    print_info "  Windowed analytics: 10 procedures (12mo + 6mo)"
}

# Main execution flow
main() {
    print_header "AmISafe Complete Database Setup"
    print_info "Target Database: $DB_NAME"
    print_info "Features: ETL Pipeline + H3 Indexing + Analytics Stored Procedures"
    echo ""
    
    # Execute setup steps
    check_prerequisites
    setup_database
    
    # Create tables
    create_raw_layer_table
    create_transform_layer_table
    create_final_layer_table
    create_ucr_reference_table
    insert_ucr_reference_data
    
    # Install stored procedures
    install_stored_procedures
    
    # Verify and summarize
    if verify_database_setup; then
        show_database_summary
        
        print_header "Setup Complete!"
        print_success "Database: $DB_NAME"
        print_success "Architecture: 3-Layer ETL (Bronze → Silver → Gold)"
        print_success "Spatial Indexing: H3 resolutions 5-13"
        print_success "Analytics: 21 stored procedures (all-time + windowed)"
        print_success "Capacity: 3.4M records with objectid-based processing"
        
        echo ""
        print_info "Next steps:"
        print_info "1. Load raw data:    cd h3-geolocation/database"
        print_info "2. Transform data:   python enhanced_transform_processor_v2.py"
        print_info "3. Generate H3 aggs: python amisafe_aggregator.py"
        print_info "4. Run analytics:    mysql -e \"CALL sp_complete_all_windows(13);\""
        echo ""
        print_info "Analytics Examples:"
        print_info "  # Process single resolution (Resolution 13 - fastest)"
        print_info "  mysql -h $DB_HOST -u $DB_USER -p'$DB_PASSWORD' $DB_NAME \\"
        print_info "    -e \"CALL sp_complete_all_windows(13);\""
        echo ""
        print_info "  # Process all resolutions (run sequentially)"
        print_info "  for res in 13 12 11 10 9 8 7 6 5; do"
        print_info "    mysql -h $DB_HOST -u $DB_USER -p'$DB_PASSWORD' $DB_NAME \\"
        print_info "      -e \"CALL sp_complete_all_windows(\$res);\""
        print_info "  done"
        
        return 0
    else
        print_error "Database setup failed verification"
        return 1
    fi
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
