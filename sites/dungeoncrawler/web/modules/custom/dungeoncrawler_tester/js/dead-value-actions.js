(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.dungeoncrawlerDeadValueActions = {
    attach(context) {
      const settings = drupalSettings?.dungeoncrawlerTester || {};
      const endpoint = settings?.routes?.deadClose;
      if (!endpoint) {
        return;
      }

      once('dc-dead-close', '.dc-dead-close-btn', context).forEach((button) => {
        button.addEventListener('click', async () => {
          const prNumber = Number(button.getAttribute('data-pr-number') || '0');
          const issueNumber = Number(button.getAttribute('data-issue-number') || '0');
          if (!prNumber) {
            return;
          }

          button.disabled = true;
          const originalText = button.textContent;
          button.textContent = 'Closing...';

          try {
            const response = await fetch(endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': settings?.csrfToken || '',
              },
              body: JSON.stringify({
                pr_number: prNumber,
                issue_number: issueNumber,
              }),
            });

            const data = await response.json();
            if (!response.ok || !data?.success) {
              throw new Error(data?.message || 'Failed to close dead-value PR.');
            }

            const card = button.closest('.issue-report-item');
            if (card) {
              card.remove();
            }

            Drupal.announce(data.message || `Closed PR #${prNumber}.`);
          }
          catch (error) {
            button.disabled = false;
            button.textContent = originalText;
            Drupal.announce(error.message || 'Close action failed.');
          }
        });
      });

      const bulkEndpoint = settings?.routes?.bulkCloseQuery;
      if (!bulkEndpoint) {
        return;
      }

      once('dc-bulk-query-run', '.dc-bulk-query-run-btn', context).forEach((button) => {
        button.addEventListener('click', async () => {
          const queryId = button.getAttribute('data-query-id') || '';
          const queryTitle = button.getAttribute('data-query-title') || 'bulk query';
          if (!queryId) {
            return;
          }

          if (!window.confirm(`Run \"${queryTitle}\" now? This will close matching open issues/PRs.`)) {
            return;
          }

          button.disabled = true;
          const originalText = button.textContent;
          button.textContent = 'Running...';

          try {
            const response = await fetch(bulkEndpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': settings?.csrfToken || '',
              },
              body: JSON.stringify({
                query_id: queryId,
              }),
            });

            const data = await response.json();
            if (!response.ok || !data?.success) {
              throw new Error(data?.message || 'Bulk query run failed.');
            }

            Drupal.announce(data.message || 'Bulk query complete.');
            window.location.reload();
          }
          catch (error) {
            button.disabled = false;
            button.textContent = originalText;
            Drupal.announce(error.message || 'Bulk query run failed.');
          }
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
