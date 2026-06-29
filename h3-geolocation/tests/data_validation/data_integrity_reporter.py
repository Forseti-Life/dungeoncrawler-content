#!/usr/bin/env python3
"""
H3 Pipeline Data Integrity Reporter

This module provides comprehensive data integrity reporting and validation
for the H3 geolocation pipeline across all three layers (Raw → Transform → Final).

Key Features:
- Record count validation across all pipeline layers
- Exclusion reason analysis and reporting
- H3 hexagon coverage validation at all resolutions
- Data quality metrics and trend analysis
- Pipeline efficiency and data flow reporting

Usage:
    python data_integrity_reporter.py --full-report
    python data_integrity_reporter.py --layer raw
    python data_integrity_reporter.py --exclusions-only
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import json
import logging
from datetime import datetime, date, timedelta
from typing import Dict, List, Tuple, Optional
import argparse
import sys
import os
from collections import defaultdict

# Add parent directories to path for imports
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from h3_framework import H3GeolocationFramework

class H3DataIntegrityReporter:
    """
    Comprehensive data integrity reporting for H3 pipeline validation.
    Analyzes data flow, exclusions, and validation across all pipeline layers.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the data integrity reporter."""
        if mysql_password is None:
            mysql_password = os.environ.get('DB_PASSWORD')
        if not mysql_password:
            raise ValueError('DB_PASSWORD environment variable is required or pass mysql_password')
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
        # Report data structure
        self.integrity_report = {
            'report_timestamp': datetime.now().isoformat(),
            'pipeline_layers': {},
            'data_flow_analysis': {},
            'exclusion_analysis': {},
            'h3_coverage_analysis': {},
            'quality_metrics': {},
            'recommendations': []
        }
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection with error handling."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def analyze_raw_layer(self, connection) -> Dict:
        """Analyze Raw layer data integrity and statistics."""
        self.logger.info("🔍 Analyzing Raw layer data integrity...")
        
        # Basic raw layer statistics
        raw_stats_query = """
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT source_file) as unique_files,
            COUNT(DISTINCT cartodb_id) as unique_cartodb_ids,
            COUNT(DISTINCT objectid) as unique_object_ids,
            COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as records_with_coordinates,
            COUNT(CASE WHEN dispatch_date_time IS NOT NULL THEN 1 END) as records_with_datetime,
            COUNT(CASE WHEN ucr_general IS NOT NULL THEN 1 END) as records_with_crime_type,
            COUNT(CASE WHEN dc_dist IS NOT NULL THEN 1 END) as records_with_district,
            MIN(ingestion_timestamp) as earliest_ingestion,
            MAX(ingestion_timestamp) as latest_ingestion,
            COUNT(CASE WHEN processing_status = 'raw' THEN 1 END) as unprocessed_records,
            COUNT(CASE WHEN processing_status = 'processed' THEN 1 END) as processed_records,
            COUNT(CASE WHEN processing_status = 'error' THEN 1 END) as error_records
        FROM amisafe_raw_incidents
        """
        
        # File-by-file breakdown
        file_breakdown_query = """
        SELECT 
            source_file,
            COUNT(*) as record_count,
            COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as valid_coordinates,
            COUNT(CASE WHEN dispatch_date_time IS NOT NULL THEN 1 END) as valid_datetime,
            COUNT(DISTINCT ucr_general) as unique_crime_types,
            COUNT(DISTINCT dc_dist) as unique_districts,
            MIN(dispatch_date_time) as earliest_incident,
            MAX(dispatch_date_time) as latest_incident
        FROM amisafe_raw_incidents
        GROUP BY source_file
        ORDER BY record_count DESC
        """
        
        # Geographic coverage
        geo_coverage_query = """
        SELECT 
            COUNT(CASE WHEN lat BETWEEN 39.867 AND 40.138 AND lng BETWEEN -75.280 AND -74.955 THEN 1 END) as philly_bounds_records,
            COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as total_coord_records,
            MIN(CAST(lat AS DECIMAL(10,8))) as min_lat,
            MAX(CAST(lat AS DECIMAL(10,8))) as max_lat,
            MIN(CAST(lng AS DECIMAL(11,8))) as min_lng,
            MAX(CAST(lng AS DECIMAL(11,8))) as max_lng,
            COUNT(DISTINCT dc_dist) as unique_districts,
            COUNT(DISTINCT ucr_general) as unique_crime_types
        FROM amisafe_raw_incidents
        WHERE lat IS NOT NULL AND lng IS NOT NULL
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get basic statistics
            cursor.execute(raw_stats_query)
            raw_stats = cursor.fetchone()
            
            # Get file breakdown
            cursor.execute(file_breakdown_query)
            file_breakdown = cursor.fetchall()
            
            # Get geographic coverage
            cursor.execute(geo_coverage_query)
            geo_coverage = cursor.fetchone()
            
            cursor.close()
            
            # Calculate data quality percentages
            total_records = raw_stats['total_records']
            coordinate_coverage = (raw_stats['records_with_coordinates'] / total_records * 100) if total_records > 0 else 0
            datetime_coverage = (raw_stats['records_with_datetime'] / total_records * 100) if total_records > 0 else 0
            crime_type_coverage = (raw_stats['records_with_crime_type'] / total_records * 100) if total_records > 0 else 0
            district_coverage = (raw_stats['records_with_district'] / total_records * 100) if total_records > 0 else 0
            
            # Philadelphia bounds coverage
            philly_coverage = 0
            if geo_coverage and geo_coverage['total_coord_records'] > 0:
                philly_coverage = (geo_coverage['philly_bounds_records'] / geo_coverage['total_coord_records'] * 100)
            
            raw_analysis = {
                'layer_name': 'Raw Layer (Bronze)',
                'total_records': total_records,
                'data_files': raw_stats['unique_files'],
                'processing_status': {
                    'unprocessed': raw_stats['unprocessed_records'],
                    'processed': raw_stats['processed_records'], 
                    'errors': raw_stats['error_records']
                },
                'data_quality': {
                    'coordinate_coverage_pct': round(coordinate_coverage, 2),
                    'datetime_coverage_pct': round(datetime_coverage, 2),
                    'crime_type_coverage_pct': round(crime_type_coverage, 2),
                    'district_coverage_pct': round(district_coverage, 2),
                    'philadelphia_bounds_pct': round(philly_coverage, 2)
                },
                'geographic_bounds': {
                    'lat_range': [float(geo_coverage['min_lat']), float(geo_coverage['max_lat'])] if geo_coverage['min_lat'] else [None, None],
                    'lng_range': [float(geo_coverage['min_lng']), float(geo_coverage['max_lng'])] if geo_coverage['min_lng'] else [None, None],
                    'unique_districts': geo_coverage['unique_districts'],
                    'unique_crime_types': geo_coverage['unique_crime_types']
                },
                'temporal_range': {
                    'ingestion_start': raw_stats['earliest_ingestion'].isoformat() if raw_stats['earliest_ingestion'] else None,
                    'ingestion_end': raw_stats['latest_ingestion'].isoformat() if raw_stats['latest_ingestion'] else None
                },
                'file_breakdown': file_breakdown[:10],  # Top 10 files
                'unique_identifiers': {
                    'cartodb_ids': raw_stats['unique_cartodb_ids'],
                    'object_ids': raw_stats['unique_object_ids']
                }
            }
            
            self.logger.info(f"✅ Raw layer analysis complete: {total_records:,} total records")
            return raw_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing Raw layer: {e}")
            return {'error': str(e)}
    
    def analyze_transform_layer(self, connection) -> Dict:
        """Analyze Transform layer data integrity and processing results."""
        self.logger.info("🔍 Analyzing Transform layer data integrity...")
        
        # Basic transform layer statistics
        transform_stats_query = """
        SELECT 
            COUNT(*) as total_records,
            COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid_records,
            COUNT(CASE WHEN is_valid = FALSE THEN 1 END) as invalid_records,
            COUNT(DISTINCT incident_id) as unique_incident_ids,
            COUNT(DISTINCT h3_res_6) as unique_h3_res6,
            COUNT(DISTINCT h3_res_7) as unique_h3_res7,
            COUNT(DISTINCT h3_res_8) as unique_h3_res8,
            COUNT(DISTINCT h3_res_9) as unique_h3_res9,
            COUNT(DISTINCT h3_res_10) as unique_h3_res10,
            COUNT(DISTINCT ucr_general) as unique_crime_types,
            COUNT(DISTINCT dc_dist) as unique_districts,
            AVG(data_quality_score) as avg_quality_score,
            MIN(incident_datetime) as earliest_incident,
            MAX(incident_datetime) as latest_incident
        FROM amisafe_clean_incidents
        """
        
        # H3 coverage analysis
        h3_coverage_query = """
        SELECT 
            h3_res_6, COUNT(*) as res6_count,
            h3_res_7, COUNT(*) as res7_count,
            h3_res_8, COUNT(*) as res8_count,
            h3_res_9, COUNT(*) as res9_count,
            h3_res_10, COUNT(*) as res10_count
        FROM amisafe_clean_incidents 
        WHERE is_valid = TRUE
        GROUP BY h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10
        ORDER BY res9_count DESC
        LIMIT 20
        """
        
        # Data quality distribution
        quality_distribution_query = """
        SELECT 
            CASE 
                WHEN data_quality_score >= 90 THEN 'Excellent (90-100)'
                WHEN data_quality_score >= 80 THEN 'Good (80-89)'
                WHEN data_quality_score >= 70 THEN 'Fair (70-79)'
                WHEN data_quality_score >= 60 THEN 'Poor (60-69)'
                ELSE 'Very Poor (<60)'
            END as quality_category,
            COUNT(*) as record_count,
            AVG(data_quality_score) as avg_score
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE
        GROUP BY quality_category
        ORDER BY avg_score DESC
        """
        
        # Crime type analysis
        crime_analysis_query = """
        SELECT 
            ucr_general,
            text_general_code,
            COUNT(*) as incident_count,
            COUNT(DISTINCT h3_res_9) as unique_hexagons,
            COUNT(DISTINCT dc_dist) as unique_districts,
            AVG(data_quality_score) as avg_quality
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE
        GROUP BY ucr_general, text_general_code
        ORDER BY incident_count DESC
        LIMIT 15
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get basic statistics
            cursor.execute(transform_stats_query)
            transform_stats = cursor.fetchone()
            
            # Get H3 coverage
            cursor.execute(h3_coverage_query)
            h3_coverage = cursor.fetchall()
            
            # Get quality distribution
            cursor.execute(quality_distribution_query)
            quality_distribution = cursor.fetchall()
            
            # Get crime analysis
            cursor.execute(crime_analysis_query)
            crime_analysis = cursor.fetchall()
            
            cursor.close()
            
            # Calculate processing efficiency
            total_records = transform_stats['total_records']
            valid_records = transform_stats['valid_records']
            processing_efficiency = (valid_records / total_records * 100) if total_records > 0 else 0
            
            transform_analysis = {
                'layer_name': 'Transform Layer (Silver)',
                'total_records': total_records,
                'valid_records': valid_records,
                'invalid_records': transform_stats['invalid_records'],
                'processing_efficiency_pct': round(processing_efficiency, 2),
                'h3_coverage': {
                    'resolution_6_hexagons': transform_stats['unique_h3_res6'],
                    'resolution_7_hexagons': transform_stats['unique_h3_res7'],
                    'resolution_8_hexagons': transform_stats['unique_h3_res8'],
                    'resolution_9_hexagons': transform_stats['unique_h3_res9'],
                    'resolution_10_hexagons': transform_stats['unique_h3_res10']
                },
                'data_dimensions': {
                    'unique_incidents': transform_stats['unique_incident_ids'],
                    'unique_crime_types': transform_stats['unique_crime_types'],
                    'unique_districts': transform_stats['unique_districts']
                },
                'quality_metrics': {
                    'average_quality_score': round(float(transform_stats['avg_quality_score']), 2) if transform_stats['avg_quality_score'] else 0,
                    'quality_distribution': quality_distribution
                },
                'temporal_coverage': {
                    'earliest_incident': transform_stats['earliest_incident'].isoformat() if transform_stats['earliest_incident'] else None,
                    'latest_incident': transform_stats['latest_incident'].isoformat() if transform_stats['latest_incident'] else None
                },
                'top_h3_hexagons': h3_coverage[:10],
                'crime_type_analysis': crime_analysis
            }
            
            self.logger.info(f"✅ Transform layer analysis complete: {valid_records:,} valid records ({processing_efficiency:.1f}% efficiency)")
            return transform_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing Transform layer: {e}")
            return {'error': str(e)}
    
    def analyze_final_layer(self, connection) -> Dict:
        """Analyze Final layer aggregations and H3 hexagon analytics."""
        self.logger.info("🔍 Analyzing Final layer H3 aggregations...")
        
        # Check if Final layer table exists
        table_exists_query = """
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = 'theoryofconspiracies_dev' 
        AND table_name = 'amisafe_h3_aggregated'
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(table_exists_query)
            table_check = cursor.fetchone()
            
            if table_check['table_exists'] == 0:
                cursor.close()
                return {
                    'layer_name': 'Final Layer (Gold)',
                    'status': 'NOT_CREATED',
                    'message': 'Final layer aggregation table does not exist',
                    'total_aggregations': 0,
                    'h3_resolutions': {},
                    'aggregation_periods': {}
                }
            
            # Basic final layer statistics
            final_stats_query = """
            SELECT 
                COUNT(*) as total_aggregations,
                COUNT(DISTINCT h3_index) as unique_h3_indexes,
                COUNT(DISTINCT h3_resolution) as unique_resolutions,
                SUM(total_incidents) as total_incidents_aggregated,
                AVG(risk_score) as avg_risk_score,
                MAX(total_incidents) as max_incidents_per_hex,
                MIN(total_incidents) as min_incidents_per_hex,
                AVG(total_incidents) as avg_incidents_per_hex
            FROM amisafe_h3_aggregated
            """
            
            # Resolution breakdown
            resolution_breakdown_query = """
            SELECT 
                h3_resolution,
                COUNT(*) as aggregation_count,
                COUNT(DISTINCT h3_index) as unique_hexagons,
                SUM(total_incidents) as total_incidents,
                AVG(risk_score) as avg_risk_score,
                MAX(total_incidents) as max_incidents_per_hex,
                COUNT(CASE WHEN hotspot_rank <= 10 THEN 1 END) as top_10_hotspots
            FROM amisafe_h3_aggregated
            GROUP BY h3_resolution
            ORDER BY h3_resolution
            """
            
            # Hotspot analysis
            hotspot_analysis_query = """
            SELECT 
                h3_index,
                h3_resolution,
                total_incidents,
                risk_score,
                hotspot_rank,
                violent_crime_count,
                property_crime_count,
                center_lat,
                center_lng
            FROM amisafe_h3_aggregated
            WHERE hotspot_rank <= 20
            ORDER BY hotspot_rank
            """
            
            cursor.execute(final_stats_query)
            final_stats = cursor.fetchone()
            
            if final_stats['total_aggregations'] == 0:
                cursor.close()
                return {
                    'layer_name': 'Final Layer (Gold)',
                    'status': 'EMPTY',
                    'message': 'Final layer table exists but contains no aggregations',
                    'total_aggregations': 0
                }
            
            cursor.execute(resolution_breakdown_query)
            resolution_breakdown = cursor.fetchall()
            
            cursor.execute(hotspot_analysis_query)
            hotspot_analysis = cursor.fetchall()
            
            cursor.close()
            
            final_analysis = {
                'layer_name': 'Final Layer (Gold)',
                'status': 'ACTIVE',
                'total_aggregations': final_stats['total_aggregations'],
                'unique_h3_indexes': final_stats['unique_h3_indexes'],
                'incidents_aggregated': final_stats['total_incidents_aggregated'],
                'aggregation_metrics': {
                    'avg_risk_score': round(float(final_stats['avg_risk_score']), 2) if final_stats['avg_risk_score'] else 0,
                    'max_incidents_per_hex': final_stats['max_incidents_per_hex'],
                    'min_incidents_per_hex': final_stats['min_incidents_per_hex'],
                    'avg_incidents_per_hex': round(float(final_stats['avg_incidents_per_hex']), 2) if final_stats['avg_incidents_per_hex'] else 0
                },
                'resolution_breakdown': {
                    f"resolution_{res['h3_resolution']}": {
                        'hexagon_count': res['unique_hexagons'],
                        'aggregation_count': res['aggregation_count'],
                        'total_incidents': res['total_incidents'],
                        'avg_risk_score': round(float(res['avg_risk_score']), 2) if res['avg_risk_score'] else 0,
                        'max_incidents': res['max_incidents_per_hex'],
                        'top_10_hotspots': res['top_10_hotspots']
                    } for res in resolution_breakdown
                },
                'top_hotspots': [
                    {
                        'h3_index': hotspot['h3_index'],
                        'resolution': hotspot['h3_resolution'],
                        'incidents': hotspot['total_incidents'],
                        'risk_score': round(float(hotspot['risk_score']), 2) if hotspot['risk_score'] else 0,
                        'rank': hotspot['hotspot_rank'],
                        'violent_crimes': hotspot['violent_crime_count'],
                        'property_crimes': hotspot['property_crime_count'],
                        'coordinates': [float(hotspot['center_lat']), float(hotspot['center_lng'])]
                    } for hotspot in hotspot_analysis
                ]
            }
            
            self.logger.info(f"✅ Final layer analysis complete: {final_stats['total_aggregations']:,} aggregations")
            return final_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing Final layer: {e}")
            return {'error': str(e)}
    
    def analyze_data_flow(self, connection) -> Dict:
        """Analyze data flow and record count validation across all layers."""
        self.logger.info("🔍 Analyzing data flow across pipeline layers...")
        
        # Get record counts from each layer
        layer_counts_query = """
        SELECT 
            'Raw' as layer,
            COUNT(*) as total_records,
            COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as records_with_coordinates,
            COUNT(CASE WHEN dispatch_date_time IS NOT NULL THEN 1 END) as records_with_datetime
        FROM amisafe_raw_incidents
        
        UNION ALL
        
        SELECT 
            'Transform' as layer,
            COUNT(*) as total_records,
            COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as records_with_coordinates,
            COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as records_with_datetime
        FROM amisafe_clean_incidents
        """
        
        # Calculate expected vs actual throughput
        throughput_analysis_query = """
        SELECT 
            raw_total.total as raw_total,
            raw_processable.processable as raw_processable,
            transform_total.total as transform_total,
            transform_valid.valid as transform_valid
        FROM 
            (SELECT COUNT(*) as total FROM amisafe_raw_incidents) raw_total,
            (SELECT COUNT(*) as processable FROM amisafe_raw_incidents 
             WHERE lat IS NOT NULL AND lng IS NOT NULL AND dispatch_date_time IS NOT NULL) raw_processable,
            (SELECT COUNT(*) as total FROM amisafe_clean_incidents) transform_total,
            (SELECT COUNT(*) as valid FROM amisafe_clean_incidents WHERE is_valid = TRUE) transform_valid
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            cursor.execute(layer_counts_query)
            layer_counts = cursor.fetchall()
            
            cursor.execute(throughput_analysis_query)
            throughput = cursor.fetchone()
            
            cursor.close()
            
            # Calculate flow metrics
            raw_total = throughput['raw_total']
            raw_processable = throughput['raw_processable']
            transform_total = throughput['transform_total']
            transform_valid = throughput['transform_valid']
            
            # Calculate percentages
            raw_completeness = (raw_processable / raw_total * 100) if raw_total > 0 else 0
            transform_processing_rate = (transform_total / raw_total * 100) if raw_total > 0 else 0
            transform_success_rate = (transform_valid / transform_total * 100) if transform_total > 0 else 0
            overall_throughput = (transform_valid / raw_total * 100) if raw_total > 0 else 0
            
            # Record loss analysis
            raw_to_transform_loss = raw_total - transform_total
            transform_internal_loss = transform_total - transform_valid
            total_loss = raw_total - transform_valid
            
            data_flow_analysis = {
                'pipeline_summary': {
                    'raw_layer_records': raw_total,
                    'raw_processable_records': raw_processable,
                    'transform_layer_records': transform_total,
                    'transform_valid_records': transform_valid,
                    'final_layer_aggregations': 0  # Will be updated if Final layer exists
                },
                'throughput_metrics': {
                    'raw_completeness_pct': round(raw_completeness, 2),
                    'transform_processing_rate_pct': round(transform_processing_rate, 2),
                    'transform_success_rate_pct': round(transform_success_rate, 2),
                    'overall_throughput_pct': round(overall_throughput, 2)
                },
                'record_loss_analysis': {
                    'raw_to_transform_loss': raw_to_transform_loss,
                    'transform_internal_loss': transform_internal_loss,
                    'total_pipeline_loss': total_loss,
                    'loss_percentage': round((total_loss / raw_total * 100), 2) if raw_total > 0 else 0
                },
                'layer_breakdown': [
                    {
                        'layer': row['layer'],
                        'total_records': row['total_records'],
                        'usable_records': row['records_with_coordinates']
                    } for row in layer_counts
                ]
            }
            
            self.logger.info(f"✅ Data flow analysis complete: {overall_throughput:.1f}% overall throughput")
            return data_flow_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing data flow: {e}")
            return {'error': str(e)}
    
    def analyze_exclusions(self, connection) -> Dict:
        """Analyze exclusion reasons and patterns in Transform layer processing."""
        self.logger.info("🔍 Analyzing exclusion patterns and reasons...")
        
        # This would require exclusion tracking to be implemented
        # For now, we'll analyze what we can from the existing data
        
        exclusion_analysis_query = """
        SELECT 
            'Invalid records' as exclusion_reason,
            COUNT(CASE WHEN is_valid = FALSE THEN 1 END) as count,
            'Records marked as invalid in Transform layer' as description
        FROM amisafe_clean_incidents
        
        UNION ALL
        
        SELECT 
            'Missing coordinates' as exclusion_reason,
            COUNT(*) as count,
            'Raw records without lat/lng coordinates' as description
        FROM amisafe_raw_incidents
        WHERE lat IS NULL OR lng IS NULL
        
        UNION ALL
        
        SELECT 
            'Missing datetime' as exclusion_reason,
            COUNT(*) as count,
            'Raw records without dispatch datetime' as description
        FROM amisafe_raw_incidents
        WHERE dispatch_date_time IS NULL
        
        UNION ALL
        
        SELECT 
            'Missing crime type' as exclusion_reason,
            COUNT(*) as count,
            'Raw records without UCR general code' as description
        FROM amisafe_raw_incidents
        WHERE ucr_general IS NULL OR ucr_general = ''
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(exclusion_analysis_query)
            exclusion_data = cursor.fetchall()
            cursor.close()
            
            exclusion_analysis = {
                'exclusion_categories': exclusion_data,
                'total_exclusions': sum(row['count'] for row in exclusion_data),
                'exclusion_recommendations': [
                    "Implement comprehensive exclusion reason tracking in Transform processor",
                    "Add detailed logging for each validation failure",
                    "Create exclusion reason lookup table for better reporting",
                    "Implement data quality improvement suggestions"
                ]
            }
            
            self.logger.info(f"✅ Exclusion analysis complete")
            return exclusion_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing exclusions: {e}")
            return {'error': str(e)}
    
    def generate_full_report(self) -> Dict:
        """Generate comprehensive data integrity report across all pipeline layers."""
        self.logger.info("📊 Generating comprehensive H3 pipeline data integrity report...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Analyze each layer
            raw_analysis = self.analyze_raw_layer(connection)
            transform_analysis = self.analyze_transform_layer(connection)
            final_analysis = self.analyze_final_layer(connection)
            data_flow_analysis = self.analyze_data_flow(connection)
            exclusion_analysis = self.analyze_exclusions(connection)
            
            # Update Final layer count in data flow if available
            if final_analysis.get('total_aggregations'):
                data_flow_analysis['pipeline_summary']['final_layer_aggregations'] = final_analysis['total_aggregations']
            
            # Build comprehensive report
            self.integrity_report.update({
                'pipeline_layers': {
                    'raw_layer': raw_analysis,
                    'transform_layer': transform_analysis,
                    'final_layer': final_analysis
                },
                'data_flow_analysis': data_flow_analysis,
                'exclusion_analysis': exclusion_analysis,
                'recommendations': self._generate_recommendations(raw_analysis, transform_analysis, final_analysis, data_flow_analysis)
            })
            
            self.logger.info("✅ Comprehensive data integrity report generated successfully")
            return self.integrity_report
            
        except Exception as e:
            self.logger.error(f"Error generating full report: {e}")
            return {'error': str(e)}
        finally:
            if connection and connection.is_connected():
                connection.close()
    
    def _generate_recommendations(self, raw_analysis, transform_analysis, final_analysis, data_flow_analysis) -> List[str]:
        """Generate recommendations based on analysis results."""
        recommendations = []
        
        # Raw layer recommendations
        if raw_analysis.get('data_quality', {}).get('coordinate_coverage_pct', 0) < 95:
            recommendations.append("Improve coordinate data quality - less than 95% coverage detected")
        
        # Transform layer recommendations
        transform_efficiency = transform_analysis.get('processing_efficiency_pct', 0)
        if transform_efficiency < 80:
            recommendations.append(f"Transform layer efficiency is {transform_efficiency:.1f}% - investigate exclusion reasons")
        
        # Final layer recommendations
        if final_analysis.get('status') == 'NOT_CREATED':
            recommendations.append("Final layer aggregation table not created - complete Final layer processing")
        elif final_analysis.get('status') == 'EMPTY':
            recommendations.append("Final layer exists but contains no aggregations - run aggregation processor")
        
        # Data flow recommendations
        overall_throughput = data_flow_analysis.get('throughput_metrics', {}).get('overall_throughput_pct', 0)
        if overall_throughput < 70:
            recommendations.append(f"Overall pipeline throughput is {overall_throughput:.1f}% - optimize data processing")
        
        if not recommendations:
            recommendations.append("Pipeline data integrity looks good - no major issues detected")
        
        return recommendations

def main():
    """Main execution function with command line interface."""
    parser = argparse.ArgumentParser(description='H3 Pipeline Data Integrity Reporter')
    parser.add_argument('--full-report', action='store_true', help='Generate full integrity report')
    parser.add_argument('--layer', choices=['raw', 'transform', 'final'], help='Analyze specific layer only')
    parser.add_argument('--exclusions-only', action='store_true', help='Analyze exclusions only')
    parser.add_argument('--output', default='console', choices=['console', 'json', 'file'], help='Output format')
    parser.add_argument('--file', help='Output file path (for file output)')
    
    args = parser.parse_args()
    
    # Initialize reporter
    reporter = H3DataIntegrityReporter()
    
    print("="*80)
    print("H3 PIPELINE DATA INTEGRITY REPORTER")
    print("="*80)
    print(f"Report Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*80)
    
    try:
        if args.full_report or not any([args.layer, args.exclusions_only]):
            # Generate full report
            report = reporter.generate_full_report()
            
            if args.output == 'json':
                print(json.dumps(report, indent=2))
            elif args.output == 'file' and args.file:
                with open(args.file, 'w') as f:
                    json.dump(report, f, indent=2)
                print(f"Report saved to: {args.file}")
            else:
                # Console output (formatted)
                print_formatted_report(report)
        
        elif args.layer:
            # Analyze specific layer
            connection = reporter.connect_to_mysql()
            if args.layer == 'raw':
                result = reporter.analyze_raw_layer(connection)
            elif args.layer == 'transform':
                result = reporter.analyze_transform_layer(connection)
            elif args.layer == 'final':
                result = reporter.analyze_final_layer(connection)
            connection.close()
            
            print(json.dumps(result, indent=2))
        
        elif args.exclusions_only:
            # Analyze exclusions only
            connection = reporter.connect_to_mysql()
            result = reporter.analyze_exclusions(connection)
            connection.close()
            
            print(json.dumps(result, indent=2))
            
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

def print_formatted_report(report):
    """Print formatted console report."""
    print("\n📊 PIPELINE OVERVIEW")
    print("-" * 50)
    
    # Data flow summary
    if 'data_flow_analysis' in report:
        flow = report['data_flow_analysis']['pipeline_summary']
        print(f"Raw Layer Records: {flow['raw_layer_records']:,}")
        print(f"Transform Valid Records: {flow['transform_valid_records']:,}")
        print(f"Final Layer Aggregations: {flow['final_layer_aggregations']:,}")
        
        throughput = report['data_flow_analysis']['throughput_metrics']
        print(f"Overall Throughput: {throughput['overall_throughput_pct']:.1f}%")
    
    print("\n🔍 LAYER ANALYSIS")
    print("-" * 50)
    
    # Layer summaries
    layers = report.get('pipeline_layers', {})
    for layer_name, layer_data in layers.items():
        if 'error' not in layer_data:
            print(f"\n{layer_data.get('layer_name', layer_name)}:")
            if 'total_records' in layer_data:
                print(f"  Total Records: {layer_data['total_records']:,}")
            if 'valid_records' in layer_data:
                print(f"  Valid Records: {layer_data['valid_records']:,}")
            if 'processing_efficiency_pct' in layer_data:
                print(f"  Efficiency: {layer_data['processing_efficiency_pct']:.1f}%")
    
    print("\n💡 RECOMMENDATIONS")
    print("-" * 50)
    for i, rec in enumerate(report.get('recommendations', []), 1):
        print(f"{i}. {rec}")
    
    print("\n" + "="*80)

if __name__ == "__main__":
    main()