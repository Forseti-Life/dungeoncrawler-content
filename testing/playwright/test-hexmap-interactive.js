#!/usr/bin/env node

/**
 * Interactive Hexmap UI Test
 * 
 * Tests hexmap interface interactivity including:
 * - Button clicks
 * - Chat message sending
 * - Element visibility
 * - Console error detection
 */

const { chromium } = require('playwright');

async function testHexmapInteractive(baseUrl = 'http://localhost:8080', waitTime = 5000) {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  let hasErrors = false;
  const errors = [];
  const testResults = [];

  // Capture console events
  page.on('console', msg => {
    if (msg.type() === 'error') {
      hasErrors = true;
      errors.push(msg.text());
      console.error(`[ERROR] ${msg.text()}`);
    }
  });

  page.on('pageerror', error => {
    hasErrors = true;
    errors.push(error.message);
    console.error(`[PAGE ERROR] ${error.message}`);
  });

  try {
    const hexmapUrl = `${baseUrl}/hexmap?campaign_id=2&character_id=4`;
    console.log(`\n🧪 Testing: ${hexmapUrl}\n`);
    
    // Navigate and wait
    await page.goto(hexmapUrl, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000); // Wait for ECS initialization

    // Test 1: Check canvas exists (hexmap rendering)
    try {
      const canvas = await page.locator('canvas').first();
      const visible = await canvas.isVisible();
      if (visible) {
        testResults.push({ name: 'Canvas renders', status: '✓ PASS' });
        console.log('✓ Canvas renders');
      } else {
        testResults.push({ name: 'Canvas renders', status: '✗ FAIL' });
        console.log('✗ Canvas not visible');
      }
    } catch (e) {
      testResults.push({ name: 'Canvas renders', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Canvas test failed: ${e.message}`);
    }

    // Test 2: Check action buttons
    try {
      const moveButton = await page.locator('#action-move');
      const attackButton = await page.locator('#action-attack');
      
      if (await moveButton.isVisible()) {
        testResults.push({ name: 'Move button exists', status: '✓ PASS' });
        console.log('✓ Move button visible');
      } else {
        testResults.push({ name: 'Move button exists', status: '✗ FAIL' });
        console.log('✗ Move button not visible');
      }

      if (await attackButton.isVisible()) {
        testResults.push({ name: 'Attack button exists', status: '✓ PASS' });
        console.log('✓ Attack button visible');
      } else {
        testResults.push({ name: 'Attack button exists', status: '✗ FAIL' });
        console.log('✗ Attack button not visible');
      }
    } catch (e) {
      testResults.push({ name: 'Action buttons', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Action buttons test failed: ${e.message}`);
    }

    // Test 3: Check chat interface
    try {
      const chatForm = await page.locator('#chat-form');
      const chatInput = await page.locator('#chat-input');
      const chatLog = await page.locator('#chat-log');

      if (await chatForm.isVisible()) {
        testResults.push({ name: 'Chat form visible', status: '✓ PASS' });
        console.log('✓ Chat form visible');
      } else {
        testResults.push({ name: 'Chat form visible', status: '✗ FAIL' });
        console.log('✗ Chat form not visible');
      }

      if (await chatInput.isVisible()) {
        testResults.push({ name: 'Chat input visible', status: '✓ PASS' });
        console.log('✓ Chat input visible');
      } else {
        testResults.push({ name: 'Chat input visible', status: '✗ FAIL' });
        console.log('✗ Chat input not visible');
      }

      if (await chatLog.isVisible()) {
        testResults.push({ name: 'Chat log visible', status: '✓ PASS' });
        console.log('✓ Chat log visible');
      } else {
        testResults.push({ name: 'Chat log visible', status: '✗ FAIL' });
        console.log('✗ Chat log not visible');
      }
    } catch (e) {
      testResults.push({ name: 'Chat interface', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Chat interface test failed: ${e.message}`);
    }

    // Test 4: Test sending chat message
    try {
      const chatInput = await page.locator('#chat-input');
      const sendButton = await page.locator('#chat-send');
      
      // Type test message
      await chatInput.fill('Test message from UI automation');
      console.log('✓ Chat message typed');
      
      // Get initial message count
      const chatLog = await page.locator('#chat-log');
      const beforeMessages = await page.locator('#chat-log .chat-line').count();
      console.log(`  Messages before send: ${beforeMessages}`);

      // Click send button
      await sendButton.click();
      console.log('✓ Send button clicked');
      
      // Wait for potential response
      await page.waitForTimeout(2000);
      
      // Check if message was added or input was cleared
      const inputValue = await chatInput.inputValue();
      const afterMessages = await page.locator('#chat-log .chat-line').count();
      
      if (inputValue === '') {
        testResults.push({ name: 'Chat send clears input', status: '✓ PASS' });
        console.log('✓ Input cleared after send');
      } else {
        testResults.push({ name: 'Chat send clears input', status: '✗ FAIL' });
        console.log('✗ Input not cleared');
      }

      testResults.push({ 
        name: 'Chat message log', 
        status: `ℹ Messages: ${beforeMessages} → ${afterMessages}` 
      });
      console.log(`  Messages after send: ${afterMessages}`);
      
    } catch (e) {
      testResults.push({ name: 'Chat message send', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Chat message test failed: ${e.message}`);
    }

    // Test 5: Check character sheet visibility
    try {
      const charSheet = await page.locator('[id*="character"]').first();
      const hpDisplay = await page.locator('text=HP').first();
      
      if (await hpDisplay.isVisible()) {
        testResults.push({ name: 'Character stats visible', status: '✓ PASS' });
        console.log('✓ Character stats visible');
      } else {
        testResults.push({ name: 'Character stats visible', status: '✗ FAIL' });
        console.log('✗ Character stats not visible');
      }
    } catch (e) {
      testResults.push({ name: 'Character sheet', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Character sheet test failed: ${e.message}`);
    }

    // Test 6: Test fullscreen button
    try {
      const fullscreenBtn = await page.locator('#fullscreen-toggle');
      if (await fullscreenBtn.isVisible()) {
        testResults.push({ name: 'Fullscreen button exists', status: '✓ PASS' });
        console.log('✓ Fullscreen button visible');
      } else {
        testResults.push({ name: 'Fullscreen button exists', status: '✗ FAIL' });
        console.log('✗ Fullscreen button not visible');
      }
    } catch (e) {
      testResults.push({ name: 'Fullscreen button', status: `✗ FAIL: ${e.message}` });
      console.log(`✗ Fullscreen button test failed: ${e.message}`);
    }

    await page.waitForTimeout(waitTime);

  } catch (error) {
    hasErrors = true;
    console.error(`❌ Navigation/setup failed: ${error.message}`);
    testResults.push({ name: 'Page navigation', status: `✗ FAIL: ${error.message}` });
  }

  await context.close();
  await browser.close();

  // Summary
  console.log('\n' + '='.repeat(60));
  console.log('📊 TEST SUMMARY');
  console.log('='.repeat(60));

  let passed = 0;
  let failed = 0;

  testResults.forEach(result => {
    console.log(`${result.status.includes('PASS') ? '✓' : result.status.includes('ℹ') ? 'ℹ' : '✗'} ${result.name}: ${result.status}`);
    if (result.status.includes('PASS')) passed++;
    if (result.status.includes('FAIL')) failed++;
  });

  console.log('='.repeat(60));
  console.log(`\nPassed: ${passed}`);
  console.log(`Failed: ${failed}`);
  console.log(`Errors: ${errors.length}`);

  if (errors.length > 0) {
    console.log('\n❌ Detected Errors:');
    errors.forEach(err => console.log(`  - ${err}`));
  }

  if (hasErrors && errors.length > 0) {
    return false;
  }

  console.log('\n✅ UI tests completed' + (failed === 0 ? ' - all critical tests passed' : ''));
  return true;
}

// Run test
const baseUrl = process.argv[2] || 'http://localhost:8080';
const waitTime = parseInt(process.argv[3]) || 5000;

testHexmapInteractive(baseUrl, waitTime)
  .then(success => process.exit(success ? 0 : 1))
  .catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
  });
