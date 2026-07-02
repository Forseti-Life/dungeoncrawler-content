(function (Drupal, drupalSettings, once) {
  'use strict';

  const settings = drupalSettings.dungeoncrawlerTester || {};
  const endpoints = settings.routes || {};
  const token = settings.csrfToken || '';
  let countdownTimer = null;
  const refreshIntervalMs = 5000;
  let countdownSeconds = refreshIntervalMs / 1000;
  let currentState = 'idle';

  Drupal.behaviors.dungeoncrawlerQueueManagement = {
    attach: function (context) {
      once('dungeoncrawlerQueueManagement', '.dc-queue-page', context).forEach((page) => {
        const runBtn = page.querySelector('.btn-run-all');
        const refreshBtn = page.querySelector('.btn-refresh');
        const refreshLogsBtn = page.querySelector('.btn-refresh-logs');
        const autoToggle = page.querySelector('#dc-auto-refresh');

        if (runBtn) {
          runBtn.addEventListener('click', () => runQueue());
        }
        if (refreshBtn) {
          refreshBtn.addEventListener('click', () => refreshStatus());
        }
        if (refreshLogsBtn) {
          refreshLogsBtn.addEventListener('click', () => refreshLogs());
        }

        page.addEventListener('click', (event) => {
          const target = event.target.closest('[data-action="delete-item"], [data-action="rerun-item"]');
          if (!target) {
            return;
          }

          const action = target.getAttribute('data-action');
          const itemId = Number(target.getAttribute('data-item-id') || 0);
          if (!itemId) {
            showMessage('Queue item id is missing.', 'error');
            return;
          }

          if (action === 'delete-item') {
            queueItemAction('delete', itemId, target);
            return;
          }

          if (action === 'rerun-item') {
            queueItemAction('rerun', itemId, target);
          }
        });

        if (autoToggle) {
          autoToggle.addEventListener('change', () => {
            if (autoToggle.checked) {
              startAutoRefresh();
            } else {
              stopAutoRefresh();
            }
          });
        }

        refreshStatus();
        refreshLogs();
        startAutoRefresh();
      });
    }
  };

  function runQueue() {
    if (!endpoints.run) {
      showMessage('Queue run endpoint is not configured.', 'error');
      return;
    }

    setStatus('running');
    fetch(endpoints.run, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ limit: 5 }),
    })
      .then(r => r.json())
      .then(data => {
        setStatus('idle');
        showMessage(data.message || 'Queue processed', data.success ? 'success' : 'error');
        refreshStatus();
        refreshLogs();
      })
      .catch(err => {
        setStatus('idle');
        showMessage('Error running queue: ' + err.message, 'error');
      });
  }

  function queueItemAction(action, itemId, buttonEl) {
    const endpoint = action === 'delete' ? endpoints.delete : endpoints.rerun;
    if (!endpoint) {
      showMessage('Queue action endpoint is not configured.', 'error');
      return;
    }

    if (action === 'delete' && !window.confirm(`Delete queue item #${itemId}?`)) {
      return;
    }

    if (buttonEl) {
      buttonEl.disabled = true;
    }

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ item_id: itemId }),
    })
      .then(r => r.json())
      .then(data => {
        showMessage(data.message || 'Queue action completed.', data.success ? 'success' : 'error');
        if (data.success) {
          window.setTimeout(() => window.location.reload(), 300);
          return;
        }
        if (data.stale) {
          window.setTimeout(() => window.location.reload(), 300);
        }
      })
      .catch(err => {
        showMessage('Error processing queue item: ' + err.message, 'error');
      })
      .finally(() => {
        if (buttonEl) {
          buttonEl.disabled = false;
        }
      });
  }

  function refreshStatus() {
    if (!endpoints.status) {
      showMessage('Queue status endpoint is not configured.', 'error');
      return;
    }

    setStatusText('Refreshing…', true);
    fetch(endpoints.status)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showMessage(data.message || 'Failed to load status', 'error');
          return;
        }
        const body = document.querySelector('#dc-queue-status-body');
        let total = 0;
        if (body && data.queues) {
          body.querySelectorAll('tr').forEach(row => {
            const id = row.getAttribute('data-queue-id');
            const q = data.queues[id];
            if (!q) return;
            const badge = row.querySelector('[data-count]');
            if (badge) {
              badge.textContent = q.items;
            }
            total += q.items;
          });
        }
        const totalEl = document.querySelector('[data-total-count]');
        if (totalEl) {
          totalEl.textContent = total;
        }
        setStatus(total > 0 ? 'pending' : 'idle');
        markUpdated('status');
        resetCountdown();
      })
      .catch(err => {
        showMessage('Error refreshing status: ' + err.message, 'error');
      })
      .finally(() => {
        setStatus(currentState);
      });
  }

  function refreshLogs() {
    if (!endpoints.logs) {
      showMessage('Queue logs endpoint is not configured.', 'error');
      return;
    }

    setStatusText('Refreshing…', true);
    fetch(endpoints.logs)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showMessage(data.message || 'Failed to load logs', 'error');
          return;
        }
        const container = document.querySelector('#dc-log-entries');
        if (!container) return;
        container.innerHTML = '';
        (data.logs || []).forEach(entry => {
          const div = document.createElement('div');
          div.className = 'log-entry';
          const ts = new Date(entry.timestamp * 1000).toLocaleString();
          div.textContent = `[${ts}] ${entry.message}`;
          container.appendChild(div);
        });
        if (!data.logs || data.logs.length === 0) {
          container.innerHTML = '<div class="log-entry">No recent activity.</div>';
        }
        markUpdated('logs');
        resetCountdown();
      })
      .catch(err => {
        showMessage('Error refreshing logs: ' + err.message, 'error');
      })
      .finally(() => {
        setStatus(currentState);
      });
  }

  function runAutoRefresh() {
    refreshStatus();
    refreshLogs();
  }

  function setStatus(state) {
    const pill = document.querySelector('.status-pill');
    const text = pill ? pill.querySelector('[data-status-text]') : null;
    if (!pill || !text) return;
    pill.classList.remove('running', 'idle', 'pending');
    pill.classList.add(state);
    currentState = state;
    if (state === 'running') {
      text.textContent = 'Running';
    } else if (state === 'pending') {
      text.textContent = 'Pending';
    } else {
      text.textContent = 'Idle';
    }
  }

  function setStatusText(text, refreshing = false) {
    const pill = document.querySelector('.status-pill');
    const t = pill ? pill.querySelector('[data-status-text]') : null;
    if (!pill || !t) return;
    pill.classList.toggle('refreshing', refreshing);
    t.textContent = text;
  }

  function showMessage(message, type) {
    const existing = document.querySelector('.dc-queue-message');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = `dc-queue-message dc-queue-message-${type}`;
    div.textContent = message;
    const page = document.querySelector('.dc-queue-page');
    if (page) {
      page.insertBefore(div, page.firstChild);
      setTimeout(() => {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.3s';
        setTimeout(() => div.remove(), 300);
      }, 4000);
    }
  }

  function startAutoRefresh() {
    stopAutoRefresh();
    countdownSeconds = refreshIntervalMs / 1000;
    updateCountdown(countdownSeconds);
    countdownTimer = setInterval(() => {
      countdownSeconds -= 1;
      if (countdownSeconds <= 0) {
        runAutoRefresh();
        countdownSeconds = refreshIntervalMs / 1000;
      }
      updateCountdown(countdownSeconds);
    }, 1000);
  }

  function stopAutoRefresh() {
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
    updateCountdown(null);
  }

  function updateCountdown(seconds) {
    const el = document.querySelector('#dc-auto-refresh-countdown');
    if (!el) return;
    if (seconds === null) {
      el.textContent = '';
      return;
    }
    el.textContent = `Next refresh in ${seconds}s`;
  }

  function markUpdated(kind) {
    const id = kind === 'logs' ? '#dc-logs-updated' : '#dc-status-updated';
    const el = document.querySelector(id);
    if (el) {
      el.textContent = new Date().toLocaleTimeString();
    }
    const inline = document.querySelector('#dc-last-refresh-inline');
    if (inline && kind === 'status') {
      inline.textContent = new Date().toLocaleTimeString();
    }
  }

  function resetCountdown() {
    countdownSeconds = refreshIntervalMs / 1000;
    updateCountdown(countdownSeconds);
  }

})(Drupal, drupalSettings, once);
