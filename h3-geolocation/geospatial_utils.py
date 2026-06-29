"""
Geospatial Utility Functions for H3 Framework

Provides utility functions for coordinate transformations, distance calculations,
and other geospatial operations to support the H3 geolocation framework.
"""

import h3
import math
from typing import List, Tuple, Dict, Optional, Union
from geopy.distance import geodesic, great_circle
from geopy.geocoders import Nominatim
import numpy as np

class GeospatialUtils:
    """Utility class for geospatial operations."""
    
    def __init__(self):
        self.geocoder = Nominatim(user_agent="h3_geolocation_framework")
    
    @staticmethod
    def haversine_distance(coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
        """
        Calculate the great circle distance between two points using the Haversine formula.
        
        Args:
            coord1 (Tuple[float, float]): First coordinate (lat, lng)
            coord2 (Tuple[float, float]): Second coordinate (lat, lng)
            
        Returns:
            float: Distance in meters
        """
        lat1, lon1 = coord1
        lat2, lon2 = coord2
        
        # Convert to radians
        lat1, lon1, lat2, lon2 = map(math.radians, [lat1, lon1, lat2, lon2])
        
        # Haversine formula
        dlat = lat2 - lat1
        dlon = lon2 - lon1
        a = math.sin(dlat/2)**2 + math.cos(lat1) * math.cos(lat2) * math.sin(dlon/2)**2
        c = 2 * math.asin(math.sqrt(a))
        
        # Earth's radius in meters
        earth_radius = 6371000
        distance = earth_radius * c
        
        return distance
    
    @staticmethod
    def bearing_between_points(coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
        """
        Calculate the bearing (direction) from coord1 to coord2.
        
        Args:
            coord1 (Tuple[float, float]): Starting coordinate (lat, lng)
            coord2 (Tuple[float, float]): Ending coordinate (lat, lng)
            
        Returns:
            float: Bearing in degrees (0-360)
        """
        lat1, lon1 = math.radians(coord1[0]), math.radians(coord1[1])
        lat2, lon2 = math.radians(coord2[0]), math.radians(coord2[1])
        
        dlon = lon2 - lon1
        
        y = math.sin(dlon) * math.cos(lat2)
        x = math.cos(lat1) * math.sin(lat2) - math.sin(lat1) * math.cos(lat2) * math.cos(dlon)
        
        bearing = math.atan2(y, x)
        bearing = math.degrees(bearing)
        bearing = (bearing + 360) % 360
        
        return bearing
    
    @staticmethod
    def destination_point(coord: Tuple[float, float], bearing: float, distance: float) -> Tuple[float, float]:
        """
        Calculate destination point given start point, bearing, and distance.
        
        Args:
            coord (Tuple[float, float]): Starting coordinate (lat, lng)
            bearing (float): Bearing in degrees
            distance (float): Distance in meters
            
        Returns:
            Tuple[float, float]: Destination coordinate (lat, lng)
        """
        lat1, lon1 = math.radians(coord[0]), math.radians(coord[1])
        bearing_rad = math.radians(bearing)
        
        # Earth's radius in meters
        earth_radius = 6371000
        
        lat2 = math.asin(math.sin(lat1) * math.cos(distance / earth_radius) +
                        math.cos(lat1) * math.sin(distance / earth_radius) * math.cos(bearing_rad))
        
        lon2 = lon1 + math.atan2(math.sin(bearing_rad) * math.sin(distance / earth_radius) * math.cos(lat1),
                                math.cos(distance / earth_radius) - math.sin(lat1) * math.sin(lat2))
        
        return math.degrees(lat2), math.degrees(lon2)
    
    @staticmethod
    def create_bounding_box(center: Tuple[float, float], radius_meters: float) -> Dict[str, float]:
        """
        Create a bounding box around a center point.
        
        Args:
            center (Tuple[float, float]): Center coordinate (lat, lng)
            radius_meters (float): Radius in meters
            
        Returns:
            Dict[str, float]: Bounding box with min/max lat/lng
        """
        lat, lng = center
        
        # Calculate approximate degree differences
        # 1 degree latitude ≈ 111,000 meters
        lat_diff = radius_meters / 111000
        
        # 1 degree longitude varies by latitude
        lng_diff = radius_meters / (111000 * math.cos(math.radians(lat)))
        
        return {
            'min_lat': lat - lat_diff,
            'max_lat': lat + lat_diff,
            'min_lng': lng - lng_diff,
            'max_lng': lng + lng_diff
        }
    
    @staticmethod
    def point_in_polygon(point: Tuple[float, float], polygon: List[Tuple[float, float]]) -> bool:
        """
        Check if a point is inside a polygon using ray casting algorithm.
        
        Args:
            point (Tuple[float, float]): Point coordinate (lat, lng)
            polygon (List[Tuple[float, float]]): Polygon vertices
            
        Returns:
            bool: True if point is inside polygon
        """
        x, y = point
        n = len(polygon)
        inside = False
        
        p1x, p1y = polygon[0]
        for i in range(1, n + 1):
            p2x, p2y = polygon[i % n]
            if y > min(p1y, p2y):
                if y <= max(p1y, p2y):
                    if x <= max(p1x, p2x):
                        if p1y != p2y:
                            xinters = (y - p1y) * (p2x - p1x) / (p2y - p1y) + p1x
                        if p1x == p2x or x <= xinters:
                            inside = not inside
            p1x, p1y = p2x, p2y
        
        return inside
    
    def geocode_address(self, address: str) -> Optional[Tuple[float, float]]:
        """
        Convert address to coordinates using geocoding.
        
        Args:
            address (str): Address string
            
        Returns:
            Optional[Tuple[float, float]]: Coordinates (lat, lng) or None if not found
        """
        try:
            location = self.geocoder.geocode(address)
            if location:
                return (location.latitude, location.longitude)
        except Exception as e:
            print(f"Geocoding error: {e}")
        return None
    
    def reverse_geocode(self, coord: Tuple[float, float]) -> Optional[str]:
        """
        Convert coordinates to address using reverse geocoding.
        
        Args:
            coord (Tuple[float, float]): Coordinate (lat, lng)
            
        Returns:
            Optional[str]: Address string or None if not found
        """
        try:
            location = self.geocoder.reverse(coord, exactly_one=True)
            if location:
                return location.address
        except Exception as e:
            print(f"Reverse geocoding error: {e}")
        return None
    
    @staticmethod
    def calculate_polygon_area(polygon: List[Tuple[float, float]]) -> float:
        """
        Calculate the area of a polygon using the Shoelace formula.
        
        Args:
            polygon (List[Tuple[float, float]]): Polygon vertices in (lat, lng)
            
        Returns:
            float: Area in square meters (approximate)
        """
        if len(polygon) < 3:
            return 0.0
        
        # Convert to Cartesian coordinates (approximate)
        # Use the first point as reference
        ref_lat, ref_lng = polygon[0]
        
        x_coords = []
        y_coords = []
        
        for lat, lng in polygon:
            # Convert to meters from reference point
            x = (lng - ref_lng) * 111000 * math.cos(math.radians(ref_lat))
            y = (lat - ref_lat) * 111000
            x_coords.append(x)
            y_coords.append(y)
        
        # Shoelace formula
        area = 0.0
        n = len(x_coords)
        
        for i in range(n):
            j = (i + 1) % n
            area += x_coords[i] * y_coords[j]
            area -= x_coords[j] * y_coords[i]
        
        return abs(area) / 2.0
    
    @staticmethod
    def calculate_polygon_centroid(polygon: List[Tuple[float, float]]) -> Tuple[float, float]:
        """
        Calculate the centroid of a polygon.
        
        Args:
            polygon (List[Tuple[float, float]]): Polygon vertices
            
        Returns:
            Tuple[float, float]: Centroid coordinate (lat, lng)
        """
        if not polygon:
            return (0.0, 0.0)
        
        lat_sum = sum(coord[0] for coord in polygon)
        lng_sum = sum(coord[1] for coord in polygon)
        
        return (lat_sum / len(polygon), lng_sum / len(polygon))
    
    @staticmethod
    def simplify_polygon(polygon: List[Tuple[float, float]], tolerance: float = 0.001) -> List[Tuple[float, float]]:
        """
        Simplify polygon by removing points that are too close together.
        
        Args:
            polygon (List[Tuple[float, float]]): Original polygon vertices
            tolerance (float): Minimum distance between points in degrees
            
        Returns:
            List[Tuple[float, float]]: Simplified polygon vertices
        """
        if len(polygon) <= 2:
            return polygon
        
        simplified = [polygon[0]]
        
        for i in range(1, len(polygon)):
            current = polygon[i]
            last_added = simplified[-1]
            
            # Calculate distance
            distance = ((current[0] - last_added[0]) ** 2 + (current[1] - last_added[1]) ** 2) ** 0.5
            
            if distance >= tolerance:
                simplified.append(current)
        
        # Ensure polygon is closed if it was originally closed
        if len(polygon) > 2 and polygon[0] == polygon[-1] and simplified[0] != simplified[-1]:
            simplified.append(simplified[0])
        
        return simplified
    
    @staticmethod
    def create_grid_points(bbox: Dict[str, float], spacing_degrees: float = 0.01) -> List[Tuple[float, float]]:
        """
        Create a grid of points within a bounding box.
        
        Args:
            bbox (Dict[str, float]): Bounding box with min/max lat/lng
            spacing_degrees (float): Spacing between grid points in degrees
            
        Returns:
            List[Tuple[float, float]]: List of grid point coordinates
        """
        points = []
        
        lat = bbox['min_lat']
        while lat <= bbox['max_lat']:
            lng = bbox['min_lng']
            while lng <= bbox['max_lng']:
                points.append((lat, lng))
                lng += spacing_degrees
            lat += spacing_degrees
        
        return points

# Distance calculation functions using different methods
def calculate_distance_geodesic(coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
    """Calculate distance using geodesic (most accurate for long distances)."""
    return geodesic(coord1, coord2).meters

def calculate_distance_great_circle(coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
    """Calculate distance using great circle (faster, less accurate)."""
    return great_circle(coord1, coord2).meters

def calculate_distance_euclidean(coord1: Tuple[float, float], coord2: Tuple[float, float]) -> float:
    """Calculate Euclidean distance (fastest, least accurate for geographic data)."""
    lat_diff = coord2[0] - coord1[0]
    lng_diff = coord2[1] - coord1[1]
    
    # Convert to approximate meters
    lat_meters = lat_diff * 111000
    lng_meters = lng_diff * 111000 * math.cos(math.radians((coord1[0] + coord2[0]) / 2))
    
    return math.sqrt(lat_meters**2 + lng_meters**2)

# Coordinate system conversion functions
def degrees_to_radians(degrees: float) -> float:
    """Convert degrees to radians."""
    return degrees * math.pi / 180

def radians_to_degrees(radians: float) -> float:
    """Convert radians to degrees."""
    return radians * 180 / math.pi

def dms_to_decimal(degrees: int, minutes: int, seconds: float) -> float:
    """Convert degrees, minutes, seconds to decimal degrees."""
    return degrees + minutes/60 + seconds/3600

def decimal_to_dms(decimal_degrees: float) -> Tuple[int, int, float]:
    """Convert decimal degrees to degrees, minutes, seconds."""
    degrees = int(decimal_degrees)
    minutes_float = (decimal_degrees - degrees) * 60
    minutes = int(minutes_float)
    seconds = (minutes_float - minutes) * 60
    
    return degrees, minutes, seconds