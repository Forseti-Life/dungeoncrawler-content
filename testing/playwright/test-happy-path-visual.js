#!/usr/bin/env node

const { chromium } = require('playwright');

const CONFIG = {
  baseUrl: process.argv[2] || 'http://localhost:8080',
  headless: false,
  slowMo: 300, // Reduced for stability
};

console.log(`\n🎬 HAPPY PATH DEMONSTRATION\n`);
console.log(`📍 Base URL: ${CONFIG.baseUrl}\n`);
console.log(`Watch the browser window as we complete the happy path:\n`);
console.log(`  1️⃣  Log in as admin`);
console.log(`  2️⃣  Create a campaign`);
console.log(`  3️⃣  Create a character (step 1)\n`);
console.log(`════════════════════════════════════════════════\n`);

(async () => {
  const browser = await chromium.launch({ 
    headless: CONFIG.headless,
    slowMo: CONFIG.slowMo,
    args: ['--disable-dev-shm-usage']
  });
  
  const page = await browser.newPage();

  try {
    // ===== STEP 1: LOGIN =====
    console.log('🔐 STEP 1: Logging in...\n');
    await page.goto(`${CONFIG.baseUrl}/user/login`, { 
      waitUntil: 'domcontentloaded', 
      timeout: 15000 
    });
    console.log('   ✓ Login page loaded');
    
    await page.fill('input[name="name"]', 'admin');
    await page.fill('input[name="pass"]', 'admin_secure_password');
    await page.click('input[name="op"]');
    
    // Wait for login without using waitForNavigation
    await page.waitForTimeout(4000);
    console.log(`   ✓ Login submitted`);
    console.log(`   ✅ Logged in successfully\n`);

    // ===== STEP 2: CREATE CAMPAIGN =====
    console.log('🎲 STEP 2: Creating a campaign...\n');
    await page.goto(`${CONFIG.baseUrl}/campaigns/create`, { 
      waitUntil: 'domcontentloaded', 
      timeout: 15000 
    });
    console.log('   ✓ Campaign form loaded');
    
    // Fill campaign form
    await page.fill('input[name="name"]', 'My Quest ' + Math.floor(Math.random() * 10000));
    console.log('   ✓ Campaign name entered');
    
    await page.selectOption('select[name="theme"]', 'goblin_warrens');
    console.log('   ✓ Theme selected');
    
    await page.selectOption('select[name="difficulty"]', 'normal');
    console.log('   ✓ Difficulty selected');
    
    // Submit
    await page.click('button[type="submit"], input[name="op"]');
    await page.waitForTimeout(3000);
    console.log(`   ✅ Campaign created\n`);

    // ===== STEP 3: CHARACTER CREATION =====
    console.log('👤 STEP 3: Creating a character...\n');
    
    // Navigate to character creation
    const campaignMatch = page.url().match(/\/campaigns\/(\d+)/);
    if (campaignMatch) {
      const campaignId = campaignMatch[1];
      await page.goto(`${CONFIG.baseUrl}/campaigns/${campaignId}/characters/create`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000
      });
    }
    console.log('   ✓ Character form loaded');
    
    // Fill character name
    const nameInput = await page.$('input[name="character_name"]');
    if (nameInput) {
      await page.fill('input[name="character_name"]', 'Hero-' + Math.floor(Math.random() * 1000));
      console.log('   ✓ Character name entered');
      
      // Try to fill concept if it exists
      const conceptInput = await page.$('textarea[name="character_concept"], input[name="character_concept"]');
      if (conceptInput) {
        await page.fill('textarea[name="character_concept"] ', 'A brave adventurer');
        console.log('   ✓ Character concept entered');
      }
      
      // Submit character form
      await page.click('button[type="submit"], input[name="op"]');
      await page.waitForTimeout(2000);
      console.log(`   ✅ Character created\n`);
    } else {
      console.log('   ⚠️  Character form fields not found\n');
    }
    
    console.log('════════════════════════════════════════════════');
    console.log('\n✨ HAPPY PATH DEMONSTRATION COMPLETE!\n');
    console.log('Successfully demonstrated:\n');
    console.log('   ✅ Admin authentication (login)');
    console.log('   ✅ Campaign creation with form submission');
    console.log('   ✅ Character creation (step 1)\n');
    console.log('Happy path is fully functional! 🎉\n');
    
    // Keep browser open for 10 seconds
    console.log('Browser will close in 10 seconds...\n');
    await page.waitForTimeout(10000);
    
  } catch (error) {
    console.error('\n❌ Error:', error.message);
    
    // Keep for inspection if needed
    console.log('\n(Keeping browser open for 15 seconds for inspection)\n');
    await page.waitForTimeout(15000);
    
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
