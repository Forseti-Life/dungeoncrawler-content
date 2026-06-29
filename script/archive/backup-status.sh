#!/bin/bash
# Backup status monitoring script

# Configuration
DAILY_BACKUP_DIR="/var/backups/stlouisintegration/daily"
WEEKLY_BACKUP_DIR="/var/backups/stlouisintegration/weekly"

echo "=== St. Louis Integration Backup Status ==="
echo "Generated: $(date)"
echo

# Daily backup status
echo "DAILY BACKUPS:"
if [ -d "$DAILY_BACKUP_DIR" ]; then
    echo "Location: $DAILY_BACKUP_DIR"
    echo "Current backups:"
    ls -la "$DAILY_BACKUP_DIR"/*.gz 2>/dev/null | head -10 || echo "No daily backups found"
    echo "Disk usage: $(du -sh "$DAILY_BACKUP_DIR" 2>/dev/null | cut -f1)"
else
    echo "Daily backup directory not found"
fi
echo

# Weekly backup status
echo "WEEKLY BACKUPS:"
if [ -d "$WEEKLY_BACKUP_DIR" ]; then
    echo "Location: $WEEKLY_BACKUP_DIR"
    echo "Current backups:"
    ls -la "$WEEKLY_BACKUP_DIR"/*.gz 2>/dev/null | head -10 || echo "No weekly backups found"
    echo "Disk usage: $(du -sh "$WEEKLY_BACKUP_DIR" 2>/dev/null | cut -f1)"
else
    echo "Weekly backup directory not found"
fi
echo

# Recent backup logs
echo "RECENT BACKUP ACTIVITY:"
echo "Daily backup log (last 10 lines):"
tail -n 10 /var/log/stlouisintegration-daily-backup.log 2>/dev/null || echo "No daily backup log found"
echo
echo "Weekly backup log (last 10 lines):" 
tail -n 10 /var/log/stlouisintegration-weekly-backup.log 2>/dev/null || echo "No weekly backup log found"