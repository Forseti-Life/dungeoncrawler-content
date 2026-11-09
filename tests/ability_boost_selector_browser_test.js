/**
 * Isolated browser regression: node tests/ability_boost_selector_browser_test.js
 *
 * Uses Drupal's installed jQuery/once and mocked score APIs; no player data.
 */
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const root = process.env.DRUPAL_ROOT || '/var/www/html/dungeoncrawler/web';
const abilities = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];
const selector = path.resolve(__dirname, '../js/ability-boost-selector.js');

function widget(step, count, selected = []) {
  return `<div class="ability-score-widget" data-step="${step}" data-max-boosts="${count}" data-character-data="{}">
    <div class="abilities-interactive">
      <span class="boosts-remaining"></span>
      ${abilities.map(ability => `<button type="button" class="ability-card ability-card--selectable" data-ability="${ability}">
        <input type="checkbox" class="ability-checkbox"><span class="score-value">10</span>
      </button>`).join('')}
    </div>
  </div><input type="hidden" id="${step}-boosts-field" value='${JSON.stringify(selected)}'>`;
}

async function submit(page, index = 0) {
  return page.evaluate(index => {
    window.alerts.length = 0;
    const event = jQuery.Event('submit');
    jQuery('form.character-creation-form').eq(index).triggerHandler(event);
    return { blocked: event.isDefaultPrevented(), alerts: window.alerts };
  }, index);
}

async function main() {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  let passed = 0;
  const errors = [];
  try {
    async function fixture(html, apiCount, options = {}) {
      const page = await browser.newPage();
      page.setDefaultTimeout(5000);
      page.on('pageerror', error => errors.push(error.message));
      let validations = 0;
      await page.route('http://wizard.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/') {
          return route.fulfill({ contentType: 'text/html', body: html });
        }
        if (url.pathname === '/session/token') {
          return route.fulfill({ body: 'fixture-token' });
        }
        if (url.pathname.endsWith('/validate-boost')) {
          validations++;
          await new Promise(resolve => setTimeout(resolve, options.delay || 0));
          if (options.failValidation) {
            return route.fulfill({ status: 503, body: 'unavailable' });
          }
          return route.fulfill({ json: { valid: true } });
        }
        if (url.pathname.includes('/available-boosts/')) {
          const payload = route.request().postDataJSON();
          const max = typeof apiCount === 'function' ? apiCount(url.pathname) : apiCount;
          return route.fulfill({ json: {
            available: abilities,
            disabled: [...new Set([...(options.disabled || []), ...(payload.selected_boosts.length >= max
              ? abilities.filter(ability => !payload.selected_boosts.includes(ability)) : [])])],
            max_selections: max,
          } });
        }
        return route.fulfill({ json: { success: true, scores: {}, modifiers: {} } });
      });
      await page.goto('http://wizard.test/');
      await page.addScriptTag({ path: path.join(root, 'core/assets/vendor/jquery/jquery.min.js') });
      await page.addScriptTag({ path: path.join(root, 'core/assets/vendor/once/once.min.js') });
      await page.evaluate(() => {
        window.Drupal = { behaviors: {} };
        window.alerts = [];
        window.alert = message => window.alerts.push(message);
      });
      await page.addScriptTag({ path: selector });
      await page.evaluate(() => {
        Object.values(Drupal.behaviors).forEach(behavior => behavior.attach(document));
      });
      if (html.includes('abilities-interactive')) {
        await page.waitForFunction(() =>
          [...document.querySelectorAll('.boosts-remaining')].every(node => node.textContent !== ''));
        await page.waitForLoadState('networkidle');
      }
      return { page, validations: () => validations };
    }

    const preview = '<div class="ability-score-widget" data-mode="compact"></div>';
    for (const [step, count] of [['ancestry', 1], ['ancestry', 2], ['background', 1], ['background', 2], ['free', 4]]) {
      const { page } = await fixture(
        `<form class="character-creation-form">${preview}${widget(step, count, abilities.slice(0, count))}</form>`,
        count
      );
      assert.equal((await submit(page)).blocked, false, `${step}/${count} should pass despite preceding preview`);
      await page.evaluate(step => jQuery(`#${step}-boosts-field`).val('[]'), step);
      assert.equal((await submit(page)).blocked, true, `${step}/${count} rejects too few`);
      await page.evaluate(({ step, boosts }) => jQuery(`#${step}-boosts-field`).val(JSON.stringify(boosts)), {
        step, boosts: abilities.slice(0, count + 1),
      });
      assert.equal((await submit(page)).blocked, true, `${step}/${count} rejects too many`);
      await page.close();
      passed++;
    }

    {
      const { page } = await fixture(`<form class="character-creation-form">${preview}</form>`, 0);
      assert.equal((await submit(page)).blocked, false, 'read-only preview cannot gate non-boost steps');
      await page.close();
      passed++;
    }
    for (const step of ['ancestry', 'background']) {
      const { page } = await fixture(
        `<form class="character-creation-form">${widget(step, 1, ['strength'])}</form>`, 1,
        { disabled: ['strength'] }
      );
      assert.equal(await page.locator('[data-ability="strength"] .ability-checkbox').isDisabled(), false, 'An invalid selected boost must remain removable.');
      await page.locator('[data-ability="strength"]').click();
      await page.waitForFunction(() => document.querySelector('[data-ability="strength"] .ability-checkbox').disabled);
      assert.equal(await page.locator(`#${step}-boosts-field`).inputValue(), '[]');
      assert.equal(await page.locator('[data-ability="strength"] .ability-checkbox').isDisabled(), true, 'A forbidden boost cannot be reselected.');
      await page.locator('[data-ability="dexterity"]').click();
      await page.waitForFunction(step => document.querySelector(`#${step}-boosts-field`).value === '["dexterity"]', step);
      assert.equal((await submit(page)).blocked, false, 'A valid replacement must clear the selection dead end.');
      await page.close();
      passed++;
    }
    {
      const { page } = await fixture(`<form class="character-creation-form">${widget('ancestry', 2)}</form>`, 0);
      assert.equal(await page.locator('.boosts-remaining').textContent(), '0');
      assert.equal((await submit(page)).blocked, false, 'zero API limit overrides template and allows empty selection');
      await page.close();
      passed++;
    }
    {
      const { page } = await fixture(`<form class="character-creation-form">${widget('ancestry', 2, ['strength'])}</form>`, 1);
      assert.equal((await submit(page)).blocked, false, 'submit shares the API-updated limit');
      await page.close();
      passed++;
    }
    {
      const { page } = await fixture(
        `<form class="character-creation-form">${widget('ancestry', 1, ['strength'])}</form>
         <form class="character-creation-form">${widget('ancestry', 1)}</form>`, 1
      );
      assert.equal((await submit(page, 0)).blocked, false);
      assert.equal((await submit(page, 1)).blocked, true, 'fields remain scoped to their form');
      await page.close();
      passed++;
    }
    {
      const { page } = await fixture(
        `<form class="character-creation-form"><div id="dynamic">${widget('ancestry', 2)}</div></form>`, 1
      );
      await page.evaluate(html => {
        document.querySelector('#dynamic').innerHTML = html;
        Object.values(Drupal.behaviors).forEach(behavior => behavior.attach(document.querySelector('#dynamic')));
      }, widget('ancestry', 1, ['dexterity']));
      assert.equal((await submit(page)).blocked, false, 'AJAX replacement uses the new interactive widget');
      await page.close();
      passed++;
    }
    for (const value of ['not-json', '{}', '["strength","strength"]']) {
      const { page } = await fixture(`<form class="character-creation-form">${widget('free', 4)}</form>`, 4);
      await page.locator('#free-boosts-field').evaluate((node, value) => node.value = value, value);
      assert.equal((await submit(page)).blocked, true, 'malformed and duplicate selections fail visibly');
      await page.close();
      passed++;
    }
    {
      const { page, validations } = await fixture(
        `<form class="character-creation-form">${widget('ancestry', 1)}</form>`, 1, { delay: 150 }
      );
      await page.evaluate(() => {
        const card = document.querySelector('[data-ability="strength"]');
        card.click();
        card.click();
        document.querySelector('[data-ability="dexterity"]').click();
      });
      const waiting = await submit(page);
      assert.equal(waiting.blocked, true);
      assert.match(waiting.alerts[0], /Waiting for the server/);
      await page.waitForFunction(() => document.querySelector('#ancestry-boosts-field').value !== '[]');
      assert.equal(validations(), 1, 'rapid clicks share a single pending validation');
      assert.deepEqual(JSON.parse(await page.locator('#ancestry-boosts-field').inputValue()), ['strength']);
      assert.equal((await submit(page)).blocked, false);
      await page.locator('[data-ability="strength"]').click();
      assert.equal(await page.locator('#ancestry-boosts-field').inputValue(), '[]', 'deselection leaves no ghost boost');
      await page.close();
      passed++;
    }
    {
      const { page } = await fixture(
        `<form class="character-creation-form">${widget('ancestry', 1)}</form>`, 1, { failValidation: true }
      );
      await page.locator('[data-ability="strength"]').click();
      await page.waitForFunction(() => document.querySelector('.abilities-interactive').getAttribute('aria-busy') === 'false');
      assert.equal(await page.locator('#ancestry-boosts-field').inputValue(), '[]');
      assert.match(await page.locator('body').textContent(), /Failed to validate boost selection/);
      await page.close();
      passed++;
    }
    assert.deepEqual(errors, [], 'no browser JavaScript errors');
    console.log(`Passed ${passed} isolated ability boost browser scenarios.`);
  } finally {
    await browser.close();
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
