/**
 * Owns campaign settings tab data load/render/update flows.
 */
export class GameShellCampaignSettingsCoordinator {
  constructor(shell) {
    this.shell = shell;
  }

  async loadCampaignSettings(force = false) {
    const shell = this.shell;
    const campaignId = Number(shell.launchContext?.campaign_id || 0);
    const statusEl = document.getElementById('campaign-settings-status');
    if (!campaignId) {
      if (statusEl) statusEl.textContent = 'Campaign settings are unavailable outside campaign mode.';
      return;
    }
    if (!force && shell._campaignSettingsLoaded && shell._campaignSettingsPayload) {
      this.renderCampaignSettings(shell._campaignSettingsPayload);
      return;
    }
    if (shell._campaignSettingsLoading) {
      return;
    }

    shell._campaignSettingsLoading = true;
    if (statusEl) statusEl.textContent = 'Loading campaign settings...';
    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings`, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to load settings: ${error}`;
        return;
      }
      shell._campaignSettingsPayload = payload;
      shell._campaignSettingsLoaded = true;
      this.renderCampaignSettings(payload);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to load settings: ${error?.message || 'network error'}`;
    } finally {
      shell._campaignSettingsLoading = false;
    }
  }

  renderCampaignSettings(payload) {
    const shell = this.shell;
    const statusEl = document.getElementById('campaign-settings-status');
    const titleEl = document.getElementById('campaign-settings-title');
    const memberListEl = document.getElementById('campaign-member-list');
    const playerBtn = document.getElementById('campaign-mode-player');
    const gmBtn = document.getElementById('campaign-mode-gm');
    if (!memberListEl || !playerBtn || !gmBtn) {
      return;
    }

    const campaignName = String(payload?.settings?.campaign_name || '').trim();
    if (titleEl && campaignName) {
      titleEl.textContent = `${campaignName} settings`;
    }

    const canManage = payload?.capabilities?.can_manage === true;
    const canUseGmMode = payload?.capabilities?.can_use_gm_mode === true;
    const canUsePlayerMode = payload?.capabilities?.can_use_player_mode !== false;
    const mode = String(payload?.settings?.mode || 'player').trim().toLowerCase() === 'gm' ? 'gm' : 'player';
    playerBtn.classList.toggle('btn-primary', mode === 'player');
    playerBtn.classList.toggle('btn-secondary', mode !== 'player');
    gmBtn.classList.toggle('btn-primary', mode === 'gm');
    gmBtn.classList.toggle('btn-secondary', mode !== 'gm');
    gmBtn.disabled = !canUseGmMode;
    playerBtn.disabled = !canUsePlayerMode;

    playerBtn.onclick = () => this.setCampaignMode('player');
    gmBtn.onclick = () => this.setCampaignMode('gm');

    shell.campaignAccess = this.normalizeCampaignAccess({
      ...(payload?.campaign_access || {}),
      campaign_id: Number(payload?.campaign_id || shell.campaignAccess?.campaign_id || 0),
      current_mode: mode,
      can_use_player_mode: canUsePlayerMode,
      can_use_gm_mode: canUseGmMode,
    });
    shell.activeCampaignMode = mode;
    this.applyCampaignModeGates();

    const members = Array.isArray(payload?.members) ? payload.members : [];
    memberListEl.innerHTML = '';
    if (!members.length) {
      memberListEl.innerHTML = '<p class="muted">No campaign members found.</p>';
    } else {
      members.forEach((member) => {
        const uid = Number(member?.uid || 0);
        if (!uid) return;
        const role = String(member?.role || 'player').trim().toLowerCase();
        const status = String(member?.status || 'active').trim().toLowerCase();
        const name = String(member?.display_name || `User ${uid}`).trim();
        const email = String(member?.email || '').trim();
        const row = document.createElement('div');
        row.className = 'campaign-settings-panel__member-row';

        const identity = document.createElement('div');
        const identityName = document.createElement('strong');
        identityName.textContent = name;
        const identityMeta = document.createElement('p');
        identityMeta.className = 'campaign-settings-panel__member-meta';
        identityMeta.textContent = email || `UID ${uid}`;
        identity.appendChild(identityName);
        identity.appendChild(identityMeta);

        const roleBadge = document.createElement('span');
        roleBadge.className = 'pill pill-muted';
        roleBadge.textContent = role === 'owner_gm' ? 'owner_gm' : role;

        const roleControl = document.createElement('select');
        roleControl.className = 'merchant-trade-panel__select';
        roleControl.innerHTML = `
          <option value="player"${role === 'player' ? ' selected' : ''}>player</option>
          <option value="gm"${role === 'gm' ? ' selected' : ''}>gm</option>
        `;
        roleControl.disabled = !canManage || role === 'owner_gm' || status === 'revoked';
        roleControl.onchange = () => this.updateCampaignMemberRole(uid, roleControl.value);

        row.appendChild(identity);
        row.appendChild(roleBadge);
        row.appendChild(roleControl);
        memberListEl.appendChild(row);
      });
    }

    if (statusEl) {
      statusEl.textContent = canManage
        ? 'You can manage campaign members and GM mode.'
        : 'You can switch your own mode; member management is GM-only.';
    }
  }

  async setCampaignMode(mode) {
    const shell = this.shell;
    const normalizedMode = String(mode || '').trim().toLowerCase();
    if (!['player', 'gm'].includes(normalizedMode)) {
      return;
    }
    const campaignId = Number(shell.launchContext?.campaign_id || 0);
    if (!campaignId) {
      return;
    }
    const statusEl = document.getElementById('campaign-settings-status');
    if (statusEl) statusEl.textContent = 'Saving mode preference...';

    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings/mode`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mode: normalizedMode }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to save mode: ${error}`;
        return;
      }
      shell.activeCampaignMode = normalizedMode;
      shell.campaignAccess = this.normalizeCampaignAccess({
        ...shell.campaignAccess,
        current_mode: normalizedMode,
      });
      this.applyCampaignModeGates();
      await this.loadCampaignSettings(true);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to save mode: ${error?.message || 'network error'}`;
    }
  }

  normalizeCampaignAccess(input = {}) {
    const shell = this.shell;
    const access = input && typeof input === 'object' ? input : {};
    const canUseGmMode = access.can_use_gm_mode === true;
    const canUsePlayerMode = access.can_use_player_mode !== false;
    const defaultMode = String(access.default_mode || (canUseGmMode ? 'gm' : 'player')).trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    let currentMode = String(access.current_mode || defaultMode).trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    if (currentMode === 'gm' && !canUseGmMode) {
      currentMode = 'player';
    }
    if (currentMode === 'player' && !canUsePlayerMode && canUseGmMode) {
      currentMode = 'gm';
    }
    return {
      campaign_id: Number(access.campaign_id || shell.launchContext?.campaign_id || 0) || 0,
      membership_role: String(access.membership_role || '').trim().toLowerCase() || 'player',
      membership_status: String(access.membership_status || '').trim().toLowerCase() || 'active',
      can_use_player_mode: canUsePlayerMode,
      can_use_gm_mode: canUseGmMode,
      default_mode: defaultMode,
      current_mode: currentMode,
      playable_principals: Array.isArray(access.playable_principals) ? access.playable_principals : [],
      gm_principals: Array.isArray(access.gm_principals) ? access.gm_principals : [],
    };
  }

  applyCampaignModeGates() {
    const shell = this.shell;
    const mode = String(shell.activeCampaignMode || shell.campaignAccess?.current_mode || 'player').trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    const canUseGmMode = shell.campaignAccess?.can_use_gm_mode === true;
    const effectiveMode = (mode === 'gm' && canUseGmMode) ? 'gm' : 'player';
    shell.activeCampaignMode = effectiveMode;
    shell.campaignAccess = this.normalizeCampaignAccess({
      ...shell.campaignAccess,
      current_mode: effectiveMode,
    });

    const shellContainer = shell.container?.closest?.('[data-game-shell]')
      || shell.container?.querySelector?.('[data-game-shell]')
      || null;
    if (shellContainer) {
      shellContainer.dataset.campaignMode = effectiveMode;
      shellContainer.dataset.canUseGmMode = canUseGmMode ? '1' : '0';
    }

    const gmSessionTab = shell.container?.querySelector?.('.session-view-tab[data-view="gm-private"]') || null;
    const gmViewEnabled = canUseGmMode && effectiveMode === 'gm';
    if (gmSessionTab) {
      gmSessionTab.hidden = !gmViewEnabled;
      gmSessionTab.setAttribute('aria-hidden', gmViewEnabled ? 'false' : 'true');
      gmSessionTab.tabIndex = gmViewEnabled ? 0 : -1;
    }
    if (!gmViewEnabled && shell.panels?.chat?.activeSessionView === 'gm-private') {
      shell.panels.chat.switchSessionView('room');
    }
  }

  async updateCampaignMemberRole(memberUid, role) {
    const shell = this.shell;
    const campaignId = Number(shell.launchContext?.campaign_id || 0);
    const uid = Number(memberUid || 0);
    const normalizedRole = String(role || '').trim().toLowerCase();
    if (!campaignId || !uid || !['player', 'gm'].includes(normalizedRole)) {
      return;
    }
    const statusEl = document.getElementById('campaign-settings-status');
    if (statusEl) statusEl.textContent = 'Saving member role...';

    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings/members/${uid}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ role: normalizedRole, status: 'active' }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to update member: ${error}`;
        return;
      }
      await this.loadCampaignSettings(true);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to update member: ${error?.message || 'network error'}`;
    }
  }
}
