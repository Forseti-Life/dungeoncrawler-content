import {
  appendEditorGmMessage, buildEditorGmRequest, renderEditorGmToolCalls,
  restoreEditorGmConversation, renderEditorGmClarification,
  shouldSubmitEditorGmKey,
} from './v2/editor/EditorGmChatClient.js?v=20261024-tool-calls-1';

(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.characterCreationGmChat = {
    attach(context) {
      once('characterCreationGmChat', '.character-creation-gm-chat, [data-character-gm-chat]', context).forEach((root) => {
        const history = root.querySelector('[data-gm-chat-history]');
        const input = root.querySelector('.character-creation-gm-chat__input');
        const sendButton = root.querySelector('[data-gm-chat-send]');
        const status = root.querySelector('[data-gm-chat-status]');
        let pending = false;
        let restored = false;
        const settings = () => drupalSettings.dungeoncrawlerCharacterGm;
        const endpoint = () => settings().endpointTemplate.replace('/0/gm', '/' + settings().characterId + '/gm');
        const setStatus = (message, error = false) => {
          status.textContent = message;
          status.classList.toggle('is-error', error);
        };
        const selectOption = (message) => {
          if (pending) return;
          input.value = message;
          send();
        };
        const readResponse = async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error?.message || payload.error || 'Character GM request failed.');
          return payload;
        };
        const showContext = (data) => {
          const summary = data.context_snapshot?.summary;
          if (summary) {
            Object.entries(summary).forEach(([key, value]) => {
              const target = root.querySelector('[data-gm-chat-summary-field="' + key + '"]');
              if (target) target.textContent = value === '' || value === null ? 'Not selected' : String(value);
            });
          }
          const validation = data.validation;
          const workspace = root.closest('[data-character-setup-root]');
          if (!validation || !workspace) return;
          workspace.querySelector('[data-character-validation-summary]').textContent =
            `— ${validation.completed_steps}/8 steps complete; ${validation.errors.length} errors, ${validation.warnings.length} choices needed`;
          const findings = workspace.querySelector('[data-character-validation-findings]');
          findings.replaceChildren();
          [...validation.errors, ...validation.warnings].forEach((issue) => {
            const paragraph = document.createElement('p');
            paragraph.textContent = `Step ${issue.step}: ${issue.message}`;
            findings.appendChild(paragraph);
          });
          if (validation.ready) findings.textContent = 'All choices validate. Use the final creation action when you are ready.';
        };
        const loadContext = async () => {
          if (!settings().characterId) {
            setStatus('Start a conversation or save the first step to create your draft.');
            return;
          }
          const payload = await readResponse(await fetch(endpoint(), { credentials: 'same-origin' }));
          if (!payload.data) throw new Error('Character GM context response is missing data.');
          showContext(payload.data);
          if (!restored) {
            restoreEditorGmConversation(history, payload.data.conversation || [], {
              onSelectOption: selectOption,
            });
            restored = true;
          }
          setStatus('Character draft inspected. The GM uses the same validation as the wizard.');
        };
        const ensureDraft = async () => {
          if (settings().characterId) return;
          const payload = await readResponse(await fetch(settings().createEndpoint, {
            method: 'POST',
            headers: { 'X-CSRF-Token': settings().csrfToken },
          }));
          if (!payload.draft?.character_id) throw new Error('Draft creation response is missing the character ID.');
          settings().characterId = payload.draft.character_id;
          if (Drupal.characterCreationWorkspace) {
            await Drupal.characterCreationWorkspace.refresh({ characterId: payload.draft.character_id, saved: true });
          }
        };
        const execute = async (intent) => {
          if (pending) throw new Error('Waiting for the character GM to finish the current request.');
          const maySave = intent.type === 'natural_language' || intent.tool_name === 'apply_character_draft_updates';
          pending = true;
          sendButton.disabled = true;
          root.setAttribute('aria-busy', 'true');
          setStatus('Waiting for the character GM. Valid requested edits will be saved automatically...');
          try {
            if (maySave) await Drupal.characterCreationWorkspace?.lock();
            await ensureDraft();
            const payload = await readResponse(await fetch(endpoint(), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': settings().csrfToken },
              body: JSON.stringify(buildEditorGmRequest({
                toolId: 'character_creation', draftId: String(settings().characterId), intent,
              })),
            }));
            const data = payload.data;
            if (!data?.tool_result) throw new Error('Character GM response is missing its tool result.');
            const result = data.tool_result;
            renderEditorGmToolCalls(history, data.tool_calls || []);
            if (result.intent === 'clarification') {
              renderEditorGmClarification(history, result, {
                onSelectOption: selectOption,
              });
            }
            else {
              appendEditorGmMessage(history, 'assistant', result.assistant_response || result.reasoning
                || (result.blocking_errors?.length ? result.blocking_errors.map((issue) => issue.message).join('\n')
                  : 'Draft inspection complete.'), result);
            }
            showContext(data);
            const saved = result.tool === 'apply_character_draft_updates' && !result.dry_run;
            if (saved) {
              if (Drupal.characterCreationWorkspace) {
                await Drupal.characterCreationWorkspace.refresh({ saved: true });
              }
              else {
                setStatus('Changes saved. Reload the structured form before editing it.');
                return;
              }
            }
            setStatus(saved ? 'Changes saved. You can edit them again at any time.' : 'GM response ready.');
          }
          finally {
            if (maySave) Drupal.characterCreationWorkspace?.unlock();
            pending = false;
            sendButton.disabled = false;
            root.removeAttribute('aria-busy');
          }
        };
        const send = async () => {
          const message = input.value.trim();
          if (!message || pending) return;
          appendEditorGmMessage(history, 'user', message);
          input.value = '';
          try {
            await execute({ type: 'natural_language', utterance: message, author_message: message });
          }
          catch (error) {
            if (!input.value) input.value = message;
            setStatus(error.message, true);
          }
        };
        sendButton.addEventListener('click', send);
        root.querySelectorAll('[data-character-gm-prompt]').forEach((button) => {
          button.addEventListener('click', () => { input.value = button.dataset.characterGmPrompt; input.focus(); });
        });
        input.addEventListener('keydown', (event) => {
          if (shouldSubmitEditorGmKey(event)) { event.preventDefault(); send(); }
        });
        root.closest('[data-character-setup-root]')?.addEventListener('character:refreshed', () => {
          loadContext().catch((error) => setStatus(error.message, true));
        });
        loadContext().catch((error) => setStatus(error.message, true));
      });
    },
  };
})(Drupal, once, drupalSettings);
