(() => {
  'use strict';

  const roots = document.querySelectorAll('[data-site-guide]');
  roots.forEach((root) => {
    const toggle = root.querySelector('[data-site-guide-toggle]');
    const close = root.querySelector('[data-site-guide-close]');
    const panel = root.querySelector('[data-site-guide-panel]');
    const expand = root.querySelector('[data-site-guide-expand]');
    const transcript = root.querySelector('[data-site-guide-transcript]');
    const input = root.querySelector('[data-site-guide-input]');
    const form = root.querySelector('[data-site-guide-form]');
    const quickActions = root.querySelector('[data-site-guide-quick-actions]');
    const send = root.querySelector('[data-site-guide-send]');
    let context = null;
    let lastActiveElement = null;

    const append = (kind, text) => {
      const item = document.createElement('p');
      item.className = `site-guide__message site-guide__message--${kind}`;
      item.textContent = text;
      transcript.appendChild(item);
      transcript.scrollTop = transcript.scrollHeight;
    };

    const open = (value) => {
      if (value) lastActiveElement = document.activeElement;
      panel.hidden = !value;
      toggle.setAttribute('aria-expanded', value ? 'true' : 'false');
      if (value) input.focus();
      if (!value && lastActiveElement instanceof HTMLElement) lastActiveElement.focus();
    };

    toggle.addEventListener('click', () => open(panel.hidden));
    close.addEventListener('click', () => open(false));
    expand.addEventListener('click', () => {
      const expanded = root.classList.toggle('site-guide--expanded');
      expand.setAttribute('aria-pressed', expanded ? 'true' : 'false');
      expand.textContent = expanded ? Drupal.t('Dock') : Drupal.t('Expand');
    });
    root.addEventListener('keydown', (event) => {
      if (panel.hidden) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        open(false);
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = [...panel.querySelectorAll(
        'button:not([disabled]), textarea:not([disabled]), a[href]',
      )].filter((element) => !element.hidden);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    fetch('/api/site-guide', { credentials: 'same-origin' })
      .then((response) => response.json())
      .then((payload) => {
        context = payload.data || {};
        const personas = context.personas || [];
        if (personas.length) {
          root.querySelector('[data-site-guide-personas]').textContent =
            Drupal.t('Guidance for: @personas', { '@personas': personas.join(', ').replaceAll('_', ' ') });
        }
        const actions = personas.includes('creator') ? [
          'Which editor owns this object?',
          'Show me the agent architecture.',
        ] : [
          'How do I get started?',
          'Where are my campaigns?',
          'What can I do on this site?',
        ];
        quickActions.replaceChildren(...actions.map((label) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn btn-sm btn-outline-light';
          button.textContent = label;
          button.addEventListener('click', () => {
            input.value = label;
            form.requestSubmit();
          });
          return button;
        }));
      })
      .catch(() => {
        append('system', Drupal.t('The Site Guide is unavailable right now.'));
      });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      append('user', message);
      input.value = '';
      send.disabled = true;
      send.textContent = Drupal.t('Waiting on Site Guide…');
      fetch('/api/site-guide', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, route: window.location.pathname + window.location.search }),
      })
        .then(async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload?.error?.message || Drupal.t('The guide request failed.'));
          return payload.data;
        })
        .then((data) => {
          append('guide', data.answer);
          (data.links || []).forEach((link) => {
            const anchor = document.createElement('a');
            anchor.href = link.url;
            anchor.textContent = `Take me to: ${link.title}`;
            anchor.className = 'site-guide__link';
            transcript.appendChild(anchor);
          });
          if (data.handoff) {
            const handoff = document.createElement('aside');
            handoff.className = 'site-guide__handoff';
            const heading = document.createElement('strong');
            heading.textContent = Drupal.t('Owning agent: @agent', {
              '@agent': data.handoff.label || data.handoff.agent_id,
            });
            const purpose = document.createElement('p');
            purpose.textContent = data.handoff.purpose || Drupal.t('Open the owning agent when you are ready.');
            handoff.append(heading, purpose);
            transcript.appendChild(handoff);
          }
          transcript.scrollTop = transcript.scrollHeight;
        })
        .catch((error) => append('system', error.message))
        .finally(() => {
          send.disabled = false;
          send.textContent = Drupal.t('Ask');
          input.focus();
        });
    });
  });
})();
