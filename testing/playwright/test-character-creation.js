#!/usr/bin/env node

/**
 * Character Creation Workflow Test
 * 
 * Tests the full character creation flow from step 1-8 to identify:
 * - Console errors and warnings
 * - Client-side vs server-side validation conflicts
 * - Missing controls or redundant validations
 * - UI/UX issues in the workflow
 * 
 * Usage:
 *   node test-character-creation.js [baseUrl] [timeout]
 *   node test-character-creation.js http://localhost:8080 10000
 */

const { chromium } = require('playwright');

// Test configuration
const CONFIG = {
  baseUrl: process.argv[2] || 'http://localhost:8080',
  timeout: parseInt(process.argv[3]) || 10000,
  screenshots: true,
  screenshotDir: './screenshots',
  hostHeader: process.env.HOST_HEADER || '',
  loginUrl: process.env.PLAYWRIGHT_LOGIN_URL || process.env.LOGIN_URL || '',
  loginPath: process.env.PLAYWRIGHT_LOGIN_PATH || '/user',
  username: process.env.PLAYWRIGHT_USERNAME || process.env.PLAYWRIGHT_USER || '',
  password: process.env.PLAYWRIGHT_PASSWORD || process.env.PLAYWRIGHT_PASS || ''
};

// Track test results
const results = {
  errors: [],
  warnings: [],
  validationIssues: [],
  clientServerConflicts: [],
  steps: {}
};

/**
 * Capture console messages and categorize them
 */
function setupConsoleCapture(page, stepName) {
  page.on('console', msg => {
    const text = msg.text();
    const type = msg.type();
    
    if (type === 'error') {
      results.errors.push({ step: stepName, message: text, type: 'console' });
      console.error(`❌ [${stepName}] Console Error: ${text}`);
    } else if (type === 'warning') {
      results.warnings.push({ step: stepName, message: text });
      console.warn(`⚠️  [${stepName}] Warning: ${text}`);
    } else if (text.includes('validation') || text.includes('required')) {
      results.validationIssues.push({ step: stepName, message: text });
      console.log(`🔍 [${stepName}] Validation: ${text}`);
    }
  });

  page.on('pageerror', error => {
    results.errors.push({ step: stepName, message: error.message, type: 'page' });
    console.error(`❌ [${stepName}] Page Error: ${error.message}`);
  });

  page.on('requestfailed', request => {
    const failure = request.failure();
    const message = `${request.url()} - ${failure ? failure.errorText : 'request failed'}`;
    results.errors.push({ step: stepName, message, type: 'request' });
    console.error(`❌ [${stepName}] Request Failed: ${message}`);
  });

  page.on('response', response => {
    if (response.status() >= 400) {
      const message = `${response.url()} - ${response.status()}`;
      results.errors.push({ step: stepName, message, type: 'response' });
      console.error(`❌ [${stepName}] HTTP ${response.status()}: ${response.url()}`);
    }
  });
}

async function loginWithCredentials(page) {
  if (!CONFIG.username || !CONFIG.password) {
    return;
  }

  const loginTarget = CONFIG.loginPath.startsWith('http')
    ? CONFIG.loginPath
    : `${CONFIG.baseUrl}${CONFIG.loginPath}`;

  console.log(`Logging in via credentials at: ${loginTarget}`);

  await page.goto(loginTarget, { waitUntil: 'domcontentloaded', timeout: 15000 });

  const nameInput = await page.$('input[name="name"]');
  const passInput = await page.$('input[name="pass"]');
  const submitButton = await page.$('input#edit-submit, button#edit-submit, button[type="submit"], input[type="submit"]');

  if (!nameInput || !passInput || !submitButton) {
    results.warnings.push({ step: 'Login', message: 'Login form fields not found at login path.' });
    console.warn('⚠️  Login form fields not found at login path.');
    return;
  }

  await nameInput.fill(CONFIG.username);
  await passInput.fill(CONFIG.password);
  await submitButton.click();
  await page.waitForTimeout(1500);
}

/**
 * Test Step 1: Name and Concept
 */
async function testStep1(page) {
  const stepName = 'Step 1: Name';
  console.log(`\n=== Testing ${stepName} ===`);
  
  try {
    await page.goto(`${CONFIG.baseUrl}/characters/create/step/1`, {
      waitUntil: 'networkidle',
      timeout: 30000
    });
    
    await page.waitForTimeout(2000);
    
    // Check for form elements
    const nameInput = await page.$('input[name="name"]');
    const conceptInput = await page.$('textarea[name="concept"]');
    const nextButton = await page.$('button[type="submit"], input[type="submit"]');
    
    results.steps[stepName] = {
      loaded: true,
      hasNameInput: !!nameInput,
      hasConceptInput: !!conceptInput,
      hasNextButton: !!nextButton
    };
    
    // Test client-side validation
    if (nextButton) {
      console.log('  Testing empty form submission...');
      await nextButton.click();
      await page.waitForTimeout(1000);
      
      // Check if client-side validation prevented submission
      const errorMsg = await page.$('.error-message, .messages--error');
      results.steps[stepName].clientValidation = !!errorMsg;
      console.log(`  Client-side validation: ${errorMsg ? '✓ Present' : '✗ Missing'}`);
    }
    
    // Fill form with valid data
    if (nameInput) {
      await nameInput.fill('Test Character');
    }
    if (conceptInput) {
      await conceptInput.fill('A brave test adventurer for validation testing');
    }
    
    if (CONFIG.screenshots) {
      await page.screenshot({ path: `${CONFIG.screenshotDir}/step1-filled.png` });
    }
    
    console.log(`✓ ${stepName} completed`);
    return true;
  } catch (error) {
    results.errors.push({ step: stepName, message: error.message, type: 'test' });
    console.error(`❌ ${stepName} failed: ${error.message}`);
    return false;
  }
}

/**
 * Test Step 2: Ancestry and Heritage
 */
async function testStep2(page) {
  const stepName = 'Step 2: Ancestry';
  console.log(`\n=== Testing ${stepName} ===`);
  
  try {
    // Check for ancestry selection cards
    const ancestryCards = await page.$$('.ancestry-card');
    results.steps[stepName] = {
      loaded: true,
      ancestryOptions: ancestryCards.length
    };
    
    console.log(`  Found ${ancestryCards.length} ancestry options`);
    
    if (ancestryCards.length > 0) {
      // Select first ancestry
      await ancestryCards[0].click();
      await page.waitForTimeout(1000);
      
      // Check if heritage options appear
      const heritageCards = await page.$$('.heritage-card');
      results.steps[stepName].heritageOptions = heritageCards.length;
      console.log(`  Found ${heritageCards.length} heritage options`);
      
      if (heritageCards.length > 0) {
        await heritageCards[0].click();
        await page.waitForTimeout(500);
      }
    }
    
    if (CONFIG.screenshots) {
      await page.screenshot({ path: `${CONFIG.screenshotDir}/step2-selected.png` });
    }
    
    console.log(`✓ ${stepName} completed`);
    return true;
  } catch (error) {
    results.errors.push({ step: stepName, message: error.message, type: 'test' });
    console.error(`❌ ${stepName} failed: ${error.message}`);
    return false;
  }
}

/**
 * Test Step 4: Class Selection
 */
async function testStep4(page) {
  const stepName = 'Step 4: Class';
  console.log(`\n=== Testing ${stepName} ===`);
  
  try {
    await page.waitForSelector('.class-card, [data-class]', { timeout: 5000 });
    
    const classCards = await page.$$('.class-card, [data-class]');
    results.steps[stepName] = {
      loaded: true,
      classOptions: classCards.length
    };
    
    console.log(`  Found ${classCards.length} class options`);
    
    // Test clicking without selection
    const nextButton = await page.$('button[type="submit"], input[type="submit"]');
    if (nextButton) {
      console.log('  Testing empty class submission...');
      await nextButton.click();
      await page.waitForTimeout(1000);
      
      const errorMsg = await page.$('.error-message, .messages--error');
      results.steps[stepName].clientValidation = !!errorMsg;
      console.log(`  Client-side validation: ${errorMsg ? '✓ Present' : '✗ Missing'}`);
    }
    
    // Select a class
    if (classCards.length > 0) {
      await classCards[0].click();
      await page.waitForTimeout(1000);
    }
    
    if (CONFIG.screenshots) {
      await page.screenshot({ path: `${CONFIG.screenshotDir}/step4-selected.png` });
    }
    
    console.log(`✓ ${stepName} completed`);
    return true;
  } catch (error) {
    results.errors.push({ step: stepName, message: error.message, type: 'test' });
    console.error(`❌ ${stepName} failed: ${error.message}`);
    return false;
  }
}

/**
 * Main test runner
 */
async function runTests() {
  console.log('=== Character Creation Workflow Test ===');
  console.log(`Base URL: ${CONFIG.baseUrl}`);
  console.log(`Timeout: ${CONFIG.timeout}ms\n`);
  
  const browser = await chromium.launch({ headless: false });
  const contextOptions = {
    viewport: { width: 1280, height: 720 }
  };

  if (CONFIG.hostHeader) {
    contextOptions.extraHTTPHeaders = { Host: CONFIG.hostHeader };
  }

  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();
  
  // Setup console capture
  setupConsoleCapture(page, 'Global');
  
  try {
    if (CONFIG.loginUrl) {
      console.log(`Logging in via: ${CONFIG.loginUrl}`);
      try {
        await page.goto(CONFIG.loginUrl, { waitUntil: 'domcontentloaded', timeout: 10000 });
        await page.waitForTimeout(2000);
      } catch (error) {
        results.warnings.push({ step: 'Login', message: error.message });
        console.warn(`⚠️  Login navigation warning: ${error.message}`);
      }
    } else if (CONFIG.username && CONFIG.password) {
      try {
        await loginWithCredentials(page);
      } catch (error) {
        results.warnings.push({ step: 'Login', message: error.message });
        console.warn(`⚠️  Login form warning: ${error.message}`);
      }
    }

    // Test Step 1
    const step1Success = await testStep1(page);
    if (!step1Success) {
      throw new Error('Step 1 failed');
    }
    
    // Submit to go to Step 2
    const nextButton = await page.$('button[type="submit"], input[type="submit"]');
    if (nextButton) {
      await nextButton.click();
      await page.waitForTimeout(3000);
    }
    
    // Test Step 2
    setupConsoleCapture(page, 'Step 2');
    await testStep2(page);
    
    // Note: Steps 3-8 would be tested similarly
    // For now, focusing on finding validation conflicts
    
  } catch (error) {
    console.error(`\n❌ Test suite failed: ${error.message}`);
    results.errors.push({ step: 'Test Suite', message: error.message, type: 'fatal' });
  }
  
  await browser.close();
  
  // Print summary
  printSummary();
  
  return results.errors.length === 0;
}

/**
 * Print test summary
 */
function printSummary() {
  console.log('\n=== Test Summary ===');
  console.log(`Total Errors:          ${results.errors.length}`);
  console.log(`Total Warnings:        ${results.warnings.length}`);
  console.log(`Validation Issues:     ${results.validationIssues.length}`);
  
  if (results.errors.length > 0) {
    console.log('\n=== Errors ===');
    results.errors.forEach((err, i) => {
      console.log(`${i + 1}. [${err.step}] ${err.message}`);
    });
  }
  
  if (results.validationIssues.length > 0) {
    console.log('\n=== Validation Issues ===');
    results.validationIssues.forEach((issue, i) => {
      console.log(`${i + 1}. [${issue.step}] ${issue.message}`);
    });
  }
  
  console.log('\n=== Recommendations ===');
  console.log('1. Standardize validation to server-side using SchemaLoader');
  console.log('2. Remove duplicate client-side validation in JS files');
  console.log('3. Use HTML5 attributes (required, pattern) for basic UI feedback');
  console.log('4. AJAX endpoints should return consistent validation errors');
}

// Run tests
runTests()
  .then(success => {
    console.log(success ? '\n✓ All tests passed' : '\n✗ Tests failed');
    process.exit(success ? 0 : 1);
  })
  .catch(error => {
    console.error('\n❌ Fatal error:', error);
    process.exit(1);
  });
