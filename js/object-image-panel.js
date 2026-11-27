(function (Drupal, once) {
  'use strict';

  async function promote(panel) {
    const select = panel.querySelector('[data-object-image-version]');
    const option = select.selectedOptions?.[0];
    const imageUuid = option?.dataset?.imageUuid;
    if (!imageUuid || !panel.dataset.endpoint) return;
    const status = panel.querySelector('[data-object-image-status]');
    status.textContent = 'Making the selected image current…';
    try {
      const response = await fetch(panel.dataset.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': panel.dataset.csrfToken,
        },
        body: JSON.stringify({ action: 'promote', image_uuid: imageUuid }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload?.error?.message || payload?.error?.code || `promote_${response.status}`);
      }
      render(panel, payload.data || {});
      status.textContent = 'Selected image is now the newest current version.';
    } catch (error) {
      status.textContent = `Use as current failed: ${error.message}`;
    }
  }

  async function request(panel, generate) {
    const endpoint = panel.dataset.endpoint;
    if (!endpoint) {
      const status = panel.querySelector('[data-object-image-status]');
      if (status) status.textContent = 'Load or save an object before generating its image.';
      return;
    }
    const status = panel.querySelector('[data-object-image-status]');
    const button = panel.querySelector('[data-object-image-generate]');
    button.disabled = generate;
    if (generate) {
      button.textContent = 'Waiting on image provider…';
      status.textContent = 'Generating image…';
    }
    try {
      const response = await fetch(endpoint, {
        method: generate ? 'POST' : 'GET',
        credentials: 'same-origin',
        headers: generate
          ? {
              'Content-Type': 'application/json',
              'X-CSRF-Token': panel.dataset.csrfToken,
            }
          : undefined,
        body: generate
          ? JSON.stringify({
              prompt: String(panel.querySelector('[data-object-image-prompt]')?.value || '').trim(),
            })
          : undefined,
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload?.error?.message || payload?.error?.code || `image_${response.status}`);
      }
      render(panel, payload.data || {});
      status.textContent = generate ? 'Image generated and saved as a new version.' : 'Current image loaded.';
    } catch (error) {
      status.textContent = `Image generation failed: ${error.message}`;
    } finally {
      button.disabled = false;
      button.textContent = 'Generate / Regenerate';
    }
  }

  function render(panel, data) {
    const image = panel.querySelector('[data-object-image-preview]');
    const placeholder = panel.querySelector('[data-object-image-placeholder]');
    const select = panel.querySelector('[data-object-image-version]');
    const versions = Array.isArray(data.versions) ? data.versions.filter((item) => item?.url) : [];
    select.replaceChildren();
    for (const version of versions) {
      const option = document.createElement('option');
      option.value = String(version.image_id || version.image_uuid || version.version_number);
      option.dataset.url = String(version.url);
      option.dataset.imageUuid = String(version.image_uuid || '');
      option.textContent = `Version ${version.version_number}${version.is_primary ? ' (current)' : ''}`;
      select.appendChild(option);
    }
    select.hidden = versions.length < 2;
    const current = versions.find((version) => version.url === data.preview_url) || versions[0];
    if (current) {
      image.src = current.url;
      image.hidden = false;
      placeholder.hidden = true;
      select.value = String(current.image_id || current.image_uuid || current.version_number);
    } else {
      image.hidden = true;
      placeholder.hidden = false;
    }
  }

  Drupal.behaviors.dungeoncrawlerObjectImagePanel = {
    attach(context) {
      once('object-image-panel', '[data-object-image-panel]', context).forEach((panel) => {
        const select = panel.querySelector('[data-object-image-version]');
        select?.addEventListener('change', () => {
          const url = select.selectedOptions?.[0]?.dataset?.url;
          if (url) panel.querySelector('[data-object-image-preview]').src = url;
        });
        panel.querySelector('[data-object-image-generate]')?.addEventListener('click', (event) => {
          event.preventDefault();
          request(panel, true);
        });
        panel.querySelector('[data-object-image-promote]')?.addEventListener('click', (event) => {
          event.preventDefault();
          promote(panel);
        });
        request(panel, false);
      });
    },
  };
  Drupal.dungeoncrawlerObjectImagePanel = {
    refresh(panel) {
      return request(panel, false);
    },
  };
})(Drupal, once);
