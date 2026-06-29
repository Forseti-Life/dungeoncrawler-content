#!/usr/bin/env python3
"""
Installation and Setup Script for H3 Geolocation Framework

This script handles the installation of all required dependencies
and sets up the H3 framework environment.
"""

import subprocess
import sys
import os
from pathlib import Path

def run_command(command, description=""):
    """Execute a shell command and handle errors."""
    if description:
        print(f"➤ {description}")
    
    try:
        result = subprocess.run(command, shell=True, check=True, 
                              capture_output=True, text=True)
        if result.stdout:
            print(result.stdout.strip())
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Error: {e}")
        if e.stderr:
            print(f"Error details: {e.stderr.strip()}")
        return False

def check_python_version():
    """Check if Python version is compatible."""
    version = sys.version_info
    if version.major < 3 or (version.major == 3 and version.minor < 8):
        print("❌ Python 3.8 or higher is required")
        print(f"Current version: {version.major}.{version.minor}.{version.micro}")
        return False
    
    print(f"✅ Python version: {version.major}.{version.minor}.{version.micro}")
    return True

def create_virtual_environment():
    """Create Python virtual environment."""
    venv_path = Path("h3-env")
    
    if venv_path.exists():
        print("✅ Virtual environment already exists")
        return True
    
    print("Creating Python virtual environment...")
    return run_command("python3 -m venv h3-env", "Creating virtual environment")

def install_system_packages():
    """Install required system packages."""
    print("Installing system packages...")
    
    packages = [
        "python3-dev",
        "python3-pip", 
        "python3-venv",
        "python3-full",
        "build-essential",
        "libgeos-dev",
        "libproj-dev",
        "libgdal-dev"
    ]
    
    # Update package list
    if not run_command("apt update", "Updating package list"):
        return False
    
    # Install packages
    package_list = " ".join(packages)
    return run_command(f"apt install -y {package_list}", "Installing system packages")

def install_python_packages():
    """Install required Python packages."""
    print("Installing Python packages...")
    
    # Activate virtual environment and install packages
    packages = [
        "h3==4.3.1",
        "pandas>=2.0.0",
        "numpy>=1.24.0",
        "matplotlib>=3.7.0",
        "folium>=0.18.0",
        "geopy>=2.4.0",
        "plotly>=5.17.0",
        "seaborn>=0.13.0",
        "jupyter>=1.0.0",
        "ipywidgets>=8.0.0"
    ]
    
    # Install each package
    for package in packages:
        if not run_command(f"./h3-env/bin/pip install {package}", f"Installing {package}"):
            print(f"Warning: Failed to install {package}")
    
    return True

def verify_installation():
    """Verify that all packages are installed correctly."""
    print("Verifying installation...")
    
    test_script = '''
import sys
sys.path.append(".")

try:
    import h3
    print(f"✅ H3 version: {h3.__version__}")
    
    import pandas as pd
    print(f"✅ Pandas version: {pd.__version__}")
    
    import numpy as np
    print(f"✅ NumPy version: {np.__version__}")
    
    import matplotlib
    print(f"✅ Matplotlib version: {matplotlib.__version__}")
    
    import folium
    print(f"✅ Folium version: {folium.__version__}")
    
    import geopy
    print(f"✅ GeoPy version: {geopy.__version__}")
    
    import plotly
    print(f"✅ Plotly version: {plotly.__version__}")
    
    # Test basic H3 functionality
    test_lat, test_lng = 38.6270, -90.1994
    h3_index = h3.latlng_to_cell(test_lat, test_lng, 9)
    back_lat, back_lng = h3.cell_to_latlng(h3_index)
    
    print(f"✅ H3 basic test: {test_lat:.4f},{test_lng:.4f} -> {h3_index} -> {back_lat:.4f},{back_lng:.4f}")
    print("✅ All packages installed and working correctly!")
    
except ImportError as e:
    print(f"❌ Import error: {e}")
    sys.exit(1)
except Exception as e:
    print(f"❌ Test error: {e}")
    sys.exit(1)
'''
    
    # Write and run test script
    with open("test_installation.py", "w") as f:
        f.write(test_script)
    
    success = run_command("./h3-env/bin/python test_installation.py", "Running installation test")
    
    # Clean up test file
    try:
        os.remove("test_installation.py")
    except:
        pass
    
    return success

def create_launcher_script():
    """Create launcher script for easy access."""
    launcher_content = '''#!/bin/bash
# H3 Geolocation Framework Launcher

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Activate virtual environment
source h3-env/bin/activate

# Check if argument provided
if [ $# -eq 0 ]; then
    echo "H3 Geolocation Framework"
    echo "Available commands:"
    echo "  quick_start    - Run quick start demo"
    echo "  examples       - Run example applications"
    echo "  jupyter        - Start Jupyter notebook"
    echo "  shell          - Open Python shell with H3"
    echo ""
    echo "Usage: ./run.sh <command>"
    echo "Or activate environment: source h3-env/bin/activate"
else
    case $1 in
        "quick_start")
            python quick_start.py
            ;;
        "examples")
            python examples.py  
            ;;
        "jupyter")
            jupyter notebook --ip=0.0.0.0 --port=8888 --no-browser --allow-root
            ;;
        "shell")
            python -c "
import h3
from h3_framework import H3GeolocationFramework
print('H3 Geolocation Framework - Python Shell')
print('Available objects:')
print('  h3 - H3 library')
print('  H3GeolocationFramework - Main framework class')
print('Example: framework = H3GeolocationFramework()')
"
            python
            ;;
        *)
            echo "Unknown command: $1"
            echo "Use ./run.sh for available commands"
            ;;
    esac
fi
'''
    
    with open("run.sh", "w") as f:
        f.write(launcher_content)
    
    # Make executable
    return run_command("chmod +x run.sh", "Creating launcher script")

def setup_jupyter_config():
    """Set up Jupyter notebook configuration."""
    print("Setting up Jupyter configuration...")
    
    jupyter_config = '''
c.NotebookApp.ip = '0.0.0.0'
c.NotebookApp.port = 8888
c.NotebookApp.open_browser = False
c.NotebookApp.allow_root = True
c.NotebookApp.token = ''
c.NotebookApp.password = ''
'''
    
    # Create jupyter config directory
    config_dir = Path.home() / ".jupyter"
    config_dir.mkdir(exist_ok=True)
    
    config_file = config_dir / "jupyter_notebook_config.py"
    with open(config_file, "w") as f:
        f.write(jupyter_config)
    
    print("✅ Jupyter configuration created")
    return True

def create_sample_notebook():
    """Create a sample Jupyter notebook."""
    notebook_content = {
        "cells": [
            {
                "cell_type": "markdown",
                "metadata": {},
                "source": [
                    "# H3 Geolocation Framework - Getting Started\n",
                    "\n",
                    "This notebook demonstrates the basic usage of the H3 geolocation framework."
                ]
            },
            {
                "cell_type": "code",
                "execution_count": None,
                "metadata": {},
                "outputs": [],
                "source": [
                    "# Import required libraries\n",
                    "import sys\n",
                    "sys.path.append('.')\n",
                    "\n",
                    "from h3_framework import H3GeolocationFramework\n",
                    "import h3\n",
                    "\n",
                    "# Initialize framework\n",
                    "framework = H3GeolocationFramework()\n",
                    "print(\"H3 Framework initialized successfully!\")"
                ]
            },
            {
                "cell_type": "code",
                "execution_count": None,
                "metadata": {},
                "source": [
                    "# Convert St. Louis coordinates to H3\n",
                    "st_louis_lat, st_louis_lng = 38.6270, -90.1994\n",
                    "h3_index = framework.coords_to_h3(st_louis_lat, st_louis_lng, 9)\n",
                    "\n",
                    "print(f\"St. Louis coordinates: {st_louis_lat}, {st_louis_lng}\")\n",
                    "print(f\"H3 index: {h3_index}\")\n",
                    "print(f\"Resolution: {h3.cell_to_res(h3_index)}\")"
                ]
            },
            {
                "cell_type": "code",
                "execution_count": None,
                "metadata": {},
                "source": [
                    "# Get neighbors\n", 
                    "neighbors = framework.get_neighbors(h3_index, k=1)\n",
                    "print(f\"Found {len(neighbors)} neighboring hexagons\")\n",
                    "\n",
                    "# Visualize hexagons\n",
                    "hex_map = framework.visualize_hexagons([h3_index] + neighbors, \n",
                    "                                      center=(st_louis_lat, st_louis_lng))\n",
                    "hex_map"
                ]
            }
        ],
        "metadata": {
            "kernelspec": {
                "display_name": "Python 3",
                "language": "python", 
                "name": "python3"
            },
            "language_info": {
                "name": "python",
                "version": "3.8.0"
            }
        },
        "nbformat": 4,
        "nbformat_minor": 4
    }
    
    import json
    with open("H3_Getting_Started.ipynb", "w") as f:
        json.dump(notebook_content, f, indent=2)
    
    print("✅ Sample notebook created: H3_Getting_Started.ipynb")
    return True

def main():
    """Main installation function."""
    print("H3 Geolocation Framework - Installation Script")
    print("=" * 50)
    
    # Check if running as root for system packages
    if os.geteuid() != 0:
        print("⚠️  Note: System package installation requires root privileges")
        print("   Run with 'sudo python3 install.py' for full installation")
        print("   Or run './h3-env/bin/pip install <packages>' manually")
        install_system = False
    else:
        install_system = True
    
    success = True
    
    # Step 1: Check Python version
    if not check_python_version():
        return False
    
    # Step 2: Install system packages (if root)
    if install_system:
        if not install_system_packages():
            print("⚠️  System package installation failed, continuing anyway...")
    
    # Step 3: Create virtual environment
    if not create_virtual_environment():
        success = False
    
    # Step 4: Install Python packages
    if success and not install_python_packages():
        success = False
    
    # Step 5: Verify installation
    if success and not verify_installation():
        success = False
    
    # Step 6: Create launcher script
    if success:
        create_launcher_script()
        setup_jupyter_config()
        create_sample_notebook()
    
    print("\n" + "=" * 50)
    if success:
        print("✅ Installation completed successfully!")
        print("\nNext steps:")
        print("1. Activate environment: source h3-env/bin/activate")
        print("2. Run quick demo: python quick_start.py")
        print("3. Or use launcher: ./run.sh quick_start")
        print("4. Start examples: ./run.sh examples")
        print("5. Jupyter notebook: ./run.sh jupyter")
        
        print("\nGenerated files:")
        print("- h3-env/          (Python virtual environment)")
        print("- run.sh           (Launcher script)")
        print("- H3_Getting_Started.ipynb (Sample notebook)")
        
    else:
        print("❌ Installation failed!")
        print("Check the error messages above and try again.")
        return False
    
    return True

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n❌ Installation cancelled by user")
    except Exception as e:
        print(f"❌ Installation error: {e}")