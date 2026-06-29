#!/usr/bin/env python3
"""
AmISafe Analytics Runner with Restart Capability
Executes stored procedures to populate all 84 analytical columns
with checkpoint/restart functionality for long-running processes.

Leverages SQL stored procedures for 10-100x faster performance than Python:
- sp_complete_all_windows(resolution) - Master procedure for all 84 columns
- Individual procedures for granular control

Features:
- Resume from last checkpoint
- Progress tracking and logging
- Concurrent resolution processing (optional)
- Verification and validation
"""

import os
import sys
import mysql.connector
from mysql.connector import Error
import json
import logging
from datetime import datetime
from typing import Dict, List, Optional, Tuple
import argparse
import time
from decimal import Decimal

class DecimalEncoder(json.JSONEncoder):
    """JSON encoder that converts Decimal to float."""
    def default(self, obj):
        if isinstance(obj, Decimal):
            return float(obj)
        return super(DecimalEncoder, self).default(obj)

class AnalyticsRunner:
    """
    Manages execution of analytics stored procedures with restart capability.
    Tracks progress per resolution and can resume interrupted operations.
    """
    
    def __init__(self,
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = None,
                 mysql_database: str = 'amisafe_database',
                 mysql_socket: str = None,
                 state_file: str = 'analytics_state.json'):
        """Initialize the analytics runner."""
        self.mysql_config = {
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Use socket if provided, otherwise use host
        if mysql_socket:
            self.mysql_config['unix_socket'] = mysql_socket
        else:
            self.mysql_config['host'] = mysql_host
        
        self.state_file = state_file
        self.state = self.load_state()
        
        # Setup logging
        log_file = f'analytics_run_{datetime.now().strftime("%Y%m%d_%H%M%S")}.log'
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_file),
                logging.StreamHandler()
            ]
        )
        self.logger = logging.getLogger(__name__)
        self.logger.info(f"Analytics runner initialized. State file: {state_file}")
    
    def connect_to_mysql(self):
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def load_state(self) -> Dict:
        """Load checkpoint state from file."""
        if os.path.exists(self.state_file):
            try:
                with open(self.state_file, 'r') as f:
                    state = json.load(f)
                    print(f"📂 Loaded checkpoint state from {self.state_file}")
                    return state
            except Exception as e:
                print(f"⚠️  Could not load state file: {e}")
                return self.init_state()
        return self.init_state()
    
    def init_state(self) -> Dict:
        """Initialize empty state."""
        return {
            'resolutions': {},
            'last_updated': None,
            'total_hexagons_processed': 0
        }
    
    def save_state(self):
        """Save checkpoint state to file."""
        self.state['last_updated'] = datetime.now().isoformat()
        try:
            with open(self.state_file, 'w') as f:
                json.dump(self.state, f, indent=2, cls=DecimalEncoder)
            self.logger.debug(f"State saved to {self.state_file}")
        except Exception as e:
            self.logger.error(f"Could not save state: {e}")
    
    def verify_stored_procedures(self, connection) -> Tuple[bool, List[str]]:
        """Verify all required stored procedures are installed."""
        cursor = connection.cursor()
        
        required_procedures = [
            'sp_complete_all_windows',
            'sp_complete_resolution_analytics',
            'sp_update_resolution_analytics_windowed',
            'sp_calculate_statistical_metrics',
            'sp_calculate_risk_scores'
        ]
        
        cursor.execute("""
            SELECT ROUTINE_NAME 
            FROM information_schema.ROUTINES 
            WHERE ROUTINE_SCHEMA = %s 
              AND ROUTINE_NAME LIKE 'sp_%'
        """, (self.mysql_config['database'],))
        
        installed = {row[0] for row in cursor.fetchall()}
        missing = [p for p in required_procedures if p not in installed]
        
        cursor.close()
        
        if missing:
            self.logger.error(f"Missing stored procedures: {missing}")
            self.logger.error("Run: ./setup/setup_amisafe_complete.sh to install procedures")
            return False, missing
        
        self.logger.info(f"✅ All {len(installed)} stored procedures verified")
        return True, []
    
    def get_resolution_status(self, connection, resolution: int) -> Dict:
        """Check analytics completion status for a resolution."""
        cursor = connection.cursor()
        
        cursor.execute("""
            SELECT 
                COUNT(*) as total_hexes,
                SUM(CASE WHEN incident_count > 0 THEN 1 ELSE 0 END) as has_incidents,
                SUM(CASE WHEN top_crime_type IS NOT NULL THEN 1 ELSE 0 END) as has_alltime_basic,
                SUM(CASE WHEN risk_category IS NOT NULL THEN 1 ELSE 0 END) as has_alltime_risk,
                SUM(CASE WHEN top_crime_type_12mo IS NOT NULL THEN 1 ELSE 0 END) as has_12mo_basic,
                SUM(CASE WHEN risk_category_12mo IS NOT NULL THEN 1 ELSE 0 END) as has_12mo_risk,
                SUM(CASE WHEN top_crime_type_6mo IS NOT NULL THEN 1 ELSE 0 END) as has_6mo_basic,
                SUM(CASE WHEN risk_category_6mo IS NOT NULL THEN 1 ELSE 0 END) as has_6mo_risk
            FROM amisafe_h3_aggregated 
            WHERE h3_resolution = %s
        """, (resolution,))
        
        row = cursor.fetchone()
        cursor.close()
        
        if not row or row[0] == 0:
            return {
                'total_hexes': 0,
                'completion': {
                    'alltime_basic': 0,
                    'alltime_risk': 0,
                    'windowed_12mo': 0,
                    'windowed_6mo': 0,
                    'overall': 0
                },
                'needs_processing': False
            }
        
        total = row[0]
        has_incidents = row[1] or 0
        
        # Calculate completion percentages
        alltime_basic_pct = (row[2] / has_incidents * 100) if has_incidents > 0 else 0
        alltime_risk_pct = (row[3] / has_incidents * 100) if has_incidents > 0 else 0
        windowed_12mo_pct = (row[4] / has_incidents * 100) if has_incidents > 0 else 0
        windowed_6mo_pct = (row[6] / has_incidents * 100) if has_incidents > 0 else 0
        
        # Overall completion (all must be 100%)
        overall_complete = all([
            alltime_basic_pct == 100,
            alltime_risk_pct == 100,
            windowed_12mo_pct == 100,
            windowed_6mo_pct == 100
        ])
        
        return {
            'total_hexes': total,
            'has_incidents': has_incidents,
            'completion': {
                'alltime_basic': round(alltime_basic_pct, 1),
                'alltime_risk': round(alltime_risk_pct, 1),
                'windowed_12mo': round(windowed_12mo_pct, 1),
                'windowed_6mo': round(windowed_6mo_pct, 1),
                'overall': 100.0 if overall_complete else 0.0
            },
            'needs_processing': not overall_complete
        }
    
    def run_analytics_for_resolution(self, connection, resolution: int, force: bool = False) -> Dict:
        """
        Run complete analytics for a resolution using sp_complete_all_windows.
        
        Args:
            connection: MySQL connection
            resolution: H3 resolution (5-13)
            force: Force reprocessing even if already complete
            
        Returns:
            Dict with processing results
        """
        # Check current status
        status = self.get_resolution_status(connection, resolution)
        
        if status['completion']['overall'] == 100.0 and not force:
            self.logger.info(f"✅ Resolution {resolution} already complete ({status['total_hexes']} hexes)")
            return {
                'resolution': resolution,
                'status': 'already_complete',
                'hexagons': status['total_hexes'],
                'completion': status['completion']
            }
        
        if not status['needs_processing']:
            self.logger.info(f"⏭️  Resolution {resolution} has no hexagons needing processing")
            return {
                'resolution': resolution,
                'status': 'no_data',
                'hexagons': 0
            }
        
        self.logger.info(f"🔄 Starting analytics for Resolution {resolution}")
        self.logger.info(f"   Total hexagons: {status['total_hexes']:,}")
        self.logger.info(f"   With incidents: {status['has_incidents']:,}")
        self.logger.info(f"   Current completion: {status['completion']}")
        
        # Update state
        res_key = str(resolution)
        if res_key not in self.state['resolutions']:
            self.state['resolutions'][res_key] = {}
        
        self.state['resolutions'][res_key]['status'] = 'processing'
        self.state['resolutions'][res_key]['start_time'] = datetime.now().isoformat()
        self.state['resolutions'][res_key]['total_hexes'] = status['total_hexes']
        self.save_state()
        
        # Execute master stored procedure
        start_time = time.time()
        cursor = connection.cursor()
        
        try:
            self.logger.info(f"📊 Executing: CALL sp_complete_all_windows({resolution})")
            cursor.callproc('sp_complete_all_windows', [resolution])
            
            # Consume results (stored procedures may return result sets)
            for result in cursor.stored_results():
                result.fetchall()
            
            elapsed = time.time() - start_time
            
            # Verify completion
            final_status = self.get_resolution_status(connection, resolution)
            
            # Update state
            self.state['resolutions'][res_key]['status'] = 'complete'
            self.state['resolutions'][res_key]['end_time'] = datetime.now().isoformat()
            self.state['resolutions'][res_key]['elapsed_seconds'] = round(elapsed, 2)
            self.state['resolutions'][res_key]['completion'] = final_status['completion']
            self.state['total_hexagons_processed'] += int(status['has_incidents'])
            self.save_state()
            
            self.logger.info(f"✅ Resolution {resolution} completed in {elapsed:.1f}s")
            self.logger.info(f"   Final completion: {final_status['completion']}")
            
            return {
                'resolution': resolution,
                'status': 'success',
                'hexagons': status['has_incidents'],
                'elapsed_seconds': round(elapsed, 2),
                'completion': final_status['completion']
            }
            
        except Exception as e:
            self.logger.error(f"❌ Error processing resolution {resolution}: {e}")
            self.state['resolutions'][res_key]['status'] = 'error'
            self.state['resolutions'][res_key]['error'] = str(e)
            self.save_state()
            
            return {
                'resolution': resolution,
                'status': 'error',
                'error': str(e)
            }
        finally:
            cursor.close()
    
    def run_all_resolutions(self, resolutions: List[int], force: bool = False) -> Dict:
        """
        Run analytics for multiple resolutions.
        
        Args:
            resolutions: List of H3 resolutions to process
            force: Force reprocessing even if already complete
            
        Returns:
            Dict with overall results
        """
        connection = self.connect_to_mysql()
        results = {
            'start_time': datetime.now().isoformat(),
            'resolutions': {},
            'summary': {
                'total_requested': len(resolutions),
                'completed': 0,
                'already_complete': 0,
                'errors': 0,
                'total_hexagons': 0
            }
        }
        
        try:
            # Verify stored procedures
            procedures_ok, missing = self.verify_stored_procedures(connection)
            if not procedures_ok:
                raise Exception(f"Missing stored procedures: {missing}")
            
            # Process each resolution
            for resolution in resolutions:
                self.logger.info(f"\n{'='*70}")
                self.logger.info(f"Processing Resolution {resolution}")
                self.logger.info(f"{'='*70}")
                
                result = self.run_analytics_for_resolution(connection, resolution, force)
                results['resolutions'][resolution] = result
                
                # Update summary
                if result['status'] == 'success':
                    results['summary']['completed'] += 1
                    results['summary']['total_hexagons'] += result.get('hexagons', 0)
                elif result['status'] == 'already_complete':
                    results['summary']['already_complete'] += 1
                    results['summary']['total_hexagons'] += result.get('hexagons', 0)
                elif result['status'] == 'error':
                    results['summary']['errors'] += 1
            
            results['end_time'] = datetime.now().isoformat()
            
        finally:
            if connection.is_connected():
                connection.close()
        
        return results
    
    def print_summary(self, results: Dict):
        """Print formatted summary of results."""
        print("\n" + "="*70)
        print("ANALYTICS RUN SUMMARY")
        print("="*70)
        
        summary = results['summary']
        print(f"📊 Resolutions processed: {summary['completed']}/{summary['total_requested']}")
        print(f"✅ Already complete: {summary['already_complete']}")
        print(f"❌ Errors: {summary['errors']}")
        print(f"🎯 Total hexagons: {summary['total_hexagons']:,}")
        
        print(f"\n{'='*70}")
        print("RESOLUTION DETAILS")
        print(f"{'='*70}")
        
        for resolution, data in sorted(results['resolutions'].items()):
            status_icon = {
                'success': '✅',
                'already_complete': '✓',
                'error': '❌',
                'no_data': '⏭️'
            }.get(data['status'], '?')
            
            print(f"\n{status_icon} Resolution {resolution}: {data['status'].upper()}")
            
            if 'hexagons' in data:
                print(f"   Hexagons: {data['hexagons']:,}")
            
            if 'elapsed_seconds' in data:
                print(f"   Time: {data['elapsed_seconds']}s")
            
            if 'completion' in data:
                comp = data['completion']
                print(f"   Completion: All-time={comp['alltime_basic']}%/{comp['alltime_risk']}%, "
                      f"12mo={comp['windowed_12mo']}%, 6mo={comp['windowed_6mo']}%")
            
            if 'error' in data:
                print(f"   Error: {data['error']}")
        
        print(f"\n{'='*70}")


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description='AmISafe Analytics Runner - Execute stored procedures with restart capability'
    )
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default=os.environ.get('DB_PASSWORD'), help='MySQL password (from DB_PASSWORD env var)')
    parser.add_argument('--mysql-database', default='amisafe_database', help='MySQL database')
    parser.add_argument('--resolutions', nargs='+', type=int, default=[13, 12, 11, 10, 9, 8, 7, 6, 5],
                        help='H3 resolutions to process (default: 13-5, high to low)')
    parser.add_argument('--force', action='store_true',
                        help='Force reprocessing even if already complete')
    
    args = parser.parse_args()
    
    if not args.mysql_password:
        print('ERROR: DB_PASSWORD environment variable is required')
        sys.exit(1)
    parser.add_argument('--state-file', default='analytics_state.json',
                        help='State file for checkpoints (default: analytics_state.json)')
    parser.add_argument('--status', action='store_true',
                        help='Show current status and exit')
    parser.add_argument('--mysql-socket', default=None,
                        help='MySQL unix socket path (e.g., /var/run/mysqld/mysqld.sock)')
    
    args = parser.parse_args()
    
    # Initialize runner
    runner = AnalyticsRunner(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database,
        mysql_socket=args.mysql_socket,
        state_file=args.state_file
    )
    
    if args.status:
        # Show status only
        connection = runner.connect_to_mysql()
        try:
            print("\n" + "="*70)
            print("ANALYTICS STATUS")
            print("="*70)
            
            for resolution in args.resolutions:
                status = runner.get_resolution_status(connection, resolution)
                print(f"\nResolution {resolution}:")
                print(f"  Total hexagons: {status['total_hexes']:,}")
                print(f"  With incidents: {status.get('has_incidents', 0):,}")
                print(f"  Completion: {status['completion']}")
                print(f"  Needs processing: {status['needs_processing']}")
        finally:
            connection.close()
        return
    
    # Run analytics
    try:
        print(f"\n🚀 Starting AmISafe Analytics Processing")
        print(f"📋 Resolutions: {args.resolutions}")
        print(f"💾 State file: {args.state_file}")
        print(f"🔄 Force reprocess: {args.force}")
        
        results = runner.run_all_resolutions(args.resolutions, force=args.force)
        runner.print_summary(results)
        
        if results['summary']['errors'] > 0:
            sys.exit(1)
        
        print(f"\n🎉 Analytics processing complete!")
        
    except Exception as e:
        print(f"\n❌ FATAL ERROR: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
