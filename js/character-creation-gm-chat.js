import { shouldSubmitEditorGmKey } from './v2/editor/EditorGmChatClient.js?v=20261024-tool-calls-1';

(function (Drupal, once, drupalSettings) {
  'use strict';

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderMessages(historyContainer, messages) {
    if (!messages.length) {
      historyContainer.innerHTML = '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--assistant">' +
        '<div class="character-creation-gm-chat__message-role">GM</div>' +
        '<div class="character-creation-gm-chat__message-body">Welcome to Character Creation. Building a complete character is hard; describe the character you want, ask for recommendations, or ask me to update the draft while you remain in control of every choice.</div>' +
      '</div>';
      return;
    }

    historyContainer.innerHTML = messages.map((entry) => {
      if (entry.role === 'tool') {
        return '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--tool">' +
          '<div class="character-creation-gm-chat__message-body">' + escapeHtml(entry.content || '') + '</div>' +
        '</div>';
      }
      const role = entry.role === 'user' ? 'user' : 'assistant';
      const label = role === 'user' ? 'You' : 'GM';
      const toolCalls = Array.isArray(entry.tool_calls) ? entry.tool_calls : [];
      const toolHtml = toolCalls.map((call) => {
        const text = typeof call === 'string' ? call : (call?.message || '');
        if (!text) return '';
        return '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--tool">' +
          '<div class="character-creation-gm-chat__message-body">' + escapeHtml(text) + '</div>' +
        '</div>';
      }).join('');
      const body = escapeHtml(entry.content || '').replace(/\n/g, '<br>');
      return toolHtml + '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--' + role + '">' +
        '<div class="character-creation-gm-chat__message-role">' + label + '</div>' +
        '<div class="character-creation-gm-chat__message-body">' + body + '</div>' +
      '</div>';
    }).join('');
    historyContainer.scrollTop = historyContainer.scrollHeight;
  }

  function updateSummary(summary, chatRoot) {
    if (!summary || typeof summary !== 'object') {
      return;
    }

    Object.keys(summary).forEach((key) => {
      const target = chatRoot.querySelector('[data-gm-chat-summary-field="' + key + '"]');
      if (!target) {
        return;
      }

      const value = summary[key];
      target.textContent = value === null || value === undefined || value === '' ? 'Not selected' : String(value);
    });
  }

  Drupal.behaviors.characterCreationGmChat = {
    attach(context) {
      const settings = drupalSettings.dungeoncrawlerCharacterGm;
      if (!settings) {
        return;
      }

      once('characterCreationGmChat', '.character-creation-gm-chat', context).forEach((chatRoot) => {
        const historyContainer = chatRoot.querySelector('[data-gm-chat-history]');
        const statusEl = chatRoot.querySelector('[data-gm-chat-status]');
        const sendButton = chatRoot.querySelector('[data-gm-chat-send]');
        const input = chatRoot.querySelector('.character-creation-gm-chat__input');
        let messages = Array.isArray(settings.history) ? settings.history.slice() : [];
        let pending = false;

        renderMessages(historyContainer, messages);
        updateSummary(settings.summary || {}, chatRoot);

        const setStatus = (message, isError = false) => {
          statusEl.textContent = message || '';
          statusEl.classList.toggle('is-error', Boolean(isError));
        };

        const send = async () => {
          const message = input.value.trim();
          if (!message || pending) {
            return;
          }

          pending = true;
          sendButton.disabled = true;
          setStatus('GM is updating your draft...');
          messages.push({ role: 'user', content: message });
          messages.push({ role: 'tool', content: 'Now looking up character creation options and draft state…' });
          renderMessages(historyContainer, messages);
          input.value = '';

          try {
            const response = await fetch(settings.endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': settings.csrfToken,
              },
              body: JSON.stringify({
                character_id: settings.characterId,
                campaign_id: settings.campaignId,
                step: settings.step,
                message,
              }),
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
              throw new Error(payload.error || 'GM chat request failed.');
            }

            messages = Array.isArray(payload.history) ? payload.history.slice() : messages.concat([{ role: 'assistant', content: payload.reply || 'Draft updated.' }]);
            renderMessages(historyContainer, messages);
            updateSummary(payload.summary || {}, chatRoot);
            if (payload.character_id) {
              settings.characterId = payload.character_id;
            }
            if (payload.step) {
              settings.step = payload.step;
            }

            const appliedKeys = Object.keys(payload.applied_updates || {});
            const shouldReload = Boolean(payload.reload_required && payload.reload_url);
            setStatus(
              appliedKeys.length
                ? 'Updated: ' + appliedKeys.join(', ')
                : (shouldReload ? 'Advice ready. Refreshing your step...' : 'Advice ready.')
            );

            if (shouldReload) {
              window.setTimeout(() => {
                if (settings.shellMode === 'character_setup') {
                  window.dispatchEvent(new CustomEvent('dungeoncrawler:character-setup-gm-update', {
                    detail: payload,
                  }));
                  return;
                }

                window.location.assign(payload.reload_url);
              }, 900);
            }
          }
          catch (error) {
            messages.push({ role: 'assistant', content: error.message || 'The GM could not update the draft.' });
            renderMessages(historyContainer, messages);
            setStatus(error.message || 'The GM could not update the draft.', true);
          }
          finally {
            pending = false;
            sendButton.disabled = false;
          }
        };

        sendButton.addEventListener('click', send);
        input.addEventListener('keydown', (event) => {
          if (shouldSubmitEditorGmKey(event)) {
            event.preventDefault();
            send();
          }
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
