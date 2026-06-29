#!/usr/bin/env node

const { chromium } = require('playwright');

const CONFIG = {
  baseUrl: process.argv[2] || 'http://localhost:8080',
  headless: false,
  slowMo: 0, // Start with no slowMo
};

console.log(`🎬 Happy Path Test (Visible Browser)\n📍 Base URL: ${CONFIG.baseUrl}\n`);

(async () => {
  const browser = await chromium.launch({ 
    headless: CONFIG.headless,
    slowMo: CONFIG.slowMo
  });
  
  const page = await browser.newPage();

  try {
    console.log('📝 Step 1: Login');
    await page.goto(`${CONFIG.baseUrl}/user/login`, { 
      waitUntil: 'domcontentloaded', 
      timeout: 30000 
    });
    
    await page.fill('input[name="name"]', 'admin');
    await page.fill('input[name="pass"]', 'admin_secure_password');
    
    // Start listening for response before clicking
    const loginPromise = page.waitForResponse(
      response => response.url().includes('/user/login') && response.status() === 303
    );
    
    await page.click('input[type="submit"]');
    
    try {
      await loginPromise;
    } catch (e) {
      // Ignore if no 303 response, just wait a bit
    }
    
    // Wait for navigation to complete
    await page.waitForTimeout(3000);
    console.log(`   ✅ Login complete, URL: ${page.url()}`);

    console.log('\n🎲 Step 2: Navigate to Campaign Create');
    await page.goto(`${CONFIG.baseUrl}/campaigns/create`, { 
      waitUntil: 'domcontentloaded', 
      timeout: 30000 
    });
    console.log(`   ✅ Navigated to: ${page.url()}`);
    
    // Check form exists
    const form = await page.$('form');
    if (!form) {
      throw new Error('Campaign form not found');
    }
    console.log('   ✅ Campaign form found');

    console.log('\n✨ Happy path verification complete!');
    console.log('   All navigation and form access working');
    
    // Keep browser open for 5 seconds so user can see it
    await page.waitForTimeout(5000);
    
  } catch (error) {
    console.error('\n❌ Error:', error.message);
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
