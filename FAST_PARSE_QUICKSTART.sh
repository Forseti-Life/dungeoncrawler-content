#!/usr/bin/env bash

# Quick Start Guide: Fast Resume Parsing
# 
# tl;dr: Use ./testing/fast-parse.sh to parse resumes in 1-2 minutes instead of waiting 60+ minutes

echo "
╔════════════════════════════════════════════════════════════════════╗
║                 QUICK START: Fast Resume Parsing                   ║
╚════════════════════════════════════════════════════════════════════╝

📝 USAGE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Step 1: Upload a resume
    → Go to: http://localhost/jobhunter/profile/edit
    → Click 'Upload Resume'
    → Select a PDF/DOC/DOCX file
    → Click 'Save profile'

  Step 2: Fast parse (processes queue immediately)
    → cd /home/keithaumiller/forseti.life
    → ./testing/fast-parse.sh

  Step 3: Verify success
    → Check: http://localhost/jobhunter/profile/edit
    → Look for: 'Individual JSON Stored: Yes ✅'

📊 PERFORMANCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Background Queue (default):  60+ minutes
  Fast Parse (./testing/fast-parse.sh):  1-2 minutes  ⭐
  Playwright E2E test:  5-10 minutes

💡 KEY FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ✅ Same production code (no workarounds)
  ✅ Includes 2-pass GenAI parsing (core + experience)
  ✅ Automatic token limit recovery
  ✅ Full data consolidation
  ✅ Instant feedback for iteration

🔍 DEBUGGING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  View parsing logs:
    drush watchdog:show job_hunter --follow

  Check database results:
    drush sql:query 'SELECT * FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1'

  View GenAI responses:
    drush sql:query 'SELECT raw_genai_response_core, raw_genai_response_experience FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1'

📚 FULL DOCUMENTATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Quick Start:  FAST_PARSE_SOLUTION.md (this file's parent)
  Details:     testing/FAST_PARSE_README.md
  Testing:     testing/README.md

🚀 READY TO GO!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  cd /home/keithaumiller/forseti.life
  ./testing/fast-parse.sh
"
