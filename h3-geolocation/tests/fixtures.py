"""
Test fixtures and sample data for H3 framework testing.

This module provides standardized test data including coordinates,
H3 indices, and expected results for consistent testing across
all test modules.
"""

# St. Louis area test coordinates
ST_LOUIS_LANDMARKS = {
    'gateway_arch': {
        'name': 'Gateway Arch',
        'lat': 38.6247,
        'lng': -90.1848,
        'h3_res9': '89283082c2fffff',
        'area_m2': 105.17  # Approximate area at resolution 9
    },
    'busch_stadium': {
        'name': 'Busch Stadium', 
        'lat': 38.6226,
        'lng': -90.1928,
        'h3_res9': '89283082c0fffff',
        'area_m2': 105.17
    },
    'forest_park': {
        'name': 'Forest Park',
        'lat': 38.6355,
        'lng': -90.2732,
        'h3_res9': '892830829afffff',
        'area_m2': 105.17
    },
    'saint_louis_university': {
        'name': 'Saint Louis University',
        'lat': 38.6370,
        'lng': -90.2307,
        'h3_res9': '89283082d5fffff',
        'area_m2': 105.17
    },
    'washington_university': {
        'name': 'Washington University',
        'lat': 38.6488,
        'lng': -90.3108,
        'h3_res9': '8928308285fffff',
        'area_m2': 105.17
    }
}

# Expected distances between landmarks (in meters)
EXPECTED_DISTANCES = {
    ('gateway_arch', 'busch_stadium'): 831.5,
    ('gateway_arch', 'forest_park'): 8420.3,
    ('busch_stadium', 'forest_park'): 8950.7,
    ('saint_louis_university', 'washington_university'): 7892.1
}

# Test data for aggregation
SAMPLE_POINTS = [
    {'lat': 38.6247, 'lng': -90.1848, 'value': 100, 'category': 'landmark'},
    {'lat': 38.6226, 'lng': -90.1928, 'value': 95, 'category': 'landmark'},
    {'lat': 38.6355, 'lng': -90.2732, 'value': 85, 'category': 'park'},
    {'lat': 38.6200, 'lng': -90.1800, 'value': 75, 'category': 'business'},
    {'lat': 38.6300, 'lng': -90.1900, 'value': 65, 'category': 'residential'},
    {'lat': 38.6400, 'lng': -90.2000, 'value': 55, 'category': 'residential'},
    {'lat': 38.6250, 'lng': -90.2100, 'value': 45, 'category': 'business'},
    {'lat': 38.6350, 'lng': -90.2200, 'value': 35, 'category': 'business'}
]

# GeoJSON test data
SAMPLE_GEOJSON = {
    "type": "FeatureCollection",
    "features": [
        {
            "type": "Feature",
            "properties": {
                "name": "Gateway Arch",
                "type": "landmark",
                "visitors_daily": 6000
            },
            "geometry": {
                "type": "Point",
                "coordinates": [-90.1848, 38.6247]
            }
        },
        {
            "type": "Feature", 
            "properties": {
                "name": "Forest Park",
                "type": "park",
                "area_acres": 1326
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[
                    [-90.2932, 38.6455],
                    [-90.2632, 38.6455], 
                    [-90.2632, 38.6255],
                    [-90.2932, 38.6255],
                    [-90.2932, 38.6455]
                ]]
            }
        }
    ]
}

# CSV test data
SAMPLE_CSV_DATA = [
    ['name', 'lat', 'lng', 'category', 'value'],
    ['Gateway Arch', '38.6247', '-90.1848', 'landmark', '100'],
    ['Busch Stadium', '38.6226', '-90.1928', 'sports', '95'],
    ['Forest Park', '38.6355', '-90.2732', 'park', '85'],
    ['City Museum', '38.6338', '-90.2006', 'museum', '80']
]

# Test hexagon neighbors for validation
HEXAGON_NEIGHBORS = {
    '89283082c2fffff': [  # Gateway Arch neighbors
        '89283082c3fffff',
        '89283082c1fffff', 
        '89283082c0fffff',
        '89283082dbfffff',
        '89283082dafffff',
        '89283082d9fffff'
    ]
}

# Resolution area expectations (square meters)
RESOLUTION_AREAS = {
    0: 4250546.848,    # ~4.25 million m²
    1: 607220.957,     # ~607k m²
    2: 86745.854,      # ~86k m²
    3: 12392.264,      # ~12k m²
    4: 1770.324,       # ~1.7k m²
    5: 252.903,        # ~253 m²
    6: 36.129,         # ~36 m²
    7: 5.161,          # ~5.2 m²
    8: 0.737,          # ~0.74 m²
    9: 0.105,          # ~0.11 m²
    10: 0.015,         # ~0.015 m²
    11: 0.002,         # ~0.002 m²
    12: 0.0003,        # ~0.0003 m²
    13: 0.00004,       # ~0.00004 m²
    14: 0.000006,      # ~0.000006 m²
    15: 0.0000009      # ~0.0000009 m²
}

# Environmental test data
ENVIRONMENTAL_DATA = [
    {'lat': 38.6270, 'lng': -90.1994, 'aqi': 45, 'pm25': 18.2, 'ozone': 0.065},
    {'lat': 38.6300, 'lng': -90.2000, 'aqi': 52, 'pm25': 21.5, 'ozone': 0.071},
    {'lat': 38.6250, 'lng': -90.1950, 'aqi': 38, 'pm25': 15.1, 'ozone': 0.058},
    {'lat': 38.6320, 'lng': -90.2050, 'aqi': 61, 'pm25': 25.8, 'ozone': 0.078}
]

# Demographic test data
DEMOGRAPHIC_DATA = [
    {'lat': 38.6270, 'lng': -90.1994, 'age_avg': 34.5, 'income_avg': 65000, 'education': 'college'},
    {'lat': 38.6300, 'lng': -90.2000, 'age_avg': 42.1, 'income_avg': 78000, 'education': 'graduate'},
    {'lat': 38.6250, 'lng': -90.1950, 'age_avg': 28.9, 'income_avg': 52000, 'education': 'high_school'},
    {'lat': 38.6320, 'lng': -90.2050, 'age_avg': 37.8, 'income_avg': 69000, 'education': 'college'}
]

# Edge case coordinates for testing
EDGE_CASES = {
    'north_pole': (89.0, 0.0),
    'south_pole': (-89.0, 0.0),
    'date_line_east': (0.0, 179.0),
    'date_line_west': (0.0, -179.0),
    'greenwich': (51.4769, 0.0),
    'equator_pacific': (0.0, -150.0)
}

# Performance test data generator
def generate_large_dataset(size=1000, center_lat=38.6270, center_lng=-90.1994, radius=0.1):
    """Generate large dataset for performance testing."""
    import random
    
    dataset = []
    for i in range(size):
        lat = center_lat + random.uniform(-radius, radius)
        lng = center_lng + random.uniform(-radius, radius)
        
        dataset.append({
            'id': i,
            'lat': lat,
            'lng': lng,
            'value': random.randint(1, 100),
            'category': random.choice(['A', 'B', 'C', 'D']),
            'timestamp': f'2024-01-{(i % 31) + 1:02d}'
        })
    
    return dataset

# Validation helpers
def validate_h3_index(h3_index):
    """Validate H3 index format."""
    if not isinstance(h3_index, str):
        return False
    if len(h3_index) != 15:
        return False
    if not all(c in '0123456789abcdef' for c in h3_index):
        return False
    return True

def validate_coordinates(lat, lng):
    """Validate coordinate ranges."""
    if not isinstance(lat, (int, float)) or not isinstance(lng, (int, float)):
        return False
    if not (-90 <= lat <= 90):
        return False
    if not (-180 <= lng <= 180):
        return False
    return True

def approximate_equal(value1, value2, tolerance=1e-6):
    """Check if two floating point values are approximately equal."""
    return abs(value1 - value2) <= tolerance

# Test data file paths (for integration tests)
TEST_DATA_DIR = 'test_data'
TEST_FILES = {
    'coordinates_csv': f'{TEST_DATA_DIR}/test_coordinates.csv',
    'sample_geojson': f'{TEST_DATA_DIR}/sample_data.geojson',
    'environmental_json': f'{TEST_DATA_DIR}/environmental_data.json',
    'demographic_json': f'{TEST_DATA_DIR}/demographic_data.json'
}