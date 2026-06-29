#!/usr/bin/env python3
"""
Corrected Record Accounting Analysis

This script provides an exact accounting of records by replicating the exact same logic
as the transform processor, including all filtering conditions.

The previous analysis showed 845,334 valid records, but only 144,327 made it to transform.
This suggests the transform processor has additional filters we need to account for.
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
import sys
import os

class CorrectedAccountingAnalysis:
    """Corrected accounting analysis using exact transform processor logic."""
    
    def __init__(self):
        """Initialize the corrected analysis."""
        db_password = os.environ.get('DB_PASSWORD')
        if not db_password:
            raise ValueError('DB_PASSWORD environment variable is required')
        self.mysql_config = {
            'host': '127.0.0.1',
            'user': 'drupal_user',
            'password': db_password,
            'database': 'theoryofconspiracies_dev',
            'autocommit': True
        }
        
        logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
        self.logger = logging.getLogger(__name__)
        
        # Philadelphia bounds (exact match to transform processor)
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # Valid districts (exact match to transform processor)
        self.valid_districts = {
            '1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', 
            '17', '18', '19', '22', '24', '25', '26', '35', '39'
        }
        
        # Exclusion tracking
        self.detailed_exclusions = defaultdict(int)
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def validate_record_exact(self, row: pd.Series) -> Tuple[bool, str]:
        """
        Exact validation logic from transform processor.
        This MUST match the transform processor exactly.
        """
        
        # Check coordinates - Missing
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            return False, 'missing_coordinates'
        
        # Check coordinates - Invalid format  
        try:
            lat, lng = float(row['lat']), float(row['lng'])
        except (ValueError, TypeError):
            return False, 'invalid_coordinates_format'
        
        # Check coordinate bounds for Philadelphia
        if not (self.philly_bounds['lat_min'] <= lat <= self.philly_bounds['lat_max'] and
                self.philly_bounds['lng_min'] <= lng <= self.philly_bounds['lng_max']):
            return False, 'coordinates_outside_bounds'
        
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
            return False, 'invalid_datetime_format'
        
        # Check crime type
        if pd.isna(row.get('ucr_general')) or str(row['ucr_general']).strip() == '':
            return False, 'missing_crime_type'
        
        # Check district
        if pd.isna(row.get('dc_dist')) or str(row['dc_dist']) not in self.valid_districts:
            return False, 'invalid_district'
        
        return True, 'valid'
    
    def detect_duplicates_exact(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Exact duplicate detection logic from transform processor.
        """
        df = df.copy()
        df['final_exclusion_reason'] = 'valid'
        
        self.logger.info(f"🔍 Starting duplicate detection on {len(df)} records")
        
        # Track duplicate counts
        cartodb_dupes_count = 0
        objectid_dupes_count = 0
        composite_dupes_count = 0
        
        # 1. Check for cartodb_id duplicates
        cartodb_dupes = df.duplicated(subset=['cartodb_id'], keep='first')
        cartodb_dupes_count = cartodb_dupes.sum()
        df.loc[cartodb_dupes, 'final_exclusion_reason'] = 'duplicate_cartodb_id'
        self.logger.info(f"📊 Found {cartodb_dupes_count:,} cartodb_id duplicates")
        
        # 2. Check for objectid duplicates (excluding already marked duplicates)
        remaining_after_cartodb = df[~cartodb_dupes]
        objectid_dupes_in_remaining = remaining_after_cartodb.duplicated(subset=['objectid'], keep='first')
        objectid_dupes_count = objectid_dupes_in_remaining.sum()
        df.loc[remaining_after_cartodb[objectid_dupes_in_remaining].index, 'final_exclusion_reason'] = 'duplicate_objectid'
        self.logger.info(f"📊 Found {objectid_dupes_count:,} objectid duplicates")
        
        # 3. Check for composite duplicates (lat/lng + datetime + crime_type)
        remaining_after_objectid = remaining_after_cartodb[~objectid_dupes_in_remaining]
        composite_dupes_in_remaining = remaining_after_objectid.duplicated(
            subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first'
        )
        composite_dupes_count = composite_dupes_in_remaining.sum()
        df.loc[remaining_after_objectid[composite_dupes_in_remaining].index, 'final_exclusion_reason'] = 'duplicate_composite'
        self.logger.info(f"📊 Found {composite_dupes_count:,} composite duplicates")
        
        total_duplicates = cartodb_dupes_count + objectid_dupes_count + composite_dupes_count
        remaining_valid = len(df) - total_duplicates
        self.logger.info(f"📊 Duplicate summary: {total_duplicates:,} total duplicates, {remaining_valid:,} remaining valid")
        
        return df
    
    def process_sample_batch_with_exact_logic(self, connection, batch_size: int = 100000) -> Dict:
        """
        Process a sample batch using the EXACT same logic as transform processor
        to understand the true exclusion patterns.
        """
        
        # Fetch sample batch using EXACT same query as transform processor
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw'
        ORDER BY id
        LIMIT %s
        """
        
        self.logger.info(f"🔍 Fetching sample batch of {batch_size:,} records...")
        
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query, (batch_size,))
        records = cursor.fetchall()
        cursor.close()
        
        if not records:
            return {'error': 'No records found'}
        
        self.logger.info(f"📊 Fetched {len(records):,} raw records")
        
        # Convert to DataFrame
        df = pd.DataFrame(records)
        
        # Initialize exclusion tracking
        exclusion_counts = defaultdict(int)
        
        # Step 1: Apply validation filters (EXACT logic)
        self.logger.info("🔍 Step 1: Applying validation filters...")
        df['is_valid'] = True
        df['exclusion_reason'] = 'valid'
        
        for idx, row in df.iterrows():
            try:
                is_valid, reason = self.validate_record_exact(row)
                df.at[idx, 'is_valid'] = is_valid
                df.at[idx, 'exclusion_reason'] = reason
                exclusion_counts[reason] += 1
            except Exception as e:
                df.at[idx, 'is_valid'] = False
                df.at[idx, 'exclusion_reason'] = 'processing_error'
                exclusion_counts['processing_error'] += 1
        
        valid_after_filters = df[df['is_valid'] == True]
        self.logger.info(f"📊 After validation filters: {len(valid_after_filters):,} valid records")
        
        # Step 2: Apply duplicate detection (EXACT logic)
        if len(valid_after_filters) > 0:
            self.logger.info("🔍 Step 2: Applying duplicate detection...")
            valid_after_filters = self.detect_duplicates_exact(valid_after_filters)
            
            # Update exclusion counts for duplicates
            for reason in ['duplicate_cartodb_id', 'duplicate_objectid', 'duplicate_composite']:
                duplicate_count = (valid_after_filters['final_exclusion_reason'] == reason).sum()
                if duplicate_count > 0:
                    exclusion_counts['valid'] -= duplicate_count
                    exclusion_counts[reason] += duplicate_count
        
        # Final count of truly valid records
        final_valid_count = len(valid_after_filters[valid_after_filters['final_exclusion_reason'] == 'valid']) if len(valid_after_filters) > 0 else 0
        
        return {
            'sample_analysis': {
                'total_sample_records': len(df),
                'exclusion_breakdown': dict(exclusion_counts),
                'final_valid_records': final_valid_count,
                'validation_rate_pct': round((final_valid_count / len(df)) * 100, 2)
            }
        }
    
    def extrapolate_full_accounting(self, sample_results: Dict, total_raw_count: int) -> Dict:
        """Extrapolate sample results to full dataset."""
        sample_data = sample_results['sample_analysis']
        sample_size = sample_data['total_sample_records']
        
        # Calculate extrapolation ratios
        extrapolated_counts = {}
        for reason, count in sample_data['exclusion_breakdown'].items():
            extrapolated_counts[reason] = int((count / sample_size) * total_raw_count)
        
        return {
            'full_dataset_projection': {
                'total_raw_records': total_raw_count,
                'projected_exclusion_breakdown': extrapolated_counts,
                'projected_final_valid': extrapolated_counts.get('valid', 0),
                'projected_validation_rate_pct': round((extrapolated_counts.get('valid', 0) / total_raw_count) * 100, 2)
            },
            'sample_basis': sample_data
        }

def main():
    """Run corrected accounting analysis."""
    print("="*100)
    print("CORRECTED RECORD ACCOUNTING ANALYSIS")
    print("="*100)
    print(f"Analysis Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    analysis = CorrectedAccountingAnalysis()
    connection = analysis.connect_to_mysql()
    
    try:
        # Get baseline counts
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT COUNT(*) as total FROM amisafe_raw_incidents")
        total_raw = cursor.fetchone()['total']
        cursor.execute("SELECT COUNT(*) as total FROM amisafe_clean_incidents WHERE is_valid = TRUE")
        total_transform = cursor.fetchone()['total']
        cursor.close()
        
        print(f"📊 BASELINE COUNTS")
        print(f"Raw Layer Total: {total_raw:,}")
        print(f"Transform Layer Total: {total_transform:,}")
        print(f"Exclusion Gap: {total_raw - total_transform:,}")
        print()
        
        # Process sample with exact logic
        print("🔍 ANALYZING SAMPLE WITH EXACT TRANSFORM PROCESSOR LOGIC")
        print("-" * 80)
        sample_results = analysis.process_sample_batch_with_exact_logic(connection, batch_size=200000)
        
        if 'error' not in sample_results:
            # Show sample results
            sample_data = sample_results['sample_analysis']
            print(f"\n📊 SAMPLE RESULTS ({sample_data['total_sample_records']:,} records):")
            print(f"Final Valid Records: {sample_data['final_valid_records']:,}")
            print(f"Validation Rate: {sample_data['validation_rate_pct']:.1f}%")
            
            print(f"\n🔍 EXCLUSION BREAKDOWN:")
            for reason, count in sample_data['exclusion_breakdown'].items():
                if count > 0 and reason != 'valid':
                    pct = (count / sample_data['total_sample_records']) * 100
                    print(f"  {reason}: {count:,} ({pct:.1f}%)")
            
            # Extrapolate to full dataset
            full_projection = analysis.extrapolate_full_accounting(sample_results, total_raw)
            projection_data = full_projection['full_dataset_projection']
            
            print(f"\n📊 FULL DATASET PROJECTION:")
            print(f"Projected Valid Records: {projection_data['projected_final_valid']:,}")
            print(f"Projected Validation Rate: {projection_data['projected_validation_rate_pct']:.1f}%")
            print(f"Gap from Transform Layer: {projection_data['projected_final_valid'] - total_transform:,}")
            
            print(f"\n🔍 PROJECTED EXCLUSION BREAKDOWN:")
            for reason, count in projection_data['projected_exclusion_breakdown'].items():
                if count > 0 and reason != 'valid':
                    pct = (count / total_raw) * 100
                    print(f"  {reason}: {count:,} ({pct:.1f}%)")
            
        else:
            print("❌ Error in sample analysis")
    
    finally:
        connection.close()
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()