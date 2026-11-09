(function (Drupal, once, drupalSettings, $) {
  'use strict';

  Drupal.behaviors.dungeoncrawlerCharacterSetup = {
    attach(context) {
      once('dungeoncrawlerCharacterSetup', '[data-character-setup-root]', context).forEach((root) => {
        const state = { ...drupalSettings.dungeoncrawlerCharacterSetup };
        const status = root.querySelector('[data-character-workspace-status]');
        const dirtyActions = root.querySelector('[data-character-dirty-actions]');
        let baseline = null;
        let busy = false;
        let mutationPending = false;

        const snapshot = () => {
          const form = root.querySelector('form.character-creation-form');
          if (!form) return '';
          return JSON.stringify(Array.from(form.elements)
            .filter((element) => element.name && !element.disabled
              && !['submit', 'button', 'file'].includes(element.type)
              && (element.type !== 'hidden' || /^(ancestry_boosts|background_boosts|free_boosts|feat_selections)/.test(element.name)))
            .map((element) => [element.name, element.type === 'checkbox' || element.type === 'radio' ? element.checked : element.value]));
        };
        const isDirty = () => baseline !== null && baseline !== snapshot();
        const setStatus = (message, error = false) => {
          status.textContent = message;
          status.classList.toggle('is-error', error);
          dirtyActions.hidden = !isDirty();
        };
        const urlFor = (step, refresh = false) => {
          const url = new URL(refresh ? state.refreshUrl : state.shellUrl, window.location.origin);
          url.searchParams.set('step', String(step));
          if (state.characterId) url.searchParams.set('character_id', String(state.characterId));
          if (state.campaignId) url.searchParams.set('campaign_id', String(state.campaignId));
          return url;
        };
        const updateTabs = () => {
          root.querySelectorAll('[data-character-setup-tab]').forEach((tab) => {
            const step = Number(tab.dataset.step);
            const enabled = step <= Number(state.maxAccessibleStep);
            tab.href = urlFor(step).toString();
            tab.classList.toggle('is-active', step === Number(state.activeStep));
            tab.setAttribute('aria-current', step === Number(state.activeStep) ? 'step' : 'false');
            tab.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            tab.title = enabled ? '' : 'Waiting for earlier steps to be saved';
            tab.querySelector('[data-character-step-state]').textContent = enabled
              ? (step === Number(state.activeStep) ? 'Editing' : 'Available') : 'Save earlier steps to unlock';
          });
        };
        const refresh = async ({ step = state.activeStep, characterId = state.characterId, saved = false, push = false } = {}) => {
          if (busy) throw new Error('Waiting for the server to finish refreshing the character editor.');
          if (mutationPending && !saved) throw new Error('Waiting for the character GM to finish the current request.');
          if (!saved && isDirty()) {
            const message = 'Unsaved edits: save this step or discard edits before continuing.';
            setStatus(message, true);
            throw new Error(message);
          }
          state.characterId = characterId;
          busy = true;
          const scrollY = window.scrollY;
          root.querySelector('[data-character-step-region]').setAttribute('aria-busy', 'true');
          setStatus('Waiting for the server to load the character step...');
          try {
            await new Promise((resolve, reject) => {
              const ajax = Drupal.ajax({
                url: urlFor(step, true).toString(),
                httpMethod: 'GET',
                progress: false,
                'disable-refocus': true,
              });
              const success = ajax.success.bind(ajax);
              ajax.success = (response, responseStatus) => Promise.resolve(success(response, responseStatus)).then(resolve, reject);
              ajax.error = (xhr, uri, message) => reject(new Drupal.AjaxError(xhr, uri, message));
              ajax.execute();
            });
            baseline = snapshot();
            history[push ? 'pushState' : 'replaceState']({}, '', urlFor(state.activeStep));
            window.scrollTo({ top: scrollY, behavior: 'instant' });
            setStatus('Character editor is up to date.');
            root.dispatchEvent(new CustomEvent('character:refreshed', { bubbles: true }));
          }
          catch (error) {
            setStatus('Editor refresh failed. Your conversation is preserved. ' + error.message, true);
            throw error;
          }
          finally {
            busy = false;
            root.querySelector('[data-character-step-region]')?.removeAttribute('aria-busy');
          }
        };
        $(root).on('characterWorkspaceRefreshed', (_event, settings) => {
          Object.assign(state, settings);
          updateTabs();
        });
        $(root).on('characterWorkspaceSaved', (_event, query) => {
          refresh({ step: Number(query.step), characterId: Number(query.character_id), saved: true })
            .catch((error) => setStatus(error.message, true));
        });
        root.addEventListener('click', (event) => {
          const tab = event.target.closest('[data-character-setup-tab]');
          if (!tab || event.ctrlKey || event.metaKey || event.shiftKey || event.button > 0) return;
          event.preventDefault();
          if (tab.getAttribute('aria-disabled') === 'true') {
            setStatus('Waiting for earlier character steps to be saved before this step unlocks.');
            return;
          }
          refresh({ step: Number(tab.dataset.step), push: true }).catch((error) => setStatus(error.message, true));
        });
        root.addEventListener('input', () => {
          dirtyActions.hidden = !isDirty();
        });
        root.addEventListener('change', () => {
          dirtyActions.hidden = !isDirty();
        });
        root.querySelector('[data-character-discard]').addEventListener('click', () => {
          refresh({ saved: true }).catch((error) => setStatus(error.message, true));
        });
        window.addEventListener('beforeunload', (event) => {
          if (!isDirty()) return;
          event.preventDefault();
          event.returnValue = '';
        });
        window.addEventListener('popstate', () => {
          const step = Number(new URL(window.location.href).searchParams.get('step') || 1);
          if (isDirty()) {
            history.replaceState({}, '', urlFor(state.activeStep));
            setStatus('Unsaved edits: save or discard them before navigating.', true);
            return;
          }
          refresh({ step }).catch((error) => setStatus(error.message, true));
        });
        Drupal.characterCreationWorkspace = {
          refresh, isDirty, isBusy: () => busy || mutationPending,
          lock() {
            mutationPending = true;
            root.querySelector('[data-character-step-region]').inert = true;
          },
          unlock() {
            mutationPending = false;
            root.querySelector('[data-character-step-region]').inert = false;
          },
        };
        requestAnimationFrame(() => { baseline = snapshot(); });
      });
    },
  };
})(Drupal, once, drupalSettings, jQuery);
