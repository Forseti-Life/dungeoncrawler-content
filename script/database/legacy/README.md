# Legacy Database Scripts

This folder contains archived database setup, export, and import scripts that have been replaced by the consolidated `setup_consolidated.sh` script.

## Database Consolidation Overview

All database setup, export, and import scripts have been consolidated to provide a single source of truth for database operations. This consolidation eliminates redundancy, ensures consistency, and provides a unified approach to database schema creation.

**Primary Script:** `../setup_consolidated.sh` - Master database setup script in parent directory

## Contents

### Setup Scripts (Archived)
- `setup_amisafe_stlouisintegration_original.sh` - Original comprehensive database setup
- `setup-amisafe-db.sh` - Simple sample data loader
- `setup-amisafe-tables.sh` - Basic test table creation
- `setup-h3-aggregated.sh` - H3 aggregation table setup
- `setup-h3-aggregated-enhanced.sh` - Enhanced H3 table setup
- `setup_database.sh` - Database creation wrapper
- `setup.sh` - H3 pipeline setup
- `run_database_setup.sh` - Quick setup wrapper

### Export Scripts (Archived)
- `export_amisafe_data.sh` - Drush-based export with CSV/SQL options
- `export_amisafe_pure.sh` - Pure MySQL export using mysqldump
- `export_amisafe_simple.sh` - Simplified drush-based export

### Import Scripts (Archived)
- `import_amisafe_data.sh` - Drush-based import from backup files
- `import_amisafe_pure.sh` - Pure MySQL import from SQL dumps
- `import_amisafe_to_stlouisintegration.sh` - Cross-database migration script

## Consolidated Script Features

### ObjectID-Based Processing
- Primary business identifier: `objectid` (unique across all 3.4M records)
- Legacy support: `cartodb_id` (75% NULL values)
- Incident ID format: `obj_{objectid}`

### Database Schema Layers
1. **Raw Layer (Bronze)**: `amisafe_raw_incidents` - Immutable source data
2. **Transform Layer (Silver)**: `amisafe_clean_incidents` - Cleaned, validated, H3-indexed
3. **Final Layer (Gold)**: `amisafe_h3_aggregated` - Pre-computed analytics
4. **Reference Data**: `amisafe_ucr_codes` - UCR crime code definitions

### Sample Data Included
- 10+ raw incidents with realistic ObjectIDs
- Processed clean incidents with H3 indexing
- Multi-resolution H3 aggregations (res 8-9)
- Complete UCR code reference data

## Usage

### New Database Setup
Use the consolidated script in the parent directory:
```bash
# Use default database (forseti_dev)
cd .. && ./setup_consolidated.sh

# Specify custom database name
cd .. && ./setup_consolidated.sh my_custom_database
```

### When to Use Legacy Scripts

#### Data Migration
If you need to migrate existing data from old database setups:

1. **Export existing data**:
   ```bash
   ./export_amisafe_pure.sh
   ```

2. **Import to new database**:
   ```bash
   ./import_amisafe_pure.sh
   ```

#### Cross-Database Operations
For moving data between databases:
```bash
./import_amisafe_to_stlouisintegration.sh
```

#### Reference and Debugging
These scripts serve as reference implementations for:
- Understanding previous database schemas
- Debugging migration issues
- Comparing with consolidated implementation

## Benefits of Consolidation

1. **Single Source of Truth**: One script maintains all database schema
2. **Consistency**: Eliminates variations between different setup scripts
3. **ObjectID Support**: Proper constraints and indexing for objectid-based processing
4. **Comprehensive**: Includes all features from previous scripts
5. **Maintainable**: Easier to update and modify schema changes
6. **Testable**: Includes sample data for immediate testing

## Processing Pipeline Compatibility

The consolidated database schema is fully compatible with:
- `enhanced_transform_processor_v2.py` (ObjectID-based processing)
- `amisafe_aggregator.py` (H3 aggregation)
- `amisafe_processor.py` (Raw data ingestion)

## Verification

After running the consolidated setup script, verify the installation:

```bash
# Check table creation
mysql -u drupal_user -p forseti_dev -e "SHOW TABLES LIKE 'amisafe_%';"

# Verify ObjectID constraints
mysql -u drupal_user -p forseti_dev -e "
SELECT table_name, constraint_name, column_name 
FROM information_schema.key_column_usage 
WHERE table_schema = 'forseti_dev' 
AND column_name = 'objectid';
"

# Check sample data
mysql -u drupal_user -p forseti_dev -e "
SELECT COUNT(*) as raw_count FROM amisafe_raw_incidents;
SELECT COUNT(*) as clean_count FROM amisafe_clean_incidents;
SELECT COUNT(*) as h3_count FROM amisafe_h3_aggregated;
SELECT COUNT(*) as ucr_count FROM amisafe_ucr_codes;
"
```

## Archive Status

These scripts are preserved for:
- Data migration from existing deployments
- Reference implementation details
- Troubleshooting legacy database issues
- Historical documentation

They are no longer actively maintained but remain functional for migration purposes.
