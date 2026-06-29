#!/bin/bash
"""
AmISafe Data Warehouse Database Setup for St. Louis Integration
Creates all tables for the complete ETL pipeline following data warehouse best practices
Raw (Bronze) → Transform (Silver) → Final (Gold) layers
MODIFIED TO USE: stlouisintegration_dev database
"""

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Configuration - MODIFIED FOR STLOUISINTEGRATION
DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="stlouisintegration_dev"  # <-- CHANGED FROM theoryofconspiracies_dev

if [ -z "${DB_PASSWORD}" ]; then
    print_error "DB_PASSWORD must be set in the environment."
    exit 1
fi

# Function to execute SQL with proper error handling
execute_sql() {
    local sql_command="$1"
    local description="$2"
    
    print_status "$description"
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "$sql_command" 2>/dev/null; then
        print_status "✅ $description completed successfully"
        return 0
    else
        print_error "❌ Failed: $description"
        return 1
    fi
}

# Function to create Raw Layer (Bronze) table
create_raw_layer_table() {
    print_step "Creating Raw Layer (Bronze) - amisafe_raw_incidents table"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_raw_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Source tracking for data lineage
        source_file VARCHAR(255) NOT NULL,
        ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- ALL original CSV fields preserved exactly as-is
        the_geom TEXT,
        cartodb_id INT,
        the_geom_webmercator TEXT,
        objectid BIGINT,
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
        
        -- Minimal indexing for raw layer (just for basic queries)
        INDEX idx_source_file (source_file),
        INDEX idx_ingested_at (ingested_at),
        INDEX idx_cartodb_id (cartodb_id),
        UNIQUE KEY unique_raw_objectid (objectid),  -- Primary business identifier
        INDEX idx_processing_status (processing_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Raw Layer (Bronze): Immutable source data preserved exactly as received from CSV files';
    "
    
    execute_sql "$sql" "Raw incidents table creation"
}

# Function to create Transform Layer (Silver) table
create_transform_layer_table() {
    print_step "Creating Transform Layer (Silver) - amisafe_clean_incidents table"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Data lineage
        raw_incident_ids JSON,                 -- Reference to source raw records
        processing_batch_id VARCHAR(50),       -- Deduplication batch tracking
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Validated business fields
        incident_id VARCHAR(50) UNIQUE,        -- Master incident identifier (based on objectid)
        cartodb_id INT,                        -- Legacy CartoDB ID (may be NULL)
        objectid BIGINT,                       -- Primary incident identifier (unique for all records)
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
        UNIQUE KEY unique_objectid (objectid),  -- Primary business key
        INDEX idx_location (lat, lng),
        INDEX idx_h3_res8 (h3_res_8),
        INDEX idx_h3_res9 (h3_res_9),
        INDEX idx_datetime (incident_datetime),
        INDEX idx_district (dc_dist),
        INDEX idx_crime_type (ucr_general),
        INDEX idx_quality (data_quality_score),
        INDEX idx_duplicates (duplicate_group_id, is_duplicate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Transform Layer (Silver): Cleaned, validated, deduplicated incidents with H3 spatial indexing';
    "
    
    execute_sql "$sql" "Clean incidents table creation"
}

# Function to create Final Layer (Gold) table
create_final_layer_table() {
    print_step "Creating Final Layer (Gold) - amisafe_h3_aggregated table"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Final Layer (Gold): Pre-computed H3 hexagon analytics optimized for dashboard queries';
    "
    
    execute_sql "$sql" "H3 aggregated analytics table creation"
}

# Function to insert initial configuration data
insert_initial_data() {
    print_step "Inserting initial configuration and reference data"
    
    # UCR crime code reference data
    local ucr_codes_sql="
    CREATE TABLE IF NOT EXISTS amisafe_ucr_codes (
        ucr_code VARCHAR(10) PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        severity_level TINYINT NOT NULL,
        color_hex VARCHAR(7) DEFAULT '#666666',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='UCR crime code reference table for standardization';
    "
    
    execute_sql "$ucr_codes_sql" "UCR codes reference table"
    
    # Insert standard UCR codes
    local insert_ucr_sql="
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
    ('1200', 'Quality of Life', 'Vice', 1, '#BA55D3');
    "
    
    execute_sql "$insert_ucr_sql" "Standard UCR codes insertion"
}

# Function to verify database setup
verify_database_setup() {
    print_step "Verifying database setup and table structure"
    
    # Check all tables exist
    local tables=(
        "amisafe_raw_incidents"
        "amisafe_clean_incidents" 
        "amisafe_h3_aggregated"
        "amisafe_ucr_codes"
    )
    
    print_status "Checking database tables..."
    for table in "${tables[@]}"; do
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
            print_status "✅ Table $table exists"
        else
            print_error "❌ Table $table is missing"
            return 1
        fi
    done
    
    # Get table statistics
    print_status "Database setup verification:"
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
}

# Main execution
main() {
    echo "=== AmISafe Data Warehouse Database Setup for St. Louis Integration ==="
    print_status "Setting up complete ETL pipeline database structure"
    print_status "Target database: $DB_NAME on $DB_HOST"
    
    # Test database connection
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" &>/dev/null; then
        print_error "Cannot connect to MySQL database. Please check credentials and ensure MySQL is running."
        exit 1
    fi
    
    print_status "✅ Database connection successful"
    
    # Create all database components
    create_raw_layer_table
    create_transform_layer_table  
    create_final_layer_table
    insert_initial_data
    
    # Verify everything was created correctly
    if verify_database_setup; then
        print_status "🎉 Database setup completed successfully!"
        print_status "=" * 50
        print_status "Ready for ETL pipeline processing:"
        print_status "1. Raw Layer: amisafe_raw_incidents (CSV import)"
        print_status "2. Transform Layer: amisafe_clean_incidents (deduplication & validation)"
        print_status "3. Final Layer: amisafe_h3_aggregated (analytics & H3 indexing)"
        print_status "Target Database: $DB_NAME"
        print_status "=" * 50
        return 0
    else
        print_error "Database setup verification failed"
        return 1
    fi
}

# Execute main function
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi