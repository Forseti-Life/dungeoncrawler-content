(function (Drupal, once, drupalSettings) {
  'use strict';

  function parseUrl(value) {
    try {
      return new URL(value, window.location.origin);
    }
    catch (error) {
      return null;
    }
  }

  function buildStepUrl(settings, step, characterId, campaignId) {
    const url = parseUrl(settings.shellUrl);
    if (!url) {
      return settings.shellUrl;
    }

    url.searchParams.set('step', String(step));
    if (characterId) {
      url.searchParams.set('character_id', String(characterId));
    }
    else {
      url.searchParams.delete('character_id');
    }

    if (campaignId) {
      url.searchParams.set('campaign_id', String(campaignId));
    }
    else {
      url.searchParams.delete('campaign_id');
    }

    return url.toString();
  }

  function updateSummaryFields(root, summary) {
    if (!summary || typeof summary !== 'object') {
      return;
    }

    Object.keys(summary).forEach((key) => {
      const target = root.querySelector('[data-gm-chat-summary-field="' + key + '"]');
      if (!target) {
        return;
      }

      const value = summary[key];
      target.textContent = value === null || value === undefined || value === '' ? 'Not selected' : String(value);
    });
  }

  Drupal.behaviors.dungeoncrawlerCharacterSetup = {
    attach(context) {
      const settings = drupalSettings.dungeoncrawlerCharacterSetup;
      if (!settings) {
        return;
      }

      once('dungeoncrawlerCharacterSetup', '[data-character-setup-root]', context).forEach((root) => {
        const state = {
          activeStep: Number(settings.activeStep || 1),
          maxAccessibleStep: Number(settings.maxAccessibleStep || 1),
          characterId: settings.characterId ? Number(settings.characterId) : null,
          campaignId: settings.campaignId ? Number(settings.campaignId) : null,
        };

        root.querySelectorAll('[data-character-setup-tab]').forEach((tab) => {
          tab.addEventListener('click', (event) => {
            if (tab.getAttribute('aria-disabled') === 'true') {
              event.preventDefault();
              return;
            }

            const step = Number(tab.dataset.step || '1');
            if (step > state.maxAccessibleStep) {
              event.preventDefault();
            }
          });
        });

        window.addEventListener('dungeoncrawler:character-setup-gm-update', (event) => {
          const payload = event.detail || {};
          if (payload.character_id) {
            state.characterId = Number(payload.character_id);
          }
          if (payload.step) {
            state.activeStep = Number(payload.step);
            state.maxAccessibleStep = Math.max(state.maxAccessibleStep, state.activeStep);
          }
          updateSummaryFields(root, payload.summary || {});
          window.location.assign(buildStepUrl(settings, state.activeStep, state.characterId, state.campaignId));
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
