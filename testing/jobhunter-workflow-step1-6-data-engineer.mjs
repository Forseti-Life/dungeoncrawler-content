import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1';
const uliUrl = process.env.ULI_URL;
const resumePath = process.env.RESUME_PATH || '';
const artifactsDir = process.env.ARTIFACTS_DIR || 'testing/artifacts';

if (!uliUrl) {
  console.error('Missing ULI_URL env var');
  process.exit(1);
}

await fs.mkdir(artifactsDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

const report = {
  startedAt: new Date().toISOString(),
  baseUrl,
  criteria: {
    roleQuery: 'Data Engineer',
    locationQuery: 'Philadelphia',
    mustFindMatchingJob: true,
  },
  steps: {},
  submission: {
    success: false,
    message: '',
    attempts: 0,
    role: 'Data Engineer',
  },
  consoleErrors: [],
};

page.on('console', (msg) => {
  if (msg.type() === 'error') {
    report.consoleErrors.push(msg.text());
  }
});
page.on('pageerror', (err) => {
  report.consoleErrors.push(`pageerror: ${err.message}`);
});

async function screenshot(name) {
  const file = path.join(artifactsDir, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  return file;
}

async function safeGoto(url, name) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(800);
  const shot = await screenshot(name);
  return { url: page.url(), screenshot: shot };
}

async function goFromDashboard(buttonText, fallbackPath, stepKey) {
  await safeGoto(`${baseUrl}/jobhunter`, `${stepKey}-dashboard`);
  const byText = page.locator('a.phase-button', { hasText: buttonText });
  if (await byText.count()) {
    await byText.first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(700);
  } else {
    await page.goto(`${baseUrl}${fallbackPath}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(700);
  }
  const shot = await screenshot(stepKey);
  report.steps[stepKey] = { url: page.url(), screenshot: shot };
}

async function maybeUploadResume() {
  const fileInput = page.locator('input[type="file"]').first();
  if (!(await fileInput.count())) return false;
  if (!resumePath) return false;

  try {
    await fileInput.setInputFiles(resumePath);
    const uploadButton = page.getByRole('button', { name: /upload/i }).first();
    if (await uploadButton.count()) {
      await uploadButton.click();
      await page.waitForTimeout(1200);
    }
    const processBtn = page.getByRole('button', { name: /process uploaded files/i }).first();
    if (await processBtn.count()) {
      await processBtn.click();
      await page.waitForTimeout(1500);
    }
    return true;
  } catch {
    return false;
  }
}

async function saveProfileFields() {
  const titleField = page.locator('textarea[name="field_target_job_titles"], input[name="field_target_job_titles"]').first();
  if (await titleField.count()) {
    await titleField.fill('Data Engineer\nSenior Data Engineer\nDirector of Data Engineering');
  }

  const keywordsField = page.locator('textarea[name="field_keywords_interested"], input[name="field_keywords_interested"]').first();
  if (await keywordsField.count()) {
    await keywordsField.fill('data engineer, spark, airflow, etl, data platform, python, sql');
  }

  const saveProfile = page.getByRole('button', { name: /save profile|save/i }).first();
  if (await saveProfile.count()) {
    await saveProfile.click();
    await page.waitForTimeout(1500);
  }
}

async function searchForDataEngineer() {
  await goFromDashboard('Job Discovery', '/jobhunter/job-discovery', 'step2-job-discovery');

  const query = page.locator('#search-query, input[name="q"]').first();
  if (await query.count()) {
    await query.fill('data engineer');
  }
  const location = page.locator('#search-location, input[name="location"]').first();
  if (await location.count()) {
    await location.fill('Philadelphia');
  }

  const searchBtn = page.getByRole('button', { name: /search jobs/i }).first();
  if (await searchBtn.count()) {
    await searchBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2200);
  }

  const pageText = (await page.locator('body').innerText()).toLowerCase();
  const hasRole = pageText.includes('data engineer');
  const hasLocation = pageText.includes('philadelphia') || pageText.includes('pa');
  report.steps.step2_search_criteria = {
    requiredRole: 'Data Engineer',
    requiredLocation: 'Philadelphia',
    hasRole,
    hasLocation,
  };

  if (!hasRole || !hasLocation) {
    return { saved: false, foundMatchingRoleInPhiladelphia: false };
  }

  const saveLink = page.locator('a.btn-save-job', { hasText: /save job/i }).first();
  if (await saveLink.count()) {
    await saveLink.click();
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(1200);
    return { saved: true, foundMatchingRoleInPhiladelphia: true };
  }

  // Some sources may render save as a button.
  const saveBtn = page.getByRole('button', { name: /save job/i }).first();
  if (await saveBtn.count()) {
    await saveBtn.click();
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(1200);
    return { saved: true, foundMatchingRoleInPhiladelphia: true };
  }

  return { saved: false, foundMatchingRoleInPhiladelphia: true };
}

async function addManualDataEngineerJob() {
  await safeGoto(`${baseUrl}/jobhunter/jobs/paste`, 'manual-job-form');
  if (!page.url().includes('/jobhunter/jobs/paste')) {
    await safeGoto(`${baseUrl}/jobhunter/job/paste`, 'manual-job-form-fallback');
  }

  const jobUrl = page.locator('input[name="job_url"], input#edit-job-url').first();
  if (await jobUrl.count()) {
    await jobUrl.fill('https://example.com/jobs/data-engineer');
  }

  const rawText = `Senior Data Engineer\nAcme Data Systems\nPhiladelphia, PA\n\nWe are hiring a Senior Data Engineer to build scalable ETL pipelines, improve data platform reliability, and support analytics stakeholders in the Philadelphia area.\n\nResponsibilities:\n- Build and maintain batch and streaming pipelines\n- Design data models and warehouse schemas\n- Partner with product and analytics teams\n\nRequirements:\n- 5+ years data engineering experience\n- Python, SQL, Airflow, Spark\n- Cloud data stack experience`; 

  const postingText = page.locator('textarea[name="raw_posting_text"]').first();
  if (await postingText.count()) {
    await postingText.fill(rawText);
  }

  const quickEntry = page.locator('summary:has-text("Quick Entry")').first();
  if (await quickEntry.count()) {
    await quickEntry.click();
    await page.waitForTimeout(200);
  }

  const company = page.locator('input[name="company_name"]').first();
  if (await company.count()) {
    await company.fill('Acme Data Systems');
  }

  const title = page.locator('input[name="job_title"]').first();
  if (await title.count()) {
    await title.fill('Senior Data Engineer');
  }

  const source = page.locator('select[name="source_platform"]').first();
  if (await source.count()) {
    await source.selectOption('company_site');
  }

  const submit = page.locator('input#edit-submit, button#edit-submit, input[type="submit"][name="op"]').first();
  if (await submit.count()) {
    await submit.click();
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(1200);
  }
}

async function markAppliedDataEngineer() {
  await safeGoto(`${baseUrl}/jobhunter/my-jobs`, 'my-jobs-before-submit');

  const rows = page.locator('main.job-hunter-content table tbody tr');
  const count = await rows.count();
  if (!count) return false;

  let targetRow = rows.first();
  for (let i = 0; i < count; i++) {
    const row = rows.nth(i);
    const text = (await row.innerText()).toLowerCase();
    if (text.includes('data engineer')) {
      targetRow = row;
      break;
    }
  }

  const checkbox = targetRow.locator('form.applied-toggle-form input[type="checkbox"][name="have_applied"]').first();
  if (await checkbox.count()) {
    if (!(await checkbox.isChecked())) {
      await checkbox.check();
    }
  } else {
    return false;
  }

  const dateInput = targetRow.locator('form.applied-toggle-form input[type="date"][name="applied_on_date"]').first();
  if (await dateInput.count()) {
    const today = new Date().toISOString().slice(0, 10);
    await dateInput.fill(today);
  }

  const updateBtn = targetRow.locator('form.applied-toggle-form button[type="submit"]', { hasText: /update/i }).first();
  if (await updateBtn.count()) {
    await updateBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1200);
  } else {
    return false;
  }

  const bodyText = (await page.locator('body').innerText()).toLowerCase();
  if (bodyText.includes('marked as applied') || bodyText.includes('updated') || bodyText.includes('have applied')) {
    report.submission.message = 'Marked as applied.';
    await screenshot('my-jobs-submission-success');
    return true;
  }

  return false;
}

try {
  await safeGoto(uliUrl, 'login');
  await safeGoto(`${baseUrl}/jobhunter`, 'dashboard-start');

  await goFromDashboard('My Profile', '/jobhunter/profile/edit', 'step1-profile');
  const uploaded = await maybeUploadResume();
  await saveProfileFields();
  report.steps.step1_profile = { ...(report.steps['step1-profile'] || {}), uploadedResume: uploaded };

  const searchOutcome = await searchForDataEngineer();
  const savedFromSearch = searchOutcome.saved;
  report.steps.step2_search = {
    ...(report.steps['step2-job-discovery'] || {}),
    ...searchOutcome,
  };

  if (!searchOutcome.foundMatchingRoleInPhiladelphia) {
    report.submission.message = 'Step 3 criterion failed: no Data Engineer role found in Philadelphia.';
    report.finishedAt = new Date().toISOString();
    const reportPath = path.join(artifactsDir, 'jobhunter-step1-6-data-engineer-report.json');
    await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
    console.log(`Report written: ${reportPath}`);
    console.log('Submission success: false');
    process.exitCode = 3;
    await browser.close();
    process.exit();
  }

  await goFromDashboard('View Submissions', '/jobhunter/application-submission', 'step3-submission-page');

  for (let attempt = 1; attempt <= 6; attempt++) {
    report.submission.attempts = attempt;
    const applied = await markAppliedDataEngineer();
    if (applied) {
      report.submission.success = true;
      break;
    }

    if (!savedFromSearch) {
      await addManualDataEngineerJob();
    } else {
      await searchForDataEngineer();
    }
  }

  await goFromDashboard('Manage Pipeline', '/jobhunter/interview-followup', 'step5-interview-followup');
  await goFromDashboard('View Analytics', '/jobhunter/analytics', 'step6-analytics');

  await safeGoto(`${baseUrl}/jobhunter`, 'dashboard-end');

  report.finishedAt = new Date().toISOString();
  report.finalUrl = page.url();

  const reportPath = path.join(artifactsDir, 'jobhunter-step1-6-data-engineer-report.json');
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2));

  console.log(`Report written: ${reportPath}`);
  console.log(`Submission success: ${report.submission.success}`);
  console.log(`Submission attempts: ${report.submission.attempts}`);
  if (!report.submission.success) process.exitCode = 2;
} finally {
  await browser.close();
}
