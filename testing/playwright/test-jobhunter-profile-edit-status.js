#!/usr/bin/env node

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const parseBool = (value, fallback) => {
  if (value === undefined || value === null || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
};

const parseIntFallback = (value, fallback) => {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const CONFIG = {
  baseUrl: process.argv[2] || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1',
  uliUrl: process.env.ULI_URL || '',
  username: process.env.PLAYWRIGHT_USERNAME || 'admin',
  password: process.env.PLAYWRIGHT_PASSWORD || '',
  headless: parseBool(process.env.PLAYWRIGHT_HEADLESS, true),
  slowMo: parseIntFallback(process.env.PLAYWRIGHT_SLOWMO, 0),
  timeoutMs: parseIntFallback(process.env.PLAYWRIGHT_TIMEOUT_MS, 30000),
  screenshotDir: path.resolve('testing/artifacts'),
};

const log = (message, type = 'info') => {
  const labels = {
    info: '[INFO] ',
    success: '[OK]   ',
    warning: '[WARN] ',
    error: '[ERROR] ',
  };
  console.log(`${labels[type] || ''}${message}`);
};

const ensureDir = (dirPath) => {
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
};

async function login(page) {
  if (CONFIG.uliUrl) {
    log('Logging in via ULI_URL...');
    await page.goto(CONFIG.uliUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
    return;
  }

  if (!CONFIG.password) {
    throw new Error('Missing auth: set ULI_URL or PLAYWRIGHT_PASSWORD (with PLAYWRIGHT_USERNAME).');
  }

  log('Logging in via /user/login...');
  await page.goto(`${CONFIG.baseUrl}/user/login`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });
  await page.fill('input[name="name"]', CONFIG.username);
  await page.fill('input[name="pass"]', CONFIG.password);

  const submit = page.locator('input[type="submit"], button[type="submit"]').first();
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs }).catch(() => {}),
    submit.click(),
  ]);
}

async function run() {
  ensureDir(CONFIG.screenshotDir);

  const browser = await chromium.launch({
    headless: CONFIG.headless,
    slowMo: CONFIG.slowMo,
    args: ['--disable-dev-shm-usage'],
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  const logs = [];
  page.on('console', (msg) => logs.push(`console:${msg.type()}: ${msg.text()}`));
  page.on('pageerror', (err) => logs.push(`pageerror: ${err.message}`));

  try {
    log(`Base URL: ${CONFIG.baseUrl}`);

    await login(page);
    await page.goto(`${CONFIG.baseUrl}/jobhunter/profile/edit`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(1500);

    const bodyText = await page.locator('body').innerText();

    const hasIndividualJsonYes = /Individual JSON Stored:\s*Yes/i.test(bodyText);
    const hasMergedYes = /Merged to Consolidated:\s*Yes/i.test(bodyText);

    const education = await page.locator('.education-display').first();
    const educationExists = (await education.count()) > 0;
    const educationInnerText = educationExists ? (await education.innerText()).trim() : '';
    const educationInnerHtml = educationExists ? (await education.innerHTML()).trim() : '';

    const screenshotPath = path.join(CONFIG.screenshotDir, 'jobhunter-profile-edit-status.png');
    await page.screenshot({ path: screenshotPath, fullPage: true });

    const report = {
      url: page.url(),
      hasIndividualJsonYes,
      hasMergedYes,
      education: {
        exists: educationExists,
        innerTextLength: educationInnerText.length,
        innerHtmlLength: educationInnerHtml.length,
        innerTextPreview: educationInnerText.slice(0, 200),
        innerHtmlPreview: educationInnerHtml.slice(0, 200),
      },
      logs,
      screenshot: screenshotPath,
    };

    fs.writeFileSync(
      path.join(CONFIG.screenshotDir, 'jobhunter-profile-edit-status.json'),
      JSON.stringify(report, null, 2)
    );

    if (!hasIndividualJsonYes || !hasMergedYes) {
      throw new Error(`Status check failed: Individual JSON Stored=${hasIndividualJsonYes} Merged=${hasMergedYes}`);
    }

    // Education must be visible text if markup exists.
    if (educationExists && educationInnerHtml.length > 0 && educationInnerText.length === 0) {
      throw new Error('Education display has HTML but no visible text (innerText empty).');
    }

    log('Profile edit status indicators are correct.', 'success');
    log(`Wrote report: ${path.join(CONFIG.screenshotDir, 'jobhunter-profile-edit-status.json')}`, 'success');
  } finally {
    await browser.close();
  }
}

run().catch((err) => {
  log(err.stack || err.message || String(err), 'error');
  process.exit(1);
});
