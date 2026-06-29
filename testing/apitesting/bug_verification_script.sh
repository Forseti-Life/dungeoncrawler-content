#!/bin/bash

# API Bug Verification Script
# Generated: November 5, 2025

echo "=== AmISafe API Bug Analysis ==="
echo "Testing hypothesis: API is summing incidents across all H3 resolution levels"
echo

echo "1. Total incidents in Silver layer (source truth):"
sudo mysql theoryofconspiracies_dev -e "SELECT COUNT(*) as actual_incidents FROM amisafe_clean_incidents;"

echo
echo "2. Total incidents per resolution level in Gold layer:"
sudo mysql theoryofconspiracies_dev -e "SELECT h3_resolution, SUM(incident_count) as incidents_at_resolution FROM amisafe_h3_aggregated GROUP BY h3_resolution ORDER BY h3_resolution;"

echo
echo "3. Sum across ALL resolution levels (this is the bug):"
sudo mysql theoryofconspiracies_dev -e "SELECT SUM(incident_count) as incorrect_total FROM amisafe_h3_aggregated;"

echo
echo "4. Current API output for comparison:"
curl -s "http://localhost:8080/api/amisafe/citywide-stats" | jq .stats.total_incidents

echo
echo "=== CONCLUSION ==="
echo "The API is summing incident_count across all resolution levels."
echo "Each incident is counted 8 times (once per resolution 6-13)."
echo "Correct approach: Use only ONE resolution level (preferably 13 for accuracy)."
echo "OR: Get count directly from Silver layer for absolute accuracy."