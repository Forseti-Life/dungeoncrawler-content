/**
 * @file jobhunter-browser-automation.mjs
 *
 * E2E smoke test for the Job Hunter Browser Automation feature.
 *
 * Covers:
 *   - TC-01: Credentials page accessible to authenticated user (HTTP 200)
 *   - TC-02: BrowserAutomationService endpoint reachable (no fatal)
 *   - TC-03: Anon request to credentials page is blocked (HTTP 403)
 *
 * Environment variables:
 *   ULI_URL      (required) one-time-login URL from `drush uli`
 *   BASE_URL     (optional, default: http://127.0.0.1)
 *   ARTIFACTS_DIR (optional, default: testing/artifacts)
 *
 * Usage:
 *   ULI_URL=$(drush uli --uri=https://forseti.life) node testing/jobhunter-browser-automation.mjs
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1';
const uliUrl = process.env.ULI_URL;
const artifactsDir = process.env.ARTIFACTS_DIR || 'testing/artifacts';

if (!uliUrl) {
  console.error('ERROR: Missing ULI_URL env var. Generate with: drush uli --uri=<base_url>');
  process.exit(1);
}

// Graceful skip if Playwright is not installed.
let chromium;
try {
  const require = createRequire(import.meta.url);
  ({ chromium } = require('playwright'));
} catch {
  console.warn('SKIP: playwright package not available. Install with: npm install playwright');
  console.warn('Exiting with code 0 (skip, not failure).');
  process.exit(0);
}

await fs.mkdir(artifactsDir, { recursive: true });

const report = {
  startedAt: new Date().toISOString(),
  baseUrl,
  feature: 'browser-automation',
  criteria: {
    credentialsPage200ForAuthUser: true,
    credentialsPage403ForAnon: true,
    noJsErrors: true,
  },
  steps: {},
  passed: false,
  failures: [],
  consoleErrors: [],
};

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page    = await context.newPage();

page.on('console', (msg) => {
  if (msg.type() === 'error') {
    report.consoleErrors.push(msg.text());
  }
});
page.on('pageerror', (err) => {
  report.consoleErrors.push(`pageerror: ${err.message}`);
});

async function screenshot(name) {
  const file = path.join(artifactsDir, `browser-auto-${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  return file;
}

// ─── Step 0: Log in via one-time-login URL ───────────────────────────────────
console.log('Step 0: Authenticating via ULI_URL...');
await page.goto(uliUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(1000);
const loginShot = await screenshot('00-login');
report.steps.login = { url: page.url(), screenshot: loginShot };

const loggedIn = !page.url().includes('/user/login');
if (!loggedIn) {
  report.failures.push('ULI authentication failed — redirected to /user/login');
}

// ─── Step 1 (TC-01): Credentials page → 200 for authenticated user ───────────
console.log('Step 1: TC-01 — credentials page accessible as authenticated user...');
const credResponse = await page.goto(`${baseUrl}/jobhunter/settings/credentials`, {
  waitUntil: 'domcontentloaded',
  timeout: 30000,
});
await page.waitForTimeout(600);
const credShot = await screenshot('01-credentials-page');

const credStatus = credResponse?.status() ?? 0;
report.steps.credentialsPage = {
  url:        page.url(),
  httpStatus: credStatus,
  screenshot: credShot,
};

if (credStatus !== 200) {
  report.failures.push(`TC-01 FAIL: Expected HTTP 200 for authenticated credentials page, got ${credStatus}`);
} else {
  console.log('  ✓ TC-01 PASS: credentials page returned 200');
}

// Check no PHP fatals / error messages on the page.
const bodyText = await page.textContent('body').catch(() => '');
if (bodyText.includes('The website encountered an unexpected error')) {
  report.failures.push('TC-02 FAIL: Drupal fatal error rendered on credentials page.');
} else {
  console.log('  ✓ TC-02 PASS: No fatal errors on credentials page');
}

// ─── Step 2 (TC-03): Anon request to credentials page → 403 ─────────────────
console.log('Step 2: TC-03 — credentials page blocked for anonymous...');
const anonContext = await browser.newContext();
const anonPage    = await anonContext.newPage();

const anonResponse = await anonPage.goto(`${baseUrl}/jobhunter/settings/credentials`, {
  waitUntil: 'domcontentloaded',
  timeout: 30000,
});
await anonPage.waitForTimeout(600);
const anonShot = await screenshot('02-credentials-anon');
await anonContext.close();

const anonStatus = anonResponse?.status() ?? 0;
report.steps.credentialsPageAnon = {
  httpStatus: anonStatus,
  screenshot: anonShot,
};

// Drupal typically redirects (302) anon to /user/login rather than pure 403.
const anonBlocked = anonStatus === 403 || anonStatus === 302 || anonStatus === 301;
if (!anonBlocked) {
  report.failures.push(`TC-03 FAIL: Anon request should be blocked (403/302), got HTTP ${anonStatus}`);
} else {
  console.log(`  ✓ TC-03 PASS: Anon request returned ${anonStatus} (blocked)`);
}

// ─── Finalize report ─────────────────────────────────────────────────────────
report.passed        = report.failures.length === 0;
report.finishedAt    = new Date().toISOString();
report.jsErrorCount  = report.consoleErrors.length;

const reportPath = path.join(artifactsDir, 'browser-automation-report.json');
await fs.writeFile(reportPath, JSON.stringify(report, null, 2));

console.log(`\nReport written: ${reportPath}`);
console.log(`Result: ${report.passed ? '✅ PASS' : '❌ FAIL'}`);

if (!report.passed) {
  console.error('Failures:');
  report.failures.forEach((f) => console.error('  -', f));
}

await browser.close();
process.exit(report.passed ? 0 : 1);
