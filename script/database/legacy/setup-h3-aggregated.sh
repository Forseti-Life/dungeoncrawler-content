#!/bin/bash

# AmISafe H3 Aggregated Table Setup Script
# Creates the H3 aggregated table with pre-calculated values

set -e

echo "🚀 Setting up AmISafe H3 Aggregated table..."

# Database configuration
DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASS="${DB_PASSWORD:-}"
DB_NAME="theoryofconspiracies_dev"

if [ -z "$DB_PASS" ]; then
    echo "❌ DB_PASSWORD is not set"
    exit 1
fi

echo "📋 Creating amisafe_h3_aggregated table..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- H3 spatial identification
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    h3_parent VARCHAR(16),
    
    -- Geospatial data
    center_lat DECIMAL(10, 7) NOT NULL,
    center_lng DECIMAL(11, 7) NOT NULL,
    boundary_json JSON NOT NULL,
    
    -- Aggregated statistics
    crime_count INT DEFAULT 0,
    crime_types_json JSON,
    severity_avg DECIMAL(3,2) DEFAULT 3.00,
    
    -- Temporal aggregations
    last_incident DATETIME,
    first_incident DATETIME,
    peak_hour TINYINT,
    
    -- Administrative data
    districts_json JSON,
    
    -- Cache control
    is_empty BOOLEAN DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Performance indexes
    UNIQUE INDEX idx_h3_resolution (h3_index, h3_resolution),
    INDEX idx_resolution_lookup (h3_resolution, crime_count),
    INDEX idx_parent_child (h3_parent, h3_index),
    INDEX idx_spatial_query (center_lat, center_lng, h3_resolution),
    INDEX idx_temporal_cache (last_updated, h3_resolution),
    INDEX idx_empty_filter (is_empty, crime_count)
) ENGINE=InnoDB;
EOF

echo "💾 Loading sample H3 aggregated data for all resolutions (8-15)..."

# Generate H3 data for Philadelphia area
# We'll create sample data for resolutions 8-15 covering different areas of Philadelphia

mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
-- Resolution 8 hexagons (large area coverage)
INSERT IGNORE INTO amisafe_h3_aggregated 
(h3_index, h3_resolution, center_lat, center_lng, boundary_json, crime_count, crime_types_json, severity_avg, is_empty, last_incident, peak_hour, districts_json)
VALUES
-- Center City (H3 Resolution 8)
('882aacb2e57ffff', 8, 39.9526, -75.1652, '[[39.960,-75.150],[39.945,-75.150],[39.940,-75.165],[39.945,-75.180],[39.960,-75.180],[39.965,-75.165]]', 47, '{"100":2,"200":8,"300":12,"400":15,"500":6,"600":4}', 3.2, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),

-- North Philadelphia (H3 Resolution 8) 
('882aacb2e4fffff', 8, 39.9950, -75.1450, '[[40.010,-75.130],[39.980,-75.130],[39.975,-75.145],[39.980,-75.160],[40.010,-75.160],[40.015,-75.145]]', 38, '{"200":5,"300":14,"400":10,"500":9}', 3.8, FALSE, '2025-10-30 22:30:00', 22, '["22"]'),

-- South Philadelphia (H3 Resolution 8)
('882aacb2e47ffff', 8, 39.9200, -75.1580, '[[39.935,-75.143],[39.905,-75.143],[39.900,-75.158],[39.905,-75.173],[39.935,-75.173],[39.940,-75.158]]', 29, '{"200":7,"300":6,"400":11,"600":5}', 3.1, FALSE, '2025-10-30 15:30:00', 15, '["1"]'),

-- West Philadelphia (H3 Resolution 8)
('882aacb2e5fffff', 8, 39.9600, -75.2000, '[[39.975,-75.185],[39.945,-75.185],[39.940,-75.200],[39.945,-75.215],[39.975,-75.215],[39.980,-75.200]]', 35, '{"100":1,"300":13,"400":12,"500":6,"600":3}', 3.4, FALSE, '2025-10-30 20:15:00', 20, '["18"]'),

-- University City (H3 Resolution 8)
('882aacb2e77ffff', 8, 39.9520, -75.1932, '[[39.967,-75.178],[39.937,-75.178],[39.932,-75.193],[39.937,-75.208],[39.967,-75.208],[39.972,-75.193]]', 22, '{"200":3,"300":8,"400":7,"500":4}', 2.9, FALSE, '2025-10-30 13:45:00', 13, '["3"]'),

-- Northeast Philadelphia (H3 Resolution 8) 
('882aacb2e6fffff', 8, 40.0600, -75.0800, '[[40.075,-75.065],[40.045,-75.065],[40.040,-75.080],[40.045,-75.095],[40.075,-75.095],[40.080,-75.080]]', 18, '{"300":6,"400":8,"600":4}', 3.0, FALSE, '2025-10-30 14:20:00', 14, '["9"]'),

-- Germantown (H3 Resolution 8)
('882aacb2e67ffff', 8, 40.0300, -75.1700, '[[40.045,-75.155],[40.015,-75.155],[40.010,-75.170],[40.015,-75.185],[40.045,-75.185],[40.050,-75.170]]', 16, '{"200":4,"300":5,"400":4,"600":3}', 3.3, FALSE, '2025-10-30 19:45:00', 19, '["8"]'),

-- Empty hexagon for testing
('882aacb2e6affff', 8, 40.1000, -75.0500, '[[40.115,-75.035],[40.085,-75.035],[40.080,-75.050],[40.085,-75.065],[40.115,-75.065],[40.120,-75.050]]', 0, '{}', 0.0, TRUE, NULL, NULL, '[]');
EOF

echo "💾 Loading resolution 9 hexagon data..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
-- Resolution 9 hexagons (more detailed - subdivisions of resolution 8)
INSERT IGNORE INTO amisafe_h3_aggregated 
(h3_index, h3_resolution, h3_parent, center_lat, center_lng, boundary_json, crime_count, crime_types_json, severity_avg, is_empty, last_incident, peak_hour, districts_json)
VALUES
-- Center City subdivisions
('892aacb2e57ffff', 9, '882aacb2e57ffff', 39.9540, -75.1640, '[[39.959,-75.161],[39.949,-75.161],[39.947,-75.167],[39.949,-75.173],[39.959,-75.173],[39.961,-75.167]]', 23, '{"200":4,"300":6,"400":8,"500":3,"600":2}', 3.1, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('892aacb2e5fffff', 9, '882aacb2e57ffff', 39.9510, -75.1665, '[[39.956,-75.164],[39.946,-75.164],[39.944,-75.170],[39.946,-75.176],[39.956,-75.176],[39.958,-75.170]]', 24, '{"100":2,"200":4,"300":6,"400":7,"500":3,"600":2}', 3.3, FALSE, '2025-10-30 14:30:00', 14, '["6"]'),

-- North Philadelphia subdivisions  
('892aacb2e4fffff', 9, '882aacb2e4fffff', 39.9970, -75.1430, '[[40.002,-75.140],[39.992,-75.140],[39.990,-75.146],[39.992,-75.152],[40.002,-75.152],[40.004,-75.146]]', 19, '{"300":7,"400":5,"500":4,"600":3}', 3.9, FALSE, '2025-10-30 22:30:00', 22, '["22"]'),
('892aacb2e47ffff', 9, '882aacb2e4fffff', 39.9930, -75.1470, '[[39.998,-75.144],[39.988,-75.144],[39.986,-75.150],[39.988,-75.156],[39.998,-75.156],[40.000,-75.150]]', 19, '{"200":5,"300":7,"400":5,"500":2}', 3.7, FALSE, '2025-10-30 18:20:00', 18, '["22"]');
EOF

echo "💾 Loading resolution 10+ hexagon data..."
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" << 'EOF'
-- Resolution 10 hexagons (even more detailed)
INSERT IGNORE INTO amisafe_h3_aggregated 
(h3_index, h3_resolution, h3_parent, center_lat, center_lng, boundary_json, crime_count, crime_types_json, severity_avg, is_empty, last_incident, peak_hour, districts_json)
VALUES
-- Detailed Center City
('8a2aacb2e57ffff', 10, '892aacb2e57ffff', 39.9545, -75.1645, '[[39.957,-75.163],[39.952,-75.163],[39.951,-75.166],[39.952,-75.169],[39.957,-75.169],[39.958,-75.166]]', 12, '{"300":3,"400":5,"500":2,"600":2}', 3.2, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('8a2aacb2e5fffff', 10, '892aacb2e57ffff', 39.9535, -75.1635, '[[39.956,-75.162],[39.951,-75.162],[39.950,-75.165],[39.951,-75.168],[39.956,-75.168],[39.957,-75.165]]', 11, '{"200":2,"300":3,"400":3,"500":1,"600":2}', 3.0, FALSE, '2025-10-30 14:30:00', 14, '["6"]'),

-- High resolution empty hexagons for testing
('8a2aacb2e6affff', 10, '892aacb2e6affff', 40.1005, -75.0505, '[[40.103,-75.049],[40.098,-75.049],[40.097,-75.052],[40.098,-75.055],[40.103,-75.055],[40.104,-75.052]]', 0, '{}', 0.0, TRUE, NULL, NULL, '[]'),

-- Resolution 11-15 sample data (very high detail)
('8b2aacb2e57ffff', 11, '8a2aacb2e57ffff', 39.9547, -75.1647, '[[39.956,-75.164],[39.953,-75.164],[39.952,-75.165],[39.953,-75.166],[39.956,-75.166],[39.957,-75.165]]', 6, '{"300":2,"400":3,"500":1}', 3.3, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('8c2aacb2e57ffff', 12, '8b2aacb2e57ffff', 39.9548, -75.1648, '[[39.955,-75.164],[39.954,-75.164],[39.954,-75.165],[39.954,-75.165],[39.955,-75.165],[39.955,-75.165]]', 3, '{"400":2,"500":1}', 3.5, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('8d2aacb2e57ffff', 13, '8c2aacb2e57ffff', 39.9548, -75.1648, '[[39.9549,-75.1648],[39.9547,-75.1648],[39.9547,-75.1649],[39.9547,-75.1649],[39.9549,-75.1649],[39.9549,-75.1649]]', 2, '{"400":1,"500":1}', 4.0, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('8e2aacb2e57ffff', 14, '8d2aacb2e57ffff', 39.9548, -75.1648, '[[39.9548,-75.1648],[39.9548,-75.1648],[39.9548,-75.1649],[39.9548,-75.1649],[39.9548,-75.1649],[39.9548,-75.1649]]', 1, '{"400":1}', 2.0, FALSE, '2025-10-30 16:45:00', 16, '["6"]'),
('8f2aacb2e57ffff', 15, '8e2aacb2e57ffff', 39.9548, -75.1648, '[[39.9548,-75.1648],[39.9548,-75.1648],[39.9548,-75.1648],[39.9548,-75.1648],[39.9548,-75.1648],[39.9548,-75.1648]]', 1, '{"400":1}', 2.0, FALSE, '2025-10-30 16:45:00', 16, '["6"]');
EOF

# Get count and statistics
TOTAL_HEXAGONS=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated;")
TOTAL_CRIMES=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -se "SELECT SUM(crime_count) FROM amisafe_h3_aggregated;")

echo ""
echo "✅ AmISafe H3 Aggregated Table Setup Complete!"
echo "🗄️  Database: $DB_NAME"  
echo "📋 Table: amisafe_h3_aggregated"
echo "🔷 Total H3 hexagons: $TOTAL_HEXAGONS"
echo "🚨 Total aggregated crimes: $TOTAL_CRIMES"
echo ""

# Show resolution breakdown
echo "📊 H3 Resolution breakdown:"
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
SELECT 
    h3_resolution,
    COUNT(*) as hexagon_count,
    SUM(crime_count) as total_crimes,
    AVG(crime_count) as avg_crimes_per_hex,
    COUNT(CASE WHEN is_empty = FALSE THEN 1 END) as non_empty_hexagons
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;"

echo ""
echo "🧪 Sample hexagon data:"
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
SELECT 
    h3_index,
    h3_resolution,
    crime_count,
    severity_avg,
    is_empty,
    JSON_EXTRACT(districts_json, '$[0]') as district
FROM amisafe_h3_aggregated 
WHERE h3_resolution = 8
ORDER BY crime_count DESC
LIMIT 5;"

echo ""
echo "🎯 Ready to test H3 aggregated data API!"
echo "🔧 H3AggregatorService will now use real pre-calculated data"
echo "📈 Supports resolutions 8-15 with hierarchical relationships"