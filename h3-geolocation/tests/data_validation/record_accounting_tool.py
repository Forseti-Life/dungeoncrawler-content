#!/usr/bin/env python3
"""
Comprehensive Record Accounting Tool

This tool provides a complete accounting of all records from Raw to Transform layer,
ensuring total count reconciliation and detailed classification of excluded records
based on the exact exclusion logic used in the transform processor.

Key Features:
- Complete record accounting (Raw count = Transform count + Excluded count)
- Detailed exclusion classification by specific validation criteria
- Statistical analysis of exclusion patterns
- Data quality assessment across all records
- Recovery opportunity identification

Usage:
    python record_accounting_tool.py --full-accounting
    python record_accounting_tool.py --exclusion-summary
    python record_accounting_tool.py --recovery-analysis
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Optional
import argparse
import sys
import os

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

class RecordAccountingTool:
    """
    Comprehensive record accounting tool for Raw to Transform layer analysis.
    Provides complete reconciliation and detailed exclusion classification.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the record accounting tool."""
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
        
        # Philadelphia geographic bounds (matching transform processor)
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # Valid districts (matching transform processor)
        self.valid_districts = {
            '1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', 
            '17', '18', '19', '22', '24', '25', '26', '35', '39'
        }
        
        # Exclusion classification categories
        self.exclusion_categories = {
            'missing_coordinates': 'Missing lat/lng coordinates',
            'invalid_coordinates': 'Invalid coordinates (non-numeric or outside Philadelphia bounds)',
            'missing_datetime': 'Missing dispatch_date_time',
            'invalid_datetime': 'Invalid datetime format',
            'missing_crime_type': 'Missing or empty ucr_general',
            'invalid_district': 'Missing or invalid dc_dist (not in valid districts)',
            'duplicate_cartodb_id': 'Duplicate cartodb_id',
            'duplicate_objectid': 'Duplicate objectid', 
            'duplicate_composite': 'Duplicate composite (lat/lng/datetime/crime_type)',
            'processing_error': 'Error during processing'
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
    
    def get_baseline_counts(self, connection) -> Dict[str, int]:
        """Get baseline record counts to verify accounting."""
        cursor = connection.cursor(dictionary=True)
        
        # Raw layer count
        cursor.execute("SELECT COUNT(*) as raw_count FROM amisafe_raw_incidents")
        raw_count = cursor.fetchone()['raw_count']
        
        # Transform layer count
        cursor.execute("SELECT COUNT(*) as transform_count FROM amisafe_clean_incidents")
        transform_count = cursor.fetchone()['transform_count']
        
        cursor.close()
        
        return {
            'raw_total': raw_count,
            'transform_total': transform_count,
            'excluded_total': raw_count - transform_count
        }
    
    def validate_record_with_classification(self, row: pd.Series) -> Tuple[bool, str]:
        """
        Validate a single record using the exact same logic as the transform processor.
        Returns (is_valid, exclusion_reason).
        """
        
        # Check coordinates - Missing
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            return False, 'missing_coordinates'
        
        # Check coordinates - Invalid format
        try:
            lat, lng = float(row['lat']), float(row['lng'])
        except (ValueError, TypeError):
            return False, 'invalid_coordinates'
        
        # Check coordinate bounds for Philadelphia
        if not (self.philly_bounds['lat_min'] <= lat <= self.philly_bounds['lat_max'] and
                self.philly_bounds['lng_min'] <= lng <= self.philly_bounds['lng_max']):
            return False, 'invalid_coordinates'
        
        # Check datetime - Missing
        if pd.isna(row.get('dispatch_date_time')):
            return False, 'missing_datetime'
        
        # Check datetime - Invalid format
        try:
            datetime_str = str(row['dispatch_date_time'])
            # Try with timezone first
            try:
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                # Try without timezone
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
        except (ValueError, TypeError):
            return False, 'invalid_datetime'
        
        # Check crime type
        if pd.isna(row.get('ucr_general')) or str(row['ucr_general']).strip() == '':
            return False, 'missing_crime_type'
        
        # Check district
        if pd.isna(row.get('dc_dist')) or str(row['dc_dist']) not in self.valid_districts:
            return False, 'invalid_district'
        
        return True, 'valid'
    
    def classify_duplicates(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Classify duplicates using the exact same logic as the transform processor.
        """
        df = df.copy()
        df['exclusion_reason'] = 'valid'  # Default
        
        # Check for cartodb_id duplicates
        cartodb_dupes = df.duplicated(subset=['cartodb_id'], keep='first')
        df.loc[cartodb_dupes, 'exclusion_reason'] = 'duplicate_cartodb_id'
        
        # Check for objectid duplicates (excluding already marked duplicates)
        remaining_df = df[~cartodb_dupes]
        objectid_dupes = remaining_df.duplicated(subset=['objectid'], keep='first')
        df.loc[remaining_df[objectid_dupes].index, 'exclusion_reason'] = 'duplicate_objectid'
        
        # Check for composite duplicates (lat/lng + datetime + crime_type)
        remaining_df = df[(~cartodb_dupes) & (~df.index.isin(remaining_df[objectid_dupes].index))]
        composite_dupes = remaining_df.duplicated(
            subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first'
        )
        df.loc[remaining_df[composite_dupes].index, 'exclusion_reason'] = 'duplicate_composite'
        
        return df
    
    def process_raw_records_batch(self, connection, batch_size: int = 50000, offset: int = 0) -> Dict:
        """Process a batch of raw records and classify exclusions."""
        
        # Fetch raw records batch
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        ORDER BY id
        LIMIT %s OFFSET %s
        """
        
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query, (batch_size, offset))
        records = cursor.fetchall()
        cursor.close()
        
        if not records:
            return {'batch_results': {}, 'records_processed': 0}
        
        # Convert to DataFrame for processing
        df = pd.DataFrame(records)
        
        # Initialize exclusion tracking
        exclusion_counts = {category: 0 for category in self.exclusion_categories.keys()}
        exclusion_counts['valid'] = 0
        
        # Apply validation logic to each record
        df['is_valid'] = True
        df['exclusion_reason'] = 'valid'
        
        for idx, row in df.iterrows():
            try:
                is_valid, reason = self.validate_record_with_classification(row)
                df.at[idx, 'is_valid'] = is_valid
                df.at[idx, 'exclusion_reason'] = reason
                exclusion_counts[reason] += 1
            except Exception as e:
                df.at[idx, 'is_valid'] = False
                df.at[idx, 'exclusion_reason'] = 'processing_error'
                exclusion_counts['processing_error'] += 1
        
        # Apply duplicate detection to valid records
        valid_records = df[df['is_valid'] == True].copy()
        if len(valid_records) > 0:
            valid_records = self.classify_duplicates(valid_records)
            
            # Update main dataframe with duplicate classifications
            for idx, row in valid_records.iterrows():
                if row['exclusion_reason'] != 'valid':
                    df.at[idx, 'is_valid'] = False
                    df.at[idx, 'exclusion_reason'] = row['exclusion_reason']
                    exclusion_counts['valid'] -= 1
                    exclusion_counts[row['exclusion_reason']] += 1
        
        return {
            'batch_results': exclusion_counts,
            'records_processed': len(df),
            'batch_offset': offset
        }
    
    def generate_complete_record_accounting(self, batch_size: int = 50000) -> Dict:
        """Generate complete record accounting across all raw records."""
        self.logger.info("🔍 Starting complete record accounting analysis...")
        
        connection = self.connect_to_mysql()
        
        try:
            # Get baseline counts
            baseline_counts = self.get_baseline_counts(connection)
            self.logger.info(f"📊 Baseline: {baseline_counts['raw_total']:,} raw → {baseline_counts['transform_total']:,} transform")
            
            # Initialize aggregated results
            total_exclusion_counts = {category: 0 for category in self.exclusion_categories.keys()}
            total_exclusion_counts['valid'] = 0
            total_processed = 0
            batch_number = 0
            
            # Process all records in batches
            while total_processed < baseline_counts['raw_total']:
                offset = total_processed
                batch_number += 1
                
                self.logger.info(f"🔄 Processing batch {batch_number} (offset: {offset:,})")
                
                batch_results = self.process_raw_records_batch(
                    connection, batch_size=batch_size, offset=offset
                )
                
                if batch_results['records_processed'] == 0:
                    break
                
                # Aggregate batch results
                for category, count in batch_results['batch_results'].items():
                    total_exclusion_counts[category] += count
                
                total_processed += batch_results['records_processed']
                
                # Progress reporting
                progress_pct = (total_processed / baseline_counts['raw_total']) * 100
                self.logger.info(f"📈 Progress: {total_processed:,}/{baseline_counts['raw_total']:,} ({progress_pct:.1f}%)")
            
            # Generate comprehensive accounting report
            accounting_report = {
                'accounting_timestamp': datetime.now().isoformat(),
                'baseline_counts': baseline_counts,
                'detailed_exclusion_analysis': self._analyze_exclusions(total_exclusion_counts, baseline_counts),
                'record_reconciliation': self._verify_reconciliation(total_exclusion_counts, baseline_counts),
                'recovery_opportunities': self._identify_recovery_opportunities(total_exclusion_counts, baseline_counts)
            }
            
            self.logger.info("✅ Complete record accounting analysis finished")
            return accounting_report
            
        finally:
            connection.close()
    
    def _analyze_exclusions(self, exclusion_counts: Dict, baseline_counts: Dict) -> Dict:
        """Analyze exclusion patterns and generate detailed breakdown."""
        total_raw = baseline_counts['raw_total']
        
        exclusion_analysis = {
            'total_records_analyzed': total_raw,
            'exclusion_breakdown': {},
            'exclusion_summary': {}
        }
        
        # Detailed breakdown by category
        for category, count in exclusion_counts.items():
            if count > 0:
                percentage = (count / total_raw) * 100
                exclusion_analysis['exclusion_breakdown'][category] = {
                    'count': count,
                    'percentage': round(percentage, 3),
                    'description': self.exclusion_categories.get(category, 'Valid records')
                }
        
        # Summary statistics
        total_excluded = sum(count for cat, count in exclusion_counts.items() if cat != 'valid')
        total_valid = exclusion_counts.get('valid', 0)
        
        exclusion_analysis['exclusion_summary'] = {
            'total_valid_records': total_valid,
            'total_excluded_records': total_excluded,
            'validation_success_rate_pct': round((total_valid / total_raw) * 100, 2),
            'exclusion_rate_pct': round((total_excluded / total_raw) * 100, 2)
        }
        
        return exclusion_analysis
    
    def _verify_reconciliation(self, exclusion_counts: Dict, baseline_counts: Dict) -> Dict:
        """Verify that record counts reconcile perfectly."""
        calculated_total = sum(exclusion_counts.values())
        expected_total = baseline_counts['raw_total']
        
        reconciliation = {
            'expected_total': expected_total,
            'calculated_total': calculated_total,
            'difference': calculated_total - expected_total,
            'reconciles_perfectly': calculated_total == expected_total,
            'transform_layer_matches': exclusion_counts.get('valid', 0) == baseline_counts['transform_total']
        }
        
        return reconciliation
    
    def _identify_recovery_opportunities(self, exclusion_counts: Dict, baseline_counts: Dict) -> List[str]:
        """Identify opportunities to recover excluded records."""
        opportunities = []
        total_raw = baseline_counts['raw_total']
        
        # High-impact recovery opportunities
        high_impact_categories = ['missing_coordinates', 'invalid_coordinates', 'missing_datetime', 'invalid_datetime']
        
        for category in high_impact_categories:
            count = exclusion_counts.get(category, 0)
            if count > 0:
                percentage = (count / total_raw) * 100
                if percentage > 1.0:  # More than 1% of records
                    opportunities.append(f"Recover {count:,} records ({percentage:.1f}%) with {category} through enhanced preprocessing")
        
        # Duplicate recovery
        duplicate_categories = ['duplicate_cartodb_id', 'duplicate_objectid', 'duplicate_composite']
        total_duplicates = sum(exclusion_counts.get(cat, 0) for cat in duplicate_categories)
        if total_duplicates > 0:
            percentage = (total_duplicates / total_raw) * 100
            opportunities.append(f"Review {total_duplicates:,} duplicates ({percentage:.1f}%) for potential legitimate records")
        
        # District validation
        invalid_district_count = exclusion_counts.get('invalid_district', 0)
        if invalid_district_count > 0:
            percentage = (invalid_district_count / total_raw) * 100
            opportunities.append(f"Validate {invalid_district_count:,} records ({percentage:.1f}%) with district mapping issues")
        
        return opportunities

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='Comprehensive Record Accounting Tool')
    parser.add_argument('--full-accounting', action='store_true', help='Complete record accounting analysis')
    parser.add_argument('--exclusion-summary', action='store_true', help='Exclusion summary only')
    parser.add_argument('--recovery-analysis', action='store_true', help='Focus on recovery opportunities')
    parser.add_argument('--batch-size', type=int, default=50000, help='Batch size for processing')
    parser.add_argument('--output', default='console', choices=['console', 'json'], help='Output format')
    
    args = parser.parse_args()
    
    # Initialize accounting tool
    accounting_tool = RecordAccountingTool()
    
    print("="*100)
    print("COMPREHENSIVE RECORD ACCOUNTING TOOL")
    print("="*100)
    print(f"Analysis Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    try:
        if args.full_accounting or not any([args.exclusion_summary, args.recovery_analysis]):
            # Generate complete accounting
            report = accounting_tool.generate_complete_record_accounting(batch_size=args.batch_size)
            
            if args.output == 'json':
                import json
                print(json.dumps(report, indent=2))
            else:
                print_accounting_summary(report)
        
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

def print_accounting_summary(report):
    """Print formatted accounting summary."""
    print("\n📊 RECORD RECONCILIATION")
    print("-" * 80)
    
    baseline = report['baseline_counts']
    reconciliation = report['record_reconciliation']
    
    print(f"Raw Layer Total: {baseline['raw_total']:,}")
    print(f"Transform Layer Total: {baseline['transform_total']:,}")
    print(f"Excluded Records: {baseline['excluded_total']:,}")
    
    print(f"\nReconciliation Status: {'✅ PERFECT' if reconciliation['reconciles_perfectly'] else '❌ MISMATCH'}")
    print(f"Expected Total: {reconciliation['expected_total']:,}")
    print(f"Calculated Total: {reconciliation['calculated_total']:,}")
    print(f"Difference: {reconciliation['difference']:,}")
    
    print("\n🔍 EXCLUSION BREAKDOWN")
    print("-" * 80)
    
    exclusion_analysis = report['detailed_exclusion_analysis']
    
    for category, data in exclusion_analysis['exclusion_breakdown'].items():
        if category != 'valid':
            print(f"{data['description']}: {data['count']:,} ({data['percentage']:.1f}%)")
    
    summary = exclusion_analysis['exclusion_summary']
    print(f"\nSUMMARY:")
    print(f"Valid Records: {summary['total_valid_records']:,} ({summary['validation_success_rate_pct']:.1f}%)")
    print(f"Excluded Records: {summary['total_excluded_records']:,} ({summary['exclusion_rate_pct']:.1f}%)")
    
    print("\n💡 RECOVERY OPPORTUNITIES")
    print("-" * 80)
    for i, opportunity in enumerate(report['recovery_opportunities'], 1):
        print(f"{i}. {opportunity}")
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()