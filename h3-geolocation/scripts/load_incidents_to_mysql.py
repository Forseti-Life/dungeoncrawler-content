#!/usr/bin/env python3
"""
Load all CSV files in `h3-geolocation/data/raw` into MySQL `amisafe.raw_incidents`.

Features:
- Scans all CSVs in the raw directory (matching *.csv)
- Normalizes known columns, stores unknown columns inside `properties` JSON
- Computes H3 index (hex string) for each row and stores in `h3_index`
- Uses batch inserts for performance

Usage example:
    source h3-env/bin/activate
    python3 scripts/load_incidents_to_mysql.py --config config/mysql_config.json --data-dir data/raw --batch-size 5000 --h3-resolution 9

"""
import os
import json
import glob
import argparse
import math
from pathlib import Path
from typing import Dict, Any, List

import pandas as pd
import h3
import mysql.connector
from mysql.connector import errorcode
from tqdm import tqdm

# Known columns we will map to table columns (from CSV sample)
KNOWN_COLUMNS = [
    'the_geom', 'cartodb_id', 'the_geom_webmercator', 'objectid', 'dc_dist', 'psa',
    'dispatch_date_time', 'dispatch_date', 'dispatch_time', 'hour', 'dc_key',
    'location_block', 'ucr_general', 'text_general_code', 'point_x', 'point_y', 'lat', 'lng'
]

INSERT_QUERY = (
    "INSERT INTO raw_incidents ("
    "source_file, cartodb_id, objectid, dc_dist, psa, dispatch_date_time, dispatch_date, dispatch_time, hour, dc_key,"
    "location_block, ucr_general, text_general_code, point_x, point_y, lat, lng, h3_index, h3_resolution, properties)"
    " VALUES (%(source_file)s, %(cartodb_id)s, %(objectid)s, %(dc_dist)s, %(psa)s, %(dispatch_date_time)s, %(dispatch_date)s, %(dispatch_time)s, %(hour)s, %(dc_key)s,"
    "%(location_block)s, %(ucr_general)s, %(text_general_code)s, %(point_x)s, %(point_y)s, %(lat)s, %(lng)s, %(h3_index)s, %(h3_resolution)s, %(properties)s)"
)


def detect_lat_lng_columns(columns: List[str]) -> (str, str):
    # Common variants
    lat_variants = ['lat', 'latitude', 'y', 'point_y']
    lng_variants = ['lng', 'lon', 'longitude', 'x', 'point_x']

    lat_col = None
    lng_col = None
    for c in columns:
        low = c.lower()
        if not lat_col and low in lat_variants:
            lat_col = c
        if not lng_col and low in lng_variants:
            lng_col = c
    return lat_col, lng_col


def connect_mysql(config: Dict[str, Any]):
    return mysql.connector.connect(
        host=config.get('host', 'localhost'),
        port=config.get('port', 3306),
        user=config['user'],
        password=config['password'],
        database=config.get('database', 'amisafe'),
        charset=config.get('charset', 'utf8mb4'),
        autocommit=False
    )


def prepare_row(row: pd.Series, source_file: str, lat_col: str, lng_col: str, h3_resolution: int) -> Dict[str, Any]:
    # Initialize record with all expected fields
    record = {
        'source_file': source_file,
        'cartodb_id': None,
        'objectid': None,
        'dc_dist': None,
        'psa': None,
        'dispatch_date_time': None,
        'dispatch_date': None,
        'dispatch_time': None,
        'hour': None,
        'dc_key': None,
        'location_block': None,
        'ucr_general': None,
        'text_general_code': None,
        'point_x': None,
        'point_y': None,
        'lat': None,
        'lng': None,
        'h3_index': None,
        'h3_resolution': None,
        'properties': None
    }

    # Map known columns from CSV to record
    for col in KNOWN_COLUMNS:
        if col in row.index:
            val = row[col]
            if pd.isna(val):
                val = None
            
            # Map CSV column to record field
            if col == 'cartodb_id':
                record['cartodb_id'] = str(val) if val is not None else None
            elif col == 'objectid':
                record['objectid'] = str(val) if val is not None else None
            elif col == 'dc_dist':
                record['dc_dist'] = str(val) if val is not None else None
            elif col == 'psa':
                record['psa'] = str(val) if val is not None else None
            elif col == 'dispatch_date_time':
                # Handle timezone info in datetime
                if val is not None:
                    val_str = str(val)
                    # Remove timezone suffix (+00, -05, etc.) for MySQL compatibility
                    if '+' in val_str:
                        val_str = val_str.split('+')[0]
                    elif val_str.endswith('Z'):
                        val_str = val_str[:-1]
                    record['dispatch_date_time'] = val_str
                else:
                    record['dispatch_date_time'] = None
            elif col == 'dispatch_date':
                record['dispatch_date'] = str(val) if val is not None else None
            elif col == 'dispatch_time':
                record['dispatch_time'] = str(val) if val is not None else None
            elif col == 'hour':
                try:
                    record['hour'] = int(val) if val is not None else None
                except (ValueError, TypeError):
                    record['hour'] = None
            elif col == 'dc_key':
                record['dc_key'] = str(val) if val is not None else None
            elif col == 'location_block':
                record['location_block'] = str(val) if val is not None else None
            elif col == 'ucr_general':
                record['ucr_general'] = str(val) if val is not None else None
            elif col == 'text_general_code':
                record['text_general_code'] = str(val) if val is not None else None
            elif col == 'point_x':
                try:
                    record['point_x'] = float(val) if val is not None else None
                except (ValueError, TypeError):
                    record['point_x'] = None
            elif col == 'point_y':
                try:
                    record['point_y'] = float(val) if val is not None else None
                except (ValueError, TypeError):
                    record['point_y'] = None
            elif col == 'lat':
                try:
                    record['lat'] = float(val) if val is not None else None
                except (ValueError, TypeError):
                    record['lat'] = None
            elif col == 'lng':
                try:
                    record['lng'] = float(val) if val is not None else None
                except (ValueError, TypeError):
                    record['lng'] = None

    # Use detected lat/lng columns if available
    if lat_col and lng_col and lat_col in row.index and lng_col in row.index:
        try:
            lat_val = row[lat_col]
            lng_val = row[lng_col]
            if not pd.isna(lat_val) and not pd.isna(lng_val):
                record['lat'] = float(lat_val)
                record['lng'] = float(lng_val)
        except (ValueError, TypeError):
            pass

    # Compute H3 index if coords are valid
    if record['lat'] is not None and record['lng'] is not None:
        if -90 <= record['lat'] <= 90 and -180 <= record['lng'] <= 180:
            try:
                h3_idx = h3.latlng_to_cell(record['lat'], record['lng'], h3_resolution)
                record['h3_index'] = h3_idx
                record['h3_resolution'] = h3_resolution
            except Exception:
                record['h3_index'] = None
                record['h3_resolution'] = None

    # Store unknown columns in properties JSON
    properties = {}
    for col in row.index:
        if col not in KNOWN_COLUMNS:
            val = row[col]
            if pd.isna(val):
                val = None
            properties[col] = val

    record['properties'] = json.dumps(properties) if properties else None

    return record


def process_file(conn, file_path: Path, batch_size: int, h3_resolution: int):
    cursor = conn.cursor()
    total_inserted = 0
    
    print(f"Processing file: {file_path.name}")
    
    # Get total rows for progress bar
    try:
        total_rows = sum(1 for _ in open(file_path)) - 1  # subtract header
    except:
        total_rows = None
    
    df_iter = pd.read_csv(file_path, chunksize=batch_size, dtype=str)

    # Detect lat/lng columns from first chunk
    sample = pd.read_csv(file_path, nrows=1)
    lat_col, lng_col = detect_lat_lng_columns(list(sample.columns))
    print(f"Using lat column: {lat_col}, lng column: {lng_col}")

    with tqdm(total=total_rows, desc=f"Loading {file_path.name}") as pbar:
        for chunk in df_iter:
            rows = []
            for _, row in chunk.iterrows():
                prepared = prepare_row(row, file_path.name, lat_col, lng_col, h3_resolution)
                rows.append(prepared)

            # Batch insert
            try:
                cursor.executemany(INSERT_QUERY, rows)
                conn.commit()
                total_inserted += cursor.rowcount
                pbar.update(len(chunk))
            except Exception as e:
                print(f"Error inserting batch from {file_path.name}: {e}")
                conn.rollback()

    cursor.close()
    return total_inserted


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True, help='Path to mysql config JSON')
    parser.add_argument('--data-dir', default='data/raw', help='Directory containing CSV files')
    parser.add_argument('--batch-size', type=int, default=5000, help='Pandas chunk size / batch insert size')
    parser.add_argument('--h3-resolution', type=int, default=9, help='H3 resolution to compute')
    parser.add_argument('--test-file', help='Process only this specific file (for testing)')
    args = parser.parse_args()

    cfg_path = Path(args.config)
    if not cfg_path.exists():
        print(f"MySQL config file not found: {cfg_path}")
        return

    config = json.loads(cfg_path.read_text())

    # Connect to MySQL
    try:
        conn = connect_mysql(config)
        print("Connected to MySQL successfully")
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("Something is wrong with your MySQL user name or password")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("Database does not exist")
        else:
            print(f"MySQL connection error: {err}")
        return

    data_dir = Path(args.data_dir)
    
    if args.test_file:
        # Process only the specified test file
        test_path = data_dir / args.test_file
        if not test_path.exists():
            print(f"Test file not found: {test_path}")
            return
        files = [test_path]
    else:
        # Process all CSV files
        files = sorted([Path(p) for p in glob.glob(str(data_dir / '*.csv'))])
    
    if not files:
        print(f"No CSV files found in {data_dir}")
        return

    print(f"Found {len(files)} CSV files to process")
    
    total = 0
    for f in files:
        inserted = process_file(conn, f, args.batch_size, args.h3_resolution)
        print(f"Inserted {inserted:,} rows from {f.name}")
        total += inserted

    conn.close()
    print(f"Done. Total inserted: {total:,} rows")


if __name__ == '__main__':
    main()