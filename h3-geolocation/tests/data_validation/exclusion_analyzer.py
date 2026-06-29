#!/usr/bin/env python3
"""
H3 Pipeline Exclusion Analysis Tool

This module provides detailed analysis of record exclusions throughout the H3 pipeline,
identifying exactly where records are dropped and the specific reasons for exclusion.

Key Features:
- Record passthrough tracking from Raw → Transform → Final
- Detailed exclusion reason categorization
- Data quality gap analysis
- Pipeline optimization recommendations

Usage:
    python exclusion_analyzer.py --detailed
    python exclusion_analyzer.py --by-reason
    python exclusion_analyzer.py --recovery-analysis
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import json
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Optional
import argparse
import sys
import os

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

class H3ExclusionAnalyzer:
    """
    Detailed exclusion analysis for H3 pipeline data processing.
    Tracks record losses and exclusion reasons at each processing stage.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the exclusion analyzer."""
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
        
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
        # Philadelphia validation bounds (from Transform processor)
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # Expected districts (1-35)
        self.valid_districts = [str(i) for i in range(1, 36)]
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def analyze_raw_layer_exclusions(self, connection) -> Dict:
        """Analyze potential exclusions in Raw layer data."""
        self.logger.info("🔍 Analyzing Raw layer exclusion patterns...")
        
        # Comprehensive exclusion analysis
        exclusion_query = """
        SELECT 
            COUNT(*) as total_records,
            
            -- Coordinate issues
            COUNT(CASE WHEN lat IS NULL THEN 1 END) as missing_lat,
            COUNT(CASE WHEN lng IS NULL THEN 1 END) as missing_lng,
            COUNT(CASE WHEN lat IS NULL OR lng IS NULL THEN 1 END) as missing_coordinates,
            COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL 
                       AND (CAST(lat AS DECIMAL(10,8)) < 39.867 
                            OR CAST(lat AS DECIMAL(10,8)) > 40.138 
                            OR CAST(lng AS DECIMAL(11,8)) < -75.280 
                            OR CAST(lng AS DECIMAL(11,8)) > -74.955) 
                       THEN 1 END) as out_of_bounds_coordinates,
            
            -- Datetime issues
            COUNT(CASE WHEN dispatch_date_time IS NULL THEN 1 END) as missing_datetime,
            COUNT(CASE WHEN dispatch_date_time IS NOT NULL 
                       AND dispatch_date_time NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}' 
                       THEN 1 END) as invalid_datetime_format,
            
            -- Crime type issues
            COUNT(CASE WHEN ucr_general IS NULL OR ucr_general = '' THEN 1 END) as missing_crime_type,
            COUNT(CASE WHEN ucr_general IS NOT NULL 
                       AND ucr_general NOT IN ('100','200','300','400','500','600','700','800','900',
                                              '1100','1200','1300','1400','1500','1600','1700','1800','1900') 
                       THEN 1 END) as unknown_crime_type,
            
            -- District issues
            COUNT(CASE WHEN dc_dist IS NULL OR dc_dist = '' THEN 1 END) as missing_district,
            COUNT(CASE WHEN dc_dist IS NOT NULL 
                       AND dc_dist NOT BETWEEN '1' AND '35' 
                       THEN 1 END) as invalid_district,
            
            -- Identifier issues
            COUNT(CASE WHEN cartodb_id IS NULL THEN 1 END) as missing_cartodb_id,
            COUNT(CASE WHEN objectid IS NULL THEN 1 END) as missing_objectid,
            
            -- Text field issues
            COUNT(CASE WHEN location_block IS NULL OR location_block = '' THEN 1 END) as missing_location,
            COUNT(CASE WHEN text_general_code IS NULL OR text_general_code = '' THEN 1 END) as missing_description,
            
            -- Perfect records (no issues)
            COUNT(CASE WHEN lat IS NOT NULL 
                       AND lng IS NOT NULL
                       AND CAST(lat AS DECIMAL(10,8)) BETWEEN 39.867 AND 40.138
                       AND CAST(lng AS DECIMAL(11,8)) BETWEEN -75.280 AND -74.955
                       AND dispatch_date_time IS NOT NULL
                       AND dispatch_date_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}'
                       AND ucr_general IS NOT NULL 
                       AND ucr_general != ''
                       AND dc_dist IS NOT NULL 
                       AND dc_dist != ''
                       AND dc_dist BETWEEN '1' AND '35'
                       THEN 1 END) as perfect_records
        FROM amisafe_raw_incidents
        """
        
        # Duplicate analysis
        duplicate_query = """
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT cartodb_id) as unique_cartodb_ids,
            COUNT(*) - COUNT(DISTINCT cartodb_id) as cartodb_duplicates,
            COUNT(DISTINCT objectid) as unique_object_ids,
            COUNT(*) - COUNT(DISTINCT objectid) as objectid_duplicates,
            COUNT(DISTINCT CONCAT(COALESCE(lat,''), '-', COALESCE(lng,''), '-', 
                                 COALESCE(dispatch_date_time,''), '-', COALESCE(ucr_general,''))) as unique_composite,
            COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(lat,''), '-', COALESCE(lng,''), '-', 
                                           COALESCE(dispatch_date_time,''), '-', COALESCE(ucr_general,''))) as composite_duplicates
        FROM amisafe_raw_incidents
        WHERE cartodb_id IS NOT NULL AND objectid IS NOT NULL
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get exclusion data
            cursor.execute(exclusion_query)
            exclusions = cursor.fetchone()
            
            # Get duplicate data
            cursor.execute(duplicate_query)
            duplicates = cursor.fetchone()
            
            cursor.close()
            
            # Calculate exclusion percentages
            total = exclusions['total_records']
            
            raw_exclusion_analysis = {
                'total_raw_records': total,
                'exclusion_categories': {
                    'coordinate_issues': {
                        'missing_coordinates': exclusions['missing_coordinates'],
                        'out_of_bounds': exclusions['out_of_bounds_coordinates'],
                        'missing_lat': exclusions['missing_lat'],
                        'missing_lng': exclusions['missing_lng'],
                        'percentage': round((exclusions['missing_coordinates'] + exclusions['out_of_bounds_coordinates']) / total * 100, 2)
                    },
                    'datetime_issues': {
                        'missing_datetime': exclusions['missing_datetime'],
                        'invalid_format': exclusions['invalid_datetime_format'],
                        'percentage': round((exclusions['missing_datetime'] + exclusions['invalid_datetime_format']) / total * 100, 2)
                    },
                    'crime_type_issues': {
                        'missing_crime_type': exclusions['missing_crime_type'],
                        'unknown_crime_type': exclusions['unknown_crime_type'],
                        'percentage': round((exclusions['missing_crime_type'] + exclusions['unknown_crime_type']) / total * 100, 2)
                    },
                    'district_issues': {
                        'missing_district': exclusions['missing_district'],
                        'invalid_district': exclusions['invalid_district'],
                        'percentage': round((exclusions['missing_district'] + exclusions['invalid_district']) / total * 100, 2)
                    },
                    'identifier_issues': {
                        'missing_cartodb_id': exclusions['missing_cartodb_id'],
                        'missing_objectid': exclusions['missing_objectid'],
                        'percentage': round((exclusions['missing_cartodb_id'] + exclusions['missing_objectid']) / total * 100, 2)
                    }
                },
                'duplicate_analysis': {
                    'total_records': duplicates['total_records'],
                    'cartodb_duplicates': duplicates['cartodb_duplicates'],
                    'objectid_duplicates': duplicates['objectid_duplicates'],
                    'composite_duplicates': duplicates['composite_duplicates'],
                    'duplicate_percentage': round(duplicates['composite_duplicates'] / duplicates['total_records'] * 100, 2) if duplicates['total_records'] > 0 else 0
                },
                'perfect_records': {
                    'count': exclusions['perfect_records'],
                    'percentage': round(exclusions['perfect_records'] / total * 100, 2),
                    'expected_transform_throughput': exclusions['perfect_records']
                }
            }
            
            self.logger.info(f"✅ Raw layer exclusion analysis complete: {exclusions['perfect_records']:,} perfect records ({exclusions['perfect_records']/total*100:.1f}%)")
            return raw_exclusion_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing Raw layer exclusions: {e}")
            return {'error': str(e)}
    
    def analyze_transform_layer_exclusions(self, connection) -> Dict:
        """Analyze exclusions that occurred during Transform layer processing."""
        self.logger.info("🔍 Analyzing Transform layer exclusion patterns...")
        
        # Transform layer processing results
        transform_analysis_query = """
        SELECT 
            COUNT(*) as total_transform_records,
            COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid_records,
            COUNT(CASE WHEN is_valid = FALSE THEN 1 END) as invalid_records,
            
            -- H3 indexing success
            COUNT(CASE WHEN h3_res_6 IS NOT NULL THEN 1 END) as h3_res6_success,
            COUNT(CASE WHEN h3_res_7 IS NOT NULL THEN 1 END) as h3_res7_success,
            COUNT(CASE WHEN h3_res_8 IS NOT NULL THEN 1 END) as h3_res8_success,
            COUNT(CASE WHEN h3_res_9 IS NOT NULL THEN 1 END) as h3_res9_success,
            COUNT(CASE WHEN h3_res_10 IS NOT NULL THEN 1 END) as h3_res10_success,
            
            -- Data quality scores
            AVG(data_quality_score) as avg_quality_score,
            COUNT(CASE WHEN data_quality_score >= 80 THEN 1 END) as high_quality_records,
            COUNT(CASE WHEN data_quality_score < 60 THEN 1 END) as low_quality_records,
            
            -- Processing metadata
            COUNT(DISTINCT processing_batch_id) as unique_batches,
            MIN(incident_datetime) as earliest_incident,
            MAX(incident_datetime) as latest_incident
        FROM amisafe_clean_incidents
        """
        
        # Compare with Raw layer expectations
        raw_vs_transform_query = """
        SELECT 
            raw_counts.total as raw_total,
            raw_counts.processable as raw_processable,
            transform_counts.total as transform_total,
            transform_counts.valid as transform_valid
        FROM 
            (SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN lat IS NOT NULL 
                           AND lng IS NOT NULL
                           AND CAST(lat AS DECIMAL(10,8)) BETWEEN 39.867 AND 40.138
                           AND CAST(lng AS DECIMAL(11,8)) BETWEEN -75.280 AND -74.955
                           AND dispatch_date_time IS NOT NULL
                           AND ucr_general IS NOT NULL 
                           AND ucr_general != ''
                           AND dc_dist IS NOT NULL 
                           AND dc_dist != ''
                           THEN 1 END) as processable
             FROM amisafe_raw_incidents) raw_counts,
            (SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid
             FROM amisafe_clean_incidents) transform_counts
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get transform analysis
            cursor.execute(transform_analysis_query)
            transform_data = cursor.fetchone()
            
            # Get comparison data
            cursor.execute(raw_vs_transform_query)
            comparison = cursor.fetchone()
            
            cursor.close()
            
            # Calculate processing metrics
            total_transform = transform_data['total_transform_records']
            valid_transform = transform_data['valid_records']
            raw_processable = comparison['raw_processable']
            
            # Processing efficiency
            processing_efficiency = (total_transform / comparison['raw_total'] * 100) if comparison['raw_total'] > 0 else 0
            validation_success_rate = (valid_transform / total_transform * 100) if total_transform > 0 else 0
            expected_vs_actual = (valid_transform / raw_processable * 100) if raw_processable > 0 else 0
            
            # H3 indexing success rates
            h3_success_rates = {
                'resolution_6': (transform_data['h3_res6_success'] / valid_transform * 100) if valid_transform > 0 else 0,
                'resolution_7': (transform_data['h3_res7_success'] / valid_transform * 100) if valid_transform > 0 else 0,
                'resolution_8': (transform_data['h3_res8_success'] / valid_transform * 100) if valid_transform > 0 else 0,
                'resolution_9': (transform_data['h3_res9_success'] / valid_transform * 100) if valid_transform > 0 else 0,
                'resolution_10': (transform_data['h3_res10_success'] / valid_transform * 100) if valid_transform > 0 else 0
            }
            
            transform_exclusion_analysis = {
                'processing_summary': {
                    'total_processed': total_transform,
                    'valid_output': valid_transform,
                    'invalid_output': transform_data['invalid_records'],
                    'processing_efficiency_pct': round(processing_efficiency, 2),
                    'validation_success_rate_pct': round(validation_success_rate, 2)
                },
                'expected_vs_actual': {
                    'raw_processable_records': raw_processable,
                    'transform_valid_records': valid_transform,
                    'achievement_rate_pct': round(expected_vs_actual, 2),
                    'missing_records': raw_processable - valid_transform
                },
                'h3_indexing_success': {
                    'resolution_success_rates': {k: round(v, 2) for k, v in h3_success_rates.items()},
                    'average_success_rate': round(sum(h3_success_rates.values()) / len(h3_success_rates), 2)
                },
                'data_quality_analysis': {
                    'average_quality_score': round(float(transform_data['avg_quality_score']), 2) if transform_data['avg_quality_score'] else 0,
                    'high_quality_records': transform_data['high_quality_records'],
                    'low_quality_records': transform_data['low_quality_records'],
                    'high_quality_percentage': round(transform_data['high_quality_records'] / valid_transform * 100, 2) if valid_transform > 0 else 0
                },
                'processing_metadata': {
                    'unique_processing_batches': transform_data['unique_batches'],
                    'temporal_coverage': {
                        'earliest_incident': transform_data['earliest_incident'].isoformat() if transform_data['earliest_incident'] else None,
                        'latest_incident': transform_data['latest_incident'].isoformat() if transform_data['latest_incident'] else None
                    }
                }
            }
            
            self.logger.info(f"✅ Transform layer exclusion analysis complete: {validation_success_rate:.1f}% validation success rate")
            return transform_exclusion_analysis
            
        except Error as e:
            self.logger.error(f"Error analyzing Transform layer exclusions: {e}")
            return {'error': str(e)}
    
    def analyze_final_layer_expectations(self, connection) -> Dict:
        """Analyze expected vs actual Final layer aggregations."""
        self.logger.info("🔍 Analyzing Final layer aggregation expectations...")
        
        # Expected aggregations based on Transform layer
        expected_aggregations_query = """
        SELECT 
            COUNT(DISTINCT h3_res_6) as expected_res6_aggregations,
            COUNT(DISTINCT h3_res_7) as expected_res7_aggregations,
            COUNT(DISTINCT h3_res_8) as expected_res8_aggregations,
            COUNT(DISTINCT h3_res_9) as expected_res9_aggregations,
            COUNT(DISTINCT h3_res_10) as expected_res10_aggregations,
            COUNT(*) as total_incidents_to_aggregate
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE
        """
        
        # Check if Final layer exists and get actual counts
        final_layer_check = """
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = 'theoryofconspiracies_dev' 
        AND table_name = 'amisafe_h3_aggregated'
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get expected aggregations
            cursor.execute(expected_aggregations_query)
            expected = cursor.fetchone()
            
            # Check if Final layer exists
            cursor.execute(final_layer_check)
            table_exists = cursor.fetchone()['table_exists'] > 0
            
            actual_aggregations = {}
            if table_exists:
                # Get actual aggregations
                actual_query = """
                SELECT 
                    COUNT(CASE WHEN h3_resolution = 6 THEN 1 END) as actual_res6_aggregations,
                    COUNT(CASE WHEN h3_resolution = 7 THEN 1 END) as actual_res7_aggregations,
                    COUNT(CASE WHEN h3_resolution = 8 THEN 1 END) as actual_res8_aggregations,
                    COUNT(CASE WHEN h3_resolution = 9 THEN 1 END) as actual_res9_aggregations,
                    COUNT(CASE WHEN h3_resolution = 10 THEN 1 END) as actual_res10_aggregations,
                    SUM(total_incidents) as total_incidents_aggregated,
                    COUNT(*) as total_aggregation_records
                FROM amisafe_h3_aggregated
                """
                cursor.execute(actual_query)
                actual_aggregations = cursor.fetchone()
            
            cursor.close()
            
            # Calculate expected vs actual
            final_expectations = {
                'final_layer_status': 'EXISTS' if table_exists else 'MISSING',
                'expected_aggregations': {
                    'resolution_6': expected['expected_res6_aggregations'],
                    'resolution_7': expected['expected_res7_aggregations'],
                    'resolution_8': expected['expected_res8_aggregations'],
                    'resolution_9': expected['expected_res9_aggregations'],
                    'resolution_10': expected['expected_res10_aggregations'],
                    'total_incidents_to_aggregate': expected['total_incidents_to_aggregate']
                }
            }
            
            if table_exists and actual_aggregations:
                final_expectations['actual_aggregations'] = {
                    'resolution_6': actual_aggregations['actual_res6_aggregations'],
                    'resolution_7': actual_aggregations['actual_res7_aggregations'],
                    'resolution_8': actual_aggregations['actual_res8_aggregations'],
                    'resolution_9': actual_aggregations['actual_res9_aggregations'],
                    'resolution_10': actual_aggregations['actual_res10_aggregations'],
                    'total_incidents_aggregated': actual_aggregations['total_incidents_aggregated'],
                    'total_aggregation_records': actual_aggregations['total_aggregation_records']
                }
                
                # Calculate fulfillment rates
                fulfillment_rates = {}
                for res in ['6', '7', '8', '9', '10']:
                    expected_key = f'expected_res{res}_aggregations'
                    actual_key = f'actual_res{res}_aggregations'
                    expected_val = expected[expected_key]
                    actual_val = actual_aggregations[actual_key] if actual_aggregations else 0
                    fulfillment_rates[f'resolution_{res}'] = round((actual_val / expected_val * 100), 2) if expected_val > 0 else 0
                
                final_expectations['fulfillment_analysis'] = {
                    'resolution_fulfillment_rates': fulfillment_rates,
                    'average_fulfillment_rate': round(sum(fulfillment_rates.values()) / len(fulfillment_rates), 2),
                    'incident_aggregation_rate': round((actual_aggregations['total_incidents_aggregated'] / expected['total_incidents_to_aggregate'] * 100), 2) if expected['total_incidents_to_aggregate'] > 0 else 0
                }
            else:
                final_expectations['actual_aggregations'] = None
                final_expectations['fulfillment_analysis'] = {
                    'status': 'Final layer not processed',
                    'resolution_fulfillment_rates': {f'resolution_{i}': 0 for i in range(6, 11)},
                    'average_fulfillment_rate': 0,
                    'incident_aggregation_rate': 0
                }
            
            self.logger.info(f"✅ Final layer expectation analysis complete")
            return final_expectations
            
        except Error as e:
            self.logger.error(f"Error analyzing Final layer expectations: {e}")
            return {'error': str(e)}
    
    def generate_exclusion_report(self) -> Dict:
        """Generate comprehensive exclusion analysis report."""
        self.logger.info("📊 Generating comprehensive exclusion analysis report...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Analyze each layer
            raw_exclusions = self.analyze_raw_layer_exclusions(connection)
            transform_exclusions = self.analyze_transform_layer_exclusions(connection)
            final_expectations = self.analyze_final_layer_expectations(connection)
            
            # Build comprehensive exclusion report
            exclusion_report = {
                'report_timestamp': datetime.now().isoformat(),
                'pipeline_exclusion_analysis': {
                    'raw_layer_exclusions': raw_exclusions,
                    'transform_layer_exclusions': transform_exclusions,
                    'final_layer_expectations': final_expectations
                },
                'recovery_recommendations': self._generate_recovery_recommendations(raw_exclusions, transform_exclusions, final_expectations)
            }
            
            self.logger.info("✅ Comprehensive exclusion analysis report generated")
            return exclusion_report
            
        except Exception as e:
            self.logger.error(f"Error generating exclusion report: {e}")
            return {'error': str(e)}
        finally:
            if connection and connection.is_connected():
                connection.close()
    
    def _generate_recovery_recommendations(self, raw_exclusions, transform_exclusions, final_expectations) -> List[str]:
        """Generate recommendations for improving data recovery and reducing exclusions."""
        recommendations = []
        
        # Raw layer recommendations
        if 'exclusion_categories' in raw_exclusions:
            coord_issues = raw_exclusions['exclusion_categories']['coordinate_issues']['percentage']
            if coord_issues > 5:
                recommendations.append(f"Address coordinate issues: {coord_issues:.1f}% of records have coordinate problems")
            
            datetime_issues = raw_exclusions['exclusion_categories']['datetime_issues']['percentage']
            if datetime_issues > 5:
                recommendations.append(f"Fix datetime formatting: {datetime_issues:.1f}% of records have datetime issues")
        
        # Transform layer recommendations
        if 'expected_vs_actual' in transform_exclusions:
            achievement_rate = transform_exclusions['expected_vs_actual']['achievement_rate_pct']
            if achievement_rate < 90:
                recommendations.append(f"Transform processing efficiency: {achievement_rate:.1f}% - investigate processing losses")
        
        # Final layer recommendations
        if 'fulfillment_analysis' in final_expectations:
            avg_fulfillment = final_expectations['fulfillment_analysis']['average_fulfillment_rate']
            if avg_fulfillment < 100:
                recommendations.append(f"Final layer aggregation incomplete: {avg_fulfillment:.1f}% fulfillment rate")
        
        if not recommendations:
            recommendations.append("Exclusion rates are within acceptable ranges")
        
        return recommendations

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='H3 Pipeline Exclusion Analyzer')
    parser.add_argument('--detailed', action='store_true', help='Detailed exclusion analysis')
    parser.add_argument('--by-reason', action='store_true', help='Group exclusions by reason')
    parser.add_argument('--recovery-analysis', action='store_true', help='Focus on recovery recommendations')
    parser.add_argument('--output', default='console', choices=['console', 'json'], help='Output format')
    
    args = parser.parse_args()
    
    # Initialize analyzer
    analyzer = H3ExclusionAnalyzer()
    
    print("="*80)
    print("H3 PIPELINE EXCLUSION ANALYZER")
    print("="*80)
    print(f"Analysis Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*80)
    
    try:
        # Generate exclusion report
        report = analyzer.generate_exclusion_report()
        
        if args.output == 'json':
            print(json.dumps(report, indent=2))
        else:
            # Console formatted output
            print_exclusion_summary(report)
            
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

def print_exclusion_summary(report):
    """Print formatted exclusion summary."""
    if 'pipeline_exclusion_analysis' not in report:
        print("No exclusion analysis data available")
        return
    
    analysis = report['pipeline_exclusion_analysis']
    
    print("\n🔍 RAW LAYER EXCLUSIONS")
    print("-" * 50)
    if 'raw_layer_exclusions' in analysis:
        raw = analysis['raw_layer_exclusions']
        total = raw['total_raw_records']
        perfect = raw['perfect_records']['count']
        print(f"Total Raw Records: {total:,}")
        print(f"Perfect Records: {perfect:,} ({perfect/total*100:.1f}%)")
        
        for category, data in raw['exclusion_categories'].items():
            if data['percentage'] > 0:
                print(f"  {category.replace('_', ' ').title()}: {data['percentage']:.1f}%")
    
    print("\n🔍 TRANSFORM LAYER PROCESSING")
    print("-" * 50)
    if 'transform_layer_exclusions' in analysis:
        transform = analysis['transform_layer_exclusions']
        processing = transform['processing_summary']
        print(f"Processed Records: {processing['total_processed']:,}")
        print(f"Valid Output: {processing['valid_output']:,}")
        print(f"Processing Efficiency: {processing['processing_efficiency_pct']:.1f}%")
        print(f"Validation Success Rate: {processing['validation_success_rate_pct']:.1f}%")
    
    print("\n🔍 FINAL LAYER EXPECTATIONS")
    print("-" * 50)
    if 'final_layer_expectations' in analysis:
        final = analysis['final_layer_expectations']
        print(f"Final Layer Status: {final['final_layer_status']}")
        
        if 'fulfillment_analysis' in final:
            fulfillment = final['fulfillment_analysis']
            if 'average_fulfillment_rate' in fulfillment:
                print(f"Average Fulfillment Rate: {fulfillment['average_fulfillment_rate']:.1f}%")
    
    print("\n💡 RECOVERY RECOMMENDATIONS")
    print("-" * 50)
    for i, rec in enumerate(report.get('recovery_recommendations', []), 1):
        print(f"{i}. {rec}")
    
    print("\n" + "="*80)

if __name__ == "__main__":
    main()