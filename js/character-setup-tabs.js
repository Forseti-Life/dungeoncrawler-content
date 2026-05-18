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

  function serializeSubmitter(submitter) {
    if (!submitter) {
      return null;
    }

    return {
      tagName: submitter.tagName || null,
      type: submitter.getAttribute ? submitter.getAttribute('type') : null,
      name: submitter.getAttribute ? submitter.getAttribute('name') : null,
      value: submitter.value || (submitter.textContent ? submitter.textContent.trim() : null),
      href: submitter.href || null,
      dataset: submitter.dataset ? { ...submitter.dataset } : {},
    };
  }

  function logSetupInteraction(message, payload) {
    console.log('[CharacterSetup]', message, payload);
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
        const form = root.querySelector('form.character-creation-form');

        root.addEventListener('click', (event) => {
          const target = event.target instanceof Element ? event.target.closest('button, a') : null;
          if (!target) {
            return;
          }

          if (target.matches('[data-character-setup-submit]') || target.matches('[data-character-setup-quick-play]')) {
            logSetupInteraction('click', {
              url: window.location.href,
              activeStep: state.activeStep,
              campaignId: state.campaignId,
              characterId: state.characterId,
              target: serializeSubmitter(target),
            });
          }
        });

        if (form) {
          form.addEventListener('submit', (event) => {
            const formData = new FormData(form);
            const payload = {
              url: window.location.href,
              action: form.action || window.location.href,
              method: form.method || 'get',
              activeStep: state.activeStep,
              campaignId: state.campaignId,
              characterId: state.characterId,
              submitter: serializeSubmitter(event.submitter || document.activeElement),
              fields: {
                op: formData.get('op'),
                wizard_next: formData.get('wizard_next'),
                campaign_id: formData.get('campaign_id'),
                character_version: formData.get('character_version'),
                form_id: formData.get('form_id'),
              },
            };
            logSetupInteraction('form-submit', payload);
          }, true);
        }

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
