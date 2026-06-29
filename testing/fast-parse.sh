#!/usr/bin/env bash

# Job Hunter Resume Fast Parser
# Executes pending resume parsing queue jobs immediately

set -e

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../" && pwd)"
DRUPAL_ROOT="$BASE_DIR/sites/forseti"

echo ""
echo "=== 🚀 Job Hunter Resume Fast Parser ===" 
echo "    Process pending queue jobs immediately"
echo ""

# Change to Drupal root
cd "$DRUPAL_ROOT"

# Step 1: Check pending jobs
echo "🔄 Step 1: Checking pending resume parsing jobs..."
PENDING=$(vendor/drush/drush/drush sql:query "
  SELECT COUNT(*) 
  FROM queue
  WHERE name = 'job_hunter_genai_parsing'
" 2>&1)

if [ -z "$PENDING" ] || [ "$PENDING" = "0" ]; then
  echo "⚠️  No pending parsing jobs found"
  echo ""
  echo "💡 To test parsing:"
  echo "   1. Upload a resume: http://localhost/jobhunter/profile/edit"
  echo "   2. Click 'Upload Resume' and select a PDF/DOC/DOCX file"
  echo "   3. This creates a parsing job automatically"
  echo "   4. Then run this script again to process it"
  echo ""
  exit 0
fi

echo "✅ Found $PENDING pending parsing job(s)"
echo ""

# Step 2: Process the queue immediately
echo "🔄 Step 2: Processing queue jobs..."
vendor/drush/drush/drush queue:run job_hunter_genai_parsing 2>&1

# Step 3: Verify results  
echo ""
echo "🔄 Step 3: Verifying results..."
COMPLETED=$(vendor/drush/drush/drush sql:query "
  SELECT COUNT(*)
  FROM jobhunter_resume_parsed_data
  WHERE status = 'complete'
  ORDER BY changed DESC
  LIMIT 1
" 2>&1)

if [ -n "$COMPLETED" ] && [ "$COMPLETED" != "0" ]; then
  echo "✅ Parsing successful!"
  echo ""
  echo "📊 Latest parsed result:"
  vendor/drush/drush/drush sql:query "
    SELECT 
      rpd.id,
      rpd.status,
      rpd.changed,
      u.name,
      json_extract(rpd.parsed_data, '$.contact_info.fullName') as fullName,
      json_extract(rpd.parsed_data, '$.professional_experience') as jobs_count
    FROM jobhunter_resume_parsed_data rpd
    JOIN users_field_data u ON rpd.uid = u.uid
    WHERE rpd.status = 'complete'
    ORDER BY rpd.changed DESC
    LIMIT 1
  " 2>&1
else
  echo "❌ Parsing may have failed - check Drupal logs"
  echo ""
  echo "💡 View logs with:"
  echo "   drush watchdog:show job_hunter"
  exit 1
fi

echo ""
echo "✅ Fast parsing complete!"
echo ""
echo "Next steps:"
echo "  1. Check profile: http://localhost/jobhunter/profile/edit"
echo "  2. Verify 'Individual JSON Stored: Yes'"
echo "  3. View jobs: http://localhost/jobhunter/my-jobs"
echo "  4. Proceed to tailoring workflow"
echo ""

