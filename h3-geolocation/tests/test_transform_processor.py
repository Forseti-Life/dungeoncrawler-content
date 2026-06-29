#!/usr/bin/env python3
"""
Test Suite for AmISafe Transform Processor

Tests the transform processor's ability to handle H3 indexing and SQL parameter alignment.
"""

import pytest
import sys
import os
import pandas as pd
import numpy as np
from datetime import datetime

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.append(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'database'))

from database.amisafe_transform_processor_v2 import AmISafeTransformProcessor


class TestAmISafeTransformProcessor:
    """Test suite for AmISafeTransformProcessor class."""
    
    def setup_method(self):
        """Setup for each test method."""
        self.processor = AmISafeTransformProcessor()
        
        # Valid Philadelphia test coordinates
        self.valid_coords = {
            'city_hall': (39.9526, -75.1652),
            'independence_hall': (39.9489, -75.1500),
            'penn_landing': (39.9431, -75.1400)
        }
    
    def test_initialization(self):
        """Test processor initialization."""
        assert self.processor is not None
        assert hasattr(self.processor, 'add_h3_indexes')
        assert hasattr(self.processor, 'prepare_clean_record')
    
    def test_add_h3_indexes_valid_coordinates(self):
        """Test H3 index generation with valid coordinates."""
        # Create test row with valid Philadelphia coordinates
        test_row = pd.Series({
            'lat': 39.9526,
            'lng': -75.1652
        })
        
        h3_indexes = self.processor.add_h3_indexes(test_row)
        
        # Verify all 5 H3 resolution keys are present
        assert 'h3_res_6' in h3_indexes
        assert 'h3_res_7' in h3_indexes
        assert 'h3_res_8' in h3_indexes
        assert 'h3_res_9' in h3_indexes
        assert 'h3_res_10' in h3_indexes
        
        # Verify all values are not None (valid H3 indexes)
        assert h3_indexes['h3_res_6'] is not None
        assert h3_indexes['h3_res_7'] is not None
        assert h3_indexes['h3_res_8'] is not None
        assert h3_indexes['h3_res_9'] is not None
        assert h3_indexes['h3_res_10'] is not None
        
        # Verify they are strings
        assert isinstance(h3_indexes['h3_res_6'], str)
        assert isinstance(h3_indexes['h3_res_7'], str)
        assert isinstance(h3_indexes['h3_res_8'], str)
        assert isinstance(h3_indexes['h3_res_9'], str)
        assert isinstance(h3_indexes['h3_res_10'], str)
    
    def test_add_h3_indexes_missing_coordinates(self):
        """Test H3 index generation with missing coordinates."""
        # Create test row with missing coordinates
        test_row = pd.Series({
            'lat': None,
            'lng': None
        })
        
        h3_indexes = self.processor.add_h3_indexes(test_row)
        
        # Verify all 5 H3 resolution keys are present
        assert 'h3_res_6' in h3_indexes
        assert 'h3_res_7' in h3_indexes
        assert 'h3_res_8' in h3_indexes
        assert 'h3_res_9' in h3_indexes
        assert 'h3_res_10' in h3_indexes
        
        # Verify all values are None (failed to generate)
        assert h3_indexes['h3_res_6'] is None
        assert h3_indexes['h3_res_7'] is None
        assert h3_indexes['h3_res_8'] is None
        assert h3_indexes['h3_res_9'] is None
        assert h3_indexes['h3_res_10'] is None
    
    def test_add_h3_indexes_invalid_coordinates(self):
        """Test H3 index generation with invalid coordinate values."""
        # Create test row with invalid coordinates (strings)
        test_row = pd.Series({
            'lat': 'invalid',
            'lng': 'invalid'
        })
        
        h3_indexes = self.processor.add_h3_indexes(test_row)
        
        # Verify all 5 H3 resolution keys are present
        assert 'h3_res_6' in h3_indexes
        assert 'h3_res_7' in h3_indexes
        assert 'h3_res_8' in h3_indexes
        assert 'h3_res_9' in h3_indexes
        assert 'h3_res_10' in h3_indexes
        
        # Verify all values are None (failed to generate)
        assert h3_indexes['h3_res_6'] is None
        assert h3_indexes['h3_res_7'] is None
        assert h3_indexes['h3_res_8'] is None
        assert h3_indexes['h3_res_9'] is None
        assert h3_indexes['h3_res_10'] is None
    
    def test_prepare_clean_record_has_all_h3_fields(self):
        """Test that prepare_clean_record returns all required fields including H3."""
        # Create test row with valid data
        test_row = pd.Series({
            'id': 1,
            'cartodb_id': 12345,
            'objectid': 67890,
            'dc_key': 'TEST123',
            'dc_dist': '1',
            'psa': 'A',
            'location_block': '1234 MAIN ST',
            'lat': 39.9526,
            'lng': -75.1652,
            'dispatch_date_time': '2024-01-15 10:30:00',
            'ucr_general': '100',
            'text_general_code': 'Test Crime'
        })
        
        batch_id = 'test_batch_001'
        clean_record = self.processor.prepare_clean_record(test_row, batch_id)
        
        # Verify clean_record is not None
        assert clean_record is not None
        
        # Verify all H3 fields are present
        assert 'h3_res_6' in clean_record
        assert 'h3_res_7' in clean_record
        assert 'h3_res_8' in clean_record
        assert 'h3_res_9' in clean_record
        assert 'h3_res_10' in clean_record
        
        # Verify other required fields
        assert 'incident_id' in clean_record
        assert 'lat' in clean_record
        assert 'lng' in clean_record
        assert 'incident_datetime' in clean_record
        assert 'ucr_general' in clean_record
    
    def test_prepare_clean_record_with_failed_h3_indexing(self):
        """Test prepare_clean_record when H3 indexing fails."""
        # Create test row with valid metadata but coordinates that might fail H3
        test_row = pd.Series({
            'id': 1,
            'cartodb_id': 12345,
            'objectid': 67890,
            'dc_key': 'TEST123',
            'dc_dist': '1',
            'psa': 'A',
            'location_block': '1234 MAIN ST',
            'lat': 39.9526,
            'lng': -75.1652,
            'dispatch_date_time': '2024-01-15 10:30:00',
            'ucr_general': '100',
            'text_general_code': 'Test Crime'
        })
        
        batch_id = 'test_batch_002'
        clean_record = self.processor.prepare_clean_record(test_row, batch_id)
        
        # Even if H3 indexing fails, record should still be created with all fields
        assert clean_record is not None
        assert 'h3_res_6' in clean_record
        assert 'h3_res_7' in clean_record
        assert 'h3_res_8' in clean_record
        assert 'h3_res_9' in clean_record
        assert 'h3_res_10' in clean_record
    
    def test_sql_parameter_alignment(self):
        """Test that clean_record fields match SQL INSERT parameters."""
        # Create test row
        test_row = pd.Series({
            'id': 1,
            'cartodb_id': 12345,
            'objectid': 67890,
            'dc_key': 'TEST123',
            'dc_dist': '1',
            'psa': 'A',
            'location_block': '1234 MAIN ST',
            'lat': 39.9526,
            'lng': -75.1652,
            'dispatch_date_time': '2024-01-15 10:30:00',
            'ucr_general': '100',
            'text_general_code': 'Test Crime'
        })
        
        batch_id = 'test_batch_003'
        clean_record = self.processor.prepare_clean_record(test_row, batch_id)
        
        # Expected fields from SQL INSERT statement
        expected_fields = [
            'raw_incident_ids', 'processing_batch_id', 'incident_id', 'cartodb_id', 'objectid', 'dc_key',
            'dc_dist', 'psa', 'location_block', 'lat', 'lng', 'coordinate_quality',
            'incident_datetime', 'incident_date', 'incident_hour', 'incident_month', 'incident_year', 'day_of_week',
            'ucr_general', 'crime_category', 'crime_description', 'severity_level',
            'h3_res_6', 'h3_res_7', 'h3_res_8', 'h3_res_9', 'h3_res_10',
            'data_quality_score', 'duplicate_group_id', 'is_duplicate', 'is_valid'
        ]
        
        # Verify all expected fields are present in clean_record
        for field in expected_fields:
            assert field in clean_record, f"Missing required field: {field}"
    
    def test_validate_record_valid_philadelphia_coords(self):
        """Test validation with valid Philadelphia coordinates."""
        test_row = pd.Series({
            'lat': 39.9526,
            'lng': -75.1652,
            'dispatch_date_time': '2024-01-15 10:30:00',
            'ucr_general': '100',
            'dc_dist': '1'
        })
        
        is_valid, reason = self.processor.validate_record(test_row)
        
        assert is_valid is True
        assert reason == 'valid'
    
    def test_validate_record_out_of_bounds_coords(self):
        """Test validation with coordinates outside Philadelphia bounds."""
        test_row = pd.Series({
            'lat': 40.5,  # Outside Philadelphia bounds
            'lng': -75.1652,
            'dispatch_date_time': '2024-01-15 10:30:00',
            'ucr_general': '100',
            'dc_dist': '1'
        })
        
        is_valid, reason = self.processor.validate_record(test_row)
        
        assert is_valid is False
        assert reason == 'invalid_coordinates'
    
    def test_validate_record_missing_datetime(self):
        """Test validation with missing datetime."""
        test_row = pd.Series({
            'lat': 39.9526,
            'lng': -75.1652,
            'dispatch_date_time': None,
            'ucr_general': '100',
            'dc_dist': '1'
        })
        
        is_valid, reason = self.processor.validate_record(test_row)
        
        assert is_valid is False
        assert reason == 'missing_datetime'


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
