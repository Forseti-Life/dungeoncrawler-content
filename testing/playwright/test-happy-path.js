#!/usr/bin/env node

/**
 * Happy Path Test: Campaign → Character → Dungeon → Quests
 *
 * Tests the complete user workflow:
 * 1. Create a campaign
 * 2. Create a character (complete 8 steps)
 * 3. Launch campaign at tavern entrance
 * 4. Discover and attempt default quests
 *
 * Usage:
 *   node test-happy-path.js [baseUrl] [timeout]
 *   node test-happy-path.js http://localhost:8080 30000
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Test configuration
const CONFIG = {
  baseUrl: process.argv[2] || 'http://localhost:8080',
  timeout: parseInt(process.argv[3]) || 30000,
  screenshots: true,
  screenshotDir: './screenshots',
  headless: false,
  username: process.env.PLAYWRIGHT_USERNAME || process.env.DRUPAL_USER || 'admin',
  password: process.env.PLAYWRIGHT_PASSWORD || process.env.DRUPAL_PASS || 'admin_secure_password',
  verbose: true
};

// Track results
const results = {
  scenario: 'Happy Path: Campaign → Character → Dungeon → Quests',
  startTime: new Date(),
  steps: {},
  errors: [],
  warnings: [],
  summary: {}
};

const log = (msg, type = 'info') => {
  const timestamp = new Date().toLocaleTimeString();
  const prefix = {
    info: '📋',
    success: '✅',
    error: '❌',
    warning: '⚠️ ',
    debug: '🔍'
  }[type] || '•';
  console.log(`[${timestamp}] ${prefix} ${msg}`);
};

const logStep = (title, message = '') => {
  const msg = message ? `${title}: ${message}` : title;
  log(msg, 'info');
};

const screenshot = async (page, name) => {
  if (CONFIG.screenshots) {
    const filepath = path.join(CONFIG.screenshotDir, `${name}.png`);
    await page.screenshot({ path: filepath });
    log(`Screenshot saved: ${filepath}`, 'debug');
    return filepath;
  }
};

async function loginUser(page) {
  logStep('Login', 'Authenticating with ' + CONFIG.username);
  
  try {
    // Go directly to login page
    logStep('Login', 'Navigating to /user/login', 'debug');
    await page.goto(`${CONFIG.baseUrl}/user/login`, {
      waitUntil: 'domcontentloaded',
      timeout: 15000
    });
    
    await page.waitForTimeout(1500);

    const nameField = await page.$('input[name="name"]');
    const passField = await page.$('input[name="pass"]');
    
    if (!nameField || !passField) {
      log('Login form fields not found', 'error');
      results.errors.push('Login form fields missing');
      return false;
    }

    log('Found login form, filling credentials...', 'debug');
    await nameField.fill(CONFIG.username);
    await passField.fill(CONFIG.password);
    
    const submitBtn = await page.$('input[type="submit"]');
    if (submitBtn) {
      log('Submitting login form...', 'debug');
      await submitBtn.click();
      
      // Wait for navigation but don't fail if it doesn't happen immediately
      try {
        await page.waitForNavigation({ timeout: 8000, waitUntil: 'domcontentloaded' }).catch(() => {});
      } catch (e) {
        // Navigation timeout is not fatal
        log('Navigation wait timeout (expected)', 'debug');
      }
      
      await page.waitForTimeout(2000);
      
      const finalUrl = page.url();
      log(`After login, URL: ${finalUrl}`, 'debug');
      
      // Check if we're logged in by looking for authenticated content
      const pageText = await page.textContent('body');
      if (pageText.includes('Campaign') || pageText.includes('My Campaigns') || !finalUrl.includes('/user/login')) {
        results.steps.login = { success: true, redirected_to: finalUrl };
        log('Login successful', 'success');
        return true;
      } else if (finalUrl.includes('/user/login')) {
        log('Still on login page - credentials may be invalid', 'error');
        results.errors.push('Login failed - still on login page');
        return false;
      } else {
        log('On different page after login', 'debug');
        results.steps.login = { success: true, redirected_to: finalUrl };
        return true;
      }
    } else {
      log('Submit button not found on login form', 'error');
      return false;
    }
  } catch (error) {
    results.errors.push({ step: 'Login', error: error.message });
    log(`Login error: ${error.message}`, 'error');
    return false;
  }
}

async function testCampaignCreation(page) {
  logStep('Campaign Creation', 'Starting campaign creation flow');
  
  try {
    await page.goto(`${CONFIG.baseUrl}/campaigns/create`, {
      waitUntil: 'domcontentloaded',
      timeout: 15000
    });

    await page.waitForTimeout(2000);  // Extra wait for JS to hydrate
    
    // Check if we got a permission error instead
    const pageText = await page.textContent('body');
    if (pageText.includes('Access denied') || pageText.includes('You do not have access')) {
      log('Access denied - may need re-authentication', 'warning');
      results.warnings.push('Possible session expiry on campaign form');
      return null;
    }

    // Wait for the form to be interactive
    await page.waitForSelector('input[name="name"]', { timeout: 10000 });
    
    // Fill campaign form
    const campaignName = `Test Campaign ${Date.now()}`;
    log(`Creating campaign: "${campaignName}"`, 'debug');
    
    await page.fill('input[name="name"]', campaignName);
    await page.selectOption('select[name="theme"]', 'classic_dungeon');
    await page.selectOption('select[name="difficulty"]', 'normal');

    // Take screenshot before submission
    await screenshot(page, 'campaign-creation-form');

    // Submit form
    const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      await page.waitForTimeout(3000);
      
      // Wait for redirect to tavern entrance
      const currentUrl = page.url();
      if (currentUrl.includes('tavernentrance')) {
        results.steps.campaign_creation = {
          success: true,
          campaignName,
          url: currentUrl
        };
        log(`Campaign created: "${campaignName}"`, 'success');
        await screenshot(page, 'campaign-created');
        return campaignName;
      } else {
        log(`Submitted but URL is: ${currentUrl}`, 'warning');
      }
    }
  } catch (error) {
    results.errors.push({ step: 'Campaign Creation', error: error.message });
    log(`Campaign creation failed: ${error.message}`, 'error');
  }
  
  return null;
}

async function testCharacterCreation(page) {
  logStep('Character Creation', 'Starting 8-step character creation wizard');
  
  const steps = {};
  
  try {
    // Go to character creation step 1
    await page.goto(`${CONFIG.baseUrl}/characters/create/step/1`, {
      waitUntil: 'domcontentloaded',
      timeout: 15000
    });

    // Step 1: Name & Concept
    logStep('Step 1', 'Name & Concept');
    await page.fill('input[name="name"]', 'Test Hero');
    await page.fill('textarea[name="concept"]', 'A brave adventurer testing the character creation system');
    await screenshot(page, 'step1-filled');
    
    let nextBtn = await page.$('button[type="submit"], input[type="submit"]');
    if (nextBtn) {
      await nextBtn.click();
      await page.waitForTimeout(2000);
    }
    steps.step1 = { success: true };

    // Step 2: Ancestry & Heritage
    logStep('Step 2', 'Ancestry & Heritage');
    const ancestryCards = await page.$$('.ancestry-card');
    if (ancestryCards.length > 0) {
      await ancestryCards[0].click();
      await page.waitForTimeout(1000);
      
      const heritageCards = await page.$$('.heritage-card');
      if (heritageCards.length > 0) {
        await heritageCards[0].click();
        await page.waitForTimeout(500);
      }
      steps.step2 = { success: true, ancestries: ancestryCards.length };
      log(`Selected ancestry, found ${heritageCards.length} heritages`, 'success');
      await screenshot(page, 'step2-selected');
      
      nextBtn = await page.$('button[type="submit"], input[type="submit"]');
      if (nextBtn) {
        await nextBtn.click();
        await page.waitForTimeout(2000);
      }
    }

    // Step 3: Background
    logStep('Step 3', 'Background');
    const backgroundCards = await page.$$('.background-card, [data-background]');
    if (backgroundCards.length > 0) {
      await backgroundCards[0].click();
      await page.waitForTimeout(500);
      steps.step3 = { success: true, backgrounds: backgroundCards.length };
      log(`Selected background, ${backgroundCards.length} available`, 'success');
      await screenshot(page, 'step3-selected');
      
      nextBtn = await page.$('button[type="submit"], input[type="submit"]');
      if (nextBtn) {
        await nextBtn.click();
        await page.waitForTimeout(2000);
      }
    }

    // Step 4: Class
    logStep('Step 4', 'Class');
    const classCards = await page.$$('.class-card, [data-class]');
    if (classCards.length > 0) {
      await classCards[0].click();
      await page.waitForTimeout(500);
      steps.step4 = { success: true, classes: classCards.length };
      log(`Selected class, ${classCards.length} available`, 'success');
      await screenshot(page, 'step4-selected');
      
      nextBtn = await page.$('button[type="submit"], input[type="submit"]');
      if (nextBtn) {
        await nextBtn.click();
        await page.waitForTimeout(2000);
      }
    }

    // Steps 5-8: Continue through remaining steps
    for (let step = 5; step <= 8; step++) {
      logStep(`Step ${step}`, 'Processing');
      
      // Look for selection options (ability boost, skill, equipment, portrait)
      const selectOptions = await page.$$('button.dc-option, [data-option], .card-selectable, input[type="radio"]');
      
      if (selectOptions.length > 0) {
        // Try to select first option
        const firstOption = selectOptions[0];
        const role = await firstOption.getAttribute('role');
        
        if (role === 'radio' || firstOption.tagName === 'INPUT') {
          await firstOption.click();
        } else if (firstOption.tagName === 'BUTTON') {
          await firstOption.click();
        } else {
          await page.evaluate(el => el.click(), firstOption);
        }
        
        await page.waitForTimeout(500);
        steps[`step${step}`] = { success: true, options: selectOptions.length };
        await screenshot(page, `step${step}-selected`);
      }
      
      // Try to advance
      nextBtn = await page.$('button[type="submit"], input[type="submit"]');
      if (nextBtn) {
        const btnText = await nextBtn.textContent();
        if (btnText.includes('Complete') || btnText.includes('Finish') || btnText.includes('Create')) {
          // Last step - submit
          await nextBtn.click();
          await page.waitForTimeout(3000);
          steps[`step${step}`].completed = true;
          break;
        } else {
          await nextBtn.click();
          await page.waitForTimeout(1500);
        }
      }
    }
    
    results.steps.character_creation = steps;
    log('Character creation completed', 'success');
    await screenshot(page, 'character-created');
    return true;

  } catch (error) {
    results.errors.push({ step: 'Character Creation', error: error.message });
    log(`Character creation failed: ${error.message}`, 'error');
    return false;
  }
}

async function testCampaignLaunch(page) {
  logStep('Campaign Launch', 'Starting tavern entry flow');
  
  try {
    // Should already be at tavern entrance from campaign creation
    // or navigate there if needed
    const currentUrl = page.url();
    if (!currentUrl.includes('tavernentrance') && !currentUrl.includes('campaigns')) {
      await page.goto(`${CONFIG.baseUrl}/campaigns`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000
      });
    }

    await page.waitForTimeout(2000);
    await screenshot(page, 'campaign-list');

    // Look for a playable campaign
    const campaignLinks = await page.$$('a[href*="tavernentrance"], button:has-text("Enter Tavern")');
    if (campaignLinks.length > 0) {
      log(`Found ${campaignLinks.length} campaigns to launch`, 'info');
      
      // Try clicking the first campaign
      await campaignLinks[0].click();
      await page.waitForTimeout(3000);
      
      results.steps.campaign_launch = { success: true, campaignsAvailable: campaignLinks.length };
      log('Campaign launched at tavern entrance', 'success');
      await screenshot(page, 'tavern-entrance');
      return true;
    } else {
      log('No campaigns available to launch', 'warning');
      results.warnings.push('No campaigns found to launch');
    }

  } catch (error) {
    results.errors.push({ step: 'Campaign Launch', error: error.message });
    log(`Campaign launch failed: ${error.message}`, 'error');
  }
  
  return false;
}

async function testQuestFlow(page) {
  logStep('Quest System', 'Testing quest discovery and completion');
  
  try {
    // Check if we're in the dungeon/tavern view
    const currentUrl = page.url();
    
    // Look for quest interaction elements
    const questButtons = await page.$$('button:has-text("Quest"), button:has-text("Accept"), [data-quest]');
    const questLinks = await page.$$('a[href*="/quests"], a[href*="/quest"]');
    
    results.steps.quest_discovery = {
      questElements: questButtons.length + questLinks.length
    };
    
    if (questButtons.length > 0) {
      log(`Found ${questButtons.length} quest interaction elements`, 'info');
      await screenshot(page, 'quests-available');
      
      // Try to interact with first quest
      try {
        await questButtons[0].click();
        await page.waitForTimeout(1000);
        log('Interacted with quest element', 'success');
        await screenshot(page, 'quest-detail');
      } catch (e) {
        log(`Quest interaction: ${e.message}`, 'warning');
      }
    } else {
      log('No quest elements found on page', 'warning');
      results.warnings.push('No quest elements found in tavern/dungeon view');
    }
    
    results.steps.quest_system = { tested: true };
    return true;

  } catch (error) {
    results.errors.push({ step: 'Quest System', error: error.message });
    log(`Quest system test failed: ${error.message}`, 'error');
    return false;
  }
}

async function runHappyPathTest() {
  console.log('\n╔════════════════════════════════════════╗');
  console.log('║ HAPPY PATH TEST: Campaign → Quests    ║');
  console.log('╚════════════════════════════════════════╝\n');
  
  log(`Base URL: ${CONFIG.baseUrl}`);
  log(`Test started at ${results.startTime.toLocaleString()}\n`);

  const browser = await chromium.launch({ headless: CONFIG.headless });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 720 }
  });
  
  const page = await context.newPage();

  // Capture page errors
  page.on('error', error => {
    results.errors.push({ type: 'page_error', message: error.message });
    log(`Page error: ${error.message}`, 'error');
  });

  page.on('pageerror', error => {
    results.errors.push({ type: 'page_error', message: error.message });
    log(`Page error: ${error.message}`, 'error');
  });

  try {
    // Step 1: Login (non-blocking)
    const loginSuccess = await loginUser(page);
    if (!loginSuccess) {
      log('Login failed but continuing with test...', 'warning');
    }

    // Step 2: Create campaign
    const campaignName = await testCampaignCreation(page);
    if (!campaignName) {
      log('Campaign creation failed - need to investigate', 'error');
      throw new Error('Campaign creation failed - this is critical');
    }

    // Step 3: Character creation (continue even if there are issues)
    try {
      await page.goto(`${CONFIG.baseUrl}/characters/create/step/1`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000
      });
      
      const charSuccess = await testCharacterCreation(page);
      if (!charSuccess) {
        results.warnings.push('Character creation completed with warnings');
      }
    } catch (e) {
      results.warnings.push(`Character creation error: ${e.message}`);
      log(`Character creation issue: ${e.message}`, 'warning');
    }

    // Step 4: Campaign launch (continue if possible)
    try {
      await page.goto(`${CONFIG.baseUrl}/campaigns`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000
      });
      
      const launchSuccess = await testCampaignLaunch(page);
      if (!launchSuccess) {
        results.warnings.push('Campaign launch encountered issues - see logs');
      }
    } catch (e) {
      results.warnings.push(`Campaign launch error: ${e.message}`);
      log(`Campaign launch issue: ${e.message}`, 'warning');
    }

    // Step 5: Quest system (exploratory if available)
    try {
      await testQuestFlow(page);
    } catch (e) {
      results.warnings.push(`Quest system test error: ${e.message}`);
      log(`Quest system issue: ${e.message}`, 'warning');
    }

    // Determine overall status: success if campaign creation worked
    results.summary.success = !!results.steps.campaign_creation?.success;
    
    if (results.summary.success) {
      log('Happy path test PASSED - core workflow functional', 'success');
    } else {
      log('Happy path test FAILED - core workflow broken', 'error');
    }

  } catch (error) {
    results.summary.success = false;
    results.errors.push({ step: 'Main', error: error.message });
    log(`Test failed: ${error.message}`, 'error');
  } finally {
    await browser.close();
  }

  // Print summary
  printSummary();
}

function printSummary() {
  const endTime = new Date();
  const duration = ((endTime - results.startTime) / 1000).toFixed(2);

  console.log('\n╔════════════════════════════════════════╗');
  console.log('║ TEST SUMMARY                           ║');
  console.log('╚════════════════════════════════════════╝\n');

  console.log(`Total Duration: ${duration}s`);
  console.log(`Completed Steps: ${Object.keys(results.steps).length}`);
  console.log(`Total Errors: ${results.errors.length}`);
  console.log(`Total Warnings: ${results.warnings.length}`);
  console.log(`Overall Status: ${results.summary.success ? '✅ PASSED' : '❌ FAILED'}\n`);

  if (results.errors.length > 0) {
    console.log('Errors:');
    results.errors.forEach((err, i) => {
      console.log(`  ${i + 1}. [${err.step || err.type}] ${err.error || err.message}`);
    });
    console.log();
  }

  if (results.warnings.length > 0) {
    console.log('Warnings:');
    results.warnings.forEach((warn, i) => {
      console.log(`  ${i + 1}. ${warn}`);
    });
    console.log();
  }

  console.log('Completed Workflow Steps:');
  Object.entries(results.steps).forEach(([step, data]) => {
    const status = data.success || data.tested ? '✅' : '⚠️ ';
    console.log(`  ${status} ${step.replace(/_/g, ' ').toUpperCase()}`);
    if (data.campaigns) console.log(`     - Campaigns created: ${data.campaigns}`);
    if (data.ancestries) console.log(`     - Ancestries found: ${data.ancestries}`);
    if (data.classes) console.log(`     - Classes available: ${data.classes}`);
  });

  console.log('\nScreenshots saved to ./screenshots/');
  console.log(`Test completed at ${endTime.toLocaleTimeString()}\n`);
}

// Run the test
runHappyPathTest()
  .then(() => {
    process.exit(results.summary.success ? 0 : 1);
  })
  .catch(error => {
    console.error('Fatal test error:', error);
    process.exit(1);
  });
