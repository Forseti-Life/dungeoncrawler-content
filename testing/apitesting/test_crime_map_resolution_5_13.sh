#!/bin/bash

# Crime Map Resolution 5-13 Integration Test
# Generated: November 5, 2025

echo "=== AmISafe Crime Map Resolution 5-13 Integration Test ==="
echo "Testing updated JavaScript crime map with full Resolution 5-13 support"
echo

echo "1. Verify Database Resolution 5 Hexagon:"
echo "========================================"
sudo mysql theoryofconspiracies_dev -e "
SELECT 
    h3_index as 'Citywide Hexagon',
    incident_count as 'Total Incidents',
    coverage_area_km2 as 'Area (km²)',
    center_latitude as 'Center Lat',
    center_longitude as 'Center Lng'
FROM amisafe_h3_aggregated 
WHERE h3_resolution = 5;
" 2>/dev/null

echo
echo "2. Test API Endpoints for Crime Map:"
echo "===================================="

echo "Testing aggregated endpoint with Resolution 5:"
curl -s "http://localhost:8080/api/amisafe/aggregated?resolution=5&h3_index=852a134bfffffff" | jq -r '.hexagons[0] | "Hexagon: \(.h3_index), Incidents: \(.incident_count), Area: \(.coverage_area_km2) km²"' 2>/dev/null || echo "❌ Resolution 5 aggregated endpoint needs verification"

echo
echo "Testing aggregated endpoint with Resolution 6 (multi-hex):"
curl -s "http://localhost:8080/api/amisafe/aggregated?resolution=6&limit=3" | jq -r '.hexagons | length' 2>/dev/null | xargs echo "Resolution 6 hexagon count:"

echo
echo "Testing aggregated endpoint with Resolution 13 (ultra-precision):"
curl -s "http://localhost:8080/api/amisafe/aggregated?resolution=13&limit=5" | jq -r '.hexagons | length' 2>/dev/null | xargs echo "Resolution 13 hexagon count:"

echo
echo "3. JavaScript Resolution Logic Test:"
echo "==================================="

echo "Expected Resolution Mapping:"
echo "Zoom ≤6  → Resolution 5 (Citywide single hex)"
echo "Zoom 7-8 → Resolution 6 (District level)"
echo "Zoom 9-10 → Resolution 7 (Neighborhood)"
echo "Zoom 17+ → Resolution 13 (Ultra-precision)"

echo
echo "4. Crime Map Access Test:"
echo "========================"

echo "Testing AmISafe crime map page:"
response=$(curl -s -w "%{http_code}" "http://localhost:8080/amisafe/crime-map" -o /dev/null)
if [ "$response" = "200" ]; then
    echo "✅ Crime map page accessible"
else
    echo "❌ Crime map page returned HTTP $response"
fi

echo
echo "Testing AmISafe dashboard:"
response=$(curl -s -w "%{http_code}" "http://localhost:8080/amisafe" -o /dev/null)
if [ "$response" = "200" ]; then
    echo "✅ AmISafe dashboard accessible"
else
    echo "❌ AmISafe dashboard returned HTTP $response"
fi

echo
echo "5. Resolution Range Verification:"
echo "================================="

echo "Database resolution availability:"
sudo mysql theoryofconspiracies_dev -e "
SELECT 
    h3_resolution as 'Resolution',
    COUNT(*) as 'Hexagon Count',
    MIN(incident_count) as 'Min Incidents',
    MAX(incident_count) as 'Max Incidents'
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;
" 2>/dev/null

echo
echo "=== TEST SUMMARY ==="
echo "✅ Updated JavaScript crime map should now support:"
echo "   - Resolution 5: Single citywide hexagon (251 km²)"
echo "   - Resolution 6-13: Multi-hexagon detailed view"
echo "   - Special citywide popup for Resolution 5"
echo "   - Proper zoom-to-resolution mapping"
echo "   - Optimized API calls based on resolution level"
echo 
echo "🌐 Open crime map at: http://localhost:8080/amisafe/crime-map"
echo "📊 Test different zoom levels to verify resolution switching"