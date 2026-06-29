# H3 Geolocation Framework - Directory Structure

## Current Active Structure

```
h3-geolocation/
├── README.md                           # Main documentation
├── install.py                          # Framework installation script
├── quick_start.py                      # Quick start examples
├── visualizer.py                       # Data visualization tools
├── h3_framework.py                     # Core H3 framework functions
├── geospatial_utils.py                 # Geospatial utility functions
├── examples.py                         # Usage examples
├── run.sh                              # Quick runner script
├── composer.json                       # PHP dependencies
├── 
├── config/                             # Configuration files
│   ├── mysql_config.json              # Database configuration
│   └── README.md                       # Config documentation
├── 
├── data/                               # Data storage
│   ├── raw/                           # Raw CSV files (20 files, 673MB+)
│   │   ├── incidents_part1_part2 (1).csv
│   │   ├── incidents_part1_part2 (2).csv
│   │   └── ... (18 more files)
│   └── README.md                      # Data documentation
├── 
├── database/                          # **ACTIVE PIPELINE SCRIPTS**
│   ├── CURRENT_FILES.md               # Documentation of active files
│   ├── run_amisafe_pipeline_stlouisintegration.sh  # MAIN PIPELINE
│   ├── setup_amisafe_stlouisintegration.sh         # DATABASE SETUP
│   ├── amisafe_processor.py           # Data processing (Bronze→Silver)
│   ├── amisafe_aggregator.py          # Aggregation (Silver→Gold)
│   ├── monitor_processing.py          # Processing monitor
│   ├── generate_h3_metro_area.py      # Metro area H3 generation
│   ├── populate_h3_incident_ids.py    # Backfill utility
│   └── archive/                       # Archived experimental files
│       ├── experimental_processors/   # Old processor versions
│       ├── old_scripts/               # Previous pipeline scripts
│       └── logs/                      # Historical logs
├── 
├── h3-env/                            # Python virtual environment
│   ├── bin/activate                   # Environment activation
│   ├── lib/python3.*/                # Python packages
│   └── ...                           # Virtual env files
├── 
├── scripts/                           # Utility scripts
│   ├── load_incidents_to_mysql.py     # MySQL loader utility
│   └── README.md                      # Scripts documentation
├── 
├── tests/                             # Test suites
│   ├── test_h3_framework.py          # Framework tests
│   ├── test_transform_processor.py    # Processor tests
│   ├── fixtures.py                   # Test fixtures
│   └── data_validation/               # Data validation tests
├── 
└── deprecated/                        # Deprecated files
    ├── data_processor.py              # Old root processor
    └── large_dataset_processor.py     # Old large dataset processor
```

## Key Active Components

### 🎯 **Main Pipeline** 
`database/run_amisafe_pipeline_stlouisintegration.sh`

### 🔧 **Core Processors**
- `database/amisafe_processor.py` - Data ingestion & cleaning
- `database/amisafe_aggregator.py` - H3 hexagon aggregation

### 🗄️ **Database Target**
`stlouisintegration_dev` database with AmISafe tables

### 📊 **Data Volume**
20 CSV files totaling 673MB+ with 3.4M+ crime incidents

## Quick Start Commands

```bash
# 1. Navigate to H3 directory
cd /workspaces/stlouisintegration.com/h3-geolocation

# 2. Activate Python environment  
source h3-env/bin/activate

# 3. Run the pipeline
cd database
bash run_amisafe_pipeline_stlouisintegration.sh full

# 4. Monitor processing
python monitor_processing.py
```

## Integration Status
✅ Integrated with complete-setup.sh  
✅ AmISafe database tables configured  
✅ Virtual environment ready  
✅ All dependencies installed  
✅ Pipeline scripts updated for stlouisintegration_dev

---
**Directory Cleaned:** November 10, 2025  
**Status:** Production Ready