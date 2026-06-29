# AmISafe Database Backup & Restore Scripts

## Overview

Comprehensive backup and restore scripts for the AmISafe H3 geolocation database. These scripts replace the previous scripts (`backup.sh` and `restore.sh`) with updated versions that correctly reflect the current database structure.

## Current Database Structure

**Database:** `amisafe_database` (previously referenced as `theoryofconspiracies_dev`)

**Tables:** 4
- `amisafe_raw_incidents` - Bronze layer (immutable source data)
- `amisafe_clean_incidents` - Silver layer (cleaned, H3-indexed data) 
- `amisafe_h3_aggregated` - Gold layer (pre-computed analytics)
- `amisafe_ucr_codes` - Reference data (UCR crime codes)

**Stored Procedures:** 21
- Analytics pipeline procedures
- Statistical calculation procedures
- Windowed analytics procedures
- Master orchestration procedures

**H3 Coverage:**
- Resolutions: 5-13 (9 distinct resolutions)
- Total Hexagons: 412,560
- Analytical Columns: 84 (28 all-time + 28 12mo + 28 6mo)

## New Scripts

### backup_amisafe.sh

**Purpose:** Creates comprehensive backups of the complete AmISafe database

**Features:**
- ✅ Backs up all 4 tables with data
- ✅ Includes all 21 stored procedures
- ✅ Includes triggers and events
- ✅ Preserves H3:13 granular filtering data (incident_ids column)
- ✅ Creates structure-only backup for reference
- ✅ Generates detailed metadata file
- ✅ Optional gzip compression
- ✅ Single-transaction consistency
- ✅ UTF-8 character set support

**Location:** `/home/keithaumiller/stlouisintegration.com/script/database/backup_amisafe.sh`

**Backup Destination:** `/home/keithaumiller/stlouisintegration.com/database-exports/dumps/`

**Usage:**
```bash
cd /home/keithaumiller/stlouisintegration.com/script/database
./backup_amisafe.sh
```

**Output Files:**
- `amisafe_complete_YYYYMMDD_HHMMSS.sql` - Full database dump
- `amisafe_complete_YYYYMMDD_HHMMSS_structure.sql` - Structure only
- `amisafe_complete_YYYYMMDD_HHMMSS_metadata.txt` - Backup metadata
- Optional: `.sql.gz` compressed versions

### restore_amisafe.sh

**Purpose:** Restores the complete AmISafe database from backup

**Features:**
- ✅ Restores all tables with data
- ✅ Restores all 21 stored procedures
- ✅ Restores triggers and events
- ✅ Auto-detects most recent backup
- ✅ Supports compressed (.sql.gz) backups
- ✅ Verifies restoration completeness
- ✅ Creates detailed restore log
- ✅ Safety confirmations before destructive operations
- ✅ Shows before/after statistics

**Location:** `/home/keithaumiller/stlouisintegration.com/script/database/restore_amisafe.sh`

**Usage:**
```bash
cd /home/keithaumiller/stlouisintegration.com/script/database
./restore_amisafe.sh
```

**Safety Features:**
- Requires confirmation before proceeding
- Shows current database contents before restore
- Requires typing "RESTORE" to confirm if database has data
- Creates restore log with timestamp

## Changes from Previous Scripts

### Old Scripts (backup.sh / restore.sh)
- ❌ Wrong database name (`theoryofconspiracies_dev`)
- ❌ Missing `amisafe_ucr_codes` table
- ❌ Did NOT include stored procedures
- ❌ Hard-coded backup directory
- ❌ No compression option
- ❌ Limited verification

### New Scripts (backup_amisafe.sh / restore_amisafe.sh)
- ✅ Correct database name (`amisafe_database`)
- ✅ Includes all 4 tables
- ✅ Backs up all 21 stored procedures
- ✅ Configurable backup directory
- ✅ Optional gzip compression
- ✅ Comprehensive verification
- ✅ Detailed metadata and logging
- ✅ Safety confirmations

## Database Statistics

**Current Production Data (as of Nov 21, 2025):**
- Raw incidents: 3,406,192 records
- Clean incidents: 3,406,192 records (100% H3-indexed)
- H3 aggregations: 412,560 hexagons
- UCR codes: ~2,000 reference entries

**Analytics Completion:**
- All-time analytics: 412,560 hexagons (100%)
- 12-month windowed: 412,559 hexagons (99.9998%)
- 6-month windowed: 412,559 hexagons (99.9998%)

**H3:13 Granular Filtering:**
- Hexagons with incident_ids: 177,129
- Room-level precision: 7m × 7m hexagons
- Enables per-incident filtering within hexagons

## Backup Best Practices

### When to Backup

**Essential backups:**
- ✅ After initial data load (3.4M incidents)
- ✅ After H3 aggregation completion (all 9 resolutions)
- ✅ After analytics pipeline completion (84 columns)
- ✅ Before major schema changes
- ✅ Before stored procedure updates
- ✅ Before production deployment

**Regular backups:**
- 📅 Weekly for active development
- 📅 Daily for production systems
- 📅 Before/after bulk data updates

### Backup Storage

**Current setup:**
```
/home/keithaumiller/stlouisintegration.com/database-exports/dumps/
```

**Recommended external storage:**
```bash
# To SD card (476GB available)
BACKUP_DIR="/mnt/chromeos/removable/SD Card/amisafe-backups" ./backup_amisafe.sh

# To external drive
BACKUP_DIR="/path/to/external/drive/amisafe-backups" ./backup_amisafe.sh
```

**Retention policy:**
- Keep last 3 backups locally
- Keep weekly backups for 1 month
- Keep monthly backups for 1 year
- Archive major milestones permanently

### Compression

**Uncompressed (default):**
- Pros: Faster backup/restore, easy inspection
- Cons: Large file size (~1-2GB)
- Use for: Local backups, frequent operations

**Compressed (gzip):**
- Pros: 80-90% size reduction, better for archival
- Cons: Slower backup/restore
- Use for: External storage, long-term archival

## Restore Scenarios

### Scenario 1: Fresh Install
```bash
# Database doesn't exist yet
./restore_amisafe.sh
# Script will create database automatically
```

### Scenario 2: Disaster Recovery
```bash
# Complete data loss, need to restore everything
./restore_amisafe.sh
# Requires typing "RESTORE" to confirm
```

### Scenario 3: Rollback After Failed Update
```bash
# Undo recent changes by restoring previous backup
./restore_amisafe.sh
# Will detect and use most recent backup
```

### Scenario 4: Clone to Different Database
```bash
# Edit restore script to change DATABASE_NAME temporarily
# Or manually restore:
gunzip < amisafe_complete_20251121_123456.sql.gz | \
  mysql -u drupal_user -p amisafe_database_clone
```

## Verification

After restore, verify completeness:

```sql
-- Check tables
USE amisafe_database;
SHOW TABLES;

-- Check record counts
SELECT 'raw_incidents' as table_name, COUNT(*) as count FROM amisafe_raw_incidents
UNION ALL
SELECT 'clean_incidents', COUNT(*) FROM amisafe_clean_incidents  
UNION ALL
SELECT 'h3_aggregated', COUNT(*) FROM amisafe_h3_aggregated
UNION ALL
SELECT 'ucr_codes', COUNT(*) FROM amisafe_ucr_codes;

-- Check stored procedures
SELECT COUNT(*) as procedure_count 
FROM information_schema.ROUTINES 
WHERE ROUTINE_SCHEMA = 'amisafe_database' AND ROUTINE_TYPE = 'PROCEDURE';

-- Check H3 coverage
SELECT 
    MIN(h3_resolution) as min_res,
    MAX(h3_resolution) as max_res,
    COUNT(DISTINCT h3_resolution) as res_count,
    COUNT(*) as total_hexagons
FROM amisafe_h3_aggregated;

-- Check analytics completion
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN risk_category IS NOT NULL THEN 1 ELSE 0 END) as alltime,
    SUM(CASE WHEN risk_category_12mo IS NOT NULL THEN 1 ELSE 0 END) as w12mo,
    SUM(CASE WHEN risk_category_6mo IS NOT NULL THEN 1 ELSE 0 END) as w6mo
FROM amisafe_h3_aggregated;
```

## Troubleshooting

### Issue: "Cannot connect to database"
**Solution:** Check MySQL credentials, ensure MySQL is running
```bash
systemctl status mysql
mysql -u drupal_user -p -e "SELECT 1;"
```

### Issue: "Backup file not found"
**Solution:** Run backup first
```bash
./backup_amisafe.sh
```

### Issue: "Insufficient disk space"
**Solution:** Check available space, use external storage
```bash
df -h
BACKUP_DIR="/mnt/chromeos/removable/SD Card/backups" ./backup_amisafe.sh
```

### Issue: "Restore taking too long"
**Solution:** Normal for large datasets. Monitor progress:
```bash
# In another terminal
mysql -u drupal_user -p amisafe_database -e "SHOW PROCESSLIST;"
```

### Issue: "Missing stored procedures after restore"
**Solution:** Ensure backup includes `--routines` flag (new scripts do this automatically)

## Legacy Scripts

The previous `backup.sh` and `restore.sh` scripts are deprecated and should not be used. They have been moved to the legacy folder for reference.

**Why deprecated:**
- Wrong database name
- Missing tables and procedures
- No verification
- Less safe (no confirmations)

**Migration:**
Use the new scripts instead:
- `backup_amisafe.sh` instead of `backup.sh`
- `restore_amisafe.sh` instead of `restore.sh`

## Environment Variables

Both scripts support environment variable overrides:

```bash
# Override backup directory
export BACKUP_DIR="/custom/path/to/backups"

# Override database credentials (if different from defaults)
export MYSQL_USER="custom_user"
export MYSQL_PASSWORD="custom_password"
export MYSQL_HOST="custom_host"
export DATABASE_NAME="custom_database"

# Run scripts
./backup_amisafe.sh
./restore_amisafe.sh
```

## Technical Details

### Backup Method
- Uses `mysqldump` with `--single-transaction` for consistency
- Includes `--routines`, `--triggers`, `--events`
- Uses `--quick` for memory efficiency
- Character set: `utf8mb4` (full Unicode support)

### File Format
- Plain SQL format (portable, human-readable)
- Optional gzip compression (80-90% size reduction)
- Includes DROP TABLE statements for clean restore
- Includes CREATE TABLE with full schema

### Restore Process
1. Verify backup file exists
2. Test database connection
3. Show current contents (if any)
4. Require user confirmation
5. Import SQL dump
6. Verify tables, procedures, data
7. Create restore log
8. Display summary statistics

## Support

For issues or questions:
1. Check this README
2. Review log files (metadata.txt, restore_log.txt)
3. Verify MySQL connection and credentials
4. Check disk space and permissions
5. Review MySQL error logs if needed

## Version History

- **2025-11-21**: Created new backup_amisafe.sh and restore_amisafe.sh
  - Fixed database name (amisafe_database)
  - Added all 4 tables
  - Included 21 stored procedures
  - Added compression option
  - Added comprehensive verification
  - Created this documentation

- **Previous**: backup.sh and restore.sh (deprecated)
  - Used wrong database name
  - Missing procedures and table
  - Limited verification
