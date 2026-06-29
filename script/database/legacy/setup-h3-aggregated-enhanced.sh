#!/bin/bash

# AmISafe H3 Aggregated Table Setup Script - Updated Version
# Creates the H3 aggregated table with incident_ids support for H3:13 granular filtering

set -e

echo "🚀 Setting up AmISafe H3 Aggregated table with incident_ids support..."

# Database configuration
DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASS="${DB_PASSWORD:-}"
DB_NAME="stlouisintegration_dev"

if [ -z "$DB_PASS" ]; then
    echo "❌ DB_PASSWORD is not set"
    exit 1
fi

echo "📋 Creating amisafe_h3_aggregated table with enhanced schema..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- H3 spatial identification
    h3_index VARCHAR(20) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    
    -- Aggregated statistics
    incident_count INT DEFAULT 0,
    unique_incident_types INT DEFAULT 0,
    
    -- Response time analytics
    avg_response_time_minutes DECIMAL(8,2) DEFAULT NULL,
    total_units INT DEFAULT 0,
    
    -- Temporal data
    earliest_incident DATETIME DEFAULT NULL,
    latest_incident DATETIME DEFAULT NULL,
    incidents_last_30_days INT DEFAULT 0,
    incidents_last_year INT DEFAULT 0,
    
    -- Geospatial data
    center_latitude DECIMAL(10,8) DEFAULT NULL,
    center_longitude DECIMAL(11,8) DEFAULT NULL,
    coverage_area_km2 DECIMAL(10,6) DEFAULT NULL,
    
    -- Crime type and district breakdowns
    incident_type_counts JSON DEFAULT NULL,
    district_counts JSON DEFAULT NULL,
    
    -- Data quality metrics
    avg_data_quality_score DECIMAL(3,2) DEFAULT NULL,
    total_valid_records INT DEFAULT 0,
    total_invalid_records INT DEFAULT 0,
    
    -- Processing metadata
    last_aggregation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source_record_count INT DEFAULT 0,
    aggregation_method VARCHAR(50) DEFAULT 'standard',
    
    -- H3:13 Granular Filtering Support
    incident_ids JSON DEFAULT NULL COMMENT 'JSON array of incident IDs in this hexagon (H3:13 only)',
    
    -- Indexes for performance
    UNIQUE KEY unique_h3_cell (h3_index, h3_resolution),
    INDEX idx_h3_index (h3_index),
    INDEX idx_h3_resolution (h3_resolution),
    INDEX idx_incident_count (incident_count),
    INDEX idx_latest_incident (latest_incident),
    INDEX idx_center_coords (center_latitude, center_longitude),
    INDEX idx_last_aggregation (last_aggregation),
    INDEX idx_h3_resolution_incident_count (h3_resolution, incident_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

echo "✅ amisafe_h3_aggregated table created successfully with incident_ids support"

# Show table structure for verification
echo "📊 Verifying table structure..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "DESCRIBE amisafe_h3_aggregated;" | head -25

echo "🎯 Key features enabled:"
echo "  ✓ H3 resolutions 4-15 support"
echo "  ✓ Multi-scale aggregation (metro-wide to room-level)"
echo "  ✓ H3:13 granular filtering with incident_ids column"
echo "  ✓ Crime type and district breakdowns"
echo "  ✓ Temporal analytics (30-day, yearly)"
echo "  ✓ Data quality tracking"
echo "  ✓ Performance indexes"

echo "📈 Ready for:"
echo "  - AmISafe aggregator pipeline"
echo "  - H3:13 incident-level filtering"
echo "  - Multi-resolution visualization"
echo "  - Granular API endpoints"