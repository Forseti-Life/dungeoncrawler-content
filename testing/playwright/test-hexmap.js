#!/usr/bin/env node

/**
 * Hexmap Console Test
 * 
 * Automated test to verify hexmap.js loads without console errors.
 * Useful for CI/CD pipelines and pre-deployment testing.
 * 
 * Usage:
 *   node test-hexmap.js
 *   node test-hexmap.js http://example.com:8080 5000
 */

const { chromium } = require('playwright');

async function testHexmap(baseUrl = 'http://localhost:8080', waitTime = 5000) {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  const loginUrl = process.env.PLAYWRIGHT_LOGIN_URL || process.env.LOGIN_URL || '';
  const loginPath = process.env.PLAYWRIGHT_LOGIN_PATH || '/user';
  const username = process.env.PLAYWRIGHT_USERNAME || process.env.PLAYWRIGHT_USER || '';
  const password = process.env.PLAYWRIGHT_PASSWORD || process.env.PLAYWRIGHT_PASS || '';

  let hasErrors = false;
  const errors = [];
  const warnings = [];

  // Capture console errors
  page.on('console', msg => {
    if (msg.type() === 'error') {
      hasErrors = true;
      errors.push(msg.text());
      console.error(`❌ Console Error: ${msg.text()}`);
    } else if (msg.type() === 'warning') {
      warnings.push(msg.text());
      console.warn(`⚠️  Warning: ${msg.text()}`);
    } else if (msg.type() === 'log') {
      console.log(`ℹ️  Log: ${msg.text()}`);
    }
  });

  // Capture page errors
  page.on('pageerror', error => {
    hasErrors = true;
    errors.push(error.message);
    console.error(`❌ Page Error: ${error.message}`);
  });

  try {
    if (loginUrl) {
      await page.goto(loginUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    } else if (username && password) {
      const loginTarget = loginPath.startsWith('http') ? loginPath : `${baseUrl}${loginPath}`;
      await page.goto(loginTarget, { waitUntil: 'domcontentloaded', timeout: 30000 });

      const nameInput = await page.$('input[name="name"]');
      const passInput = await page.$('input[name="pass"]');
      const submitButton = await page.$('input#edit-submit, button#edit-submit, button[type="submit"], input[type="submit"]');

      if (nameInput && passInput && submitButton) {
        await nameInput.fill(username);
        await passInput.fill(password);
        await submitButton.click();
        await page.waitForTimeout(1500);
      } else {
        console.warn('⚠️  Login form fields not found at login path.');
      }
    }

    const hexmapUrl = `${baseUrl}/hexmap`;
    console.log(`Testing: ${hexmapUrl}`);
    console.log(`Timeout: ${waitTime}ms\n`);

    await page.goto(hexmapUrl, { waitUntil: 'networkidle', timeout: 30000 });
    console.log('✓ Page loaded\n');

    // Wait for dynamic content and scripts to execute
    await page.waitForTimeout(waitTime);

    // Check for JavaScript errors
    const jsErrors = await page.evaluate(() => {
      return window.__jsErrors || [];
    });

    if (jsErrors.length > 0) {
      hasErrors = true;
      console.error(`❌ JavaScript Runtime Errors: ${jsErrors.length}`);
      jsErrors.forEach(err => console.error(`   - ${err}`));
    }

  } catch (error) {
    hasErrors = true;
    console.error(`❌ Navigation Error: ${error.message}`);
  }

  await context.close();
  await browser.close();

  // Summary
  console.log('\n=== Test Summary ===');
  console.log(`Errors:   ${errors.length}`);
  console.log(`Warnings: ${warnings.length}`);

  if (hasErrors && errors.length > 0) {
    console.log('\n=== Detailed Errors ===');
    errors.forEach((err, i) => console.log(`${i + 1}. ${err}`));
    return false;
  }

  console.log('\n✓ All tests passed - no console errors');
  return true;
}

// Parse arguments
const baseUrl = process.argv[2] || 'http://localhost:8080';
const waitTime = parseInt(process.argv[3]) || 5000;

testHexmap(baseUrl, waitTime)
  .then(success => process.exit(success ? 0 : 1))
  .catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
  });
