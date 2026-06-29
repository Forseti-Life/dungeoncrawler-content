# Job Hunter Resume Fast Parse

**Skip the queue! Parse resumes instantly for testing and development.**

## Overview

The fast-parse script allows you to process resume parsing jobs **immediately** without waiting for background queue processing. Instead of:
- Wait for cron to run (default 1 hour)
- Wait for manual queue execution  
- Poll status pages repeatedly

You can instantly execute the entire parsing pipeline directly.

## Quick Start

```bash
# Navigate to project root
cd /home/keithaumiller/forseti.life

# Run fast parser (processes all pending resume parsing jobs)
./testing/fast-parse.sh
```

## How It Works

### Standard Queue Approach (Slow)
```
Upload Resume
    ↓
Creates job in queue
    ↓
Wait for cron/drush queue:run
    ↓
GenAI Parsing (2-pass) 
    ↓
Data Consolidation
    ↓
Status: "Individual JSON Stored: Yes"
    ↓
Can proceed to tailoring
```

### Fast Parse Approach (Instant)
```
Upload Resume
    ↓
Creates job in queue
    ↓
./testing/fast-parse.sh
    ↓
Immediately executes: drush queue:run job_hunter_genai_parsing
    ↓
GenAI Parsing (2-pass) completes in <2 minutes
    ↓
Data Consolidation
    ↓
Status: "Individual JSON Stored: Yes"
    ↓
Can proceed to tailoring
```

## Usage

### Basic Usage
```bash
# Run all pending parsing jobs
./testing/fast-parse.sh

# Output:
# 🔄 Step 1: Checking pending resume parsing jobs...
# ✅ Found 1 pending parsing job(s)
# 🔄 Step 2: Processing queue jobs...
# [parsing output...]
# ✅ Parsing successful!
```

### With Results Verification
The script automatically:
1. ✅ Checks for pending queue items
2. ✅ Executes `drush queue:run job_hunter_genai_parsing`
3. ✅ Verifies results in database
4. ✅ Displays the latest parsed resume data

## Testing Workflow

### Test Resume Tailoring (Complete Flow)

```bash
# 1. Upload resume via UI
#    Navigate to: http://localhost/jobhunter/profile/edit
#    Click "Upload Resume" and select a PDF/DOC/DOCX file

# 2. Process the parsing job immediately
./testing/fast-parse.sh

```

# 3. Verify parsing success
#    Check: http://localhost/jobhunter/profile/edit
#    Should show: "Individual JSON Stored: Yes"

# 4. Run automated Playwright tests
cd /home/keithaumiller/forseti.life
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'
```

## Performance

| Step | Time | Tool |
|------|------|------|
| Resume text extraction | ~2-5s | pdftotext/docx2txt |
| GenAI parsing (core profile) | ~20-30s | AWS Bedrock |
| GenAI parsing (prof. experience) | ~30-40s | AWS Bedrock | 
| Data consolidation | ~1-2s | PHP/Database |
| **Total** | **~1-2 minutes** | **fast-parse.sh** |

Compare to queue approach: **Wait for cron (60+ minutes)**

## Architecture

### Resume Parsing Pipeline (Implemented)

The fast-parse uses the same **production-ready** parsing pipeline as the queue:

```
ResumeGenAiParsingWorker::parseResumeProdMode()
├── parseCoreProfileFromChunks()
│   ├── Split resume into 8KB chunks
│   ├── Extract core profile (repeats until contact_info found)
│   │   ├── Contact information
│   ├── Summary
│   ├── Education
│   └── Skills
│
└── parseProfessionalExperienceChunks()
    ├── Split resume into 6KB chunks
    ├── Extract job experiences
    ├── Adaptive retry with token limit recovery
    │   └── If token limit hit: split chunk in half, retry (max depth 2)
    └── Deduplicate experiences
```

### Queue Execution (Used by fast-parse)

```bash
drush queue:run job_hunter_genai_parsing
```

Processes each queue item:
1. Loads file + user
2. Extracts text
3. Calls `ResumeGenAiParsingWorker::processItem()`  
4. Stores JSON in `jobhunter_resume_parsed_data`
5. Consolidates all user resumes

## Database Queries

### Check Pending Jobs
```sql
SELECT COUNT(*) FROM queue WHERE name = 'job_hunter_genai_parsing';
```

### View Latest Parsing Results
```sql
SELECT 
  id,
  status,
  changed,
  JSON_EXTRACT(parsed_data, '$.contact_info.fullName') as fullName
FROM jobhunter_resume_parsed_data
ORDER BY changed DESC
LIMIT 1;
```

### View All Completed Parses
```sql
SELECT 
  rpd.id,
  u.name,
  rpd.status,
  rpd.changed,
  LENGTH(rpd.parsed_data) as data_size
FROM jobhunter_resume_parsed_data rpd
JOIN users_field_data u ON rpd.uid = u.uid
WHERE rpd.status = 'complete'
ORDER BY rpd.changed DESC;
```

## Troubleshooting

### No pending jobs found
```
💡 To create a job:
   1. Navigate to: http://localhost/jobhunter/profile/edit
   2. Click "Upload Resume"
   3. Select a PDF/DOC/DOCX file
   4. Click "Save profile"
   5. The upload triggers: job_hunter_genai_parsing queue item creation
```

### Parsing fails with error
```bash
# View detailed logs
drush watchdog:show job_hunter --limit=20

# Or check database error message
SELECT id, status, error_message FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1;
```

### GenAI timeout errors
Check AWS Bedrock API:
```bash
# View raw GenAI responses
SELECT 
  raw_genai_response_core,
  raw_genai_response_experience
FROM jobhunter_resume_parsed_data
ORDER BY changed DESC LIMIT 1;
```

## Production Use  

The fast-parse uses the exact same code path as production:
- ✅ Production: `ResumeGenAiParsingWorker::processItem()` via `drush queue:run`
- ✅ Fast-parse: `./testing/fast-parse.sh` → `drush queue:run` → same worker

**No code differences.** Fast-parse just skips the background queue wait.

## Related Scripts

| Script | Purpose | Time |
|--------|---------|------|
| `testing/fast-parse.sh` | Execute resume parsing queue jobs immediately | ~1-2 min |
| `testing/playwright/test-jobhunter-resume-tailoring.js` | Full workflow testing (parse → tailor → PDF) | ~5-10 min |
| `sites/forseti/vendor/bin/drush queue:run job_hunter_genai_parsing` | Manual queue execution (slower) | ~1-2 min |
| `sites/forseti/vendor/bin/drush queue:work job_hunter_genai_parsing --time-limit=3600` | Process queue continuously for 1 hour | Variable |

## Implementation Details

### Where is the Parsing Logic?

**Core Parsing Worker:**
```
sites/forseti/web/modules/custom/job_hunter/
└── src/Plugin/QueueWorker/
    └── ResumeGenAiParsingWorker.php  (708 lines, all parsing logic)
        ├── processItem() - Entry point
        ├── parseResumeProdMode() - 2-pass approach
        ├── parseCoreProfileFromChunks() - Core data extraction
        ├── parseProfessionalExperienceChunks() - Job history extraction
        ├── parseExperienceChunkWithRetries() - Token limit recovery
        └── [6 more helper methods]
```

**Fast-Parse Invocation:**
```bash
./testing/fast-parse.sh
  └── vendor/drush/drush/drush queue:run job_hunter_genai_parsing
      └── Drupal QueueWorkerPluginInterface::processItem()
          └── ResumeGenAiParsingWorker::processItem()
              └── ResumeGenAiParsingWorker::parseResumeProdMode()
```

## Examples

### Test 1: Single Resume Parse
```bash
# 1. Upload resume via web UI
# 2. Run fast parser
./testing/fast-parse.sh

# 3. Verify
drush watchdog:show job_hunter --limit=5
```

### Test 2: Full Workflow (Parse + Tailor + PDF)
```bash
# 1. Upload resume
# 2. Fast parse (handles parsing + consolidation)
./testing/fast-parse.sh

# 3. Automated end-to-end test
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'
```

### Test 3: Large Document Handling
```bash
# Upload a 50+ KB resume (triggers adaptive chunk splitting)
./testing/fast-parse.sh

# Monitor token limit recovery in logs
drush watchdog:show job_hunter | grep "token limit\|fallback\|splitting"
```

## Key Features

✅ **Instant Feedback** - See parsing results in <2 minutes  
✅ **No Config Changes** - Uses production code exactly as-is  
✅ **Full Integration** - Includes consolidation, status updates, error handling  
✅ **Debuggable** - All logs available via `drush watchdog:show`  
✅ **Testable** - Perfect for Playwright automated testing  
✅ **Safe** - Read-only preflight checks, clear error messages  

## Next Steps

1. **Test Parsing:** Run `./testing/fast-parse.sh` after uploading a resume
2. **Verify Data:** Check profile page shows "Individual JSON Stored: Yes"
3. **Test Tailoring:** Use Playwright script to test full workflow
4. **Monitor Production:** Queue processing still works via cron or manual execution

## Support

For issues:
- Check logs: `drush watchdog:show job_hunter`
- View results: `drush sql:query "SELECT * FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1"`
- Debug parsing: See `raw_genai_response_core` and `raw_genai_response_experience` columns
