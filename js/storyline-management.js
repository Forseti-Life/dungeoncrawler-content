((Drupal, once) => {
  let csrfTokenPromise;

  const csrfToken = async () => {
    csrfTokenPromise ||= fetch('/session/token', { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`token_${response.status}`);
        }
        return response.text();
      })
      .then((token) => token.trim());
    return csrfTokenPromise;
  };

  Drupal.behaviors.storylineManagement = {
    attach(context) {
      once('storyline-draft-delete', '[data-storyline-draft-delete]', context).forEach((button) => {
        button.addEventListener('click', async () => {
          const title = button.dataset.draftTitle || 'this draft';
          if (!window.confirm(`Permanently delete ${title} and all of its saved versions? Published canonical storylines are not deleted.`)) {
            return;
          }
          const status = document.querySelector('[data-storyline-draft-delete-status]');
          button.disabled = true;
          if (status) {
            status.textContent = `Deleting ${title}...`;
          }
          try {
            const response = await fetch(button.dataset.deleteUrl || '', {
              method: 'DELETE',
              credentials: 'same-origin',
              headers: {
                'X-CSRF-Token': await csrfToken(),
              },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `delete_${response.status}`);
            }
            button.closest('[data-storyline-draft-row]')?.remove();
            window.location.reload();
          } catch (error) {
            button.disabled = false;
            if (status) {
              status.textContent = `Draft deletion failed: ${error.message}`;
            }
          }
        });
      });
    },
  };
})(Drupal, once);
