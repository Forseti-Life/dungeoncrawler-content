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
    await page.locator('#characterSetupGmInput').fill('Start my character draft with the name Original New Draft Fixture. Leave all other decisions for me.');
    await page.locator('[data-gm-chat-send]').click();
    await page.waitForFunction(() => drupalSettings.dungeoncrawlerCharacterGm.characterId > 0);
    fixtureId = await page.evaluate(() => Number(drupalSettings.dungeoncrawlerCharacterGm.characterId));
    assert.ok(Number.isInteger(fixtureId) && fixtureId > 0);
    await page.waitForFunction(() => !document.querySelector('[data-character-gm-chat]').hasAttribute('aria-busy'), null, { timeout: 120000 });
    assert.equal(await page.locator('[data-gm-chat-summary-field="name"]').innerText(), 'Original New Draft Fixture', 'The GM must create and edit a new draft without a lock dead end.');
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
    await page.locator('#characterSetupGmInput').fill('Keep this after saving');
    await page.waitForFunction(() => !document.querySelector('[data-character-gm-chat]').hasAttribute('aria-busy'), null, { timeout: 120000 });
    assert.equal(await page.getByRole('button', { name: /approve and run/i }).count(), 0);
    assert.equal(await page.locator('[data-gm-chat-summary-field="name"]').innerText(), 'Original GM Browser Fixture');
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    assert.equal(await page.locator('#characterSetupGmInput').inputValue(), 'Keep this after saving');
    assert.equal(await page.locator('form.character-creation-form [name="name"]').inputValue(), 'Original GM Browser Fixture');
    assert.equal(await page.evaluate(() => drupalSettings.dungeoncrawlerCharacterSetup.activeStep), 1);
    drush('php:eval', `
      $chat = \\Drupal::service('dungeoncrawler_content.chat_session_manager');
      $session = $chat->ensureEditorGmSession('character_creation', '${fixtureId}', 'Original Option Fixture');
      $chat->postMessage((int) $session['id'], 0, 'Game Master Agent', 'gm', '', 'Choose a name.', 'dialogue', 'private', [
        'editor_gm_response' => ['tool_result' => [
          'intent' => 'clarification', 'question' => 'Choose a name.',
          'options' => [
            ['label' => 'Original Option Fixture', 'description' => 'Use the first original fixture name.', 'tradeoff' => 'First choice.', 'selection' => 'Change only my character name to Original Option Fixture.'],
            ['label' => 'Original Alternate Fixture', 'description' => 'Use the alternate original fixture name.', 'tradeoff' => 'Second choice.', 'selection' => 'Change only my character name to Original Alternate Fixture.'],
          ],
        ]],
      ], FALSE);
    `);
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-gm-chat-history]').textContent.includes("Change my character's name"));
    const firstOption = page.getByRole('button', { name: /^1\. Original Option Fixture/ });
    const secondOption = page.getByRole('button', { name: /^2\. Original Alternate Fixture/ });
    await firstOption.waitFor();
    const firstBox = await firstOption.boundingBox();
    const secondBox = await secondOption.boundingBox();
    assert.ok(secondBox.y >= firstBox.y + firstBox.height, 'Numbered options must appear on separate list rows.');
    await firstOption.click();
    await page.waitForFunction(() => !document.querySelector('[data-character-gm-chat]').hasAttribute('aria-busy'), null, { timeout: 120000 });
    assert.equal(await page.locator('form.character-creation-form [name="name"]').inputValue(), 'Original Option Fixture', 'Selecting an option must execute and save without another send or approval.');
    assert.equal(await page.getByRole('button', { name: /approve and run/i }).count(), 0);
    await page.locator('#characterSetupGmInput').fill('Set my ancestry to orc. Leave heritage, ancestry feat, and ability boosts for me to choose next. Do not change any other fields.');
    await page.locator('[data-gm-chat-send]').click();
    await page.waitForFunction(() => !document.querySelector('[data-character-gm-chat]').hasAttribute('aria-busy'), null, { timeout: 120000 });
    assert.equal(await page.locator('[data-gm-chat-summary-field="ancestry"]').innerText(), 'orc');
    const process = await page.evaluate(async () => {
      const settings = drupalSettings.dungeoncrawlerCharacterGm;
      const response = await fetch(settings.endpointTemplate.replace('/0/gm', '/' + settings.characterId + '/gm'));
      if (!response.ok) throw new Error('Process inspection failed.');
      return (await response.json()).data;
    });
    assert.equal(process.context_snapshot.process_flow.current_step, process.validation.next_step);
    assert.ok(process.context_snapshot.process_flow.decision_points.some(issue => issue.field === 'heritage'), 'The flow must discover conditional ancestry choices before moving to later wizard steps.');
    assert.ok(process.context_snapshot.process_flow.decision_points.every(issue => issue.step === 2));
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
    console.log('PASS: new GM draft, explicit invalid-boost rejection, native save/errors, stable tabs/chat, dirty protection, immediate GM save, vertical one-click options, conditional wizard process, persisted history, responsive layout.');
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
