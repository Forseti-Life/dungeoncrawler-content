#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  // First, login
  console.log('Logging in as admin...');
  await page.goto('http://localhost:8080/user/login', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(1000);
  
  const nameField = await page.$('input[name="name"]');
  const passField = await page.$('input[name="pass"]');
  
  if (nameField && passField) {
    console.log('Found login form');
    await nameField.fill('admin');
    await passField.fill('admin_secure_password');
    
    const submitBtn = await page.$('input[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      await page.waitForTimeout(3000);
      console.log('Login submitted');
    }
  } else {
    console.log('Login form not found');
  }
  
  // Navigate to campaign create
  console.log('Navigating to http://localhost:8080/campaigns/create...');
  await page.goto('http://localhost:8080/campaigns/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(2000);
  
  // Look for form elements
  const inputs = await page.$$('input');
  const selects = await page.$$('select');
  const buttons = await page.$$('button, input[type="submit"]');
  const textareas = await page.$$('textarea');
  
  console.log('\n=== PAGE INSPECTION ===');  
  console.log('URL:', page.url());
  console.log('Input fields found:', inputs.length);
  console.log('Select fields found:', selects.length);
  console.log('Button fields found:', buttons.length);
  console.log('Textarea fields found:', textareas.length);
  
  // Get page content to analyze structure
  const bodyText = await page.textContent('body');
  if (bodyText.includes('Create Campaign')) {
    console.log('✓ Page contains "Create Campaign" text');
  } else if (bodyText.includes('Campaign')) {
    console.log('✓ Page contains "Campaign" text');
  } else {
    console.log('✗ Campaign-related text not found');
  }
  
  // Get page title
  const title = await page.title();
  console.log('Page title:', title);
  
  // List all input names
  console.log('\n=== Input Fields ===');
  for (let i = 0; i < Math.min(10, inputs.length); i++) {
    const name = await inputs[i].getAttribute('name');
    const type = await inputs[i].getAttribute('type');
    console.log(`  Input ${i}: name="${name}" type="${type}"`);
  }
  
  // List all select names
  console.log('\n=== Select Fields ===');
  for (let i = 0; i < selects.length; i++) {
    const name = await selects[i].getAttribute('name');
    const options = await selects[i].$$('option');
    console.log(`  Select ${i}: name="${name}" options: ${options.length}`);
  }
  
  // Check for form
  const form = await page.$('form');
  if (form) {
    const formId = await form.getAttribute('id');
    const formClass = await form.getAttribute('class');
    console.log('\n=== Form Info ===');
    console.log('Form ID:', formId);
    console.log('Form class:', formClass);
  }
  
  // Check for data attributes
  const formContainer = await page.$('[data-drupal-form-id], [role="form"]');
  if (formContainer) {
    console.log('\n✓ Found form container with data attributes');
    const drupalFormId = await formContainer.getAttribute('data-drupal-form-id');
    console.log('Drupal form ID:', drupalFormId);
  }
  
  // Get rendered content snippet
  console.log('\n=== Content Preview ===');
  const mainContent = await page.$('main, .main-content, .content');
  if (mainContent) {
    const preview = await mainContent.textContent();
    console.log(preview.substring(0, 300) + '...');
  }
  
  await browser.close();
  process.exit(0);
})();
