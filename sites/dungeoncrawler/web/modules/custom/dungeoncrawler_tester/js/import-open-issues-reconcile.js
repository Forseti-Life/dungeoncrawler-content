(function (Drupal, drupalSettings, once) {
  'use strict';

  const AUTO_REFRESH_MS = 2000;
  const settings = drupalSettings.dungeoncrawlerTesterReconcile || {};
  const endpoints = settings.routes || {};
  const csrfToken = settings.csrfToken || '';

  Drupal.behaviors.dungeoncrawlerImportOpenIssuesReconcile = {
    attach: function (context) {
      once('dungeoncrawlerImportOpenIssuesReconcile', '.dc-reconcile-card', context).forEach((card) => {
        initializeCard(card);
      });
    }
  };

  function initializeCard(card) {
    const state = {
      card,
      pollTimer: null,
      countdownTimer: null,
      nextRefreshAt: 0,
      filter: 'all',
    };

    const elements = {
      startBtn: card.querySelector('.dc-reconcile-start-btn'),
      refreshBtn: card.querySelector('.dc-reconcile-refresh-btn'),
      refreshLogsBtn: card.querySelector('.dc-reconcile-refresh-logs-btn'),
      filterSelect: card.querySelector('#dc-reconcile-log-filter'),
      autoRefreshCheckbox: card.querySelector('#dc-reconcile-auto-refresh'),
      countdown: card.querySelector('#dc-reconcile-auto-refresh-countdown'),
      statusText: card.querySelector('[data-status-text]'),
      statusPill: card.querySelector('.status-pill'),
      statusSummary: card.querySelector('#dc-reconcile-status'),
      totalCount: card.querySelector('[data-total-count]'),
      statusUpdated: card.querySelector('#dc-reconcile-status-updated'),
      logsUpdated: card.querySelector('#dc-reconcile-logs-updated'),
      lastRefreshInline: card.querySelector('#dc-reconcile-last-refresh-inline'),
      logEntries: card.querySelector('#dc-reconcile-log-entries'),
      message: card.querySelector('#dc-reconcile-message'),
    };

    if (elements.filterSelect) {
      state.filter = elements.filterSelect.value || 'deleted';
      elements.filterSelect.addEventListener('change', () => {
        state.filter = elements.filterSelect.value || 'all';
        refreshLogs(state, elements);
      });
    }

    if (elements.startBtn) {
      elements.startBtn.addEventListener('click', () => {
        startReconcile(state, elements);
      });
    }

    if (elements.refreshBtn) {
      elements.refreshBtn.addEventListener('click', () => {
        refreshAll(state, elements, true);
      });
    }

    if (elements.refreshLogsBtn) {
      elements.refreshLogsBtn.addEventListener('click', () => {
        refreshLogs(state, elements, true);
      });
    }

    if (elements.autoRefreshCheckbox) {
      elements.autoRefreshCheckbox.addEventListener('change', () => {
        if (elements.autoRefreshCheckbox.checked) {
          startPolling(state, elements);
        } else {
          stopPolling(state, elements);
        }
      });
    }

    refreshAll(state, elements, true);
    startPolling(state, elements);
  }

  function startReconcile(state, elements) {
    const repoInput = document.querySelector('input[name="repo"]');
    const repo = repoInput ? (repoInput.value || '').trim() : '';
    const startBtn = elements.startBtn;

    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = 'Starting...';
    }

    fetch(endpoints.start, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ repo: repo }),
    })
      .then(readJsonResponse)
      .then((data) => {
        if (!data.success) {
          showMessage(elements, data.message || 'Unable to start reconcile.', 'error');
          return;
        }

        showMessage(elements, data.message || 'Reconcile started.', 'success');
        refreshAll(state, elements, true);
        startPolling(state, elements);
      })
      .catch((error) => {
        showMessage(elements, `Error starting reconcile (${error.message}).`, 'error');
      })
      .finally(() => {
        if (startBtn) {
          startBtn.disabled = false;
          startBtn.textContent = '▶️ Run reconcile';
        }
      });
  }

  function startPolling(state, elements) {
    if (elements.autoRefreshCheckbox && !elements.autoRefreshCheckbox.checked) {
      stopPolling(state, elements);
      return;
    }

    stopPolling(state, elements);
    state.nextRefreshAt = Date.now() + AUTO_REFRESH_MS;
    updateCountdown(state, elements);

    state.pollTimer = window.setInterval(() => {
      refreshAll(state, elements);
      state.nextRefreshAt = Date.now() + AUTO_REFRESH_MS;
      updateCountdown(state, elements);
    }, AUTO_REFRESH_MS);

    state.countdownTimer = window.setInterval(() => {
      updateCountdown(state, elements);
    }, 250);
  }

  function stopPolling(state, elements) {
    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }

    if (state.countdownTimer) {
      clearInterval(state.countdownTimer);
      state.countdownTimer = null;
    }

    if (elements.countdown) {
      elements.countdown.textContent = 'paused';
    }
  }

  function updateCountdown(state, elements) {
    if (!elements.countdown) {
      return;
    }

    if (elements.autoRefreshCheckbox && !elements.autoRefreshCheckbox.checked) {
      elements.countdown.textContent = 'paused';
      return;
    }

    const remainingMs = Math.max(state.nextRefreshAt - Date.now(), 0);
    const seconds = Math.ceil(remainingMs / 1000);
    elements.countdown.textContent = `${seconds}s`;
  }

  function refreshAll(state, elements, forcePillRefresh) {
    const setRefreshing = forcePillRefresh === true;
    if (setRefreshing) {
      setPill(elements, 'Refreshing', 'refreshing');
    }
    tickRun(state)
      .finally(() => {
        refreshStatus(elements);
        refreshLogs(state, elements);
      });
  }

  function tickRun(state) {
    if (!endpoints.tick) {
      return Promise.resolve();
    }

    return fetch(endpoints.tick, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ limit: 1 }),
    })
      .then(readJsonResponse)
      .then((data) => {
        if (!data.success || !data.status || data.status.running) {
          return;
        }
      })
      .catch(() => {
      });
  }

  function refreshStatus(elements) {
    if (!endpoints.status) {
      return;
    }

    fetch(endpoints.status, {
      headers: {
        'Accept': 'application/json',
      },
    })
      .then(readJsonResponse)
      .then((data) => {
        if (!data.success) {
          setSummary(elements, `Status: ${data.message || 'Unable to load reconcile status.'}`);
          setPill(elements, 'Error', 'pending');
          return;
        }

        const status = data.status || {};
        const stateText = status.running ? 'Running' : 'Idle';
        const pending = Number(status.pending_count || 0);
        const deleted = Number(status.deleted_count || 0);
        const failed = Number(status.failed_count || 0);

        setSummary(elements, `Status: ${stateText} | Pending: ${pending} | Deleted: ${deleted} | Failed: ${failed}`);
        setPill(elements, getPillText(stateText, pending, failed), getPillClass(status.running, pending, failed));
        if (elements.totalCount) {
          elements.totalCount.textContent = String(pending);
        }
        if (elements.statusUpdated) {
          elements.statusUpdated.textContent = new Date().toLocaleTimeString();
        }
        if (elements.lastRefreshInline) {
          elements.lastRefreshInline.textContent = `updated ${new Date().toLocaleTimeString()}`;
        }
        if (elements.startBtn) {
          elements.startBtn.disabled = Boolean(status.running);
          elements.startBtn.textContent = status.running ? '⏳ Reconcile running...' : '▶️ Run reconcile';
        }
      })
      .catch((error) => {
        setSummary(elements, `Status: Error loading status (${error.message}).`);
        setPill(elements, 'Error', 'pending');
      });
  }

  function refreshLogs(state, elements, showInfoMessage) {
    if (!endpoints.logs) {
      return;
    }

    const url = new URL(endpoints.logs, window.location.origin);
    url.searchParams.set('contains', state.filter || 'all');

    fetch(url.toString(), {
      headers: {
        'Accept': 'application/json',
      },
    })
      .then(readJsonResponse)
      .then((data) => {
        if (!data.success) {
          renderLogs(elements, [{ timestamp: 0, message: data.message || 'Unable to load reconcile logs.' }]);
          return;
        }

        renderLogs(elements, data.logs || []);
        if (elements.logsUpdated) {
          elements.logsUpdated.textContent = new Date().toLocaleTimeString();
        }
        if (showInfoMessage) {
          showMessage(elements, 'Reconcile logs refreshed.', 'info');
        }
      })
      .catch((error) => {
        renderLogs(elements, [{ timestamp: 0, message: `Error loading logs (${error.message}).` }]);
      });
  }

  function setSummary(elements, text) {
    if (elements.statusSummary) {
      elements.statusSummary.textContent = text;
    }
  }

  function renderLogs(elements, logs) {
    if (!elements.logEntries) {
      return;
    }

    elements.logEntries.innerHTML = '';
    if (!logs.length) {
      const empty = document.createElement('div');
      empty.className = 'log-entry';
      empty.textContent = 'No reconcile log entries yet.';
      elements.logEntries.appendChild(empty);
      return;
    }

    logs.forEach((entry) => {
      const div = document.createElement('div');
      div.className = 'log-entry';
      const ts = entry.timestamp ? new Date(entry.timestamp * 1000).toLocaleString() : 'n/a';
      div.textContent = `[${ts}] ${entry.message || ''}`;
      elements.logEntries.appendChild(div);
    });
  }

  function setPill(elements, text, statusClass) {
    if (elements.statusText) {
      elements.statusText.textContent = text;
    }

    if (elements.statusPill) {
      elements.statusPill.classList.remove('idle', 'refreshing', 'running', 'pending');
      elements.statusPill.classList.add(statusClass || 'idle');
    }
  }

  function getPillClass(running, pending, failed) {
    if (running) {
      return 'running';
    }
    if (failed > 0 || pending > 0) {
      return 'pending';
    }
    return 'idle';
  }

  function getPillText(stateText, pending, failed) {
    if (stateText === 'Running') {
      return 'Running';
    }
    if (failed > 0) {
      return 'Completed with warnings';
    }
    if (pending > 0) {
      return 'Pending';
    }
    return 'Idle';
  }

  function showMessage(elements, message, type) {
    if (!elements.message) {
      return;
    }

    elements.message.hidden = false;
    elements.message.classList.remove('dc-queue-message-success', 'dc-queue-message-error', 'dc-queue-message-info');
    elements.message.classList.add(`dc-queue-message-${type || 'info'}`);
    elements.message.textContent = message;
  }

  function readJsonResponse(response) {
    return response.text().then((text) => {
      let payload;
      try {
        payload = text ? JSON.parse(text) : {};
      } catch (error) {
        const trimmed = (text || '').trim();
        const preview = trimmed.slice(0, 120).replace(/\s+/g, ' ');
        throw new Error(`Unexpected response (${response.status}): ${preview || 'empty response'}`);
      }

      if (!response.ok) {
        const message = payload && payload.message ? payload.message : `Request failed (${response.status})`;
        throw new Error(message);
      }

      return payload || {};
    });
  }

})(Drupal, drupalSettings, once);
