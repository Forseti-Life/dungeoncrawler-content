#!/usr/bin/env node

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const parseBool = (value, fallback) => {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
};

const parseIntFallback = (value, fallback) => {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const CONFIG = {
  baseUrl: process.argv[2] || process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',
  resumePath: process.argv[3] || process.env.PLAYWRIGHT_RESUME_PATH || '/mnt/chromeos/MyFiles/Downloads/KeithAumillerA.pdf',
  username: process.env.PLAYWRIGHT_USERNAME || 'admin',
  password: process.env.PLAYWRIGHT_PASSWORD || 'admin_secure_password',
  jobId: process.env.JOBHUNTER_JOB_ID || '',
  headless: parseBool(process.env.PLAYWRIGHT_HEADLESS, true),
  slowMo: parseIntFallback(process.env.PLAYWRIGHT_SLOWMO, 0),
  timeoutMs: parseIntFallback(process.env.PLAYWRIGHT_TIMEOUT_MS, 15000),
  pollDelayMs: parseIntFallback(process.env.PLAYWRIGHT_POLL_DELAY_MS, 10000),
  maxPolls: parseIntFallback(process.env.PLAYWRIGHT_MAX_POLLS, 30),
  screenshotDir: path.resolve('testing/playwright/screenshots')
};

const log = (message, type = 'info') => {
  const labels = {
    info: '[INFO] ',
    success: '[OK]   ',
    warning: '[WARN] ',
    error: '[ERROR] ',
    debug: '[DEBUG] '
  };
  console.log(`${labels[type] || ''}${message}`);
};

const ensureFileExists = (filePath) => {
  if (!fs.existsSync(filePath)) {
    throw new Error(`Resume file not found: ${filePath}`);
  }
};

const ensureDir = (dirPath) => {
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
};

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const checkServerReachable = async () => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), Math.min(CONFIG.timeoutMs, 10000));

  try {
    const response = await fetch(`${CONFIG.baseUrl}/user/login`, { signal: controller.signal });
    return response.ok;
  } catch (error) {
    return false;
  } finally {
    clearTimeout(timeout);
  }
};

async function login(page) {
  log('Logging in...');
  await page.goto(`${CONFIG.baseUrl}/user/login`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });
  await page.fill('input[name="name"]', CONFIG.username);
  await page.fill('input[name="pass"]', CONFIG.password);

  const submit = page.locator('input[type="submit"], button[type="submit"]').first();
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs }).catch(() => {}),
    submit.click()
  ]);

  if (page.url().includes('/user/login')) {
    log('Login may have failed or already logged in.', 'warning');
  } else {
    log('Login successful.', 'success');
  }
}

async function uploadResume(page) {
  log('Uploading resume...');
  await page.goto(`${CONFIG.baseUrl}/jobhunter/profile/edit`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });

  const fileInput = page.locator('input[type="file"][name*="field_resume_file"]');
  await fileInput.setInputFiles(CONFIG.resumePath);

  const processButton = page.locator('input[type="submit"][value*="Process Uploaded Files"], button:has-text("Process Uploaded Files")').first();
  if (!(await processButton.isVisible())) {
    throw new Error('Process Uploaded Files button not found.');
  }

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs }).catch(() => {}),
    processButton.click()
  ]);

  log('Resume uploaded and processing queued.', 'success');
}

async function waitForParsingComplete(page) {
  log('Waiting for resume parsing to complete...');

  for (let attempt = 1; attempt <= CONFIG.maxPolls; attempt++) {
    await page.goto(`${CONFIG.baseUrl}/jobhunter/profile/edit`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });
    const content = await page.content();

    if (/Individual JSON Stored:\s*Error/i.test(content) || /Parsing failed/i.test(content)) {
      throw new Error('Resume parsing failed. Check logs for details.');
    }

    const hasJson = /Individual JSON Stored:\s*Yes/i.test(content);
    const inConsolidated = /Merged to Consolidated:\s*Yes/i.test(content);

    if (hasJson && inConsolidated) {
      log('Resume parsing and consolidation complete.', 'success');
      return;
    }

    log(`Parsing not complete yet (attempt ${attempt}/${CONFIG.maxPolls}).`, 'info');
    await wait(CONFIG.pollDelayMs);
  }

  throw new Error('Parsing did not complete before timeout.');
}

async function findJobId(page) {
  if (CONFIG.jobId) {
    log(`Using JOBHUNTER_JOB_ID=${CONFIG.jobId}`);
    return CONFIG.jobId;
  }

  await page.goto(`${CONFIG.baseUrl}/jobhunter/my-jobs`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });
  const jobLinks = await page.$$eval('a[href^="/jobhunter/tailor-resume/"]', (links) => links.map((link) => link.getAttribute('href')));

  if (!jobLinks.length) {
    throw new Error('No job entries found on /jobhunter/my-jobs. Save a job first or set JOBHUNTER_JOB_ID.');
  }

  const match = jobLinks[0].match(/\/jobhunter\/tailor-resume\/(\d+)/);
  if (!match) {
    throw new Error('Unable to parse job ID from job link.');
  }

  log(`Selected job ID ${match[1]}`);
  return match[1];
}

async function waitForTailoring(page, jobId) {
  log('Starting resume tailoring...');
  await page.goto(`${CONFIG.baseUrl}/jobhunter/tailor-resume/${jobId}`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });

  const generateButton = page.locator('#generate-tailored-resume');
  if (await generateButton.isVisible()) {
    await generateButton.click();
    log('Tailoring queued.', 'info');
  }

  for (let attempt = 1; attempt <= CONFIG.maxPolls; attempt++) {
    await page.waitForTimeout(CONFIG.pollDelayMs);
    await page.goto(`${CONFIG.baseUrl}/jobhunter/tailor-resume/${jobId}`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });

    if (await page.locator('#generate-pdf-btn').isVisible()) {
      log('Tailoring completed.', 'success');
      return;
    }

    if (await page.locator('text=Tailoring Failed').isVisible()) {
      if (await generateButton.isVisible()) {
        log('Tailoring failed, retrying...', 'warning');
        await generateButton.click();
      }
    }

    log(`Tailoring in progress (attempt ${attempt}/${CONFIG.maxPolls}).`, 'info');
  }

  throw new Error('Tailoring did not complete before timeout.');
}

async function generatePdf(page, jobId) {
  log('Generating tailored PDF...');
  await page.goto(`${CONFIG.baseUrl}/jobhunter/tailor-resume/${jobId}`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });

  const generatePdfButton = page.locator('#generate-pdf-btn');
  if (!(await generatePdfButton.isVisible())) {
    throw new Error('Generate PDF button not available.');
  }

  await generatePdfButton.click();
  await page.waitForTimeout(3000);

  await page.goto(`${CONFIG.baseUrl}/jobhunter/tailor-resume/${jobId}`, { waitUntil: 'domcontentloaded', timeout: CONFIG.timeoutMs });
  const downloadButton = page.locator('#download-pdf-btn');

  if (await downloadButton.isVisible()) {
    log('PDF generated and download link available.', 'success');
    return;
  }

  if (await page.locator('.pdf-item--latest').isVisible()) {
    log('PDF generated and listed in history.', 'success');
    return;
  }

  throw new Error('PDF did not appear after generation.');
}

async function run() {
  ensureFileExists(CONFIG.resumePath);
  ensureDir(CONFIG.screenshotDir);

  const serverOk = await checkServerReachable();
  if (!serverOk) {
    throw new Error(`Unable to reach ${CONFIG.baseUrl}. Make sure the site is running.`);
  }

  const browser = await chromium.launch({ headless: CONFIG.headless, slowMo: CONFIG.slowMo, args: ['--disable-dev-shm-usage'] });
  const page = await browser.newPage();

  try {
    log(`Base URL: ${CONFIG.baseUrl}`);
    log(`Resume: ${CONFIG.resumePath}`);

    await login(page);
    await uploadResume(page);
    await waitForParsingComplete(page);

    const jobId = await findJobId(page);
    await waitForTailoring(page, jobId);
    await generatePdf(page, jobId);

    log('Job Hunter resume flow completed successfully.', 'success');
  } catch (error) {
    log(error.message, 'error');
    const screenshotPath = path.join(CONFIG.screenshotDir, `jobhunter-failure-${Date.now()}.png`);
    try {
      await page.screenshot({ path: screenshotPath, fullPage: true, timeout: 5000 });
      log(`Saved screenshot: ${screenshotPath}`, 'warning');
    } catch (screenshotError) {
      log(`Screenshot failed: ${screenshotError.message}`, 'warning');
    }
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
}

run();
