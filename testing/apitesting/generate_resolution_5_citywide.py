#!/usr/bin/env python3
"""
Generate Resolution 5 Citywide Hexagon for Philadelphia
Creates a single hexagon that encompasses the entire city for citywide statistics.
"""

import os
import mysql.connector
import h3
from datetime import datetime

def create_resolution_5_hexagon():
    """Generate and insert resolution 5 hexagon data"""
    
    # Philadelphia resolution 5 hexagon details
    res5_hex = "852a134bfffffff"
    
    # Resolution 6 children hexagons (all 7 that make up Philadelphia)
    children_hex = [
        '862a13487ffffff', '862a1348fffffff', '862a13497ffffff', 
        '862a1349fffffff', '862a134a7ffffff', '862a134afffffff', 
        '862a134b7ffffff'
    ]
    
    try:
        # Connect to database
        db_host = os.environ.get('DB_HOST', 'localhost')
        db_name = os.environ.get('DB_NAME')
        db_user = os.environ.get('DB_USER')
        db_password = os.environ.get('DB_PASSWORD')

        if not db_name or not db_user or not db_password:
            raise ValueError(
                'DB_NAME, DB_USER, and DB_PASSWORD must be set in the environment.'
            )

        connection = mysql.connector.connect(
            host=db_host,
            database=db_name,
            user=db_user,
            password=db_password
        )
        
        cursor = connection.cursor()
        
        # Get aggregated data from the 7 resolution 6 hexagons
        placeholders = ', '.join(['%s'] * len(children_hex))
        query = f"""
        SELECT 
            SUM(incident_count) as total_incidents,
            COUNT(*) as child_hexagons,
            SUM(unique_incident_types) as total_unique_types,
            MIN(earliest_incident) as earliest,
            MAX(latest_incident) as latest,
            SUM(incidents_last_30_days) as last_30_days,
            SUM(incidents_last_year) as last_year,
            AVG(center_latitude) as avg_lat,
            AVG(center_longitude) as avg_lng,
            SUM(total_valid_records) as total_valid,
            SUM(total_invalid_records) as total_invalid
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = 6 AND h3_index IN ({placeholders})
        """
        
        cursor.execute(query, tuple(children_hex))
        result = cursor.fetchone()
        
        if not result:
            print("No data found for resolution 6 hexagons")
            return
        
        # Get H3 center coordinates
        center_lat, center_lng = h3.cell_to_latlng(res5_hex)
        area_km2 = h3.cell_area(res5_hex, unit='km^2')
        
        # Prepare data for insertion
        aggregated_data = {
            'h3_index': res5_hex,
            'h3_resolution': 5,
            'incident_count': result[0],
            'unique_incident_types': min(result[2] or 0, 26),  # Cap at max unique types
            'earliest_incident': result[3],
            'latest_incident': result[4],
            'incidents_last_30_days': result[5],
            'incidents_last_year': result[6],
            'center_latitude': center_lat,
            'center_longitude': center_lng,
            'coverage_area_km2': area_km2,
            'total_valid_records': result[9] or 0,
            'total_invalid_records': result[10] or 0,
            'last_aggregation': datetime.now(),
            'source_record_count': result[1],  # Number of child hexagons
            'aggregation_method': 'resolution_5_citywide'
        }
        
        # Check if resolution 5 record already exists
        check_query = "SELECT id FROM amisafe_h3_aggregated WHERE h3_index = %s"
        cursor.execute(check_query, (res5_hex,))
        existing = cursor.fetchone()
        
        if existing:
            print(f"Resolution 5 hexagon {res5_hex} already exists. Updating...")
            update_query = """
            UPDATE amisafe_h3_aggregated SET
                incident_count = %s,
                unique_incident_types = %s,
                earliest_incident = %s,
                latest_incident = %s,
                incidents_last_30_days = %s,
                incidents_last_year = %s,
                center_latitude = %s,
                center_longitude = %s,
                coverage_area_km2 = %s,
                total_valid_records = %s,
                total_invalid_records = %s,
                last_aggregation = %s,
                source_record_count = %s,
                aggregation_method = %s
            WHERE h3_index = %s
            """
            cursor.execute(update_query, (
                aggregated_data['incident_count'],
                aggregated_data['unique_incident_types'],
                aggregated_data['earliest_incident'],
                aggregated_data['latest_incident'],
                aggregated_data['incidents_last_30_days'],
                aggregated_data['incidents_last_year'],
                aggregated_data['center_latitude'],
                aggregated_data['center_longitude'],
                aggregated_data['coverage_area_km2'],
                aggregated_data['total_valid_records'],
                aggregated_data['total_invalid_records'],
                aggregated_data['last_aggregation'],
                aggregated_data['source_record_count'],
                aggregated_data['aggregation_method'],
                res5_hex
            ))
        else:
            print(f"Inserting new resolution 5 hexagon {res5_hex}...")
            insert_query = """
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, coverage_area_km2,
                total_valid_records, total_invalid_records, last_aggregation,
                source_record_count, aggregation_method
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(insert_query, (
                aggregated_data['h3_index'],
                aggregated_data['h3_resolution'],
                aggregated_data['incident_count'],
                aggregated_data['unique_incident_types'],
                aggregated_data['earliest_incident'],
                aggregated_data['latest_incident'],
                aggregated_data['incidents_last_30_days'],
                aggregated_data['incidents_last_year'],
                aggregated_data['center_latitude'],
                aggregated_data['center_longitude'],
                aggregated_data['coverage_area_km2'],
                aggregated_data['total_valid_records'],
                aggregated_data['total_invalid_records'],
                aggregated_data['last_aggregation'],
                aggregated_data['source_record_count'],
                aggregated_data['aggregation_method']
            ))
        
        connection.commit()
        
        print("Resolution 5 Citywide Hexagon Generated Successfully!")
        print(f"Hexagon ID: {res5_hex}")
        print(f"Total Incidents: {aggregated_data['incident_count']:,}")
        print(f"Coverage Area: {aggregated_data['coverage_area_km2']:.2f} km²")
        print(f"Center: {aggregated_data['center_latitude']:.6f}, {aggregated_data['center_longitude']:.6f}")
        print(f"Child Hexagons: {aggregated_data['source_record_count']}")
        
    except mysql.connector.Error as error:
        print(f"Database error: {error}")
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()

if __name__ == "__main__":
    create_resolution_5_hexagon()