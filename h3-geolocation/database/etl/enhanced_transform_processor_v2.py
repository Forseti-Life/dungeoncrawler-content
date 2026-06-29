#!/usr/bin/env python3
"""
Enhanced AmISafe Transform Processor with Integrated Validation Reporting

This enhanced processor combines transform processing with comprehensive validation
reporting to provide consistent, detailed analysis of data processing operations.

Key Features:
- Complete record accounting and reconciliation
- Integrated validation testing and reporting
- Comprehensive exclusion analysis
- Automated report generation
- Processing status tracking
- Recovery recommendations

Usage:
    python enhanced_transform_processor_v2.py --continue-processing
    python enhanced_transform_processor_v2.py --full-reprocess
    python enhanced_transform_processor_v2.py --validation-only
    python enhanced_transform_processor_v2.py --status-check
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import json
import uuid
import h3
import logging
from datetime import datetime, date, timedelta
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
import argparse
import sys
import os
import time
import warnings

# Suppress pandas SQLAlchemy warning for mysql.connector usage
warnings.filterwarnings('ignore', message='pandas only supports SQLAlchemy connectable.*', category=UserWarning)

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

# Removed record_accounting_tool dependency - not needed for core ETL

class EnhancedTransformProcessor:
    """
    Enhanced transform processor with integrated validation reporting.
    Provides complete record accounting and comprehensive processing reports.
    """
    
    def __init__(self,
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'amisafe_database',
                 mysql_socket: str = None,
                 reports_dir: str = None):
        """Initialize the enhanced transform processor."""
        
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
        
        # Reports directory
        if reports_dir is None:
            reports_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'reports')
        self.reports_dir = reports_dir
        self.processing_reports_dir = os.path.join(reports_dir, 'processing')
        self.validation_reports_dir = os.path.join(reports_dir, 'validation')
        
        # Ensure reports directories exist
        os.makedirs(self.processing_reports_dir, exist_ok=True)
        os.makedirs(self.validation_reports_dir, exist_ok=True)
        
        # Setup logging
        log_filename = os.path.join(self.processing_reports_dir, f'transform_processing_{datetime.now().strftime("%Y%m%d_%H%M%S")}.log')
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_filename),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Record accounting removed - not needed for core ETL
        
        # Removed geographic bounds and district validation filters
        # Only using incident ID deduplication as requested
        
        # Processing statistics
        self.processing_stats = {
            'start_time': None,
            'end_time': None,
            'total_raw_records': 0,
            'records_processed': 0,
            'records_inserted': 0,
            'processing_batches': 0,
            'batch_failures': 0,
            'last_processed_id': 0,
            'exclusions': defaultdict(int),
            'processing_errors': []
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
    
    def get_processing_status(self) -> Dict:
        """Get current processing status and statistics - OPTIMIZED for speed."""
        connection = self.connect_to_mysql()
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # FAST: Basic counts only - no expensive operations
            cursor.execute("""
                SELECT 
                    (SELECT COUNT(*) FROM amisafe_raw_incidents) as total_raw,
                    (SELECT COUNT(*) FROM amisafe_clean_incidents) as total_transform,
                    (SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw') as raw_status_remaining
            """)
            
            result = cursor.fetchone()
            
            if not result:
                # Fallback to individual queries if combined query fails
                cursor.execute("SELECT COUNT(*) as total FROM amisafe_raw_incidents")
                raw_result = cursor.fetchone()
                total_raw = raw_result['total'] if raw_result else 0
                
                cursor.execute("SELECT COUNT(*) as total FROM amisafe_clean_incidents")
                transform_result = cursor.fetchone()
                total_transform = transform_result['total'] if transform_result else 0
                
                cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
                raw_status_result = cursor.fetchone()
                raw_status_remaining = raw_status_result[0] if raw_status_result else 0
                
                processing_range = {'min_processed_id': 0, 'max_processed_id': 0}
                batch_info = {'total_batches': 0, 'first_batch_time': None, 'last_batch_time': None}
            else:
                total_raw = result['total_raw'] or 0
                total_transform = result['total_transform'] or 0
                raw_status_remaining = result['raw_status_remaining'] or 0
                processing_range = {'min_processed_id': 0, 'max_processed_id': 0}
                batch_info = {'total_batches': 0, 'first_batch_time': None, 'last_batch_time': None}
            
            # SKIP EXPENSIVE OPERATIONS: Only do basic column sampling for large tables
            column_fill_rates = {}
            incomplete_columns = {}
            
            if total_transform > 0 and total_transform < 1000000:  # Only for smaller datasets
                # Sample-based column fill rate analysis for performance
                sample_size = min(10000, total_transform)  # Sample at most 10k records
                
                # Core columns only - skip detailed H3 analysis for speed
                core_columns = {
                    'incident_id': 'string',
                    'lat': 'coordinate',
                    'lng': 'coordinate', 
                    'h3_res_5': 'h3_index',
                    'h3_res_11': 'h3_index',
                    'h3_res_12': 'h3_index',
                    'h3_res_13': 'h3_index'
                }
                
                # FAST: Sample-based fill rate check
                fill_rate_selects = []
                for column in core_columns.keys():
                    fill_rate_selects.append(f"SUM(CASE WHEN {column} IS NOT NULL THEN 1 ELSE 0 END) as {column}_filled")
                
                fill_query = f"""
                    SELECT 
                        COUNT(*) as total_records,
                        {', '.join(fill_rate_selects)}
                    FROM (
                        SELECT {', '.join(core_columns.keys())}
                        FROM amisafe_clean_incidents 
                        ORDER BY RAND() 
                        LIMIT {sample_size}
                    ) sample
                """
                
                try:
                    cursor.execute(fill_query)
                    fill_results = cursor.fetchone()
                    
                    if fill_results:
                        sample_total = fill_results['total_records']
                        
                        # Calculate fill rates from sample
                        for column, data_type in core_columns.items():
                            filled_count = fill_results[f'{column}_filled']
                            fill_rate = (filled_count / sample_total * 100) if sample_total > 0 else 0
                            column_fill_rates[column] = {
                                'filled_count': filled_count,
                                'total_count': sample_total,
                                'fill_rate': round(fill_rate, 2),
                                'data_type': data_type,
                                'is_sample': True
                            }
                            
                            # Track columns that aren't well filled
                            if fill_rate < 95.0:
                                incomplete_columns[column] = {
                                    'fill_rate': round(fill_rate, 2),
                                    'missing_count': sample_total - filled_count,
                                    'data_type': data_type,
                                    'is_sample': True
                                }
                except Exception as e:
                    self.logger.warning(f"Column analysis sampling failed: {e}")
                    # Continue without column analysis
            
            cursor.close()
            
            status = {
                'total_raw_records': total_raw,
                'total_transform_records': total_transform,
                'records_remaining': total_raw - total_transform,
                'raw_status_remaining': raw_status_remaining,  # More accurate remaining count
                'completion_percentage': round((total_transform / total_raw * 100), 2) if total_raw > 0 else 0,
                'processing_range': processing_range,
                'batch_summary': {
                    'total_batches': batch_info['total_batches'],
                    'first_processed': batch_info['first_batch_time'].isoformat() if batch_info['first_batch_time'] else None,
                    'last_processed': batch_info['last_batch_time'].isoformat() if batch_info['last_batch_time'] else None
                },
                'column_fill_rates': column_fill_rates,
                'incomplete_columns': incomplete_columns,
                'data_quality_summary': {
                    'total_columns_checked': len(column_fill_rates),
                    'fully_populated_columns': len(column_fill_rates) - len(incomplete_columns),
                    'incomplete_columns_count': len(incomplete_columns),
                    'overall_data_completeness': round((len(column_fill_rates) - len(incomplete_columns)) / len(column_fill_rates) * 100, 1) if column_fill_rates else 0,
                    'analysis_method': 'sample' if any(col.get('is_sample', False) for col in column_fill_rates.values()) else 'full'
                }
            }
            
            return status
            
        finally:
            connection.close()
    
    def validate_record(self, row: pd.Series) -> Tuple[bool, str]:
        """Minimal validation - only check if record exists (no filtering)."""
        # All records are considered valid - no filtering applied
        # Only deduplication by incident ID will be performed
        return True, 'valid'
    
    def fetch_raw_incidents(self, connection, batch_size: int = 50000, start_id: int = 1) -> pd.DataFrame:
        """Fetch raw incidents starting from a specific ID."""
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw' AND id >= %s
        ORDER BY id
        LIMIT %s
        """
        
        try:
            df = pd.read_sql(query, connection, params=(start_id, batch_size))
            self.logger.info(f"Fetched {len(df)} raw incidents (starting from ID: {start_id})")
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents: {e}")
            return pd.DataFrame()
    
    def detect_duplicates(self, df: pd.DataFrame) -> pd.DataFrame:
        """Detect and mark duplicates by objectid - NO DEDUPLICATION since objectid is unique."""
        df = df.copy()
        
        # Initialize exclusion_reason if not exists
        if 'exclusion_reason' not in df.columns:
            df['exclusion_reason'] = 'valid'
        
        # Since objectid is unique for all records, no deduplication is needed
        # All records are considered valid and unique
        
        # Count total duplicates found (should be 0)
        duplicates_found = (df['exclusion_reason'] != 'valid').sum()
        self.logger.info(f"No deduplication applied - objectid is unique for all records (duplicates: {duplicates_found})")
        return df
    
    def prepare_clean_record(self, row: pd.Series, batch_id: str) -> Dict:
        """Prepare a cleaned record for insertion."""
        try:
            # Parse datetime - handle timezone format
            datetime_str = str(row['dispatch_date_time'])
            try:
                # Try with timezone first
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                # Try without timezone
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
            
            # Generate H3 indexes (5-13 for complete coverage)
            lat, lng = float(row['lat']), float(row['lng'])
            h3_indexes = {}
            for resolution in range(5, 14):  # H3:5 to H3:13
                try:
                    h3_index = h3.latlng_to_cell(lat, lng, resolution)
                    h3_indexes[f'h3_res_{resolution}'] = h3_index
                except Exception as e:
                    self.logger.warning(f"H3 indexing failed for resolution {resolution}: {e}")
                    h3_indexes[f'h3_res_{resolution}'] = None
            
            # Calculate data quality score
            quality_score = self.calculate_data_quality_score(row)
            
            # Create incident ID
            incident_id = f"{row['cartodb_id']}_{row['objectid']}" if pd.notna(row['cartodb_id']) and pd.notna(row['objectid']) else str(uuid.uuid4())
            
            clean_record = {
                'raw_incident_ids': json.dumps([int(row['id'])]),
                'processing_batch_id': batch_id,
                'incident_id': incident_id,
                'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                'dc_dist': str(row['dc_dist']),
                'psa': str(row['psa']) if pd.notna(row['psa']) else None,
                'location_block': str(row['location_block']) if pd.notna(row['location_block']) else None,
                'lat': lat,
                'lng': lng,
                'coordinate_quality': 'HIGH',
                'incident_datetime': incident_dt,
                'incident_date': incident_dt.date(),
                'incident_hour': incident_dt.hour,
                'incident_month': incident_dt.month,
                'incident_year': incident_dt.year,
                'day_of_week': incident_dt.weekday() + 1,
                'ucr_general': str(row['ucr_general']),
                'crime_category': self.get_crime_category(str(row['ucr_general'])),
                'crime_description': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
                'severity_level': self.get_severity_level(str(row['ucr_general'])),
                'data_quality_score': quality_score,
                'duplicate_group_id': None,
                'is_duplicate': row.get('exclusion_reason', '').startswith('duplicate'),
                'is_valid': True
            }
            
            # Add H3 indexes
            clean_record.update(h3_indexes)
            
            return clean_record
            
        except Exception as e:
            self.logger.error(f"Error preparing clean record: {e}")
            self.processing_stats['processing_errors'].append(f"prepare_clean_record: {str(e)}")
            return None
    
    def calculate_data_quality_score(self, row: pd.Series) -> float:
        """Calculate data quality score (0.0 - 1.0) - NO FILTERING, just scoring."""
        score = 1.0
        
        # Coordinate quality
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            score -= 0.3
        
        # Temporal quality
        if pd.isna(row.get('dispatch_date_time')):
            score -= 0.2
        
        # Location description quality
        if pd.isna(row.get('location_block')) or str(row.get('location_block')).strip() == '':
            score -= 0.1
        
        # Crime description quality
        if pd.isna(row.get('text_general_code')) or str(row.get('text_general_code')).strip() == '':
            score -= 0.1
        
        # District quality (no filtering, just scoring)
        if pd.isna(row.get('dc_dist')):
            score -= 0.2
        
        # UCR code quality
        if pd.isna(row.get('ucr_general')):
            score -= 0.1
        
        return max(0.0, score)
    
    def prepare_clean_records_vectorized(self, valid_records: pd.DataFrame, batch_id: str) -> List[Dict]:
        """Vectorized preparation of clean records - MUCH FASTER than row-by-row."""
        clean_records = []
        
        try:
            # Vectorized datetime parsing
            datetime_series = valid_records['dispatch_date_time'].astype(str)
            
            # Vectorized coordinate extraction
            lat_series = pd.to_numeric(valid_records['lat'], errors='coerce')
            lng_series = pd.to_numeric(valid_records['lng'], errors='coerce')
            
            # NO FILTERING - Process all records regardless of coordinate validity
            # Keep invalid coordinates as NULL/None values for data completeness
            valid_coord_records = valid_records
            valid_lats = lat_series
            valid_lngs = lng_series
            
            for idx, (_, row) in enumerate(valid_coord_records.iterrows()):
                try:
                    # Parse datetime - handle invalid dates gracefully
                    datetime_str = str(row['dispatch_date_time'])
                    incident_dt = None
                    try:
                        incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
                    except ValueError:
                        try:
                            incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
                        except ValueError:
                            # Use a default date for invalid timestamps, don't filter out
                            incident_dt = datetime(2000, 1, 1, 0, 0, 0)
                    
                    # Get coordinates - handle invalid coordinates gracefully
                    lat, lng = valid_lats.iloc[idx], valid_lngs.iloc[idx]
                    
                    # Generate H3 indexes (5-13) - only if coordinates are valid
                    h3_indexes = {}
                    if pd.notna(lat) and pd.notna(lng) and -90 <= lat <= 90 and -180 <= lng <= 180:
                        for resolution in range(5, 14):  # H3:5 to H3:13
                            try:
                                h3_index = h3.latlng_to_cell(lat, lng, resolution)
                                h3_indexes[f'h3_res_{resolution}'] = h3_index
                            except:
                                h3_indexes[f'h3_res_{resolution}'] = None
                    else:
                        # Set H3 indexes to None for invalid coordinates
                        for resolution in range(5, 14):  # H3:5 to H3:13
                            h3_indexes[f'h3_res_{resolution}'] = None
                    
                    # Calculate quality score
                    quality_score = self.calculate_data_quality_score(row)
                    
                    # Create incident ID using objectid as primary identifier
                    incident_id = f"obj_{row['objectid']}" if pd.notna(row['objectid']) else str(uuid.uuid4())
                    
                    # Create clean record
                    clean_record = {
                        'raw_incident_ids': json.dumps([int(row['id'])]),
                        'incident_id': incident_id,
                        'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                        'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                        'incident_datetime': incident_dt.strftime('%Y-%m-%d %H:%M:%S'),
                        'incident_date': incident_dt.strftime('%Y-%m-%d'),
                        'incident_year': incident_dt.year,
                        'incident_month': incident_dt.month,
                        'incident_hour': incident_dt.hour,
                        'day_of_week': incident_dt.weekday() + 1,
                        'lat': lat if pd.notna(lat) else None,
                        'lng': lng if pd.notna(lng) else None,
                        'coordinate_quality': 'HIGH' if (pd.notna(lat) and pd.notna(lng)) else 'LOW',
                        'location_block': str(row.get('location_block', '')),
                        'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                        'dc_dist': str(row.get('dc_dist', '')),
                        'psa': str(row.get('psa', '')) if pd.notna(row.get('psa')) else None,
                        'ucr_general': str(row.get('ucr_general', '')),
                        'crime_description': str(row.get('text_general_code', '')),
                        'crime_category': self.get_crime_category(str(row.get('ucr_general', ''))),
                        'severity_level': self.get_severity_level(str(row.get('ucr_general', ''))),
                        'data_quality_score': quality_score,
                        'processing_batch_id': batch_id,
                        'duplicate_group_id': None,
                        'is_duplicate': row.get('exclusion_reason', '').startswith('duplicate'),
                        'is_valid': True
                    }
                    
                    # Add H3 indexes
                    clean_record.update(h3_indexes)
                    clean_records.append(clean_record)
                    
                except Exception as e:
                    self.logger.warning(f"Failed to prepare clean record: {e}")
                    continue
                    
            self.logger.info(f"✅ Vectorized processing: {len(clean_records)} clean records prepared from {len(valid_records)} valid records")
            return clean_records
            
        except Exception as e:
            self.logger.error(f"Vectorized processing failed, falling back to individual processing: {e}")
            # Fallback to individual processing if vectorized fails
            for _, row in valid_records.iterrows():
                clean_record = self.prepare_clean_record(row, batch_id)
                if clean_record:
                    clean_records.append(clean_record)
            return clean_records
    
    def get_crime_category(self, ucr_code: str) -> str:
        """Map UCR code to crime category."""
        category_map = {
            '100': 'Violent Crime', '200': 'Violent Crime', '300': 'Violent Crime', '400': 'Violent Crime',
            '500': 'Property Crime', '600': 'Property Crime', '700': 'Property Crime', '800': 'Property Crime',
            '900': 'Other'
        }
        return category_map.get(ucr_code[:1] + '00', 'Other')
    
    def get_severity_level(self, ucr_code: str) -> int:
        """Map UCR code to severity level (1-5)."""
        severity_map = {
            '100': 5, '200': 4, '300': 3, '400': 2,
            '500': 3, '600': 2, '700': 2, '800': 1,
            '900': 3
        }
        return severity_map.get(ucr_code[:1] + '00', 3)
    
    def process_batch(self, connection, batch_df: pd.DataFrame, batch_id: str) -> Dict:
        """Process a single batch of raw incidents."""
        batch_stats = {
            'total_records': len(batch_df),
            'valid_records': 0,
            'excluded_records': 0,
            'exclusion_reasons': defaultdict(int),
            'clean_records_created': 0,
            'insertion_failures': 0
        }
        
        if batch_df.empty:
            return batch_stats
        
        # Add exclusion reason column
        batch_df['exclusion_reason'] = 'valid'
        
        # Validate each record
        for idx, row in batch_df.iterrows():
            is_valid, reason = self.validate_record(row)
            if not is_valid:
                batch_df.at[idx, 'exclusion_reason'] = reason
                batch_stats['exclusion_reasons'][reason] += 1
                self.processing_stats['exclusions'][reason] += 1
        
        # Detect duplicates in valid records
        valid_mask = batch_df['exclusion_reason'] == 'valid'
        if valid_mask.sum() > 0:
            batch_df = self.detect_duplicates(batch_df)
            
            # Update exclusion stats for duplicates found - VECTORIZED VERSION
            excluded_records = batch_df[batch_df['exclusion_reason'] != 'valid']
            if len(excluded_records) > 0:
                exclusion_counts = excluded_records['exclusion_reason'].value_counts()
                for reason, count in exclusion_counts.items():
                    batch_stats['exclusion_reasons'][reason] += count
                    self.processing_stats['exclusions'][reason] += count
        
        # Count final exclusions
        excluded_mask = batch_df['exclusion_reason'] != 'valid'
        batch_stats['excluded_records'] = excluded_mask.sum()
        batch_stats['valid_records'] = len(batch_df) - batch_stats['excluded_records']
        
        # Prepare clean records from valid data - VECTORIZED VERSION
        valid_records = batch_df[~excluded_mask]
        clean_records = []
        
        if len(valid_records) > 0:
            # Vectorized clean record preparation
            clean_records = self.prepare_clean_records_vectorized(valid_records, batch_id)
            batch_stats['insertion_failures'] = len(valid_records) - len(clean_records)
        
        # Insert clean records
        if clean_records:
            inserted_count = self.insert_clean_records(connection, clean_records, batch_id)
            batch_stats['clean_records_created'] = inserted_count
            self.processing_stats['records_inserted'] += inserted_count
        
        return batch_stats
    
    def insert_clean_records(self, connection, clean_records: List[Dict], batch_id: str) -> int:
        """Insert clean records into amisafe_clean_incidents table."""
        if not clean_records:
            return 0
        
        insert_sql = """
        INSERT IGNORE INTO amisafe_clean_incidents (
            raw_incident_ids, processing_batch_id, incident_id, cartodb_id, objectid, dc_key,
            dc_dist, psa, location_block, lat, lng, coordinate_quality,
            incident_datetime, incident_date, incident_hour, incident_month, incident_year, day_of_week,
            ucr_general, crime_category, crime_description, severity_level,
            h3_res_5, h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10, h3_res_11, h3_res_12, h3_res_13,
            data_quality_score, duplicate_group_id, is_duplicate, is_valid
        ) VALUES (
            %(raw_incident_ids)s, %(processing_batch_id)s, %(incident_id)s, %(cartodb_id)s, %(objectid)s, %(dc_key)s,
            %(dc_dist)s, %(psa)s, %(location_block)s, %(lat)s, %(lng)s, %(coordinate_quality)s,
            %(incident_datetime)s, %(incident_date)s, %(incident_hour)s, %(incident_month)s, %(incident_year)s, %(day_of_week)s,
            %(ucr_general)s, %(crime_category)s, %(crime_description)s, %(severity_level)s,
            %(h3_res_5)s, %(h3_res_6)s, %(h3_res_7)s, %(h3_res_8)s, %(h3_res_9)s, %(h3_res_10)s, %(h3_res_11)s, %(h3_res_12)s, %(h3_res_13)s,
            %(data_quality_score)s, %(duplicate_group_id)s, %(is_duplicate)s, %(is_valid)s
        )
        """
        
        try:
            cursor = connection.cursor()
            cursor.executemany(insert_sql, clean_records)
            
            # Update raw records status to 'processed' after successful insertion
            raw_ids = []
            for record in clean_records:
                # Extract raw incident IDs from the JSON array
                raw_incident_ids = json.loads(record['raw_incident_ids'])
                raw_ids.extend(raw_incident_ids)
            
            if raw_ids:
                # Update processing status for successfully processed raw records
                placeholders = ','.join(['%s'] * len(raw_ids))
                update_sql = f"UPDATE amisafe_raw_incidents SET processing_status = 'processed' WHERE id IN ({placeholders})"
                cursor.execute(update_sql, raw_ids)
                self.logger.info(f"Marked {len(raw_ids)} raw records as processed")
            
            connection.commit()
            inserted_count = cursor.rowcount
            cursor.close()
            return inserted_count
        except Error as e:
            self.logger.error(f"Error inserting clean records: {e}")
            self.processing_stats['batch_failures'] += 1
            self.processing_stats['processing_errors'].append(f"insert_clean_records: {str(e)}")
            return 0
    
    def drop_indexes_for_bulk_load(self, connection) -> List[str]:
        """Drop non-unique indexes to speed up bulk loading. Returns list of dropped indexes."""
        self.logger.info("🔧 Dropping indexes for faster bulk loading...")
        dropped_indexes = []
        
        try:
            cursor = connection.cursor()
            
            # Get all indexes except PRIMARY and unique constraints
            cursor.execute("""
                SELECT DISTINCT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = 'amisafe_clean_incidents'
                AND NON_UNIQUE = 1
                AND INDEX_NAME NOT IN ('PRIMARY')
            """, (self.mysql_config['database'],))
            
            indexes = [row[0] for row in cursor.fetchall()]
            
            if indexes:
                # Drop all non-unique indexes
                for index_name in indexes:
                    try:
                        cursor.execute(f"ALTER TABLE amisafe_clean_incidents DROP INDEX {index_name}")
                        dropped_indexes.append(index_name)
                        self.logger.info(f"   ✓ Dropped index: {index_name}")
                    except Error as e:
                        self.logger.warning(f"   ⚠ Could not drop index {index_name}: {e}")
                
                connection.commit()
                self.logger.info(f"✅ Dropped {len(dropped_indexes)} indexes for bulk loading")
            else:
                self.logger.info("✅ No indexes to drop (may already be dropped)")
            
            cursor.close()
        except Error as e:
            self.logger.error(f"Error dropping indexes: {e}")
        
        return dropped_indexes
    
    def rebuild_indexes(self, connection) -> bool:
        """Rebuild all indexes after bulk loading completes."""
        self.logger.info("🔧 Rebuilding indexes after bulk load...")
        
        try:
            cursor = connection.cursor()
            
            # Define indexes to rebuild
            indexes = [
                "CREATE INDEX idx_batch ON amisafe_clean_incidents(processing_batch_id)",
                "CREATE INDEX idx_crime_type ON amisafe_clean_incidents(ucr_general, crime_category)",
                "CREATE INDEX idx_datetime ON amisafe_clean_incidents(incident_datetime)",
                "CREATE INDEX idx_district ON amisafe_clean_incidents(dc_dist, psa)",
                "CREATE INDEX idx_h3_res5 ON amisafe_clean_incidents(h3_res_5)",
                "CREATE INDEX idx_h3_res6 ON amisafe_clean_incidents(h3_res_6)",
                "CREATE INDEX idx_h3_res7 ON amisafe_clean_incidents(h3_res_7)",
                "CREATE INDEX idx_h3_res8 ON amisafe_clean_incidents(h3_res_8)",
                "CREATE INDEX idx_h3_res9 ON amisafe_clean_incidents(h3_res_9)",
                "CREATE INDEX idx_h3_res10 ON amisafe_clean_incidents(h3_res_10)",
                "CREATE INDEX idx_h3_res11 ON amisafe_clean_incidents(h3_res_11)",
                "CREATE INDEX idx_h3_res12 ON amisafe_clean_incidents(h3_res_12)",
                "CREATE INDEX idx_h3_res13 ON amisafe_clean_incidents(h3_res_13)",
                "CREATE INDEX idx_location ON amisafe_clean_incidents(lat, lng)",
                "CREATE INDEX idx_quality ON amisafe_clean_incidents(data_quality_score, coordinate_quality)"
            ]
            
            rebuild_start = datetime.now()
            for i, index_sql in enumerate(indexes, 1):
                index_name = index_sql.split()[2]  # Extract index name
                try:
                    self.logger.info(f"   Creating index {i}/{len(indexes)}: {index_name}...")
                    cursor.execute(index_sql)
                    self.logger.info(f"   ✓ Created index: {index_name}")
                except Error as e:
                    if "Duplicate key name" in str(e):
                        self.logger.info(f"   ⚠ Index {index_name} already exists")
                    else:
                        self.logger.warning(f"   ⚠ Could not create index {index_name}: {e}")
            
            connection.commit()
            rebuild_duration = (datetime.now() - rebuild_start).total_seconds()
            self.logger.info(f"✅ Rebuilt {len(indexes)} indexes in {rebuild_duration:.1f} seconds")
            cursor.close()
            return True
            
        except Error as e:
            self.logger.error(f"Error rebuilding indexes: {e}")
            return False
    
    def continue_processing(self, batch_size: int = 50000) -> Dict:
        """Continue processing from where it left off."""
        self.logger.info("🔄 Starting enhanced transform processing (continue mode)...")
        self.processing_stats['start_time'] = datetime.now()
        
        # Initialize timing variables for ETA calculation
        batch_start_time = datetime.now()
        processing_start_time = datetime.now()
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Drop indexes for faster bulk loading
            self.drop_indexes_for_bulk_load(connection)
            
            # Get current processing status
            status = self.get_processing_status()
            self.processing_stats['total_raw_records'] = status['total_raw_records']
            initial_processed = status['total_transform_records']
            
            # Enhanced processing status logic
            cursor = connection.cursor()
            
            # Check for unprocessed records by status
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            status_based_remaining = cursor.fetchone()[0]
            
            # Check for record mismatch (Bronze marked processed but Silver empty)
            bronze_processed_count = status['total_raw_records'] - status_based_remaining
            silver_count = status['total_transform_records']
            
            # Detect mismatch situation
            if bronze_processed_count > 0 and silver_count == 0:
                self.logger.warning(f"🚨 MISMATCH DETECTED: {bronze_processed_count:,} Bronze records marked as processed but Silver table is empty!")
                self.logger.info("🔄 Resetting Bronze processing status to allow reprocessing...")
                
                # Reset all Bronze records to 'raw' status
                cursor.execute("UPDATE amisafe_raw_incidents SET processing_status = 'raw'")
                connection.commit()
                
                # Recalculate remaining records
                cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
                status_based_remaining = cursor.fetchone()[0]
                
                self.logger.info(f"✅ Reset complete: {status_based_remaining:,} records ready for processing")
            
            # Find the lowest unprocessed ID to start from
            cursor.execute("SELECT MIN(id) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            start_id = cursor.fetchone()[0] or 1
            cursor.close()
            
            self.logger.info(f"📊 Processing Status:")
            self.logger.info(f"   Total Raw Records: {status['total_raw_records']:,}")
            self.logger.info(f"   Silver Records: {status['total_transform_records']:,}")
            self.logger.info(f"   Unprocessed Records: {status_based_remaining:,}")
            self.logger.info(f"   Starting from ID: {start_id:,}")
            self.logger.info(f"   Batch Size: {batch_size:,}")
            
            # Check if processing is needed
            if status_based_remaining == 0:
                self.logger.info("✅ All records already processed!")
                return self.generate_final_report()
            else:
                self.logger.info(f"🔄 Found {status_based_remaining:,} unprocessed records, starting transformation...")
                actual_remaining = status_based_remaining
            
            batch_num = status['batch_summary']['total_batches']
            
            while True:
                # Fetch next batch
                batch_df = self.fetch_raw_incidents(connection, batch_size, start_id)
                if batch_df.empty:
                    break
                
                batch_num += 1
                batch_id = f"enhanced_batch_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{batch_num}"
                
                self.logger.info(f"🔄 Processing batch {batch_num} ({len(batch_df)} records, starting ID: {start_id:,})...")
                
                # Process batch
                batch_stats = self.process_batch(connection, batch_df, batch_id)
                self.processing_stats['processing_batches'] += 1
                self.processing_stats['records_processed'] += batch_stats['total_records']
                
                # Update last processed ID
                self.processing_stats['last_processed_id'] = int(batch_df['id'].max())
                start_id = self.processing_stats['last_processed_id'] + 1
                
                # Log batch results
                self.logger.info(
                    f"✅ Batch {batch_num}: {batch_stats['clean_records_created']} inserted, "
                    f"{batch_stats['excluded_records']} excluded, "
                    f"{batch_stats['insertion_failures']} failed preparations"
                )
                
                # Enhanced progress tracking with timing
                processed_this_session = self.processing_stats['records_inserted']
                remaining_records = actual_remaining - processed_this_session
                current_progress = (processed_this_session / actual_remaining * 100) if actual_remaining > 0 else 0
                
                # Calculate ETA after every batch
                current_time = datetime.now()
                elapsed_time = (current_time - processing_start_time).total_seconds()
                records_processed_this_session = self.processing_stats['records_inserted']
                
                if records_processed_this_session > 0 and elapsed_time > 0:
                    records_per_second = records_processed_this_session / elapsed_time
                    eta_seconds = remaining_records / records_per_second if records_per_second > 0 else 0
                    eta_time = current_time + timedelta(seconds=eta_seconds)
                    eta_formatted = eta_time.strftime('%Y-%m-%d %H:%M:%S')
                else:
                    eta_formatted = "Calculating..."
                
                # Show progress update every batch (more frequent updates)
                self.logger.info(
                    f"📈 Batch {batch_num} Complete: {processed_this_session:,}/{actual_remaining:,} "
                    f"({current_progress:.2f}%) | Remaining: {remaining_records:,} | ETA: {eta_formatted}"
                )
            
        except Exception as e:
            self.logger.error(f"Transform processing failed: {e}")
            self.processing_stats['processing_errors'].append(f"main_processing: {str(e)}")
            raise
        finally:
            # Rebuild indexes before closing connection
            if connection and connection.is_connected():
                self.logger.info("🏁 Processing complete, rebuilding indexes...")
                self.rebuild_indexes(connection)
                connection.close()
        
        self.processing_stats['end_time'] = datetime.now()
        return self.generate_final_report()
    
    def populate_h3_columns(self, target_columns: List[str] = None, batch_size: int = 1000) -> Dict:
        """Populate specific H3 columns for existing records."""
        self.logger.info("🔄 Starting H3 column population...")
        self.processing_stats['start_time'] = datetime.now()
        
        # Default to all H3 columns if none specified
        if target_columns is None:
            target_columns = [f'h3_res_{res}' for res in range(5, 14)]
        
        # Validate column names
        valid_columns = [f'h3_res_{res}' for res in range(5, 14)]
        target_columns = [col for col in target_columns if col in valid_columns]
        
        if not target_columns:
            self.logger.error("No valid H3 columns specified")
            return {'error': 'No valid H3 columns specified'}
        
        self.logger.info(f"Target H3 columns: {target_columns}")
        
        connection = None
        total_updated = 0
        
        try:
            connection = self.connect_to_mysql()
            cursor = connection.cursor()
            
            # OPTIMIZED: Build precise filter to get only records that actually need H3 population for target columns
            where_conditions = []
            for col in target_columns:
                where_conditions.append(f"{col} IS NULL")
            
            # Get exact count of records needing updates for the specified columns
            count_query = f"""
                SELECT COUNT(DISTINCT id) FROM amisafe_clean_incidents 
                WHERE lat IS NOT NULL AND lng IS NOT NULL 
                AND ({' OR '.join(where_conditions)})
            """
            cursor.execute(count_query)
            total_records = cursor.fetchone()[0]
            
            self.logger.info(f"Found {total_records:,} records needing H3 population for columns: {', '.join(target_columns)}")
            
            if total_records == 0:
                self.logger.info("✅ All specified H3 columns are already populated")
                return {'total_updated': 0, 'message': 'All columns already populated'}
            
            # OPTIMIZED: Process in efficient batches using LIMIT with ORDER BY for consistency
            batch_num = 0
            last_processed_id = 0
            
            while True:
                batch_num += 1
                
                # OPTIMIZED: Use ID-based pagination instead of OFFSET (much faster for large datasets)
                fetch_query = f"""
                    SELECT id, lat, lng, {', '.join(target_columns)} 
                    FROM amisafe_clean_incidents 
                    WHERE lat IS NOT NULL AND lng IS NOT NULL 
                    AND id > {last_processed_id}
                    AND ({' OR '.join(where_conditions)})
                    ORDER BY id
                    LIMIT {batch_size}
                """
                
                cursor.execute(fetch_query)
                batch_records = cursor.fetchall()
                
                if not batch_records:
                    break
                
                self.logger.info(f"🔄 Processing batch {batch_num}: {len(batch_records)} records (ID range: {batch_records[0][0]}-{batch_records[-1][0]})")
                
                # OPTIMIZED: Prepare batch update data efficiently
                batch_updates = []
                records_needing_update = 0
                
                for record in batch_records:
                    record_id, lat, lng = record[0], record[1], record[2]
                    current_h3_values = record[3:]  # Current H3 values for target columns
                    
                    # Only calculate H3 for columns that are actually NULL
                    h3_updates = {}
                    needs_update = False
                    
                    for i, column in enumerate(target_columns):
                        if current_h3_values[i] is None:  # Only process NULL columns
                            resolution = int(column.split('_')[-1])
                            try:
                                h3_cell = h3.latlng_to_cell(lat, lng, resolution)
                                h3_updates[column] = h3_cell
                                needs_update = True
                            except Exception as e:
                                self.logger.warning(f"Failed to calculate H3 for resolution {resolution} at ({lat}, {lng}): {e}")
                                h3_updates[column] = None
                    
                    if needs_update:
                        batch_updates.append((record_id, h3_updates))
                        records_needing_update += 1
                    
                    last_processed_id = record_id
                
                # OPTIMIZED: Execute efficient batch updates
                batch_updated = 0
                if batch_updates:
                    # Group updates by columns being updated for more efficient queries
                    for record_id, h3_updates in batch_updates:
                        if h3_updates:
                            # Build efficient update query for non-null H3 values only
                            update_fields = []
                            update_params = []
                            
                            for col, val in h3_updates.items():
                                if val is not None:
                                    update_fields.append(f"{col} = %s")
                                    update_params.append(val)
                            
                            if update_fields:
                                update_query = f"""
                                    UPDATE amisafe_clean_incidents 
                                    SET {', '.join(update_fields)} 
                                    WHERE id = %s
                                """
                                update_params.append(record_id)
                                cursor.execute(update_query, update_params)
                                if cursor.rowcount > 0:
                                    batch_updated += 1
                
                connection.commit()
                total_updated += batch_updated
                
                self.logger.info(f"✅ Batch {batch_num}: Updated {batch_updated}/{records_needing_update} records (Total: {total_updated:,}/{total_records:,})")
                
                # Enhanced progress tracking with ETA
                progress = (total_updated / total_records * 100) if total_records > 0 else 0
                remaining = total_records - total_updated
                
                # Simple ETA based on current rate
                if batch_num > 1 and batch_updated > 0:
                    elapsed = (datetime.now() - self.processing_stats['start_time']).total_seconds()
                    rate = total_updated / elapsed if elapsed > 0 else 0
                    eta_seconds = remaining / rate if rate > 0 else 0
                    eta_time = datetime.now() + timedelta(seconds=eta_seconds)
                    eta_str = eta_time.strftime('%H:%M:%S')
                else:
                    eta_str = "Calculating..."
                
                self.logger.info(f"📈 Progress: {progress:.1f}% | Remaining: {remaining:,} | ETA: {eta_str}")
            
            cursor.close()
            
            # Final validation
            self.logger.info("🔍 Validating H3 column population...")
            cursor = connection.cursor()
            for column in target_columns:
                cursor.execute(f"SELECT COUNT(*) FROM amisafe_clean_incidents WHERE {column} IS NOT NULL")
                populated_count = cursor.fetchone()[0]
                cursor.execute("SELECT COUNT(*) FROM amisafe_clean_incidents WHERE lat IS NOT NULL AND lng IS NOT NULL")
                total_with_coords = cursor.fetchone()[0]
                
                population_rate = (populated_count / total_with_coords * 100) if total_with_coords > 0 else 0
                status = "✅" if population_rate > 95 else "⚠️" if population_rate > 0 else "❌"
                self.logger.info(f"{status} {column}: {populated_count:,}/{total_with_coords:,} ({population_rate:.1f}%)")
            
            cursor.close()
            
        except Exception as e:
            self.logger.error(f"H3 column population failed: {e}")
            self.processing_stats['processing_errors'].append(f"populate_h3_columns: {str(e)}")
            raise
        finally:
            if connection and connection.is_connected():
                connection.close()
        
        self.processing_stats['end_time'] = datetime.now()
        duration = self.processing_stats['end_time'] - self.processing_stats['start_time']
        
        self.logger.info(f"🎉 H3 Column Population Complete!")
        self.logger.info(f"   Total Updated: {total_updated:,}")
        self.logger.info(f"   Duration: {duration.total_seconds():.1f} seconds")
        
        return {
            'total_updated': total_updated,
            'target_columns': target_columns,
            'duration_seconds': duration.total_seconds(),
            'success': True
        }

    def generate_final_report(self) -> Dict:
        """Generate comprehensive final processing report."""
        self.logger.info("📊 Generating comprehensive processing report...")
        
        # Get final status
        final_status = self.get_processing_status()
        
        # Generate validation report
        validation_report = self.accounting_tool.generate_complete_record_accounting(batch_size=100000)
        
        # Calculate processing duration
        duration = None
        if self.processing_stats['start_time'] and self.processing_stats['end_time']:
            duration = self.processing_stats['end_time'] - self.processing_stats['start_time']
        
        # Compile comprehensive report
        comprehensive_report = {
            'report_timestamp': datetime.now().isoformat(),
            'processing_session': {
                'start_time': self.processing_stats['start_time'].isoformat() if self.processing_stats['start_time'] else None,
                'end_time': self.processing_stats['end_time'].isoformat() if self.processing_stats['end_time'] else None,
                'duration_seconds': duration.total_seconds() if duration else None,
                'records_processed_this_session': self.processing_stats['records_processed'],
                'records_inserted_this_session': self.processing_stats['records_inserted'],
                'batches_processed_this_session': self.processing_stats['processing_batches'],
                'batch_failures': self.processing_stats['batch_failures'],
                'processing_errors': self.processing_stats['processing_errors']
            },
            'final_status': final_status,
            'validation_analysis': validation_report,
            'recommendations': self.generate_recommendations(final_status, validation_report)
        }
        
        # Save reports
        self.save_reports(comprehensive_report)
        
        return comprehensive_report
    
    def generate_recommendations(self, status: Dict, validation: Dict) -> List[str]:
        """Generate processing recommendations."""
        recommendations = []
        
        if status['completion_percentage'] < 100:
            recommendations.append(f"Continue processing remaining {status['records_remaining']:,} records ({100-status['completion_percentage']:.1f}% remaining)")
        
        if self.processing_stats['batch_failures'] > 0:
            recommendations.append(f"Investigate {self.processing_stats['batch_failures']} batch failures for data integrity")
        
        if len(self.processing_stats['processing_errors']) > 0:
            recommendations.append(f"Review {len(self.processing_stats['processing_errors'])} processing errors")
        
        # Add validation-based recommendations if available
        if 'recommendations' in validation:
            recommendations.extend(validation['recommendations'][:3])  # Top 3
        
        if not recommendations:
            recommendations.append("Processing completed successfully with no issues identified")
        
        return recommendations
    
    def save_reports(self, comprehensive_report: Dict):
        """Save comprehensive reports to files."""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        
        # Save JSON report
        json_filename = os.path.join(self.processing_reports_dir, f'enhanced_transform_report_{timestamp}.json')
        with open(json_filename, 'w') as f:
            json.dump(comprehensive_report, f, indent=2, default=str)
        
        # Save human-readable report
        markdown_filename = os.path.join(self.processing_reports_dir, f'enhanced_transform_report_{timestamp}.md')
        markdown_content = self.generate_markdown_report(comprehensive_report)
        with open(markdown_filename, 'w') as f:
            f.write(markdown_content)
        
        # Save validation report separately
        if 'validation_analysis' in comprehensive_report:
            validation_filename = os.path.join(self.validation_reports_dir, f'validation_analysis_{timestamp}.json')
            with open(validation_filename, 'w') as f:
                json.dump(comprehensive_report['validation_analysis'], f, indent=2, default=str)
        
        self.logger.info(f"📄 Reports saved:")
        self.logger.info(f"   JSON Report: {json_filename}")
        self.logger.info(f"   Markdown Report: {markdown_filename}")
        self.logger.info(f"   Validation Report: {validation_filename}")
    
    def run_data_quality_checks(self) -> Dict:
        """Run comprehensive data quality checks on the clean incidents table."""
        self.logger.info("🔍 Running comprehensive data quality checks...")
        
        connection = self.connect_to_mysql()
        quality_report = {}
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # 1. Coordinate Quality Analysis
            self.logger.info("Analyzing coordinate quality...")
            coord_quality_query = """
                SELECT 
                    'NULL lat' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lat IS NULL

                UNION ALL

                SELECT 
                    'NULL lng' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lng IS NULL

                UNION ALL

                SELECT 
                    'Invalid lat range' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lat < -90 OR lat > 90

                UNION ALL

                SELECT 
                    'Invalid lng range' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lng < -180 OR lng > 180

                UNION ALL

                SELECT 
                    'Zero coordinates' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lat = 0 AND lng = 0

                UNION ALL

                SELECT 
                    'Valid coordinates' as issue_type,
                    COUNT(*) as record_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM amisafe_clean_incidents) * 100), 2) as percentage
                FROM amisafe_clean_incidents 
                WHERE lat IS NOT NULL 
                  AND lng IS NOT NULL 
                  AND lat BETWEEN -90 AND 90 
                  AND lng BETWEEN -180 AND 180 
                  AND NOT (lat = 0 AND lng = 0)
            """
            
            cursor.execute(coord_quality_query)
            coordinate_quality = cursor.fetchall()
            quality_report['coordinate_quality'] = coordinate_quality
            
            # 2. H3 Population Analysis
            self.logger.info("Analyzing H3 column population...")
            h3_analysis = {}
            h3_columns = ['h3_res_5', 'h3_res_6', 'h3_res_7', 'h3_res_8', 'h3_res_9', 
                         'h3_res_10', 'h3_res_11', 'h3_res_12', 'h3_res_13']
            
            # Get total records with valid coordinates
            cursor.execute("""
                SELECT COUNT(*) as total_valid_coords
                FROM amisafe_clean_incidents 
                WHERE lat IS NOT NULL AND lng IS NOT NULL 
                AND lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180
            """)
            total_valid_coords = cursor.fetchone()['total_valid_coords']
            
            for h3_col in h3_columns:
                cursor.execute(f"""
                    SELECT 
                        COUNT(*) as populated_count,
                        ROUND((COUNT(*) / %s * 100), 2) as fill_rate
                    FROM amisafe_clean_incidents 
                    WHERE {h3_col} IS NOT NULL
                """, (total_valid_coords,))
                
                result = cursor.fetchone()
                h3_analysis[h3_col] = {
                    'populated_count': result['populated_count'],
                    'missing_count': total_valid_coords - result['populated_count'],
                    'fill_rate': result['fill_rate'],
                    'total_valid_coords': total_valid_coords
                }
            
            quality_report['h3_population'] = h3_analysis
            
            # 3. Sample Missing H3 Records
            self.logger.info("Sampling records missing H3 indexes...")
            sample_query = """
                SELECT 
                    id, incident_id, lat, lng,
                    CASE 
                        WHEN lat IS NULL THEN 'NULL_LAT'
                        WHEN lng IS NULL THEN 'NULL_LNG' 
                        WHEN lat < -90 OR lat > 90 THEN 'INVALID_LAT'
                        WHEN lng < -180 OR lng > 180 THEN 'INVALID_LNG'
                        WHEN lat = 0 AND lng = 0 THEN 'ZERO_COORDINATES'
                        ELSE 'VALID'
                    END as coordinate_status,
                    h3_res_5, h3_res_11, h3_res_12, h3_res_13,
                    dc_dist, location_block
                FROM amisafe_clean_incidents 
                WHERE h3_res_5 IS NULL OR h3_res_11 IS NULL OR h3_res_12 IS NULL OR h3_res_13 IS NULL
                ORDER BY id
                LIMIT 20
            """
            
            cursor.execute(sample_query)
            missing_h3_samples = cursor.fetchall()
            quality_report['missing_h3_samples'] = missing_h3_samples
            
            # 4. Overall Data Quality Summary
            cursor.execute("SELECT COUNT(*) as total_records FROM amisafe_clean_incidents")
            total_records = cursor.fetchone()['total_records']
            
            # Calculate overall quality metrics
            valid_coords = next((item['record_count'] for item in coordinate_quality if item['issue_type'] == 'Valid coordinates'), 0)
            coord_quality_rate = (valid_coords / total_records * 100) if total_records > 0 else 0
            
            # Calculate average H3 fill rate - convert to float to avoid decimal/float mixing
            avg_h3_fill_rate = sum(float(col['fill_rate']) for col in h3_analysis.values()) / len(h3_analysis)
            
            quality_report['summary'] = {
                'total_records': int(total_records),
                'coordinate_quality_rate': round(float(coord_quality_rate), 2),
                'average_h3_fill_rate': round(float(avg_h3_fill_rate), 2),
                'records_needing_h3_population': int(total_valid_coords) - min(int(col['populated_count']) for col in h3_analysis.values()),
                'data_quality_grade': self.calculate_data_quality_grade(float(coord_quality_rate), float(avg_h3_fill_rate))
            }
            
            cursor.close()
            
        except Exception as e:
            self.logger.error(f"Data quality check failed: {e}")
            quality_report['error'] = str(e)
        finally:
            if connection and connection.is_connected():
                connection.close()
        
        return quality_report
    
    def calculate_data_quality_grade(self, coord_rate: float, h3_rate: float) -> str:
        """Calculate an overall data quality grade."""
        overall_score = (coord_rate + h3_rate) / 2
        
        if overall_score >= 95:
            return "A+ (Excellent)"
        elif overall_score >= 90:
            return "A (Very Good)"
        elif overall_score >= 80:
            return "B (Good)"
        elif overall_score >= 70:
            return "C (Fair)"
        elif overall_score >= 60:
            return "D (Poor)"
        else:
            return "F (Critical Issues)"
    
    def print_data_quality_report(self, quality_report: Dict):
        """Print a formatted data quality report."""
        if 'error' in quality_report:
            self.logger.error(f"Data quality check failed: {quality_report['error']}")
            return
        
        print("\n" + "="*80)
        print("📊 COMPREHENSIVE DATA QUALITY REPORT")
        print("="*80)
        
        # Summary
        summary = quality_report['summary']
        print(f"\n🎯 OVERALL SUMMARY:")
        print(f"   Total Records: {summary['total_records']:,}")
        print(f"   Coordinate Quality: {summary['coordinate_quality_rate']}%")
        print(f"   Average H3 Fill Rate: {summary['average_h3_fill_rate']}%")
        print(f"   Data Quality Grade: {summary['data_quality_grade']}")
        print(f"   Records Needing H3: {summary['records_needing_h3_population']:,}")
        
        # Coordinate Quality Details
        print(f"\n🌍 COORDINATE QUALITY BREAKDOWN:")
        for item in quality_report['coordinate_quality']:
            status = "✅" if item['issue_type'] == 'Valid coordinates' else "⚠️" if item['record_count'] == 0 else "❌"
            print(f"   {status} {item['issue_type']}: {item['record_count']:,} ({item['percentage']}%)")
        
        # H3 Population Details
        print(f"\n🗺️  H3 GEOSPATIAL INDEX POPULATION:")
        h3_pop = quality_report['h3_population']
        for col, data in h3_pop.items():
            status = "✅" if data['fill_rate'] >= 95 else "⚠️" if data['fill_rate'] >= 50 else "❌"
            print(f"   {status} {col}: {data['populated_count']:,}/{data['total_valid_coords']:,} ({data['fill_rate']}%) - Missing: {data['missing_count']:,}")
        
        # Sample of problematic records
        if quality_report['missing_h3_samples']:
            print(f"\n🔍 SAMPLE RECORDS MISSING H3 INDEXES (showing 10 of {len(quality_report['missing_h3_samples'])}):")
            samples = quality_report['missing_h3_samples'][:10]
            print(f"{'ID':<10} {'Coord Status':<15} {'Lat':<12} {'Lng':<12} {'H3_5':<8} {'H3_11':<8} {'Location':<25}")
            print("-" * 90)
            for sample in samples:
                h3_5_status = "✓" if sample['h3_res_5'] else "✗"
                h3_11_status = "✓" if sample['h3_res_11'] else "✗"
                location = (sample['location_block'] or '')[:24]
                print(f"{sample['id']:<10} {sample['coordinate_status']:<15} {sample['lat']:<12.6f} {sample['lng']:<12.6f} {h3_5_status:<8} {h3_11_status:<8} {location:<25}")
        
        print("\n" + "="*80)

    def generate_markdown_report(self, report: Dict) -> str:
        """Generate human-readable markdown report."""
        status = report['final_status']
        session = report['processing_session']
        
        duration_str = "N/A"
        if session['duration_seconds']:
            duration_str = f"{session['duration_seconds']:.1f} seconds"
        
        markdown = f"""# Enhanced Transform Processing Report

**Generated:** {report['report_timestamp']}

## 📊 Processing Summary

| Metric | Value |
|--------|-------|
| **Total Raw Records** | {status['total_raw_records']:,} |
| **Total Transform Records** | {status['total_transform_records']:,} |
| **Completion Percentage** | {status['completion_percentage']:.1f}% |
| **Records Remaining** | {status['records_remaining']:,} |

## ⏱️ Session Information

| Metric | Value |
|--------|-------|
| **Session Duration** | {duration_str} |
| **Records Processed This Session** | {session['records_processed_this_session']:,} |
| **Records Inserted This Session** | {session['records_inserted_this_session']:,} |
| **Batches Processed** | {session['batches_processed_this_session']:,} |
| **Batch Failures** | {session['batch_failures']:,} |

## 🔍 Processing Range

| Metric | Value |
|--------|-------|
| **Min Processed ID** | {status['processing_range']['min_id']:,} |
| **Max Processed ID** | {status['processing_range']['max_id']:,} |
| **Total Batches** | {status['batch_summary']['total_batches']:,} |

## 💡 Recommendations

"""
        
        for i, rec in enumerate(report['recommendations'], 1):
            markdown += f"{i}. {rec}\n"
        
        if session['processing_errors']:
            markdown += f"\n## ⚠️ Processing Errors\n\n"
            for i, error in enumerate(session['processing_errors'], 1):
                markdown += f"{i}. {error}\n"
        
        markdown += f"\n---\n*Report generated by Enhanced Transform Processor*"
        
        return markdown

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='Enhanced AmISafe Transform Processor')
    parser.add_argument('--continue-processing', action='store_true', help='Continue processing from last checkpoint')
    parser.add_argument('--full-reprocess', action='store_true', help='Reprocess all records (clears transform layer)')
    parser.add_argument('--validation-only', action='store_true', help='Run validation analysis only')
    parser.add_argument('--status-check', action='store_true', help='Check current processing status')
    parser.add_argument('--populate-h3-columns', action='store_true', help='Populate missing H3 columns for existing records')
    parser.add_argument('--h3-columns', nargs='+', help='Specific H3 columns to populate (e.g., h3_res_5 h3_res_11)', 
                        choices=['h3_res_5', 'h3_res_6', 'h3_res_7', 'h3_res_8', 'h3_res_9', 'h3_res_10', 'h3_res_11', 'h3_res_12', 'h3_res_13'])
    parser.add_argument('--data-quality-check', action='store_true', help='Run comprehensive data quality analysis')
    parser.add_argument('--batch-size', type=int, default=25000, help='Batch size for processing')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default=os.environ.get('DB_PASSWORD'), help='MySQL password (from DB_PASSWORD env var)')
    parser.add_argument('--mysql-database', default='amisafe_database', help='MySQL database')
    parser.add_argument('--mysql-socket', default=None, help='MySQL unix socket path (e.g., /var/run/mysqld/mysqld.sock)')
    
    args = parser.parse_args()
    
    if not args.mysql_password:
        print('ERROR: DB_PASSWORD environment variable is required')
        sys.exit(1)
    
    # Initialize enhanced processor
    processor = EnhancedTransformProcessor(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database,
        mysql_socket=args.mysql_socket
    )
    
    print("="*100)
    print("ENHANCED AMISAFE TRANSFORM PROCESSOR")
    print("="*100)
    print(f"Processing Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    try:
        if args.status_check:
            # Status check only
            status = processor.get_processing_status()
            print(f"\n📊 CURRENT PROCESSING STATUS")
            print(f"Total Raw Records: {status['total_raw_records']:,}")
            print(f"Transform Records: {status['total_transform_records']:,}")
            print(f"Completion: {status['completion_percentage']:.1f}%")
            print(f"Records Remaining: {status['records_remaining']:,}")
            
            # Enhanced: Display column fill rates and data quality
            if 'data_quality_summary' in status:
                quality = status['data_quality_summary']
                print(f"\n📋 DATA QUALITY SUMMARY")
                print(f"Overall Data Completeness: {quality['overall_data_completeness']}%")
                print(f"Fully Populated Columns: {quality['fully_populated_columns']}/{quality['total_columns_checked']}")
                
                # Show incomplete columns if any exist
                if status['incomplete_columns']:
                    print(f"\n⚠️  INCOMPLETE COLUMNS ({len(status['incomplete_columns'])} found):")
                    
                    # Group by data type for better organization
                    h3_columns = {}
                    core_columns = {}
                    other_columns = {}
                    
                    for col, info in status['incomplete_columns'].items():
                        if info['data_type'] == 'h3_index':
                            h3_columns[col] = info
                        elif info['data_type'] in ['coordinate', 'datetime', 'string']:
                            core_columns[col] = info
                        else:
                            other_columns[col] = info
                    
                    # Display H3 columns first (most important for our use case)
                    if h3_columns:
                        print(f"  🗺️  H3 Geospatial Columns:")
                        for col, info in sorted(h3_columns.items()):
                            print(f"    {col}: {info['fill_rate']:.1f}% ({info['missing_count']:,} missing)")
                    
                    # Display core data columns
                    if core_columns:
                        print(f"  📊 Core Data Columns:")
                        for col, info in sorted(core_columns.items()):
                            print(f"    {col}: {info['fill_rate']:.1f}% ({info['missing_count']:,} missing)")
                    
                    # Display other columns
                    if other_columns:
                        print(f"  🔧 Other Columns:")
                        for col, info in sorted(other_columns.items()):
                            print(f"    {col}: {info['fill_rate']:.1f}% ({info['missing_count']:,} missing)")
                else:
                    print(f"\n✅ ALL COLUMNS 100% POPULATED - Excellent data quality!")
            
        elif args.validation_only:
            # Validation analysis only
            print(f"\n🔍 RUNNING VALIDATION ANALYSIS")
            validation_report = processor.accounting_tool.generate_complete_record_accounting()
            # Save validation report
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            validation_filename = os.path.join(processor.validation_reports_dir, f'validation_only_{timestamp}.json')
            with open(validation_filename, 'w') as f:
                json.dump(validation_report, f, indent=2, default=str)
            print(f"✅ Validation report saved: {validation_filename}")
            
        elif args.data_quality_check:
            # Run comprehensive data quality analysis
            print(f"\n🔍 RUNNING COMPREHENSIVE DATA QUALITY ANALYSIS")
            quality_report = processor.run_data_quality_checks()
            processor.print_data_quality_report(quality_report)
            
            # Save quality report
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            quality_filename = os.path.join(processor.validation_reports_dir, f'data_quality_report_{timestamp}.json')
            with open(quality_filename, 'w') as f:
                json.dump(quality_report, f, indent=2, default=str)
            print(f"\n📄 Quality report saved: {quality_filename}")
            
        elif args.populate_h3_columns:
            # Populate H3 columns for existing records
            print(f"\n🔄 POPULATING H3 COLUMNS")
            target_columns = args.h3_columns if args.h3_columns else None
            if target_columns:
                print(f"Target columns: {', '.join(target_columns)}")
            else:
                print("Target columns: All H3 columns (h3_res_5 through h3_res_13)")
            
            results = processor.populate_h3_columns(target_columns=target_columns, batch_size=args.batch_size)
            
            if results.get('success'):
                print(f"\n✅ H3 POPULATION COMPLETE")
                print(f"Records Updated: {results['total_updated']:,}")
                print(f"Duration: {results['duration_seconds']:.1f} seconds")
                print(f"Columns Updated: {', '.join(results['target_columns'])}")
            else:
                print(f"\n❌ H3 POPULATION FAILED")
                print(f"Error: {results.get('error', 'Unknown error')}")
            
        elif args.continue_processing or not any([args.full_reprocess, args.validation_only, args.status_check, args.populate_h3_columns, args.data_quality_check]):
            # Continue processing (default)
            print(f"\n🔄 CONTINUING TRANSFORM PROCESSING")
            results = processor.continue_processing(batch_size=args.batch_size)
            
            print(f"\n✅ PROCESSING COMPLETE")
            print(f"Final Status: {results['final_status']['completion_percentage']:.1f}% complete")
            print(f"Records Processed This Session: {results['processing_session']['records_processed_this_session']:,}")
            print(f"Reports saved to: {processor.processing_reports_dir}")
        
        elif args.full_reprocess:
            # Full reprocess: clear Silver layer and reset Bronze status
            print("🔄 FULL REPROCESS MODE")
            print("This will:")
            print("  1. Truncate the Silver layer (amisafe_clean_incidents)")
            print("  2. Reset all Bronze records to 'raw' status")
            print("  3. Start fresh processing")
            
            confirm = input("\nContinue with full reprocess? (yes/no): ")
            if confirm.lower() not in ['yes', 'y']:
                print("Full reprocess cancelled.")
                return
            
            # Truncate Silver and reset Bronze status
            processor = EnhancedTransformProcessor()
            connection = processor.connect_to_mysql()
            cursor = connection.cursor()
            
            print("🗑️  Truncating Silver layer...")
            cursor.execute("TRUNCATE TABLE amisafe_clean_incidents")
            
            print("🔄 Resetting Bronze processing status...")
            cursor.execute("UPDATE amisafe_raw_incidents SET processing_status = 'raw'")
            
            connection.commit()
            cursor.close()
            connection.close()
            
            print("✅ Reset complete! Starting fresh processing...")
            
            # Start processing
            processor = EnhancedTransformProcessor()
            processor.continue_processing()
            
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        sys.exit(1)
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()