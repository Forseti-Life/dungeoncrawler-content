"""
H3 Geolocation Data Framework

A comprehensive framework for geospatial analysis using Uber's H3 hexagonal grid system.
Provides tools for spatial indexing, data aggregation, and visualization.

Author: St. Louis Integration Team
License: MIT
"""

import h3
import pandas as pd
import numpy as np
import folium
from geopy.distance import geodesic
from typing import List, Tuple, Dict, Optional, Union
import json
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class H3GeolocationFramework:
    """
    Main framework class for H3-based geospatial analysis.
    
    This class provides methods for:
    - Converting coordinates to H3 indices
    - Spatial analysis and aggregation
    - Visualization and mapping
    - Data processing and export
    """
    
    def __init__(self, default_resolution: int = 9):
        """
        Initialize the H3 Geolocation Framework.
        
        Args:
            default_resolution (int): Default H3 resolution level (0-15)
        """
        self.default_resolution = default_resolution
        self.validate_resolution(default_resolution)
        logger.info(f"H3 Framework initialized with resolution {default_resolution}")
    
    @staticmethod
    def validate_resolution(resolution: int) -> None:
        """Validate H3 resolution is within valid range."""
        if not 0 <= resolution <= 15:
            raise ValueError(f"Resolution must be between 0 and 15, got {resolution}")
    
    def coords_to_h3(self, lat: float, lng: float, resolution: Optional[int] = None) -> str:
        """
        Convert latitude/longitude coordinates to H3 index.
        
        Args:
            lat (float): Latitude coordinate
            lng (float): Longitude coordinate
            resolution (int, optional): H3 resolution level
            
        Returns:
            str: H3 hexagon index
        """
        res = resolution or self.default_resolution
        self.validate_resolution(res)
        return h3.latlng_to_cell(lat, lng, res)
    
    def h3_to_coords(self, h3_index: str) -> Tuple[float, float]:
        """
        Convert H3 index to latitude/longitude coordinates.
        
        Args:
            h3_index (str): H3 hexagon index
            
        Returns:
            Tuple[float, float]: (latitude, longitude) coordinates
        """
        return h3.cell_to_latlng(h3_index)
    
    def get_hexagon_boundary(self, h3_index: str) -> List[Tuple[float, float]]:
        """
        Get the boundary coordinates of an H3 hexagon.
        
        Args:
            h3_index (str): H3 hexagon index
            
        Returns:
            List[Tuple[float, float]]: List of (lat, lng) boundary coordinates
        """
        return h3.cell_to_boundary(h3_index)
    
    def get_neighbors(self, h3_index: str, ring_size: int = 1, include_center: bool = False) -> List[str]:
        """
        Get neighboring hexagons within specified ring distance.
        
        Args:
            h3_index (str): Central H3 hexagon index
            ring_size (int): Ring distance (1 = immediate neighbors)
            include_center (bool): Whether to include the center hexagon
            
        Returns:
            List[str]: List of neighboring H3 hexagon indices
        """
        neighbors = list(h3.grid_disk(h3_index, ring_size))
        if not include_center and h3_index in neighbors:
            neighbors.remove(h3_index)
        return neighbors
    
    def calculate_distance(self, coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
        """
        Calculate distance between two coordinates using Haversine formula.
        
        Args:
            coord1 (Tuple[float, float]): First coordinate (lat, lng)
            coord2 (Tuple[float, float]): Second coordinate (lat, lng)
            
        Returns:
            float: Distance in meters
        """
        from geospatial_utils import GeospatialUtils
        return GeospatialUtils.haversine_distance(coord1, coord2)
    
    def get_hexagon_ring(self, h3_index: str, ring_distance: int) -> List[str]:
        """
        Get hexagons at exact ring distance.
        
        Args:
            h3_index (str): Central H3 hexagon index
            ring_distance (int): Exact ring distance
            
        Returns:
            List[str]: List of H3 indices at ring distance
        """
        return list(h3.grid_ring(h3_index, ring_distance))
    
    def get_parent_child_hierarchy(self, h3_index: str) -> Dict[str, Union[str, List[str]]]:
        """
        Get parent and children hexagons in the H3 hierarchy.
        
        Args:
            h3_index (str): H3 hexagon index
            
        Returns:
            Dict: Dictionary with parent and children information
        """
        resolution = h3.get_resolution(h3_index)
        
        result = {
            'index': h3_index,
            'resolution': resolution,
            'parent': None,
            'children': []
        }
        
        # Get parent (lower resolution)
        if resolution > 0:
            result['parent'] = h3.cell_to_parent(h3_index, resolution - 1)
        
        # Get children (higher resolution)
        if resolution < 15:
            result['children'] = list(h3.cell_to_children(h3_index, resolution + 1))
        
        return result
    
    def aggregate_points_to_hexagons(self, points: List[Tuple[float, float]], 
                                   resolution: Optional[int] = None,
                                   data: Optional[List] = None) -> pd.DataFrame:
        """
        Aggregate point data to H3 hexagons.
        
        Args:
            points (List[Tuple[float, float]]): List of (lat, lng) coordinates
            resolution (int, optional): H3 resolution level
            data (List, optional): Associated data for each point
            
        Returns:
            pd.DataFrame: DataFrame with H3 indices and aggregated data
        """
        res = resolution or self.default_resolution
        
        # Convert points to H3 indices
        h3_indices = [self.coords_to_h3(lat, lng, res) for lat, lng in points]
        
        # Create DataFrame
        df_data = {
            'h3_index': h3_indices,
            'latitude': [point[0] for point in points],
            'longitude': [point[1] for point in points]
        }
        
        if data:
            df_data['data'] = data
        
        df = pd.DataFrame(df_data)
        
        # Aggregate by H3 index
        agg_dict = {'latitude': 'mean', 'longitude': 'mean'}
        if data:
            agg_dict['data'] = ['count', 'sum', 'mean']
        else:
            agg_dict['count'] = ('h3_index', 'size')
        
        aggregated = df.groupby('h3_index').agg(agg_dict).reset_index()
        
        # Flatten column names if multi-level
        if isinstance(aggregated.columns, pd.MultiIndex):
            aggregated.columns = ['_'.join(col).strip() if col[1] else col[0] 
                                for col in aggregated.columns.values]
        
        return aggregated
    
    def calculate_hexagon_area(self, h3_index: str) -> float:
        """
        Calculate the area of an H3 hexagon in square meters.
        
        Args:
            h3_index (str): H3 hexagon index
            
        Returns:
            float: Area in square meters
        """
        return h3.cell_area(h3_index, unit='m^2')
    
    def get_hexagons_in_polygon(self, polygon_coords: List[Tuple[float, float]], 
                               resolution: Optional[int] = None) -> List[str]:
        """
        Get all H3 hexagons that intersect with a polygon.
        
        Args:
            polygon_coords (List[Tuple[float, float]]): Polygon boundary coordinates
            resolution (int, optional): H3 resolution level
            
        Returns:
            List[str]: List of H3 indices within polygon
        """
        res = resolution or self.default_resolution
        
        # Convert to GeoJSON-like format expected by h3
        geojson_coords = [list(reversed(coord)) for coord in polygon_coords]  # lng, lat format
        
        try:
            return list(h3.polygon_to_cells(geojson_coords, res))
        except Exception as e:
            logger.error(f"Error getting hexagons in polygon: {e}")
            return []
    
    def visualize_hexagons(self, h3_indices: List[str], 
                          center: Optional[Tuple[float, float]] = None,
                          zoom_start: int = 10,
                          colors: Optional[List[str]] = None,
                          popup_data: Optional[Dict[str, str]] = None) -> folium.Map:
        """
        Create an interactive map visualization of H3 hexagons.
        
        Args:
            h3_indices (List[str]): List of H3 hexagon indices to visualize
            center (Tuple[float, float], optional): Map center coordinates
            zoom_start (int): Initial zoom level
            colors (List[str], optional): Colors for each hexagon
            popup_data (Dict[str, str], optional): Popup data for each hexagon
            
        Returns:
            folium.Map: Interactive map with hexagon overlay
        """
        # Calculate center if not provided
        if center is None and h3_indices:
            coords = [self.h3_to_coords(idx) for idx in h3_indices]
            center = (
                np.mean([coord[0] for coord in coords]),
                np.mean([coord[1] for coord in coords])
            )
        elif center is None:
            center = (39.8283, -98.5795)  # Geographic center of US
        
        # Create map
        m = folium.Map(location=center, zoom_start=zoom_start)
        
        # Add hexagons
        for i, h3_index in enumerate(h3_indices):
            # Get hexagon boundary
            boundary = self.get_hexagon_boundary(h3_index)
            
            # Set color
            color = colors[i] if colors and i < len(colors) else '#ff0000'
            
            # Create popup text
            popup_text = f"H3 Index: {h3_index}"
            if popup_data and h3_index in popup_data:
                popup_text += f"<br>{popup_data[h3_index]}"
            
            # Add hexagon to map
            folium.Polygon(
                locations=boundary,
                color=color,
                weight=2,
                opacity=0.8,
                fillOpacity=0.3,
                popup=folium.Popup(popup_text, max_width=300)
            ).add_to(m)
        
        return m
    
    def export_to_geojson(self, h3_indices: List[str], 
                         properties: Optional[Dict[str, Dict]] = None) -> Dict:
        """
        Export H3 hexagons to GeoJSON format.
        
        Args:
            h3_indices (List[str]): List of H3 hexagon indices
            properties (Dict[str, Dict], optional): Properties for each hexagon
            
        Returns:
            Dict: GeoJSON representation of hexagons
        """
        features = []
        
        for h3_index in h3_indices:
            # Get hexagon boundary
            boundary = self.get_hexagon_boundary(h3_index)
            
            # Convert to GeoJSON coordinates (lng, lat format)
            coordinates = [[list(reversed(coord)) for coord in boundary]]
            
            # Create feature
            feature = {
                "type": "Feature",
                "geometry": {
                    "type": "Polygon",
                    "coordinates": coordinates
                },
                "properties": {
                    "h3_index": h3_index,
                    "resolution": h3.get_resolution(h3_index)
                }
            }
            
            # Add additional properties if provided
            if properties and h3_index in properties:
                feature["properties"].update(properties[h3_index])
            
            features.append(feature)
        
        return {
            "type": "FeatureCollection",
            "features": features
        }
    
    def distance_between_hexagons(self, h3_index1: str, h3_index2: str) -> float:
        """
        Calculate distance between two H3 hexagon centers.
        
        Args:
            h3_index1 (str): First H3 hexagon index
            h3_index2 (str): Second H3 hexagon index
            
        Returns:
            float: Distance in meters
        """
        coord1 = self.h3_to_coords(h3_index1)
        coord2 = self.h3_to_coords(h3_index2)
        return geodesic(coord1, coord2).meters
    
    def get_resolution_info(self, resolution: int) -> Dict[str, Union[int, float, str]]:
        """
        Get information about a specific H3 resolution level.
        
        Args:
            resolution (int): H3 resolution level
            
        Returns:
            Dict: Resolution information including edge length and area
        """
        self.validate_resolution(resolution)
        
        # Get edge length in meters
        edge_length = h3.get_hexagon_edge_length(resolution, 'm')
        
        # Get average hexagon area
        avg_area = h3.get_hexagon_area_avg(resolution, 'm^2')
        
        return {
            'resolution': resolution,
            'edge_length_m': edge_length,
            'edge_length_km': edge_length / 1000,
            'avg_area_m2': avg_area,
            'avg_area_km2': avg_area / 1_000_000,
            'use_case': self._get_resolution_use_case(resolution)
        }
    
    @staticmethod
    def _get_resolution_use_case(resolution: int) -> str:
        """Get typical use case for a resolution level."""
        use_cases = {
            0: "Global/continental analysis",
            1: "Country-level analysis", 
            2: "State/province analysis",
            3: "Metropolitan areas",
            4: "City-wide analysis",
            5: "Urban districts",
            6: "Neighborhoods",
            7: "Local areas",
            8: "City blocks",
            9: "Buildings/parcels",
            10: "Property-level",
            11: "Sub-property level",
            12: "Individual rooms",
            13: "Precise indoor positioning",
            14: "Ultra-precise positioning",
            15: "Maximum precision"
        }
        return use_cases.get(resolution, "Custom analysis")

# Example usage
if __name__ == "__main__":
    # Initialize framework
    h3_framework = H3GeolocationFramework(resolution=9)
    
    # St. Louis coordinates
    st_louis_coords = (38.6270, -90.1994)
    
    # Convert to H3
    h3_index = h3_framework.coords_to_h3(*st_louis_coords)
    print(f"St. Louis H3 Index: {h3_index}")
    
    # Get neighbors
    neighbors = h3_framework.get_neighbors(h3_index, ring_size=2)
    print(f"Found {len(neighbors)} hexagons within 2 rings")
    
    # Create visualization
    map_viz = h3_framework.visualize_hexagons([h3_index] + neighbors[:10])
    map_viz.save('st_louis_h3_demo.html')
    print("Visualization saved to st_louis_h3_demo.html")