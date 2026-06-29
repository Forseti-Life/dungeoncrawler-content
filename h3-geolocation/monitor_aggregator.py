#!/usr/bin/env python3
"""
H3 Aggregator Progress Monitor
Keeps workspace alive and monitors aggregation progress
"""

import time
import json
import mysql.connector
from datetime import datetime
import sys
import os

def load_db_config():
    """Load database configuration"""
    config_path = os.path.join(os.path.dirname(__file__), 'config', 'mysql_config.json')
    with open(config_path, 'r') as f:
        return json.load(f)

def check_aggregator_progress():
    """Check current H3 aggregation progress"""
    try:
        config = load_db_config()
        conn = mysql.connector.connect(**config)
        cursor = conn.cursor()
        
        # Check if table exists
        cursor.execute('SHOW TABLES LIKE "amisafe_h3_aggregated"')
        if not cursor.fetchone():
            return "H3 aggregated table does not exist yet"
        
        # Get progress by resolution
        cursor.execute('''
            SELECT h3_resolution, COUNT(*) as count, 
                   MIN(incident_count) as min_incidents,
                   MAX(incident_count) as max_incidents,
                   AVG(incident_count) as avg_incidents
            FROM amisafe_h3_aggregated 
            GROUP BY h3_resolution 
            ORDER BY h3_resolution
        ''')
        results = cursor.fetchall()
        
        if not results:
            return "No H3 aggregation data found yet"
        
        # Format progress report
        report = [
            f"🔄 H3 Aggregation Progress - {datetime.now().strftime('%H:%M:%S')}",
            "=" * 60,
            f"{'Resolution':<10} {'Cells':<8} {'Min':<6} {'Max':<8} {'Avg':<8}",
            "-" * 60
        ]
        
        total_cells = 0
        for res, count, min_inc, max_inc, avg_inc in results:
            total_cells += count
            report.append(f"H3:{res:<6} {count:>8,} {min_inc:>6} {max_inc:>8,} {avg_inc:>8,.1f}")
        
        report.extend([
            "-" * 60,
            f"Total H3 cells processed: {total_cells:,}",
            f"Expected resolutions: 5, 6, 7, 8, 9, 10",
            ""
        ])
        
        return "\n".join(report)
        
    except Exception as e:
        return f"Error checking progress: {e}"
    finally:
        if 'conn' in locals():
            conn.close()

def main():
    """Main monitoring loop"""
    print("🚀 Starting H3 Aggregator Monitor")
    print("This will check progress every 5 minutes to keep workspace alive")
    print("Press Ctrl+C to stop monitoring")
    print("=" * 60)
    
    try:
        while True:
            # Check progress
            progress = check_aggregator_progress()
            print(progress)
            
            # Keep workspace alive message
            print(f"💓 Workspace keepalive - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            print("=" * 60)
            
            # Wait 5 minutes (300 seconds)
            time.sleep(300)
            
    except KeyboardInterrupt:
        print("\n👋 Monitoring stopped by user")
    except Exception as e:
        print(f"❌ Monitor error: {e}")

if __name__ == "__main__":
    main()