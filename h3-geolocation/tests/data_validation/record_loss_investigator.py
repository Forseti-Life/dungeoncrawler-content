#!/usr/bin/env python3
"""
Record Loss Investigation Tool

This tool investigates exactly where records are being lost in the transform processor.
It replicates the exact logic step by step to identify where the 701,007 "valid" records
(845,334 - 144,327) are being lost after validation.
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import json
import uuid
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
import sys
import os

class RecordLossInvestigator:
    """Investigates exactly where records are lost in the transform process."""
    
    def __init__(self):
        """Initialize the investigator."""
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
        """Exact validation logic from transform processor."""
        
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
    
    def test_prepare_clean_record(self, row: pd.Series) -> Tuple[bool, str]:
        """Test if prepare_clean_record would succeed for this row."""
        try:
            # Test datetime parsing
            datetime_str = str(row['dispatch_date_time'])
            try:
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                try:
                    incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
                except ValueError:
                    return False, 'prepare_datetime_parse_failed'
            
            # Test coordinate conversion
            try:
                lat, lng = float(row['lat']), float(row['lng'])
            except (ValueError, TypeError):
                return False, 'prepare_coordinate_conversion_failed'
            
            # Test cartodb_id conversion
            if pd.notna(row['cartodb_id']):
                try:
                    int(row['cartodb_id'])
                except (ValueError, TypeError):
                    return False, 'prepare_cartodb_id_conversion_failed'
            
            # Test objectid conversion  
            if pd.notna(row['objectid']):
                try:
                    int(row['objectid'])
                except (ValueError, TypeError):
                    return False, 'prepare_objectid_conversion_failed'
            
            return True, 'prepare_success'
            
        except Exception as e:
            return False, f'prepare_exception_{type(e).__name__}'
    
    def investigate_sample_losses(self, connection, sample_size: int = 50000) -> Dict:
        """Investigate losses in a sample of records."""
        
        # Fetch sample using exact same query as transform processor
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw'
        ORDER BY id
        LIMIT %s
        """
        
        self.logger.info(f"🔍 Investigating record losses in sample of {sample_size:,} records...")
        
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query, (sample_size,))
        records = cursor.fetchall()
        cursor.close()
        
        if not records:
            return {'error': 'No records found'}
        
        self.logger.info(f"📊 Fetched {len(records):,} raw records")
        
        # Convert to DataFrame
        df = pd.DataFrame(records)
        
        # Step-by-step loss analysis
        losses = {
            'total_sample': len(df),
            'step_1_validation_pass': 0,
            'step_2_after_duplicates': 0,
            'step_3_prepare_clean_pass': 0,
            'step_4_final_ready': 0,
            'validation_failures': defaultdict(int),
            'duplicate_failures': defaultdict(int),
            'prepare_failures': defaultdict(int),
            'detailed_analysis': []
        }
        
        # Step 1: Validation
        self.logger.info("🔍 Step 1: Testing validation logic...")
        df['validation_pass'] = False
        df['validation_reason'] = 'unknown'
        
        for idx, row in df.iterrows():
            is_valid, reason = self.validate_record_exact(row)
            df.at[idx, 'validation_pass'] = is_valid
            df.at[idx, 'validation_reason'] = reason
            if not is_valid:
                losses['validation_failures'][reason] += 1
        
        validation_pass_df = df[df['validation_pass'] == True]
        losses['step_1_validation_pass'] = len(validation_pass_df)
        self.logger.info(f"📊 After validation: {len(validation_pass_df):,} records pass")
        
        # Step 2: Duplicate detection (simplified - just check if duplicates exist)
        if len(validation_pass_df) > 0:
            self.logger.info("🔍 Step 2: Testing duplicate detection...")
            
            # Check for cartodb_id duplicates
            cartodb_dupes = validation_pass_df.duplicated(subset=['cartodb_id'], keep='first')
            losses['duplicate_failures']['duplicate_cartodb_id'] = cartodb_dupes.sum()
            
            # Check for objectid duplicates
            remaining_after_cartodb = validation_pass_df[~cartodb_dupes]
            objectid_dupes = remaining_after_cartodb.duplicated(subset=['objectid'], keep='first')
            losses['duplicate_failures']['duplicate_objectid'] = objectid_dupes.sum()
            
            # Check for composite duplicates
            remaining_after_objectid = remaining_after_cartodb[~objectid_dupes]
            composite_dupes = remaining_after_objectid.duplicated(
                subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first'
            )
            losses['duplicate_failures']['duplicate_composite'] = composite_dupes.sum()
            
            # Records after duplicate removal
            after_duplicates_df = validation_pass_df[
                ~cartodb_dupes & 
                ~validation_pass_df.index.isin(remaining_after_cartodb[objectid_dupes].index) &
                ~validation_pass_df.index.isin(remaining_after_objectid[composite_dupes].index)
            ]
            losses['step_2_after_duplicates'] = len(after_duplicates_df)
            self.logger.info(f"📊 After duplicate removal: {len(after_duplicates_df):,} records remain")
        else:
            after_duplicates_df = pd.DataFrame()
            losses['step_2_after_duplicates'] = 0
        
        # Step 3: Test prepare_clean_record logic
        if len(after_duplicates_df) > 0:
            self.logger.info("🔍 Step 3: Testing prepare_clean_record logic...")
            
            prepare_pass_count = 0
            for idx, row in after_duplicates_df.iterrows():
                can_prepare, reason = self.test_prepare_clean_record(row)
                if can_prepare:
                    prepare_pass_count += 1
                else:
                    losses['prepare_failures'][reason] += 1
            
            losses['step_3_prepare_clean_pass'] = prepare_pass_count
            losses['step_4_final_ready'] = prepare_pass_count
            self.logger.info(f"📊 After prepare_clean_record: {prepare_pass_count:,} records ready")
        else:
            losses['step_3_prepare_clean_pass'] = 0
            losses['step_4_final_ready'] = 0
        
        return losses
    
    def extrapolate_losses(self, sample_losses: Dict, total_raw: int) -> Dict:
        """Extrapolate sample losses to full dataset."""
        sample_size = sample_losses['total_sample']
        
        extrapolated = {
            'total_raw_records': total_raw,
            'sample_size': sample_size,
            'extrapolated_flow': {}
        }
        
        # Extrapolate each step
        for step in ['step_1_validation_pass', 'step_2_after_duplicates', 'step_3_prepare_clean_pass', 'step_4_final_ready']:
            sample_count = sample_losses[step]
            extrapolated_count = int((sample_count / sample_size) * total_raw)
            extrapolated['extrapolated_flow'][step] = extrapolated_count
        
        # Extrapolate failure reasons
        extrapolated['validation_failures'] = {}
        for reason, count in sample_losses['validation_failures'].items():
            extrapolated['validation_failures'][reason] = int((count / sample_size) * total_raw)
        
        extrapolated['duplicate_failures'] = {}
        for reason, count in sample_losses['duplicate_failures'].items():
            extrapolated['duplicate_failures'][reason] = int((count / sample_size) * total_raw)
        
        extrapolated['prepare_failures'] = {}
        for reason, count in sample_losses['prepare_failures'].items():
            extrapolated['prepare_failures'][reason] = int((count / sample_size) * total_raw)
        
        return extrapolated

def main():
    """Run record loss investigation."""
    print("="*100)
    print("RECORD LOSS INVESTIGATION")
    print("="*100)
    print(f"Investigation Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    investigator = RecordLossInvestigator()
    connection = investigator.connect_to_mysql()
    
    try:
        # Get baseline counts
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT COUNT(*) as total FROM amisafe_raw_incidents")
        total_raw = cursor.fetchone()['total']
        cursor.execute("SELECT COUNT(*) as total FROM amisafe_clean_incidents WHERE is_valid = TRUE")
        total_transform = cursor.fetchone()['total']
        cursor.close()
        
        print(f"📊 BASELINE MYSTERY")
        print(f"Raw Layer Total: {total_raw:,}")
        print(f"Transform Layer Total: {total_transform:,}")
        print(f"Missing Records: {total_raw - total_transform:,}")
        print()
        
        # Investigate sample losses
        sample_losses = investigator.investigate_sample_losses(connection, sample_size=100000)
        
        if 'error' not in sample_losses:
            print(f"📊 SAMPLE ANALYSIS RESULTS ({sample_losses['total_sample']:,} records)")
            print("-" * 80)
            print(f"Step 1 - Validation Pass:      {sample_losses['step_1_validation_pass']:,}")
            print(f"Step 2 - After Duplicates:     {sample_losses['step_2_after_duplicates']:,}")
            print(f"Step 3 - Prepare Clean Pass:   {sample_losses['step_3_prepare_clean_pass']:,}")
            print(f"Step 4 - Final Ready:          {sample_losses['step_4_final_ready']:,}")
            
            # Show failure breakdowns
            if sample_losses['validation_failures']:
                print(f"\n🔍 VALIDATION FAILURES:")
                for reason, count in sample_losses['validation_failures'].items():
                    pct = (count / sample_losses['total_sample']) * 100
                    print(f"  {reason}: {count:,} ({pct:.1f}%)")
            
            if sample_losses['duplicate_failures']:
                print(f"\n🔍 DUPLICATE FAILURES:")
                for reason, count in sample_losses['duplicate_failures'].items():
                    pct = (count / sample_losses['total_sample']) * 100
                    print(f"  {reason}: {count:,} ({pct:.1f}%)")
            
            if sample_losses['prepare_failures']:
                print(f"\n🔍 PREPARE_CLEAN_RECORD FAILURES:")
                for reason, count in sample_losses['prepare_failures'].items():
                    pct = (count / sample_losses['total_sample']) * 100
                    print(f"  {reason}: {count:,} ({pct:.1f}%)")
            
            # Extrapolate to full dataset
            extrapolated = investigator.extrapolate_losses(sample_losses, total_raw)
            
            print(f"\n📊 FULL DATASET PROJECTION")
            print("-" * 80)
            flow = extrapolated['extrapolated_flow']
            print(f"Raw Records:                   {total_raw:,}")
            print(f"Projected Validation Pass:     {flow['step_1_validation_pass']:,}")
            print(f"Projected After Duplicates:    {flow['step_2_after_duplicates']:,}")
            print(f"Projected Prepare Clean Pass:  {flow['step_3_prepare_clean_pass']:,}")
            print(f"Projected Final Ready:         {flow['step_4_final_ready']:,}")
            print(f"Actual Transform Layer:        {total_transform:,}")
            print(f"Projection vs Actual Gap:      {flow['step_4_final_ready'] - total_transform:,}")
        
        else:
            print("❌ Error in investigation")
    
    finally:
        connection.close()
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()