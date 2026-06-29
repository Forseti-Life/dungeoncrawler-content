# Playwright Testing Utilities

Browser automation testing using Playwright for capturing console logs and validating page loads.

## Installation

Playwright is already installed as a dev dependency:

```bash
npm list playwright
```

## Scripts

### 1. capture-console.js

Captures all browser console output (logs, errors, warnings) from a URL.

**Usage:**
```bash
node testing/playwright/capture-console.js <url> [timeout] [output-file]
```

**Examples:**
```bash
# Test hexmap with 10 second wait
node testing/playwright/capture-console.js http://localhost:8080/hexmap 10000

# Save output to JSON file
node testing/playwright/capture-console.js http://localhost:8080/hexmap 10000 console-output.json

# Test with default 10 second timeout
node testing/playwright/capture-console.js http://localhost:8080/hexmap
```

**Output:**
- Real-time console output to stdout
- Optional JSON file with full details
- Error count summary

### 2. test-hexmap.js

Automated test for hexmap page console errors (useful for CI/CD).

**Usage:**
```bash
node testing/playwright/test-hexmap.js [base-url] [timeout]
```

**Examples:**
```bash
# Test with defaults
node testing/playwright/test-hexmap.js

# Test custom URL
node testing/playwright/test-hexmap.js http://staging.example.com:8080 5000

# CI/CD usage
node testing/playwright/test-hexmap.js || exit 1
```

**Exit Codes:**
- `0` = All tests passed, no errors
- `1` = Console errors detected

### 3. test-jobhunter-resume-tailoring.js

Automates the Job Hunter resume flow: upload resume, wait for parsing, tailor resume, generate PDF.

**Usage:**
```bash
node testing/playwright/test-jobhunter-resume-tailoring.js <base-url> [resume-path]
```

**Examples:**
```bash
# Use default resume path
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost:8080

# Explicit resume path
node testing/playwright/test-jobhunter-resume-tailoring.js http://localhost:8080 /mnt/chromeos/MyFiles/Downloads/KeithAumillerA.pdf
```

**Environment Variables:**
- `PLAYWRIGHT_USERNAME` / `PLAYWRIGHT_PASSWORD`
- `PLAYWRIGHT_RESUME_PATH`
- `JOBHUNTER_JOB_ID`
- `PLAYWRIGHT_HEADLESS` / `PLAYWRIGHT_SLOWMO`
- `PLAYWRIGHT_TIMEOUT_MS` / `PLAYWRIGHT_POLL_DELAY_MS` / `PLAYWRIGHT_MAX_POLLS`

### 4. test-jobhunter-profile-edit-status.js

Validates `/jobhunter/profile/edit` status indicators without uploading anything.

Checks:
- `Individual JSON Stored: Yes`
- `Merged to Consolidated: Yes`
- Education preview renders visible text (fails if HTML exists but `innerText` is empty)

**Usage:**
```bash
export ULI_URL="<one-time-login-url>"  # preferred
node testing/playwright/test-jobhunter-profile-edit-status.js http://127.0.0.1

# Or login with credentials
export PLAYWRIGHT_USERNAME="admin"
export PLAYWRIGHT_PASSWORD="<password>"
node testing/playwright/test-jobhunter-profile-edit-status.js http://127.0.0.1
```

## Quick Commands

### Test Hexmap for Console Errors
```bash
cd /home/keithaumiller/forseti.life
node testing/playwright/test-hexmap.js http://localhost:8080
```

### Authenticate via /user Login
Provide credentials to run tests without a reset URL:

```bash
export PLAYWRIGHT_USERNAME="playwright_player"
export PLAYWRIGHT_PASSWORD="<your_password>"
export PLAYWRIGHT_LOGIN_PATH="/user"

node testing/playwright/test-character-creation.js http://localhost:8080 10000
node testing/playwright/test-hexmap.js http://localhost:8080 5000

# Fast-mode happy path (headless, no slow-mo)
PLAYWRIGHT_HEADLESS=1 PLAYWRIGHT_SLOWMO=0 node testing/playwright/test-happy-path-simple.js http://localhost:8080
```

### Capture and Save Console Logs
```bash
node testing/playwright/capture-console.js http://localhost:8080/hexmap 10000 /tmp/hexmap-console.json
cat /tmp/hexmap-console.json
```

### Test All URLs
```bash
#!/bin/bash
urls=(
  "http://localhost:8080/hexmap"
  "http://localhost:8080/campaigns"
  "http://localhost:8080/characters"
)

for url in "${urls[@]}"; do
  echo "Testing: $url"
  node testing/playwright/test-hexmap.js "$url" 5000 || exit 1
done
```

## Captured Data

### Console Events
- **log**: Standard console.log messages
- **error**: console.error messages
- **warning**: console.warn messages
- **info**: console.info messages
- **debug**: console.debug messages

### Network Events
- Failed HTTP responses (400+)
- URL and status codes
- Useful for detecting API failures

### Page Errors
- Uncaught JavaScript exceptions
- Stack traces for debugging

## Integration with Instructions

See `.github/instructions/instructions.md` for how to use these tools in your development workflow.

## Troubleshooting

### Playwright Browsers Not Installed
```bash
npx playwright install
```

### Port Already in Use
If `localhost:8080` is not available:
```bash
node testing/playwright/test-hexmap.js http://localhost:3000
```

### Timeout Too Short
For pages with heavy JavaScript:
```bash
node testing/playwright/capture-console.js http://localhost:8080/hexmap 30000
```

## CI/CD Integration

Add to your deployment pipeline:

```yaml
# GitHub Actions example
- name: Test Hexmap Console
  run: |
    npm install --save-dev playwright
    node testing/playwright/test-hexmap.js http://localhost:8080 10000
```

## Performance Notes

- First run installs browser binaries (~200MB)
- Subsequent runs are fast (~1-2 seconds per page)
- Good for local testing before pushing changes
- Can be integrated into pre-commit hooks

## Advanced Usage

### Custom Script Template

```javascript
const { chromium } = require('playwright');

async function customTest() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  page.on('console', msg => console.log(msg.text()));
  
  await page.goto('http://localhost:8080');
  await page.waitForTimeout(5000);
  
  await browser.close();
}

customTest();
```

## More Resources

- [Playwright Documentation](https://playwright.dev/)
- [API Reference](https://playwright.dev/docs/api/class-page#page-event-console)
- [Best Practices](https://playwright.dev/docs/best-practices)
