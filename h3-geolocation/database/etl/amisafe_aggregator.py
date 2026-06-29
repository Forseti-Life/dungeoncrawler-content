#!/usr/bin/env python3
"""
AmISafe Final Layer (Gold) Aggregator
Creates H3 aggregated analytics from the Transform layer data
Part of the 3-layer data warehouse architecture:
- Raw Layer (Bronze) -> Transform Layer (Silver) -> Final Layer (Gold) <- THIS SCRIPT

Integrated Functionality:
- Metro Area H3 Generation: generate_metro_area_h3_cells() for complete Philadelphia metro coverage
- H3 Incident ID Collection: JSON_ARRAYAGG(incident_id) for granular incident tracking
- Multi-resolution H3 Aggregation: Supports resolutions 5-13 for all scales
"""

import os
import sys
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import subprocess
import mysql.connector
from mysql.connector import Error
import h3
import json
import logging
from typing import List, Dict, Tuple, Optional
import argparse

class AmISafeFinalLayerAggregator:
    """
    Final Layer (Gold) processor for the AmISafe 3-layer data warehouse.
    Creates H3 aggregated analytics from clean Transform layer data.
    Supports H3 resolutions 5-10 for multi-scale visualization.
    """
    
    def __init__(self,
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'amisafe_database',
                 mysql_socket: str = None):
        """Initialize the Final Layer aggregator."""
        self.mysql_config = {
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Use socket if provided, otherwise use host
        if mysql_socket:
            self.mysql_config['unix_socket'] = mysql_socket
        else:
            self.mysql_config['host'] = mysql_host
        
        # Philadelphia metropolitan area bounds for metro-wide H3:5-7 coverage
        self.philly_metro_bounds = {
            'north': 41.0,    # Extends into New Jersey and suburbs
            'south': 39.5,    # South to Delaware County
            'east': -74.5,    # East to New Jersey
            'west': -76.0     # West to Lancaster County edge
        }
        
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
    # ========================================================================
    # GOLD LAYER COLUMN POPULATION INVENTORY
    # ========================================================================
    """
    COLUMN POPULATION STATUS:
    
    ✅ FULLY POPULATED COLUMNS (100% fill rate):
    - id: Auto-increment primary key (MySQL auto-generated)
    - h3_index: H3 hexagon identifier (from Silver layer h3_res_{resolution} columns)
    - h3_resolution: H3 resolution level 5-13 (aggregation parameter)
    - incident_count: COUNT(*) from Silver layer grouped by H3 index
    - unique_incident_types: COUNT(DISTINCT ucr_general) from Silver layer
    - earliest_incident: MIN(incident_datetime) from Silver layer
    - latest_incident: MAX(incident_datetime) from Silver layer 
    - incidents_last_30_days: COUNT with DATE_SUB filter for 30 days
    - incidents_last_year: COUNT with DATE_SUB filter for 1 year
    - center_latitude: AVG(lat) from Silver layer incidents in hexagon
    - center_longitude: AVG(lng) from Silver layer incidents in hexagon
    - incident_type_counts: JSON_OBJECT() placeholder (empty JSON)
    - district_counts: JSON_OBJECT() placeholder (empty JSON) 
    - total_valid_records: COUNT(*) duplicate of incident_count
    - last_aggregation: NOW() timestamp of aggregation processing
    - is_empty: MySQL default 0 (all current hexagons have incidents)
    
    🔄 PARTIALLY POPULATED COLUMNS:
    - incident_ids: JSON_ARRAYAGG(incident_id) only for H3:13 (41.2% fill rate)
    
    ❌ UNPOPULATED COLUMNS (0% fill rate - need functions):
    - severity_avg: Average crime severity score per hexagon
    - severity_max: Maximum severity in hexagon 
    - data_quality_avg: Data quality metrics per hexagon
    - top_crime_type: Most frequent crime type in hexagon
    - crime_diversity_index: Shannon diversity of crime types
    - incidents_by_hour: JSON array of hourly incident counts [0-23]
    - incidents_by_dow: JSON array of day-of-week counts [0-6] 
    - incidents_by_month: JSON array of monthly counts [1-12]
    - peak_hour: Hour with most incidents (0-23)
    - peak_dow: Day of week with most incidents (0-6)
    - h3_parent: Parent H3 index at resolution-1 
    - boundary_geojson: H3 hexagon boundary as GeoJSON
    - date_range_start: First incident date in hexagon
    - date_range_end: Last incident date in hexagon 
    - data_freshness_days: Days since last incident
    - aggregation_batch_id: Processing batch tracking identifier
    """
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    # Removed generate_metro_area_h3_cells() - now using Silver layer H3 indices for all resolutions

    def is_resolution_complete(self, connection, resolution: int) -> bool:
        """Check if a resolution is already complete by comparing expected vs actual hex count."""
        cursor = connection.cursor()
        
        # Get current count of hexagons for this resolution
        cursor.execute("""
        SELECT COUNT(*) FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        current_count = cursor.fetchone()[0]
        
        # For ALL resolutions, check against Silver layer H3 indices
        h3_column = f"h3_res_{resolution}"
        cursor.execute(f"""
        SELECT COUNT(DISTINCT {h3_column}) 
        FROM amisafe_clean_incidents 
        WHERE {h3_column} IS NOT NULL AND is_duplicate = FALSE
        """, ())
        expected_count = cursor.fetchone()[0]
        
        if expected_count > 0:
            self.logger.info(f"📊 Resolution {resolution}: {current_count}/{expected_count} hexagons exist")
            # Consider complete if we have at least 95% of expected cells
            completion_threshold = int(expected_count * 0.95)
            cursor.close()
            return current_count >= completion_threshold
        else:
            self.logger.info(f"📊 Resolution {resolution}: No source data available")
            cursor.close()
            return True  # No data to process, consider complete

    def create_h3_aggregations(self, connection, resolution: int):
        """Create H3 aggregations at specified resolution from Transform layer data."""
        self.logger.info(f"Creating H3 aggregations for resolution {resolution}")
        
        cursor = connection.cursor()
        
        # Check if this resolution is already complete
        if self.is_resolution_complete(connection, resolution):
            self.logger.info(f"✅ Resolution {resolution} is already complete, skipping...")
            return
        
        # Clear existing aggregations for this resolution
        cursor.execute("DELETE FROM amisafe_h3_aggregated WHERE h3_resolution = %s", (resolution,))
        
        # Use pre-calculated H3 indices from Silver layer for ALL resolutions
        # This eliminates the over-counting problem from spatial radius queries
        h3_column = f"h3_res_{resolution}"
        
        # Include incident_ids for H3:13 granular filtering
        if resolution >= 13:
            aggregation_query = f"""
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, incident_type_counts, district_counts,
                total_valid_records, last_aggregation, incident_ids
            )
            SELECT 
                {h3_column} as h3_index,
                %s as h3_resolution,
                COUNT(*) as incident_count,
                COUNT(DISTINCT ucr_general) as unique_incident_types,
                MIN(incident_datetime) as earliest_incident,
                MAX(incident_datetime) as latest_incident,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as incidents_last_30_days,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 1 YEAR) THEN 1 END) as incidents_last_year,
                AVG(lat) as center_latitude,
                AVG(lng) as center_longitude,
                JSON_OBJECT() as incident_type_counts,
                JSON_OBJECT() as district_counts,
                COUNT(*) as total_valid_records,
                NOW() as last_aggregation,
                JSON_ARRAYAGG(incident_id) as incident_ids
            FROM amisafe_clean_incidents 
            WHERE {h3_column} IS NOT NULL 
                AND is_duplicate = FALSE
            GROUP BY {h3_column}
            HAVING COUNT(*) > 0
            """
        else:
            aggregation_query = f"""
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, incident_type_counts, district_counts,
                total_valid_records, last_aggregation
            )
            SELECT 
                {h3_column} as h3_index,
                %s as h3_resolution,
                COUNT(*) as incident_count,
                COUNT(DISTINCT ucr_general) as unique_incident_types,
                MIN(incident_datetime) as earliest_incident,
                MAX(incident_datetime) as latest_incident,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as incidents_last_30_days,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 1 YEAR) THEN 1 END) as incidents_last_year,
                AVG(lat) as center_latitude,
                AVG(lng) as center_longitude,
                JSON_OBJECT() as incident_type_counts,
                JSON_OBJECT() as district_counts,
                COUNT(*) as total_valid_records,
                NOW() as last_aggregation
            FROM amisafe_clean_incidents 
            WHERE {h3_column} IS NOT NULL 
                AND is_duplicate = FALSE
            GROUP BY {h3_column}
            HAVING COUNT(*) > 0
            """
        
        cursor.execute(aggregation_query, (resolution,))
        rows_affected = cursor.rowcount
        self.logger.info(f"Created {rows_affected} H3:{resolution} aggregation records")
        
        cursor.close()
        
    # ========================================================================
    # EMPTY FUNCTIONS FOR UNPOPULATED COLUMNS - TODO: IMPLEMENT
    # ========================================================================
    
    def calculate_severity_metrics(self, connection, h3_index: str, resolution: int) -> Tuple[float, int]:
        """Calculate average and maximum severity for incidents in hexagon.
        
        TODO: Implement severity scoring based on:
        - UCR crime type severity weights
        - Incident outcome severity (arrests, injuries, property damage)
        - Time-of-day risk factors
        - Location risk factors
        
        Returns:
            Tuple[float, int]: (severity_avg, severity_max)
        """
        # TODO: Implement severity calculation logic
        return (0.0, 0)
    
    def calculate_data_quality_avg(self, connection, h3_index: str, resolution: int) -> float:
        """Calculate average data quality score for incidents in hexagon.
        
        TODO: Implement data quality scoring based on:
        - Completeness of required fields
        - Accuracy of geocoding
        - Consistency of crime type classification  
        - Timeliness of incident reporting
        
        Returns:
            float: Average data quality score (0.0-1.0)
        """
        # TODO: Implement data quality calculation logic
        return 0.0
    
    def fetch_hex_incidents(self, connection, h3_index: str, resolution: int, 
                            chunk_size: int = 100000) -> List[Dict]:
        """Fetch all incident data for a hexagon, using chunked queries for large datasets.
        
        For lower resolutions (5-9) that may contain millions of incidents per hex,
        fetches data in chunks to avoid memory issues.
        
        Args:
            connection: MySQL connection
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            chunk_size: Number of records to fetch per chunk (default 100k)
        
        Returns:
            List[Dict]: All incident records for this hexagon
        """
        cursor = connection.cursor(dictionary=True)
        h3_column = f"h3_res_{resolution}"
        
        try:
            # For lower resolutions (5-9), use chunked fetching
            if resolution <= 9:
                self.logger.info(f"📦 Chunked fetch for {h3_index} at resolution {resolution}")
                
                all_incidents = []
                offset = 0
                
                while True:
                    query = f"""
                    SELECT 
                        incident_id,
                        ucr_general,
                        dc_dist,
                        incident_datetime,
                        lat,
                        lng,
                        HOUR(incident_datetime) as hour_of_day,
                        WEEKDAY(incident_datetime) as day_of_week,
                        MONTH(incident_datetime) as month_num,
                        DATE(incident_datetime) as incident_date
                    FROM amisafe_clean_incidents 
                    WHERE {h3_column} = %s 
                        AND is_duplicate = FALSE
                        AND incident_datetime IS NOT NULL
                    LIMIT %s OFFSET %s
                    """
                    
                    cursor.execute(query, (h3_index, chunk_size, offset))
                    chunk = cursor.fetchall()
                    
                    if not chunk:
                        break
                    
                    all_incidents.extend(chunk)
                    offset += chunk_size
                    
                    if len(chunk) < chunk_size:
                        break
                    
                    self.logger.info(f"    Fetched {len(all_incidents):,} incidents so far...")
                
                self.logger.info(f"  ✅ Total: {len(all_incidents):,} incidents for {h3_index}")
                return all_incidents
            
            else:
                # For higher resolutions (10-13), single query is fine
                query = f"""
                SELECT 
                    incident_id,
                    ucr_general,
                    dc_dist,
                    incident_datetime,
                    lat,
                    lng,
                    HOUR(incident_datetime) as hour_of_day,
                    WEEKDAY(incident_datetime) as day_of_week,
                    MONTH(incident_datetime) as month_num,
                    DATE(incident_datetime) as incident_date
                FROM amisafe_clean_incidents 
                WHERE {h3_column} = %s 
                    AND is_duplicate = FALSE
                    AND incident_datetime IS NOT NULL
                """
                
                cursor.execute(query, (h3_index,))
                incidents = cursor.fetchall()
                
                return incidents
            
        except Exception as e:
            self.logger.error(f"Error fetching incidents for {h3_index}: {e}")
            return []
        finally:
            cursor.close()
    
    def calculate_analytics_from_incidents(self, incidents: List[Dict], h3_index: str, resolution: int) -> Dict:
        """Calculate all analytics from in-memory incident data.
        
        Args:
            incidents: List of incident dictionaries from Silver layer
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            
        Returns:
            Dict: All calculated analytical values
        """
        analytics = {
            'top_crime_type': None,
            'crime_diversity_index': 0.0,
            'incidents_by_hour': [0] * 24,
            'incidents_by_dow': [0] * 7,
            'incidents_by_month': [0] * 12,
            'peak_hour': None,
            'peak_dow': None,
            'h3_parent': None,
            'boundary_geojson': None,
            'date_range_start': None,
            'date_range_end': None,
            'data_freshness_days': None,
            'aggregation_batch_id': None,
            'incident_type_counts': {},
            'district_counts': {}
        }
        
        if not incidents:
            return analytics
            
        # Count crime types for top crime and diversity
        crime_counts = {}
        district_counts = {}
        dates = []
        
        for incident in incidents:
            # Crime type counting
            crime_type = incident.get('ucr_general')
            if crime_type:
                crime_counts[crime_type] = crime_counts.get(crime_type, 0) + 1
            
            # District counting
            district = incident.get('dc_dist')
            if district:
                district_counts[str(district)] = district_counts.get(str(district), 0) + 1
            
            # Temporal patterns
            hour = incident.get('hour_of_day')
            if hour is not None and 0 <= hour <= 23:
                analytics['incidents_by_hour'][hour] += 1
                
            dow = incident.get('day_of_week')
            if dow is not None and 0 <= dow <= 6:
                analytics['incidents_by_dow'][dow] += 1
                
            month = incident.get('month_num')
            if month is not None and 1 <= month <= 12:
                analytics['incidents_by_month'][month - 1] += 1  # 0-indexed
                
            # Date tracking
            if incident.get('incident_date'):
                dates.append(incident['incident_date'])
        
        # Store crime type and district counts
        analytics['incident_type_counts'] = crime_counts
        analytics['district_counts'] = district_counts
        
        # Calculate top crime type
        if crime_counts:
            analytics['top_crime_type'] = max(crime_counts.keys(), key=crime_counts.get)
            
            # Calculate Shannon diversity index
            if len(crime_counts) > 1:
                total_crimes = sum(crime_counts.values())
                shannon_index = 0.0
                
                for count in crime_counts.values():
                    if count > 0:
                        proportion = count / total_crimes
                        shannon_index -= proportion * np.log(proportion)
                
                analytics['crime_diversity_index'] = round(shannon_index, 3)
        
        # Find peak hour and day of week
        if any(analytics['incidents_by_hour']):
            analytics['peak_hour'] = analytics['incidents_by_hour'].index(max(analytics['incidents_by_hour']))
            
        if any(analytics['incidents_by_dow']):
            analytics['peak_dow'] = analytics['incidents_by_dow'].index(max(analytics['incidents_by_dow']))
        
        # Calculate H3 parent
        analytics['h3_parent'] = self.get_h3_parent(h3_index, resolution)
        
        # Generate boundary GeoJSON
        boundary = self.generate_boundary_geojson(h3_index)
        analytics['boundary_geojson'] = json.dumps(boundary) if boundary else None
        
        # Calculate date range and freshness
        if dates:
            analytics['date_range_start'] = min(dates).strftime('%Y-%m-%d')
            analytics['date_range_end'] = max(dates).strftime('%Y-%m-%d')
            
            from datetime import datetime
            freshness = (datetime.now().date() - max(dates)).days
            analytics['data_freshness_days'] = freshness
        
        # Generate batch ID (same for all records in this run)
        if not hasattr(self, '_current_batch_id'):
            self._current_batch_id = self.generate_batch_id()
        analytics['aggregation_batch_id'] = self._current_batch_id
        
        return analytics
    
    # Removed calculate_temporal_patterns() - now calculated in-memory from fetched data
    
    def get_h3_parent(self, h3_index: str, resolution: int) -> str:
        """Get parent H3 index at resolution-1 for hierarchical navigation.
        
        Returns:
            str: Parent H3 index or None if resolution=0
        """
        try:
            if resolution <= 0:
                return None
                
            # Get parent H3 cell at resolution-1
            parent_resolution = resolution - 1
            parent_index = h3.cell_to_parent(h3_index, parent_resolution)
            
            return parent_index
            
        except Exception as e:
            self.logger.error(f"Error getting H3 parent for {h3_index} at resolution {resolution}: {e}")
            return None
    
    def generate_boundary_geojson(self, h3_index: str) -> Dict:
        """Generate GeoJSON boundary for H3 hexagon.
        
        Returns:
            Dict: GeoJSON Polygon feature
        """
        try:
            # Get hexagon boundary vertices
            boundary = h3.cell_to_boundary(h3_index)
            
            # Convert to GeoJSON coordinates format [lng, lat]
            coordinates = []
            for lat, lng in boundary:
                coordinates.append([lng, lat])
            
            # Close the polygon by adding first point at the end
            if coordinates:
                coordinates.append(coordinates[0])
            
            # Create GeoJSON Polygon
            geojson = {
                "type": "Feature",
                "properties": {
                    "h3_index": h3_index,
                    "resolution": h3.get_resolution(h3_index)
                },
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [coordinates]
                }
            }
            
            return geojson
            
        except Exception as e:
            self.logger.error(f"Error generating boundary GeoJSON for {h3_index}: {e}")
            return None
    
    # Removed calculate_date_range_and_freshness() - now calculated in-memory from fetched data
    
    def calculate_analytics_from_incidents_with_stats(self, incidents: List[Dict], 
                                                       h3_index: str, resolution: int,
                                                       all_hex_stats: List[Dict] = None) -> Dict:
        """Calculate all analytics from in-memory incident data including statistical metrics.
        
        Args:
            incidents: List of incident dictionaries from Silver layer (already in memory)
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            all_hex_stats: Statistics for all hexagons (for z-scores/percentiles)
            
        Returns:
            Dict: All calculated analytical values ready for database update
        """
        try:
            # Calculate all basic analytics from in-memory data
            analytics = self.calculate_analytics_from_incidents(incidents, h3_index, resolution)
            
            # Calculate statistical metrics if we have population data
            if all_hex_stats:
                statistical_metrics = self.stats_calculator.calculate_complete_statistics(
                    incidents, all_hex_stats)
                analytics.update(statistical_metrics)
            
            # Convert arrays and dicts to JSON strings for database storage
            analytics['incident_type_counts'] = json.dumps(analytics['incident_type_counts'])
            analytics['district_counts'] = json.dumps(analytics['district_counts'])
            analytics['incidents_by_hour'] = json.dumps(analytics['incidents_by_hour'])
            analytics['incidents_by_dow'] = json.dumps(analytics['incidents_by_dow'])
            analytics['incidents_by_month'] = json.dumps(analytics['incidents_by_month'])
            
            # Convert temporal JSON arrays for windowed stats
            if 'incidents_by_hour_12mo' in analytics:
                analytics['incidents_by_hour_12mo'] = json.dumps(analytics['incidents_by_hour_12mo'])
                analytics['incidents_by_dow_12mo'] = json.dumps(analytics['incidents_by_dow_12mo'])
                analytics['incidents_by_month_12mo'] = json.dumps(analytics['incidents_by_month_12mo'])
            
            if 'incidents_by_hour_6mo' in analytics:
                analytics['incidents_by_hour_6mo'] = json.dumps(analytics['incidents_by_hour_6mo'])
                analytics['incidents_by_dow_6mo'] = json.dumps(analytics['incidents_by_dow_6mo'])
                analytics['incidents_by_month_6mo'] = json.dumps(analytics['incidents_by_month_6mo'])
            
            # TODO: Implement remaining functions
            analytics['severity_avg'] = None
            analytics['severity_max'] = None
            analytics['data_quality_avg'] = None
            
            return analytics
            
        except Exception as e:
            self.logger.error(f"Error in calculate_analytics_from_incidents_with_stats for {h3_index}: {e}")
            return {
                'severity_avg': None,
                'severity_max': None,
                'data_quality_avg': None,
                'top_crime_type': None,
                'crime_diversity_index': None,
                'incidents_by_hour': None,
                'incidents_by_dow': None,
                'incidents_by_month': None,
                'peak_hour': None,
                'peak_dow': None,
                'h3_parent': None,
                'boundary_geojson': None,
                'date_range_start': None,
                'date_range_end': None,
                'data_freshness_days': None,
                'aggregation_batch_id': None
            }
    
    def generate_batch_id(self) -> str:
        """Generate unique batch ID for aggregation tracking.
        
        Returns:
            str: Unique batch identifier
        """
        import uuid
        from datetime import datetime
        
        # Generate timestamp-based batch ID with UUID suffix for uniqueness
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        uuid_suffix = str(uuid.uuid4())[:8]
        
        return f"AGG_{timestamp}_{uuid_suffix}"
    
    def populate_advanced_analytics(self, connection, h3_index: str, resolution: int, all_hex_stats: List[Dict] = None) -> Dict:
        """Populate all advanced analytical columns for a hexagon.
        
        Uses single query approach: fetch all incident data once, calculate in memory.
        
        Args:
            connection: MySQL connection
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            all_hex_stats: Statistics for all hexagons (for z-scores/percentiles)
        
        Returns:
            Dict: All calculated analytical values
        """
        try:
            # Single query to fetch all incident data for this hexagon
            incidents = self.fetch_hex_incidents(connection, h3_index, resolution)
            
            # Calculate all analytics from in-memory data
            analytics = self.calculate_analytics_from_incidents(incidents, h3_index, resolution)
            
            # Calculate statistical metrics if we have population data
            if all_hex_stats:
                statistical_metrics = self.stats_calculator.calculate_complete_statistics(
                    incidents, all_hex_stats)
                analytics.update(statistical_metrics)
            
            # Convert arrays and dicts to JSON strings for database storage
            analytics['incident_type_counts'] = json.dumps(analytics['incident_type_counts'])
            analytics['district_counts'] = json.dumps(analytics['district_counts'])
            analytics['incidents_by_hour'] = json.dumps(analytics['incidents_by_hour'])
            analytics['incidents_by_dow'] = json.dumps(analytics['incidents_by_dow'])
            analytics['incidents_by_month'] = json.dumps(analytics['incidents_by_month'])
            
            # Convert temporal JSON arrays for windowed stats
            if 'incidents_by_hour_12mo' in analytics:
                analytics['incidents_by_hour_12mo'] = json.dumps(analytics['incidents_by_hour_12mo'])
                analytics['incidents_by_dow_12mo'] = json.dumps(analytics['incidents_by_dow_12mo'])
                analytics['incidents_by_month_12mo'] = json.dumps(analytics['incidents_by_month_12mo'])
            
            if 'incidents_by_hour_6mo' in analytics:
                analytics['incidents_by_hour_6mo'] = json.dumps(analytics['incidents_by_hour_6mo'])
                analytics['incidents_by_dow_6mo'] = json.dumps(analytics['incidents_by_dow_6mo'])
                analytics['incidents_by_month_6mo'] = json.dumps(analytics['incidents_by_month_6mo'])
            
            # TODO: Implement remaining functions
            analytics['severity_avg'] = None
            analytics['severity_max'] = None
            analytics['data_quality_avg'] = None
            
            return analytics
            
        except Exception as e:
            self.logger.error(f"Error in populate_advanced_analytics for {h3_index}: {e}")
            return {
                'severity_avg': None,
                'severity_max': None,
                'data_quality_avg': None,
                'top_crime_type': None,
                'crime_diversity_index': None,
                'incidents_by_hour': None,
                'incidents_by_dow': None,
                'incidents_by_month': None,
                'peak_hour': None,
                'peak_dow': None,
                'h3_parent': None,
                'boundary_geojson': None,
                'date_range_start': None,
                'date_range_end': None,
                'data_freshness_days': None,
                'aggregation_batch_id': None
            }
    
    def update_advanced_analytics(self, connection, resolution: int):
        """
        DEPRECATED: This Python-based analytics method has been replaced by SQL stored procedures.
        
        Use run_analytics.py instead, which calls sp_complete_all_windows() stored procedure.
        SQL stored procedures are 10-100x faster than this Python implementation.
        
        This method is kept for reference but will raise an error if called.
        """
        raise DeprecationWarning(
            "update_advanced_analytics() is deprecated. "
            "Use run_analytics.py with stored procedures instead: "
            "python etl/run_analytics.py --resolutions X"
        )

    # DEPRECATED METHOD - Replaced by SQL stored procedures (10-100x faster)
    # Use: python etl/run_analytics.py --resolutions X instead
    # This 300+ line Python implementation has been superseded by sp_complete_all_windows()

    def run_full_aggregation(self, resolutions: List[int] = [5, 6, 7, 8, 9, 10, 11, 12, 13]) -> Dict:
        """Run the complete Final Layer (Gold) aggregation pipeline."""
        self.logger.info(f"Starting Final Layer aggregation for H3 resolutions: {resolutions}")
        
        connection = self.connect_to_mysql()
        results = {}
        
        try:
            # Process each resolution
            for resolution in resolutions:
                self.logger.info(f"\n📊 Processing H3 Resolution {resolution}...")
                
                # Create H3 aggregations for this resolution
                self.create_h3_aggregations(connection, resolution)
                
                # Verify the aggregation
                verification = self.verify_aggregation(connection, resolution)
                results[resolution] = verification
            
            # Generate final analytics summary
            results['summary'] = self.generate_final_summary(connection)
            
            self.logger.info("Final Layer aggregation pipeline completed successfully")
            return results
            
        finally:
            if connection.is_connected():
                connection.close()
                
    def verify_aggregation(self, connection, resolution: int) -> Dict:
        """Verify the H3 aggregation for a given resolution."""
        cursor = connection.cursor()
        
        # Get aggregation statistics
        cursor.execute("""
        SELECT 
            COUNT(*) as total_hexagons,
            SUM(incident_count) as total_incidents,
            AVG(incident_count) as avg_incidents_per_hex,
            MIN(incident_count) as min_incidents,
            MAX(incident_count) as max_incidents,
            COUNT(CASE WHEN incident_count > 0 THEN 1 END) as non_empty_hexagons
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        
        stats = cursor.fetchone()
        cursor.close()
        
        # Get resolution description
        res_descriptions = {
            5: "Metro Area (~251km²)",
            6: "Districts (~36km²)", 
            7: "Neighborhoods (~5.2km²)",
            8: "Areas (~0.7km²)",
            9: "Blocks (~0.1km²)",
            10: "Sub-blocks (~15,047m²)"
        }
        desc = res_descriptions.get(resolution, f"Resolution {resolution}")
        
        result = {
            'resolution': resolution,
            'description': desc,
            'total_hexagons': stats[0] if stats else 0,
            'total_incidents': stats[1] if stats and stats[1] else 0,
            'avg_incidents_per_hex': float(stats[2]) if stats and stats[2] else 0.0,
            'min_incidents': stats[3] if stats else 0,
            'max_incidents': stats[4] if stats else 0,
            'non_empty_hexagons': stats[5] if stats else 0
        }
        
        self.logger.info(f"H3:{resolution} - {desc}: {result['total_hexagons']} hexagons, {result['total_incidents']} incidents")
        return result
        
    def generate_final_summary(self, connection) -> Dict:
        """Generate final summary statistics for the Gold layer."""
        cursor = connection.cursor()
        
        # Overall statistics
        cursor.execute("""
        SELECT 
            h3_resolution,
            COUNT(*) as hexagon_count,
            SUM(incident_count) as total_incidents
        FROM amisafe_h3_aggregated 
        GROUP BY h3_resolution
        ORDER BY h3_resolution
        """)
        
        resolution_stats = {}
        total_hexagons = 0
        total_incidents = 0
        
        for row in cursor.fetchall():
            resolution = row[0]
            hexagon_count = row[1]
            incident_count = row[2] if row[2] else 0
            
            resolution_stats[resolution] = {
                'hexagons': hexagon_count,
                'incidents': incident_count
            }
            total_hexagons += hexagon_count
            total_incidents += incident_count
        
        cursor.close()
        
        return {
            'total_hexagons_all_resolutions': total_hexagons,
            'total_incidents_all_resolutions': total_incidents,
            'resolution_breakdown': resolution_stats,
            'timestamp': datetime.now().isoformat()
        }


def main():
    """Main function to run the Final Layer (Gold) aggregator with analytics."""
    parser = argparse.ArgumentParser(
        description='Creates hexagons with basic aggregations and runs analytics for AmISafe H3 system'
    )
    parser.add_argument('--resolutions', nargs='+', type=int, 
                       default=[5, 6, 7, 8, 9, 10, 11, 12, 13],
                       help='H3 resolutions to process (default: 5-13)')
    parser.add_argument('--skip-analytics', action='store_true',
                       help='Skip analytics step (only create basic hexagon aggregations)')
    parser.add_argument('--mysql-host', default='127.0.0.1',
                       help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user',
                       help='MySQL user')
    parser.add_argument('--mysql-password', default=os.environ.get('DB_PASSWORD'),
                       help='MySQL password (from DB_PASSWORD env var)')
    parser.add_argument('--mysql-database', default='amisafe_database',
                       help='MySQL database name')
    parser.add_argument('--mysql-socket', default=None,
                       help='MySQL unix socket path (e.g., /var/run/mysqld/mysqld.sock)')
    
    args = parser.parse_args()
    
    if not args.mysql_password:
        print('ERROR: DB_PASSWORD environment variable is required')
        sys.exit(1)
    
    args = parser.parse_args()
    
    # Initialize Final Layer aggregator
    aggregator = AmISafeFinalLayerAggregator(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database,
        mysql_socket=args.mysql_socket
    )
    
    try:
        # Step 1: Run hexagon aggregation
        print(f"🚀 Starting Final Layer (Gold) aggregation for H3 resolutions: {args.resolutions}")
        print(f"   Creating hexagons with basic metrics...")
        results = aggregator.run_full_aggregation(args.resolutions)
        
        print(f"\n✅ Aggregation completed!")
        print("=" * 70)
        
        total_hexagons = 0
        total_incidents = 0
        
        for resolution in args.resolutions:
            if resolution in results:
                data = results[resolution]
                total_hexagons += data['total_hexagons']
                total_incidents += data['total_incidents']
                
                print(f"📊 H3:{resolution} - {data['description']}")
                print(f"   Hexagons: {data['total_hexagons']:,}")
                print(f"   Incidents: {data['total_incidents']:,}")
                print(f"   Non-empty: {data['non_empty_hexagons']:,}")
                print(f"   Avg per hex: {data['avg_incidents_per_hex']:.1f}")
                print()
        
        print(f"🎯 TOTAL: {total_hexagons:,} hexagons with {total_incidents:,} incidents")
        
        if 'summary' in results:
            summary = results['summary']
            print(f"📊 Multi-resolution H3 aggregation completed at {summary['timestamp']}")
        
        # Step 2: Run analytics via run_analytics.py
        if not args.skip_analytics:
            print(f"\n{'='*70}")
            print(f"🔬 Starting analytics enrichment (84 columns via stored procedures)...")
            print(f"{'='*70}")
            
            # Find run_analytics.py in same directory
            script_dir = os.path.dirname(os.path.abspath(__file__))
            analytics_script = os.path.join(script_dir, 'run_analytics.py')
            
            if not os.path.exists(analytics_script):
                print(f"⚠️  WARNING: {analytics_script} not found")
                print(f"   Analytics will be skipped.")
            else:
                # Build command with same resolutions
                cmd = [
                    sys.executable,  # Use same Python interpreter
                    analytics_script,
                    '--resolutions'
                ] + [str(r) for r in args.resolutions] + [
                    '--mysql-host', args.mysql_host,
                    '--mysql-user', args.mysql_user,
                    '--mysql-password', args.mysql_password,
                    '--mysql-database', args.mysql_database
                ]
                
                print(f"📊 Running: {' '.join(cmd[:3])} --resolutions {' '.join(map(str, args.resolutions))}")
                
                try:
                    result = subprocess.run(cmd, check=True)
                    print(f"\n✅ Analytics completed successfully!")
                except subprocess.CalledProcessError as e:
                    print(f"\n⚠️  Analytics failed with exit code {e.returncode}")
                    print(f"   Basic hexagons were created successfully.")
                    print(f"   You can run analytics manually: python {analytics_script} --resolutions {' '.join(map(str, args.resolutions))}")
        else:
            print(f"\n⏭️  Skipping analytics (--skip-analytics flag set)")
            print(f"   To add analytics later, run: python etl/run_analytics.py --resolutions {' '.join(map(str, args.resolutions))}")
        
        print(f"\n{'='*70}")
        print(f"🎯 SUCCESS: Complete workflow finished!")
        print(f"🗺️ Ready for AmISafe Crime Map visualization")
        
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()