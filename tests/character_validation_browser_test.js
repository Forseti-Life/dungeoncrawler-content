/**
 * Isolated manual/GM validation parity check; no model or gameplay actions.
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdirSync, writeFileSync } = require('node:fs');
const { join } = require('node:path');
const { chromium } = require('playwright');

const base = 'https://dungeoncrawler.forseti.life';
const site = '/var/www/html/dungeoncrawler';
function drush(...args) {
  return execFileSync(site + '/vendor/bin/drush',
    ['--root=' + site + '/web', '--uri=' + base, ...args],
    { cwd: site, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

async function main() {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  let id;
  const errors = [];
  const evidence = {};
  try {
    id = Number(drush('php:eval', `
      \\Drupal::currentUser()->setAccount(\\Drupal\\user\\Entity\\User::load(1));
      echo \\Drupal::service('dungeoncrawler_content.character_creation_gm')->createDraft()['character_id'];
    `));
    assert.ok(Number.isInteger(id) && id > 0);
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(20000);
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(drush('user:login', '--uid=1', '--no-browser'));
    await page.goto(`${base}/charactersetup?character_id=${id}&step=1`);
    const name = page.locator('form.character-creation-form [name="name"]');
    assert.equal(await name.inputValue(), '');
    await page.locator('[data-character-setup-submit]').click();
    await page.waitForFunction(() =>
      document.querySelector('[data-character-step-region]').textContent.includes('field is required'));
    await name.fill('Original Manual Validation Fixture');
    await page.locator('[data-character-setup-submit]').click();
    await page.waitForFunction(() => drupalSettings.dungeoncrawlerCharacterSetup.activeStep === 2);
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    const invoke = async (tool, args = {}) => page.evaluate(async ({ tool, args }) => {
      const settings = drupalSettings.dungeoncrawlerCharacterGm;
      const response = await fetch(settings.endpointTemplate.replace('/0/gm', `/${settings.characterId}/gm`), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': settings.csrfToken },
        body: JSON.stringify({
          schema_version: 'editor-gm-request-v1',
          tool_context: { tool_id: 'character_creation', draft_id: String(settings.characterId), validation_profile: 'editing' },
          intent: { type: 'tool_call', tool_name: tool, arguments: args },
          options: { dry_run: false },
        }),
      });
      const assertResponse = await response.json();
      if (!response.ok) throw new Error(JSON.stringify(assertResponse));
      return assertResponse.data;
    }, { tool, args });
    evidence.review = await invoke('validate_draft');
    assert.equal(evidence.review.context_snapshot.summary.name, 'Original Manual Validation Fixture');
    assert.equal(evidence.review.context_snapshot.process_flow.authority, 'StateValidationService::validateCharacterDraft');
    const revision = evidence.review.context_snapshot.draft.revision;
    evidence.save = await invoke('apply_character_draft_updates', {
      expected_revision: revision, updates: { name: 'Original GM Validation Fixture', ancestry: 'orc' },
    });
    assert.ok(evidence.save.tool_result.next_steps.length > 0);
    assert.match(evidence.save.tool_result.next_steps[0], /^Step 2:/);
    await page.evaluate(() => Drupal.characterCreationWorkspace.refresh({ saved: true }));
    await page.waitForFunction(() => !Drupal.characterCreationWorkspace.isBusy());
    assert.equal(await page.locator('select[name="ancestry"]').inputValue(), 'orc');
    const fields = evidence.save.validation.steps[1].choices;
    for (const field of ['heritage', 'ancestry_feat']) {
      const options = await page.locator(`[name="${field}"]`).evaluateAll(elements =>
        elements.flatMap(element => element.tagName === 'SELECT'
          ? Array.from(element.options).map(option => option.value)
          : [element.value]).filter(Boolean));
      assert.deepEqual([...new Set(options)].sort(), Object.keys(fields[field].options).filter(Boolean).sort());
    }
    await page.goto(`${base}/charactersetup?character_id=${id}&step=1`);
    assert.equal(await name.inputValue(), 'Original GM Validation Fixture');
    assert.equal(await page.getByRole('button', { name: /approve and run/i }).count(), 0);
    const inventory = JSON.parse(drush('php:eval', `
      \\Drupal::currentUser()->setAccount(\\Drupal\\user\\Entity\\User::load(1));
      $id = ${id}; $db = \\Drupal::database();
      $row = $db->select('dc_campaign_characters', 'c')->fields('c')->condition('id', $id)->execute()->fetchObject();
      if (!$row || (int) $row->uid !== 1 || (int) $row->campaign_id !== 0) { throw new \\RuntimeException('Fixture scope mismatch.'); }
      $data = json_decode($row->character_data, TRUE);
      $data['step'] = $data['wizard']['step'] = 8;
      $db->update('dc_campaign_characters')->fields(['character_data' => json_encode($data)])->condition('id', $id)->execute();
      $data = \\Drupal::service('dungeoncrawler_content.character_creation_gm')->getDraft($id)['payload'];
      $rules = \\Drupal::service('dungeoncrawler_content.character_creation_validation');
      $schema = \\Drupal::service('dungeoncrawler_content.schema_loader');
      $walk = static function (array $elements, array $parents = [], bool $tree = FALSE) use (&$walk): array {
        $fields = [];
        foreach ($elements as $key => $element) {
          if (str_starts_with((string) $key, '#') || !is_array($element)) { continue; }
          $child_tree = $element['#tree'] ?? $tree;
          $path = $element['#parents'] ?? ($child_tree && $tree ? [...$parents, (string) $key] : [(string) $key]);
          $type = $element['#type'] ?? '';
          if (in_array($type, ['textfield','textarea','select','radios','checkboxes','checkbox','hidden','number'], TRUE)) {
            $fields[] = [
              'field' => array_shift($path) . ($path ? '[' . implode('][', $path) . ']' : ''),
              'source' => 'shared_definition.' . implode('.', $element['#parents'] ?? ($child_tree && $tree ? [...$parents, (string) $key] : [(string) $key])) . '.#default_value',
              'input_source' => 'canonical character payload via shared step definition',
              'type' => $type, 'multiple' => $type === 'checkboxes' || !empty($element['#multiple']),
              'expected' => $element['#value'] ?? $element['#default_value'] ?? '',
            ];
            $path = $element['#parents'] ?? ($child_tree && $tree ? [...$parents, (string) $key] : [(string) $key]);
          }
          array_push($fields, ...$walk($element, $path, (bool) $child_tree));
        }
        return $fields;
      };
      $inventory = [];
      for ($step = 1; $step <= 8; $step++) {
        $input = (new \\Drupal\\dungeoncrawler_content\\Service\\Validation\\CharacterCreationInput($data))->set('step', $step)->set('campaign_id', 0);
        $fields = []; $method = 'buildStep' . $step . 'Fields';
        $rules->$method($fields, $input, $data, $schema->getStepFields($step));
        $inventory[$step] = $walk($fields);
      }
      echo json_encode($inventory);
    `));
    evidence.fields = [];
    for (const [step, fields] of Object.entries(inventory)) {
      await page.goto(`${base}/charactersetup?character_id=${id}&step=${step}`);
      for (const field of fields) {
        const actual = await page.locator('form.character-creation-form').evaluate((form, field) => {
          const inputs = Array.from(form.querySelectorAll('input,select,textarea'))
            .filter(input => input.name === field.field || input.name === field.field + '[]'
              || (field.multiple && input.name.startsWith(field.field + '[')));
          if (!inputs.length) throw new Error('Missing rendered field: ' + field.field);
          if (field.type === 'checkbox') return inputs[0].checked;
          if (field.type === 'radios') return inputs.find(input => input.checked)?.value || '';
          if (field.multiple) return inputs.flatMap(input => input.tagName === 'SELECT'
            ? Array.from(input.selectedOptions).map(option => option.value)
            : input.checked ? [input.value] : []).sort();
          return inputs[0].value;
        }, field);
        const expected = field.type === 'checkbox' ? Boolean(field.expected)
          : field.multiple ? (Array.isArray(field.expected) ? field.expected : Object.values(field.expected || {})).map(String).sort()
            : String(field.expected);
        evidence.fields.push({ step: Number(step), ...field, expected, actual, pass: JSON.stringify(expected) === JSON.stringify(actual) });
        assert.deepEqual(actual, expected, `Step ${step}: ${field.field} must display ${field.source}`);
      }
    }
    await page.goto(`${base}/charactersetup?character_id=${id}&step=1`);
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    assert.deepEqual(errors, []);
    if (process.env.WIZARD_ARTIFACT_DIR) {
      mkdirSync(process.env.WIZARD_ARTIFACT_DIR, { recursive: true });
      await page.screenshot({ path: join(process.env.WIZARD_ARTIFACT_DIR, 'shared-validation-browser.png'), fullPage: true });
      writeFileSync(join(process.env.WIZARD_ARTIFACT_DIR, 'shared-validation-browser.json'),
        JSON.stringify({ errors, ...evidence }, null, 2));
    }
    console.log(`PASS: ${evidence.fields.length} fields across eight steps, shared validation authority, manual errors/save, direct GM save/next steps, conditional option parity, refreshed saved fields, mobile layout.`);
  }
  finally {
    await browser.close();
    if (id) {
      drush('php:eval', `
        $id = ${id}; $db = \\Drupal::database();
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
