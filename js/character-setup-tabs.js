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
    const url = parseUrl(settings.stepRoutePrefix + step);
    if (!url) {
      return settings.stepRoutePrefix + step;
    }

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

    url.searchParams.set('embedded', '1');
    url.searchParams.set('charactersetup', '1');
    return url.toString();
  }

  function buildRefreshUrl(settings, state, preferredUrl) {
    const url = parseUrl(preferredUrl || buildStepUrl(settings, state.activeStep, state.characterId, state.campaignId));
    if (!url) {
      const fallback = buildStepUrl(settings, state.activeStep, state.characterId, state.campaignId);
      return fallback + (fallback.indexOf('?') === -1 ? '?' : '&') + '_gm_refresh=' + Date.now();
    }

    if (state.characterId) {
      url.searchParams.set('character_id', String(state.characterId));
    }
    else {
      url.searchParams.delete('character_id');
    }

    if (state.campaignId) {
      url.searchParams.set('campaign_id', String(state.campaignId));
    }
    else {
      url.searchParams.delete('campaign_id');
    }

    url.searchParams.set('embedded', '1');
    url.searchParams.set('charactersetup', '1');
    url.searchParams.set('_gm_refresh', String(Date.now()));
    return url.toString();
  }

  function replaceShellUrl(settings, state) {
    const shellUrl = parseUrl(settings.shellUrl);
    if (!shellUrl) {
      return;
    }

    shellUrl.searchParams.set('step', String(state.activeStep));
    if (state.characterId) {
      shellUrl.searchParams.set('character_id', String(state.characterId));
    }
    else {
      shellUrl.searchParams.delete('character_id');
    }

    if (state.campaignId) {
      shellUrl.searchParams.set('campaign_id', String(state.campaignId));
    }
    else {
      shellUrl.searchParams.delete('campaign_id');
    }

    window.history.replaceState({}, '', shellUrl.toString());
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

  function resizeIframeToContent(iframe) {
    let doc = null;
    try {
      doc = iframe.contentDocument;
    }
    catch (error) {
      return;
    }

    if (!doc || !doc.body) {
      return;
    }

    const target = iframe._characterSetupResizeTarget
      || doc.querySelector('.character-creation-step--embedded')
      || doc.querySelector('.character-creation-step')
      || doc.body;
    const docEl = doc.documentElement;
    const height = Math.max(
      Math.ceil(target.getBoundingClientRect ? target.getBoundingClientRect().height : 0),
      target.scrollHeight || 0,
      target.offsetHeight || 0,
      target.clientHeight || 0,
      doc.body.scrollHeight || 0,
      doc.body.offsetHeight || 0,
      docEl ? (docEl.scrollHeight || 0) : 0,
      docEl ? (docEl.offsetHeight || 0) : 0,
      1
    );
    const nextHeight = height + 'px';

    if (iframe.style.height !== nextHeight) {
      iframe.style.height = nextHeight;
    }

    iframe.setAttribute('scrolling', 'no');
  }

  function observeEmbeddedDocument(iframe) {
    let doc = null;
    try {
      doc = iframe.contentDocument;
    }
    catch (error) {
      return;
    }

    if (!doc || !doc.body) {
      return;
    }

    const embeddedRoot = doc.querySelector('.character-creation-step--embedded')
      || doc.querySelector('.character-creation-step')
      || doc.body;
    iframe._characterSetupResizeTarget = embeddedRoot;

    if (doc.head && !doc.getElementById('character-setup-iframe-style')) {
      const style = doc.createElement('style');
      style.id = 'character-setup-iframe-style';
      style.textContent = 'html, body { margin: 0; padding: 0; background: transparent; } body { overflow: visible; }';
      doc.head.appendChild(style);
    }

    if (iframe._characterSetupResizeObserver) {
      iframe._characterSetupResizeObserver.disconnect();
    }

    if (window.ResizeObserver) {
      iframe._characterSetupResizeObserver = new ResizeObserver(() => {
        resizeIframeToContent(iframe);
      });
      iframe._characterSetupResizeObserver.observe(embeddedRoot);
      iframe._characterSetupResizeObserver.observe(doc.body);
      if (doc.documentElement) {
        iframe._characterSetupResizeObserver.observe(doc.documentElement);
      }
    }

    window.requestAnimationFrame(() => {
      resizeIframeToContent(iframe);
    });
  }

  function syncTabState(root, state, settings) {
    const buttons = root.querySelectorAll('[data-character-setup-tab]');
    const activeTitle = root.querySelector('[data-character-setup-active-title]');
    const activeMeta = root.querySelector('[data-character-setup-active-meta]');
    const gmSettings = drupalSettings.dungeoncrawlerCharacterGm || {};

    buttons.forEach((button) => {
      const step = Number(button.dataset.step || '1');
      const enabled = step <= state.maxAccessibleStep;
      button.disabled = !enabled;
      button.classList.toggle('is-active', step === state.activeStep);
      button.setAttribute('aria-selected', step === state.activeStep ? 'true' : 'false');
      button.dataset.url = buildStepUrl(settings, step, state.characterId, state.campaignId);
    });

    const activeButton = root.querySelector('[data-character-setup-tab].is-active');
    if (activeButton && activeTitle) {
      const titleText = activeButton.querySelector('.character-setup-page__tab-name');
      activeTitle.textContent = titleText ? titleText.textContent : 'Step ' + state.activeStep;
    }
    if (activeMeta) {
      activeMeta.textContent = 'Step ' + state.activeStep + ' of 8';
    }

    gmSettings.characterId = state.characterId || null;
    gmSettings.campaignId = state.campaignId || null;
    gmSettings.step = state.activeStep;

    replaceShellUrl(settings, state);
  }

  Drupal.behaviors.dungeoncrawlerCharacterSetup = {
    attach(context) {
      const settings = drupalSettings.dungeoncrawlerCharacterSetup;
      if (!settings) {
        return;
      }

      once('dungeoncrawlerCharacterSetup', '[data-character-setup-root]', context).forEach((root) => {
        const iframe = root.querySelector('[data-character-setup-frame]');
        if (!iframe) {
          return;
        }

        const state = {
          activeStep: Number(settings.activeStep || 1),
          maxAccessibleStep: Number(settings.maxAccessibleStep || 1),
          characterId: settings.characterId ? Number(settings.characterId) : null,
          campaignId: settings.campaignId ? Number(settings.campaignId) : null,
        };

        const loadStep = (step) => {
          if (step > state.maxAccessibleStep) {
            return;
          }
          state.activeStep = step;
          syncTabState(root, state, settings);
          const button = root.querySelector('[data-character-setup-tab][data-step="' + step + '"]');
          if (button && button.dataset.url) {
            iframe.src = button.dataset.url;
          }
        };

        const reloadFrame = (preferredUrl) => {
          const nextUrl = buildRefreshUrl(settings, state, preferredUrl);

          try {
            if (iframe.contentWindow && iframe.contentWindow.location.href) {
              iframe.contentWindow.location.replace(nextUrl);
              return;
            }
          }
          catch (error) {
          }

          iframe.src = nextUrl;
        };

        root.querySelectorAll('[data-character-setup-tab]').forEach((button) => {
          button.addEventListener('click', () => {
            loadStep(Number(button.dataset.step || '1'));
          });
        });

        iframe.addEventListener('load', () => {
          let currentHref = iframe.getAttribute('src') || '';
          try {
            currentHref = iframe.contentWindow.location.href || currentHref;
          }
          catch (error) {
          }

          const currentUrl = parseUrl(currentHref);
          if (!currentUrl) {
            return;
          }

          const stepMatch = currentUrl.pathname.match(/\/characters\/create\/step\/(\d+)/);
          if (!stepMatch) {
            if (currentUrl.origin === window.location.origin) {
              window.location.assign(currentUrl.pathname + currentUrl.search + currentUrl.hash);
            }
            return;
          }

          state.activeStep = Number(stepMatch[1]);
          state.maxAccessibleStep = Math.max(state.maxAccessibleStep, state.activeStep);
          const unlockedStep = Number(currentUrl.searchParams.get('unlocked_step') || '0');
          if (unlockedStep > 0) {
            state.maxAccessibleStep = Math.max(state.maxAccessibleStep, unlockedStep);
          }

          const nextCharacterId = currentUrl.searchParams.get('character_id');
          if (nextCharacterId) {
            state.characterId = Number(nextCharacterId);
          }

          const nextCampaignId = currentUrl.searchParams.get('campaign_id');
          if (nextCampaignId) {
            state.campaignId = Number(nextCampaignId);
          }

          observeEmbeddedDocument(iframe);
          resizeIframeToContent(iframe);
          syncTabState(root, state, settings);
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
          syncTabState(root, state, settings);

          if (payload.reload_url) {
            reloadFrame(payload.reload_url);
          }
        });

        syncTabState(root, state, settings);
      });
    },
  };
})(Drupal, once, drupalSettings);
