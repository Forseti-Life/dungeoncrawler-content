#!/bin/bash
set -e  # Exit on any error

# ============================================================================
# AmISafe H3 Complete ETL Pipeline Orchestrator
# ============================================================================
# Pipeline Stages:
#   1. Bronze Layer:  Load CSV → amisafe_raw_incidents
#   2. Silver Layer:  Clean and transform → amisafe_clean_incidents  
#   3. Gold Layer:    Aggregate by H3 hexagon → amisafe_h3_aggregated
#   4. Analytics:     Calculate 84 analytical columns (all-time + windowed)
#
# Usage:
#   ./run_complete_pipeline.sh [OPTIONS]
#
# Options:
#   --full              Run complete pipeline (Bronze → Silver → Gold → Analytics)
#   --bronze            Run Bronze layer only (load raw CSV data)
#   --silver            Run Silver layer only (transform and clean)
#   --gold              Run Gold layer only (H3 aggregation)
#   --analytics         Run Analytics only (stored procedures)
#   --analytics-basic   Run basic all-time analytics only (no windowed)
#   --resume            Resume from last successful stage
#   --help              Show this help message
#
# Environment Variables:
#   DB_USER             MySQL username (default: stlouis_user)
#   DB_PASSWORD         MySQL password (default: StLouis2024!Secure#DB)
#   DB_HOST             MySQL host (default: 127.0.0.1)
#   DB_SOCKET           MySQL socket path (overrides DB_HOST if set)
#   DB_NAME             MySQL database (default: amisafe_database)
#   H3_RESOLUTIONS      Space-separated H3 resolutions (default: "13 12 11 10 9 8 7 6 5")
# ============================================================================

# Configuration
DB_USER="${DB_USER:-stlouis_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-amisafe_database}"
H3_RESOLUTIONS="${H3_RESOLUTIONS:-13 12 11 10 9 8 7 6 5}"

# Validate required environment variables
if [ -z "${DB_PASSWORD}" ]; then
    echo "ERROR: DB_PASSWORD environment variable is required." >&2
    echo "Set it from your secrets manager or environment:" >&2
    echo "  export DB_PASSWORD='<your_database_password>'" >&2
    exit 1
fi

if [ -z "${DB_PASSWORD}" ]; then
    echo "ERROR: DB_PASSWORD must be set in the environment." >&2
    exit 1
fi

# Auto-detect MySQL socket if skip-grant-tables is enabled
if [ -z "${DB_SOCKET}" ]; then
    if echo "SELECT 1;" | mysql ${DB_NAME} -sN 2>/dev/null | grep -q "1"; then
        # Skip-grant-tables mode detected, find the socket
        DB_SOCKET=$(mysql -e "SHOW VARIABLES LIKE 'socket';" -sN 2>/dev/null | awk '{print $2}')
        if [ -z "${DB_SOCKET}" ]; then
            DB_SOCKET="/var/run/mysqld/mysqld.sock"  # Default fallback
        fi
    fi
fi

# Base directory for the H3 project
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ETL_DIR="${BASE_DIR}/database/etl"
DATA_DIR="${BASE_DIR}/data/raw"
STATE_FILE="${BASE_DIR}/database/pipeline_state.json"
LOG_FILE="${BASE_DIR}/database/pipeline_$(date +%Y%m%d_%H%M%S).log"

# ============================================================================
# Functions
# ============================================================================

show_help() {
    head -n 35 "$0" | grep "^#" | sed 's/^# //' | sed 's/^#//'
    exit 0
}

log_info() {
    local msg="$1"
    echo "" | tee -a "${LOG_FILE}"
    echo "=====================================" | tee -a "${LOG_FILE}"
    echo "$msg" | tee -a "${LOG_FILE}"
    echo "=====================================" | tee -a "${LOG_FILE}"
}

log_step() {
    local msg="$1"
    echo "$(date '+%Y-%m-%d %H:%M:%S') | $msg" | tee -a "${LOG_FILE}"
}

save_state() {
    local stage="$1"
    local status="$2"
    local timestamp="$(date -Iseconds)"
    
    cat > "${STATE_FILE}" <<EOF
{
    "stage": "${stage}",
    "status": "${status}",
    "timestamp": "${timestamp}",
    "last_successful": "${stage}"
}
EOF
    log_step "State saved: ${stage} - ${status}"
}

load_state() {
    if [ -f "${STATE_FILE}" ]; then
        cat "${STATE_FILE}"
    else
        echo '{"stage": "none", "status": "pending", "last_successful": "none"}'
    fi
}

get_record_count() {
    local table="$1"
    local mysql_cmd=""
    
    # Try connection without auth first (for --skip-grant-tables mode)
    if echo "SELECT 1;" | mysql ${DB_NAME} -sN 2>/dev/null | grep -q "1"; then
        mysql_cmd="mysql ${DB_NAME} -sN"
    elif [ -n "${DB_SOCKET}" ]; then
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -S ${DB_SOCKET} ${DB_NAME} -sN"
    else
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -h ${DB_HOST} ${DB_NAME} -sN"
    fi
    
    echo "SELECT COUNT(*) FROM ${table};" | ${mysql_cmd} 2>/dev/null || echo "0"
}

run_bronze() {
    log_info "STAGE 1/4: BRONZE LAYER - Loading Raw CSV Data"
    log_step "Target: amisafe_raw_incidents (~3.5M records from 19 years)"
    log_step "Data Directory: ${DATA_DIR}"
    
    local csv_count=$(find "${DATA_DIR}" -name "*.csv" 2>/dev/null | wc -l)
    log_step "Found ${csv_count} CSV files to process"
    
    if [ "${csv_count}" -eq 0 ]; then
        log_step "❌ No CSV files found in ${DATA_DIR}"
        return 1
    fi
    
    local cmd_args=(
        --mysql-user "${DB_USER}"
        --mysql-password "${DB_PASSWORD}"
        --mysql-database "${DB_NAME}"
        --data-dir "${DATA_DIR}"
    )
    
    if [ -n "${DB_SOCKET}" ]; then
        cmd_args+=(--mysql-socket "${DB_SOCKET}")
    else
        cmd_args+=(--mysql-host "${DB_HOST}")
    fi
    
    log_step "Starting Bronze layer ETL..."
    python3 "${ETL_DIR}/amisafe_processor.py" "${cmd_args[@]}" 2>&1 | tee -a "${LOG_FILE}"
    
    if [ $? -eq 0 ]; then
        local count=$(get_record_count "amisafe_raw_incidents")
        log_step "✅ Bronze layer completed: ${count} records loaded"
        save_state "bronze" "completed"
        return 0
    else
        log_step "❌ Bronze layer failed"
        save_state "bronze" "failed"
        return 1
    fi
}

run_silver() {
    log_info "STAGE 2/4: SILVER LAYER - Transform & Clean Data"
    log_step "Target: amisafe_clean_incidents (deduplicate, validate, standardize)"
    
    local raw_count=$(get_record_count "amisafe_raw_incidents")
    log_step "Raw incidents to process: ${raw_count}"
    
    local cmd_args=(
        --mysql-user "${DB_USER}"
        --mysql-password "${DB_PASSWORD}"
        --mysql-database "${DB_NAME}"
    )
    
    if [ -n "${DB_SOCKET}" ]; then
        cmd_args+=(--mysql-socket "${DB_SOCKET}")
    else
        cmd_args+=(--mysql-host "${DB_HOST}")
    fi
    
    log_step "Starting Silver layer ETL..."
    python3 "${ETL_DIR}/enhanced_transform_processor_v2.py" "${cmd_args[@]}" 2>&1 | tee -a "${LOG_FILE}"
    
    if [ $? -eq 0 ]; then
        local count=$(get_record_count "amisafe_clean_incidents")
        log_step "✅ Silver layer completed: ${count} clean records"
        save_state "silver" "completed"
        return 0
    else
        log_step "❌ Silver layer failed"
        save_state "silver" "failed"
        return 1
    fi
}

run_gold() {
    log_info "STAGE 3/4: GOLD LAYER - H3 Hexagon Aggregation"
    log_step "Target: amisafe_h3_aggregated (resolutions: ${H3_RESOLUTIONS})"
    
    local clean_count=$(get_record_count "amisafe_clean_incidents")
    log_step "Clean incidents to aggregate: ${clean_count}"
    
    local cmd_args=(
        --mysql-user "${DB_USER}"
        --mysql-password "${DB_PASSWORD}"
        --mysql-database "${DB_NAME}"
        --resolutions ${H3_RESOLUTIONS}
    )
    
    if [ -n "${DB_SOCKET}" ]; then
        cmd_args+=(--mysql-socket "${DB_SOCKET}")
    else
        cmd_args+=(--mysql-host "${DB_HOST}")
    fi
    
    log_step "Starting Gold layer ETL..."
    python3 "${ETL_DIR}/amisafe_aggregator.py" "${cmd_args[@]}" 2>&1 | tee -a "${LOG_FILE}"
    
    if [ $? -eq 0 ]; then
        local count=$(get_record_count "amisafe_h3_aggregated")
        log_step "✅ Gold layer completed: ${count} hexagon aggregations"
        save_state "gold" "completed"
        return 0
    else
        log_step "❌ Gold layer failed"
        save_state "gold" "failed"
        return 1
    fi
}

run_analytics_basic() {
    log_info "STAGE 4/4: ANALYTICS - All-Time Metrics Only"
    log_step "Calculating basic, statistical, and risk metrics (all-time)"
    log_step "Resolutions to process: ${H3_RESOLUTIONS}"
    
    local mysql_cmd=""
    # Try connection without auth first (for --skip-grant-tables mode)
    if echo "SELECT 1;" | mysql ${DB_NAME} -sN 2>/dev/null | grep -q "1"; then
        mysql_cmd="mysql ${DB_NAME}"
        log_step "Using direct socket connection (no auth required)"
    elif [ -n "${DB_SOCKET}" ]; then
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -S ${DB_SOCKET} ${DB_NAME}"
    else
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -h ${DB_HOST} ${DB_NAME}"
    fi
    
    # Run all-time analytics for each resolution
    for resolution in ${H3_RESOLUTIONS}; do
        log_step "Processing resolution ${resolution} (all-time analytics)..."
        
        # Get hexagon count for this resolution
        local hex_count=$(echo "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = ${resolution};" | ${mysql_cmd} -sN)
        log_step "  → ${hex_count} hexagons at resolution ${resolution}"
        
        # Run the stored procedure
        echo "CALL sp_complete_resolution_analytics(${resolution});" | ${mysql_cmd} 2>&1 | tee -a "${LOG_FILE}"
        
        if [ $? -ne 0 ]; then
            log_step "❌ Analytics failed at resolution ${resolution}"
            save_state "analytics" "failed"
            return 1
        fi
        
        log_step "  ✅ Resolution ${resolution} completed"
    done
    
    log_step "✅ All-time analytics completed for all resolutions"
    save_state "analytics" "completed"
    return 0
}

run_analytics_full() {
    log_info "STAGE 4/4: ANALYTICS - Complete (All-Time + 12mo + 6mo Windows)"
    log_step "Calculating 84 analytical columns per hexagon"
    log_step "Resolutions to process: ${H3_RESOLUTIONS}"
    
    local mysql_cmd=""
    # Try connection without auth first (for --skip-grant-tables mode)
    if echo "SELECT 1;" | mysql ${DB_NAME} -sN 2>/dev/null | grep -q "1"; then
        mysql_cmd="mysql ${DB_NAME}"
        log_step "Using direct socket connection (no auth required)"
    elif [ -n "${DB_SOCKET}" ]; then
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -S ${DB_SOCKET} ${DB_NAME}"
    else
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -h ${DB_HOST} ${DB_NAME}"
    fi
    
    # Run complete analytics (all-time + windowed) for each resolution
    for resolution in ${H3_RESOLUTIONS}; do
        log_step "Processing resolution ${resolution} (all windows)..."
        
        # Get hexagon count for this resolution
        local hex_count=$(echo "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = ${resolution};" | ${mysql_cmd} -sN)
        log_step "  → ${hex_count} hexagons at resolution ${resolution}"
        log_step "  → Estimated time: ~$((hex_count / 100)) minutes (100 hex/min)"
        
        # Run the stored procedure
        echo "CALL sp_complete_all_windows(${resolution});" | ${mysql_cmd} 2>&1 | tee -a "${LOG_FILE}"
        
        if [ $? -ne 0 ]; then
            log_step "❌ Analytics failed at resolution ${resolution}"
            save_state "analytics" "failed"
            return 1
        fi
        
        log_step "  ✅ Resolution ${resolution} completed"
    done
    
    log_step "✅ Complete analytics finished for all resolutions"
    save_state "analytics" "completed"
    return 0
}

show_summary() {
    log_info "PIPELINE SUMMARY"
    
    local mysql_cmd=""
    # Try connection without auth first (for --skip-grant-tables mode)
    if echo "SELECT 1;" | mysql ${DB_NAME} -sN 2>/dev/null | grep -q "1"; then
        mysql_cmd="mysql ${DB_NAME} -t"
    elif [ -n "${DB_SOCKET}" ]; then
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -S ${DB_SOCKET} ${DB_NAME} -t"
    else
        mysql_cmd="mysql -u ${DB_USER} -p'${DB_PASSWORD}' -h ${DB_HOST} ${DB_NAME} -t"
    fi
    
    log_step "Database: ${DB_NAME}"
    echo "" | tee -a "${LOG_FILE}"
    
    echo "Data Layer Statistics:" | tee -a "${LOG_FILE}"
    echo "SELECT 
        'amisafe_raw_incidents' as Table_Name,
        COUNT(*) as Record_Count
    FROM amisafe_raw_incidents
    UNION ALL
    SELECT 
        'amisafe_clean_incidents',
        COUNT(*)
    FROM amisafe_clean_incidents
    UNION ALL
    SELECT
        'amisafe_h3_aggregated',
        COUNT(*)
    FROM amisafe_h3_aggregated;" | ${mysql_cmd} | tee -a "${LOG_FILE}"
    
    echo "" | tee -a "${LOG_FILE}"
    echo "H3 Hexagon Distribution by Resolution:" | tee -a "${LOG_FILE}"
    echo "SELECT 
        h3_resolution,
        COUNT(*) as hexagon_count,
        SUM(incident_count) as total_incidents,
        ROUND(AVG(incident_count), 1) as avg_incidents_per_hex,
        COUNT(CASE WHEN top_crime_type IS NOT NULL THEN 1 END) as with_analytics
    FROM amisafe_h3_aggregated
    GROUP BY h3_resolution
    ORDER BY h3_resolution DESC;" | ${mysql_cmd} | tee -a "${LOG_FILE}"
    
    echo "" | tee -a "${LOG_FILE}"
    log_step "Pipeline state saved to: ${STATE_FILE}"
    log_step "Complete log saved to: ${LOG_FILE}"
}

# ============================================================================
# Main Pipeline Execution
# ============================================================================

main() {
    log_info "AmISafe H3 Complete ETL Pipeline"
    log_step "Base Directory: ${BASE_DIR}"
    log_step "ETL Scripts: ${ETL_DIR}"
    log_step "Data Directory: ${DATA_DIR}"
    log_step "Database: ${DB_NAME}"
    
    if [ -n "${DB_SOCKET}" ]; then
        log_step "MySQL Socket: ${DB_SOCKET}"
    else
        log_step "MySQL Host: ${DB_HOST}"
    fi
    
    log_step "H3 Resolutions: ${H3_RESOLUTIONS}"
    log_step "Log File: ${LOG_FILE}"
    
    # Activate virtual environment if it exists
    if [ -d "${BASE_DIR}/h3-env" ]; then
        log_step "🐍 Activating virtual environment..."
        source "${BASE_DIR}/h3-env/bin/activate"
    else
        echo ""
        echo "⚠️  WARNING: Virtual environment not found at ${BASE_DIR}/h3-env"
        echo "    Create it with:"
        echo "    cd ${BASE_DIR}"
        echo "    python3 -m venv h3-env"
        echo "    source h3-env/bin/activate"
        echo "    pip install pandas numpy h3 mysql-connector-python folium matplotlib plotly seaborn geopy tqdm psutil"
        exit 1
    fi
    
    # Set PYTHONPATH to include h3-geolocation directory
    export PYTHONPATH="${BASE_DIR}:${PYTHONPATH}"
    log_step "PYTHONPATH set to: ${PYTHONPATH}"
    
    # Parse command line arguments
    case "${1:-}" in
        --help|-h)
            show_help
            ;;
        --bronze)
            run_bronze && show_summary
            ;;
        --silver)
            run_silver && show_summary
            ;;
        --gold)
            run_gold && show_summary
            ;;
        --analytics)
            run_analytics_full && show_summary
            ;;
        --analytics-basic)
            run_analytics_basic && show_summary
            ;;
        --resume)
            local state=$(load_state)
            local last_stage=$(echo "$state" | grep -o '"last_successful": "[^"]*"' | cut -d'"' -f4)
            log_step "Resuming from last successful stage: ${last_stage}"
            
            case "${last_stage}" in
                none)
                    run_bronze && run_silver && run_gold && run_analytics_full && show_summary
                    ;;
                bronze)
                    run_silver && run_gold && run_analytics_full && show_summary
                    ;;
                silver)
                    run_gold && run_analytics_full && show_summary
                    ;;
                gold)
                    run_analytics_full && show_summary
                    ;;
                *)
                    log_step "All stages already completed. Use --full to re-run."
                    show_summary
                    ;;
            esac
            ;;
        --full|"")
            # Default: Run complete pipeline
            log_step "Starting complete pipeline execution..."
            run_bronze || exit 1
            run_silver || exit 1
            run_gold || exit 1
            run_analytics_full || exit 1
            show_summary
            ;;
        *)
            echo "Unknown option: $1"
            show_help
            ;;
    esac
    
    log_info "✅ Pipeline Execution Completed"
}

main "$@"
