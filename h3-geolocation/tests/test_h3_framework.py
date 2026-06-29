"""
Test Suite for H3GeolocationFramework Core Functionality

This module contains comprehensive unit tests for the main H3GeolocationFramework class,
testing coordinate conversion, spatial analysis, visualization, and data processing capabilities.
"""

import pytest
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from h3_framework import H3GeolocationFramework
import h3
import numpy as np
from unittest.mock import patch, MagicMock
import tempfile
import json

class TestH3GeolocationFramework:
    """Test suite for H3GeolocationFramework class."""
    
    def setup_method(self):
        """Setup for each test method."""
        self.framework = H3GeolocationFramework()
        
        # Test coordinates (St. Louis landmarks)
        self.test_coords = {
            'gateway_arch': (38.6247, -90.1848),
            'busch_stadium': (38.6226, -90.1928),
            'forest_park': (38.6355, -90.2732)
        }
        
        # Known H3 indices for validation
        self.known_h3_indices = {
            'gateway_arch_res9': '89283082c2fffff',
            'busch_stadium_res9': '89283082c0fffff'
        }
    
    def test_initialization(self):
        """Test framework initialization."""
        framework = H3GeolocationFramework()
        assert framework is not None
        assert hasattr(framework, 'coords_to_h3')
        assert hasattr(framework, 'h3_to_coords')
    
    def test_coords_to_h3_basic(self):
        """Test basic coordinate to H3 conversion."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        assert isinstance(h3_index, str)
        assert len(h3_index) == 15  # Standard H3 index length for resolution 9
        assert h3.cell_to_res(h3_index) == 9
    
    def test_coords_to_h3_different_resolutions(self):
        """Test coordinate conversion at different resolutions."""
        lat, lng = self.test_coords['gateway_arch']
        
        for resolution in range(0, 16):
            h3_index = self.framework.coords_to_h3(lat, lng, resolution)
            assert h3.cell_to_res(h3_index) == resolution
    
    def test_coords_to_h3_invalid_coordinates(self):
        """Test coordinate conversion with invalid inputs."""
        # Invalid latitude
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(91.0, 0.0, 9)  # Lat > 90
        
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(-91.0, 0.0, 9)  # Lat < -90
        
        # Invalid longitude
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(0.0, 181.0, 9)  # Lng > 180
        
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(0.0, -181.0, 9)  # Lng < -180
        
        # Invalid resolution
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(0.0, 0.0, 16)  # Resolution > 15
        
        with pytest.raises(ValueError):
            self.framework.coords_to_h3(0.0, 0.0, -1)  # Resolution < 0
    
    def test_h3_to_coords_basic(self):
        """Test basic H3 to coordinate conversion."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        converted_lat, converted_lng = self.framework.h3_to_coords(h3_index)
        
        # Should be close to original coordinates (within H3 precision)
        assert abs(converted_lat - lat) < 0.001
        assert abs(converted_lng - lng) < 0.001
    
    def test_h3_to_coords_invalid_index(self):
        """Test H3 to coordinate conversion with invalid index."""
        with pytest.raises(ValueError):
            self.framework.h3_to_coords("invalid_h3_index")
        
        with pytest.raises(ValueError):
            self.framework.h3_to_coords("")
        
        with pytest.raises(ValueError):
            self.framework.h3_to_coords(None)
    
    def test_round_trip_conversion(self):
        """Test round-trip coordinate <-> H3 conversion."""
        for name, (lat, lng) in self.test_coords.items():
            h3_index = self.framework.coords_to_h3(lat, lng, 9)
            converted_lat, converted_lng = self.framework.h3_to_coords(h3_index)
            
            # Convert back to H3
            h3_index2 = self.framework.coords_to_h3(converted_lat, converted_lng, 9)
            
            # Should get the same H3 index
            assert h3_index == h3_index2
    
    def test_get_neighbors_basic(self):
        """Test basic neighbor finding."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        neighbors = self.framework.get_neighbors(h3_index, k=1)
        
        assert isinstance(neighbors, list)
        assert len(neighbors) == 6  # Hexagon has 6 direct neighbors
        assert all(isinstance(n, str) for n in neighbors)
        assert h3_index not in neighbors  # Original should not be in neighbors
    
    def test_get_neighbors_k_rings(self):
        """Test neighbor finding with different k-ring values."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        # Test different k values
        neighbors_k1 = self.framework.get_neighbors(h3_index, k=1)
        neighbors_k2 = self.framework.get_neighbors(h3_index, k=2)
        
        assert len(neighbors_k1) == 6
        assert len(neighbors_k2) == 18  # k=2 ring has 18 hexagons
        
        # All k=1 neighbors should be in k=2 neighbors
        assert all(n in neighbors_k2 for n in neighbors_k1)
    
    def test_get_neighbors_include_center(self):
        """Test neighbor finding with center included."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        neighbors = self.framework.get_neighbors(h3_index, k=1, include_center=True)
        
        assert len(neighbors) == 7  # 6 neighbors + 1 center
        assert h3_index in neighbors
    
    def test_calculate_distance(self):
        """Test distance calculation between coordinates."""
        coord1 = self.test_coords['gateway_arch']
        coord2 = self.test_coords['busch_stadium']
        
        distance = self.framework.calculate_distance(coord1, coord2)
        
        assert isinstance(distance, float)
        assert distance > 0
        # Known approximate distance between Gateway Arch and Busch Stadium
        assert 500 < distance < 1500  # meters
    
    def test_calculate_distance_same_point(self):
        """Test distance calculation for same point."""
        coord = self.test_coords['gateway_arch']
        distance = self.framework.calculate_distance(coord, coord)
        
        assert distance == 0.0
    
    def test_calculate_hexagon_area(self):
        """Test hexagon area calculation."""
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        
        area = self.framework.calculate_hexagon_area(h3_index)
        
        assert isinstance(area, float)
        assert area > 0
        # Resolution 9 hexagons should be around 100-200 square meters
        assert 50 < area < 500
    
    def test_calculate_hexagon_area_different_resolutions(self):
        """Test hexagon area calculation at different resolutions."""
        lat, lng = self.test_coords['gateway_arch']
        
        areas = {}
        for resolution in [7, 8, 9, 10]:
            h3_index = self.framework.coords_to_h3(lat, lng, resolution)
            areas[resolution] = self.framework.calculate_hexagon_area(h3_index)
        
        # Higher resolution should have smaller area
        assert areas[7] > areas[8] > areas[9] > areas[10]
    
    def test_get_hexagons_in_radius(self):
        """Test getting hexagons within radius."""
        lat, lng = self.test_coords['gateway_arch']
        
        hexagons = self.framework.get_hexagons_in_radius(lat, lng, 1000, resolution=9)
        
        assert isinstance(hexagons, list)
        assert len(hexagons) > 0
        assert all(isinstance(h, str) for h in hexagons)
        
        # All hexagons should be at the same resolution
        assert all(h3.cell_to_res(h) == 9 for h in hexagons)
    
    def test_get_hexagons_in_radius_different_radii(self):
        """Test getting hexagons with different radii."""
        lat, lng = self.test_coords['gateway_arch']
        
        hexagons_500m = self.framework.get_hexagons_in_radius(lat, lng, 500, resolution=9)
        hexagons_1000m = self.framework.get_hexagons_in_radius(lat, lng, 1000, resolution=9)
        
        # Larger radius should include more hexagons
        assert len(hexagons_1000m) > len(hexagons_500m)
        
        # All hexagons from smaller radius should be in larger radius
        assert all(h in hexagons_1000m for h in hexagons_500m)
    
    def test_aggregate_points_to_hexagons(self):
        """Test point aggregation to hexagons."""
        # Create test points
        points = []
        for name, (lat, lng) in self.test_coords.items():
            # Add some random points around each landmark
            for i in range(5):
                offset_lat = lat + np.random.uniform(-0.001, 0.001)
                offset_lng = lng + np.random.uniform(-0.001, 0.001)
                points.append({
                    'lat': offset_lat,
                    'lng': offset_lng,
                    'value': np.random.randint(1, 100),
                    'category': name
                })
        
        aggregated = self.framework.aggregate_points_to_hexagons(points, resolution=9)
        
        assert isinstance(aggregated, dict)
        assert len(aggregated) > 0
        
        # Check aggregated data structure
        for h3_index, data in aggregated.items():
            assert isinstance(h3_index, str)
            assert h3.cell_to_res(h3_index) == 9
            assert 'count' in data
            assert 'center_lat' in data
            assert 'center_lng' in data
            assert data['count'] > 0
    
    def test_create_interactive_map(self):
        """Test interactive map creation."""
        # Create test data
        data = []
        for name, (lat, lng) in self.test_coords.items():
            data.append({
                'lat': lat,
                'lng': lng,
                'name': name,
                'value': np.random.randint(1, 100)
            })
        
        # Mock folium to avoid actual map creation in tests
        with patch('h3_framework.folium') as mock_folium:
            mock_map = MagicMock()
            mock_folium.Map.return_value = mock_map
            
            result = self.framework.create_interactive_map(data)
            
            # Verify folium.Map was called
            mock_folium.Map.assert_called_once()
            assert result == mock_map
    
    def test_visualize_hexagons(self):
        """Test hexagon visualization."""
        # Get some test hexagons
        lat, lng = self.test_coords['gateway_arch']
        center_h3 = self.framework.coords_to_h3(lat, lng, 9)
        hexagons = [center_h3] + self.framework.get_neighbors(center_h3, k=1)
        
        # Mock folium to avoid actual visualization in tests
        with patch('h3_framework.folium') as mock_folium:
            mock_map = MagicMock()
            mock_folium.Map.return_value = mock_map
            
            result = self.framework.visualize_hexagons(hexagons)
            
            # Verify map creation
            mock_folium.Map.assert_called_once()
            assert result == mock_map
    
    def test_export_to_geojson(self):
        """Test GeoJSON export functionality."""
        # Create test hexagons
        lat, lng = self.test_coords['gateway_arch']
        h3_index = self.framework.coords_to_h3(lat, lng, 9)
        hexagons = [h3_index]
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.geojson', delete=False) as f:
            temp_file = f.name
        
        try:
            # Export to GeoJSON
            geojson_data = self.framework.export_to_geojson(hexagons, temp_file)
            
            # Verify structure
            assert isinstance(geojson_data, dict)
            assert geojson_data['type'] == 'FeatureCollection'
            assert 'features' in geojson_data
            assert len(geojson_data['features']) == 1
            
            # Verify feature structure
            feature = geojson_data['features'][0]
            assert feature['type'] == 'Feature'
            assert 'geometry' in feature
            assert 'properties' in feature
            assert feature['properties']['h3_index'] == h3_index
            
            # Verify file was created
            assert os.path.exists(temp_file)
            
            # Verify file content
            with open(temp_file, 'r') as f:
                file_data = json.load(f)
            assert file_data == geojson_data
            
        finally:
            # Clean up
            if os.path.exists(temp_file):
                os.unlink(temp_file)
    
    def test_edge_cases(self):
        """Test edge cases and boundary conditions."""
        # Test at poles
        north_pole_h3 = self.framework.coords_to_h3(89.9, 0.0, 5)
        assert isinstance(north_pole_h3, str)
        
        south_pole_h3 = self.framework.coords_to_h3(-89.9, 0.0, 5)
        assert isinstance(south_pole_h3, str)
        
        # Test at international date line
        dateline_h3 = self.framework.coords_to_h3(0.0, 179.9, 5)
        assert isinstance(dateline_h3, str)
        
        dateline_h3_neg = self.framework.coords_to_h3(0.0, -179.9, 5)
        assert isinstance(dateline_h3_neg, str)
    
    def test_empty_data_handling(self):
        """Test handling of empty data inputs."""
        # Empty point list
        aggregated = self.framework.aggregate_points_to_hexagons([], resolution=9)
        assert aggregated == {}
        
        # Empty hexagon list
        with patch('h3_framework.folium') as mock_folium:
            mock_map = MagicMock()
            mock_folium.Map.return_value = mock_map
            
            result = self.framework.visualize_hexagons([])
            mock_folium.Map.assert_called_once()
    
    def test_performance_with_large_dataset(self):
        """Test performance with larger datasets."""
        # Generate larger dataset
        large_dataset = []
        for i in range(1000):
            lat = 38.6 + np.random.uniform(-0.1, 0.1)
            lng = -90.2 + np.random.uniform(-0.1, 0.1)
            large_dataset.append({
                'lat': lat,
                'lng': lng,
                'value': np.random.randint(1, 100)
            })
        
        # This should complete in reasonable time
        import time
        start_time = time.time()
        
        aggregated = self.framework.aggregate_points_to_hexagons(large_dataset, resolution=8)
        
        end_time = time.time()
        processing_time = end_time - start_time
        
        # Should complete within 5 seconds
        assert processing_time < 5.0
        assert len(aggregated) > 0
    
    def teardown_method(self):
        """Cleanup after each test method."""
        # Clean up any temporary files if needed
        pass

if __name__ == '__main__':
    pytest.main([__file__, '-v'])