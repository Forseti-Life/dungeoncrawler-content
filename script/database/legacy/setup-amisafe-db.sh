#!/bin/bash

# AmISafe Database Setup Script
# Creates the amisafe database and loads sample incident data

set -e

echo "🚀 Setting up AmISafe Database..."

# Database configuration
DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASS="${DB_PASSWORD:-}"
DB_NAME="amisafe"

if [ -z "$DB_PASS" ]; then
    echo "❌ DB_PASSWORD is not set"
    exit 1
fi

# Create database
echo "📊 Creating amisafe database..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create raw_incidents table
echo "📋 Creating raw_incidents table..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
CREATE TABLE IF NOT EXISTS raw_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_file VARCHAR(255) NOT NULL DEFAULT 'sample_data',
    
    -- Spatial data
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(11, 7) NOT NULL,
    h3_index VARCHAR(16),
    h3_resolution TINYINT DEFAULT 9,
    
    -- Administrative boundaries  
    dc_dist VARCHAR(10),
    
    -- Temporal data
    dispatch_date_time DATETIME NOT NULL,
    dispatch_date DATE NOT NULL,
    hour TINYINT,
    month TINYINT,
    year SMALLINT,
    
    -- Crime classification
    ucr_general VARCHAR(10) NOT NULL,
    text_general_code VARCHAR(255),
    severity_level TINYINT DEFAULT 3,
    
    -- Location details
    location_block TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Essential indexes
    INDEX idx_temporal (dispatch_date_time),
    INDEX idx_spatial (lat, lng),
    INDEX idx_district (dc_dist),
    INDEX idx_crime_type (ucr_general)
) ENGINE=InnoDB;
EOF

# Insert sample data based on Philadelphia crime patterns
echo "💾 Loading sample incident data..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
INSERT INTO raw_incidents 
(lat, lng, dc_dist, dispatch_date_time, dispatch_date, hour, month, year, ucr_general, text_general_code, severity_level, location_block, source_file)
VALUES
-- Center City incidents
(39.9526, -75.1652, '6', '2025-10-30 14:30:00', '2025-10-30', 14, 10, 2025, '400', 'THEFT', 2, '1500 BLOCK MARKET ST', 'sample_batch_1'),
(39.9500, -75.1667, '6', '2025-10-30 16:45:00', '2025-10-30', 16, 10, 2025, '200', 'BURGLARY', 4, '1600 BLOCK WALNUT ST', 'sample_batch_1'),
(39.9480, -75.1635, '6', '2025-10-30 09:15:00', '2025-10-30', 9, 10, 2025, '100', 'HOMICIDE', 5, '1700 BLOCK CHESTNUT ST', 'sample_batch_1'),

-- North Philadelphia incidents  
(39.9950, -75.1450, '22', '2025-10-30 22:30:00', '2025-10-30', 22, 10, 2025, '300', 'ASSAULT', 4, '2800 BLOCK N BROAD ST', 'sample_batch_1'),
(40.0100, -75.1300, '22', '2025-10-30 01:45:00', '2025-10-30', 1, 10, 2025, '400', 'THEFT', 3, '3200 BLOCK N 5TH ST', 'sample_batch_1'),
(39.9850, -75.1500, '22', '2025-10-30 18:20:00', '2025-10-30', 18, 10, 2025, '500', 'DRUG OFFENSE', 3, '2600 BLOCK N BROAD ST', 'sample_batch_1'),

-- South Philadelphia incidents
(39.9200, -75.1580, '1', '2025-10-30 12:00:00', '2025-10-30', 12, 10, 2025, '400', 'THEFT', 2, '1900 BLOCK S BROAD ST', 'sample_batch_1'),
(39.9150, -75.1620, '1', '2025-10-30 15:30:00', '2025-10-30', 15, 10, 2025, '600', 'VANDALISM', 2, '2000 BLOCK S 15TH ST', 'sample_batch_1'),

-- West Philadelphia incidents
(39.9600, -75.2000, '18', '2025-10-30 20:15:00', '2025-10-30', 20, 10, 2025, '300', 'ASSAULT', 4, '4800 BLOCK BALTIMORE AVE', 'sample_batch_1'),
(39.9550, -75.1950, '18', '2025-10-30 11:45:00', '2025-10-30', 11, 10, 2025, '400', 'THEFT', 3, '4600 BLOCK CHESTNUT ST', 'sample_batch_1'),

-- Additional historical data for better statistics
(39.9526, -75.1652, '6', '2025-10-29 08:30:00', '2025-10-29', 8, 10, 2025, '400', 'THEFT', 2, '1500 BLOCK MARKET ST', 'sample_batch_2'),
(39.9500, -75.1667, '6', '2025-10-29 19:15:00', '2025-10-29', 19, 10, 2025, '300', 'ASSAULT', 3, '1600 BLOCK WALNUT ST', 'sample_batch_2'),
(39.9950, -75.1450, '22', '2025-10-29 23:45:00', '2025-10-29', 23, 10, 2025, '500', 'DRUG OFFENSE', 3, '2800 BLOCK N BROAD ST', 'sample_batch_2'),
(39.9200, -75.1580, '1', '2025-10-29 13:20:00', '2025-10-29', 13, 10, 2025, '200', 'BURGLARY', 4, '1900 BLOCK S BROAD ST', 'sample_batch_2'),
(39.9600, -75.2000, '18', '2025-10-29 17:00:00', '2025-10-29', 17, 10, 2025, '600', 'VANDALISM', 2, '4800 BLOCK BALTIMORE AVE', 'sample_batch_2'),

-- Week ago data
(39.9480, -75.1635, '6', '2025-10-23 10:30:00', '2025-10-23', 10, 10, 2025, '400', 'THEFT', 2, '1700 BLOCK CHESTNUT ST', 'sample_batch_3'),
(40.0100, -75.1300, '22', '2025-10-23 14:15:00', '2025-10-23', 14, 10, 2025, '300', 'ASSAULT', 4, '3200 BLOCK N 5TH ST', 'sample_batch_3'),
(39.9150, -75.1620, '1', '2025-10-23 21:45:00', '2025-10-23', 21, 10, 2025, '200', 'BURGLARY', 4, '2000 BLOCK S 15TH ST', 'sample_batch_3'),
(39.9550, -75.1950, '18', '2025-10-23 07:30:00', '2025-10-23', 7, 10, 2025, '100', 'HOMICIDE', 5, '4600 BLOCK CHESTNUT ST', 'sample_batch_3'),

-- Additional incidents for statistical variety
(39.9400, -75.1700, '6', '2025-10-28 16:00:00', '2025-10-28', 16, 10, 2025, '400', 'THEFT', 2, '1800 BLOCK PINE ST', 'sample_batch_4'),
(39.9800, -75.1400, '22', '2025-10-28 03:30:00', '2025-10-28', 3, 10, 2025, '500', 'DRUG OFFENSE', 3, '2700 BLOCK N FRONT ST', 'sample_batch_4'),
(39.9300, -75.1500, '1', '2025-10-28 12:45:00', '2025-10-28', 12, 10, 2025, '600', 'VANDALISM', 2, '1800 BLOCK S BROAD ST', 'sample_batch_4'),
(39.9650, -75.1900, '18', '2025-10-28 19:20:00', '2025-10-28', 19, 10, 2025, '300', 'ASSAULT', 4, '4700 BLOCK MARKET ST', 'sample_batch_4'),

-- Month ago for temporal analysis
(39.9526, -75.1652, '6', '2025-09-30 11:15:00', '2025-09-30', 11, 9, 2025, '200', 'BURGLARY', 4, '1500 BLOCK MARKET ST', 'sample_batch_5'),
(39.9950, -75.1450, '22', '2025-09-30 20:30:00', '2025-09-30', 20, 9, 2025, '400', 'THEFT', 3, '2800 BLOCK N BROAD ST', 'sample_batch_5'),
(39.9200, -75.1580, '1', '2025-09-30 15:45:00', '2025-09-30', 15, 9, 2025, '300', 'ASSAULT', 4, '1900 BLOCK S BROAD ST', 'sample_batch_5'),
(39.9600, -75.2000, '18', '2025-09-30 09:00:00', '2025-09-30', 9, 9, 2025, '500', 'DRUG OFFENSE', 3, '4800 BLOCK BALTIMORE AVE', 'sample_batch_5');
EOF

# Get count to verify
INCIDENT_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -se "SELECT COUNT(*) FROM raw_incidents;")

echo "✅ AmISafe Database Setup Complete!"
echo "📊 Total incidents loaded: $INCIDENT_COUNT"
echo "🗄️  Database: $DB_NAME"
echo "🏠 Host: $DB_HOST"
echo ""
echo "🔧 To connect from Drupal, update CrimeDataService to use:"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"  
echo "   Connection: Use fallback to default Drupal database"

# Test query
echo ""
echo "🧪 Sample query results:"
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
SELECT 
    dc_dist as district,
    COUNT(*) as incident_count,
    AVG(severity_level) as avg_severity
FROM raw_incidents 
GROUP BY dc_dist 
ORDER BY incident_count DESC;"

echo ""
echo "🌆 Total citywide incidents: $INCIDENT_COUNT"
echo "🎯 Ready to test real database queries!"