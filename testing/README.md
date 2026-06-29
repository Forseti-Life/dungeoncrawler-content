# Job Hunter Testing Guide

## Quick Links

- 🚀 **Fast Resume Parsing**: [testing/FAST_PARSE_README.md](./FAST_PARSE_README.md)
- 🎭 **End-to-End Testing**: [test-jobhunter-resume-tailoring.js](./playwright/test-jobhunter-resume-tailoring.js)
- 📊 **Resume JSON Schema**: [../sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md](../sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md)

## Testing Workflows

### 1. **Fast Resume Parsing (Recommended for Development)**

**Purpose:** Test resume parsing without queue delays

```bash
# Step 1: Upload resume via web UI
# Navigate to: http://localhost/jobhunter/profile/edit
# Click "Upload Resume" and select a file

# Step 2: Execute fast parser (processes queue immediately)
./testing/fast-parse.sh

# Total time: ~1-2 minutes
```

**What happens:**
- Executes `drush queue:run job_hunter_genai_parsing`
- Runs ResumeGenAiParsingWorker for all pending jobs
- Consolidates parsed data
- Displays results

See: [testing/FAST_PARSE_README.md](./FAST_PARSE_README.md)

---

### 2. **Full End-to-End Workflow Testing (Playwright)**

**Purpose:** Test complete user journey: Upload → Parse → Tailor → PDF

```bash
# Full workflow test
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'

# With specific job
JOBHUNTER_JOB_ID=123 node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'

# Total time: ~5-10 minutes
```

**What happens:**
1. Logs into site (admin/admin_secure_password)
2. Uploads resume file
3. **Polls for parsing completion** (max 30 polls × 10s = 5 minutes)
4. Selects a job to tailor for
5. Starts resume tailoring
6. **Polls for tailoring completion** (max 30 polls × 10s = 5 minutes)
7. Generates PDF 
8. Verifies PDF download available

See: [test-jobhunter-resume-tailoring.js](./playwright/test-jobhunter-resume-tailoring.js)

---

### 3. **Manual Queue Execution (Alternative)**

**Purpose:** Process queue via Drush manually

```bash
cd /home/keithaumiller/forseti.life/sites/forseti

# Process all pending resume parsing jobs
vendor/bin/drush queue:run job_hunter_genai_parsing

# Process continuous for 1 hour
vendor/bin/drush queue:work job_hunter_genai_parsing --time-limit=3600

# Check queue status
vendor/bin/drush queue:list
```

---

## Test Scenarios

### Test 1: Simple Resume Upload & Parse

```bash
# 1. Upload resume (web UI)
#    File: Any PDF/DOC/DOCX under 50KB

# 2. Fast parse
./testing/fast-parse.sh

# 3. Verify parsing succeeded
vendor/bin/drush sql:query "
  SELECT id, status, error_message 
  FROM jobhunter_resume_parsed_data 
  ORDER BY changed DESC LIMIT 1"
```

**Expected Result:**
- Status: "complete"
- error_message: NULL
- parsed_data: Contains contact_info, professional_experience, etc.

### Test 2: Large Document & Token Limit Recovery

```bash
# Upload a 50+ KB resume (tests adaptive chunk splitting)
# Test file: /mnt/chromeos/MyFiles/Downloads/KeithAumillerA.pdf

./testing/fast-parse.sh

# Check if token limit recovery was triggered
vendor/bin/drush watchdog:show job_hunter | grep -i "token\|split\|fallback"
```

**Expected Result:**
- Parsing succeeds despite large size
- Log shows: "fallback splitting" or "token limit" recovery
- raw_genai_response_experience contains multiple chunk responses

### Test 3: Full Resume Tailoring Workflow

```bash
# Run complete end-to-end test
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'

# Or with specific job selection
JOBHUNTER_JOB_ID=1 node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'
```

**Expected Result:**
- Login succeeds
- Resume uploads
- Parser completes (shows JSON stored status)
- Job selected for tailoring
- Tailoring completes
- PDF generated successfully

---

## Configuration

### Environment Variables (Playwright Tests)

```bash
# Override base URL (default: http://localhost:7777)
BASE_URL=http://localhost:8080 node testing/playwright/...

# Override user credentials
JOBHUNTER_LOGIN=testuser node testing/playwright/...
JOBHUNTER_PASSWORD=testpass node testing/playwright/...

# Specify job to tailor for (default: auto-select)
JOBHUNTER_JOB_ID=123 node testing/playwright/...

# Override polling behavior
JOBHUNTER_POLL_DELAY=2000              # 2 seconds
JOBHUNTER_MAX_POLL_COUNT=60            # Allow 2 minutes
JOBHUNTER_POLL_TIMEOUT=10000           # 10 second per poll

# Chrome-specific options
JOBHUNTER_HEADLESS=false               # Show browser (slower)
JOBHUNTER_SLOW_MO=1000                 # Slow down actions by 1s
```

### Example: Custom Configuration

```bash
# Use custom URL, user, job_id, with verbose output
JOBHUNTER_LOGIN=admin \
JOBHUNTER_PASSWORD=admin_secure_password \
JOBHUNTER_JOB_ID=1 \
JOBHUNTER_POLL_DELAY=3000 \
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost:7777 '/path/to/resume.pdf'
```

---

## Troubleshooting

### Parsing Fails During Playwright Test

```bash
# Monitor logs during test
tailf /var/log/apache2/forseti_error.log

# Or in another terminal:
drush watchdog:show job_hunter --follow

# Check queue status
drush queue:list
```

### Resume Shows "Pending" After 5 Minutes

```bash
# Check if parsing actually finished
drush sql:query "SELECT status FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1"

# If status is 'processing' or 'error':
drush watchdog:show job_hunter --limit=20

# Check for GenAI errors  
drush sql:query "SELECT error_message FROM jobhunter_resume_parsed_data WHERE status='error' LIMIT 1"
```

### File Upload Fails

```bash
# Verify file permissions
ls -la /home/keithaumiller/forseti.life/sites/forseti/web/sites/default/files

# Check file upload settings
drush config:get file.settings

# Check database constraints
drush sql:query "SELECT * FROM file_managed WHERE filename LIKE '%resume%'"
```

---

## Performance Benchmarks

| Component | Time | Method |
|-----------|------|--------|
| Text extraction | 2-5 sec | pdftotext/docx2txt |
| GenAI core profile | 20-30 sec | AWS Bedrock API |
| GenAI prof. experience | 30-40 sec | AWS Bedrock API (with retry) |
| Consolidation | 1-2 sec | PHP/Database |
| **Full Parse** | **~1-2 min** | fast-parse.sh |
| **Full Workflow** | **~5-10 min** | Playwright test (parsing+tailoring) |

Compare to queue (cron):
- Background queue processing: 60+ minutes
- Manual `drush queue:run`: ~1-2 min (same as fast-parse)

---

## Best Practices

### For Development & Testing

1. ✅ Use **fast-parse.sh** for iteration
   - Quick feedback (1-2 minutes)
   - Same production code path
   - Perfect for debugging

2. ✅ Use **Playwright tests** for full workflows
   - Validates entire user journey
   - Automated regression testing
   - CI/CD integration ready

3. ✅ Monitor **Drupal logs** during testing
   ```bash
   drush watchdog:show job_hunter --follow
   ```

### For Production

- Queue processing runs automatically via cron
- Manual execution: `drush queue:run job_hunter_genai_parsing`
- Monitor via: `drush queue:list` and `drush watchdog:show`

---

## Related Documentation

- **Resume Parser Code**: [../sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeGenAiParsingWorker.php](../sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeGenAiParsingWorker.php)
- **JSON Schema**: [../sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md](../sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md)
- **Module README**: [../sites/forseti/web/modules/custom/job_hunter/README.md](../sites/forseti/web/modules/custom/job_hunter/README.md)
- **Fast-Parse Details**: [FAST_PARSE_README.md](./FAST_PARSE_README.md)

---

## Support

**Quick checks:**
```bash
# See latest parsed resume
drush sql:query "SELECT * FROM jobhunter_resume_parsed_data ORDER BY changed DESC LIMIT 1\G"

# View parsing errors
drush sql:query "SELECT * FROM jobhunter_resume_parsed_data WHERE status='error' LIMIT 1\G"

# Check job hunter module logs
drush watchdog:show job_hunter --limit=20

# Verify GenAI service is working
drush eval '\Drupal::service("ai_conversation.ai_api_service")->invokeModelDirect(...)'
```

---

## Quick Command Reference

```bash
# Fast path (recommended)
./testing/fast-parse.sh

# Manual queue processing
drush queue:run job_hunter_genai_parsing

# View logs
drush watchdog:show job_hunter

# Database queries
drush sql:query "SELECT ..."

# End-to-end test
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost '/path/to/resume.pdf'
```
