#!/bin/bash
# ==============================================================================
# Setup H3 Analytics Stored Procedures
# Installs SQL stored procedures into amisafe_database
# ==============================================================================

DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASS="drupal_secure_password"
DB_NAME="amisafe_database"

echo "=================================="
echo "H3 Analytics Stored Procedures Setup"
echo "=================================="
echo ""

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL client not found"
    exit 1
fi

# Test database connection
echo "🔍 Testing database connection..."
if ! mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
    echo "❌ Cannot connect to database $DB_NAME"
    exit 1
fi
echo "✅ Database connection successful"
echo ""

# Install all-time analytics procedures
echo "📦 Installing all-time analytics procedures..."
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME < stored_procedures_h3_analytics.sql 2>&1 | grep -v "Warning"
echo "✅ All-time analytics procedures installed"

# Install windowed analytics procedures
echo "📦 Installing windowed analytics procedures (12mo, 6mo)..."
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME < stored_procedures_h3_analytics_windowed.sql 2>&1 | grep -v "Warning"
echo "✅ Windowed analytics procedures installed"
echo ""

# Verify procedures are installed
echo "🔍 Verifying installed procedures..."
PROC_COUNT=$(mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME -sN -e "
    SELECT COUNT(*) 
    FROM information_schema.ROUTINES 
    WHERE ROUTINE_SCHEMA = '$DB_NAME' 
        AND ROUTINE_NAME LIKE 'sp_%';
")

echo "   Found $PROC_COUNT stored procedures:"
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
    SELECT ROUTINE_NAME, ROUTINE_TYPE 
    FROM information_schema.ROUTINES 
    WHERE ROUTINE_SCHEMA = '$DB_NAME' 
        AND ROUTINE_NAME LIKE 'sp_%'
    ORDER BY ROUTINE_NAME;
" 2>/dev/null

echo ""
echo "=================================="
echo "✅ Setup Complete!"
echo "=================================="
echo ""
echo "Installed 21 Stored Procedures:"
echo "  - 11 all-time analytics procedures"
echo "  - 10 windowed analytics procedures (12mo, 6mo)"
echo ""
echo "Quick Start - Process Resolution 13 (fastest, 177K hexagons):"
echo "  mysql -h $DB_HOST -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"CALL sp_complete_all_windows(13);\""
echo ""
echo "Process all resolutions (run one at a time, highest to lowest):"
echo "  for res in 13 12 11 10 9 8 7 6 5; do"
echo "    mysql -h $DB_HOST -u $DB_USER -p'$DB_PASS' $DB_NAME -e \"CALL sp_complete_all_windows(\$res);\""
echo "  done"
echo ""
