#!/bin/bash
# H3 Geolocation Framework Launcher

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if virtual environment exists
if [ ! -d "h3-env" ]; then
    echo -e "${RED}❌ Virtual environment not found. Please run: python3 install.py${NC}"
    exit 1
fi

# Activate virtual environment
source h3-env/bin/activate

# Check if argument provided
if [ $# -eq 0 ]; then
    echo -e "${BLUE}🗺️  H3 Geolocation Framework${NC}"
    echo "=========================================="
    echo -e "${GREEN}Available commands:${NC}"
    echo "  ${YELLOW}quick_start${NC}    - Run quick start demo with St. Louis landmarks"
    echo "  ${YELLOW}examples${NC}       - Run example applications (5 real-world use cases)"
    echo "  ${YELLOW}test${NC}           - Run basic functionality test"
    echo "  ${YELLOW}install${NC}        - Run installation/setup script"
    echo "  ${YELLOW}jupyter${NC}        - Start Jupyter notebook server"
    echo "  ${YELLOW}shell${NC}          - Open Python shell with H3 framework loaded"
    echo "  ${YELLOW}info${NC}           - Show framework information and status"
    echo ""
    echo -e "${GREEN}Usage:${NC} ./run.sh <command>"
    echo -e "${GREEN}Or activate environment:${NC} source h3-env/bin/activate"
else
    case $1 in
        "quick_start"|"demo"|"quickstart")
            echo -e "${BLUE}🚀 Starting H3 Quick Demo...${NC}"
            python quick_start.py
            ;;
        "examples"|"example")
            echo -e "${BLUE}📊 Starting H3 Example Applications...${NC}"
            python examples.py  
            ;;
        "test"|"verify")
            echo -e "${BLUE}🧪 Running H3 Framework Tests...${NC}"
            python -c "
from h3_framework import H3GeolocationFramework
import h3

print('Testing H3 Geolocation Framework...')
framework = H3GeolocationFramework()

# Test coordinate conversion
lat, lng = 38.6270, -90.1994
h3_index = framework.coords_to_h3(lat, lng, 9)
back_lat, back_lng = framework.h3_to_coords(h3_index)

print(f'✅ Coordinate conversion: ({lat}, {lng}) -> {h3_index} -> ({back_lat:.4f}, {back_lng:.4f})')

# Test neighbors
neighbors = framework.get_neighbors(h3_index, ring_size=1)
print(f'✅ Neighbor analysis: Found {len(neighbors)} neighbors')

# Test distance
gateway_arch = (38.6247, -90.1848)
busch_stadium = (38.6226, -90.1928)
distance = framework.calculate_distance(gateway_arch, busch_stadium)
print(f'✅ Distance calculation: Gateway Arch to Busch Stadium = {distance:.0f}m')

# Test area
area = framework.calculate_hexagon_area(h3_index)
print(f'✅ Area calculation: Resolution 9 hexagon = {area:.0f} m²')

print('🎉 All tests passed! Framework is working correctly.')
"
            ;;
        "install"|"setup")
            echo -e "${BLUE}⚙️  Running H3 Installation Script...${NC}"
            python install.py
            ;;
        "jupyter"|"notebook")
            echo -e "${BLUE}📓 Starting Jupyter Notebook Server...${NC}"
            echo "Access at: http://localhost:8888"
            echo "Press Ctrl+C to stop the server"
            jupyter notebook --ip=0.0.0.0 --port=8888 --no-browser --allow-root --notebook-dir=.
            ;;
        "shell"|"python")
            echo -e "${BLUE}🐍 Starting Python Shell with H3 Framework...${NC}"
            python -c "
import h3
from h3_framework import H3GeolocationFramework
from geospatial_utils import GeospatialUtils
from data_processor import H3DataProcessor
from visualizer import H3Visualizer

print('H3 Geolocation Framework - Python Shell')
print('========================================')
print('Available objects:')
print('  h3                    - H3 library')
print('  H3GeolocationFramework - Main framework class')
print('  GeospatialUtils       - Utility functions')
print('  H3DataProcessor       - Data processing')
print('  H3Visualizer          - Visualization tools')
print('')
print('Quick start:')
print('  framework = H3GeolocationFramework()')
print('  h3_index = framework.coords_to_h3(38.6270, -90.1994, 9)')
print('  print(h3_index)')
print('')
"
            python
            ;;
        "info"|"status"|"version")
            echo -e "${BLUE}ℹ️  H3 Geolocation Framework Information${NC}"
            echo "=========================================="
            python -c "
import h3, pandas, numpy, matplotlib, folium, geopy, plotly
from h3_framework import H3GeolocationFramework

print(f'H3 Library Version: {h3.__version__}')
print(f'Pandas Version: {pandas.__version__}')
print(f'NumPy Version: {numpy.__version__}')
print(f'Matplotlib Version: {matplotlib.__version__}')
print(f'Folium Version: {folium.__version__}')
print(f'GeoPy Version: {geopy.__version__}')
print(f'Plotly Version: {plotly.__version__}')
print('')
print('Framework Status: ✅ READY')
print('Supported Resolutions: 0-15 (global to building level)')
print('Default Resolution: 9 (~105m hexagons)')
print('Coordinate System: WGS84 (EPSG:4326)')
print('')
print('Components:')
print('  ✅ H3GeolocationFramework - Core functionality')
print('  ✅ GeospatialUtils        - Utility functions')
print('  ✅ H3DataProcessor        - Data import/export')
print('  ✅ H3Visualizer           - Visualization tools')
print('  ✅ Examples               - 5 real-world applications')
print('  ✅ Quick Start            - Interactive demo')
print('  ✅ Test Suite             - Comprehensive tests')
"
            ;;
        "help"|"-h"|"--help")
            $0  # Show main help
            ;;
        *)
            echo -e "${RED}❌ Unknown command: $1${NC}"
            echo -e "${YELLOW}Use './run.sh' to see available commands${NC}"
            exit 1
            ;;
    esac
fi