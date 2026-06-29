#!/usr/bin/env node

/**
 * Simplified Happy Path Test - Working Version
 * 
 * Tests campaign → character → quest workflow
 * Using working credentials from copilot-instructions.md
 */

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
  baseUrl: process.argv[2] || 'http://localhost:8080',
  username: 'admin',
  password: 'admin_secure_password',
  headless: parseBool(process.env.PLAYWRIGHT_HEADLESS, true),
  screenshotDir: './screenshots',
  slowMo: parseIntFallback(process.env.PLAYWRIGHT_SLOWMO, 0),
  navTimeout: parseIntFallback(process.env.PLAYWRIGHT_NAV_TIMEOUT, 30000)
};

const log = (msg, type = 'info') => {
  const types = {
    info: '📋',
    success: '✅',
    error: '❌',
    warning: '⚠️',
    debug: '🔍'
  };
  console.log(`${types[type] || '•'} ${msg}`);
};

const gotoWithRetry = async (page, url, options, retries = 2) => {
  let lastError = null;
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      await page.goto(url, options);
      return;
    } catch (error) {
      lastError = error;
      if (attempt < retries) {
        await page.waitForTimeout(1500);
      }
    }
  }
  throw lastError;
};

async function runTest() {
  console.log('\n╔════════════════════════════════════════╗');
  console.log('║ HAPPY PATH TEST - SIMPLIFIED          ║');
  console.log('╚════════════════════════════════════════╝\n');

  log(`Starting test against ${CONFIG.baseUrl}`);
  log(`Username: ${CONFIG.username}\n`);

  const browser = await chromium.launch({ headless: CONFIG.headless, slowMo: CONFIG.slowMo });
  const page = await browser.newPage();
  
  try {
    // Step 1: Login
    log('Step 1: Login', 'info');
    await gotoWithRetry(page, `${CONFIG.baseUrl}/user/login`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
    await page.waitForTimeout(1000);
    
    await page.fill('input[name="name"]', CONFIG.username);
    await page.fill('input[name="pass"]', CONFIG.password);
    
    const submitBtn = await page.$('input[type="submit"]');
    if (submitBtn) {
      await page.click('input[type="submit"]', { noWaitAfter: true });
      await page.waitForTimeout(1500);

      const loginMarker = await page.waitForSelector('body.user-logged-in, a[href*="/user/logout"]', {
        timeout: CONFIG.navTimeout
      }).catch(() => null);

      const url = page.url();
      if (loginMarker || !url.includes('/user/login')) {
        log('Login successful, redirected', 'success');
      } else {
        const errorText = await page.textContent('.messages, .alert, [role="alert"]').catch(() => '');
        throw new Error(`Login failed${errorText ? `: ${errorText.trim()}` : ''}`);
      }
    }

    // Step 2: Create Campaign
    log('\nStep 2: Campaign Creation', 'info');
    await gotoWithRetry(page, `${CONFIG.baseUrl}/campaigns/create`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
    if (page.url().includes('/user/login')) {
      throw new Error('Login session missing when opening campaign create');
    }
    await page.waitForTimeout(1000);

    const campaignName = `Test Campaign ${Date.now()}`;
    await page.fill('input[name="name"]', campaignName);
    await page.selectOption('select[name="theme"]', 'classic_dungeon');
    await page.selectOption('select[name="difficulty"]', 'normal');

    log(`Submitting campaign: "${campaignName}"`, 'debug');

    const createBtn = await page.$('button[type="submit"], input[type="submit"]');
    if (createBtn) {
      await createBtn.click();
      await page.waitForTimeout(2000);
    }

    // Resolve campaign id from list
    await gotoWithRetry(page, `${CONFIG.baseUrl}/campaigns`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
    await page.waitForTimeout(1000);

    const campaignHref = await page.locator(`a.dc-character-card:has-text("${campaignName}")`).getAttribute('href');
    if (!campaignHref) {
      throw new Error('Campaign card not found in list');
    }

    const campaignMatch = campaignHref.match(/\/campaigns\/(\d+)\//);
    if (!campaignMatch) {
      throw new Error(`Unable to parse campaign ID from ${campaignHref}`);
    }

    const campaignId = campaignMatch[1];
    log(`Campaign created: ${campaignId}`, 'success');

    // Step 3: Resolve Character
    log('\nStep 3: Character Selection', 'info');
    await gotoWithRetry(page, `${CONFIG.baseUrl}/characters`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
    await page.waitForTimeout(1000);

    let characterHref = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('a[href^="/characters/"]'));
      const match = links.find((link) => /\/characters\/\d+/.test(link.getAttribute('href') || ''));
      return match ? match.getAttribute('href') : null;
    });
    let characterId = characterHref ? characterHref.match(/\/characters\/(\d+)/)?.[1] : null;

    if (!characterId) {
      log('No existing characters found, creating a new one', 'warning');
      await gotoWithRetry(page, `${CONFIG.baseUrl}/characters/create`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
      await page.waitForTimeout(1000);

      const characterName = `Quest Hero ${Date.now()}`;
      await page.fill('input[name="name"]', characterName);

      const ancestrySelect = await page.$('select[name="ancestry"]');
      const classSelect = await page.$('select[name="class"]');

      if (ancestrySelect && classSelect) {
        const ancestryValue = await page.$eval('select[name="ancestry"]', (select) => {
          return select.options.length > 1 ? select.options[1].value : '';
        });
        const classValue = await page.$eval('select[name="class"]', (select) => {
          return select.options.length > 1 ? select.options[1].value : '';
        });

        if (!ancestryValue || !classValue) {
          throw new Error('Character form missing ancestry or class options');
        }

        await page.selectOption('select[name="ancestry"]', ancestryValue);
        await page.selectOption('select[name="class"]', classValue);
        await page.fill('input[name="background"]', 'Tavern Helper');
        await page.fill('textarea[name="backstory"]', 'Testing quest completion.');
      }

      const characterSubmit = await page.$('button[type="submit"], input[type="submit"]');
      if (!characterSubmit) {
        throw new Error('Character submit button not found');
      }

      await characterSubmit.click();
      await page.waitForTimeout(2000);

      const characterUrl = page.url();
      const characterMatch = characterUrl.match(/\/characters\/(\d+)/);
      if (!characterMatch) {
        throw new Error(`Unable to parse character ID from ${characterUrl}`);
      }

      characterId = characterMatch[1];
    }

    log(`Character ready: ${characterId}`, 'success');

    // Step 4: Enter Tavern / Hexmap
    log('\nStep 4: Enter Tavern', 'info');
    await gotoWithRetry(page, `${CONFIG.baseUrl}/campaigns/${campaignId}/select-character/${characterId}`, { waitUntil: 'domcontentloaded', timeout: CONFIG.navTimeout });
    await page.waitForTimeout(3000);

    const canvasVisible = await page.locator('canvas').first().isVisible().catch(() => false);
    if (!canvasVisible) {
      log('Hexmap canvas not visible (continuing)', 'warning');
    } else {
      log('Hexmap loaded', 'success');
    }

    // Step 5: Quest Complete Flow
    log('\nStep 5: Quest Completion', 'info');
    const questFlow = await page.evaluate(async ({ campaignId, characterId }) => {
      const response = await fetch(`/api/campaign/${campaignId}/quests/available`);
      if (!response.ok) {
        const text = await response.text();
        return { error: `Available quests failed (${response.status})`, detail: text.slice(0, 200) };
      }

      const available = await response.json();
      if (!available.success || !available.quests?.length) {
        return { error: 'No available quests' };
      }

      const quest = available.quests[0];
      const startRes = await fetch(`/api/campaign/${campaignId}/quests/${quest.quest_id}/start`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ character_id: Number(characterId) })
      });
      if (!startRes.ok) {
        const text = await startRes.text();
        return { error: `Start quest failed (${startRes.status})`, detail: text.slice(0, 200) };
      }
      const startJson = await startRes.json();
      if (!startJson.success) {
        return { error: startJson.error || 'Failed to start quest' };
      }

      const completeRes = await fetch(`/api/campaign/${campaignId}/quests/${quest.quest_id}/complete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entity_id: Number(characterId), outcome: 'success' })
      });
      if (!completeRes.ok) {
        const text = await completeRes.text();
        return { error: `Complete quest failed (${completeRes.status})`, detail: text.slice(0, 200) };
      }
      const completeJson = await completeRes.json();
      if (!completeJson.success) {
        return { error: completeJson.error || 'Failed to complete quest' };
      }

      const chatRes = await fetch(`/api/campaign/${campaignId}/room/tavern_entrance/chat`);
      if (!chatRes.ok) {
        const text = await chatRes.text();
        return { error: `Chat history failed (${chatRes.status})`, detail: text.slice(0, 200) };
      }
      const chatJson = await chatRes.json();

      return {
        questId: quest.quest_id,
        questName: quest.quest_name,
        completeMessage: completeJson.message,
        chat: chatJson,
      };
    }, { campaignId, characterId });

    if (questFlow?.error) {
      const detail = questFlow.detail ? `: ${questFlow.detail}` : '';
      throw new Error(`${questFlow.error}${detail}`);
    }

    const chatMessages = questFlow.chat?.data?.messages || [];
    const questDialog = chatMessages.find((msg) => (msg.message || '').includes('Quest complete'));
    if (!questDialog) {
      throw new Error('Quest complete dialog not found in room chat');
    }

    log(`Quest completed: ${questFlow.questId}`, 'success');
    log(`Quest dialog: ${questDialog.speaker}: ${questDialog.message}`, 'success');

    log('\n✅ HAPPY PATH TEST COMPLETE', 'success');
    log('Quest completion confirmed with NPC dialog\n', 'info');

  } catch (error) {
    log(`Test error: ${error.message}`, 'error');
    console.log(error);
  } finally {
    await browser.close();
  }
}

runTest()
  .then(() => process.exit(0))
  .catch(error => {
    log(`Fatal error: ${error.message}`, 'error');
    process.exit(1);
  });
