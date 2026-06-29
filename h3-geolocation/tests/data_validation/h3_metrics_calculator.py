#!/usr/bin/env python3
"""
H3 Metrics Calculator and Validator

This module calculates and validates H3 hexagon metrics across all resolutions,
providing detailed analysis of spatial coverage, incident density, and aggregation accuracy.

Key Features:
- Expected H3 hexagon counts based on Philadelphia geography
- Actual H3 coverage analysis from Transform layer
- Incident distribution validation across hexagon resolutions
- Spatial coverage efficiency metrics
- H3 indexing accuracy validation

Usage:
    python h3_metrics_calculator.py --coverage-analysis
    python h3_metrics_calculator.py --resolution 9
    python h3_metrics_calculator.py --validate-indexing
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import h3
import json
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Optional
import argparse
import sys
import os
import math

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from h3_framework import H3GeolocationFramework

class H3MetricsCalculator:
    """
    H3 hexagon metrics calculator and validator for spatial analysis validation.
    Provides comprehensive analysis of H3 coverage, density, and indexing accuracy.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the H3 metrics calculator."""
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
        
        # Philadelphia geographic bounds
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # H3 resolution properties
        self.h3_resolution_properties = {
            6: {'avg_hex_area_km2': 36.129, 'avg_hex_edge_m': 3229.482},
            7: {'avg_hex_area_km2': 5.161, 'avg_hex_edge_m': 1220.629},
            8: {'avg_hex_area_km2': 0.737, 'avg_hex_edge_m': 461.354},
            9: {'avg_hex_area_km2': 0.105, 'avg_hex_edge_m': 174.375},
            10: {'avg_hex_area_km2': 0.015, 'avg_hex_edge_m': 65.907}
        }
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def calculate_philadelphia_h3_coverage(self) -> Dict:
        """Calculate expected H3 hexagon coverage for Philadelphia area."""
        self.logger.info("🔍 Calculating expected H3 coverage for Philadelphia area...")
        
        # Philadelphia area calculation
        lat_range = self.philly_bounds['lat_max'] - self.philly_bounds['lat_min']
        lng_range = self.philly_bounds['lng_max'] - self.philly_bounds['lng_min']
        
        # Approximate area calculation (rough estimate)
        # This is a simplified calculation - actual area would require more precise geographic calculations
        area_deg_sq = lat_range * lng_range
        area_km2_approx = area_deg_sq * 111 * 111 * math.cos(math.radians((self.philly_bounds['lat_min'] + self.philly_bounds['lat_max']) / 2))
        
        # Calculate expected hexagon counts for each resolution
        expected_coverage = {
            'philadelphia_bounds': self.philly_bounds,
            'estimated_area_km2': round(area_km2_approx, 2),
            'expected_hexagon_counts': {}
        }
        
        for resolution in range(6, 11):
            hex_area_km2 = self.h3_resolution_properties[resolution]['avg_hex_area_km2']
            expected_hex_count = int(area_km2_approx / hex_area_km2)
            
            expected_coverage['expected_hexagon_counts'][f'resolution_{resolution}'] = {
                'estimated_hexagon_count': expected_hex_count,
                'hex_area_km2': hex_area_km2,
                'hex_edge_m': self.h3_resolution_properties[resolution]['avg_hex_edge_m']
            }
        
        self.logger.info(f"✅ Philadelphia H3 coverage calculated: ~{area_km2_approx:.1f} km²")
        return expected_coverage
    
    def analyze_actual_h3_coverage(self, connection) -> Dict:
        """Analyze actual H3 coverage from Transform layer data."""
        self.logger.info("🔍 Analyzing actual H3 coverage from Transform layer...")
        
        # Get actual H3 coverage statistics
        coverage_query = """
        SELECT 
            COUNT(DISTINCT h3_res_6) as unique_res6_hexagons,
            COUNT(DISTINCT h3_res_7) as unique_res7_hexagons,
            COUNT(DISTINCT h3_res_8) as unique_res8_hexagons,
            COUNT(DISTINCT h3_res_9) as unique_res9_hexagons,
            COUNT(DISTINCT h3_res_10) as unique_res10_hexagons,
            COUNT(*) as total_incidents,
            COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid_incidents,
            
            -- Geographic bounds check
            MIN(lat) as min_lat,
            MAX(lat) as max_lat,
            MIN(lng) as min_lng,
            MAX(lng) as max_lng,
            
            -- Incident distribution per resolution
            COUNT(*) / COUNT(DISTINCT h3_res_6) as avg_incidents_per_res6_hex,
            COUNT(*) / COUNT(DISTINCT h3_res_7) as avg_incidents_per_res7_hex,
            COUNT(*) / COUNT(DISTINCT h3_res_8) as avg_incidents_per_res8_hex,
            COUNT(*) / COUNT(DISTINCT h3_res_9) as avg_incidents_per_res9_hex,
            COUNT(*) / COUNT(DISTINCT h3_res_10) as avg_incidents_per_res10_hex
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE
        """
        
        # Get hexagon incident distribution
        distribution_query = """
        SELECT 
            'Resolution 6' as resolution,
            h3_res_6 as h3_index,
            COUNT(*) as incident_count,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE AND h3_res_6 IS NOT NULL
        GROUP BY h3_res_6
        ORDER BY incident_count DESC
        LIMIT 10
        
        UNION ALL
        
        SELECT 
            'Resolution 7' as resolution,
            h3_res_7 as h3_index,
            COUNT(*) as incident_count,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE AND h3_res_7 IS NOT NULL
        GROUP BY h3_res_7
        ORDER BY incident_count DESC
        LIMIT 10
        
        UNION ALL
        
        SELECT 
            'Resolution 8' as resolution,
            h3_res_8 as h3_index,
            COUNT(*) as incident_count,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE AND h3_res_8 IS NOT NULL
        GROUP BY h3_res_8
        ORDER BY incident_count DESC
        LIMIT 10
        
        UNION ALL
        
        SELECT 
            'Resolution 9' as resolution,
            h3_res_9 as h3_index,
            COUNT(*) as incident_count,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE AND h3_res_9 IS NOT NULL
        GROUP BY h3_res_9
        ORDER BY incident_count DESC
        LIMIT 10
        
        UNION ALL
        
        SELECT 
            'Resolution 10' as resolution,
            h3_res_10 as h3_index,
            COUNT(*) as incident_count,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE AND h3_res_10 IS NOT NULL
        GROUP BY h3_res_10
        ORDER BY incident_count DESC
        LIMIT 10
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get coverage statistics
            cursor.execute(coverage_query)
            coverage_stats = cursor.fetchone()
            
            # Get distribution data
            cursor.execute(distribution_query)
            distribution_data = cursor.fetchall()
            
            cursor.close()
            
            # Organize distribution data by resolution
            distribution_by_resolution = {}
            for row in distribution_data:
                res = row['resolution']
                if res not in distribution_by_resolution:
                    distribution_by_resolution[res] = []
                distribution_by_resolution[res].append({
                    'h3_index': row['h3_index'],
                    'incident_count': row['incident_count'],
                    'center_coordinates': [float(row['center_lat']), float(row['center_lng'])]
                })
            
            # Build actual coverage analysis
            actual_coverage = {
                'data_summary': {
                    'total_incidents': coverage_stats['total_incidents'],
                    'valid_incidents': coverage_stats['valid_incidents'],
                    'geographic_bounds': {
                        'lat_range': [float(coverage_stats['min_lat']), float(coverage_stats['max_lat'])],
                        'lng_range': [float(coverage_stats['min_lng']), float(coverage_stats['max_lng'])]
                    }
                },
                'actual_hexagon_counts': {
                    'resolution_6': coverage_stats['unique_res6_hexagons'],
                    'resolution_7': coverage_stats['unique_res7_hexagons'],
                    'resolution_8': coverage_stats['unique_res8_hexagons'],
                    'resolution_9': coverage_stats['unique_res9_hexagons'],
                    'resolution_10': coverage_stats['unique_res10_hexagons']
                },
                'incident_density_per_hexagon': {
                    'resolution_6': round(float(coverage_stats['avg_incidents_per_res6_hex']), 2) if coverage_stats['avg_incidents_per_res6_hex'] else 0,
                    'resolution_7': round(float(coverage_stats['avg_incidents_per_res7_hex']), 2) if coverage_stats['avg_incidents_per_res7_hex'] else 0,
                    'resolution_8': round(float(coverage_stats['avg_incidents_per_res8_hex']), 2) if coverage_stats['avg_incidents_per_res8_hex'] else 0,
                    'resolution_9': round(float(coverage_stats['avg_incidents_per_res9_hex']), 2) if coverage_stats['avg_incidents_per_res9_hex'] else 0,
                    'resolution_10': round(float(coverage_stats['avg_incidents_per_res10_hex']), 2) if coverage_stats['avg_incidents_per_res10_hex'] else 0
                },
                'top_incident_hexagons': distribution_by_resolution
            }
            
            self.logger.info(f"✅ Actual H3 coverage analysis complete: {coverage_stats['valid_incidents']:,} incidents across multiple resolutions")
            return actual_coverage
            
        except Error as e:
            self.logger.error(f"Error analyzing actual H3 coverage: {e}")
            return {'error': str(e)}
    
    def validate_h3_indexing_accuracy(self, connection, sample_size: int = 1000) -> Dict:
        """Validate H3 indexing accuracy by recalculating a sample of indexes."""
        self.logger.info(f"🔍 Validating H3 indexing accuracy with {sample_size} sample records...")
        
        # Get sample of records for validation
        sample_query = f"""
        SELECT 
            incident_id,
            lat, lng,
            h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE 
        AND lat IS NOT NULL 
        AND lng IS NOT NULL
        AND h3_res_9 IS NOT NULL
        ORDER BY RAND()
        LIMIT {sample_size}
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(sample_query)
            sample_records = cursor.fetchall()
            cursor.close()
            
            if not sample_records:
                return {'error': 'No sample records found for validation'}
            
            # Validate H3 indexes
            validation_results = {
                'sample_size': len(sample_records),
                'validation_summary': {},
                'accuracy_rates': {},
                'sample_validation_errors': []
            }
            
            resolution_accuracies = {6: 0, 7: 0, 8: 0, 9: 0, 10: 0}
            
            for record in sample_records:
                lat, lng = record['lat'], record['lng']
                errors_for_record = []
                
                # Validate each resolution
                for resolution in range(6, 11):
                    expected_h3 = h3.latlng_to_cell(lat, lng, resolution)
                    actual_h3 = record[f'h3_res_{resolution}']
                    
                    if expected_h3 == actual_h3:
                        resolution_accuracies[resolution] += 1
                    else:
                        errors_for_record.append({
                            'resolution': resolution,
                            'expected': expected_h3,
                            'actual': actual_h3,
                            'coordinates': [lat, lng]
                        })
                
                if errors_for_record and len(validation_results['sample_validation_errors']) < 10:
                    validation_results['sample_validation_errors'].append({
                        'incident_id': record['incident_id'],
                        'errors': errors_for_record
                    })
            
            # Calculate accuracy rates
            sample_count = len(sample_records)
            for resolution in range(6, 11):
                accuracy_rate = (resolution_accuracies[resolution] / sample_count * 100)
                validation_results['accuracy_rates'][f'resolution_{resolution}'] = round(accuracy_rate, 2)
            
            # Overall accuracy
            total_correct = sum(resolution_accuracies.values())
            total_possible = sample_count * 5  # 5 resolutions
            overall_accuracy = (total_correct / total_possible * 100)
            
            validation_results['validation_summary'] = {
                'overall_accuracy_pct': round(overall_accuracy, 2),
                'total_validations': total_possible,
                'correct_validations': total_correct,
                'incorrect_validations': total_possible - total_correct,
                'average_accuracy_across_resolutions': round(sum(validation_results['accuracy_rates'].values()) / 5, 2)
            }
            
            self.logger.info(f"✅ H3 indexing validation complete: {overall_accuracy:.1f}% overall accuracy")
            return validation_results
            
        except Error as e:
            self.logger.error(f"Error validating H3 indexing accuracy: {e}")
            return {'error': str(e)}
    
    def compare_expected_vs_actual(self, expected_coverage: Dict, actual_coverage: Dict) -> Dict:
        """Compare expected vs actual H3 coverage and calculate efficiency metrics."""
        self.logger.info("🔍 Comparing expected vs actual H3 coverage...")
        
        comparison_results = {
            'comparison_summary': {},
            'efficiency_metrics': {},
            'coverage_analysis': {}
        }
        
        # Compare hexagon counts
        for resolution in range(6, 11):
            res_key = f'resolution_{resolution}'
            expected_count = expected_coverage['expected_hexagon_counts'][res_key]['estimated_hexagon_count']
            actual_count = actual_coverage['actual_hexagon_counts'][res_key]
            
            coverage_efficiency = (actual_count / expected_count * 100) if expected_count > 0 else 0
            
            comparison_results['coverage_analysis'][res_key] = {
                'expected_hexagons': expected_count,
                'actual_hexagons': actual_count,
                'coverage_efficiency_pct': round(coverage_efficiency, 2),
                'hexagon_difference': actual_count - expected_count,
                'expected_hex_area_km2': expected_coverage['expected_hexagon_counts'][res_key]['hex_area_km2']
            }
        
        # Calculate overall metrics
        total_expected = sum(expected_coverage['expected_hexagon_counts'][f'resolution_{i}']['estimated_hexagon_count'] for i in range(6, 11))
        total_actual = sum(actual_coverage['actual_hexagon_counts'][f'resolution_{i}'] for i in range(6, 11))
        
        comparison_results['comparison_summary'] = {
            'total_expected_hexagons': total_expected,
            'total_actual_hexagons': total_actual,
            'overall_coverage_efficiency_pct': round((total_actual / total_expected * 100), 2) if total_expected > 0 else 0,
            'philadelphia_area_km2': expected_coverage['estimated_area_km2'],
            'incidents_analyzed': actual_coverage['data_summary']['valid_incidents']
        }
        
        # Efficiency assessment
        avg_efficiency = sum(comp['coverage_efficiency_pct'] for comp in comparison_results['coverage_analysis'].values()) / 5
        comparison_results['efficiency_metrics'] = {
            'average_coverage_efficiency_pct': round(avg_efficiency, 2),
            'most_efficient_resolution': max(comparison_results['coverage_analysis'].items(), key=lambda x: x[1]['coverage_efficiency_pct'])[0],
            'least_efficient_resolution': min(comparison_results['coverage_analysis'].items(), key=lambda x: x[1]['coverage_efficiency_pct'])[0],
            'efficiency_rating': 'Excellent' if avg_efficiency >= 80 else 'Good' if avg_efficiency >= 60 else 'Fair' if avg_efficiency >= 40 else 'Poor'
        }
        
        self.logger.info(f"✅ Coverage comparison complete: {avg_efficiency:.1f}% average efficiency")
        return comparison_results
    
    def generate_h3_metrics_report(self) -> Dict:
        """Generate comprehensive H3 metrics analysis report."""
        self.logger.info("📊 Generating comprehensive H3 metrics analysis report...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Calculate all metrics
            expected_coverage = self.calculate_philadelphia_h3_coverage()
            actual_coverage = self.analyze_actual_h3_coverage(connection)
            indexing_validation = self.validate_h3_indexing_accuracy(connection, sample_size=500)
            coverage_comparison = self.compare_expected_vs_actual(expected_coverage, actual_coverage)
            
            # Build comprehensive report
            h3_metrics_report = {
                'report_timestamp': datetime.now().isoformat(),
                'h3_metrics_analysis': {
                    'expected_coverage': expected_coverage,
                    'actual_coverage': actual_coverage,
                    'indexing_validation': indexing_validation,
                    'coverage_comparison': coverage_comparison
                },
                'recommendations': self._generate_h3_recommendations(expected_coverage, actual_coverage, indexing_validation, coverage_comparison)
            }
            
            self.logger.info("✅ Comprehensive H3 metrics report generated")
            return h3_metrics_report
            
        except Exception as e:
            self.logger.error(f"Error generating H3 metrics report: {e}")
            return {'error': str(e)}
        finally:
            if connection and connection.is_connected():
                connection.close()
    
    def _generate_h3_recommendations(self, expected, actual, validation, comparison) -> List[str]:
        """Generate recommendations based on H3 metrics analysis."""
        recommendations = []
        
        # Coverage efficiency recommendations
        if 'efficiency_metrics' in comparison:
            avg_efficiency = comparison['efficiency_metrics']['average_coverage_efficiency_pct']
            if avg_efficiency < 50:
                recommendations.append(f"H3 coverage efficiency is {avg_efficiency:.1f}% - data may not represent full Philadelphia area")
        
        # Indexing accuracy recommendations
        if 'validation_summary' in validation:
            accuracy = validation['validation_summary']['overall_accuracy_pct']
            if accuracy < 95:
                recommendations.append(f"H3 indexing accuracy is {accuracy:.1f}% - investigate H3 calculation issues")
        
        # Incident distribution recommendations
        if 'incident_density_per_hexagon' in actual:
            densities = actual['incident_density_per_hexagon']
            max_density = max(densities.values())
            if max_density > 1000:
                recommendations.append(f"High incident density detected (max: {max_density:.0f} per hexagon) - consider higher resolution analysis")
        
        if not recommendations:
            recommendations.append("H3 metrics analysis shows good coverage and accuracy")
        
        return recommendations

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='H3 Metrics Calculator and Validator')
    parser.add_argument('--coverage-analysis', action='store_true', help='Full coverage analysis')
    parser.add_argument('--resolution', type=int, choices=[6,7,8,9,10], help='Analyze specific resolution')
    parser.add_argument('--validate-indexing', action='store_true', help='Validate H3 indexing accuracy')
    parser.add_argument('--output', default='console', choices=['console', 'json'], help='Output format')
    
    args = parser.parse_args()
    
    # Initialize calculator
    calculator = H3MetricsCalculator()
    
    print("="*80)
    print("H3 METRICS CALCULATOR AND VALIDATOR")
    print("="*80)
    print(f"Analysis Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*80)
    
    try:
        if args.coverage_analysis or not any([args.resolution, args.validate_indexing]):
            # Generate full metrics report
            report = calculator.generate_h3_metrics_report()
            
            if args.output == 'json':
                print(json.dumps(report, indent=2))
            else:
                print_h3_metrics_summary(report)
        
        elif args.validate_indexing:
            # Validate indexing only
            connection = calculator.connect_to_mysql()
            validation = calculator.validate_h3_indexing_accuracy(connection, sample_size=1000)
            connection.close()
            
            print(json.dumps(validation, indent=2))
        
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

def print_h3_metrics_summary(report):
    """Print formatted H3 metrics summary."""
    if 'h3_metrics_analysis' not in report:
        print("No H3 metrics analysis data available")
        return
    
    analysis = report['h3_metrics_analysis']
    
    print("\n📊 H3 COVERAGE COMPARISON")
    print("-" * 50)
    if 'coverage_comparison' in analysis:
        comparison = analysis['coverage_comparison']
        summary = comparison['comparison_summary']
        print(f"Expected Total Hexagons: {summary['total_expected_hexagons']:,}")
        print(f"Actual Total Hexagons: {summary['total_actual_hexagons']:,}")
        print(f"Overall Coverage Efficiency: {summary['overall_coverage_efficiency_pct']:.1f}%")
        print(f"Philadelphia Area: {summary['philadelphia_area_km2']:.1f} km²")
        print(f"Incidents Analyzed: {summary['incidents_analyzed']:,}")
    
    print("\n🔍 RESOLUTION BREAKDOWN")
    print("-" * 50)
    if 'coverage_comparison' in analysis:
        for res, data in analysis['coverage_comparison']['coverage_analysis'].items():
            print(f"{res.replace('_', ' ').title()}:")
            print(f"  Expected: {data['expected_hexagons']:,} | Actual: {data['actual_hexagons']:,}")
            print(f"  Efficiency: {data['coverage_efficiency_pct']:.1f}%")
    
    print("\n✅ INDEXING VALIDATION")
    print("-" * 50)
    if 'indexing_validation' in analysis:
        validation = analysis['indexing_validation']
        if 'validation_summary' in validation:
            summary = validation['validation_summary']
            print(f"Overall Accuracy: {summary['overall_accuracy_pct']:.1f}%")
            print(f"Validations Performed: {summary['total_validations']:,}")
            print(f"Correct Calculations: {summary['correct_validations']:,}")
    
    print("\n💡 RECOMMENDATIONS")
    print("-" * 50)
    for i, rec in enumerate(report.get('recommendations', []), 1):
        print(f"{i}. {rec}")
    
    print("\n" + "="*80)

if __name__ == "__main__":
    main()