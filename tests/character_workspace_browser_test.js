/**
 * Focused live workspace check using one temporary, original fixture.
 * Run from the module: node tests/character_workspace_browser_test.js
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdirSync, writeFileSync } = require('node:fs');
const { join } = require('node:path');
const { chromium } = require('playwright');

const base = process.env.WIZARD_BASE_URL || 'https://dungeoncrawler.forseti.life';
const site = '/var/www/html/dungeoncrawler';
const artifacts = process.env.WIZARD_ARTIFACT_DIR;
function drush(...args) {
  return execFileSync(site + '/vendor/bin/drush', ['--root=' + site + '/web', '--uri=' + base, ...args],
    { cwd: site, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

async function main() {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  let fixtureId = null;
  let page;
  const errors = [];
  try {
    page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', async response => {
      if (response.status() >= 400 && response.url().startsWith(base)) {
        console.error('HTTP failure:', response.status(), new URL(response.url()).pathname, (await response.text()).slice(0, 1000));
      }
    });
    await page.goto(drush('user:login', '--uid=1', '--no-browser'));
    await page.goto(base + '/charactersetup');
    const created = await page.evaluate(async () => {
      const settings = drupalSettings.dungeoncrawlerCharacterGm;
      const response = await fetch(settings.createEndpoint, {
        method: 'POST', headers: { 'X-CSRF-Token': settings.csrfToken },
      });
      if (!response.ok) throw new Error('Fixture creation failed: ' + response.status);
      return response.json();
    });
    fixtureId = Number(created.draft.character_id);
    assert.ok(Number.isInteger(fixtureId) && fixtureId > 0);
    await page.goto(base + '/charactersetup?character_id=' + fixtureId + '&step=1');
    await page.waitForFunction(() => !document.querySelector('[data-gm-chat-status]').classList.contains('is-error'));
    await page.locator('#characterSetupGmInput').fill('Keep this unsent question');
    await page.locator('form.character-creation-form [name="name"]').fill('Workspace Browser Fixture');
    await page.locator('[data-character-setup-submit]').click();
    await page.waitForFunction(() => drupalSettings.dungeoncrawlerCharacterSetup.activeStep === 2);
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    assert.equal(await page.locator('#characterSetupGmInput').inputValue(), 'Keep this unsent question');
    assert.equal(await page.locator('.character-setup-page__workspace [data-character-gm-chat]').count(), 1);
    const rejected = await page.evaluate(async () => {
      const settings = drupalSettings.dungeoncrawlerCharacterGm;
      const endpoint = settings.endpointTemplate.replace('/0/gm', '/' + settings.characterId + '/gm');
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': settings.csrfToken },
        body: JSON.stringify({
          schema_version: 'editor-gm-request-v1',
          tool_context: { tool_id: 'character_creation', draft_id: String(settings.characterId), validation_profile: 'editing' },
          intent: { type: 'tool_call', tool_name: 'plan_character_draft_updates', arguments: {
            updates: { free_boosts: ['strength', 'constitution', 'charisma', 'strength'] },
          } },
          options: { dry_run: false },
        }),
      });
      return { status: response.status, payload: await response.json() };
    });
    assert.equal(rejected.status, 422);
    assert.match(rejected.payload.error.message, /free boosts repeats strength/i);
    await page.locator('[data-character-setup-tab][data-step="1"]').click();
    await page.waitForFunction(() => drupalSettings.dungeoncrawlerCharacterSetup.activeStep === 1);
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    await page.locator('form.character-creation-form [name="name"]').fill('Unsaved Fixture');
    await page.locator('[data-character-setup-tab][data-step="2"]').click();
    await page.waitForFunction(() => document.querySelector('[data-character-workspace-status]').textContent.includes('Unsaved'));
    assert.equal(await page.locator('form.character-creation-form [name="name"]').inputValue(), 'Unsaved Fixture');
    await page.locator('[data-character-discard]').click();
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    assert.equal(await page.locator('form.character-creation-form [name="name"]').inputValue(), 'Workspace Browser Fixture');
    await page.locator('form.character-creation-form [name="name"]').fill('');
    await page.locator('[data-character-setup-submit]').click();
    await page.waitForFunction(() => document.querySelector('[data-character-step-region]').textContent.includes('field is required'));
    assert.equal(await page.locator('#characterSetupGmInput').inputValue(), 'Keep this unsent question');
    await page.evaluate(() => Drupal.characterCreationWorkspace.refresh({ saved: true }));
    await page.locator('#characterSetupGmInput').fill("Change my character's name to Original GM Browser Fixture. Do not change any other fields.");
    await page.locator('[data-gm-chat-send]').click();
    await page.waitForFunction(() => !document.querySelector('[data-character-gm-chat]').hasAttribute('aria-busy'), null, { timeout: 120000 });
    const approve = page.getByRole('button', { name: 'Approve and run', exact: true });
    await approve.waitFor({ timeout: 10000 });
    await page.locator('#characterSetupGmInput').fill('Keep this after applying');
    await approve.click();
    await page.waitForFunction(() => document.querySelector('[data-gm-chat-summary-field="name"]').textContent === 'Original GM Browser Fixture', null, { timeout: 120000 });
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    assert.equal(await page.locator('#characterSetupGmInput').inputValue(), 'Keep this after applying');
    assert.equal(await page.locator('form.character-creation-form [name="name"]').inputValue(), 'Original GM Browser Fixture');
    assert.equal(await page.evaluate(() => drupalSettings.dungeoncrawlerCharacterSetup.activeStep), 1);
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-gm-chat-history]').textContent.includes("Change my character's name"));
    if (artifacts) {
      mkdirSync(artifacts, { recursive: true });
      await page.screenshot({ path: join(artifacts, 'character-workspace-desktop.png'), fullPage: true });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'Mobile layout must not overflow.');
    if (artifacts) {
      await page.screenshot({ path: join(artifacts, 'character-workspace-mobile.png'), fullPage: true });
      writeFileSync(join(artifacts, 'character-workspace-browser-errors.json'), JSON.stringify(errors));
    }
    assert.deepEqual(errors, []);
    console.log('PASS: explicit invalid-boost rejection, native save/errors, stable tabs/chat, dirty protection, real GM plan/approval, persisted history, responsive layout.');
  }
  catch (error) {
    console.error('Workspace:', await page.locator('[data-character-step-region]').innerText());
    console.error('GM status:', await page.locator('[data-gm-chat-status]').innerText());
    console.error('Browser errors:', errors);
    throw error;
  }
  finally {
    await browser.close();
    if (fixtureId) {
      drush('php:eval', `
        $id = ${fixtureId};
        $db = \\Drupal::database();
        $row = $db->select('dc_campaign_characters', 'c')->fields('c', ['uid','campaign_id'])->condition('id', $id)->execute()->fetchObject();
        if (!$row || (int) $row->uid !== 1 || (int) $row->campaign_id !== 0) { throw new \\RuntimeException('Fixture cleanup scope mismatch.'); }
        $session = \\Drupal::service('dungeoncrawler_content.chat_session_manager')->loadSession('editor_gm.character_creation.' . $id);
        if ($session) {
          $db->delete('dc_chat_messages')->condition('session_id', (int) $session['id'])->execute();
          $db->delete('dc_chat_sessions')->condition('id', (int) $session['id'])->execute();
        }
        $db->delete('dc_campaign_characters')->condition('id', $id)->condition('uid', 1)->condition('campaign_id', 0)->execute();
      `);
    }
  }
}
main().catch(error => { console.error(error.stack); process.exitCode = 1; });
